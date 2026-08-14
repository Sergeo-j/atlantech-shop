<?php
/**
 * Logout Product Admin
 * Atlantech Shop
 */

session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit();
