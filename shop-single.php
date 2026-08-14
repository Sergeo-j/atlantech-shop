<?php
/**
 * Fiche Produit — AtlanTech E-commerce
 * Connexion MySQL, dynamique, branding AtlanTech
 */

require_once 'config/config.php';
require_once 'includes/header_counters.php';

// ── ID du produit ────────────────────────────────────────────
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: shop.php');
    exit();
}

// ── Charger le produit ───────────────────────────────────────
$stmt = $mysqli->prepare("
    SELECT p.*,
           c.name  AS category_name, c.id AS cat_id,
           b.name  AS brand_name
    FROM   products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands     b ON p.brand_id     = b.id
    WHERE  p.id = ? AND p.is_active = 1
    LIMIT  1
");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: shop.php?error=not_found');
    exit();
}

// ── Images galerie (table product_images) ───────────────────
$gallery = [];
$stmt = $mysqli->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC LIMIT 6");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$gres = $stmt->get_result();
while ($gi = $gres->fetch_assoc()) {
    $gallery[] = $gi['image'];
}
$stmt->close();

// ── Couleurs disponibles (table product_colors → colors) ────
$product_colors = [];
$stmt = $mysqli->prepare("
    SELECT c.id, c.name, c.hex_code, pc.price
    FROM product_colors pc
    JOIN colors c ON c.id = pc.color_id
    WHERE pc.product_id = ?
    ORDER BY c.name ASC
");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$cres = $stmt->get_result();
while ($cc = $cres->fetch_assoc()) {
    $product_colors[] = $cc;
}
$stmt->close();

// Image principale toujours en premier
$main_img_file = $product['image'] ?? null;
$fb_main = sprintf('assets/img/product/img_%02d.png', ((($product_id - 1) % 177) + 1));
$main_img = $main_img_file ? 'uploads/products/' . $main_img_file : $fb_main;

// Si pas de galerie, on génère 4 images de remplacement cohérentes
if (empty($gallery)) {
    for ($gi = 0; $gi < 4; $gi++) {
        $n = (($product_id + $gi) % 177) + 1;
        $gallery[] = 'local:assets/img/product/img_' . $n . '.png';
    }
}

// ── Avis / Reviews ───────────────────────────────────────────
$reviews = [];
$stmt = $mysqli->prepare("
    SELECT r.*, u.name AS user_name
    FROM   reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE  r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT  6
");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$rres = $stmt->get_result();
while ($rv = $rres->fetch_assoc()) { $reviews[] = $rv; }
$stmt->close();

// Nombre total d'avis approuvés (pas seulement les 6 affichés)
$stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM reviews WHERE product_id = ? AND status = 'approved'");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$review_count = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
$stmt->close();

// ── Produits similaires (même catégorie, sinon populaires) ───
$related = [];
$related_sql = "SELECT p.id, p.name, p.price, p.old_price, p.image, p.rating, p.stock, p.is_new, p.sold_count,
                       b.name AS brand_name
                FROM   products p
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE  p.is_active = 1
                  AND  p.id != ?
                  %FILTER%
                ORDER BY p.sold_count DESC, p.rating DESC
                LIMIT 8";

// 1er essai : même catégorie
if (!empty($product['cat_id'])) {
    $cat_id = (int)$product['cat_id'];
    $sql = str_replace('%FILTER%', 'AND p.category_id = ?', $related_sql);
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $product_id, $cat_id);
    $stmt->execute();
    $rp = $stmt->get_result();
    while ($r = $rp->fetch_assoc()) { $related[] = $r; }
    $stmt->close();
}

// Fallback : produits populaires si < 4 résultats
if (count($related) < 4) {
    $existing_ids = array_merge([$product_id], array_column($related, 'id'));
    $ph = implode(',', array_fill(0, count($existing_ids), '?'));
    $types = str_repeat('i', count($existing_ids));
    $need = 8 - count($related);
    $sql2 = "SELECT p.id, p.name, p.price, p.old_price, p.image, p.rating, p.stock, p.is_new, p.sold_count,
                    b.name AS brand_name
             FROM   products p
             LEFT JOIN brands b ON p.brand_id = b.id
             WHERE  p.is_active = 1 AND p.id NOT IN ($ph)
             ORDER BY p.sold_count DESC, p.rating DESC
             LIMIT $need";
    $stmt2 = $mysqli->prepare($sql2);
    $stmt2->bind_param($types, ...$existing_ids);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    while ($r = $r2->fetch_assoc()) { $related[] = $r; }
    $stmt2->close();
}

fill_product_images($related);

// ── Catégories pour la nav / recherche ──────────────────────
$cat_res = $mysqli->query("SELECT id, name, icon FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY display_order ASC LIMIT 9");
$categories = $cat_res ? $cat_res->fetch_all(MYSQLI_ASSOC) : [];

// ── Session utilisateur ──────────────────────────────────────
$user_name       = $_SESSION['user_name'] ?? null;
$user_first_name = $user_name ? explode(' ', $user_name)[0] : null;

// ── Panier session (requête DB) ──────────────────────────────
$cart_items  = [];
$cart_total  = 0.0;
$cart_count  = 0;
if (!empty($_SESSION['cart'])) {
    $_p_ids  = array_map('intval', array_keys($_SESSION['cart']));
    $_ph     = implode(',', array_fill(0, count($_p_ids), '?'));
    $_types  = str_repeat('i', count($_p_ids));
    $_st     = $mysqli->prepare("SELECT id, name, price, old_price, image FROM products WHERE id IN ($_ph) AND is_active = 1");
    $_st->bind_param($_types, ...$_p_ids);
    $_st->execute();
    foreach ($_st->get_result()->fetch_all(MYSQLI_ASSOC) as $_p) {
        $_qty = (int)$_SESSION['cart'][$_p['id']];
        $_unit = (float)$_p['price'];
        $cart_items[] = array_merge($_p, ['qty' => $_qty, 'unit_price' => $_unit]);
        $cart_total  += $_unit * $_qty;
        $cart_count  += $_qty;
    }
    $_st->close();
}

// ── Messages flash (retour reviews.php) ─────────────────────
$flash_type    = '';
$flash_message = '';
$flash_errors  = [
    'login_required'  => 'Vous devez être connecté pour laisser un avis.',
    'invalid_product' => 'Produit introuvable.',
    'invalid_rating'  => 'Veuillez sélectionner une note.',
    'empty_comment'   => 'Votre avis ne peut pas être vide.',
    'comment_too_long'=> 'Votre avis est trop long (2 000 caractères max).',
    'already_reviewed'=> 'Vous avez déjà soumis un avis pour ce produit.',
    'db_error'        => 'Une erreur est survenue. Veuillez réessayer.',
];
if (!empty($_GET['success']) && $_GET['success'] === 'review_sent') {
    $flash_type    = 'success';
    $flash_message = 'Merci ! Votre avis a été publié.';
} elseif (!empty($_GET['error']) && isset($flash_errors[$_GET['error']])) {
    $flash_type    = 'danger';
    $flash_message = $flash_errors[$_GET['error']];
}

// ── Étoiles ─────────────────────────────────────────────────
function stars_single(float $r): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="' . ($i <= round($r) ? 'fas' : 'far') . ' fa-star"></i>';
    }
    return $html;
}

// ── Prix formatés ────────────────────────────────────────────
$price     = (float)$product['price'];
$old_price = !empty($product['old_price']) && $product['old_price'] > $price ? (float)$product['old_price'] : 0;
$rating    = (float)($product['rating'] ?? 0);
$stock     = (int)($product['stock'] ?? 0);
$stock_pct = min(100, ($stock / max($stock, 100)) * 100);

