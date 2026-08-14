<?php
/**
 * Vos Paiements - AtlanTech E-commerce
 * Historique des transactions + méthodes de paiement sauvegardées
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=payments');
}

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors  = [];
$success = '';
$tab     = $_GET['tab'] ?? 'history'; // history | methods

// ──────────────────────────────────────────────
// Actions POST
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete_method') {
            $method_id = (int)($_POST['method_id'] ?? 0);
            $stmt = $mysqli->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $method_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Méthode de paiement supprimée.';
            $tab = 'methods';

        } elseif ($action === 'set_default_method') {
            $method_id = (int)($_POST['method_id'] ?? 0);
            $mysqli->begin_transaction();
            $stmt = $mysqli->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $mysqli->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $method_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $mysqli->commit();
            $success = 'Méthode par défaut mise à jour.';
            $tab = 'methods';
        }
    }
}

// ── Historique des commandes / transactions ──
$stmt = $mysqli->prepare(
    "SELECT id, order_number, status, total_amount, payment_method,
            subtotal, shipping_cost, discount_amount, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Totaux par statut ──
$total_paid = 0.0;
$total_pending = 0.0;
foreach ($transactions as $t) {
    if (in_array($t['status'], ['paid','shipped','delivered'])) {
        $total_paid += $t['total_amount'];
    } elseif ($t['status'] === 'pending') {
        $total_pending += $t['total_amount'];
    }
}

// ── Méthodes de paiement sauvegardées ──
$stmt = $mysqli->prepare(
    "SELECT id, payment_type, card_brand, card_last4, card_holder_name,
            expiry_month, expiry_year, is_default, is_verified, created_at
     FROM payment_methods
     WHERE user_id = ?
     ORDER BY is_default DESC, created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$methods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Stats par méthode de paiement ──
$method_stats = [];
foreach ($transactions as $t) {
    $m = $t['payment_method'];
    if (!isset($method_stats[$m])) $method_stats[$m] = ['count' => 0, 'total' => 0];
    $method_stats[$m]['count']++;
    $method_stats[$m]['total'] += $t['total_amount'];
}

$status_labels = [
    'pending'   => ['label' => 'En attente',  'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'paid'      => ['label' => 'Payée',       'color' => '#3b82f6', 'bg' => '#dbeafe'],
    'shipped'   => ['label' => 'Expédiée',    'color' => '#8b5cf6', 'bg' => '#ede9fe'],
    'delivered' => ['label' => 'Livrée',      'color' => '#10b981', 'bg' => '#d1fae5'],
    'cancelled' => ['label' => 'Annulée',     'color' => '#ef4444', 'bg' => '#fee2e2'],
];

$type_icons = [
    'credit_card'   => ['icon' => '💳', 'label' => 'Carte de crédit'],
    'debit_card'    => ['icon' => '💳', 'label' => 'Carte de débit'],
    'mobile_money'  => ['icon' => '📱', 'label' => 'Mobile Money'],
    'bank_transfer' => ['icon' => '🏦', 'label' => 'Virement bancaire'],
    'paypal'        => ['icon' => '🅿️', 'label' => 'PayPal'],
    'crypto'        => ['icon' => '₿',  'label' => 'Crypto'],
];

$method_icons = ['MonCash' => '📱', 'Zelle' => '💸', 'Bank' => '🏦', 'Cash' => '💵'];
$method_names = ['MonCash' => 'MonCash', 'Zelle' => 'Zelle', 'Bank' => 'Virement bancaire', 'Cash' => 'Espèces'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Vos Paiements - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body { background: #f3f3f3; }
        .pay-wrap { max-width: 960px; margin: 40px auto; padding: 0 20px 80px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 20px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }
        .page-title { font-size: 26px; font-weight: 700; color: #0F1111; margin-bottom: 24px; }

        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Résumé */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .summary-card {
            background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 20px;
            text-align: center;
        }
        .summary-icon  { font-size: 32px; margin-bottom: 8px; }
        .summary-value { font-size: 22px; font-weight: 800; color: #0F1111; margin-bottom: 4px; }
        .summary-label { font-size: 12px; color: #666; }

        /* Tabs */
        .tabs { display: flex; gap: 0; border-bottom: 2px solid #D5D9D9; margin-bottom: 28px; }
        .tab-btn { padding: 12px 24px; font-size: 15px; font-weight: 600; color: #565959;
            text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #0F1111; border-bottom-color: #e77600; }
        .tab-btn:hover { color: #0F1111; }

        /* Tableau transactions */
        .table-box { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; overflow: hidden; margin-bottom: 28px; }
        .table-box table { width: 100%; border-collapse: collapse; }
        .table-box th { background: #F0F2F2; font-size: 12px; font-weight: 700; padding: 10px 16px;
            text-align: left; text-transform: uppercase; color: #565959; }
        .table-box td { font-size: 13px; padding: 12px 16px; border-top: 1px solid #E7E7E7; vertical-align: middle; }
        .table-box tr:hover td { background: #fafafa; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .amount-positive { color: #B12704; font-weight: 700; }

        /* Méthodes sauvegardées */
        .methods-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .method-card {
            background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 20px;
        }
        .method-card.default { border-color: #e77600; box-shadow: 0 0 0 2px rgba(231,118,0,0.15); }
        .method-icon  { font-size: 32px; margin-bottom: 10px; }
        .method-name  { font-size: 15px; font-weight: 700; color: #0F1111; margin-bottom: 4px; }
        .method-detail { font-size: 13px; color: #565959; margin-bottom: 12px; }
        .default-badge { display: inline-block; background: #e77600; color: #fff; font-size: 11px;
            font-weight: 700; padding: 2px 10px; border-radius: 12px; margin-bottom: 10px; }
        .method-actions { display: flex; gap: 10px; }
        .btn-sm-link { font-size: 13px; color: #007185; text-decoration: none; cursor: pointer;
            background: none; border: none; padding: 0; }
        .btn-sm-link:hover { text-decoration: underline; }
        .btn-sm-link.danger { color: #ef4444; }
        .separator { color: #D5D9D9; }

        /* Stats méthodes */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 16px; }
        .stat-icon { font-size: 24px; margin-bottom: 6px; }
        .stat-name  { font-size: 13px; font-weight: 700; color: #0F1111; }
        .stat-detail { font-size: 12px; color: #666; }

        @media (max-width: 700px) {
            .table-box { overflow-x: auto; }
        }
    </style>
</head>
<body>

<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-user-circle"></i>&nbsp;Mon compte
    </a>
</div>

<div class="pay-wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Vos Paiements</span>
    </nav>

    <h1 class="page-title">Vos Paiements</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Résumé financier -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon">✅</div>
            <div class="summary-value"><?php echo number_format($total_paid, 0, ',', ' '); ?> HTG</div>
            <div class="summary-label">Total payé</div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">⏳</div>
            <div class="summary-value"><?php echo number_format($total_pending, 0, ',', ' '); ?> HTG</div>
            <div class="summary-label">En attente</div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">🧾</div>
            <div class="summary-value"><?php echo count($transactions); ?></div>
            <div class="summary-label">Commandes total</div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">💳</div>
            <div class="summary-value"><?php echo count($methods); ?></div>
            <div class="summary-label">Méthodes sauvegardées</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="payments.php?tab=history"  class="tab-btn <?php echo $tab === 'history'  ? 'active' : ''; ?>">
            Historique (<?php echo count($transactions); ?>)
        </a>
        <a href="payments.php?tab=methods"  class="tab-btn <?php echo $tab === 'methods'  ? 'active' : ''; ?>">
            Méthodes de paiement (<?php echo count($methods); ?>)
        </a>
        <a href="payments.php?tab=stats"    class="tab-btn <?php echo $tab === 'stats'    ? 'active' : ''; ?>">
            Statistiques
        </a>
    </div>

    <!-- ── TAB : Historique ── -->
    <?php if ($tab === 'history'): ?>
        <?php if (empty($transactions)): ?>
            <div style="text-align:center; padding:40px; color:#666; background:#fff; border:1px solid #D5D9D9; border-radius:8px;">
                <div style="font-size:48px; margin-bottom:14px;">🧾</div>
                <p>Aucune transaction pour l'instant.</p>
                <a href="../shop.php" style="color:#007185;">Découvrir nos produits</a>
            </div>
        <?php else: ?>
            <div class="table-box">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>N° Commande</th>
                            <th>Méthode</th>
                            <th>Statut</th>
                            <th style="text-align:right;">Montant</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <?php
                            $badge = $status_labels[$t['status']] ?? ['label' => $t['status'], 'color' => '#666', 'bg' => '#eee'];
                            ?>
                            <tr>
                                <td style="color:#565959; white-space:nowrap;">
                                    <?php echo date('d/m/Y', strtotime($t['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="order-detail.php?id=<?php echo $t['id']; ?>"
                                       style="color:#007185; text-decoration:none; font-weight:600;">
                                        #<?php echo htmlspecialchars($t['order_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo ($method_icons[$t['payment_method']] ?? '💰') . ' '; ?>
                                    <?php echo htmlspecialchars($method_names[$t['payment_method']] ?? $t['payment_method']); ?>
                                </td>
                                <td>
                                    <span class="status-badge"
                                          style="color:<?php echo $badge['color']; ?>; background:<?php echo $badge['bg']; ?>;">
                                        <?php echo $badge['label']; ?>
                                    </span>
                                </td>
                                <td style="text-align:right;" class="amount-positive">
                                    <?php echo number_format($t['total_amount'], 2); ?> HTG
                                </td>
                                <td>
                                    <a href="order-detail.php?id=<?php echo $t['id']; ?>"
                                       style="font-size:12px; color:#007185;">Détail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- ── TAB : Méthodes sauvegardées ── -->
    <?php elseif ($tab === 'methods'): ?>
        <?php if (empty($methods)): ?>
            <div style="text-align:center; padding:40px; color:#666; background:#fff; border:1px solid #D5D9D9; border-radius:8px;">
                <div style="font-size:48px; margin-bottom:14px;">💳</div>
                <p>Aucune méthode de paiement sauvegardée.</p>
                <p style="font-size:13px;">Les méthodes sont enregistrées automatiquement lors de vos commandes.</p>
            </div>
        <?php else: ?>
            <div class="methods-grid">
                <?php foreach ($methods as $m): ?>
                    <?php $ti = $type_icons[$m['payment_type']] ?? ['icon' => '💳', 'label' => $m['payment_type']]; ?>
                    <div class="method-card <?php echo $m['is_default'] ? 'default' : ''; ?>">
                        <?php if ($m['is_default']): ?>
                            <div class="default-badge">⭐ Par défaut</div><br>
                        <?php endif; ?>
                        <div class="method-icon"><?php echo $ti['icon']; ?></div>
                        <div class="method-name"><?php echo $ti['label']; ?></div>
                        <div class="method-detail">
                            <?php if (!empty($m['card_brand']) && !empty($m['card_last4'])): ?>
                                <?php echo htmlspecialchars($m['card_brand']); ?>
                                se terminant par <strong><?php echo htmlspecialchars($m['card_last4']); ?></strong><br>
                                <?php if (!empty($m['card_holder_name'])): ?>
                                    <?php echo htmlspecialchars($m['card_holder_name']); ?><br>
                                <?php endif; ?>
                                <?php if ($m['expiry_month'] && $m['expiry_year']): ?>
                                    Expire : <?php echo str_pad($m['expiry_month'],2,'0',STR_PAD_LEFT).'/'.substr($m['expiry_year'],2); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Enregistrée le <?php echo date('d/m/Y', strtotime($m['created_at'])); ?>
                            <?php endif; ?>
                            <?php if ($m['is_verified']): ?>
                                <br><span style="color:#10b981; font-size:11px;">✔ Vérifiée</span>
                            <?php endif; ?>
                        </div>
                        <div class="method-actions">
                            <?php if (!$m['is_default']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action"    value="set_default_method">
                                    <input type="hidden" name="method_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn-sm-link">Par défaut</button>
                                </form>
                                <span class="separator">|</span>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Supprimer cette méthode ?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action"    value="delete_method">
                                <input type="hidden" name="method_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="btn-sm-link danger">Supprimer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- ── TAB : Statistiques ── -->
    <?php else: ?>
        <?php if (empty($method_stats)): ?>
            <div style="text-align:center; padding:40px; color:#666; background:#fff; border:1px solid #D5D9D9; border-radius:8px;">
                Aucune statistique disponible.
            </div>
        <?php else: ?>
            <h2 style="font-size:17px; font-weight:700; margin-bottom:16px;">Répartition par méthode de paiement</h2>
            <div class="stats-grid">
                <?php foreach ($method_stats as $method => $stat): ?>
                    <div class="stat-card">
                        <div class="stat-icon"><?php echo $method_icons[$method] ?? '💰'; ?></div>
                        <div class="stat-name"><?php echo htmlspecialchars($method_names[$method] ?? $method); ?></div>
                        <div class="stat-detail">
                            <?php echo $stat['count']; ?> commande<?php echo $stat['count'] > 1 ? 's' : ''; ?><br>
                            <strong><?php echo number_format($stat['total'], 0, ',', ' '); ?> HTG</strong>
                        </div>
                        <?php if ($total_paid > 0): ?>
                            <div style="margin-top:8px;">
                                <div style="background:#E7E7E7; border-radius:4px; height:4px;">
                                    <div style="width:<?php echo min(100, round($stat['total'] / ($total_paid + $total_pending) * 100)); ?>%;
                                                height:4px; border-radius:4px; background:#007185;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tableau mensuel -->
            <?php
            $monthly = [];
            foreach ($transactions as $t) {
                $month = date('Y-m', strtotime($t['created_at']));
                if (!isset($monthly[$month])) $monthly[$month] = ['total' => 0, 'count' => 0];
                $monthly[$month]['total'] += $t['total_amount'];
                $monthly[$month]['count']++;
            }
            krsort($monthly);
            $monthly_slice = array_slice($monthly, 0, 6, true);
            $max_monthly = $monthly_slice ? max(array_column($monthly_slice, 'total')) : 1;
            ?>

            <?php if (!empty($monthly_slice)): ?>
                <h2 style="font-size:17px; font-weight:700; margin-bottom:16px;">Dépenses mensuelles</h2>
                <div style="background:#fff; border:1px solid #D5D9D9; border-radius:8px; padding:20px 24px;">
                    <?php foreach ($monthly_slice as $month => $data): ?>
                        <?php
                        $label = ucfirst(strftime('%B %Y', mktime(0,0,0, (int)substr($month,5,2), 1, (int)substr($month,0,4))));
                        $pct   = round($data['total'] / $max_monthly * 100);
                        ?>
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                            <div style="width:110px; font-size:12px; color:#565959; flex-shrink:0;"><?php echo $label; ?></div>
                            <div style="flex:1; background:#E7E7E7; border-radius:4px; height:18px; overflow:hidden;">
                                <div style="width:<?php echo $pct; ?>%; height:100%; background:#007185; border-radius:4px;"></div>
                            </div>
                            <div style="width:120px; text-align:right; font-size:12px; font-weight:700; color:#0F1111;">
                                <?php echo number_format($data['total'], 0, ',', ' '); ?> HTG
                                <span style="font-weight:400; color:#888;">(<?php echo $data['count']; ?>)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:28px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
