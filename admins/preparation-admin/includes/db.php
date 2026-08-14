<?php
// Charger le loader .env (racine du projet : 3 niveaux au-dessus)
require_once __DIR__ . '/../../../config/env.php';

// Configuration de la base de données (lue depuis .env / variables système)
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'atldb'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . env('DB_CHARSET', 'utf8mb4'),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion : " . $e->getMessage());
    }
    http_response_code(500);
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}
?>
