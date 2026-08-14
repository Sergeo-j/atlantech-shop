<?php
/**
 * Dashboard Product Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Dashboard Produits';
$current_page = 'dashboard';

// Statistiques
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM products WHERE is_active = 1");
    $active_products = $stmt->fetch()['active'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM products WHERE stock = 0");
    $out_of_stock = $stmt->fetch()['out_of_stock'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM products WHERE stock > 0 AND stock <= stock_threshold");
    $low_stock = $stmt->fetch()['low_stock'];
    
    $stmt = $pdo->query("SELECT SUM(price * stock) as stock_value FROM products WHERE is_active = 1");
    $stock_value = $stmt->fetch()['stock_value'] ?? 0;
    
    $stmt = $pdo->query("SELECT id, name, image, price, sold_count, stock FROM products WHERE is_active = 1 ORDER BY sold_count DESC LIMIT 5");
    $bestsellers = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name, sku, stock, stock_threshold FROM products WHERE stock <= stock_threshold AND is_active = 1 ORDER BY stock ASC LIMIT 10");
    $stock_alerts = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) as new_products FROM products WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $new_products_month = $stmt->fetch()['new_products'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1");
    $total_categories = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    error_log("Erreur Dashboard: " . $e->getMessage());
    $total_products = $active_products = $out_of_stock = $low_stock = 0;
    $stock_value = 0;
    $bestsellers = $stock_alerts = [];
    $new_products_month = $total_categories = 0;
}

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
    background: linear-gradient(135deg, rgba(138, 43, 226, 0.1), rgba(75, 0, 130, 0.1));
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(138, 43, 226, 0.3);
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin: 10px 0;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-home"></i> Dashboard Produits</h1>
</div>

<!-- Actions Rapides -->
<div class="quick-actions">
    <a href="product-add.php" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i>
        Ajouter un Produit
    </a>
    <a href="products-list.php" class="btn btn-secondary">
        <i class="fas fa-list"></i>
        Liste des Produits
    </a>
    <a href="categories-list.php" class="btn btn-secondary">
        <i class="fas fa-folder"></i>
        Catégories
    </a>
    <a href="stock-alerts.php" class="btn btn-secondary">
        <i class="fas fa-exclamation-triangle"></i>
        Alertes Stock
    </a>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Produits</div>
        <div class="stat-value" style="color: var(--neon-purple);">
            <?php echo number_format($total_products); ?>
        </div>
        <div style="font-size: 13px;">
            <i class="fas fa-arrow-up" style="color: var(--neon-green);"></i>
            +<?php echo $new_products_month; ?> ce mois
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Produits Actifs</div>
        <div class="stat-value" style="color: var(--neon-cyan);">
            <?php echo number_format($active_products); ?>
        </div>
        <div style="font-size: 13px;">
            <?php echo $total_products > 0 ? round(($active_products / $total_products) * 100, 1) : 0; ?>% du total
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Valeur du Stock</div>
        <div class="stat-value" style="color: var(--neon-green);">
            <?php echo number_format($stock_value, 0, ',', ' '); ?> HTG
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Stock Faible</div>
        <div class="stat-value" style="color: var(--neon-gold);">
            <?php echo number_format($low_stock); ?>
        </div>
        <a href="stock-alerts.php" style="font-size: 13px; color: var(--neon-gold);">
            <i class="fas fa-arrow-right"></i> Voir alertes
        </a>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Rupture de Stock</div>
        <div class="stat-value" style="color: #ff0000;">
            <?php echo number_format($out_of_stock); ?>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Catégories</div>
        <div class="stat-value" style="color: var(--neon-purple);">
            <?php echo number_format($total_categories); ?>
        </div>
    </div>
</div>

<!-- Widgets -->
<div class="dashboard-widgets">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-fire"></i> Top 5 Produits</h2>
        </div>
        <div class="card-body">
            <?php if (empty($bestsellers)): ?>
                <p class="text-center">Aucune vente enregistrée</p>
            <?php else: ?>
                <?php foreach ($bestsellers as $product): ?>
                    <div class="product-item">
                        <?php if ($product['image']): ?>
                            <img src="/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="">
                        <?php else: ?>
                            <div class="product-img-placeholder"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-stats">
                                <span><i class="fas fa-shopping-cart"></i> <?php echo number_format($product['sold_count']); ?> vendus</span>
                                <span><i class="fas fa-box"></i> Stock: <?php echo number_format($product['stock']); ?></span>
                            </div>
                        </div>
                        <div class="product-price"><?php echo number_format($product['price'], 2); ?> HTG</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Alertes Stock (<?php echo count($stock_alerts); ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($stock_alerts)): ?>
                <p class="text-center"><i class="fas fa-check-circle" style="color: var(--neon-green);"></i> Tous les stocks sont OK !</p>
            <?php else: ?>
                <?php foreach ($stock_alerts as $alert): ?>
                    <div class="alert-item">
                        <div>
                            <div class="alert-name"><?php echo htmlspecialchars($alert['name']); ?></div>
                            <div class="alert-sku">SKU: <?php echo htmlspecialchars($alert['sku']); ?></div>
                        </div>
                        <div class="alert-badge">
                            <?php echo $alert['stock']; ?> / <?php echo $alert['stock_threshold']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
