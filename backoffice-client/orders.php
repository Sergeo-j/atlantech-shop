<?php
/**
 * Mes Commandes - AtlanTech E-commerce
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=orders');
}

$user_id = (int)$_SESSION['user_id'];

// Filtres
$filter_status = $_GET['status'] ?? '';
$allowed_statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = '';
}

// Construire la requête
$where  = "user_id = ?";
$params = [$user_id];
$types  = 'i';

if ($filter_status !== '') {
    $where   .= " AND status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}

$stmt = $mysqli->prepare(
    "SELECT id, order_number, status, total_amount, payment_method, customer_name, created_at
     FROM orders
     WHERE $where
     ORDER BY created_at DESC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Libellés statuts
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Mes Commandes - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/animate.css"/>
    <link rel="stylesheet" href="../assets/css/uikit.min.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        .orders-wrap { max-width: 1000px; margin: 40px auto; padding: 0 20px 60px; }
        .page-title { font-size: 26px; font-weight: 700; margin-bottom: 8px; color: #0F1111; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 28px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }

        /* Filtres */
        .status-filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; }
        .filter-btn {
            padding: 7px 16px; border-radius: 20px; border: 1px solid #D5D9D9;
            background: #fff; color: #0F1111; font-size: 13px;
            text-decoration: none; transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #0F1111; color: #fff; border-color: #0F1111;
        }

        /* Carte commande */
        .order-card {
            background: #fff; border: 1px solid #D5D9D9;
            border-radius: 8px; margin-bottom: 16px; overflow: hidden;
        }
        .order-header {
            background: #F0F2F2; padding: 12px 20px;
            display: flex; flex-wrap: wrap; gap: 16px; align-items: center;
            justify-content: space-between;
        }
        .order-meta { display: flex; gap: 24px; flex-wrap: wrap; }
        .order-meta-item { font-size: 12px; color: #565959; }
        .order-meta-item strong { display: block; font-size: 13px; color: #0F1111; }
        .order-body { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .order-info { flex: 1; }
        .order-number { font-weight: 700; font-size: 15px; color: #0F1111; margin-bottom: 4px; }
        .order-customer { font-size: 13px; color: #565959; }
        .order-total { font-size: 18px; font-weight: 700; color: #0F1111; white-space: nowrap; }
        .status-badge {
            display: inline-block; padding: 4px 12px; border-radius: 12px;
            font-size: 12px; font-weight: 600;
        }
        .order-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-view {
            padding: 8px 18px; background: #FFD814; border: 1px solid #FFA41C;
            border-radius: 6px; font-size: 13px; font-weight: 600; color: #0F1111;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-view:hover { background: #F7CA00; }

        /* Vide */
        .empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .empty-state .icon { font-size: 64px; display: block; margin-bottom: 16px; }
        .empty-state p { font-size: 16px; margin-bottom: 20px; }
        .btn-shop {
            display: inline-block; padding: 12px 28px; background: #FFD814;
            border: 1px solid #FFA41C; border-radius: 8px; font-weight: 700;
            color: #0F1111; text-decoration: none;
        }
        .btn-shop:hover { background: #F7CA00; }

        @media (max-width: 600px) {
            .order-header { flex-direction: column; }
            .order-body  { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- ========= HEADER SIMPLIFIÉ ========= -->
<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php">
        <img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;">
    </a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-user-circle"></i>&nbsp;Mon compte
    </a>
    <a href="../cart.php" style="color:#fff; font-size:13px; text-decoration:none; margin-left:16px;">
        <i class="fas fa-shopping-cart"></i>
    </a>
</div>

<!-- ========= CONTENU ========= -->
<div class="orders-wrap">

    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Mes commandes</span>
    </nav>

    <h1 class="page-title">Mes Commandes</h1>

    <!-- Filtres par statut -->
    <div class="status-filters">
        <a href="orders.php" class="filter-btn <?php echo $filter_status === '' ? 'active' : ''; ?>">
            Toutes (<?php echo count($orders); ?>)
        </a>
        <?php foreach ($status_labels as $key => $info): ?>
            <?php
            $count_for_status = count(array_filter($orders, fn($o) => $o['status'] === $key));
            ?>
            <a href="orders.php?status=<?php echo $key; ?>"
               class="filter-btn <?php echo $filter_status === $key ? 'active' : ''; ?>">
                <?php echo $info['label']; ?>
                <?php if ($count_for_status > 0): ?>
                    (<?php echo $count_for_status; ?>)
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <span class="icon">📦</span>
            <p>
                <?php if ($filter_status !== ''): ?>
                    Aucune commande avec le statut
                    « <?php echo htmlspecialchars($status_labels[$filter_status]['label']); ?> ».
                <?php else: ?>
                    Vous n'avez encore passé aucune commande.
                <?php endif; ?>
            </p>
            <a href="../shop.php" class="btn-shop">Découvrir nos produits</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php
            $st     = $order['status'];
            $badge  = $status_labels[$st]  ?? ['label' => $st, 'color' => '#666', 'bg' => '#eee'];
            $pmeth  = $payment_labels[$order['payment_method']] ?? $order['payment_method'];
            $date   = date('d/m/Y', strtotime($order['created_at']));
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-meta">
                        <div class="order-meta-item">
                            <span>COMMANDE PASSÉE LE</span>
                            <strong><?php echo $date; ?></strong>
                        </div>
                        <div class="order-meta-item">
                            <span>TOTAL</span>
                            <strong><?php echo number_format($order['total_amount'], 2); ?> HTG</strong>
                        </div>
                        <div class="order-meta-item">
                            <span>PAIEMENT</span>
                            <strong><?php echo htmlspecialchars($pmeth); ?></strong>
                        </div>
                    </div>
                    <div style="font-size:12px; color:#565959;">
                        N° <?php echo htmlspecialchars($order['order_number']); ?>
                    </div>
                </div>

                <div class="order-body">
                    <div class="order-info">
                        <div class="order-number">
                            Commande #<?php echo htmlspecialchars($order['order_number']); ?>
                        </div>
                        <div class="order-customer">
                            <i class="fas fa-user" style="font-size:11px;"></i>
                            <?php echo htmlspecialchars($order['customer_name']); ?>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                        <span class="status-badge"
                              style="color:<?php echo $badge['color']; ?>; background:<?php echo $badge['bg']; ?>;">
                            <?php echo $badge['label']; ?>
                        </span>
                        <div class="order-total">
                            <?php echo number_format($order['total_amount'], 2); ?> HTG
                        </div>
                    </div>

                    <div class="order-actions">
                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-view">
                            Voir le détail
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:30px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<!-- Scripts -->
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
