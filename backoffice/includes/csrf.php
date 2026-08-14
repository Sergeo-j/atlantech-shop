<?php
/**
 * Helper CSRF — Back-office (backoffice/)
 * Mêmes fonctions que admin/includes/csrf.php pour cohérence.
 *
 * Usage :
 *   require_once __DIR__ . '/includes/csrf.php';
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
 *   <form method="POST"><?= csrf_field() ?> ...
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['backoffice_csrf_token'])) {
    $_SESSION['backoffice_csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['backoffice_csrf_token'])) {
            $_SESSION['backoffice_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['backoffice_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $sent   = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $stored = $_SESSION['backoffice_csrf_token'] ?? '';
        if ($sent === '' || $stored === '' || !hash_equals($stored, $sent)) {
            http_response_code(403);
            exit('Requête refusée : jeton CSRF invalide ou expiré. Rechargez la page.');
        }
    }
}
