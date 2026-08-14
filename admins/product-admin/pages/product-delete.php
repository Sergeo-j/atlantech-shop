<?php
/**
 * Suppression d'un Produit
 * Product Admin - Atlantech Shop
 *
 * Accessible via GET ?id=X (avec confirmation JS côté appelant)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: products-list.php?error=invalid_id');
    exit();
}

// Charger le produit (pour récupérer son nom et ses images)
$product = get_product_by_id($product_id);

if (!$product) {
    header('Location: products-list.php?error=not_found');
    exit();
}

try {
    // 1. Supprimer toutes les images de la galerie (fichiers + lignes DB)
    $gallery = get_product_gallery($product_id);
    foreach ($gallery as $gimg) {
        $file_path = __DIR__ . '/../../../uploads/products/' . $gimg['image'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    // Supprimer toutes les lignes product_images
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$product_id]);

    // 2. Supprimer l'image principale si elle existe encore sur disque
    if (!empty($product['image'])) {
        $main_path = __DIR__ . '/../../../uploads/products/' . $product['image'];
        if (file_exists($main_path)) {
            @unlink($main_path);
        }
    }

    // 3. Supprimer le produit
    if (delete_product($product_id)) {
        log_admin_action('product_deleted', 'Produit supprimé : ' . $product['name'] . ' (ID: ' . $product_id . ')');
        header('Location: products-list.php?success=deleted');
        exit();
    } else {
        header('Location: products-list.php?error=delete_failed');
        exit();
    }

} catch (Exception $e) {
    error_log('product-delete.php: ' . $e->getMessage());
    header('Location: products-list.php?error=delete_failed');
    exit();
}
