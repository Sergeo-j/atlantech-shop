<?php
/**
 * Fonctions Super Admin
 * Atlantech Shop - Super Admin Dashboard
 * AVEC CRÉATION ARGON2ID AUTOMATIQUE
 */

require_once __DIR__ . '/config.php';

// ===== GESTION DES ADMINISTRATEURS =====

// Obtenir tous les administrateurs
function get_all_admins($search = '', $role = '', $status = '') {
    global $pdo;
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($role)) {
        $where[] = "admin_role_id = ?";
        $params[] = $role;
    }
    
    if ($status !== '') {
        $where[] = "is_active = ?";
        $params[] = $status;
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.*,
                ar.role_name,
                ar.role_description,
                sa.full_name as created_by_name
            FROM admins a
            LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
            LEFT JOIN superadmins sa ON a.created_by_superadmin = sa.id
            $where_clause
            ORDER BY a.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_all_admins : " . $e->getMessage());
        return [];
    }
}

// Obtenir un admin par ID
function get_admin_by_id($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.*,
                ar.role_name,
                ar.role_description,
                sa.full_name as created_by_name
            FROM admins a
            LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
            LEFT JOIN superadmins sa ON a.created_by_superadmin = sa.id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur get_admin_by_id : " . $e->getMessage());
        return null;
    }
}

// CRÉER UN NOUVEL ADMIN AVEC ARGON2ID AUTOMATIQUE
function create_admin($data, $created_by_superadmin_id) {
    global $pdo;
    
    try {
        // HASH AUTOMATIQUE EN ARGON2ID
        $hashed_password = hash_password($data['password']);
        
        $stmt = $pdo->prepare("
            INSERT INTO admins 
            (full_name, name, email, password, phone, admin_role_id, 
             is_active, created_by_superadmin, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $data['full_name'],
            $data['name'],
            $data['email'],
            $hashed_password,  // ← MOT DE PASSE ARGON2ID
            $data['phone'],
            $data['role_id'],
            $created_by_superadmin_id
        ]);
        
        if ($result) {
            $admin_id = $pdo->lastInsertId();
            
            // Logger l'action
            log_superadmin_action(
                $created_by_superadmin_id,
                'CREATE_ADMIN',
                "Création de l'admin: {$data['email']} (ID: $admin_id)",
                'admins'
            );
            
            return ['success' => true, 'admin_id' => $admin_id];
        }
        
        return ['success' => false, 'error' => 'Erreur lors de la création'];
        
    } catch (PDOException $e) {
        error_log("Erreur create_admin : " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Mettre à jour un admin
function update_admin($id, $data, $updated_by_superadmin_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE admins 
            SET full_name = ?,
                name = ?,
                email = ?,
                phone = ?,
                admin_role_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $data['full_name'],
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['role_id'],
            $id
        ]);
        
        if ($result) {
            log_superadmin_action(
                $updated_by_superadmin_id,
                'UPDATE_ADMIN',
                "Modification de l'admin ID: $id",
                'admins'
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur update_admin : " . $e->getMessage());
        return false;
    }
}

// Réinitialiser le mot de passe d'un admin (ARGON2ID)
function reset_admin_password($admin_id, $new_password, $reset_by_superadmin_id) {
    global $pdo;
    
    try {
        // HASH EN ARGON2ID
        $hashed_password = hash_password($new_password);
        
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $admin_id]);
        
        if ($result) {
            log_superadmin_action(
                $reset_by_superadmin_id,
                'RESET_ADMIN_PASSWORD',
                "Réinitialisation du mot de passe de l'admin ID: $admin_id",
                'admins'
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur reset_admin_password : " . $e->getMessage());
        return false;
    }
}

// Toggle statut d'un admin
function toggle_admin_status($id, $toggled_by_superadmin_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE admins 
            SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
            WHERE id = ?
        ");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            log_superadmin_action(
                $toggled_by_superadmin_id,
                'TOGGLE_ADMIN_STATUS',
                "Changement du statut de l'admin ID: $id",
                'admins'
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur toggle_admin_status : " . $e->getMessage());
        return false;
    }
}

// Supprimer un admin
function delete_admin($id, $deleted_by_superadmin_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            log_superadmin_action(
                $deleted_by_superadmin_id,
                'DELETE_ADMIN',
                "Suppression de l'admin ID: $id",
                'admins'
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Erreur delete_admin : " . $e->getMessage());
        return false;
    }
}

// ===== GESTION DES RÔLES =====

// Obtenir tous les rôles
function get_all_roles() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY role_name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_all_roles : " . $e->getMessage());
        return [];
    }
}

// ===== STATISTIQUES =====

// Obtenir les statistiques du système
function get_system_stats() {
    global $pdo;
    
    try {
        // Total admins
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM admins");
        $total_admins = $stmt->fetch()['total'];
        
        // Admins actifs
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM admins WHERE is_active = 1");
        $active_admins = $stmt->fetch()['active'];
        
        // Admins inactifs
        $stmt = $pdo->query("SELECT COUNT(*) as inactive FROM admins WHERE is_active = 0");
        $inactive_admins = $stmt->fetch()['inactive'];
        
        // Total clients
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $total_users = $stmt->fetch()['total'];
        
        // Admins par rôle
        $stmt = $pdo->query("
            SELECT ar.role_name, COUNT(a.id) as count
            FROM admin_roles ar
            LEFT JOIN admins a ON ar.id = a.admin_role_id AND a.is_active = 1
            GROUP BY ar.id, ar.role_name
        ");
        $admins_by_role = $stmt->fetchAll();
        
        return [
            'total_admins' => $total_admins,
            'active_admins' => $active_admins,
            'inactive_admins' => $inactive_admins,
            'total_users' => $total_users,
            'admins_by_role' => $admins_by_role
        ];
    } catch (PDOException $e) {
        error_log("Erreur get_system_stats : " . $e->getMessage());
        return [
            'total_admins' => 0,
            'active_admins' => 0,
            'inactive_admins' => 0,
            'total_users' => 0,
            'admins_by_role' => []
        ];
    }
}

// Obtenir les derniers logs
function get_recent_logs($limit = 20) {
    global $pdo;
    
    try {
        // Logs du super admin
        $stmt = $pdo->prepare("
            SELECT 'superadmin' as source, sal.*, sa.full_name as user_name
            FROM superadmin_activity_logs sal
            INNER JOIN superadmins sa ON sal.superadmin_id = sa.id
            ORDER BY sal.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $superadmin_logs = $stmt->fetchAll();
        
        // Logs des admins
        $stmt = $pdo->prepare("
            SELECT 'admin' as source, aal.*, a.full_name as user_name
            FROM admin_activity_logs aal
            INNER JOIN admins a ON aal.admin_id = a.id
            ORDER BY aal.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $admin_logs = $stmt->fetchAll();
        
        // Fusionner et trier
        $all_logs = array_merge($superadmin_logs, $admin_logs);
        usort($all_logs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($all_logs, 0, $limit);
    } catch (PDOException $e) {
        error_log("Erreur get_recent_logs : " . $e->getMessage());
        return [];
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
    $time = date('H:i', $timestamp);
    
    return "$day $month $year à $time";
}

// Générer un mot de passe aléatoire sécurisé
function generate_random_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    return $password;
}
?>
