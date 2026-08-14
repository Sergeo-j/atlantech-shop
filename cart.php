<?php
/**
 * Panier — AtlanTech E-commerce
 * Gestion session panier : ajout, mise à jour, suppression, vidage
 */

require_once 'config/config.php';
require_once 'includes/header_counters.php';

// ── Initialiser le panier en session ────────────────────────
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ════════════════════════════════════════════════════════════
// ACTIONS PANIER
// ════════════════════════════════════════════════════════════

// ── Ajouter un produit (GET ?add=id ou POST action=add) ─────
$add_id = (int)($_GET['add'] ?? $_POST['product_id'] ?? 0);
if ($add_id > 0 && isset($_GET['add'])) {
    $qty_add = max(1, (int)($_GET['qty'] ?? 1));
    $pr = $mysqli->query("SELECT id, name, price, image, stock FROM products WHERE id = $add_id AND is_active = 1 LIMIT 1");
    if ($pr && $p = $pr->fetch_assoc()) {
        if (isset($_SESSION['cart'][$add_id])) {
            $_SESSION['cart'][$add_id]['qty'] = min(
                $_SESSION['cart'][$add_id]['qty'] + $qty_add,
                (int)$p['stock']
            );
        } else {
            $_SESSION['cart'][$add_id] = [
                'id'    => $p['id'],
                'name'  => $p['name'],
                'price' => (float)$p['price'],
                'image' => $p['image'],
                'stock' => (int)$p['stock'],
                'qty'   => $qty_add,
            ];
        }
    }
    // Retourner à la page précédente plutôt que rester sur cart.php (fallback sans JS)
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    $safe_pages = ['index.php', 'shop.php', 'shop-single.php', 'wishlist.php', 'promotions.php'];
    $is_safe = false;
    if ($back) {
        $path = parse_url($back, PHP_URL_PATH) ?? '';
        foreach ($safe_pages as $pg) {
            if (str_ends_with($path, $pg)) { $is_safe = true; break; }
        }
    }
    header('Location: ' . ($is_safe ? $back : 'index.php'));
    exit();
}

// ── Supprimer un article (GET ?remove=id) ───────────────────
if (isset($_GET['remove'])) {
    $rem_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$rem_id]);
    header('Location: cart.php');
    exit();
}

// ── Vider le panier (GET ?clear=1) ──────────────────────────
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header('Location: cart.php');
    exit();
}

// ── Ajouter via POST (depuis shop-single) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    // Couleur choisie par le client (facultative selon le produit)
    $color_id   = !empty($_POST['color_id']) ? (int)$_POST['color_id'] : null;
    $color_name = trim((string)($_POST['color_name'] ?? ''));
    if ($color_name === '') $color_name = null;
    if ($pid > 0) {
        $pr = $mysqli->query("SELECT id, name, price, image, stock FROM products WHERE id = $pid AND is_active = 1 LIMIT 1");
        if ($pr && $p = $pr->fetch_assoc()) {
            // Prix effectif recalculé côté serveur selon la couleur (anti-tampering)
            $eff_price = effective_color_price($mysqli, (int)$p['id'], $color_id, (float)$p['price']);
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid]['qty'] = min($_SESSION['cart'][$pid]['qty'] + $qty, (int)$p['stock']);
                // Mettre à jour la couleur + le prix si une nouvelle couleur est fournie
                if ($color_id !== null) {
                    $_SESSION['cart'][$pid]['color_id']   = $color_id;
                    $_SESSION['cart'][$pid]['color_name'] = $color_name;
                    $_SESSION['cart'][$pid]['price']      = $eff_price;
                }
            } else {
                $_SESSION['cart'][$pid] = [
                    'id'         => $p['id'],
                    'name'       => $p['name'],
                    'price'      => $eff_price,
                    'image'      => $p['image'],
                    'stock'      => (int)$p['stock'],
                    'qty'        => $qty,
                    'color_id'   => $color_id,
                    'color_name' => $color_name,
                ];
            }
        }
    }
    // Retourner à la page produit (fallback sans JS)
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    $safe_pages = ['shop-single.php', 'index.php', 'shop.php', 'wishlist.php'];
    $is_safe = false;
    if ($back) {
        $path = parse_url($back, PHP_URL_PATH) ?? '';
        foreach ($safe_pages as $pg) {
            if (str_ends_with($path, $pg)) { $is_safe = true; break; }
        }
    }
    header('Location: ' . ($is_safe ? $back : 'index.php'));
    exit();
}

