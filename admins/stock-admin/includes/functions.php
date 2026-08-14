<?php
/**
 * Functions Stock Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/config.php';

/**
 * Nettoyer les inputs
 */
function clean_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Logger une action admin
 */
function log_admin_action(string $action, string $description = '') {
    global $pdo;
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, module, description, ip_address, created_at) 
            VALUES (?, ?, 'stock', ?, ?, NOW())
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

/**
 * Enregistrer un mouvement de stock
 */
function create_stock_movement($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Insérer le mouvement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements 
            (product_id, type, quantity, unit_price, total_value, reason, reference, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['product_id'],
            $data['type'], // 'in', 'out', 'adjust'
            $data['quantity'],
            $data['unit_price'] ?? 0,
            $data['total_value'] ?? 0,
            $data['reason'] ?? null,
            $data['reference'] ?? null,
            $_SESSION['admin_id']
        ]);
        
        // Mettre à jour le stock du produit
        // 'in' et 'adjust' augmentent le stock, 'out' le diminue
        if ($data['type'] === 'in' || $data['type'] === 'adjust') {
            $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        } else {
            // type = 'out' : on s'assure de ne pas descendre sous 0
            $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
        }
        $stmt->execute([$data['quantity'], $data['product_id']]);
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur create_stock_movement : " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les mouvements de stock
 */
function get_stock_movements($page = 1, $per_page = 20, $filters = []) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    
    $where = [];
    $params = [];
    
    if (!empty($filters['type'])) {
        $where[] = "sm.type = ?";
        $params[] = $filters['type'];
    }
    
    if (!empty($filters['product_id'])) {
        $where[] = "sm.product_id = ?";
        $params[] = $filters['product_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(sm.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(sm.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    try {
        // Compter le total
        $count_sql = "SELECT COUNT(*) as total FROM stock_movements sm $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Récupérer les mouvements (LIMIT/OFFSET castés en int, non bindés car PDO strict)
        $sql = "SELECT
                    sm.*,
                    p.name as product_name,
                    p.sku as product_sku,
                    p.image as product_image,
                    a.full_name as admin_name
                FROM stock_movements sm
                LEFT JOIN products p ON sm.product_id = p.id
                LEFT JOIN admins a ON sm.created_by = a.id
                $where_clause
                ORDER BY sm.created_at DESC
                LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movements = $stmt->fetchAll();
        
        return [
            'movements' => $movements,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ];
    } catch (PDOException $e) {
        error_log("Erreur get_stock_movements : " . $e->getMessage());
        return ['movements' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1];
    }
}

/**
 * Obtenir le stock total par produit
 */
function get_inventory_report() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                p.id,
                p.name,
                p.sku,
                p.image,
                p.stock,
                p.price,
                (p.stock * p.price) as total_value,
                c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            ORDER BY total_value DESC
        ");
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_inventory_report : " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les statistiques de stock
 */
function get_stock_statistics() {
    global $pdo;
    
    try {
        // Valeur totale du stock
        $stmt = $pdo->query("
            SELECT SUM(stock * price) as total_value 
            FROM products 
            WHERE is_active = 1
        ");
        $total_value = $stmt->fetch()['total_value'] ?? 0;
        
        // Nombre de produits
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
        $total_products = $stmt->fetch()['count'];
        
        // Entrées du mois
        $stmt = $pdo->query("
            SELECT SUM(quantity) as total 
            FROM stock_movements 
            WHERE type = 'in' 
            AND MONTH(created_at) = MONTH(NOW())
            AND YEAR(created_at) = YEAR(NOW())
        ");
        $entries_month = $stmt->fetch()['total'] ?? 0;
        
        // Sorties du mois
        $stmt = $pdo->query("
            SELECT SUM(quantity) as total 
            FROM stock_movements 
            WHERE type = 'out' 
            AND MONTH(created_at) = MONTH(NOW())
            AND YEAR(created_at) = YEAR(NOW())
        ");
        $exits_month = $stmt->fetch()['total'] ?? 0;
        
        // Alertes stock
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM products 
            WHERE stock <= stock_threshold AND is_active = 1
        ");
        $stock_alerts = $stmt->fetch()['count'];
        
        return [
            'total_value' => $total_value,
            'total_products' => $total_products,
            'entries_month' => $entries_month,
            'exits_month' => $exits_month,
            'stock_alerts' => $stock_alerts
        ];
        
    } catch (PDOException $e) {
        error_log("Erreur get_stock_statistics : " . $e->getMessage());
        return [
            'total_value' => 0,
            'total_products' => 0,
            'entries_month' => 0,
            'exits_month' => 0,
            'stock_alerts' => 0
        ];
    }
}

/**
 * Obtenir tous les produits actifs
 */
function get_active_products() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, name, sku, stock, price 
            FROM products 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_active_products : " . $e->getMessage());
        return [];
    }
}
