<?php
/**
 * Dashboard Marketing — KPI promo + raccourci vers la gestion des codes.
 */
require_once __DIR__ . '/includes/auth.php';
require_mkt_auth();
$page_title = 'Tableau de bord';

$stats = ['total' => 0, 'active_now' => 0, 'upcoming' => 0, 'expired' => 0, 'total_uses' => 0];
$top_codes = [];
try {
    $today = date('Y-m-d');
    $r = $pdo->query("
        SELECT
          COUNT(*) AS total,
          SUM(is_active = 1 AND (valid_from IS NULL OR valid_from <= '$today') AND (valid_until IS NULL OR valid_until >= '$today')) AS active_now,
          SUM(valid_from IS NOT NULL AND valid_from > '$today') AS upcoming,
          SUM(valid_until IS NOT NULL AND valid_until < '$today') AS expired,
          COALESCE(SUM(usage_count), 0) AS total_uses
        FROM promo_codes
    ")->fetch();
    if ($r) $stats = array_merge($stats, array_map('intval', $r));

    $top_codes = $pdo->query("SELECT code, description, discount_percent, usage_count, is_active, valid_until
                              FROM promo_codes
                              ORDER BY usage_count DESC LIMIT 8")->fetchAll();
} catch (PDOException $e) {
    error_log('MKT dashboard: ' . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-bullhorn"></i> Tableau de bord — Marketing</h2>
    <a href="pages/promo-codes.php" class="btn btn-accent">
        <i class="fas fa-plus"></i> Gérer les codes promo
    </a>
</div>

<div class="kpi-grid">
    <div class="kpi success">
        <div class="label">Codes actifs maintenant</div>
        <div class="value"><i class="fas fa-tags"></i><?= $stats['active_now'] ?></div>
        <div class="sub">disponibles à l'achat</div>
    </div>
    <div class="kpi warning">
        <div class="label">À venir</div>
        <div class="value"><i class="fas fa-clock"></i><?= $stats['upcoming'] ?></div>
        <div class="sub">programmés pour plus tard</div>
    </div>
    <div class="kpi danger">
        <div class="label">Expirés</div>
        <div class="value"><i class="fas fa-times-circle"></i><?= $stats['expired'] ?></div>
        <div class="sub">à renouveler si besoin</div>
    </div>
    <div class="kpi info">
        <div class="label">Utilisations totales</div>
        <div class="value"><i class="fas fa-shopping-bag"></i><?= $stats['total_uses'] ?></div>
        <div class="sub">commandes avec un code</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-trophy"></i> Top des codes les plus utilisés</h3>
    </div>
    <?php if (empty($top_codes)): ?>
        <p class="text-muted text-center" style="padding:24px">
            Aucun code n'a encore été utilisé.<br>
            <a href="pages/promo-codes.php" class="btn btn-primary btn-sm" style="margin-top:10px">
                <i class="fas fa-plus"></i> Créer le premier code
            </a>
        </p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr><th>Code</th><th>Description</th><th>%</th><th>Utilisations</th><th>Expire le</th><th>État</th></tr>
        </thead>
        <tbody>
        <?php foreach ($top_codes as $c): ?>
        <tr>
            <td><strong style="color:#fde68a;font-family:monospace"><?= htmlspecialchars($c['code']) ?></strong></td>
            <td class="text-muted"><?= htmlspecialchars($c['description'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['discount_percent']) ?>%</td>
            <td><strong><?= (int)$c['usage_count'] ?></strong></td>
            <td class="text-muted" style="font-size:0.85rem"><?= $c['valid_until'] ? date('d/m/Y', strtotime($c['valid_until'])) : '—' ?></td>
            <td><span class="badge <?= $c['is_active'] ? 'badge-success' : 'badge-muted' ?>"><?= $c['is_active'] ? 'Actif' : 'Désactivé' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
