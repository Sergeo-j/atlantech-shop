<?php
session_start();
require_once 'config/config.php'; // <-- Ajoute cette ligne tout en haut

// Si tu veux loguer l’heure de déconnexion (facultatif)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    try {
        // Vérifie que $pdo existe
        if (!isset($pdo)) {
            throw new Exception("Connexion PDO non trouvée.");
        }

        // Exemple : mise à jour de la dernière déconnexion
        $stmt = $pdo->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        // En cas d’erreur, on peut loguer le message (facultatif)
        error_log("Erreur logout : " . $e->getMessage());
    }
}

// Sauvegarder le panier en base avant de détruire la session
if (isset($_SESSION['user_id']) && isset($mysqli)) {
    try {
        require_once __DIR__ . '/includes/cart_persist.php';
        cart_db_save($mysqli, (int)$_SESSION['user_id']);
    } catch (\Throwable $e) {
        error_log('logout cart_db_save: ' . $e->getMessage());
    }
}

// Détruire la session
$_SESSION = [];
session_destroy();

// Redirection vers la page d’accueil
header("Location: index.php");
exit;
?>
