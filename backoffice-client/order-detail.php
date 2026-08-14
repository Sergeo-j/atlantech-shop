<?php
/**
 * Détail d'une Commande - AtlanTech E-commerce
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=orders');
}

$user_id  = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    redirect('orders.php');
}

// Charger la commande (vérifier qu'elle appartient à l'utilisateur)
$stmt = $mysqli->prepare(
    "SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1"
);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    redirect('orders.php');
}

$status_labels = [
    'pending'   => ['label' => 'En attente',  'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'paid'      => ['label' => 'Payée',       'color' => '#3b82f6', 'bg' => '#dbeafe'],
    'shipped'   => ['label' => 'Expédiée',    'color' => '#8b5cf6', 'bg' => '#ede9fe'],
    'delivered' => ['label' => 'Livrée',      'color' => '#10b981', 'bg' => '#d1fae5'],
    'cancelled' => ['label' => 'Annulée',     'color' => '#ef4444', 'bg' => '#fee2e2'],
];

$payment_labels = [
    'MonCash' => 'MonCash',
    'Zelle'   => 'Zelle',
    'Bank'    => 'Virement bancaire',
    'Cash'    => 'Espèces',
];

$st    = $order['status'];
$badge = $status_labels[$st] ?? ['label' => $st, 'color' => '#666', 'bg' => '#eee'];
$pmeth = $payment_labels[$order['payment_method']] ?? $order['payment_method'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Commande #<?php echo htmlspecialchars($order['order_number']); ?> - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        .detail-wrap { max-width: 860px; margin: 40px auto; padding: 0 20px 80px; }
        .page-title { font-size: 24px; font-weight: 700; color: #0F1111; margin-bottom: 6px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 28px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }

        .order-box { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 24px; margin-bottom: 20px; }
        .box-title { font-size: 17px; font-weight: 700; color: #0F1111; margin-bottom: 16px;
            padding-bottom: 10px; border-bottom: 1px solid #E7E7E7; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0;
            border-bottom: 1px solid #F0F2F2; font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #565959; }
        .info-value { font-weight: 600; color: #0F1111; text-align: right; }

        .status-badge {
            display: inline-block; padding: 4px 14px; border-radius: 12px;
            font-size: 13px; font-weight: 700;
        }
        .total-row { font-size: 17px; font-weight: 700; }
        .total-amount { color: #B12704; }
    </style>
</head>
<body>

<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="orders.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-arrow-left"></i>&nbsp;Mes commandes
    </a>
</div>

<div class="detail-wrap">

    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <a href="orders.php">Mes commandes</a> &rsaquo;
        <span>#<?php echo htmlspecialchars($order['order_number']); ?></span>
    </nav>

    <h1 class="page-title">Commande #<?php echo htmlspecialchars($order['order_number']); ?></h1>
    <p style="font-size:13px; color:#565959; margin-bottom:24px;">
        Passée le <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?>
    </p>

    <!-- Statut -->
    <div class="order-box">
        <div class="box-title">Statut de la commande</div>
        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
            <span class="status-badge"
                  style="color:<?php echo $badge['color']; ?>; background:<?php echo $badge['bg']; ?>;">
                <?php echo $badge['label']; ?>
            </span>
            <span style="font-size:13px; color:#565959;">
                Paiement : <strong><?php echo htmlspecialchars($pmeth); ?></strong>
            </span>
        </div>
    </div>

    <!-- Informations client & livraison -->
    <div class="order-box">
        <div class="box-title">Informations de livraison</div>
        <div class="info-row">
            <span class="info-label">Destinataire</span>
            <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Téléphone</span>
            <span class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? '—'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Adresse</span>
            <span class="info-value" style="max-width:60%;">
                <?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? '—')); ?>
            </span>
        </div>
    </div>

    <!-- Récapitulatif financier -->
    <div class="order-box">
        <div class="box-title">Récapitulatif</div>
        <div class="info-row">
            <span class="info-label">Sous-total</span>
            <span class="info-value"><?php echo number_format($order['subtotal'] ?? $order['total_amount'], 2); ?> HTG</span>
        </div>
        <?php if (!empty($order['shipping_cost']) && $order['shipping_cost'] > 0): ?>
        <div class="info-row">
            <span class="info-label">Frais de livraison</span>
            <span class="info-value"><?php echo number_format($order['shipping_cost'], 2); ?> HTG</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
        <div class="info-row">
            <span class="info-label">Remise</span>
            <span class="info-value" style="color:#067D62;">
                - <?php echo number_format($order['discount_amount'], 2); ?> HTG
            </span>
        </div>
        <?php endif; ?>
        <div class="info-row total-row">
            <span class="info-label">Total</span>
            <span class="info-value total-amount">
                <?php echo number_format($order['total_amount'], 2); ?> HTG
            </span>
        </div>
    </div>

    <div style="display:flex; gap:16px; margin-top:16px;">
        <a href="orders.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour aux commandes
        </a>
        <span style="color:#D5D9D9;">|</span>
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            Tableau de bord
        </a>
    </div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
