<?php
/**
 * AtlanTech — Préparation Admin : détail commande + action principale
 *
 * Affiche les articles à préparer + un bouton "Prendre en préparation"
 * qui passe la commande en statut `processing` et envoie l'email client.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../../config/order_emails.php';

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) { header('Location: index.php'); exit; }

// ── Helper interne : encapsule transition + historique + email ─────
function _prep_transition(PDO $pdo, int $order_id, string $expected_from, string $new_status, string $note, bool $send_email): array
{
    $st = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([$order_id]);
    $orderRow = $st->fetch();
    if (!$orderRow) return ['success'=>false, 'message'=>'Commande introuvable'];

    $old = $orderRow['status'] ?? '';
    if ($old !== $expected_from) {
        return ['success'=>false, 'message'=>"Statut actuel : '$old' — action impossible."];
    }

    $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND status = ?");
    $upd->execute([$new_status, $order_id, $expected_from]);
    if ($upd->rowCount() === 0) {
        return ['success'=>false, 'message'=>'La commande a été modifiée entre-temps. Rafraîchissez la page.'];
    }

    $admin_id   = (int)($_SESSION['admin_id']   ?? 0);
    $admin_name = (string)($_SESSION['admin_name'] ?? 'Préparateur');
    $hist_note  = $note ?: ($expected_from . ' → ' . $new_status);

    // Historique
    try {
        if (function_exists('record_order_status_change')) {
            record_order_status_change($order_id, $old, $new_status, $admin_id, $admin_name, $hist_note);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $sh = $pdo->prepare(
                "INSERT INTO order_status_history
                    (order_id, old_status, new_status, changed_by_type, changed_by_id, changed_by_name, note, ip_address)
                 VALUES (?, ?, ?, 'admin', ?, ?, ?, ?)"
            );
            $sh->execute([$order_id, $old, $new_status, $admin_id, $admin_name, $hist_note, $ip]);
        }
    } catch (\Throwable $eh) { error_log('preparation history: ' . $eh->getMessage()); }

    // Log admin
    try {
        if (function_exists('log_admin_action')) {
            log_admin_action($admin_id, 'update_order_status',
                "Commande #{$orderRow['order_number']} : $old → $new_status" . ($note ? " ($note)" : ''),
                $order_id);
        }
    } catch (\Throwable $el) { error_log('preparation log: ' . $el->getMessage()); }

    // Email client (uniquement si demandé : on n'envoie pas pour la transition interne ready)
    $email_sent = false;
    if ($send_email) {
        try {
            $email_sent = sendOrderStatusEmailToCustomer($orderRow, $old, $new_status, $note);
            if ($email_sent) {
                $pdo->prepare("UPDATE order_status_history SET email_sent = 1
                               WHERE order_id = ? ORDER BY id DESC LIMIT 1")->execute([$order_id]);
            }
        } catch (\Throwable $em) { error_log('preparation email: ' . $em->getMessage()); }
    }

    return ['success'=>true, 'email_sent'=>$email_sent];
}

// ── AJAX : transitions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $note   = trim($_POST['note'] ?? '');

    // Action 1 : paid → processing (démarrer la préparation) — email "en préparation"
    if ($action === 'take_for_preparation') {
        echo json_encode(_prep_transition($pdo, $order_id, 'paid', 'processing', $note, true));
        exit;
    }

    // Action 2 : processing → ready_for_delivery (colis prêt à livrer) — pas d'email client
    //            (transition interne entre prep et livraison, le client sera notifié
    //            par le livreur quand il prendra le colis pour l'expédition)
    if ($action === 'mark_ready') {
        echo json_encode(_prep_transition($pdo, $order_id, 'processing', 'ready_for_delivery', $note, false));
        exit;
    }

    echo json_encode(['success'=>false, 'message'=>'Action inconnue']);
    exit;
}

// ── Charger la commande ─────────────────────────────────────────────
$st = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$order_id]);
$order = $st->fetch();
if (!$order) { header('Location: index.php'); exit; }

// ── Charger les items ───────────────────────────────────────────────
$items = [];
try {
    $sti = $pdo->prepare("
        SELECT oi.*,
               COALESCE(NULLIF(oi.product_name,''), p.name)  AS pname,
               COALESCE(oi.product_image, p.image)            AS pimage,
               COALESCE(NULLIF(oi.unit_price,0), oi.price)    AS uprice,
               COALESCE(NULLIF(oi.total_price,0), oi.price * oi.quantity) AS tprice,
               p.sku, p.stock AS current_stock
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $sti->execute([$order_id]);
    $items = $sti->fetchAll();
} catch (\Throwable $e) { error_log('preparation items: ' . $e->getMessage()); }

// Fallback : reconstruire depuis internal_notes si order_items vide
if (empty($items) && preg_match('/Articles:\s*(\[.*?\])(?:\s*\||\s*$)/s', $order['internal_notes'] ?? '', $m)) {
    $json = json_decode($m[1], true);
    if (is_array($json)) {
        foreach ($json as $it) {
            $items[] = [
                'pname'        => $it['name']  ?? '—',
                'pimage'       => $it['image'] ?? null,
                'color'        => $it['color_name'] ?? null,
                'quantity'     => $it['qty']   ?? 1,
                'uprice'       => $it['price'] ?? 0,
                'tprice'       => $it['total'] ?? 0,
                'product_id'   => $it['id']    ?? 0,
                'sku'          => null,
                'current_stock'=> null,
            ];
        }
    }
}

$is_paid    = $order['status'] === 'paid';
$is_proc    = $order['status'] === 'processing';
$is_ready   = $order['status'] === 'ready_for_delivery';
$is_cash    = ($order['payment_method'] ?? '') === 'Cash';
$admin_name = $_SESSION['admin_name'] ?? 'Préparateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préparation — Commande #<?= htmlspecialchars($order['order_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f8fafc; color: #1f2937; }
        header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: #fff; padding: 24px 40px; }
        header h1 { font-size: 1.3rem; }
        main { max-width: 1100px; margin: 30px auto; padding: 0 24px; }
        a.back { color: #06b6d4; text-decoration: none; font-size: .9rem; display: inline-block; margin-bottom: 16px; }
        a.back:hover { text-decoration: underline; }

        .top-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #1f2937; }
        .page-title span { color: #06b6d4; }

        .status-banner { padding: 14px 20px; border-radius: 12px; border-left: 6px solid; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .status-banner.paid { background: #d1fae5; border-color: #10b981; }
        .status-banner.processing { background: #ede9fe; border-color: #8b5cf6; }
        .status-banner.other { background: #f1f5f9; border-color: #94a3b8; }
        .status-text { font-size: 1.05rem; font-weight: 700; }
        .status-banner.paid .status-text { color: #065f46; }
        .status-banner.processing .status-text { color: #5b21b6; }

        .action-panel { background: #fff8f0; border: 2px solid #ff9100; border-radius: 12px; padding: 24px; margin: 18px 0; }
        .action-panel h3 { color: #9a3412; font-size: 1.05rem; margin-bottom: 10px; }
        .action-panel p { color: #92400e; font-size: .88rem; line-height: 1.5; }
        .btn-take { display: inline-block; margin-top: 14px; padding: 14px 28px; background: #06b6d4; color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .15s; }
        .btn-take:hover { background: #0891b2; }

        .grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; margin-top: 20px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .card h3 { font-size: 1rem; font-weight: 700; color: #1f2937; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }

        .item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .item:last-child { border-bottom: none; }
        .item img { width: 64px; height: 64px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; }
        .item-info { flex: 1; }
        .item-name { font-weight: 600; color: #1f2937; }
        .item-sku { font-size: .75rem; color: #6b7280; font-family: monospace; }
        .item-qty { font-weight: 700; color: #06b6d4; font-size: 1.05rem; white-space: nowrap; }
        .item-stock { font-size: .78rem; color: #6b7280; text-align: right; }

        .info-row { padding: 6px 0; font-size: .88rem; }
        .info-row strong { color: #374151; display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }

        .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); padding: 14px 22px; border-radius: 8px; color: #fff; font-weight: 600; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,.15); opacity: 0; transition: opacity .25s; }
        .toast.show { opacity: 1; }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
    </style>
</head>
<body>

<header>
    <h1>📦 Préparation des commandes — AtlanTech</h1>
</header>

<main>
    <a href="index.php" class="back">← Retour à la liste</a>

    <div class="top-bar">
        <div class="page-title">📋 Commande <span>#<?= htmlspecialchars($order['order_number']) ?></span></div>
    </div>

    <!-- Bannière statut -->
    <?php if ($is_paid): ?>
        <div class="status-banner paid">
            <span class="status-text">✅ Payée — prête à préparer</span>
        </div>
    <?php elseif ($is_proc): ?>
        <div class="status-banner processing">
            <span class="status-text">🔧 En cours de préparation</span>
        </div>
    <?php elseif ($is_ready): ?>
        <div class="status-banner" style="background:#dbeafe;border-color:#3b82f6;">
            <span class="status-text" style="color:#1e3a8a;">🎁 Colis prêt — en attente du livreur</span>
        </div>
    <?php else: ?>
        <div class="status-banner other">
            <span class="status-text">📝 Statut : <?= htmlspecialchars($order['status']) ?></span>
        </div>
    <?php endif; ?>

    <!-- Panneau d'action principal selon le statut -->
    <?php if ($is_paid): ?>
    <div class="action-panel">
        <h3>📦 Prendre cette commande en préparation</h3>
        <p>
            Vérifiez que tous les articles ci-dessous sont disponibles, sortez-les du stock,
            puis cliquez pour confirmer le démarrage. <strong>Le client recevra immédiatement
            un email l'informant que sa commande est en préparation</strong>, et la commande
            disparaîtra de la liste des commandes à préparer pour éviter qu'un autre agent
            la traite en doublon.
        </p>
        <?php if ($is_cash): ?>
        <p style="margin-top:8px;background:#fef3c7;padding:8px 12px;border-radius:6px;color:#92400e;">
            💵 <strong>Paiement à la livraison</strong> — le client paiera à la réception.
            Pensez à bien noter le montant à percevoir sur le bon de livraison.
        </p>
        <?php endif; ?>
        <button class="btn-take" onclick="takeForPreparation()">
            🚀 Démarrer la préparation
        </button>
    </div>

    <?php elseif ($is_proc): ?>
    <div class="action-panel" style="background:#f5f3ff;border-color:#8b5cf6;">
        <h3 style="color:#5b21b6;">📦 Préparation en cours — finalisation</h3>
        <p style="color:#5b21b6;">
            Une fois tous les articles emballés et le colis prêt physiquement à être pris
            par le livreur, cliquez sur le bouton ci-dessous. La commande passera dans la
            liste des livreurs.
        </p>
        <?php if ($is_cash): ?>
        <p style="margin-top:8px;background:#fef3c7;padding:8px 12px;border-radius:6px;color:#92400e;">
            💵 <strong>Paiement Cash à la livraison</strong> — notez bien le montant à percevoir
            sur le bordereau du colis : <strong><?= number_format((float)$order['total_amount'], 2) ?> HTG</strong>
        </p>
        <?php endif; ?>
        <button class="btn-take" style="background:#3b82f6;" onclick="markReady()">
            🎁 Colis prêt à livrer
        </button>
    </div>

    <?php elseif ($is_ready): ?>
    <div class="action-panel" style="background:#eff6ff;border-color:#3b82f6;">
        <h3 style="color:#1e3a8a;">🎁 Colis prêt et en attente</h3>
        <p style="color:#1e3a8a;">
            La préparation est terminée. Le colis est dans la liste du livreur — il viendra
            le prendre pour la livraison. Vous n'avez plus rien à faire sur cette commande.
        </p>
    </div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Articles -->
        <div class="card">
            <h3>🛍️ Articles à préparer (<?= count($items) ?>)</h3>
            <?php if (empty($items)): ?>
                <p style="color:#6b7280;font-style:italic;">Aucun article enregistré.</p>
            <?php else: ?>
                <?php foreach ($items as $it):
                    $pname = $it['pname'] ?? '—';
                    $pid   = (int)($it['product_id'] ?? 0);
                    $img   = !empty($it['pimage']) ? '../../uploads/products/' . htmlspecialchars($it['pimage']) : '';
                    $qty   = (int)($it['quantity'] ?? 1);
                    $stock = $it['current_stock'] ?? null;
                ?>
                <div class="item">
                    <?php if ($img): ?>
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($pname) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="item-info">
                        <div class="item-name"><?= htmlspecialchars($pname) ?></div>
                        <?php if (!empty($it['color'])): ?>
                            <div style="font-size:.85rem; color:#0891b2; font-weight:700; margin-top:2px;">
                                🎨 Couleur : <?= htmlspecialchars($it['color']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($it['sku'])): ?>
                            <div class="item-sku"><?= htmlspecialchars($it['sku']) ?></div>
                        <?php endif; ?>
                        <?php if ($stock !== null): ?>
                            <div class="item-stock">Stock restant : <?= (int)$stock ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="item-qty">×<?= $qty ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Infos client + adresse -->
        <div class="card">
            <h3>👤 Client &amp; livraison</h3>
            <div class="info-row">
                <strong>Nom</strong>
                <?= htmlspecialchars($order['customer_name']) ?>
            </div>
            <div class="info-row">
                <strong>Téléphone</strong>
                <?= htmlspecialchars($order['customer_phone'] ?? '') ?>
            </div>
            <div class="info-row">
                <strong>Email</strong>
                <?= htmlspecialchars($order['customer_email'] ?? '') ?>
            </div>
            <div class="info-row">
                <strong>Adresse de livraison</strong>
                <?= nl2br(htmlspecialchars($order['shipping_address'] ?? '—')) ?>
            </div>
            <hr style="border:none;border-top:1px solid #f1f5f9;margin:12px 0;">
            <div class="info-row">
                <strong>Méthode de paiement</strong>
                <?= htmlspecialchars($order['payment_method'] ?? '') ?>
                <?php if ($is_cash): ?>
                    <span style="color:#9a3412;font-weight:700;">— à percevoir à la livraison</span>
                <?php endif; ?>
            </div>
            <div class="info-row">
                <strong>Total commande</strong>
                <span style="font-size:1.1rem;font-weight:700;color:#06b6d4;">
                    <?= number_format((float)$order['total_amount'], 2) ?> HTG
                </span>
            </div>
            <?php if (!empty($order['notes'])): ?>
            <div class="info-row" style="background:#fef3c7;padding:10px;border-radius:6px;margin-top:8px;">
                <strong>📝 Note du client</strong>
                <?= nl2br(htmlspecialchars($order['notes'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="toast" class="toast"></div>

<script>
function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + (type || 'success');
    setTimeout(function() { t.classList.remove('show'); }, 3500);
}

function _postAction(action, successMsg, originalBtnText) {
    var btn = document.querySelector('.btn-take');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Traitement...'; }

    fetch('preparation-details.php?id=<?= (int)$order_id ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + encodeURIComponent(action)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            var msg = d.email_sent ? successMsg + ' — email envoyé au client' : successMsg;
            showToast('✅ ' + msg, 'success');
            setTimeout(function() { window.location.reload(); }, 1500);
        } else {
            showToast('❌ ' + (d.message || 'Erreur inconnue'), 'error');
            if (btn) { btn.disabled = false; btn.textContent = originalBtnText; }
        }
    })
    .catch(err => {
        showToast('❌ Erreur réseau', 'error');
        if (btn) { btn.disabled = false; btn.textContent = originalBtnText; }
    });
}

function takeForPreparation() {
    if (!confirm('Confirmer : démarrer la préparation de cette commande ?\nUn email sera envoyé au client.')) return;
    _postAction('take_for_preparation', 'Préparation démarrée', '🚀 Démarrer la préparation');
}

function markReady() {
    if (!confirm('Confirmer : le colis est terminé et prêt à être pris par le livreur ?\nLa commande disparaîtra de votre liste et passera côté livraison.')) return;
    _postAction('mark_ready', 'Colis marqué prêt à livrer', '🎁 Colis prêt à livrer');
}
</script>

</body>
</html>
