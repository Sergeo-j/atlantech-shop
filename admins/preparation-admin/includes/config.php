<?php
/**
 * Configuration - Order Admin
 * Atlantech Shop
 */

// Charger le loader .env (racine du projet : 3 niveaux au-dessus)
require_once __DIR__ . '/../../../config/env.php';

// Configuration de la base de données (lue depuis .env / variables système)
if (!defined('DB_HOST'))    define('DB_HOST',    env('DB_HOST', 'localhost'));
if (!defined('DB_NAME'))    define('DB_NAME',    env('DB_NAME', 'atldb'));
if (!defined('DB_USER'))    define('DB_USER',    env('DB_USER', 'root'));
if (!defined('DB_PASS'))    define('DB_PASS',    env('DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Configuration de la session
if (!defined('SESSION_LIFETIME'))    define('SESSION_LIFETIME', 3600); // 1 heure
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', 3600);

// Connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion DB: " . $e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion : " . $e->getMessage());
    }
    http_response_code(500);
    die("Erreur de connexion à la base de données");
}

// Fonction de logging
// NOTE : la vraie table admin_logs utilise les colonnes user_id/description
//        (pas admin_id/details). On adapte en conséquence + user_agent.
function log_admin_action($admin_id, $action, $details, $order_id = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admin_logs
                (user_id, action, description, table_affected, record_id, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            (int)$admin_id,
            $action,
            $details,
            $order_id !== null ? 'orders' : null,
            $order_id,
            $_SERVER['REMOTE_ADDR']           ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (PDOException $e) {
        error_log("Log error: " . $e->getMessage());
    }
}

/**
 * Enregistre un changement de statut dans order_status_history.
 * Safe si la table n'existe pas encore.
 */
function record_order_status_change(
    int $order_id,
    ?string $old_status,
    string $new_status,
    int $admin_id,
    string $admin_name,
    string $note = ''
): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO order_status_history
                (order_id, old_status, new_status, changed_by_type, changed_by_id, changed_by_name, note, ip_address)
             VALUES (?, ?, ?, 'admin', ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $order_id,
            $old_status,
            $new_status,
            $admin_id,
            $admin_name,
            $note !== '' ? $note : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log("record_order_status_change: " . $e->getMessage());
        return false;
    }
}
?>
