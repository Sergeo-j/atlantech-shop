<?php
require_once 'config.php';
// requireLogin();

// Vérifier l'ID du produit
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID du produit manquant";
    header('Location: products-manager.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Variables pour le header (seront mis à jour après chargement du produit)
$page_title = "Modifier le Produit";
$page_icon = "fa-edit";

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];
        
        // Validation
        $required_fields = ['name', 'sku', 'price', 'category_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Le champ " . $field . " est requis";
            }
        }
        
        if (empty($errors)) {
            // Préparer les données
            $data = [
                'id' => $product_id,
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
                'meta_description' => $_POST['meta_description'] ?? '',
                'meta_keywords' => $_POST['meta_keywords'] ?? ''
            ];
            
            // Gérer l'upload d'image si nouvelle image
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadImage($_FILES['image']);
                if ($upload_result['success']) {
                    $data['image'] = $upload_result['path'];
                } else {
                    $errors[] = $upload_result['error'];
                }
            }
            
            if (empty($errors)) {
                // Construire la requête de mise à jour
                $update_fields = [];
                foreach ($data as $key => $value) {
                    if ($key !== 'id' && ($key !== 'image' || isset($data['image']))) {
                        $update_fields[] = "$key = :$key";
                    }
                }
                
                $sql = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                
                $_SESSION['success_message'] = "Produit modifié avec succès !";
                header('Location: products-manager.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de la modification : " . $e->getMessage();
    }
}

// Récupérer les informations du produit
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error_message'] = "Produit introuvable";
    header('Location: products-manager.php');
    exit();
}

// Mettre à jour le titre de la page avec le nom du produit
$page_title = "Modifier : " . $product['name'];

// Récupérer les catégories et marques
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();

function generateSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-'));
}

function uploadImage($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    
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
                    Retour
                </a>
            </div>
            <div style="color: var(--gray-600); font-size: 0.875rem;">
                <i class="fas fa-info-circle"></i>
                Dernière modification: <?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?>
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
                        <label for="name">Nom du Produit <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($product['name']); ?>"
                               oninput="generateSlugAuto()">
                    </div>

                    <div class="form-group">
                        <label for="slug">Slug (URL)</label>
                        <input type="text" id="slug" name="slug" 
                               value="<?php echo htmlspecialchars($product['slug']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="sku">SKU <span class="required">*</span></label>
                        <input type="text" id="sku" name="sku" required 
                               value="<?php echo htmlspecialchars($product['sku']); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="short_description">Description Courte</label>
                        <textarea id="short_description" name="short_description" rows="3"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="description">Description Complète</label>
                        <textarea id="description" name="description" rows="6"><?php echo htmlspecialchars($product['description']); ?></textarea>
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
                        <label for="category_id">Catégorie <span class="required">*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Sélectionner</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"
                                <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="brand_id">Marque</label>
                        <select id="brand_id" name="brand_id">
                            <option value="">Sélectionner</option>
                            <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"
                                <?php echo $product['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
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
                        <label for="price">Prix de Vente (HTG) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required 
                               value="<?php echo $product['price']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="compare_at_price">Prix de Comparaison</label>
                        <input type="number" id="compare_at_price" name="compare_at_price" step="0.01" min="0" 
                               value="<?php echo $product['compare_at_price']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="cost_price">Prix de Revient</label>
                        <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" 
                               value="<?php echo $product['cost_price']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="stock">Quantité en Stock</label>
                        <input type="number" id="stock" name="stock" min="0" 
                               value="<?php echo $product['stock']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="stock_threshold">Stock Minimum</label>
                        <input type="number" id="stock_threshold" name="stock_threshold" min="0" 
                               value="<?php echo $product['stock_threshold']; ?>">
                    </div>
                </div>
            </div>

            <!-- Dimensions -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-ruler-combined" style="color: var(--info-color);"></i>
                    Dimensions et Poids
                </h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="weight">Poids (kg)</label>
                        <input type="number" id="weight" name="weight" step="0.01" min="0" 
                               value="<?php echo $product['weight']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="length">Longueur (cm)</label>
                        <input type="number" id="length" name="length" step="0.01" min="0" 
                               value="<?php echo $product['length']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="width">Largeur (cm)</label>
                        <input type="number" id="width" name="width" step="0.01" min="0" 
                               value="<?php echo $product['width']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="height">Hauteur (cm)</label>
                        <input type="number" id="height" name="height" step="0.01" min="0" 
                               value="<?php echo $product['height']; ?>">
                    </div>
                </div>
            </div>

            <!-- Image -->
            <div class="form-container" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--gray-900); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-image" style="color: var(--secondary-color);"></i>
                    Image du Produit
                </h2>

                <div class="form-group full-width">
                    <?php if ($product['image']): ?>
                    <div style="margin-bottom: 1rem;">
                        <p style="font-weight: 600; margin-bottom: 0.5rem;">Image actuelle:</p>
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="Current" style="max-width: 200px; border-radius: var(--radius); box-shadow: var(--shadow-md);">
                    </div>
                    <?php endif; ?>
                    
                    <label for="image">Changer l'image</label>
                    <div class="upload-area" onclick="document.getElementById('image').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Cliquez pour télécharger une nouvelle image</p>
                        <small>PNG, JPG, WEBP jusqu'à 5MB</small>
                    </div>
                    <input type="file" id="image" name="image" accept="image/*"
                           style="display: none;" onchange="previewImage(this)">
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
                            <input type="checkbox" id="is_active" name="is_active" style="width: auto;"
                                   <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                            <span>Produit Actif</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="is_featured" name="is_featured" style="width: auto;"
                                   <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                            <span>Produit Vedette</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="is_on_sale" name="is_on_sale" style="width: auto;"
                                   <?php echo $product['is_on_sale'] ? 'checked' : ''; ?>>
                            <span>En Promotion</span>
                        </label>
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
                        <label for="meta_title">Titre SEO</label>
                        <input type="text" id="meta_title" name="meta_title" maxlength="70"
                               value="<?php echo htmlspecialchars($product['meta_title']); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="meta_description">Description SEO</label>
                        <textarea id="meta_description" name="meta_description" rows="3" maxlength="160"><?php echo htmlspecialchars($product['meta_description']); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="meta_keywords">Mots-clés SEO</label>
                        <input type="text" id="meta_keywords" name="meta_keywords"
                               value="<?php echo htmlspecialchars($product['meta_keywords']); ?>">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                <a href="products-manager.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Enregistrer les Modifications
                </button>
            </div>
        </form>
    </div>

    <script>
        function generateSlugAuto() {
            const name = document.getElementById('name').value;
            const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
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
        }
    </script>

