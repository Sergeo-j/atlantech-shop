<?php
/**
 * Page de connexion - Super Admin Dashboard
 * Atlantech Shop - AVEC ARGON2ID
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Vérifier si un Super Admin existe
$has_superadmin = false;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM superadmins");
    $result = $stmt->fetch();
    $has_superadmin = ($result['count'] > 0);
} catch (PDOException $e) {
    // Ignorer l'erreur si la table n'existe pas
}

// Si déjà connecté, rediriger vers le dashboard
if (is_superadmin_logged_in()) {
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
        if (login_superadmin($email, $password)) {
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

// Message après inscription
if (isset($_GET['registered'])) {
    $success = 'Inscription réussie ! Connectez-vous avec vos identifiants.';
}

// Message si Super Admin existe déjà
if (isset($_GET['error']) && $_GET['error'] === 'already_exists') {
    $error = 'Un Super Admin existe déjà. Veuillez vous connecter.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Atlantech Shop</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Rajdhani', sans-serif;
            background: #020817;
            color: #e6f1ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Background animé violet/or */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(ellipse at 20% 30%, rgba(168, 85, 247, 0.2) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(255, 215, 0, 0.15) 0%, transparent 50%);
            pointer-events: none;
            animation: rotateBackground 20s linear infinite;
        }
        
        @keyframes rotateBackground {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            padding: 20px;
        }
        
        .login-box {
            width: 100%;
            max-width: 450px;
            background: rgba(17, 34, 64, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(168, 85, 247, 0.3);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 36px;
            font-weight: 900;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(168, 85, 247, 0.5);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .login-logo i {
            font-size: 40px;
            color: #ffd700;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }
        
        .login-subtitle {
            color: #8892b0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 500;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: rgba(255, 0, 110, 0.1);
            color: #ff006e;
            border: 1px solid #ff006e;
        }
        
        .alert-success {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            border: 1px solid #ffd700;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a855f7;
            font-size: 18px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 10px;
            color: #e6f1ff;
            font-size: 15px;
            font-family: 'Rajdhani', sans-serif;
            transition: 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 20px rgba(168, 85, 247, 0.4);
        }
        
        .form-group input::placeholder {
            color: #8892b0;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 13px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #8892b0;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: #a855f7;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .forgot-password:hover {
            color: #ffd700;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #a855f7, #ffd700);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 4px 20px rgba(168, 85, 247, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 30px rgba(255, 215, 0, 0.6);
        }
        
        .login-footer {
            text-align: center;
            color: #8892b0;
            font-size: 12px;
            margin-top: 30px;
        }
        
        .badge-argon2 {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 15px;
            border: 1px solid rgba(255, 215, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-crown"></i> SUPER ADMIN
                </div>
                <div class="login-subtitle">Atlantech Shop Dashboard</div>
                <div class="badge-argon2">
                    <i class="fas fa-shield-alt"></i>
                    Sécurisé avec Argon2id
                </div>
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
                        placeholder="Adresse email Super Admin"
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
                    Connexion Super Admin
                </button>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2024 Atlantech Shop. Super Admin Access Only.</p>
                <?php if (!$has_superadmin): ?>
                <p style="margin-top: 15px;">
                    <a href="register.php" style="color: #ffd700; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-user-plus"></i> Créer le premier Super Admin
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
