<?php
/**
 * Système d'authentification
 * Atlantech Shop - Preparation Admin Dashboard
 * AVEC SUPPORT STRUCTURE DB admin_role_id
 */

require_once __DIR__ . '/config.php';

// Démarrer la session de manière sécurisée
function secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    $session_name = 'atlantech_preparation_admin';
    $secure = false; // Mettre à true en HTTPS
    $httponly = true;
    
    ini_set('session.use_only_cookies', 1);
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params(
        SESSION_LIFETIME,
        $cookieParams["path"],
        $cookieParams["domain"],
        $secure,
        $httponly
    );
    
    session_name($session_name);
    session_start();
    // NE PAS appeler session_regenerate_id() ici — cela détruit la session
    // à chaque chargement de page sur Windows/XAMPP.
    // La régénération se fait uniquement après authentification réussie (dans login_admin()).
}

// Vérifier si l'utilisateur est connecté ET a bien le rôle 'preparation'
// (revérification BD à chaque requête, empêche l'usurpation de session)
function is_logged_in() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role_id'])) {
        return false;
    }
    global $pdo;
    try {
        $st = $pdo->prepare("
            SELECT 1 FROM admins a
            JOIN admin_roles ar ON a.admin_role_id = ar.id
            WHERE a.id = ? AND ar.role_name = 'preparation' AND a.is_active = 1
            LIMIT 1
        ");
        $st->execute([(int)$_SESSION['admin_id']]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) {
        error_log('preparation-admin is_logged_in: ' . $e->getMessage());
        return false;
    }
}

// Vérifier l'authentification et rediriger si nécessaire
function check_auth() {
    if (!is_logged_in()) {
        header('Location: ../login.php');
        exit();
    }
    
    // Vérifier le timeout de session
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: ../login.php?timeout=1');
        exit();
    }
    
    $_SESSION['last_activity'] = time();
}

// Connexion de l'utilisateur - IDENTIQUE au client-admin
function login_admin($email, $password) {
    global $pdo;
    
    try {
        // REQUÊTE avec admin_role_id et jointure admin_roles
        $stmt = $pdo->prepare("
            SELECT a.id, a.full_name, a.name, a.email, a.password, 
                   a.is_active, a.admin_role_id, a.login_attempts, 
                   a.account_locked_until,
                   ar.role_name, ar.role_description
            FROM admins a
            LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
            WHERE a.email = ? AND ar.role_name = 'preparation'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            error_log("Preparation Admin non trouvé: " . $email);
            return false;
        }
        
        // Vérifier si le compte est verrouillé
        if ($admin['account_locked_until'] && strtotime($admin['account_locked_until']) > time()) {
            error_log("Compte verrouillé jusqu'à : " . $admin['account_locked_until']);
            return false;
        }
        
        // Vérifier si le compte est actif
        if (!$admin['is_active']) {
            error_log("Compte inactif: " . $email);
            return false;
        }
        
        // Vérifier le mot de passe
        if (password_verify($password, $admin['password'])) {
            // Régénérer l'ID de session pour éviter la fixation
            session_regenerate_id(true);
            
            // Stocker les informations dans la session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_username'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role_id'] = $admin['admin_role_id'];
            $_SESSION['admin_role_name'] = $admin['role_name'];
            $_SESSION['last_activity'] = time();
            
            // Réinitialiser les tentatives de connexion
            $stmt = $pdo->prepare("
                UPDATE admins 
                SET login_attempts = 0, 
                    account_locked_until = NULL,
                    last_login = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin['id']]);
            
            // Vérifier si le hash doit être mis à jour vers Argon2id
            if (defined('PASSWORD_ARGON2ID') && password_needs_rehash($admin['password'], PASSWORD_ARGON2ID)) {
                $new_hash = hash_password($password);
                $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $admin['id']]);
            }
            
            // Logger la connexion
            log_admin_action($admin['id'], 'LOGIN', 'Connexion au Preparation Admin Dashboard - ' . $admin['role_name']);
            
            return true;
        } else {
            // Incrémenter les tentatives de connexion échouées
            $new_attempts = ($admin['login_attempts'] ?? 0) + 1;
            
            // Verrouiller le compte après 5 tentatives
            if ($new_attempts >= 5) {
                $locked_until = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                $stmt = $pdo->prepare("
                    UPDATE admins 
                    SET login_attempts = ?, account_locked_until = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_attempts, $locked_until, $admin['id']]);
                error_log("Compte verrouillé pour 30 minutes: " . $email);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET login_attempts = ? WHERE id = ?");
                $stmt->execute([$new_attempts, $admin['id']]);
            }
            
            return false;
        }
    } catch (PDOException $e) {
        error_log("Erreur de connexion : " . $e->getMessage());
        return false;
    }
}

// Hasher un mot de passe avec Argon2id
function hash_password($password) {
    // Vérifier si Argon2id est disponible (PHP >= 7.3)
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,  // 64 MB de mémoire
            'time_cost' => 4,        // 4 itérations
            'threads' => 3           // 3 threads parallèles
        ]);
    } else {
        // Fallback sur Argon2i si Argon2id n'est pas disponible
        if (defined('PASSWORD_ARGON2I')) {
            return password_hash($password, PASSWORD_ARGON2I, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
        }
        // Fallback final sur BCrypt
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

// Vérifier un mot de passe
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Déconnexion
function logout_admin() {
    // Logger la déconnexion
    if (isset($_SESSION['admin_id'])) {
        log_admin_action($_SESSION['admin_id'], 'LOGOUT', 'Déconnexion du Preparation Admin Dashboard');
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
}

// Générer un token CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Vérifier le token CSRF
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Nettoyer les entrées
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Initialiser la session
secure_session_start();
?>
