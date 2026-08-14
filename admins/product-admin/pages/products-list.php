<?php
/**
 * Liste des Produits
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Liste des Produits';
$current_page = 'products';

// Paramètres de recherche et pagination
$search = clean_input($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

// Obtenir les produits (avec filtre stock)
$result = get_all_products($page, $per_page, $search, $category_filter, $status_filter, $stock_filter);
$products = $result['products'];
$total = $result['total'];
$total_pages = $result['pages'];

// Obtenir les catégories pour le filtre
$categories = get_all_categories();

// Messages de succès
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = 'Produit ajouté avec succès';
            break;
        case 'updated':
            $success_message = 'Produit mis à jour avec succès';
            break;
        case 'deleted':
            $success_message = 'Produit supprimé avec succès';
            break;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.filters-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
    gap: 15px;
    align-items: end;
    margin-bottom: 25px;
}

.table-container {
    overflow-x: auto;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: rgba(138, 43, 226, 0.1);
}

th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--neon-cyan);
    border-bottom: 2px solid var(--border-color);
    font-size: 13px;
    text-transform: uppercase;
}

td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

tr:hover {
    background: rgba(255, 255, 255, 0.02);
}

.product-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.product-image-placeholder {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.product-sku {
    background: rgba(0, 217, 255, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    color: var(--neon-cyan);
    font-size: 12px;
    font-family: monospace;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-badge.active {
    background: rgba(0, 255, 136, 0.1);
    color: var(--neon-green);
    border: 1px solid var(--neon-green);
}

.status-badge.inactive {
    background: rgba(255, 0, 0, 0.1);
    color: #ff0000;
    border: 1px solid #ff0000;
}

.status-badge.low-stock {
    background: rgba(255, 215, 0, 0.1);
    color: var(--neon-gold);
    border: 1px solid var(--neon-gold);
}

.status-badge.out-of-stock {
    background: rgba(255, 0, 0, 0.1);
    color: #ff0000;
    border: 1px solid #ff0000;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
}

.action-btn.view:hover {
    background: rgba(0, 217, 255, 0.1);
    border-color: var(--neon-cyan);
    color: var(--neon-cyan);
}

.action-btn.edit:hover {
    background: rgba(255, 215, 0, 0.1);
    border-color: var(--neon-gold);
    color: var(--neon-gold);
}

.action-btn.delete:hover {
    background: rgba(255, 0, 0, 0.1);
    border-color: #ff0000;
    color: #ff0000;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.pagination-btn {
    padding: 8px 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.pagination-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--neon-cyan);
}

.pagination-btn.active {
    background: linear-gradient(135deg, var(--neon-purple), var(--neon-cyan));
    border: none;
    color: white;
}

.featured-badge {
    font-size: 11px;
    padding: 2px 8px;
    background: rgba(255, 215, 0, 0.1);
    color: var(--neon-gold);
    border-radius: 8px;
    display: inline-block;
    margin-top: 5px;
}

@media (max-width: 1200px) {
    .filters-form {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .filters-form {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-box"></i> Liste des Produits</h1>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Barre de recherche et filtres -->
<div class="card">
    <form method="GET" action="" class="filters-form">
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Rechercher</label>
            <input 
                type="text" 
                name="search" 
                class="form-input"
                placeholder="Nom, SKU, description..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Catégorie</label>
            <select name="category" class="form-select">
                <option value="">Toutes</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label class="form-label">Stock</label>
            <select name="stock" class="form-select">
                <option value="">Tous</option>
                <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>En stock</option>
                <option value="low_stock" <?php echo $stock_filter === 'low_stock' ? 'selected' : ''; ?>>Stock bas</option>
                <option value="out_of_stock" <?php echo $stock_filter === 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-top: 24px;">
            <i class="fas fa-filter"></i>
            Filtrer
        </button>
        
        <a href="products-list.php" class="btn btn-secondary" style="margin-top: 24px;">
            <i class="fas fa-redo"></i>
        </a>
    </form>
</div>

<!-- Liste des produits -->
<div class="card">
    <div class="card-header">
        <h2>
            <i class="fas fa-list"></i>
            Produits (<?php echo number_format($total); ?>)
        </h2>
        <a href="product-add.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i>
            Ajouter un Produit
        </a>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Image</th>
                    <th>Produit</th>
                    <th>SKU</th>
                    <th>Catégorie</th>
                    <th>Prix</th>
                    <th>Stock</th>
                    <th>Vendus</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th style="width: 150px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                            <p>Aucun produit trouvé</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="product-image">
                                <?php else: ?>
                                    <div class="product-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                <?php if ($product['is_featured']): ?>
                                    <span class="featured-badge">
                                        <i class="fas fa-star"></i> Featured
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="product-sku">
                                    <?php echo htmlspecialchars($product['sku']); ?>
                                </code>
                            </td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--neon-green);">
                                    <?php echo number_format($product['price'], 2); ?> HTG
                                </div>
                                <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                    <div style="font-size: 12px; color: var(--text-secondary); text-decoration: line-through;">
                                        <?php echo number_format($product['old_price'], 2); ?> HTG
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $stock_class = 'active';
                                if ($product['stock'] == 0) {
                                    $stock_class = 'out-of-stock';
                                } elseif ($product['stock'] <= $product['stock_threshold']) {
                                    $stock_class = 'low-stock';
                                }
                                ?>
                                <span class="status-badge <?php echo $stock_class; ?>">
                                    <?php echo number_format($product['stock']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: var(--neon-cyan); font-weight: 600;">
                                    <i class="fas fa-shopping-cart"></i>
                                    <?php echo number_format($product['sold_count']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($product['is_active']): ?>
                                    <span class="status-badge active">
                                        <i class="fas fa-check-circle"></i> Actif
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge inactive">
                                        <i class="fas fa-times-circle"></i> Inactif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 13px; color: var(--text-secondary);">
                                    <?php echo date('d/m/Y', strtotime($product['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons" style="justify-content: center;">
                                    <a href="product-view.php?id=<?php echo $product['id']; ?>" 
                                       class="action-btn view" 
                                       title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="product-edit.php?id=<?php echo $product['id']; ?>" 
                                       class="action-btn edit" 
                                       title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button 
                                        onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')" 
                                        class="action-btn delete" 
                                        title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                <button onclick="changePage(<?php echo $page - 1; ?>)" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <button 
                    onclick="changePage(<?php echo $i; ?>)" 
                    class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"
                >
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <button onclick="changePage(<?php echo $page + 1; ?>)" class="pagination-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            <?php endif; ?>
            
            <span style="margin-left: 15px; color: var(--text-secondary); font-size: 14px;">
                Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
            </span>
        </div>
    <?php endif; ?>
</div>

<script>
function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

function deleteProduct(productId, productName) {
    if (confirm(`⚠️ ATTENTION ⚠️\n\nÊtes-vous sûr de vouloir supprimer le produit :\n"${productName}" ?\n\nCette action est irréversible !`)) {
        window.location.href = `product-delete.php?id=${productId}`;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
