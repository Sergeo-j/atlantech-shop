<?php
/**
 * Soumission d'avis produit — AtlanTech E-commerce
 * Accepte uniquement les requêtes POST depuis shop-single.php
 */

require_once 'config/config.php';

// ── Uniquement POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: shop.php');
    exit();
}

// ── L'utilisateur doit être connecté ────────────────────────
if (!isLoggedIn()) {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $return = $product_id > 0 ? 'shop-single.php?id=' . $product_id . '&error=login_required' : 'shop.php';
    header('Location: login.php?redirect=' . urlencode($return));
    exit();
}

// ── Récupérer et valider les données ────────────────────────
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating     = isset($_POST['rating'])     ? (int)$_POST['rating']     : 0;
$comment    = trim($_POST['comment'] ?? '');
$user_id    = (int)$_SESSION['user_id'];

// Vérifications de base
if ($product_id <= 0) {
    header('Location: shop.php?error=invalid_product');
    exit();
}

// URL de retour (fragment en dernier pour que les paramètres GET soient bien lus)
$back_base = 'shop-single.php?id=' . $product_id;

if ($rating < 1 || $rating > 5) {
    header('Location: ' . $back_base . '&error=invalid_rating#tb-reviews');
    exit();
}

if (empty($comment)) {
    header('Location: ' . $back_base . '&error=empty_comment#tb-reviews');
    exit();
}

if (mb_strlen($comment) > 2000) {
    header('Location: ' . $back_base . '&error=comment_too_long#tb-reviews');
    exit();
}

// ── Vérifier que le produit existe ──────────────────────────
$stmt = $mysqli->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    header('Location: shop.php?error=not_found');
    exit();
}
$stmt->close();

// ── Empêcher les doublons (un seul avis par utilisateur/produit) ─
$stmt = $mysqli->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $product_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    header('Location: ' . $back_base . '&error=already_reviewed#tb-reviews');
    exit();
}
$stmt->close();

// ── Insérer l'avis ───────────────────────────────────────────
$title  = ''; // champ optionnel, non exposé dans le formulaire
$status = 'approved'; // publication immédiate (modération a posteriori via backoffice)

$stmt = $mysqli->prepare("
    INSERT INTO reviews (product_id, user_id, title, rating, comment, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param('iisiss', $product_id, $user_id, $title, $rating, $comment, $status);

if ($stmt->execute()) {
    $stmt->close();
    // Mettre à jour la note moyenne du produit
    update_product_rating($product_id);
    header('Location: ' . $back_base . '&success=review_sent#tb-reviews');
} else {
    $stmt->close();
    header('Location: ' . $back_base . '&error=db_error#tb-reviews');
}
exit();

// ── Recalculer la note moyenne du produit ───────────────────
function update_product_rating(int $product_id): void {
    global $mysqli;
    $stmt = $mysqli->prepare("
        UPDATE products
        SET rating = (
            SELECT COALESCE(ROUND(AVG(rating), 2), 0)
            FROM reviews
            WHERE product_id = ? AND status = 'approved'
        )
        WHERE id = ?
    ");
    $stmt->bind_param('ii', $product_id, $product_id);
    $stmt->execute();
    $stmt->close();
}
