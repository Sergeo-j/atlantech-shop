<?php
/**
 * Gestionnaire de Catalogue — DG Admin
 * Accès réservé : Directeur Général uniquement.
 * CRUD complet sur la table `categories` avec CSRF + logs.
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Catalogue Produits';
$dg_id      = (int) $_SESSION['dg_id'];
$flash      = ['type' => null, 'msg' => null];

// ─── Helpers ─────────────────────────────────────────────────────────────────
function make_slug(string $name): string {
    $s = mb_strtolower($name, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// ─── Traitement POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        // ── Ajouter ──────────────────────────────────────────────────────────
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
                $slug = make_slug($name);
                $pdo->prepare("
                    INSERT INTO categories (name, description, slug, parent_id, icon, display_order, is_active,
                                           is_visible_menu, is_visible_homepage, level, template, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, 'default', NOW(), NOW())
                ")->execute([$name, $desc, $slug, $parent_id, $icon ?: null, $order,
                             $active, $parent_id ? 1 : 0]);

                $new_id = (int)$pdo->lastInsertId();
                log_dg_action($dg_id, 'catalogue_add', "Catégorie ajoutée : #$new_id « $name »", 'catalogue');
                $flash = ['type'=>'success','msg'=>"Catégorie « ".htmlspecialchars($name)." » ajoutée avec succès."];
            }
        }

        // ── Modifier ─────────────────────────────────────────────────────────
        elseif ($action === 'edit') {
            $id        = (int)($_POST['id'] ?? 0);
            $name      = trim($_POST['name']        ?? '');
            $desc      = trim($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $icon      = trim($_POST['icon']        ?? '');
            $order     = (int)($_POST['display_order'] ?? 0);
            $active    = isset($_POST['is_active']) ? 1 : 0;

            // Empêcher qu'une catégorie soit son propre parent
            if ($parent_id === $id) {
                $flash = ['type'=>'danger','msg'=>'Une catégorie ne peut pas être son propre parent.'];
            } elseif ($name === '') {
                $flash = ['type'=>'danger','msg'=>'Le nom est requis.'];
            } elseif ($id > 0) {
                $slug = make_slug($name);
                $pdo->prepare("
                    UPDATE categories
                    SET name=?, description=?, slug=?, parent_id=?, icon=?,
                        display_order=?, is_active=?, level=?, updated_at=NOW()
                    WHERE id=?
                ")->execute([$name, $desc, $slug, $parent_id, $icon ?: null,
                             $order, $active, $parent_id ? 1 : 0, $id]);

                log_dg_action($dg_id, 'catalogue_edit', "Catégorie modifiée : #$id « $name »", 'catalogue');
                $flash = ['type'=>'success','msg'=>"Catégorie « ".htmlspecialchars($name)." » mise à jour."];
            }
        }

        // ── Basculer actif/inactif ───────────────────────────────────────────
        elseif ($action === 'toggle' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE categories SET is_active = 1 - is_active, updated_at=NOW() WHERE id=?")
                ->execute([$id]);
            log_dg_action($dg_id, 'catalogue_toggle', "Catégorie #$id : état basculé", 'catalogue');
            $flash = ['type'=>'success','msg'=>'Statut de la catégorie basculé.'];
        }

        // ── Supprimer ────────────────────────────────────────────────────────
        elseif ($action === 'delete' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];

            // Vérifier enfants
            $kids = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id=?");
            $kids->execute([$id]);
            if ((int)$kids->fetchColumn() > 0) {
                $flash = ['type'=>'danger','msg'=>'Impossible de supprimer : cette catégorie contient des sous-catégories. Supprimez-les d\'abord.'];
            } else {
                // Vérifier produits liés
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
                    log_dg_action($dg_id, 'catalogue_delete', "Catégorie supprimée : #$id « $cat_name »", 'catalogue');
                    $flash = ['type'=>'success','msg'=>"Catégorie « ".htmlspecialchars($cat_name)." » supprimée."];
                }
            }
        }

    } catch (PDOException $e) {
        error_log('catalogue DG: '.$e->getMessage());
        if ((int)$e->getCode() === 23000) {
            $flash = ['type'=>'danger','msg'=>'Ce slug ou ce nom existe déjà. Choisissez un autre nom.'];
        } else {
            $flash = ['type'=>'danger','msg'=>'Erreur base de données. Veuillez réessayer.'];
        }
    }
}

// ─── Lecture des données ──────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all | active | inactive | root | sub

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
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

// Catégories racines pour le dropdown parent
$roots = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

// Stats globales
$total_cats   = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$active_cats  = $pdo->query("SELECT COUNT(*) FROM categories WHERE is_active=1")->fetchColumn();
$root_cats    = $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Page Catalogue ─────────────────────────────────────────────── */
.page-header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.page-title-block h2 { margin:0; font-size:1.4rem; font-weight:700; }
.page-title-block p  { margin:4px 0 0; color:#6b7280; font-size:.85rem; }

.stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:22px; }
.stat-card { background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.stat-card .val { font-size:1.8rem; font-weight:700; color:#1d4ed8; }
.stat-card .lbl { font-size:.78rem; color:#6b7280; margin-top:2px; }

.filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:18px; }
.filter-bar input[type=text] { padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; flex:1; min-width:200px; font-size:.9rem; }
.filter-bar select { padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:.9rem; }
.btn-add { background:#1d4ed8; color:#fff; border:none; padding:9px 18px; border-radius:8px; font-size:.88rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-add:hover { background:#1e40af; }

.cat-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.08); }
.cat-table th { background:#f8fafc; font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; padding:10px 14px; text-align:left; border-bottom:1px solid #e5e7eb; }
.cat-table td { padding:10px 14px; border-bottom:1px solid #f1f5f9; font-size:.875rem; vertical-align:middle; color:#1f2937 !important; }
.cat-table td code { color:#334155 !important; }
.cat-table td em { color:#9ca3af !important; }
.cat-table tr:last-child td { border-bottom:none; }
.cat-table tr:hover td { background:#fafafa; }

.row-sub td:first-child { padding-left:30px; }
.indent-icon { color:#9ca3af; margin-right:6px; }

.badge-active   { background:#dcfce7; color:#15803d; padding:3px 9px; border-radius:20px; font-size:.75rem; font-weight:600; }
.badge-inactive { background:#f3f4f6; color:#6b7280; padding:3px 9px; border-radius:20px; font-size:.75rem; font-weight:600; }
.badge-root     { background:#dbeafe; color:#1d4ed8; padding:3px 9px; border-radius:20px; font-size:.72rem; }
.badge-sub      { background:#fef3c7; color:#92400e; padding:3px 9px; border-radius:20px; font-size:.72rem; }

.action-btns { display:flex; gap:5px; }
.btn-sm { padding:5px 10px; border:none; border-radius:6px; cursor:pointer; font-size:.78rem; font-weight:600; }
.btn-edit   { background:#fef3c7; color:#92400e; }
.btn-toggle { background:#e0e7ff; color:#3730a3; }
.btn-del    { background:#fee2e2; color:#991b1b; }
.btn-sm:hover { opacity:.8; }

/* Modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:14px; padding:28px 30px; max-width:540px; width:95%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-box h3 { margin:0 0 18px; font-size:1.15rem; font-weight:700; border-bottom:1px solid #e5e7eb; padding-bottom:12px; }
.form-row { margin-bottom:14px; }
.form-row label { display:block; font-size:.83rem; font-weight:600; margin-bottom:5px; color:#374151; }
.form-row input, .form-row textarea, .form-row select {
    width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:8px;
    font-size:.88rem; box-sizing:border-box;
}
.form-row textarea { resize:vertical; min-height:70px; }
.form-row small { color:#6b7280; font-size:.75rem; margin-top:4px; display:block; }
.form-actions { display:flex; gap:10px; margin-top:20px; justify-content:flex-end; }
.btn-save   { background:#1d4ed8; color:#fff; border:none; padding:9px 20px; border-radius:8px; font-weight:600; cursor:pointer; }
.btn-cancel { background:#f3f4f6; color:#374151; border:none; padding:9px 16px; border-radius:8px; cursor:pointer; }
.checkbox-row { display:flex; align-items:center; gap:8px; font-size:.88rem; }

.flash { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:.875rem; font-weight:500; }
.flash.success { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
.flash.danger  { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

.empty-state { text-align:center; padding:50px 20px; color:#6b7280; }
.empty-state i { font-size:3rem; margin-bottom:12px; opacity:.4; display:block; }
</style>

<?php if ($flash['type']): ?>
<div class="flash <?= $flash['type'] ?>">
    <i class="fas <?= $flash['type']==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<div class="page-header-row">
    <div class="page-title-block">
        <h2><i class="fas fa-th-large" style="color:#1d4ed8"></i> Gestionnaire de Catalogue</h2>
        <p>Gérez toutes les catégories de produits — seuls le DG et le Super Admin peuvent modifier ce catalogue.</p>
    </div>
    <button class="btn-add" onclick="openModal()">
        <i class="fas fa-plus"></i> Nouvelle Catégorie
    </button>
</div>

<!-- Stats -->
<div class="stat-row">
    <div class="stat-card"><div class="val"><?= $total_cats ?></div><div class="lbl">Total catégories</div></div>
    <div class="stat-card"><div class="val" style="color:#15803d"><?= $active_cats ?></div><div class="lbl">Actives</div></div>
    <div class="stat-card"><div class="val" style="color:#7c3aed"><?= $root_cats ?></div><div class="lbl">Catégories racines</div></div>
    <div class="stat-card"><div class="val" style="color:#b45309"><?= $total_cats - $root_cats ?></div><div class="lbl">Sous-catégories</div></div>
</div>

<!-- Filtres -->
<form method="GET" class="filter-bar">
    <input type="text" name="q" placeholder="Rechercher par nom, slug…" value="<?= htmlspecialchars($search) ?>">
    <select name="filter">
        <option value="all"      <?= $filter==='all'      ?'selected':'' ?>>Toutes</option>
        <option value="active"   <?= $filter==='active'   ?'selected':'' ?>>Actives</option>
        <option value="inactive" <?= $filter==='inactive' ?'selected':'' ?>>Inactives</option>
        <option value="root"     <?= $filter==='root'     ?'selected':'' ?>>Racines seulement</option>
        <option value="sub"      <?= $filter==='sub'      ?'selected':'' ?>>Sous-catégories</option>
    </select>
    <button type="submit" class="btn-sm btn-edit" style="padding:9px 14px">
        <i class="fas fa-search"></i> Filtrer
    </button>
    <?php if ($search || $filter!=='all'): ?>
        <a href="catalogue.php" class="btn-sm btn-cancel" style="padding:9px 14px; text-decoration:none;">
            <i class="fas fa-times"></i> Effacer
        </a>
    <?php endif; ?>
</form>

<!-- Résultats -->
<?php if (empty($categories)): ?>
<div class="empty-state">
    <i class="fas fa-folder-open"></i>
    Aucune catégorie trouvée<?= $search ? " pour « ".htmlspecialchars($search)." »" : '' ?>.
</div>
<?php else: ?>
<table class="cat-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Slug</th>
            <th>Type</th>
            <th>Parent</th>
            <th>Enfants</th>
            <th>Produits</th>
            <th>Ordre</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $cat):
        $is_sub = !is_null($cat['parent_id']);
    ?>
        <tr class="<?= $is_sub ? 'row-sub' : 'row-root' ?>">
            <td><?= $cat['id'] ?></td>
            <td>
                <?php if ($is_sub): ?>
                    <i class="fas fa-level-up-alt fa-rotate-90 indent-icon"></i>
                <?php else: ?>
                    <i class="fas fa-folder" style="color:#1d4ed8;margin-right:6px"></i>
                <?php endif; ?>
                <?= htmlspecialchars($cat['name']) ?>
            </td>
            <td><code style="font-size:.78rem;background:#f1f5f9;padding:2px 6px;border-radius:4px"><?= htmlspecialchars($cat['slug']) ?></code></td>
            <td>
                <?php if ($is_sub): ?>
                    <span class="badge-sub">Sous-catégorie</span>
                <?php else: ?>
                    <span class="badge-root">Racine</span>
                <?php endif; ?>
            </td>
            <td><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<em style="color:#9ca3af">—</em>' ?></td>
            <td><span style="font-weight:600;color:<?= $cat['children_count']>0?'#1d4ed8':'#9ca3af' ?>"><?= $cat['children_count'] ?></span></td>
            <td><span style="font-weight:600;color:<?= $cat['product_count']>0?'#15803d':'#9ca3af' ?>"><?= $cat['product_count'] ?></span></td>
            <td><?= $cat['display_order'] ?></td>
            <td>
                <?php if ($cat['is_active']): ?>
                    <span class="badge-active">Actif</span>
                <?php else: ?>
                    <span class="badge-inactive">Inactif</span>
                <?php endif; ?>
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
                    <form method="POST" style="display:inline" onsubmit="return true">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn-sm btn-toggle" title="<?= $cat['is_active']?'Désactiver':'Activer' ?>">
                            <i class="fas <?= $cat['is_active']?'fa-eye-slash':'fa-eye' ?>"></i>
                        </button>
                    </form>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Supprimer « <?= htmlspecialchars(addslashes($cat['name'])) ?> » ?\n\nCette action est irréversible.')">
                        <?= csrf_field() ?>
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
<p style="font-size:.8rem;color:#9ca3af;margin-top:10px">
    <?= count($categories) ?> catégorie(s) affichée(s)
    <?= $search ? " — recherche : « ".htmlspecialchars($search)." »" : '' ?>
</p>
<?php endif; ?>

<!-- ── Modal Ajouter / Modifier ───────────────────────────────────────────────── -->
<div id="catModal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modalTitle"><i class="fas fa-folder-plus"></i> Nouvelle Catégorie</h3>
        <form method="POST" id="catForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id"     id="catId">

            <div class="form-row">
                <label for="catName">Nom <span style="color:red">*</span></label>
                <input type="text" name="name" id="catName" required placeholder="Ex : Vêtements Femme">
            </div>

            <div class="form-row">
                <label for="catSlug">Slug (généré automatiquement)</label>
                <input type="text" name="slug" id="catSlug" placeholder="vetements-femme" readonly
                       style="background:#f9fafb;color:#6b7280">
            </div>

            <div class="form-row">
                <label for="catDesc">Description</label>
                <textarea name="description" id="catDesc" placeholder="Description courte de la catégorie…"></textarea>
            </div>

            <div class="form-row">
                <label for="catParent">Catégorie Parent</label>
                <select name="parent_id" id="catParent">
                    <option value="">— Aucune (catégorie racine) —</option>
                    <?php foreach ($roots as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Laissez vide pour créer une catégorie de premier niveau.</small>
            </div>

            <div class="form-row">
                <label for="catIcon">Icône (fichier SVG)</label>
                <input type="text" name="icon" id="catIcon" placeholder="Ex : hc_mode.svg">
                <small>Nom du fichier SVG dans assets/img/</small>
            </div>

            <div class="form-row">
                <label for="catOrder">Ordre d'affichage</label>
                <input type="number" name="display_order" id="catOrder" value="0" min="0" max="9999">
            </div>

            <div class="form-row">
                <div class="checkbox-row">
                    <input type="checkbox" name="is_active" id="catActive" value="1" checked>
                    <label for="catActive" style="margin:0;font-weight:400">Catégorie active (visible sur le site)</label>
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
// Auto-slug depuis le nom
document.getElementById('catName').addEventListener('input', function() {
    const s = this.value.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g,'')
        .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    document.getElementById('catSlug').value = s;
});

function openModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-folder-plus"></i> Nouvelle Catégorie';
    document.getElementById('formAction').value = 'add';
    document.getElementById('catId').value = '';
    document.getElementById('catForm').reset();
    document.getElementById('catActive').checked = true;
    document.getElementById('catModal').classList.add('open');
}

function editModal(c) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen"></i> Modifier la Catégorie';
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

function closeModal() {
    document.getElementById('catModal').classList.remove('open');
}

// Fermer en cliquant à l'extérieur
document.getElementById('catModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
