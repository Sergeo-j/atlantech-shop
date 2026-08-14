<?php
/**
 * Liste des Catégories
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Gestion des Catégories';
$current_page = 'categories';

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
                $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    $error_message = 'Le nom de la catégorie est requis';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO categories (name, description, parent_id, is_active, slug, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', 
                            iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
                        
                        if ($stmt->execute([$name, $description, $parent_id, $is_active, $slug])) {
                            log_admin_action('category_created', 'Catégorie créée : ' . $name);
                            $success_message = 'Catégorie ajoutée avec succès';
                        }
                    } catch (PDOException $e) {
                        $error_message = 'Erreur lors de l\'ajout : ' . $e->getMessage();
                    }
                }
                break;
                
            case 'toggle':
                $id = intval($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE categories SET is_active = NOT is_active WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            log_admin_action('category_toggled', 'Statut catégorie changé : ID ' . $id);
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
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                        $stmt->execute([$id]);
                        $count = $stmt->fetch()['count'];
                        
                        if ($count > 0) {
                            $error_message = "Impossible de supprimer : $count produit(s) utilisent cette catégorie";
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                            if ($stmt->execute([$id])) {
                                log_admin_action('category_deleted', 'Catégorie supprimée : ID ' . $id);
                                $success_message = 'Catégorie supprimée avec succès';
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

// Récupérer toutes les catégories
try {
    $stmt = $pdo->query("
        SELECT 
            c.*,
            p.name as parent_name,
            COUNT(DISTINCT pr.id) as products_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        LEFT JOIN products pr ON c.id = pr.category_id
        GROUP BY c.id
        ORDER BY c.parent_id IS NULL DESC, c.parent_id, c.name
    ");
    $categories = $stmt->fetchAll();
    
    // Catégories parentes pour le formulaire
    $parent_categories = array_filter($categories, function($cat) {
        return $cat['parent_id'] === null;
    });
    
} catch (PDOException $e) {
    error_log("Erreur categories-list: " . $e->getMessage());
    $categories = [];
    $parent_categories = [];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.categories-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 25px;
}

.category-form {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 30px;
}

.category-row {
    display: grid;
    grid-template-columns: 40px 2fr 1fr 100px 80px 150px;
    gap: 15px;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s;
}

.category-row:hover {
    background: rgba(255, 255, 255, 0.02);
}

.category-row.parent {
    background: rgba(138, 43, 226, 0.05);
    font-weight: 600;
}

.category-row.child {
    padding-left: 55px;
    background: rgba(0, 217, 255, 0.02);
}

.category-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--neon-purple), var(--neon-cyan));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.category-row.child .category-icon {
    background: rgba(0, 217, 255, 0.2);
    border: 1px solid var(--neon-cyan);
}

.category-info {
    flex: 1;
}

.category-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 3px;
}

.category-description {
    font-size: 12px;
    color: var(--text-secondary);
}

.category-count {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--text-secondary);
    font-size: 13px;
}

.toggle-switch {
    position: relative;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 0, 0, 0.3);
    border: 1px solid #ff0000;
    border-radius: 34px;
    transition: 0.3s;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: 0.3s;
}

input:checked + .toggle-slider {
    background-color: rgba(0, 255, 136, 0.3);
    border-color: var(--neon-green);
}

input:checked + .toggle-slider:before {
    transform: translateX(24px);
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
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-form {
        position: relative;
        top: 0;
    }
}

@media (max-width: 768px) {
    .category-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-folder"></i> Gestion des Catégories</h1>
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

<div class="categories-grid">
    <!-- Formulaire d'ajout -->
    <div class="category-form">
        <h3 class="form-section-title">
            <i class="fas fa-plus-circle"></i>
            Ajouter une Catégorie
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">
                    Nom de la Catégorie
                    <span style="color: #ff0000;">*</span>
                </label>
                <input 
                    type="text" 
                    name="name" 
                    class="form-input" 
                    required
                    placeholder="Ex: Smartphones"
                >
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea 
                    name="description" 
                    class="form-textarea" 
                    rows="3"
                    placeholder="Description de la catégorie..."
                ></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Catégorie Parente</label>
                <select name="parent_id" class="form-select">
                    <option value="">Aucune (Catégorie principale)</option>
                    <?php foreach ($parent_categories as $parent): ?>
                        <option value="<?php echo $parent['id']; ?>">
                            <?php echo htmlspecialchars($parent['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                    Laissez vide pour créer une catégorie principale
                </div>
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
                        <strong>Catégorie Active</strong>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Visible sur le site
                        </div>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-plus-circle"></i>
                Ajouter la Catégorie
            </button>
        </form>
    </div>
    
    <!-- Liste des catégories -->
    <div class="card">
        <div class="card-header">
            <h2>
                <i class="fas fa-list"></i>
                Catégories (<?php echo count($categories); ?>)
            </h2>
        </div>
        
        <?php if (empty($categories)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                <p>Aucune catégorie</p>
            </div>
        <?php else: ?>
            <div style="padding: 20px;">
                <?php foreach ($categories as $category): ?>
                    <div class="category-row <?php echo $category['parent_id'] ? 'child' : 'parent'; ?>">
                        <div class="category-icon">
                            <?php if ($category['parent_id']): ?>
                                <i class="fas fa-angle-right"></i>
                            <?php else: ?>
                                <i class="fas fa-folder"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="category-info">
                            <div class="category-name">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </div>
                            <?php if ($category['parent_name']): ?>
                                <div class="category-description">
                                    Sous-catégorie de : <?php echo htmlspecialchars($category['parent_name']); ?>
                                </div>
                            <?php elseif ($category['description']): ?>
                                <div class="category-description">
                                    <?php echo htmlspecialchars(substr($category['description'], 0, 60)); ?>
                                    <?php echo strlen($category['description']) > 60 ? '...' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="category-count">
                            <i class="fas fa-box"></i>
                            <span><?php echo number_format($category['products_count']); ?> produit(s)</span>
                        </div>
                        
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                            
                            <label class="toggle-switch">
                                <input 
                                    type="checkbox" 
                                    <?php echo $category['is_active'] ? 'checked' : ''; ?>
                                    onchange="this.form.submit()"
                                >
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                        
                        <div style="display: flex; gap: 8px;">
                            <a href="category-edit.php?id=<?php echo $category['id']; ?>" 
                               class="action-btn edit" 
                               title="Modifier">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('⚠️ Supprimer cette catégorie ?\n\n<?php echo htmlspecialchars($category['name']); ?>');">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                
                                <button type="submit" class="action-btn delete" title="Supprimer">
                                    <i class="fas fa-trash"></i>
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
