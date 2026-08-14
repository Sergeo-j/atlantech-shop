<?php
require_once __DIR__ . '/includes/auth.php';
logout_dg();
header('Location: login.php?logout=1');
exit;
