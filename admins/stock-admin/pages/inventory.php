<?php
/**
 * Inventaire Complet
 * Stock Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Inventaire';
$current_page = 'inventory';

// Filtres
$search = clean_input($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'value_desc';

// Construire la requête
$where = ["p.is_active = 1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    // Échapper les wildcards SQL % et _ pour éviter les injections de pattern
    $search_safe = '%' . addcslashes($search, '%_') . '%';
    $params[] = $search_safe;
    $params[] = $search_safe;
}

if ($category_filter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = implode(" AND ", $where);

// Ordre de tri
$order_by = match($sort) {
    'value_asc' => "(p.stock * p.price) ASC",
    'value_desc' => "(p.stock * p.price) DESC",
    'stock_asc' => "p.stock ASC",
    'stock_desc' => "p.stock DESC",
    'name_asc' => "p.name ASC",
    'name_desc' => "p.name DESC",
    default => "(p.stock * p.price) DESC"
};

// Récupérer l'inventaire
try {
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.image,
            p.stock,
            p.price,
            (p.stock * p.price) as total_value,
            p.stock_threshold,
            c.name as category_name,
            b.name as brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE $where_clause
        ORDER BY $order_by
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();
    
    // Calculer la valeur totale
    $total_inventory_value = array_sum(array_column($inventory, 'total_value'));
    $total_items = count($inventory);
    
} catch (PDOException $e) {
    error_log("Erreur inventory: " . $e->getMessage());
    $inventory = [];
    $total_inventory_value = 0;
    $total_items = 0;
}

// Catégories pour filtre
try {
    $stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.summary-bar {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.1), rgba(0, 217, 255, 0.1));
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px 30px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.summary-item {
    text-align: center;
}

.summary-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--neon-green);
}

.summary-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.filters-bar {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 15px;
    margin-bottom: 20px;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.inventory-table th {
    padding: 15px;
    text-align: left;
    background: rgba(0, 255, 136, 0.1);
    border-bottom: 2px solid var(--border-color);
    font-size: 13px;
    text-transform: uppercase;
    color: var(--neon-green);
    cursor: pointer;
}

.inventory-table th:hover {
    background: rgba(0, 255, 136, 0.15);
}

.inventory-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

.inventory-table tr:hover {
    background: rgba(255, 255, 255, 0.02);
}

.product-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.product-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.product-placeholder {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
}

.stock-status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.stock-ok {
    background: rgba(0, 255, 136, 0.1);
    color: var(--neon-green);
    border: 1px solid var(--neon-green);
}

.stock-low {
    background: rgba(255, 215, 0, 0.1);
    color: var(--neon-gold);
    border: 1px solid var(--neon-gold);
}

.stock-out {
    background: rgba(255, 0, 0, 0.1);
    color: #ff0000;
    border: 1px solid #ff0000;
}

@media (max-width: 768px) {
    .filters-bar {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> Inventaire</h1>
</div>

<!-- Résumé -->
<div class="summary-bar">
    <div class="summary-item">
        <div class="summary-value"><?php echo number_format($total_items); ?></div>
        <div class="summary-label">Produits</div>
    </div>
    <div class="summary-item">
        <div class="summary-value"><?php echo number_format($total_inventory_value, 0, ',', ' '); ?> HTG</div>
        <div class="summary-label">Valeur Totale</div>
    </div>
    <div class="summary-item">
        <div class="summary-value">
            <?php echo number_format(array_sum(array_column($inventory, 'stock'))); ?>
        </div>
        <div class="summary-label">Unités Totales</div>
    </div>
</div>

<!-- Filtres -->
<div class="card">
    <form method="GET" class="filters-bar">
        <div class="form-group" style="margin: 0;">
            <input 
                type="text" 
                name="search" 
                class="form-input"
                placeholder="Rechercher un produit (nom ou SKU)..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>
        
        <div class="form-group" style="margin: 0;">
            <select name="category" class="form-select">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <select name="sort" class="form-select">
                <option value="value_desc" <?php echo $sort === 'value_desc' ? 'selected' : ''; ?>>Valeur (Haut → Bas)</option>
                <option value="value_asc" <?php echo $sort === 'value_asc' ? 'selected' : ''; ?>>Valeur (Bas → Haut)</option>
                <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock (Haut → Bas)</option>
                <option value="stock_asc" <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock (Bas → Haut)</option>
                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Nom (A → Z)</option>
                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Nom (Z → A)</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Filtrer
        </button>
    </form>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h2>
            <i class="fas fa-list"></i>
            Inventaire Complet (<?php echo number_format($total_items); ?>)
        </h2>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <i class="fas fa-print"></i>
                Imprimer
            </button>
            <a href="movement-add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i>
                Nouveau Mouvement
            </a>
        </div>
    </div>
    
    <?php if (empty($inventory)): ?>
        <div style="text-align: center; padding: 60px; color: var(--text-secondary);">
            <i class="fas fa-inbox" style="font-size: 64px; display: block; margin-bottom: 20px;"></i>
            <p>Aucun produit trouvé</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>SKU</th>
                        <th>Catégorie</th>
                        <th style="text-align: right;">Stock</th>
                        <th style="text-align: right;">Prix Unitaire</th>
                        <th style="text-align: right;">Valeur Totale</th>
                        <th style="text-align: center;">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <?php
                        $stock_status = 'stock-ok';
                        $status_text = 'OK';
                        
                        if ($item['stock'] == 0) {
                            $stock_status = 'stock-out';
                            $status_text = 'Rupture';
                        } elseif ($item['stock'] <= $item['stock_threshold']) {
                            $stock_status = 'stock-low';
                            $status_text = 'Faible';
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <?php if ($item['image']): ?>
                                        <img src="../../../uploads/products/<?php echo htmlspecialchars($item['image']); ?>"
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="product-thumb"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <?php else: ?>
                                        <div class="product-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </div>
                                        <?php if ($item['brand_name']): ?>
                                            <div style="font-size: 12px; color: var(--text-secondary);">
                                                <?php echo htmlspecialchars($item['brand_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code style="background: rgba(0, 217, 255, 0.1); padding: 4px 8px; border-radius: 4px; color: var(--neon-cyan); font-size: 12px;">
                                    <?php echo htmlspecialchars($item['sku']); ?>
                                </code>
                            </td>
                            <td>
                                <span style="color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span style="font-weight: 700; font-size: 16px; color: var(--neon-green);">
                                    <?php echo number_format($item['stock']); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span style="font-weight: 600;">
                                    <?php echo number_format($item['price'], 2); ?> HTG
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span style="font-weight: 700; font-size: 16px; color: var(--neon-cyan);">
                                    <?php echo number_format($item['total_value'], 2); ?> HTG
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="stock-status <?php echo $stock_status; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: rgba(0, 255, 136, 0.05); font-weight: 700;">
                        <td colspan="5" style="text-align: right; padding: 20px;">
                            TOTAL INVENTAIRE:
                        </td>
                        <td style="text-align: right; padding: 20px; font-size: 18px; color: var(--neon-green);">
                            <?php echo number_format($total_inventory_value, 2); ?> HTG
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
