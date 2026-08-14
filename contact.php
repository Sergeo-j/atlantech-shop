<?php
/**
 * Contact - AtlanTech E-commerce
 */
require_once 'config/config.php';
require_once 'includes/header_counters.php';

// Session utilisateur
$user_name       = isset($_SESSION['user_name'])  ? $_SESSION['user_name']  : null;
$user_first_name = $user_name ? explode(' ', trim($user_name))[0] : null;
$user_email      = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Catégories pour nav + footer
try {
    $r = $mysqli->query("SELECT id, name, slug, icon FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY display_order ASC, name ASC");
    $rootCategories = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) { $rootCategories = []; }

// Panier depuis session
$cart_items  = [];
$cart_total  = 0.0;
$cart_count  = 0;
if (!empty($_SESSION['cart'])) {
    $cart_ids = array_map('intval', array_keys($_SESSION['cart']));
    if ($cart_ids) {
        $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
        $types = str_repeat('i', count($cart_ids));
        $stmt = $mysqli->prepare("SELECT id, name, price, old_price, image FROM products WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->bind_param($types, ...$cart_ids);
        $stmt->execute();
        $products_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($products_res as $p) {
            $qty  = (int)$_SESSION['cart'][$p['id']];
            $unit = (float)$p['price'];
            $cart_items[] = array_merge($p, ['qty' => $qty, 'unit_price' => $unit]);
            $cart_total  += $unit * $qty;
            $cart_count  += $qty;
        }
    }
}
$wishlist_count = count($_SESSION['wishlist'] ?? []);

// Traitement du formulaire
$success = '';
$errors  = [];
$form    = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // Pré-remplissage
    $form['name']    = trim($_POST['name']    ?? '');
    $form['email']   = trim($_POST['email']   ?? '');
    $form['phone']   = trim($_POST['phone']   ?? '');
    $form['subject'] = trim($_POST['subject'] ?? '');
    $form['message'] = trim($_POST['message'] ?? '');

    // Validation
    if (empty($form['name']))    $errors['name']    = 'Le nom est requis.';
    if (empty($form['email']) || !filter_var($form['email'], FILTER_VALIDATE_EMAIL))
                                  $errors['email']   = 'Email invalide.';
    if (empty($form['subject'])) $errors['subject'] = 'Le sujet est requis.';
    if (strlen($form['message']) < 10)
                                  $errors['message'] = 'Le message doit contenir au moins 10 caractères.';

    if (empty($errors)) {
        // Inclure le téléphone dans le message si fourni
        $msg_to_save = $form['message'];
        if ($form['phone']) {
            $msg_to_save = "Téléphone : " . $form['phone'] . "\n\n" . $form['message'];
        }

        $stmt = $mysqli->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $form['name'], $form['email'], $form['subject'], $msg_to_save);
        if ($stmt->execute()) {
            $success = 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.';
            $form    = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
        } else {
            $errors['general'] = 'Une erreur est survenue. Veuillez réessayer.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="x-ua-compatible" content="ie=edge"/>
    <meta name="description" content="Contactez AtlanTech — votre spécialiste high-tech aux Cayes, Haïti."/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Contact — AtlanTech</title>
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
<div class="body_wrap">

    <!-- preloader -->
    <div class="preloder_part">
        <div class="spinner"><div class="dot1"></div><div class="dot2"></div></div>
    </div>

    <!-- back to top -->
    <div class="progress-wrap">
        <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98"/>
        </svg>
    </div>

    <!-- ===== HEADER ===== -->
    <header class="header header__style-one">
        <div class="header__top-info-wrap d-none d-lg-block">
            <div class="container">
                <div class="header__top-info ul_li_between mt-none-10">
                    <ul class="ul_li mt-10">
                        <li><i class="far fa-map-marker-alt"></i> Les Cayes, Haïti</li>
                        <li><i class="fas fa-phone"></i> +509 4466-7553</li>
                        <li><i class="fas fa-heart"></i> AtlanTech — Votre spécialiste High-Tech en Haïti</li>
                    </ul>
                    <div class="header__top-right ul_li mt-10">
                        <div class="date">
                            <i class="fal fa-calendar-alt"></i>
                            <?php
                                date_default_timezone_set('America/Port-au-Prince');
                                $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::SHORT, 'America/Port-au-Prince');
                                echo ucfirst($formatter->format(new DateTime()));
                            ?>
                        </div>
                        <div class="header__social ml-25">
                            <a href="https://facebook.com/atlantech.service" target="_blank"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
                            <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
                            <a href="mailto:atlantech.service@gmail.com"><i class="far fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="header__middle ul_li_between justify-content-xs-center">
                <div class="header__logo">
                    <a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"/></a>
                </div>
                <form class="header__search-box" action="shop.php" method="GET">
                    <div class="select-box">
                        <select name="category">
                            <option value="">Toutes les Catégories</option>
                            <?php foreach ($rootCategories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" name="search" placeholder="Rechercher un produit..." required/>
                    <button type="submit"><i class="far fa-search"></i></button>
                </form>
                <div class="header__lang ul_li">
                    <div class="header__language mr-15">
                        <ul><li>
                            <a href="#!" class="lang-btn">HTG <i class="far fa-chevron-down"></i></a>
                            <ul class="lang_sub_list"><li><a href="#">HTG</a></li><li><a href="#">USD</a></li></ul>
                        </li></ul>
                    </div>
                    <div class="header__language">
                        <ul><li>
                            <a href="#!" class="lang-btn"><img loading="lazy" src="assets/img/icon/ht_flag.svg" style="width:22px;height:15px;object-fit:cover;border-radius:2px;vertical-align:middle;margin-right:5px;" alt=""/>Kreyòl <i class="far fa-chevron-down"></i></a>
                            <ul class="lang_sub_list"><li><a href="#">Kreyòl</a></li><li><a href="#">Français</a></li><li><a href="#">English</a></li></ul>
                        </li></ul>
                    </div>
                </div>
                <div class="header__icons ul_li">
                    <div class="icon">
                        <?php if (isLoggedIn()): ?>
                            <a href="backoffice-client/dashboard.php" title="Mon Compte"><img loading="lazy" src="assets/img/icon/user.svg" alt=""/></a>
                        <?php else: ?>
                            <a href="account.php" title="Se connecter"><img loading="lazy" src="assets/img/icon/user.svg" alt=""/></a>
                        <?php endif; ?>
                    </div>
                    <div class="icon wishlist-icon">
                        <a href="wishlist.php">
                            <img loading="lazy" src="assets/img/icon/heart.svg" alt=""/>
                            <span class="count"><?php echo $wishlist_count; ?></span>
                        </a>
                    </div>
                    <div class="cart_btn icon">
                        <img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt=""/>
                        <span class="count"><?php echo $cart_count; ?></span>
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
                                <div class="icon bar"><span><i class="fal fa-bars"></i></span></div>
                            </a>
                        </div>
                    </div>
                    <div class="login-sign-btn">
                        <?php if (isLoggedIn()): ?>
                            <a class="thm-btn" href="backoffice-client/dashboard.php">
                                <span class="btn-wrap"><span>Mon Compte</span><span>Mon Compte</span></span>
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

    <!-- slide-bar (panier mobile) -->
    <aside class="slide-bar">
        <div class="close-mobile-menu">
            <a href="javascript:void(0);"><i class="fal fa-times"></i></a>
        </div>
        <div class="cart_sidebar">
            <button type="button" class="cart_close_btn"><i class="fal fa-times"></i></button>
            <h2 class="heading_title text-uppercase">Mon Panier — <span><?php echo $cart_count; ?></span></h2>
            <div class="cart_items_list">
                <?php if (empty($cart_items)): ?>
                    <p style="padding:20px;color:#888;">Votre panier est vide.</p>
                <?php else: foreach ($cart_items as $item): ?>
                <div class="cart_item">
                    <div class="item_image">
                        <img loading="lazy" src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.src='assets/img/product/placeholder.png'">
                    </div>
                    <div class="item_content">
                        <h4 class="item_title"><?php echo htmlspecialchars($item['name']); ?></h4>
                        <span class="item_price"><?php echo number_format($item['unit_price']); ?> HTG × <?php echo $item['qty']; ?></span>
                        <a href="cart.php?remove=<?php echo $item['id']; ?>" class="remove_btn"><i class="fal fa-times"></i></a>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="total_price text-uppercase">
                <span>Total :</span>
                <span><?php echo number_format($cart_total); ?> HTG</span>
            </div>
            <ul class="btns_group ul_li">
                <li><a href="cart.php" class="thm-btn"><span class="btn-wrap"><span>Voir Panier</span><span>Voir Panier</span></span></a></li>
                <li><a href="checkout.php" class="thm-btn thm-btn__black"><span class="btn-wrap"><span>Commander</span><span>Commander</span></span></a></li>
            </ul>
        </div>

        <nav class="side-mobile-menu">
            <div class="header-mobile-search">
                <form role="search" method="GET" action="shop.php">
                    <input type="text" name="search" placeholder="Rechercher...">
                    <button type="submit"><i class="ti-search"></i></button>
                </form>
            </div>
            <ul id="mobile-menu-active">
                <li><a href="index.php">Accueil</a></li>
                <li class="dropdown">
                    <a href="shop.php">Boutique</a>
                    <ul class="sub-menu">
                        <li><a href="shop.php">Tous les Produits</a></li>
                        <?php foreach (array_slice($rootCategories, 0, 5) as $cat): ?>
                        <li><a href="shop.php?category=<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li><a href="cart.php">Panier</a></li>
                <li><a href="wishlist.php">Favoris</a></li>
                <li><a href="about.php">À propos</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </aside>
    <div class="body-overlay"></div>
    <!-- slide-bar end -->

    <main>
        <!-- breadcrumb -->
        <section class="breadcrumb-area">
            <div class="container">
                <div class="atl-breadcrumb breadcrumbs">
                    <ul class="list-unstyled d-flex align-items-center">
                        <li class="atl-bcrumb-item atl-bcrumb-begin"><a href="index.php"><span>Accueil</span></a></li>
                        <li class="atl-bcrumb-item atl-bcrumb-end"><span>Contact</span></li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- info cards -->
        <section class="contact-info pt-60">
            <div class="container">
                <div class="row justify-content-center mt-none-30">

                    <div class="col-xl-3 col-lg-4 col-md-6 mt-30">
                        <div class="contact-info__item d-flex">
                            <span class="icon"><img loading="lazy" src="assets/img/icon/mail.svg" alt=""></span>
                            <div class="content">
                                <h3>Adresse Email</h3>
                                <a href="mailto:atlantech.service@gmail.com">atlantech.service@gmail.com</a>
                                <a href="mailto:contact@atlantech.shop">contact@atlantech.shop</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-4 col-md-6 mt-30">
                        <div class="contact-info__item active d-flex">
                            <span class="icon"><img loading="lazy" src="assets/img/icon/location.svg" alt=""></span>
                            <div class="content">
                                <h3>Notre Adresse</h3>
                                <p>Les Cayes, Sud d'Haïti<br>Rue Antoine Simon, près de la Place d'Armes</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-4 col-md-6 mt-30">
                        <div class="contact-info__item d-flex">
                            <span class="icon"><img loading="lazy" src="assets/img/icon/call-2.svg" alt=""></span>
                            <div class="content">
                                <h3>Téléphone & WhatsApp</h3>
                                <a href="tel:+50944667553">+509 44 66 75 53</a>
                                <a href="https://wa.me/50944667553" target="_blank">WhatsApp disponible</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-lg-4 col-md-6 mt-30">
                        <div class="contact-info__item d-flex">
                            <span class="icon"><img loading="lazy" src="assets/img/icon/c_us.svg" alt=""></span>
                            <div class="content">
                                <h3>Heures d'ouverture</h3>
                                <p>Lun – Sam : 8h00 – 18h00</p>
                                <p>Dimanche : 9h00 – 13h00</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- formulaire + carte -->
        <section class="contact pt-70 pb-90">
            <div class="container">
                <div class="row align-items-start">

                    <!-- Formulaire -->
                    <div class="col-lg-6">
                        <div class="contact-from__wrap">
                            <h2 class="title mb-30">Envoyez-nous un message</h2>
                            <p class="mb-30" style="color:#666;">Une question sur un produit, une commande, ou un service ? Remplissez ce formulaire et notre équipe vous répondra sous 24h.</p>

                            <?php if ($success): ?>
                                <div class="alert alert-success" style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px 20px;border-radius:8px;margin-bottom:20px;">
                                    <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($errors['general'])): ?>
                                <div class="alert alert-danger" style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px 20px;border-radius:8px;margin-bottom:20px;">
                                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($errors['general']); ?>
                                </div>
                            <?php endif; ?>

                            <form class="contact-from" method="POST" action="contact.php">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="contact-from__field">
                                            <input type="text" name="name" placeholder="Votre nom complet *"
                                                   value="<?php echo htmlspecialchars($form['name'] ?: ($user_name ?? '')); ?>"
                                                   style="<?php echo isset($errors['name']) ? 'border-color:#dc3545' : ''; ?>">
                                            <?php if (isset($errors['name'])): ?>
                                                <small style="color:#dc3545"><?php echo $errors['name']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="contact-from__field">
                                            <input type="email" name="email" placeholder="Votre email *"
                                                   value="<?php echo htmlspecialchars($form['email'] ?: $user_email); ?>"
                                                   style="<?php echo isset($errors['email']) ? 'border-color:#dc3545' : ''; ?>">
                                            <?php if (isset($errors['email'])): ?>
                                                <small style="color:#dc3545"><?php echo $errors['email']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="contact-from__field">
                                            <input type="tel" name="phone" placeholder="Téléphone (optionnel)"
                                                   value="<?php echo htmlspecialchars($form['phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="contact-from__field">
                                            <input type="text" name="subject" placeholder="Sujet *"
                                                   value="<?php echo htmlspecialchars($form['subject']); ?>"
                                                   style="<?php echo isset($errors['subject']) ? 'border-color:#dc3545' : ''; ?>">
                                            <?php if (isset($errors['subject'])): ?>
                                                <small style="color:#dc3545"><?php echo $errors['subject']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="contact-from__field">
                                            <textarea name="message" rows="6" placeholder="Votre message *"
                                                      style="<?php echo isset($errors['message']) ? 'border-color:#dc3545' : ''; ?>"><?php echo htmlspecialchars($form['message']); ?></textarea>
                                            <?php if (isset($errors['message'])): ?>
                                                <small style="color:#dc3545"><?php echo $errors['message']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="contact-from__btn mt-20">
                                        <button type="submit" name="send_message" class="thm-btn thm-btn__2">
                                            <span class="btn-wrap">
                                                <span>Envoyer le message</span>
                                                <span>Envoyer le message</span>
                                            </span>
                                            <i class="far fa-long-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Carte Google Maps + infos -->
                    <div class="col-lg-6 mt-40 mt-lg-0">
                        <h2 class="title mb-20">Trouvez-nous</h2>
                        <div style="border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
                            <iframe
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15232.093!2d-73.7537!3d18.1947!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8eca7e8b3af9e7e5%3A0x0!2sLes%20Cayes%2C%20Ha%C3%AFti!5e0!3m2!1sfr!2sht!4v1"
                                width="100%" height="320" style="border:0;" allowfullscreen="" loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                        <div style="margin-top:25px;background:#f8f9fa;border-radius:12px;padding:25px;">
                            <h4 style="margin-bottom:15px;font-size:16px;font-weight:600;">Informations pratiques</h4>
                            <ul style="list-style:none;padding:0;margin:0;font-size:14px;color:#555;line-height:2;">
                                <li><i class="far fa-map-marker-alt" style="color:#ff6b35;width:20px;"></i> Les Cayes, Rue Antoine Simon</li>
                                <li><i class="fas fa-phone" style="color:#ff6b35;width:20px;"></i> <a href="tel:+50944667553" style="color:#555;">+509 44 66 75 53</a></li>
                                <li><i class="fab fa-whatsapp" style="color:#25d366;width:20px;"></i> <a href="https://wa.me/50944667553" target="_blank" style="color:#555;">WhatsApp : +509 44 66 75 53</a></li>
                                <li><i class="far fa-envelope" style="color:#ff6b35;width:20px;"></i> <a href="mailto:atlantech.service@gmail.com" style="color:#555;">atlantech.service@gmail.com</a></li>
                                <li><i class="far fa-clock" style="color:#ff6b35;width:20px;"></i> Lun–Sam 8h–18h / Dim 9h–13h</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="contact-info-area pt-50 pb-80" style="background:#f8f9fa;">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-5 mt-30">
                        <div class="contact-img pos-rel">
                            <img loading="lazy" src="assets/img/contact/img_01.jpg" alt="AtlanTech support" style="border-radius:12px;width:100%;">
                        </div>
                    </div>
                    <div class="col-lg-7 mt-30">
                        <h2 class="title mb-30">Questions fréquentes</h2>
                        <div class="faq_wrap">
                            <ul class="accordion_box clearfix">
                                <li class="accordion block">
                                    <div class="acc-btn">01. Quelles sont vos méthodes de livraison ?</div>
                                    <div class="acc_body">
                                        <div class="content">
                                            <p>Nous livrons dans tout Haïti via nos partenaires logistiques. La livraison à Les Cayes est disponible le jour même ou le lendemain pour les commandes passées avant 14h. Pour les autres villes, comptez 2 à 5 jours ouvrables.</p>
                                        </div>
                                    </div>
                                </li>
                                <li class="accordion block">
                                    <div class="acc-btn active">02. Comment suivre ma commande ?</div>
                                    <div class="acc_body current active-block">
                                        <div class="content">
                                            <p>Connectez-vous à votre compte AtlanTech et rendez-vous dans la section "Vos commandes" de votre tableau de bord. Vous y trouverez le statut en temps réel de chaque commande. Vous recevrez également une notification par email ou SMS.</p>
                                        </div>
                                    </div>
                                </li>
                                <li class="accordion block">
                                    <div class="acc-btn">03. Acceptez-vous les retours ?</div>
                                    <div class="acc_body">
                                        <div class="content">
                                            <p>Oui. Vous disposez de 7 jours après la livraison pour retourner un produit défectueux ou non conforme à la description. Le produit doit être dans son emballage d'origine et non utilisé. Contactez-nous pour initier la procédure de retour.</p>
                                        </div>
                                    </div>
                                </li>
                                <li class="accordion block">
                                    <div class="acc-btn">04. Quels modes de paiement acceptez-vous ?</div>
                                    <div class="acc_body">
                                        <div class="content">
                                            <p>Nous acceptons : le paiement à la livraison (cash), MonCash, NatCash, et les virements bancaires. D'autres méthodes de paiement seront bientôt disponibles.</p>
                                        </div>
                                    </div>
                                </li>
                                <li class="accordion block">
                                    <div class="acc-btn">05. Les produits sont-ils garantis ?</div>
                                    <div class="acc_body">
                                        <div class="content">
                                            <p>Tous nos produits sont certifiés et livrés avec leur garantie constructeur. La durée varie selon la marque et le type de produit (6 mois à 2 ans). Nous proposons également une assistance technique après-vente gratuite.</p>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- ===== FOOTER ===== -->
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
                            <?php if (!empty($rootCategories)): ?>
                                <?php foreach (array_slice($rootCategories, 0, 7) as $cat): ?>
                                    <li><a href="shop.php?category=<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><a href="shop.php">Tous les produits</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Liens rapides</h2>
                        <ul class="quick-links">
                            <li><a href="index.php">Accueil</a></li>
                            <li><a href="shop.php">Boutique</a></li>
                            <li><a href="cart.php">Mon panier</a></li>
                            <li><a href="wishlist.php">Mes favoris</a></li>
                            <li><a href="backoffice-client/dashboard.php">Mon compte</a></li>
                            <li><a href="about.php">À propos</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </div>
                    <div class="footer__widget col-lg-3 col-md-6 mt-40">
                        <h2 class="title">Service client</h2>
                        <ul class="category">
                            <li><a href="backoffice-client/customer-service.php">Centre d'aide</a></li>
                            <li><a href="#">Conditions d'utilisation</a></li>
                            <li><a href="#">Livraison &amp; Expédition</a></li>
                            <li><a href="#">Politique de confidentialité</a></li>
                            <li><a href="#">Retours &amp; Remboursements</a></li>
                            <li><a href="about.php">À propos d'AtlanTech</a></li>
                            <li><a href="backoffice-client/support.php">FAQ</a></li>
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

    <!-- newsletter popup -->
    <section class="newsletter-popup-area-section">
        <div class="newsletter-popup-area">
            <div class="newsletter-popup-ineer">
                <button class="btn newsletter-close-btn"><i class="fal fa-times"></i></button>
                <div class="img-holder">
                    <img loading="lazy" src="assets/img/bg/newsletter.jpg" alt="">
                </div>
                <div class="details">
                    <h4>Obtenez 10% de réduction sur votre première commande</h4>
                    <p>Abonnez-vous à la newsletter AtlanTech pour recevoir nos meilleures offres et nouveautés.</p>
                    <form method="POST" action="contact.php">
                        <div>
                            <input type="email" name="email" placeholder="Entrez votre email"/>
                            <button type="submit">S'abonner</button>
                        </div>
                        <div>
                            <label class="checkbox-holder">Ne plus afficher ce message
                                <input type="checkbox" class="show-message">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- cookies -->
    <div class="cookies-area">
        <p>Ce site utilise des cookies pour améliorer votre expérience. En continuant, vous acceptez notre <a href="#">Politique de confidentialité</a>.</p>
        <a href="#" class="read-more">En savoir plus</a>
        <div><button class="cookie-btn">Accepter</button></div>
    </div>

</div><!-- /body_wrap -->

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
<script src="assets/js/countdown.js"></script>
<script src="assets/js/jquery.magnific-popup.min.js"></script>
<script src="assets/js/metisMenu.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
