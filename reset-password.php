<?php
/**
 * Réinitialisation du mot de passe — AtlanTech
 * Valide le token reçu par email et permet de choisir un nouveau mot de passe
 */

require_once 'config/config.php';

// Rediriger si déjà connecté
if (isLoggedIn()) redirect('index.php');

$error   = '';
$success = '';
$token   = trim($_GET['token'] ?? '');
$user    = null;

// ─── Données communes (nav) ─────────────────────────────────────────────
try {
    $r = $mysqli->query("SELECT id, name FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY id ASC LIMIT 7");
    $rootCategories = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) { $rootCategories = []; }

$cart_count      = 0;
$user_first_name = null;
$wishlist_count  = 0;

// ─── Valider le token ───────────────────────────────────────────────────
// On compare le HASH du token reçu (jamais le token en clair, qui n'est
// jamais stocké en base — cf. forgot-password.php).
if (empty($token)) {
    $error = "Lien invalide. Veuillez refaire une demande de réinitialisation.";
} else {
    $token_hash = hash('sha256', $token);
    $stmt = $mysqli->prepare(
        "SELECT id, name, email FROM users
         WHERE reset_token = ? AND reset_token_expires > NOW() AND is_active = 1
         LIMIT 1"
    );
    $stmt->bind_param('s', $token_hash);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $error = "Ce lien est invalide ou a expiré. Veuillez refaire une demande de réinitialisation.";
    }
}

