<?php
/**
 * Dashboard — DG Admin
 * Vue d'ensemble : admins actifs, commissions du mois, commandes, tarifs livraison.
 */
require_once __DIR__ . '/includes/auth.php';
require_dg_auth();

$page_title = 'Tableau de bord';

// ─── KPI : récupération en une passe ──────────────────────────────────────
$kpi = [
    'total_admins'        => 0,
    'active_admins'       => 0,
    'inactive_admins'     => 0,
    'orders_this_month'   => 0,
    'revenue_this_month'  => 0.0,
    'commissions_pending' => 0.0,
    'commissions_paid'    => 0.0,
    'shipping_rates'      => 0,
];

try {
    // Admins (hors DG/super_admin lui-même)
    $r = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(is_active = 1) AS actifs,
            SUM(is_active = 0) AS inactifs
        FROM admins
        WHERE admin_role_id != " . (int)DG_ROLE_ID . "
    ")->fetch();
    $kpi['total_admins']    = (int)$r['total'];
    $kpi['active_admins']   = (int)$r['actifs'];
    $kpi['inactive_admins'] = (int)$r['inactifs'];

    // Commandes du mois
    $r = $pdo->query("
        SELECT
            COUNT(*) AS nb,
            COALESCE(SUM(total_amount), 0) AS rev
        FROM orders
        WHERE YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ")->fetch();
    $kpi['orders_this_month']  = (int)$r['nb'];
    $kpi['revenue_this_month'] = (float)$r['rev'];

    // Commissions (si la table existe)
    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'admin_commissions'")->fetch();
    if ($tableExists) {
        $r = $pdo->query("
            SELECT
                COALESCE(SUM(CASE WHEN status='pending'  THEN commission_amount ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status='paid'     THEN commission_amount ELSE 0 END), 0) AS paid
            FROM admin_commissions
        ")->fetch();
        $kpi['commissions_pending'] = (float)$r['pending'];
        $kpi['commissions_paid']    = (float)$r['paid'];
    }

    // Tarifs livraison
    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'shipping_rates'")->fetch();
    if ($tableExists) {
        $kpi['shipping_rates'] = (int) $pdo->query("SELECT COUNT(*) FROM shipping_rates WHERE is_active=1")->fetchColumn();
    }
} catch (PDOException $e) {
    error_log('DG dashboard KPI error: ' . $e->getMessage());
}

// Dernières activités admin (5)
$recent_activity = [];
try {
    $stmt = $pdo->query("
        SELECT l.id, l.admin_id, l.action, l.module, l.description, l.ip_address, l.created_at,
               a.full_name, a.email, r.role_name
        FROM admin_activity_logs l
        LEFT JOIN admins      a ON l.admin_id = a.id
        LEFT JOIN admin_roles r ON a.admin_role_id = r.id
        ORDER BY l.created_at DESC
        LIMIT 8
    ");
    $recent_activity = $stmt->fetchAll();
} catch (PDOException $e) { /* table peut ne pas exister */ }

// Dernières commandes (5)
$recent_orders = [];
try {
    $stmt = $pdo->query("
        SELECT id, order_number, customer_name, total_amount, status, payment_method, created_at
        FROM orders
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();
} catch (PDOException $e) {}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-chart-line"></i> Tableau de bord</h2>
    <span class="text-muted"><?= date('l j F Y, H:i') ?></span>
</div>

<!-- KPI -->
<div class="kpi-grid">
    <div class="kpi">
        <div class="label">Admins actifs</div>
        <div class="value"><i class="fas fa-users"></i><?= $kpi['active_admins'] ?></div>
        <div class="sub"><?= $kpi['inactive_admins'] ?> désactivé(s) · <?= $kpi['total_admins'] ?> au total</div>
    </div>
    <div class="kpi info">
        <div class="label">Commandes (mois)</div>
        <div class="value"><i class="fas fa-shopping-bag"></i><?= $kpi['orders_this_month'] ?></div>
        <div class="sub"><?= number_format($kpi['revenue_this_month'], 0, ',', ' ') ?> HTG de revenu</div>
    </div>
    <div class="kpi warning">
        <div class="label">Commissions en attente</div>
        <div class="value"><i class="fas fa-clock"></i><?= number_format($kpi['commissions_pending'], 0, ',', ' ') ?></div>
        <div class="sub">HTG à valider / payer</div>
    </div>
    <div class="kpi success">
        <div class="label">Commissions payées</div>
        <div class="value"><i class="fas fa-check-circle"></i><?= number_format($kpi['commissions_paid'], 0, ',', ' ') ?></div>
        <div class="sub">HTG distribués</div>
    </div>
    <div class="kpi">
        <div class="label">Tarifs livraison actifs</div>
        <div class="value"><i class="fas fa-truck"></i><?= $kpi['shipping_rates'] ?></div>
        <div class="sub">villes configurées</div>
    </div>
</div>

<!-- Activité récente -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Activité récente des admins</h3>
        <a href="pages/activity-log.php" class="btn btn-ghost btn-sm">Voir tout</a>
    </div>

    <?php if (empty($recent_activity)): ?>
        <p class="text-muted text-center" style="padding:20px">Aucune activité enregistrée pour le moment.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th>Date</th>
            <th>Admin</th>
            <th>Rôle</th>
            <th>Action</th>
            <th>Module</th>
            <th>Description</th>
            <th>IP</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($recent_activity as $log): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['full_name'] ?? 'Inconnu') ?></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($log['role_name'] ?? '—') ?></span></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><span class="badge badge-muted"><?= htmlspecialchars($log['module'] ?? '—') ?></span></td>
                <td class="text-muted"><?= htmlspecialchars(mb_substr($log['description'] ?? '', 0, 60)) ?></td>
                <td class="text-muted" style="font-size:0.8rem"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Dernières commandes -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-receipt"></i> Dernières commandes</h3>
    </div>

    <?php if (empty($recent_orders)): ?>
        <p class="text-muted text-center" style="padding:20px">Aucune commande pour le moment.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th>N°</th>
            <th>Client</th>
            <th>Total</th>
            <th>Paiement</th>
            <th>Statut</th>
            <th>Date</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $statusColors = [
            'pending'   => 'warning',
            'paid'      => 'info',
            'shipped'   => 'info',
            'delivered' => 'success',
            'cancelled' => 'danger',
        ];
        foreach ($recent_orders as $o):
            $color = $statusColors[$o['status']] ?? 'muted';
        ?>
            <tr>
                <td><strong><?= htmlspecialchars($o['order_number'] ?? ('#'.$o['id'])) ?></strong></td>
                <td><?= htmlspecialchars($o['customer_name'] ?? '—') ?></td>
                <td><?= number_format((float)$o['total_amount'], 0, ',', ' ') ?> HTG</td>
                <td class="text-muted"><?= htmlspecialchars($o['payment_method'] ?: '—') ?></td>
                <td><span class="badge badge-<?= $color ?>"><?= htmlspecialchars($o['status']) ?></span></td>
                <td class="text-muted"><?= htmlspecialchars($o['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
