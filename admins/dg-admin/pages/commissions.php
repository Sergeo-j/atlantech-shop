<?php
/**
 * Page : Commissions des admins (vue DG)
 *
 * Flux métier :
 *   1. Une commission est calculée pour chaque (admin, commande) où l'admin a
 *      RÉELLEMENT effectué une action sur la commande (pas de "pool partagé").
 *   2. Statuts : pending → approved → paid (ou cancelled).
 *   3. Calcul : commission_amount = base × (commission_rate / 100), où base est :
 *        - 'order_total' → orders.total_amount
 *        - 'subtotal'    → orders.subtotal
 *        - 'shipping'    → orders.shipping_cost
 *
 * Identification de l'admin rémunéré (mapping rôle → statut déclencheur) :
 *   - delivery     → admin qui a passé la commande à 'delivered'
 *   - order        → admin qui a passé la commande à 'paid'
 *   - preparation  → admin qui a passé la commande à 'ready_for_delivery'
 *
 *   ➜ Lecture dans `order_status_history` : on cherche la ligne où
 *     `new_status` = statut déclencheur ET `changed_by_id` non null.
 *
 *   ➜ Si un rôle n'a pas de statut déclencheur défini (client, stock, etc.),
 *     aucune commission auto n'est créée pour ce rôle — c'est délibéré, ces
 *     rôles n'ont pas d'action mesurable sur une commande spécifique.
 *
 * Le DG peut :
 *   - Générer les commissions pour les nouvelles commandes livrées
 *   - Approuver (pending → approved)
 *   - Marquer payée (approved/pending → paid)
 *   - Annuler (→ cancelled)
 *   - Filtrer par admin, statut, période
 *   - Paiement en masse (bulk)
 */

// Mapping rôle → statut déclencheur (modifiable si workflow évolue)
const ROLE_TRIGGER_STATUS = [
    'delivery'    => 'delivered',
    'order'       => 'paid',
    'preparation' => 'ready_for_delivery',
];
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Commissions';

$flash = ['type' => null, 'message' => null];
$dg_id = (int) $_SESSION['dg_id'];

// ─── Génération des commissions manquantes ──────────────────────────────
/**
 * Crédite la commission à l'admin qui a RÉELLEMENT effectué l'action métier
 * sur la commande (lecture dans order_status_history). Plus de "pool partagé".
 */
