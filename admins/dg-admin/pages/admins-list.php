<?php
/**
 * Page : Gestion des Admins (vue DG)
 *
 * Le DG voit tous les admins sauf : les autres DG et les éventuels superadmins
 * (qui sont dans une table séparée `superadmins`).
 * Il peut :
 *   - Lister, filtrer par rôle / état
 *   - Activer/désactiver un admin (sauf les autres DG)
 *   - Aller vers le formulaire de création
 *
 * Le PDG (superadmin) reste le seul à pouvoir créer un compte DG ou modifier
 * un compte DG existant.
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Gestion des admins';

// ─── POST : activer/désactiver un admin ──────────────────────────────────
$flash = ['type' => null, 'message' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action  = $_POST['action']   ?? '';
    $adminId = (int)($_POST['admin_id'] ?? 0);

    if (in_array($action, ['activate', 'deactivate'], true) && $adminId > 0) {
        try {
            // Vérifier que la cible n'est PAS un DG (un DG ne peut pas modifier un autre DG)
            $st = $pdo->prepare("SELECT id, full_name, admin_role_id FROM admins WHERE id = ?");
            $st->execute([$adminId]);
            $target = $st->fetch();

            if (!$target) {
                $flash = ['type' => 'danger', 'message' => 'Admin introuvable.'];
            } elseif ((int)$target['admin_role_id'] === DG_ROLE_ID) {
                $flash = ['type' => 'danger', 'message' => "Vous ne pouvez pas modifier un autre Directeur Général. Cette action est réservée au PDG."];
                log_dg_action((int)$_SESSION['dg_id'], 'dg_modify_attempt_blocked', "Tentative bloquée sur admin #$adminId (autre DG)");
            } else {
                $newState = ($action === 'activate') ? 1 : 0;
                $pdo->prepare("UPDATE admins SET is_active = ? WHERE id = ?")
                    ->execute([$newState, $adminId]);

                $verb = $newState ? 'activé' : 'désactivé';
                $flash = ['type' => 'success', 'message' => "Admin « " . htmlspecialchars($target['full_name']) . " » {$verb} avec succès."];
                log_dg_action(
                    (int)$_SESSION['dg_id'],
                    'admin_' . $action,
                    "Admin #$adminId ({$target['full_name']}) {$verb} par le DG"
                );
            }
        } catch (PDOException $e) {
            error_log('DG admins-list POST: ' . $e->getMessage());
            $flash = ['type' => 'danger', 'message' => 'Erreur technique. Réessayez.'];
        }
    }
}

// ─── Filtres ──────────────────────────────────────────────────────────────
$filter_role   = isset($_GET['role'])   ? (int)$_GET['role'] : 0;
$filter_state  = $_GET['state']  ?? '';                  // 'active' | 'inactive' | ''
$filter_search = trim($_GET['q'] ?? '');

$where  = ["a.admin_role_id != ?"];                       // exclure les DG eux-mêmes
$params = [DG_ROLE_ID];

if ($filter_role > 0) {
    $where[]  = "a.admin_role_id = ?";
    $params[] = $filter_role;
}
if ($filter_state === 'active') {
    $where[] = "a.is_active = 1";
} elseif ($filter_state === 'inactive') {
    $where[] = "a.is_active = 0";
}
if ($filter_search !== '') {
    $where[]  = "(a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ?)";
    $like = '%' . $filter_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$where_sql = implode(' AND ', $where);

// ─── Données ──────────────────────────────────────────────────────────────
$admins = [];
try {
    $sql = "
        SELECT a.id, a.full_name, a.name, a.email, a.phone, a.is_active,
               a.last_login, a.created_at, a.admin_role_id,
               r.role_name, r.role_description
        FROM admins a
        LEFT JOIN admin_roles r ON a.admin_role_id = r.id
        WHERE $where_sql
        ORDER BY a.is_active DESC, a.created_at DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $admins = $st->fetchAll();
} catch (PDOException $e) {
    error_log('DG admins-list query: ' . $e->getMessage());
    $flash = ['type' => 'danger', 'message' => 'Erreur de chargement.'];
}

// Liste des rôles (hors DG) pour le filtre
$roles = [];
try {
    $st = $pdo->prepare("SELECT id, role_name, role_description FROM admin_roles WHERE is_active = 1 AND id != ? ORDER BY role_name");
    $st->execute([DG_ROLE_ID]);
    $roles = $st->fetchAll();
} catch (PDOException $e) { /* OK */ }

// Libellés rôles (cohérent avec superadmin)
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

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-users-cog"></i> Gestion des admins</h2>
    <a href="admin-create.php" class="btn btn-accent">
        <i class="fas fa-user-plus"></i> Créer un admin
    </a>
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
            <input type="text" name="q" class="form-input" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Nom, email, téléphone…">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Rôle</label>
            <select name="role" class="form-select">
                <option value="0">Tous les rôles</option>
                <?php foreach ($roles as $r):
                    $lbl = $ROLE_LABELS[$r['role_name']] ?? ucfirst($r['role_name']);
                ?>
                <option value="<?= (int)$r['id'] ?>" <?= $filter_role === (int)$r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl) ?>
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
        <div class="form-group" style="margin-bottom:0">
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrer</button>
        </div>
    </form>
</div>

<!-- Tableau -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> <?= count($admins) ?> administrateur(s)</h3>
    </div>

    <?php if (empty($admins)): ?>
        <p class="text-muted text-center" style="padding:30px">Aucun administrateur ne correspond aux filtres.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Rôle</th>
            <th>État</th>
            <th>Dernière connexion</th>
            <th>Créé le</th>
            <th class="actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $a):
            $roleLbl = $ROLE_LABELS[$a['role_name']] ?? ucfirst($a['role_name'] ?? '—');
            $active  = (int)$a['is_active'] === 1;
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($a['full_name']) ?></strong>
                <?php if (!empty($a['name']) && $a['name'] !== $a['full_name']): ?>
                <div class="text-muted" style="font-size:0.78rem">@<?= htmlspecialchars($a['name']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($a['email']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($a['phone'] ?? '—') ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($roleLbl) ?></span></td>
            <td>
                <?php if ($active): ?>
                    <span class="badge badge-success">Actif</span>
                <?php else: ?>
                    <span class="badge badge-danger">Désactivé</span>
                <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:0.85rem">
                <?= $a['last_login'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($a['last_login']))) : '—' ?>
            </td>
            <td class="text-muted" style="font-size:0.85rem">
                <?= htmlspecialchars(date('d/m/Y', strtotime($a['created_at']))) ?>
            </td>
            <td class="actions">
                <form method="POST" style="display:inline" onsubmit="return confirm('<?= $active ? 'Désactiver' : 'Activer' ?> ce compte ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="action"   value="<?= $active ? 'deactivate' : 'activate' ?>">
                    <?php if ($active): ?>
                        <button class="btn btn-sm btn-danger" type="submit" title="Désactiver">
                            <i class="fas fa-ban"></i> Désactiver
                        </button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-success" type="submit" title="Activer">
                            <i class="fas fa-check"></i> Activer
                        </button>
                    <?php endif; ?>
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
    Les comptes <strong>Directeur Général</strong> et le PDG sont gérés exclusivement par le PDG depuis l'interface superadmin.
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
