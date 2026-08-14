<?php
/**
 * Liste des Marques
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Gestion des Marques';
$current_page = 'brands';

// Messages
$success_message = '';
$error_message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Token de sécurité invalide';
    } else {
        switch ($action) {
            case 'add':
                $name = clean_input($_POST['name'] ?? '');
                $description = clean_input($_POST['description'] ?? '');
                $website = clean_input($_POST['website'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                
                if (empty($name)) {
                    $error_message = 'Le nom de la marque est requis';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO brands (name, description, is_active, created_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        
                        if ($stmt->execute([$name, $description, $is_active])) {
                            log_admin_action('brand_created', 'Marque créée : ' . $name);
                            $success_message = 'Marque ajoutée avec succès';
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $error_message = 'Cette marque existe déjà';
                        } else {
                            $error_message = 'Erreur lors de l\'ajout';
                        }
                    }
                }
                break;
                
            case 'toggle':
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE brands SET is_active = NOT is_active WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            log_admin_action('brand_toggled', 'Statut marque changé : ID ' . $id);
                            $success_message = 'Statut modifié avec succès';
                        }
                    } catch (PDOException $e) {
                        $error_message = 'Erreur lors de la modification';
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        // Vérifier s'il y a des produits
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
                        $stmt->execute([$id]);
                        $count = $stmt->fetch()['count'];
                        
                        if ($count > 0) {
                            $error_message = "Impossible de supprimer : $count produit(s) utilisent cette marque";
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
                            if ($stmt->execute([$id])) {
                                log_admin_action('brand_deleted', 'Marque supprimée : ID ' . $id);
                                $success_message = 'Marque supprimée avec succès';
                            }
                        }
                    } catch (PDOException $e) {
                        $error_message = 'Erreur lors de la suppression';
                    }
                }
                break;
        }
    }
}

// Récupérer toutes les marques
try {
    $stmt = $pdo->query("
        SELECT 
            b.*,
            COUNT(DISTINCT p.id) as products_count
        FROM brands b
        LEFT JOIN products p ON b.id = p.brand_id
        GROUP BY b.id
        ORDER BY b.name ASC
    ");
    $brands = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur brands-list: " . $e->getMessage());
    $brands = [];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.brands-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 25px;
}

.brand-form {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 30px;
}

.brands-grid-view {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    padding: 20px;
}

.brand-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.brand-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(138, 43, 226, 0.3);
    border-color: var(--neon-purple);
}

.brand-card.featured {
    border: 2px solid var(--neon-gold);
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.05), transparent);
}

.brand-header {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
}

.brand-logo {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--neon-purple), var(--neon-cyan));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    font-weight: 700;
    flex-shrink: 0;
}

.brand-info {
    flex: 1;
    min-width: 0;
}

.brand-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.brand-products-count {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--text-secondary);
    font-size: 13px;
}

.brand-description {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 12px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.brand-website {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--neon-cyan);
    text-decoration: none;
    margin-bottom: 15px;
}

.brand-website:hover {
    text-decoration: underline;
}

.brand-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.brand-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.brand-badge.active {
    background: rgba(0, 255, 136, 0.1);
    color: var(--neon-green);
    border: 1px solid var(--neon-green);
}

.brand-badge.inactive {
    background: rgba(255, 0, 0, 0.1);
    color: #ff0000;
    border: 1px solid #ff0000;
}

.brand-badge.featured {
    background: rgba(255, 215, 0, 0.1);
    color: var(--neon-gold);
    border: 1px solid var(--neon-gold);
}

.brand-actions {
    display: flex;
    gap: 8px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
}

.brand-action-btn {
    flex: 1;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.brand-action-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.brand-action-btn.toggle:hover {
    border-color: var(--neon-cyan);
    color: var(--neon-cyan);
}

.brand-action-btn.featured:hover {
    border-color: var(--neon-gold);
    color: var(--neon-gold);
}

.brand-action-btn.delete:hover {
    border-color: #ff0000;
    color: #ff0000;
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--neon-cyan);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 1024px) {
    .brands-grid {
        grid-template-columns: 1fr;
    }
    
    .brand-form {
        position: relative;
        top: 0;
    }
    
    .brands-grid-view {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-tag"></i> Gestion des Marques</h1>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="brands-grid">
    <!-- Formulaire d'ajout -->
    <div class="brand-form">
        <h3 class="form-section-title">
            <i class="fas fa-plus-circle"></i>
            Ajouter une Marque
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">
                    Nom de la Marque
                    <span style="color: #ff0000;">*</span>
                </label>
                <input 
                    type="text" 
                    name="name" 
                    class="form-input" 
                    required
                    placeholder="Ex: Apple, Samsung, Sony..."
                >
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea 
                    name="description" 
                    class="form-textarea" 
                    rows="3"
                    placeholder="Description de la marque..."
                ></textarea>
            </div>
            
            <div class="form-group">
                <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); border-radius: 8px;">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        id="is_active"
                        checked
                        style="width: 20px; height: 20px; cursor: pointer;"
                    >
                    <label for="is_active" style="cursor: pointer; margin: 0;">
                        <strong>Marque Active</strong>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Visible sur le site
                        </div>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus-circle"></i>
                Ajouter la Marque
            </button>
        </form>
    </div>
    
    <!-- Liste des marques -->
    <div class="card">
        <div class="card-header">
            <h2>
                <i class="fas fa-list"></i>
                Marques (<?php echo count($brands); ?>)
            </h2>
        </div>
        
        <?php if (empty($brands)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                <p>Aucune marque</p>
            </div>
        <?php else: ?>
            <div class="brands-grid-view">
                <?php foreach ($brands as $brand): ?>
                    <div class="brand-card">
                        <div class="brand-header">
                            <div class="brand-logo">
                                <?php echo strtoupper(substr($brand['name'], 0, 2)); ?>
                            </div>
                            <div class="brand-info">
                                <div class="brand-name">
                                    <?php echo htmlspecialchars($brand['name']); ?>
                                </div>
                                <div class="brand-products-count">
                                    <i class="fas fa-box"></i>
                                    <span><?php echo number_format($brand['products_count']); ?> produit(s)</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($brand['description']): ?>
                            <div class="brand-description">
                                <?php echo htmlspecialchars($brand['description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="brand-badges">
                            <span class="brand-badge <?php echo $brand['is_active'] ? 'active' : 'inactive'; ?>">
                                <i class="fas fa-<?php echo $brand['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                <?php echo $brand['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <div class="brand-actions">
                            <form method="POST" style="margin: 0; flex: 1;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $brand['id']; ?>">
                                <button type="submit" class="brand-action-btn toggle">
                                    <i class="fas fa-power-off"></i>
                                    <?php echo $brand['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('⚠️ Supprimer cette marque ?\n\n<?php echo htmlspecialchars($brand['name']); ?>');">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $brand['id']; ?>">
                                <button type="submit" class="brand-action-btn delete">
                                    <i class="fas fa-trash"></i>
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
