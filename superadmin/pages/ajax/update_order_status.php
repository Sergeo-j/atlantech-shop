<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

check_superadmin_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../manage_orders.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header('Location: ../manage_orders.php?error=csrf');
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';
$allowed = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
$redirect = $_POST['redirect'] ?? 'manage_orders.php';

if ($order_id && in_array($new_status, $allowed)) {
    try {
        $extra = '';
        if ($new_status === 'delivered') {
            $extra = ", completed_at = NOW()";
        } elseif ($new_status === 'cancelled') {
            $extra = ", cancelled_at = NOW()";
        }

        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() $extra WHERE id = ?")->execute([$new_status, $order_id]);

        log_superadmin_action($_SESSION['superadmin_id'], 'UPDATE_ORDER_STATUS', "Commande ID $order_id → $new_status", 'orders');
    } catch(Exception $e) {
        error_log($e->getMessage());
    }
}

header('Location: ../' . basename($redirect));
exit;
