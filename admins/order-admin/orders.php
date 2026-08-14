<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
check_auth();

// Helper emails partagé (client/admin)
require_once __DIR__ . '/../../config/order_emails.php';

// ── Paramètres de filtre ──────────────────────────────────────────
$search    = trim($_GET['search']    ?? '');
$status    = $_GET['status']         ?? '';
$payment   = $_GET['payment']        ?? '';
$date_from = $_GET['date_from']      ?? '';
$date_to   = $_GET['date_to']        ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;
$offset    = ($page - 1) * $per_page;

$ALLOWED_STATUSES = ['pending','paid','processing','shipped','delivered','cancelled'];

// ── AJAX : mise à jour statut ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $order_id   = (int)($_POST['order_id'] ?? 0);
        $note       = trim($_POST['note'] ?? '');
        if (!in_array($new_status, $ALLOWED_STATUSES, true) || $order_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Données invalides']);
            exit;
        }
        try {
            // 1) Récupérer la commande AVANT modification (pour l'historique + l'email)
            $stOld = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stOld->execute([$order_id]);
            $orderRow = $stOld->fetch();
            if (!$orderRow) {
                echo json_encode(['success' => false, 'message' => 'Commande introuvable']);
                exit;
            }
            $old_status = $orderRow['status'] ?? null;

            if ($old_status === $new_status) {
                echo json_encode(['success' => true, 'message' => 'Statut inchangé']);
                exit;
            }

            // 2) Mettre à jour le statut
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
                ->execute([$new_status, $order_id]);

            // 3) Historique + log admin
            $admin_id   = (int)($_SESSION['admin_id']   ?? 0);
            $admin_name = (string)($_SESSION['admin_name'] ?? 'Admin');
            record_order_status_change($order_id, $old_status, $new_status, $admin_id, $admin_name, $note);
            log_admin_action($admin_id, 'update_order_status',
                "Commande #{$orderRow['order_number']} : {$old_status} → {$new_status}" . ($note ? " ({$note})" : ''),
                $order_id
            );

            // 4) Email au client (ne bloque jamais la réponse si échec)
            $email_sent = false;
            try {
                $email_sent = sendOrderStatusEmailToCustomer($orderRow, (string)$old_status, $new_status, $note);
                if ($email_sent) {
                    $pdo->prepare("UPDATE order_status_history SET email_sent = 1
                                   WHERE order_id = ? ORDER BY id DESC LIMIT 1")
                        ->execute([$order_id]);
                }
            } catch (\Throwable $e) {
                error_log('status email error: ' . $e->getMessage());
            }

            echo json_encode(['success' => true, 'email_sent' => $email_sent]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    exit;
}

// ── Construction de la requête ───────────────────────────────────
$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') {
    $where[]  = "o.status = ?";
    $params[] = $status;
}
if ($payment !== '') {
    $where[]  = "o.payment_method = ?";
    $params[] = $payment;
}
if ($date_from !== '') {
    $where[]  = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where[]  = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Export CSV (réutilise les mêmes filtres, ignore la pagination) ──
if (($_GET['export'] ?? '') === 'csv') {
    try {
        $st = $pdo->prepare("
            SELECT o.order_number, o.created_at, o.status, o.payment_method, o.payment_processor,
                   o.payment_transaction_id,
                   o.customer_name, o.customer_email, o.customer_phone, o.shipping_address,
                   o.subtotal, o.shipping_cost, o.total_amount,
                   u.name AS user_full_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            $whereSQL
            ORDER BY o.created_at DESC
        ");
        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (PDOException $e) {
        $rows = [];
        error_log('orders export: ' . $e->getMessage());
    }

    log_admin_action((int)($_SESSION['admin_id'] ?? 0), 'export_orders_csv',
        'Export CSV de ' . count($rows) . ' commande(s)');

    $filename = 'commandes_atlantech_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 pour Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'N° Commande','Date','Statut','Méthode','Processeur','Réf. transaction',
        'Client','Email','Téléphone','Adresse',
        'Sous-total (HTG)','Livraison (HTG)','Total (HTG)','Compte'
    ], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['order_number'],
            $r['created_at'],
            $r['status'],
            $r['payment_method'],
            $r['payment_processor'],
            $r['payment_transaction_id'],
            $r['customer_name'] ?? $r['user_full_name'] ?? '',
            $r['customer_email'],
            $r['customer_phone'],
            $r['shipping_address'],
            number_format((float)$r['subtotal'],     2, '.', ''),
            number_format((float)$r['shipping_cost'],2, '.', ''),
            number_format((float)$r['total_amount'], 2, '.', ''),
            $r['user_full_name'] ?: 'Invité',
        ], ';');
    }
    fclose($out);
    exit;
}

