<?php
/**
 * Modifier une Catégorie
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Modifier une Catégorie';
$current_page = 'categories';

$error = '';
$success = '';

// Récupérer l'ID de la catégorie
$category_id = intval($_GET['id'] ?? 0);

if ($category_id <= 0) {
    header('Location: categories-list.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $name = clean_input($_POST['name'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_visible_menu = isset($_POST['is_visible_menu']) ? 1 : 0;
        $is_visible_homepage = isset($_POST['is_visible_homepage']) ? 1 : 0;
        
        if (empty($name)) {
            $error = 'Le nom de la catégorie est requis';
        } elseif ($parent_id == $category_id) {
            $error = 'Une catégorie ne peut pas être sa propre parente';
        } else {
            try {
                // Générer le slug
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', 
                    iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
                
                $stmt = $pdo->prepare("
                    UPDATE categories 
                    SET name = ?,
                        description = ?,
                        parent_id = ?,
                        is_active = ?,
                        is_visible_menu = ?,
                        is_visible_homepage = ?,
                        slug = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$name, $description, $parent_id, $is_active, $is_visible_menu, $is_visible_homepage, $slug, $category_id])) {
                    log_admin_action('category_updated', 'Catégorie modifiée : ' . $name . ' (ID: ' . $category_id . ')');
                    $success = 'Catégorie modifiée avec succès';
                    
                    // Rediriger après 2 secondes
                    header('refresh:2;url=categories-list.php?success=updated');
                }
            } catch (PDOException $e) {
                error_log("Erreur update category: " . $e->getMessage());
                $error = 'Erreur lors de la modification';
            }
        }
    }
}

// Récupérer la catégorie
try {
    $stmt = $pdo->prepare("
        SELECT c.*, p.name as parent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.id = ?
    ");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        header('Location: categories-list.php');
        exit();
    }
    
    // Récupérer toutes les catégories pour le dropdown (sauf la catégorie actuelle et ses enfants)
    $stmt = $pdo->prepare("
        SELECT id, name, parent_id
        FROM categories
        WHERE id != ? AND parent_id != ?
        ORDER BY name
    ");
    $stmt->execute([$category_id, $category_id]);
    $all_categories = $stmt->fetchAll();
    
    // Filtrer uniquement les catégories parentes (pas de sous-catégories)
    $parent_categories = array_filter($all_categories, function($cat) {
        return $cat['parent_id'] === null;
    });
    
    // Compter les produits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $products_count = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    error_log("Erreur fetch category: " . $e->getMessage());
    header('Location: categories-list.php');
    exit();
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.info-box {
    background: rgba(0, 217, 255, 0.1);
    border: 1px solid var(--neon-cyan);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-box i {
    font-size: 24px;
    color: var(--neon-cyan);
}

.info-box-content {
    flex: 1;
}

.info-box-title {
    font-weight: 600;
    color: var(--neon-cyan);
    margin-bottom: 5px;
}

.info-box-text {
    font-size: 13px;
    color: var(--text-secondary);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 12px;
}

.checkbox-group:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--neon-cyan);
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-group label {
    cursor: pointer;
    margin: 0;
    flex: 1;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    padding-top: 30px;
    border-top: 1px solid var(--border-color);
    margin-top: 30px;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: rgba(138, 43, 226, 0.1);
    border: 1px solid var(--neon-purple);
    border-radius: 8px;
    color: var(--neon-purple);
    font-size: 13px;
    font-weight: 600;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Modifier une Catégorie</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
        <div style="font-size: 12px; margin-top: 5px;">Redirection en cours...</div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <!-- Info Box -->
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div class="info-box-content">
            <div class="info-box-title">Catégorie : <?php echo htmlspecialchars($category['name']); ?></div>
            <div class="info-box-text">
                <?php if ($category['parent_name']): ?>
                    Sous-catégorie de : <strong><?php echo htmlspecialchars($category['parent_name']); ?></strong> •
                <?php endif; ?>
                <span class="stat-badge">
                    <i class="fas fa-box"></i>
                    <?php echo number_format($products_count); ?> produit(s)
                </span>
            </div>
        </div>
    </div>
    
    <div class="card">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Informations de base -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-info-circle"></i>
                    Informations de Base
                </h3>
                
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
                        value="<?php echo htmlspecialchars($category['name']); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea 
                        name="description" 
                        class="form-textarea" 
                        rows="4"
                        placeholder="Description de la catégorie..."
                    ><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Catégorie Parente</label>
                    <select name="parent_id" class="form-select">
                        <option value="">Aucune (Catégorie principale)</option>
                        <?php foreach ($parent_categories as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>"
                                <?php echo $category['parent_id'] == $parent['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($parent['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                        Laissez vide pour que ce soit une catégorie principale
                    </div>
                </div>
            </div>
            
            <!-- Options de visibilité -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-eye"></i>
                    Visibilité
                </h3>
                
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        id="is_active"
                        <?php echo $category['is_active'] ? 'checked' : ''; ?>
                    >
                    <label for="is_active">
                        <strong>Catégorie Active</strong>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            La catégorie est visible et utilisable sur le site
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        name="is_visible_menu" 
                        id="is_visible_menu"
                        <?php echo $category['is_visible_menu'] ? 'checked' : ''; ?>
                    >
                    <label for="is_visible_menu">
                        <strong>Visible dans le Menu</strong>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Afficher dans le menu de navigation du site
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        name="is_visible_homepage" 
                        id="is_visible_homepage"
                        <?php echo $category['is_visible_homepage'] ? 'checked' : ''; ?>
                    >
                    <label for="is_visible_homepage">
                        <strong>Visible sur la Page d'Accueil</strong>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Afficher comme catégorie mise en avant sur la page d'accueil
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Informations système -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-cog"></i>
                    Informations Système
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 13px;">
                    <div>
                        <div style="color: var(--text-secondary); margin-bottom: 5px;">Slug (URL)</div>
                        <div style="color: var(--text-primary); font-family: monospace; background: rgba(255,255,255,0.05); padding: 8px; border-radius: 4px;">
                            <?php echo htmlspecialchars($category['slug']); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="color: var(--text-secondary); margin-bottom: 5px;">ID Catégorie</div>
                        <div style="color: var(--text-primary); font-family: monospace; background: rgba(255,255,255,0.05); padding: 8px; border-radius: 4px;">
                            #<?php echo $category['id']; ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="color: var(--text-secondary); margin-bottom: 5px;">Date de Création</div>
                        <div style="color: var(--text-primary);">
                            <?php echo date('d/m/Y H:i', strtotime($category['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div>
                        <div style="color: var(--text-secondary); margin-bottom: 5px;">Dernière Modification</div>
                        <div style="color: var(--text-primary);">
                            <?php echo $category['updated_at'] ? date('d/m/Y H:i', strtotime($category['updated_at'])) : 'Jamais'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="form-actions">
                <a href="categories-list.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Enregistrer les Modifications
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
