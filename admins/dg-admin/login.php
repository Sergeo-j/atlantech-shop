<?php
/**
 * Page de connexion — DG Admin Dashboard
 */
require_once __DIR__ . '/includes/auth.php';

if (is_dg_logged_in()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée ou requête invalide. Rechargez la page.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $res = login_dg($email, $password);
        if ($res['ok']) {
            header('Location: index.php');
            exit;
        }
        $error = $res['error'] ?? 'Échec de la connexion.';
    }
}

if (isset($_GET['expired']))  $error   = 'Votre session a expiré. Veuillez vous reconnecter.';
if (isset($_GET['logout']))   $success = 'Vous avez été déconnecté avec succès.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Direction Générale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-wrap">
    <div class="login-card">
        <div class="brand">
            <h1><i class="fas fa-crown"></i> ATLANTECH</h1>
            <p>Tableau de bord — Direction</p>
            <span class="badge">DG</span>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Adresse email</label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="dg@atlantech.ht" required autofocus>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Mot de passe</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-accent w-100" style="justify-content:center;padding:12px">
                <i class="fas fa-sign-in-alt"></i>
                Se connecter
            </button>
        </form>

        <p class="text-center text-muted" style="margin-top:20px;font-size:0.8rem">
            Accès réservé à la Direction Générale.<br>
            Toute tentative est journalisée.
        </p>
    </div>
</body>
</html>
