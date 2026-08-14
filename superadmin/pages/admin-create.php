<?php
/**
 * Créer un nouvel administrateur
 * Atlantech Shop - Super Admin Panel
 */

require_once __DIR__ . '/../../config/env.php';
if (env('APP_ENV', 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification Super Admin
check_superadmin_auth();

// Récupérer les rôles
$roles = get_all_roles();
$error = '';
$success = '';

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF invalide.';
    } else {
        // Préparer les données
        $data = [
            'full_name' => clean_input($_POST['full_name'] ?? ''),
            'name' => clean_input($_POST['name'] ?? ''),
            'email' => clean_input($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'phone' => clean_input($_POST['phone'] ?? ''),
            'role_id' => intval($_POST['role_id'] ?? 0),
        ];

        // Valider les données
        if (empty($data['full_name']) || empty($data['email']) || empty($data['password']) || $data['role_id'] === 0) {
            $error = 'Tous les champs obligatoires doivent être remplis.';
        } elseif (strlen($data['password']) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($_POST['password'] !== ($_POST['confirm_password'] ?? '')) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            // Créer l'administrateur
            $result = create_admin($data, $_SESSION['superadmin_id']);
            if ($result['success']) {
                $success = "Administrateur créé avec succès (ID: {$result['admin_id']})";
                // Réinitialiser le formulaire
                $data = [];
                $_POST = [];
            } else {
                $error = 'Erreur: ' . ($result['error'] ?? 'Inconnue');
            }
        }
    }
}

$page_title = 'Créer un Administrateur';
$superadmin_name = $_SESSION['superadmin_name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Atlantech Admin</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: #020817;
            color: #e6f1ff;
            overflow-x: hidden;
        }

        /* Background animé */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: rgba(17, 34, 64, 0.8);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(168, 85, 247, 0.3);
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 900;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #8892b0;
            text-decoration: none;
            border-radius: 10px;
            transition: 0.3s;
            font-size: 15px;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border-left: 3px solid #ffd700;
        }

        .sidebar-menu a i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(168, 85, 247, 0.3);
        }

        .sidebar-user {
            padding: 10px;
            background: rgba(168, 85, 247, 0.1);
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .sidebar-user-name {
            font-size: 14px;
            font-weight: 600;
            color: #e6f1ff;
        }

        .sidebar-user-role {
            font-size: 11px;
            color: #ffd700;
            text-transform: uppercase;
        }

        .sidebar-logout {
            width: 100%;
            padding: 10px;
            background: rgba(255, 0, 110, 0.2);
            border: 1px solid #ff006e;
            border-radius: 8px;
            color: #ff006e;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .sidebar-logout:hover {
            background: #ff006e;
            color: white;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .header {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
        }

        .header-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .breadcrumb {
            font-size: 14px;
            color: #8892b0;
            margin-top: 10px;
        }

        .breadcrumb a {
            color: #a855f7;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border-left-color: #00ff88;
            color: #00ff88;
        }

        .alert-error {
            background: rgba(255, 0, 110, 0.1);
            border-left-color: #ff006e;
            color: #ff006e;
        }

        .alert i {
            font-size: 18px;
        }

        /* Form Container */
        .form-container {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #8892b0;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group label .required {
            color: #ff006e;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(2, 8, 23, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 8px;
            color: #e6f1ff;
            font-size: 14px;
            font-family: 'Rajdhani', sans-serif;
            transition: 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.3);
        }

        .form-group input::placeholder {
            color: #8892b0;
        }

        .password-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a855f7;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: 0.3s;
        }

        .password-toggle:hover {
            color: #ffd700;
        }

        .form-hint {
            font-size: 12px;
            color: #8892b0;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-primary {
            flex: 1;
            padding: 14px 24px;
            background: linear-gradient(135deg, #a855f7, #7c3aed);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.4);
        }

        .btn-secondary {
            flex: 1;
            padding: 14px 24px;
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid #a855f7;
            border-radius: 10px;
            color: #a855f7;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #a855f7;
            color: white;
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

    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
    <!-- Close button (mobile) -->
    <button class="sa-sidebar-close" id="sa-sidebar-close-btn" aria-label="Fermer">
        <i class="fas fa-times"></i>
    </button>

            <div class="sidebar-logo">
                <i class="fas fa-crown"></i>
                <span>SUPER ADMIN</span>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admins-list.php"><i class="fas fa-user-shield"></i> Administrateurs</a></li>
                <li><a href="admin-create.php" class="active"><i class="fas fa-user-plus"></i> Créer Admin</a></li>
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
                <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            </ul>

            <div class="sidebar-bottom">
                <div class="sidebar-user">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($superadmin_name); ?></div>
                    <div class="sidebar-user-role">Super Admin</div>
                </div>
                <a href="../logout.php" class="sidebar-logout">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 class="header-title">
                    <i class="fas fa-user-plus"></i> Créer un Administrateur
                </h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a> /
                    <a href="admins-list.php">Administrateurs</a> /
                    <span>Créer un nouvel administrateur</span>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="form-container">
                <form method="POST" action="" id="adminForm">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <!-- Full Name -->
                    <div class="form-group">
                        <label>Nom complet <span class="required">*</span></label>
                        <input type="text" name="full_name" placeholder="Ex: Jean Dupont"
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>

                    <!-- Username -->
                    <div class="form-group">
                        <label>Nom d'utilisateur <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="Ex: jean.dupont"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        <div class="form-hint">Lettres minuscules, chiffres et tirets uniquement</div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="Ex: jean@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label>Rôle <span class="required">*</span></label>
                        <select name="role_id" required>
                            <option value="">-- Sélectionner un rôle --</option>
                            <?php
                            $role_labels = [
                                'order'       => '🛒 Gestion des commandes (validation paiement)',
                                'preparation' => '📦 Préparation des commandes (emballage)',
                                'delivery'    => '🚚 Livreur (expédition + livraison)',
                                'product'     => '🏷️ Gestion des produits',
                                'stock'       => '📊 Gestion des stocks (inventaire)',
                                'client'      => '👥 Gestion des clients',
                                'marketing'   => '📢 Marketing & promotions',
                                'support'     => '🎧 Support client',
                            ];
                            foreach ($roles as $role):
                                $lbl = $role_labels[$role['role_name']] ?? ucfirst($role['role_name']);
                            ?>
                                <option value="<?php echo $role['id']; ?>"
                                        <?php echo isset($_POST['role_id']) && $_POST['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lbl); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">⚠️ Pour accéder au panneau commandes, choisir <strong>🛒 Gestion des commandes</strong></div>
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="phone" placeholder="Ex: +509 XXXX XXXX"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <!-- Password Row -->
                    <div class="form-row">
                        <!-- Password -->
                        <div class="form-group">
                            <label>Mot de passe <span class="required">*</span></label>
                            <div class="password-group">
                                <input type="password" name="password" id="password"
                                       placeholder="Min. 8 caractères" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-hint">Minimum 8 caractères</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label>Confirmer mot de passe <span class="required">*</span></label>
                            <div class="password-group">
                                <input type="password" name="confirm_password" id="confirm_password"
                                       placeholder="Répéter le mot de passe" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Créer l'administrateur
                        </button>
                        <a href="admins-list.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = event.target.closest('button');
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }

            return true;
        });
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
