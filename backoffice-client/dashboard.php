<?php
/**
 * Tableau de bord - Compte Client
 * AtlanTech E-commerce
 */

require_once '../config/config.php';

// Protection : rediriger vers la connexion si non connecté
if (!isLoggedIn()) {
    redirect('../account.php?redirect=dashboard');
}

$user_id = (int)$_SESSION['user_id'];

// Charger les données réelles de l'utilisateur
$stmt = $mysqli->prepare(
    "SELECT id, name, email, phone, profile_image, created_at, last_login
     FROM users WHERE id = ? AND is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Compte désactivé ou introuvable → déconnexion
if (!$user) {
    session_destroy();
    redirect('../account.php');
}

$user_name       = $user['name'];
$user_first_name = explode(' ', trim($user_name))[0];

// Nombre de commandes
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM orders WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders_count = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Nombre d'adresses
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM addresses WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$addresses_count = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Catégories pour le footer
$footerCategories = [];
$res = $mysqli->query("SELECT id, name FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY id ASC LIMIT 7");
if ($res) $footerCategories = $res->fetch_all(MYSQLI_ASSOC);

// Panier réel depuis la session
$cart_items = [];
$cart_total = 0.0;
$cart_count = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_ids = array_map('intval', array_keys($_SESSION['cart']));
    if (!empty($cart_ids)) {
        $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
        $types = str_repeat('i', count($cart_ids));
        $stmt = $mysqli->prepare(
            "SELECT id, name, price, image FROM products
             WHERE id IN ($placeholders) AND is_active = 1"
        );
        $stmt->bind_param($types, ...$cart_ids);
        $stmt->execute();
        $products_db = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($products_db as $p) {
            $qty         = (int)($_SESSION['cart'][$p['id']] ?? 1);
            $subtotal    = $p['price'] * $qty;
            $cart_total += $subtotal;
            $cart_count += $qty;
            $cart_items[] = [
                'id'       => $p['id'],
                'name'     => $p['name'],
                'price'    => $p['price'],
                'image'    => $p['image'],
                'qty'      => $qty,
                'subtotal' => $subtotal,
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zxx">
  <head>
    <!--========= Required meta tags =========-->
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="description" content="" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1, shrink-to-fit=no"
    />

    <title>ATLANTECH</title>

    <link
      rel="shortcut icon"
      href="../assets/img/favicon.png"
      type="images/x-icon"
    />

    <!-- css include -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/fontawesome.css" />
    <link rel="stylesheet" href="../assets/css/animate.css" />
    <link rel="stylesheet" href="../assets/css/metisMenu.css" />
    <link rel="stylesheet" href="../assets/css/uikit.min.css" />
    <link rel="stylesheet" href="../assets/css/jquery-ui.css" />
    <link rel="stylesheet" href="../assets/css/slick.css" />
    <link rel="stylesheet" href="../assets/css/magnific-popup.css" />
    <link rel="stylesheet" href="../assets/css/main.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f3f3f3;
            color: #0F1111;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 400;
            color: #0F1111;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background: white;
            border: 1px solid #D5D9D9;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            gap: 15px;
        }

        .dashboard-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: #007185;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: #F0F2F2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .card-content {
            flex: 1;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #0F1111;
            margin-bottom: 8px;
        }

        .card-description {
            font-size: 14px;
            color: #565959;
            line-height: 1.4;
        }

        .sections-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .section {
            background: white;
            border: 1px solid #D5D9D9;
            border-radius: 8px;
            padding: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #0F1111;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #E7E7E7;
        }

        .section-links {
            list-style: none;
        }

        .section-links li {
            margin-bottom: 10px;
        }

        .section-links a {
            color: #007185;
            text-decoration: none;
            font-size: 14px;
            display: block;
            padding: 5px 0;
            transition: color 0.2s;
        }

        .section-links a:hover {
            color: #C7511F;
            text-decoration: underline;
        }

        /* Icônes personnalisées */
        .icon-orders { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .icon-security { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .icon-prime { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .icon-addresses { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .icon-business { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
        .icon-giftcards { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white; }
        .icon-payments { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #0F1111; }
        .icon-family { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #0F1111; }
        .icon-support { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #0F1111; }
        .icon-lists { background: linear-gradient(135deg, #ff6e7f 0%, #bfe9ff 100%); color: white; }
        .icon-service { background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); color: #0F1111; }
        .icon-messages { background: linear-gradient(135deg, #f8b500 0%, #fceabb 100%); color: #0F1111; }

        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .sections-container {
                grid-template-columns: 1fr;
            }
        }


.account-menu { position: relative; }
.account-trigger {
  background: none; border: none; cursor: pointer;
  display: flex; align-items: center; gap: .5rem;
  color: #fff; font-size: .85rem;
}
.account-trigger img { width: 22px; height: 22px; }
.account-trigger .labels { display: flex; flex-direction: column; line-height: 1.1; text-align: left; }
.account-trigger .hello { font-size: .75rem; }
.account-trigger .acct { font-weight: 600; }

.account-dropdown {
  position: absolute; top: 100%; right: 0;
  background: #fff; border: 1px solid #ddd; border-radius: 4px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.15);
  width: 500px; padding: 1rem; display: none; z-index: 1000;
}
.account-menu.open .account-dropdown { display: block; }

.dropdown-header {
  display: flex; justify-content: space-between; align-items: center;
  border-bottom: 1px solid #eee; padding-bottom: .5rem; margin-bottom: .75rem;
  font-size: 1rem;
}
.dropdown-header .blue { color: #0073bb; text-decoration: none; }
.dropdown-header .blue:hover { text-decoration: underline; }

.menu-columns {
  display: flex; justify-content: space-between; gap: 1rem;
}
.menu-group { width: 50%; }
.menu-group h4 { font-size: .85rem; font-weight: 700; margin-bottom: .25rem; }
.menu-group ul { list-style: none; padding: 0; margin: 0; }
.menu-group li { margin: .25rem 0; }
.menu-group a { color: #111; text-decoration: none; font-size: .8rem; }
.menu-group a:hover { text-decoration: underline; }

    </style>
  </head>

  <body>
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
        <a href="../index.php">
          <img src="../assets/img/logo/logo.svg" alt="" />
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
              <a href="#!" class="lang-btn"
                ><img src="../assets/img/icon/ht_flag.svg" style="width:22px;height:15px;object-fit:cover;border-radius:2px;vertical-align:middle;margin-right:5px;" alt="" />Kreyòl
                <i class="far fa-chevron-down"></i
              ></a>
              <ul class="lang_sub_list">
                <li><a href="#">French</a></li>
                <li><a href="#">Kreyol</a></li>
                <li><a href="#">English</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div> 
<!--***************** icon user -->
      <div class="account-menu">
  <button class="account-trigger" aria-haspopup="true" aria-expanded="false">
    <img src="../assets/img/icon/user.svg" alt="Compte" />
    <span class="labels">
      <span class="hello">
        <?php if (!empty($user_first_name)): ?>
          Bonjour, <?= htmlspecialchars($user_first_name) ?>
        <?php else: ?>
          Bonjour, identifiez-vous
        <?php endif; ?>
      </span>
      <span class="acct">Compte &amp; Listes <i class="fas fa-caret-down"></i></span>
    </span>
  </button>

  <div class="account-dropdown" role="menu" aria-label="Compte et listes">
    <div class="dropdown-header">
      <a href="../switch.php">Qui fait ses achats ? <span class="blue">Profil</span></a>
      <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
        <a href="../logout.php" class="blue">Se déconnecter</a>
      <?php else: ?>
        <a href="../account.php" class="blue">Se connecter</a>
      <?php endif; ?>
    </div>

    <div class="menu-columns">
      <div class="menu-group">
        <h4>Vos Listes</h4>
        <ul>
          <li><a href="#">Liste d’achats 1</a></li>
          <li><a href="#">Créer une liste</a></li>
          <li><a href="#">Trouvez une liste ou un registre</a></li>
          <li><a href="#">Vos livres enregistrés</a></li>
        </ul>
      </div>

      <div class="menu-group">
        <h4>Votre Compte</h4>
        <ul>
          <li><a href="dashboard.php">Mon tableau de bord</a></li>
          <li><a href="orders.php">Vos commandes</a></li>
          <li><a href="addresses.php">Vos adresses</a></li>
          <li><a href="../wishlist.php">Listes de souhaits</a></li>
          <li><a href="security.php">Connexion &amp; sécurité</a></li>
          <li><a href="../contact.php">Service client</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>

  <!-- wishlist-->
        <div class="icon wishlist-icon">
          <a href="../wishlist.php">
            <img src="../assets/img/icon/heart.svg" alt="" />
            <span class="count"><?php echo count($_SESSION['wishlist'] ?? []); ?></span>
          </a>
        </div>
        <div class="cart_btn icon">
          <img src="../assets/img/icon/shopping_bag.svg" alt="" />
          <span class="count"><?php echo $cart_count; ?></span>
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
          <ul class="category ul_li">
            <li>
              <a href="#!"
                ><span
                  ><img src="../assets/img/icon/hc_01.svg" alt="" /></span
                >Ordinateurs & Laptops</a
              >
            </li>
            <li>
              <a href="#!"
                ><span
                  ><img src="../assets/img/icon/hc_02.svg" alt="" /></span
                >Caméras & Surveillance</a
              >
            </li>
            <li>
              <a href="#!"
                ><span
                  ><img src="../assets/img/icon/hc_03.svg" alt="" /></span
                >Électroménagers</a
              >
            </li>
            <li>
              <a href="#!"
                ><span
                  ><img src="../assets/img/icon/hc_04.svg" alt="" /></span
                >TV & Systèmes Audio</a
              >
            </li>
            <li>
              <a href="#!"
                ><span
                  ><img src="../assets/img/icon/hc_05.svg" alt="" /></span
                >Imprimantes & Encres</a
              >
            </li>
            <li>
              <a href="#!"
                ><span
                  ><img src="../assets/img/icon/hc_06.svg" alt="" /></span
                >Gaming & Consoles</a
              >
            </li>
          </ul>
        </div>
        <div class="login-sign-btn">
  <?php if (isLoggedIn()): ?>
    <a class="thm-btn" href="dashboard.php">
      <span class="btn-wrap">
        <span>Mon Compte</span>
        <span>Mon Compte</span>
      </span>
    </a>
  <?php else: ?>
    <a class="thm-btn" href="../account.php">
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
  <!--============================================= end header=======================================================-->

        <!-- slide-bar start -->
        <aside class="slide-bar">
            <div class="close-mobile-menu">
                <a href="javascript:void(0);"><i class="fal fa-times"></i></a>
            </div>

            <!-- sidebar-info start -->
            <div class="cart_sidebar">
                <button type="button" class="cart_close_btn"><i class="fal fa-times"></i></button>
                <h2 class="heading_title text-uppercase">
                    Panier - <span><?php echo $cart_count; ?></span>
                </h2>
                <div class="cart_items_list">
                    <?php if (empty($cart_items)): ?>
                        <p style="text-align:center; padding:20px; color:#666;">
                            Votre panier est vide.
                        </p>
                    <?php else: ?>
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart_item">
                                <div class="item_image">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>"
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             onerror="this.src='../assets/img/product/placeholder.png'">
                                    <?php else: ?>
                                        <img src="../assets/img/product/placeholder.png" alt="produit">
                                    <?php endif; ?>
                                </div>
                                <div class="item_content">
                                    <h4 class="item_title">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                        <small style="display:block;color:#888;">Qté : <?php echo $item['qty']; ?></small>
                                    </h4>
                                    <span class="item_price">
                                        <?php echo number_format($item['subtotal'], 2); ?> HTG
                                    </span>
                                    <a href="../cart.php?remove=<?php echo $item['id']; ?>" class="remove_btn">
                                        <i class="fal fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="total_price text-uppercase">
                    <span>Sous-total :</span>
                    <span><?php echo number_format($cart_total, 2); ?> HTG</span>
                </div>
                <ul class="btns_group ul_li">
                    <li><a href="../cart.php" class="thm-btn">
                        <span class="btn-wrap">
                            <span>Voir le Panier</span>
                            <span>Voir le Panier</span>
                        </span>
                    </a></li>
                    <li><a href="../checkout.php" class="thm-btn thm-btn__black">
                        <span class="btn-wrap">
                            <span>Commander</span>
                            <span>Commander</span>
                        </span>
                    </a></li>
                </ul>
            </div>
            <!-- sidebar-info end -->

            <!-- side-mobile-menu start -->
            <nav class="side-mobile-menu">
                <div class="header-mobile-search">
                    <form role="search" method="get" action="#">
                        <input type="text" placeholder="Search Keywords">
                        <button type="submit"><i class="ti-search"></i></button>
                    </form>
                </div>
                <ul id="mobile-menu-active">
                    <li class="dropdown"><a href="../index.php">Home</a>
                        <ul class="sub-menu">
                            <li><a href="../index.php">Home One</a></li>
                            <li><a href="../home-2.php">Home Two</a></li>
                            <li><a href="../home-3.php">Home Three</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="#">Shop</a>
                        <ul class="sub-menu">
                            <li><a href="../shop.php">Shop Default</a></li>
                            <li><a href="../shop-left-sidebar.php">Shop Left Sidebar</a></li>
                            <li><a href="../shop-single.php">Shop Single</a></li>
                            <li><a href="../cart.php">Shop Cart</a></li>
                            <li><a href="../checkout.php">Shop Checkout</a></li>
                            <li><a href="../account.php">Account</a></li>
                        </ul>
                    </li>
                    <li><a href="../shop.php">Accesories</a></li>
                    <li class="dropdown">
                        <a href="#!">Blog</a>
                        <ul class="sub-menu">
                            <li><a href="../news.php">Blog</a></li>
                            <li><a href="../news-single.php">Blog Details</a></li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a href="#!">Pages</a>
                        <ul class="submenu">
                            <li><a href="../about.php">About Us</a></li>
                            <li><a href="../about.php">Account</a></li>
                            <li><a href="404.php">404</a></li>
                        </ul>
                    </li>
                    <li><a href="../contact.php">Contact</a></li>
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
                                <a href="../index.php"><span>Home</span></a>
                            </li>
                            <li class="atl-bcrumb-item atl-bcrumb-end">
                                <span>My Account</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
            <!-- breadcrumb end -->
  <!--======================================Body================================================-->
            <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Bonjour, <?php echo htmlspecialchars($user_first_name); ?> 👋</h1>
            <p style="color:#555;font-size:14px;margin-top:4px;">
                Membre depuis <?php echo date('F Y', strtotime($user['created_at'])); ?>
                &nbsp;·&nbsp; <?php echo $orders_count; ?> commande<?php echo $orders_count > 1 ? 's' : ''; ?>
                &nbsp;·&nbsp; <?php echo $addresses_count; ?> adresse<?php echo $addresses_count > 1 ? 's' : ''; ?>
            </p>
        </div>

        <div class="cards-grid">
            <div class="dashboard-card" onclick="location.href='orders.php'">
                <div class="card-icon icon-orders">📦</div>
                <div class="card-content">
                    <div class="card-title">Vos Commandes</div>
                    <div class="card-description">Suivre, retourner, annuler une commande, télécharger la facture ou acheter à nouveau</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='security.php'">
                <div class="card-icon icon-security">🔒</div>
                <div class="card-content">
                    <div class="card-title">Connexion et sécurité</div>
                    <div class="card-description">Modifier votre nom, email et numéro de téléphone mobile</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='membership.php'">
                <div class="card-icon icon-prime">⭐</div>
                <div class="card-content">
                    <div class="card-title">Avantages VIP</div>
                    <div class="card-description">Gérer votre adhésion, consulter les avantages et les paramètres de paiement</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='addresses.php'">
                <div class="card-icon icon-addresses">📍</div>
                <div class="card-content">
                    <div class="card-title">Vos Adresses</div>
                    <div class="card-description">Modifier, supprimer ou définir l'adresse par défaut</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='business.php'">
                <div class="card-icon icon-business">💼</div>
                <div class="card-content">
                    <div class="card-title">Votre compte professionnel</div>
                    <div class="card-description">Gérer les tarifs professionnels, la facturation B2B, les profils d'acheteurs et plus encore</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='giftcards.php'">
                <div class="card-icon icon-giftcards">🎁</div>
                <div class="card-content">
                    <div class="card-title">Cartes cadeaux</div>
                    <div class="card-description">Voir le solde ou échanger une carte cadeau et acheter une nouvelle carte cadeau</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='payments.php'">
                <div class="card-icon icon-payments">💳</div>
                <div class="card-content">
                    <div class="card-title">Vos Paiements</div>
                    <div class="card-description">Voir toutes les transactions, gérer les méthodes de paiement et les paramètres</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='family.php'">
                <div class="card-icon icon-family">👨‍👩‍👧‍👦</div>
                <div class="card-content">
                    <div class="card-title">Votre Famille AtlanTech</div>
                    <div class="card-description">Gérer les profils, le partage et les autorisations en un seul endroit</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='support.php'">
                <div class="card-icon icon-support">🛠️</div>
                <div class="card-content">
                    <div class="card-title">Services numériques et Assistance</div>
                    <div class="card-description">Dépanner des problèmes d'appareil, gérer ou annuler des abonnements numériques</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='lists.php'">
                <div class="card-icon icon-lists">📋</div>
                <div class="card-content">
                    <div class="card-title">Vos Listes</div>
                    <div class="card-description">Voir, modifier et partager vos listes ou créer de nouvelles</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='customer-service.php'">
                <div class="card-icon icon-service">💬</div>
                <div class="card-content">
                    <div class="card-title">Service Client</div>
                    <div class="card-description">Parcourir les options du service d'aide, obtenir de l'aide ou nous contacter</div>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='messages.php'">
                <div class="card-icon icon-messages">✉️</div>
                <div class="card-content">
                    <div class="card-title">Vos Messages</div>
                    <div class="card-description">Afficher ou répondre aux messages d'AtlanTech, des vendeurs et des acheteurs</div>
                </div>
            </div>
        </div>

        <div class="sections-container">
            <div class="section">
                <h2 class="section-title">Commandes et préférences d'achat</h2>
                <ul class="section-links">
                    <li><a href="addresses.php">Vos Adresses</a></li>
                    <li><a href="payments.php">Vos Paiements</a></li>
                    <li><a href="transactions.php">Vos Transactions</a></li>
                    <li><a href="preferences.php">Préférences d'achat</a></li>
                    <li><a href="profile.php">Profil</a></li>
                    <li><a href="coupons.php">Coupons</a></li>
                    <li><a href="notifications.php">Préférences de notification</a></li>
                </ul>
            </div>

            <div class="section">
                <h2 class="section-title">Contenu et appareils numériques</h2>
                <ul class="section-links">
                    <li><a href="../wishlist.php">Liste de souhaits</a></li>
                    <li><a href="../devices.php">Appareils</a></li>
                    <li><a href="../recently-viewed.php">Récemment consultés</a></li>
                    <li><a href="../reviews.php">Vos avis</a></li>
                </ul>
            </div>

            <div class="section">
                <h2 class="section-title">Adhésions et abonnements</h2>
                <ul class="section-links">
                    <li><a href="membership.php">Adhésion VIP</a></li>
                    <li><a href="../newsletter.php">Newsletter</a></li>
                    <li><a href="../subscriptions.php">Autres abonnements</a></li>
                </ul>
            </div>

            <div class="section">
                <h2 class="section-title">Communication et contenu</h2>
                <ul class="section-links">
                    <li><a href="../email-preferences.php">Préférences de messagerie</a></li>
                    <li><a href="../advertising.php">Préférences publicitaires</a></li>
                </ul>
            </div>

            <div class="section">
                <h2 class="section-title">Programmes d'achat</h2>
                <ul class="section-links">
                    <li><a href="giftcards.php">Cartes cadeaux</a></li>
                    <li><a href="../loyalty.php">Programme de fidélité</a></li>
                </ul>
            </div>

            <div class="section">
                <h2 class="section-title">Autres programmes</h2>
                <ul class="section-links">
                    <li><a href="../account-linking.php">Liaison de compte</a></li>
                    <li><a href="../referral.php">Programme de parrainage</a></li>
                </ul>
            </div>
        </div>
    </div>
            </main>
  <!--================================================================================ end =================================================-->

        <!-- footer start -->
        <footer class="footer" data-background="../assets/img/bg/footer_bg.jpg">
            <div class="newslater newslater__border pt-30 pb-30">
                <div class="container">
                    <div class="newslater__two ul_li">
                        <div class="newslater__content">
                            <h2 class="title">Nous sommes là pour vous <span>aider</span></h2>
                            <p>Consultez nos experts pour toute information sur nos produits</p>
                        </div>
                        <form class="newslater__form" action="../contact.php" method="post">
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
                                <a href="../index.php"><img src="../assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
                            </div>
                            <p>AtlanTech — Votre partenaire technologique aux Cayes, Haïti. Produits certifiés, service professionnel et livraison rapide.</p>
                            <ul class="footer__info mt-30">
                                <li><i class="far fa-map-marker-alt"></i> Les Cayes, Sud, Haïti</li>
                                <li><i class="fas fa-phone"></i> (+509) 44 66 75 53</li>
                                <li><i class="far fa-envelope"></i> atlantech.service@gmail.com</li>
                            </ul>
                            <div class="apps-img mt-15 ul_li">
                                <div class="app mt-15"><a href="#!"><img src="../assets/img/icon/google_play.png" alt="Google Play"></a></div>
                                <div class="app mt-15"><a href="#!"><img src="../assets/img/icon/app_store.png" alt="App Store"></a></div>
                            </div>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Catégories</h2>
                            <ul class="quick-links">
                                <?php if (!empty($footerCategories)): ?>
                                    <?php foreach ($footerCategories as $cat): ?>
                                        <li><a href="../shop.php?category=<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><a href="../shop.php">Tous les produits</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Liens rapides</h2>
                            <ul class="quick-links">
                                <li><a href="dashboard.php">Mon compte</a></li>
                                <li><a href="../cart.php">Mon panier</a></li>
                                <li><a href="../wishlist.php">Mes favoris</a></li>
                                <li><a href="../checkout.php">Commander</a></li>
                                <li><a href="../contact.php">Contact</a></li>
                                <li><a href="../shop.php">Boutique</a></li>
                                <li><a href="orders.php">Mes commandes</a></li>
                            </ul>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Service client</h2>
                            <ul class="category">
                                <li><a href="customer-service.php">Centre d'aide</a></li>
                                <li><a href="#">Conditions d'utilisation</a></li>
                                <li><a href="#">Livraison &amp; Expédition</a></li>
                                <li><a href="#">Politique de confidentialité</a></li>
                                <li><a href="#">Retours &amp; Remboursements</a></li>
                                <li><a href="../about.php">À propos</a></li>
                                <li><a href="support.php">FAQ</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="footer__bottom ul_li_center">
                    <div class="footer__copyright mt-15">
                        &copy; <?php echo date('Y'); ?> <a href="../index.php">AtlanTech</a>. Tous droits réservés.
                    </div>
                    <div class="footer__social mt-15">
                        <a href="https://facebook.com/atlantech.service" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="mailto:atlantech.service@gmail.com"><i class="far fa-envelope"></i></a>
                    </div>
                    <div class="payment_method mt-15">
                        <img src="../assets/img/bg/payment_method.png" alt="Méthodes de paiement">
                    </div>
                </div>
            </div>
        </footer>
        <!-- footer end -->

        <!-- start newsletter-popup-area-section -->
        <section class="newsletter-popup-area-section">
            <div class="newsletter-popup-area">
                <div class="newsletter-popup-ineer">
                    <button class="btn newsletter-close-btn"><i class="fal fa-times"></i></button>
                    <div class="img-holder">
                        <img src="../assets/img/bg/newsletter.jpg" alt>
                    </div>
                    <div class="details">
                        <h4>Get 45% discount shipped to your inbox</h4>
                        <p>Abonnez-vous à la newsletter AtlanTech pour recevoir nos dernières nouveautés et offres exclusives</p>
                        <form>
                            <div>
                                <input type="email" placeholder="Enter your email" />
                                <button type="submit">Subscribe</button>
                            </div>
                            <div>
                                <label class="checkbox-holder"> Don't show this popup again!
                                    <input type="checkbox" class="show-message">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div> 
        </section>
        <!-- end newsletter-popup-area-section -->


        <!-- start cookies-area -->    
        <div class="cookies-area">
            <p> This website uses cookies to improve your experience. By using this website you agree to our <a href="#">Data Protection Policy</a>. </p>
            <a href="#" class="read-more">Read more</a>
            <div>
                <button class="cookie-btn">Accept</button>
            </div>
        </div>
        <!-- end cookies-area -->


    </div>

    <!-- jquery include -->
    <script src="../assets/js/jquery-3.5.1.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/slick.js"></script>
    <script src="../assets/js/backToTop.js"></script>
    <script src="../assets/js/uikit.min.js"></script>
    <script src="../assets/js/resize-sensor.min.js"></script>
    <script src="../assets/js/theia-sticky-sidebar.min.js"></script>
    <script src="../assets/js/wow.min.js"></script>
    <script src="../assets/js/jqueryui.js"></script>
    <script src="../assets/js/touchspin.js"></script>
    <script src="../assets/js/countdown.js"></script>
    <script src="../assets/js/jquery.magnific-popup.min.js"></script>
    <script src="../assets/js/metisMenu.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
    // Dropdown Compte & Listes
    document.addEventListener('DOMContentLoaded', function() {
        var menu    = document.querySelector('.account-menu');
        if (!menu) return;
        var trigger = menu.querySelector('.account-trigger');

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target)) menu.classList.remove('open');
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') menu.classList.remove('open');
        });
    });
    </script>
</body>


  </html>
