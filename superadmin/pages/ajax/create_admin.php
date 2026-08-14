<?php
/**
 * Créer un nouvel administrateur
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
    // Validation des champs requis
    $required_fields = ['full_name', 'name', 'email', 'password', 'admin_role_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Le champ {$field} est requis"]);
            exit;
        }
    }
    
    $full_name = trim($_POST['full_name']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone'] ?? '');
    $admin_role_id = intval($_POST['admin_role_id']);
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email invalide']);
        exit;
    }
    
    // Vérifier si l'email existe déjà
    $check_stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $check_stmt->execute([$email]);
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }
    
    // Validation du mot de passe
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
        exit;
    }
    
    // Vérifier que le rôle existe
    $role_check = $pdo->prepare("SELECT id FROM admin_roles WHERE id = ? AND is_active = 1");
    $role_check->execute([$admin_role_id]);
    if (!$role_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Rôle invalide']);
        exit;
    }
    
    // Hasher le mot de passe avec Argon2id
    $hashed_password = password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
    
    if (!$hashed_password) {
        // Fallback sur bcrypt si Argon2id n'est pas disponible
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    // Insérer le nouvel admin
    $stmt = $pdo->prepare("
        INSERT INTO admins (full_name, name, email, password, phone, admin_role_id, is_active, created_by_superadmin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $superadmin_id = $_SESSION['superadmin_id'] ?? 1;
    
    $stmt->execute([
        $full_name,
        $name,
        $email,
        $hashed_password,
        $phone ?: null,
        $admin_role_id,
        $is_active,
        $superadmin_id
    ]);
    
    $new_admin_id = $pdo->lastInsertId();
    
    // Logger l'action
    $log_stmt = $pdo->prepare("
        INSERT INTO superadmin_activity_logs (superadmin_id, action, module, description, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $log_stmt->execute([
        $superadmin_id,
        'CREATE_ADMIN',
        'admins',
        "Création de l'administrateur: {$full_name} ({$email})",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Administrateur créé avec succès',
        'admin_id' => $new_admin_id
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur création admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
} catch (Exception $e) {
    error_log("Erreur création admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
