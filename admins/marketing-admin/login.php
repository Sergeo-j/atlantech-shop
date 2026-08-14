<?php
require_once __DIR__ . '/includes/auth.php';
if (is_mkt_logged_in()) { header('Location: index.php'); exit; }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée. Rechargez la page.';
    } else {
        $res = login_mkt($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($res['ok']) { header('Location: index.php'); exit; }
        $error = $res['error'] ?? 'Échec de la connexion.';
    }
}
if (isset($_GET['expired'])) $error   = 'Session expirée. Reconnectez-vous.';
if (isset($_GET['logout']))  $success = 'Vous avez été déconnecté avec succès.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — Marketing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../dg-admin/assets/css/style.css">
    <style>:root{--dg-accent:#f97316;--dg-primary:#ec4899}</style>
</head>
<body class="login-wrap">
    <div class="login-card">
        <div class="brand">
            <h1><i class="fas fa-bullhorn"></i> ATLANTECH</h1>
            <p>Marketing &amp; Promotions</p>
            <span class="badge">MKT</span>
        </div>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Mot de passe</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-accent w-100" style="justify-content:center;padding:12px">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>
        <p class="text-center text-muted" style="margin-top:20px;font-size:0.8rem">
            Accès réservé à l'équipe Marketing.<br>Compte créé par le PDG ou le DG.
        </p>
    </div>
</body>
</html>
