<?php
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Détruire le cookie "Se souvenir de moi"
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time()-42000, '/');
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: login.php');
exit();
?>
