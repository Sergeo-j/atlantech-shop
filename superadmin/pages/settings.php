<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$success = '';
$error = '';
$superadmin_id = $_SESSION['superadmin_id'];

// Load current superadmin
try {
    $stmt = $pdo->prepare("SELECT * FROM superadmins WHERE id = ? LIMIT 1");
    $stmt->execute([$superadmin_id]);
    $me = $stmt->fetch();
} catch(Exception $e) {
    $me = [];
    error_log($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF invalide.';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $full_name = clean_input($_POST['full_name'] ?? '');
            $email = clean_input($_POST['email'] ?? '');
            if (empty($full_name) || empty($email)) {
                $error = 'Nom et email requis.';
            } else {
                try {
                    $pdo->prepare("UPDATE superadmins SET full_name=?, email=?, updated_at=NOW() WHERE id=?")->execute([$full_name, $email, $superadmin_id]);
                    $_SESSION['superadmin_name'] = $full_name;
                    $_SESSION['superadmin_email'] = $email;
                    $me['full_name'] = $full_name;
                    $me['email'] = $email;
                    $success = 'Profil mis à jour.';
                    log_superadmin_action($superadmin_id, 'UPDATE_PROFILE', 'Mise à jour du profil super admin');
                } catch(Exception $e) {
                    $error = 'Erreur lors de la mise à jour.';
                    error_log($e->getMessage());
                }
            }
        } elseif ($_POST['action'] === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!verify_password($current, $me['password'])) {
                $error = 'Mot de passe actuel incorrect.';
            } elseif (strlen($new_pass) < 8) {
                $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
            } elseif ($new_pass !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                try {
                    $hash = hash_password($new_pass);
                    $pdo->prepare("UPDATE superadmins SET password=?, updated_at=NOW() WHERE id=?")->execute([$hash, $superadmin_id]);
                    $success = 'Mot de passe modifié avec succès.';
                    log_superadmin_action($superadmin_id, 'CHANGE_PASSWORD', 'Changement de mot de passe super admin');
                } catch(Exception $e) {
                    $error = 'Erreur lors du changement de mot de passe.';
                    error_log($e->getMessage());
                }
            }
        }
    }
}

// System info
$php_version = PHP_VERSION;
$db_version = '';
$admin_count = '?';
$user_count = '?';
$product_count = '?';

try {
    $db_version = $pdo->query("SELECT VERSION()")->fetchColumn();
} catch(Exception $e) {
    error_log($e->getMessage());
}

try {
    $admin_count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
} catch(Exception $e) {
    error_log($e->getMessage());
}

try {
    $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch(Exception $e) {
    error_log($e->getMessage());
}

try {
    $product_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
} catch(Exception $e) {
    error_log($e->getMessage());
}

$timezone = date_default_timezone_get();
$server_date = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Super Admin</title>
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
            color: #e6f1ff;
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(168,85,247,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(255,215,0,0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: rgba(17,34,64,0.8);
            border-right: 1px solid rgba(168,85,247,0.3);
            padding: 30px 20px;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo i {
            color: #ffd700;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            color: #8892b0;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 15px;
        }

        .sidebar-menu a:hover {
            color: #a855f7;
            background: rgba(168,85,247,0.1);
        }

        .sidebar-menu a.active {
            background: rgba(168,85,247,0.2);
            color: #a855f7;
            border-left: 3px solid #ffd700;
            padding-left: 12px;
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }

        .sidebar-bottom {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid rgba(168,85,247,0.2);
            color: #8892b0;
            font-size: 13px;
            text-align: center;
        }

        .sidebar-bottom i {
            color: #ffd700;
            margin-right: 5px;
        }

        .main-container {
            margin-left: 280px;
            padding: 30px;
            position: relative;
            z-index: 1;
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
            color: #ffd700;
        }

        .page-header i {
            font-size: 32px;
            color: #a855f7;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            color: #10b981;
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #ef4444;
        }

        .alert i {
            margin-top: 2px;
            flex-shrink: 0;
        }

        .sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
        }

        .card h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h2 i {
            color: #ffd700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: #a855f7;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 8px;
            padding: 12px 15px;
            color: #e6f1ff;
            font-family: 'Rajdhani', sans-serif;
            font-size: 15px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            background: rgba(255,255,255,0.08);
            border-color: rgba(168,85,247,0.6);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 38px;
            background: none;
            border: none;
            color: #a855f7;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }

        .password-toggle .toggle-btn:hover {
            color: #ffd700;
        }

        .btn {
            background: linear-gradient(135deg,#a855f7,#7c3aed);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168,85,247,0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .system-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(168,85,247,0.2);
            border-radius: 8px;
            padding: 15px;
        }

        .info-label {
            font-size: 12px;
            color: #a855f7;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .info-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            color: #ffd700;
            word-break: break-all;
        }

        @media (max-width: 1200px) {
            .sections {
                grid-template-columns: 1fr;
            }

            .system-info {
                grid-template-columns: 1fr;
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

    <!-- Sidebar -->
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
            <li><a href="manage_users.php"><i class="fas fa-users"></i> Clients</a></li>
            <li><a href="manage_products.php"><i class="fas fa-box"></i> Produits</a></li>
            <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                            <li>
                    <a href="taux-change.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'taux-change.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Taux de Change</span>
                    </a>
                </li>
                <li style="margin-top:15px;border-top:1px solid rgba(168,85,247,0.2);padding-top:15px;">
                <a href="system-logs.php"><i class="fas fa-history"></i> Journaux</a>
            </li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Paramètres</a></li>
            <li><a href="../logout.php" style="color:#ff006e;margin-top:20px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
        <div class="sidebar-bottom">
            <i class="fas fa-crown"></i> <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="page-header">
            <i class="fas fa-cog"></i>
            <h1>Paramètres</h1>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Settings Sections -->
        <div class="sections">
            <!-- Mon Profil -->
            <div class="card">
                <h2><i class="fas fa-user-circle"></i> Mon Profil</h2>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label for="full_name">Nom Complet</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($me['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse E-mail</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($me['email'] ?? ''); ?>" required>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </form>
            </div>

            <!-- Sécurité -->
            <div class="card">
                <h2><i class="fas fa-lock"></i> Sécurité</h2>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group password-toggle">
                        <label for="current_password">Mot de passe Actuel</label>
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="toggle-btn" onclick="togglePassword('current_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="form-group password-toggle">
                        <label for="new_password">Nouveau Mot de passe</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="toggle-btn" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="form-group password-toggle">
                        <label for="confirm_password">Confirmer Mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>

        <!-- Informations Système -->
        <div class="card">
            <h2><i class="fas fa-server"></i> Informations Système</h2>

            <div class="system-info">
                <div class="info-item">
                    <div class="info-label">Version PHP</div>
                    <div class="info-value"><?php echo htmlspecialchars($php_version); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Version MySQL</div>
                    <div class="info-value"><?php echo htmlspecialchars($db_version ?: 'N/A'); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Fuseau Horaire</div>
                    <div class="info-value"><?php echo htmlspecialchars($timezone); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Serveur</div>
                    <div class="info-value"><?php echo htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'N/A'); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Administrateurs</div>
                    <div class="info-value"><?php echo number_format($admin_count); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Clients</div>
                    <div class="info-value"><?php echo number_format($user_count); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Produits</div>
                    <div class="info-value"><?php echo number_format($product_count); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Date/Heure Serveur</div>
                    <div class="info-value"><?php echo htmlspecialchars($server_date); ?></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = event.target.closest('.toggle-btn');
            const icon = button.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
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
