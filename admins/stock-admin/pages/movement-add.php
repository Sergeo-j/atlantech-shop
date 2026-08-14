<?php
/**
 * Ajouter un Mouvement de Stock
 * Stock Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Nouveau Mouvement';
$current_page = 'add-movement';

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $data = [
            'product_id' => intval($_POST['product_id'] ?? 0),
            'type' => clean_input($_POST['type'] ?? ''),
            'quantity' => intval($_POST['quantity'] ?? 0),
            'unit_price' => floatval($_POST['unit_price'] ?? 0),
            'total_value' => floatval($_POST['total_value'] ?? 0),
            'reason' => clean_input($_POST['reason'] ?? ''),
            'reference' => clean_input($_POST['reference'] ?? '')
        ];
        
        // Validation
        if ($data['product_id'] <= 0) {
            $error = 'Veuillez sélectionner un produit';
        } elseif (!in_array($data['type'], ['in', 'out', 'adjust'])) {
            $error = 'Type de mouvement invalide';
        } elseif ($data['quantity'] <= 0) {
            $error = 'La quantité doit être supérieure à 0';
        } else {
            if (create_stock_movement($data)) {
                log_admin_action('stock_movement_created', 
                    "Mouvement créé : {$data['type']} - {$data['quantity']} unités");
                
                header('Location: movements-list.php?success=added');
                exit();
            } else {
                $error = 'Erreur lors de la création du mouvement';
            }
        }
    }
}

// Récupérer les produits
$products = get_active_products();

include __DIR__ . '/../includes/header.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.type-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.type-card {
    padding: 30px 20px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.type-card input[type="radio"] {
    display: none;
}

.type-card i {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.type-card.in {
    border-color: rgba(0, 255, 136, 0.3);
}

.type-card.in:hover {
    background: rgba(0, 255, 136, 0.1);
    border-color: var(--neon-green);
}

.type-card.in input:checked + label {
    color: var(--neon-green);
}

.type-card.in input:checked ~ i {
    color: var(--neon-green);
}

.type-card.out {
    border-color: rgba(255, 0, 0, 0.3);
}

.type-card.out:hover {
    background: rgba(255, 0, 0, 0.1);
    border-color: #ff0000;
}

.type-card.out input:checked + label {
    color: #ff0000;
}

.type-card.out input:checked ~ i {
    color: #ff0000;
}

.type-card.adjust {
    border-color: rgba(0, 217, 255, 0.3);
}

.type-card.adjust:hover {
    background: rgba(0, 217, 255, 0.1);
    border-color: var(--neon-cyan);
}

.type-card.adjust input:checked + label {
    color: var(--neon-cyan);
}

.type-card.adjust input:checked ~ i {
    color: var(--neon-cyan);
}

.type-card input:checked ~ .type-card {
    border-width: 3px;
}

.product-preview {
    background: rgba(0, 255, 136, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    display: none;
}

.product-preview.active {
    display: block;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> Nouveau Mouvement de Stock</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <div class="card">
        <form method="POST" onsubmit="return validateMovementForm()">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Sélection du type -->
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 18px; color: var(--neon-green); margin-bottom: 20px;">
                    <i class="fas fa-exchange-alt"></i>
                    Type de Mouvement
                </h3>
                
                <div class="type-selector">
                    <div class="type-card in">
                        <input type="radio" name="type" value="in" id="type_in" required>
                        <label for="type_in" style="cursor: pointer; display: block;">
                            <i class="fas fa-arrow-down"></i>
                            <strong style="font-size: 16px; display: block;">ENTRÉE</strong>
                            <small style="color: var(--text-secondary); font-size: 12px;">
                                Ajout de stock
                            </small>
                        </label>
                    </div>
                    
                    <div class="type-card out">
                        <input type="radio" name="type" value="out" id="type_out" required>
                        <label for="type_out" style="cursor: pointer; display: block;">
                            <i class="fas fa-arrow-up"></i>
                            <strong style="font-size: 16px; display: block;">SORTIE</strong>
                            <small style="color: var(--text-secondary); font-size: 12px;">
                                Retrait de stock
                            </small>
                        </label>
                    </div>
                    
                    <div class="type-card adjust">
                        <input type="radio" name="type" value="adjust" id="type_adjust" required>
                        <label for="type_adjust" style="cursor: pointer; display: block;">
                            <i class="fas fa-sync-alt"></i>
                            <strong style="font-size: 16px; display: block;">AJUSTEMENT</strong>
                            <small style="color: var(--text-secondary); font-size: 12px;">
                                Correction
                            </small>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Produit -->
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 18px; color: var(--neon-green); margin-bottom: 20px;">
                    <i class="fas fa-box"></i>
                    Produit
                </h3>
                
                <div class="form-group">
                    <label class="form-label">
                        Sélectionner le Produit
                        <span style="color: #ff0000;">*</span>
                    </label>
                    <select name="product_id" id="product_id" class="form-select" required onchange="showProductPreview(this)">
                        <option value="">-- Choisir un produit --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                data-sku="<?php echo htmlspecialchars($product['sku']); ?>"
                                data-stock="<?php echo $product['stock']; ?>"
                                data-price="<?php echo $product['price']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> 
                                (Stock: <?php echo $product['stock']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="product-preview" id="product-preview">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary);">
                                SKU: <span id="preview-sku"></span>
                            </div>
                            <div style="font-size: 13px; color: var(--text-secondary);">
                                Stock actuel: <span id="preview-stock" style="font-weight: 700; color: var(--neon-green);"></span> unités
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 12px; color: var(--text-secondary);">Prix unitaire</div>
                            <div style="font-weight: 700; font-size: 16px; color: var(--neon-cyan);">
                                <span id="preview-price"></span> HTG
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quantité et Prix -->
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 18px; color: var(--neon-green); margin-bottom: 20px;">
                    <i class="fas fa-calculator"></i>
                    Quantité et Valeur
                </h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            Quantité
                            <span style="color: #ff0000;">*</span>
                        </label>
                        <input 
                            type="number" 
                            name="quantity" 
                            id="quantity" 
                            class="form-input"
                            min="1"
                            required
                            onchange="calculateTotalValue()"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Prix Unitaire (HTG)
                            <span style="color: #ff0000;">*</span>
                        </label>
                        <input 
                            type="number" 
                            step="0.01"
                            name="unit_price" 
                            id="unit_price" 
                            class="form-input"
                            min="0"
                            required
                            onchange="calculateTotalValue()"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Valeur Totale (HTG)</label>
                    <input 
                        type="number" 
                        step="0.01"
                        name="total_value" 
                        id="total_value" 
                        class="form-input"
                        readonly
                        style="background: rgba(0, 255, 136, 0.1); font-weight: 700; font-size: 18px;"
                    >
                </div>
            </div>
            
            <!-- Détails -->
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 18px; color: var(--neon-green); margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i>
                    Détails (Optionnel)
                </h3>
                
                <div class="form-group">
                    <label class="form-label">Raison / Motif</label>
                    <textarea 
                        name="reason" 
                        class="form-textarea" 
                        rows="3"
                        placeholder="Ex: Réapprovisionnement, Vente, Casse, Inventaire..."
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Référence</label>
                    <input 
                        type="text" 
                        name="reference" 
                        class="form-input"
                        placeholder="Ex: BON-2024-001, CMD-12345"
                    >
                </div>
            </div>
            
            <!-- Actions -->
            <div style="display: flex; gap: 15px; justify-content: flex-end; padding-top: 30px; border-top: 1px solid var(--border-color);">
                <a href="movements-list.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Enregistrer le Mouvement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showProductPreview(select) {
    const preview = document.getElementById('product-preview');
    const option = select.options[select.selectedIndex];

    if (option.value) {
        document.getElementById('preview-sku').textContent = option.dataset.sku;
        document.getElementById('preview-stock').textContent = option.dataset.stock;
        document.getElementById('preview-price').textContent = parseFloat(option.dataset.price).toFixed(2);

        // Remplir le prix unitaire automatiquement
        document.getElementById('unit_price').value = option.dataset.price;

        preview.classList.add('active');
        calculateTotalValue();
    } else {
        preview.classList.remove('active');
    }
}

/**
 * Calcule la valeur totale = quantité × prix unitaire
 */
function calculateTotalValue() {
    const qty       = parseFloat(document.getElementById('quantity').value)    || 0;
    const unitPrice = parseFloat(document.getElementById('unit_price').value)  || 0;
    const total     = qty * unitPrice;
    document.getElementById('total_value').value = total.toFixed(2);
}

/**
 * Validation du formulaire avant soumission
 */
function validateMovementForm() {
    const productId = document.getElementById('product_id').value;
    const quantity  = parseFloat(document.getElementById('quantity').value);
    const unitPrice = parseFloat(document.getElementById('unit_price').value);
    const typeChecked = document.querySelector('input[name="type"]:checked');

    if (!productId) {
        alert('Veuillez sélectionner un produit.');
        return false;
    }
    if (!typeChecked) {
        alert('Veuillez choisir un type de mouvement (Entrée, Sortie ou Ajustement).');
        return false;
    }
    if (!quantity || quantity <= 0) {
        alert('La quantité doit être supérieure à 0.');
        return false;
    }
    if (isNaN(unitPrice) || unitPrice < 0) {
        alert('Le prix unitaire est invalide.');
        return false;
    }
    return true;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
