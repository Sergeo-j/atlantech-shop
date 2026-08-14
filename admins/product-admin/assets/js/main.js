/**
 * Product Admin - JavaScript Principal
 * Atlantech Shop
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Product Admin loaded');
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
 * Valider un formulaire produit
 */
function validateProductForm() {
    const name = document.getElementById('name')?.value.trim();
    const sku = document.getElementById('sku')?.value.trim();
    const price = document.getElementById('price')?.value;
    const stock = document.getElementById('stock')?.value;
    
    if (!name) {
        alert('Le nom du produit est requis');
        return false;
    }
    
    if (!sku) {
        alert('Le SKU est requis');
        return false;
    }
    
    if (!price || price <= 0) {
        alert('Le prix doit être supérieur à 0');
        return false;
    }
    
    if (stock === '' || stock < 0) {
        alert('Le stock doit être un nombre positif');
        return false;
    }
    
    return true;
}

/**
 * Prévisualiser une image
 */
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Générer un slug
 */
function generateSlug(text) {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
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