// Compter le total
try {
    $stCount = $pdo->prepare("SELECT COUNT(*) FROM orders o $whereSQL");
    $stCount->execute($params);
    $total_rows = (int)$stCount->fetchColumn();
} catch (PDOException $e) {
    $total_rows = 0;
}
$total_pages = max(1, ceil($total_rows / $per_page));

// Récupérer les commandes
$orders = [];
try {
    $st = $pdo->prepare("
        SELECT o.*, u.name AS user_full_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        $whereSQL
        ORDER BY o.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $st->execute($params);
    $orders = $st->fetchAll();
} catch (PDOException $e) {
    error_log("orders.php query: " . $e->getMessage());
}

// Labels statuts
$statusLabels = [
    'pending'    => ['label' => 'En attente',   'class' => 'warning'],
    'paid'       => ['label' => 'Payée',         'class' => 'success'],
    'processing' => ['label' => 'En préparation','class' => 'info'],
    'shipped'    => ['label' => 'Expédiée',      'class' => 'primary'],
    'delivered'  => ['label' => 'Livrée',        'class' => 'delivered'],
    'cancelled'  => ['label' => 'Annulée',       'class' => 'danger'],
];
$paymentLabels = [
    'MonCash' => '📱 MonCash/NatCash',
    'Bank'    => '🏦 Virement',
    'Zelle'   => '₮ USDT',
    'Cash'    => '💵 Cash',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes — Order Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-bar { background: #1e1e3a; padding: 18px 20px; border-radius: 10px; margin-bottom: 22px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .filter-bar input, .filter-bar select { background: #2d2d50; border: 1px solid #3d3d6b; color: #e2e8f0; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: #8b5cf6; }
        .filter-bar label { color: #94a3b8; font-size: 12px; display: block; margin-bottom: 4px; }
        .filter-bar .filter-group { display: flex; flex-direction: column; }
        .filter-bar .btn-filter { padding: 8px 18px; background: #8b5cf6; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; align-self: flex-end; }
        .filter-bar .btn-reset { padding: 8px 14px; background: #374151; color: #94a3b8; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; align-self: flex-end; text-decoration: none; }
        .results-info { color: #94a3b8; font-size: 13px; margin-bottom: 12px; }
        .data-table td { vertical-align: middle; }
        .order-num { font-weight: 700; color: #8b5cf6; font-family: monospace; font-size: 13px; }
        .customer-info small { color: #64748b; display: block; }
        .payment-info { font-size: 12px; }
        .payment-processor { color: #94a3b8; font-size: 11px; }
        .select-status { background: #2d2d50; border: 1px solid #3d3d6b; color: #e2e8f0; padding: 5px 8px; border-radius: 5px; font-size: 12px; cursor: pointer; }
        .select-status:focus { outline: none; border-color: #8b5cf6; }
        .btn-save-status { padding: 5px 10px; background: #10b981; border: none; color: #fff; border-radius: 5px; cursor: pointer; font-size: 12px; margin-left: 4px; }
        .btn-save-status:hover { background: #059669; }
        .btn-detail { padding: 5px 12px; background: #8b5cf6; color: #fff; border-radius: 5px; text-decoration: none; font-size: 12px; white-space: nowrap; }
        .btn-detail:hover { background: #7c3aed; color: #fff; }
        .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 7px 13px; background: #1e1e3a; border: 1px solid #3d3d6b; color: #e2e8f0; border-radius: 5px; text-decoration: none; font-size: 13px; }
        .pagination a:hover { background: #2d2d50; }
        .pagination .current { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
        .badge-delivered { background: #065f46; color: #6ee7b7; }
        .toast { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; color: #fff; font-size: 13px; z-index: 9999; display: none; }
        .toast.success { background: #10b981; }
        .toast.error   { background: #ef4444; }
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
        <div class="page-header">
            <h2>📦 Toutes les commandes</h2>
            <p class="text-muted"><?= number_format($total_rows) ?> commande<?= $total_rows > 1 ? 's' : '' ?> au total</p>
        </div>

        <!-- Filtres -->
        <form method="GET" action="orders.php">
            <div class="filter-bar">
                <div class="filter-group">
                    <label>🔍 Recherche</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="N° commande, client, email..." style="width:220px;">
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select name="status">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statusLabels as $key => $info): ?>
                            <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Paiement</label>
                    <select name="payment">
                        <option value="">Tous</option>
                        <?php foreach ($paymentLabels as $key => $lbl): ?>
                            <option value="<?= $key ?>" <?= $payment === $key ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date début</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>Date fin</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <button type="submit" class="btn-filter">🔍 Filtrer</button>
                <a href="orders.php" class="btn-reset">✕ Réinitialiser</a>
                <?php
                    $csvQuery = http_build_query(array_filter([
                        'search' => $search, 'status' => $status, 'payment' => $payment,
                        'date_from' => $date_from, 'date_to' => $date_to, 'export' => 'csv',
                    ]));
                ?>
                <a href="orders.php?<?= $csvQuery ?>" class="btn-filter" style="background:#10b981;text-decoration:none;" title="Télécharger les commandes filtrées en CSV (ouvrable Excel)">⬇ Export CSV</a>
            </div>
        </form>

        <!-- Tableau -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Paiement</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">Aucune commande trouvée</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr id="row-<?= $order['id'] ?>">
                                <td><span class="order-num"><?= htmlspecialchars($order['order_number']) ?></span></td>
                                <td class="customer-info">
                                    <?= htmlspecialchars($order['customer_name'] ?? $order['user_full_name'] ?? '—') ?>
                                    <small><?= htmlspecialchars($order['customer_email'] ?? '') ?></small>
                                    <small><?= htmlspecialchars($order['customer_phone'] ?? '') ?></small>
                                </td>
                                <td><strong><?= number_format($order['total_amount'], 2) ?> HTG</strong>
                                    <small class="text-muted" style="display:block">Livraison: <?= number_format($order['shipping_cost'], 2) ?></small>
                                </td>
                                <td class="payment-info">
                                    <?= $paymentLabels[$order['payment_method']] ?? htmlspecialchars($order['payment_method']) ?>
                                    <?php if (!empty($order['payment_processor']) && $order['payment_processor'] !== $order['payment_method']): ?>
                                        <span class="payment-processor"><?= htmlspecialchars($order['payment_processor']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $si = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'class' => 'secondary']; ?>
                                    <select class="select-status" data-order-id="<?= $order['id'] ?>" onchange="markChanged(this)">
                                        <?php foreach ($statusLabels as $key => $info): ?>
                                            <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn-save-status" onclick="saveStatus(<?= $order['id'] ?>)" title="Enregistrer">✓</button>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td><a href="order-details.php?id=<?= $order['id'] ?>" class="btn-detail">👁 Détails</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $base = "orders.php?" . http_build_query(array_filter([
                'search'    => $search,
                'status'    => $status,
                'payment'   => $payment,
                'date_from' => $date_from,
                'date_to'   => $date_to,
            ]));
            $base .= ($base ? '&' : '') . 'page=';
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base . ($page - 1) ?>">‹ Préc.</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="current"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= $base . $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base . ($page + 1) ?>">Suiv. ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Toast notification -->
    <div id="toast" class="toast"></div>

    <script>
    function markChanged(select) {
        select.style.borderColor = '#f59e0b';
    }

    function saveStatus(orderId) {
        const select  = document.querySelector(`select[data-order-id="${orderId}"]`);
        const newStatus = select.value;

        if (!confirm(`Confirmer le passage au statut "${select.options[select.selectedIndex].text}" ?\nUn email sera envoyé au client.`)) return;
        const note = prompt('Message optionnel pour le client (laisser vide pour aucun) :', '');
        if (note === null) return;

        fetch('orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_status&order_id=${orderId}&status=${encodeURIComponent(newStatus)}&note=${encodeURIComponent(note)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                select.style.borderColor = '#10b981';
                const msg = data.email_sent ? '✅ Statut mis à jour — email envoyé' : '✅ Statut mis à jour (email non envoyé)';
                showToast(msg, data.email_sent ? 'success' : 'error');
                setTimeout(() => select.style.borderColor = '#3d3d6b', 2000);
            } else {
                showToast('❌ Erreur : ' + (data.message || 'inconnue'), 'error');
            }
        })
        .catch(() => showToast('❌ Erreur réseau', 'error'));
    }

    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + type;
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
    </script>
</body>
</html>
