<?php
/**
 * Préférences d'achat - AtlanTech E-commerce
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=preferences');

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Charger les préférences depuis la session (persistées en session car pas de table dédiée)
if (!isset($_SESSION['preferences'])) {
    $_SESSION['preferences'] = [
        'currency'          => 'HTG',
        'language'          => 'fr',
        'default_address'   => '',
        'default_payment'   => '',
        'order_updates'     => '1',
        'promo_emails'      => '1',
        'newsletter'        => '1',
        'save_cart'         => '1',
        'show_prices_usd'   => '0',
        'categories'        => [],
    ];
}
$prefs = $_SESSION['preferences'];

// Vérifier si l'utilisateur est dans la newsletter DB
$stmt = $mysqli->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $_SESSION['user_email'] ?? '');
$stmt->execute();
$in_newsletter = $stmt->get_result()->num_rows > 0;
$stmt->close();

$success = ''; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token invalide.';
    } else {
        $prefs['currency']        = in_array($_POST['currency']??'', ['HTG','USD']) ? $_POST['currency'] : 'HTG';
        $prefs['language']        = in_array($_POST['language']??'', ['fr','ht','en']) ? $_POST['language'] : 'fr';
        $prefs['order_updates']   = isset($_POST['order_updates'])  ? '1' : '0';
        $prefs['promo_emails']    = isset($_POST['promo_emails'])    ? '1' : '0';
        $prefs['newsletter']      = isset($_POST['newsletter'])      ? '1' : '0';
        $prefs['save_cart']       = isset($_POST['save_cart'])       ? '1' : '0';
        $prefs['show_prices_usd'] = isset($_POST['show_prices_usd']) ? '1' : '0';

        $allowed_cats = ['ordinateurs','smartphones','cameras','tv-audio','accessoires','gaming','imprimantes','reseaux','electromenagers'];
        $prefs['categories'] = array_intersect($_POST['categories'] ?? [], $allowed_cats);

        $_SESSION['preferences'] = $prefs;

        // Gérer la newsletter DB
        $email = $_SESSION['user_email'] ?? '';
        if ($prefs['newsletter'] === '1' && !$in_newsletter && !empty($email)) {
            $stmt = $mysqli->prepare("INSERT IGNORE INTO newsletter_subscribers (email, subscribed_at) VALUES (?, NOW())");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
        } elseif ($prefs['newsletter'] === '0' && $in_newsletter && !empty($email)) {
            $stmt = $mysqli->prepare("DELETE FROM newsletter_subscribers WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
        }

        $success = 'Vos préférences ont été enregistrées.';
    }
}

$categories = [
    'ordinateurs'    => ['icon' => '💻', 'label' => 'Ordinateurs & Laptops'],
    'smartphones'    => ['icon' => '📱', 'label' => 'Smartphones & Tablettes'],
    'cameras'        => ['icon' => '📷', 'label' => 'Caméras & Photos'],
    'tv-audio'       => ['icon' => '📺', 'label' => 'TV & Audio'],
    'accessoires'    => ['icon' => '🎧', 'label' => 'Accessoires Tech'],
    'gaming'         => ['icon' => '🎮', 'label' => 'Gaming & Consoles'],
    'imprimantes'    => ['icon' => '🖨️', 'label' => 'Imprimantes'],
    'reseaux'        => ['icon' => '📡', 'label' => 'Réseaux & Wi-Fi'],
    'electromenagers'=> ['icon' => '🏠', 'label' => 'Électroménagers'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Préférences d'achat - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:760px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

        .pref-section{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:24px;margin-bottom:18px;}
        .section-title{font-size:16px;font-weight:700;color:#0F1111;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #E7E7E7;display:flex;align-items:center;gap:8px;}
        .form-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F0F2F2;}
        .form-row:last-child{border-bottom:none;}
        .form-row-label{font-size:14px;color:#0F1111;}
        .form-row-desc{font-size:12px;color:#666;margin-top:2px;}
        .form-select{padding:7px 12px;border:1px solid #888C8C;border-radius:6px;font-size:13px;background:#fff;}
        .form-select:focus{outline:none;border-color:#e77600;}

        /* Toggle switch */
        .toggle-wrap{display:flex;align-items:center;}
        .toggle{position:relative;display:inline-block;width:44px;height:24px;}
        .toggle input{opacity:0;width:0;height:0;}
        .toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s;}
        .toggle-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
        .toggle input:checked + .toggle-slider{background:#e77600;}
        .toggle input:checked + .toggle-slider:before{transform:translateX(20px);}

        /* Catégories */
        .cats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:4px;}
        .cat-chip{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #D5D9D9;border-radius:8px;cursor:pointer;transition:all .2s;font-size:13px;}
        .cat-chip input{accent-color:#e77600;}
        .cat-chip:has(input:checked){border-color:#e77600;background:#fffde7;font-weight:600;}

        .btn-save{width:100%;padding:13px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:15px;font-weight:700;color:#0F1111;cursor:pointer;margin-top:4px;}
        .btn-save:hover{background:#F7CA00;}
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
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Préférences d'achat</span>
    </nav>
    <h1 class="page-title">Préférences d'achat</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

    <form method="POST" action="preferences.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <!-- Région & langue -->
        <div class="pref-section">
            <div class="section-title">🌍 Région &amp; Langue</div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">Devise d'affichage</div>
                    <div class="form-row-desc">Choisissez votre devise préférée</div>
                </div>
                <select name="currency" class="form-select">
                    <option value="HTG" <?php echo $prefs['currency']==='HTG'?'selected':''; ?>>HTG — Gourde haïtienne</option>
                    <option value="USD" <?php echo $prefs['currency']==='USD'?'selected':''; ?>>USD — Dollar américain</option>
                </select>
            </div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">Langue</div>
                    <div class="form-row-desc">Langue de l'interface</div>
                </div>
                <select name="language" class="form-select">
                    <option value="fr" <?php echo $prefs['language']==='fr'?'selected':''; ?>>Français</option>
                    <option value="ht" <?php echo $prefs['language']==='ht'?'selected':''; ?>>Kreyòl Ayisyen</option>
                    <option value="en" <?php echo $prefs['language']==='en'?'selected':''; ?>>English</option>
                </select>
            </div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">Afficher les prix en USD</div>
                    <div class="form-row-desc">En plus du prix en HTG</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="show_prices_usd" <?php echo $prefs['show_prices_usd']==='1'?'checked':''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <!-- Panier -->
        <div class="pref-section">
            <div class="section-title">🛒 Panier &amp; Commandes</div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">Sauvegarder le panier</div>
                    <div class="form-row-desc">Retrouver votre panier à chaque connexion</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="save_cart" <?php echo $prefs['save_cart']==='1'?'checked':''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <!-- Notifications & emails -->
        <div class="pref-section">
            <div class="section-title">📧 E-mails &amp; Notifications</div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">Mises à jour de commande</div>
                    <div class="form-row-desc">Confirmation, expédition, livraison</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="order_updates" <?php echo $prefs['order_updates']==='1'?'checked':''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">E-mails promotionnels</div>
                    <div class="form-row-desc">Offres spéciales, ventes flash, coupons</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="promo_emails" <?php echo $prefs['promo_emails']==='1'?'checked':''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="form-row">
                <div>
                    <div class="form-row-label">Newsletter AtlanTech</div>
                    <div class="form-row-desc">Nouveautés, actualités tech en Haïti</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="newsletter" <?php echo ($prefs['newsletter']==='1'||$in_newsletter)?'checked':''; ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <!-- Catégories favorites -->
        <div class="pref-section">
            <div class="section-title">⭐ Catégories favorites</div>
            <p style="font-size:13px;color:#666;margin-bottom:14px;">Personnalisez votre expérience — nous mettrons en avant les produits qui vous intéressent.</p>
            <div class="cats-grid">
                <?php foreach ($categories as $key => $cat): ?>
                    <label class="cat-chip">
                        <input type="checkbox" name="categories[]" value="<?php echo $key; ?>"
                               <?php echo in_array($key, $prefs['categories']??[]) ? 'checked' : ''; ?>>
                        <span><?php echo $cat['icon']; ?></span>
                        <span><?php echo $cat['label']; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn-save">Enregistrer mes préférences</button>
    </form>

    <div style="margin-top:20px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
