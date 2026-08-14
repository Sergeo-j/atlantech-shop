<?php
require_once __DIR__ . '/config.php';

function secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_only_cookies', 1);
    $cp = session_get_cookie_params();
    session_set_cookie_params(SESSION_LIFETIME, $cp['path'], $cp['domain'], false, true);
    session_name('atlantech_delivery');
    session_start();
}

function is_logged_in(): bool {
    if (!isset($_SESSION['delivery_id']) || !isset($_SESSION['delivery_role'])) {
        return false;
    }
    // Revérification BD : l'admin doit toujours exister, être actif, ET avoir le rôle 'delivery'
    global $pdo;
    try {
        $st = $pdo->prepare("
            SELECT 1 FROM admins a
            JOIN admin_roles ar ON a.admin_role_id = ar.id
            WHERE a.id = ? AND ar.role_name = 'delivery' AND a.is_active = 1
            LIMIT 1
        ");
        $st->execute([(int)$_SESSION['delivery_id']]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) {
        error_log('delivery-admin is_logged_in: ' . $e->getMessage());
        return false;
    }
}

function check_auth() {
    secure_session_start();
    if (!is_logged_in()) {
        header('Location: login.php'); exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset(); session_destroy();
        header('Location: login.php?expired=1'); exit;
    }
    $_SESSION['last_activity'] = time();
}

function login_delivery(string $email, string $password): bool {
    global $pdo;
    secure_session_start();
    try {
        $st = $pdo->prepare("
            SELECT a.id, a.name, a.email, a.password, a.is_active,
                   a.login_attempts, a.account_locked_until, ar.role_name
            FROM admins a
            LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
            WHERE a.email = ? AND ar.role_name = 'delivery'
            LIMIT 1
        ");
        $st->execute([$email]);
        $admin = $st->fetch();

        if (!$admin || !$admin['is_active']) return false;
        if ($admin['account_locked_until'] && strtotime($admin['account_locked_until']) > time()) return false;

        if (password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['delivery_id']    = $admin['id'];
            $_SESSION['delivery_name']  = $admin['name'];
            $_SESSION['delivery_email'] = $admin['email'];
            $_SESSION['delivery_role']  = $admin['role_name'];
            $_SESSION['last_activity']  = time();

            $pdo->prepare("UPDATE admins SET login_attempts=0, account_locked_until=NULL, last_login=NOW() WHERE id=?")
                ->execute([$admin['id']]);

            // Rehash Argon2ID si nécessaire
            if (defined('PASSWORD_ARGON2ID') && password_needs_rehash($admin['password'], PASSWORD_ARGON2ID)) {
                $pdo->prepare("UPDATE admins SET password=? WHERE id=?")
                    ->execute([password_hash($password, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>4,'threads'=>3]), $admin['id']]);
            }
            log_delivery_action($admin['id'], 'login_success', 'Connexion Delivery Admin');
            return true;
        }

        // Échec : incrémenter tentatives
        $attempts = ($admin['login_attempts'] ?? 0) + 1;
        $lock = $attempts >= 5 ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;
        $pdo->prepare("UPDATE admins SET login_attempts=?, account_locked_until=? WHERE id=?")
            ->execute([$attempts, $lock, $admin['id']]);
        log_delivery_action($admin['id'], 'login_failed', "Tentative $attempts");
        return false;

    } catch (PDOException $e) { error_log("login_delivery: ".$e->getMessage()); return false; }
}

/**
 * Génère / récupère le token CSRF de la session.
 * Utilisé par le JS de delivery-details.php pour les requêtes AJAX.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['_csrf_delivery'])) {
        $_SESSION['_csrf_delivery'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_delivery'];
}

secure_session_start();
?>
