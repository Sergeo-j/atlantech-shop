<?php
/**
 * Déconnexion - Order Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Déconnexion
logout_admin();

// Rediriger vers la page de connexion
header('Location: login.php?logout=1');
exit();
?>
