<?php
require_once 'includes/auth.php';
check_auth();
require_once __DIR__ . '/../../config/order_emails.php';

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) { header('Location: index.php'); exit; }

/**
 * Helper interne : enregistrer un changement de statut + envoyer l'email client.
 * Centralise la logique pour les 2 transitions du livreur.
 */
function _delivery_transition(PDO $pdo, int $order_id, string $expected_from, string $new_status, string $note = ''): array
{
    // 1) Récupérer la commande
    $st = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([$order_id]);
    $orderRow = $st->fetch();
    if (!$orderRow) return ['success'=>false, 'message'=>'Commande introuvable'];

    $old = $orderRow['status'] ?? '';
    if ($old !== $expected_from) {
        return ['success'=>false, 'message'=>"Statut actuel : '$old'. Action impossible."];
    }

    // 2) Update atomique (anti-double clic)
    $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND status = ?");
    $upd->execute([$new_status, $order_id, $expected_from]);
    if ($upd->rowCount() === 0) {
        return ['success'=>false, 'message'=>'La commande a déjà été mise à jour ailleurs.'];
    }

    // 3) Historique
    $admin_id   = (int)($_SESSION['delivery_id']   ?? 0);
    $admin_name = (string)($_SESSION['delivery_name'] ?? $_SESSION['delivery_email'] ?? 'Livreur');
    $hist_note  = $note ?: 'Transition livreur : ' . $expected_from . ' → ' . $new_status;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $sh = $pdo->prepare(
            "INSERT INTO order_status_history
                (order_id, old_status, new_status, changed_by_type, changed_by_id, changed_by_name, note, ip_address)
             VALUES (?, ?, ?, 'admin', ?, ?, ?, ?)"
        );
        $sh->execute([$order_id, $old, $new_status, $admin_id, $admin_name, $hist_note, $ip]);
    } catch (\Throwable $eh) { error_log('delivery history: ' . $eh->getMessage()); }

    // 4) Email client (status normal)
    $email_sent = false;
    try {
        $email_sent = sendOrderStatusEmailToCustomer($orderRow, $old, $new_status, $note);
    } catch (\Throwable $em) { error_log('delivery email: ' . $em->getMessage()); }

    // 5) Email spécial Cash : si on marque "delivered" pour une commande Cash,
    //    envoyer en plus l'email "paiement reçu"
    $cash_email_sent = false;
    if ($new_status === 'delivered' && ($orderRow['payment_method'] ?? '') === 'Cash') {
        try {
            $cash_email_sent = sendCashPaymentReceivedEmail($orderRow);
        } catch (\Throwable $ec) { error_log('cash email: ' . $ec->getMessage()); }
    }

    return [
        'success'         => true,
        'email_sent'      => $email_sent,
        'cash_email_sent' => $cash_email_sent,
    ];
}

