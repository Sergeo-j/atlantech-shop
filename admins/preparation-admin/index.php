<?php
/**
 * AtlanTech — Préparation Admin : Dashboard
 *
 * Liste les commandes au statut `paid` qui attendent d'être préparées.
 * Le préparateur clique sur une commande, vérifie les articles, puis
 * clique "Prendre en préparation" (transition paid → processing).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Préparateur';

// ── Charger les commandes au statut paid ────────────────────────────
$paid_orders = [];
$processing_orders = [];
try {
    $st = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.customer_phone,
               o.total_amount, o.payment_method, o.payment_processor,
               o.shipping_address, o.created_at, o.status,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS nb_items
        FROM orders o
        WHERE o.status = 'paid'
        ORDER BY o.created_at ASC
    ");
    $st->execute();
    $paid_orders = $st->fetchAll();

    $st2 = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.total_amount,
               o.payment_method, o.created_at, o.status
        FROM orders o
        WHERE o.status = 'processing'
        ORDER BY o.updated_at DESC
        LIMIT 15
    ");
    $st2->execute();
    $processing_orders = $st2->fetchAll();
} catch (\Throwable $e) {
    error_log('preparation-admin index: ' . $e->getMessage());
}

$nb_paid = count($paid_orders);
$nb_proc = count($processing_orders);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préparation des commandes — AtlanTech</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f8fafc; color: #1f2937; }

        header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: #fff; padding: 24px 40px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        header h1 { font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        header .user-info { display: flex; align-items: center; gap: 20px; margin-top: 8px; font-size: .9rem; opacity: .92; }
        header a.logout { color: #fff; text-decoration: underline; }

        main { max-width: 1200px; margin: 30px auto; padding: 0 24px; }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .stat { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
        .stat .lbl { font-size: .78rem; color: #6b7280; text-transform: uppercase; font-weight: 600; letter-spacing: .5px; }
        .stat .val { font-size: 2rem; font-weight: 700; color: #06b6d4; margin-top: 6px; }
        .stat.processing .val { color: #8b5cf6; }

        .section-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; margin: 20px 0 14px; display: flex; align-items: center; gap: 8px; }

        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        th, td { padding: 12px 16px; text-align: left; font-size: .88rem; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; font-weight: 700; color: #475569; font-size: .78rem; text-transform: uppercase; letter-spacing: .4px; }
        tr:hover td { background: #fafbfd; }
        tr:last-child td { border-bottom: none; }

        .order-num { font-weight: 700; color: #06b6d4; }
        .badge { padding: 3px 10px; border-radius: 12px; font-size: .73rem; font-weight: 700; display: inline-block; }
        .badge.paid { background: #d1fae5; color: #065f46; }
        .badge.processing { background: #ede9fe; color: #5b21b6; }
        .badge.cash { background: #fff7ed; color: #9a3412; }
        .badge.elec { background: #e0f2fe; color: #075985; }

        .btn-prepare { padding: 8px 14px; background: #06b6d4; color: #fff; border: none; border-radius: 7px; font-size: .82rem; font-weight: 700; text-decoration: none; cursor: pointer; transition: background .15s; }
        .btn-prepare:hover { background: #0891b2; }

        .empty { text-align: center; padding: 60px 20px; color: #94a3b8; font-style: italic; background: #fff; border-radius: 12px; }
        .empty-icon { font-size: 3rem; margin-bottom: 12px; opacity: .5; }

        .small { font-size: .78rem; color: #6b7280; }

        @media (max-width: 720px) {
            header { padding: 18px 16px; }
            main { padding: 0 12px; }
            th:nth-child(4), td:nth-child(4),
            th:nth-child(5), td:nth-child(5) { display: none; }
        }
    </style>
</head>
<body>

<header>
    <h1>📦 Préparation des commandes</h1>
    <div class="user-info">
        <span>👋 Bonjour, <strong><?= htmlspecialchars($admin_name) ?></strong></span>
        <span>•</span>
        <a href="logout.php" class="logout">Se déconnecter</a>
    </div>
</header>

<main>
    <!-- Statistiques -->
    <div class="stat-grid">
        <div class="stat">
            <div class="lbl">À préparer</div>
            <div class="val"><?= $nb_paid ?></div>
        </div>
        <div class="stat processing">
            <div class="lbl">En cours de préparation</div>
            <div class="val"><?= $nb_proc ?></div>
        </div>
    </div>

    <!-- Commandes en attente de préparation -->
    <div class="section-title">⏳ Commandes à préparer (statut "Payée")</div>
    <?php if (empty($paid_orders)): ?>
        <div class="empty">
            <div class="empty-icon">🎉</div>
            Aucune commande en attente de préparation. Tout est sous contrôle !
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>N° commande</th>
                    <th>Client</th>
                    <th>Articles</th>
                    <th>Paiement</th>
                    <th>Montant</th>
                    <th>Reçue le</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paid_orders as $o):
                $pm = $o['payment_method'] ?? '';
                $is_cash = ($pm === 'Cash');
            ?>
                <tr>
                    <td>
                        <a href="preparation-details.php?id=<?= (int)$o['id'] ?>" class="order-num">
                            #<?= htmlspecialchars($o['order_number']) ?>
                        </a>
                    </td>
                    <td>
                        <?= htmlspecialchars($o['customer_name']) ?><br>
                        <span class="small"><?= htmlspecialchars($o['customer_phone'] ?? '') ?></span>
                    </td>
                    <td><?= (int)$o['nb_items'] ?> article<?= $o['nb_items'] > 1 ? 's' : '' ?></td>
                    <td>
                        <span class="badge <?= $is_cash ? 'cash' : 'elec' ?>">
                            <?= $is_cash ? '💵 Cash' : '💳 ' . htmlspecialchars($o['payment_processor'] ?: $pm) ?>
                        </span>
                    </td>
                    <td><strong><?= number_format((float)$o['total_amount'], 2) ?> HTG</strong></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                    <td>
                        <a href="preparation-details.php?id=<?= (int)$o['id'] ?>" class="btn-prepare">
                            📦 Préparer
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($processing_orders)): ?>
    <div class="section-title" style="margin-top:30px;">🔧 En cours de préparation — finalisez puis cliquez "Colis prêt"</div>
    <table>
        <thead>
            <tr>
                <th>N° commande</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Démarrée le</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($processing_orders as $o): ?>
            <tr>
                <td><span class="order-num">#<?= htmlspecialchars($o['order_number']) ?></span></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><strong><?= number_format((float)$o['total_amount'], 2) ?> HTG</strong></td>
                <td class="small"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                <td>
                    <a href="preparation-details.php?id=<?= (int)$o['id'] ?>" class="btn-prepare" style="background:#3b82f6;">
                        🎁 Finaliser
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</main>

</body>
</html>