function generate_missing_commissions(PDO $pdo, int $dg_id): array {
    // 1) Charger les règles actives, indexées par role_name
    $rules_by_role = [];
    $st = $pdo->query("
        SELECT cr.admin_role_id, cr.commission_rate, cr.applies_to, r.role_name
        FROM admin_commission_rules cr
        JOIN admin_roles r ON cr.admin_role_id = r.id
        WHERE cr.is_active = 1 AND r.is_active = 1
    ");
    foreach ($st->fetchAll() as $r) {
        $rules_by_role[$r['role_name']] = $r;
    }

    if (empty($rules_by_role)) {
        return ['generated' => 0, 'skipped' => 0, 'no_actor' => 0, 'error' => 'Aucune règle active.'];
    }

    // 2) Charger les commandes livrées (on attend 'delivered' pour figer le calcul)
    $orders = $pdo->query("
        SELECT id, order_number, total_amount, subtotal, shipping_cost
        FROM orders
        WHERE status = 'delivered'
    ")->fetchAll();

    if (empty($orders)) {
        return ['generated' => 0, 'skipped' => 0, 'no_actor' => 0, 'error' => null];
    }

    // 3) Précharger les transitions de statut pour toutes ces commandes
    //    Structure : $transitions[$order_id][$new_status] = ['admin_id' => ..., 'created_at' => ...]
    $transitions = [];
    if (!empty($orders)) {
        $order_ids = array_map(fn($o) => (int)$o['id'], $orders);
        $place     = implode(',', array_fill(0, count($order_ids), '?'));
        $sthist = $pdo->prepare("
            SELECT order_id, new_status, changed_by_id, created_at
            FROM order_status_history
            WHERE order_id IN ($place)
              AND changed_by_type = 'admin'
              AND changed_by_id IS NOT NULL
              AND new_status IN ('paid','ready_for_delivery','delivered')
            ORDER BY created_at ASC
        ");
        $sthist->execute($order_ids);
        foreach ($sthist->fetchAll() as $row) {
            $oid = (int)$row['order_id'];
            $ns  = $row['new_status'];
            // Garder la PREMIÈRE transition vers chaque statut (la plus ancienne)
            if (!isset($transitions[$oid][$ns])) {
                $transitions[$oid][$ns] = [
                    'admin_id'   => (int)$row['changed_by_id'],
                    'created_at' => $row['created_at'],
                ];
            }
        }
    }

    // 4) Récupérer le rôle de chaque admin impliqué (pour vérifier cohérence)
    //    Ex : un admin a-t-il bien le rôle 'delivery' au moment où on lui attribue
    //    une commission delivery ? S'il a changé de rôle entre-temps, c'est OK,
    //    on lui crédite quand même car il a fait le travail à ce moment-là.
    $admin_role_map = [];
    $stmtA = $pdo->query("SELECT id, admin_role_id FROM admins");
    foreach ($stmtA->fetchAll() as $a) {
        $admin_role_map[(int)$a['id']] = (int)$a['admin_role_id'];
    }

    // 5) Insertion idempotente (uniq_admin_order empêche doublons)
    $generated = 0;
    $skipped   = 0;
    $no_actor  = 0;
    $insert = $pdo->prepare("
        INSERT INTO admin_commissions
            (admin_id, admin_role_id, order_id, order_number, commission_rate,
             base_amount, commission_amount, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    foreach ($orders as $o) {
        $oid = (int)$o['id'];

        foreach (ROLE_TRIGGER_STATUS as $role_name => $trigger_status) {
            // Y a-t-il une règle pour ce rôle ?
            if (!isset($rules_by_role[$role_name])) continue;
            $rule = $rules_by_role[$role_name];

            // Qui a effectué la transition ?
            if (empty($transitions[$oid][$trigger_status])) {
                $no_actor++;
                continue;  // pas d'historique → on ne crédite personne
            }
            $admin_id = $transitions[$oid][$trigger_status]['admin_id'];

            // Calculer le montant
            $base = match ($rule['applies_to']) {
                'order_total' => (float) $o['total_amount'],
                'subtotal'    => (float) ($o['subtotal'] ?? 0),
                'shipping'    => (float) ($o['shipping_cost'] ?? 0),
                default       => 0.0,
            };
            if ($base <= 0) continue;

            $rate   = (float) $rule['commission_rate'];
            $amount = round($base * $rate / 100, 2);
            if ($amount <= 0) continue;

            try {
                $insert->execute([
                    $admin_id,
                    (int)$rule['admin_role_id'],
                    $oid,
                    $o['order_number'],
                    $rate,
                    $base,
                    $amount,
                ]);
                $generated++;
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) {
                    $skipped++; // déjà existe (uniq_admin_order)
                } else {
                    error_log('generate_commissions: ' . $e->getMessage());
                }
            }
        }
    }

    log_dg_action($dg_id, 'commissions_generated',
        "Génération : $generated nouvelles, $skipped déjà présentes, $no_actor sans acteur identifié");
    return ['generated' => $generated, 'skipped' => $skipped, 'no_actor' => $no_actor, 'error' => null];
}

// ─── Traitement POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'generate') {
            $res = generate_missing_commissions($pdo, $dg_id);
            if ($res['error']) {
                $flash = ['type' => 'danger', 'message' => $res['error']];
            } else {
                $msg = "Génération terminée : {$res['generated']} nouvelle(s) commission(s) attribuée(s) aux admins ayant réellement traité les commandes.";
                if ($res['skipped'] > 0)  $msg .= " · {$res['skipped']} déjà existante(s).";
                if ($res['no_actor'] > 0) $msg .= " · {$res['no_actor']} étape(s) sans acteur tracé dans l'historique (commande créée avant le tracking, ou statut sauté).";
                $type = ($res['generated'] > 0) ? 'success' : 'warning';
                $flash = ['type' => $type, 'message' => $msg];
            }
        }
        elseif (in_array($action, ['approve', 'pay', 'cancel'], true) && !empty($_POST['id'])) {
            $id      = (int) $_POST['id'];
            $newStat = match ($action) {
                'approve' => 'approved',
                'pay'     => 'paid',
                'cancel'  => 'cancelled',
            };
            if ($newStat === 'paid') {
                $pdo->prepare("UPDATE admin_commissions SET status = ?, paid_at = NOW(), paid_by_admin_id = ? WHERE id = ?")
                    ->execute([$newStat, $dg_id, $id]);
            } else {
                $pdo->prepare("UPDATE admin_commissions SET status = ? WHERE id = ?")
                    ->execute([$newStat, $id]);
            }
            $flash = ['type' => 'success', 'message' => "Commission #$id → " . $newStat];
            log_dg_action($dg_id, 'commission_' . $action, "Commission #$id passée à $newStat");
        }
        elseif ($action === 'pay_bulk' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
            $ids = array_filter($ids, fn($v) => $v > 0);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$dg_id], $ids);
                $pdo->prepare("UPDATE admin_commissions SET status = 'paid', paid_at = NOW(), paid_by_admin_id = ? WHERE id IN ($placeholders)")
                    ->execute($params);
                $flash = ['type' => 'success', 'message' => count($ids) . ' commission(s) marquée(s) payée(s).'];
                log_dg_action($dg_id, 'commission_pay_bulk', "Bulk paid : " . count($ids) . " commissions");
            }
        }
    } catch (PDOException $e) {
        error_log('DG commissions POST: ' . $e->getMessage());
        $flash = ['type' => 'danger', 'message' => 'Erreur technique. Réessayez.'];
    }
}