// ── Mettre à jour les quantités (POST update) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (!empty($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $pid => $qty) {
            $pid = (int)$pid;
            $qty = (int)$qty;
            if ($pid > 0 && isset($_SESSION['cart'][$pid])) {
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$pid]);
                } else {
                    $stock = $_SESSION['cart'][$pid]['stock'] ?? 9999;
                    $_SESSION['cart'][$pid]['qty'] = min($qty, $stock);
                }
            }
        }
    }
    header('Location: cart.php?updated=1');
    exit();
}

// ════════════════════════════════════════════════════════════
// CALCULS PANIER
// ════════════════════════════════════════════════════════════
$cart_items  = $_SESSION['cart'] ?? [];
$cart_count  = count($cart_items);
$subtotal    = 0.0;
foreach ($cart_items as $item) {
    $subtotal += (float)$item['price'] * (int)$item['qty'];
}
$livraison   = 0.0;           // Livraison gratuite
$total       = $subtotal + $livraison;

// ── Catégories pour le header ────────────────────────────────
$cat_res = $mysqli->query("SELECT id, name, icon FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY display_order ASC LIMIT 9");
$categories = $cat_res ? $cat_res->fetch_all(MYSQLI_ASSOC) : [];

// ── Session utilisateur ──────────────────────────────────────
$user_name       = $_SESSION['user_name'] ?? null;
$user_first_name = $user_name ? explode(' ', $user_name)[0] : null;

