<?php
// Définir les variables pour le header
$page_title = "Ajouter un Produit";
$page_icon = "fa-plus-circle";

require_once 'config.php';
// requireLogin(); // Décommenter pour activer l'authentification

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $required_fields = ['name', 'sku', 'price', 'category_id'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Le champ " . $field . " est requis";
            }
        }
        
        if (empty($errors)) {
            // Préparer les données
            $data = [
                'name' => $_POST['name'],
                'slug' => !empty($_POST['slug']) ? $_POST['slug'] : generateSlug($_POST['name']),
                'sku' => $_POST['sku'],
                'description' => $_POST['description'] ?? '',
                'short_description' => $_POST['short_description'] ?? '',
                'category_id' => $_POST['category_id'],
                'brand_id' => !empty($_POST['brand_id']) ? $_POST['brand_id'] : null,
                'price' => $_POST['price'],
                'compare_at_price' => !empty($_POST['compare_at_price']) ? $_POST['compare_at_price'] : null,
                'cost_price' => !empty($_POST['cost_price']) ? $_POST['cost_price'] : null,
                'stock' => $_POST['stock'] ?? 0,
                'stock_threshold' => $_POST['stock_threshold'] ?? 5,
                'weight' => !empty($_POST['weight']) ? $_POST['weight'] : null,
                'length' => !empty($_POST['length']) ? $_POST['length'] : null,
                'width' => !empty($_POST['width']) ? $_POST['width'] : null,
                'height' => !empty($_POST['height']) ? $_POST['height'] : null,
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'is_on_sale' => isset($_POST['is_on_sale']) ? 1 : 0,
                'meta_title' => $_POST['meta_title'] ?? $_POST['name'],
                'meta_description' => $_POST['meta_description'] ?? $_POST['short_description'] ?? '',
                'meta_keywords' => $_POST['meta_keywords'] ?? ''
            ];
            
            // Gérer l'upload d'image
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadImage($_FILES['image']);
                if ($upload_result['success']) {
                    $data['image'] = $upload_result['path'];
                } else {
                    $errors[] = $upload_result['error'];
                }
            }
            
            if (empty($errors)) {
                // Insérer le produit
                $sql = "INSERT INTO products (
                    name, slug, sku, description, short_description, category_id, brand_id,
                    price, compare_at_price, cost_price, stock, stock_threshold,
                    weight, length, width, height, image,
                    is_featured, is_active, is_on_sale,
                    meta_title, meta_description, meta_keywords
                ) VALUES (
                    :name, :slug, :sku, :description, :short_description, :category_id, :brand_id,
                    :price, :compare_at_price, :cost_price, :stock, :stock_threshold,
                    :weight, :length, :width, :height, :image,
                    :is_featured, :is_active, :is_on_sale,
                    :meta_title, :meta_description, :meta_keywords
                )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                
                $_SESSION['success_message'] = "Produit ajouté avec succès !";
                header('Location: products-manager.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de l'ajout du produit : " . $e->getMessage();
    }
}

