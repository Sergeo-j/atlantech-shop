<?php require_once 'config/config.php'; require_once 'includes/header_counters.php'; ?>
<!DOCTYPE html>
<html lang="zxx">
  <head>
    <!--========= Required meta tags =========-->
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="description" content="AtlanTech, fondée en 2024, est votre spécialiste High-Tech en Haïti : ordinateurs, smartphones, électroménagers et plus, à prix justes avec un service fiable." />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1, shrink-to-fit=no"
    />

    <title>À Propos - AtlanTech | Spécialiste High-Tech en Haïti</title>

    <link
      rel="shortcut icon"
      href="assets/img/favicon.png"
      type="images/x-icon"
    />

  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css?v=<?php echo filemtime(__DIR__.'/assets/css/bundle.min.css'); ?>" />
  <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime(__DIR__.'/assets/css/mobile.css'); ?>" />

  <style>
  /* ═══ ABOUT PAGE — STYLES SPÉCIFIQUES ═══ */

  /* Breadcrumb — dégradé sombre */
  .breadcrumb-area {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    padding: 36px 0;
  }
  .atl-bcrumb-item a span  { color: rgba(255,255,255,.65); }
  .atl-bcrumb-item.atl-bcrumb-end span { color: #ff9100; font-weight: 600; }
  .atl-breadcrumb li + li::before {
    content: '›'; margin: 0 8px; color: rgba(255,255,255,.4);
  }

  /* Section about — espacement propre */
  .about { padding: 70px 0; }
  .about .row.g-0 + .row.g-0 { margin-top: 60px; }

  /* Image about : coins arrondis */
  .about__img img { border-radius: 12px; width: 100%; }

  /* Boxes d'info */
  .about__info-box { gap: 14px; }
  .about__info-box .icon { flex-shrink: 0; }
  .about__info-box h4  { font-size: 15px; margin-bottom: 4px; }
  .about__info-box p   { font-size: 13px; color: #666; margin: 0; }

  /* Liste checkmark */
  .about__list li {
    padding-left: 22px;
    position: relative;
    font-size: 14px;
    color: #555;
    margin-bottom: 8px;
  }
  .about__list li::before {
    content: '✓';
    position: absolute; left: 0;
    color: #ff9100; font-weight: 700;
  }

  /* Citation fondateur */
  .founder-quote {
    position: relative;
    padding: 20px 20px 20px 30px;
    border-left: 4px solid #ff9100;
    background: #fff9f0;
    border-radius: 0 10px 10px 0;
    margin: 20px 0 16px;
    font-size: 15px;
    line-height: 1.8;
    color: #444;
  }
  .founder-quote::before {
    content: '\201C';
    position: absolute; top: -6px; left: 8px;
    font-size: 60px; color: #ff9100; opacity: .25;
    font-family: Georgia, serif; line-height: 1;
  }
  .founder-meta {
    display: flex; align-items: center; gap: 10px;
    margin-top: 8px;
  }
  .founder-meta strong { color: #222; font-size: 15px; }
  .founder-meta span   { font-size: 13px; color: #888; }

  /* Compteurs statistiques */
  .about-stats {
    background: #f8f9fa;
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
    padding: 50px 0;
  }
  .about-stats .stat-item   { padding: 0 10px; text-align: center; }
  .about-stats .stat-number {
    font-size: 42px; font-weight: 800;
    color: #ff9100; line-height: 1;
    margin-bottom: 10px; display: block;
  }
  .about-stats .stat-label {
    font-size: 11px; color: #888;
    text-transform: uppercase;
    letter-spacing: .8px; font-weight: 600;
  }
  </style>

  <!-- Google Fonts non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>
  </head>

  <body>
<?php include __DIR__ . '/includes/header_mobile_v2.php'; ?>
    <div class="body_wrap">
      <!-- preloder start  -->
      <div class="preloder_part">
        <div class="spinner">
          <div class="dot1"></div>
          <div class="dot2"></div>
        </div>
      </div>
      <!-- preloder end  -->

      <!-- back to top start -->
      <div class="progress-wrap">
        <svg
          class="progress-circle svg-content"
          width="100%"
          height="100%"
          viewBox="-1 -1 102 102"
        >
          <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
      </div>
      <!-- back to top end -->

      <!-- header start -->
      <header class="header header__style-one">
  <div class="header__top-info-wrap d-none d-lg-block">
    <div class="container">
      <div class="header__top-info ul_li_between mt-none-10">
        <ul class="ul_li mt-10">
          <li><i class="far fa-map-marker-alt"></i>Nos Magasins</li>
          <li><i class="far fa-truck"></i>Suivre ma Commande</li>
          <li><i class="fas fa-phone"></i>Appelez-nous : +509 4466-7553</li>
          <li>
            <i class="fas fa-heart"></i>ATLANTECH - Votre spécialiste High-Tech en Haïti
          </li>
        </ul>
        <div class="header__top-right ul_li mt-10">
          <div class="header__top-right ul_li mt-10">
<!--*******************Date ****************-->
    <div class="date">
    <i class="fal fa-calendar-alt"></i>
    <?php
        date_default_timezone_set('America/Port-au-Prince');
        $formatter = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::SHORT,
            'America/Port-au-Prince'
        );
        echo ucfirst($formatter->format(new DateTime()));
    ?>
</div>

          <div class="header__social ml-25">
            <a href="#!"><i class="fab fa-facebook-f"></i></a>
            <a href="#!"><i class="fab fa-twitter"></i></a>
            <a href="#!"><i class="fab fa-instagram"></i></a>
            <a href="#!"><i class="fab fa-youtube"></i></a>
            <a href="#!"><i class="fab fa-whatsapp"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="container">
    <div class="header__middle ul_li_between justify-content-xs-center">
      <div class="header__logo">
        <a href="index.php">
          <img src="assets/img/logo/logo.svg" alt="" />
        </a>
      </div>
      <!-- Catalogue container --> 
      <form class="header__search-box" action="#">
        <div class="select-box">
          <select id="category" name="category">
            <option value="">Toutes les Catégories</option>
            <option value="4">Ordinateurs & Laptops</option>
            <option value="5">Smartphones & Tablettes</option>
            <option value="6">Caméras & Photos</option>
            <option value="7">TV & Audio</option>
            <option value="8">Accessoires Tech</option>
            <option value="9">Gaming & Consoles</option>
            <option value="10">Imprimantes & Scanners</option>
            <option value="11">Réseaux & Wi-Fi</option>
            <option value="12">Électroménagers</option>
          </select>
        </div>
        <input
          type="text"
          name="search"
          id="search"
          placeholder="Rechercher un produit..."
          required
        />
        <button type="submit"><i class="far fa-search"></i></button>
      </form>
      <div class="header__lang ul_li">
        <div class="header__language mr-15">
          <ul>
            <li>
              <a href="#!" class="lang-btn"
                >HTG <i class="far fa-chevron-down"></i
              ></a>
              <ul class="lang_sub_list">
                <li><a href="#">HTG</a></li>
                <li><a href="#">USD</a></li>
              </ul>
            </li>
          </ul>
        </div>
        <div class="header__language">
          <ul>
            <li>
              <a href="#!" class="lang-btn">
                <img loading="lazy" src="assets/img/icon/ht_flag.svg" alt="Haïti" style="width:22px;height:15px;object-fit:cover;border-radius:2px;vertical-align:middle;margin-right:5px;"/>Kreyòl
                <i class="far fa-chevron-down"></i>
              </a>
              <ul class="lang_sub_list">
                <li><a href="#"><img loading="lazy" src="assets/img/icon/ht_flag.svg" alt="" style="width:18px;height:12px;object-fit:cover;margin-right:5px;vertical-align:middle;"/>Kreyòl</a></li>
                <li><a href="#"><img loading="lazy" src="assets/img/icon/fr_flag.svg" alt="" style="width:18px;height:12px;object-fit:cover;margin-right:5px;vertical-align:middle;"/>Français</a></li>
                <li><a href="#"><img loading="lazy" src="assets/img/icon/us_flag.svg" alt="" style="width:18px;height:12px;object-fit:cover;margin-right:5px;vertical-align:middle;"/>English</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div> 
<!--***************** icon user -->
      <div class="header__icons ul_li">
        <div class="icon">
  <?php if (isLoggedIn()): ?>
    <a href="backoffice-client/dashboard.php" title="Mon Compte">
      <img loading="lazy" src="assets/img/icon/user.svg" alt="" />
    </a>
  <?php else: ?>
    <a href="account.php" title="Se connecter">
      <img loading="lazy" src="assets/img/icon/user.svg" alt="" />
    </a>
  <?php endif; ?>
</div>
  <!-- wishlist-->
        <div class="icon wishlist-icon">
          <a href="wishlist.php">
            <img loading="lazy" src="assets/img/icon/heart.svg" alt="" />
            <span class="count"><?= (int)$wishlist_count ?></span>
          </a>
        </div>
        <div class="cart_btn icon">
          <img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt="" />
          <span class="count"><?= (int)$cart_count ?></span>
        </div>
      </div>
    </div>
  </div>
  <div
    class="header__cat-wrap"
    data-uk-sticky="top: 250; animation: uk-animation-slide-top;"
  >
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
  <?php if (isLoggedIn()): ?>
    <a class="thm-btn" href="backoffice-client/dashboard.php">
      <span class="btn-wrap">
        <span>Mon Compte</span>
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
        <?php include 'config/cart-sidebar.php'; ?>


            <!-- side-mobile-menu start -->
            <nav class="side-mobile-menu">
                <div class="header-mobile-search">
                    <form role="search" method="get" action="#">
                        <input type="text" placeholder="Search Keywords">
                        <button type="submit"><i class="ti-search"></i></button>
                    </form>
                </div>
                <ul id="mobile-menu-active">
                    <li class="dropdown"><a href="index.html">Home</a>
                        <ul class="sub-menu">
                            <li><a href="index.html">Home One</a></li>
                            <li><a href="home-2.html">Home Two</a></li>
                            <li><a href="home-3.html">Home Three</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="#">Shop</a>
                        <ul class="sub-menu">
                            <li><a href="shop.html">Shop Default</a></li>
                            <li><a href="shop-left-sidebar.html">Shop Left Sidebar</a></li>
                            <li><a href="shop-single.html">Shop Single</a></li>
                            <li><a href="cart.html">Shop Cart</a></li>
                            <li><a href="checkout.html">Shop Checkout</a></li>
                            <li><a href="account.html">Account</a></li>
                        </ul>
                    </li>
                    <li><a href="shop.html">Accesories</a></li>
                    <li class="dropdown">
                        <a href="#!">Blog</a>
                        <ul class="sub-menu">
                            <li><a href="news.html">Blog</a></li>
                            <li><a href="news-single.html">Blog Details</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="#!">Pages</a>
                        <ul class="submenu">
                            <li><a href="about.html">About Us</a></li>
                            <li><a href="about.html">Account</a></li>
                            <li><a href="404.html">404</a></li>
                        </ul>
                    </li>
                    <li><a href="contact.html">Contact</a></li>
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
                                <span>À propos</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
            <!-- breadcrumb end -->

            <!-- about start -->
            <section class="about">
                <div class="container">
                    <div class="row g-0 align-items-center">
                        <div class="col-xl-6 col-lg-6">
                            <div class="about__img">
                                <img loading="lazy" src="assets/img/about/img_01.jpg" alt="">
                            </div>
                        </div>
                        <div class="col-xl-6 col-lg-6">
                            <div class="about__content pl-70">
                                <h2>Depuis 2024, Nous Rendons la Technologie Accessible en Haïti</h2>
                                <p>AtlanTech est né d'une conviction simple : chaque foyer et chaque entreprise en Haïti mérite un accès facile à une technologie fiable, à un prix juste. Basée aux Cayes, notre équipe 100% haïtienne livre ordinateurs, smartphones, électroménagers et bien plus — partout au pays.</p>
                                <div class="row mt-6">
                                    <div class="col-lg-6 mt-30">
                                        <div class="about__info-box d-flex">
                                            <span class="icon"><img loading="lazy" src="assets/img/icon/about_01.svg" alt=""></span>
                                            <div class="content">
                                                <h4>Prix Justes</h4>
                                                <p>Des produits tech sélectionnés avec soin, à des prix accessibles pour tous les budgets.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mt-30">
                                        <div class="about__info-box d-flex">
                                            <span class="icon"><img loading="lazy" src="assets/img/icon/about_01.svg" alt=""></span>
                                            <div class="content">
                                                <h4>Service Fiable</h4>
                                                <p>Une équipe disponible avant, pendant et après votre achat pour vous accompagner.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <ul class="about__list list-unstyled mt-25">
                                    <li>Produits vérifiés et garantis avant chaque livraison</li>
                                    <li>Support client réactif par téléphone et WhatsApp</li>
                                </ul>
                                <div class="about__btn mt-30">
                                    <a class="thm-btn thm-btn__2" href="shop.php">
                                        <span class="btn-wrap">
                                            <span>Voir Nos Produits</span>
                                            <span>Voir Nos Produits</span>
                                        </span>
                                        <i class="far fa-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-0 align-items-center flex-row-reverse md-mt-30">
                        <div class="col-xl-6 col-lg-6">
                            <div class="about__img">
                                <img loading="lazy" src="assets/img/about/img_03.jpg" alt="">
                            </div>
                        </div>
                        <div class="col-xl-6 col-lg-6">
                            <div class="about__content pr-55">
                                <h3>Notre Engagement</h3>
                                    <p>Depuis notre lancement en 2024, nous travaillons chaque jour pour simplifier l'accès à la technologie partout en Haïti : ordinateurs, smartphones, électroménagers, gaming et bien plus. Notre objectif n'est pas seulement de vendre, mais de bâtir une relation de confiance durable avec chacun de nos clients.</p>
                                <div class="about__video mt-35 ul_li">
                                    <div class="about__video-img pos-rel">
                                        <img loading="lazy" src="assets/img/about/img_02.jpg" alt="">
                                        <a class="popup-video popup-video--sm" href="https://www.youtube.com/watch?v=cRXm1p-CNyk"><i class="fas fa-play"></i></a>
                                    </div>
                                    <div class="about__video-content">
                                        <h4>Pourquoi Choisir AtlanTech ?</h4>
                                        <p>Une équipe 100% haïtienne, engagée à vos côtés à chaque étape</p>
                                        <ul class="about__list list-unstyled mt-15">
                                            <li>Livraison rapide dans plusieurs villes d'Haïti</li>
                                            <li>Paiement flexible : cash, MonCash, carte</li>
                                            <li>Garantie sur tous nos produits</li>
                                            <li>Support client réactif 7j/7</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-0 align-items-center md-mt-30">
                        <div class="col-xl-6 col-lg-6">
                            <div class="about__img">
                                <img loading="lazy" src="assets/img/about/ceo-sergeo-clean.jpg" alt="Sergeo, Fondateur et PDG d'AtlanTech, Les Cayes, Haïti">
                            </div>
                        </div>
                        <div class="col-xl-6 col-lg-6">
                            <div class="about__content pl-70">
                                <h3>Le Mot du Fondateur</h3>
                                <div class="founder-quote">
                                    Nous avons lancé AtlanTech aux Cayes en 2024 avec une conviction simple : la technologie ne devrait pas être un luxe réservé à une seule ville. Notre mission est de livrer confiance et qualité à chaque client, où qu'il soit en Haïti.
                                </div>
                                <div class="founder-meta">
                                    <strong>Sergeo Jean</strong>
                                    <span>— Fondateur &amp; PDG, AtlanTech · Les Cayes, Haïti</span>
                                </div>
                                <ul class="about__list list-unstyled mt-20">
                                    <li>Fondé en 2024 avec une vision nationale</li>
                                    <li>Service disponible partout en Haïti</li>
                                    <li>Équipe 100% haïtienne, engagée pour vous</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- about end -->

            <!-- stats start -->
            <section class="about-stats">
                <div class="container">
                    <div class="row text-center gy-4">
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <span class="stat-number">2024</span>
                                <span class="stat-label">Année de Fondation</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Clients Satisfaits</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <span class="stat-number">1 000+</span>
                                <span class="stat-label">Produits Disponibles</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-item">
                                <span class="stat-number">3+</span>
                                <span class="stat-label">Villes en Haïti</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- stats end -->

            <!-- about info start -->
            <section class="about-info pt-75 pb-100">
                <div class="container">
                    <div class="about-info__wrap">
                        <div class="row align-items-center">
                            <div class="col-xl-4 col-lg-5">
                                <div class="about-info__box">
                                    <div class="about-info__item d-flex">
                                        <span class="number">01</span>
                                        <div class="content">
                                            <h4>Produits Garantis</h4>
                                            <p>Chaque article est vérifié avant expédition pour garantir sa qualité et son bon fonctionnement.</p>
                                        </div>
                                    </div>
                                    <div class="about-info__item d-flex">
                                        <span class="number">02</span>
                                        <div class="content">
                                            <h4>Équipe à Votre Écoute</h4>
                                            <p>Notre équipe répond rapidement à vos questions, du choix du produit jusqu'au service après-vente.</p>
                                        </div>
                                    </div>
                                    <div class="about-info__item d-flex">
                                        <span class="number">03</span>
                                        <div class="content">
                                            <h4>Nous Grandissons Avec Haïti</h4>
                                            <p>Une entreprise 100% haïtienne, fondée pour accompagner la croissance numérique du pays.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-8 col-lg-7">
                                <div class="about-info__tab-wrap pl-150">
                                    <h2>Notre Vision Pour l'Avenir d'AtlanTech</h2>
                                    <p>Lancée en 2024, AtlanTech ambitionne de devenir la référence <br> High-Tech en Haïti — un partenaire de confiance pour les particuliers comme pour les entreprises.</p>
                                    <div class="about-info__tab mt-25">
                                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                              <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Qui Sommes-Nous</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                              <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Notre Objectif</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                              <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false">Notre Engagement</button>
                                            </li>
                                        </ul>
                                        <div class="tab-content" id="myTabContent">
                                            <div class="tab-pane animated fadeInUp show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                                                <div class="about-info__tab-content">
                                                    <ul class="about-info__tab-list list-unstyled">
                                                        <li>Fondée en 2024 aux Cayes, Haïti</li>
                                                        <li>Spécialiste des produits High-Tech pour particuliers et entreprises</li>
                                                        <li>Présente pour servir Haïti, du choix du produit à la livraison</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="tab-pane animated fadeInUp" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                                <div class="about-info__tab-content">
                                                    <ul class="about-info__tab-list list-unstyled">
                                                        <li>Rendre la technologie accessible à tous, sans compromis sur la qualité</li>
                                                        <li>Offrir des prix justes et compétitifs</li>
                                                        <li>Simplifier l'achat en ligne et la livraison partout au pays</li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="tab-pane animated fadeInUp" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                                                <div class="about-info__tab-content">
                                                    <ul class="about-info__tab-list list-unstyled">
                                                        <li>Produits garantis et vérifiés avant chaque livraison</li>
                                                        <li>Support client réactif par téléphone et WhatsApp</li>
                                                        <li>Paiement sécurisé et flexible (Cash, MonCash, carte)</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- about info end -->
            
        </main>

        <!-- footer start -->
        <footer class="footer" data-background="assets/img/bg/footer_bg.jpg">
            <div class="newslater newslater__border pt-30 pb-30">
                <div class="container">
                    <div class="newslater__two ul_li">
                        <div class="newslater__content">
                            <h2 class="title">We are ready to <span>help</span></h2>
                            <p>For information Consult with our expert members</p>
                        </div>
                        <form class="newslater__form" action="#!">
                            <input placeholder="Enter your Email" type="text">
                            <button>Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="footer__main pt-90 pb-90">
                    <div class="row mt-none-40">
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <div class="footer__logo mb-20">
                                <a href="index.html"><img src="assets/img/logo/logo.svg" alt=""></a>
                            </div>
                            <p>4517 Washington Ave. Manchester, Kentucky 39495 ashington Ave. Manchester,</p>
                            <ul class="footer__info mt-30">
                                <li><i class="far fa-map-marker-alt"></i>254 Lillian Blvd, Holbrook</li>
                                <li><i class="fas fa-phone"></i>1-800-654-3210</li>
                            </ul>
                            <div class="apps-img mt-15 ul_li">
                                <div class="app mt-15">
                                    <a href="#!"><img loading="lazy" src="assets/img/icon/google_play.png" alt=""></a>
                                </div>
                                <div class="app mt-15">
                                    <a href="#!"><img loading="lazy" src="assets/img/icon/app_store.png" alt=""></a>
                                </div>
                            </div>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Find It Fast</h2>
                            <ul class="quick-links">
                                <li><a href="#!">Laptops & Computers</a></li>
                                <li><a href="#!">Cameras & Photography</a></li>
                                <li><a href="#!">Smart Phones & Tablets</a></li>
                                <li><a href="#!">Video Games & Consoles</a></li>
                                <li><a href="#!">TV & Audio</a></li>
                                <li><a href="#!">Gadgets</a></li>
                                <li><a href="#!">Waterproof Headphones</a></li>
                            </ul>
                        </div>
                        
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Quick Links</h2>
                            <ul class="quick-links">
                                <li><a href="#!">Your Account</a></li>
                                <li><a href="#!">Returns & Exchanges</a></li>
                                <li><a href="#!">Return Center</a></li>
                                <li><a href="#!">Purchase Hisotry</a></li>
                                <li><a href="#!">App Download</a></li>
            