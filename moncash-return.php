<?php
/**
 * AtlanTech - retour MonCash sandbox/live.
 *
 * Apres paiement, MonCash redirige ici. On confirme le paiement via l'API,
 * on enregistre le transaction_id, puis on marque la commande comme payee.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/moncash.php';

if (!isLoggedIn()) {
    redirect('account.php?redirect=checkout');
}

$user_id = (int)$_SESSION['user_id'];

$pending      = $_SESSION['moncash_pending'] ?? null;
$order_id     = (int)($pending['order_id'] ?? 0);
$order_number = trim((string)($pending['order_number'] ?? ''));

$transactionId = trim((string)(
    $_GET['transactionId']
    ?? $_GET['transaction_id']
    ?? $_GET['transactionid']
    ?? ''
));

$returned_order = trim((string)(
    $_GET['orderId']
    ?? $_GET['order_id']
    ?? $_GET['orderNumber']
    ?? $_GET['order_number']
    ?? $_GET['reference']
    ?? ''
));
if ($order_number === '' && $returned_order !== '') {
    $order_number = $returned_order;
}

$payment = null;

if ($transactionId !== '') {
    $result = moncash_retrieve_by_transaction($transactionId);
    if (!empty($result['ok'])) {
        $payment = $result['payment'];
    }
}

if ($payment && $order_number === '') {
    $order_number = trim((string)($payment['reference'] ?? ''));
}

if (!$payment && $order_number !== '') {
    $result = moncash_retrieve_by_order($order_number);
    if (!empty($result['ok'])) {
        $payment = $result['payment'];
    }
}

if ($order_id <= 0 && $order_number !== '') {
    try {
        $stmt = $mysqli->prepare(
            "SELECT id FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('si', $order_number, $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $order_id = (int)$row['id'];
            }
        }
    } catch (\Throwable $e) {
        error_log('moncash-return order lookup: ' . $e->getMessage());
    }
}

$success      = false;
$display_txid = '';
$amount_paid  = null;
$failure_note = '';

if ($payment) {
    $display_txid = trim((string)($payment['transaction_id'] ?? $transactionId));
    $amount_paid  = isset($payment['cost']) ? (float)$payment['cost'] : null;
    $message      = strtolower(trim((string)($payment['message'] ?? '')));
    $is_paid      = in_array($message, ['successful', 'success', 'approved'], true);

    if (!$is_paid) {
        $failure_note = "MonCash n'a pas retourne un statut successful.";
    }

    if ($is_paid && $order_id > 0 && $display_txid !== '') {
        try {
            $current = null;
            $stmt = $mysqli->prepare(
                "SELECT status, total_amount
                 FROM orders
                 WHERE id = ? AND user_id = ? AND payment_method = 'MonCash'
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('ii', $order_id, $user_id);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if (!$current) {
                $failure_note = 'Commande MonCash introuvable pour ce compte.';
            } else {
                $expected_total = (float)($current['total_amount'] ?? 0);
                $amount_ok = $amount_paid === null || abs($amount_paid - $expected_total) < 0.01;
                $old_status = (string)($current['status'] ?? 'pending');
                $new_status = $old_status === 'pending' ? 'paid' : $old_status;

                if (!$amount_ok) {
                    $failure_note = 'Montant MonCash different du total de la commande.';
                    error_log(
                        'moncash-return amount mismatch order=' . $order_number
                        . ' expected=' . $expected_total
                        . ' paid=' . (string)$amount_paid
                    );
                } else {
                    $processor = 'MonCash';
                    $stmt = $mysqli->prepare(
                        "UPDATE orders
                         SET payment_transaction_id = ?,
                             payment_processor = ?,
                             status = ?
                         WHERE id = ? AND user_id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param('sssii', $display_txid, $processor, $new_status, $order_id, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    try {
                        $note = 'Paiement MonCash sandbox confirme - transaction ' . $display_txid
                              . ($amount_paid !== null ? ' (' . number_format($amount_paid, 2) . ' HTG)' : '');
                        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                        $history = $mysqli->prepare(
                            "INSERT INTO order_status_history
                                (order_id, old_status, new_status, changed_by_type, changed_by_id, changed_by_name, note, ip_address)
                             VALUES (?, ?, ?, 'system', ?, 'MonCash Sandbox', ?, ?)"
                        );
                        if ($history) {
                            $history->bind_param('ississ', $order_id, $old_status, $new_status, $user_id, $note, $ip);
                            $history->execute();
                            $history->close();
                        }
                    } catch (\Throwable $e) {
                        error_log('moncash history: ' . $e->getMessage());
                    }

                    $success = true;
                }
            }
        } catch (\Throwable $e) {
            $failure_note = 'Erreur locale pendant la confirmation de la commande.';
            error_log('moncash-return update: ' . $e->getMessage());
        }
    } elseif ($is_paid) {
        $failure_note = 'Paiement recu, mais commande locale introuvable.';
    }
} else {
    $failure_note = 'Aucun paiement MonCash confirme par API.';
}

unset($_SESSION['moncash_pending']);

$user_first_name = isset($_SESSION['user_name']) ? explode(' ', $_SESSION['user_name'])[0] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Paiement MonCash - AtlanTech</title>
  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css" />
  <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime(__DIR__.'/assets/css/mobile.css'); ?>" />
  <!-- Google Fonts non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>
  <style>
    body { background:#f7f7f7; font-family:'Segoe UI',Tahoma,sans-serif; }
    .mc-box { max-width:560px; margin:60px auto; background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.08); padding:48px 36px; text-align:center; }
    .mc-icon { font-size:4rem; margin-bottom:18px; }
    .mc-icon.ok { color:#27ae60; }
    .mc-icon.ko { color:#e74c3c; }
    .mc-box h2 { font-size:1.5rem; font-weight:700; color:#222; margin-bottom:10px; }
    .mc-box p { color:#666; margin-bottom:8px; }
    .mc-txid { font-family:monospace; background:#f0fff4; border:1px solid #b7eb8f; padding:8px 16px; border-radius:8px; display:inline-block; margin:14px 0; color:#237804; font-weight:700; word-break:break-all; }
    .mc-num { font-weight:700; color:#ff9100; background:#fff8f0; padding:10px 20px; border-radius:8px; display:inline-block; margin:10px 0; }
    .mc-btn { display:inline-block; margin-top:18px; padding:12px 30px; background:#ff9100; color:#fff; border-radius:8px; font-weight:700; text-decoration:none; }
    .mc-btn:hover { background:#e07d00; color:#fff; }
    .mc-btn.alt { background:#eee; color:#555; margin-left:8px; }
  </style>
</head>
<body>
  <div class="mc-box">
    <?php if ($success): ?>
      <div class="mc-icon ok"><i class="fas fa-check-circle"></i></div>
      <h2>Paiement MonCash confirme</h2>
      <p>Merci <?= htmlspecialchars($user_first_name) ?>. Votre paiement MonCash a bien ete enregistre.</p>
      <?php if ($order_number): ?>
        <div class="mc-num"><?= htmlspecialchars($order_number) ?></div>
      <?php endif; ?>
      <?php if ($display_txid): ?>
        <p style="margin-top:14px; font-size:.9rem;">Numero de transaction MonCash :</p>
        <div class="mc-txid"><?= htmlspecialchars($display_txid) ?></div>
      <?php endif; ?>
      <p style="font-size:.88rem; color:#888;">
        Votre commande est maintenant marquee comme payee et prete pour la preparation.
      </p>
      <a href="backoffice-client/orders.php" class="mc-btn">Voir mes commandes</a>
      <a href="shop.php" class="mc-btn alt">Continuer mes achats</a>
    <?php else: ?>
      <div class="mc-icon ko"><i class="fas fa-times-circle"></i></div>
      <h2>Paiement non confirme</h2>
      <p>Nous n'avons pas pu confirmer votre paiement MonCash pour le moment.</p>
      <?php if ($failure_note): ?>
        <p style="font-size:.88rem; color:#a94442;"><?= htmlspecialchars($failure_note) ?></p>
      <?php endif; ?>
      <?php if ($order_number): ?>
        <p>Votre commande <strong><?= htmlspecialchars($order_number) ?></strong> reste en attente.</p>
      <?php endif; ?>
      <p style="font-size:.88rem; color:#888;">
        Si vous avez bien ete debite, contactez-nous au <strong>+509 4466-7553</strong>
        avec votre numero de commande.
      </p>
      <a href="backoffice-client/orders.php" class="mc-btn">Voir mes commandes</a>
      <a href="checkout.php" class="mc-btn alt">Reessayer</a>
    <?php endif; ?>
  </div>
</body>
</html>
