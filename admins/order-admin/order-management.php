<?php
/**
 * Order Management — Tableau Kanban par statut
 *
 * Page opérationnelle pour traiter les commandes actives au quotidien :
 *   - Vue kanban (5 colonnes : pending / paid / processing / shipped / delivered récentes)
 *   - Actions rapides (1 clic pour passer au statut suivant)
 *   - Email client + historique automatiques
 *   - Mise en évidence des commandes > 24h (urgent)
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
check_auth();

require_once __DIR__ . '/../../config/order_emails.php';

// ══════════════════════════════════════════════════════════════════
//  AJAX : avancer/changer le statut (avec note optionnelle)
// ══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $ALLOWED = ['pending','paid','processing','shipped','delivered','cancelled'];

    if ($_POST['action'] === 'quick_update') {
        $oid  = (int)($_POST['order_id'] ?? 0);
        $ns   = $_POST['status'] ?? '';
        $note = trim($_POST['note'] ?? '');
        if ($oid <= 0 || !in_array($ns, $ALLOWED, true)) {
            echo json_encode(['success' => false, 'message' => 'Paramètres invalides']); exit;
        }
        try {
            $stCur = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stCur->execute([$oid]);
            $o = $stCur->fetch();
            if (!$o) { echo json_encode(['success'=>false,'message'=>'Commande introuvable']); exit; }
            $old = $o['status'] ?? null;
            if ($old === $ns) { echo json_encode(['success'=>true,'message'=>'Statut inchangé']); exit; }

            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$ns, $oid]);

            $admin_id   = (int)($_SESSION['admin_id']   ?? 0);
            $admin_name = (string)($_SESSION['admin_name'] ?? 'Admin');
            record_order_status_change($oid, $old, $ns, $admin_id, $admin_name, $note);
            log_admin_action($admin_id, 'quick_update_status',
                "Commande #{$o['order_number']} : {$old} → {$ns}" . ($note ? " ({$note})" : ''),
                $oid
            );

            $email_sent = false;
            try {
                $email_sent = sendOrderStatusEmailToCustomer($o, (string)$old, $ns, $note);
                if ($email_sent) {
                    $pdo->prepare("UPDATE order_status_history SET email_sent = 1 WHERE order_id = ? ORDER BY id DESC LIMIT 1")
                        ->execute([$oid]);
                }
            } catch (\Throwable $e) { error_log('quick_update email: '.$e->getMessage()); }

            echo json_encode(['success'=>true, 'email_sent'=>$email_sent, 'new_status'=>$ns]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
        }
        exit;
    }
    echo json_encode(['success'=>false, 'message'=>'Action inconnue']); exit;
}

// ══════════════════════════════════════════════════════════════════
//  Charger les commandes par statut (hors cancelled/delivered anciennes)
// ══════════════════════════════════════════════════════════════════
$columns = [
    'pending'    => ['label'=>'En attente', 'icon'=>'⏳', 'color'=>'#f59e0b', 'next'=>'paid',       'next_label'=>'✅ Marquer Payée',    'desc'=>'À valider / recevoir le paiement'],
    'paid'       => ['label'=>'Payées',      'icon'=>'✅', 'color'=>'#10b981', 'next'=>'processing', 'next_label'=>'🔧 Préparer',          'desc'=>'Prêtes à préparer'],
    'processing' => ['label'=>'Préparation', 'icon'=>'🔧', 'color'=>'#06b6d4', 'next'=>'shipped',    'next_label'=>'🚚 Expédier',          'desc'=>'En cours d\'emballage'],
    'shipped'    => ['label'=>'Expédiées',   'icon'=>'🚚', 'color'=>'#8b5cf6', 'next'=>'delivered',  'next_label'=>'📦 Marquer Livrée',   'desc'=>'En livraison'],
    'delivered'  => ['label'=>'Livrées',     'icon'=>'📦', 'color'=>'#059669', 'next'=>null,         'next_label'=>null,                     'desc'=>'Récentes (7 derniers jours)'],
];

$ordersByStatus = array_fill_keys(array_keys($columns), []);
$itemsCountByOrder = [];

try {
    // On charge :
    //  - toutes les commandes actives (pending, paid, processing, shipped)
    //  - + delivered des 7 derniers jours
    $sql = "SELECT o.*, u.name AS user_full_name,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS nb_items
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.status IN ('pending','paid','processing','shipped')
               OR (o.status = 'delivered' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
            ORDER BY o.created_at ASC";
    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $r) {
        $s = $r['status'];
        if (!isset($ordersByStatus[$s])) continue;
        $ordersByStatus[$s][] = $r;
        $itemsCountByOrder[(int)$r['id']] = (int)($r['nb_items'] ?? 0);
    }
} catch (PDOException $e) {
    error_log('order-management query: '.$e->getMessage());
}

// Compter les commandes "urgentes" (pending > 24h)
$urgentCount = 0;
$now = time();
foreach ($ordersByStatus['pending'] as $p) {
    if ($now - strtotime($p['created_at']) >= 86400) $urgentCount++;
}

// Paiement labels
$paymentIcons = [
    'MonCash' => '📱', 'Zelle' => '₮', 'Bank' => '🏦', 'Cash' => '💵'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Management — AtlanTech</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* ── Topbar de synthèse ─────────────────────────────────────────── */
