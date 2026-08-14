<?php
/**
 * AtlanTech — Preparation Admin : garde d'accès aux pages internes
 *
 * À inclure depuis chaque page interne (index.php, preparation-details.php).
 * Si l'utilisateur n'est pas connecté, redirige vers login.php.
 *
 * Délègue la logique à check_auth() défini dans auth.php pour ne pas
 * dupliquer la gestion des sessions.
 */

if (!function_exists('check_auth')) {
    require_once __DIR__ . '/auth.php';
}

// Démarrer la session avec le bon nom (atlantech_preparation_admin)
if (function_exists('secure_session_start')) {
    secure_session_start();
}

// check_auth() redirige vers login.php si pas connecté
check_auth();
