<?php
/**
 * Page : Activité des admins (vue DG)
 *
 * Lecture du journal `admin_activity_logs` avec filtres :
 *   - Recherche libre (action, module, description, IP)
 *   - Admin spécifique
 *   - Rôle spécifique
 *   - Module (auth, products, clients, dg, etc.)
 *   - Plage de dates
 *   - Pagination (50 par page)
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Activité des admins';

// ─── Filtres ─────────────────────────────────────────────────────────────
$filter_q      = trim($_GET['q']        ?? '');
$filter_admin  = (int) ($_GET['admin']  ?? 0);
$filter_role   = (int) ($_GET['role']   ?? 0);
$filter_module = trim($_GET['module']   ?? '');
$filter_from   = trim($_GET['from']     ?? '');
$filter_to     = trim($_GET['to']       ?? '');

$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;

$where  = ['1=1'];
$params = [];

if ($filter_q !== '') {
    $where[] = '(l.action LIKE ? OR l.module LIKE ? OR l.description LIKE ? OR l.ip_address LIKE ?)';
    $like = '%' . $filter_q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filter_admin > 0) {
    $where[]  = 'l.admin_id = ?';
    $params[] = $filter_admin;
}
if ($filter_role > 0) {
    $where[]  = 'a.admin_role_id = ?';
    $params[] = $filter_role;
}
if ($filter_module !== '') {
    $where[]  = 'l.module = ?';
    $params[] = $filter_module;
}
if ($filter_from !== '') {
    $where[]  = 'DATE(l.created_at) >= ?';
    $params[] = $filter_from;
}
if ($filter_to !== '') {
    $where[]  = 'DATE(l.created_at) <= ?';
    $params[] = $filter_to;
}

$where_sql = implode(' AND ', $where);

// ─── Données ─────────────────────────────────────────────────────────────
$logs        = [];
$total_count = 0;
try {
    // Total pour pagination
    $stc = $pdo->prepare("
        SELECT COUNT(*)
        FROM admin_activity_logs l
        LEFT JOIN admins      a ON l.admin_id = a.id
        LEFT JOIN admin_roles r ON a.admin_role_id = r.id
        WHERE $where_sql
    ");
    $stc->execute($params);
    $total_count = (int) $stc->fetchColumn();

    // Page courante
    $sql = "
        SELECT l.id, l.admin_id, l.action, l.module, l.description, l.ip_address,
               l.user_agent, l.created_at,
               a.full_name, a.email,
               r.role_name
        FROM admin_activity_logs l
        LEFT JOIN admins      a ON l.admin_id = a.id
        LEFT JOIN admin_roles r ON a.admin_role_id = r.id
        WHERE $where_sql
        ORDER BY l.created_at DESC
        LIMIT $per OFFSET $off
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $logs = $st->fetchAll();
} catch (PDOException $e) {
    error_log('DG activity-log query: ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total_count / $per));

// ─── Listes pour filtres ─────────────────────────────────────────────────
$admins_list  = [];
$roles_list   = [];
$modules_list = [];
try {
    $admins_list  = $pdo->query("SELECT id, full_name FROM admins ORDER BY full_name")->fetchAll();
    $roles_list   = $pdo->query("SELECT id, role_name FROM admin_roles WHERE is_active = 1 ORDER BY role_name")->fetchAll();
    $modules_list = $pdo->query("SELECT DISTINCT module FROM admin_activity_logs WHERE module IS NOT NULL AND module != '' ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* OK */ }

$ROLE_LABELS = [
    'dg'          => '👑 DG',
    'order'       => '🛒 Commandes',
    'preparation' => '📦 Préparation',
    'delivery'    => '🚚 Livraison',
    'product'     => '🏷️ Produits',
    'stock'       => '📊 Stock',
    'client'      => '👥 Clients',
    'marketing'   => '📢 Marketing',
    'support'     => '🎧 Support',
];

