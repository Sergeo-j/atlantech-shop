<?php
/**
 * Page Boutique - AtlanTech E-commerce
 * Version dynamique — connexion MySQL, filtres, tri, pagination
 */

require_once 'config/config.php';
require_once 'includes/header_counters.php';

// ============================================================
// PARAMÈTRES DE FILTRES ET TRI (GET)
// ============================================================
$search      = isset($_GET['search'])   ? trim($_GET['search'])        : '';
$cat_id      = isset($_GET['category']) ? (int)$_GET['category']       : 0;
$brand_id    = isset($_GET['brand'])    ? (int)$_GET['brand']          : 0;
$orderby     = isset($_GET['orderby'])  ? $_GET['orderby']             : 'default';
$price_min   = isset($_GET['price_min'])? (float)$_GET['price_min']    : 0;
$price_max   = isset($_GET['price_max'])? (float)$_GET['price_max']    : 0;
$page        = isset($_GET['page'])     ? max(1, (int)$_GET['page'])   : 1;
$per_page    = 55;
$offset      = ($page - 1) * $per_page;

// ============================================================
// CHARGEMENT DES CATÉGORIES (menu + sidebar)
// ============================================================
$categories_result = $mysqli->query(
    "SELECT id, name, slug, icon, parent_id FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY display_order ASC LIMIT 9"
);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// ============================================================
// CHARGEMENT DES MARQUES (sidebar)
// ============================================================
$brands_result = $mysqli->query(
    "SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name ASC"
);
$brands = $brands_result->fetch_all(MYSQLI_ASSOC);

// ============================================================
// PRIX MIN / MAX GLOBAL (pour slider)
// ============================================================
$price_range = $mysqli->query("SELECT MIN(price) as pmin, MAX(price) as pmax FROM products WHERE is_active = 1")->fetch_assoc();
$global_min = (float)($price_range['pmin'] ?? 0);
$global_max = (float)($price_range['pmax'] ?? 999999);
if ($price_max == 0) $price_max = $global_max;

// ============================================================
// CONSTRUCTION DE LA REQUÊTE PRODUITS
// ============================================================
$where  = "p.is_active = 1";
$params = [];
$types  = '';

if ($search !== '') {
    $s = '%' . $mysqli->real_escape_string($search) . '%';
    $where .= " AND (p.name LIKE '$s' OR p.short_description LIKE '$s' OR p.description LIKE '$s')";
}

if ($cat_id > 0) {
    $where .= " AND (p.category_id = $cat_id OR c.parent_id = $cat_id)";
}

if ($brand_id > 0) {
    $where .= " AND p.brand_id = $brand_id";
}

if ($price_min > 0 || $price_max < $global_max) {
    $where .= " AND p.price BETWEEN $price_min AND $price_max";
}

// Tri
$order_sql = match($orderby) {
    'popularity' => 'p.sold_count DESC',
    'rating'     => 'p.rating DESC',
    'date'       => 'p.created_at DESC',
    'price'      => 'p.price ASC',
    'price-desc' => 'p.price DESC',
    default      => 'p.is_featured DESC, p.created_at DESC',
};

// Compter le total pour la pagination
$count_sql = "SELECT COUNT(*) as total FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE $where";
$total_products = (int)$mysqli->query($count_sql)->fetch_assoc()['total'];
$total_pages    = max(1, ceil($total_products / $per_page));

// Récupérer les produits
$products_sql = "SELECT p.id, p.name, p.slug, p.price, p.old_price, p.image, p.stock,
                        p.rating, p.is_new, p.badge, p.badge_color, p.discount_percent,
                        p.short_description, p.sold_count, p.is_featured,
                        b.name AS brand_name, c.name AS category_name
                 FROM products p
                 LEFT JOIN brands b ON p.brand_id = b.id
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE $where
                 ORDER BY $order_sql
                 LIMIT $per_page OFFSET $offset";
$products_result = $mysqli->query($products_sql);
$products = $products_result ? $products_result->fetch_all(MYSQLI_ASSOC) : [];
fill_product_images($products);

