<?php
// Définir les variables pour le header
$page_title = "Gestion des Produits";
$page_icon = "fa-boxes";

require_once 'config.php';
// requireLogin(); // Décommenter pour activer l'authentification

// Récupérer les statistiques
$stats_query = "SELECT 
    COUNT(*) as total_products,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
    SUM(CASE WHEN stock <= stock_threshold THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_products
FROM products";
$stats = $pdo->query($stats_query)->fetch();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtres
$where_conditions = [];
$params = [];

if (!empty($_GET['search'])) {
    $where_conditions[] = "(name LIKE :search OR sku LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['category'])) {
    $where_conditions[] = "category_id = :category";
    $params[':category'] = $_GET['category'];
}

if (!empty($_GET['brand'])) {
    $where_conditions[] = "brand_id = :brand";
    $params[':brand'] = $_GET['brand'];
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "is_active = :status";
    $params[':status'] = $_GET['status'];
}

if (!empty($_GET['stock_status'])) {
    if ($_GET['stock_status'] == 'low') {
        $where_conditions[] = "stock <= stock_threshold";
    } elseif ($_GET['stock_status'] == 'out') {
        $where_conditions[] = "stock = 0";
    }
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total pour la pagination
$count_query = "SELECT COUNT(*) as total FROM products $where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch()['total'];
$total_pages = ceil($total_products / $per_page);

// Récupérer les produits
$products_query = "SELECT 
    p.*,
    c.name as category_name,
    b.name as brand_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN brands b ON p.brand_id = b.id
$where_sql
ORDER BY p.created_at DESC
LIMIT :limit OFFSET :offset";

$products_stmt = $pdo->prepare($products_query);
foreach ($params as $key => $value) {
    $products_stmt->bindValue($key, $value);
}
$products_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$products_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$products_stmt->execute();
$products = $products_stmt->fetchAll();

// Récupérer les catégories pour le filtre
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

// Récupérer les marques pour le filtre
$brands = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();

// Messages de succès/erreur
$message = '';
$message_type = '';

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = 'danger';
    unset($_SESSION['error_message']);
}
?>
<?php 
// Inclure le header
include 'includes/admin-header.php'; 
// Inclure le sidebar
include 'includes/admin-sidebar.php';
?>

    <div class="admin-container" style="max-width: 100%; padding: 0;">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions Bar -->
        <div style="background: var(--white); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="product-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Ajouter un Produit
                </a>
                <button class="btn btn-success" onclick="exportData()">
                    <i class="fas fa-file-export"></i>
                    Exporter
                </button>
                <button class="btn btn-info" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i>
                    Actualiser
                </button>
            </div>
            <div style="color: var(--gray-600); font-size: 0.938rem;">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_products']); ?></h3>
                    <p>Total Produits</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['active_products']); ?></h3>
                    <p>Produits Actifs</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['low_stock']); ?></h3>
                    <p>Stock Faible</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['featured_products']); ?></h3>
                    <p>Produits Vedettes</p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="" id="filterForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search">
                            <i class="fas fa-search"></i>
                            Rechercher
                        </label>
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            placeholder="Nom, SKU, description..."
                            value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="category">
                            <i class="fas fa-tags"></i>
                            Catégorie
                        </label>
                        <select id="category" name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="brand">
                            <i class="fas fa-copyright"></i>
                            Marque
                        </label>
                        <select id="brand" name="brand">
                            <option value="">Toutes les marques</option>
                            <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"
                                <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="status">
                            <i class="fas fa-toggle-on"></i>
                            Statut
                        </label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="1" <?php echo (isset($_GET['status']) && $_GET['status'] == '1') ? 'selected' : ''; ?>>Actif</option>
                            <option value="0" <?php echo (isset($_GET['status']) && $_GET['status'] == '0') ? 'selected' : ''; ?>>Inactif</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="stock_status">
                            <i class="fas fa-warehouse"></i>
                            Stock
                        </label>
                        <select id="stock_status" name="stock_status">
                            <option value="">Tous</option>
                            <option value="low" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'low') ? 'selected' : ''; ?>>Stock Faible</option>
                            <option value="out" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'out') ? 'selected' : ''; ?>>Rupture</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Filtrer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i>
                        Réinitialiser
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="table-container">
            <div class="table-header">
                <h2>Liste des Produits (<?php echo number_format($total_products); ?>)</h2>
                <div class="table-actions">
                    <button class="btn btn-info btn-sm" onclick="exportData()">
                        <i class="fas fa-file-export"></i>
                        Exporter
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="bulkActions()">
                        <i class="fas fa-tasks"></i>
                        Actions Groupées
                    </button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                            </th>
                            <th>Image</th>
                            <th>Nom du Produit</th>
                            <th>SKU</th>
                            <th>Catégorie</th>
                            <th>Marque</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Vues</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="11" class="text-center" style="padding: 3rem;">
                                <i class="fas fa-box-open" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem; display: block;"></i>
                                <p style="color: var(--gray-500); font-size: 1.125rem;">Aucun produit trouvé</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>">
                                </td>
                                <td>
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="product-image" style="background: var(--gray-200); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: var(--gray-400);"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="product-name" title="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </div>
                                    <?php if ($product['is_featured']): ?>
                                        <span class="badge badge-warning" style="margin-top: 0.25rem;">
                                            <i class="fas fa-star"></i> Vedette
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($product['brand_name'] ?? 'N/A'); ?></td>
                                <td style="font-weight: 700; color: var(--primary-color);">
                                    <?php echo number_format($product['price'], 2); ?> HTG
                                    <?php if ($product['compare_price'] > $product['price']): ?>
                                        <br>
                                        <span style="text-decoration: line-through; color: var(--gray-400); font-size: 0.875rem;">
                                            <?php echo number_format($product['compare_price'], 2); ?> HTG
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $stock_class = 'badge-success';
                                    if ($product['stock'] == 0) {
                                        $stock_class = 'badge-danger';
                                        $stock_text = 'Rupture';
                                    } elseif ($product['stock'] <= $product['stock_threshold']) {
                                        $stock_class = 'badge-warning';
                                        $stock_text = $product['stock'] . ' (Faible)';
                                    } else {
                                        $stock_text = $product['stock'];
                                    }
                                    ?>
                                    <span class="badge <?php echo $stock_class; ?>">
                                        <?php echo $stock_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $product['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $product['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-eye" style="color: var(--gray-400);"></i>
                                    <?php echo number_format($product['views']); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="product-edit.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-info btn-sm btn-icon" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)" 
                                                class="btn btn-danger btn-sm btn-icon" 
                                                title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="<?php echo BASE_URL . 'product.php?id=' . $product['id']; ?>" 
                                           target="_blank"
                                           class="btn btn-secondary btn-sm btn-icon" 
                                           title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo http_build_query(array_diff_key($_GET, ['page' => ''])) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php include 'includes/admin-footer.php'; ?>

<!-- Page Specific Scripts -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> Confirmer la Suppression</h3>
                <button class="modal-close" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                <button class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i>
                    Supprimer
                </button>
            </div>
        </div>
    </div>

    <script>
        let deleteProductId = null;

        function deleteProduct(id) {
            deleteProductId = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteProductId = null;
        }

        function confirmDelete() {
            if (deleteProductId) {
                window.location.href = 'product-delete.php?id=' + deleteProductId;
            }
        }

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function resetFilters() {
            window.location.href = 'products-manager.php';
        }

        function exportData() {
            alert('Fonctionnalité d\'export à implémenter');
        }

        function bulkActions() {
            const selected = document.querySelectorAll('.product-checkbox:checked');
            if (selected.length === 0) {
                alert('Veuillez sélectionner au moins un produit');
                return;
            }
            alert('Actions groupées à implémenter pour ' + selected.length + ' produit(s)');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }

        // Auto-submit form on filter change
        document.querySelectorAll('#filterForm select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
    </script>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> Confirmer la Suppression</h3>
            <button class="modal-close" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Annuler</button>
            <button class="btn btn-danger" onclick="confirmDelete()">
                <i class="fas fa-trash"></i>
                Supprimer
            </button>
        </div>
    </div>
</div>

