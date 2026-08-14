<?php
/**
 * Liste des Clients
 * Atlantech Shop - Client Admin Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification
check_auth();

// Paramètres de recherche et pagination
$search = clean_input($_GET['search'] ?? '');
$status_filter = clean_input($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;

// Traiter les actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $client_id = intval($_GET['id']);
    
    if ($action === 'toggle' && verify_csrf_token($_GET['token'] ?? '')) {
        if (toggle_client_status($client_id)) {
            log_admin_action('TOGGLE_STATUS', "Changement de statut du client ID: $client_id");
            header('Location: clients-list.php?success=status_changed');
            exit();
        }
    }
}

// Récupérer les clients
$result = get_all_clients($page, $per_page, $search, $status_filter);
$clients = $result['clients'];
$total_pages = $result['pages'];
$current_page = $result['current_page'];

// Variables pour le header
$page_title = 'Liste des Clients';
$current_page_menu = 'clients';

// Messages de succès
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'updated':
            $success_message = 'Client mis à jour avec succès';
            break;
        case 'status_changed':
            $success_message = 'Statut du client modifié avec succès';
            break;
    }
}

// Inclure le header
include __DIR__ . '/../includes/header.php';
?>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Barre de recherche et filtres -->
<div class="card">
    <form method="GET" action="" id="searchForm" class="search-filters">
        <div class="search-box">
            <input 
                type="text" 
                name="search" 
                id="searchClients"
                placeholder="Rechercher un client (nom, email, téléphone)..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <i class="fas fa-search"></i>
        </div>
        
        <select name="status" id="statusFilter" class="filter-select">
            <option value="">Tous les statuts</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
        </select>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Filtrer
        </button>
        
        <a href="clients-list.php" class="btn btn-secondary">
            <i class="fas fa-redo"></i>
            Réinitialiser
        </a>
    </form>
</div>

<!-- Liste des clients -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-users"></i>
            Clients (<?php echo $result['total']; ?>)
        </h2>
        <div class="card-actions">
            <button class="btn btn-secondary btn-sm" onclick="exportClients('csv')">
                <i class="fas fa-file-csv"></i>
                Exporter CSV
            </button>
            <button class="btn btn-secondary btn-sm" onclick="exportClients('pdf')">
                <i class="fas fa-file-pdf"></i>
                Exporter PDF
            </button>
        </div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Tier</th>
                    <th>Commandes</th>
                    <th>Points</th>
                    <th>Statut</th>
                    <th>Inscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                            <p>Aucun client trouvé</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong>#<?php echo $client['id']; ?></strong></td>
                            <td>
                                <strong style="color: var(--text-primary);">
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </strong>
                                <?php if ($client['email_verified']): ?>
                                    <i class="fas fa-check-circle" style="color: #00d9ff; font-size: 12px;" title="Email vérifié"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge <?php echo strtolower($client['account_tier']); ?>">
                                    <?php echo strtoupper($client['account_tier']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($client['total_orders']); ?></td>
                            <td>
                                <span style="color: #ffd700;">
                                    <i class="fas fa-star"></i> <?php echo number_format($client['loyalty_points']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($client['blocked']): ?>
                                    <span class="status-badge inactive">Bloqué</span>
                                <?php elseif ($client['is_active']): ?>
                                    <span class="status-badge active">Actif</span>
                                <?php else: ?>
                                    <span class="status-badge inactive">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($client['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Toggle Status Button -->
                                    <?php if (!$client['blocked']): ?>
                                        <button 
                                            onclick="toggleClientStatus(<?php echo $client['id']; ?>, <?php echo $client['is_active'] ? 'true' : 'false'; ?>)"
                                            class="btn-icon <?php echo $client['is_active'] ? 'success' : 'secondary'; ?>"
                                            title="<?php echo $client['is_active'] ? 'Actif - Cliquer pour désactiver' : 'Inactif - Cliquer pour activer'; ?>"
                                            id="toggle-btn-<?php echo $client['id']; ?>"
                                        >
                                            <i class="fas fa-toggle-<?php echo $client['is_active'] ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="btn-icon danger" title="Client bloqué - Modifier dans la page d'édition">
                                            <i class="fas fa-ban"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- View Button -->
                                    <a 
                                        href="client-view.php?id=<?php echo $client['id']; ?>" 
                                        class="btn-icon primary" 
                                        title="Voir les détails"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Edit Button -->
                                    <a 
                                        href="client-edit.php?id=<?php echo $client['id']; ?>" 
                                        class="btn-icon warning" 
                                        title="Modifier"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <button onclick="changePage(<?php echo $current_page - 1; ?>)" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
            <?php endif; ?>
            
            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                <button 
                    onclick="changePage(<?php echo $i; ?>)" 
                    class="pagination-btn <?php echo $i === $current_page ? 'active' : ''; ?>"
                >
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
                <button onclick="changePage(<?php echo $current_page + 1; ?>)" class="pagination-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.btn-icon {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.btn-icon.primary {
    background: rgba(0, 217, 255, 0.1);
    color: var(--neon-cyan);
    border: 1px solid var(--neon-cyan);
}

.btn-icon.success {
    background: rgba(0, 255, 136, 0.1);
    color: var(--neon-green);
    border: 1px solid var(--neon-green);
}

.btn-icon.warning {
    background: rgba(255, 215, 0, 0.1);
    color: #ffd700;
    border: 1px solid #ffd700;
}

.btn-icon.danger {
    background: rgba(255, 0, 0, 0.1);
    color: #ff0000;
    border: 1px solid #ff0000;
}

.btn-icon.secondary {
    background: rgba(128, 128, 128, 0.1);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}

.btn-icon:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}
</style>

<script>
// Générer un token CSRF pour les actions
const csrfToken = '<?php echo generate_csrf_token(); ?>';

/**
 * Toggle rapide du statut client (AJAX)
 */
