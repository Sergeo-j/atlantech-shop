<?php
require_once 'includes/auth.php';
if (isset($_SESSION['delivery_id'])) {
    log_delivery_action($_SESSION['delivery_id'], 'logout', 'Déconnexion Delivery Admin');
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
