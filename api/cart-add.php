<?php
/**
 * API AJAX — Ajouter au panier
 * AtlanTech Shop
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';

// Initialiser le panier
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Paramètres (POST ou GET)
$pid        = (int)(  $_POST['product_id'] ?? $_GET['product_id'] ?? 0);
$qty        = max(1, (int)($_POST['qty']   ?? $_GET['qty']        ?? 1));
$color_id   = !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null;
$color_name = trim((string)($_POST['color_name'] ?? ''));
if ($color_name === '') $color_name = null;

if ($pid <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
    exit;
}

// Charger le produit
$stmt = $mysqli->prepare(
    "SELECT id, name, price, image, stock FROM products WHERE id = ? AND is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $pid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) {
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit;
}

if ((int)$p['stock'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produit en rupture de stock']);
    exit;
}

// Prix effectif selon la couleur choisie
$eff_price = effective_color_price($mysqli, (int)$p['id'], $color_id, (float)$p['price']);

// Ajouter / mettre à jour le panier en session
if (isset($_SESSION['cart'][$pid])) {
    $new_qty = min($_SESSION['cart'][$pid]['qty'] + $qty, (int)$p['stock']);
    $_SESSION['cart'][$pid]['qty'] = $new_qty;
    if ($color_id !== null) {
        $_SESSION['cart'][$pid]['color_id']   = $color_id;
        $_SESSION['cart'][$pid]['color_name'] = $color_name;
        $_SESSION['cart'][$pid]['price']      = $eff_price;
    }
} else {
    $_SESSION['cart'][$pid] = [
        'id'         => (int)$p['id'],
        'name'       => $p['name'],
        'price'      => $eff_price,
        'image'      => $p['image'],
        'stock'      => (int)$p['stock'],
        'qty'        => min($qty, (int)$p['stock']),
        'color_id'   => $color_id,
        'color_name' => $color_name,
    ];
}

// Total articles dans le panier
$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_count += max(1, (int)($item['qty'] ?? 1));
}

echo json_encode([
    'success'    => true,
    'cart_count' => $cart_count,
    'product'    => htmlspecialchars($p['name'], ENT_QUOTES),
    'message'    => htmlspecialchars($p['name'], ENT_QUOTES) . ' ajouté au panier',
]);
