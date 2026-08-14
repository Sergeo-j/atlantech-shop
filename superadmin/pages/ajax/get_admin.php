<?php
/**
 * Récupérer les détails d'un administrateur
 * AJAX Handler
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Vérifier l'authentification
check_superadmin_auth();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    $admin_id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("
        SELECT a.*, ar.role_name, ar.role_description
        FROM admins a
        LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
        WHERE a.id = ?
    ");
    
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Administrateur introuvable']);
        exit;
    }
    
    // Retirer le mot de passe de la réponse
    unset($admin['password']);
    
    echo json_encode([
        'success' => true,
        'admin' => $admin
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur récupération admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
} catch (Exception $e) {
    error_log("Erreur récupération admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
