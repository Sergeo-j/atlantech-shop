<?php
/**
 * Authentification + CSRF — Marketing Admin
 * Le marketing admin est dans la table `admins` avec admin_role_id = MARKETING_ROLE_ID.
 * Session : clés `mkt_*` pour éviter conflits avec les autres back-offices.
 */
require_once __DIR__ . '/config.php';

function mkt_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_only_cookies', 1);
    $cp = session_get_cookie_params();

    // Isolation du cookie sur le chemin du back-office Marketing.
    // Évite tout mélange avec dg-admin ou le site client.
    $script   = $_SERVER['SCRIPT_NAME'] ?? '';
    $mkt_path = preg_match('#^(.*?/admins/marketing-admin)/?#', $script, $m)
        ? $m[1] . '/'
        : ($cp['path'] ?: '/');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => $mkt_path,
        'domain'   => $cp['domain'],
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('atlantech_marketing');
    session_start();
}

function is_mkt_logged_in(): bool
{
    return !empty($_SESSION['mkt_id']) && ($_SESSION['mkt_role'] ?? '') === 'marketing';
}

function mkt_base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('#^(.*?/admins/marketing-admin)#', $script, $m)) {
        $base = $m[1];
    } else {
        $base = '';
    }
    return $base;
}

function require_mkt_auth(): void
{
    mkt_session_start();
    if (!is_mkt_logged_in()) {
        header('Location: ' . mkt_base_url() . '/login.php');
        exit;
    }
    if (
        isset($_SESSION['mkt_last_activity']) &&
        (time() - $_SESSION['mkt_last_activity']) > SESSION_LIFETIME
    ) {
        session_unset(); session_destroy();
        header('Location: ' . mkt_base_url() . '/login.php?expired=1');
        exit;
    }
    $_SESSION['mkt_last_activity'] = time();
}

function login_mkt(string $email, string $password): array
{
    global $pdo;
    mkt_session_start();
    $email = trim($email);
    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Email et mot de passe requis.'];
    }
    try {
        $st = $pdo->prepare("
            SELECT id, full_name, name, email, password, admin_role_id, is_active,
                   login_attempts, account_locked_until
            FROM admins
            WHERE email = ? AND admin_role_id = ?
            LIMIT 1
        ");
        $st->execute([$email, MARKETING_ROLE_ID]);
        $u = $st->fetch();
        if (!$u)                                   return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
        if (!$u['is_active'])                      return ['ok' => false, 'error' => 'Ce compte est désactivé.'];
        if (!empty($u['account_locked_until']) && strtotime($u['account_locked_until']) > time()) {
            $m = ceil((strtotime($u['account_locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Compte verrouillé. Réessayez dans $m min."];
        }
        if (!password_verify($password, $u['password'])) {
            $att  = (int)($u['login_attempts'] ?? 0) + 1;
            $lock = $att >= 5 ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;
            $pdo->prepare("UPDATE admins SET login_attempts = ?, account_locked_until = ? WHERE id = ?")
                ->execute([$att, $lock, $u['id']]);
            log_mkt_action($u['id'], 'login_failed', "Tentative #$att");
            return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
        }
        session_regenerate_id(true);
        $_SESSION['mkt_id']            = (int)$u['id'];
        $_SESSION['mkt_full_name']     = $u['full_name'];
        $_SESSION['mkt_name']          = $u['name'];
        $_SESSION['mkt_email']         = $u['email'];
        $_SESSION['mkt_role']          = 'marketing';
        $_SESSION['mkt_last_activity'] = time();
        $pdo->prepare("UPDATE admins SET login_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?")
            ->execute([$u['id']]);
        log_mkt_action($u['id'], 'login_success', 'Connexion Marketing');
        return ['ok' => true];
    } catch (PDOException $e) {
        error_log('login_mkt: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur technique.'];
    }
}

function logout_mkt(): void
{
    mkt_session_start();
    if (!empty($_SESSION['mkt_id'])) {
        log_mkt_action((int)$_SESSION['mkt_id'], 'logout', 'Déconnexion Marketing');
    }
    session_unset(); session_destroy();
}

function log_mkt_action(int $adminId, string $action, string $description = '', ?string $module = 'marketing'): void
{
    global $pdo;
    try {
        $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, module, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $adminId, $action, $module, $description,
            $_SERVER['REMOTE_ADDR']     ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (PDOException $e) {
        error_log('log_mkt_action skipped: ' . $e->getMessage());
    }
}

// CSRF (mêmes signatures que les autres back-offices)
function generate_csrf_token(): string
{
    if (
        !isset($_SESSION['mkt_csrf_token']) ||
        !isset($_SESSION['mkt_csrf_token_time']) ||
        (time() - $_SESSION['mkt_csrf_token_time']) > CSRF_TOKEN_LIFETIME
    ) {
        $_SESSION['mkt_csrf_token']      = bin2hex(random_bytes(32));
        $_SESSION['mkt_csrf_token_time'] = time();
    }
    return $_SESSION['mkt_csrf_token'];
}

function verify_csrf_token($token): bool
{
    if (!isset($_SESSION['mkt_csrf_token']) || !isset($_SESSION['mkt_csrf_token_time'])) return false;
    if ((time() - $_SESSION['mkt_csrf_token_time']) > CSRF_TOKEN_LIFETIME) return false;
    return is_string($token) && hash_equals($_SESSION['mkt_csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        exit('Requête refusée : jeton CSRF invalide ou expiré.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

mkt_session_start();
