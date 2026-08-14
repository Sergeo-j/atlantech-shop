<?php
/**
 * Page de déconnexion
 * Atlantech Shop - Client Admin Dashboard
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Déconnexion
logout_admin();

// Redirection vers la page de connexion
header('Location: login.php?logout=1');
exit();
?>