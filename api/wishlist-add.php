<?php
/**
 * API AJAX — Ajouter / Retirer de la wishlist
 * AtlanTech Shop
 *
 * GET  : ?product_id=X        → ajoute ou retire (toggle)
 * POST : product_id=X         → idem
 *
 * Réponse JSON :
 *   { "success": true, "action": "added"|"removed", "wishlist_count": 2, "message": "..." }
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$pid = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

if ($pid <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
    exit;
}

// Vérifier que le produit existe
$stmt = $mysqli->prepare("SELECT id, name FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param('i', $pid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) {
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit;
}

// Toggle : ajouter si absent, retirer si déjà présent
if (in_array($pid, $_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = array_values(array_diff($_SESSION['wishlist'], [$pid]));
    $action  = 'removed';
    $message = htmlspecialchars($p['name'], ENT_QUOTES) . ' retiré des favoris';
} else {
    $_SESSION['wishlist'][] = $pid;
    $action  = 'added';
    $message = htmlspecialchars($p['name'], ENT_QUOTES) . ' ajouté aux favoris';
}

echo json_encode([
    'success'        => true,
    'action'         => $action,
    'wishlist_count' => count($_SESSION['wishlist']),
    'message'        => $message,
]);
