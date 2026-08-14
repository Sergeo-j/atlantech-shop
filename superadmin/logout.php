<?php
/**
 * Déconnexion Super Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Déconnecter le super admin
logout_superadmin();

// Rediriger vers la page de login avec message
header('Location: login.php?logout=1');
exit();
?>