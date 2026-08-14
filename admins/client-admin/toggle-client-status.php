<?php
/**
 * Toggle Client Status (AJAX)
 * Atlantech Shop - Client Admin Dashboard
 * Action rapide pour activer/désactiver un client depuis la liste
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Headers JSON
header('Content-Type: application/json');

// Vérifier l'authentification
if (!is_logged_in()) {
    echo json_encode([
        'success' => false,
        'message' => 'Non authentifié'
    ]);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

// Vérifier le token CSRF (compatible AJAX)
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!verify_csrf_token($csrf_token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Token de sécurité invalide ou expiré'
    ]);
    exit();
}

// Récupérer les données
$client_id = intval($_POST['client_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'activate' ou 'deactivate'

// 🔒 Validation stricte de l'action
if (!in_array($action, ['activate', 'deactivate'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Action invalide'
    ]);
    exit();
}

if ($client_id === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID client invalide'
    ]);
    exit();
}

// Récupérer le client
$client = get_client_by_id($client_id);

if (!$client) {
    echo json_encode([
        'success' => false,
        'message' => 'Client non trouvé'
    ]);
    exit();
}

// Vérifier si le client est bloqué
if ($client['blocked']) {
    echo json_encode([
        'success' => false,
        'message' => 'Ce client est bloqué. Veuillez le débloquer d\'abord dans la page de modification.'
    ]);
    exit();
}

// Déterminer le nouveau statut
$new_status = ($action === 'activate') ? 1 : 0;

// Mettre à jour le statut
$status_data = [
    'is_active' => $new_status,
    'blocked' => 0 // S'assurer que blocked reste à 0
];

if (update_client_status($client_id, $status_data)) {
    $status_text = $new_status ? 'activé' : 'désactivé';
    log_admin_action('TOGGLE_CLIENT_STATUS', "Client ID: $client_id - Statut: $status_text");
    
    echo json_encode([
        'success' => true,
        'message' => 'Statut mis à jour avec succès',
        'new_status' => $new_status,
        'status_text' => $status_text,
        'status_badge' => $new_status ? 
            '<span class="status-badge active"><i class="fas fa-check-circle"></i> Actif</span>' : 
            '<span class="status-badge inactive"><i class="fas fa-pause-circle"></i> Inactif</span>'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour du statut'
    ]);
}
?>