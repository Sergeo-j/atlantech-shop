<?php
/**
 * Header commun — AtlanTech E-commerce
 * Inclure après require_once 'config/config.php'
 */

// Charger les catégories si pas déjà fait
if (!isset($rootCategories)) {
    try {
        $r = $mysqli->query("SELECT id, name, slug, icon FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY display_order ASC, name ASC");
        $rootCategories = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Exception $e) { $rootCategories = []; }
}
if (!isset($categoriesByParent)) {
    try {
        $r2 = $mysqli->query("SELECT id, name, slug, parent_id FROM categories WHERE parent_id IS NOT NULL AND is_active = 1 ORDER BY name ASC");
        $subCats = $r2 ? $r2->fetch_all(MYSQLI_ASSOC) : [];
        $categoriesByParent = [];
        foreach ($subCats as $s) { $categoriesByParent[$s['parent_id']][] = $s; }
    } catch (Exception $e) { $categoriesByParent = []; }
}

// Infos utilisateur connecté
if (!isset($user_first_name)) {
    $user_name       = $_SESSION['user_name'] ?? null;
    $user_first_name = $user_name ? explode(' ', $user_name)[0] : null;
}

// Compteurs wishlist + panier — via header_counters.php si pas encore chargés
// header_counters.php interroge la DB pour les users connectés
// et tombe sur $_SESSION pour les visiteurs.
if (!isset($wishlist_count) || !isset($cart_count)) {
    require_once __DIR__ . '/header_counters.php';
}
// Sécurité : toujours un entier
$wishlist_count = max(0, (int)($wishlist_count ?? 0));
$cart_count     = max(0, (int)($cart_count     ?? 0));

