<?php
/**
 * Configuration AtlanTech — FICHIER EXEMPLE
 * Copier ce fichier en config.php et remplir vos vraies valeurs
 */

// Base de données
define('DB_HOST',     'localhost');
define('DB_NAME',     'atldb');
define('DB_USER',     'root');         // Changer
define('DB_PASS',     '');             // Changer
define('DB_CHARSET',  'utf8mb4');

// Site
define('SITE_URL',    'http://localhost/atlantech-shop');
define('SITE_NAME',   'AtlanTech');

// Session
session_start();

// Connexion MySQLi
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$mysqli->set_charset(DB_CHARSET);
if ($mysqli->connect_error) {
    die('Erreur de connexion : ' . $mysqli->connect_error);
}

// Fonctions utilitaires
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit();
}