// ── Attributs dynamiques ─────────────────────────────────────
$product_attrs = [];
if (!empty($product['attributes'])) {
    $decoded = json_decode(is_string($product['attributes']) ? $product['attributes'] : json_encode($product['attributes']), true);
    if (is_array($decoded)) $product_attrs = $decoded;
}
$product_type_label = $product['product_type'] ?? '';

// Labels lisibles pour tous les champs d'attributs
$ATTR_LABELS = [
    'genre'=>'Genre','tailles'=>'Tailles','matiere'=>'Matière','motif'=>'Motif',
    'style'=>'Style','coupe'=>'Coupe','saison'=>'Saison','longueur'=>'Longueur',
    'type_col'=>'Type de col','type_manche'=>'Type de manche','fermeture'=>'Fermeture',
    'occasion'=>'Occasion','pays_fab'=>'Pays de fabrication','poids'=>'Poids (g)',
    'type_chaussure'=>'Type de chaussure','pointures'=>'Pointure(s)',
    'matiere_ext'=>'Matière extérieure','matiere_int'=>'Matière intérieure',
    'semelle'=>'Semelle','hauteur_talon'=>'Hauteur talon (cm)','usage'=>'Usage',
    'type_sac'=>'Type de sac','dimensions'=>'Dimensions','nb_compart'=>'Compartiments',
    'capacite'=>'Capacité','type_bijou'=>'Type de bijou','metal'=>'Métal',
    'pierre'=>'Pierre','taille'=>'Taille','hypoallergenique'=>'Hypoallergénique',
    'resistant_eau'=>'Résistant à l\'eau','type_beaute'=>'Type de produit',
    'volume'=>'Volume / Quantité','type_peau'=>'Type de peau','fonction'=>'Fonction',
    'ingredients'=>'Ingrédients clés','parfum_prod'=>'Parfum / Senteur','dlc'=>'DLC',
    'certification'=>'Certification','usage_beaute'=>'Usage',
    'sous_type'=>'Sous-catégorie','modele'=>'Modèle','reference'=>'Référence fabricant',
    'couleur'=>'Couleur(s)','ecran'=>'Taille écran','resolution'=>'Résolution',
    'ram'=>'RAM','stockage'=>'Stockage','processeur'=>'Processeur',
    'batterie'=>'Batterie','camera_avant'=>'Caméra avant','camera_arriere'=>'Caméra arrière',
    'sim'=>'SIM','reseau'=>'Réseau','garantie'=>'Garantie','pays_origine'=>'Pays d\'origine',
    'os'=>'Système d\'exploitation','connectivite'=>'Connectivité',
    'ports'=>'Ports / Connectique','autonomie'=>'Autonomie',
    'type_mobilier'=>'Type de meuble','materiaux'=>'Matériaux',
    'dimensions_prod'=>'Dimensions (cm)','poids_kg'=>'Poids (kg)',
    'assemblage'=>'Assemblage requis','type_sport'=>'Type de sport',
    'taille_equipement'=>'Taille équipement','surface'=>'Surface recommandée',
    'niveau'=>'Niveau pratiquant','type_livre'=>'Type de livre','langue'=>'Langue',
    'nb_pages'=>'Nombre de pages','auteur'=>'Auteur','editeur'=>'Éditeur',
    'isbn'=>'ISBN','annee_pub'=>'Année de publication','format'=>'Format',
    'type_jouet'=>'Type de jouet','age_min'=>'Âge minimum','nb_joueurs'=>'Nb joueurs',
    'materiau'=>'Matériau','piles'=>'Piles requises','type_aliment'=>'Type d\'aliment',
    'poids_net'=>'Poids net','contenance'=>'Contenance','allergenes'=>'Allergènes',
    'conservation'=>'Conservation','origine'=>'Origine','bio'=>'Bio / Naturel',
    'halal'=>'Halal','vegan'=>'Végan',
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php
    $site_url   = rtrim(env('SITE_URL', 'http://localhost/atlantech-shop'), '/');
    $p_name     = htmlspecialchars($product['name'], ENT_QUOTES);
    $p_desc_raw = !empty($product['short_description'])
                    ? $product['short_description']
                    : ($product['description'] ?? $product['name']);
    $p_desc     = htmlspecialchars(mb_substr(strip_tags($p_desc_raw), 0, 160), ENT_QUOTES);
    $p_url      = $site_url . '/shop-single.php?id=' . $product_id;
    $p_img      = !empty($product['image'])
                    ? $site_url . '/uploads/products/' . $product['image']
                    : $site_url . '/assets/img/logo/logo.png';
    $p_price    = number_format((float)$product['price'], 2, '.', '');
    $p_avail    = ((int)($product['stock'] ?? 0) > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    $p_cat      = htmlspecialchars($product['category_name'] ?? 'Technologie', ENT_QUOTES);
    $p_brand    = htmlspecialchars($product['brand_name'] ?? 'AtlanTech', ENT_QUOTES);
    $p_rating   = round((float)($product['rating'] ?? 0), 1);
    $p_reviews  = (int)($product['review_count'] ?? $review_count ?? 0);
    ?>
    <title><?= $p_name ?> — AtlanTech Haïti</title>
    <meta name="description" content="<?= $p_desc ?>" />
    <meta name="robots" content="index, follow" />
    <link rel="canonical" href="<?= $p_url ?>" />

    <!-- Open Graph -->
    <meta property="og:type"        content="product" />
    <meta property="og:url"         content="<?= $p_url ?>" />
    <meta property="og:title"       content="<?= $p_name ?> — AtlanTech" />
    <meta property="og:description" content="<?= $p_desc ?>" />
    <meta property="og:image"       content="<?= $p_img ?>" />
    <meta property="og:site_name"   content="AtlanTech" />
    <meta property="product:price:amount"   content="<?= $p_price ?>" />
    <meta property="product:price:currency" content="HTG" />

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image" />
    <meta name="twitter:title"       content="<?= $p_name ?> — AtlanTech" />
    <meta name="twitter:description" content="<?= $p_desc ?>" />
    <meta name="twitter:image"       content="<?= $p_img ?>" />

    <!-- Schema.org — Product -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Product",
      "name": "<?= addslashes($product['name']) ?>",
      "description": "<?= addslashes(strip_tags($p_desc_raw)) ?>",
      "url": "<?= $p_url ?>",
      "image": "<?= $p_img ?>",
      "sku": "<?= addslashes($product['sku'] ?? 'ATL-' . $product_id) ?>",
      "category": "<?= $p_cat ?>",
      "brand": {
        "@type": "Brand",
        "name": "<?= $p_brand ?>"
      },
      "offers": {
        "@type": "Offer",
        "url": "<?= $p_url ?>",
        "priceCurrency": "HTG",
        "price": "<?= $p_price ?>",
        "availability": "<?= $p_avail ?>",
        "seller": {
          "@type": "Organization",
          "name": "AtlanTech"
        }
      }
      <?php if ($p_rating > 0): ?>
      ,"aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "<?= $p_rating ?>",
        "bestRating": "5",
        "worstRating": "1",
        "reviewCount": "<?= max(1, $p_reviews) ?>"
      }
      <?php endif; ?>
    }
    </script>

    <link rel="shortcut icon" href="assets/img/favicon.png" type="image/x-icon"/>
  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css" />
  <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime(__DIR__.'/assets/css/mobile.css'); ?>" />
  <!-- Google Fonts non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>
    <style>
        .product-gallery-main img { width:100%; border-radius:8px; object-fit:contain; max-height:420px; }
        .shop_thumb_tab .nav-link { padding:4px; border:2px solid transparent; border-radius:6px; background:none; }
        .shop_thumb_tab .nav-link.active,
        .shop_thumb_tab .nav-link:hover { border-color: var(--color-primary, #e87c1e); }
        .shop_thumb_tab .nav-link img { width:70px; height:70px; object-fit:cover; border-radius:4px; }
        /* Tableau attributs dynamiques */
        .attrs-table { width:100%; border-collapse:collapse; margin-top:8px; }
        .attrs-table th, .attrs-table td { padding:10px 14px; border-bottom:1px solid #eee; text-align:left; font-size:14px; vertical-align:top; }
        .attrs-table th { width:38%; color:#555; font-weight:600; background:#f9f9f9; white-space:nowrap; }
        .attrs-table td { color:#333; }
        .attrs-table tr:last-child th, .attrs-table tr:last-child td { border-bottom:none; }
        .attrs-table .chip-val { display:inline-block; background:#eef2ff; color:#4338ca; border-radius:999px; padding:2px 10px; font-size:12px; margin:2px 3px 2px 0; }
        .product-meta-row { display:flex; flex-wrap:wrap; gap:8px; margin:12px 0; }
        .meta-pill { font-size:13px; padding:4px 12px; border-radius:20px; background:#f4f4f4; color:#444; }
        .meta-pill span { font-weight:600; }
        .product-share a { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#f0f0f0; color:#444; margin-right:6px; transition:.2s; }
        .product-share a:hover { background: var(--color-primary, #e87c1e); color:#fff; }
        .qty-wrap { display:flex; align-items:center; gap:8px; }
        .qty-wrap button { width:34px; height:34px; border-radius:6px; border:1px solid #ddd; background:#f9f9f9; font-size:18px; cursor:pointer; }
        .qty-wrap input { width:60px; text-align:center; height:34px; border:1px solid #ddd; border-radius:6px; }
        .stock-bar { height:6px; border-radius:3px; background:#eee; margin:6px 0 4px; }
        .stock-bar .bar { height:100%; border-radius:3px; background: var(--color-primary, #e87c1e); }
        .badge-new  { background:#26c080; color:#fff; font-size:11px; padding:2px 8px; border-radius:3px; }
        .badge-hot  { background:#e87c1e; color:#fff; font-size:11px; padding:2px 8px; border-radius:3px; }
        .client-rv  { display:flex; gap:14px; margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid #f0f0f0; }
        .client-rv:last-child { border-bottom:none; }
        .client-rv .avatar { width:50px; height:50px; border-radius:50%; object-fit:cover; flex-shrink:0; }
        .client-rv .rv-name { font-weight:600; margin-bottom:2px; }
        .client-rv .rv-date { font-size:12px; color:#999; margin-bottom:6px; }
        .star-rating-input { display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:4px; }
        .star-rating-input input { display:none; }
        .star-rating-input label { font-size:22px; color:#ddd; cursor:pointer; }
        .star-rating-input input:checked ~ label,
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label { color:#f5a623; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header_mobile_v2.php'; ?>
<?php include __DIR__ . '/includes/promo_banner.php'; ?>
<div class="body_wrap">

    <!-- preloder -->
    <div class="preloder_part"><div class="spinner"><div class="dot1"></div><div class="dot2"></div></div></div>

    <!-- back to top -->
    <div class="progress-wrap">
        <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98"/>
        </svg>
    </div>

    <!-- ======================================================= HEADER -->
    <header class="header header__style-one">
        <div class="header__top-info-wrap d-none d-lg-block">
            <div class="container">
                <div class="header__top-info ul_li_between mt-none-10">
                    <ul class="ul_li mt-10">
                        <li><i class="far fa-map-marker-alt"></i> Les Cayes, Sud d'Haïti</li>
                        <li><i class="far fa-truck"></i> Suivez votre commande en ligne</li>
                        <li><i class="fas fa-phone"></i> Appelez-nous : <strong>(+509) 44 66 75 53</strong></li>
                        <li><i class="fas fa-heart"></i> Bienvenue chez <strong>AtlanTech</strong> — Votre partenaire tech &amp; innovation</li>
                    </ul>
                    <div class="header__top-right ul_li mt-10">
                        <div class="date"><i class="fal fa-calendar-alt"></i> <?php echo date("d M Y"); ?></div>
                        <div class="header__social ml-25">
                            <a href="https://facebook.com/atlantech.service" target="_blank"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
                            <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="header__middle ul_li_between justify-content-xs-center">
                <div class="header__logo">
                    <a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
                </div>
                <form class="header__search-box" action="shop.php" method="get">
                    <div class="select-box">
                        <select name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($product['cat_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="search" placeholder="Rechercher un produit...">
                    <button type="submit"><i class="far fa-search"></i></button>
                </form>
                <div class="header__icons ul_li">
                    <div class="icon"><a href="account.php"><img loading="lazy" src="assets/img/icon/user.svg" alt="Mon compte"></a></div>
                    <div class="icon wishlist-icon">
                        <a href="wishlist.php"><img loading="lazy" src="assets/img/icon/heart.svg" alt="Favoris"><span class="count"><?= (int)$wishlist_count ?></span></a>
                    </div>
                    <div class="cart_btn icon">
                        <a href="cart.php"><img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt="Panier">
                            <span class="count"><?= (int)$cart_count ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="header__cat-wrap" data-uk-sticky="top: 250; animation: uk-animation-slide-top;">
            <div class="container">
                <div class="header__wrap ul_li_between">
                    <div class="header__cat ul_li">
                        <div class="hamburger_menu">
                            <a href="javascript:void(0);" class="active"><div class="icon bar"><span><i class="fal fa-bars"></i></span></div></a>
                        </div>
                    </div>
                    <div class="login-sign-btn">
                        <?php if ($user_first_name): ?>
                            <a class="thm-btn" href="account.php">
                                <span class="btn-wrap"><span>Bonjour, <?php echo htmlspecialchars($user_first_name); ?></span><span>Mon Compte</span></span>
                            </a>
                        <?php else: ?>
                            <a class="thm-btn" href="account.php">
                                <span class="btn-wrap"><span>Connexion / Inscription</span><span>Connexion / Inscription</span></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- header end -->

    <!-- ======================================================= SLIDE-BAR -->
    <aside class="slide-bar">
        <div class="close-mobile-menu"><a href="javascript:void(0);"><i class="fal fa-times"></i></a></div>

        <!-- Panier latéral -->
        <?php include 'config/cart-sidebar.php'; ?>


        <!-- Menu mobile -->
        <nav class="side-mobile-menu">
            <div class="header-mobile-search">
                <form role="search" method="get" action="shop.php">
                    <input type="text" name="search" placeholder="Rechercher...">
                    <button type="submit"><i class="ti-search"></i></button>
                </form>
            </div>
            <ul id="mobile-menu-active">
                <li><a href="index.php">Accueil</a></li>
                <li class="dropdown">
                    <a href="shop.php">Boutique</a>
                    <ul class="sub-menu">
                        <li><a href="shop.php">Tous les produits</a></li>
                        <li><a href="cart.php">Mon panier</a></li>
                        <li><a href="checkout.php">Commander</a></li>
                        <li><a href="account.php">Mon compte</a></li>
                    </ul>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li><a href="shop.php?category=<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                <?php endforeach; ?>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </aside>
    <div class="body-overlay"></div>
    <!-- slide-bar end -->

    <main>
        <!-- Breadcrumb -->
        <section class="breadcrumb-area">
            <div class="container">
                <div class="atl-breadcrumb breadcrumbs">
                    <ul class="list-unstyled d-flex align-items-center">
                        <li class="atl-bcrumb-item atl-bcrumb-begin">
                            <a href="index.php"><span>Accueil</span></a>
                        </li>
                        <li class="atl-bcrumb-item">
                            <a href="shop.php"><span>Boutique</span></a>
                        </li>
                        <?php if (!empty($product['category_name'])): ?>
                            <li class="atl-bcrumb-item">
                                <a href="shop.php?category=<?php echo $product['cat_id']; ?>">
                                    <span><?php echo htmlspecialchars($product['category_name']); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="atl-bcrumb-item atl-bcrumb-end">
                            <span><?php echo htmlspecialchars(mb_substr($product['name'], 0, 50)); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ===================================================== FICHE PRODUIT -->
        <section class="shop-single-section pb-70">
            <div class="container">
                <div class="row">

                    <!-- ── Colonne Images ── -->
                    <div class="col-md-6">
                        <div class="product-single-wrap mb-30">
                            <div class="product_details_img product-gallery-main">
                                <div class="tab-content" id="galleryTabContent">
                                    <?php foreach ($gallery as $gi => $g_src):
                                        // Si format "local:", c'est une image locale (pas un upload)
                                        $is_local = strpos($g_src, 'local:') === 0;
                                        $g_url    = $is_local ? substr($g_src, 6) : 'uploads/products/' . $g_src;
                                        $g_fb     = sprintf('assets/img/product/img_%02d.png', ((($product_id + $gi) % 177) + 1));
                                        $tab_id   = 'gtab-' . $gi;
                                    ?>
                                        <div class="tab-pane <?php echo $gi === 0 ? 'show active' : ''; ?>"
                                             id="<?php echo $tab_id; ?>" role="tabpanel">
                                            <div class="pl_thumb">
                                                <img loading="lazy" src="<?php echo htmlspecialchars($g_url); ?>"
                                                     onerror="this.src='<?php echo $g_fb; ?>'; this.onerror=null;"
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Miniatures -->
                            <div class="shop_thumb_tab">
                                <ul class="nav" id="galleryTab2" role="tablist">
                                    <?php foreach ($gallery as $gi => $g_src):
                                        $is_local = strpos($g_src, 'local:') === 0;
                                        $g_url    = $is_local ? substr($g_src, 6) : 'uploads/products/' . $g_src;
                                        $g_fb     = sprintf('assets/img/product/img_%02d.png', ((($product_id + $gi) % 177) + 1));
                                        $tab_id   = 'gtab-' . $gi;
                                    ?>
                                        <li class="nav-item">
                                            <button class="nav-link <?php echo $gi === 0 ? 'active' : ''; ?>"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#<?php echo $tab_id; ?>"
                                                    type="button">
                                                <img loading="lazy" src="<?php echo htmlspecialchars($g_url); ?>"
                                                     onerror="this.src='<?php echo $g_fb; ?>'; this.onerror=null;"
                                                     alt="">
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- ── Colonne Détails ── -->
                    <div class="col-md-6 product-details-col">
                        <div class="pd2">
                        <?php
                        $cond_labels = ['new'=>'Neuf','refurbished'=>'Reconditionné','used_like_new'=>'Occasion — comme neuf','used_good'=>'Occasion — bon état','used_acceptable'=>'Occasion — état acceptable'];
                        $cond_display = $cond_labels[$product['condition'] ?? ''] ?? ($product['condition'] ?? '');
                        // $price / $old_price sont exprimés en HTG (voir fmt_price() plus bas, qui attend du HTG).
                        $savings_htg = ($old_price > 0 && $old_price > $price) ? $old_price - $price : 0;
                        $disc_pct    = ($old_price > 0 && $old_price > $price) ? round((($old_price - $price) / $old_price) * 100) : (int)($product['discount_percent'] ?? 0);
                        ?>

                        <!-- ① Badges top -->
                        <div class="pd2-badges">
                            <?php if ($product['is_new']): ?><span class="pd2-badge pd2-badge--new">Nouveau</span><?php endif; ?>
                            <?php if (!empty($product['badge'])): ?><span class="pd2-badge pd2-badge--hot"><?php echo htmlspecialchars($product['badge']); ?></span><?php endif; ?>
                            <?php if ($disc_pct > 0): ?><span class="pd2-badge pd2-badge--deal">Deal &minus;<?php echo $disc_pct; ?>%</span><?php endif; ?>
                        </div>

                        <!-- ② Titre -->
                        <h2 class="pd2-title"><?php echo htmlspecialchars($product['name']); ?></h2>

                        <!-- ③ Rating & vendus -->
                        <div class="pd2-rating">
                            <?php echo stars_single($rating); ?>
                            <span class="pd2-review-count">(<?php echo $review_count; ?> avis)</span>
                            <span class="pd2-sep">|</span>
                            <span class="pd2-sold"><?php echo (int)$product['sold_count']; ?> vendus</span>
                        </div>

                        <hr class="pd2-divider">

                        <!-- ④ Prix -->
                        <div class="pd2-price-block">
                            <?php if ($disc_pct > 0): ?>
                                <span class="pd2-discount-pct">&minus;<?php echo $disc_pct; ?>%</span>
                            <?php endif; ?>
                            <span class="pd2-price" id="current-price" data-base-price="<?php echo $price; ?>"><?= fmt_price($price) ?></span>
                            <?php if ($old_price > 0): ?>
                                <span class="pd2-old-price"><?= fmt_price($old_price) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($savings_htg > 0): ?>
                        <div class="pd2-savings">
                            Vous économisez : <strong><?= fmt_price($savings_htg) ?></strong>
                        </div>
                        <?php endif; ?>

                        <!-- ⑤ Résumé -->
                        <?php if (!empty($product['short_description'])): ?>
                            <p class="pd2-short-desc"><?php echo nl2br(htmlspecialchars($product['short_description'])); ?></p>
                        <?php endif; ?>

                        <!-- ⑥ Couleurs -->
                        <?php if (!empty($product_colors)): ?>
                        <div class="pd2-colors">
                            <p class="pd2-label">Couleur : <span id="selected-color-label" class="pd2-color-name"></span></p>
                            <div class="pd2-color-grid" id="color-picker">
                                <?php foreach ($product_colors as $pc): ?>
                                    <label class="color-pick" title="<?php echo htmlspecialchars($pc['name']); ?>">
                                        <input type="radio" name="picker_color" class="color-radio"
                                               value="<?php echo (int)$pc['id']; ?>"
                                               data-name="<?php echo htmlspecialchars($pc['name']); ?>"
                                               data-price="<?php echo ($pc['price'] !== null && (float)$pc['price'] > 0) ? (float)$pc['price'] : ''; ?>"
                                               style="display:none;">
                                        <span class="color-bubble" style="background:<?php echo htmlspecialchars($pc['hex_code']); ?>;"></span>
                                        <span class="pd2-color-label"><?php echo htmlspecialchars($pc['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p id="color-picker-error" class="pd2-color-error" style="display:none;">
                                <i class="fas fa-exclamation-circle"></i> Veuillez choisir une couleur avant de continuer.
                            </p>
                        </div>
                        <?php endif; ?>

                        <!-- ⑦ Box achat (encadré comme Amazon) -->
                        <div class="pd2-buy-box">

                            <!-- Stock -->
                            <div class="pd2-stock">
                                <?php if ($stock > 0): ?>
                                    <i class="fas fa-check-circle" style="color:#26c080;"></i>
                                    <span class="pd2-instock">En stock</span>
                                    <span class="pd2-stock-qty"><?php echo $stock; ?> unité<?php echo $stock > 1 ? 's' : ''; ?> disponibles</span>
                                    <div class="pd2-stock-bar"><div style="width:<?php echo $stock_pct; ?>%;"></div></div>
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color:#e74c3c;"></i>
                                    <span style="color:#e74c3c; font-weight:700;">Rupture de stock</span>
                                <?php endif; ?>
                            </div>

                            <!-- Livraison estimée -->
                            <?php if ($stock > 0): ?>
                            <div class="pd2-delivery">
                                <i class="fas fa-truck"></i>
                                <div>
                                    <span class="pd2-delivery-title">Livraison estimée</span>
                                    <span class="pd2-delivery-date">3 – 7 jours ouvrables</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Quantité -->
                            <?php if ($stock > 0): ?>
                            <div class="pd2-qty-row">
                                <span class="pd2-label">Quantité :</span>
                                <div class="qty-wrap">
                                    <button type="button" onclick="changeQty(-1)">−</button>
                                    <input type="number" id="qty-input" value="1" min="1" max="<?php echo $stock; ?>">
                                    <button type="button" onclick="changeQty(1)">+</button>
                                </div>
                            </div>

                            <!-- Boutons -->
                            <div class="pd2-actions">
                                <form class="form pd2-form-full" action="cart.php" method="post" onsubmit="return syncQty(this)">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                    <input type="hidden" name="qty" id="qty-cart">
                                    <input type="hidden" name="color_id"   class="field-color-id">
                                    <input type="hidden" name="color_name" class="field-color-name">
                                    <button class="pd2-btn pd2-btn--cart" type="submit">
                                        <i class="far fa-shopping-basket"></i> Ajouter au panier
                                    </button>
                                </form>

                                <form class="form pd2-form-full" action="buy-now.php" method="post" onsubmit="return syncQty(this)">
                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                    <input type="hidden" name="qty" id="qty-buynow">
                                    <input type="hidden" name="color_id"   class="field-color-id">
                                    <input type="hidden" name="color_name" class="field-color-name">
                                    <button class="pd2-btn pd2-btn--buy" type="submit">
                                        <i class="fas fa-bolt"></i> Acheter maintenant
                                    </button>
                                </form>

                                <a href="wishlist.php?add=<?php echo $product_id; ?>" class="pd2-btn pd2-btn--wish">
                                    <i class="far fa-heart"></i> Ajouter aux favoris
                                </a>
                            </div>

                            <!-- Trust badges -->
                            <div class="pd2-trust">
                                <div class="pd2-trust-item"><i class="fas fa-shield-alt"></i><span>Paiement sécurisé</span></div>
                                <div class="pd2-trust-item"><i class="fas fa-undo-alt"></i><span>Retour 7 jours</span></div>
                                <div class="pd2-trust-item"><i class="fas fa-certificate"></i><span>Produit authentique</span></div>
                            </div>

                            <?php else: ?>
                            <button class="pd2-btn pd2-btn--disabled" type="button" disabled>
                                <i class="fas fa-ban"></i> Produit indisponible
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- ⑧ Méta produit -->
                        <div class="pd2-meta">
                            <?php if (!empty($product['brand_name'])): ?>
                                <div class="pd2-meta-row"><span class="pd2-meta-key">Marque</span><span class="pd2-meta-val"><?php echo htmlspecialchars($product['brand_name']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($product['category_name'])): ?>
                                <div class="pd2-meta-row"><span class="pd2-meta-key">Catégorie</span><span class="pd2-meta-val"><a href="shop.php?category=<?php echo $product['cat_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></span></div>
                            <?php endif; ?>
                            <?php if (!empty($product_type_label)): ?>
                                <div class="pd2-meta-row"><span class="pd2-meta-key">Type</span><span class="pd2-meta-val"><?php echo htmlspecialchars(ucfirst($product_type_label)); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($product_attrs['tailles'])): ?>
                                <div class="pd2-meta-row">
                                    <span class="pd2-meta-key">Tailles</span>
                                    <span class="pd2-meta-val pd2-chips">
                                        <?php foreach ((array)$product_attrs['tailles'] as $t): ?>
                                            <span class="pd2-chip"><?php echo htmlspecialchars($t); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($cond_display)): ?>
                                <div class="pd2-meta-row"><span class="pd2-meta-key">État</span><span class="pd2-meta-val"><?php echo htmlspecialchars($cond_display); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($product['warranty'])): ?>
                                <div class="pd2-meta-row"><span class="pd2-meta-key">Garantie</span><span class="pd2-meta-val"><?php echo htmlspecialchars($product['warranty']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($product['sku'])): ?>
                                <div class="pd2-meta-row"><span class="pd2-meta-key">Référence</span><span class="pd2-meta-val"><?php echo htmlspecialchars($product['sku']); ?></span></div>
                            <?php endif; ?>
                        </div>

                        <!-- ⑨ Partage -->
                        <div class="pd2-share">
                            <span>Partager :</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' — https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" target="_blank" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($product['name']); ?>&url=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" target="_blank" title="Twitter/X"><i class="fab fa-twitter"></i></a>
                        </div>

                        </div><!-- .pd2 -->
                    </div><!-- end col -->

                </div><!-- end row -->

                <!-- ===== TABS : Description / Spécifications / Avis -->
                <div class="row" style="margin-top:50px;">
                    <div class="col col-xs-12">
                        <div class="single-product-info">
                            <div class="tablist">
                                <ul class="nav nav-tabs" id="pills-tab" role="tablist">
                                    <li><button class="active" data-bs-toggle="pill" data-bs-target="#tb-desc">Description</button></li>
                                    <?php if (!empty($product_attrs)): ?>
                                        <li><button data-bs-toggle="pill" data-bs-target="#tb-attrs">Caractéristiques</button></li>
                                    <?php endif; ?>
                                    <?php if (!empty($product['specifications'])): ?>
                                        <li><button data-bs-toggle="pill" data-bs-target="#tb-specs">Spécifications</button></li>
                                    <?php endif; ?>
                                    <li><button data-bs-toggle="pill" data-bs-target="#tb-reviews">Avis (<?php echo $review_count; ?>)</button></li>
                                </ul>
                            </div>

                            <div class="tab-content" id="pills-tabContent">

                                <!-- Description -->
                                <div class="tab-pane fade show active" id="tb-desc">
                                    <?php if (!empty($product['description'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                    <?php else: ?>
                                        <p>Aucune description disponible pour ce produit.</p>
                                    <?php endif; ?>
                                    <?php if (!empty($product['features'])): ?>
                                        <h5 style="margin-top:20px;">Points forts</h5>
                                        <p><?php echo nl2br(htmlspecialchars($product['features'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Caractéristiques dynamiques -->
                                <?php if (!empty($product_attrs)): ?>
                                <div class="tab-pane fade" id="tb-attrs">
                                    <table class="attrs-table">
                                        <tbody>
                                        <?php foreach ($product_attrs as $key => $val):
                                            $label   = $ATTR_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
                                            $is_arr  = is_array($val);
                                        ?>
                                        <tr>
                                            <th><?php echo htmlspecialchars($label); ?></th>
                                            <td>
                                            <?php if ($is_arr): ?>
                                                <?php foreach ($val as $chip): ?>
                                                    <span class="chip-val"><?php echo htmlspecialchars($chip); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($val); ?>
                                            <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>

                                <!-- Spécifications -->
                                <?php if (!empty($product['specifications'])): ?>
                                    <div class="tab-pane fade" id="tb-specs">
                                        <p><?php echo nl2br(htmlspecialchars($product['specifications'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Avis clients -->
                                <div class="tab-pane fade" id="tb-reviews">
                                    <div class="row">
                                        <!-- Liste des avis -->
                                        <div class="col-lg-6 col-sm-12">
                                            <?php if ($review_count > 0): ?>
                                                <?php foreach ($reviews as $rv):
                                                    $rv_rating = round((float)($rv['rating'] ?? 0));
                                                    $rv_date   = date('d M Y', strtotime($rv['created_at'] ?? 'now'));
                                                ?>
                                                    <div class="client-rv">
                                                        <img loading="lazy" class="avatar"
                                                             src="assets/img/avatar/comments/img_01.jpg"
                                                             alt="<?php echo htmlspecialchars($rv['user_name'] ?? 'Client'); ?>">
                                                        <div>
                                                            <div class="rv-name"><?php echo htmlspecialchars($rv['user_name'] ?? 'Client anonyme'); ?></div>
                                                            <div class="rv-date"><?php echo $rv_date; ?></div>
                                                            <div class="rating" style="font-size:13px; color:#f5a623;">
                                                                <?php echo stars_single((float)$rv_rating); ?>
                                                            </div>
                                                            <p style="margin-top:6px; font-size:14px;"><?php echo htmlspecialchars($rv['comment'] ?? $rv['review'] ?? ''); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p style="color:#777;">Aucun avis pour ce produit. Soyez le premier à donner votre avis !</p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Formulaire d'avis -->
                                        <div class="col-lg-6 col-sm-12 review-form-wrapper">
                                            <div class="review-form">
                                                <h4>Laisser un avis</h4>

                                                <?php if (!empty($flash_message)): ?>
                                                    <div class="alert alert-<?php echo $flash_type; ?>" style="margin-bottom:14px; padding:10px 14px; border-radius:6px; font-size:14px;">
                                                        <?php echo htmlspecialchars($flash_message); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (isLoggedIn()): ?>
                                                    <?php if ($flash_type !== 'success'): ?>
                                                    <form action="reviews.php" method="post">
                                                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                        <div style="margin-bottom:12px;">
                                                            <textarea class="form-control" name="comment" rows="4" placeholder="Partagez votre expérience avec ce produit *" required></textarea>
                                                        </div>
                                                        <div class="rating-wrapper" style="margin-bottom:16px;">
                                                            <label style="font-size:13px; color:#777; display:block; margin-bottom:6px;">Note :</label>
                                                            <div id="atl-stars" style="display:flex;flex-direction:row;gap:6px;">
                                                                <input type="radio" name="rating" id="s1" value="1" style="display:none">
                                                                <input type="radio" name="rating" id="s2" value="2" style="display:none">
                                                                <input type="radio" name="rating" id="s3" value="3" checked style="display:none">
                                                                <input type="radio" name="rating" id="s4" value="4" style="display:none">
                                                                <input type="radio" name="rating" id="s5" value="5" style="display:none">
                                                                <span class="atl-star" data-v="1" style="font-size:24px;color:#ddd;cursor:pointer;line-height:1;">&#9733;</span>
                                                                <span class="atl-star" data-v="2" style="font-size:24px;color:#ddd;cursor:pointer;line-height:1;">&#9733;</span>
                                                                <span class="atl-star" data-v="3" style="font-size:24px;color:#ddd;cursor:pointer;line-height:1;">&#9733;</span>
                                                                <span class="atl-star" data-v="4" style="font-size:24px;color:#ddd;cursor:pointer;line-height:1;">&#9733;</span>
                                                                <span class="atl-star" data-v="5" style="font-size:24px;color:#ddd;cursor:pointer;line-height:1;">&#9733;</span>
                                                            </div>
                                                            <script>
                                                            (function(){
                                                              var box = document.getElementById('atl-stars');
                                                              if (!box) return;
                                                              var stars = box.querySelectorAll('.atl-star');
                                                              function paint(n){
                                                                stars.forEach(function(s){
                                                                  s.style.color = (parseInt(s.dataset.v) <= n) ? '#f5a623' : '#ddd';
                                                                });
                                                              }
                                                              stars.forEach(function(s){
                                                                s.addEventListener('click', function(){
                                                                  var v = parseInt(this.dataset.v);
                                                                  document.getElementById('s' + v).checked = true;
                                                                  paint(v);
                                                                });
                                                                s.addEventListener('mouseenter', function(){ paint(parseInt(this.dataset.v)); });
                                                              });
                                                              box.addEventListener('mouseleave', function(){
                                                                var c = box.querySelector('input:checked');
                                                                paint(c ? parseInt(c.value) : 0);
                                                              });
                                                              var c = box.querySelector('input:checked');
                                                              paint(c ? parseInt(c.value) : 0);
                                                            })();
                                                            </script>
                                                            <div class="submit" style="margin-top:14px;">
                                                                <button class="thm-btn thm-btn__2 no-icon" type="submit">
                                                                    <span class="btn-wrap">
                                                                        <span>Soumettre l'avis</span>
                                                                        <span>Soumettre l'avis</span>
                                                                    </span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p style="font-size:14px; color:#555; margin-bottom:14px;">
                                                        Vous devez être connecté pour laisser un avis.
                                                    </p>
                                                    <a href="login.php?redirect=<?php echo urlencode('shop-single.php?id=' . $product_id . '#tb-reviews'); ?>"
                                                       class="thm-btn thm-btn__2 no-icon">
                                                        <span class="btn-wrap">
                                                            <span>Se connecter</span>
                                                            <span>Se connecter</span>
                                                        </span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- end tab-content -->
                        </div>
                    </div>
                </div><!-- end row tabs -->

                <!-- ===== PRODUITS SIMILAIRES -->
                <?php if (!empty($related)): ?>
                <section class="related-products-section">
                    <div class="related-products-header">
                        <h3 class="related-products-title">
                            <span class="rp-accent"></span>
                            Vous aimerez aussi
                        </h3>
                        <a href="shop.php<?php echo !empty($product['cat_id']) ? '?category='.(int)$product['cat_id'] : ''; ?>" class="rp-see-all">
                            Voir tout <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="related-products-scroll">
                        <?php foreach ($related as $rp):
                            $rp_fb  = sprintf('assets/img/product/img_%02d.png', ((((int)$rp['id']-1)%177)+1));
                            $rp_img = !empty($rp['image']) ? 'uploads/products/' . htmlspecialchars($rp['image']) : $rp_fb;
                            $rp_url = 'shop-single.php?id=' . (int)$rp['id'];
                            $rp_has_old = !empty($rp['old_price']) && (float)$rp['old_price'] > (float)$rp['price'];
                            $rp_rating  = round((float)($rp['rating'] ?? 0));
                            $rp_in_stock = (int)($rp['stock'] ?? 0) > 0;
                        ?>
                        <a href="<?php echo $rp_url; ?>" class="rp-card">
                            <?php if ($rp['is_new']): ?>
                                <span class="rp-badge">Nouveau</span>
                            <?php endif; ?>
                            <div class="rp-img-wrap">
                                <img loading="lazy"
                                     src="<?php echo $rp_img; ?>"
                                     onerror="this.src='<?php echo $rp_fb; ?>'; this.onerror=null;"
                                     alt="<?php echo htmlspecialchars($rp['name']); ?>">
                            </div>
                            <div class="rp-info">
                                <p class="rp-name"><?php echo htmlspecialchars($rp['name']); ?></p>
                                <div class="rp-stars">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="<?php echo $s <= $rp_rating ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="rp-price-row">
                                    <span class="rp-price"><?= fmt_price((float)$rp['price']) ?></span>
                                    <?php if ($rp_has_old): ?>
                                        <span class="rp-old-price"><?= fmt_price((float)$rp['old_price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$rp_in_stock): ?>
                                    <span class="rp-rupture">Rupture</span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

            </div><!-- end container -->
        </section>
        <!-- end shop-single-section -->

    </main>

    <!-- ======================================================= FOOTER -->
    <footer class="footer" data-background="assets/img/bg/footer_bg.jpg">
        <div class="newslater newslater__border pt-30 pb-30">
            <div class="container">
                <div class="newslater__two ul_li">
                    <div class="newslater__content">
                        <h2 class="title">Besoin d'aide ? <span>Contactez-nous</span></h2>
                        <p>Notre équipe est disponible pour vous accompagner</p>
                    </div>
                    <form class="newslater__form" action="#!">
                        <input placeholder="Votre adresse email" type="email">
                        <button>S'abonner</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="footer__main pt-90 pb-90">
                <div class="row mt-none-40">
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <div class="footer__logo mb-20">
                            <a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
                        </div>
                        <p>AtlanTech — Votre partenaire technologique à Les Cayes, Haïti. Produits high-tech, service de qualité.</p>
                        <ul class="footer__info mt-30">
                            <li><i class="far fa-map-marker-alt"></i> Les Cayes, Sud, Haïti</li>
                            <li><i class="fas fa-phone"></i> (+509) 44 66 75 53</li>
                            <li><i class="far fa-envelope"></i> atlantech.service@gmail.com</li>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Catégories</h2>
                        <ul class="quick-links">
                            <?php foreach (array_slice($categories, 0, 7) as $cat): ?>
                                <li><a href="shop.php?category=<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Liens rapides</h2>
                        <ul class="quick-links">
                            <li><a href="account.php">Mon compte</a></li>
                            <li><a href="cart.php">Mon panier</a></li>
                            <li><a href="checkout.php">Commander</a></li>
                            <li><a href="shop.php">Tous les produits</a></li>
                            <li><a href="contact.php">Nous contacter</a></li>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Service client</h2>
                        <ul class="category">
                            <li><a href="#!">Centre d'aide</a></li>
                            <li><a href="#!">Politique de retour</a></li>
                            <li><a href="#!">Livraison &amp; Suivi</a></li>
                            <li><a href="#!">Conditions d'utilisation</a></li>
                            <li><a href="#!">Politique de confidentialité</a></li>
                            <li><a href="#!">FAQ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer__bottom ul_li_center">
                <div class="footer__copyright mt-15">
                    &copy; <?php echo date('Y'); ?> <a href="index.php">AtlanTech</a>. Tous droits réservés.
                </div>
                <div class="footer__social mt-15">
                    <a href="https://facebook.com/atlantech.service" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
                </div>
                <div class="payment_method mt-15">
                    <img loading="lazy" src="assets/img/bg/payment_method.png" alt="Méthodes de paiement">
                </div>
            </div>
        </div>
    </footer>
    <!-- footer end -->

</div><!-- end body_wrap -->

<!-- Scripts -->
<script src="assets/js/jquery-3.5.1.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/slick.js"></script>
<script src="assets/js/backToTop.js"></script>
<script src="assets/js/uikit.min.js"></script>
<script src="assets/js/resize-sensor.min.js"></script>
<script src="assets/js/theia-sticky-sidebar.min.js"></script>
<script src="assets/js/wow.min.js"></script>
<script src="assets/js/jqueryui.js"></script>
<script src="assets/js/touchspin.js"></script>
<script src="assets/js/jquery.magnific-popup.min.js"></script>
<script src="assets/js/metisMenu.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/js/atl-cart.js"></script>

<script>
// Quantité — +/−
function changeQty(delta) {
    const input = document.getElementById('qty-input');
    const val   = parseInt(input.value) + delta;
    const max   = parseInt(input.max) || 999;
    input.value = Math.max(1, Math.min(val, max));
}

// Couleur sélectionnée par le client
var __hasColors = document.querySelectorAll('.color-radio').length > 0;

// Formate un prix avec HTG + USD (taux lu depuis la BD via _atl_usd_rate())
var __USD_RATE = <?= _atl_usd_rate() ?>;
function fmtPrice(n) {
    var htg = n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    var usd = '$' + (n / __USD_RATE).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    return '<span class="p-usd">' + usd + '</span> <small class="p-htg">' + htg + ' HTG</small>';
}

document.addEventListener('DOMContentLoaded', function () {
    var radios   = document.querySelectorAll('.color-radio');
    var label    = document.getElementById('selected-color-label');
    var priceEl  = document.getElementById('current-price');
    var basePrice = priceEl ? parseFloat(priceEl.dataset.basePrice) : 0;

    radios.forEach(function (r) {
        r.addEventListener('change', function () {
            // Reporter le choix dans les deux formulaires
            document.querySelectorAll('.field-color-id').forEach(function (f) { f.value = r.value; });
            document.querySelectorAll('.field-color-name').forEach(function (f) { f.value = r.dataset.name; });
            if (label) label.textContent = r.dataset.name;

            // Une couleur est choisie : on efface l'état d'erreur éventuel
            var picker = document.getElementById('color-picker');
            var err    = document.getElementById('color-picker-error');
            if (picker) picker.classList.remove('pd2-color-grid--error');
            if (err) err.style.display = 'none';

            // Mettre à jour le prix affiché : prix de la couleur si défini, sinon prix de base
            if (priceEl) {
                var cp = parseFloat(r.dataset.price);
                priceEl.innerHTML = fmtPrice((!isNaN(cp) && cp > 0) ? cp : basePrice);
            }
        });
    });
});

// Synchronise la quantité + valide qu'une couleur est choisie avant submit
/* ── Produits similaires ── */
</script>
<style>
/* ══════════════════════════════════════════
   PD2 — Fiche produit style Amazon
   ══════════════════════════════════════════ */
.pd2 { padding:0 0 10px; }
.pd2-badges { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.pd2-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
.pd2-badge--new  { background:#e8f5e9; color:#2e7d32; }
.pd2-badge--hot  { background:#fff3e0; color:#e65100; }
.pd2-badge--deal { background:#e74c3c; color:#fff; }
.pd2-title { font-size:20px; font-weight:700; color:#0f172a; line-height:1.35; margin:0 0 10px; }
.pd2-rating { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:12px; font-size:13px; }
.pd2-review-count { color:#f46e24; font-weight:600; }
.pd2-sep { color:#ddd; }
.pd2-sold { color:#666; }
.pd2-divider { border:none; border-top:1px solid #f0f0f0; margin:12px 0; }
.pd2-price-block { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin-bottom:4px; }
.pd2-discount-pct { background:#e74c3c; color:#fff; font-size:13px; font-weight:700; padding:2px 8px; border-radius:4px; }
.pd2-price { font-size:28px; font-weight:800; color:#e74c3c; }
.pd2-old-price { font-size:15px; color:#999; text-decoration:line-through; }
.pd2-savings { font-size:13px; color:#26a65b; font-weight:600; margin-bottom:12px; }
.pd2-savings-htg { color:#888; font-weight:400; }
.pd2-short-desc { font-size:14px; color:#555; line-height:1.6; margin:10px 0 14px; }
.pd2-colors { margin:14px 0; }
.pd2-color-error { color:#e74c3c; font-size:12.5px; font-weight:600; margin:8px 0 0; }
.pd2-color-grid--error { outline:2px solid #e74c3c; outline-offset:6px; border-radius:10px; animation:pd2-shake .35s; }
@keyframes pd2-shake { 10%,90%{transform:translateX(-1px);} 20%,80%{transform:translateX(2px);} 30%,50%,70%{transform:translateX(-4px);} 40%,60%{transform:translateX(4px);} }
.pd2-label { font-size:13px; font-weight:600; color:#333; margin:0 0 8px; }
.pd2-color-name { color:#f46e24; }
.pd2-color-grid { display:flex; flex-wrap:wrap; gap:10px; }
.color-pick { display:flex; flex-direction:column; align-items:center; gap:4px; cursor:pointer; }
.color-bubble { width:32px; height:32px; border-radius:50%; border:3px solid #fff; box-shadow:0 0 0 2px #cbd5e1; display:block; transition:box-shadow .15s; }
.color-pick input.color-radio:checked + .color-bubble { box-shadow:0 0 0 2px #f46e24, 0 0 0 5px rgba(244,110,36,.25); }
.pd2-color-label { font-size:11px; color:#666; }
.pd2-buy-box { background:#f8fafc; border:1px solid #e8edf2; border-radius:12px; padding:18px; margin:16px 0; }
.pd2-stock { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:10px; font-size:13px; }
.pd2-instock { color:#26c080; font-weight:700; font-size:14px; }
.pd2-stock-qty { color:#666; }
.pd2-stock-bar { width:100%; height:4px; background:#e0e0e0; border-radius:2px; margin:6px 0 0; overflow:hidden; }
.pd2-stock-bar div { height:100%; background:#26c080; border-radius:2px; }
.pd2-delivery { display:flex; align-items:flex-start; gap:10px; background:#fff; border:1px solid #e0e7ef; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:13px; }
.pd2-delivery i { color:#f46e24; font-size:18px; margin-top:2px; }
.pd2-delivery div { display:flex; flex-direction:column; }
.pd2-delivery-title { font-weight:700; color:#222; }
.pd2-delivery-date { color:#26a65b; font-weight:600; }
.pd2-qty-row { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.pd2-qty-row .pd2-label { font-size:13px; white-space:nowrap; margin:0; }
.pd2-form-full { display:block; width:100%; }
.pd2-actions { display:flex; flex-direction:column; gap:10px; margin-bottom:14px; }
.pd2-btn { display:block; width:100%; padding:13px 20px; border:none; border-radius:50px; font-size:15px; font-weight:700; text-align:center; cursor:pointer; transition:all .2s; text-decoration:none; letter-spacing:.3px; box-sizing:border-box; }
.pd2-btn--cart  { background:#1a1a2e; color:#fff; }
.pd2-btn--cart:hover  { background:#f46e24; color:#fff; text-decoration:none; }
.pd2-btn--buy   { background:#f46e24; color:#fff; }
.pd2-btn--buy:hover   { background:#d45a10; color:#fff; text-decoration:none; }
.pd2-btn--wish  { background:#fff; color:#333; border:2px solid #e0e0e0; }
.pd2-btn--wish:hover  { border-color:#f46e24; color:#f46e24; text-decoration:none; }
.pd2-btn--disabled { background:#e0e0e0; color:#999; cursor:not-allowed; opacity:.7; }
.pd2-trust { display:flex; gap:6px; padding-top:12px; border-top:1px solid #eee; flex-wrap:wrap; }
.pd2-trust-item { display:flex; align-items:center; gap:5px; font-size:11.5px; color:#555; background:#fff; border:1px solid #e8e8e8; border-radius:20px; padding:4px 10px; }
.pd2-trust-item i { color:#f46e24; font-size:13px; }
.pd2-meta { border:1px solid #f0f0f0; border-radius:8px; overflow:hidden; margin-bottom:16px; }
.pd2-meta-row { display:flex; padding:8px 14px; font-size:13px; border-bottom:1px solid #f7f7f7; }
.pd2-meta-row:last-child { border-bottom:none; }
.pd2-meta-row:nth-child(even) { background:#fafafa; }
.pd2-meta-key { width:100px; min-width:100px; color:#888; font-weight:600; }
.pd2-meta-val { color:#222; flex:1; }
.pd2-meta-val a { color:#f46e24; }
.pd2-share { display:flex; align-items:center; gap:10px; font-size:13px; color:#888; }
.pd2-share a { width:32px; height:32px; border-radius:50%; background:#f4f4f4; display:inline-flex; align-items:center; justify-content:center; color:#555; transition:all .2s; text-decoration:none; }
.pd2-share a:hover { background:#f46e24; color:#fff; }
@media (max-width:767px) {
  .pd2-title { font-size:17px; }
  .pd2-price { font-size:24px; }
  .pd2-buy-box { padding:14px; }
  .pd2-trust-item { font-size:10.5px; padding:3px 8px; }
  .pd2-meta-key { width:85px; min-width:85px; }
}
.related-products-section { margin-top:50px; padding:0 0 40px; }
.related-products-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.related-products-title { font-size:20px; font-weight:700; color:#1a1a2e; display:flex; align-items:center; gap:10px; margin:0; }
.rp-accent { display:inline-block; width:4px; height:22px; background:#f46e24; border-radius:2px; }
.rp-see-all { font-size:13px; color:#f46e24; text-decoration:none; font-weight:600; white-space:nowrap; }
.rp-see-all:hover { text-decoration:underline; }
.related-products-scroll { display:flex; gap:14px; overflow-x:auto; padding-bottom:10px; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; }
.related-products-scroll::-webkit-scrollbar { height:4px; }
.related-products-scroll::-webkit-scrollbar-track { background:#f0f0f0; border-radius:2px; }
.related-products-scroll::-webkit-scrollbar-thumb { background:#f46e24; border-radius:2px; }
.rp-card { flex:0 0 155px; min-width:155px; background:#fff; border-radius:10px; border:1px solid #eee; text-decoration:none; color:inherit; scroll-snap-align:start; transition:box-shadow .2s, transform .2s; position:relative; overflow:hidden; display:block; }
.rp-card:hover { box-shadow:0 6px 20px rgba(244,110,36,.15); transform:translateY(-2px); text-decoration:none; color:inherit; }
.rp-badge { position:absolute; top:8px; left:8px; background:#f46e24; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; z-index:1; text-transform:uppercase; }
.rp-img-wrap { width:100%; aspect-ratio:1; background:#f9f9f9; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.rp-img-wrap img { width:100%; height:100%; object-fit:cover; transition:transform .3s; }
.rp-card:hover .rp-img-wrap img { transform:scale(1.05); }
.rp-info { padding:10px 10px 12px; }
.rp-name { font-size:12px; font-weight:600; color:#222; line-height:1.4; margin:0 0 5px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.rp-stars { color:#f8a51b; font-size:10px; margin-bottom:6px; }
.rp-price-row { display:flex; align-items:baseline; gap:5px; flex-wrap:wrap; }
.rp-price { font-size:14px; font-weight:700; color:#f46e24; }
.rp-old-price { font-size:11px; color:#aaa; text-decoration:line-through; }
.rp-rupture { display:inline-block; margin-top:4px; font-size:10px; color:#e74c3c; font-weight:600; }
@media (min-width:768px) {
  .related-products-scroll { display:grid; grid-template-columns:repeat(4,1fr); overflow-x:visible; gap:20px; }
  .rp-card { flex:none; min-width:auto; }
  .related-products-title { font-size:24px; }
}
</style>
<script>
function syncQty(form) {
    const qty = document.getElementById('qty-input').value;
    const hidden = form.querySelector('input[name="qty"]');
    if (hidden) hidden.value = qty;
    if (typeof __hasColors !== 'undefined' && __hasColors) {
        const checked = document.querySelector('.color-radio:checked');
        if (!checked) {
            var picker = document.getElementById('color-picker');
            var err    = document.getElementById('color-picker-error');
            if (picker) {
                picker.classList.add('pd2-color-grid--error');
                picker.scrollIntoView({ behavior:'smooth', block:'center' });
            }
            if (err) err.style.display = 'block';
            return false;
        }
        form.querySelector('.field-color-id').value   = checked.value;
        form.querySelector('.field-color-name').value = checked.dataset.name;
    }
    return true;
}
</script>
<script>
/* Fix onglets produit — jQuery UI intercepte data-bs-toggle="pill", on gère manuellement */
(function() {
    function initProductTabs() {
        var nav = document.getElementById('pills-tab');
        var content = document.getElementById('pills-tabContent');
        if (!nav || !content) return;

        var buttons = nav.querySelectorAll('button[data-bs-target]');