// ─── Traitement du nouveau mot de passe ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset']) && $user) {

    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $error = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Les deux mots de passe ne correspondent pas.";
    } else {
        $hashed = hashPassword($new_password);

        // Mettre à jour le mot de passe, effacer le token, et lever une éventuelle
        // obligation de changement (mot de passe temporaire généré par un admin) :
        // choisir un nouveau mot de passe via ce flux authentifié par email suffit.
        $upd = $mysqli->prepare(
            "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL, force_password_change = 0 WHERE id = ?"
        );
        $upd->bind_param('si', $hashed, $user['id']);
        $upd->execute();
        $upd->close();

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nouveau mot de passe — AtlanTech</title>

  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css" />
  <!-- Google Fonts non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>

    <style>
        .reset__section {
            padding: 80px 0 100px;
            background: #f8f8f8;
            min-height: calc(100vh - 300px);
        }
        .reset__card {
            background: #fff;
            border-radius: 8px;
            padding: 50px 50px 40px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
        }
        .reset__card h2 {
            font-size: 26px;
            font-weight: 700;
            color: #111;
            margin-bottom: 8px;
        }
        .reset__card .subtitle {
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .reset__card .form-group { margin-bottom: 20px; }
        .reset__card label { font-weight: 600; font-size: 14px; color: #333; display:block; margin-bottom:6px; }
        .reset__card .input-wrap { position: relative; }
        .reset__card input[type="password"],
        .reset__card input[type="text"] {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px 44px 12px 16px;
            font-size: 15px;
            outline: none;
            transition: border .2s;
        }
        .reset__card input:focus { border-color: #ff8717; }
        .toggle-pw {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #aaa; background: none; border: none; padding: 0;
        }
        .toggle-pw:hover { color: #ff8717; }
        .pw-strength { margin-top: 6px; height: 4px; border-radius: 2px; background: #eee; overflow:hidden; }
        .pw-strength-bar { height: 100%; width: 0; border-radius: 2px; transition: width .3s, background .3s; }
        .pw-hint { font-size: 12px; color: #888; margin-top: 4px; }
        .reset__btn {
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
        .reset__btn:hover { background: #e07810; }
        .reset__links { text-align: center; margin-top: 24px; font-size: 14px; color: #888; }
        .reset__links a { color: #ff8717; text-decoration: none; font-weight: 600; }
        .reset__links a:hover { text-decoration: underline; }
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
        .shield-icon {
            width: 60px; height: 60px;
            background: #fff3e0;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 26px; color: #ff8717;
        }
        .success-block { text-align: center; padding: 10px 0; }
        .success-block .big-icon {
            font-size: 56px; color: #28a745; margin-bottom: 16px;
        }
        .success-block h3 { color: #111; margin-bottom: 10px; }
        .success-block p { color: #666; margin-bottom: 24px; }
    </style>
</head>
<body>
    <div class="body-overlay"></div>

    <?php include 'includes/header.php'; ?>

    <!-- fil d'ariane -->
    <div class="breadcrumb__area breadcrumb__overlay pt-30 pb-30"
         data-background="assets/img/bg/breadcrumb_bg.jpg">
        <div class="container">
            <div class="breadcrumb__content">
                <h2 class="breadcrumb__title">Nouveau mot de passe</h2>
                <ul class="breadcrumb__list ul_li">
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="account.php">Connexion</a></li>
                    <li>Nouveau mot de passe</li>
                </ul>
            </div>
        </div>
    </div>

    <main>
        <section class="reset__section">
            <div class="container">
                <div class="reset__card">

                    <?php if ($success === true): ?>
                    <!-- ─── Succès ─── -->
                    <div class="success-block">
                        <div class="big-icon"><i class="fas fa-check-circle"></i></div>
                        <h3>Mot de passe mis à jour !</h3>
                        <p>Votre mot de passe a été changé avec succès.<br>Vous pouvez maintenant vous connecter.</p>
                        <a href="account.php" class="thm-btn thm-btn__2">
                            <span class="btn-wrap">
                                <span>Se connecter</span>
                                <span>Se connecter</span>
                            </span>
                        </a>
                    </div>

                    <?php elseif ($error && !$user): ?>
                    <!-- ─── Token invalide / expiré ─── -->
                    <div class="shield-icon"><i class="fas fa-shield-alt"></i></div>
                    <h2>Lien invalide</h2>
                    <div class="alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                    <div class="reset__links">
                        <a href="forgot-password.php">
                            <i class="fas fa-redo mr-1"></i> Nouvelle demande de réinitialisation
                        </a>
                    </div>

                    <?php else: ?>
                    <!-- ─── Formulaire ─── -->
                    <div class="shield-icon"><i class="fas fa-key"></i></div>
                    <h2>Nouveau mot de passe</h2>
                    <p class="subtitle">
                        Bonjour <strong><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></strong>,
                        choisissez un nouveau mot de passe sécurisé.
                    </p>

                    <?php if ($error): ?>
                        <div class="alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="?token=<?= htmlspecialchars($token) ?>" id="resetForm">

                        <!-- Nouveau mot de passe -->
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <div class="input-wrap">
                                <input type="password" id="new_password" name="new_password"
                                       placeholder="Au moins <?= PASSWORD_MIN_LENGTH ?> caractères"
                                       minlength="<?= PASSWORD_MIN_LENGTH ?>" required />
                                <button type="button" class="toggle-pw" onclick="togglePw('new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="pw-strength"><div class="pw-strength-bar" id="strengthBar"></div></div>
                            <p class="pw-hint" id="strengthHint"></p>
                        </div>

                        <!-- Confirmer mot de passe -->
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe</label>
                            <div class="input-wrap">
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="Retapez le même mot de passe" required />
                                <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="pw-hint" id="matchHint"></p>
                        </div>

                        <button type="submit" name="reset" class="reset__btn" id="submitBtn">
                            <i class="fas fa-save mr-2"></i> Enregistrer le nouveau mot de passe
                        </button>
                    </form>

                    <div class="reset__links">
                        <a href="account.php"><i class="fas fa-arrow-left mr-1"></i> Retour à la connexion</a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- JS -->
    <!-- JS bundle (15 fichiers → 1 requête) -->
    <script src="assets/js/bundle.min.js"></script>

    <script>
    // ─── Afficher/masquer mot de passe ───────────────────────────────────
    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // ─── Jauge de force du mot de passe ─────────────────────────────────
    const pwInput   = document.getElementById('new_password');
    const bar       = document.getElementById('strengthBar');
    const hint      = document.getElementById('strengthHint');
    const confInput = document.getElementById('confirm_password');
    const matchHint = document.getElementById('matchHint');
    const submitBtn = document.getElementById('submitBtn');

    if (pwInput) {
        pwInput.addEventListener('input', function () {
            const val = this.value;
            let score = 0;
            if (val.length >= <?= PASSWORD_MIN_LENGTH ?>) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { w: '0%',   bg: '#eee',    label: '' },
                { w: '25%',  bg: '#dc3545', label: 'Très faible' },
                { w: '50%',  bg: '#fd7e14', label: 'Faible' },
                { w: '75%',  bg: '#ffc107', label: 'Moyen' },
                { w: '100%', bg: '#28a745', label: 'Fort' },
            ];
            const lvl = val.length === 0 ? 0 : Math.max(1, score);
            bar.style.width      = levels[lvl].w;
            bar.style.background = levels[lvl].bg;
            hint.textContent     = levels[lvl].label;
            hint.style.color     = levels[lvl].bg;
            checkMatch();
        });
    }

    // ─── Vérification correspondance ────────────────────────────────────
    function checkMatch() {
        if (!confInput.value) { matchHint.textContent = ''; return; }
        if (pwInput.value === confInput.value) {
            matchHint.textContent = '✓ Les mots de passe correspondent';
            matchHint.style.color = '#28a745';
        } else {
            matchHint.textContent = '✗ Les mots de passe ne correspondent pas';
            matchHint.style.color = '#dc3545';
        }
    }

    if (confInput) confInput.addEventListener('input', checkMatch);
    </script>
</body>
</html>