$cart_total_session = $subtotal; // pour le sidebar
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="description" content="Mon Panier — AtlanTech Haïti">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Mon Panier — AtlanTech</title>
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
        .cart-empty-box {
            text-align: center;
            padding: 60px 20px;
        }
        .cart-empty-box i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
            display: block;
        }
        .cart-empty-box h3 { color: #555; margin-bottom: 16px; }
        .cart-updated-msg {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .qty-input {
            width: 70px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 6px;
            font-size: 14px;
        }
        .cart-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .remove-link {
            color: #e74c3c;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid #e74c3c;
            transition: .2s;
        }
        .remove-link:hover { background: #e74c3c; color: #fff; }
        .cart-totals-box {
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 28px;
        }
        .cart-totals-box table { width: 100%; }
        .cart-totals-box table tr td,
        .cart-totals-box table tr th {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            font-size: 15px;
        }
        .cart-totals-box table tr:last-child td,
        .cart-totals-box table tr:last-child th { border-bottom: none; }
        .total-row th, .total-row td { font-size: 18px !important; font-weight: 700; color: #111; }
        .coupon-wrap { display: flex; gap: 10px; margin-bottom: 20px; }
        .coupon-wrap input { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 10px 14px; }
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
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
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
        <?php include 'config/cart-sidebar.php'; ?>

        <nav class="side-mobile-menu">
            <div class="header-mobile-search">
                <form role="search" method="get" action="shop.php">
                    <input type="text" name="search" placeholder="Rechercher...">
                    <button type="submit"><i class="ti-search"></i></button>
                </form>
            </div>
            <ul id="mobile-menu-active">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="shop.php">Boutique</a></li>
                <?php foreach ($categories as $cat): ?>
                    <li><a href="shop.php?category=<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                <?php endforeach; ?>
                <li><a href="account.php">Mon compte</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </aside>
    <div class="body-overlay"></div>

    <main>
        <!-- Breadcrumb -->
        <section class="breadcrumb-area">
            <div class="container">
                <div class="atl-breadcrumb breadcrumbs">
                    <ul class="list-unstyled d-flex align-items-center">
                        <li class="atl-bcrumb-item atl-bcrumb-begin">
                            <a href="index.php"><span>Accueil</span></a>
                        </li>
                        <li class="atl-bcrumb-item atl-bcrumb-end">
                            <span>Mon Panier</span>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ======================================================= PANIER -->
        <section class="cart-section woocommerce-cart pb-80">
            <div class="container">
                <div class="row">
                    <div class="col col-xs-12">
                        <div class="woocommerce">

                            <?php if (isset($_GET['updated'])): ?>
                                <div class="cart-updated-msg">
                                    <i class="fas fa-check-circle"></i>
                                    Panier mis à jour avec succès.
                                </div>
                            <?php endif; ?>

                            <?php if ($cart_count > 0): ?>

                                <!-- ── Tableau des articles ── -->
                                <form method="post" action="cart.php">
                                    <input type="hidden" name="update_cart" value="1">
                                    <table class="shop_table shop_table_responsive cart">
                                        <thead>
                                            <tr>
                                                <th class="product-remove">&nbsp;</th>
                                                <th class="product-thumbnail">&nbsp;</th>
                                                <th class="product-name">Produit</th>
                                                <th class="product-price">Prix unitaire</th>
                                                <th class="product-quantity">Quantité</th>
                                                <th class="product-subtotal">Sous-total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $pid => $item):
                                                $line_total = (float)$item['price'] * (int)$item['qty'];
                                                $fb_img = sprintf('assets/img/product/img_%02d.png', ((((int)$pid - 1) % 177) + 1));
                                                $prod_img = !empty($item['image']) ? 'uploads/products/' . htmlspecialchars($item['image']) : $fb_img;
                                            ?>
                                                <tr class="cart_single">
                                                    <td class="product-remove">
                                                        <a href="cart.php?remove=<?php echo (int)$pid; ?>"
                                                           class="remove-link"
                                                           title="Supprimer cet article">&times;</a>
                                                    </td>
                                                    <td class="product-thumbnail">
                                                        <a href="shop-single.php?id=<?php echo (int)$pid; ?>">
                                                            <img loading="lazy" class="cart-img"
                                                                 src="<?php echo $prod_img; ?>"
                                                                 onerror="this.src='<?php echo $fb_img; ?>'; this.onerror=null;"
                                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                        </a>
                                                    </td>
                                                    <td class="product-name" data-title="Produit">
                                                        <a href="shop-single.php?id=<?php echo (int)$pid; ?>">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                        </a>
                                                        <?php if (!empty($item['color_name'])): ?>
                                                            <br><span style="font-size:12px; color:#777;">
                                                                Couleur : <strong style="color:#e87c1e;"><?php echo htmlspecialchars($item['color_name']); ?></strong>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="product-price" data-title="Prix">
                                                        <span class="woocommerce-Price-amount amount">
                                                            <?= fmt_price((float)$item['price']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="product-quantity" data-title="Quantité">
                                                        <div class="quantity">
                                                            <input type="number"
                                                                   class="qty-input"
                                                                   name="quantities[<?php echo (int)$pid; ?>]"
                                                                   value="<?php echo (int)$item['qty']; ?>"
                                                                   min="0"
                                                                   max="<?php echo (int)($item['stock'] ?? 999); ?>"
                                                                   title="Quantité">
                                                        </div>
                                                    </td>
                                                    <td class="product-subtotal" data-title="Sous-total">
                                                        <span class="woocommerce-Price-amount amount">
                                                            <strong><?= fmt_price($line_total) ?></strong>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Actions -->
                                            <tr>
                                                <td colspan="6" class="actions">
                                                    <div class="coupon-wrap">
                                                        <input type="text" name="coupon_code"
                                                               placeholder="Code promo (bientôt disponible)">
                                                        <button class="thm-btn thm-btn__2 br-0 no-icon" type="button" disabled>
                                                            <span class="btn-wrap"><span>Appliquer</span><span>Appliquer</span></span>
                                                        </button>
                                                    </div>
                                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                        <button class="thm-btn thm-btn__2 br-0 no-icon" type="submit">
                                                            <span class="btn-wrap">
                                                                <span><i class="fas fa-sync-alt"></i> Mettre à jour</span>
                                                                <span><i class="fas fa-sync-alt"></i> Mettre à jour</span>
                                                            </span>
                                                        </button>
                                                        <a href="cart.php?clear=1"
                                                           class="thm-btn br-0 no-icon"
                                                           style="background:#e74c3c;"
                                                           onclick="return confirm('Vider le panier ?')">
                                                            <span class="btn-wrap">
                                                                <span><i class="fas fa-trash"></i> Vider le panier</span>
                                                                <span><i class="fas fa-trash"></i> Vider le panier</span>
                                                            </span>
                                                        </a>
                                                        <a href="shop.php" class="thm-btn thm-btn__black br-0 no-icon">
                                                            <span class="btn-wrap">
                                                                <span><i class="fas fa-arrow-left"></i> Continuer mes achats</span>
                                                                <span><i class="fas fa-arrow-left"></i> Continuer mes achats</span>
                                                            </span>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </form>

                                <!-- ── Totaux ── -->
                                <div class="cart-collaterals">
                                    <div class="cart_totals calculated_shipping">
                                        <div class="cart-totals-box">
                                            <h2 style="margin-bottom:20px;">Récapitulatif de commande</h2>
                                            <table class="shop_table shop_table_responsive">
                                                <tr class="cart-subtotal">
                                                    <th>Sous-total</th>
                                                    <td><?= fmt_price($subtotal) ?></td>
                                                </tr>
                                                <tr class="shipping">
                                                    <th>Livraison</th>
                                                    <td>
                                                        <?php if ($livraison == 0): ?>
                                                            <span style="color:#26c080; font-weight:600;">
                                                                <i class="fas fa-check-circle"></i> Gratuite
                                                            </span>
                                                        <?php else: ?>
                                                            <?= fmt_price($livraison) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr class="total-row">
                                                    <th>Total</th>
                                                    <td><?= fmt_price($total) ?></td>
                                                </tr>
                                            </table>

                                            <a href="checkout.php" class="thm-btn thm-btn__2 no-icon"
                                               style="width:100%; margin-top:20px; display:block; text-align:center;">
                                                <span class="btn-wrap">
                                                    <span><i class="fas fa-lock"></i> Procéder au paiement</span>
                                                    <span><i class="fas fa-lock"></i> Procéder au paiement</span>
                                                </span>
                                            </a>

                                            <div style="margin-top:14px; text-align:center;">
                                                <img loading="lazy" src="assets/img/bg/payment_method.png"
                                                     alt="Méthodes de paiement"
                                                     style="max-width:280px; opacity:.7;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php else: ?>
                                <!-- ── Panier vide ── -->
                                <div class="cart-empty-box">
                                    <i class="far fa-shopping-cart"></i>
                                    <h3>Votre panier est vide</h3>
                                    <p style="color:#777; margin-bottom:24px;">
                                        Vous n'avez encore ajouté aucun article à votre panier.
                                    </p>
                                    <a href="shop.php" class="thm-btn thm-btn__2 no-icon">
                                        <span class="btn-wrap">
                                            <span><i class="fas fa-shopping-bag"></i> Commencer mes achats</span>
                                            <span><i class="fas fa-shopping-bag"></i> Commencer mes achats</span>
                                        </span>
                                    </a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </section>
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
                        <p>AtlanTech — Votre partenaire technologique à Les Cayes, Haïti.</p>
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
                        <h2 class="title">Mon compte</h2>
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
                            <li><a href="#!">Retours &amp; Échanges</a></li>
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
                    <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"><