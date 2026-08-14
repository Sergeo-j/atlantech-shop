<?php
/**
 * Page : Codes promo (vue Marketing)
 *
 * Mêmes fonctionnalités que la page DG, mais sous l'identité visuelle Marketing.
 * Logique métier identique : création / édition / activation / suppression.
 */
require_once __DIR__ . '/../includes/auth.php';
require_mkt_auth();

$page_title = 'Codes promo';
$flash      = ['type' => null, 'message' => null];
$mkt_id     = (int) $_SESSION['mkt_id'];

// ─── Traitement POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $code    = strtoupper(trim($_POST['code']             ?? ''));
            $desc    = trim($_POST['description']                 ?? '');
            $percent = (float)($_POST['discount_percent']         ?? 0);
            $from    = trim($_POST['valid_from']                  ?? '');
            $until   = trim($_POST['valid_until']                 ?? '');

            if ($code === '')                              $flash = ['type'=>'danger','message'=>"Le code est requis."];
            elseif (!preg_match('/^[A-Z0-9_-]{2,50}$/', $code))
                                                            $flash = ['type'=>'danger','message'=>"Code invalide (lettres MAJ, chiffres, tirets/underscores)."];
            elseif ($percent <= 0 || $percent > 100)        $flash = ['type'=>'danger','message'=>"Pourcentage entre 0 et 100."];
            elseif ($from && $until && $from > $until)      $flash = ['type'=>'danger','message'=>"Date fin < date début."];
            else {
                $pdo->prepare("INSERT INTO promo_codes
                              (code, description, discount_percent, valid_from, valid_until, is_active, created_by_admin_id)
                              VALUES (?, ?, ?, ?, ?, 1, ?)")
                    ->execute([$code, $desc ?: null, $percent, $from ?: null, $until ?: null, $mkt_id]);
                $flash = ['type'=>'success','message'=>"Code « {$code} » créé ({$percent}%)."];
                log_mkt_action($mkt_id, 'promo_code_add', "Code {$code} créé : {$percent}%");
            }
        }
        elseif ($action === 'update' && !empty($_POST['id'])) {
            $id      = (int) $_POST['id'];
            $percent = (float)($_POST['discount_percent'] ?? 0);
            $from    = trim($_POST['valid_from']  ?? '');
            $until   = trim($_POST['valid_until'] ?? '');
            if ($percent <= 0 || $percent > 100) {
                $flash = ['type'=>'danger','message'=>"Pourcentage entre 0 et 100."];
            } elseif ($from && $until && $from > $until) {
                $flash = ['type'=>'danger','message'=>"Date fin < date début."];
            } else {
                $pdo->prepare("UPDATE promo_codes SET discount_percent = ?, valid_from = ?, valid_until = ?, updated_by_admin_id = ? WHERE id = ?")
                    ->execute([$percent, $from ?: null, $until ?: null, $mkt_id, $id]);
                $flash = ['type'=>'success','message'=>"Code mis à jour."];
                log_mkt_action($mkt_id, 'promo_code_update', "Code #{$id} mis à jour : {$percent}%");
            }
        }
        elseif ($action === 'toggle' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $pdo->prepare("UPDATE promo_codes SET is_active = 1 - is_active, updated_by_admin_id = ? WHERE id = ?")
                ->execute([$mkt_id, $id]);
            $flash = ['type'=>'success','message'=>"Statut basculé."];
            log_mkt_action($mkt_id, 'promo_code_toggle', "Code #{$id} basculé");
        }
        elseif ($action === 'delete' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $st = $pdo->prepare("SELECT code, usage_count FROM promo_codes WHERE id = ?");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) {
                $flash = ['type'=>'danger','message'=>"Code introuvable."];
            } elseif ((int)$row['usage_count'] > 0) {
                $flash = ['type'=>'danger','message'=>"Impossible de supprimer un code déjà utilisé. Désactivez-le plutôt."];
            } else {
                $pdo->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$id]);
                $flash = ['type'=>'success','message'=>"Code « {$row['code']} » supprimé."];
                log_mkt_action($mkt_id, 'promo_code_delete', "Code #{$id} ({$row['code']}) supprimé");
            }
        }
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            $flash = ['type'=>'danger','message'=>"Ce code existe déjà."];
        } else {
            error_log('MKT promo-codes POST: ' . $e->getMessage());
            $flash = ['type'=>'danger','message'=>"Erreur technique."];
        }
    }
}

// ─── Filtres + données (identique au DG) ─────────────────────────────────
$filter_q     = trim($_GET['q']     ?? '');
$filter_state = $_GET['state'] ?? '';
$where  = ['1=1']; $params = [];
if ($filter_q !== '') {
    $where[] = "(code LIKE ? OR description LIKE ?)";
    $params[] = '%' . $filter_q . '%'; $params[] = '%' . $filter_q . '%';
}
$today = date('Y-m-d');
if ($filter_state === 'active') {
    $where[] = "is_active = 1 AND (valid_from IS NULL OR valid_from <= ?) AND (valid_until IS NULL OR valid_until >= ?)";
    $params[] = $today; $params[] = $today;
} elseif ($filter_state === 'expired') {
    $where[] = "valid_until IS NOT NULL AND valid_until < ?";
    $params[] = $today;
} elseif ($filter_state === 'inactive') {
    $where[] = "is_active = 0";
} elseif ($filter_state === 'upcoming') {
    $where[] = "valid_from IS NOT NULL AND valid_from > ?";
    $params[] = $today;
}
$where_sql = implode(' AND ', $where);

