<?php
/**
 * Paramètres Product Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title = 'Paramètres';
$current_page = 'settings';

$success_message = '';
$error_message = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Token de sécurité invalide';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $full_name = clean_input($_POST['full_name'] ?? '');
                $email = clean_input($_POST['email'] ?? '');
                
                if (empty($full_name) || empty($email)) {
                    $error_message = 'Tous les champs sont requis';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE admins 
                            SET full_name = ?, email = ? 
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([$full_name, $email, $_SESSION['admin_id']])) {
                            $_SESSION['admin_name'] = $full_name;
                            log_admin_action('profile_updated', 'Profil mis à jour');
                            $success_message = 'Profil mis à jour avec succès';
                        }
                    } catch (PDOException $e) {
                        $error_message = 'Erreur lors de la mise à jour';
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'Tous les champs sont requis';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'Les mots de passe ne correspondent pas';
                } elseif (strlen($new_password) < 8) {
                    $error_message = 'Le mot de passe doit contenir au moins 8 caractères';
                } else {
                    try {
                        // Vérifier le mot de passe actuel
                        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
                        $stmt->execute([$_SESSION['admin_id']]);
                        $admin = $stmt->fetch();
                        
                        if ($admin && password_verify($current_password, $admin['password'])) {
                            // Hasher le nouveau mot de passe
                            $new_hash = password_hash($new_password, PASSWORD_ARGON2ID);
                            
                            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                            if ($stmt->execute([$new_hash, $_SESSION['admin_id']])) {
                                log_admin_action('password_changed', 'Mot de passe modifié');
                                $success_message = 'Mot de passe modifié avec succès';
                            }
                        } else {
                            $error_message = 'Mot de passe actuel incorrect';
                        }
                    } catch (PDOException $e) {
                        $error_message = 'Erreur lors de la modification';
                    }
                }
                break;
        }
    }
}

// Récupérer les infos admin
try {
    $stmt = $pdo->prepare("
        SELECT a.*, r.name as role_name 
        FROM admins a 
        LEFT JOIN admin_roles r ON a.admin_role_id = r.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Admin non trouvé, déconnecter
        session_destroy();
        header('Location: ../login.php?error=session_expired');
        exit();
    }
    
    // Statistiques d'activité
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as actions_count 
        FROM admin_activity_logs 
        WHERE admin_id = ? AND module = 'products'
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $actions_count = $stmt->fetch()['actions_count'] ?? 0;
    
    // Dernières actions
    $stmt = $pdo->prepare("
        SELECT action, description, created_at 
        FROM admin_activity_logs 
        WHERE admin_id = ? AND module = 'products' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $recent_actions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur settings: " . $e->getMessage());
    $admin = null;
    $actions_count = 0;
    $recent_actions = [];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.settings-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
}

.settings-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
}

.settings-section:last-child {
    border-bottom: none;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--neon-cyan);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-card {
    background: linear-gradient(135deg, rgba(138, 43, 226, 0.1), rgba(0, 217, 255, 0.1));
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--text-secondary);
    font-size: 13px;
}

.info-value {
    color: var(--text-primary);
    font-weight: 600;
}

.activity-item {
    padding: 15px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 12px;
}

.activity-action {
    font-weight: 600;
    color: var(--neon-cyan);
    margin-bottom: 5px;
}

.activity-description {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.activity-time {
    font-size: 11px;
    color: var(--text-secondary);
}

.stat-box {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}

.stat-box-value {
    font-size: 36px;
    font-weight: 700;
    color: var(--neon-purple);
    margin: 10px 0;
}

.stat-box-label {
    font-size: 13px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.danger-zone {
    background: rgba(255, 0, 0, 0.05);
    border: 2px solid rgba(255, 0, 0, 0.3);
    border-radius: 12px;
    padding: 20px;
}

.danger-zone-title {
    color: #ff0000;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 1024px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <h1><i class="fas fa-cog"></i> Paramètres</h1>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="settings-grid">
    <!-- Colonne principale -->
    <div>
        <!-- Informations du compte -->
        <div class="card">
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>
                    Informations du Compte
                </h3>
                
                <div class="info-card">
                    <div class="info-row">
                        <span class="info-label">Nom complet</span>
                        <span class="info-value"><?php echo htmlspecialchars($admin['full_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Rôle</span>
                        <span class="info-value"><?php echo htmlspecialchars($admin['role_name'] ?? 'Product Admin'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Dernière connexion</span>
                        <span class="info-value">
                            <?php 
                            if ($admin && !empty($admin['last_login'])) {
                                echo date('d/m/Y H:i', strtotime($admin['last_login']));
                            } else {
                                echo 'Jamais';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Compte créé le</span>
                        <span class="info-value">
                            <?php 
                            if ($admin && !empty($admin['created_at'])) {
                                echo date('d/m/Y', strtotime($admin['created_at']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!$admin): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Impossible de charger les informations du compte.
                    </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label class="form-label">Nom complet</label>
                        <input 
                            type="text" 
                            name="full_name" 
                            class="form-input"
                            value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-input"
                            value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Mettre à jour le Profil
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <!-- Changer le mot de passe -->
            <div class="settings-section">
                <h3 class="section-title">
                    <i class="fas fa-lock"></i>
                    Sécurité
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe actuel</label>
                        <input 
                            type="password" 
                            name="current_password" 
                            class="form-input"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input 
                            type="password" 
                            name="new_password" 
                            class="form-input"
                            required
                            minlength="8"
                        >
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">
                            Minimum 8 caractères
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmer le mot de passe</label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            class="form-input"
                            required
                            minlength="8"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        Changer le Mot de Passe
                    </button>
                </form>
            </div>
            
            <!-- Zone de danger -->
            <div class="danger-zone">
                <div class="danger-zone-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Zone de Danger
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 15px; font-size: 13px;">
                    Pour désactiver votre compte ou supprimer vos données, veuillez contacter un Super Admin.
                </p>
                <a href="mailto:admin@atlantech-shop.com" class="btn btn-danger">
                    <i class="fas fa-envelope"></i>
                    Contacter un Super Admin
                </a>
            </div>
        </div>
    </div>
    
    <!-- Colonne latérale -->
    <div>
        <!-- Statistiques -->
        <div class="stat-box">
            <div class="stat-box-label">Total Actions</div>
            <div class="stat-box-value">
                <?php echo number_format($actions_count); ?>
            </div>
            <div style="font-size: 12px; color: var(--text-secondary);">
                Depuis la création du compte
            </div>
        </div>
        
        <!-- Activité récente -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size: 16px; font-weight: 600;">
                    <i class="fas fa-history"></i>
                    Activité Récente
                </h3>
            </div>
            
            <div style="padding: 20px;">
                <?php if (empty($recent_actions)): ?>
                    <p style="text-align: center; color: var(--text-secondary); padding: 20px;">
                        Aucune activité récente
                    </p>
                <?php else: ?>
                    <?php foreach ($recent_actions as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-action">
                                <?php 
                                $action_labels = [
                                    'product_created' => 'Produit créé',
                                    'product_updated' => 'Produit modifié',
                                    'product_deleted' => 'Produit supprimé',
                                    'category_created' => 'Catégorie créée',
                                    'category_updated' => 'Catégorie modifiée',
                                    'brand_created' => 'Marque créée',
                                    'profile_updated' => 'Profil mis à jour',
                                    'password_changed' => 'Mot de passe changé'
                                ];
                                echo $action_labels[$activity['action']] ?? $activity['action'];
                                ?>
                            </div>
                            <?php if ($activity['description']): ?>
                                <div class="activity-description">
                                    <?php echo htmlspecialchars($activity['description']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="activity-time">
                                <i class="fas fa-clock"></i>
                                <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <a href="logs.php" class="btn btn-secondary" style="width: 100%; margin-top: 15px;">
                        <i class="fas fa-list"></i>
                        Voir Tous les Logs
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Liens utiles -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 style="font-size: 16px; font-weight: 600;">
                    <i class="fas fa-link"></i>
                    Liens Utiles
                </h3>
            </div>
            
            <div style="padding: 20px;">
                <a href="dashboard.php" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-primary); margin-bottom: 10px; transition: all 0.3s;">
                    <i class="fas fa-home" style="color: var(--neon-cyan);"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="products-list.php" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-primary); margin-bottom: 10px; transition: all 0.3s;">
                    <i class="fas fa-box" style="color: var(--neon-purple);"></i>
                    <span>Mes Produits</span>
                </a>
                
                <a href="stock-alerts.php" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-primary); transition: all 0.3s;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--neon-gold);"></i>
                    <span>Alertes Stock</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
