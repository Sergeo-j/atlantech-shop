<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Fonction pour vérifier l'authentification
function checkOrderAdminAuth() {
    // Vérifier si l'admin est connecté
    if (!isset($_SESSION['order_admin_id'])) {
        // Vérifier le cookie "Se souvenir de moi"
        if (isset($_COOKIE['order_admin_token'])) {
            return checkRememberToken();
        }
        
        // Pas connecté, rediriger vers login
        header('Location: login.php');
        exit();
    }

    // Vérifier que le rôle est correct
    if ($_SESSION['order_admin_role'] !== 'order_admin') {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Vérifier que le compte est toujours actif
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT is_active FROM admins WHERE id = ? AND role = 'order_admin' LIMIT 1");
        $stmt->execute([$_SESSION['order_admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !$admin['is_active']) {
            session_destroy();
            $_SESSION['login_error'] = "Votre compte a été désactivé.";
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Auth check error: " . $e->getMessage());
    }

    return true;
}

// Fonction pour vérifier le token "Se souvenir de moi"
function checkRememberToken() {
    global $pdo;
    
    $token = $_COOKIE['order_admin_token'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins 
                              WHERE remember_token = ? 
                              AND remember_token_expiry > NOW() 
                              AND role = 'order_admin'
                              AND is_active = 1 
                              LIMIT 1");
        $stmt->execute([$token]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            // Recréer la session
            $_SESSION['order_admin_id'] = $admin['id'];
            $_SESSION['order_admin_name'] = $admin['name'];
            $_SESSION['order_admin_email'] = $admin['email'];
            $_SESSION['order_admin_role'] = $admin['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            return true;
        } else {
            // Token invalide ou expiré
            setcookie('order_admin_token', '', time() - 3600, '/');
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Remember token error: " . $e->getMessage());
        header('Location: login.php');
        exit();
    }
}

// Fonction pour générer un token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier un token CSRF
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Vérifier l'authentification
checkOrderAdminAuth();
?>
