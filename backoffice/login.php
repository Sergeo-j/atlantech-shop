<?php
session_start();

// Si déjà connecté → redirection vers dashboard admin
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

// Connexion à la base
// IMPORTANT : adapter le chemin selon ton projet
require_once '../config/config.php';   // si ton fichier DB est ici


$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        try {
            // Vérifier si l'utilisateur existe et est un admin
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE email = ? 
                AND role IN ('admin', 'super_admin') 
                AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {

                // Vérification du verrouillage temporaire
                if (!empty($user['account_locked_until']) && strtotime($user['account_locked_until']) > time()) {
                    $minutes_left = ceil((strtotime($user['account_locked_until']) - time()) / 60);
                    $error = "Compte verrouillé. Réessayez dans {$minutes_left} minute(s).";
                } else {

                    // Vérification du mot de passe
                    if (password_verify($password, $user['password'])) {

                        // Réinitialiser les tentatives ratées
                        $update = $pdo->prepare("
                            UPDATE users SET 
                                login_attempts = 0,
                                last_login = NOW(),
                                last_login_ip = ?,
                                account_locked_until = NULL
                            WHERE id = ?
                        ");
                        $update->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);

                        // Création session admin
                        $_SESSION['admin_id']    = $user['id'];
                        $_SESSION['admin_name']  = $user['name'];
                        $_SESSION['admin_email'] = $user['email'];
                        $_SESSION['admin_role']  = $user['role'];
                        $_SESSION['admin_avatar'] = $user['avatar'];

                        // Se souvenir de moi
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            setcookie('admin_remember', $token, time() + (86400 * 30), '/');
                        }

                        header('Location: admin_dashboard.php');
                        exit();

                    } else {

                        // Gestion des tentatives échouées
                        $attempts = $user['login_attempts'] + 1;

                        if ($attempts >= 5) {
                            $locked_until = date('Y-m-d H:i:s', time() + (30 * 60));
                            $update = $pdo->prepare("
                                UPDATE users SET 
                                    login_attempts = ?, 
                                    account_locked_until = ?
                                WHERE id = ?
                            ");
                            $update->execute([$attempts, $locked_until, $user['id']]);
                            $error = "Trop de tentatives incorrectes. Compte verrouillé 30 minutes.";
                        } else {
                            $update = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                            $update->execute([$attempts, $user['id']]);

                            $rest = 5 - $attempts;
                            $error = "Email ou mot de passe incorrect. {$rest} tentative(s) restante(s).";
                        }
                    }
                }

            } else {
                $error = "Identifiants invalides ou compte non administrateur.";
            }

        } catch (PDOException $e) {
            $error = "Erreur serveur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Atlantech Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 15s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .login-left h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-left p {
            font-size: 1.125rem;
            opacity: 0.9;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .login-left .features {
            margin-top: 2rem;
            position: relative;
            z-index: 1;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            opacity: 0;
            animation: fadeInLeft 0.6s ease-out forwards;
        }

        .feature:nth-child(1) { animation-delay: 0.2s; }
        .feature:nth-child(2) { animation-delay: 0.4s; }
        .feature:nth-child(3) { animation-delay: 0.6s; }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .feature i {
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-right {
            padding: 60px 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h2 {
            color: #1e293b;
            font-size: 1.875rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.938rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #334155;
            font-weight: 600;
            font-size: 0.938rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.125rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1.125rem;
            padding: 0.5rem;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-wrapper label {
            margin: 0;
            color: #64748b;
            font-size: 0.875rem;
            cursor: pointer;
            font-weight: 400;
        }

        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: #764ba2;
        }

        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
            }

            .login-left {
                padding: 40px 30px;
            }

            .login-left h1 {
                font-size: 2rem;
            }

            .login-right {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side -->
        <div class="login-left">
            <h1><i class="fas fa-store"></i> Atlantech Shop</h1>
            <p>Système d'administration.</p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-boxes"></i>
                    <div>
                        <strong>Gestion des Produits</strong><br>
                        Interface intuitive et puissante
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <div>
                        <strong>Analytics en Temps Réel</strong><br>
                        Suivez vos performances
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Sécurité Avancée</strong><br>
                        Protection de vos données
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h2>Connexion Admin</h2>
                <p>Entrez vos identifiants pour accéder au panneau</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Adresse Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="admin@atlantech.com"
                            required
                            autocomplete="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Mot de Passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="#" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Se Connecter
                </button>
            </form>

            <div class="login-footer">
                <p>© 2025 Atlantech Shop. Tous droits réservés.</p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Empêcher la soumission multiple du formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-login');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';
        });
    </script>
</body>
</html>
