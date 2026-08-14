<?php
/**
 * Page : Taux de commission par rôle (vue DG)
 *
 * Édition des règles dans la table `admin_commission_rules`.
 * Une règle par rôle (UNIQUE KEY uniq_role). Le DG peut :
 *   - Modifier le taux % et le mode de calcul (sur total / sous-total / livraison)
 *   - Activer / désactiver une règle
 *   - Ajouter une règle pour un rôle qui n'en a pas encore
 *
 * Pas de DELETE : on désactive plutôt (la règle peut être réactivée).
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Taux de commission';

$flash = ['type' => null, 'message' => null];
$dg_id = (int) $_SESSION['dg_id'];

// ─── Traitement POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update') {
            $id      = (int)   ($_POST['id']               ?? 0);
            $rate    = (float) ($_POST['commission_rate']  ?? 0);
            $applies = (string)($_POST['applies_to']       ?? 'order_total');

            if (!in_array($applies, ['order_total', 'subtotal', 'shipping'], true)) {
                $flash = ['type' => 'danger', 'message' => 'Base de calcul invalide.'];
            } elseif ($rate < 0 || $rate > 100) {
                $flash = ['type' => 'danger', 'message' => 'Le taux doit être entre 0 et 100 %.'];
            } else {
                $pdo->prepare("UPDATE admin_commission_rules SET commission_rate = ?, applies_to = ?, updated_by_admin_id = ? WHERE id = ?")
                    ->execute([$rate, $applies, $dg_id, $id]);
                $flash = ['type' => 'success', 'message' => "Règle mise à jour : {$rate}% sur {$applies}."];
                log_dg_action($dg_id, 'commission_rule_update', "Règle #$id : {$rate}% sur {$applies}");
            }
        }
        elseif ($action === 'toggle' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $pdo->prepare("UPDATE admin_commission_rules SET is_active = 1 - is_active, updated_by_admin_id = ? WHERE id = ?")
                ->execute([$dg_id, $id]);
            $flash = ['type' => 'success', 'message' => "Statut basculé."];
            log_dg_action($dg_id, 'commission_rule_toggle', "Règle #$id état basculé");
        }
        elseif ($action === 'add') {
            $role_id = (int)   ($_POST['admin_role_id']   ?? 0);
            $rate    = (float) ($_POST['commission_rate'] ?? 0);
            $applies = (string)($_POST['applies_to']      ?? 'order_total');

            if ($role_id <= 0) {
                $flash = ['type' => 'danger', 'message' => 'Veuillez choisir un rôle.'];
            } elseif (!in_array($applies, ['order_total', 'subtotal', 'shipping'], true)) {
                $flash = ['type' => 'danger', 'message' => 'Base de calcul invalide.'];
            } elseif ($rate < 0 || $rate > 100) {
                $flash = ['type' => 'danger', 'message' => 'Le taux doit être entre 0 et 100 %.'];
            } else {
                // Empêcher l'ajout pour le rôle DG lui-même
                if ($role_id === DG_ROLE_ID) {
                    $flash = ['type' => 'danger', 'message' => 'Le rôle Directeur Général ne reçoit pas de commission sur les commandes.'];
                } else {
                    $pdo->prepare("INSERT INTO admin_commission_rules (admin_role_id, commission_rate, applies_to, is_active, updated_by_admin_id) VALUES (?, ?, ?, 1, ?)")
                        ->execute([$role_id, $rate, $applies, $dg_id]);
                    $flash = ['type' => 'success', 'message' => "Règle ajoutée."];
                    log_dg_action($dg_id, 'commission_rule_add', "Rôle #$role_id : {$rate}% sur {$applies}");
                }
            }
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            $flash = ['type' => 'danger', 'message' => 'Une règle existe déjà pour ce rôle (modifiez-la plutôt).'];
        } else {
            error_log('DG commission-rules POST: ' . $e->getMessage());
            $flash = ['type' => 'danger', 'message' => 'Erreur technique. Réessayez.'];
        }
    }
}

// ─── Données ─────────────────────────────────────────────────────────────
$rules = [];
try {
    $rules = $pdo->query("
        SELECT cr.*, r.role_name, r.role_description
        FROM admin_commission_rules cr
        LEFT JOIN admin_roles r ON cr.admin_role_id = r.id
        ORDER BY cr.is_active DESC, r.role_name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('DG commission-rules query: ' . $e->getMessage());
}

// Rôles qui n'ont PAS encore de règle (pour le formulaire d'ajout)
$role_ids_with_rule = array_map(fn($r) => (int)$r['admin_role_id'], $rules);
$available_roles    = [];
try {
    $st = $pdo->prepare("SELECT id, role_name, role_description FROM admin_roles WHERE is_active = 1 AND id != ? ORDER BY role_name");
    $st->execute([DG_ROLE_ID]);
    foreach ($st->fetchAll() as $r) {
        if (!in_array((int)$r['id'], $role_ids_with_rule, true)) {
            $available_roles[] = $r;
        }
    }
} catch (PDOException $e) { /* OK */ }

