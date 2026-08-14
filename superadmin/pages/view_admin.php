<?php
/**
 * Voir les détails d'un administrateur
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

// Récupérer l'ID de l'administrateur
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$admin_id) {
    header('Location: admins-list.php');
    exit;
}

// Récupérer les détails de l'administrateur
$admin = get_admin_by_id($admin_id);
if (!$admin) {
    header('Location: admins-list.php?error=not_found');
    exit;
}

// Récupérer l'historique d'activité
$activity = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_activity_logs WHERE admin_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$admin_id]);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $activity = [];
}

// Traiter les actions POST
$reset_error = '';
$reset_success = '';
$toggle_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $reset_error = 'Token CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';

        // Réinitialiser le mot de passe
        if ($action === 'reset_password') {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password) || empty($confirm_password)) {
                $reset_error = 'Tous les champs sont obligatoires.';
            } elseif (strlen($new_password) < 8) {
                $reset_error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($new_password !== $confirm_password) {
                $reset_error = 'Les mots de passe ne correspondent pas.';
            } else {
                $result = reset_admin_password($admin_id, $new_password, $_SESSION['superadmin_id']);
                if ($result['success']) {
                    $reset_success = 'Mot de passe réinitialisé avec succès.';
                    // Actualiser les données de l'administrateur
                    $admin = get_admin_by_id($admin_id);
                } else {
                    $reset_error = 'Erreur: ' . ($result['error'] ?? 'Inconnue');
                }
            }
        }
    }
}

$page_title = 'Fiche Administrateur';
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

        /* Profile Section */
        .profile-header {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 30px;
            align-items: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
        }

        .profile-info h2 {
            font-size: 24px;
            color: #e6f1ff;
            margin-bottom: 10px;
        }

        .profile-info p {
            color: #8892b0;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .profile-badges {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-role {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid #a855f7;
        }

        .badge-active {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
            border: 1px solid #00ff88;
        }

        .badge-inactive {
            background: rgba(255, 0, 110, 0.2);
            color: #ff006e;
            border: 1px solid #ff006e;
        }

        .profile-meta {
            text-align: right;
            color: #8892b0;
            font-size: 13px;
        }

        .profile-meta div {
            margin-bottom: 8px;
        }

        /* Info Grid */
        .info-grid {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .info-item {
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
            padding-bottom: 15px;
        }

        .info-item:last-child,
        .info-item:nth-last-child(2) {
            border-bottom: none;
        }

        .info-label {
            color: #8892b0;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            color: #e6f1ff;
            font-size: 16px;
            font-weight: 600;
        }

        /* Activity Table */
        .activity-section {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
            padding: 20px 30px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.3);
            color: #a855f7;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table thead {
            background: rgba(168, 85, 247, 0.1);
        }

        .activity-table th {
            padding: 15px 20px;
            text-align: left;
            color: #ffd700;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid rgba(168, 85, 247, 0.3);
        }

        .activity-table td {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.1);
            color: #e6f1ff;
            font-size: 14px;
        }

        .activity-table tbody tr:hover {
            background: rgba(168, 85, 247, 0.05);
        }

        .activity-empty {
            text-align: center;
            padding: 40px 20px;
            color: #8892b0;
        }

        .activity-empty i {
            font-size: 36px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Reset Password Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: rgba(17, 34, 64, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            position: relative;
        }

        .modal-header {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 20px;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: #8892b0;
            font-size: 24px;
            cursor: pointer;
            transition: 0.3s;
        }

        .modal-close:hover {
            color: #a855f7;
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

        .form-group input {
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

        .form-group input:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.3);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px 20px;
            background: linear-gradient(135deg, #a855f7, #7c3aed);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.4);
        }

        .btn-secondary {
            flex: 1;
            padding: 12px 20px;
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid #a855f7;
            border-radius: 8px;
            color: #a855f7;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-secondary:hover {
            background: #a855f7;
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-action {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
            border: 1px solid #ffd700;
        }

        .btn-edit:hover {
            background: #ffd700;
            color: #020817;
        }

        .btn-reset {
            background: rgba(0, 217, 255, 0.2);
            color: #00d9ff;
            border: 1px solid #00d9ff;
        }

        .btn-reset:hover {
            background: #00d9ff;
            color: white;
        }

        .btn-toggle {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid #a855f7;
        }

        .btn-toggle:hover {
            background: #a855f7;
            color: white;
        }

        .btn-back {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid #a855f7;
        }

        .btn-back:hover {
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
                <li><a href="admins-list.php" class="active"><i class="fas fa-user-shield"></i> Administrateurs</a></li>
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
                    <i class="fas fa-user-shield"></i> Fiche Administrateur
                </h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a> /
                    <a href="admins-list.php">Administrateurs</a> /
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($reset_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($reset_error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($reset_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($reset_success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                </div>

                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($admin['full_name']); ?></h2>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($admin['email']); ?></p>
                    <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($admin['name']); ?></p>

                    <div class="profile-badges">
                        <span class="badge badge-role">
                            <i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($admin['role_name'] ?? 'N/A'); ?>
                        </span>
                        <?php if ($admin['is_active']): ?>
                            <span class="badge badge-active">
                                <i class="fas fa-check-circle"></i> Actif
                            </span>
                        <?php else: ?>
                            <span class="badge badge-inactive">
                                <i class="fas fa-times-circle"></i> Inactif
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-meta">
                    <div><strong>ID:</strong> <?php echo $admin['id']; ?></div>
                    <div><strong>Créé le:</strong> <?php echo format_date($admin['created_at']); ?></div>
                    <div><strong>Dernier accès:</strong> <?php echo !empty($admin['last_login']) ? format_date($admin['last_login']) : 'Jamais'; ?></div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ID Administrateur</div>
                    <div class="info-value"><?php echo $admin['id']; ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Nom d'utilisateur</div>
                    <div class="info-value"><?php echo htmlspecialchars($admin['name']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($admin['email']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value"><?php echo !empty($admin['phone']) ? htmlspecialchars($admin['phone']) : 'Non renseigné'; ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Rôle</div>
                    <div class="info-value"><?php echo htmlspecialchars($admin['role_name'] ?? 'N/A'); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Statut</div>
                    <div class="info-value"><?php echo $admin['is_active'] ? 'Actif' : 'Inactif'; ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Dernier accès</div>
                    <div class="info-value"><?php echo !empty($admin['last_login']) ? format_date($admin['last_login']) : 'Jamais'; ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Tentatives échouées</div>
                    <div class="info-value"><?php echo intval($admin['login_attempts']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Date de création</div>
                    <div class="info-value"><?php echo format_date($admin['created_at']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Dernière modification</div>
                    <div class="info-value"><?php echo format_date($admin['updated_at']); ?></div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="admins-list.php" class="btn-action btn-back">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
                <button class="btn-action btn-reset" onclick="openResetModal()">
                    <i class="fas fa-key"></i> Réinitialiser mot de passe
                </button>
                <form method="POST" action="ajax/toggle_admin_status.php" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                    <button type="submit" class="btn-action btn-toggle"
                            onclick="return confirm('Êtes-vous sûr?')">
                        <i class="fas fa-<?php echo $admin['is_active'] ? 'lock' : 'unlock'; ?>"></i>
                        <?php echo $admin['is_active'] ? 'Désactiver' : 'Activer'; ?>
                    </button>
                </form>
            </div>

            <!-- Recent Activity -->
            <div class="activity-section">
                <div class="section-title">
                    <i class="fas fa-history"></i> Activité Récente
                </div>

                <?php if (count($activity) > 0): ?>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Adresse IP</th>
                                <th>Date & Heure</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity as $log): ?>
                                <tr>
                                    <td>
                                        <span style="background: rgba(168, 85, 247, 0.2); color: #a855f7; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_date($log['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="activity-empty">
                        <i class="fas fa-inbox"></i>
                        <p>Aucune activité enregistrée</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal">
            <button class="modal-close" onclick="closeResetModal()">
                <i class="fas fa-times"></i>
            </button>

            <div class="modal-header">
                Réinitialiser le mot de passe
            </div>

            <form method="POST" action="" id="resetPasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="reset_password">

                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="new_password" placeholder="Min. 8 caractères" required>
                </div>

                <div class="form-group">
                    <label>Confirmer mot de passe</label>
                    <input type="password" name="confirm_password" placeholder="Répéter le mot de passe" required>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Réinitialiser
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeResetModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openResetModal() {
            document.getElementById('resetModal').classList.add('active');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetModal();
            }
        });

        // Form validation
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }

            return true;
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeResetModal();
            }
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
