<?php
/**
 * Gestionnaire de Catalogue — Super Admin
 * Accès réservé : Super Admin uniquement.
 * CRUD complet sur la table `categories` avec CSRF + logs.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$page_title  = 'Gestionnaire de Catalogue';
$sa_id       = (int) $_SESSION['superadmin_id'];
$flash       = ['type' => null, 'msg' => null];

// ─── Helpers ─────────────────────────────────────────────────────────────────
function make_slug_cat(string $name): string {
    $s = mb_strtolower($name, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function csrf_field_sa(): string {
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// ─── Traitement POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = ['type'=>'danger','msg'=>'Jeton CSRF invalide. Rechargez la page.'];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            // ── Ajouter ──────────────────────────────────────────────────────
            if ($action === 'add') {
                $name      = trim($_POST['name']        ?? '');
                $desc      = trim($_POST['description'] ?? '');
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $icon      = trim($_POST['icon']        ?? '');
                $order     = (int)($_POST['display_order'] ?? 0);
                $active    = isset($_POST['is_active']) ? 1 : 0;

                if ($name === '') {
                    $flash = ['type'=>'danger','msg'=>'Le nom de la catégorie est requis.'];
                } else {
                    $slug = make_slug_cat($name);
                    $pdo->prepare("
                        INSERT INTO categories (name, description, slug, parent_id, icon, display_order,
                                               is_active, is_visible_menu, is_visible_homepage, level, template, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, 'default', NOW(), NOW())
                    ")->execute([$name, $desc, $slug, $parent_id, $icon ?: null, $order,
                                 $active, $parent_id ? 1 : 0]);

                    $new_id = (int)$pdo->lastInsertId();
                    log_superadmin_action($sa_id, 'CATALOGUE_ADD', "Catégorie ajoutée : #$new_id « $name »");
                    $flash = ['type'=>'success','msg'=>"Catégorie « ".htmlspecialchars($name)." » ajoutée."];
                }
            }

            // ── Modifier ─────────────────────────────────────────────────────
            elseif ($action === 'edit') {
                $id        = (int)($_POST['id'] ?? 0);
                $name      = trim($_POST['name']        ?? '');
                $desc      = trim($_POST['description'] ?? '');
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $icon      = trim($_POST['icon']        ?? '');
                $order     = (int)($_POST['display_order'] ?? 0);
                $active    = isset($_POST['is_active']) ? 1 : 0;

                if ($parent_id === $id) {
                    $flash = ['type'=>'danger','msg'=>'Une catégorie ne peut pas être son propre parent.'];
                } elseif ($name === '') {
                    $flash = ['type'=>'danger','msg'=>'Le nom est requis.'];
                } elseif ($id > 0) {
                    $slug = make_slug_cat($name);
                    $pdo->prepare("
                        UPDATE categories
                        SET name=?, description=?, slug=?, parent_id=?, icon=?,
                            display_order=?, is_active=?, level=?, updated_at=NOW()
                        WHERE id=?
                    ")->execute([$name, $desc, $slug, $parent_id, $icon ?: null,
                                 $order, $active, $parent_id ? 1 : 0, $id]);

                    log_superadmin_action($sa_id, 'CATALOGUE_EDIT', "Catégorie modifiée : #$id « $name »");
                    $flash = ['type'=>'success','msg'=>"Catégorie « ".htmlspecialchars($name)." » mise à jour."];
                }
            }

            // ── Toggle ───────────────────────────────────────────────────────
            elseif ($action === 'toggle' && !empty($_POST['id'])) {
                $id = (int)$_POST['id'];
                $pdo->prepare("UPDATE categories SET is_active = 1 - is_active, updated_at=NOW() WHERE id=?")
                    ->execute([$id]);
                log_superadmin_action($sa_id, 'CATALOGUE_TOGGLE', "Catégorie #$id : état basculé");
                $flash = ['type'=>'success','msg'=>'Statut basculé.'];
            }

            // ── Supprimer ────────────────────────────────────────────────────
            elseif ($action === 'delete' && !empty($_POST['id'])) {
                $id = (int)$_POST['id'];
                $kids = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id=?");
                $kids->execute([$id]);
                if ((int)$kids->fetchColumn() > 0) {
                    $flash = ['type'=>'danger','msg'=>'Impossible de supprimer : des sous-catégories existent. Supprimez-les d\'abord.'];
                } else {
                    $prods = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
                    $prods->execute([$id]);
                    $cnt = (int)$prods->fetchColumn();
                    if ($cnt > 0) {
                        $flash = ['type'=>'danger','msg'=>"Impossible de supprimer : $cnt produit(s) sont liés à cette catégorie."];
                    } else {
                        $row = $pdo->prepare("SELECT name FROM categories WHERE id=?");
                        $row->execute([$id]);
                        $cat_name = $row->fetchColumn() ?: '#'.$id;
                        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
                        log_superadmin_action($sa_id, 'CATALOGUE_DELETE', "Catégorie supprimée : #$id « $cat_name »");
                        $flash = ['type'=>'success','msg'=>"Catégorie « ".htmlspecialchars($cat_name)." » supprimée."];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('catalogue SuperAdmin: '.$e->getMessage());
            if ((int)$e->getCode() === 23000) {
                $flash = ['type'=>'danger','msg'=>'Ce slug ou ce nom existe déjà.'];
            } else {
                $flash = ['type'=>'danger','msg'=>'Erreur base de données. Veuillez réessayer.'];
            }
        }
    }
}

// ─── Lecture des données ──────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
if ($filter === 'active')   { $where .= " AND c.is_active=1"; }
if ($filter === 'inactive') { $where .= " AND c.is_active=0"; }
if ($filter === 'root')     { $where .= " AND c.parent_id IS NULL"; }
if ($filter === 'sub')      { $where .= " AND c.parent_id IS NOT NULL"; }

$stmt = $pdo->prepare("
    SELECT c.*,
           p.name AS parent_name,
           (SELECT COUNT(*) FROM categories cc WHERE cc.parent_id=c.id) AS children_count,
           (SELECT COUNT(*) FROM products pr WHERE pr.category_id=c.id) AS product_count
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    $where
    ORDER BY COALESCE(c.parent_id, c.id), c.display_order, c.name
");
$stmt->execute($params);
$categories = $stmt->fetchAll();

$roots       = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();
$total_cats  = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$active_cats = $pdo->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn();
$root_cats   = $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn();

$sa_name = $_SESSION['superadmin_name'] ?? 'Super Admin';
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Super Admin AtlanTech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; font-family:'Inter',sans-serif; background:#0f0e17; color:#e0e0f0; min-height:100vh; display:flex; }

    /* ── Sidebar ── */
    .sidebar { width:240px; min-height:100vh; background:#1a1a2e; padding:20px 0; flex-shrink:0; }
    .sidebar-logo { padding:0 20px 20px; border-bottom:1px solid rgba(168,85,247,.2); }
    .sidebar-logo i { color:#a855f7; margin-right:8px; }
    .sidebar-logo { font-size:1rem; font-weight:700; color:#fff; }
    .sidebar-menu { list-style:none; margin:20px 0 0; padding:0; }
    .sidebar-menu li a {
        display:flex; align-items:center; gap:10px;
        padding:10px 20px; color:#9ca3af; text-decoration:none; font-size:.875rem; transition:.15s;
    }
    .sidebar-menu li a:hover, .sidebar-menu li a.active { background:rgba(168,85,247,.15); color:#a855f7; }
    .sidebar-menu i { width:16px; text-align:center; }
    .sidebar-bottom { padding:20px; margin-top:auto; color:#6b7280; font-size:.8rem; border-top:1px solid rgba(168,85,247,.1); }

    /* ── Main ── */
    .main-container { flex:1; padding:30px; max-width:1200px; }
    .page-header { margin-bottom:24px; }
    .page-header h1 { margin:0 0 4px; font-size:1.5rem; font-weight:700; color:#fff; }
    .page-header p { margin:0; color:#9ca3af; font-size:.875rem; }
    .header-actions { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; }

    .stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
    .stat-card { background:#1a1a2e; border:1px solid rgba(168,85,247,.2); border-radius:12px; padding:18px; }
    .stat-card .val { font-size:1.9rem; font-weight:700; color:#a855f7; }
    .stat-card .lbl { font-size:.75rem; color:#6b7280; margin-top:4px; }

    .filter-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; align-items:center; }
    .filter-bar input, .filter-bar select {
        padding:9px 13px; background:#1a1a2e; border:1px solid rgba(168,85,247,.3);
        border-radius:8px; color:#e0e0f0; font-size:.88rem;
    }
    .filter-bar input { flex:1; min-width:200px; }

    .btn-purple { background:#7c3aed; color:#fff; border:none; padding:9px 18px; border-radius:8px; font-weight:600; cursor:pointer; font-size:.875rem; }
    .btn-purple:hover { background:#6d28d9; }
    .btn-ghost  { background:rgba(124,58,237,.15); color:#a855f7; border:1px solid rgba(124,58,237,.3); padding:9px 14px; border-radius:8px; font-size:.875rem; cursor:pointer; text-decoration:none; }

    table { width:100%; border-collapse:collapse; background:#1a1a2e; border-radius:12px; overflow:hidden; }
    th { background:#12122a; color:#6b7280; font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; padding:10px 14px; text-align:left; border-bottom:1px solid rgba(168,85,247,.15); }
    td { padding:10px 14px; border-bottom:1px solid rgba(255,255,255,.04); font-size:.875rem; vertical-align:middle; color:#d1d5db; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(168,85,247,.05); }
    .row-sub td:first-child { padding-left:28px; }
    .indent-icon { color:#4b5563; margin-right:6px; }

    .badge-active   { background:rgba(34,197,94,.15);  color:#4ade80; padding:3px 9px; border-radius:20px; font-size:.73rem; font-weight:600; }
    .badge-inactive { background:rgba(107,114,128,.15);color:#6b7280; padding:3px 9px; border-radius:20px; font-size:.73rem; font-weight:600; }
    .badge-root { background:rgba(99,102,241,.2); color:#818cf8; padding:3px 8px; border-radius:20px; font-size:.72rem; }
    .badge-sub  { background:rgba(245,158,11,.15); color:#fbbf24; padding:3px 8px; border-radius:20px; font-size:.72rem; }

    .action-btns { display:flex; gap:5px; }
    .btn-sm { padding:5px 10px; border:none; border-radius:6px; cursor:pointer; font-size:.75rem; font-weight:600; }
    .btn-edit   { background:rgba(245,158,11,.2);  color:#fbbf24; }
    .btn-toggle { background:rgba(99,102,241,.2);  color:#818cf8; }
    .btn-del    { background:rgba(239,68,68,.15);  color:#f87171; }
    .btn-sm:hover { opacity:.75; }
    .btn-sm:disabled { opacity:.35; cursor:not-allowed; }

    .flash { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:.875rem; }
    .flash.success { background:rgba(34,197,94,.15);  color:#4ade80; border:1px solid rgba(34,197,94,.3); }
    .flash.danger  { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }

    code { background:rgba(124,58,237,.15); color:#c4b5fd; padding:2px 6px; border-radius:4px; font-size:.78rem; }

    /* Modal */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:1000; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:#1a1a2e; border:1px solid rgba(168,85,247,.3); border-radius:14px; padding:28px 30px; max-width:540px; width:95%; max-height:90vh; overflow-y:auto; }
    .modal-box h3 { margin:0 0 18px; font-size:1.1rem; font-weight:700; color:#fff; border-bottom:1px solid rgba(168,85,247,.2); padding-bottom:12px; }
    .form-row { margin-bottom:14px; }
    .form-row label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; color:#9ca3af; }
    .form-row input, .form-row textarea, .form-row select {
        width:100%; padding:9px 12px; background:#12122a; border:1px solid rgba(168,85,247,.25);
        border-radius:8px; color:#e0e0f0; font-size:.88rem;
    }
    .form-row textarea { resize:vertical; min-height:70px; }
    .form-row small { color:#4b5563; font-size:.75rem; margin-top:4px; display:block; }
    .form-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    .btn-save   { background:#7c3aed; color:#fff; border:none; padding:9px 20px; border-radius:8px; font-weight:600; cursor:pointer; }
    .btn-cancel { background:rgba(255,255,255,.08); color:#9ca3af; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; }
    .checkbox-row { display:flex; align-items:center; gap:8px; font-size:.88rem; color:#9ca3af; }
    .empty-state { text-align:center; padding:60px 20px; color:#4b5563; }
    .empty-state i { font-size:3rem; margin-bottom:14px; display:block; }
    </style>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

<!-- Mobile top bar (hamburger) -->
<div class="sa-mobile-header">
    <span class="sa-mobile-logo"><i class="fas fa-shield-alt" style="margin-right:6px;color:#ffd700;-webkit-text-fill-color:#ffd700"></i>ATLANTECH SA</span>
    <button class="sa-hamburger" id="sa-hamburger-btn" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
    </button>
</div>
<!-- Sidebar overlay -->
<div class="sa-sidebar-overlay" id="sa-sidebar-overlay"></div>


<!-- ── Sidebar ── -->
<div class="sidebar">
    <!-- Close button (mobile) -->
    <button class="sa-sidebar-close" id="sa-sidebar-close-btn" aria-label="Fermer">
        <i class="fas fa-times"></i>
    </button>

    <div class="sidebar-logo"><i class="fas fa-crown"></i> SUPER ADMIN</div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"      class="<?= $current==='dashboard.php'      ?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="admins-list.php"    class="<?= $current==='admins-list.php'    ?'active':'' ?>"><i class="fas fa-user-shield"></i> Administrateurs</a></li>
        <li><a href="admin-create.php"   class="<?= $current==='admin-create.php'   ?'active':'' ?>"><i class="fas fa-user-plus"></i> Créer Admin</a></li>
        <li><a href="manage_users.php"   class="<?= $current==='manage_users.php'   ?'active':'' ?>"><i class="fas fa-users"></i> Clients</a></li>
        <li><a href="manage_products.php" class="<?= $current==='manage_products.php'?'active':'' ?>"><i class="fas fa-box"></i> Produits</a></li>
        <li><a href="catalogue.php"      class="<?= $current==='catalogue.php'      ?'active':'' ?>"><i class="fas fa-th-large"></i> Catalogue</a></li>
        <li><a href="manage_orders.php"  class="<?= $current==='manage_orders.php'  ?'active':'' ?>"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
        <li><a href="taux-change.php"    class="<?= $current==='taux-change.php'    ?'active':'' ?>"><i class="fas fa-dollar-sign"></i> Taux de Change</a></li>
        <li><a href="system-logs.php"    class="<?= $current==='system-logs.php'    ?'active':'' ?>"><i class="fas fa-history"></i> Journaux</a></li>
        <li><a href="settings.php"       class="<?= $current==='settings.php'       ?'active':'' ?>"><i class="fas fa-cog"></i> Paramètres</a></li>
        <li style="margin-top:20px;border-top:1px solid rgba(168,85,247,.15);padding-top:15px;">
            <a href="../logout.php" style="color:#f87171"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </li>
    </ul>
    <div class="sidebar-bottom"><i class="fas fa-crown"></i> <?= htmlspecialchars($sa_name) ?></div>
</div>

<!-- ── Main ── -->
<div class="main-container">
    <div class="page-header">
        <h1><i class="fas fa-th-large" style="color:#a855f7"></i> Gestionnaire de Catalogue</h1>
        <p>Catalogue universel AtlanTech — réservé au Super Admin et au DG. Créez, modifiez et organisez toutes les catégories.</p>
    </div>

    <?php if ($flash['type']): ?>
    <div class="flash <?= $flash['type'] ?>">
        <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-row">
        <div class="stat-card"><div class="val"><?= $total_cats ?></div><div class="lbl">Total catégories</div></div>
        <div class="stat-card"><div class="val" style="color:#4ade80"><?= $active_cats ?></div><div class="lbl">Actives</div></div>
        <div class="stat-card"><div class="val" style="color:#818cf8"><?= $root_cats ?></div><div class="lbl">Catégories racines</div></div>
        <div class="stat-card"><div class="val" style="color:#fbbf24"><?= $total_cats - $root_cats ?></div><div class="lbl">Sous-catégories</div></div>
    </div>

    <div class="header-actions">
        <form method="GET" class="filter-bar" style="flex:1;margin:0">
            <input type="text" name="q" placeholder="Rechercher par nom, slug…" value="<?= htmlspecialchars($search) ?>">
            <select name="filter">
                <option value="all"      <?= $filter==='all'      ?'selected':'' ?>>Toutes</option>
                <option value="active"   <?= $filter==='active'   ?'selected':'' ?>>Actives</option>
                <option value="inactive" <?= $filter==='inactive' ?'selected':'' ?>>Inactives</option>
                <option value="root"     <?= $filter==='root'     ?'selected':'' ?>>Racines</option>
                <option value="sub"      <?= $filter==='sub'      ?'selected':'' ?>>Sous-catégories</option>
            </select>
            <button type="submit" class="btn-ghost"><i class="fas fa-search"></i></button>
            <?php if ($search || $filter!=='all'): ?>
                <a href="catalogue.php" class="btn-ghost"><i class="fas fa-times"></i> Effacer</a>
            <?php endif; ?>
        </form>
        <button class="btn-purple" onclick="openModal()" style="white-space:nowrap">
            <i class="fas fa-plus"></i> Nouvelle Catégorie
        </button>
    </div>

    <!-- Table -->
    <?php if (empty($categories)): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        Aucune catégorie trouvée<?= $search ? " pour « ".htmlspecialchars($search)."»" : '' ?>.
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Nom</th><th>Slug</th><th>Type</th>
                <th>Parent</th><th>Enfants</th><th>Produits</th><th>Ordre</th>
                <th>Statut</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat):
            $is_sub = !is_null($cat['parent_id']);
        ?>
            <tr class="<?= $is_sub ? 'row-sub' : '' ?>">
                <td style="color:#6b7280"><?= $cat['id'] ?></td>
                <td>
                    <?php if ($is_sub): ?>
                        <i class="fas fa-level-up-alt fa-rotate-90 indent-icon"></i>
                    <?php else: ?>
                        <i class="fas fa-folder" style="color:#7c3aed;margin-right:6px"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($cat['name']) ?>
                </td>
                <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                <td>
                    <?= $is_sub ? '<span class="badge-sub">Sous-catégorie</span>' : '<span class="badge-root">Racine</span>' ?>
                </td>
                <td><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<span style="color:#374151">—</span>' ?></td>
                <td style="font-weight:600;color:<?= $cat['children_count']>0?'#818cf8':'#374151' ?>"><?= $cat['children_count'] ?></td>
                <td style="font-weight:600;color:<?= $cat['product_count']>0?'#4ade80':'#374151' ?>"><?= $cat['product_count'] ?></td>
                <td style="color:#6b7280"><?= $cat['display_order'] ?></td>
                <td>
                    <?= $cat['is_active']
                        ? '<span class="badge-active">Actif</span>'
                        : '<span class="badge-inactive">Inactif</span>' ?>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn-sm btn-edit"
                            onclick='editModal(<?= json_encode([
                                "id"            => $cat["id"],
                                "name"          => $cat["name"],
                                "description"   => $cat["description"] ?? "",
                                "slug"          => $cat["slug"],
                                "parent_id"     => $cat["parent_id"],
                                "icon"          => $cat["icon"] ?? "",
                                "display_order" => $cat["display_order"],
                                "is_active"     => $cat["is_active"],
                            ]) ?>)'>
                            <i class="fas fa-pen"></i>
                        </button>
                        <form method="POST" style="display:inline">
                            <?= csrf_field_sa() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn-sm btn-toggle">
                                <i class="fas <?= $cat['is_active']?'fa-eye-slash':'fa-eye' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Supprimer « <?= htmlspecialchars(addslashes($cat['name'])) ?> » ?\nCette action est irréversible.')">
                            <?= csrf_field_sa() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn-sm btn-del"
                                <?= ($cat['children_count']>0||$cat['product_count']>0) ? 'disabled title="Des enfants ou produits sont liés"' : '' ?>>
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:.78rem;color:#374151;margin-top:10px">
        <?= count($categories) ?> catégorie(s) — <?= $search ? "recherche : « ".htmlspecialchars($search)." »" : 'toutes affichées' ?>
    </p>
    <?php endif; ?>
</div><!-- /main-container -->

<!-- ── Modal ── -->
<div id="catModal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modalTitle"><i class="fas fa-folder-plus" style="color:#a855f7"></i> Nouvelle Catégorie</h3>
        <form method="POST" id="catForm">
            <?= csrf_field_sa() ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id"     id="catId">

            <div class="form-row">
                <label>Nom <span style="color:#f87171">*</span></label>
                <input type="text" name="name" id="catName" required placeholder="Ex : Mode & Vêtements">
            </div>
            <div class="form-row">
                <label>Slug (auto-généré)</label>
                <input type="text" name="slug" id="catSlug" readonly style="opacity:.6">
            </div>
            <div class="form-row">
                <label>Description</label>
                <textarea name="description" id="catDesc" placeholder="Description courte…"></textarea>
            </div>
            <div class="form-row">
                <label>Catégorie Parent</label>
                <select name="parent_id" id="catParent">
                    <option value="">— Aucune (catégorie racine) —</option>
                    <?php foreach ($roots as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Laissez vide pour une catégorie de premier niveau.</small>
            </div>
            <div class="form-row">
                <label>Icône SVG</label>
                <input type="text" name="icon" id="catIcon" placeholder="hc_mode.svg">
            </div>
            <div class="form-row">
                <label>Ordre d'affichage</label>
                <input type="number" name="display_order" id="catOrder" value="0" min="0">
            </div>
            <div class="form-row">
                <div class="checkbox-row">
                    <input type="checkbox" name="is_active" id="catActive" value="1" checked>
                    <label for="catActive" style="margin:0;font-weight:400">Catégorie active</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('catName').addEventListener('input', function() {
    const s = this.value.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g,'')
        .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    document.getElementById('catSlug').value = s;
});
function openModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-folder-plus" style="color:#a855f7"></i> Nouvelle Catégorie';
    document.getElementById('formAction').value = 'add';
    document.getElementById('catId').value = '';
    document.getElementById('catForm').reset();
    document.getElementById('catActive').checked = true;
    document.getElementById('catModal').classList.add('open');
}
function editModal(c) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen" style="color:#fbbf24"></i> Modifier la Catégorie';
    document.getElementById('formAction').value  = 'edit';
    document.getElementById('catId').value       = c.id;
    document.getElementById('catName').value     = c.name;
    document.getElementById('catSlug').value     = c.slug;
    document.getElementById('catDesc').value     = c.description || '';
    document.getElementById('catParent').value   = c.parent_id || '';
    document.getElementById('catIcon').value     = c.icon || '';
    document.getElementById('catOrder').value    = c.display_order || 0;
    document.getElementById('catActive').checked = c.is_active == 1;
    document.getElementById('catModal').classList.add('open');
}
function closeModal() { document.getElementById('catModal').classList.remove('open'); }
document.getElementById('catModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
<script>
(function(){
    var overlay   = document.getElementById('sa-sidebar-overlay');
    var sidebar   = document.querySelector('.sidebar');
    var hamburger = document.getElementById('sa-hamburger-btn');
    var closeBtn  = document.getElementById('sa-sidebar-close-btn');
    function openSidebar()  { if(sidebar){sidebar.classList.add('sa-open');}    if(overlay){overlay.classList.add('active');} }
    function closeSidebar() { if(sidebar){sidebar.classList.remove('sa-open');} if(overlay){overlay.classList.remove('active');} }
    if(hamburger) hamburger.addEventListener('click', openSidebar);
    if(closeBtn)  closeBtn.addEventListener('click', closeSidebar);
    if(overlay)   overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
