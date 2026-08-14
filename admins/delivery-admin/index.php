<?php
require_once 'includes/auth.php';
check_auth();

// Stats
$toDeliver = $delivering = $deliveredToday = $totalToday = 0;

try {
    // À récupérer chez la prep (colis emballés, prêts à partir)
    $toDeliver    = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='ready_for_delivery'")->fetchColumn();
    // En cours de livraison (livreur a déjà pris)
    $delivering   = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='shipped'")->fetchColumn();
    $deliveredToday = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered' AND DATE(updated_at)=CURDATE()")->fetchColumn();
    $totalToday   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='delivered' AND DATE(created_at)=CURDATE()")->fetchColumn();
} catch (PDOException $e) { error_log($e->getMessage()); }

// Commandes à voir : prêtes (à récupérer) + en cours de livraison (déjà prises)
$orders = [];
try {
    $orders = $pdo->query("
        SELECT id, order_number, customer_name, customer_phone, shipping_address,
               total_amount, payment_method, payment_processor, status, created_at
        FROM orders
        WHERE status IN ('ready_for_delivery','shipped')
        ORDER BY FIELD(status,'ready_for_delivery','shipped'), created_at ASC
    ")->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

$statusInfo = [
    'ready_for_delivery' => ['label'=>'Prêt à livrer', 'color'=>'#10b981', 'bg'=>'#064e3b'],
    'shipped'            => ['label'=>'En livraison',  'color'=>'#f59e0b', 'bg'=>'#451a03'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Livraisons — AtlanTech</title>
<style>
*{box-sizing:border-box}
body{background:#0f0f23;color:#e2e8f0;font-family:'Segoe UI',sans-serif;margin:0;padding:0}
/* Navbar */
.navbar{background:#1e1e3a;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #2d2d50;position:sticky;top:0;z-index:100}
.nav-brand{display:flex;align-items:center;gap:10px}
.nav-brand h1{font-size:1rem;font-weight:800;color:#22c55e;margin:0}
.nav-brand span{font-size:.75rem;color:#64748b}
.nav-right{display:flex;align-items:center;gap:12px}
.nav-user{font-size:.82rem;color:#94a3b8}
.btn-logout{padding:6px 12px;background:#1f2937;color:#f87171;border:1px solid #374151;border-radius:6px;text-decoration:none;font-size:.78rem}
/* Container */
.container{max-width:900px;margin:0 auto;padding:20px 16px}
/* KPI */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
@media(max-width:600px){.kpi-row{grid-template-columns:repeat(2,1fr)}}
.kpi{background:#1e1e3a;border-radius:12px;padding:16px;border:1px solid #2d2d50;text-align:center}
.kpi .val{font-size:1.8rem;font-weight:900;line-height:1}
.kpi .lbl{font-size:.72rem;color:#64748b;margin-top:4px;text-transform:uppercase;letter-spacing:.4px}
.kpi.green .val{color:#22c55e}
.kpi.yellow .val{color:#f59e0b}
.kpi.blue .val{color:#06b6d4}
.kpi.emerald .val{color:#10b981}
/* Section title */
.sec-title{font-size:.85rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:8px}
/* Carte commande */
.order-card{background:#1e1e3a;border-radius:12px;padding:18px;margin-bottom:12px;border:1px solid #2d2d50;transition:border .2s}
.order-card:hover{border-color:#22c55e}
.order-card.urgent{border-left:3px solid #f59e0b}
.card-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;gap:6px}
.order-num{font-size:.85rem;font-weight:800;color:#22c55e;font-family:monospace}
.status-pill{padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.card-info{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
@media(max-width:480px){.card-info{grid-template-columns:1fr}}
.info-item{font-size:.82rem}
.info-item .lbl{color:#64748b;font-size:.72rem;display:block;margin-bottom:2px}
.info-item .val{color:#e2e8f0;font-weight:600}
.info-item .val.phone{color:#22c55e;font-size:.95rem}
.info-item .val.addr{color:#cbd5e1;font-size:.8rem;line-height:1.4}
.card-bottom{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;padding-top:10px;border-top:1px solid #2d2d50}
.total-val{font-size:1.1rem;font-weight:800;color:#22c55e}
.btn-detail{padding:9px 20px;background:#22c55e;color:#000;border:none;border-radius:8px;font-weight:800;font-size:.85rem;cursor:pointer;text-decoration:none;display:inline-block}
.btn-detail:hover{background:#16a34a;color:#fff}
.empty{text-align:center;padding:60px 20px;color:#4b5563}
.empty .icon{font-size:3rem;display:block;margin-bottom:12px}
/* PM badge */
.pm{font-size:.72rem;background:#2d2d50;color:#94a3b8;padding:2px 8px;border-radius:4px}
</style>
</head>
<body>
<nav class="navbar">
    <div class="nav-brand">
        <span style="font-size:1.4rem">🚚</span>
        <div>
            <h1>Livraisons</h1>
            <span>AtlanTech</span>
        </div>
    </div>
    <div class="nav-right">
        <span class="nav-user">👤 <?= htmlspecialchars($_SESSION['delivery_name']) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

    <!-- KPI -->
    <div class="kpi-row">
        <div class="kpi yellow">
            <div class="val"><?= $toDeliver ?></div>
            <div class="lbl">À livrer</div>
        </div>
        <div class="kpi blue">
            <div class="val"><?= $delivering ?></div>
            <div class="lbl">En prépa.</div>
        </div>
        <div class="kpi green">
            <div class="val"><?= $deliveredToday ?></div>
            <div class="lbl">Livrées auj.</div>
        </div>
        <div class="kpi emerald">
            <div class="val" style="font-size:1.1rem"><?= number_format($totalToday, 0, ',', ' ') ?></div>
            <div class="lbl">HTG livrés auj.</div>
        </div>
    </div>

    <!-- Liste commandes -->
    <?php if (empty($orders)): ?>
        <div class="empty">
            <span class="icon">✅</span>
            <p style="font-size:1.1rem;font-weight:700;color:#22c55e">Toutes les livraisons sont à jour !</p>
            <p style="font-size:.85rem;margin-top:6px">Aucune commande en attente de livraison.</p>
        </div>
    <?php else: ?>
        <div class="sec-title">📦 <?= count($orders) ?> commande<?= count($orders)>1?'s':'' ?> à traiter</div>

        <?php foreach ($orders as $o):
            $si = $statusInfo[$o['status']] ?? ['label'=>$o['status'],'color'=>'#94a3b8','bg'=>'#1e1e3a'];
            $isUrgent = $o['status'] === 'shipped';
            $pm = match($o['payment_method']) {
                'MonCash' => '📱 MonCash',
                'Bank'    => '🏦 Virement',
                'Zelle'   => '₮ USDT',
                'Cash'    => '💵 Cash',
                default   => $o['payment_method'],
            };
            if ($o['payment_processor'] && $o['payment_processor'] !== $o['payment_method']) {
                $pm .= ' · '.$o['payment_processor'];
            }
        ?>
        <div class="order-card <?= $isUrgent ? 'urgent' : '' ?>">
            <div class="card-top">
                <div>
                    <div class="order-num"><?= htmlspecialchars($o['order_number']) ?></div>
                    <div style="font-size:.72rem;color:#64748b;margin-top:2px"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
                </div>
                <span class="status-pill" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>"><?= $si['label'] ?></span>
            </div>

            <div class="card-info">
                <div class="info-item">
                    <span class="lbl">👤 Client</span>
                    <span class="val"><?= htmlspecialchars($o['customer_name'] ?? '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="lbl">📞 Téléphone</span>
                    <a href="tel:<?= htmlspecialchars($o['customer_phone'] ?? '') ?>" class="val phone">
                        <?= htmlspecialchars($o['customer_phone'] ?? '—') ?>
                    </a>
                </div>
                <div class="info-item" style="grid-column:1/-1">
                    <span class="lbl">📍 Adresse de livraison</span>
                    <span class="val addr"><?= htmlspecialchars($o['shipping_address'] ?? '—') ?></span>
                </div>
            </div>

            <div class="card-bottom">
                <div>
                    <div class="total-val"><?= number_format($o['total_amount'], 2, ',', ' ') ?> HTG</div>
                    <span class="pm"><?= htmlspecialchars($pm) ?></span>
                </div>
                <a href="delivery-details.php?id=<?= $o['id'] ?>" class="btn-detail">Voir détails →</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
// Auto-refresh toutes les 2 minutes
setTimeout(() => location.reload(), 120000);
</script>
</body>
</html>
