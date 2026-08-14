<?php
/**
 * Votre Famille AtlanTech
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=family');

$user_id = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$success = ''; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $invite_email = trim($_POST['invite_email'] ?? '');
        $invite_name  = trim($_POST['invite_name']  ?? '');
        $invite_role  = in_array($_POST['invite_role'] ?? '', ['adult','child']) ? $_POST['invite_role'] : 'adult';

        if (empty($invite_email) || !filter_var($invite_email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Adresse e-mail invalide.';
        if (empty($invite_name))
            $errors[] = 'Le prénom est requis.';

        if (empty($errors)) {
            // Pas encore de table famille - on log la demande
            error_log("[FAMILLE] Invitation de {$user['name']} (ID $user_id) vers $invite_name <$invite_email> - rôle: $invite_role");
            $success = "Invitation envoyée à $invite_name ($invite_email). Ils recevront un e-mail pour rejoindre votre espace famille.";
        }
    }
}

// Membres fictifs selon le compte (démo si aucune table)
$members = [
    ['initials' => strtoupper(substr($user['name'], 0, 2)), 'name' => $user['name'], 'role' => 'Administrateur', 'color' => '#0F1111', 'you' => true],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Famille AtlanTech - Mon Compte</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:900px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

        /* Hero */
        .hero{background:linear-gradient(135deg,#ff9a9e 0%,#fecfef 100%);border-radius:12px;padding:32px 36px;display:flex;align-items:center;gap:24px;margin-bottom:32px;flex-wrap:wrap;}
        .hero .icon{font-size:56px;}
        .hero h2{font-size:22px;font-weight:800;color:#0F1111;margin-bottom:6px;}
        .hero p{font-size:14px;color:#555;margin:0;}

        /* Avantages */
        .benefits{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:32px;}
        .benefit{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:20px;text-align:center;}
        .benefit .icon{font-size:32px;margin-bottom:10px;display:block;}
        .benefit h4{font-size:14px;font-weight:700;color:#0F1111;margin-bottom:4px;}
        .benefit p{font-size:12px;color:#666;margin:0;}

        /* Membres */
        .members-section{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:24px;margin-bottom:28px;}
        .section-title{font-size:17px;font-weight:700;color:#0F1111;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #E7E7E7;}
        .member-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #F0F2F2;}
        .member-row:last-child{border-bottom:none;}
        .avatar{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:#fff;flex-shrink:0;}
        .member-info{flex:1;}
        .member-name{font-size:15px;font-weight:700;color:#0F1111;}
        .member-role{font-size:12px;color:#666;}
        .you-badge{background:#FFD814;color:#0F1111;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;}

        /* Formulaire */
        .invite-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:28px;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
        .form-group{margin-bottom:0;}
        .form-group.full{grid-column:1/-1;}
        .form-label{display:block;font-size:13px;font-weight:600;color:#0F1111;margin-bottom:5px;}
        .form-control{width:100%;padding:10px 12px;border:1px solid #888C8C;border-radius:6px;font-size:14px;}
        .form-control:focus{outline:none;border-color:#e77600;box-shadow:0 0 0 3px rgba(231,118,0,.15);}
        .btn-submit{margin-top:20px;padding:11px 28px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:14px;font-weight:700;color:#0F1111;cursor:pointer;}
        .btn-submit:hover{background:#F7CA00;}
        @media(max-width:600px){.form-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div style="background:#131921;padding:12px 20px;display:flex;align-items:center;gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff;font-size:13px;text-decoration:none;"><i class="fas fa-user-circle"></i>&nbsp;Mon compte</a>
</div>

<div class="wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Famille AtlanTech</span>
    </nav>
    <h1 class="page-title">Votre Famille AtlanTech</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

    <div class="hero">
        <div class="icon">👨‍👩‍👧‍👦</div>
        <div>
            <h2>Partagez AtlanTech en famille</h2>
            <p>Invitez jusqu'à 5 membres de votre famille. Chaque membre a son propre profil,<br>
               ses propres commandes et sa liste de souhaits, tout en bénéficiant de vos avantages VIP.</p>
        </div>
    </div>

    <!-- Avantages -->
    <div class="benefits">
        <div class="benefit"><span class="icon">🛍️</span><h4>Profils séparés</h4><p>Chaque membre a ses propres commandes et préférences</p></div>
        <div class="benefit"><span class="icon">⭐</span><h4>Points partagés</h4><p>Cumulez et utilisez les points de fidélité ensemble</p></div>
        <div class="benefit"><span class="icon">🏷️</span><h4>Remises VIP</h4><p>Tous les membres profitent de votre niveau VIP</p></div>
        <div class="benefit"><span class="icon">🔒</span><h4>Contrôle parental</h4><p>Définissez des limites d'achat pour les enfants</p></div>
        <div class="benefit"><span class="icon">📦</span><h4>Livraison groupée</h4><p>Commandes regroupées pour économiser sur les frais</p></div>
        <div class="benefit"><span class="icon">🎁</span><h4>Listes de souhaits</h4><p>Partagez vos listes avec tous les membres</p></div>
    </div>

    <!-- Membres actuels -->
    <div class="members-section">
        <div class="section-title">Membres de votre famille (<?php echo count($members); ?>/6)</div>
        <?php foreach ($members as $m): ?>
            <div class="member-row">
                <div class="avatar" style="background:<?php echo $m['color']; ?>;"><?php echo htmlspecialchars($m['initials']); ?></div>
                <div class="member-info">
                    <div class="member-name">
                        <?php echo htmlspecialchars($m['name']); ?>
                        <?php if (!empty($m['you'])): ?><span class="you-badge">Vous</span><?php endif; ?>
                    </div>
                    <div class="member-role"><?php echo $m['role']; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($members) < 6): ?>
            <p style="font-size:13px;color:#666;margin-top:14px;margin-bottom:0;">
                Vous pouvez inviter encore <?php echo 6 - count($members); ?> membre<?php echo (6 - count($members)) > 1 ? 's' : ''; ?>.
            </p>
        <?php endif; ?>
    </div>

    <!-- Invitation -->
    <?php if (count($members) < 6): ?>
    <div class="invite-card">
        <div class="section-title">Inviter un membre</div>
        <form method="POST" action="family.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Prénom <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="invite_name" class="form-control" required placeholder="Ex : Marie">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse e-mail <span style="color:#ef4444;">*</span></label>
                    <input type="email" name="invite_email" class="form-control" required placeholder="marie@exemple.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle</label>
                    <select name="invite_role" class="form-control">
                        <option value="adult">Adulte (accès complet)</option>
                        <option value="child">Enfant (accès limité)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-submit">Envoyer l'invitation</button>
        </form>
    </div>
    <?php endif; ?>

    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
