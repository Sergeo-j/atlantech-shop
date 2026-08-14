<?php
/**
 * Achat Direct — AtlanTech E-commerce
 * Permet d'acheter un seul produit sans passer par le panier
 */

require_once 'config/config.php';

// Vérifier les paramètres
$product_id = (int)($_POST['product_id'] ?? 0);
$qty        = max(1, (int)($_POST['qty'] ?? 1));
// Couleur choisie (facultative)
$color_id   = !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null;
$color_name = trim((string)($_POST['color_name'] ?? ''));
if ($color_name === '') $color_name = null;

if ($product_id <= 0) {
    header('Location: shop.php');
    exit();
}

// Récupérer le produit en base
$stmt = $mysqli->prepare("SELECT id, name, price, image, stock FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: shop.php');
    exit();
}

// Limiter la quantité au stock disponible
$qty = min($qty, (int)$product['stock']);
if ($qty < 1) {
    header('Location: shop-single.php?id=' . $product_id . '&error=stock');
    exit();
}

// Prix effectif recalculé côté serveur selon la couleur (anti-tampering)
$eff_price = effective_color_price($mysqli, (int)$product['id'], $color_id, (float)$product['price']);

// Stocker l'achat direct en session (séparé du panier normal)
$_SESSION['buy_now'] = [
    'product_id' => $product['id'],
    'name'       => $product['name'],
    'price'      => $eff_price,
    'image'      => $product['image'],
    'stock'      => (int)$product['stock'],
    'qty'        => $qty,
    'total'      => $eff_price * $qty,
    'color_id'   => $color_id,
    'color_name' => $color_name,
];

// Rediriger vers le checkout
header('Location: checkout.php?mode=buy_now');
exit();
