<?php
/**
 * Page : Tarifs de livraison par ville (vue DG)
 *
 * CRUD complet sur la table `shipping_rates` :
 *   - Liste filtrée par département / recherche
 *   - Ajout d'une nouvelle ville + tarif
 *   - Édition inline du prix
 *   - Activation/désactivation
 *   - Suppression (avec confirmation)
 *
 * Toutes les actions sont CSRF-protégées et tracées via log_dg_action().
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Tarifs de livraison';

$flash = ['type' => null, 'message' => null];

// ─── Traitement POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? '';
    $dg_id  = (int) $_SESSION['dg_id'];

    try {
        if ($action === 'add') {
            $city       = trim($_POST['city']       ?? '');
            $department = trim($_POST['department'] ?? '');
            $price      = (float) ($_POST['price_htg'] ?? 0);
            $notes      = trim($_POST['notes']      ?? '');

            if ($city === '') {
                $flash = ['type' => 'danger', 'message' => 'Le nom de la ville est requis.'];
            } elseif ($price < 0) {
                $flash = ['type' => 'danger', 'message' => 'Le prix doit être positif ou zéro.'];
            } else {
                $st = $pdo->prepare(
                    "INSERT INTO shipping_rates (city, department, price_htg, notes, is_active, created_by_admin_id)
                     VALUES (?, ?, ?, ?, 1, ?)"
                );
                $st->execute([$city, $department ?: null, $price, $notes ?: null, $dg_id]);
                $flash = ['type' => 'success', 'message' => "Tarif ajouté : " . htmlspecialchars($city) . " — " . number_format($price, 0, ',', ' ') . " HTG"];
                log_dg_action($dg_id, 'shipping_rate_add', "Ajout tarif livraison : $city = $price HTG");
            }
        }
        elseif ($action === 'update_price') {
            $id    = (int) ($_POST['id'] ?? 0);
            $price = (float) ($_POST['price_htg'] ?? 0);
            if ($id > 0 && $price >= 0) {
                $pdo->prepare("UPDATE shipping_rates SET price_htg = ?, updated_by_admin_id = ? WHERE id = ?")
                    ->execute([$price, $dg_id, $id]);
                $flash = ['type' => 'success', 'message' => "Prix mis à jour à " . number_format($price, 0, ',', ' ') . " HTG."];
                log_dg_action($dg_id, 'shipping_rate_update', "Tarif #$id mis à $price HTG");
            }
        }
        elseif ($action === 'toggle' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $pdo->prepare("UPDATE shipping_rates SET is_active = 1 - is_active, updated_by_admin_id = ? WHERE id = ?")
                ->execute([$dg_id, $id]);
            $flash = ['type' => 'success', 'message' => "Statut basculé."];
            log_dg_action($dg_id, 'shipping_rate_toggle', "Tarif #$id état basculé");
        }
        elseif ($action === 'delete' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $st = $pdo->prepare("SELECT city FROM shipping_rates WHERE id = ?");
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row) {
                $pdo->prepare("DELETE FROM shipping_rates WHERE id = ?")->execute([$id]);
                $flash = ['type' => 'success', 'message' => "Tarif « " . htmlspecialchars($row['city']) . " » supprimé."];
                log_dg_action($dg_id, 'shipping_rate_delete', "Tarif #$id ({$row['city']}) supprimé");
            }
        }
    } catch (PDOException $e) {
        // Code 23000 = violation contrainte (typiquement uniq_city → doublon)
        if ((int) $e->getCode() === 23000) {
            $flash = ['type' => 'danger', 'message' => 'Cette ville existe déjà dans la liste.'];
        } else {
            error_log('DG shipping-rates POST: ' . $e->getMessage());
            $flash = ['type' => 'danger', 'message' => 'Erreur technique. Réessayez.'];
        }
    }
}

// ─── Filtres GET ─────────────────────────────────────────────────────────
$filter_search = trim($_GET['q']  ?? '');
$filter_dept   = trim($_GET['dept'] ?? '');
$filter_state  = $_GET['state'] ?? '';

$where  = ['1=1'];
$params = [];
if ($filter_search !== '') {
    $where[]  = '(city LIKE ? OR notes LIKE ?)';
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}
if ($filter_dept !== '') {
    $where[]  = 'department = ?';
    $params[] = $filter_dept;
}
if ($filter_state === 'active')   $where[] = 'is_active = 1';
if ($filter_state === 'inactive') $where[] = 'is_active = 0';

$where_sql = implode(' AND ', $where);

// ─── Données ─────────────────────────────────────────────────────────────
$rates = [];
try {
    $st = $pdo->prepare("SELECT * FROM shipping_rates WHERE $where_sql ORDER BY is_active DESC, department ASC, city ASC");
    $st->execute($params);
    $rates = $st->fetchAll();
} catch (PDOException $e) {
    error_log('DG shipping-rates query: ' . $e->getMessage());
}

// Liste des départements distincts (pour le filtre)
$departments = [];
try {
    $departments = $pdo->query("SELECT DISTINCT department FROM shipping_rates WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* OK */ }

