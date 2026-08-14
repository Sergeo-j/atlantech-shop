<?php
/**
 * Mettre à jour un administrateur
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
    if (empty($_POST['id']) || empty($_POST['full_name']) || empty($_POST['name']) || empty($_POST['email']) || empty($_POST['admin_role_id'])) {
        echo json_encode(['success' => false, 'message' => 'Champs requis manquants']);
        exit;
    }
    
    $admin_id = intval($_POST['id']);
    $full_name = trim($_POST['full_name']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $admin_role_id = intval($_POST['admin_role_id']);
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email invalide']);
        exit;
    }
    
    // Vérifier si l'admin existe
    $check_admin = $pdo->prepare("SELECT id, email FROM admins WHERE id = ?");
    $check_admin->execute([$admin_id]);
    $existing_admin = $check_admin->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_admin) {
        echo json_encode(['success' => false, 'message' => 'Administrateur introuvable']);
        exit;
    }
    
    // Vérifier si l'email existe déjà (sauf pour cet admin)
    $check_email = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
    $check_email->execute([$email, $admin_id]);
    if ($check_email->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
        exit;
    }
    
    // Vérifier que le rôle existe
    $role_check = $pdo->prepare("SELECT id FROM admin_roles WHERE id = ? AND is_active = 1");
    $role_check->execute([$admin_role_id]);
    if (!$role_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Rôle invalide']);
        exit;
    }
    
    // Préparer la requête de mise à jour
    $update_fields = [
        'full_name' => $full_name,
        'name' => $name,
        'email' => $email,
        'phone' => $phone ?: null,
        'admin_role_id' => $admin_role_id,
        'is_active' => $is_active
    ];
    
    // Si un nouveau mot de passe est fourni
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
            exit;
        }
        
        // Hasher le mot de passe
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        if (!$hashed_password) {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        $update_fields['password'] = $hashed_password;
    }
    
    // Construire la requête SQL
    $set_clause = [];
    $values = [];
    
    foreach ($update_fields as $field => $value) {
        $set_clause[] = "{$field} = ?";
        $values[] = $value;
    }
    
    $values[] = $admin_id;
    
    $sql = "UPDATE admins SET " . implode(', ', $set_clause) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    // Logger l'action
    $superadmin_id = $_SESSION['superadmin_id'] ?? 1;
    $log_stmt = $pdo->prepare("
        INSERT INTO superadmin_activity_logs (superadmin_id, action, module, description, ip_address, old_values, new_values)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $log_stmt->execute([
        $superadmin_id,
        'UPDATE_ADMIN',
        'admins',
        "Modification de l'administrateur: {$full_name} ({$email})",
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        json_encode(['email' => $existing_admin['email']]),
        json_encode(['email' => $email])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Administrateur modifié avec succès'
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur modification admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
} catch (Exception $e) {
    error_log("Erreur modification admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
}