// Libellés humanisés
$ROLE_LABELS = [
    'order'       => '🛒 Commandes',
    'preparation' => '📦 Préparation',
    'delivery'    => '🚚 Livraison',
    'product'     => '🏷️ Produits',
    'stock'       => '📊 Stock',
    'client'      => '👥 Clients',
    'marketing'   => '📢 Marketing',
    'support'     => '🎧 Support',
];
$APPLIES_LABELS = [
    'order_total' => 'Total commande',
    'subtotal'    => 'Sous-total (hors livraison)',
    'shipping'    => 'Frais de livraison',
];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-percentage"></i> Taux de commission</h2>
    <span class="text-muted"><?= count($rules) ?> règle(s)</span>
</div>

<?php if ($flash['type']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    <div>
        Les taux configurés ici déterminent les <strong>commissions futures</strong>.
        Les commissions déjà calculées dans <a href="commissions.php">la page Commissions</a>
        gardent le taux qui était en vigueur au moment du calcul (taux gelé).
    </div>
</div>

<!-- Règles existantes -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Règles existantes</h3>
    </div>

    <?php if (empty($rules)): ?>
        <p class="text-muted text-center" style="padding:30px">Aucune règle configurée pour le moment.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th>Rôle</th>
            <th style="width:160px">Taux (%)</th>
            <th style="width:230px">Calculé sur</th>
            <th>État</th>
            <th class="actions" style="width:260px">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rules as $r):
            $lbl = $ROLE_LABELS[$r['role_name']] ?? ucfirst($r['role_name'] ?? '—');
        ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($lbl) ?></strong>
                <div class="text-muted" style="font-size:0.78rem"><?= htmlspecialchars($r['role_description'] ?? '') ?></div>
            </td>
            <td colspan="2">
                <!-- Édition inline -->
                <form method="POST" style="display:flex; gap:6px; align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id"     value="<?= (int) $r['id'] ?>">
                    <input type="number" name="commission_rate" class="form-input"
                           style="width:90px; padding:5px 8px" step="0.1" min="0" max="100"
                           value="<?= htmlspecialchars($r['commission_rate']) ?>">
                    <span>%</span>
                    <select name="applies_to" class="form-select" style="width:auto; padding:5px 8px">
                        <?php foreach ($APPLIES_LABELS as $val => $label_app): ?>
                        <option value="<?= $val ?>" <?= $r['applies_to'] === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label_app) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-save"></i> Sauver</button>
                </form>
            </td>
            <td>
                <?php if ($r['is_active']): ?>
                    <span class="badge badge-success">Actif</span>
                <?php else: ?>
                    <span class="badge badge-muted">Désactivé</span>
                <?php endif; ?>
            </td>
            <td class="actions">
                <form method="POST" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id"     value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit">
                        <i class="fas fa-toggle-<?= $r['is_active'] ? 'on' : 'off' ?>"></i>
                        <?= $r['is_active'] ? 'Désactiver' : 'Activer' ?>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Ajouter une règle (si rôles disponibles) -->
<?php if (!empty($available_roles)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle"></i> Ajouter une règle pour un rôle</h3>
    </div>
    <form method="POST" class="form-row" style="grid-template-columns: 2fr 1fr 2fr auto; align-items: end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-group" style="margin-bottom:0">
            <label>Rôle</label>
            <select name="admin_role_id" class="form-select" required>
                <option value="">-- Choisir --</option>
                <?php foreach ($available_roles as $r):
                    $lbl = $ROLE_LABELS[$r['role_name']] ?? ucfirst($r['role_name']);
                ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Taux (%)</label>
            <input type="number" name="commission_rate" class="form-input" step="0.1" min="0" max="100" required placeholder="2.0">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Calculé sur</label>
            <select name="applies_to" class="form-select" required>
                <?php foreach ($APPLIES_LABELS as $val => $label_app): ?>
                <option value="<?= $val ?>"><?= htmlspecialchars($label_app) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-accent" type="submit"><i class="fas fa-plus"></i> Ajouter</button>
    </form>
</div>
<?php else: ?>
<p class="text-muted" style="margin-top:14px">
    <i class="fas fa-check"></i> Tous les rôles éligibles ont une règle configurée.
</p>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
