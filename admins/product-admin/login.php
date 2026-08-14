<?php
/**
 * Login Product Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
// auth.php ouvre la session avec le bon nom (atlantech_product_admin)

// Si déjà connecté ET avec le bon rôle, aller au dashboard
if (is_logged_in()) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        try {
            // Filtre strict : seul un admin avec le rôle 'product' peut se connecter ici
            $stmt = $pdo->prepare("
                SELECT a.id, a.full_name, a.email, a.password, a.admin_role_id, a.is_active, ar.role_name
                FROM admins a
                LEFT JOIN admin_roles ar ON a.admin_role_id = ar.id
                WHERE a.email = ? AND ar.role_name = 'product'
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                if ($admin['is_active']) {
                    // Connexion réussie
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role_id'] = $admin['admin_role_id'];

                    // Update last_login
                    $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$admin['id']]);

                    header('Location: pages/dashboard.php');
                    exit();
                } else {
                    $error = 'Compte désactivé';
                }
            } else {
                $error = 'Email ou mot de passe incorrect, ou rôle non autorisé pour ce module';
            }
        } catch (PDOException $e) {
            error_log("Erreur login: " . $e->getMessage());
            $error = 'Erreur de connexion';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Product Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <i class="fas fa-box"></i>
                <h1>ATLANTECH</h1>
                <p>Product Admin</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" class="form-input" required autofocus>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Mot de passe</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    Se Connecter
                </button>
            </form>
        </div>
    </div>
</body>
</html>
