<?php
/**
 * Dashboard Stock Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Dashboard Stock';
$current_page = 'dashboard';

// Récupérer les statistiques
$stats = get_stock_statistics();

// Mouvements récents
try {
    $stmt = $pdo->query("
        SELECT 
            sm.*,
            p.name as product_name,
            p.sku as product_sku
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        ORDER BY sm.created_at DESC
        LIMIT 10
    ");
    $recent_movements = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_movements = [];
}

// Produits en alerte
try {
    $stmt = $pdo->query("
        SELECT 
            id, name, sku, stock, stock_threshold, price
        FROM products
        WHERE stock <= stock_threshold AND is_active = 1
        ORDER BY stock ASC
        LIMIT 5
    ");
    $low_stock_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $low_stock_products = [];
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
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.1), rgba(0, 200, 100, 0.1));
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 255, 136, 0.3);
}

.stat-card.cyan {
    background: linear-gradient(135deg, rgba(0, 217, 255, 0.1), rgba(0, 150, 199, 0.1));
}

.stat-card.gold {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(218, 165, 32, 0.1));
}

.stat-card.red {
    background: linear-gradient(135deg, rgba(255, 0, 0, 0.1), rgba(200, 0, 0, 0.1));
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

.dashboard-widgets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
}

.movement-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 12px;
}

.movement-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.movement-icon.in {
    background: rgba(0, 255, 136, 0.2);
    color: var(--neon-green);
}

.movement-icon.out {
    background: rgba(255, 0, 0, 0.2);
    color: #ff0000;
}

.movement-icon.adjust {
    background: rgba(0, 217, 255, 0.2);
    color: var(--neon-cyan);
}

.alert-item {
    padding: 12px;
    background: rgba(255, 0, 0, 0.05);
    border-left: 3px solid #ff0000;
    border-radius: 4px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-home"></i> Dashboard Stock</h1>
</div>

<!-- Actions Rapides -->
<div class="quick-actions">
    <a href="movement-add.php" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i>
        Nouveau Mouvement
    </a>
    <a href="inventory.php" class="btn btn-secondary">
        <i class="fas fa-boxes"></i>
        Voir Inventaire
    </a>
    <a href="movements-list.php" class="btn btn-secondary">
        <i class="fas fa-exchange-alt"></i>
        Historique
    </a>
    <a href="reports.php" class="btn btn-secondary">
        <i class="fas fa-chart-line"></i>
        Rapports
    </a>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Valeur Totale du Stock</div>
        <div class="stat-value" style="color: var(--neon-green);">
            <?php echo number_format($stats['total_value'], 0, ',', ' '); ?> HTG
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Inventaire total valorisé
        </div>
    </div>
    
    <div class="stat-card cyan">
        <div class="stat-label">Produits en Stock</div>
        <div class="stat-value" style="color: var(--neon-cyan);">
            <?php echo number_format($stats['total_products']); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Références actives
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-label">Entrées ce Mois</div>
        <div class="stat-value" style="color: var(--neon-green);">
            +<?php echo number_format($stats['entries_month']); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Unités ajoutées
        </div>
    </div>
    
    <div class="stat-card red">
        <div class="stat-label">Sorties ce Mois</div>
        <div class="stat-value" style="color: #ff0000;">
            -<?php echo number_format($stats['exits_month']); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Unités retirées
        </div>
    </div>
    
    <div class="stat-card gold">
        <div class="stat-label">Alertes Stock</div>
        <div class="stat-value" style="color: var(--neon-gold);">
            <?php echo number_format($stats['stock_alerts']); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Produits en alerte
        </div>
    </div>
</div>

<!-- Widgets -->
<div class="dashboard-widgets">
    <!-- Mouvements récents -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exchange-alt"></i> Mouvements Récents</h2>
            <a href="movements-list.php" class="btn btn-secondary btn-sm">
                Voir Tout
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($recent_movements)): ?>
                <p class="text-center" style="color: var(--text-secondary); padding: 30px;">
                    Aucun mouvement récent
                </p>
            <?php else: ?>
                <?php foreach ($recent_movements as $movement): ?>
                    <div class="movement-item">
                        <div class="movement-icon <?php echo $movement['type']; ?>">
                            <?php 
                            $icons = [
                                'in' => 'fa-arrow-down',
                                'out' => 'fa-arrow-up',
                                'adjust' => 'fa-sync-alt'
                            ];
                            echo '<i class="fas ' . ($icons[$movement['type']] ?? 'fa-exchange-alt') . '"></i>';
                            ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 3px;">
                                <?php echo htmlspecialchars($movement['product_name']); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($movement['product_sku']); ?> • 
                                <?php 
                                $type_labels = [
                                    'in' => 'Entrée',
                                    'out' => 'Sortie',
                                    'adjust' => 'Ajustement'
                                ];
                                echo $type_labels[$movement['type']] ?? $movement['type'];
                                ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: var(--neon-green);">
                                <?php echo $movement['type'] === 'out' ? '-' : '+'; ?>
                                <?php echo number_format($movement['quantity']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-secondary);">
                                <?php echo date('d/m H:i', strtotime($movement['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Alertes stock -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Alertes Stock</h2>
            <span class="badge badge-out"><?php echo count($low_stock_products); ?></span>
        </div>
        <div class="card-body">
            <?php if (empty($low_stock_products)): ?>
                <p class="text-center" style="color: var(--text-secondary); padding: 30px;">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: var(--neon-green); display: block; margin-bottom: 15px;"></i>
                    Tous les stocks sont OK !
                </p>
            <?php else: ?>
                <?php foreach ($low_stock_products as $product): ?>
                    <div class="alert-item">
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 3px;">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                SKU: <?php echo htmlspecialchars($product['sku']); ?>
                            </div>
                        </div>
                        <div style="padding: 4px 12px; background: rgba(255, 0, 0, 0.2); color: #ff0000; border-radius: 12px; font-size: 12px; font-weight: 600;">
                            <?php echo $product['stock']; ?> / <?php echo $product['stock_threshold']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>