<?php
/**
 * Authentification + CSRF — DG Admin Dashboard
 * Atlantech Shop — Directeur Général
 *
 * Le DG est stocké dans la table `admins` (avec admin_role_id = 8).
 * Sa session utilise des clés dédiées `dg_*` pour ne pas se mélanger avec
 * les autres back-offices qui utilisent `admin_id`.
 */

require_once __DIR__ . '/config.php';

// ─────────────────────────────────────────────────────────────────────────────
// Session sécurisée (cookie httponly, samesite Lax, nom dédié)
// ─────────────────────────────────────────────────────────────────────────────
function dg_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.use_only_cookies', 1);
    $cp = session_get_cookie_params();

    // Isolation du cookie sur le chemin du back-office DG.
    // Empêche le navigateur d'envoyer le cookie atlantech_dg sur les pages
    // marketing-admin ou site client → évite tout conflit de session.
    $script  = $_SERVER['SCRIPT_NAME'] ?? '';
    $dg_path = preg_match('#^(.*?/admins/dg-admin)/?#', $script, $m)
        ? $m[1] . '/'
        : ($cp['path'] ?: '/');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => $dg_path,
        'domain'   => $cp['domain'],
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('atlantech_dg');
    session_start();
}

// ─────────────────────────────────────────────────────────────────────────────
// État de session
// ─────────────────────────────────────────────────────────────────────────────
function is_dg_logged_in(): bool
{
    return !empty($_SESSION['dg_id']) && !empty($_SESSION['dg_role']) && $_SESSION['dg_role'] === 'dg';
}

/**
 * Garde de page : redirige vers login si pas connecté ou session expirée.
 */
function require_dg_auth(): void
{
    dg_session_start();
    if (!is_dg_logged_in()) {
        header('Location: ' . dg_base_url() . '/login.php'); exit;
    }
    // Expiration par inactivité
    if (
        isset($_SESSION['dg_last_activity']) &&
        (time() - $_SESSION['dg_last_activity']) > SESSION_LIFETIME
    ) {
        session_unset();
        session_destroy();
        header('Location: ' . dg_base_url() . '/login.php?expired=1'); exit;
    }
    $_SESSION['dg_last_activity'] = time();
}

/**
 * URL de base du back-office DG (utile pour les redirects depuis sous-dossiers).
 * Détecte automatiquement le chemin web.
 */
function dg_base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // ex : /atlantech-shop/admins/dg-admin/pages/admins-list.php
    if (preg_match('#^(.*?/admins/dg-admin)#', $script, $m)) {
        $base = $m[1];
    } else {
        $base = '';
    }
    return $base;
}

// ─────────────────────────────────────────────────────────────────────────────
// Login / Logout
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Tente de connecter un DG.
 *   - Vérifie email + password Argon2id
 *   - Vérifie admin_role_id = DG_ROLE_ID
 *   - Vérifie is_active = 1
 *   - Gère le lock-out (5 tentatives → 30 min)
 *   - Rehash Argon2id si nécessaire (mise à niveau transparente)
 */
