<?php
/**
 * Authentication Functions
 * Product Admin - Atlantech Shop
 */

// Démarrer la session avec un nom unique pour ce module
if (session_status() === PHP_SESSION_NONE) {
    session_name('atlantech_product_admin');
    session_start();
}

/**
 * Vérifier si l'utilisateur est connecté ET a bien le rôle 'product'.
 * Revérification BD à chaque requête (defense-in-depth).
 */
function is_logged_in() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role_id'])) {
        return false;
    }
    global $pdo;
    if (!isset($pdo)) {
        return false;
    }
    try {
        $st = $pdo->prepare("
            SELECT 1 FROM admins a
            JOIN admin_roles ar ON a.admin_role_id = ar.id
            WHERE a.id = ? AND ar.role_name = 'product' AND a.is_active = 1
            LIMIT 1
        ");
        $st->execute([(int)$_SESSION['admin_id']]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) {
        error_log('product-admin is_logged_in: ' . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier l'authentification et rediriger si nécessaire.
 */
function check_auth() {
    if (!is_logged_in()) {
        $_SESSION = [];
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Déconnecter l'utilisateur.
 */
function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('atlantech_product_admin');
        session_start();
    }
    $_SESSION = [];
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

/**
 * Vérifier un rôle (legacy — gardé pour compat).
 */
function has_role($role_id) {
    return isset($_SESSION['admin_role_id']) && $_SESSION['admin_role_id'] == $role_id;
}

/**
 * Générer / récupérer le token CSRF de la session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifier un token CSRF posté.
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}
