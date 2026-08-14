<?php
/**
 * Profil - AtlanTech E-commerce
 * Informations complètes du compte : nom, username, genre, date de naissance, photo
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=profile');

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Charger les données complètes
$stmt = $mysqli->prepare(
    "SELECT id, name, username, email, phone, gender, birth_date,
            profile_image, created_at, last_login, total_orders,
            total_spent, email_verified, profile_completed
     FROM users WHERE id = ? AND is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { session_destroy(); redirect('../account.php'); }

$success = ''; $errors = [];

// ── Traitement POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token invalide.';
    } else {
        $name       = trim($_POST['name']       ?? '');
        $username   = trim($_POST['username']   ?? '');
        $phone      = trim($_POST['phone']      ?? '');
        $gender     = in_array($_POST['gender']??'',['male','female','other','prefer_not_to_say']) ? $_POST['gender'] : null;
        $birth_date = trim($_POST['birth_date'] ?? '');

        if (empty($name)) $errors[] = 'Le nom est requis.';

        // Valider username : lettres, chiffres, underscore uniquement
        if (!empty($username) && !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username))
            $errors[] = "Le nom d'utilisateur doit contenir 3–30 caractères (lettres, chiffres, _).";

        // Valider date de naissance
        $birth_date_val = null;
        if (!empty($birth_date)) {
            $d = DateTime::createFromFormat('Y-m-d', $birth_date);
            if (!$d || $d->format('Y-m-d') !== $birth_date) $errors[] = 'Date de naissance invalide.';
            elseif ($d > new DateTime()) $errors[] = 'Date de naissance dans le futur.';
            else $birth_date_val = $birth_date;
        }

        // Vérifier unicité du username
        if (empty($errors) && !empty($username)) {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
            $stmt->bind_param('si', $username, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $errors[] = "Ce nom d'utilisateur est déjà pris.";
            $stmt->close();
        }

        // Gérer l'upload de photo
        $profile_image = $user['profile_image'];
        if (!empty($_FILES['profile_image']['name'])) {
            $file    = $_FILES['profile_image'];
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            if (!in_array($file['type'], $allowed)) {
                $errors[] = 'Format image invalide (JPG, PNG, WebP, GIF).';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Image trop grande (max 2 Mo).';
            } else {
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_name = 'user_'.$user_id.'_'.time().'.'.$ext;
                $upload_dir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                    // Supprimer l'ancienne
                    if ($profile_image && file_exists($upload_dir . $profile_image))
                        unlink($upload_dir . $profile_image);
                    $profile_image = $new_name;
                } else {
                    $errors[] = 'Erreur lors de l\'upload de la photo.';
                }
            }
        }

        if (empty($errors)) {
            $stmt = $mysqli->prepare(
                "UPDATE users SET name=?, username=?, phone=?, gender=?, birth_date=?,
                 profile_image=?, profile_completed=1 WHERE id=?"
            );
            $stmt->bind_param('ssssssi', $name, $username, $phone, $gender, $birth_date_val, $profile_image, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['user_name'] = $name;
            $user['name']          = $name;
            $user['username']      = $username;
            $user['phone']         = $phone;
            $user['gender']        = $gender;
            $user['birth_date']    = $birth_date_val;
            $user['profile_image'] = $profile_image;

            $success = 'Votre profil a été mis à jour.';
        }
    }
}

$gender_labels = ['male'=>'Homme','female'=>'Femme','other'=>'Autre','prefer_not_to_say'=>'Préfère ne pas préciser'];
$avatar_url = !empty($user['profile_image'])
    ? '../uploads/avatars/'.htmlspecialchars($user['profile_image'])
    : null;
$initials = strtoupper(implode('', array_map(fn($w)=>$w[0], explode(' ', trim($user['name'])))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Mon Profil - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:820px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

        /* Header profil */
        .profile-header{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:28px;margin-bottom:20px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;}
        .avatar-wrap{position:relative;cursor:pointer;}
        .avatar-circle{width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#fff;overflow:hidden;}
        .avatar-circle img{width:100%;height:100%;object-fit:cover;}
        .avatar-edit{position:absolute;bottom:0;right:0;background:#e77600;color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;}
        .profile-stats{display:flex;gap:24px;flex-wrap:wrap;}
        .stat{text-align:center;}
        .stat-val{font-size:22px;font-weight:800;color:#0F1111;}
        .stat-lbl{font-size:12px;color:#666;}
        .verified-badge{display:inline-flex;align-items:center;gap:4px;background:#d1fae5;color:#065f46;font-size:12px;font-weight:700;padding:3px 10px;border-radius:10px;}
        .profile-pct{margin-top:10px;}
        .pct-bar{height:6px;background:#E7E7E7;border-radius:4px;margin-top:4px;overflow:hidden;}
        .pct-fill{height:100%;border-radius:4px;background:#e77600;}

        /* Formulaire */
        .form-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:28px;}
        .form-card h3{font-size:17px;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #E7E7E7;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .form-group{margin-bottom:0;}
        .form-group.full{grid-column:1/-1;}
        .form-label{display:block;font-size:13px;font-weight:600;color:#0F1111;margin-bottom:5px;}
        .form-label span.opt{font-weight:400;color:#888;}
        .form-control{width:100%;padding:10px 12px;border:1px solid #888C8C;border-radius:6px;font-size:14px;}
        .form-control:focus{outline:none;border-color:#e77600;box-shadow:0 0 0 3px rgba(231,118,0,.15);}
        .form-hint{font-size:12px;color:#888;margin-top:4px;}
        .btn-save{margin-top:24px;padding:12px 32px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:14px;font-weight:700;color:#0F1111;cursor:pointer;}
        .btn-save:hover{background:#F7CA00;}
        @media(max-width:600px){.form-grid{grid-template-columns:1fr;}.profile-header{flex-direction:column;}}
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
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Mon Profil</span>
    </nav>
    <h1 class="page-title">Mon Profil</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

    <!-- Header -->
    <div class="profile-header">
        <label class="avatar-wrap" for="profile_image_input" title="Changer la photo">
            <div class="avatar-circle">
                <?php if ($avatar_url): ?>
                    <img src="<?php echo $avatar_url; ?>" alt="avatar">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="avatar-edit">✏️</div>
        </label>

        <div style="flex:1;">
            <div style="font-size:20px;font-weight:800;color:#0F1111;"><?php echo htmlspecialchars($user['name']); ?></div>
            <?php if (!empty($user['username'])): ?>
                <div style="font-size:14px;color:#666;">@<?php echo htmlspecialchars($user['username']); ?></div>
            <?php endif; ?>
            <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <?php if ($user['email_verified']): ?>
                    <span class="verified-badge">✓ E-mail vérifié</span>
                <?php endif; ?>
                <span style="font-size:12px;color:#888;">Membre depuis <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                <?php if ($user['last_login']): ?>
                    <span style="font-size:12px;color:#888;">· Dernière connexion <?php echo date('d/m/Y', strtotime($user['last_login'])); ?></span>
                <?php endif; ?>
            </div>
            <?php
            // Calculer % profil complété
            $fields = [$user['name'],$user['username'],$user['phone'],$user['gender'],$user['birth_date'],$user['profile_image']];
            $filled = count(array_filter($fields, fn($v)=>!empty($v)));
            $pct    = round($filled / count($fields) * 100);
            ?>
            <div class="profile-pct">
                <span style="font-size:12px;color:#555;">Profil complété à <strong><?php echo $pct; ?>%</strong></span>
                <div class="pct-bar"><div class="pct-fill" style="width:<?php echo $pct; ?>%;"></div></div>
            </div>
        </div>

        <div class="profile-stats">
            <div class="stat"><div class="stat-val"><?php echo (int)$user['total_orders']; ?></div><div class="stat-lbl">Commandes</div></div>
            <div class="stat"><div class="stat-val"><?php echo number_format((float)$user['total_spent'], 0); ?></div><div class="stat-lbl">HTG dépensés</div></div>
        </div>
    </div>

    <!-- Formulaire -->
    <form method="POST" action="profile.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <!-- Input fichier caché, déclenché par le label avatar -->
        <input type="file" id="profile_image_input" name="profile_image" accept="image/*" style="display:none;" onchange="previewAvatar(this)">

        <div class="form-card">
            <h3>Informations personnelles</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom complet <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="100"
                           value="<?php echo htmlspecialchars($user['name']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Nom d'utilisateur <span class="opt">(optionnel)</span></label>
                    <input type="text" name="username" class="form-control" maxlength="30"
                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                           placeholder="ex : jean_dupont">
                    <p class="form-hint">3–30 caractères, lettres/chiffres/_ uniquement</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone <span class="opt">(optionnel)</span></label>
                    <input type="tel" name="phone" class="form-control" maxlength="20"
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                           placeholder="+509 XXXX-XXXX">
                </div>
                <div class="form-group">
                    <label class="form-label">Genre <span class="opt">(optionnel)</span></label>
                    <select name="gender" class="form-control">
                        <option value="">-- Non précisé --</option>
                        <?php foreach ($gender_labels as $k=>$v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($user['gender']===$k)?'selected':''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date de naissance <span class="opt">(optionnel)</span></label>
                    <input type="date" name="birth_date" class="form-control"
                           value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>"
                           max="<?php echo date('Y-m-d', strtotime('-10 years')); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                           style="background:#F0F2F2;color:#888;">
                    <p class="form-hint">Pour changer l'email → <a href="security.php" style="color:#007185;">Connexion &amp; Sécurité</a></p>
                </div>
            </div>
            <button type="submit" class="btn-save">Enregistrer le profil</button>
        </div>
    </form>

    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var circle = document.querySelector('.avatar-circle');
            circle.innerHTML = '<img src="'+e.target.result+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
