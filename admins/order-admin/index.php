<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Vérifier l'authentification
check_auth();

// Récupérer les statistiques des commandes — chaque requête dans son propre try/catch
$ordersToday = 0;
$pendingOrders = 0;
$paidOrders = 0;
$shippedToday = 0;
$revenueToday = 0;
$urgentOrders = 0;
$recentOrders = [];
$statusStats = [];
$dailyOrders = [];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
    $ordersToday = $stmt->fetch()['total'];
} catch (PDOException $e) { error_log("stat ordersToday: " . $e->getMessage()); }

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $pendingOrders = $stmt->fetch()['total'];
} catch (PDOException $e) { error_log("stat pendingOrders: " . $e->getMessage()); }

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'paid'");
    $paidOrders = $stmt->fetch()['total'];
} catch (PDOException $e) { error_log("stat paidOrders: " . $e->getMessage()); }

try {
    // Expédiées aujourd'hui (statut shipped, créées aujourd'hui)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'shipped' AND DATE(created_at) = CURDATE()");
    $shippedToday = $stmt->fetch()['total'];
} catch (PDOException $e) { error_log("stat shippedToday: " . $e->getMessage()); }

try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'");
    $revenueToday = $stmt->fetch()['revenue'];
} catch (PDOException $e) { error_log("stat revenueToday: " . $e->getMessage()); }

// Commandes "urgentes" = pending depuis plus de 24h
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $urgentOrders = $stmt->fetch()['total'];
} catch (PDOException $e) { error_log("stat urgentOrders: " . $e->getMessage()); }

// Dernières commandes — u.name corrigé (pas u.username)
try {
    $stmt = $pdo->query("SELECT o.*, u.name AS username, u.email as user_email
                        FROM orders o
                        LEFT JOIN users u ON o.user_id = u.id
                        ORDER BY o.created_at DESC
                        LIMIT 10");
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) { error_log("stat recentOrders: " . $e->getMessage()); }

try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count
                        FROM orders
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
                        GROUP BY status");
    $statusStats = $stmt->fetchAll();
} catch (PDOException $e) { error_log("stat statusStats: " . $e->getMessage()); }

try {
    $stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count
                        FROM orders
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC");
    $dailyOrders = $stmt->fetchAll();
} catch (PDOException $e) { error_log("stat dailyOrders: " . $e->getMessage()); }

// ── Ventes : jour / semaine / mois ──────────────────────────────
$salesPeriods = [];
try {
    $stmt = $pdo->query("
        SELECT
            SUM(DATE(created_at) = CURDATE()) AS day_count,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status != 'cancelled' THEN total_amount END), 0) AS day_revenue,
            SUM(DATE(created_at) = CURDATE() AND status = 'delivered') AS day_delivered,
            SUM(DATE(created_at) = CURDATE() AND status = 'cancelled') AS day_cancelled,
            SUM(YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)) AS week_count,
            COALESCE(SUM(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) AND status != 'cancelled' THEN total_amount END), 0) AS week_revenue,
            SUM(YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) AND status = 'delivered') AS week_delivered,
            SUM(YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) AND status = 'cancelled') AS week_cancelled,
            SUM(MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) AS month_count,
            COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status != 'cancelled' THEN total_amount END), 0) AS month_revenue,
            SUM(MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'delivered') AS month_delivered,
            SUM(MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'cancelled') AS month_cancelled
        FROM orders
    ");
    $salesPeriods = $stmt->fetch();
} catch (PDOException $e) { error_log("stat salesPeriods: " . $e->getMessage()); }

// Détail par jour : 30 derniers jours
$dailyBreakdown = [];
try {
    $stmt = $pdo->query("
        SELECT
            DATE(created_at) AS jour,
            COUNT(*) AS nb_commandes,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount END), 0) AS chiffre_affaires,
            SUM(status = 'delivered') AS livrees,
            SUM(status = 'pending')   AS en_attente,
            SUM(status = 'paid')      AS payees,
            SUM(status = 'cancelled') AS annulees
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY jour DESC
    ");
    $dailyBreakdown = $stmt->fetchAll();
} catch (PDOException $e) { error_log("stat dailyBreakdown: " . $e->getMessage()); }