.om-topbar { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:18px; align-items:center; }
.om-stat   { background:#1e1e3a; border:1px solid #2d2d50; border-radius:10px; padding:12px 18px; font-size:.82rem; color:#94a3b8; }
.om-stat strong { color:#e2e8f0; font-size:1.05rem; display:block; margin-bottom:2px; }
.om-stat.urgent { border-color:#ef4444; background:linear-gradient(135deg,#2d0f0f,#1e1e3a); }
.om-stat.urgent strong { color:#ef4444; }
.om-refresh { margin-left:auto; background:#8b5cf6; color:#fff; border:none; padding:9px 16px; border-radius:7px; cursor:pointer; font-size:13px; font-weight:600; }
.om-refresh:hover { background:#7c3aed; }

/* ── Kanban ─────────────────────────────────────────────────────── */
.kanban { display:grid; grid-template-columns:repeat(5, 1fr); gap:14px; min-height:500px; }
@media(max-width:1400px){ .kanban{ grid-template-columns:repeat(3,1fr); } }
@media(max-width:900px) { .kanban{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:600px) { .kanban{ grid-template-columns:1fr; } }

.kcol { background:#16162e; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; border:1px solid #2d2d50; min-height:200px; }
.kcol-head { padding:10px 14px; font-weight:700; font-size:.78rem; text-transform:uppercase; letter-spacing:.4px; color:#fff; display:flex; align-items:center; gap:8px; }
.kcol-head .count { margin-left:auto; background:rgba(255,255,255,.28); padding:2px 9px; border-radius:12px; font-size:.78rem; font-weight:700; }
.kcol-desc { padding:6px 14px 10px; color:#64748b; font-size:.7rem; font-style:italic; border-bottom:1px solid #2d2d50; }
.kcol-body { flex:1; padding:10px; display:flex; flex-direction:column; gap:10px; overflow-y:auto; max-height:calc(100vh - 340px); }
.kcol-body::-webkit-scrollbar { width:6px; }
.kcol-body::-webkit-scrollbar-thumb { background:#3d3d6b; border-radius:3px; }
.kcol-empty { color:#4b5563; font-size:.78rem; text-align:center; padding:30px 10px; font-style:italic; }

/* ── Carte commande ─────────────────────────────────────────────── */
.kcard { background:#1e1e3a; border:1px solid #2d2d50; border-radius:9px; padding:11px 12px; font-size:.82rem; transition:all .15s; position:relative; }
.kcard:hover { border-color:#8b5cf6; transform:translateY(-1px); box-shadow:0 4px 10px rgba(0,0,0,.25); }
.kcard.urgent { border-color:#ef4444; background:linear-gradient(135deg,#2d0f0f,#1e1e3a); }
.kcard .order-num { font-family:monospace; font-weight:700; color:#a78bfa; font-size:.76rem; }
.kcard .cust-name { color:#e2e8f0; font-weight:700; margin-top:3px; font-size:.88rem; }
.kcard .meta { display:flex; gap:8px; flex-wrap:wrap; margin-top:5px; font-size:.72rem; color:#94a3b8; }
.kcard .meta span { display:inline-flex; align-items:center; gap:3px; }
.kcard .total { color:#10b981; font-weight:700; font-size:.88rem; margin-top:5px; }
.kcard .age { color:#64748b; font-size:.7rem; }
.kcard .age.old { color:#ef4444; font-weight:700; }
.kcard .actions { display:flex; gap:6px; margin-top:9px; flex-wrap:wrap; }
.kcard-btn { flex:1; padding:6px 8px; border:none; border-radius:5px; cursor:pointer; font-size:.72rem; font-weight:700; color:#fff; min-width:auto; }
.kcard-btn.next   { background:#10b981; }
.kcard-btn.next:hover { background:#059669; }
.kcard-btn.cancel { background:#ef4444; }
.kcard-btn.cancel:hover { background:#dc2626; }
.kcard-btn.view   { background:#374151; }
.kcard-btn.view:hover { background:#4b5563; }

.kcard a.kcard-btn { display:inline-block; text-align:center; text-decoration:none; }
.kcard .payment-pill { background:#2d2d50; color:#c4b5fd; padding:2px 6px; border-radius:9px; font-size:.66rem; }

/* ── Toast ───────────────────────────────────────────────────────── */
.toast { position:fixed; top:20px; right:20px; padding:12px 20px; border-radius:8px; color:#fff; font-size:13px; z-index:9999; display:none; max-width:320px; }
.toast.success { background:#10b981; } .toast.error { background:#ef4444; }

/* Loading overlay sur carte pendant action */
.kcard.loading { opacity:.5; pointer-events:none; }
.kcard.loading::after {
    content:"⏳"; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    background:#000; color:#fff; padding:6px 14px; border-radius:20px; font-size:.8rem;
}
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
            <a href="index.php"              class="nav-link">📊 Dashboard</a>
            <a href="order-management.php"   class="nav-link active">⚡ Traitement</a>
            <a href="orders.php"             class="nav-link">📦 Toutes les commandes</a>
            <div class="nav-user">
                <span>👤 <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h2>⚡ Traitement des commandes</h2>
        <p class="text-muted">Console opérationnelle — faites avancer les commandes d'un clic</p>
    </div>

    <!-- Barre de synthèse -->
    <div class="om-topbar">
        <div class="om-stat <?= $urgentCount > 0 ? 'urgent' : '' ?>">
            <strong>🔥 <?= $urgentCount ?></strong>Urgent (&gt; 24h)
        </div>
        <div class="om-stat"><strong><?= count($ordersByStatus['pending']) ?></strong>En attente</div>
        <div class="om-stat"><strong><?= count($ordersByStatus['paid']) ?></strong>Payées à préparer</div>
        <div class="om-stat"><strong><?= count($ordersByStatus['processing']) ?></strong>En préparation</div>
        <div class="om-stat"><strong><?= count($ordersByStatus['shipped']) ?></strong>Expédiées</div>
        <button class="om-refresh" onclick="location.reload()">🔄 Actualiser</button>
    </div>

    <!-- Tableau Kanban -->
    <div class="kanban">
    <?php foreach ($columns as $status => $col): ?>
        <div class="kcol">
            <div class="kcol-head" style="background:linear-gradient(135deg,<?= $col['color'] ?>,<?= $col['color'] ?>cc);">
                <span><?= $col['icon'] ?> <?= htmlspecialchars($col['label']) ?></span>
                <span class="count"><?= count($ordersByStatus[$status]) ?></span>
            </div>
            <div class="kcol-desc"><?= htmlspecialchars($col['desc']) ?></div>
            <div class="kcol-body">
                <?php if (empty($ordersByStatus[$status])): ?>
                    <div class="kcol-empty">🎉 Aucune commande<br>dans cette étape</div>
                <?php else: foreach ($ordersByStatus[$status] as $o):
                    $age_sec = $now - strtotime($o['created_at']);
                    $is_urgent = ($status === 'pending' && $age_sec >= 86400);
                    $nb_items = $itemsCountByOrder[(int)$o['id']] ?? 0;
                    $cname = $o['customer_name'] ?? $o['user_full_name'] ?? '—';
                    $icon  = $paymentIcons[$o['payment_method']] ?? '💳';
                ?>
                <div class="kcard <?= $is_urgent ? 'urgent' : '' ?>" id="kcard-<?= $o['id'] ?>">
                    <div class="order-num">#<?= htmlspecialchars($o['order_number']) ?></div>
                    <div class="cust-name"><?= htmlspecialchars($cname) ?></div>
                    <div class="meta">
                        <span class="payment-pill"><?= $icon ?> <?= htmlspecialchars($o['payment_method']) ?></span>
                        <span>🛍️ <?= $nb_items ?> art.</span>
                    </div>
                    <div class="total"><?= number_format((float)$o['total_amount'], 2, ',', ' ') ?> HTG</div>
                    <div class="age <?= $is_urgent ? 'old' : '' ?>">
                        <?= $is_urgent ? '🔥 ' : '⏱ ' ?>
                        <?php
                            if ($age_sec < 3600)     echo floor($age_sec/60) . ' min';
                            elseif ($age_sec < 86400)echo floor($age_sec/3600) . ' h';
                            else                     echo floor($age_sec/86400) . ' j';
                        ?>
                        <small style="color:#64748b;"> · <?= date('d/m H:i', strtotime($o['created_at'])) ?></small>
                    </div>
                    <div class="actions">
                        <a href="order-details.php?id=<?= $o['id'] ?>" class="kcard-btn view">👁 Détails</a>
                        <?php if ($col['next']): ?>
                            <button class="kcard-btn next"
                                    onclick="quickUpdate(<?= $o['id'] ?>, '<?= $col['next'] ?>', '<?= htmlspecialchars($col['next_label'], ENT_QUOTES) ?>')">
                                <?= htmlspecialchars($col['next_label']) ?>
                            </button>
                        <?php endif; ?>
                        <?php if (in_array($status, ['pending','paid','processing'])): ?>
                            <button class="kcard-btn cancel"
                                    onclick="quickUpdate(<?= $o['id'] ?>, 'cancelled', '❌ Annuler la commande')"
                                    title="Annuler la commande">❌</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + (type || 'success');
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3200);
}

function quickUpdate(orderId, newStatus, actionLabel) {
    const card = document.getElementById('kcard-' + orderId);
    if (!confirm(`${actionLabel}\n\nCommande #${orderId} → ${newStatus}\n\nUn email sera envoyé au client.`)) return;
    const note = prompt('Message optionnel pour le client (inclus dans l\'email) :', '');
    if (note === null) return;

    if (card) card.classList.add('loading');

    const body = new URLSearchParams();
    body.set('action', 'quick_update');
    body.set('order_id', orderId);
    body.set('status', newStatus);
    body.set('note', note);

    fetch('order-management.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.email_sent ? '✅ Mis à jour — email envoyé' : '✅ Mis à jour (email non envoyé)',
                          data.email_sent ? 'success' : 'error');
                setTimeout(() => location.reload(), 900);
            } else {
                if (card) card.classList.remove('loading');
                showToast('❌ ' + (data.message || 'Erreur'), 'error');
            }
        })
        .catch(() => {
            if (card) card.classList.remove('loading');
            showToast('❌ Erreur réseau', 'error');
        });
}

// Auto-refresh toutes les 2 minutes pour rester à jour
setTimeout(() => location.reload(), 120000);
</script>
</body>
</html>