// Récupérer les catégories
$categories = $pdo->query("SELECT id, name, parent_id FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

// Récupérer les marques
$brands = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();

// Fonction pour générer un slug
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

// Fonction pour uploader une image
function uploadImage($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $upload_path = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'path' => 'uploads/products/' . $filename];
    }
    
    return ['success' => false, 'error' => 'Erreur lors du téléchargement'];
}
?>
<?php 
include 'includes/admin-header.php'; 
include 'includes/admin-sidebar.php';
?>

    <div class="admin-container" style="max-width: 100%; padding: 0;">
        <!-- Quick Actions -->
        <div style="background: var(--white); padding: 1rem 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <a href="products-manager.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour à la Liste
                </a>
            </div>
            <div style="color: var(--gray-600); font-size: 0.875rem;">
                <i class="fas fa-info-circle"></i>
                Les champs marqués avec <span style="color: var(--danger-color);">*</span> sont obligatoires
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="productForm">
            <!-- Informations Générales -->
            <div class="form-container">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                    Informations Générales
                </h2>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="name">
                            Nom du Produit <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            placeholder="Ex: iPhone 15 Pro Max"
                            value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                            oninput="generateSlugAuto()"
                        >
                        <small>Le nom complet du produit tel qu'il apparaîtra sur le site</small>
                    </div>

                    <div class="form-group">
                        <label for="slug">
                            Slug (URL)
                        </label>
                        <input 
                            type="text" 
                            id="slug" 
                            name="slug" 
                            placeholder="iphone-15-pro-max"
                            value="<?php echo htmlspecialchars($_POST['slug'] ?? ''); ?>"
                        >
                        <small>Généré automatiquement si laissé vide</small>
                    </div>

                    <div class="form-group">
                        <label for="sku">
                            SKU <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="sku" 
                            name="sku" 
                            required 
                            placeholder="IPHONE-15-PM-256"
                            value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>"
                        >
                        <small>Code unique d'identification du produit</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="short_description">
                            Description Courte
                        </label>
                        <textarea 
                            id="short_description" 
                            name="short_description" 
                            rows="3"
                            placeholder="Description courte qui apparaîtra dans les listes de produits..."
                        ><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                        <small>Un résumé bref du produit (recommandé: 150-200 caractères)</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="description">
                            Description Complète
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="6"
                            placeholder="Description détaillée du produit, ses caractéristiques, avantages..."
                        ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small>Description détaillée qui apparaîtra sur la page du produit</small>
                    </div>
                </div>
            </div>

            <!-- Classification -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-sitemap" style="color: var(--success-color);"></i>
                    Classification
                </h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="category_id">
                            Catégorie <span class="required">*</span>
                        </label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"
                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="brand_id">
                            Marque
                        </label>
                        <select id="brand_id" name="brand_id">
                            <option value="">Sélectionner une marque</option>
                            <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"
                                <?php echo (isset($_POST['brand_id']) && $_POST['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Prix et Inventaire -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-dollar-sign" style="color: var(--warning-color);"></i>
                    Prix et Inventaire
                </h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="price">
                            Prix de Vente (HTG) <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="price" 
                            name="price" 
                            step="0.01" 
                            min="0" 
                            required 
                            placeholder="999.99"
                            value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="compare_at_price">
                            Prix de Comparaison (HTG)
                        </label>
                        <input 
                            type="number" 
                            id="compare_at_price" 
                            name="compare_at_price" 
                            step="0.01" 
                            min="0" 
                            placeholder="1299.99"
                            value="<?php echo htmlspecialchars($_POST['compare_at_price'] ?? ''); ?>"
                        >
                        <small>Prix avant réduction (optionnel)</small>
                    </div>

                    <div class="form-group">
                        <label for="cost_price">
                            Prix de Revient (HTG)
                        </label>
                        <input 
                            type="number" 
                            id="cost_price" 
                            name="cost_price" 
                            step="0.01" 
                            min="0" 
                            placeholder="799.99"
                            value="<?php echo htmlspecialchars($_POST['cost_price'] ?? ''); ?>"
                        >
                        <small>Coût d'achat du produit</small>
                    </div>

                    <div class="form-group">
                        <label for="stock">
                            Quantité en Stock
                        </label>
                        <input 
                            type="number" 
                            id="stock" 
                            name="stock" 
                            min="0" 
                            value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="stock_threshold">
                            Niveau de Stock Minimum
                        </label>
                        <input 
                            type="number" 
                            id="stock_threshold" 
                            name="stock_threshold" 
                            min="0" 
                            value="<?php echo htmlspecialchars($_POST['stock_threshold'] ?? '5'); ?>"
                        >
                        <small>Alerte de stock faible</small>
                    </div>
                </div>
            </div>

            <!-- Dimensions et Poids -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-ruler-combined" style="color: var(--info-color);"></i>
                    Dimensions et Poids
                </h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="weight">
                            Poids (kg)
                        </label>
                        <input 
                            type="number" 
                            id="weight" 
                            name="weight" 
                            step="0.01" 
                            min="0" 
                            placeholder="0.5"
                            value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="length">
                            Longueur (cm)
                        </label>
                        <input 
                            type="number" 
                            id="length" 
                            name="length" 
                            step="0.01" 
                            min="0" 
                            placeholder="20"
                            value="<?php echo htmlspecialchars($_POST['length'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="width">
                            Largeur (cm)
                        </label>
                        <input 
                            type="number" 
                            id="width" 
                            name="width" 
                            step="0.01" 
                            min="0" 
                            placeholder="10"
                            value="<?php echo htmlspecialchars($_POST['width'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="height">
                            Hauteur (cm)
                        </label>
                        <input 
                            type="number" 
                            id="height" 
                            name="height" 
                            step="0.01" 
                            min="0" 
                            placeholder="5"
                            value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>"
                        >
                    </div>
                </div>
            </div>

            <!-- Image du Produit -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-image" style="color: var(--secondary-color);"></i>
                    Image du Produit
                </h2>

                <div class="form-group full-width">
                    <label for="image">
                        Image Principale
                    </label>
                    <div class="upload-area" onclick="document.getElementById('image').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Cliquez pour télécharger une image</p>
                        <small>PNG, JPG, WEBP jusqu'à 5MB</small>
                    </div>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/*"
                        style="display: none;"
                        onchange="previewImage(this)"
                    >
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <div class="preview-item">
                            <img id="preview" src="" alt="Preview">
                            <button type="button" class="remove-image" onclick="removePreview()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-cog" style="color: var(--gray-600);"></i>
                    Options
                </h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                name="is_active" 
                                checked
                                style="width: auto;"
                            >
                            <span>Produit Actif</span>
                        </label>
                        <small>Le produit sera visible sur le site</small>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="is_featured" 
                                name="is_featured"
                                style="width: auto;"
                            >
                            <span>Produit Vedette</span>
                        </label>
                        <small>Afficher dans la section des produits vedettes</small>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="is_on_sale" 
                                name="is_on_sale"
                                style="width: auto;"
                            >
                            <span>En Promotion</span>
                        </label>
                        <small>Afficher le badge "En Promo"</small>
                    </div>
                </div>
            </div>

            <!-- SEO -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-search" style="color: var(--primary-color);"></i>
                    Optimisation SEO
                </h2>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="meta_title">
                            Titre SEO
                        </label>
                        <input 
                            type="text" 
                            id="meta_title" 
                            name="meta_title" 
                            maxlength="70"
                            placeholder="Titre pour les moteurs de recherche"
                            value="<?php echo htmlspecialchars($_POST['meta_title'] ?? ''); ?>"
                        >
                        <small>Recommandé: 50-60 caractères</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="meta_description">
                            Description SEO
                        </label>
                        <textarea 
                            id="meta_description" 
                            name="meta_description" 
                            rows="3"
                            maxlength="160"
                            placeholder="Description pour les moteurs de recherche"
                        ><?php echo htmlspecialchars($_POST['meta_description'] ?? ''); ?></textarea>
                        <small>Recommandé: 120-160 caractères</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="meta_keywords">
                            Mots-clés SEO
                        </label>
                        <input 
                            type="text" 
                            id="meta_keywords" 
                            name="meta_keywords" 
                            placeholder="smartphone, iphone, apple, 5g"
                            value="<?php echo htmlspecialchars($_POST['meta_keywords'] ?? ''); ?>"
                        >
                        <small>Séparez les mots-clés par des virgules</small>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                <a href="products-manager.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Enregistrer le Produit
                </button>
            </div>
        </form>
    </div>

    <script>
        function generateSlugAuto() {
            const name = document.getElementById('name').value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'grid';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removePreview() {
            document.getElementById('image').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('preview').src = '';
        }

        // Validation du formulaire
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const comparePrice = parseFloat(document.getElementById('compare_at_price').value);
            
            if (comparePrice && comparePrice <= price) {
                alert('Le prix de comparaison doit être supérieur au prix de vente');
                e.preventDefault();
                return false;
            }
        });
    </script>

