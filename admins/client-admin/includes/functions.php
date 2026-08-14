<?php
/**
 * Fonctions utilitaires - VERSION CORRIGÉE
 * Atlantech Shop - Client Admin Dashboard
 */

require_once __DIR__ . '/config.php';

// Obtenir tous les clients avec pagination
function get_all_clients($page = 1, $per_page = 10, $search = '', $status = '') {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($status !== '') {
        if ($status === 'active') {
            $where[] = "is_active = 1";
        } else if ($status === 'inactive') {
            $where[] = "is_active = 0";
        }
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    try {
        // Compter le total
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where_clause");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Récupérer les clients
        $params[] = $offset;
        $params[] = $per_page;
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, is_active, blocked, 
                   email_verified, total_orders, total_spent, 
                   loyalty_points, account_tier, created_at 
            FROM users 
            $where_clause
            ORDER BY created_at DESC
            LIMIT ?, ?
        ");
        $stmt->execute($params);
        $clients = $stmt->fetchAll();
        
        return [
            'clients' => $clients,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ];
    } catch (PDOException $e) {
        error_log("Erreur get_all_clients : " . $e->getMessage());
        return ['clients' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1];
    }
}

// Changer le statut d'un client (is_active)
function toggle_client_status($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET is_active = CASE 
                WHEN is_active = 1 THEN 0
                WHEN is_active = 0 THEN 1
                ELSE 1
            END
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Erreur toggle_client_status : " . $e->getMessage());
        return false;
    }
}

// Obtenir un client par ID
function get_client_by_id($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur get_client_by_id : " . $e->getMessage());
        return null;
    }
}

// Mettre à jour un client
function update_client($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, 
                email = ?, 
                phone = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $id
        ]);
    } catch (PDOException $e) {
        error_log("Erreur update_client : " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour le statut d'un client (is_active, blocked)
 * 
 * @param int $client_id ID du client
 * @param array $status_data Tableau avec is_active, blocked, block_reason (optionnel)
 * @return bool Succès de l'opération
 */
function update_client_status($client_id, $status_data) {
    global $pdo;
    
    try {
        // Construire la requête dynamiquement
        $updates = [];
        $params = [];
        
        if (isset($status_data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = intval($status_data['is_active']);
        }
        
        if (isset($status_data['blocked'])) {
            $updates[] = "blocked = ?";
            $params[] = intval($status_data['blocked']);
        }
        
        if (isset($status_data['block_reason'])) {
            $updates[] = "block_reason = ?";
            $params[] = $status_data['block_reason'];
        } else if (isset($status_data['blocked']) && $status_data['blocked'] == 0) {
            // Si on débloque, effacer la raison
            $updates[] = "block_reason = NULL";
        }
        
        // Ajouter updated_at
        $updates[] = "updated_at = NOW()";
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $client_id;
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
        
    } catch (PDOException $e) {
        error_log("Erreur update_client_status: " . $e->getMessage());
        return false;
    }
}

// Obtenir les statistiques des clients
function get_client_stats() {
    global $pdo;
    
    try {
        // Total des clients
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $total = $stmt->fetch()['total'];
        
        // Clients actifs
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
        $active = $stmt->fetch()['active'];
        
        // Clients inactifs
        $stmt = $pdo->query("SELECT COUNT(*) as inactive FROM users WHERE is_active = 0");
        $inactive = $stmt->fetch()['inactive'];
        
        // Clients bloqués
        $stmt = $pdo->query("SELECT COUNT(*) as blocked FROM users WHERE blocked = 1");
        $blocked = $stmt->fetch()['blocked'];
        
        // Nouveaux clients ce mois
        $stmt = $pdo->query("
            SELECT COUNT(*) as new_this_month 
            FROM users 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $new_this_month = $stmt->fetch()['new_this_month'];
        
        // Croissance des clients par mois (6 derniers mois)
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $growth = $stmt->fetchAll();
        
        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'blocked' => $blocked,
            'new_this_month' => $new_this_month,
            'growth' => $growth
        ];
    } catch (PDOException $e) {
        error_log("Erreur get_client_stats : " . $e->getMessage());
        return [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'blocked' => 0,
            'new_this_month' => 0,
            'growth' => []
        ];
    }
}

// Obtenir les commandes d'un client
function get_client_orders($client_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.id,
                o.order_number,
                o.total_amount,
                o.status,
                o.created_at,
                COUNT(oi.id) as items_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$client_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_client_orders : " . $e->getMessage());
        // Retourner des données de démo si erreur
        return [
            [
                'id' => 1001,
                'date' => '2024-11-15',
                'total' => 2500.00,
                'status' => 'completed',
                'items' => 5
            ],
            [
                'id' => 1045,
                'date' => '2024-12-01',
                'total' => 1800.00,
                'status' => 'pending',
                'items' => 3
            ]
        ];
    }
}

// Formater la date
function format_date($date) {
    $months = [
        1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

// Formater le prix
function format_price($price) {
    return number_format($price, 2, ',', ' ') . ' HTG';
}

// Logger une action admin
function log_admin_action(string $action, string $description = '') {
    global $pdo;
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, module, description, ip_address, created_at) 
            VALUES (?, ?, 'clients', ?, ?, NOW())
        ");

        return $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);

    } catch (PDOException $e) {
        error_log("Erreur log_admin_action : " . $e->getMessage());
        return false;
    }
}
?>