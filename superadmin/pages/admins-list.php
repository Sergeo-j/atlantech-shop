<?php
/**
 * Gestion des Administrateurs
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

// Récupération des filtres
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Construction de la requête
$query = "SELECT a.*, ar.role_name, ar.role_description,
          (SELECT COUNT(*) FROM admin_activity_logs WHERE admin_id = a.id) as total_actions
          FROM admins a
          LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (a.name LIKE ? OR a.email LIKE ? OR a.full_name LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($role_filter)) {
    $query .= " AND a.admin_role_id = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $query .= " AND a.is_active = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les rôles pour le filtre
$roles_stmt = $pdo->query("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY role_name");
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Mapping centralisé des libellés de rôles (utilisé partout sur la page) ─
// Si un rôle n'est pas mappé ici, on retombe sur ucfirst(role_name).
$ROLE_LABELS = [
    'dg'          => '👑 Directeur Général (supervision)',
    'order'       => '🛒 Gestion des commandes (validation paiement)',
    'preparation' => '📦 Préparation des commandes (emballage)',
    'delivery'    => '🚚 Livreur (expédition + livraison)',
    'product'     => '🏷️ Gestion des produits',
    'stock'       => '📊 Gestion des stocks (inventaire)',
    'client'      => '👥 Gestion des clients',
    'marketing'   => '📢 Marketing & promotions',
    'support'     => '🎧 Support client',
];
$ROLE_LABELS_SHORT = [
    'dg'          => '👑 Directeur Général',
    'order'       => '🛒 Commandes',
    'preparation' => '📦 Préparation',
    'delivery'    => '🚚 Livraison',
    'product'     => '🏷️ Produits',
    'stock'       => '📊 Stock',
    'client'      => '👥 Clients',
    'marketing'   => '📢 Marketing',
    'support'     => '🎧 Support',
];
function format_role_label(string $role, array $labels): string {
    return $labels[$role] ?? ucfirst($role);
}

// Stats rapides
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_logins
    FROM admins";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

$page_title = 'Gestion des Administrateurs';
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
            font-size: 24px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .btn-primary {
            padding: 12px 24px;
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
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.4);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #8892b0;
            font-size: 14px;
        }
        
        /* Filters Section */
        .filters-section {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            color: #8892b0;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .filter-input,
        .filter-select {
            width: 100%;
            padding: 10px 15px;
            background: rgba(2, 8, 23, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 8px;
            color: #e6f1ff;
            font-size: 14px;
            font-family: 'Rajdhani', sans-serif;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: #a855f7;
        }
        
        .btn-filter {
            padding: 10px 20px;
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid #a855f7;
            border-radius: 8px;
            color: #a855f7;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-filter:hover {
            background: #a855f7;
            color: white;
        }
        
        /* Table */
        .table-container {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: rgba(168, 85, 247, 0.1);
        }
        
        .data-table th {
            padding: 15px;
            text-align: left;
            color: #ffd700;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            border-bottom: 2px solid rgba(168, 85, 247, 0.3);
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.1);
            color: #e6f1ff;
        }
        
        .data-table tbody tr {
            transition: 0.3s;
        }
        
        .data-table tbody tr:hover {
            background: rgba(168, 85, 247, 0.05);
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        
        .admin-details {
            flex: 1;
        }
        
        .admin-name {
            font-weight: 600;
            color: #e6f1ff;
        }
        
        .admin-email {
            font-size: 12px;
            color: #8892b0;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
        
        .badge-role {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid #a855f7;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        
        .btn-view {
            background: rgba(0, 217, 255, 0.2);
            color: #00d9ff;
        }
        
        .btn-view:hover {
            background: #00d9ff;
            color: white;
        }
        
        .btn-edit {
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
        }
        
        .btn-edit:hover {
            background: #ffd700;
            color: #020817;
        }
        
        .btn-delete {
            background: rgba(255, 0, 110, 0.2);
            color: #ff006e;
        }
        
        .btn-delete:hover {
            background: #ff006e;
            color: white;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(2, 8, 23, 0.9);
            backdrop-filter: blur(10px);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: rgba(17, 34, 64, 0.95);
            border: 2px solid rgba(168, 85, 247, 0.4);
            border-radius: 20px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.3);
        }
        
        .modal-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .btn-close {
            background: rgba(255, 0, 110, 0.2);
            border: 1px solid #ff006e;
            color: #ff006e;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-close:hover {
            background: #ff006e;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: #8892b0;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(2, 8, 23, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 8px;
            color: #e6f1ff;
            font-size: 14px;
            font-family: 'Rajdhani', sans-serif;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #a855f7;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .btn-cancel {
            padding: 10px 20px;
            background: rgba(136, 146, 176, 0.2);
            border: 1px solid #8892b0;
            border-radius: 8px;
            color: #8892b0;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-cancel:hover {
            background: #8892b0;
            color: #020817;
        }
        
        .btn-submit {
            padding: 10px 20px;
            background: linear-gradient(135deg, #a855f7, #7c3aed);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 85, 247, 0.4);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #8892b0;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
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
                <i class="fas fa-shield-alt"></i>
                <span>ATLANTECH</span>
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
                <li style="margin-top:15px;border-top:1px solid rgba(168,85,247,0.2);padding-top:15px;"><a href="system-logs.php"><i class="fas fa-history"></i> Journaux</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            </ul>
            
            <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(168, 85, 247, 0.3);">
                <div style="padding: 10px; background: rgba(168, 85, 247, 0.1); border-radius: 10px; margin-bottom: 15px;">
                    <div style="font-size: 14px; font-weight: 600; color: #e6f1ff;"><?php echo htmlspecialchars($superadmin_name); ?></div>
                    <div style="font-size: 11px; color: #ffd700; text-transform: uppercase;">Super Admin</div>
                </div>
                <a href="../logout.php" style="width: 100%; padding: 10px; background: rgba(255, 0, 110, 0.2); border: 1px solid #ff006e; border-radius: 8px; color: #ff006e; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 class="header-title">
                    <i class="fas fa-user-shield"></i> Gestion des Administrateurs
                </h1>
                <button class="btn-primary" onclick="openModal('add')">
                    <i class="fas fa-plus"></i> Nouvel Admin
                </button>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users" style="font-size: 24px; color: #a855f7;"></i>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Admins</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-user-check" style="font-size: 24px; color: #00ff88;"></i>
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Actifs</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-user-times" style="font-size: 24px; color: #ff006e;"></i>
                    <div class="stat-value"><?php echo $stats['inactive']; ?></div>
                    <div class="stat-label">Inactifs</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-clock" style="font-size: 24px; color: #ffd700;"></i>
                    <div class="stat-value"><?php echo $stats['recent_logins']; ?></div>
                    <div class="stat-label">Connexions 7j</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Rechercher</label>
                            <input type="text" name="search" class="filter-input" placeholder="Nom, email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Rôle</label>
                            <select name="role" class="filter-select">
                                <option value="">Tous les rôles</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(format_role_label($role['role_name'], $ROLE_LABELS_SHORT)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Statut</label>
                            <select name="status" class="filter-select">
                                <option value="">Tous</option>
                                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Actif</option>
                                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Administrateur</th>
                            <th>Rôle</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Dernière Connexion</th>
                            <th>Actions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($admins) > 0): ?>
                            <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <div class="admin-info">
                                        <div class="admin-avatar">
                                            <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                                        </div>
                                        <div class="admin-details">
                                            <div class="admin-name"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                                            <div class="admin-email"><?php echo htmlspecialchars($admin['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-role">
                                        <?php echo ucfirst(htmlspecialchars($admin['role_name'] ?? 'N/A')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($admin['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $admin['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $admin['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($admin['last_login']) {
                                        echo date('d/m/Y H:i', strtotime($admin['last_login']));
                                    } else {
                                        echo '<span style="color: #8892b0;">Jamais</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <small style="color: #8892b0;"><?php echo $admin['total_actions']; ?> actions</small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-view" onclick="viewAdmin(<?php echo $admin['id']; ?>)" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action btn-edit" onclick="editAdmin(<?php echo $admin['id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-delete" onclick="toggleStatus(<?php echo $admin['id']; ?>, <?php echo $admin['is_active']; ?>)" title="<?php echo $admin['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                            <i class="fas fa-<?php echo $admin['is_active'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="fas fa-user-slash"></i>
                                        <p>Aucun administrateur trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Modal Add/Edit Admin -->
    <div class="modal" id="adminModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nouvel Administrateur</h2>
                <button class="btn-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="adminForm" onsubmit="submitAdmin(event)">
                <input type="hidden" id="adminId" name="id">
                
                <div class="form-group">
                    <label class="form-label">Nom complet *</label>
                    <input type="text" id="fullName" name="full_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nom d'utilisateur *</label>
                    <input type="text" id="name" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="form-input" placeholder="+509 XXXX XXXX">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Rôle *</label>
                    <select id="roleId" name="admin_role_id" class="form-select" required>
                        <option value="">-- Sélectionner un rôle --</option>
                        <?php
                        $role_labels_modal = [
                            'dg'          => '👑 Directeur Général (supervision complète)',
                            'order'       => '🛒 Gestion des commandes (validation paiement)',
                            'preparation' => '📦 Préparation des commandes (emballage)',
                            'delivery'    => '🚚 Livreur (expédition + livraison)',
                            'product'     => '🏷️ Gestion des produits',
                            'stock'       => '📊 Gestion des stocks (inventaire)',
                            'client'      => '👥 Gestion des clients',
                            'marketing'   => '📢 Marketing & promotions',
                            'support'     => '🎧 Support client',
                        ];
                        // Le rôle DG est placé en premier dans la liste pour mise en avant.
                        // Le superadmin reste le seul à pouvoir le créer (cette page exige check_superadmin_auth).
                        usort($roles, function ($a, $b) {
                            if ($a['role_name'] === 'dg') return -1;
                            if ($b['role_name'] === 'dg') return  1;
                            return strcmp($a['role_name'], $b['role_name']);
                        });
                        foreach ($roles as $role):
                            $lbl_modal = $role_labels_modal[$role['role_name']] ?? ucfirst($role['role_name']);
                            $is_dg     = $role['role_name'] === 'dg';
                        ?>
                        <option value="<?php echo $role['id']; ?>"<?= $is_dg ? ' data-special="dg" style="font-weight:700"' : '' ?>>
                            <?php echo htmlspecialchars($lbl_modal); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label class="form-label">Mot de passe *</label>
                    <input type="password" id="password" name="password" class="form-input">
                    <small style="color: #8892b0; font-size: 12px;">Minimum 8 caractères</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select id="isActive" name="is_active" class="form-select">
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Ouvrir modal
        function openModal(mode, adminData = null) {
            const modal = document.getElementById('adminModal');
            const modalTitle = document.getElementById('modalTitle');
            const form = document.getElementById('adminForm');
            const passwordGroup = document.getElementById('passwordGroup');
            
            form.reset();
            
            if (mode === 'add') {
                modalTitle.textContent = 'Nouvel Administrateur';
                document.getElementById('adminId').value = '';
                passwordGroup.style.display = 'block';
                document.getElementById('password').required = true;
            } else if (mode === 'edit' && adminData) {
                modalTitle.textContent = 'Modifier Administrateur';
                document.getElementById('adminId').value = adminData.id;
                document.getElementById('fullName').value = adminData.full_name;
                document.getElementById('name').value = adminData.name;
                document.getElementById('email').value = adminData.email;
                document.getElementById('phone').value = adminData.phone || '';
                document.getElementById('roleId').value = adminData.admin_role_id;
                document.getElementById('isActive').value = adminData.is_active;
                passwordGroup.style.display = 'none';
                document.getElementById('password').required = false;
            }
            
            modal.classList.add('active');
        }
        
        // Fermer modal
        function closeModal() {
            document.getElementById('adminModal').classList.remove('active');
        }
        
        // Soumettre formulaire
        function submitAdmin(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const adminId = document.getElementById('adminId').value;
            const url = adminId ? 'ajax/update_admin.php' : 'ajax/create_admin.php';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue');
            });
        }
        
        // Voir détails admin
        function viewAdmin(adminId) {
            window.location.href = 'view_admin.php?id=' + adminId;
        }
        
        // Éditer admin
        function editAdmin(adminId) {
            fetch('ajax/get_admin.php?id=' + adminId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    openModal('edit', data.admin);
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue');
            });
        }
        
        // Toggle statut
        function toggleStatus(adminId, currentStatus) {
            const action = currentStatus ? 'désactiver' : 'activer';
            if (confirm(`Voulez-vous vraiment ${action} cet administrateur?`)) {
                fetch('ajax/toggle_admin_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: adminId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Une erreur est survenue');
                });
            }
        }
        
        // Fermer modal avec Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
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
