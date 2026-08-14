<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) { header('Location: manage_users.php'); exit; }

$error = ''; $success = '';

// Load user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(Exception $e) { $user = null; }
if (!$user) { header('Location: manage_users.php?error=not_found'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF invalide.';
    } elseif (($_POST['quick_action'] ?? '') === 'send_reset_link') {
        // Cas extrême : envoyer un lien de réinitialisation au client (comme
        // le flux "mot de passe oublié" public). Le mot de passe actuel
        // n'est jamais consulté ni affiché — techniquement impossible avec
        // un hachage Argon2id à sens unique.
        try {
            $token      = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires    = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $stmt->execute([$token_hash, $expires, $user_id]);

            require_once __DIR__ . '/../../config/mailer.php';
            if (!defined('SITE_URL')) {
                define('SITE_URL', env('SITE_URL', 'https://atlantech.shop'));
            }
            $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
            $prenom     = explode(' ', (string)($user['name'] ?? ''))[0];

            $email_body = '
            <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
            <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
              <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 0;">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
                  <tr><td style="background:#ff8717;padding:30px 40px;text-align:center;">
                    <h1 style="color:#fff;margin:0;font-size:28px;letter-spacing:1px;">ATL&#9881;NTECH</h1>
                  </td></tr>
                  <tr><td style="padding:40px;">
                    <h2 style="color:#111;margin:0 0 16px;">Bonjour ' . htmlspecialchars($prenom) . ',</h2>
                    <p style="color:#555;line-height:1.7;margin:0 0 20px;">
                      Notre équipe a initié une réinitialisation de votre mot de passe AtlanTech à votre demande.<br>
                      Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.<br>
                      <strong>Ce lien est valable pendant 1 heure.</strong>
                    </p>
                    <div style="text-align:center;margin:30px 0;">
                      <a href="' . $reset_link . '" style="background:#ff8717;color:#fff;padding:14px 36px;border-radius:4px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block;">Réinitialiser mon mot de passe</a>
                    </div>
                  </td></tr>
                </table>
              </td></tr></table>
            </body></html>';

            $sent = sendMailSMTP($user['email'], 'Réinitialisation de votre mot de passe AtlanTech', $email_body);

            $success = $sent
                ? 'Lien de réinitialisation envoyé à ' . htmlspecialchars($user['email']) . '.'
                : "Le lien a été généré mais l'envoi de l'email a échoué (config SMTP à vérifier).";

            log_superadmin_action($_SESSION['superadmin_id'], 'CLIENT_RESET_LINK', "Lien de réinitialisation envoyé au client ID: $user_id", 'users');

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            error_log('edit_user send_reset_link: ' . $e->getMessage());
            $error = 'Erreur technique lors de la génération du lien.';
        }
    } elseif (($_POST['quick_action'] ?? '') === 'generate_temp_password') {
        // Cas extrême : générer un NOUVEAU mot de passe temporaire, affiché
        // une seule fois, jamais loggé en clair. Le client devra le changer
        // à sa prochaine connexion (force_password_change).
        try {
            $temp_password = generate_temp_password(12);
            $hashed_temp   = hash_password($temp_password);

            $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 1, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->execute([$hashed_temp, $user_id]);

            log_superadmin_action($_SESSION['superadmin_id'], 'CLIENT_TEMP_PASSWORD', "Mot de passe temporaire généré pour le client ID: $user_id", 'users');

            $temp_password_display = $temp_password;
            $success = 'Mot de passe temporaire généré. Communiquez-le au client de vive voix — il ne sera plus jamais affiché après cette page.';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (\Throwable $e) {
            error_log('edit_user generate_temp_password: ' . $e->getMessage());
            $error = 'Erreur technique lors de la génération du mot de passe temporaire.';
        }
    } else {
        $first_name = clean_input($_POST['first_name'] ?? '');
        $last_name = clean_input($_POST['last_name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $is_blocked = isset($_POST['is_blocked']) ? 1 : 0;

        if (empty($email)) {
            $error = "L'email est requis.";
        } else {
            try {
                // Update basic user info
                $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, blocked=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $is_blocked, $user_id]);

                // Handle password change if provided
                if (!empty($_POST['new_password'])) {
                    $new_password = $_POST['new_password'];
                    if (strlen($new_password) < 8) {
                        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
                    } else {
                        $hashed_password = hash_password($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                        $stmt->execute([$hashed_password, $user_id]);
                    }
                }

                if (empty($error)) {
                    // Re-fetch updated user
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    $success = 'Client mis à jour avec succès.';
                    // Log action
                    log_superadmin_action($_SESSION['superadmin_id'], 'UPDATE_USER', "Modification du client ID: $user_id", 'users');
                }
            } catch(Exception $e) {
                $error = 'Erreur lors de la mise à jour.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Client - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #020817;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            font-family: 'Rajdhani', sans-serif;
            color: #e6f1ff;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: rgba(17, 34, 64, 0.8);
            border-right: 1px solid rgba(168, 85, 247, 0.3);
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-logo {
            padding: 0 20px 30px;
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #a855f7;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo i {
            color: #ffd700;
            font-size: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            flex: 1;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: #b0b9c3;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .sidebar-menu li a i {
            width: 20px;
            text-align: center;
        }

        .sidebar-menu li a:hover {
            color: #a855f7;
            background: rgba(168, 85, 247, 0.1);
        }

        .sidebar-menu li.active a {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border-left: 3px solid #ffd700;
            padding-left: 17px;
        }

        .sidebar > div:last-child {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(168, 85, 247, 0.2);
            color: #8892b0;
            font-size: 13px;
            text-align: center;
            padding: 20px;
        }

        .sidebar > div:last-child i {
            color: #ffd700;
            margin-right: 5px;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            flex: 1;
            width: calc(100% - 280px);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #8892b0;
        }

        .breadcrumb a {
            color: #a855f7;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #ffd700;
        }

        .breadcrumb i {
            color: #a855f7;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #a855f7;
            flex: 1;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(0, 212, 170, 0.2);
            border: 1px solid rgba(0, 212, 170, 0.3);
            color: #00d4aa;
        }

        .alert-error {
            background: rgba(255, 0, 110, 0.2);
            border: 1px solid rgba(255, 0, 110, 0.3);
            color: #ff006e;
        }

        .form-card {
            background: rgba(17, 34, 64, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 13px;
            color: #b0b9c3;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .form-label .required {
            color: #ff006e;
        }

        .form-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 8px;
            padding: 12px 15px;
            color: #e6f1ff;
            font-family: 'Rajdhani', sans-serif;
            font-size: 15px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(168, 85, 247, 0.5);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.2);
        }

        .form-input::placeholder {
            color: #8892b0;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-input {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #a855f7;
        }

        .checkbox-label {
            cursor: pointer;
            font-size: 14px;
            color: #b0b9c3;
            transition: color 0.3s ease;
        }

        .checkbox-label:hover {
            color: #a855f7;
        }

        .form-help {
            font-size: 12px;
            color: #8892b0;
            margin-top: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            font-family: 'Rajdhani', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-submit {
            background: rgba(168, 85, 247, 0.3);
            border: 1px solid rgba(168, 85, 247, 0.5);
            color: #a855f7;
        }

        .btn-submit:hover {
            background: rgba(168, 85, 247, 0.4);
            color: #ffd700;
        }

        .btn-secondary {
            background: rgba(17, 34, 64, 0.8);
            border: 1px solid rgba(168, 85, 247, 0.3);
            color: #b0b9c3;
        }

        .btn-secondary:hover {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
                width: calc(100% - 220px);
                padding: 20px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .page-header h1 {
                font-size: 24px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

<!-- Mobile top bar (hamburger) -->
<div class="sa-mobile-header">
    <span class="sa-mobile-logo"><i class="fas fa-shield-alt" style="margin-right:6px;color:#ffd700;-webkit-text-fill-color:#ffd700"></i>ATLANTECH SA</span>
    <button class="sa-hamburger" id="sa-hamburger-btn" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
    </button>
</div>
<!-- Sidebar overlay -->
<div class="sa-sidebar-overlay" id="sa-sidebar-overlay"></div>

    <div class="sidebar">
    <!-- Close button (mobile) -->
    <button class="sa-sidebar-close" id="sa-sidebar-close-btn" aria-label="Fermer">
        <i class="fas fa-times"></i>
    </button>

        <div class="sidebar-logo"><i class="fas fa-crown"></i> SUPER ADMIN</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admins-list.php"><i class="fas fa-user-shield"></i> Administrateurs</a></li>
            <li><a href="admin-create.php"><i class="fas fa-user-plus"></i> Créer Admin</a></li>
            <li class="active"><a href="manage_users.php"><i class="fas fa-users"></i> Clients</a></li>
            <li><a href="manage_products.php"><i class="fas fa-box"></i> Produits</a></li>
            <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
            <li style="margin-top:15px; border-top:1px solid rgba(168,85,247,0.2); padding-top:15px;">
                <a href="system-logs.php"><i class="fas fa-history"></i> Journaux</a>
            </li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            <li><a href="../logout.php" style="color:#ff006e; margin-top:20px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
        <div style="margin-top:auto; padding-top:20px; border-top:1px solid rgba(168,85,247,0.2); color:#8892b0; font-size:13px; text-align:center;">
            <i class="fas fa-crown" style="color:#ffd700;"></i> <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="breadcrumb">
            <a href="manage_users.php"><i class="fas fa-users"></i> Clients</a>
            <i class="fas fa-chevron-right"></i>
            <a href="view_user.php?id=<?php echo htmlspecialchars($user['id']); ?>">Fiche</a>
            <i class="fas fa-chevron-right"></i>
            <span>Modifier</span>
        </div>

        <div class="page-header">
            <h1>Modifier Client</h1>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($temp_password_display)): ?>
            <div class="form-card" style="border: 2px solid #ffd700; margin-bottom: 25px;">
                <div class="section-title"><i class="fas fa-key"></i> Mot de passe temporaire — visible une seule fois</div>
                <p style="font-size: 1.6rem; font-family: monospace; letter-spacing: 2px; background: rgba(255,215,0,.08); border: 1px dashed #ffd700; border-radius: 8px; padding: 16px; text-align: center; margin: 10px 0; color: #e6f1ff;">
                    <?php echo htmlspecialchars($temp_password_display); ?>
                </p>
                <p class="form-help">
                    Communiquez ce mot de passe au client de vive voix ou par un canal sécurisé (pas par email non chiffré).
                    Il ne sera plus jamais affiché — actualiser cette page l'efface définitivement.
                    Le client devra le changer dès sa prochaine connexion.
                </p>
            </div>
        <?php endif; ?>

        <div class="form-card" style="border-left: 3px solid #ff006e; margin-bottom: 25px;">
            <div class="section-title"><i class="fas fa-life-ring"></i> Débloquer l'accès du client (cas extrême)</div>
            <p class="form-help" style="margin-bottom:16px">
                Le mot de passe actuel du client n'est jamais visible ni récupérable (hachage Argon2id à sens unique).
                Ces actions créent un nouvel accès — à utiliser uniquement si le client est bloqué et injoignable autrement.
            </p>
            <div style="display:flex; gap:15px; flex-wrap:wrap">
                <form method="POST" onsubmit="return confirm('Envoyer un lien de réinitialisation à ' + <?php echo json_encode($user['email'] ?? ''); ?> + ' ?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <input type="hidden" name="quick_action" value="send_reset_link">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-paper-plane"></i> Envoyer un lien de réinitialisation
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('Générer un mot de passe temporaire pour ce client ?\n\nÀ utiliser uniquement si le client est injoignable par email — ce mot de passe devra être communiqué de vive voix.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <input type="hidden" name="quick_action" value="generate_temp_password">
                    <button type="submit" class="btn" style="background: rgba(255,0,110,0.3); border: 1px solid rgba(255,0,110,0.5); color: #ff006e;">
                        <i class="fas fa-key"></i> Générer un mot de passe temporaire
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

            <div class="form-section">
                <div class="section-title"><i class="fas fa-user"></i> Informations personnelles</div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" placeholder="Ex: Jean">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nom</label>
                        <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" placeholder="Ex: Dupont">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="exemple@email.com" required>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Ex: +509 2222-2222">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title"><i class="fas fa-lock"></i> Sécurité</div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe (optionnel)</label>
                        <input type="password" name="new_password" class="form-input" placeholder="Laisser vide pour ne pas changer">
                        <div class="form-help">Minimum 8 caractères. Laisser vide pour conserver le mot de passe actuel.</div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_blocked" name="is_blocked" class="checkbox-input" <?php echo ($user['blocked'] ?? false) ? 'checked' : ''; ?>>
                            <label for="is_blocked" class="checkbox-label">Bloquer ce compte</label>
                        </div>
                        <div class="form-help">Un compte bloqué ne pourra pas se connecter à son espace client.</div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
                <a href="view_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> Voir la fiche
                </a>
                <a href="manage_users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>
        </form>
    </div>
<script>
(function(){
    var overlay   = document.getElementById('sa-sidebar-overlay');
    var sidebar   = document.querySelector('.sidebar');
    var hamburger = document.getElementById('sa-hamburger-btn');
    var closeBtn  = document.getElementById('sa-sidebar-close-btn');
    function openSidebar()  { if(sidebar){sidebar.classList.add('sa-open');}    if(overlay){overlay.classList.add('active');} }
    function closeSidebar() { if(sidebar){sidebar.classList.remove('sa-open');} if(overlay){overlay.classList.remove('active');} }
    if(hamburger) hamburger.addEventListener('click', openSidebar);
    if(closeBtn)  closeBtn.addEventListener('click', closeSidebar);
    if(overlay)   overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
