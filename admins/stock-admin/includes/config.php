<?php
/**
 * Configuration Database
 * Stock Admin - Atlantech Shop
 */

// Charger le loader .env (racine du projet : 3 niveaux au-dessus)
require_once __DIR__ . '/../../../config/env.php';

// Configuration base de données (lue depuis .env / variables système)
$host     = env('DB_HOST', '127.0.0.1');
$dbname   = env('DB_NAME', 'atldb');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$charset  = env('DB_CHARSET', 'utf8mb4');

// Connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur PDO stock-admin : " . $e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion : " . $e->getMessage());
    }
    http_response_code(500);
    die("Impossible de se connecter à la base de données. Veuillez réessayer plus tard.");
}

// Timezone
date_default_timezone_set('America/Port-au-Prince');
