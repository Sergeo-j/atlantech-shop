<?php
/**
 * Configuration — Marketing Admin Dashboard
 * Atlantech Shop — Marketing
 */
require_once __DIR__ . '/../../../config/env.php';

if (!defined('DB_HOST'))    define('DB_HOST',    env('DB_HOST', 'localhost'));
if (!defined('DB_NAME'))    define('DB_NAME',    env('DB_NAME', 'atldb'));
if (!defined('DB_USER'))    define('DB_USER',    env('DB_USER', 'root'));
if (!defined('DB_PASS'))    define('DB_PASS',    env('DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

if (!defined('SESSION_LIFETIME'))    define('SESSION_LIFETIME', 3600);
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', 1800);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('PDO connect error (marketing-admin): ' . $e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion : " . $e->getMessage());
    }
    http_response_code(500);
    die("Erreur de connexion à la base de données");
}

date_default_timezone_set('America/Port-au-Prince');

// MARKETING_ROLE_ID dynamique
if (!defined('MARKETING_ROLE_ID')) {
    try {
        $st = $pdo->prepare("SELECT id FROM admin_roles WHERE role_name = 'marketing' AND is_active = 1 LIMIT 1");
        $st->execute();
        $row = $st->fetch();
        define('MARKETING_ROLE_ID', $row ? (int)$row['id'] : -1);
    } catch (PDOException $e) {
        define('MARKETING_ROLE_ID', -1);
    }
}
