<?php
/**
 * Gestion des Utilisateurs/Clients
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
$tier_filter = $_GET['tier'] ?? '';
$status_filter = $_GET['status'] ?? '';
$blocked_filter = $_GET['blocked'] ?? '';

// Construction de la requête
$query = "SELECT u.*,
          COUNT(DISTINCT o.id) as order_count,
          COALESCE(SUM(o.total_amount), 0) as total_revenue
          FROM users u
          LEFT JOIN orders o ON u.id = o.user_id AND o.status != 'cancelled'
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($tier_filter)) {
    $query .= " AND u.account_tier = ?";
    $params[] = $tier_filter;
}

if ($status_filter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = $status_filter;
}

if ($blocked_filter !== '') {
    $query .= " AND u.blocked = ?";
    $params[] = $blocked_filter;
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats rapides
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked,
    SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month
    FROM users";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);

$page_title = 'Gestion des Clients';
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
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
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #e6f1ff;
        }
        
        .user-email {
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
        
        .badge-blocked {
            background: rgba(255, 0, 0, 0.2);
            color: #ff0000;
            border: 1px solid #ff0000;
        }
        
        .badge-verified {
            background: rgba(0, 217, 255, 0.2);
            color: #00d9ff;
            border: 1px solid #00d9ff;
        }
        
        .badge-tier {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid #a855f7;
            text-transform: uppercase;
        }
        
        .badge-tier.gold {
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
            border: 1px solid #ffd700;
        }
        
        .badge-tier.platinum {
            background: rgba(229, 228, 226, 0.2);
            color: #e5e4e2;
            border: 1px solid #e5e4e2;
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
        
        .btn-block {
            background: rgba(255, 0, 110, 0.2);
            color: #ff006e;
        }
        
        .btn-block:hover {
            background: #ff006e;
            color: white;
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
                <li><a href="admins-list.php"><i class="fas fa-user-shield"></i> Administrateurs</a></li>
                <li><a href="admin-create.php"><i class="fas fa-user-plus"></i> Créer Admin</a></li>
                <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> Clients</a></li>
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
                    <i class="fas fa-users"></i> Gestion des Clients
                </h1>
                <button class="btn-primary" onclick="exportUsers()">
                    <i class="fas fa-download"></i> Exporter CSV
                </button>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users" style="font-size: 24px; color: #a855f7;"></i>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Clients</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-user-check" style="font-size: 24px; color: #00ff88;"></i>
                    <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label">Actifs</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-user-times" style="font-size: 24px; color: #ff006e;"></i>
                    <div class="stat-value"><?php echo number_format($stats['blocked']); ?></div>
                    <div class="stat-label">Bloqués</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-envelope-circle-check" style="font-size: 24px; color: #00d9ff;"></i>
                    <div class="stat-value"><?php echo number_format($stats['verified']); ?></div>
                    <div class="stat-label">Vérifiés</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-calendar-plus" style="font-size: 24px; color: #ffd700;"></i>
                    <div class="stat-value"><?php echo number_format($stats['new_this_month']); ?></div>
                    <div class="stat-label">Nouveaux (30j)</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Rechercher</label>
                            <input type="text" name="search" class="filter-input" placeholder="Nom, email, username..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Tier</label>
                            <select name="tier" class="filter-select">
                                <option value="">Tous les tiers</option>
                                <option value="bronze" <?php echo $tier_filter == 'bronze' ? 'selected' : ''; ?>>Bronze</option>
                                <option value="silver" <?php echo $tier_filter == 'silver' ? 'selected' : ''; ?>>Silver</option>
                                <option value="gold" <?php echo $tier_filter == 'gold' ? 'selected' : ''; ?>>Gold</option>
                                <option value="platinum" <?php echo $tier_filter == 'platinum' ? 'selected' : ''; ?>>Platinum</option>
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
                            <label>Bloqué</label>
                            <select name="blocked" class="filter-select">
                                <option value="">Tous</option>
                                <option value="0" <?php echo $blocked_filter === '0' ? 'selected' : ''; ?>>Non</option>
                                <option value="1" <?php echo $blocked_filter === '1' ? 'selected' : ''; ?>>Oui</option>
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
                            <th>Client</th>
                            <th>Tier</th>
                            <th>Commandes</th>
                            <th>CA Total</th>
                            <th>Points</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-tier <?php echo $user['account_tier']; ?>">
                                        <?php echo strtoupper($user['account_tier']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($user['order_count']); ?></td>
                                <td><?php echo number_format($user['total_revenue'], 2); ?> HTG</td>
                                <td>
                                    <span style="color: #ffd700; font-weight: 600;">
                                        <i class="fas fa-star"></i> <?php echo number_format($user['loyalty_points']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['blocked']): ?>
                                        <span class="badge badge-blocked">Bloqué</span>
                                    <?php elseif ($user['is_active']): ?>
                                        <span class="badge badge-active">Actif</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactif</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['email_verified']): ?>
                                        <span class="badge badge-verified" title="Email vérifié">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-view" onclick="viewUser(<?php echo $user['id']; ?>)" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action btn-edit" onclick="editUser(<?php echo $user['id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-block" onclick="toggleBlock(<?php echo $user['id']; ?>, <?php echo $user['blocked']; ?>)" title="<?php echo $user['blocked'] ? 'Débloquer' : 'Bloquer'; ?>">
                                            <i class="fas fa-<?php echo $user['blocked'] ? 'unlock' : 'ban'; ?>"></i>
                                        </button>
                                        <button class="btn-action btn-delete-user" style="background:#dc3545;color:#fff;" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name']), ENT_QUOTES); ?>')" title="Supprimer définitivement">
                                            <i class="fas fa-trash"></i>
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
                                        <p>Aucun client trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        // Voir détails utilisateur
        function viewUser(userId) {
            window.location.href = 'view_user.php?id=' + userId;
        }
        
        // Éditer utilisateur
        function editUser(userId) {
            window.location.href = 'edit_user.php?id=' + userId;
        }
        
        // Toggle block/unblock
        function toggleBlock(userId, currentStatus) {
            const action = currentStatus ? 'débloquer' : 'bloquer';
            if (confirm(`Voulez-vous vraiment ${action} ce client?`)) {
                fetch('ajax/toggle_user_block.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: userId })
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
        
        // Exporter CSV
        function exportUsers() {
            window.location.href = 'ajax/export_users.php' + window.location.search;
        }

        // Suppression DÉFINITIVE d'un client (Super Admin uniquement)
        function deleteUser(userId, userName) {
            if (!confirm('⚠️ ATTENTION : suppression DÉFINITIVE du client « ' + userName + ' » !\n\n' +
                         '• Son compte et toutes ses données personnelles seront effacés\n' +
                         '• Ses commandes seront conservées mais anonymisées (rapports intacts)\n' +
                         '• Cette action est IRRÉVERSIBLE\n\nContinuer ?')) {
                return;
            }
            const typed = prompt('Pour confirmer, tapez exactement : SUPPRIMER');
            if (typed === null) return;
            if (typed.trim() !== 'SUPPRIMER') {
                alert('Confirmation incorrecte — suppression annulée.');
                return;
            }
            fetch('ajax/delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId, confirm: 'SUPPRIMER' })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.success ? data.message : ('Erreur: ' + data.message));
                if (data.success) location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue');
            });
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
