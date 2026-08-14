<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$type = $_GET['type'] ?? 'superadmin'; // 'superadmin' or 'admin'
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

$logs = [];
$total = 0;
$today_count = 0;
$unique_actions = 0;

try {
    if ($type === 'superadmin') {
        $where = "WHERE 1=1";
        $params = [];
        if ($search) {
            $where .= " AND (sal.action LIKE ? OR sal.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($date_from) {
            $where .= " AND DATE(sal.created_at) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(sal.created_at) <= ?";
            $params[] = $date_to;
        }
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM superadmin_activity_logs sal $where");
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();

        // Today count
        $today_stmt = $pdo->prepare("SELECT COUNT(*) FROM superadmin_activity_logs sal WHERE DATE(sal.created_at) = CURDATE()");
        $today_stmt->execute();
        $today_count = $today_stmt->fetchColumn();

        // Unique actions
        $unique_stmt = $pdo->prepare("SELECT COUNT(DISTINCT action) FROM superadmin_activity_logs sal");
        $unique_stmt->execute();
        $unique_actions = $unique_stmt->fetchColumn();

        $fetch_params = $params;
        $fetch_params[] = $offset;
        $fetch_params[] = $per_page;
        $stmt = $pdo->prepare("SELECT sal.*, sa.full_name as actor_name FROM superadmin_activity_logs sal LEFT JOIN superadmins sa ON sal.superadmin_id = sa.id $where ORDER BY sal.created_at DESC LIMIT ?, ?");
        $stmt->execute($fetch_params);
        $logs = $stmt->fetchAll();
    } else {
        $where = "WHERE 1=1";
        $params = [];
        if ($search) {
            $where .= " AND (al.action LIKE ? OR al.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($date_from) {
            $where .= " AND DATE(al.created_at) >= ?";
            $params[] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(al.created_at) <= ?";
            $params[] = $date_to;
        }
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_logs al $where");
        $count_stmt->execute($params);
        $total = $count_stmt->fetchColumn();

        // Today count
        $today_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_logs al WHERE DATE(al.created_at) = CURDATE()");
        $today_stmt->execute();
        $today_count = $today_stmt->fetchColumn();

        // Unique actions
        $unique_stmt = $pdo->prepare("SELECT COUNT(DISTINCT action) FROM admin_activity_logs al");
        $unique_stmt->execute();
        $unique_actions = $unique_stmt->fetchColumn();

        $fetch_params = $params;
        $fetch_params[] = $offset;
        $fetch_params[] = $per_page;
        $stmt = $pdo->prepare("SELECT al.*, a.full_name as actor_name FROM admin_activity_logs al LEFT JOIN admins a ON al.admin_id = a.id $where ORDER BY al.created_at DESC LIMIT ?, ?");
        $stmt->execute($fetch_params);
        $logs = $stmt->fetchAll();
    }
} catch(Exception $e) {
    error_log($e->getMessage());
}

$total_pages = ceil($total / $per_page);

