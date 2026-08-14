<?php
/**
 * Service Client - AtlanTech E-commerce
 */
require_once '../config/config.php';

$user_id   = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$user_name = $_SESSION['user_name'] ?? '';
$user_email= $_SESSION['user_email'] ?? '';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$success = ''; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name))    $errors[] = 'Votre nom est requis.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
        if (empty($subject)) $errors[] = 'Le sujet est requis.';
        if (strlen($message) < 10) $errors[] = 'Le message est trop court.';

        if (empty($errors)) {
            $stmt = $mysqli->prepare(
                "INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssss', $name, $email, $subject, $message);
            $stmt->execute();
            $stmt->close();
            $success = "Merci $name ! Votre message a bien été envoyé. Nous vous répondrons à $email dans les 24h.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Service Client - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:1000px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:6px;}
        .page-sub{font-size:14px;color:#565959;margin-bottom:28px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

        /* Layout 2 colonnes */
        .layout{display:grid;grid-template-columns:1fr 380px;gap:28px;}

        /* Contact channels */
        .channels-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:28px;}
        .channel{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:18px;display:flex;gap:14px;align-items:flex-start;}
        .channel .icon{font-size:28px;flex-shrink:0;}
        .channel h4{font-size:14px;font-weight:700;color:#0F1111;margin-bottom:3px;}
        .channel p{font-size:12px;color:#666;margin:0;}
        .channel a{color:#007185;text-decoration:none;font-size:13px;font-weight:600;}
        .channel a:hover{text-decoration:underline;}

        /* Formulaire */
        .form-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:28px;}
        .form-card h3{font-size:18px;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #E7E7E7;}
        .form-group{margin-bottom:16px;}
        .form-label{display:block;font-size:13px;font-weight:600;color:#0F1111;margin-bottom:5px;}
        .form-control{width:100%;padding:10px 12px;border:1px solid #888C8C;border-radius:6px;font-size:14px;}
        .form-control:focus{outline:none;border-color:#e77600;box-shadow:0 0 0 3px rgba(231,118,0,.15);}
        .btn-submit{width:100%;padding:12px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:14px;font-weight:700;color:#0F1111;cursor:pointer;}
        .btn-submit:hover{background:#F7CA00;}

        /* Sidebar info */
        .info-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:22px;margin-bottom:16px;}
        .info-card h4{font-size:15px;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #E7E7E7;}
        .info-row{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid #F5F5F5;}
        .info-row:last-child{border-bottom:none;}
        .info-row .label{color:#666;}
        .info-row .value{font-weight:600;color:#0F1111;}
        .status-badge{display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:5px;}

        /* Carte map */
        .map-placeholder{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:8px;padding:28px;text-align:center;color:#fff;margin-bottom:16px;}
        .map-placeholder .icon{font-size:40px;margin-bottom:10px;}

        @media(max-width:768px){.layout{grid-template-columns:1fr;}.channels-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div style="background:#131921;padding:12px 20px;display:flex;align-items:center;gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <?php if ($user_id): ?>
        <a href="dashboard.php" style="color:#fff;font-size:13px;text-decoration:none;"><i class="fas fa-user-circle"></i>&nbsp;Mon compte</a>
    <?php else: ?>
        <a href="../account.php" style="color:#fff;font-size:13px;text-decoration:none;">Connexion</a>
    <?php endif; ?>
</div>

<div class="wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <?php if ($user_id): ?><a href="dashboard.php">Mon compte</a> &rsaquo;<?php endif; ?>
        <span>Service Client</span>
    </nav>
    <h1 class="page-title">Service Client</h1>
    <p class="page-sub">Nous sommes là pour vous aider. Contactez-nous par le canal de votre choix.</p>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

    <!-- Canaux de contact -->
    <div class="channels-grid">
        <div class="channel">
            <div class="icon">📞</div>
            <div>
                <h4>Téléphone</h4>
                <a href="tel:+50944667553">+509 4466-7553</a>
                <p>Lun–Sam · 8h00 – 18h00</p>
            </div>
        </div>
        <div class="channel">
            <div class="icon">💬</div>
            <div>
                <h4>WhatsApp</h4>
                <a href="https://wa.me/50944667553" target="_blank">Ouvrir WhatsApp →</a>
                <p>Réponse rapide · 8h–20h</p>
            </div>
        </div>
        <div class="channel">
            <div class="icon">✉️</div>
            <div>
                <h4>E-mail</h4>
                <a href="mailto:support@atlantech.ht">support@atlantech.ht</a>
                <p>Réponse sous 24h ouvrables</p>
            </div>
        </div>
        <div class="channel">
            <div class="icon">📍</div>
            <div>
                <h4>En boutique</h4>
                <p>Rue Principale, Les Cayes<br>Haïti — Sud</p>
            </div>
        </div>
    </div>

    <div class="layout">
        <!-- Formulaire -->
        <div>
            <div class="form-card">
                <h3>💬 Envoyer un message</h3>
                <?php if (!$success): ?>
                <form method="POST" action="customer-service.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label class="form-label">Nom complet <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['name'] ?? $user_name); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adresse e-mail <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? $user_email); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sujet <span style="color:#ef4444;">*</span></label>
                        <select name="subject" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            <option>Question sur un produit</option>
                            <option>Problème de commande</option>
                            <option>Remboursement</option>
                            <option>Réclamation garantie</option>
                            <option>Information livraison</option>
                            <option>Partenariat / B2B</option>
                            <option>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message <span style="color:#ef4444;">*</span></label>
                        <textarea name="message" class="form-control" rows="5" required
                                  placeholder="Décrivez votre demande..."></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Envoyer le message</button>
                </form>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:#065f46;">
                        <div style="font-size:48px;margin-bottom:12px;">✅</div>
                        <p style="font-size:15px;">Message envoyé avec succès !</p>
                        <button onclick="location.reload()" style="padding:10px 22px;background:#FFD814;border:1px solid #FFA41C;border-radius:6px;font-weight:700;cursor:pointer;color:#0F1111;">Envoyer un autre message</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Horaires -->
            <div class="info-card">
                <h4>🕐 Horaires d'ouverture</h4>
                <?php
                $days = [
                    ['Lundi',   '8h00 – 18h00', true],
                    ['Mardi',   '8h00 – 18h00', true],
                    ['Mercredi','8h00 – 18h00', true],
                    ['Jeudi',   '8h00 – 18h00', true],
                    ['Vendredi','8h00 – 18h00', true],
                    ['Samedi',  '9h00 – 16h00', true],
                    ['Dimanche','Fermé',         false],
                ];
                foreach ($days as [$d, $h, $open]):
                ?>
                    <div class="info-row">
                        <span class="label"><?php echo $d; ?></span>
                        <span class="value" style="color:<?php echo $open?'#0F1111':'#ef4444'; ?>;">
                            <?php if ($open): ?><span class="status-badge"></span><?php endif; ?>
                            <?php echo $h; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Délais de réponse -->
            <div class="info-card">
                <h4>⚡ Délais de réponse</h4>
                <div class="info-row"><span class="label">WhatsApp</span><span class="value">~30 minutes</span></div>
                <div class="info-row"><span class="label">Téléphone</span><span class="value">Immédiat</span></div>
                <div class="info-row"><span class="label">E-mail</span><span class="value">Sous 24h</span></div>
                <div class="info-row"><span class="label">Formulaire</span><span class="value">Sous 24h</span></div>
            </div>

            <!-- Carte -->
            <div class="map-placeholder">
                <div class="icon">📍</div>
                <div style="font-size:15px;font-weight:700;">AtlanTech — Les Cayes</div>
                <div style="font-size:13px;opacity:.8;margin-top:4px;">Rue Principale, Sud, Haïti</div>
                <a href="https://maps.google.com/?q=Les+Cayes+Haiti" target="_blank"
                   style="display:inline-block;margin-top:12px;padding:8px 18px;background:rgba(255,255,255,.2);border-radius:6px;color:#fff;text-decoration:none;font-size:13px;">
                    Voir sur Google Maps →
                </a>
            </div>

            <?php if ($user_id): ?>
            <div style="text-align:center;font-size:13px;color:#666;">
                Besoin d'aide avec une commande ?<br>
                <a href="support.php?tab=ticket" style="color:#007185;">Ouvrir un ticket d'assistance →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($user_id): ?>
    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
    <?php else: ?>
    <div style="margin-top:24px;"><a href="../index.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour à l'accueil</a></div>
    <?php endif; ?>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
