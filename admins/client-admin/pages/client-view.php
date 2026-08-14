<?php
/**
 * Voir un Client
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

// Récupérer les commandes du client
$orders = get_client_orders($client_id);

// Variables pour le header
$page_title = 'Détails Client';
$current_page = 'clients';

// Inclure le header
include __DIR__ . '/../includes/header.php';
?>

<!-- Bouton retour -->
<div style="margin-bottom: 20px;">
    <a href="clients-list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Retour à la liste
    </a>
</div>

<!-- Informations du client -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-user"></i>
            Informations du Client
        </h2>
        <div class="card-actions">
            <a href="client-edit.php?id=<?php echo $client['id']; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i>
                Modifier
            </a>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; padding: 10px 0;">
        <!-- Informations personnelles -->
        <div>
            <h3 style="color: var(--neon-cyan); margin-bottom: 20px; font-size: 18px;">
                <i class="fas fa-id-card"></i>
                Informations Personnelles
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-cyan);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">ID Client</div>
                    <div style="color: var(--text-primary); font-weight: 600;">#<?php echo $client['id']; ?></div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-cyan);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Nom</div>
                    <div style="color: var(--text-primary); font-weight: 600;"><?php echo htmlspecialchars($client['name']); ?></div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-cyan);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Email</div>
                    <div style="color: var(--text-primary); font-weight: 600;">
                        <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" style="color: var(--neon-cyan);">
                            <?php echo htmlspecialchars($client['email']); ?>
                        </a>
                    </div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-cyan);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Téléphone</div>
                    <div style="color: var(--text-primary); font-weight: 600;">
                        <a href="tel:<?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?>" style="color: var(--neon-cyan);">
                            <?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statut et activité -->
        <div>
            <h3 style="color: var(--neon-cyan); margin-bottom: 20px; font-size: 18px;">
                <i class="fas fa-chart-line"></i>
                Statut & Activité
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-purple);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Statut du Compte</div>
                    <div>
                        <?php if ($client['blocked']): ?>
                            <span class="status-badge inactive">Bloqué</span>
                        <?php elseif ($client['is_active']): ?>
                            <span class="status-badge active">Actif</span>
                        <?php else: ?>
                            <span class="status-badge inactive">Inactif</span>
                        <?php endif; ?>
                        
                        <?php if ($client['email_verified']): ?>
                            <i class="fas fa-check-circle" style="color: #00ff88; margin-left: 10px;" title="Email vérifié"></i>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-purple);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Tier du Compte</div>
                    <div>
                        <span class="status-badge <?php echo strtolower($client['account_tier']); ?>">
                            <?php echo strtoupper($client['account_tier']); ?>
                        </span>
                    </div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-purple);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Date d'inscription</div>
                    <div style="color: var(--text-primary); font-weight: 600;"><?php echo format_date($client['created_at']); ?></div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-purple);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Dernière activité</div>
                    <div style="color: var(--text-primary); font-weight: 600;">
                        <?php echo isset($client['last_login']) ? format_date($client['last_login']) : 'Jamais connecté'; ?>
                    </div>
                </div>
                
                <div style="padding: 15px; background: rgba(255, 255, 255, 0.02); border-radius: 8px; border-left: 3px solid var(--neon-purple);">
                    <div style="color: var(--text-secondary); font-size: 12px; margin-bottom: 5px;">Points de fidélité</div>
                    <div style="color: var(--text-primary); font-weight: 600;">
                        <span style="color: #ffd700;">
                            <i class="fas fa-star"></i> <?php echo number_format($client['loyalty_points'] ?? 0); ?> points
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques des commandes -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #00d9ff, #a855f7);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-content" style="margin-top: 15px;">
            <h3><?php echo number_format($client['total_orders'] ?? 0); ?></h3>
            <p>Commandes Total</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #00ff88, #00d9ff);">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-content" style="margin-top: 15px;">
            <h3><?php echo format_price($client['total_spent'] ?? 0); ?></h3>
            <p>Montant Total</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #a855f7, #ff006e);">
            <i class="fas fa-box"></i>
        </div>
        <div class="stat-content" style="margin-top: 15px;">
            <h3><?php echo number_format(count($orders)); ?></h3>
            <p>Commandes Récentes</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ff006e, #00d9ff);">
            <i class="fas fa-chart-bar"></i>
        </div>
        <div class="stat-content" style="margin-top: 15px;">
            <h3><?php echo $client['total_orders'] > 0 ? format_price($client['total_spent'] / $client['total_orders']) : format_price(0); ?></h3>
            <p>Panier Moyen</p>
        </div>
    </div>
</div>

<!-- Historique des commandes -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i>
            Historique des Commandes
        </h2>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>N° Commande</th>
                    <th>Date</th>
                    <th>Articles</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            <i class="fas fa-shopping-bag" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                            <p>Aucune commande pour ce client</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            <td><?php echo format_date($order['date']); ?></td>
                            <td><?php echo $order['items']; ?> articles</td>
                            <td><strong style="color: var(--neon-green);"><?php echo format_price($order['total']); ?></strong></td>
                            <td>
                                <?php
                                $status_class = $order['status'] === 'completed' ? 'active' : 'inactive';
                                $status_text = [
                                    'completed' => 'Complétée',
                                    'pending' => 'En attente',
                                    'processing' => 'En traitement',
                                    'cancelled' => 'Annulée'
                                ];
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text[$order['status']] ?? $order['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn view" title="Voir la commande">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Actions -->
<div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
    <a href="client-edit.php?id=<?php echo $client['id']; ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i>
        Modifier les informations
    </a>
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i>
        Imprimer
    </button>
    <a href="clients-list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Retour à la liste
    </a>
</div>

<?php
// Inclure le footer
include __DIR__ . '/../includes/footer.php';
?>