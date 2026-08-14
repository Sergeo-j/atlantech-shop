<?php
require_once 'config.php';
// requireLogin();

// Vérifier l'ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID du produit manquant";
    header('Location: products-manager.php');
    exit();
}

$product_id = (int)$_GET['id'];

try {
    // Vérifier si le produit existe
    $stmt = $pdo->prepare("SELECT id, name, featured_image FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error_message'] = "Produit introuvable";
        header('Location: products-manager.php');
        exit();
    }
    
    // Supprimer l'image si elle existe
    if ($product['image'] && file_exists('../' . $product['image'])) {
        unlink('../' . $product['image']);
    }
    
    // Supprimer le produit (les suppressions en cascade sont gérées par les contraintes FK)
    $delete_stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $delete_stmt->execute([$product_id]);
    
    $_SESSION['success_message'] = "Le produit '" . htmlspecialchars($product['name']) . "' a été supprimé avec succès";
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
}

header('Location: products-manager.php');
exit();
?>
