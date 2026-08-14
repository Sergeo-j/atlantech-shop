<?php
/**
 * Configuration — DG Admin Dashboard
 * Atlantech Shop — Directeur Général
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
if (!defined('SESSION_LIFETIME'))    define('SESSION_LIFETIME', 3600);       // 1 h
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', 1800);    // 30 min

// Connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('PDO connect error (dg-admin): ' . $e->getMessage());
    if (env('APP_ENV', 'production') === 'development') {
        die("Erreur de connexion : " . $e->getMessage());
    }
    http_response_code(500);
    die("Erreur de connexion à la base de données");
}

date_default_timezone_set('America/Port-au-Prince');

// ─────────────────────────────────────────────────────────────────────────────
// DG_ROLE_ID : lecture DYNAMIQUE depuis la BD
//   - Évite tout conflit avec d'autres installations où l'id 'dg' pourrait
//     différer (l'AUTO_INCREMENT donne un id différent selon l'ordre de
//     création des rôles).
//   - Si le rôle n'existe pas (= migration non appliquée), on déclenche une
//     erreur explicite plutôt que de laisser le code échouer silencieusement.
// ─────────────────────────────────────────────────────────────────────────────
if (!defined('DG_ROLE_ID')) {
    try {
        $st = $pdo->prepare("SELECT id FROM admin_roles WHERE role_name = 'dg' AND is_active = 1 LIMIT 1");
        $st->execute();
        $row = $st->fetch();
        if ($row && (int)$row['id'] > 0) {
            define('DG_ROLE_ID', (int)$row['id']);
        } else {
            // Rôle non trouvé : la migration n'est pas appliquée
            error_log('DG_ROLE_ID: rôle "dg" introuvable dans admin_roles. Appliquez la migration 2026_05_30_dg_admin_setup.sql.');
            define('DG_ROLE_ID', -1); // valeur sentinelle invalide
        }
    } catch (PDOException $e) {
        error_log('DG_ROLE_ID lookup error: ' . $e->getMessage());
        define('DG_ROLE_ID', -1);
    }
}
