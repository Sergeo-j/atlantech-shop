<?php
/**
 * Récupération de mot de passe — AtlanTech
 * Envoie un lien de réinitialisation par Email (PHPMailer) ou WhatsApp (wa.me)
 */

require_once 'config/config.php';
require_once 'config/mailer.php';

// Rediriger si déjà connecté
if (isLoggedIn()) redirect('index.php');

$error   = '';
$success = '';
$wa_link = '';   // lien wa.me généré si canal = whatsapp
$wa_phone = '';  // numéro affiché dans le message de succès

// ─── Données communes (nav + footer) ───────────────────────────────────
try {
    $r = $mysqli->query("SELECT id, name FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY id ASC LIMIT 7");
    $rootCategories = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) { $rootCategories = []; }

$cart_count = 0;

/**
 * Normalise un numéro de téléphone (ne garde que les chiffres).
 * Si moins de 10 chiffres → ajoute l'indicatif Haïti (509) par défaut.
 */
function normalizePhone(string $raw): string {
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '') return '';
    // Si le numéro commence par 0 → on enlève le 0 initial (format local)
    if (strlen($digits) > 8 && $digits[0] === '0') {
        $digits = ltrim($digits, '0');
    }
    // Si moins de 10 chiffres, c'est un numéro local → ajoute 509 (Haïti)
    if (strlen($digits) <= 8) {
        $digits = '509' . $digits;
    }
    return $digits;
}

