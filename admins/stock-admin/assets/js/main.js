/**
 * Stock Admin - JavaScript Principal
 * Atlantech Shop
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Stock Admin loaded');
});

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    const icon = {
        'success': 'fa-check-circle',
        'danger': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    }[type] || 'fa-info-circle';
    
    notification.innerHTML = `
        <i class="fas ${icon}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.remove(), 3000);
}

/**
 * Changer de page (pagination)
 */
function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

/**
 * Valider un formulaire de mouvement
 */
function validateMovementForm() {
    const product_id = document.getElementById('product_id')?.value;
    const quantity = document.getElementById('quantity')?.value;
    const type = document.getElementById('type')?.value;
    
    if (!product_id) {
        alert('Veuillez sélectionner un produit');
        return false;
    }
    
    if (!quantity || quantity <= 0) {
        alert('La quantité doit être supérieure à 0');
        return false;
    }
    
    if (!type) {
        alert('Veuillez sélectionner un type de mouvement');
        return false;
    }
    
    return true;
}

/**
 * Format prix
 */
function formatPrice(price) {
    return new Intl.NumberFormat('fr-HT', {
        style: 'currency',
        currency: 'HTG'
    }).format(price);
}

/**
 * Format nombre
 */
function formatNumber(number) {
    return new Intl.NumberFormat('fr-FR').format(number);
}

/**
 * Calculer la valeur totale
 */
function calculateTotalValue() {
    const quantity = parseFloat(document.getElementById('quantity')?.value || 0);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    const totalField = document.getElementById('total_value');
    
    if (totalField) {
        const total = quantity * price;
        totalField.value = total.toFixed(2);
    }
}
