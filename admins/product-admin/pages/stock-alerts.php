<?php
/**
 * Alertes Stock
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Alertes Stock';
$current_page = 'stock-alerts';

// Filtres
$alert_type = $_GET['type'] ?? 'all'; // all, out_of_stock, low_stock
$category_filter = intval($_GET['category'] ?? 0);
$search = clean_input($_GET['search'] ?? '');

// Construire la requête
$where = ["p.is_active = 1"];
$params = [];

// Filtre par type d'alerte
if ($alert_type === 'out_of_stock') {
    $where[] = "p.stock = 0";
} elseif ($alert_type === 'low_stock') {
    $where[] = "p.stock > 0 AND p.stock <= p.stock_threshold";
} else {
    // all = stock faible OU rupture
    $where[] = "p.stock <= p.stock_threshold";
}

// Filtre par catégorie
if ($category_filter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
}

// Filtre par recherche
if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(" AND ", $where);

// Récupérer les produits en alerte
try {
    $sql = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.image,
            p.stock,
            p.stock_threshold,
            p.price,
            p.sold_count,
            c.name as category_name,
            b.name as brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE $where_clause
        ORDER BY p.stock ASC, p.sold_count DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alerts = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock = 0 AND is_active = 1");
    $out_of_stock_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock > 0 AND stock <= stock_threshold AND is_active = 1");
    $low_stock_count = $stmt->fetch()['count'];
    
    $total_alerts = $out_of_stock_count + $low_stock_count;
    
    // Valeur des stocks faibles
    $stmt = $pdo->query("
        SELECT SUM(price * stock) as value 
        FROM products 
        WHERE stock > 0 AND stock <= stock_threshold AND is_active = 1
    ");
    $low_stock_value = $stmt->fetch()['value'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Erreur stock-alerts: " . $e->getMessage());
    $alerts = [];
    $out_of_stock_count = 0;
    $low_stock_count = 0;
    $total_alerts = 0;
    $low_stock_value = 0;
}

// Catégories pour le filtre
$categories = get_all_categories();

include __DIR__ . '/../includes/header.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s;
}

.stat-card.critical {
    background: linear-gradient(135deg, rgba(255, 0, 0, 0.1), rgba(200, 0, 0, 0.05));
    border-color: #ff0000;
}

.stat-card.warning {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(218, 165, 32, 0.05));
    border-color: var(--neon-gold);
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin: 10px 0;
}

.stat-label {
    font-size: 14px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.filters-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto auto;
    gap: 15px;
    margin-bottom: 25px;
}

.alert-item {
    display: grid;
    grid-template-columns: 80px 2fr 1fr 1fr 120px 120px 150px;
    gap: 15px;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s;
}

.alert-item:hover {
    background: rgba(255, 255, 255, 0.02);
}

.alert-item.critical {
    background: rgba(255, 0, 0, 0.05);
    border-left: 4px solid #ff0000;
}

.alert-item.warning {
    background: rgba(255, 215, 0, 0.05);
    border-left: 4px solid var(--neon-gold);
}

.product-image-small {
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

.stock-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stock-bar {
    flex: 1;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.stock-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s;
}

.stock-bar-fill.critical {
    background: linear-gradient(90deg, #ff0000, #cc0000);
}

.stock-bar-fill.warning {
    background: linear-gradient(90deg, var(--neon-gold), #cc8800);
}

.quick-action-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.priority-high {
    background: rgba(255, 0, 0, 0.1);
    border-color: #ff0000;
    color: #ff0000;
}

.priority-medium {
    background: rgba(255, 215, 0, 0.1);
    border-color: var(--neon-gold);
    color: var(--neon-gold);
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    border-bottom: 2px solid var(--border-color);
}

.filter-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.filter-tab:hover {
    color: var(--text-primary);
}

.filter-tab.active {
    color: var(--neon-cyan);
    border-bottom-color: var(--neon-cyan);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 64px;
    color: var(--neon-green);
    display: block;
    margin-bottom: 20px;
}

@media (max-width: 1200px) {
    .alert-item {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-exclamation-triangle"></i> Alertes Stock</h1>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Alertes</div>
        <div class="stat-value" style="color: var(--neon-purple);">
            <?php echo number_format($total_alerts); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Produits nécessitant attention
        </div>
    </div>
    
    <div class="stat-card critical">
        <div class="stat-label">Rupture de Stock</div>
        <div class="stat-value" style="color: #ff0000;">
            <?php echo number_format($out_of_stock_count); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Stock à 0 - Urgent
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-label">Stock Faible</div>
        <div class="stat-value" style="color: var(--neon-gold);">
            <?php echo number_format($low_stock_count); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            En dessous du seuil
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Valeur à Risque</div>
        <div class="stat-value" style="color: var(--neon-gold); font-size: 24px;">
            <?php echo number_format($low_stock_value, 0, ',', ' '); ?> HTG
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Stocks faibles
        </div>
    </div>
</div>

<!-- Tabs de filtre -->
<div class="filter-tabs">
    <a href="?type=all" class="filter-tab <?php echo $alert_type === 'all' ? 'active' : ''; ?>">
        <i class="fas fa-exclamation-triangle"></i>
        Toutes les Alertes (<?php echo number_format($total_alerts); ?>)
    </a>
    <a href="?type=out_of_stock" class="filter-tab <?php echo $alert_type === 'out_of_stock' ? 'active' : ''; ?>">
        <i class="fas fa-times-circle"></i>
        Rupture (<?php echo number_format($out_of_stock_count); ?>)
    </a>
    <a href="?type=low_stock" class="filter-tab <?php echo $alert_type === 'low_stock' ? 'active' : ''; ?>">
        <i class="fas fa-exclamation-circle"></i>
        Stock Faible (<?php echo number_format($low_stock_count); ?>)
    </a>
</div>

<!-- Filtres -->
<div class="card">
    <form method="GET" action="" class="filters-row">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($alert_type); ?>">
        
        <div class="form-group" style="margin: 0;">
            <input 
                type="text" 
                name="search" 
                class="form-input"
                placeholder="Rechercher un produit..."
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
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Filtrer
        </button>
        
        <a href="stock-alerts.php" class="btn btn-secondary">
            <i class="fas fa-redo"></i>
        </a>
    </form>
</div>

<!-- Liste des alertes -->
<div class="card">
    <div class="card-header">
        <h2>
            <i class="fas fa-list"></i>
            <?php 
            if ($alert_type === 'out_of_stock') {
                echo 'Produits en Rupture';
            } elseif ($alert_type === 'low_stock') {
                echo 'Produits à Stock Faible';
            } else {
                echo 'Toutes les Alertes';
            }
            ?>
            (<?php echo count($alerts); ?>)
        </h2>
        <a href="../products-list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Retour aux Produits
        </a>
    </div>
    
    <?php if (empty($alerts)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h3 style="font-size: 24px; margin-bottom: 10px; color: var(--text-primary);">
                Aucune alerte !
            </h3>
            <p>Tous les stocks sont OK 👍</p>
        </div>
    <?php else: ?>
        <div style="padding: 20px 0;">
            <?php foreach ($alerts as $product): ?>
                <?php 
                $is_out = $product['stock'] == 0;
                $percentage = $product['stock_threshold'] > 0 
                    ? min(100, ($product['stock'] / $product['stock_threshold']) * 100) 
                    : 0;
                ?>
                
                <div class="alert-item <?php echo $is_out ? 'critical' : 'warning'; ?>">
                    <div>
                        <?php if ($product['image']): ?>
                            <img src="/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-image-small">
                        <?php else: ?>
                            <div class="product-image-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 5px;">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            SKU: <code style="background: rgba(0,217,255,0.1); padding: 2px 6px; border-radius: 4px; color: var(--neon-cyan);">
                                <?php echo htmlspecialchars($product['sku']); ?>
                            </code>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 3px;">Catégorie</div>
                        <div style="font-size: 14px; color: var(--text-primary);">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 3px;">Prix</div>
                        <div style="font-size: 14px; font-weight: 600; color: var(--neon-green);">
                            <?php echo number_format($product['price'], 2); ?> HTG
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                            Stock: <?php echo $product['stock']; ?> / <?php echo $product['stock_threshold']; ?>
                        </div>
                        <div class="stock-indicator">
                            <div class="stock-bar">
                                <div class="stock-bar-fill <?php echo $is_out ? 'critical' : 'warning'; ?>" 
                                     style="width: <?php echo $percentage; ?>%;">
                                </div>
                            </div>
                            <span style="font-size: 11px; color: var(--text-secondary);">
                                <?php echo round($percentage); ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <span class="quick-action-badge <?php echo $is_out ? 'priority-high' : 'priority-medium'; ?>">
                            <i class="fas fa-<?php echo $is_out ? 'exclamation-circle' : 'exclamation-triangle'; ?>"></i>
                            <?php echo $is_out ? 'URGENT' : 'Attention'; ?>
                        </span>
                        <?php if ($product['sold_count'] > 0): ?>
                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 5px;">
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo number_format($product['sold_count']); ?> vendus
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                        <a href="product-view.php?id=<?php echo $product['id']; ?>" 
                           class="action-btn view" 
                           title="Voir">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="product-edit.php?id=<?php echo $product['id']; ?>" 
                           class="action-btn edit" 
                           title="Réapprovisionner">
                            <i class="fas fa-box"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
