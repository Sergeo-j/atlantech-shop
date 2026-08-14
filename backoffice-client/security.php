<?php
/**
 * Sécurité & Profil - AtlanTech E-commerce
 * Mise à jour : nom, email, téléphone, mot de passe
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=security');
}

$user_id = (int)$_SESSION['user_id'];

// Charger l'utilisateur
$stmt = $mysqli->prepare(
    "SELECT id, name, email, phone, force_password_change FROM users WHERE id = ? AND is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    redirect('../account.php');
}

$errors   = [];
$success  = '';
$tab      = $_GET['tab'] ?? 'profile'; // profile | password

// ──────────────────────────────────────────────
// Traitement POST : mise à jour du profil
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Vérification CSRF basique (timestamp dans session)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide. Veuillez recharger la page.';
    } else {

        if ($_POST['action'] === 'update_profile') {
            // ── Mise à jour nom / email / téléphone ──
            $tab  = 'profile';
            $name  = trim($_POST['name']  ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name))  $errors[] = 'Le nom est obligatoire.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                $errors[] = 'Adresse e-mail invalide.';

            if (empty($errors)) {
                // Vérifier unicité email (sauf pour soi-même)
                $stmt = $mysqli->prepare(
                    "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
                );
                $stmt->bind_param('si', $email, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = 'Cet e-mail est déjà utilisé par un autre compte.';
                }
                $stmt->close();
            }

            if (empty($errors)) {
                $stmt = $mysqli->prepare(
                    "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?"
                );
                $stmt->bind_param('sssi', $name, $email, $phone, $user_id);
                $stmt->execute();
                $stmt->close();

                // Mettre à jour la session
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $user['name']           = $name;
                $user['email']          = $email;
                $user['phone']          = $phone;

                $success = 'Vos informations ont été mises à jour avec succès.';
            }

        } elseif ($_POST['action'] === 'update_password') {
            // ── Changement de mot de passe ──
            $tab          = 'password';
            $current_pw   = $_POST['current_password'] ?? '';
            $new_pw       = $_POST['new_password']     ?? '';
            $confirm_pw   = $_POST['confirm_password'] ?? '';

            if (empty($current_pw)) $errors[] = 'Saisissez votre mot de passe actuel.';
            if (strlen($new_pw) < 8) $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
            if ($new_pw !== $confirm_pw) $errors[] = 'Les deux nouveaux mots de passe ne correspondent pas.';

            if (empty($errors)) {
                // Vérifier l'ancien mot de passe
                $stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row || !password_verify($current_pw, $row['password'])) {
                    $errors[] = 'Le mot de passe actuel est incorrect.';
                } else {
                    $hashed = hashPassword($new_pw);
                    // On efface aussi force_password_change : si ce changement
                    // faisait suite à un mot de passe temporaire généré par un
                    // admin, l'obligation de changement est maintenant levée.
                    $stmt = $mysqli->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
                    $stmt->bind_param('si', $hashed, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $user['force_password_change'] = 0;
                    $_SESSION['force_password_change'] = 0;
                    $success = 'Votre mot de passe a été modifié avec succès.';
                }
            }
        }
    }
}

// Générer le token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Connexion & Sécurité - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        .security-wrap { max-width: 700px; margin: 40px auto; padding: 0 20px 80px; }
        .page-title { font-size: 26px; font-weight: 700; color: #0F1111; margin-bottom: 6px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 28px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }

        /* Tabs */
        .tabs { display: flex; gap: 0; border-bottom: 2px solid #D5D9D9; margin-bottom: 30px; }
        .tab-btn {
            padding: 12px 24px; font-size: 15px; font-weight: 600; color: #565959;
            text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn.active { color: #0F1111; border-bottom-color: #e77600; }
        .tab-btn:hover { color: #0F1111; }

        /* Alert */
        .alert {
            padding: 12px 16px; border-radius: 6px; margin-bottom: 22px;
            font-size: 14px; display: flex; align-items: flex-start; gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Form */
        .form-section { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 28px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 600; color: #0F1111; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #888C8C;
            border-radius: 6px; font-size: 14px; color: #0F1111;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none; border-color: #e77600;
            box-shadow: 0 0 0 3px rgba(231,118,0,0.15);
        }
        .form-hint { font-size: 12px; color: #888C8C; margin-top: 4px; }
        .btn-submit {
            padding: 11px 28px; background: #FFD814; border: 1px solid #FFA41C;
            border-radius: 8px; font-size: 14px; font-weight: 700; color: #0F1111;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #F7CA00; }
        .btn-cancel {
            padding: 11px 20px; background: #fff; border: 1px solid #D5D9D9;
            border-radius: 8px; font-size: 14px; color: #0F1111;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-cancel:hover { background: #F0F2F2; }

        .password-strength { margin-top: 6px; font-size: 12px; }
        .strength-bar { height: 4px; border-radius: 2px; background: #D5D9D9; margin-top: 4px; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; }
    </style>
</head>
<body>

<!-- ========= HEADER SIMPLIFIÉ ========= -->
<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php">
        <img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;">
    </a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-user-circle"></i>&nbsp;Mon compte
    </a>
    <a href="../cart.php" style="color:#fff; font-size:13px; text-decoration:none; margin-left:16px;">
        <i class="fas fa-shopping-cart"></i>
    </a>
</div>

<!-- ========= CONTENU ========= -->
<div class="security-wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Connexion &amp; Sécurité</span>
    </nav>

    <h1 class="page-title">Connexion &amp; Sécurité</h1>

    <?php if (!empty($user['force_password_change'])): ?>
        <div class="alert" style="background:#fff3cd; color:#664d03; border:1px solid #ffe69c;">
            <i class="fas fa-exclamation-triangle"></i>
            Un mot de passe temporaire a été généré pour votre compte. Pour des raisons de sécurité, vous devez le changer maintenant avant de continuer.
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $e): ?>
                    <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="security.php?tab=profile"
           class="tab-btn <?php echo $tab === 'profile'  ? 'active' : ''; ?>">
            Mes informations
        </a>
        <a href="security.php?tab=password"
           class="tab-btn <?php echo $tab === 'password' ? 'active' : ''; ?>">
            Mot de passe
        </a>
    </div>

    <!-- ── TAB : Profil ── -->
    <?php if ($tab === 'profile'): ?>
        <div class="form-section">
            <form method="POST" action="security.php?tab=profile">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action"     value="update_profile">

                <div class="form-group">
                    <label class="form-label" for="name">Nom complet</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user['name']); ?>"
                        required
                        maxlength="100"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user['email']); ?>"
                        required
                        maxlength="150"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">
                        Téléphone <span style="font-weight:400;color:#888;">(optionnel)</span>
                    </label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                        maxlength="20"
                        placeholder="+509 __ __ - ____"
                    >
                    <p class="form-hint">Format recommandé : +509 XXXX-XXXX</p>
                </div>

                <div style="display:flex; gap:12px; align-items:center; margin-top:8px;">
                    <button type="submit" class="btn-submit">Enregistrer</button>
                    <a href="dashboard.php" class="btn-cancel">Annuler</a>
                </div>
            </form>
        </div>

    <!-- ── TAB : Mot de passe ── -->
    <?php else: ?>
        <div class="form-section">
            <form method="POST" action="security.php?tab=password" id="pwd-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action"     value="update_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Mot de passe actuel</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        class="form-control"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">Nouveau mot de passe</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-control"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        oninput="checkStrength(this.value)"
                    >
                    <div class="password-strength">
                        <span id="strength-label" style="color:#888;"></span>
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill" style="width:0%; background:#ef4444;"></div>
                        </div>
                    </div>
                    <p class="form-hint">Minimum 8 caractères. Utilisez des lettres, chiffres et symboles.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <div style="display:flex; gap:12px; align-items:center; margin-top:8px;">
                    <button type="submit" class="btn-submit">Changer le mot de passe</button>
                    <a href="dashboard.php" class="btn-cancel">Annuler</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div style="margin-top:24px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<!-- Scripts -->
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function checkStrength(val) {
    var fill  = document.getElementById('strength-fill');
    var label = document.getElementById('strength-label');
    var score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var colors = ['#ef4444','#f59e0b','#10b981','#10b981'];
    var labels = ['','Faible','Moyen','Fort','Très fort'];
    var pct    = (score / 4) * 100;

    fill.style.width      = pct + '%';
    fill.style.background = colors[score - 1] || '#ef4444';
    label.textContent     = score > 0 ? labels[score] : '';
}
</script>
</body>
</html>
