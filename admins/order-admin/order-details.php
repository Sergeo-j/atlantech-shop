<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
check_auth();

// Helper emails partagé
require_once __DIR__ . '/../../config/order_emails.php';

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) { header('Location: orders.php'); exit; }

// ── AJAX : actions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $allowed = ['pending','paid','processing','shipped','delivered','cancelled'];

    if ($_POST['action'] === 'update_status') {
        $ns   = $_POST['status'] ?? '';
        $note = trim($_POST['note'] ?? '');
        if (!in_array($ns, $allowed, true)) { echo json_encode(['success'=>false,'message'=>'Statut invalide']); exit; }
        try {
            // 1) Charger la commande AVANT modification
            $stCur = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stCur->execute([$order_id]);
            $orderRow = $stCur->fetch();
            if (!$orderRow) { echo json_encode(['success'=>false,'message'=>'Commande introuvable']); exit; }
            $old = $orderRow['status'] ?? null;
            if ($old === $ns) { echo json_encode(['success'=>true,'message'=>'Statut inchangé']); exit; }

            // 2) Mise à jour
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$ns, $order_id]);

            // 3) Historique + log
            $admin_id   = (int)($_SESSION['admin_id']   ?? 0);
            $admin_name = (string)($_SESSION['admin_name'] ?? 'Admin');
            record_order_status_change($order_id, $old, $ns, $admin_id, $admin_name, $note);
            log_admin_action($admin_id, 'update_order_status',
                "Commande #{$orderRow['order_number']} : {$old} → {$ns}" . ($note ? " ({$note})" : ''),
                $order_id
            );

            // 4) Email client
            $email_sent = false;
            try {
                $email_sent = sendOrderStatusEmailToCustomer($orderRow, (string)$old, $ns, $note);
                if ($email_sent) {
                    $pdo->prepare("UPDATE order_status_history SET email_sent = 1 WHERE order_id = ? ORDER BY id DESC LIMIT 1")
                        ->execute([$order_id]);
                }
            } catch (\Throwable $e) { error_log('status email error: ' . $e->getMessage()); }

            echo json_encode(['success'=>true, 'email_sent'=>$email_sent]);
        } catch (PDOException $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;
    }
    if ($_POST['action'] === 'save_note') {
        $admin_note_new = trim($_POST['note'] ?? '');
        try {
            // Récupérer la note actuelle pour garder la partie système
            $cur = $pdo->prepare("SELECT internal_notes FROM orders WHERE id=?");
            $cur->execute([$order_id]);
            $current_raw = $cur->fetchColumn() ?? '';

            // Extraire la partie système (Mode + Articles + Note client)
            $sys_part = '';
            if (preg_match('/^((?:Mode:\s*\S+\s*\|\s*)?(?:Articles:\s*\[.*?\]\s*\|?\s*)?(?:Note client:\s*.+)?)/s', $current_raw, $m)) {
                // Reconstruire proprement la partie système
                $parts = [];
                if (preg_match('/Mode:\s*(\S+)/i', $current_raw, $pm)) $parts[] = 'Mode: '.$pm[1];
                if (preg_match('/Articles:\s*(\[.*?\])(?:\s*\||\s*$)/s', $current_raw, $pa)) $parts[] = 'Articles: '.$pa[1];
                if (preg_match('/Note client:\s*(.+)$/s', $current_raw, $pc)) $parts[] = 'Note client: '.trim($pc[1]);
                $sys_part = implode(' | ', $parts);
            }

            // Reconstituer : partie système + note admin
            if ($sys_part && $admin_note_new) {
                $new_notes = $sys_part . ' | ' . $admin_note_new;
            } elseif ($sys_part) {
                $new_notes = $sys_part;
            } else {
                $new_notes = $admin_note_new;
            }

            $pdo->prepare("UPDATE orders SET internal_notes=? WHERE id=?")->execute([$new_notes, $order_id]);
            echo json_encode(['success'=>true]);
        } catch (PDOException $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Action inconnue']); exit;
}

// ── Charger la commande ────────────────────────────────────────────
$order = null;
try {
    $st = $pdo->prepare("
        SELECT o.*, u.name AS user_full_name, u.email AS user_email_db
        FROM orders o LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $st->execute([$order_id]);
    $order = $st->fetch();
} catch (PDOException $e) {}

if (!$order) { echo '<p style="color:red;padding:40px">Commande introuvable.</p>'; exit; }

// ── Charger les items ──────────────────────────────────────────────
$items = [];
try {
    $st = $pdo->prepare("
        SELECT oi.*,
               COALESCE(NULLIF(oi.product_name,''), p.name)  AS pname,
               COALESCE(oi.product_image, p.image)            AS pimage,
               COALESCE(NULLIF(oi.unit_price,0), oi.price)   AS uprice,
               COALESCE(NULLIF(oi.total_price,0), oi.price * oi.quantity) AS tprice,
               p.name AS p_name_live, p.image AS p_image_live
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $st->execute([$order_id]);
    $items = $st->fetchAll();
} catch (PDOException $e) { error_log("order-details items: ".$e->getMessage()); }

// Fallback : si order_items vide → lire le JSON de internal_notes
if (empty($items)) {
    if (preg_match('/Articles:\s*(\[.*?\])(?:\s*\||\s*$)/s', $order['internal_notes'] ?? '', $m)) {
        $json = json_decode($m[1], true);
        if (is_array($json)) {
            foreach ($json as $it) {
                $items[] = [
                    'pname'  => $it['name']  ?? '—',
                    'pimage' => $it['image'] ?? null,
                    'color'  => $it['color_name'] ?? null,
                    'quantity'=> $it['qty']  ?? 1,
                    'uprice' => $it['price'] ?? 0,
                    'tprice' => $it['total'] ?? 0,
                    'product_id' => $it['id'] ?? 0,
                ];
            }
        }
    }
}

// ── Historique des statuts ────────────────────────────────────────
$history = [];
try {
    $st = $pdo->prepare("
        SELECT h.*
        FROM order_status_history h
        WHERE h.order_id = ?
        ORDER BY h.created_at DESC, h.id DESC
    ");
    $st->execute([$order_id]);
    $history = $st->fetchAll();
} catch (PDOException $e) {
    // Table non encore migrée : on affiche simplement rien.
    $history = [];
}

// ── Helpers ────────────────────────────────────────────────────────
$SITE_URL = 'http://localhost/atlantech-shop';

$statusInfo = [
    'pending'    => ['label'=>'En attente de paiement', 'class'=>'warning',   'icon'=>'⏳', 'color'=>'#f59e0b'],
    'paid'       => ['label'=>'Payée',                  'class'=>'success',   'icon'=>'✅', 'color'=>'#10b981'],
    'processing' => ['label'=>'En préparation',          'class'=>'info',      'icon'=>'🔧', 'color'=>'#06b6d4'],
    'shipped'    => ['label'=>'Expédiée',               'class'=>'primary',   'icon'=>'🚚', 'color'=>'#8b5cf6'],
    'delivered'  => ['label'=>'Livrée',                 'class'=>'delivered', 'icon'=>'📦', 'color'=>'#6ee7b7'],
    'cancelled'  => ['label'=>'Annulée',                'class'=>'danger',    'icon'=>'❌', 'color'=>'#ef4444'],
];
$paymentLabels = [
    'MonCash'=>'📱 MonCash / NatCash','Bank'=>'🏦 Virement bancaire',
    'Zelle'=>'₮ USDT (TRC-20)','Cash'=>'💵 Cash',
];
$nextActions = [
    'pending'    => ['paid'=>'✅ Marquer Payée','cancelled'=>'❌ Annuler'],
    'paid'       => ['processing'=>'🔧 En préparation','cancelled'=>'❌ Annuler'],
    'processing' => ['shipped'=>'🚚 Expédiée','cancelled'=>'❌ Annuler'],
    'shipped'    => ['delivered'=>'📦 Livrée'],
    'delivered'  => [], 'cancelled' => [],
];
$si      = $statusInfo[$order['status']] ?? ['label'=>$order['status'],'class'=>'secondary','icon'=>'?','color'=>'#94a3b8'];
$actions = $nextActions[$order['status']] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Commande #<?= htmlspecialchars($order['order_number']) ?> — Order Admin</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* ── Écran ──────────────────────────────────────────────────────── */
.back-link { display:inline-flex;align-items:center;gap:6px;color:#94a3b8;text-decoration:none;font-size:13px;margin-bottom:16px; }
.back-link:hover { color:#e2e8f0; }
.top-bar { display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:18px; }
.page-title { font-size:1.3rem;font-weight:800;color:#e2e8f0; }
.page-title span { color:#8b5cf6; }
.page-sub { color:#64748b;font-size:.82rem;margin-top:3px; }
.btn-print { padding:9px 18px;background:#374151;color:#e2e8f0;border:none;border-radius:7px;cursor:pointer;font-size:13px;font-weight:600; }
.btn-print:hover { background:#4b5563; }

/* Bannière statut */
.status-banner { border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border:1px solid; }
.status-text { font-size:1.1rem;font-weight:800; }
.action-btns { display:flex;gap:8px;flex-wrap:wrap;align-items:center; }
.btn-act { padding:8px 16px;border:none;border-radius:7px;cursor:pointer;font-size:12px;font-weight:700; }
.btn-act.success  { background:#10b981;color:#fff; }
.btn-act.info     { background:#06b6d4;color:#fff; }
.btn-act.primary  { background:#8b5cf6;color:#fff; }
.btn-act.delivered{ background:#059669;color:#fff; }
.btn-act.danger   { background:#ef4444;color:#fff; }
.btn-act.warning  { background:#f59e0b;color:#000; }
.btn-act:hover { opacity:.85; }
.sel-status { background:#2d2d50;border:1px solid #3d3d6b;color:#e2e8f0;padding:7px 10px;border-radius:6px;font-size:12px; }

/* Grille infos */
.info-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px; }
@media(max-width:720px){ .info-grid { grid-template-columns:1fr; } }
.info-card { background:#1e1e3a;border-radius:10px;padding:18px;border:1px solid #2d2d50; }
.info-card h4 { color:#8b5cf6;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #2d2d50; }
.irow { display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #2d2d50;font-size:.83rem; }
.irow:last-child { border-bottom:none; }
.ilbl { color:#64748b; }
.ival { color:#e2e8f0;font-weight:600;text-align:right;max-width:60%; }

/* Tableau produits */
.items-card { background:#1e1e3a;border-radius:10px;border:1px solid #2d2d50;margin-bottom:20px;overflow:hidden; }
.items-head { padding:14px 18px;border-bottom:1px solid #2d2d50;font-weight:700;color:#e2e8f0;font-size:.9rem;display:flex;justify-content:space-between;align-items:center; }
.prod-table { width:100%;border-collapse:collapse; }
.prod-table thead th { padding:10px 14px;font-size:.72rem;text-transform:uppercase;color:#64748b;background:#16162e;text-align:left;border-bottom:1px solid #2d2d50; }
.prod-table tbody td { padding:12px 14px;font-size:.85rem;color:#cbd5e1;border-bottom:1px solid #2d2d50;vertical-align:middle; }
.prod-table tbody tr:last-child td { border-bottom:none; }
.prod-table tbody tr:hover { background:#252545; }
.prod-img { width:56px;height:56px;border-radius:8px;object-fit:cover;background:#2d2d50;display:block;border:1px solid #3d3d6b; }
.prod-img-placeholder { width:56px;height:56px;border-radius:8px;background:#2d2d50;display:flex;align-items:center;justify-content:center;font-size:1.4rem;border:1px solid #3d3d6b; }
.prod-name { font-weight:700;color:#e2e8f0;font-size:.88rem; }
.prod-id { color:#64748b;font-size:.72rem; }
.qty-badge { display:inline-flex;align-items:center;justify-content:center;background:#2d2d50;color:#a78bfa;border-radius:6px;padding:4px 10px;font-weight:700;font-size:.88rem; }
.total-row { background:#1a1a30 !important; }
.total-row td { font-size:.9rem;font-weight:700;color:#e2e8f0; }
.grand-total { font-size:1.1rem;color:#8b5cf6; }

/* Note interne */
.note-card { background:#1e1e3a;border-radius:10px;padding:18px;border:1px solid #2d2d50;margin-bottom:20px; }
.note-card h4 { color:#8b5cf6;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;margin:0 0 14px; }
.note-section { margin-bottom:14px; }
.note-section-label { font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:6px;display:flex;align-items:center;gap:6px; }
.note-chip { display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:.82rem;font-weight:600; }
.note-chip.mode-cart    { background:#1e3a5f;color:#60a5fa; }
.note-chip.mode-buynow  { background:#3b1f5e;color:#c4b5fd; }
.note-client-box { background:#2d2d50;border-left:3px solid #f59e0b;border-radius:0 7px 7px 0;padding:10px 14px;font-size:.85rem;color:#fde68a;line-height:1.6; }
.sys-articles { background:#16162e;border-radius:7px;padding:10px 14px;font-size:.8rem;color:#94a3b8; }
.sys-article-row { display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #2d2d50; }
.sys-article-row:last-child { border-bottom:none; }
.sys-article-name { color:#cbd5e1; }
.sys-article-qty  { color:#a78bfa;font-weight:700; }
.sys-article-price{ color:#10b981;font-weight:700; }
.divider { border:none;border-top:1px solid #2d2d50;margin:14px 0; }
.note-area { width:100%;background:#2d2d50;border:1px solid #3d3d6b;color:#e2e8f0;border-radius:7px;padding:10px;font-size:13px;resize:vertical;min-height:70px; }
.note-area:focus { outline:none;border-color:#8b5cf6; }
.btn-save-note { margin-top:8px;padding:7px 14px;background:#8b5cf6;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px; }
.btn-save-note:hover { background:#7c3aed; }

/* Toast */
.toast { position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:#fff;font-size:13px;z-index:9999;display:none; }
.toast.success { background:#10b981; }
.toast.error   { background:#ef4444; }

/* ── REÇU (impression) ──────────────────────────────────────────── */
@media print {
    * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    body { background:#fff !important; }
    nav,.back-link,.btn-print,.status-banner,.action-btns,.sel-status,
    .btn-act,.btn-save-note,.toast,.note-area,
    .note-section-label,.sys-articles,.note-chip { display:none !important; }
    /* Garder visible uniquement note client et note admin texte */
    .note-card { background:#f9fafb !important;border:1px solid #e5e7eb !important; }
    .note-card h4 { color:#7c3aed !important; }
    .note-client-box { background:#fffbeb !important;border-color:#d97706 !important;color:#92400e !important; }
    .container { max-width:100% !important; padding:0 !important; }
    .info-grid { grid-template-columns:1fr 1fr; }
    .info-card { background:#f9fafb !important;border:1px solid #e5e7eb !important;border-radius:8px; }
    .info-card h4 { color:#7c3aed !important; }
    .irow { border-color:#e5e7eb !important; }
    .ilbl { color:#6b7280 !important; }
    .ival { color:#111827 !important; }
    .items-card { background:#fff !important;border:1px solid #e5e7eb !important;page-break-inside:avoid; }
    .items-head { background:#f3f4f6 !important;color:#111827 !important;border-color:#e5e7eb !important; }
    .prod-table thead th { background:#f9fafb !important;color:#6b7280 !important;border-color:#e5e7eb !important; }
    .prod-table tbody td { color:#374151 !important;border-color:#f3f4f6 !important; }
    .prod-table tbody tr:hover { background:transparent !important; }
    .total-row { background:#f3f4f6 !important; }
    .total-row td { color:#111827 !important; }
    .grand-total { color:#7c3aed !important; }
    .qty-badge { background:#ede9fe !important;color:#7c3aed !important; }
    .prod-name { color:#111827 !important; }
    #receipt-header { display:block !important; }
}
#receipt-header { display:none; }
#receipt-header { text-align:center;padding:0 0 20px;border-bottom:2px solid #7c3aed;margin-bottom:20px; }
#receipt-header h1 { font-size:1.6rem;font-weight:900;color:#7c3aed;margin:0 0 4px; }
#receipt-header p  { color:#6b7280;font-size:.85rem;margin:2px 0; }
#receipt-header .rcpt-status { display:inline-block;padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:700;margin-top:8px;background:#ede9fe;color:#7c3aed; }
</style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand"><h1>🛒 ORDER ADMIN</h1><span class="nav-subtitle">Gestion des Commandes</span></div>
        <div class="nav-menu">
            <a href="index.php"  class="nav-link">📊 Dashboard</a>
            <a href="orders.php" class="nav-link active">📦 Commandes</a>
            <div class="nav-user">
                <span>👤 <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                <a href="logout.php" class="btn-logout">🚪 Déconnexion</a>
            </div>
        </div>
    </div>
</nav>

<div class="container">

    <!-- En-tête reçu (visible uniquement à l'impression) -->
    <div id="receipt-header">
        <h1>🔷 AtlanTech</h1>
        <p>Spécialiste High-Tech en Haïti</p>
        <p>atlantech-shop.ht &nbsp;|&nbsp; contact@atlantech.ht</p>
        <div class="rcpt-status"><?= $si['icon'] ?> <?= $si['label'] ?></div>
    </div>

    <a href="orders.php" class="back-link">← Retour aux commandes</a>

    <div class="top-bar">
        <div>
            <div class="page-title">📋 Commande <span>#<?= htmlspecialchars($order['order_number']) ?></span></div>
            <div class="page-sub">Passée le <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></div>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimer le reçu</button>
    </div>

    <!-- Bannière statut -->
    <div class="status-banner" style="background:<?= $si['color'] ?>18;border-color:<?= $si['color'] ?>;">
        <span class="status-text" style="color:<?= $si['color'] ?>"><?= $si['icon'] ?> <?= $si['label'] ?></span>
        <div class="action-btns">
            <?php foreach ($actions as $target => $label):
                $tc = $statusInfo[$target]['class'] ?? 'primary'; ?>
                <button class="btn-act <?= $tc ?>" onclick="changeStatus('<?= $target ?>')"><?= $label ?></button>
            <?php endforeach; ?>
            <select id="sel-status" class="sel-status">
                <?php foreach ($statusInfo as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $order['status']===$k ? 'selected':'' ?>><?= $v['icon'] ?> <?= $v['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-act primary" onclick="changeStatus(document.getElementById('sel-status').value)">💾 Sauvegarder</button>
        </div>
    </div>

    <!-- ══ BANDEAU VALIDATION PAIEMENT (visible uniquement si pending) ══ -->
    <?php if (($order['status'] ?? '') === 'pending'):
        $pm  = $order['payment_method']         ?? '';
        $pp  = $order['payment_processor']      ?? $pm;
        $tx  = $order['payment_transaction_id'] ?? '';
        $isCash = ($pm === 'Cash');
    ?>
    <div class="payment-validation-panel" style="background:#fff8f0;border:2px solid #ff9100;border-radius:12px;padding:20px 24px;margin:18px 0;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="font-size:1.6rem;">💳</div>
            <div>
                <h3 style="margin:0;color:#9a3412;font-size:1.05rem;">Validation du paiement</h3>
                <p style="margin:2px 0 0;color:#92400e;font-size:.8rem;">Cette commande attend votre validation.</p>
            </div>
        </div>

        <?php if ($isCash): ?>
        <div style="background:#ecfdf5;border-left:4px solid #10b981;padding:12px 14px;border-radius:0 8px 8px 0;margin-bottom:14px;">
            <p style="margin:0 0 4px;color:#065f46;font-weight:700;font-size:.9rem;">💵 Paiement à la livraison</p>
            <p style="margin:0;color:#047857;font-size:.85rem;">Aucun paiement à vérifier maintenant — le client paiera en espèces à la réception. Confirmez juste que la commande peut partir en préparation.</p>
        </div>
        <button class="btn-act success" style="font-size:.95rem;padding:12px 24px;" onclick="changeStatus('paid')">
            ✅ Confirmer la commande (Cash)
        </button>
        <?php else: ?>
        <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:12px 14px;border-radius:0 8px 8px 0;margin-bottom:14px;">
            <p style="margin:0 0 6px;color:#1e3a8a;font-weight:700;font-size:.9rem;">🔍 Paiement à vérifier sur votre plateforme</p>
            <div style="display:grid;grid-template-columns:max-content 1fr;gap:6px 16px;font-size:.85rem;color:#1e40af;">
                <span style="font-weight:600;">Méthode :</span>
                <span><?= htmlspecialchars($pp ?: $pm) ?></span>
                <span style="font-weight:600;">Référence :</span>
                <span style="font-family:monospace;background:#fff;padding:2px 8px;border-radius:4px;border:1px solid #c7d2fe;">
                    <?= $tx ? htmlspecialchars($tx) : '<em style="color:#9ca3af;">(non fournie)</em>' ?>
                </span>
            </div>
            <p style="margin:8px 0 0;color:#475569;font-size:.78rem;">→ Connectez-vous à votre interface MonCash/NatCash/banque et confirmez la réception du montant <strong><?= number_format((float)$order['total_amount'], 2) ?> HTG</strong>, puis cliquez ci-dessous.</p>
        </div>
        <button class="btn-act success" style="font-size:.95rem;padding:12px 24px;" onclick="if(confirm('Avez-vous bien vérifié la réception du paiement ?')) changeStatus('paid')">
            ✅ Paiement vérifié — Valider la commande
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ ARTICLES DE LA COMMANDE ══════════════════════════════════ -->
    <div class="items-card">
        <div class="items-head">
            <span>🛍️ Articles commandés (<?= count($items) ?> produit<?= count($items)>1?'s':'' ?>)</span>
            <?php if (empty($items)): ?>
                <span style="color:#ef4444;font-size:.78rem;">⚠ Aucun article enregistré</span>
            <?php endif; ?>
        </div>
        <div style="overflow-x:auto;">
        <table class="prod-table">
            <thead>
                <tr>
                    <th style="width:70px">Photo</th>
                    <th>Produit</th>
                    <th style="text-align:center;width:80px">Qté</th>
                    <th style="text-align:right;width:120px">Prix unit.</th>
                    <th style="text-align:right;width:130px">Sous-total</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:#4b5563;">
                    Aucun article — commande passée avant la mise à jour ou items non enregistrés.
                </td></tr>
            <?php else: ?>
            <?php foreach ($items as $item):
                $imgSrc  = $item['pimage'] ?? $item['product_image'] ?? null;
                $imgUrl  = $imgSrc ? ($SITE_URL . '/' . ltrim($imgSrc, '/')) : null;
                $pname   = htmlspecialchars($item['pname'] ?? $item['product_name'] ?? 'Produit #'.$item['product_id']);
                $qty     = (int)($item['quantity'] ?? 1);
                $uprice  = (float)($item['uprice'] ?? $item['unit_price'] ?? $item['price'] ?? 0);
                $tprice  = (float)($item['tprice'] ?? $item['total_price'] ?? $uprice * $qty);
            ?>
                <tr>
                    <td>
                        <?php if ($imgUrl): ?>
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= $pname ?>" class="prod-img"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="prod-img-placeholder" style="display:none">📦</div>
                        <?php else: ?>
                            <div class="prod-img-placeholder">📦</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="prod-name"><?= $pname ?></div>
                        <div class="prod-id">Réf. #<?= $item['product_id'] ?? '—' ?></div>
                        <?php if (!empty($item['color'])): ?>
                            <div style="font-size:.78rem;color:#8b5cf6;font-weight:700;margin-top:2px;">🎨 <?= htmlspecialchars($item['color']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center"><span class="qty-badge">× <?= $qty ?></span></td>
                    <td style="text-align:right"><?= number_format($uprice, 2, ',', ' ') ?> HTG</td>
                    <td style="text-align:right;font-weight:700;color:#10b981"><?= number_format($tprice, 2, ',', ' ') ?> HTG</td>
                </tr>
            <?php endforeach; ?>

            <!-- Totaux -->
            <tr class="total-row">
                <td colspan="4" style="text-align:right;padding-right:16px;">Sous-total</td>
                <td style="text-align:right"><?= number_format($order['subtotal'], 2, ',', ' ') ?> HTG</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" style="text-align:right;padding-right:16px;">Livraison</td>
                <td style="text-align:right">
                    <?= $order['shipping_cost'] == 0 ? '🎁 Gratuite' : number_format($order['shipping_cost'], 2, ',', ' ').' HTG' ?>
                </td>
            </tr>
            <tr class="total-row">
                <td colspan="4" style="text-align:right;padding-right:16px;font-size:1rem">TOTAL</td>
                <td style="text-align:right" class="grand-total"><?= number_format($order['total_amount'], 2, ',', ' ') ?> HTG</td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Infos client + paiement -->
    <div class="info-grid">
        <div class="info-card">
            <h4>👤 Client</h4>
            <div class="irow"><span class="ilbl">Nom</span><span class="ival"><?= htmlspecialchars($order['customer_name'] ?? $order['user_full_name'] ?? '—') ?></span></div>
            <div class="irow"><span class="ilbl">Email</span><span class="ival"><?= htmlspecialchars($order['customer_email'] ?? $order['user_email_db'] ?? '—') ?></span></div>
            <div class="irow"><span class="ilbl">Téléphone</span><span class="ival"><?= htmlspecialchars($order['customer_phone'] ?? '—') ?></span></div>
            <div class="irow"><span class="ilbl">Compte</span><span class="ival" style="color:<?= $order['user_id'] ? '#8b5cf6' : '#64748b' ?>">
                <?= $order['user_id'] ? 'Client enregistré (ID '.$order['user_id'].')' : 'Invité' ?>
            </span></div>
        </div>

        <div class="info-card">
            <h4>💳 Paiement</h4>
            <div class="irow"><span class="ilbl">Méthode</span><span class="ival"><?= $paymentLabels[$order['payment_method']] ?? htmlspecialchars($order['payment_method']) ?></span></div>
            <?php if (!empty($order['payment_processor'])): ?>
            <div class="irow"><span class="ilbl">Détail</span><span class="ival"><?= htmlspecialchars($order['payment_processor']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($order['payment_transaction_id'])): ?>
            <div class="irow"><span class="ilbl">Réf. transaction</span><span class="ival" style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($order['payment_transaction_id']) ?></span></div>
            <?php endif; ?>
            <div class="irow"><span class="ilbl">Sous-total</span><span class="ival"><?= number_format($order['subtotal'],2,',',' ') ?> HTG</span></div>
            <div class="irow"><span class="ilbl">Livraison</span><span class="ival"><?= $order['shipping_cost']==0?'Gratuite':number_format($order['shipping_cost'],2,',',' ').' HTG' ?></span></div>
            <div class="irow"><span class="ilbl" style="font-weight:700">TOTAL</span><span class="ival" style="color:#8b5cf6;font-size:1rem"><?= number_format($order['total_amount'],2,',',' ') ?> HTG</span></div>
        </div>

        <div class="info-card">
            <h4>🏠 Adresse de livraison</h4>
            <p style="color:#e2e8f0;font-size:.85rem;line-height:1.7;margin:0;white-space:pre-wrap"><?= htmlspecialchars($order['shipping_address'] ?? '—') ?></p>
        </div>

        <?php if (!empty($order['notes'])): ?>
        <div class="info-card">
            <h4>📝 Note du client</h4>
            <p style="color:#e2e8f0;font-size:.85rem;line-height:1.7;margin:0;background:#2d2d50;padding:10px;border-radius:6px;white-space:pre-wrap"><?= htmlspecialchars($order['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Note interne interprétée -->
    <?php
    $raw_note = $order['internal_notes'] ?? '';

    // ── Extraire les parties auto-générées ──────────────────────
    // Format : "Mode: cart | Articles: [...] | Note client: texte"
    $parsed_mode     = null;
    $parsed_articles = [];
    $parsed_client   = null;
    $admin_note      = $raw_note; // par défaut : tout affiché dans le textarea

    if (!empty($raw_note)) {
        // Mode
        if (preg_match('/Mode:\s*(cart|buy_now)/i', $raw_note, $m)) {
            $parsed_mode = $m[1];
        }
        // Articles JSON
        if (preg_match('/Articles:\s*(\[.*?\])(?:\s*\||\s*$)/s', $raw_note, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) $parsed_articles = $json;
        }
        // Note client
        if (preg_match('/Note client:\s*(.+)$/s', $raw_note, $m)) {
            $parsed_client = trim($m[1]);
        }
        // Note admin = tout ce qui reste après les parties système
        $admin_note = preg_replace('/^Mode:\s*\S+\s*\|\s*/i', '', $raw_note);
        $admin_note = preg_replace('/Articles:\s*\[.*?\](\s*\|)?/s', '', $admin_note);
        $admin_note = preg_replace('/Note client:\s*.+$/s', '', $admin_note);
        $admin_note = trim(trim($admin_note, '| '));
    }
    ?>

    <!-- ══ HISTORIQUE DES STATUTS ════════════════════════════════════ -->
    <div class="note-card" style="padding:0;overflow:hidden;">
        <h4 style="padding:18px 18px 0;">📜 Historique des statuts <span style="color:#64748b;font-size:.72rem;font-weight:500;text-transform:none;">(<?= count($history) ?> entrée<?= count($history)>1?'s':'' ?>)</span></h4>
        <?php if (empty($history)): ?>
            <p style="padding:14px 18px;color:#64748b;font-size:.82rem;margin:0;">
                Aucun historique enregistré.
                Exécutez la migration <code style="background:#2d2d50;padding:2px 6px;border-radius:4px;">migrations/002_orders_admin_upgrade.sql</code> pour activer le suivi.
            </p>
        <?php else: ?>
            <div style="padding:10px 18px 18px;">
            <?php foreach ($history as $i => $h):
                $hi = $statusInfo[$h['new_status']] ?? ['label'=>$h['new_status'],'class'=>'secondary','icon'=>'📝','color'=>'#94a3b8'];
                $by  = htmlspecialchars($h['changed_by_name'] ?: ucfirst($h['changed_by_type'] ?: 'système'));
                $who = $h['changed_by_type'] === 'customer' ? '🧑 Client' : ($h['changed_by_type'] === 'system' ? '⚙ Système' : '👤 ' . $by);
                $when = date('d/m/Y H:i', strtotime($h['created_at']));
            ?>
                <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px dashed #2d2d50;">
                    <div style="width:8px;min-width:8px;margin-top:6px;background:<?= $hi['color'] ?>;border-radius:50%;height:8px;box-shadow:0 0 0 3px <?= $hi['color'] ?>22;"></div>
                    <div style="flex:1;">
                        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center;">
                            <span style="font-weight:700;color:<?= $hi['color'] ?>;font-size:.88rem;">
                                <?= $hi['icon'] ?> <?= htmlspecialchars($hi['label']) ?>
                                <?php if ($h['old_status']): ?>
                                    <span style="color:#64748b;font-weight:500;font-size:.75rem;"> (depuis <?= htmlspecialchars($statusInfo[$h['old_status']]['label'] ?? $h['old_status']) ?>)</span>
                                <?php endif; ?>
                            </span>
                            <span style="color:#64748b;font-size:.72rem;"><?= $when ?></span>
                        </div>
                        <div style="color:#94a3b8;font-size:.78rem;margin-top:3px;">
                            <?= $who ?>
                            <?php if (!empty($h['email_sent'])): ?>
                                <span style="background:#065f46;color:#6ee7b7;padding:2px 7px;border-radius:10px;font-size:.68rem;margin-left:6px;">✉ Email envoyé</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($h['note'])): ?>
                            <div style="background:#16162e;border-left:3px solid <?= $hi['color'] ?>;padding:6px 10px;margin-top:6px;border-radius:0 6px 6px 0;color:#cbd5e1;font-size:.8rem;white-space:pre-wrap;"><?= htmlspecialchars($h['note']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="note-card">
        <h4>🔒 Informations internes</h4>

        <!-- Mode de commande -->
        <?php if ($parsed_mode): ?>
        <div class="note-section">
            <div class="note-section-label">🛒 Mode d'achat</div>
            <?php if ($parsed_mode === 'buy_now'): ?>
                <span class="note-chip mode-buynow">⚡ Achat immédiat (Acheter maintenant)</span>
            <?php else: ?>
                <span class="note-chip mode-cart">🛒 Panier normal</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Articles commandés (si order_items vide = fallback) -->
        <?php if (!empty($parsed_articles) && empty($items)): ?>
        <div class="note-section">
            <div class="note-section-label">📦 Articles (données système)</div>
            <div class="sys-articles">
                <?php foreach ($parsed_articles as $a): ?>
                <div class="sys-article-row">
                    <span class="sys-article-name"><?= htmlspecialchars($a['name'] ?? 'Produit #'.($a['id']??'?')) ?></span>
                    <span class="sys-article-qty">× <?= (int)($a['qty'] ?? 1) ?></span>
                    <span class="sys-article-price"><?= number_format((float)($a['price'] ?? 0), 2, ',', ' ') ?> HTG</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Note laissée par le client lors de la commande -->
        <?php if ($parsed_client && empty($order['notes'])): ?>
        <div class="note-section">
            <div class="note-section-label">💬 Note laissée par le client</div>
            <div class="note-client-box"><?= nl2br(htmlspecialchars($parsed_client)) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($parsed_mode || $parsed_client || !empty($parsed_articles)): ?>
            <hr class="divider">
        <?php endif; ?>

        <!-- Zone de note admin (libre) -->
        <div class="note-section">
            <div class="note-section-label">✏️ Note admin (modifiable)</div>
            <textarea id="internal-note" class="note-area"
                placeholder="Ajouter une note interne : suivi, remarque, instruction livraison..."><?= htmlspecialchars($admin_note) ?></textarea>
            <button class="btn-save-note" onclick="saveNote()">💾 Enregistrer la note</button>
        </div>
    </div>

</div><!-- /container -->

<div id="toast" class="toast"></div>

<script>
const orderId = <?= $order_id ?>;

function changeStatus(newStatus) {
    if (!confirm('Confirmer le changement de statut ?\n\nUn email de notification sera envoyé au client.')) return;
    const note = prompt('Message optionnel pour le client (sera inclus dans l\'email) :', '');
    if (note === null) return; // annulé
    fetch('order-details.php?id=' + orderId, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=update_status&status=' + encodeURIComponent(newStatus) + '&note=' + encodeURIComponent(note)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const msg = d.email_sent ? '✅ Statut mis à jour — email envoyé' : '✅ Statut mis à jour (email non envoyé)';
            showToast(msg, d.email_sent ? 'success' : 'error');
            setTimeout(()=>location.reload(), 900);
        } else {
            showToast('❌ ' + (d.message || 'Erreur'), 'error');
        }
    });
}

function saveNote() {
    const note = document.getElementById('internal-note').value;
    fetch('order-details.php?id=' + orderId, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=save_note&note=' + encodeURIComponent(note)
    })
    .then(r => r.json())
    .then(d => showToast(d.success ? '✅ Note enregistrée' : '❌ Erreur', d.success ? 'success' : 'error'));
}

function showToast(msg, type) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.className = 'toast ' + (type || 'success');
    t.style.display = 'block';
    setTimeout(function () { t.style.display = 'none'; }, 3000);
}
</script>
</body>
</html>