// ─── Filtres GET ─────────────────────────────────────────────────────────
$filter_admin  = (int) ($_GET['admin'] ?? 0);
$filter_status = trim($_GET['status'] ?? '');
$filter_from   = trim($_GET['from']   ?? '');
$filter_to     = trim($_GET['to']     ?? '');

$where  = ['1=1'];
$params = [];
if ($filter_admin > 0)       { $where[] = 'c.admin_id = ?'; $params[] = $filter_admin; }
if ($filter_status !== '')   { $where[] = 'c.status = ?'; $params[] = $filter_status; }
if ($filter_from !== '')     { $where[] = 'DATE(c.created_at) >= ?'; $params[] = $filter_from; }
if ($filter_to   !== '')     { $where[] = 'DATE(c.created_at) <= ?'; $params[] = $filter_to;   }
$where_sql = implode(' AND ', $where);

// ─── Données ─────────────────────────────────────────────────────────────
$commissions = [];
$totals = ['pending' => 0, 'approved' => 0, 'paid' => 0, 'cancelled' => 0];
try {
    $st = $pdo->prepare("
        SELECT c.*, a.full_name, a.email, r.role_name, o.order_number AS o_num
        FROM admin_commissions c
        LEFT JOIN admins      a ON c.admin_id      = a.id
        LEFT JOIN admin_roles r ON c.admin_role_id = r.id
        LEFT JOIN orders      o ON c.order_id      = o.id
        WHERE $where_sql
        ORDER BY c.status = 'pending' DESC, c.status = 'approved' DESC, c.created_at DESC
        LIMIT 200
    ");
    $st->execute($params);
    $commissions = $st->fetchAll();

    // Totaux par statut (sur les filtres actuels)
    $stt = $pdo->prepare("
        SELECT status, COALESCE(SUM(commission_amount), 0) AS total
        FROM admin_commissions c
        WHERE $where_sql
        GROUP BY status
    ");
    $stt->execute($params);
    foreach ($stt->fetchAll() as $t) {
        $totals[$t['status']] = (float) $t['total'];
    }
} catch (PDOException $e) {
    error_log('DG commissions query: ' . $e->getMessage());
}

$admins_list = [];
try {
    $admins_list = $pdo->query("
        SELECT DISTINCT a.id, a.full_name
        FROM admin_commissions c
        JOIN admins a ON c.admin_id = a.id
        ORDER BY a.full_name
    ")->fetchAll();
} catch (PDOException $e) {}

$ROLE_LABELS = [
    'order'       => '🛒 Commandes',
    'preparation' => '📦 Préparation',
    'delivery'    => '🚚 Livraison',
    'product'     => '🏷️ Produits',
    'stock'       => '📊 Stock',
    'client'      => '👥 Clients',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-money-bill-wave"></i> Commissions</h2>
    <form method="POST" style="display:inline" onsubmit="return confirm('Générer les commissions pour toutes les commandes livrées ?\n\nLes lignes déjà existantes ne sont pas dupliquées.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate">
        <button class="btn btn-accent" type="submit">
            <i class="fas fa-sync"></i> Générer les commissions manquantes
        </button>
    </form>
</div>

<?php if ($flash['type']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="alert alert-info" style="font-size:0.85rem">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>Règle de calcul :</strong> la commission est attribuée à <strong>l'admin qui a réellement effectué l'action</strong> sur la commande (lecture de l'historique des statuts).<br>
        • <strong>🚚 Livraison</strong> → le livreur qui a marqué la commande comme « delivered »<br>
        • <strong>🛒 Commandes</strong> → l'admin qui a validé le paiement (« paid »)<br>
        • <strong>📦 Préparation</strong> → l'admin qui a passé la commande à « ready_for_delivery »
    </div>
</div>

<!-- KPI totaux -->
<div class="kpi-grid">
    <div class="kpi warning">
        <div class="label">En attente</div>
        <div class="value"><?= number_format($totals['pending'], 0, ',', ' ') ?></div>
        <div class="sub">HTG · à valider</div>
    </div>
    <div class="kpi info">
        <div class="label">Approuvées</div>
        <div class="value"><?= number_format($totals['approved'], 0, ',', ' ') ?></div>
        <div class="sub">HTG · à payer</div>
    </div>
    <div class="kpi success">
        <div class="label">Payées</div>
        <div class="value"><?= number_format($totals['paid'], 0, ',', ' ') ?></div>
        <div class="sub">HTG · distribués</div>
    </div>
    <div class="kpi danger">
        <div class="label">Annulées</div>
        <div class="value"><?= number_format($totals['cancelled'], 0, ',', ' ') ?></div>
        <div class="sub">HTG · perdus</div>
    </div>
</div>

<!-- Filtres -->
<div class="card">
    <form method="GET" class="form-row" style="grid-template-columns: 1fr 1fr 1fr 1fr auto; align-items: end">
        <div class="form-group" style="margin-bottom:0">
            <label>Admin</label>
            <select name="admin" class="form-select">
                <option value="0">Tous</option>
                <?php foreach ($admins_list as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= $filter_admin === (int)$a['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous</option>
                <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>En attente</option>
                <option value="approved"  <?= $filter_status === 'approved'  ? 'selected' : '' ?>>Approuvées</option>
                <option value="paid"      <?= $filter_status === 'paid'      ? 'selected' : '' ?>>Payées</option>
                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Annulées</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Du</label>
            <input type="date" name="from" class="form-input" value="<?= htmlspecialchars($filter_from) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Au</label>
            <input type="date" name="to" class="form-input" value="<?= htmlspecialchars($filter_to) ?>">
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrer</button>
    </form>
</div>

<!-- Tableau commissions -->
<form method="POST" id="bulk-form">
<?= csrf_field() ?>
<input type="hidden" name="action" value="pay_bulk">

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> <?= count($commissions) ?> commission(s) (top 200)</h3>
        <button class="btn btn-success btn-sm" type="submit" onclick="return confirm('Marquer toutes les commissions sélectionnées comme payées ?');">
            <i class="fas fa-check-double"></i> Payer la sélection
        </button>
    </div>

    <?php if (empty($commissions)): ?>
        <p class="text-muted text-center" style="padding:30px">
            Aucune commission pour ces filtres.<br>
            <small>Cliquez « Générer les commissions manquantes » si vous avez des commandes livrées.</small>
        </p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th style="width:32px"><input type="checkbox" id="select-all" title="Tout sélectionner"></th>
            <th>Date</th>
            <th>Admin</th>
            <th>Rôle</th>
            <th>Commande</th>
            <th>Base</th>
            <th>Taux</th>
            <th>Montant</th>
            <th>Statut</th>
            <th class="actions" style="width:280px">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($commissions as $c):
            $statusBadge = [
                'pending'   => 'badge-warning',
                'approved'  => 'badge-info',
                'paid'      => 'badge-success',
                'cancelled' => 'badge-danger',
            ][$c['status']] ?? 'badge-muted';
            $statusLbl = [
                'pending' => 'En attente', 'approved' => 'Approuvée',
                'paid' => 'Payée',         'cancelled' => 'Annulée',
            ][$c['status']] ?? $c['status'];
            $roleLbl = $ROLE_LABELS[$c['role_name']] ?? ucfirst($c['role_name'] ?? '—');
            $isPayable = in_array($c['status'], ['pending', 'approved'], true);
        ?>
        <tr>
            <td>
                <?php if ($isPayable): ?>
                    <input type="checkbox" name="ids[]" value="<?= (int)$c['id'] ?>" class="row-check">
                <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:0.83rem"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
            <td>
                <strong><?= htmlspecialchars($c['full_name'] ?? '—') ?></strong>
                <div class="text-muted" style="font-size:0.75rem"><?= htmlspecialchars($c['email'] ?? '') ?></div>
            </td>
            <td><span class="badge badge-info"><?= htmlspecialchars($roleLbl) ?></span></td>
            <td><strong><?= htmlspecialchars($c['order_number'] ?? ('#'.$c['order_id'])) ?></strong></td>
            <td class="text-muted"><?= number_format((float)$c['base_amount'], 0, ',', ' ') ?> HTG</td>
            <td class="text-muted"><?= htmlspecialchars($c['commission_rate']) ?>%</td>
            <td><strong style="color:#fde68a"><?= number_format((float)$c['commission_amount'], 2, ',', ' ') ?> HTG</strong></td>
            <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($statusLbl) ?></span></td>
            <td class="actions">
                <?php if ($c['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-ghost" type="button"
                        onclick="quickAction(<?= (int)$c['id'] ?>, 'approve')"
                        title="Approuver"><i class="fas fa-check"></i></button>
                <?php endif; ?>
                <?php if ($isPayable): ?>
                    <button class="btn btn-sm btn-success" type="button"
                        onclick="quickAction(<?= (int)$c['id'] ?>, 'pay')"
                        title="Marquer payée"><i class="fas fa-money-bill"></i> Payer</button>
                <?php endif; ?>
                <?php if ($c['status'] !== 'cancelled' && $c['status'] !== 'paid'): ?>
                    <button class="btn btn-sm btn-danger" type="button"
                        onclick="if(confirm('Annuler cette commission ?')) quickAction(<?= (int)$c['id'] ?>, 'cancel')"
                        title="Annuler"><i class="fas fa-times"></i></button>
                <?php endif; ?>
                <?php if ($c['status'] === 'paid' && $c['paid_at']): ?>
                    <span class="text-muted" style="font-size:0.78rem">
                        Payée le <?= htmlspecialchars(date('d/m/Y', strtotime($c['paid_at']))) ?>
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</form>

<!-- Formulaire caché pour les actions unitaires (quickAction) -->
<form method="POST" id="quick-action-form" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="qa-action">
    <input type="hidden" name="id"     id="qa-id">
</form>

<script>
function quickAction(id, action) {
    document.getElementById('qa-action').value = action;
    document.getElementById('qa-id').value     = id;
    document.getElementById('quick-action-form').submit();
}

document.getElementById('select-all')?.addEventListener('change', function () {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
