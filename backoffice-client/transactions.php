<?php
/**
 * Vos Transactions - AtlanTech E-commerce
 * Historique complet : commandes + cartes cadeaux utilisées + points
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=transactions');

$user_id = (int)$_SESSION['user_id'];

// Filtres
$filter_month  = $_GET['month']  ?? '';
$filter_method = $_GET['method'] ?? '';
$filter_status = $_GET['status'] ?? '';
$allowed_statuses = ['pending','paid','shipped','delivered','cancelled'];
if (!in_array($filter_status, $allowed_statuses)) $filter_status = '';

$allowed_methods = ['MonCash','Zelle','Bank','Cash'];
if (!in_array($filter_method, $allowed_methods)) $filter_method = '';

// Construire la requête
$where  = "user_id = ?";
$params = [$user_id];
$types  = 'i';

if ($filter_status !== '') { $where .= " AND status = ?";         $params[] = $filter_status; $types .= 's'; }
if ($filter_method !== '') { $where .= " AND payment_method = ?"; $params[] = $filter_method; $types .= 's'; }
if ($filter_month  !== '' && preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $where .= " AND DATE_FORMAT(created_at,'%Y-%m') = ?";
    $params[] = $filter_month; $types .= 's';
}

$stmt = $mysqli->prepare(
    "SELECT id, order_number, status, payment_method, total_amount,
            subtotal, shipping_cost, discount_amount, created_at
     FROM orders WHERE $where ORDER BY created_at DESC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totaux filtrés
$grand_total = array_sum(array_column($transactions, 'total_amount'));

// Mois disponibles pour le filtre
$stmt = $mysqli->prepare(
    "SELECT DISTINCT DATE_FORMAT(created_at,'%Y-%m') AS ym, DATE_FORMAT(created_at,'%M %Y') AS label
     FROM orders WHERE user_id = ? ORDER BY ym DESC LIMIT 24"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$months = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$status_labels = ['pending'=>['label'=>'En attente','color'=>'#f59e0b','bg'=>'#fef3c7'],
                  'paid'   =>['label'=>'Payée',     'color'=>'#3b82f6','bg'=>'#dbeafe'],
                  'shipped'=>['label'=>'Expédiée',  'color'=>'#8b5cf6','bg'=>'#ede9fe'],
                  'delivered'=>['label'=>'Livrée',  'color'=>'#10b981','bg'=>'#d1fae5'],
                  'cancelled'=>['label'=>'Annulée', 'color'=>'#ef4444','bg'=>'#fee2e2']];
$method_icons = ['MonCash'=>'📱','Zelle'=>'💸','Bank'=>'🏦','Cash'=>'💵'];
$method_names = ['MonCash'=>'MonCash','Zelle'=>'Zelle','Bank'=>'Virement','Cash'=>'Espèces'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Vos Transactions - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:1000px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}

        /* Filtres */
        .filters{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:18px 20px;margin-bottom:22px;display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;}
        .filter-group{display:flex;flex-direction:column;gap:4px;}
        .filter-label{font-size:12px;font-weight:600;color:#565959;}
        .filter-select{padding:7px 10px;border:1px solid #888C8C;border-radius:6px;font-size:13px;background:#fff;}
        .filter-select:focus{outline:none;border-color:#e77600;}
        .btn-filter{padding:8px 18px;background:#0F1111;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;}
        .btn-reset{padding:8px 14px;background:#fff;color:#0F1111;border:1px solid #D5D9D9;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none;}

        /* Résumé */
        .summary{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
        .summary-total{font-size:18px;font-weight:800;color:#B12704;}
        .summary-count{font-size:13px;color:#565959;}

        /* Tableau */
        .table-box{background:#fff;border:1px solid #D5D9D9;border-radius:8px;overflow:hidden;}
        .table-box table{width:100%;border-collapse:collapse;}
        .table-box th{background:#F0F2F2;font-size:12px;font-weight:700;padding:10px 16px;text-align:left;text-transform:uppercase;color:#565959;white-space:nowrap;}
        .table-box td{font-size:13px;padding:13px 16px;border-top:1px solid #E7E7E7;vertical-align:middle;}
        .table-box tr:hover td{background:#fafafa;}
        .status-badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;}
        .amount{font-weight:700;color:#B12704;}
        .order-link{color:#007185;text-decoration:none;font-weight:600;}
        .order-link:hover{text-decoration:underline;}

        /* Vide */
        .empty{text-align:center;padding:50px 20px;background:#fff;border:1px solid #D5D9D9;border-radius:8px;color:#666;}
        .empty .icon{font-size:52px;display:block;margin-bottom:14px;}
        @media(max-width:700px){.table-box{overflow-x:auto;}}
    </style>
</head>
<body>
<div style="background:#131921;padding:12px 20px;display:flex;align-items:center;gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff;font-size:13px;text-decoration:none;"><i class="fas fa-user-circle"></i>&nbsp;Mon compte</a>
</div>

<div class="wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Vos Transactions</span>
    </nav>
    <h1 class="page-title">Vos Transactions</h1>

    <!-- Filtres -->
    <form method="GET" action="transactions.php">
        <div class="filters">
            <div class="filter-group">
                <span class="filter-label">Mois</span>
                <select name="month" class="filter-select">
                    <option value="">Tous les mois</option>
                    <?php foreach ($months as $m): ?>
                        <option value="<?php echo $m['ym']; ?>" <?php echo $filter_month===$m['ym']?'selected':''; ?>>
                            <?php echo htmlspecialchars($m['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Méthode de paiement</span>
                <select name="method" class="filter-select">
                    <option value="">Toutes</option>
                    <?php foreach ($allowed_methods as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $filter_method===$m?'selected':''; ?>>
                            <?php echo $method_names[$m]; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Statut</span>
                <select name="status" class="filter-select">
                    <option value="">Tous</option>
                    <?php foreach ($status_labels as $k=>$v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $filter_status===$k?'selected':''; ?>>
                            <?php echo $v['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">Filtrer</button>
            <a href="transactions.php" class="btn-reset">Réinitialiser</a>
        </div>
    </form>

    <!-- Résumé -->
    <div class="summary">
        <span class="summary-count"><?php echo count($transactions); ?> transaction<?php echo count($transactions)>1?'s':''; ?> trouvée<?php echo count($transactions)>1?'s':''; ?></span>
        <span class="summary-total">Total : <?php echo number_format($grand_total, 2); ?> HTG</span>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="empty">
            <span class="icon">🧾</span>
            <p>Aucune transaction trouvée<?php echo ($filter_month||$filter_method||$filter_status) ? ' pour ce filtre' : ''; ?>.</p>
            <?php if ($filter_month||$filter_method||$filter_status): ?>
                <a href="transactions.php" style="color:#007185;">Voir toutes les transactions</a>
            <?php else: ?>
                <a href="../shop.php" style="color:#007185;">Découvrir nos produits</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>N° Commande</th>
                        <th>Paiement</th>
                        <th>Statut</th>
                        <th>Sous-total</th>
                        <th>Livraison</th>
                        <th>Remise</th>
                        <th style="text-align:right;">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t):
                        $badge = $status_labels[$t['status']] ?? ['label'=>$t['status'],'color'=>'#666','bg'=>'#eee'];
                    ?>
                        <tr>
                            <td style="color:#565959;white-space:nowrap;"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
                            <td><a href="order-detail.php?id=<?php echo $t['id']; ?>" class="order-link">#<?php echo htmlspecialchars($t['order_number']); ?></a></td>
                            <td><?php echo ($method_icons[$t['payment_method']]??'💰').' '.($method_names[$t['payment_method']]??$t['payment_method']); ?></td>
                            <td><span class="status-badge" style="color:<?php echo $badge['color']; ?>;background:<?php echo $badge['bg']; ?>;"><?php echo $badge['label']; ?></span></td>
                            <td><?php echo number_format($t['subtotal']??$t['total_amount'], 2); ?> HTG</td>
                            <td><?php echo ($t['shipping_cost']>0) ? number_format($t['shipping_cost'],2).' HTG' : '—'; ?></td>
                            <td><?php echo ($t['discount_amount']>0) ? '<span style="color:#067D62;">-'.number_format($t['discount_amount'],2).' HTG</span>' : '—'; ?></td>
                            <td style="text-align:right;" class="amount"><?php echo number_format($t['total_amount'], 2); ?> HTG</td>
                            <td><a href="order-detail.php?id=<?php echo $t['id']; ?>" style="font-size:12px;color:#007185;">Détail</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F0F2F2;">
                        <td colspan="7" style="text-align:right;font-size:14px;font-weight:700;padding:12px 16px;">TOTAL</td>
                        <td style="text-align:right;font-size:15px;font-weight:800;color:#B12704;padding:12px 16px;"><?php echo number_format($grand_total,2); ?> HTG</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
