<?php
/**
 * Modifier un Client
 * Atlantech Shop - Client Admin Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification
check_auth();

// Obtenir l'ID du client
$client_id = intval($_GET['id'] ?? 0);

if ($client_id === 0) {
    header('Location: clients-list.php');
    exit();
}

// Récupérer les informations du client
$client = get_client_by_id($client_id);

if (!$client) {
    header('Location: clients-list.php?error=not_found');
    exit();
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $action = $_POST['action'] ?? 'update_info';
        
        if ($action === 'update_info') {
            // Mise à jour des informations de base
            $name = clean_input($_POST['name'] ?? '');
            $email = clean_input($_POST['email'] ?? '');
            $phone = clean_input($_POST['phone'] ?? '');
            
            // Validation
            if (empty($name)) {
                $error = 'Le nom est requis';
            } elseif (empty($email)) {
                $error = 'L\'email est requis';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email invalide';
            } elseif (empty($phone)) {
                $error = 'Le téléphone est requis';
            } else {
                // Mettre à jour le client
                $data = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone
                ];
                
                if (update_client($client_id, $data)) {
                    log_admin_action('UPDATE_CLIENT', "Mise à jour du client ID: $client_id");
                    $success = 'Informations mises à jour avec succès';
                    // Recharger les données
                    $client = get_client_by_id($client_id);
                } else {
                    $error = 'Erreur lors de la mise à jour';
                }
            }
        } elseif ($action === 'update_status') {
            // Mise à jour du statut
            $is_active = ($_POST['is_active'] ?? '0') == '1' ? 1 : 0;
            $blocked   = ($_POST['blocked'] ?? '0') == '1' ? 1 : 0;

            $block_reason = clean_input($_POST['block_reason'] ?? '');
            
            // Validation logique : un client ne peut pas être actif ET bloqué
            if ($is_active && $blocked) {
                $error = 'Un client ne peut pas être actif et bloqué en même temps';
            } elseif ($blocked && empty($block_reason)) {
                $error = 'Veuillez indiquer la raison du blocage';
            } else {
                $status_data = [
                    'is_active' => $is_active,
                    'blocked' => $blocked
                ];
                
                // Ajouter la raison si bloqué
                if ($blocked) {
                    $status_data['block_reason'] = $block_reason;
                }
                
                if (update_client_status($client_id, $status_data)) {
                    $status_text = $blocked ? 'bloqué' : ($is_active ? 'activé' : 'désactivé');
                    log_admin_action('UPDATE_CLIENT_STATUS', "Client ID: $client_id - Statut: $status_text" . ($blocked ? " - Raison: $block_reason" : ""));
                    $success = 'Statut du client mis à jour avec succès';
                    // Recharger les données
                    $client = get_client_by_id($client_id);
                } else {
                    $error = 'Erreur lors de la mise à jour du statut';
                }
            }
        }
    }
}

// Variables pour le header
$page_title = 'Modifier Client';
$current_page = 'clients';

// Inclure le header
include __DIR__ . '/../includes/header.php';
?>

<!-- Bouton retour -->
<div style="margin-bottom: 20px;">
    <a href="client-view.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Retour aux détails
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- Formulaire de modification des informations -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-edit"></i>
            Informations du Client
        </h2>
    </div>
    
    <form method="POST" action="" onsubmit="return validateClientForm()">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="action" value="update_info">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
            <!-- Colonne gauche -->
            <div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i>
                        Nom Complet *
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name"
                        class="form-input" 
                        value="<?php echo htmlspecialchars($client['name']); ?>"
                        required
                        placeholder="Entrez le nom complet"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        Adresse Email *
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email"
                        class="form-input" 
                        value="<?php echo htmlspecialchars($client['email']); ?>"
                        required
                        placeholder="exemple@email.com"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-phone"></i>
                        Numéro de Téléphone *
                    </label>
                    <input 
                        type="tel" 
                        name="phone" 
                        id="phone"
                        class="form-input" 
                        value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                        required
                        placeholder="+509 37 12 34 56"
                    >
                </div>
            </div>
            
            <!-- Colonne droite -->
            <div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-info-circle"></i>
                        Informations du Compte
                    </label>
                    <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border: 1px solid var(--border-color);">
                        <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">ID Client:</span>
                                <span style="color: var(--text-primary); font-weight: 600;">#<?php echo $client['id']; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Tier:</span>
                                <span>
                                    <span class="status-badge <?php echo strtolower($client['account_tier']); ?>">
                                        <?php echo strtoupper($client['account_tier']); ?>
                                    </span>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Points fidélité:</span>
                                <span style="color: #ffd700; font-weight: 600;">
                                    <i class="fas fa-star"></i> <?php echo number_format($client['loyalty_points'] ?? 0); ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Inscrit le:</span>
                                <span style="color: var(--text-primary); font-weight: 600;"><?php echo format_date($client['created_at']); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Statut actuel:</span>
                                <span>
                                    <?php if ($client['blocked']): ?>
                                        <span class="status-badge inactive">Bloqué</span>
                                    <?php elseif ($client['is_active']): ?>
                                        <span class="status-badge active">Actif</span>
                                    <?php else: ?>
                                        <span class="status-badge inactive">Inactif</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Total commandes:</span>
                                <span style="color: var(--text-primary); font-weight: 600;"><?php echo number_format($client['total_orders'] ?? 0); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary);">Total dépensé:</span>
                                <span style="color: #00ff88; font-weight: 600;"><?php echo format_price($client['total_spent'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Boutons d'action -->
        <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <a href="client-view.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Annuler
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Enregistrer les Informations
            </button>
        </div>
    </form>
</div>

<!-- Gestion du Statut du Client -->
<div class="card" style="margin-top: 25px;">
    <div class="card-header" style="background: linear-gradient(135deg, rgba(138, 43, 226, 0.1), rgba(75, 0, 130, 0.1));">
        <h2 class="card-title">
            <i class="fas fa-shield-alt"></i>
            Gestion du Statut du Client
        </h2>
    </div>
    
    <form method="POST" action="" onsubmit="return validateStatusForm()">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="action" value="update_status">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
            <!-- Options de statut -->
            <div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-toggle-on"></i>
                        Statut du Compte
                    </label>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <!-- Option Actif -->
                        <label style="display: flex; align-items: center; padding: 15px; background: rgba(0, 255, 136, 0.05); border: 2px solid <?php echo ($client['is_active'] && !$client['blocked']) ? 'var(--neon-green)' : 'transparent'; ?>; border-radius: 8px; cursor: pointer; transition: all 0.3s;">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                id="is_active"
                                value="1"
                                <?php echo ($client['is_active'] && !$client['blocked']) ? 'checked' : ''; ?>
                                onchange="updateStatusOptions()"
                                style="width: 20px; height: 20px; margin-right: 12px; cursor: pointer;"
                            >
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--neon-green); margin-bottom: 5px;">
                                    <i class="fas fa-check-circle"></i>
                                    Compte Actif
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    Le client peut se connecter et passer des commandes
                                </div>
                            </div>
                        </label>
                        
                        <!-- Option Bloqué -->
                        <label style="display: flex; align-items: center; padding: 15px; background: rgba(255, 0, 0, 0.05); border: 2px solid <?php echo $client['blocked'] ? '#ff0000' : 'transparent'; ?>; border-radius: 8px; cursor: pointer; transition: all 0.3s;">
                            <input 
                                type="checkbox" 
                                name="blocked" 
                                id="blocked"
                                value="1"
                                <?php echo $client['blocked'] ? 'checked' : ''; ?>
                                onchange="updateStatusOptions()"
                                style="width: 20px; height: 20px; margin-right: 12px; cursor: pointer;"
                            >
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #ff0000; margin-bottom: 5px;">
                                    <i class="fas fa-ban"></i>
                                    Compte Bloqué
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    Le client ne peut plus accéder à son compte
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Raison du blocage (visible si bloqué) -->
                <div class="form-group" id="block_reason_group" style="<?php echo $client['blocked'] ? '' : 'display: none;'; ?>">
                    <label class="form-label">
                        <i class="fas fa-comment-alt"></i>
                        Raison du Blocage *
                    </label>
                    <textarea 
                        name="block_reason" 
                        id="block_reason"
                        class="form-input" 
                        rows="4"
                        placeholder="Indiquez la raison du blocage (fraude, abus, violation des conditions, etc.)"
                        style="resize: vertical;"
                    ><?php echo htmlspecialchars($client['block_reason'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Informations de statut -->
            <div>
                <div style="padding: 20px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border: 1px solid var(--border-color);">
                    <h3 style="color: var(--neon-cyan); font-size: 15px; margin-bottom: 15px;">
                        <i class="fas fa-info-circle"></i>
                        Informations Importantes
                    </h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px; font-size: 13px;">
                        <div style="padding: 12px; background: rgba(0, 217, 255, 0.05); border-left: 3px solid var(--neon-cyan); border-radius: 4px;">
                            <strong style="color: var(--neon-cyan); display: block; margin-bottom: 5px;">
                                <i class="fas fa-lightbulb"></i> Compte Actif
                            </strong>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.5;">
                                Le client peut se connecter, consulter ses commandes et effectuer de nouveaux achats.
                            </p>
                        </div>
                        
                        <div style="padding: 12px; background: rgba(255, 215, 0, 0.05); border-left: 3px solid #ffd700; border-radius: 4px;">
                            <strong style="color: #ffd700; display: block; margin-bottom: 5px;">
                                <i class="fas fa-pause-circle"></i> Compte Inactif
                            </strong>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.5;">
                                Le client ne peut plus passer de commandes mais peut consulter son historique.
                            </p>
                        </div>
                        
                        <div style="padding: 12px; background: rgba(255, 0, 0, 0.05); border-left: 3px solid #ff0000; border-radius: 4px;">
                            <strong style="color: #ff0000; display: block; margin-bottom: 5px;">
                                <i class="fas fa-ban"></i> Compte Bloqué
                            </strong>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.5;">
                                Le client ne peut plus accéder à son compte. Toute tentative de connexion sera refusée.
                            </p>
                        </div>
                        
                        <div style="padding: 12px; background: rgba(138, 43, 226, 0.05); border-left: 3px solid var(--neon-purple); border-radius: 4px;">
                            <strong style="color: var(--neon-purple); display: block; margin-bottom: 5px;">
                                <i class="fas fa-shield-alt"></i> Sécurité
                            </strong>
                            <p style="color: var(--text-secondary); margin: 0; line-height: 1.5;">
                                Toutes les modifications de statut sont enregistrées dans les logs système avec votre identifiant.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Boutons d'action -->
        <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <button type="button" onclick="resetStatusForm()" class="btn btn-secondary">
                <i class="fas fa-undo"></i>
                Réinitialiser
            </button>
            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, var(--neon-purple), var(--neon-cyan));">
                <i class="fas fa-shield-alt"></i>
                Mettre à Jour le Statut
            </button>
        </div>
    </form>
</div>

<!-- Informations supplémentaires -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 25px;">
    <div style="padding: 20px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px;">
        <h3 style="color: var(--neon-cyan); font-size: 16px; margin-bottom: 15px;">
            <i class="fas fa-shield-alt"></i>
            Sécurité
        </h3>
        <p style="color: var(--text-secondary); font-size: 13px; line-height: 1.6;">
            Toutes les modifications sont enregistrées dans les logs système et peuvent être auditées par le super administrateur.
        </p>
    </div>
    
    <div style="padding: 20px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px;">
        <h3 style="color: var(--neon-purple); font-size: 16px; margin-bottom: 15px;">
            <i class="fas fa-bell"></i>
            Notifications
        </h3>
        <p style="color: var(--text-secondary); font-size: 13px; line-height: 1.6;">
            Le client recevra un email de notification si des modifications importantes sont apportées à son compte.
        </p>
    </div>
    
    <div style="padding: 20px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px;">
        <h3 style="color: var(--neon-green); font-size: 16px; margin-bottom: 15px;">
            <i class="fas fa-history"></i>
            Historique
        </h3>
        <p style="color: var(--text-secondary); font-size: 13px; line-height: 1.6;">
            Consultez l'historique complet des modifications dans la section logs d'activité.
        </p>
    </div>
</div>

<script>
function validateClientForm() {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    if (name === '') {
        alert('Le nom est requis');
        return false;
    }
    
    if (email === '') {
        alert('L\'email est requis');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Email invalide');
        return false;
    }
    
    if (phone === '') {
        alert('Le téléphone est requis');
        return false;
    }
    
    return confirm('Êtes-vous sûr de vouloir enregistrer ces modifications ?');
}

function updateStatusOptions() {
    const isActive = document.getElementById('is_active');
    const blocked = document.getElementById('blocked');
    const blockReasonGroup = document.getElementById('block_reason_group');
    
    // Si bloqué est coché, décocher actif
    if (blocked.checked) {
        isActive.checked = false;
        blockReasonGroup.style.display = 'block';
    } else {
        blockReasonGroup.style.display = 'none';
    }
    
    // Si actif est coché, décocher bloqué
    if (isActive.checked) {
        blocked.checked = false;
        blockReasonGroup.style.display = 'none';
    }
}

function validateStatusForm() {
    const isActive = document.getElementById('is_active').checked;
    const blocked = document.getElementById('blocked').checked;
    const blockReason = document.getElementById('block_reason').value.trim();
    
    // Vérifier qu'au moins une option est sélectionnée
    if (!isActive && !blocked) {
        alert('Veuillez sélectionner un statut pour le compte');
        return false;
    }
    
    // Si bloqué, vérifier la raison
    if (blocked && blockReason === '') {
        alert('Veuillez indiquer la raison du blocage');
        document.getElementById('block_reason').focus();
        return false;
    }
    
    // Confirmation
    let message = '';
    if (blocked) {
        message = '⚠️ ATTENTION ⚠️\n\nVous êtes sur le point de BLOQUER ce client.\n\nLe client ne pourra plus accéder à son compte.\n\nConfirmer ?';
    } else if (isActive) {
        message = 'Activer le compte de ce client ?';
    } else {
        message = 'Désactiver le compte de ce client ?';
    }
    
    return confirm(message);
}

function resetStatusForm() {
    // Recharger la page pour réinitialiser le formulaire
    window.location.reload();
}
</script>

<?php
// Inclure le footer
include __DIR__ . '/../includes/footer.php';
?>