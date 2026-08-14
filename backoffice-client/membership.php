<?php
/**
 * Avantages VIP - Programme de fidélité
 * AtlanTech E-commerce
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=membership');
}

$user_id = (int)$_SESSION['user_id'];

// ── Total de points disponibles ──
$stmt = $mysqli->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN transaction_type IN ('earned','adjusted') THEN points ELSE 0 END), 0)
      - COALESCE(SUM(CASE WHEN transaction_type IN ('redeemed','expired') THEN points ELSE 0 END), 0)
        AS balance,
        COALESCE(SUM(CASE WHEN transaction_type = 'earned' THEN points ELSE 0 END), 0) AS total_earned
     FROM loyalty_transactions
     WHERE user_id = ?"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$pts = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balance      = max(0, (int)$pts['balance']);
$total_earned = (int)$pts['total_earned'];

// ── Historique des 10 dernières transactions de fidélité ──
$stmt = $mysqli->prepare(
    "SELECT transaction_type, points, description, created_at
     FROM loyalty_transactions
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 10"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Total dépensé (commandes payées + livrées) ──
$stmt = $mysqli->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) AS total_spent,
            COUNT(*) AS orders_count
     FROM orders
     WHERE user_id = ? AND status IN ('paid','shipped','delivered')"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$spending = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_spent  = (float)$spending['total_spent'];
$orders_count = (int)$spending['orders_count'];

// ── Calcul du palier VIP ──
$tiers = [
    ['name' => 'Bronze',   'min' => 0,       'max' => 49999,  'color' => '#cd7f32', 'bg' => '#fdf3e7', 'icon' => '🥉', 'discount' => 0,  'points_rate' => 1],
    ['name' => 'Argent',   'min' => 50000,   'max' => 199999, 'color' => '#9e9e9e', 'bg' => '#f5f5f5', 'icon' => '🥈', 'discount' => 5,  'points_rate' => 1.5],
    ['name' => 'Or',       'min' => 200000,  'max' => 499999, 'color' => '#f9a825', 'bg' => '#fffde7', 'icon' => '🥇', 'discount' => 10, 'points_rate' => 2],
    ['name' => 'Platine',  'min' => 500000,  'max' => PHP_INT_MAX, 'color' => '#1565c0', 'bg' => '#e3f2fd', 'icon' => '💎', 'discount' => 15, 'points_rate' => 3],
];

$current_tier  = $tiers[0];
$next_tier     = $tiers[1];
foreach ($tiers as $i => $tier) {
    if ($total_spent >= $tier['min']) {
        $current_tier = $tier;
        $next_tier    = $tiers[$i + 1] ?? null;
    }
}

$progress_pct = 0;
if ($next_tier) {
    $range        = $next_tier['min'] - $current_tier['min'];
    $done         = $total_spent - $current_tier['min'];
    $progress_pct = min(100, round($done / $range * 100));
    $remaining    = $next_tier['min'] - $total_spent;
}

$type_icons  = ['earned' => '➕', 'redeemed' => '🛒', 'expired' => '⏰', 'adjusted' => '✏️'];
$type_labels = ['earned' => 'Points gagnés', 'redeemed' => 'Points utilisés', 'expired' => 'Points expirés', 'adjusted' => 'Ajustement'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Avantages VIP - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body { background: #f3f3f3; }
        .vip-wrap { max-width: 900px; margin: 40px auto; padding: 0 20px 80px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 20px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }
        .page-title { font-size: 26px; font-weight: 700; color: #0F1111; margin-bottom: 24px; }

        /* Carte statut */
        .tier-card {
            border-radius: 12px; padding: 28px 32px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 28px; flex-wrap: wrap;
        }
        .tier-icon { font-size: 56px; line-height: 1; }
        .tier-info { flex: 1; }
        .tier-name { font-size: 28px; font-weight: 800; margin-bottom: 4px; }
        .tier-desc { font-size: 14px; color: #555; margin-bottom: 14px; }
        .tier-points { font-size: 38px; font-weight: 800; line-height: 1; }
        .tier-points small { font-size: 14px; font-weight: 400; color: #777; }

        /* Barre de progression */
        .progress-section { margin-bottom: 24px; }
        .progress-label { display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 6px; }
        .progress-bar-wrap { background: #E7E7E7; border-radius: 99px; height: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s; }

        /* Grille avantages */
        .benefits-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .benefit-card {
            background: #fff; border: 1px solid #D5D9D9; border-radius: 8px;
            padding: 18px; text-align: center;
        }
        .benefit-icon { font-size: 32px; display: block; margin-bottom: 10px; }
        .benefit-title { font-size: 14px; font-weight: 700; color: #0F1111; margin-bottom: 4px; }
        .benefit-desc { font-size: 12px; color: #666; }

        /* Paliers */
        .tiers-table { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; overflow: hidden; margin-bottom: 28px; }
        .tiers-table table { width: 100%; border-collapse: collapse; }
        .tiers-table th { background: #F0F2F2; font-size: 13px; font-weight: 700; padding: 12px 16px; text-align: left; }
        .tiers-table td { font-size: 13px; padding: 12px 16px; border-top: 1px solid #E7E7E7; }
        .tiers-table tr.active-tier td { background: #fffde7; font-weight: 700; }

        /* Historique */
        .history-box { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; overflow: hidden; }
        .history-header { padding: 14px 20px; font-size: 16px; font-weight: 700; color: #0F1111; border-bottom: 1px solid #E7E7E7; }
        .history-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #F0F2F2; font-size: 13px; }
        .history-row:last-child { border-bottom: none; }
        .pts-positive { color: #10b981; font-weight: 700; }
        .pts-negative { color: #ef4444; font-weight: 700; }
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

<div class="vip-wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Avantages VIP</span>
    </nav>

    <h1 class="page-title">Programme de fidélité AtlanTech VIP</h1>

    <!-- Carte statut actuel -->
    <div class="tier-card" style="background:<?php echo $current_tier['bg']; ?>; border: 2px solid <?php echo $current_tier['color']; ?>;">
        <div class="tier-icon"><?php echo $current_tier['icon']; ?></div>
        <div class="tier-info">
            <div class="tier-name" style="color:<?php echo $current_tier['color']; ?>;">
                Statut <?php echo $current_tier['name']; ?>
            </div>
            <div class="tier-desc">
                <?php echo $orders_count; ?> commande<?php echo $orders_count > 1 ? 's' : ''; ?> &mdash;
                <?php echo number_format($total_spent, 0, ',', ' '); ?> HTG dépensés
            </div>
            <?php if ($next_tier): ?>
                <div class="progress-section">
                    <div class="progress-label">
                        <span>Progrès vers <?php echo $next_tier['icon']; ?> <?php echo $next_tier['name']; ?></span>
                        <span><?php echo number_format($remaining, 0, ',', ' '); ?> HTG restants</span>
                    </div>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-fill"
                             style="width:<?php echo $progress_pct; ?>%; background:<?php echo $current_tier['color']; ?>;"></div>
                    </div>
                </div>
            <?php else: ?>
                <div style="font-size:14px; color:<?php echo $current_tier['color']; ?>; font-weight:700;">
                    🎉 Vous avez atteint le statut maximum !
                </div>
            <?php endif; ?>
        </div>
        <div style="text-align:center;">
            <div class="tier-points" style="color:<?php echo $current_tier['color']; ?>;">
                <?php echo number_format($balance, 0, ',', ' '); ?>
                <small><br>points disponibles</small>
            </div>
        </div>
    </div>

    <!-- Avantages du palier actuel -->
    <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Vos avantages <?php echo $current_tier['name']; ?></h2>
    <div class="benefits-grid">
        <?php if ($current_tier['discount'] > 0): ?>
        <div class="benefit-card">
            <span class="benefit-icon">🏷️</span>
            <div class="benefit-title"><?php echo $current_tier['discount']; ?>% de remise</div>
            <div class="benefit-desc">Sur toutes vos commandes</div>
        </div>
        <?php endif; ?>
        <div class="benefit-card">
            <span class="benefit-icon">⭐</span>
            <div class="benefit-title">×<?php echo $current_tier['points_rate']; ?> de points</div>
            <div class="benefit-desc">Multiplicateur sur chaque achat</div>
        </div>
        <div class="benefit-card">
            <span class="benefit-icon">🚚</span>
            <div class="benefit-title">
                <?php echo in_array($current_tier['name'], ['Or','Platine']) ? 'Livraison prioritaire' : 'Livraison standard'; ?>
            </div>
            <div class="benefit-desc">
                <?php echo in_array($current_tier['name'], ['Or','Platine']) ? 'Traitement en priorité' : 'Délais habituels'; ?>
            </div>
        </div>
        <?php if (in_array($current_tier['name'], ['Argent','Or','Platine'])): ?>
        <div class="benefit-card">
            <span class="benefit-icon">📞</span>
            <div class="benefit-title">Support dédié</div>
            <div class="benefit-desc">File d'attente prioritaire</div>
        </div>
        <?php endif; ?>
        <?php if (in_array($current_tier['name'], ['Or','Platine'])): ?>
        <div class="benefit-card">
            <span class="benefit-icon">🎁</span>
            <div class="benefit-title">Cadeaux anniversaire</div>
            <div class="benefit-desc">Surprise chaque année</div>
        </div>
        <?php endif; ?>
        <?php if ($current_tier['name'] === 'Platine'): ?>
        <div class="benefit-card">
            <span class="benefit-icon">🔑</span>
            <div class="benefit-title">Accès avant-première</div>
            <div class="benefit-desc">Nouvelles collections en exclusivité</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tableau des paliers -->
    <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Tous les paliers</h2>
    <div class="tiers-table">
        <table>
            <thead>
                <tr>
                    <th>Palier</th>
                    <th>Dépenses requises</th>
                    <th>Remise</th>
                    <th>Multiplicateur de points</th>
                    <th>Avantages</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiers as $tier): ?>
                    <tr class="<?php echo $tier['name'] === $current_tier['name'] ? 'active-tier' : ''; ?>">
                        <td>
                            <?php echo $tier['icon']; ?> <strong style="color:<?php echo $tier['color']; ?>;">
                                <?php echo $tier['name']; ?>
                            </strong>
                            <?php echo $tier['name'] === $current_tier['name'] ? ' ← vous êtes ici' : ''; ?>
                        </td>
                        <td>
                            <?php if ($tier['max'] === PHP_INT_MAX): ?>
                                <?php echo number_format($tier['min'], 0, ',', ' '); ?> HTG et +
                            <?php else: ?>
                                <?php echo number_format($tier['min'], 0, ',', ' '); ?> – <?php echo number_format($tier['max'], 0, ',', ' '); ?> HTG
                            <?php endif; ?>
                        </td>
                        <td><?php echo $tier['discount'] > 0 ? $tier['discount'].'%' : '—'; ?></td>
                        <td>×<?php echo $tier['points_rate']; ?></td>
                        <td style="font-size:12px; color:#565959;">
                            <?php
                            $avantages = [];
                            if ($tier['discount'] > 0) $avantages[] = 'Remise '.$tier['discount'].'%';
                            if (in_array($tier['name'], ['Argent','Or','Platine'])) $avantages[] = 'Support dédié';
                            if (in_array($tier['name'], ['Or','Platine'])) $avantages[] = 'Livraison prioritaire';
                            if (in_array($tier['name'], ['Or','Platine'])) $avantages[] = 'Cadeau anniversaire';
                            if ($tier['name'] === 'Platine') $avantages[] = 'Avant-première';
                            echo $avantages ? implode(', ', $avantages) : 'Points de base';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Historique des points -->
    <div class="history-box">
        <div class="history-header">
            Historique de vos points
            <span style="font-size:13px; font-weight:400; color:#666; margin-left:8px;">
                Total gagné : <strong><?php echo number_format($total_earned, 0, ',', ' '); ?></strong> pts
            </span>
        </div>
        <?php if (empty($history)): ?>
            <div style="padding:28px; text-align:center; color:#666; font-size:14px;">
                Aucun mouvement de points pour l'instant.<br>
                Passez votre première commande pour commencer à accumuler des points !
            </div>
        <?php else: ?>
            <?php foreach ($history as $h): ?>
                <div class="history-row">
                    <div>
                        <span style="margin-right:8px;"><?php echo $type_icons[$h['transaction_type']] ?? '•'; ?></span>
                        <strong><?php echo $type_labels[$h['transaction_type']] ?? $h['transaction_type']; ?></strong>
                        <?php if (!empty($h['description'])): ?>
                            <span style="color:#666;"> — <?php echo htmlspecialchars($h['description']); ?></span>
                        <?php endif; ?>
                        <div style="font-size:12px; color:#888; margin-top:2px;">
                            <?php echo date('d/m/Y', strtotime($h['created_at'])); ?>
                        </div>
                    </div>
                    <div class="<?php echo in_array($h['transaction_type'], ['earned','adjusted']) ? 'pts-positive' : 'pts-negative'; ?>">
                        <?php echo in_array($h['transaction_type'], ['earned','adjusted']) ? '+' : '-'; ?>
                        <?php echo number_format(abs($h['points']), 0, ',', ' '); ?> pts
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="margin-top:24px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