// Construire l'URL des filtres pour la pagination (préserve les params)
function build_query(array $extra = []): string {
    $params = $_GET;
    foreach ($extra as $k => $v) $params[$k] = $v;
    return '?' . http_build_query($params);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-history"></i> Activité des admins</h2>
    <span class="text-muted"><?= number_format($total_count, 0, ',', ' ') ?> entrée(s) — page <?= $page ?>/<?= $total_pages ?></span>
</div>

<!-- Filtres -->
<div class="card">
    <form method="GET" class="form-row" style="grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto; align-items: end">
        <div class="form-group" style="margin-bottom:0">
            <label>Recherche</label>
            <input type="text" name="q" class="form-input" value="<?= htmlspecialchars($filter_q) ?>" placeholder="Action, description, IP…">
        </div>
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
            <label>Rôle</label>
            <select name="role" class="form-select">
                <option value="0">Tous</option>
                <?php foreach ($roles_list as $r):
                    $lbl = $ROLE_LABELS[$r['role_name']] ?? ucfirst($r['role_name']);
                ?>
                <option value="<?= (int)$r['id'] ?>" <?= $filter_role === (int)$r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Module</label>
            <select name="module" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($modules_list as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= $filter_module === $m ? 'selected' : '' ?>>
                    <?= htmlspecialchars($m) ?>
                </option>
                <?php endforeach; ?>
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
    <?php if ($filter_q || $filter_admin || $filter_role || $filter_module || $filter_from || $filter_to): ?>
        <p style="margin-top:10px"><a href="activity-log.php" class="btn btn-sm btn-ghost"><i class="fas fa-times"></i> Réinitialiser les filtres</a></p>
    <?php endif; ?>
</div>

<!-- Tableau -->
<div class="card">
    <?php if (empty($logs)): ?>
        <p class="text-muted text-center" style="padding:30px">Aucune entrée pour ces filtres.</p>
    <?php else: ?>
    <table class="data">
        <thead>
        <tr>
            <th>Date</th>
            <th>Admin</th>
            <th>Rôle</th>
            <th>Action</th>
            <th>Module</th>
            <th>Description</th>
            <th>IP</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log):
            $roleLbl = $ROLE_LABELS[$log['role_name']] ?? ucfirst($log['role_name'] ?? '—');
            $actionLower = strtolower($log['action']);
            $badge = 'badge-muted';
            if (str_contains($actionLower, 'login') && !str_contains($actionLower, 'fail')) $badge = 'badge-success';
            elseif (str_contains($actionLower, 'fail') || str_contains($actionLower, 'block')) $badge = 'badge-danger';
            elseif (str_contains($actionLower, 'logout')) $badge = 'badge-info';
            elseif (str_contains($actionLower, 'delete'))  $badge = 'badge-danger';
            elseif (str_contains($actionLower, 'create') || str_contains($actionLower, 'add')) $badge = 'badge-success';
            elseif (str_contains($actionLower, 'update') || str_contains($actionLower, 'edit')) $badge = 'badge-warning';
        ?>
        <tr>
            <td class="text-muted" style="font-size:0.83rem; white-space:nowrap">
                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($log['created_at']))) ?>
            </td>
            <td>
                <?php if (!empty($log['full_name'])): ?>
                    <strong><?= htmlspecialchars($log['full_name']) ?></strong>
                <?php else: ?>
                    <span class="text-muted">— supprimé —</span>
                <?php endif; ?>
                <div class="text-muted" style="font-size:0.75rem"><?= htmlspecialchars($log['email'] ?? '') ?></div>
            </td>
            <td><span class="badge badge-info"><?= htmlspecialchars($roleLbl) ?></span></td>
            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($log['action']) ?></span></td>
            <td><span class="badge badge-muted"><?= htmlspecialchars($log['module'] ?? '—') ?></span></td>
            <td class="text-muted" style="font-size:0.85rem">
                <?= htmlspecialchars(mb_substr($log['description'] ?? '', 0, 110)) ?>
                <?php if (mb_strlen($log['description'] ?? '') > 110): ?>…<?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:0.78rem; white-space:nowrap"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center">
        <span class="text-muted">Page <?= $page ?> sur <?= $total_pages ?></span>
        <div style="display:flex; gap:6px">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars(build_query(['page' => 1])) ?>"><i class="fas fa-angle-double-left"></i></a>
                <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars(build_query(['page' => $page - 1])) ?>"><i class="fas fa-angle-left"></i> Préc.</a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars(build_query(['page' => $page + 1])) ?>">Suiv. <i class="fas fa-angle-right"></i></a>
                <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars(build_query(['page' => $total_pages])) ?>"><i class="fas fa-angle-double-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