// Stats
$stats_total   = count($rates);
$stats_active  = 0;
$stats_avg     = 0;
$stats_sum_act = 0;
foreach ($rates as $r) {
    if ($r['is_active']) { $stats_active++; $stats_sum_act += (float) $r['price_htg']; }
}
$stats_avg = $stats_active > 0 ? $stats_sum_act / $stats_active : 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-truck"></i> Tarifs de livraison par ville</h2>
    <span class="text-muted">
        <?= $stats_active ?>/<?= $stats_total ?> actifs · Tarif moyen : <strong><?= number_format($stats_avg, 0, ',', ' ') ?> HTG</strong>
    </span>
</div>

<?php if ($flash['type']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="card">
    <form method="GET" class="form-row" style="grid-template-columns: 2fr 1fr 1fr auto; align-items: end">
        <div class="form-group" style="margin-bottom:0">
            <label>Recherche</label>
            <input type="text" name="q" class="form-input" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Ville ou note…">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Département</label>
            <select name="dept" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $filter_dept === $d ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>État</label>
            <select name="state" class="form-select">
                <option value="">Tous</option>
                <option value="active"   <?= $filter_state === 'active'   ? 'selected' : '' ?>>Actifs</option>
                <option value="inactive" <?= $filter_state === 'inactive' ? 'selected' : '' ?>>Désactivés</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrer</button>
    </form>
</div>

<!-- Ajouter une ville -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle"></i> Ajouter un tarif</h3>
    </div>
    <form method="POST" class="form-row" style="grid-template-columns: 2fr 1.5fr 1fr 2fr auto; align-items: end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-group" style="margin-bottom:0">
            <label>Ville *</label>
            <input type="text" name="city" class="form-input" required maxlength="100" placeholder="Ex : Saint-Marc">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Département</label>
            <input type="text" name="department" class="form-input" maxlength="100" placeholder="Ex : Artibonite" list="dept-list">
            <datalist id="dept-list">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Prix HTG *</label>
            <input type="number" name="price_htg" class="form-input" step="50" min="0" required placeholder="500">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Notes</label>
            <input type="text" name="notes" class="form-input" maxlength="200" placeholder="Optionnel">
        </div>
        <button class="btn btn-accent" type="submit"><i class="fas fa-plus"></i> Ajouter</button>
    </form>
</div>

<!-- Tableau -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> <?= $stats_total ?> ville(s)</h3>
    </div>

    <?php if (empty($rates)): ?>
        <p class="text-muted text-center" style="padding:30px">Aucune ville pour ces filtres.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th>Ville</th>
            <th>Département</th>
            <th style="width:180px">Prix (HTG)</th>
            <th>Notes</th>
            <th>État</th>
            <th class="actions" style="width:280px">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rates as $r): ?>
        <tr>
            <td><strong><?= htmlspecialchars($r['city']) ?></strong></td>
            <td class="text-muted"><?= htmlspecialchars($r['department'] ?? '—') ?></td>
            <td>
                <!-- Édition inline du prix -->
                <form method="POST" style="display:flex; gap:6px; align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="id"     value="<?= (int) $r['id'] ?>">
                    <input type="number" name="price_htg" class="form-input" style="width:100px; padding:5px 8px"
                           step="50" min="0" value="<?= htmlspecialchars($r['price_htg']) ?>">
                    <button class="btn btn-sm btn-primary" type="submit" title="Mettre à jour le prix">
                        <i class="fas fa-save"></i>
                    </button>
                </form>
            </td>
            <td class="text-muted" style="font-size:0.85rem"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
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
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement « <?= htmlspecialchars($r['city'], ENT_QUOTES) ?> » ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<p class="text-muted" style="font-size:0.8rem; margin-top:12px">
    <i class="fas fa-info-circle"></i>
    Les tarifs <strong>actifs</strong> sont disponibles côté checkout client. Désactivez plutôt que supprimer si une ville sera réutilisée plus tard.
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