// Titre de page (peut être surchargé par la page appelante)
if (!isset($page_title)) { $page_title = 'AtlanTech'; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="x-ua-compatible" content="ie=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title><?php echo htmlspecialchars($page_title); ?> — AtlanTech</title>
  <link rel="shortcut icon" href="assets/img/favicon.png" type="images/x-icon" />
  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css" />
  <!-- Responsive smartphone -->
  <link rel="stylesheet" href="assets/css/mobile.css?v=3" />
  <!-- Google Fonts chargé en non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>
</head>
<body>
<div class="body_wrap">

  <!-- preloader start -->
  <div class="preloder_part" id="site-preloader">
    <div class="spinner"><div class="dot1"></div><div class="dot2"></div></div>
  </div>
  <script>
    setTimeout(function(){ var p=document.getElementById('site-preloader'); if(p){p.style.display='none';} },4000);
    document.addEventListener('DOMContentLoaded',function(){ setTimeout(function(){ var p=document.getElementById('site-preloader'); if(p){p.style.transition='opacity 0.5s';p.style.opacity='0';setTimeout(function(){p.style.display='none';},500);} },500); });
  </script>
  <!-- preloader end -->

  <!-- back to top -->
  <div class="progress-wrap">
    <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
      <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
    </svg>
  </div>

<style>
.account-menu { position: relative; }
.account-trigger { background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: .5rem; color: #fff; font-size: .85rem; }
.account-trigger img { width: 22px; height: 22px; }
.account-trigger .labels { display: flex; flex-direction: column; line-height: 1.1; text-align: left; }
.account-trigger .hello { font-size: .75rem; }
.account-trigger .acct { font-weight: 600; }
.account-dropdown { position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); width: 500px; padding: 1rem; display: none; z-index: 1000; }
.account-menu.open .account-dropdown { display: block; }
.dropdown-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid #eee; padding-bottom: .5rem; margin-bottom: .75rem; font-size: 1rem; }
.dropdown-header .blue { color: #ff9100ff; text-decoration: none; }
.dropdown-header .blue:hover { text-decoration: underline; }
.menu-columns { display: flex; justify-content: space-between; gap: 1rem; }
.menu-group { width: 50%; }
.menu-group h4 { font-size: .85rem; font-weight: 700; margin-bottom: .25rem; }
.menu-group ul { list-style: none; padding: 0; margin: 0; }
.menu-group li { margin: .25rem 0; }
.menu-group a { color: #111; text-decoration: none; font-size: .8rem; }
.menu-group a:hover { text-decoration: underline; }
</style>

  <!-- header start -->
  <header class="header header__style-one">
    <div class="header__top-info-wrap d-none d-lg-block">
      <div class="container">
        <div class="header__top-info ul_li_between mt-none-10">
          <ul class="ul_li mt-10">
            <li><i class="far fa-map-marker-alt"></i>Nos Magasins</li>
            <li><i class="far fa-truck"></i>Suivre ma Commande</li>
            <li><i class="fas fa-phone"></i>Appelez-nous : +509 4466-7553</li>
            <li><i class="fas fa-heart"></i>ATLANTECH - Votre spécialiste High-Tech en Haïti</li>
          </ul>
          <div class="header__top-right ul_li mt-10">
            <div class="header__top-right ul_li mt-10">
              <div class="date">
                <i class="fal fa-calendar-alt"></i>
                <?php
                  date_default_timezone_set('America/Port-au-Prince');
                  $fr_jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                  $fr_mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
                  $now_dt   = new DateTime('now', new DateTimeZone('America/Port-au-Prince'));
                  echo $fr_jours[(int)$now_dt->format('w')] . ' ' . $now_dt->format('d') . ' ' . $fr_mois[(int)$now_dt->format('n')] . ' ' . $now_dt->format('Y');
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
    </div>

    <div class="container">
      <div class="header__middle ul_li_between justify-content-xs-center">
        <div class="header__logo">
          <a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech" /></a>
        </div>
        <form class="header__search-box" action="shop.php" method="get">
          <div class="select-box">
            <select id="category" name="cat">
              <option value="">Toutes catégories</option>
              <?php foreach ($rootCategories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="text" name="q" id="search" placeholder="Rechercher un produit..." />
          <button type="submit"><i class="far fa-search"></i></button>
        </form>

        <div class="header__lang ul_li">
          <div class="header__language mr-15">
            <ul><li><a href="#!" class="lang-btn">HTG <i class="far fa-chevron-down"></i></a><ul class="lang_sub_list"><li><a href="#">HTG</a></li><li><a href="#">USD</a></li></ul></li></ul>
          </div>
          <div class="header__language">
            <ul><li><a href="#!" class="lang-btn"><img src="assets/img/icon/ht_flag.svg" alt="Haïti" style="width:20px;height:14px;vertical-align:middle;margin-right:4px;" />Kreyòl <i class="far fa-chevron-down"></i></a><ul class="lang_sub_list"><li><a href="#">Kreyòl</a></li><li><a href="#">Français</a></li><li><a href="#">English</a></li></ul></li></ul>
          </div>
        </div>

        <div class="account-menu">
          <button class="account-trigger" aria-haspopup="true" aria-expanded="false">
            <img src="assets/img/icon/user.svg" alt="Compte" />
            <span class="labels">
              <span class="hello">
                <?php if (!empty($user_first_name)): ?>Bonjour, <?= htmlspecialchars($user_first_name) ?><?php else: ?>Bonjour, identifiez-vous<?php endif; ?>
              </span>
              <span class="acct">Compte &amp; Listes <i class="fas fa-caret-down"></i></span>
            </span>
          </button>
          <div class="account-dropdown" role="menu">
            <div class="dropdown-header">
              <a href="switch.php">Profil</a>
              <?php if (isLoggedIn()): ?><a href="logout.php" class="blue">Se déconnecter</a><?php else: ?><a href="account.php" class="blue">Se connecter</a><?php endif; ?>
            </div>
            <div class="menu-columns">
              <div class="menu-group">
                <h4>Vos Listes</h4>
                <ul>
                  <li><a href="wishlist.php">Ma Wishlist</a></li>
                  <li><a href="#">Créer une liste</a></li>
                </ul>
              </div>
              <div class="menu-group">
                <h4>Votre Compte</h4>
                <ul>
                  <li><a href="dashboard.php">Mon compte</a></li>
                  <li><a href="dashboard.php">Mes commandes</a></li>
                  <li><a href="account.php">Connexion / Inscription</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="icon wishlist-icon">
          <a href="wishlist.php">
            <img src="assets/img/icon/heart.svg" alt="" />
            <span class="count"><?= $wishlist_count ?></span>
          </a>
        </div>
        <div class="cart_btn icon">
          <a href="cart.php">
            <img src="assets/img/icon/shopping_bag.svg" alt="" />
            <span class="count"><?= $cart_count ?></span>
          </a>
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
            <?php if (isLoggedIn()): ?>
              <a class="thm-btn" href="dashboard.php"><span class="btn-wrap"><span>Mon Compte</span><span>Mon Compte</span></span></a>
            <?php else: ?>
              <a class="thm-btn" href="account.php"><span class="btn-wrap"><span>Connexion / Inscription</span><span>Connexion / Inscription</span></span></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </header>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.account-menu');
    const trigger = menu.querySelector('.account-trigger');
    trigger.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', (e) => { if (!menu.contains(e.target)) menu.classList.remove('open'); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') menu.classList.remove('open'); });
  });
  </script>
  <!-- header end -->

  <!-- slide-bar start -->
  <aside class="slide-bar">
    <div class="close-mobile-menu"><a href="javascript:void(0);"><i class="fal fa-times"></i></a></div>
    <nav class="side-mobile-menu">
      <div class="header-mobile-search">
        <form role="search" method="get" action="shop.php">
          <input type="text" name="q" placeholder="Rechercher..." />
          <button type="submit"><i class="ti-search"></i></button>
        </form>
      </div>
      <ul id="mobile-menu-active">
        <li><a href="index.php">Accueil</a></li>
        <li class="dropdown">
          <a href="shop.php">Boutique</a>
          <ul class="sub-menu">
            <?php foreach ($rootCategories as $cat): ?>
            <li><a href="shop.php?category=<?php echo (int)$cat['id']; ?>"><?= htmlspecialchars($cat['name']) ?></a>
              <?php if (!empty($categoriesByParent[$cat['id']])): ?>
              <ul class="sub-menu">
                <?php foreach ($categoriesByParent[$cat['id']] as $sub): ?>
                <li><a href="shop.php?category=<?php echo (int)$sub['id']; ?>"><?= htmlspecialchars($sub['name']) ?></a></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </li>
        <li><a href="about.php">À propos</a></li>
        <li><a href="contact.php">Contact</a></li>
        <?php if (isLoggedIn()): ?>
        <li><a href="dashboard.php">Mon Compte</a></li>
        <li><a href="logout.php">Déconnexion</a></li>
        <?php else: ?>
        <li><a href="account.php">Connexion / Inscription</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </aside>
  <div class="body-overlay"></div>
  <!-- slide-bar end -->
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        