// ─── Traitement du formulaire ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot'])) {

    $identifier = trim($_POST['identifier'] ?? '');
    $channel    = ($_POST['channel'] ?? 'email') === 'whatsapp' ? 'whatsapp' : 'email';

    if (empty($identifier)) {
        $error = "Veuillez entrer votre adresse e-mail ou votre numéro de téléphone.";
    } else {
        // L'identifiant peut être un email ou un numéro de téléphone
        $is_email  = isValidEmail($identifier);
        $user      = null;

        if ($is_email) {
            $stmt = $mysqli->prepare("SELECT id, name, email, phone FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->bind_param('s', $identifier);
        } else {
            // Recherche par téléphone (comparaison sur chiffres uniquement)
            $phone_digits = normalizePhone($identifier);
            // On cherche une correspondance sur les derniers 8 chiffres (numéro local)
            $suffix = substr($phone_digits, -8);
            $like   = '%' . $suffix;
            // Nettoie espaces, tirets, plus, parenthèses et points avant le LIKE
            $stmt   = $mysqli->prepare("SELECT id, name, email, phone FROM users
                                        WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,
                                                ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', '') LIKE ?
                                          AND is_active = 1 LIMIT 1");
            $stmt->bind_param('s', $like);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Générer un token sécurisé valable 1 heure.
            // Seul un HASH (sha256) du token est stocké en base : si la base
            // de données venait à fuiter, le token lui-même (envoyé au client
            // par email/WhatsApp) resterait impossible à retrouver, exactement
            // comme un mot de passe. Le token en clair n'existe que dans le lien
            // envoyé à l'utilisateur.
            $token      = generateToken(32);               // 64 caractères hex, envoyé au client
            $token_hash = hash('sha256', $token);           // stocké en base
            $expires    = date('Y-m-d H:i:s', time() + 3600);

            $upd = $mysqli->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $upd->bind_param('ssi', $token_hash, $expires, $user['id']);
            $upd->execute();
            $upd->close();

            // Construire le lien de réinitialisation
            $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
            $prenom     = explode(' ', $user['name'])[0];

            // ─── Canal WhatsApp ───────────────────────────────────────
            if ($channel === 'whatsapp') {
                $user_phone = $user['phone'] ?? '';
                if (empty($user_phone)) {
                    // Sécurité : ne JAMAIS révéler explicitement qu'un compte existe
                    // mais sans téléphone enregistré (permettrait à un attaquant de
                    // deviner des comptes valides). Même message générique que le cas
                    // "aucun compte trouvé" ci-dessous.
                    $success = "Si un compte correspond à ces informations et qu'un numéro de téléphone est enregistré, vous recevrez un lien sur WhatsApp.";
                } else {
                    $wa_phone_digits = normalizePhone($user_phone);
                    $wa_phone        = $user_phone;
                    $wa_message      = "Bonjour {$prenom}, voici votre lien de réinitialisation de mot de passe AtlanTech (valable 1 heure) :\n\n{$reset_link}\n\nSi vous n'avez pas demandé cette réinitialisation, ignorez ce message.";
                    $wa_link         = 'https://wa.me/' . $wa_phone_digits . '?text=' . rawurlencode($wa_message);
                    $success         = "Cliquez sur le bouton ci-dessous pour ouvrir WhatsApp et recevoir votre lien de réinitialisation.";
                }
            } else {
                // ─── Canal Email (comportement existant) ─────────────
                $email = $user['email'];

            // Corps de l'email en HTML
            $email_body = '
            <!DOCTYPE html>
            <html lang="fr">
            <head><meta charset="UTF-8"></head>
            <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td align="center" style="padding:40px 0;">
                  <table width="600" cellpadding="0" cellspacing="0"
                         style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
                    <!-- En-tête -->
                    <tr>
                      <td style="background:#ff8717;padding:30px 40px;text-align:center;">
                        <h1 style="color:#fff;margin:0;font-size:28px;letter-spacing:1px;">ATL&#9881;NTECH</h1>
                        <p style="color:rgba(255,255,255,.85);margin:4px 0 0;font-size:13px;letter-spacing:2px;">VOTRE TECH, LIVRÉE</p>
                      </td>
                    </tr>
                    <!-- Corps -->
                    <tr>
                      <td style="padding:40px;">
                        <h2 style="color:#111;margin:0 0 16px;">Bonjour ' . htmlspecialchars($prenom) . ',</h2>
                        <p style="color:#555;line-height:1.7;margin:0 0 20px;">
                          Vous avez demandé la réinitialisation de votre mot de passe AtlanTech.<br>
                          Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.<br>
                          <strong>Ce lien est valable pendant 1 heure.</strong>
                        </p>
                        <div style="text-align:center;margin:30px 0;">
                          <a href="' . $reset_link . '"
                             style="background:#ff8717;color:#fff;padding:14px 36px;border-radius:4px;
                                    text-decoration:none;font-weight:bold;font-size:16px;display:inline-block;">
                            Réinitialiser mon mot de passe
                          </a>
                        </div>
                        <p style="color:#888;font-size:13px;margin:20px 0 0;">
                          Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.<br>
                          Votre mot de passe restera inchangé.
                        </p>
                        <hr style="border:none;border-top:1px solid #eee;margin:30px 0;">
                        <p style="color:#bbb;font-size:12px;margin:0;">
                          Lien de secours : <a href="' . $reset_link . '" style="color:#ff8717;">' . $reset_link . '</a>
                        </p>
                      </td>
                    </tr>
                    <!-- Pied -->
                    <tr>
                      <td style="background:#f9f9f9;padding:20px 40px;text-align:center;">
                        <p style="color:#bbb;font-size:12px;margin:0;">
                          &copy; ' . date('Y') . ' AtlanTech &mdash; Les Cayes, Haïti<br>
                          <a href="' . SITE_URL . '" style="color:#ff8717;">www.atlantech.ht</a>
                        </p>
                      </td>
                    </tr>
                  </table>
                </td></tr>
              </table>
            </body>
            </html>';

                $sent = sendMailSMTP($email, 'Réinitialisation de votre mot de passe AtlanTech', $email_body);

                // Sécurité : le message affiché est TOUJOURS le même générique,
                // que l'envoi ait réussi ou non, et qu'un compte existe ou pas.
                // Un message différent en cas d'échec SMTP permettrait à un
                // attaquant de déduire qu'un compte existe (échec = compte trouvé
                // mais email cassé, succès générique = compte non trouvé). Les
                // vraies erreurs d'envoi sont journalisées côté serveur pour debug.
                if (!$sent) {
                    error_log('forgot-password.php : échec envoi email à ' . $email);
                }
                $success = "Si un compte correspond à cet email, un lien de réinitialisation a été envoyé.";
            } // fin canal email

        } else {
            // Sécurité : même message si identifiant inexistant (évite de révéler les comptes)
            if ($channel === 'whatsapp') {
                $success = "Si un compte correspond à ces informations et qu'un numéro de téléphone est enregistré, vous recevrez un lien sur WhatsApp.";
            } else {
                $success = "Si un compte correspond à cet email, un lien de réinitialisation a été envoyé.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mot de passe oublié — AtlanTech</title>

  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css" />
  <!-- Google Fonts non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>

    <style>
        .forgot__section {
            padding: 80px 0 100px;
            background: #f8f8f8;
            min-height: calc(100vh - 300px);
        }
        .forgot__card {
            background: #fff;
            border-radius: 8px;
            padding: 50px 50px 40px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
        }
        .forgot__card h2 {
            font-size: 26px;
            font-weight: 700;
            color: #111;
            margin-bottom: 8px;
        }
        .forgot__card .subtitle {
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .forgot__card .form-group { margin-bottom: 20px; }
        .forgot__card label { font-weight: 600; font-size: 14px; color: #333; display:block; margin-bottom:6px; }
        .forgot__card input[type="email"] {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px 16px;
            font-size: 15px;
            outline: none;
            transition: border .2s;
        }
        .forgot__card input[type="email"]:focus { border-color: #ff8717; }
        .forgot__btn {
            width: 100%;
            background: #ff8717;
            color: #fff;
            border: none;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            transition: background .2s;
            margin-top: 6px;
        }
        .forgot__btn:hover { background: #e07810; }
        .forgot__links { text-align: center; margin-top: 24px; font-size: 14px; color: #888; }
        .forgot__links a { color: #ff8717; text-decoration: none; font-weight: 600; }
        .forgot__links a:hover { text-decoration: underline; }
        .alert-success {
            background: #d4edda; color: #155724;
            border: 1px solid #c3e6cb; border-radius: 4px;
            padding: 14px 16px; margin-bottom: 20px; font-size: 14px;
        }
        .alert-danger {
            background: #f8d7da; color: #721c24;
            border: 1px solid #f5c6cb; border-radius: 4px;
            padding: 14px 16px; margin-bottom: 20px; font-size: 14px;
        }
        .lock-icon {
            width: 60px; height: 60px;
            background: #fff3e0;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 26px; color: #ff8717;
        }
        .channel-options {
            display: flex;
            gap: 12px;
        }
        .channel-option {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all .15s;
            margin: 0;
            font-weight: 500;
            font-size: 14px;
            color: #333;
            background: #fff;
        }
        .channel-option:hover {
            border-color: #ff8717;
            background: #fffaf3;
        }
        .channel-option input[type="radio"] {
            accent-color: #ff8717;
            margin: 0;
        }
        .channel-option input[type="radio"]:checked + span {
            color: #ff8717;
            font-weight: 700;
        }
        .channel-option:has(input:checked) {
            border-color: #ff8717;
            background: #fff3e0;
        }
    </style>
</head>
<body>
    <div class="body-overlay"></div>

    <?php
    // Header simplifié
    $user_first_name = null;
    $wishlist_count  = 0;
    include 'includes/header.php';
    ?>

    <!-- fil d'ariane -->
    <div class="breadcrumb__area breadcrumb__overlay pt-30 pb-30"
         data-background="assets/img/bg/breadcrumb_bg.jpg">
        <div class="container">
            <div class="breadcrumb__content">
                <h2 class="breadcrumb__title">Mot de passe oublié</h2>
                <ul class="breadcrumb__list ul_li">
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="account.php">Connexion</a></li>
                    <li>Mot de passe oublié</li>
                </ul>
            </div>
        </div>
    </div>

    <main>
        <section class="forgot__section">
            <div class="container">
                <div class="forgot__card">

                    <div class="lock-icon">
                        <i class="fas fa-lock"></i>
                    </div>

                    <h2>Mot de passe oublié ?</h2>
                    <p class="subtitle">
                        Entrez votre adresse e-mail ou votre numéro de téléphone.
                        Choisissez ensuite comment recevoir votre lien de réinitialisation.
                    </p>

                    <?php if ($success): ?>
                        <div class="alert-success">
                            <i class="fas fa-check-circle mr-2"></i> <?= $success ?>
                        </div>
                        <?php if (!empty($wa_link)): ?>
                            <div style="text-align:center;margin:20px 0;">
                                <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener"
                                   class="forgot__btn" style="background:#25D366;display:inline-block;text-decoration:none;padding:14px 28px;">
                                    <i class="fab fa-whatsapp mr-2"></i> Ouvrir WhatsApp
                                </a>
                                <p style="color:#888;font-size:13px;margin-top:12px;">
                                    WhatsApp va s'ouvrir avec un message pré-rempli contenant votre lien.<br>
                                    Envoyez-le vous à vous-même (<?= htmlspecialchars($wa_phone) ?>) pour le recevoir.
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="identifier">Adresse e-mail ou numéro de téléphone</label>
                            <input type="text" id="identifier" name="identifier"
                                   placeholder="exemple@email.com ou +509 xxxx xxxx"
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                                   required />
                        </div>

                        <div class="form-group">
                            <label style="margin-bottom:10px;">Comment voulez-vous recevoir le lien&nbsp;?</label>
                            <div class="channel-options">
                                <label class="channel-option">
                                    <input type="radio" name="channel" value="email"
                                        <?= (($_POST['channel'] ?? 'email') === 'email') ? 'checked' : '' ?> />
                                    <span><i class="fas fa-envelope mr-2"></i> Par e-mail</span>
                                </label>
                                <label class="channel-option">
                                    <input type="radio" name="channel" value="whatsapp"
                                        <?= (($_POST['channel'] ?? '') === 'whatsapp') ? 'checked' : '' ?> />
                                    <span><i class="fab fa-whatsapp mr-2" style="color:#25D366;"></i> Par WhatsApp</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="forgot" class="forgot__btn">
                            <i class="fas fa-paper-plane mr-2"></i> Envoyer le lien de réinitialisation
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="forgot__links">
                        <a href="account.php"><i class="fas fa-arrow-left mr-1"></i> Retour à la connexion</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- JS -->
    <!-- JS bundle (15 fichiers → 1 requête) -->
    <script src="assets/js/bundle.min.js"></script>
</body>
</html>
