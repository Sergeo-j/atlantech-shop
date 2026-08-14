<?php
/**
 * Page de connexion - Client Admin Dashboard
 * Atlantech Shop
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Si déjà connecté, rediriger vers le dashboard
if (is_logged_in()) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        if (login_admin($email, $password)) {
            header('Location: pages/dashboard.php');
            exit();
        } else {
            $error = 'Email ou mot de passe incorrect';
        }
    }
}

// Message de timeout
if (isset($_GET['timeout'])) {
    $error = 'Votre session a expiré. Veuillez vous reconnecter.';
}

// Message de déconnexion
if (isset($_GET['logout'])) {
    $success = 'Vous avez été déconnecté avec succès.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Atlantech Shop Admin</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-bolt"></i> ATLANTECH
                </div>
                <div class="login-subtitle">Client Admin Dashboard</div>
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
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <i class="fas fa-envelope"></i>
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="Adresse email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                        autocomplete="email"
                    >
                </div>
                
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        name="password" 
                        placeholder="Mot de passe"
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        Se souvenir de moi
                    </label>
                    <a href="#" class="forgot-password">Mot de passe oublié ?</a>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2024 Atlantech Shop. Tous droits réservés.</p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
