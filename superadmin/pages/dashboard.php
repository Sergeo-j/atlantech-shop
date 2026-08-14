<?php
/**
 * Dashboard Super Admin - Vue d'ensemble  
 * Atlantech Shop
 */

// Activer l'affichage des erreurs
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

// Vérifier l'authentification
check_superadmin_auth();

// Récupérer les statistiques
$stats = get_system_stats();
$recent_logs = get_recent_logs(10);

// Infos du Super Admin connecté
$superadmin_name = $_SESSION['superadmin_name'] ?? 'Super Admin';
$superadmin_email = $_SESSION['superadmin_email'] ?? '';

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Super Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .sidebar-logo i {
            color: #ffd700;
            font-size: 28px;
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
        
        .sidebar-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(168, 85, 247, 0.3);
        }
        
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: rgba(168, 85, 247, 0.1);
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .sidebar-user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
        }
        
        .sidebar-user-info {
            flex: 1;
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
        
        .btn-logout {
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
        
        .btn-logout:hover {
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
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            color: #8892b0;
            font-size: 14px;
        }
        
        .header-subtitle .time {
            color: #ffd700;
            font-weight: 600;
        }
        
        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(168, 85, 247, 0.3);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #a855f7, #ffd700);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
            color: white;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #e6f1ff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #8892b0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 10px;
        }
        
        .stat-change.positive {
            color: #00ff88;
        }
        
        .stat-change.negative {
            color: #ff006e;
        }
        
        /* ===== CHARTS SECTION ===== */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #a855f7;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-title i {
            color: #ffd700;
        }
        
        /* ===== RECENT LOGS ===== */
        .logs-section {
            background: rgba(17, 34, 64, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logs-title {
            font-size: 18px;
            font-weight: 600;
            color: #a855f7;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logs-title i {
            color: #ffd700;
        }
        
        .btn-view-all {
            padding: 8px 16px;
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid #a855f7;
            border-radius: 8px;
            color: #a855f7;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
        }
        
        .btn-view-all:hover {
            background: #a855f7;
            color: white;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .logs-table thead tr {
            border-bottom: 1px solid rgba(168, 85, 247, 0.3);
        }
        
        .logs-table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            color: #8892b0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .logs-table td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.1);
            font-size: 14px;
        }
        
        .logs-table tbody tr:hover {
            background: rgba(168, 85, 247, 0.05);
        }
        
        .log-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .log-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
        }
        
        .log-user-name {
            font-weight: 600;
            color: #e6f1ff;
        }
        
        .log-action {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .log-action.create {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
        }
        
        .log-action.update {
            background: rgba(255, 215, 0, 0.2);
            color: #ffd700;
        }
        
        .log-action.delete {
            background: rgba(255, 0, 110, 0.2);
            color: #ff006e;
        }
        
        .log-action.login {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
        }
        
        .log-time {
            color: #8892b0;
            font-size: 13px;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
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

    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
    <!-- Close button (mobile) -->
    <button class="sa-sidebar-close" id="sa-sidebar-close-btn" aria-label="Fermer">
        <i class="fas fa-times"></i>
    </button>

            <div class="sidebar-logo">
                <i class="fas fa-crown"></i>
                SUPER ADMIN
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="admins-list.php">
                        <i class="fas fa-user-shield"></i>
                        <span>Administrateurs</span>
                    </a>
                </li>
                <li>
                    <a href="admin-create.php">
                        <i class="fas fa-user-plus"></i>
                        <span>Créer Admin</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php">
                        <i class="fas fa-users"></i>
                        <span>Clients</span>
                    </a>
                </li>
                <li>
                    <a href="manage_products.php">
                        <i class="fas fa-box"></i>
                        <span>Produits</span>
                    </a>
                </li>
                <li>
                    <a href="manage_orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                    </a>
                </li>
                                <li>
                    <a href="taux-change.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'taux-change.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Taux de Change</span>
                    </a>
                </li>
                <li style="margin-top:15px;border-top:1px solid rgba(168,85,247,0.2);padding-top:15px;">
                    <a href="system-logs.php">
                        <i class="fas fa-history"></i>
                        <span>Journaux</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Paramètres</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <?php echo strtoupper(substr($superadmin_name, 0, 1)); ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($superadmin_name); ?></div>
                        <div class="sidebar-user-role">Super Admin</div>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 class="header-title">Dashboard</h1>
                <p class="header-subtitle">
                    Bienvenue, <strong><?php echo htmlspecialchars($superadmin_name); ?></strong> 
                    | <span class="time" id="currentTime"></span>
                </p>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_admins']); ?></div>
                    <div class="stat-label">Total Admins</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> <?php echo $stats['active_admins']; ?> actifs
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active_admins']); ?></div>
                    <div class="stat-label">Admins Actifs</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i> Opérationnels
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['inactive_admins']); ?></div>
                    <div class="stat-label">Admins Inactifs</div>
                    <?php if ($stats['inactive_admins'] > 0): ?>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-circle"></i> Désactivés
                    </div>
                    <?php else: ?>
                    <div class="stat-change positive">
                        <i class="fas fa-check"></i> Aucun
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Total Clients</div>
                    <div class="stat-change positive">
                        <i class="fas fa-chart-line"></i> Base utilisateurs
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Répartition des Admins par Rôle
                    </h3>
                    <canvas id="rolesChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-bar"></i>
                        Statistiques Admins
                    </h3>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <!-- Recent Logs -->
            <div class="logs-section">
                <div class="logs-header">
                    <h3 class="logs-title">
                        <i class="fas fa-history"></i>
                        Activités Récentes
                    </h3>
                    <a href="system-logs.php" class="btn-view-all">
                        Voir tout <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_logs)): ?>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="log-user">
                                        <div class="log-avatar">
                                            <?php echo strtoupper(substr($log['user_name'], 0, 1)); ?>
                                        </div>
                                        <span class="log-user-name"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $action_class = 'login';
                                    if (strpos($log['action'], 'CREATE') !== false) $action_class = 'create';
                                    elseif (strpos($log['action'], 'UPDATE') !== false || strpos($log['action'], 'EDIT') !== false) $action_class = 'update';
                                    elseif (strpos($log['action'], 'DELETE') !== false) $action_class = 'delete';
                                    ?>
                                    <span class="log-action <?php echo $action_class; ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                                <td class="log-time"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #8892b0;">
                                    Aucune activité récente
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        // Horloge en temps réel
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentTime').textContent = now.toLocaleDateString('fr-FR', options);
        }
        updateTime();
        setInterval(updateTime, 1000);
        
        // Chart des rôles (Pie Chart)
        const rolesData = <?php echo json_encode($stats['admins_by_role']); ?>;
        const roleLabels = rolesData.map(r => r.role_name);
        const roleCounts = rolesData.map(r => parseInt(r.count));
        
        const rolesCtx = document.getElementById('rolesChart').getContext('2d');
        new Chart(rolesCtx, {
            type: 'doughnut',
            data: {
                labels: roleLabels,
                datasets: [{
                    data: roleCounts,
                    backgroundColor: [
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(255, 215, 0, 0.8)',
                        'rgba(0, 255, 136, 0.8)',
                        'rgba(255, 0, 110, 0.8)',
                        'rgba(0, 217, 255, 0.8)',
                        'rgba(255, 128, 0, 0.8)',
                        'rgba(128, 255, 0, 0.8)'
                    ],
                    borderColor: 'rgba(17, 34, 64, 0.8)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#e6f1ff',
                            font: {
                                family: 'Rajdhani',
                                size: 13
                            }
                        }
                    }
                }
            }
        });
        
        // Chart du statut (Bar Chart)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['Actifs', 'Inactifs', 'Total'],
                datasets: [{
                    label: 'Nombre d\'admins',
                    data: [
                        <?php echo $stats['active_admins']; ?>,
                        <?php echo $stats['inactive_admins']; ?>,
                        <?php echo $stats['total_admins']; ?>
                    ],
                    backgroundColor: [
                        'rgba(0, 255, 136, 0.8)',
                        'rgba(255, 0, 110, 0.8)',
                        'rgba(168, 85, 247, 0.8)'
                    ],
                    borderColor: [
                        'rgba(0, 255, 136, 1)',
                        'rgba(255, 0, 110, 1)',
                        'rgba(168, 85, 247, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#8892b0',
                            font: {
                                family: 'Rajdhani'
                            }
                        },
                        grid: {
                            color: 'rgba(168, 85, 247, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#e6f1ff',
                            font: {
                                family: 'Rajdhani',
                                size: 14
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
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