<?php
/**
 * Dashboard Principal
 * Atlantech Shop - Client Admin Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification
check_auth();

// Obtenir les statistiques
$stats = get_client_stats();

// Variables pour le header
$page_title = 'Tableau de Bord';
$current_page = 'dashboard';

// Inclure le header
include __DIR__ . '/../includes/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i>
                12%
            </div>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Clients</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #00ff88, #00d9ff);">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i>
                8%
            </div>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['active']; ?></h3>
            <p>Clients Actifs</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ff006e, #a855f7);">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-trend down">
                <i class="fas fa-arrow-down"></i>
                3%
            </div>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['inactive']; ?></h3>
            <p>Clients Inactifs</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon" style="background: linear-gradient(135deg, #a855f7, #ff006e);">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i>
                15%
            </div>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['new_this_month']; ?></h3>
            <p>Nouveaux ce mois</p>
        </div>
    </div>
</div>

<!-- Graphique de croissance -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-chart-line"></i>
            Croissance des Clients
        </h2>
        <div class="card-actions">
            <button class="btn btn-secondary btn-sm">
                <i class="fas fa-download"></i>
                Exporter
            </button>
        </div>
    </div>
    <div class="chart-container">
        <canvas id="growthChart"></canvas>
    </div>
</div>

<!-- Actions rapides -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-bolt"></i>
            Actions Rapides
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 10px 0;">
        <a href="clients-list.php" class="btn btn-primary" style="text-align: center;">
            <i class="fas fa-list"></i>
            Voir tous les clients
        </a>
        <button class="btn btn-secondary" onclick="exportClients('csv')" style="text-align: center;">
            <i class="fas fa-file-csv"></i>
            Exporter CSV
        </button>
        <button class="btn btn-secondary" onclick="exportClients('pdf')" style="text-align: center;">
            <i class="fas fa-file-pdf"></i>
            Exporter PDF
        </button>
        <a href="#" class="btn btn-secondary" style="text-align: center;">
            <i class="fas fa-chart-bar"></i>
            Statistiques détaillées
        </a>
    </div>
</div>

<!-- Activité récente -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i>
            Activité Récente
        </h2>
        <a href="#" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Client</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>10 Déc 2024 14:30</td>
                    <td><span class="status-badge active">Activé</span></td>
                    <td>Jean Dupont</td>
                    <td>Compte client activé</td>
                </tr>
                <tr>
                    <td>10 Déc 2024 12:15</td>
                    <td><span class="status-badge inactive">Modifié</span></td>
                    <td>Marie Claire</td>
                    <td>Informations mises à jour</td>
                </tr>
                <tr>
                    <td>09 Déc 2024 16:45</td>
                    <td><span class="status-badge active">Créé</span></td>
                    <td>Pierre Michel</td>
                    <td>Nouveau compte client</td>
                </tr>
                <tr>
                    <td>09 Déc 2024 11:20</td>
                    <td><span class="status-badge inactive">Désactivé</span></td>
                    <td>Sophie Laurent</td>
                    <td>Compte temporairement désactivé</td>
                </tr>
                <tr>
                    <td>08 Déc 2024 15:30</td>
                    <td><span class="status-badge active">Activé</span></td>
                    <td>Luc Bernard</td>
                    <td>Compte réactivé après suspension</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
// Initialiser le graphique de croissance
document.addEventListener('DOMContentLoaded', function() {
    const growthData = <?php echo json_encode($stats['growth']); ?>;
    if (growthData && growthData.length > 0) {
        initGrowthChart(growthData);
    }
});
</script>

<?php
// Inclure le footer
include __DIR__ . '/../includes/footer.php';
?>
