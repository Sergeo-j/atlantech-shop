<?php
/**
 * Suppression DÉFINITIVE d'un client — réservé au Super Admin
 * Les commandes sont conservées mais ANONYMISÉES (rapports de ventes intacts).
 * AJAX Handler
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Réservé exclusivement au Super Admin
check_superadmin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = (int)($input['id'] ?? 0);
$confirm = trim($input['confirm'] ?? '');

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID client invalide']);
    exit;
}

if ($confirm !== 'SUPPRIMER') {
    echo json_encode(['success' => false, 'message' => 'Confirmation invalide. Tapez SUPPRIMER pour confirmer.']);
    exit;
}

try {
    // Vérifier que le client existe
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Client introuvable']);
        exit;
    }

    // S'assurer que orders.user_id accepte NULL (migration à la volée, une seule fois)
    $col = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'")->fetch();
    if ($col && $col['IS_NULLABLE'] === 'NO') {
        $pdo->exec("ALTER TABLE orders MODIFY user_id INT NULL");
    }

    $pdo->beginTransaction();

    // 1. ANONYMISER les commandes (conservées pour les rapports)
    $stmt = $pdo->prepare("UPDATE orders SET
        user_id = NULL,
        customer_name  = 'Client supprimé',
        customer_phone = NULL,
        customer_email = NULL,
        shipping_address = 'Anonymisé',
        billing_address  = NULL,
        gift_message     = NULL
        WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $orders_anonymized = $stmt->rowCount();

    // 2. Supprimer les avis + mémoriser les produits pour recalcul
    $stmt = $pdo->prepare("SELECT DISTINCT product_id FROM reviews WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $review_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $pdo->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$user_id]);

    // 3. Supprimer toutes les autres données liées (détection automatique des tables)
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'user_id'
        AND TABLE_NAME NOT IN ('users','orders','reviews')
        AND TABLE_NAME NOT LIKE 'admin%'")->fetchAll(PDO::FETCH_COLUMN);
    $purged = [];
    foreach ($tables as $t) {
        $st = $pdo->prepare("DELETE FROM `$t` WHERE user_id = ?");
        $st->execute([$user_id]);
        if ($st->rowCount() > 0) $purged[] = $t . '(' . $st->rowCount() . ')';
    }

    // 4. Supprimer le compte client
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

    $pdo->commit();

    // 5. Recalculer les notes des produits concernés (hors transaction)
    foreach ($review_products as $pid) {
        $st = $pdo->prepare("UPDATE products SET rating = (
            SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM reviews
            WHERE product_id = :p1 AND status = 'approved') WHERE id = :p2");
        $st->execute([':p1' => $pid, ':p2' => $pid]);
    }

    // 6. Journaliser l'action
    try {
        $pdo->prepare("INSERT INTO admin_logs (user_id, action, description, created_at)
                       VALUES (NULL, 'SUPERADMIN_DELETE_CLIENT', ?, NOW())")
            ->execute(['Suppression définitive du client #' . $user_id . ' (' . $user['email'] . ') — ' . $orders_anonymized . ' commande(s) anonymisée(s)']);
    } catch (Exception $e) { /* journal optionnel */ }

    echo json_encode([
        'success' => true,
        'message' => 'Client "' . $user['name'] . '" supprimé définitivement. '
                   . $orders_anonymized . ' commande(s) conservée(s) et anonymisée(s).'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('delete_user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression. Consultez les logs.']);
}