function login_dg(string $email, string $password): array
{
    global $pdo;
    dg_session_start();

    $email = trim($email);
    if ($email === '' || $password === '') {
        return ['ok' => false, 'error' => 'Email et mot de passe requis.'];
    }

    try {
        $st = $pdo->prepare("
            SELECT id, full_name, name, email, password, phone, admin_role_id, is_active,
                   login_attempts, account_locked_until
            FROM admins
            WHERE email = ? AND admin_role_id = ?
            LIMIT 1
        ");
        $st->execute([$email, DG_ROLE_ID]);
        $dg = $st->fetch();

        if (!$dg) {
            return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
        }
        if (!$dg['is_active']) {
            return ['ok' => false, 'error' => 'Ce compte est désactivé.'];
        }
        if (!empty($dg['account_locked_until']) && strtotime($dg['account_locked_until']) > time()) {
            $mins = ceil((strtotime($dg['account_locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Compte verrouillé. Réessayez dans $mins minute(s)."];
        }

        if (!password_verify($password, $dg['password'])) {
            // Incrémenter les tentatives, verrouiller après 5
            $att  = (int)($dg['login_attempts'] ?? 0) + 1;
            $lock = $att >= 5 ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;
            $pdo->prepare("UPDATE admins SET login_attempts = ?, account_locked_until = ? WHERE id = ?")
                ->execute([$att, $lock, $dg['id']]);
            log_dg_action($dg['id'], 'login_failed', "Tentative #$att");
            return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
        }

        // Succès : régénérer l'ID de session, peupler la session
        session_regenerate_id(true);
        $_SESSION['dg_id']            = (int)$dg['id'];
        $_SESSION['dg_full_name']     = $dg['full_name'];
        $_SESSION['dg_name']          = $dg['name'];
        $_SESSION['dg_email']         = $dg['email'];
        $_SESSION['dg_role']          = 'dg';
        $_SESSION['dg_last_activity'] = time();

        // Reset compteur, last_login
        $pdo->prepare("UPDATE admins SET login_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?")
            ->execute([$dg['id']]);

        // Rehash Argon2id si paramètres ont changé
        if (defined('PASSWORD_ARGON2ID') && password_needs_rehash($dg['password'], PASSWORD_ARGON2ID,
            ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3])
        ) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID,
                ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
            $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")
                ->execute([$newHash, $dg['id']]);
        }

        log_dg_action($dg['id'], 'login_success', 'Connexion DG');
        return ['ok' => true];

    } catch (PDOException $e) {
        error_log('login_dg: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur technique. Réessayez.'];
    }
}

function logout_dg(): void
{
    dg_session_start();
    if (!empty($_SESSION['dg_id'])) {
        log_dg_action((int)$_SESSION['dg_id'], 'logout', 'Déconnexion DG');
    }
    session_unset();
    session_destroy();
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging des actions DG (réutilise admin_activity_logs si la table existe)
// ─────────────────────────────────────────────────────────────────────────────
function log_dg_action(int $adminId, string $action, string $description = '', ?string $module = 'dg'): void
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, module, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $adminId,
            $action,
            $module,
            $description,
            $_SERVER['REMOTE_ADDR']     ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (PDOException $e) {
        // Logger silencieusement — ne jamais bloquer l'action métier sur un échec de log
        error_log('log_dg_action skipped: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Hash de mot de passe (Argon2id + repli), mêmes paramètres que le reste du site
// ─────────────────────────────────────────────────────────────────────────────
function hash_password(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
    }
    if (defined('PASSWORD_ARGON2I')) {
        return password_hash($password, PASSWORD_ARGON2I, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
    }
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Génère un mot de passe temporaire lisible et cryptographiquement sûr
 * (alphabet réduit sans caractères ambigus : pas de 0/O, 1/l/I).
 * Utilisé UNIQUEMENT pour être communiqué une seule fois à un client
 * (cas extrême : client important injoignable par email/WhatsApp).
 * N'est JAMAIS stocké en clair — seul son hash Argon2id est écrit en base.
 */
function generate_temp_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $pw = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $alphabet[random_int(0, $max)];
    }
    return $pw;
}

// ─────────────────────────────────────────────────────────────────────────────
// CSRF (mêmes signatures que les autres back-offices)
// ─────────────────────────────────────────────────────────────────────────────
function generate_csrf_token(): string
{
    if (
        !isset($_SESSION['dg_csrf_token']) ||
        !isset($_SESSION['dg_csrf_token_time']) ||
        (time() - $_SESSION['dg_csrf_token_time']) > CSRF_TOKEN_LIFETIME
    ) {
        $_SESSION['dg_csrf_token']      = bin2hex(random_bytes(32));
        $_SESSION['dg_csrf_token_time'] = time();
    }
    return $_SESSION['dg_csrf_token'];
}

function verify_csrf_token($token): bool
{
    if (!isset($_SESSION['dg_csrf_token']) || !isset($_SESSION['dg_csrf_token_time'])) return false;
    if ((time() - $_SESSION['dg_csrf_token_time']) > CSRF_TOKEN_LIFETIME) return false;
    return is_string($token) && hash_equals($_SESSION['dg_csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        exit('Requête refusée : jeton CSRF invalide ou expiré. Rechargez la page.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8')
         . '">';
}

// Démarrer la session par défaut quand auth.php est inclus
dg_session_start();
