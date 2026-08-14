<?php
/**
 * Rapports Stock
 * Stock Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Rapports';
$current_page = 'reports';

// Période sélectionnée
$period = $_GET['period'] ?? 'month';
$custom_from = $_GET['custom_from'] ?? '';
$custom_to = $_GET['custom_to'] ?? '';

// Définir les dates
switch ($period) {
    case 'today':
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        break;
    case 'week':
        $date_from = date('Y-m-d', strtotime('-7 days'));
        $date_to = date('Y-m-d');
        break;
    case 'month':
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
        break;
    case 'year':
        $date_from = date('Y-01-01');
        $date_to = date('Y-12-31');
        break;
    case 'custom':
        // Valider les dates personnalisées (format YYYY-MM-DD)
        $date_from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_from) && strtotime($custom_from))
            ? $custom_from : date('Y-m-01');
        $date_to = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_to) && strtotime($custom_to))
            ? $custom_to : date('Y-m-d');
        // S'assurer que date_from <= date_to
        if ($date_from > $date_to) {
            [$date_from, $date_to] = [$date_to, $date_from];
        }
        break;
    default:
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
}

// Statistiques de la période
try {
    // Mouvements par type
    $stmt = $pdo->prepare("
        SELECT 
            type,
            COUNT(*) as count,
            SUM(quantity) as total_quantity,
            SUM(total_value) as total_value
        FROM stock_movements
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY type
    ");
    $stmt->execute([$date_from, $date_to]);
    $movements_by_type = $stmt->fetchAll();
    
    // Top 10 produits mouvementés
    $stmt = $pdo->prepare("
        SELECT 
            p.name,
            p.sku,
            SUM(sm.quantity) as total_movements,
            COUNT(sm.id) as movement_count
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        WHERE DATE(sm.created_at) BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY total_movements DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $top_products = $stmt->fetchAll();
    
    // Valeur totale des mouvements par jour
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN type = 'in' THEN total_value ELSE 0 END) as entries_value,
            SUM(CASE WHEN type = 'out' THEN total_value ELSE 0 END) as exits_value
        FROM stock_movements
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_values = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur reports: " . $e->getMessage());
    $movements_by_type = [];
    $top_products = [];
    $daily_values = [];
}

// Calculer les totaux
$total_entries = 0;
$total_exits = 0;
$total_adjustments = 0;
$entries_value = 0;
$exits_value = 0;

foreach ($movements_by_type as $stat) {
    switch ($stat['type']) {
        case 'in':
            $total_entries = $stat['total_quantity'];
            $entries_value = $stat['total_value'];
            break;
        case 'out':
            $total_exits = $stat['total_quantity'];
            $exits_value = $stat['total_value'];
            break;
        case 'adjust':
            $total_adjustments = $stat['total_quantity'];
            break;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.period-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.period-btn {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s;
}

.period-btn:hover {
    background: rgba(0, 255, 136, 0.1);
    border-color: var(--neon-green);
}

.period-btn.active {
    background: var(--neon-green);
    color: #000;
    border-color: var(--neon-green);
    font-weight: 700;
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.summary-card.green {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.1), transparent);
    border-color: var(--neon-green);
}

.summary-card.red {
    background: linear-gradient(135deg, rgba(255, 0, 0, 0.1), transparent);
    border-color: #ff0000;
}

.summary-card.cyan {
    background: linear-gradient(135deg, rgba(0, 217, 255, 0.1), transparent);
    border-color: var(--neon-cyan);
}

.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> Rapports & Statistiques</h1>
</div>

<!-- Sélecteur de période -->
<div class="period-selector">
    <a href="?period=today" class="period-btn <?php echo $period === 'today' ? 'active' : ''; ?>">
        Aujourd'hui
    </a>
    <a href="?period=week" class="period-btn <?php echo $period === 'week' ? 'active' : ''; ?>">
        7 Derniers Jours
    </a>
    <a href="?period=month" class="period-btn <?php echo $period === 'month' ? 'active' : ''; ?>">
        Ce Mois
    </a>
    <a href="?period=year" class="period-btn <?php echo $period === 'year' ? 'active' : ''; ?>">
        Cette Année
    </a>
    
    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="period" value="custom">
        <input type="date" name="custom_from" class="form-input" value="<?php echo htmlspecialchars($custom_from); ?>" style="width: auto;">
        <span>→</span>
        <input type="date" name="custom_to" class="form-input" value="<?php echo htmlspecialchars($custom_to); ?>" style="width: auto;">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-calendar"></i>
            Personnalisé
        </button>
    </form>
</div>

<!-- Période affichée -->
<div style="background: rgba(0, 255, 136, 0.05); border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-bottom: 25px;">
    <i class="fas fa-calendar-alt" style="color: var(--neon-green);"></i>
    <strong>Période :</strong>
    <?php echo date('d/m/Y', strtotime($date_from)); ?> 
    → 
    <?php echo date('d/m/Y', strtotime($date_to)); ?>
</div>

<!-- Statistiques résumées -->
<div class="stats-summary">
    <div class="summary-card green">
        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px; text-transform: uppercase;">
            Entrées
        </div>
        <div style="font-size: 32px; font-weight: 700; color: var(--neon-green); margin-bottom: 5px;">
            +<?php echo number_format($total_entries); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            <?php echo number_format($entries_value, 0, ',', ' '); ?> HTG
        </div>
    </div>
    
    <div class="summary-card red">
        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px; text-transform: uppercase;">
            Sorties
        </div>
        <div style="font-size: 32px; font-weight: 700; color: #ff0000; margin-bottom: 5px;">
            -<?php echo number_format($total_exits); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            <?php echo number_format($exits_value, 0, ',', ' '); ?> HTG
        </div>
    </div>
    
    <div class="summary-card cyan">
        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px; text-transform: uppercase;">
            Ajustements
        </div>
        <div style="font-size: 32px; font-weight: 700; color: var(--neon-cyan); margin-bottom: 5px;">
            <?php echo number_format($total_adjustments); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Corrections
        </div>
    </div>
    
    <div class="summary-card">
        <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px; text-transform: uppercase;">
            Variation Nette
        </div>
        <div style="font-size: 32px; font-weight: 700; color: <?php echo ($total_entries - $total_exits) >= 0 ? 'var(--neon-green)' : '#ff0000'; ?>; margin-bottom: 5px;">
            <?php echo ($total_entries - $total_exits) >= 0 ? '+' : ''; ?>
            <?php echo number_format($total_entries - $total_exits); ?>
        </div>
        <div style="font-size: 13px; color: var(--text-secondary);">
            Unités
        </div>
    </div>
</div>

<!-- Graphiques et tableaux -->
<div class="charts-grid">
    <!-- Top 10 Produits -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-trophy"></i> Top 10 Produits</h2>
        </div>
        <div class="card-body">
            <?php if (empty($top_products)): ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 30px;">
                    Aucun mouvement sur cette période
                </p>
            <?php else: ?>
                <?php foreach ($top_products as $index => $product): ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; margin-bottom: 10px;">
                        <div style="width: 30px; height: 30px; background: var(--neon-green); color: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            <?php echo $index + 1; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: var(--text-primary);">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($product['sku']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; font-size: 18px; color: var(--neon-cyan);">
                                <?php echo number_format($product['total_movements']); ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-secondary);">
                                <?php echo $product['movement_count']; ?> mouvement(s)
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Évolution par jour -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-bar"></i> Évolution Quotidienne</h2>
        </div>
        <div class="card-body">
            <?php if (empty($daily_values)): ?>
                <p style="text-align: center; color: var(--text-secondary); padding: 30px;">
                    Aucune donnée sur cette période
                </p>
            <?php else: ?>
                <?php foreach ($daily_values as $day): ?>
                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                <?php echo date('d/m/Y', strtotime($day['date'])); ?>
                            </div>
                            <div style="display: flex; gap: 15px; font-size: 13px;">
                                <span style="color: var(--neon-green);">
                                    <i class="fas fa-arrow-up"></i>
                                    <?php echo number_format($day['entries_value'], 0); ?>
                                </span>
                                <span style="color: #ff0000;">
                                    <i class="fas fa-arrow-down"></i>
                                    <?php echo number_format($day['exits_value'], 0); ?>
                                </span>
                            </div>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.05); height: 4px; border-radius: 2px; overflow: hidden;">
                            <?php 
                            $max = max($day['entries_value'], $day['exits_value']);
                            $entries_percent = $max > 0 ? ($day['entries_value'] / $max) * 100 : 0;
                            ?>
                            <div style="background: var(--neon-green); height: 100%; width: <?php echo $entries_percent; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Actions -->
<div style="text-align: center; padding: 30px;">
    <button onclick="window.print()" class="btn btn-secondary">
        <i class="fas fa-print"></i>
        Imprimer ce Rapport
    </button>
    <a href="movements-list.php?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-primary">
        <i class="fas fa-list"></i>
        Voir Mouvements Détaillés
    </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