// Function to get badge color by action type
function get_action_badge($action) {
    $colors = [
        'LOGIN' => '#10b981',
        'LOGOUT' => '#6b7280',
        'CREATE' => '#06b6d4',
        'UPDATE' => '#eab308',
        'DELETE' => '#ef4444',
    ];
    return $colors[$action] ?? '#a855f7';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journaux d'Activité - Super Admin</title>
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

        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(168,85,247,0.2);
        }

        .tab-btn {
            padding: 12px 20px;
            background: transparent;
            border: none;
            color: #8892b0;
            font-family: 'Rajdhani', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            color: #a855f7;
        }

        .tab-btn.active {
            color: #a855f7;
            border-bottom-color: #ffd700;
        }

        .filter-bar {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            color: #a855f7;
            font-weight: 600;
            text-transform: uppercase;
        }

        .filter-group input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 8px;
            padding: 12px 15px;
            color: #e6f1ff;
            font-family: 'Rajdhani', sans-serif;
            font-size: 15px;
            width: 200px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus {
            outline: none;
            background: rgba(255,255,255,0.08);
            border-color: rgba(168,85,247,0.6);
        }

        .filter-group input[type="date"] {
            width: 180px;
        }

        .filter-btn {
            background: linear-gradient(135deg,#a855f7,#7c3aed);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168,85,247,0.3);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 13px;
            color: #a855f7;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stat-card .value {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #ffd700;
        }

        .table-container {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: rgba(168,85,247,0.1);
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #a855f7;
            text-transform: uppercase;
            border-bottom: 2px solid rgba(168,85,247,0.3);
        }

        tbody td {
            padding: 15px;
            border-bottom: 1px solid rgba(168,85,247,0.1);
            font-size: 14px;
        }

        tbody tr:hover {
            background: rgba(168,85,247,0.05);
        }

        .action-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            align-items: center;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 6px;
            color: #8892b0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .pagination a:hover {
            background: rgba(168,85,247,0.2);
            color: #a855f7;
        }

        .pagination .active {
            background: rgba(168,85,247,0.3);
            color: #a855f7;
            border-color: #a855f7;
        }

        .pagination .disabled {
            color: #4b5563;
            cursor: not-allowed;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #8892b0;
        }

        .no-data i {
            font-size: 48px;
            color: #a855f7;
            margin-bottom: 15px;
            display: block;
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
                <a href="system-logs.php" class="active"><i class="fas fa-history"></i> Journaux</a>
            </li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            <li><a href="../logout.php" style="color:#ff006e;margin-top:20px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
        <div class="sidebar-bottom">
            <i class="fas fa-crown"></i> <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="page-header">
            <i class="fas fa-history"></i>
            <h1>Journaux d'Activité</h1>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn <?php echo $type === 'superadmin' ? 'active' : ''; ?>" onclick="changeType('superadmin')">
                <i class="fas fa-shield-alt"></i> Super Admins
            </button>
            <button class="tab-btn <?php echo $type === 'admin' ? 'active' : ''; ?>" onclick="changeType('admin')">
                <i class="fas fa-user-shield"></i> Administrateurs
            </button>
        </div>

        <!-- Filter Bar -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">

            <div class="filter-group">
                <label>Recherche</label>
                <input type="text" name="search" placeholder="Action, description..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="filter-group">
                <label>Du</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>

            <div class="filter-group">
                <label>Au</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>

            <button type="submit" class="filter-btn">
                <i class="fas fa-search"></i> Filtrer
            </button>
        </form>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Journaux</h3>
                <div class="value"><?php echo number_format($total); ?></div>
            </div>
            <div class="stat-card">
                <h3>Aujourd'hui</h3>
                <div class="value"><?php echo number_format($today_count); ?></div>
            </div>
            <div class="stat-card">
                <h3>Actions Uniques</h3>
                <div class="value"><?php echo number_format($unique_actions); ?></div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="table-container">
            <?php if (count($logs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th width="150">Acteur</th>
                            <th width="120">Action</th>
                            <th width="250">Description</th>
                            <th width="130">Adresse IP</th>
                            <th width="180">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $i => $log): ?>
                            <tr>
                                <td><?php echo ($offset + $i + 1); ?></td>
                                <td><?php echo htmlspecialchars($log['actor_name'] ?? 'Système'); ?></td>
                                <td>
                                    <span class="action-badge" style="background-color: <?php echo get_action_badge($log['action']); ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Précédent</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1): ?>
                        <a href="?type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=1">1</a>
                        <?php if ($start_page > 2): ?><span>...</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="active"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="?type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?><span>...</span><?php endif; ?>
                        <a href="?type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?type=<?php echo urlencode($type); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&page=<?php echo $page + 1; ?>">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Suivant <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>Aucun journal trouvé</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeType(newType) {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const date_from = new URLSearchParams(window.location.search).get('date_from') || '';
            const date_to = new URLSearchParams(window.location.search).get('date_to') || '';

            let url = '?type=' + newType;
            if (search) url += '&search=' + encodeURIComponent(search);
            if (date_from) url += '&date_from=' + encodeURIComponent(date_from);
            if (date_to) url += '&date_to=' + encodeURIComponent(date_to);

            window.location.href = url;
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