function toggleClientStatus(clientId, currentStatus) {
    const action = currentStatus ? 'désactiver' : 'activer';
    const message = `Voulez-vous vraiment ${action} ce client ?`;
    
    if (!confirm(message)) {
        return;
    }
    
    // Désactiver le bouton pendant la requête
    const button = document.getElementById(`toggle-btn-${clientId}`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    // Préparer les données
    const formData = new FormData();
    formData.append('client_id', clientId);
    formData.append('action', currentStatus ? 'deactivate' : 'activate');
    formData.append('csrf_token', csrfToken);
    
    // Envoi AJAX
    fetch('toggle-client-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            
            // Actualiser la page après 1 seconde
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showAlert('error', data.message);
            
            // Réactiver le bouton
            if (button) {
                button.disabled = false;
                updateToggleButton(button, currentStatus);
            }
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('error', 'Une erreur est survenue lors de la mise à jour du statut');
        
        // Réactiver le bouton
        if (button) {
            button.disabled = false;
            updateToggleButton(button, currentStatus);
        }
    });
}

/**
 * Mettre à jour le contenu du bouton toggle
 */
function updateToggleButton(button, isActive) {
    if (isActive) {
        button.innerHTML = '<i class="fas fa-toggle-on"></i>';
        button.className = 'btn-icon success';
        button.title = 'Actif - Cliquer pour désactiver';
    } else {
        button.innerHTML = '<i class="fas fa-toggle-off"></i>';
        button.className = 'btn-icon secondary';
        button.title = 'Inactif - Cliquer pour activer';
    }
}

/**
 * Afficher une alerte personnalisée
 */
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease-out;';
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(alertDiv);
    
    // Supprimer après 3 secondes
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}

function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

function exportClients(format) {
    // TODO: Implémenter l'export
    alert('Export ' + format.toUpperCase() + ' en cours de développement...');
}
</script>

<?php
// Inclure le footer
include __DIR__ . '/../includes/footer.php';
?>