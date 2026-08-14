<?php
/**
 * Fonctions utilitaires pour Order-admin
 */

/**
 * Formater un montant en HTG
 */
function formatMoney($amount) {
    return number_format($amount, 2, '.', ',') . ' HTG';
}

/**
 * Formater une date
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Obtenir le label d'un statut de commande
 */
function getOrderStatusLabel($status) {
    $labels = [
        'pending' => 'En attente',
        'paid' => 'Payée',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée'
    ];
    return $labels[$status] ?? $status;
}

/**
 * Obtenir la classe CSS d'un statut
 */
function getOrderStatusClass($status) {
    $classes = [
        'pending' => 'warning',
        'paid' => 'success',
        'shipped' => 'info',
        'delivered' => 'primary',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

/**
 * Obtenir le label d'une priorité
 */
function getPriorityLabel($priority) {
    $labels = [
        'low' => 'Basse',
        'normal' => 'Normale',
        'high' => 'Haute',
        'urgent' => 'Urgente'
    ];
    return $labels[$priority] ?? $priority;
}

/**
 * Obtenir la classe CSS d'une priorité
 */
function getPriorityClass($priority) {
    $classes = [
        'low' => 'secondary',
        'normal' => 'info',
        'high' => 'warning',
        'urgent' => 'danger'
    ];
    return $classes[$priority] ?? 'secondary';
}

/**
 * Logger une activité
 */
function logOrderActivity($pdo, $admin_id, $action, $details, $order_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        $logDetails = $details;
        if ($order_id) {
            $logDetails .= " (Order ID: $order_id)";
        }
        
        $stmt->execute([
            $admin_id,
            $action,
            $logDetails,
            $_SERVER['REMOTE_ADDR']
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier si une commande peut être modifiée
 */
function canModifyOrder($status) {
    return !in_array($status, ['shipped', 'delivered', 'cancelled']);
}

/**
 * Obtenir les statuts disponibles pour une transition
 */
function getAvailableStatusTransitions($currentStatus) {
    $transitions = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => []
    ];
    
    return $transitions[$currentStatus] ?? [];
}

/**
 * Sanitizer une entrée
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Générer un numéro de commande unique
 */
function generateOrderNumber($prefix = 'ORD') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Calculer le temps écoulé depuis une date
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "À l'instant";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' h';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' j';
    } else {
        return date('d/m/Y', $timestamp);
    }
}

/**
 * Vérifier les permissions
 */
function hasPermission($action, $status = null) {
    // Pour l'instant, tous les order_admin ont toutes les permissions
    // Cette fonction peut être étendue pour des permissions plus granulaires
    return true;
}

/**
 * Obtenir les méthodes de paiement
 */
function getPaymentMethods() {
    return [
        'MonCash' => 'MonCash',
        'Zelle' => 'Zelle',
        'Bank' => 'Virement bancaire',
        'Cash' => 'Espèces'
    ];
}

/**
 * Obtenir les types de commande
 */
function getOrderTypes() {
    return [
        'regular' => 'Régulière',
        'subscription' => 'Abonnement',
        'pre_order' => 'Précommande',
        'gift' => 'Cadeau',
        'corporate' => 'Entreprise'
    ];
}

/**
 * Formater une adresse
 */
function formatAddress($address) {
    if (!$address) return 'N/A';
    
    // Si c'est du JSON
    if (isJson($address)) {
        $addr = json_decode($address, true);
        return implode(', ', array_filter([
            $addr['street'] ?? '',
            $addr['city'] ?? '',
            $addr['state'] ?? '',
            $addr['zip'] ?? ''
        ]));
    }
    
    return $address;
}

/**
 * Vérifier si une chaîne est du JSON valide
 */
function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Obtenir la couleur d'un type de commande
 */
function getOrderTypeClass($type) {
    $classes = [
        'regular' => 'info',
        'subscription' => 'primary',
        'pre_order' => 'warning',
        'gift' => 'success',
        'corporate' => 'secondary'
    ];
    return $classes[$type] ?? 'info';
}

/**
 * Envoyer une notification (placeholder)
 */
function sendNotification($type, $message, $orderId = null) {
    // Cette fonction peut être étendue pour envoyer des emails, SMS, etc.
    error_log("Notification [$type]: $message" . ($orderId ? " (Order: $orderId)" : ""));
    return true;
}
?>
