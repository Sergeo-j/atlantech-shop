<?php
/**
 * Bloquer/Débloquer un utilisateur
 * AJAX Handler
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Vérifier l'authentification
check_superadmin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    // Récupérer les données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        exit;
    }
    
    $user_id = intval($input['id']);
    
    // Récupérer l'état actuel
    $stmt = $pdo->prepare("SELECT blocked, name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
        exit;
    }
    
    // Inverser le statut
    $new_status = $user['blocked'] ? 0 : 1;
    $blocked_reason = $new_status ? 'Bloqué par l\'administrateur' : null;
    
    $update_stmt = $pdo->prepare("UPDATE users SET blocked = ?, blocked_reason = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $blocked_reason, $user_id]);
    
    // Logger l'action
    $superadmin_id = $_SESSION['superadmin_id'] ?? 1;
    $action = $new_status ? 'BLOCK_USER' : 'UNBLOCK_USER';
    $description = $new_status 
        ? "Blocage de l'utilisateur: {$user['name']} ({$user['email']})"
        : "Déblocage de l'utilisateur: {$user['name']} ({$user['email']})";
    
    $log_stmt = $pdo->prepare("
        INSERT INTO superadmin_activity_logs (superadmin_id, action, module, description, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $log_stmt->execute([
        $superadmin_id,
        $action,
        'users',
        $description,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $new_status ? 'Utilisateur bloqué avec succès' : 'Utilisateur débloqué avec succès',
        'new_status' => $new_status
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur toggle user block: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
} catch (Exception $e) {
    error_log("Erreur toggle user block: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