// Paiements ce mois
$paymentBreakdown = [];
try {
    $stmt = $pdo->query("
        SELECT
            payment_method, payment_processor,
            COUNT(*) AS nb,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount END), 0) AS total
        FROM orders
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
        GROUP BY payment_method, payment_processor
        ORDER BY total DESC
    ");
    $paymentBreakdown = $stmt->fetchAll();
} catch (PDOException $e) { error_log("stat paymentBreakdown: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Admin - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── Tableau des ventes ───────────────────────── */
        .sales-section-title { color: #e2e8f0; font-size: 1rem; font-weight: 700; margin: 28px 0 14px; display: flex; align-items: center; gap: 8px; }
        .sales-section-title span { color: #94a3b8; font-size: .8rem; font-weight: 400; }
        .sales-periods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
        @media(max-width:768px){ .sales-periods { grid-template-columns: 1fr; } }
        .sp-card { background: #1e1e3a; border-radius: 12px; overflow: hidden; border: 1px solid #2d2d50; }
        .sp-head { padding: 12px 16px; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #fff; display: flex; align-items: center; gap: 7px; }
        .sp-head.day   { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .sp-head.week  { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
        .sp-head.month { background: linear-gradient(135deg, #10b981, #065f46); }
        .sp-body { padding: 14px 16px; }
        .sp-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #2d2d50; font-size: .85rem; }
        .sp-row:last-child { border-bottom: none; }
        .sp-lbl { color: #94a3b8; }
        .sp-val { font-weight: 700; color: #e2e8f0; }
        .sp-val.revenue  { color: #10b981; font-size: .95rem; }
        .sp-val.danger   { color: #ef4444; }
        .sp-val.success  { color: #6ee7b7; }

        .sales-detail-grid { display: grid; grid-template-columns: 3fr 2fr; gap: 16px; margin-bottom: 28px; }
        @media(max-width:900px){ .sales-detail-grid { grid-template-columns: 1fr; } }
        .sales-table-card { background: #1e1e3a; border-radius: 12px; border: 1px solid #2d2d50; overflow: hidden; }
        .sales-table-head { padding: 14px 18px; border-bottom: 1px solid #2d2d50; font-weight: 700; color: #e2e8f0; font-size: .9rem; }
        .s-table { width: 100%; border-collapse: collapse; }
        .s-table thead th { padding: 10px 14px; font-size: .72rem; text-transform: uppercase; color: #64748b; background: #16162e; text-align: left; border-bottom: 1px solid #2d2d50; }
        .s-table tbody td { padding: 9px 14px; font-size: .82rem; color: #cbd5e1; border-bottom: 1px solid #2d2d50; }
        .s-table tbody tr:last-child td { border-bottom: none; }
        .s-table tbody tr:hover { background: #252545; }
        .s-table tbody tr.today-row { background: #1a2744; }
        .s-table tbody tr.today-row td { color: #93c5fd; font-weight: 600; }
        .bar-wrap { display: flex; align-items: center; gap: 8px; }
        .bar-bg { flex: 1; background: #2d2d50; border-radius: 4px; height: 7px; overflow: hidden; min-width: 50px; }
        .bar-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #8b5cf6, #3b82f6); }
        .pm-pill { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: .72rem; font-weight: 600; background: #2d2d50; color: #a78bfa; margin-bottom: 4px; }
        .pm-bar-wrap { margin-top: 6px; }
        .pm-bar-bg { background: #2d2d50; border-radius: 4px; height: 8px; overflow: hidden; }
        .pm-bar-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #10b981, #3b82f6); }
        .scroll-tbody { max-height: 320px; overflow-y: auto; display: block; }
        .scroll-tbody tr { display: table; width: 100%; table-layout: fixed; }
        .s-table thead tr { display: table; width: 100%; table-layout: fixed; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>🛒 ORDER ADMIN</h1>
                <span class="nav-subtitle">Gestion des Commandes</span>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-link active">📊 Dashboard</a>
                <a href="orders.php" class="nav-link">📦 Commandes</a>
                <a href="preparing.php" class="nav-link">🔧 Préparation</a>
                <a href="returns.php" class="nav-link">↩️ Retours</a>
                <div class="nav-user">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                    <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h2>📊 Tableau de Bord</h2>
            <p class="text-muted">Vue d'ensemble des commandes - <?php echo date('d/m/Y H:i'); ?></p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <h3><?php echo number_format($ordersToday); ?></h3>
                    <p>Commandes aujourd'hui</p>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo number_format($pendingOrders); ?></h3>
                    <p>En attente de paiement</p>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">✓</div>
                <div class="stat-content">
                    <h3><?php echo number_format($paidOrders); ?></h3>
                    <p>Payées - À préparer</p>
                </div>
            </div>

            <div class="stat-card stat-info">
                <div class="stat-icon">🚚</div>
                <div class="stat-content">
                    <h3><?php echo number_format($shippedToday); ?></h3>
                    <p>Expédiées aujourd'hui</p>
                </div>
            </div>

            <div class="stat-card stat-gold">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3><?php echo number_format($revenueToday, 2); ?> HTG</h3>
                    <p>Revenus du jour</p>
                </div>
            </div>

            <div class="stat-card stat-danger">
                <div class="stat-icon">🔥</div>
                <div class="stat-content">
                    <h3><?php echo number_format($urgentOrders); ?></h3>
                    <p>En attente +24h</p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-row">
            <div class="chart-card">
                <h3>📈 Commandes par statut (7 derniers jours)</h3>
                <canvas id="statusChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>📊 Évolution quotidienne</h3>
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <!-- ══ TABLEAU DES VENTES ══════════════════════════ -->
        <?php
        $sp = $salesPeriods ?: [];
        function spf(mixed $v, int $d=0): string { return number_format((float)$v, $d, ',', ' '); }
        ?>

        <div class="sales-section-title">
            📊 Tableau des ventes
            <span>— <?php echo date('d/m/Y H:i'); ?></span>
        </div>

        <!-- 3 blocs Jour / Semaine / Mois -->
        <div class="sales-periods">

            <!-- AUJOURD'HUI -->
            <div class="sp-card">
                <div class="sp-head day">📅 Aujourd'hui</div>
                <div class="sp-body">
                    <div class="sp-row"><span class="sp-lbl">Commandes</span><span class="sp-val"><?php echo spf($sp['day_count'] ?? 0); ?></span></div>
                    <div class="sp-row"><span class="sp-lbl">Chiffre d'affaires</span><span class="sp-val revenue"><?php echo spf($sp['day_revenue'] ?? 0, 0); ?> HTG</span></div>
                    <div class="sp-row"><span class="sp-lbl">Livrées</span><span class="sp-val success">✓ <?php echo spf($sp['day_delivered'] ?? 0); ?></span></div>
                    <div class="sp-row"><span class="sp-lbl">Annulées</span><span class="sp-val danger">✕ <?php echo spf($sp['day_cancelled'] ?? 0); ?></span></div>
                    <?php
                        $dc = (int)($sp['day_count'] ?? 0);
                        $da = (int)($sp['day_cancelled'] ?? 0);
                        $taux = $dc > 0 ? round($da / $dc * 100) : 0;
                    ?>
                    <div class="sp-row"><span class="sp-lbl">Taux annulation</span><span class="sp-val <?php echo $taux > 20 ? 'danger' : ''; ?>"><?php echo $taux; ?>%</span></div>
                </div>
            </div>

            <!-- CETTE SEMAINE -->
            <div class="sp-card">
                <div class="sp-head week">📆 Cette semaine</div>
                <div class="sp-body">
                    <div class="sp-row"><span class="sp-lbl">Commandes</span><span class="sp-val"><?php echo spf($sp['week_count'] ?? 0); ?></span></div>
                    <div class="sp-row"><span class="sp-lbl">Chiffre d'affaires</span><span class="sp-val revenue"><?php echo spf($sp['week_revenue'] ?? 0, 0); ?> HTG</span></div>
                    <div class="sp-row"><span class="sp-lbl">Livrées</span><span class="sp-val success">✓ <?php echo spf($sp['week_delivered'] ?? 0); ?></span></div>
                    <div class="sp-row"><span class="sp-lbl">Annulées</span><span class="sp-val danger">✕ <?php echo spf($sp['week_cancelled'] ?? 0); ?></span></div>
                    <?php
                        $wc = (int)($sp['week_count'] ?? 0);
                        $wa = (int)($sp['week_cancelled'] ?? 0);
                        $taux = $wc > 0 ? round($wa / $wc * 100) : 0;
                    ?>
                    <div class="sp-row"><span class="sp-lbl">Taux annulation</span><span class="sp-val <?php echo $taux > 20 ? 'danger' : ''; ?>"><?php echo $taux; ?>%</span></div>
                </div>
            </div>

            <!-- CE MOIS -->
            <div class="sp-card">
                <div class="sp-head month">🗓️ <?php echo date('F Y'); ?></div>
                <div class="sp-body">
                    <div class="sp-row"><span class="sp-lbl">Commandes</span><span class="sp-val"><?php echo spf($sp['month_count'] ?? 0); ?></span></div>
                    <div class="sp-row"><span class="sp-lbl">Chiffre d'affaires</span><span class="sp-val revenue"><?php echo spf($sp['month_revenue'] ?? 0, 0); ?> HTG</span></div>
                    <div class="sp-row"><span class="sp-lbl">Livrées</span><span class="sp-val success">✓ <?php echo spf($sp['month_delivered'] ?? 0); ?></span></div>
                    <div class="sp-row"><span class="sp-lbl">Annulées</span><span class="sp-val danger">✕ <?php echo spf($sp['month_cancelled'] ?? 0); ?></span></div>
                    <?php
                        $mc = (int)($sp['month_count'] ?? 0);
                        $ma = (int)($sp['month_cancelled'] ?? 0);
                        $taux = $mc > 0 ? round($ma / $mc * 100) : 0;
                    ?>
                    <div class="sp-row"><span class="sp-lbl">Taux annulation</span><span class="sp-val <?php echo $taux > 20 ? 'danger' : ''; ?>"><?php echo $taux; ?>%</span></div>
                </div>
            </div>
        </div>

        <!-- Détail par jour + paiements du mois -->
        <div class="sales-detail-grid">

            <!-- Tableau journalier 30j -->
            <div class="sales-table-card">
                <div class="sales-table-head">📅 Ventes par jour (30 derniers jours)</div>
                <?php $maxCA = max(array_column($dailyBreakdown ?: [[0]], 'chiffre_affaires') ?: [1]); ?>
                <div style="overflow-x:auto;">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th style="text-align:center">Cmds</th>
                            <th>CA (HTG)</th>
                            <th style="text-align:center">Livrées</th>
                            <th style="text-align:center">Payées</th>
                            <th style="text-align:center">Attente</th>
                            <th style="text-align:center">Annul.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dailyBreakdown)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:30px;color:#4b5563;">Aucune donnée</td></tr>
                        <?php else: ?>
                        <?php foreach ($dailyBreakdown as $row):
                            $isToday = ($row['jour'] === date('Y-m-d'));
                            $pct = $maxCA > 0 ? round($row['chiffre_affaires'] / $maxCA * 100) : 0;
                        ?>
                            <tr <?php echo $isToday ? 'class="today-row"' : ''; ?>>
                                <td style="white-space:nowrap;">
                                    <?php echo $isToday ? '★ ' : ''; ?>
                                    <?php echo date('d/m/Y', strtotime($row['jour'])); ?>
                                    <?php echo $isToday ? '<small style="color:#60a5fa;"> Auj.</small>' : ''; ?>
                                </td>
                                <td style="text-align:center;font-weight:700;"><?php echo $row['nb_commandes']; ?></td>
                                <td>
                                    <div class="bar-wrap">
                                        <span style="min-width:72px;font-weight:700;color:#10b981;"><?php echo spf($row['chiffre_affaires'], 0); ?></span>
                                        <div class="bar-bg"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                    </div>
                                </td>
                                <td style="text-align:center;color:#6ee7b7;"><?php echo $row['livrees'] ?: '—'; ?></td>
                                <td style="text-align:center;color:#34d399;"><?php echo $row['payees'] ?: '—'; ?></td>
                                <td style="text-align:center;color:#f59e0b;"><?php echo $row['en_attente'] ?: '—'; ?></td>
                                <td style="text-align:center;color:#ef4444;"><?php echo $row['annulees'] ?: '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Paiements ce mois -->
            <div class="sales-table-card">
                <div class="sales-table-head">💳 Paiements — <?php echo date('F Y'); ?></div>
                <div style="padding:16px;">
                    <?php
                    $pmLabels = [
                        'MonCash' => '📱 MonCash / NatCash',
                        'Bank'    => '🏦 Virement bancaire',
                        'Zelle'   => '₮ USDT',
                        'Cash'    => '💵 Cash',
                    ];
                    $totalRev = array_sum(array_column($paymentBreakdown ?: [], 'total')) ?: 1;
                    ?>
                    <?php if (empty($paymentBreakdown)): ?>
                        <p style="color:#4b5563;text-align:center;padding:30px 0;">Aucune vente ce mois</p>
                    <?php else: ?>
                    <?php foreach ($paymentBreakdown as $pm):
                        $lbl = $pmLabels[$pm['payment_method']] ?? $pm['payment_method'];
                        if ($pm['payment_processor'] && $pm['payment_processor'] !== $pm['payment_method']) {
                            $lbl .= ' · ' . $pm['payment_processor'];
                        }
                        $pct = round($pm['total'] / $totalRev * 100);
                    ?>
                        <div style="margin-bottom:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <span class="pm-pill"><?php echo htmlspecialchars($lbl); ?></span>
                                <span style="font-size:.8rem;font-weight:700;color:#e2e8f0;"><?php echo $pm['nb']; ?> cmd</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                                <span style="font-size:.85rem;color:#10b981;font-weight:700;"><?php echo spf($pm['total'], 0); ?> HTG</span>
                                <span style="font-size:.78rem;color:#64748b;"><?php echo $pct; ?>% du CA</span>
                            </div>
                            <div class="pm-bar-bg"><div class="pm-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- FIN TABLEAU DES VENTES ═══════════════════════ -->

        <!-- Recent Orders Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>📋 Dernières commandes</h3>
                <a href="orders.php" class="btn btn-primary">Voir tout</a>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucune commande récente</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name'] ?? $order['username'] ?? 'N/A'); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email'] ?? $order['user_email'] ?? ''); ?></small>
                                    </td>
                                    <td><strong><?php echo number_format($order['total_amount'], 2); ?> HTG</strong></td>
                                    <td><span class="badge badge-payment"><?php echo htmlspecialchars($order['payment_method']); ?></span></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'shipped' => 'info',
                                            'delivered' => 'primary',
                                            'cancelled' => 'danger'
                                        ];
                                        $statusLabel = [
                                            'pending' => 'En attente',
                                            'paid' => 'Payée',
                                            'shipped' => 'Expédiée',
                                            'delivered' => 'Livrée',
                                            'cancelled' => 'Annulée'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass[$order['status']] ?? 'secondary'; ?>">
                                            <?php echo $statusLabel[$order['status']] ?? $order['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Données pour les graphiques
        const statusData = <?php echo json_encode($statusStats); ?>;
        const dailyData = <?php echo json_encode($dailyOrders); ?>;

        // Chart par statut
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => {
                    const labels = {
                        'pending': 'En attente',
                        'paid': 'Payées',
                        'shipped': 'Expédiées',
                        'delivered': 'Livrées',
                        'cancelled': 'Annulées'
                    };
                    return labels[s.status] || s.status;
                }),
                datasets: [{
                    data: statusData.map(s => s.count),
                    backgroundColor: [
                        '#f59e0b', // warning - pending
                        '#10b981', // success - paid
                        '#06b6d4', // info - shipped
                        '#8b5cf6', // primary - delivered
                        '#ef4444'  // danger - cancelled
                    ],
                    borderWidth: 2,
                    borderColor: '#1a1a2e'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#e2e8f0',
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Chart quotidien
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [{
                    label: 'Commandes',
                    data: dailyData.map(d => d.count),
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#94a3b8',
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(45, 45, 68, 0.5)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            color: 'rgba(45, 45, 68, 0.5)'
                        }
                    }
                }
            }
        });

        // Auto-refresh toutes les 60 secondes
        setTimeout(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