// ============================================================
// INFORMATIONS UTILISATEUR CONNECTÉ
// ============================================================
$user_name       = $_SESSION['user_name'] ?? null;
$user_first_name = $user_name ? explode(' ', $user_name)[0] : null;

// URL de base pour les liens de pagination (conserver les filtres)
function build_url($params_override = []) {
    $params = $_GET;
    foreach ($params_override as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    $query = http_build_query($params);
    return 'shop.php' . ($query ? '?' . $query : '');
}
?>

<!doctype html>
<html lang="fr">

<head>
    <!--========= Required meta tags =========-->
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="description" content="Boutique AtlanTech — Technologie & Innovation en Haïti">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Boutique — AtlanTech</title>

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
</head>

<body>
<?php include __DIR__ . '/includes/header_mobile_v2.php'; ?>
<?php include __DIR__ . '/includes/promo_banner.php'; ?>

<div class="body_wrap">

    <!-- preloder start -->
    <div class="preloder_part">
        <div class="spinner">
            <div class="dot1"></div>
            <div class="dot2"></div>
        </div>
    </div>
    <!-- preloder end -->

    <!-- back to top start -->
    <div class="progress-wrap">
        <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98"/>
        </svg>
    </div>
    <!-- back to top end -->

    <!-- header start -->
    <header class="header header__style-one">
        <div class="header__top-info-wrap d-none d-lg-block">
            <div class="container">
                <div class="header__top-info ul_li_between mt-none-10">
                    <ul class="ul_li mt-10">
                        <li><i class="far fa-map-marker-alt"></i> Les Cayes, Sud d'Haïti</li>
                        <li><i class="far fa-truck"></i> Suivez votre commande en ligne</li>
                        <li><i class="fas fa-phone"></i> Appelez-nous : <strong>(+509) 44 66 75 53</strong></li>
                        <li><i class="fas fa-heart"></i> Bienvenue chez <strong>AtlanTech</strong> — Votre partenaire en technologie & innovation 💻⚡</li>
                    </ul>
                    <div class="header__top-right ul_li mt-10">
                        <div class="date">
                            <i class="fal fa-calendar-alt"></i>
                            <?php echo date("l, d F Y"); ?>
                        </div>
                        <div class="header__social ml-25">
                            <a href="https://facebook.com/atlantech.service" target="_blank"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
                            <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            <a href="mailto:atlantech.service@gmail.com"><i class="far fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="header__middle ul_li_between justify-content-xs-center">
                <div class="header__logo">
                    <a href="index.php">
                        <img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech Logo">
                    </a>
                </div>

                <!-- Barre de recherche -->
                <form class="header__search-box" action="shop.php" method="get">
                    <div class="select-box">
                        <select id="category" name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($cat_id == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="search" id="search"
                           value="<?php echo e($search); ?>"
                           placeholder="Rechercher un produit..." />
                    <button type="submit"><i class="far fa-search"></i></button>
                </form>

                <div class="header__lang ul_li">
                    <div class="header__language mr-15">
                        <ul>
                            <li><a href="#!" class="lang-btn">HTG <i class="far fa-chevron-down"></i></a>
                                <ul class="lang_sub_list">
                                    <li><a href="#">HTG</a></li>
                                    <li><a href="#">USD</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="header__language">
                        <ul>
                            <li><a href="#!" class="lang-btn"><img loading="lazy" src="assets/img/icon/ht_flag.png" alt="">Français <i class="far fa-chevron-down"></i></a>
                                <ul class="lang_sub_list">
                                    <li><a href="#">Français</a></li>
                                    <li><a href="#">English</a></li>
                                    <li><a href="#">Español</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="header__icons ul_li">
                    <div class="icon">
                        <a href="account.php"><img loading="lazy" src="assets/img/icon/user.svg" alt="Mon compte"></a>
                    </div>
                    <div class="icon wishlist-icon">
                        <a href="wishlist.php">
                            <img loading="lazy" src="assets/img/icon/heart.svg" alt="Favoris">
                            <span class="count"><?= (int)$wishlist_count ?></span>
                        </a>
                    </div>
                    <div class="cart_btn icon">
                        <a href="cart.php">
                            <img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt="Panier">
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
                            <a href="javascript:void(0);" class="active">
                                <div class="icon bar">
                                    <span><i class="fal fa-bars"></i></span>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="login-sign-btn">
                        <?php if ($user_first_name): ?>
                            <a class="thm-btn" href="account.php">
                                <span class="btn-wrap">
                                    <span>Bonjour, <?php echo e($user_first_name); ?></span>
                                    <span>Mon Compte</span>
                                </span>
                            </a>
                        <?php else: ?>
                            <a class="thm-btn" href="account.php">
                                <span class="btn-wrap">
                                    <span>Connexion / Inscription</span>
                                    <span>Connexion / Inscription</span>
                                </span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- header end -->

    <!-- slide-bar start -->
    <aside class="slide-bar">
        <div class="close-mobile-menu">
            <a href="javascript:void(0);"><i class="fal fa-times"></i></a>
        </div>

        <!-- sidebar cart start -->
        <?php include 'config/cart-sidebar.php'; ?>

        <!-- sidebar cart end -->

        <!-- side-mobile-menu start -->
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
                        <li><a href="shop-left-sidebar.php">Sidebar gauche</a></li>
                        <li><a href="cart.php">Mon panier</a></li>
                        <li><a href="checkout.php">Commander</a></li>
                        <li><a href="account.php">Mon compte</a></li>
                    </ul>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li><a href="<?php echo build_url(['category' => $cat['id'], 'page' => null]); ?>"><?php echo e($cat['name']); ?></a></li>
                <?php endforeach; ?>
                <li><a href="news.php">Blog</a></li>
                <li><a href="about.php">À propos</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
        <!-- side-mobile-menu end -->
    </aside>
    <div class="body-overlay"></div>
    <!-- slide-bar end -->

    <main>

        <!-- breadcrumb start -->
        <section class="breadcrumb-area">
            <div class="container">
                <div class="atl-breadcrumb breadcrumbs">
                    <ul class="list-unstyled d-flex align-items-center">
                        <li class="atl-bcrumb-item atl-bcrumb-begin">
                            <a href="index.php"><span>Accueil</span></a>
                        </li>
                        <li class="atl-bcrumb-item atl-bcrumb-end">
                            <span>Boutique</span>
                        </li>
                        <?php if ($search): ?>
                            <li class="atl-bcrumb-item atl-bcrumb-end">
                                <span>Résultats pour "<?php echo e($search); ?>"</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>
        <!-- breadcrumb end -->

        <!-- start shop-section -->
        <section class="shop-section pb-80">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="shop-area clearfix">
                            <div class="woocommerce-content-wrap">

                                <!-- Barre d'outils -->
                                <div class="woocommerce-toolbar-top">
                                    <p class="woocommerce-result-count">
                                        <?php
                                        $from = $total_products > 0 ? $offset + 1 : 0;
                                        $to   = min($offset + $per_page, $total_products);
                                        echo $from . '&ndash;' . $to . ' sur ' . $total_products . ' r&eacute;sultat' . ($total_products > 1 ? 's' : '');
                                        ?>
                                    </p>
                                    <div class="shop-toolbar-row">
                                        <!-- 1. Recherche -->
                                        <form method="get" action="" class="shop-search-wrap">
                                            <?php foreach ($_GET as $k => $v) { if ($k !== 'search') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; } ?>
                                            <input type="text" name="search" class="shop-search-input" placeholder="Rechercher..." value="<?php echo e($search); ?>">
                                            <button type="submit" class="shop-search-btn">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            </button>
                                        </form>
                                        <!-- 2 & 3. Colonnes -->
                                        <div class="shop-col-group">
                                            <a href="#!" class="col-btn col-2 active" data-cols="2" title="2 colonnes">
                                                <svg width="14" height="14" viewBox="0 0 18 18" fill="currentColor"><rect x="0" y="0" width="7" height="18" rx="1"/><rect x="11" y="0" width="7" height="18" rx="1"/></svg>
                                            </a>
                                            <a href="#!" class="col-btn col-3" data-cols="3" title="3 colonnes">
                                                <svg width="14" height="14" viewBox="0 0 18 18" fill="currentColor"><rect x="0" y="0" width="4" height="18" rx="1"/><rect x="7" y="0" width="4" height="18" rx="1"/><rect x="14" y="0" width="4" height="18" rx="1"/></svg>
                                            </a>
                                        </div>
                                        <!-- 4. Tri -->
                                        <form class="woocommerce-ordering shop-sort-wrap" method="get" id="sort-form">
                                            <?php foreach ($_GET as $k => $v) { if ($k !== 'orderby') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; } ?>
                                            <select name="orderby" class="orderby shop-sort-select" onchange="this.form.submit()">
                                                <option value="default"    <?php echo $orderby === 'default'    ? 'selected' : ''; ?>>Défaut</option>
                                                <option value="popularity" <?php echo $orderby === 'popularity' ? 'selected' : ''; ?>>Vendus</option>
                                                <option value="rating"     <?php echo $orderby === 'rating'     ? 'selected' : ''; ?>>Notes</option>
                                                <option value="date"       <?php echo $orderby === 'date'       ? 'selected' : ''; ?>>Nouveau</option>
                                                <option value="price"      <?php echo $orderby === 'price'      ? 'selected' : ''; ?>>Prix ↑</option>
                                                <option value="price-desc" <?php echo $orderby === 'price-desc' ? 'selected' : ''; ?>>Prix ↓</option>
                                            </select>
                                        </form>
                                    </div>
                                </div>

                                <!-- Grille de produits -->
                                <div class="woocommerce-content-inner">
                                    <?php if (empty($products)): ?>
                                        <div style="text-align:center; padding:60px 20px;">
                                            <i class="far fa-search" style="font-size:48px; color:#ccc;"></i>
                                            <h3 style="margin-top:20px; color:#666;">Aucun produit trouvé</h3>
                                            <p style="color:#999;">Essayez de modifier vos critères de recherche.</p>
                                            <a href="shop.php" class="thm-btn mt-20">
                                                <span class="btn-wrap"><span>Voir tous les produits</span><span>Voir tous les produits</span></span>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <ul class="products three-column clearfix">
                                            <?php foreach ($products as $p):
                                                // Image : fichier uploadé OU fallback cyclique parmi les 177 images locales
                                                $fb_num   = (($p['id'] - 1) % 177) + 1;
                                                $fb_img   = sprintf('assets/img/product/img_%02d.png', $fb_num);
                                                $img_path = !empty($p['image']) ? 'uploads/products/' . $p['image'] : $fb_img;

                                                $product_url = 'shop-single.php?id=' . $p['id'];
                                                $has_old_price = !empty($p['old_price']) && $p['old_price'] > $p['price'];
                                                // Étoiles de notation
                                                $rating = round((float)$p['rating']);
                                            ?>
                                                <li class="product">
                                                    <div class="product-holder">
                                                        <a href="<?php echo $product_url; ?>">
                                                            <img loading="lazy" src="<?php echo e($img_path); ?>"
                                                                 onerror="this.src='<?php echo $fb_img; ?>'; this.onerror=null;"
                                                                 alt="<?php echo e($p['name']); ?>">
                                                        </a>
                                                        <ul class="product__action">
                                                            <li><a href="#!" title="Aperçu rapide"><i class="far fa-compress-alt"></i></a></li>
                                                            <li><a href="cart.php?add=<?php echo $p['id']; ?>" title="Ajouter au panier"><i class="far fa-shopping-basket"></i></a></li>
                                                            <li><a href="wishlist.php?add=<?php echo $p['id']; ?>" title="Ajouter aux favoris"><i class="far fa-heart"></i></a></li>
                                                        </ul>
                                                    </div>
                                                    <div class="product-info">
                                                        <div class="product__review ul_li">
                                                            <ul class="rating-star ul_li mr-10">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <li><i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i></li>
                                                                <?php endfor; ?>
                                                            </ul>
                                                            <?php if (!empty($p['brand_name'])): ?>
                                                                <span><?php echo e($p['brand_name']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <h2 class="product__title">
                                                            <a href="<?php echo $product_url; ?>"><?php echo e($p['name']); ?></a>
                                                        </h2>
                                                        <?php if ($p['stock'] > 0): ?>
                                                            <span class="product__available">
                                                                En stock : <span><?php echo $p['stock']; ?></span>
                                                            </span>
                                                            <div class="product__progress progress color-primary">
                                                                <?php
                                                                $stock_pct = min(100, ($p['stock'] / 100) * 100);
                                                                ?>
                                                                <div class="progress-bar" role="progressbar"
                                                                     style="width: <?php echo $stock_pct; ?>%"
                                                                     aria-valuenow="<?php echo $stock_pct; ?>"
                                                                     aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="product__available" style="color:#e74c3c;">Rupture de stock</span>
                                                        <?php endif; ?>
                                                        <h4 class="product__price">
                                                            <span class="new"><?= fmt_price($p['price']) ?></span>
                                                            <?php if ($has_old_price): ?>
                                                                <span class="old"><?= fmt_price($p['old_price']) ?></span>
                                                            <?php endif; ?>
                                                        </h4>
                                                        <?php if (!empty($p['short_description'])): ?>
                                                            <p class="product-description">
                                                                <?php echo e(mb_substr($p['short_description'], 0, 120)) . (mb_strlen($p['short_description']) > 120 ? '...' : ''); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($p['is_new']): ?>
                                                        <span class="product__badge color-2"><span>Nouveau</span></span>
                                                    <?php elseif (!empty($p['badge'])): ?>
                                                        <span class="product__badge" style="background:<?php echo e($p['badge_color'] ?? '#e74c3c'); ?>">
                                                            <span><?php echo e($p['badge']); ?></span>
                                                        </span>
                                                    <?php elseif ($p['discount_percent'] > 0): ?>
                                                        <span class="product__badge color-1"><span>-<?php echo $p['discount_percent']; ?>%</span></span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="pagination_wrap pt-20">
                                        <ul>
                                            <?php if ($page > 1): ?>
                                                <li><a href="<?php echo build_url(['page' => $page - 1]); ?>"><i class="far fa-angle-double-left"></i></a></li>
                                            <?php endif; ?>
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li>
                                                    <a class="<?php echo $i === $page ? 'current_page' : ''; ?>"
                                                       href="<?php echo build_url(['page' => $i]); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            <?php if ($page < $total_pages): ?>
                                                <li><a href="<?php echo build_url(['page' => $page + 1]); ?>"><i class="far fa-angle-double-right"></i></a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                            </div>
                            <!-- .woocommerce-content-wrap -->

                            <!-- Sidebar -->
                            <div class="shop-sidebar">

                                <!-- Recherche -->
                                <div class="widget">
                                    <h2 class="widget__title"><span>Recherche</span></h2>
                                    <form class="widget__search" action="shop.php" method="get">
                                        <input type="text" name="search"
                                               value="<?php echo e($search); ?>"
                                               placeholder="Rechercher...">
                                        <button><i class="far fa-search"></i></button>
                                    </form>
                                </div>

                                <!-- Filtre de prix -->
                                <div class="widget widget_price_filter">
                                    <h2 class="widget__title"><span>Filtrer par prix (HTG)</span></h2>
                                    <div class="filter-price">
                                        <form method="get" action="shop.php" id="price-filter-form">
                                            <?php foreach ($_GET as $k => $v): ?>
                                                <?php if (!in_array($k, ['price_min', 'price_max', 'page'])): ?>
                                                    <input type="hidden" name="<?php echo e($k); ?>" value="<?php echo e($v); ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <div id="slider-range"></div>
                                            <p>
                                                Prix :
                                                <input type="text" id="amount"
                                                       value="<?php echo number_format($price_min, 0) . ' HTG — ' . number_format($price_max, 0) ; ?>"
                                                       readonly>
                                            </p>
                                            <input type="hidden" name="price_min" id="price_min" value="<?php echo $price_min; ?>">
                                            <input type="hidden" name="price_max" id="price_max" value="<?php echo $price_max; ?>">
                                            <button type="submit">Filtrer</button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Catégories -->
                                <div class="widget">
                                    <h2 class="widget__title"><span>Catégories</span></h2>
                                    <ul class="widget__category">
                                        <li>
                                            <a href="shop.php" class="<?php echo $cat_id === 0 ? 'active' : ''; ?>">
                                                Toutes les catégories <i class="far fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php foreach ($categories as $cat): ?>
                                            <li>
                                                <a href="<?php echo build_url(['category' => $cat['id'], 'page' => null]); ?>"
                                                   class="<?php echo $cat_id == $cat['id'] ? 'active' : ''; ?>">
                                                    <?php echo e($cat['name']); ?> <i class="far fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <!-- Marques -->
                                <div class="widget">
                                    <h2 class="widget__title"><span>Marques</span></h2>
                                    <div class="checkbox">
                                        <?php foreach ($brands as $brand): ?>
                                            <div class="checkbox__item ul_li">
                                                <input class="form-check-input" type="checkbox"
                                                       name="brand_filter"
                                                       id="brand_<?php echo $brand['id']; ?>"
                                                       value="<?php echo $brand['id']; ?>"
                                                       <?php echo $brand_id == $brand['id'] ? 'checked' : ''; ?>
                                                       onchange="window.location='<?php echo build_url(['brand' => null, 'page' => null]); ?>&brand='+this.value">
                                                <label for="brand_<?php echo $brand['id']; ?>">
                                                    <?php echo e($brand['name']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($brand_id > 0): ?>
                                            <p><a href="<?php echo build_url(['brand' => null, 'page' => null]); ?>" style="font-size:12px; color:#e74c3c;">✕ Effacer le filtre marque</a></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Bannière promo -->
                                <div class="widget">
                                    <div class="widget__add">
                                        <div class="content">
                                            <span>Tendance</span>
                                            <h3>Collection <span>2025</span><br>Laptops & Phones</h3>
                                            <a class="thm-btn no-icon" href="shop.php">
                                                <span class="btn-wrap">
                                                    <span>Acheter maintenant</span>
                                                    <span>Acheter maintenant</span>
                                                </span>
                                            </a>
                                        </div>
                                        <div class="image">
                                            <img loading="lazy" class="add_img" src="assets/img/product/img_177.png" alt="">
                                            <img loading="lazy" class="add_shape" src="assets/img/shape/add_shape.png" alt="">
                                        </div>
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div class="widget">
                                    <h2 class="widget__title"><span>Tags populaires</span></h2>
                                    <div class="tagcloud">
                                        <a href="shop.php?search=iPhone">iPhone</a>
                                        <a href="shop.php?search=Samsung">Samsung</a>
                                        <a href="shop.php?search=Laptop">Laptop</a>
                                        <a href="shop.php?search=MacBook">MacBook</a>
                                        <a href="shop.php?search=Tablette">Tablette</a>
                                        <a href="shop.php?search=Gaming">Gaming</a>
                                        <a href="shop.php?search=Casque">Casque</a>
                                        <a href="shop.php?search=Apple">Apple</a>
                                    </div>
                                </div>

                            </div>
                            <!-- .shop-sidebar -->

                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- end shop-section -->

    </main>

    <!-- footer start -->
    <footer class="footer" data-background="assets/img/bg/footer_bg.jpg">
        <div class="newslater newslater__border pt-30 pb-30">
            <div class="container">
                <div class="newslater__two ul_li">
                    <div class="newslater__content">
                        <h2 class="title">Nous sommes là pour vous <span>aider</span></h2>
                        <p>Consultez nos experts pour toute information sur nos produits</p>
                    </div>
                    <form class="newslater__form" action="contact.php" method="post">
                        <input placeholder="Entrez votre Email" type="email" name="email">
                        <button type="submit">S'abonner</button>
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
                        <p>AtlanTech — Votre partenaire technologique aux Cayes, Haïti. Produits certifiés, service professionnel et livraison rapide.</p>
                        <ul class="footer__info mt-30">
                            <li><i class="far fa-map-marker-alt"></i> Les Cayes, Sud, Haïti</li>
                            <li><i class="fas fa-phone"></i> (+509) 44 66 75 53</li>
                            <li><i class="far fa-envelope"></i> atlantech.service@gmail.com</li>
                        </ul>
                        <div class="apps-img mt-15 ul_li">
                            <div class="app mt-15"><a href="#!"><img loading="lazy" src="assets/img/icon/google_play.png" alt="Google Play"></a></div>
                            <div class="app mt-15"><a href="#!"><img loading="lazy" src="assets/img/icon/app_store.png" alt="App Store"></a></div>
                        </div>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Catégories</h2>
                        <ul class="quick-links">
                            <?php foreach (array_slice($categories, 0, 7) as $cat): ?>
                                <li><a href="<?php echo build_url(['category' => $cat['id'], 'page' => null]); ?>"><?php echo e($cat['name']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Liens rapides</h2>
                        <ul class="quick-links">
                            <li><a href="account.php">Mon compte</a></li>
                            <li><a href="cart.php">Mon panier</a></li>
                            <li><a href="wishlist.php">Mes favoris</a></li>
                            <li><a href="checkout.php">Commander</a></li>
                            <li><a href="news.php">Blog</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Service client</h2>
                        <ul class="category">
                            <li><a href="contact.php">Centre d'aide</a></li>
                            <li><a href="#">Conditions d'utilisation</a></li>
                            <li><a href="#">Livraison & Expédition</a></li>
                            <li><a href="#">Politique de confidentialité</a></li>
                            <li><a href="#">Retours & Remboursements</a></li>
                            <li><a href="#">FAQ</a></li>
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
                    <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
                    <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="mailto:atlantech.service@gmail.com"><i class="far fa-envelope"></i></a>
                </div>
                <div class="payment_method mt-15">
                    <img loading="lazy" src="assets/img/bg/payment_method.png" alt="Méthodes de paiement">
                </div>
            </div>
        </div>
    </footer>
    <!-- footer end -->

    <!-- newsletter-popup start -->
    <section class="newsletter-popup-area-section">
        <div class="newsletter-popup-area">
            <div class="newsletter-popup-ineer">
                <button class="btn newsletter-close-btn"><i class="fal fa-times"></i></button>
                <div class="img-holder">
                    <img loading="lazy" src="assets/img/bg/newsletter.jpg" alt="">
                </div>
                <div class="details">
                    <h4>Obtenez 45% de réduction livré dans votre boîte mail</h4>
                    <p>Abonnez-vous à la newsletter AtlanTech pour recevoir les meilleures offres sur vos produits préférés.</p>
                    <form>
                        <div>
                            <input type="email" placeholder="Entrez votre email" />
                            <button type="submit">S'abonner</button>
                        </div>
                        <div>
                            <label class="checkbox-holder"> Ne plus afficher ce message
                                <input type="checkbox" class="show-message">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
    <!-- newsletter-popup end -->

    <!-- cookies-area start -->
    <div class="cookies-area">
        <p>Ce site utilise des cookies pour améliorer votre expérience. En utilisant ce site, vous acceptez notre <a href="#">Politique de confidentialité</a>.</p>
        <a href="#" class="read-more">En savoir plus</a>
        <div><button class="cookie-btn">Accepter</button></div>
    </div>
    <!-- cookies-area end -->

</div>

<!-- jquery include -->
    <!-- JS bundle (15 fichiers → 1 requête) -->
    <script src="assets/js/bundle.min.js"></script>

<!-- Slider de prix jQuery UI -->
<script>
$(function () {
    var globalMin = <?php echo $global_min; ?>;
    var globalMax = <?php echo $global_max; ?>;
    var currentMin = <?php echo $price_min; ?>;
    var currentMax = <?php echo $price_max; ?>;

    $("#slider-range").slider({
        range: true,
        min: globalMin,
        max: globalMax,
        values: [currentMin, currentMax],
        slide: function (event, ui) {
            $("#amount").val(ui.values[0].toLocaleString('fr') + ' HTG — ' + ui.values[1].toLocaleString('fr') + ' HTG');
            $("#price_min").val(ui.values[0]);
            $("#price_max").val(ui.values[1]);
        }
    });

    $("#amount").val(
        $("#slider-range").slider("values", 0).toLocaleString('fr') + ' HTG — ' +
        $("#slider-range").slider("values", 1).toLocaleString('fr') + ' HTG'
    );
});
</script>

<script>
(function(){
  var ul = document.querySelector('ul.products.three-column');
  var btns = document.querySelectorAll('.col-btn');

  var mqMobile = window.matchMedia('(max-width: 767px)');

  function clearInline(){
    if(!ul) return;
    ul.style.removeProperty('gap');
    ul.querySelectorAll('li').forEach(function(li){
      li.style.removeProperty('width');
      li.style.removeProperty('float');
      var img = li.querySelector('.product-holder img');
      if(img){ img.style.removeProperty('height'); }
      var holder = li.querySelector('.product-holder');
      if(holder){ holder.style.removeProperty('height'); }
      var info = li.querySelector('.product-info');
      if(info){ info.style.removeProperty('padding'); }
      var title = li.querySelector('.product__title a');
      if(title){ title.style.fontSize=''; }
      var newp = li.querySelector('.product__price .new');
      if(newp){ newp.style.fontSize=''; }
      var rev = li.querySelector('.product__review');
      if(rev){ rev.style.fontSize=''; }
    });
  }

  function setCols(n){
    if(!ul) return;
    if(!mqMobile.matches){
      clearInline();
      btns.forEach(function(b){ b.classList.toggle('active', b.dataset.cols==n); });
      return;
    }
    var lis = ul.querySelectorAll('li');
    if(n == '3'){
      ul.style.setProperty('gap','4px','important');
      lis.forEach(function(li){
        li.style.setProperty('width','calc(33.33% - 3px)','important');
        li.style.setProperty('float','none','important');
        var img = li.querySelector('.product-holder img');
        if(img){ img.style.setProperty('height','115px','important'); }
        var holder = li.querySelector('.product-holder');
        if(holder){ holder.style.setProperty('height','115px','important'); }
        var info = li.querySelector('.product-info');
        if(info){ info.style.setProperty('padding','2px 3px','important'); }
        var title = li.querySelector('.product__title a');
        if(title){ title.style.fontSize='8px'; }
        var newp = li.querySelector('.product__price .new');
        if(newp){ newp.style.fontSize='9px'; }
        var rev = li.querySelector('.product__review');
        if(rev){ rev.style.fontSize='7px'; }
      });
    } else {
      ul.style.setProperty('gap','8px','important');
      lis.forEach(function(li){
        li.style.setProperty('width','calc(50% - 4px)','important');
        li.style.setProperty('float','none','important');
        var img = li.querySelector('.product-holder img');
        if(img){ img.style.setProperty('height','190px','important'); }
        var holder = li.querySelector('.product-holder');
        if(holder){ holder.style.setProperty('height','190px','important'); }
        var info = li.querySelector('.product-info');
        if(info){ info.style.removeProperty('padding'); }
        var title = li.querySelector('.product__title a');
        if(title){ title.style.fontSize=''; }
        var newp = li.querySelector('.product__price .new');
        if(newp){ newp.style.fontSize=''; }
        var rev = li.querySelector('.product__review');
        if(rev){ rev.style.fontSize=''; }
      });
    }
    btns.forEach(function(b){ b.classList.toggle('active', b.dataset.cols==n); });
    try{ localStorage.setItem('shop_cols',n); }catch(e){}
  }

  btns.forEach(function(btn){
    btn.addEventListener('click',function(e){
      e.preventDefault();
      setCols(this.dataset.cols);
    });
  });
  function getSaved(){ var s='2'; try{ s=localStorage.getItem('shop_cols')||'2'; }catch(e){} return s; }
  var mqHandler = function(){ setCols(getSaved()); };
  if(mqMobile.addEventListener){ mqMobile.addEventListener('change', mqHandler); }
  else if(mqMobile.addListener){ mqMobile.addListener(mqHandler); }
  setCols(getSaved());
})();
</script>