$codes = [];
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Connexion BD non disponible ($pdo manquant).');
    }
    $st = $pdo->prepare("SELECT * FROM promo_codes WHERE $where_sql ORDER BY is_active DESC, created_at DESC LIMIT 200");
    $st->execute($params);
    $codes = $st->fetchAll();
} catch (\Throwable $e) {
    error_log('MKT promo-codes query: ' . $e->getMessage());
    $flash = ['type'=>'danger','message'=>"Erreur : " . $e->getMessage()];
}

include __DIR__ . '/../includes/header.php';

function _code_status(array $c): array {
    $today = date('Y-m-d');
    if (!$c['is_active'])                                   return ['Désactivé',  'badge-muted'];
    if ($c['valid_until'] && $today > $c['valid_until'])    return ['Expiré',     'badge-danger'];
    if ($c['valid_from']  && $today < $c['valid_from'])     return ['À venir',    'badge-warning'];
    return ['Actif', 'badge-success'];
}
?>

<div class="page-header">
    <h2><i class="fas fa-tags"></i> Codes promo</h2>
    <span class="text-muted"><?= count($codes) ?> code(s)</span>
</div>

<?php if ($flash['type']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" class="form-row" style="grid-template-columns: 2fr 1fr auto; align-items: end">
        <div class="form-group" style="margin-bottom:0">
            <label>Recherche</label>
            <input type="text" name="q" class="form-input" value="<?= htmlspecialchars($filter_q) ?>" placeholder="Code ou description…">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>État</label>
            <select name="state" class="form-select">
                <option value="">Tous</option>
                <option value="active"   <?= $filter_state === 'active'   ? 'selected' : '' ?>>✅ Actifs en cours</option>
                <option value="upcoming" <?= $filter_state === 'upcoming' ? 'selected' : '' ?>>⏳ À venir</option>
                <option value="expired"  <?= $filter_state === 'expired'  ? 'selected' : '' ?>>⌛ Expirés</option>
                <option value="inactive" <?= $filter_state === 'inactive' ? 'selected' : '' ?>>❌ Désactivés</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrer</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle"></i> Créer un code</h3>
    </div>
    <form method="POST" class="form-row" style="grid-template-columns: 1fr 2fr 1fr 1fr 1fr auto; align-items: end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-group" style="margin-bottom:0">
            <label>Code *</label>
            <input type="text" name="code" class="form-input" required maxlength="50" placeholder="NOEL10" pattern="[A-Za-z0-9_-]{2,50}" style="text-transform:uppercase">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Description</label>
            <input type="text" name="description" class="form-input" maxlength="255" placeholder="Promo de fin d'année">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Réduction (%) *</label>
            <input type="number" name="discount_percent" class="form-input" required step="0.5" min="1" max="100" placeholder="10">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Du</label>
            <input type="date" name="valid_from" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Au</label>
            <input type="date" name="valid_until" class="form-input">
        </div>
        <button class="btn btn-accent" type="submit"><i class="fas fa-plus"></i> Créer</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> <?= count($codes) ?> code(s)</h3>
    </div>
    <?php if (empty($codes)): ?>
        <p class="text-muted text-center" style="padding:30px">Aucun code.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr><th>Code</th><th>Description</th><th style="width:120px">Réduction</th><th style="width:180px">Du</th><th style="width:180px">Au</th><th>Utilisations</th><th>Statut</th><th class="actions" style="width:280px">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($codes as $c):
            [$status, $badge] = _code_status($c);
            $never_used = (int)$c['usage_count'] === 0;
        ?>
        <tr>
            <td><strong style="color:#fde68a;font-family:monospace"><?= htmlspecialchars($c['code']) ?></strong></td>
            <td class="text-muted" style="font-size:0.88rem"><?= htmlspecialchars($c['description'] ?? '—') ?></td>
            <td>
                <form method="POST" style="display:flex; gap:6px; align-items:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id"     value="<?= (int)$c['id'] ?>">
                    <input type="number" name="discount_percent" class="form-input" style="width:80px; padding:4px 6px" step="0.5" min="1" max="100" value="<?= htmlspecialchars($c['discount_percent']) ?>">
                    <span>%</span>
            </td>
            <td><input type="date" name="valid_from" class="form-input" style="padding:4px 6px" value="<?= htmlspecialchars($c['valid_from'] ?? '') ?>"></td>
            <td>
                    <input type="date" name="valid_until" class="form-input" style="padding:4px 6px" value="<?= htmlspecialchars($c['valid_until'] ?? '') ?>">
                    <button class="btn btn-sm btn-primary" type="submit" style="margin-left:6px" title="Sauver"><i class="fas fa-save"></i></button>
                </form>
            </td>
            <td><strong><?= (int)$c['usage_count'] ?></strong></td>
            <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
            <td class="actions">
                <form method="POST" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id"     value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit"><i class="fas fa-toggle-<?= $c['is_active'] ? 'on' : 'off' ?>"></i> <?= $c['is_active'] ? 'Désactiver' : 'Activer' ?></button>
                </form>
                <?php if ($never_used): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer « <?= htmlspecialchars($c['code'], ENT_QUOTES) ?> » ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit" title="Supprimer"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
