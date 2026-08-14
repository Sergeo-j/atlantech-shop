<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$search       = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$page         = max(1, intval($_GET['page'] ?? 1));
$per_page     = 20;
$offset       = ($page - 1) * $per_page;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($status_filter) {
    $where .= " AND o.status = ?";
    $params[] = $status_filter;
}
if ($payment_filter) {
    $where .= " AND o.payment_method = ?";
    $params[] = $payment_filter;
}
if ($date_from) {
    $where .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

try {
    $total = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
    $total->execute($params);
    $total = $total->fetchColumn();

    $params[] = $offset;
    $params[] = $per_page;
    $stmt = $pdo->prepare("
        SELECT o.*,
               u.name AS user_full_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        $where
        ORDER BY o.created_at DESC
        LIMIT ?, ?
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Stats
    $stats = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(status='pending') as pending,
        SUM(status='paid') as paid,
        SUM(status='shipped') as shipped,
        SUM(status='delivered') as delivered,
        SUM(status='cancelled') as cancelled,
        SUM(total_amount) as revenue_total,
        SUM(CASE WHEN DATE(created_at)=CURDATE() THEN total_amount ELSE 0 END) as revenue_today,
        SUM(DATE(created_at)=CURDATE()) as orders_today
    FROM orders")->fetch();

} catch(Exception $e) {
    $orders = [];
    $total = 0;
    $stats = [];
}

$total_pages = max(1, ceil($total / $per_page));

// Helper: status label + color
function order_status_info($status) {
    return match($status) {
        'pending'   => ['label' => 'En attente',  'color' => '#ffd700',  'bg' => 'rgba(255,215,0,0.15)',    'icon' => 'fa-clock'],
        'paid'      => ['label' => 'Payée',        'color' => '#00d4ff',  'bg' => 'rgba(0,212,255,0.15)',    'icon' => 'fa-check-circle'],
        'shipped'   => ['label' => 'Expédiée',     'color' => '#3b82f6',  'bg' => 'rgba(59,130,246,0.15)',   'icon' => 'fa-truck'],
        'delivered' => ['label' => 'Livrée',       'color' => '#00ff88',  'bg' => 'rgba(0,255,136,0.15)',    'icon' => 'fa-box-check'],
        'cancelled' => ['label' => 'Annulée',      'color' => '#ff006e',  'bg' => 'rgba(255,0,110,0.15)',    'icon' => 'fa-times-circle'],
        default     => ['label' => ucfirst($status), 'color' => '#8892b0','bg' => 'rgba(136,146,176,0.15)', 'icon' => 'fa-question'],
    };
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #020817;
            color: #e6f1ff;
            font-family: 'Rajdhani', sans-serif;
            font-size: 14px;
            line-height: 1.6;
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
            z-index: -1;
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
            z-index: 1000;
        }

        .sidebar-logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
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
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(168,85,247,0.1);
            color: #a855f7;
        }

        .sidebar-menu a.active {
            background: rgba(168,85,247,0.2);
            color: #a855f7;
            border-left: 3px solid #ffd700;
            padding-left: 12px;
        }

        .sidebar-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid rgba(168,85,247,0.2);
            color: #8892b0;
            font-size: 13px;
            text-align: center;
        }

        .sidebar-footer i {
            color: #ffd700;
        }

        .main {
            margin-left: 280px;
            padding: 30px;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #e6f1ff;
        }

        .page-header i {
            font-size: 24px;
            color: #a855f7;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            background: rgba(17,34,64,0.8);
            border-color: rgba(168,85,247,0.6);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }

        .stat-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #8892b0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-bar {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-bar form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            width: 100%;
        }

        .filter-bar input,
        .filter-bar select {
            background: rgba(17,34,64,0.8);
            border: 1px solid rgba(168,85,247,0.3);
            color: #e6f1ff;
            padding: 10px 12px;
            border-radius: 8px;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            outline: none;
            border-color: rgba(168,85,247,0.6);
            background: rgba(17,34,64,0.9);
        }

        .filter-bar input::placeholder {
            color: #5d6b8a;
        }

        .filter-bar button {
            background: #a855f7;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-bar button:hover {
            background: #9333ea;
            transform: translateY(-2px);
        }

        .filter-reset {
            color: #a855f7;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s ease;
        }

        .filter-reset:hover {
            color: #d8b4fe;
        }

        .table-container {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: rgba(17,34,64,0.8);
            padding: 15px;
            text-align: left;
            color: #a855f7;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(168,85,247,0.3);
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid rgba(168,85,247,0.2);
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background: rgba(168,85,247,0.1);
        }

        .order-number {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            color: #a855f7;
            display: block;
            margin-bottom: 5px;
        }

        .order-date {
            color: #5d6b8a;
            font-size: 12px;
        }

        .customer-name {
            font-weight: 600;
            color: #e6f1ff;
            display: block;
            margin-bottom: 3px;
        }

        .customer-contact {
            color: #8892b0;
            font-size: 12px;
            display: block;
            margin-bottom: 2px;
        }

        .amount {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            color: #ffd700;
            display: block;
            margin-bottom: 5px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            width: fit-content;
        }

        .status-select {
            background: rgba(17,34,64,0.8) !important;
            border: 1px solid rgba(168,85,247,0.3) !important;
            color: #e6f1ff !important;
            padding: 5px 8px !important;
            border-radius: 6px !important;
            font-family: 'Rajdhani', sans-serif !important;
            font-size: 13px !important;
            cursor: pointer;
        }

        .payment-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .payment-moneyash { background: rgba(255,165,0,0.2); color: #ffa500; }
        .payment-zelle { background: rgba(0,102,255,0.2); color: #0066ff; }
        .payment-bank { background: rgba(0,200,83,0.2); color: #00c853; }
        .payment-cash { background: rgba(128,128,128,0.2); color: #a0a0a0; }

        .transaction-id {
            color: #8892b0;
            font-size: 11px;
            display: block;
            margin-top: 3px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 8px;
            color: #a855f7;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: rgba(168,85,247,0.2);
            border-color: rgba(168,85,247,0.6);
        }

        .pagination .active {
            background: #a855f7;
            color: #fff;
            border-color: #a855f7;
        }

        .no-orders {
            text-align: center;
            padding: 50px 25px;
            color: #8892b0;
        }

        .no-orders i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #a855f7;
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
            <li><a href="manage_orders.php" class="active"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
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
            <li><a href="../logout.php" style="color:#ff006e;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
        <div class="sidebar-footer">
            <i class="fas fa-crown"></i><br><?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main">
        <div class="page-header">
            <i class="fas fa-shopping-cart"></i>
            <h1>Gestion des Commandes</h1>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-clock stat-icon" style="color:#ffd700;"></i>
                <div class="stat-value"><?php echo intval($stats['pending'] ?? 0); ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon" style="color:#00d4ff;"></i>
                <div class="stat-value"><?php echo intval($stats['paid'] ?? 0); ?></div>
                <div class="stat-label">Payées</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-truck stat-icon" style="color:#3b82f6;"></i>
                <div class="stat-value"><?php echo intval($stats['shipped'] ?? 0); ?></div>
                <div class="stat-label">Expédiées</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-box-check stat-icon" style="color:#00ff88;"></i>
                <div class="stat-value"><?php echo intval($stats['delivered'] ?? 0); ?></div>
                <div class="stat-label">Livrées</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-times-circle stat-icon" style="color:#ff006e;"></i>
                <div class="stat-value"><?php echo intval($stats['cancelled'] ?? 0); ?></div>
                <div class="stat-label">Annulées</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-list stat-icon" style="color:#a855f7;"></i>
                <div class="stat-value"><?php echo intval($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Commandes</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-today stat-icon" style="color:#ffa500;"></i>
                <div class="stat-value"><?php echo intval($stats['orders_today'] ?? 0); ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign stat-icon" style="color:#ffd700;"></i>
                <div class="stat-value"><?php echo number_format($stats['revenue_total'] ?? 0, 2, ',', ' '); ?> HTG</div>
                <div class="stat-label">CA Total</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon" style="color:#00ff88;"></i>
                <div class="stat-value"><?php echo number_format($stats['revenue_today'] ?? 0, 2, ',', ' '); ?> HTG</div>
                <div class="stat-label">CA Aujourd'hui</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; width: 100%;">
                <input type="text" name="search" placeholder="N° commande, client, email..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">

                <select name="status" style="min-width: 140px;">
                    <option value="">Tous les statuts</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Payée</option>
                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                </select>

                <select name="payment" style="min-width: 120px;">
                    <option value="">Tous les paiements</option>
                    <option value="MonCash" <?php echo $payment_filter === 'MonCash' ? 'selected' : ''; ?>>MonCash</option>
                    <option value="Zelle" <?php echo $payment_filter === 'Zelle' ? 'selected' : ''; ?>>Zelle</option>
                    <option value="Bank" <?php echo $payment_filter === 'Bank' ? 'selected' : ''; ?>>Bank</option>
                    <option value="Cash" <?php echo $payment_filter === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                </select>

                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="min-width: 130px;">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="min-width: 130px;">

                <button type="submit">Filtrer</button>

                <a href="manage_orders.php" class="filter-reset">Réinitialiser</a>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-container">
            <?php if (count($orders) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Commande</th>
                            <th>Client</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Paiement</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o):
                            $status_info = order_status_info($o['status']);
                            $payment_class = 'payment-' . strtolower($o['payment_method']);
                        ?>
                            <tr>
                                <td>
                                    <span class="order-number"><?php echo htmlspecialchars($o['order_number']); ?></span>
                                    <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?></span>
                                </td>
                                <td>
                                    <span class="customer-name"><?php echo htmlspecialchars($o['customer_name']); ?></span>
                                    <span class="customer-contact"><?php echo htmlspecialchars($o['customer_email']); ?></span>
                                    <span class="customer-contact"><?php echo htmlspecialchars($o['customer_phone']); ?></span>
                                </td>
                                <td>
                                    <span class="amount"><?php echo number_format($o['total_amount'], 2, ',', ' '); ?> HTG</span>
                                    <form method="POST" action="ajax/update_order_status.php" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="redirect" value="manage_orders.php">
                                        <select name="new_status" onchange="this.form.submit()" class="status-select">
                                            <option value="pending" <?php echo $o['status'] === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                            <option value="paid" <?php echo $o['status'] === 'paid' ? 'selected' : ''; ?>>Payée</option>
                                            <option value="shipped" <?php echo $o['status'] === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                                            <option value="delivered" <?php echo $o['status'] === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                            <option value="cancelled" <?php echo $o['status'] === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="status-badge" style="background:<?php echo $status_info['bg']; ?>; color:<?php echo $status_info['color']; ?>;">
                                        <i class="fas <?php echo $status_info['icon']; ?>"></i>
                                        <?php echo $status_info['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-badge <?php echo $payment_class; ?>">
                                        <?php echo htmlspecialchars($o['payment_method']); ?>
                                    </span>
                                    <?php if (!empty($o['payment_transaction_id'])): ?>
                                        <span class="transaction-id">
                                            <?php echo htmlspecialchars(substr($o['payment_transaction_id'], 0, 15)); ?>
                                            <?php if (strlen($o['payment_transaction_id']) > 15): ?>...<?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($o['created_at'])); ?><br>
                                    <span style="color:#8892b0; font-size:11px;"><?php echo date('H:i', strtotime($o['created_at'])); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-inbox"></i>
                    <p>Aucune commande trouvée</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="manage_orders.php?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $payment_filter ? '&payment=' . urlencode($payment_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>">« Première</a>
                    <a href="manage_orders.php?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $payment_filter ? '&payment=' . urlencode($payment_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>">‹ Précédente</a>
                <?php endif; ?>

                <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);

                    if ($start > 1): ?>
                        <span>...</span>
                    <?php endif;

                    for ($i = $start; $i <= $end; $i++):
                        if ($i == $page):
                            echo '<span class="active">' . $i . '</span>';
                        else:
                            echo '<a href="manage_orders.php?page=' . $i . ($search ? '&search=' . urlencode($search) : '') . ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($payment_filter ? '&payment=' . urlencode($payment_filter) : '') . ($date_from ? '&date_from=' . urlencode($date_from) : '') . ($date_to ? '&date_to=' . urlencode($date_to) : '') . '">' . $i . '</a>';
                        endif;
                    endfor;

                    if ($end < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="manage_orders.php?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $payment_filter ? '&payment=' . urlencode($payment_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>">Suivante ›</a>
                    <a href="manage_orders.php?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $payment_filter ? '&payment=' . urlencode($payment_filter) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>">Dernière »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