// AJAX : actions du livreur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $note   = trim($_POST['note'] ?? '');

    // ── Action 1 : récupérer pour livrer (ready_for_delivery → shipped) ─────
    if ($action === 'take_for_delivery') {
        $res = _delivery_transition($pdo, $order_id, 'ready_for_delivery', 'shipped', $note);
        if ($res['success']) {
            try { log_delivery_action($_SESSION['delivery_id'], 'order_taken', "Commande #$order_id récupérée pour livraison"); } catch (\Throwable $_) {}
        }
        echo json_encode($res); exit;
    }

    // ── Action 2 : marquer livré (shipped → delivered) ───────────────
    if ($action === 'mark_delivered') {
        $res = _delivery_transition($pdo, $order_id, 'shipped', 'delivered', $note);
        if ($res['success']) {
            try { log_delivery_action($_SESSION['delivery_id'], 'order_delivered', "Commande #$order_id marquée livrée"); } catch (\Throwable $_) {}
        }
        echo json_encode($res); exit;
    }

    // ── Action 3 : signaler un problème (réinitialise à pending) ─────
    if ($action === 'report_issue') {
        $issue = trim($_POST['issue'] ?? '');
        try {
            $pdo->prepare("UPDATE orders SET status='pending', internal_notes=CONCAT(IFNULL(internal_notes,''), ?) WHERE id=?")
                ->execute([' | ⚠️ Problème livraison: '.$issue.' ['.date('d/m/Y H:i').']', $order_id]);
            try { log_delivery_action($_SESSION['delivery_id'], 'delivery_issue', "Commande #$order_id — $issue"); } catch (\Throwable $_) {}
            echo json_encode(['success'=>true]);
        } catch (PDOException $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Action inconnue']); exit;
}

// Charger commande
$order = null;
try {
    $st = $pdo->prepare("SELECT o.*, u.name AS user_full_name FROM orders o LEFT JOIN users u ON o.user_id=u.id WHERE o.id=?");
    $st->execute([$order_id]);
    $order = $st->fetch();
} catch (PDOException $e) {}

if (!$order) { header('Location: index.php'); exit; }

// Charger les items
$items = [];
try {
    $st = $pdo->prepare("
        SELECT oi.*,
               COALESCE(NULLIF(oi.product_name,''), p.name)  AS pname,
               COALESCE(oi.product_image, p.image)            AS pimage,
               COALESCE(NULLIF(oi.unit_price,0), oi.price)   AS uprice,
               COALESCE(NULLIF(oi.total_price,0), oi.price * oi.quantity) AS tprice
        FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id
        WHERE oi.order_id=? ORDER BY oi.id
    ");
    $st->execute([$order_id]);
    $items = $st->fetchAll();
} catch (PDOException $e) {}

// Fallback JSON
if (empty($items) && preg_match('/Articles:\s*(\[.*?\])(?:\s*\||\s*$)/s', $order['internal_notes']??'', $m)) {
    $json = json_decode($m[1], true);
    if (is_array($json)) foreach ($json as $it) $items[] = [
        'pname'=>$it['name']??'—', 'pimage'=>$it['image']??null, 'color'=>$it['color_name']??null,
        'quantity'=>$it['qty']??1, 'uprice'=>$it['price']??0, 'tprice'=>$it['total']??0, 'product_id'=>$it['id']??0,
    ];
}

$canTake     = $order['status'] === 'ready_for_delivery'; // récupérer pour livrer (→ shipped)
$canDeliver  = $order['status'] === 'shipped';            // marquer livrée (→ delivered)
$isDelivered = $order['status'] === 'delivered';
$isCashOrder = ($order['payment_method'] ?? '') === 'Cash';
$pm = match($order['payment_method']) {
    'MonCash'=>'📱 MonCash / NatCash','Bank'=>'🏦 Virement bancaire',
    'Zelle'=>'₮ USDT (TRC-20)','Cash'=>'💵 Cash',
    default=>$order['payment_method'],
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>#<?= htmlspecialchars($order['order_number']) ?> — Livraison</title>
<style>
*{box-sizing:border-box}
body{background:#0f0f23;color:#e2e8f0;font-family:'Segoe UI',sans-serif;margin:0;padding:0 0 40px}
.navbar{background:#1e1e3a;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #2d2d50;position:sticky;top:0;z-index:100}
.nav-brand h1{font-size:.95rem;font-weight:800;color:#22c55e;margin:0}
.nav-back{color:#94a3b8;text-decoration:none;font-size:.82rem;display:flex;align-items:center;gap:5px}
.nav-back:hover{color:#e2e8f0}
.container{max-width:680px;margin:0 auto;padding:20px 16px}
/* En-tête */
.order-header{background:#1e1e3a;border-radius:12px;padding:20px;margin-bottom:16px;border:1px solid #2d2d50}
.order-num{font-size:1.3rem;font-weight:900;color:#22c55e;font-family:monospace}
.order-date{font-size:.78rem;color:#64748b;margin-top:3px}
.status-big{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.85rem;font-weight:700;margin-top:10px}
/* Carte info */
.card{background:#1e1e3a;border-radius:12px;padding:18px;margin-bottom:14px;border:1px solid #2d2d50}
.card h3{font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #2d2d50}
.info-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #2d2d50;font-size:.85rem}
.info-row:last-child{border-bottom:none}
.info-row .lbl{color:#64748b}
.info-row .val{color:#e2e8f0;font-weight:600;text-align:right;max-width:65%}
.phone-link{color:#22c55e;text-decoration:none;font-size:1rem;font-weight:800}
.phone-link:hover{text-decoration:underline}
.address-box{background:#16162e;border-radius:8px;padding:12px;font-size:.88rem;color:#e2e8f0;line-height:1.7;border-left:3px solid #22c55e}
/* Maps btn */
.btn-maps{display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:8px 16px;background:#1e3a5f;color:#60a5fa;border-radius:8px;text-decoration:none;font-size:.82rem;font-weight:600}
.btn-maps:hover{background:#1e40af;color:#fff}
/* Items */
.item-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #2d2d50}
.item-row:last-child{border-bottom:none}
.item-img{width:50px;height:50px;border-radius:8px;object-fit:cover;background:#2d2d50;flex-shrink:0}
.item-img-ph{width:50px;height:50px;border-radius:8px;background:#2d2d50;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.item-name{font-size:.88rem;font-weight:700;color:#e2e8f0;flex:1}
.item-qty{color:#a78bfa;font-weight:700;font-size:.85rem}
.item-price{color:#10b981;font-weight:700;font-size:.88rem;text-align:right}
.total-section{padding-top:10px;border-top:1px solid #2d2d50;margin-top:6px}
.total-row{display:flex;justify-content:space-between;font-size:.85rem;padding:4px 0}
.total-row.grand{font-size:1.1rem;font-weight:900;color:#22c55e;padding-top:8px}
/* Actions */
.action-section{margin-bottom:14px}
.btn-delivered{width:100%;padding:16px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border:none;border-radius:12px;font-size:1.1rem;font-weight:900;cursor:pointer;letter-spacing:.3px}
.btn-delivered:hover{opacity:.9}
.btn-delivered:disabled{background:#374151;color:#6b7280;cursor:not-allowed}
.btn-issue{width:100%;padding:12px;background:transparent;color:#f87171;border:1px solid #ef4444;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;margin-top:8px}
.btn-issue:hover{background:#450a0a}
.delivered-banner{background:#14532d;border:1px solid #22c55e;border-radius:12px;padding:20px;text-align:center}
.delivered-banner .icon{font-size:2.5rem;display:block;margin-bottom:8px}
.delivered-banner p{color:#22c55e;font-weight:700;font-size:1rem}
/* Issue modal */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;display:none;align-items:flex-end;justify-content:center}
.overlay.show{display:flex}
.modal{background:#1e1e3a;border-radius:16px 16px 0 0;padding:24px 20px;width:100%;max-width:680px;border:1px solid #2d2d50}
.modal h3{color:#f87171;margin:0 0 14px;font-size:1rem}
.modal textarea{width:100%;background:#2d2d50;border:1px solid #3d3d6b;color:#e2e8f0;border-radius:8px;padding:10px;font-size:.9rem;resize:none;height:90px}
.modal textarea:focus{outline:none;border-color:#ef4444}
.modal-btns{display:flex;gap:8px;margin-top:12px}
.modal-btns button{flex:1;padding:11px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.88rem;border:none}
.btn-cancel-modal{background:#374151;color:#9ca3af}
.btn-send-issue{background:#ef4444;color:#fff}
/* Toast */
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);padding:13px 24px;border-radius:30px;color:#fff;font-size:.9rem;font-weight:700;z-index:300;display:none}
.toast.success{background:#22c55e;color:#000}
.toast.error{background:#ef4444}
/* Print */
@media print{
    .navbar,.action-section,.btn-maps,#issue-overlay,.toast{display:none!important}
    body{background:#fff!important;color:#000!important}
    .card,.order-header{background:#f9fafb!important;border-color:#e5e7eb!important}
    .order-num{color:#16a34a!important}
    .address-box{border-color:#16a34a!important;background:#f0fdf4!important;color:#14532d!important}
    .item-name,.info-row .val{color:#111827!important}
    .total-row.grand{color:#16a34a!important}
}
</style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-back">← Retour</a>
    <div class="nav-brand"><h1>🚚 Livraison</h1></div>
    <button onclick="window.print()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:.82rem">🖨️</button>
</nav>

<div class="container">

    <!-- En-tête commande -->
    <div class="order-header">
        <div class="order-num"><?= htmlspecialchars($order['order_number']) ?></div>
        <div class="order-date">Passée le <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></div>
        <?php if ($isDelivered): ?>
            <span class="status-big" style="background:#14532d;color:#22c55e">✅ Livrée</span>
        <?php elseif ($order['status'] === 'shipped'): ?>
            <span class="status-big" style="background:#451a03;color:#f59e0b">🚚 En livraison</span>
        <?php elseif ($order['status'] === 'ready_for_delivery'): ?>
            <span class="status-big" style="background:#064e3b;color:#10b981">🎁 Prêt à livrer</span>
        <?php elseif ($order['status'] === 'processing'): ?>
            <span class="status-big" style="background:#0e3a50;color:#06b6d4">🔧 En préparation (chez l'agent)</span>
        <?php else: ?>
            <span class="status-big" style="background:#1e3a5f;color:#60a5fa"><?= htmlspecialchars($order['status']) ?></span>
        <?php endif; ?>
    </div>

    <!-- ══ ACTION PRINCIPALE ══════════════════════════════════ -->
    <div class="action-section">
        <?php if ($isDelivered): ?>
            <div class="delivered-banner">
                <span class="icon">🎉</span>
                <p>Cette commande a été livrée avec succès !</p>
                <?php if ($isCashOrder): ?>
                <p style="margin-top:6px;font-size:.85rem;color:#86efac;">💵 Paiement Cash encaissé à la livraison.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($canTake): ?>
            <!-- Statut processing → bouton pour récupérer la commande -->
            <button class="btn-delivered" style="background:linear-gradient(135deg,#06b6d4,#0891b2);" onclick="takeForDelivery()">
                🚚 Récupérer pour la livraison
            </button>
            <p style="text-align:center;margin-top:10px;color:#94a3b8;font-size:.82rem;">
                Cliquez après avoir pris physiquement le colis emballé.<br>
                Le client recevra un email confirmant l'expédition.
            </p>
        <?php elseif ($canDeliver): ?>
            <!-- Statut shipped → bouton pour confirmer la livraison -->
            <?php if ($isCashOrder): ?>
            <div style="background:#451a03;border-left:4px solid #f59e0b;padding:12px 14px;border-radius:0 10px 10px 0;margin-bottom:14px;">
                <p style="margin:0 0 4px;font-weight:700;color:#fcd34d;">💵 Paiement Cash à encaisser</p>
                <p style="margin:0;color:#fde68a;font-size:.85rem;">
                    Montant à percevoir : <strong style="font-size:1.05rem;"><?= number_format((float)$order['total_amount'], 2) ?> HTG</strong><br>
                    En cliquant "Confirmer la livraison", vous confirmez aussi avoir reçu le paiement.
                </p>
            </div>
            <?php endif; ?>
            <button class="btn-delivered" onclick="markDelivered()">
                ✅ Confirmer la livraison<?= $isCashOrder ? ' + paiement reçu' : '' ?>
            </button>
            <button class="btn-issue" onclick="document.getElementById('issue-overlay').classList.add('show')">
                ⚠️ Signaler un problème
            </button>
        <?php else: ?>
            <div style="background:#1e3a5f;border-radius:10px;padding:14px;text-align:center;color:#60a5fa;font-size:.88rem">
                ⏳ Commande en <strong><?= htmlspecialchars($order['status']) ?></strong> — pas encore prête pour la livraison
            </div>
        <?php endif; ?>
    </div>

    <!-- Infos client -->
    <div class="card">
        <h3>👤 Client</h3>
        <div class="info-row"><span class="lbl">Nom</span><span class="val"><?= htmlspecialchars($order['customer_name'] ?? $order['user_full_name'] ?? '—') ?></span></div>
        <div class="info-row">
            <span class="lbl">📞 Téléphone</span>
            <a href="tel:<?= htmlspecialchars($order['customer_phone'] ?? '') ?>" class="phone-link">
                <?= htmlspecialchars($order['customer_phone'] ?? '—') ?>
            </a>
        </div>
        <div class="info-row"><span class="lbl">Email</span><span class="val" style="font-size:.78rem"><?= htmlspecialchars($order['customer_email'] ?? '—') ?></span></div>
    </div>

    <!-- Adresse -->
    <div class="card">
        <h3>📍 Adresse de livraison</h3>
        <div class="address-box"><?= nl2br(htmlspecialchars($order['shipping_address'] ?? '—')) ?></div>
        <?php
        $addr_encoded = urlencode($order['shipping_address'] ?? '');
        ?>
        <a href="https://maps.google.com/?q=<?= $addr_encoded ?>" target="_blank" class="btn-maps">
            🗺️ Ouvrir dans Google Maps
        </a>
    </div>

    <!-- Paiement -->
    <div class="card">
        <h3>💳 Paiement</h3>
        <div class="info-row"><span class="lbl">Méthode</span><span class="val"><?= $pm ?></span></div>
        <?php if (!empty($order['payment_processor']) && $order['payment_processor'] !== $order['payment_method']): ?>
        <div class="info-row"><span class="lbl">Détail</span><span class="val"><?= htmlspecialchars($order['payment_processor']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($order['payment_transaction_id'])): ?>
        <div class="info-row"><span class="lbl">Réf. transaction</span><span class="val" style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($order['payment_transaction_id']) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Articles -->
    <div class="card">
        <h3>🛍️ Articles (<?= count($items) ?>)</h3>
        <?php if (empty($items)): ?>
            <p style="color:#4b5563;font-size:.85rem;text-align:center;padding:10px 0">Détail des articles non disponible</p>
        <?php else: ?>
            <?php foreach ($items as $item):
                $imgSrc = $item['pimage'] ?? null;
                $imgUrl = $imgSrc ? ('http://localhost/atlantech-shop/'.ltrim($imgSrc,'/')) : null;
                $pname  = htmlspecialchars($item['pname'] ?? 'Produit #'.($item['product_id']??'?'));
                $qty    = (int)($item['quantity'] ?? 1);
                $tprice = (float)($item['tprice'] ?? 0);
            ?>
            <div class="item-row">
                <?php if ($imgUrl): ?>
                    <img src="<?= htmlspecialchars($imgUrl) ?>" class="item-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" alt="">
                    <div class="item-img-ph" style="display:none">📦</div>
                <?php else: ?>
                    <div class="item-img-ph">📦</div>
                <?php endif; ?>
                <div class="item-name">
                    <?= $pname ?>
                    <?php if (!empty($item['color'])): ?>
                        <span style="display:block;font-size:.78rem;color:#fbbf24;font-weight:700;">🎨 <?= htmlspecialchars($item['color']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="item-qty">× <?= $qty ?></div>
                <div class="item-price"><?= number_format($tprice, 0, ',', ' ') ?> HTG</div>
            </div>
            <?php endforeach; ?>

            <div class="total-section">
                <div class="total-row"><span>Sous-total</span><span><?= number_format($order['subtotal'], 2, ',', ' ') ?> HTG</span></div>
                <div class="total-row"><span>Livraison</span><span><?= $order['shipping_cost']==0 ? '🎁 Gratuite' : number_format($order['shipping_cost'],2,',',' ').' HTG' ?></span></div>
                <div class="total-row grand"><span>TOTAL</span><span><?= number_format($order['total_amount'], 2, ',', ' ') ?> HTG</span></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Note client -->
    <?php if (!empty($order['notes'])): ?>
    <div class="card">
        <h3>📝 Note du client</h3>
        <p style="background:#2d2d50;border-radius:7px;padding:10px;font-size:.85rem;color:#fde68a;line-height:1.6;margin:0"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
    </div>
    <?php endif; ?>

</div>

<!-- Modal signalement problème -->
<div class="overlay" id="issue-overlay">
    <div class="modal">
        <h3>⚠️ Signaler un problème</h3>
        <textarea id="issue-text" placeholder="Ex: Client absent, adresse introuvable, colis refusé..."></textarea>
        <div class="modal-btns">
            <button class="btn-cancel-modal" onclick="document.getElementById('issue-overlay').classList.remove('show')">Annuler</button>
            <button class="btn-send-issue" onclick="sendIssue()">Envoyer le rapport</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
const orderId = <?= $order_id ?>;
const isCashOrder = <?= $isCashOrder ? 'true' : 'false' ?>;

function takeForDelivery() {
    if (!confirm('Confirmer : vous récupérez physiquement ce colis pour la livraison ?\nUn email sera envoyé au client pour l\'informer de l\'expédition.')) return;
    const btn = document.querySelector('.btn-delivered');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Traitement...'; }

    fetch('delivery-details.php?id=' + orderId, {
        method: 'POST',
        headers: {
            'Content-Type':'application/x-www-form-urlencoded',
            'X-CSRF-Token': <?= json_encode(generate_csrf_token()) ?>
        },
        body: 'action=take_for_delivery'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast(d.email_sent ? '🚚 Colis pris en charge — email envoyé' : '🚚 Colis pris en charge', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            if (btn) { btn.disabled = false; btn.textContent = '🚚 Récupérer pour la livraison'; }
            showToast('❌ Erreur : '+(d.message||'réessayez'), 'error');
        }
    });
}

function markDelivered() {
    const msg = isCashOrder
        ? 'Confirmer la livraison ET la réception du paiement en espèces ?'
        : 'Confirmer que cette commande a bien été livrée ?';
    if (!confirm(msg)) return;
    const btn = document.querySelector('.btn-delivered');
    btn.disabled = true;
    btn.textContent = '⏳ En cours...';

    fetch('delivery-details.php?id=' + orderId, {
        method: 'POST',
        headers: {
            'Content-Type':'application/x-www-form-urlencoded',
            'X-CSRF-Token': <?= json_encode(generate_csrf_token()) ?>
        },
        body: 'action=mark_delivered'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const note = d.cash_email_sent ? ' (✉ paiement Cash confirmé)' : '';
            showToast('✅ Livraison confirmée !' + note, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            btn.disabled = false;
            btn.textContent = '✅ Confirmer la livraison';
            showToast('❌ Erreur : '+(d.message||'réessayez'), 'error');
        }
    });
}

function sendIssue() {
    const issue = document.getElementById('issue-text').value.trim();
    if (!issue) { showToast('⚠️ Décrivez le problème', 'error'); return; }

    fetch('delivery-details.php?id=' + orderId, {
        method: 'POST',
        headers: {
            'Content-Type':'application/x-www-form-urlencoded',
            'X-CSRF-Token': <?= json_encode(generate_csrf_token()) ?>
        },
        body: 'action=report_issue&issue=' + encodeURIComponent(issue)
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('issue-overlay').classList.remove('show');
        showToast(d.success ? '📋 Problème signalé' : '❌ Erreur', d.success ? 'success' : 'error');
        if (d.success) setTimeout(() => location.href='index.php', 1500);
    });
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'toast '+type; t.style.display = 'block';
    setTimeout(() => t.style.display='none', 3000);
}
</script>
</body>
</html>
