<?php
/**
 * Activer/Désactiver un administrateur
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
    
    $admin_id = intval($input['id']);
    
    // Récupérer l'état actuel
    $stmt = $pdo->prepare("SELECT is_active, full_name, email FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Administrateur introuvable']);
        exit;
    }
    
    // Inverser le statut
    $new_status = $admin['is_active'] ? 0 : 1;
    
    $update_stmt = $pdo->prepare("UPDATE admins SET is_active = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $admin_id]);
    
    // Logger l'action
    $superadmin_id = $_SESSION['superadmin_id'] ?? 1;
    $action = $new_status ? 'ACTIVATE_ADMIN' : 'DEACTIVATE_ADMIN';
    $description = $new_status 
        ? "Activation de l'administrateur: {$admin['full_name']} ({$admin['email']})"
        : "Désactivation de l'administrateur: {$admin['full_name']} ({$admin['email']})";
    
    $log_stmt = $pdo->prepare("
        INSERT INTO superadmin_activity_logs (superadmin_id, action, module, description, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $log_stmt->execute([
        $superadmin_id,
        $action,
        'admins',
        $description,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => $new_status ? 'Administrateur activé avec succès' : 'Administrateur désactivé avec succès',
        'new_status' => $new_status
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur toggle admin status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
} catch (Exception $e) {
    error_log("Erreur toggle admin status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
