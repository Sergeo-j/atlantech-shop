<?php
/**
 * Système d'authentification Super Admin
 * Atlantech Shop - Super Admin Dashboard
 * AVEC ARGON2ID
 */

require_once __DIR__ . '/config.php';

// Démarrer la session de manière sécurisée
function secure_session_start() {
    // Ne pas démarrer si session déjà existante
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $session_name = 'atlantech_superadmin';
    $secure = false; // Mettre true si HTTPS
    $httponly = true;

    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);

    session_name($session_name);
    session_start();
    session_regenerate_id(true);
}

// Démarrer la session automatiquement
secure_session_start();

// Vérifier si l'utilisateur est connecté en tant que SUPER ADMIN
function is_superadmin_logged_in() {
    return isset($_SESSION['superadmin_id']) && 
           isset($_SESSION['superadmin_role']) && 
           $_SESSION['superadmin_role'] === 'superadmin';
}

// Vérifier si un ADMIN normal est connecté
function is_logged_in() {
    return isset($_SESSION['admin_id']) && 
           isset($_SESSION['admin_role_id']);
}

// Vérifier l'authentification Super Admin
function check_superadmin_auth() {
    if (!is_superadmin_logged_in()) {
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

// FONCTION DE HASHAGE ARGON2ID
function hash_password($password) {
    // Vérifier si Argon2id est disponible (PHP >= 7.2)
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

/**
 * Génère un mot de passe temporaire lisible et cryptographiquement sûr
 * (alphabet réduit sans caractères ambigus : pas de 0/O, 1/l/I).
 * Utilisé UNIQUEMENT pour être communiqué une seule fois à un client dans
 * un cas extrême (client important injoignable par email). N'est jamais
 * stocké en clair — seul son hash Argon2id est écrit en base.
 */
function generate_temp_password($length = 12) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $pw = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $alphabet[random_int(0, $max)];
    }
    return $pw;
}

// Connexion du Super Admin
function login_superadmin($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, password, is_active 
            FROM superadmins 
            WHERE email = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $superadmin = $stmt->fetch();
        
        if ($superadmin && verify_password($password, $superadmin['password'])) {
            // Régénérer l'ID de session pour éviter la fixation
            session_regenerate_id(true);
            
            // Stocker les informations dans la session
            $_SESSION['superadmin_id'] = $superadmin['id'];
            $_SESSION['superadmin_name'] = $superadmin['full_name'];
            $_SESSION['superadmin_email'] = $superadmin['email'];
            $_SESSION['superadmin_role'] = 'superadmin';
            $_SESSION['last_activity'] = time();
            
            // Vérifier si le hash doit être mis à jour vers Argon2id
            if (password_needs_rehash($superadmin['password'], PASSWORD_ARGON2ID)) {
                $new_hash = hash_password($password);
                $stmt = $pdo->prepare("UPDATE superadmins SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $superadmin['id']]);
            }
            
            // Mettre à jour la dernière connexion
            $stmt = $pdo->prepare("UPDATE superadmins SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$superadmin['id']]);
            
            // Logger la connexion
            log_superadmin_action($superadmin['id'], 'LOGIN', 'Connexion au dashboard Super Admin');
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Erreur de connexion Super Admin : " . $e->getMessage());
        return false;
    }
}

// ===== NOUVELLE FONCTION: Connexion Admin Normal =====
function login_admin($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.id, a.full_name, a.name, a.email, a.password, a.is_active, 
                   a.admin_role_id, a.login_attempts, a.account_locked_until,
                   ar.role_name
            FROM admins a
            LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
            WHERE a.email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            return false;
        }
        
        // Vérifier si le compte est verrouillé
        if ($admin['account_locked_until'] && strtotime($admin['account_locked_until']) > time()) {
            error_log("Compte verrouillé jusqu'à: " . $admin['account_locked_until']);
            return false;
        }
        
        // Vérifier si le compte est actif
        if (!$admin['is_active']) {
            error_log("Compte inactif pour: " . $email);
            return false;
        }
        
        // Vérifier le mot de passe
        if (verify_password($password, $admin['password'])) {
            // Régénérer l'ID de session
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
            log_admin_action($admin['id'], 'LOGIN', 'Connexion au dashboard Admin');
            
            return true;
        } else {
            // Incrémenter les tentatives de connexion échouées
            $new_attempts = $admin['login_attempts'] + 1;
            
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
        error_log("Erreur de connexion Admin : " . $e->getMessage());
        return false;
    }
}

// Déconnexion
function logout_superadmin() {
    // Logger la déconnexion
    if (isset($_SESSION['superadmin_id'])) {
        log_superadmin_action($_SESSION['superadmin_id'], 'LOGOUT', 'Déconnexion du dashboard Super Admin');
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

// Déconnexion Admin
function logout_admin() {
    // Logger la déconnexion
    if (isset($_SESSION['admin_id'])) {
        log_admin_action($_SESSION['admin_id'], 'LOGOUT', 'Déconnexion du dashboard Admin');
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

// Logger une action du super admin
function log_superadmin_action($superadmin_id, $action, $description = '', $module = 'system') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO superadmin_activity_logs 
            (superadmin_id, action, module, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $superadmin_id,
            $action,
            $module,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Erreur log_superadmin_action : " . $e->getMessage());
        return false;
    }
}

// Logger une action d'un admin normal
function log_admin_action($admin_id, $action, $description = '', $table_affected = null, $record_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, description, table_affected, record_id, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $admin_id,
            $action,
            $description,
            $table_affected,
            $record_id,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Erreur log_admin_action : " . $e->getMessage());
        return false;
    }
}
