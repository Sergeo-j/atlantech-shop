<?php
require_once 'includes/auth.php';
if (is_logged_in()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($email && $password) {
        if (login_delivery($email, $password)) {
            header('Location: index.php'); exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Delivery Admin — Connexion</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:linear-gradient(135deg,#0f0f23,#1a1a3e);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;padding:20px}
.card{background:#1e1e3a;border-radius:16px;padding:40px 36px;width:100%;max-width:400px;border:1px solid #2d2d50;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.logo{text-align:center;margin-bottom:28px}
.logo h1{font-size:2rem;color:#22c55e}
.logo p{color:#94a3b8;font-size:.85rem;margin-top:4px}
.logo .badge{display:inline-block;background:#14532d;color:#22c55e;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;margin-top:8px}
h2{color:#e2e8f0;font-size:1.1rem;margin-bottom:20px;text-align:center}
label{display:block;color:#94a3b8;font-size:.8rem;font-weight:600;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
input[type=email],input[type=password]{width:100%;background:#2d2d50;border:1px solid #3d3d6b;color:#e2e8f0;padding:12px 14px;border-radius:8px;font-size:.95rem;margin-bottom:16px;transition:border .2s}
input:focus{outline:none;border-color:#22c55e}
.btn{width:100%;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border:none;padding:13px;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:4px}
.btn:hover{opacity:.9}
.error{background:#3b0c0c;border:1px solid #ef4444;color:#fca5a5;padding:11px 14px;border-radius:7px;font-size:.85rem;margin-bottom:16px}
.expired{background:#3b2a0c;border:1px solid #f59e0b;color:#fde68a;padding:11px 14px;border-radius:7px;font-size:.85rem;margin-bottom:16px}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>🚚</h1>
        <p>AtlanTech — Livraisons</p>
        <span class="badge">LIVREUR</span>
    </div>
    <h2>Connexion</h2>

    <?php if (isset($_GET['expired'])): ?>
        <div class="expired">⏱ Session expirée. Veuillez vous reconnecter.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="votre@email.com" required autofocus>
        <label>Mot de passe</label>
        <input type="password" name="password" placeholder="••••••••" required>
        <button type="submit" class="btn">🔐 Se connecter</button>
    </form>
</div>
</body>
</html>
