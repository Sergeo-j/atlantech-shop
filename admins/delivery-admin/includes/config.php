<?php
require_once __DIR__ . '/../../../config/env.php';

if (!defined('DB_HOST'))         define('DB_HOST',         env('DB_HOST', 'localhost'));
if (!defined('DB_NAME'))         define('DB_NAME',         env('DB_NAME', 'atldb'));
if (!defined('DB_USER'))         define('DB_USER',         env('DB_USER', 'root'));
if (!defined('DB_PASS'))         define('DB_PASS',         env('DB_PASS', ''));
if (!defined('DB_CHARSET'))      define('DB_CHARSET',      env('DB_CHARSET', 'utf8mb4'));
if (!defined('SESSION_LIFETIME'))define('SESSION_LIFETIME', 3600);
if (!defined('SITE_URL'))        define('SITE_URL',        env('SITE_URL', 'http://localhost/atlantech-shop'));

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );
} catch (PDOException $e) {
    error_log("Delivery Admin DB: ".$e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion : ".$e->getMessage());
    }
    http_response_code(500);
    die("Erreur de connexion à la base de données");
}

function log_delivery_action($admin_id, $action, $details) {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) VALUES (?,?,?,?,NOW())")
            ->execute([$admin_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) { error_log("Log: ".$e->getMessage()); }
}
?>
