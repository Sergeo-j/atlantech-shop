<?php
/**
 * Liste des Mouvements de Stock
 * Stock Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Mouvements de Stock';
$current_page = 'movements';

// Pagination et filtres
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

$filters = [
    'type' => $_GET['type'] ?? '',
    'product_id' => intval($_GET['product_id'] ?? 0),
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Récupérer les mouvements
$result = get_stock_movements($page, $per_page, $filters);
$movements = $result['movements'];
$total = $result['total'];
$total_pages = $result['pages'];

// Produits pour filtre
$products = get_active_products();

include __DIR__ . '/../includes/header.php';
?>

<style>
.filters-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr) auto;
    gap: 15px;
    margin-bottom: 25px;
}

.movement-row {
    display: grid;
    grid-template-columns: 80px 2fr 1fr 100px 150px 120px 150px;
    gap: 15px;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s;
}

.movement-row:hover {
    background: rgba(255, 255, 255, 0.02);
}

.movement-type-badge {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
}

.movement-type-badge.in {
    background: rgba(0, 255, 136, 0.2);
    border: 2px solid var(--neon-green);
    color: var(--neon-green);
}

.movement-type-badge.out {
    background: rgba(255, 0, 0, 0.2);
    border: 2px solid #ff0000;
    color: #ff0000;
}

.movement-type-badge.adjust {
    background: rgba(0, 217, 255, 0.2);
    border: 2px solid var(--neon-cyan);
    color: var(--neon-cyan);
}

.movement-type-badge i {
    font-size: 20px;
    margin-bottom: 5px;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
}

.page-btn {
    padding: 8px 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s;
}

.page-btn:hover {
    background: rgba(0, 255, 136, 0.1);
    border-color: var(--neon-green);
    color: var(--neon-green);
}

.page-btn.active {
    background: var(--neon-green);
    color: #000;
    border-color: var(--neon-green);
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 1200px) {
    .movement-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-exchange-alt"></i> Mouvements de Stock</h1>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="movement-add.php" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i>
        Nouveau Mouvement
    </a>
    <a href="inventory.php" class="btn btn-secondary">
        <i class="fas fa-boxes"></i>
        Voir Inventaire
    </a>
    <a href="reports.php" class="btn btn-secondary">
        <i class="fas fa-chart-line"></i>
        Rapports
    </a>
</div>

<!-- Filtres -->
<div class="card">
    <form method="GET" class="filters-grid">
        <div class="form-group" style="margin: 0;">
            <select name="type" class="form-select">
                <option value="">Tous types</option>
                <option value="in" <?php echo $filters['type'] === 'in' ? 'selected' : ''; ?>>Entrées</option>
                <option value="out" <?php echo $filters['type'] === 'out' ? 'selected' : ''; ?>>Sorties</option>
                <option value="adjust" <?php echo $filters['type'] === 'adjust' ? 'selected' : ''; ?>>Ajustements</option>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <input 
                type="date" 
                name="date_from" 
                class="form-input"
                value="<?php echo htmlspecialchars($filters['date_from']); ?>"
                placeholder="Date début"
            >
        </div>
        
        <div class="form-group" style="margin: 0;">
            <input 
                type="date" 
                name="date_to" 
                class="form-input"
                value="<?php echo htmlspecialchars($filters['date_to']); ?>"
                placeholder="Date fin"
            >
        </div>
        
        <div class="form-group" style="margin: 0;">
            <select name="product_id" class="form-select">
                <option value="">Tous produits</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>" 
                        <?php echo $filters['product_id'] == $product['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Filtrer
        </button>
    </form>
</div>

<!-- Liste des mouvements -->
<div class="card">
    <div class="card-header">
        <h2>
            <i class="fas fa-list"></i>
            Historique (<?php echo number_format($total); ?> mouvements)
        </h2>
    </div>
    
    <?php if (empty($movements)): ?>
        <div style="text-align: center; padding: 60px; color: var(--text-secondary);">
            <i class="fas fa-inbox" style="font-size: 64px; display: block; margin-bottom: 20px;"></i>
            <p>Aucun mouvement trouvé</p>
        </div>
    <?php else: ?>
        <div style="padding: 20px 0;">
            <?php foreach ($movements as $movement): ?>
                <?php
                $type_labels = [
                    'in' => 'ENTRÉE',
                    'out' => 'SORTIE',
                    'adjust' => 'AJUST'
                ];
                $type_icons = [
                    'in' => 'fa-arrow-down',
                    'out' => 'fa-arrow-up',
                    'adjust' => 'fa-sync-alt'
                ];
                ?>
                <div class="movement-row">
                    <div class="movement-type-badge <?php echo $movement['type']; ?>">
                        <i class="fas <?php echo $type_icons[$movement['type']] ?? 'fa-exchange-alt'; ?>"></i>
                        <?php echo $type_labels[$movement['type']] ?? $movement['type']; ?>
                    </div>
                    
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 5px;">
                            <?php echo htmlspecialchars($movement['product_name']); ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            SKU: <code style="background: rgba(0,217,255,0.1); padding: 2px 6px; border-radius: 4px; color: var(--neon-cyan);">
                                <?php echo htmlspecialchars($movement['product_sku']); ?>
                            </code>
                        </div>
                        <?php if ($movement['reason']): ?>
                            <div style="font-size: 11px; color: var(--text-secondary); margin-top: 3px;">
                                <i class="fas fa-comment"></i>
                                <?php echo htmlspecialchars($movement['reason']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 3px;">Quantité</div>
                        <div style="font-size: 20px; font-weight: 700; color: <?php echo $movement['type'] === 'out' ? '#ff0000' : 'var(--neon-green)'; ?>">
                            <?php echo $movement['type'] === 'out' ? '-' : '+'; ?>
                            <?php echo number_format($movement['quantity']); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 3px;">Prix Unit.</div>
                        <div style="font-weight: 600;">
                            <?php echo number_format($movement['unit_price'], 2); ?> HTG
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 3px;">Valeur Totale</div>
                        <div style="font-weight: 700; font-size: 16px; color: var(--neon-cyan);">
                            <?php echo number_format($movement['total_value'], 2); ?> HTG
                        </div>
                    </div>
                    
                    <div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 3px;">Par</div>
                        <div style="font-size: 13px; color: var(--text-primary);">
                            <?php echo htmlspecialchars($movement['admin_name'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    
                    <div style="text-align: right;">
                        <div style="font-size: 13px; color: var(--text-primary); margin-bottom: 3px;">
                            <?php echo date('d/m/Y', strtotime($movement['created_at'])); ?>
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary);">
                            <?php echo date('H:i', strtotime($movement['created_at'])); ?>
                        </div>
                        <?php if ($movement['reference']): ?>
                            <div style="font-size: 10px; color: var(--text-secondary); margin-top: 3px;">
                                Ref: <?php echo htmlspecialchars($movement['reference']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>" class="page-btn">
                        <i class="fas fa-chevron-left"></i>
                        Précédent
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>" 
                       class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>" class="page-btn">
                        Suivant
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
