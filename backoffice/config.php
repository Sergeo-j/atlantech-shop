<?php
// Charger le loader .env
require_once __DIR__ . '/../config/env.php';

// Configuration de la base de données (lue depuis .env / variables système)
if (!defined('DB_HOST'))    define('DB_HOST',    env('DB_HOST', 'localhost'));
if (!defined('DB_PORT'))    define('DB_PORT',    env('DB_PORT', '3306'));
if (!defined('DB_NAME'))    define('DB_NAME',    env('DB_NAME', 'atldb'));
if (!defined('DB_USER'))    define('DB_USER',    env('DB_USER', 'root'));
if (!defined('DB_PASS'))    define('DB_PASS',    env('DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('PDO connect error (backoffice/config.php): ' . $e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
    http_response_code(500);
    die("Erreur interne. Veuillez réessayer plus tard.");
}

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction pour vérifier si l'utilisateur est connecté (à adapter selon votre système)
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Fonction pour rediriger si non connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Configuration des chemins
define('UPLOAD_DIR', '../uploads/products/');
define('BASE_URL', 'http://localhost/atlantech-shop/');
define('ADMIN_URL', BASE_URL . 'admin/');

// Créer le dossier d'upload s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
?>