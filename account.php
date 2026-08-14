<?php
/**
 * Page d'authentification (Login & Sign Up)
 * AtlanTech E-commerce
 * Synchronisé avec la table users - VERSION MYSQLI
 */

require_once 'config/config.php';
require_once 'includes/header_counters.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('index.php');
}

// Variables pour affichage des erreurs
$errors = [];
$success = '';

// ============================================
// TRAITEMENT INSCRIPTION (Sign Up)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    
    // Récupération et nettoyage des données
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $terms = isset($_POST['terms']);
    
    // VALIDATION ÉTAPE PAR ÉTAPE
    
    // 1. Vérifier nom (users.name VARCHAR(100))
    if (empty($name)) {
        $errors['name'] = "Le nom est requis";
    } elseif (strlen($name) < 3) {
        $errors['name'] = "Le nom doit contenir au moins 3 caractères";
    } elseif (strlen($name) > 100) {
        $errors['name'] = "Le nom ne doit pas dépasser 100 caractères";
    }
    
    // 2. Vérifier email (users.email VARCHAR(150) UNIQUE)
    if (empty($email)) {
        $errors['email'] = "L'email est requis";
    } elseif (!isValidEmail($email)) {
        $errors['email'] = "Format d'email invalide";
    } elseif (strlen($email) > 150) {
        $errors['email'] = "L'email ne doit pas dépasser 150 caractères";
    } else {
        // Vérifier si email existe déjà (UNIQUE constraint) - VERSION MYSQLI
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['email'] = "Cet email est déjà utilisé";
        }
        $stmt->close();
    }
    
    // 3. Vérifier password (users.password VARCHAR(255))
    if (empty($password)) {
        $errors['password'] = "Le mot de passe est requis";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères";
    }
    
    // 4. Vérifier acceptation des conditions
    if (!$terms) {
        $errors['terms'] = "Vous devez accepter les conditions";
    }
    
    // SI AUCUNE ERREUR : INSERTION DANS LA DB
    if (empty($errors)) {
        try {
            // Générer token de vérification
            $verification_token = generateToken();
            
            // Hasher le mot de passe
            $hashed_password = hashPassword($password);
            
            // Préparer la requête SQL (correspond exactement à la structure de la table users) - VERSION MYSQLI
            $sql = "INSERT INTO users (
                        name, 
                        email, 
                        password, 
                        role, 
                        email_verified, 
                        verification_token, 
                        is_active,
                        created_at
                    ) VALUES (?, ?, ?, 'client', 0, ?, 1, NOW())";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $verification_token);
            
            if ($stmt->execute()) {
                // Récupérer l'ID du nouvel utilisateur
                $user_id = $mysqli->insert_id;

                // Envoyer email de bienvenue (vérification + code promo cadeau)
                $verify_link = SITE_URL . "/verify-email.php?token=" . $verification_token;
                require_once __DIR__ . '/config/order_emails.php';
                try {
                    @sendWelcomeEmail($email, $name, $verify_link, $mysqli);
                } catch (\Throwable $eMail) {
                    error_log('welcome email failed: ' . $eMail->getMessage());
                }
                
                // Message de succès
                $success = "Inscription réussie ! Un email de vérification a été envoyé à " . $email;
                
                // Optionnel : Connexion automatique après inscription
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'client';
                $_SESSION['email_verified'] = 0;

                // Recharger le panier sauvegardé (persistance login/logout)
                require_once __DIR__ . '/includes/cart_persist.php';
                cart_db_load($mysqli, (int)$user_id);

                // Rediriger après 2 secondes
                header("refresh:2;url=index.php");
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Erreur inscription : " . $e->getMessage());
            $errors['general'] = "Une erreur est survenue lors de l'inscription";
        }
    }
}

// ============================================
// TRAITEMENT CONNEXION (Login)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // Récupération et nettoyage des données
    $email = clean($_POST['lemail'] ?? '');
    $password = $_POST['lpassword'] ?? '';
    $remember = isset($_POST['remember']);
    
    // VALIDATION
    
    if (empty($email)) {
        $errors['lemail'] = "L'email est requis";
    } elseif (!isValidEmail($email)) {
        $errors['lemail'] = "Format d'email invalide";
    }
    
    if (empty($password)) {
        $errors['lpassword'] = "Le mot de passe est requis";
    }
    
    // SI AUCUNE ERREUR : VÉRIFICATION DANS LA DB
    if (empty($errors)) {
        try {
            // Récupérer l'utilisateur depuis la DB - VERSION MYSQLI
            $sql = "SELECT id, name, email, password, role, email_verified, is_active, force_password_change
                    FROM users
                    WHERE email = ?";
            
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Vérifier si l'utilisateur existe
            if (!$user) {
                $errors['lemail'] = "Email ou mot de passe incorrect";
            }
            // Vérifier si le compte est actif (users.is_active)
            elseif ($user['is_active'] == 0) {
                $errors['general'] = "Votre compte a été désactivé. Contactez le support.";
            }
            // Vérifier le mot de passe (users.password)
            elseif (!verifyPassword($password, $user['password'])) {
                $errors['lpassword'] = "Email ou mot de passe incorrect";
            }
            // Tout est OK : CONNEXION RÉUSSIE
            else {
                // Migration transparente vers un hash plus fort si nécessaire
                // (ex: ancien compte bcrypt -> Argon2id). Aucun impact pour le
                // client : ça se passe silencieusement à la prochaine connexion.
                if (passwordNeedsRehash($user['password'])) {
                    $new_hash = hashPassword($password);
                    $rehash_stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $rehash_stmt->bind_param('si', $new_hash, $user['id']);
                    $rehash_stmt->execute();
                    $rehash_stmt->close();
                }

                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['email_verified'] = $user['email_verified'];
                $_SESSION['force_password_change'] = (int)($user['force_password_change'] ?? 0);

                // Recharger le panier sauvegardé (persistance login/logout)
                require_once __DIR__ . '/includes/cart_persist.php';
                cart_db_load($mysqli, (int)$user['id']);

                // Mettre à jour last_login - VERSION MYSQLI
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $mysqli->prepare($update_sql);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Cookie "Se souvenir de moi" (30 jours)
                if ($remember) {
                    $token = generateToken();
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                    
                    // Sauvegarder le token en DB (optionnel, nécessite une colonne remember_token)
                }
                
                // Si un DG/superadmin a généré un mot de passe temporaire pour ce
                // compte (cas extrême), on force le changement avant toute autre
                // action — même s'il tape directement l'URL d'une autre page,
                // security.php le renverra ici tant que le flag est actif.
                if (!empty($user['force_password_change'])) {
                    redirect('backoffice-client/security.php?tab=password&forced=1');
                }

                // Redirection selon le rôle
                if ($user['role'] === 'admin') {
                    redirect('admin/admin_dashboard.php');
                } else {
                    redirect('index.php');
                }
            }
            
        } catch (Exception $e) {
            error_log("Erreur connexion : " . $e->getMessage());
            $errors['general'] = "Une erreur est survenue lors de la connexion";
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
      href="assets/img/favicon.png"
      type="images/x-icon"
    />

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
          <img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech" />
        </a>
      </div>
      <!-- Barre de recherche -->
      <form class="header__search-box" action="shop.php" method="get">
        <div class="select-box">
          <select id="category" name="cat">
            <option value="">Toutes les Catégories</option>
            <option value="ordinateurs-laptops">Ordinateurs & Laptops</option>
            <option value="smartphones-tablettes">Smartphones & Tablettes</option>
            <option value="cameras-photos">Caméras & Photos</option>
            <option value="tv-audio">TV & Audio</option>
            <option value="accessoires-tech">Accessoires Tech</option>
            <option value="gaming-consoles">Gaming & Consoles</option>
            <option value="imprimantes-scanners">Imprimantes & Scanners</option>
            <option value="reseaux-wifi">Réseaux & Wi-Fi</option>
            <option value="electromenagers">Électroménagers</option>
          </select>
        </div>
        <input type="text" name="q" id="search" placeholder="Rechercher un produit..." />
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
                    <form role="search" method="get" action="shop.php">
                        <input type="text" name="q" placeholder="Rechercher un produit...">
                        <button type="submit"><i class="far fa-search"></i></button>
                    </form>
                </div>
                <ul id="mobile-menu-active">
                    <li><a href="index.php">Accueil</a></li>
                    <li class="dropdown">
                        <a href="shop.php">Boutique</a>
                        <ul class="sub-menu">
                            <li><a href="shop.php?cat=ordinateurs-laptops">Ordinateurs & Laptops</a></li>
                            <li><a href="shop.php?cat=smartphones-tablettes">Smartphones & Tablettes</a></li>
                            <li><a href="shop.php?cat=cameras-photos">Caméras & Photos</a></li>
                            <li><a href="shop.php?cat=tv-audio">TV & Audio</a></li>
                            <li><a href="shop.php?cat=accessoires-tech">Accessoires Tech</a></li>
                            <li><a href="shop.php?cat=gaming-consoles">Gaming & Consoles</a></li>
                            <li><a href="shop.php?cat=electromenagers">Électroménagers</a></li>
                        </ul>
                    </li>
                    <li><a href="account.php">Connexion / Inscription</a></li>
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
                                <span>Mon Compte</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
            <!-- breadcrumb end -->

<!-- ================================  Sign up and login section ====================================================-->
        <!-- account start -->
<section class="account pb-90">
    <div class="container">
        <div class="row mt-none-30">
            
            <!-- FORMULAIRE INSCRIPTION -->
            <div class="col-lg-6 mt-30">
                <div class="account__wrap pr-60">
                    <h2 class="account__title">Créer un compte</h2>

                    <?php if (!empty($errors) && isset($_POST['signup'])): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-1"><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="account__input-field">
                            <label for="name">Votre nom</label>
                            <input id="name" name="name" type="text" placeholder="Entrez votre nom complet"
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>

                        <div class="account__input-field">
                            <label for="email">Adresse e-mail</label>
                            <input id="email" name="email" type="email" placeholder="Entrez votre adresse e-mail"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <div class="account__input-field">
                            <label for="password">Mot de passe</label>
                            <div style="position:relative;">
                                <input id="password" name="password" type="password" placeholder="Créez un mot de passe" style="padding-right:42px;width:100%;box-sizing:border-box;">
                                <button type="button" onclick="togglePwd('password')" title="Afficher/Masquer" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#aaa;line-height:1;">
                                    <i id="eye-password" class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="account__input-field">
                            <input class="form-check-input" id="checkbox" name="terms" type="checkbox">
                            <label class="form-check-label" for="checkbox">J'accepte les <a href="#">Conditions d'utilisation</a> et la <a href="#">Politique de confidentialité</a></label>
                        </div>

                        <div class="account__btn">
                            <button type="submit" name="signup" class="thm-btn thm-btn__2">
                                <span class="btn-wrap">
                                    <span>Créer mon compte</span>
                                    <span>Créer mon compte</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- FORMULAIRE CONNEXION -->
            <div class="col-lg-6 mt-30">
                <div class="account__wrap pl-60">
                    <h2 class="account__title">Se connecter</h2>

                    <?php if (!empty($errors) && isset($_POST['login'])): ?>
                        <div class="alert alert-danger">
                            <?php if (isset($errors['general'])): ?>
                                <p class="mb-1"><?php echo $errors['general']; ?></p>
                            <?php endif; ?>
                            <?php if (isset($errors['lemail'])): ?>
                                <p class="mb-1"><?php echo $errors['lemail']; ?></p>
                            <?php endif; ?>
                            <?php if (isset($errors['lpassword'])): ?>
                                <p class="mb-1"><?php echo $errors['lpassword']; ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="account__input-field">
                            <label for="lemail">Adresse e-mail</label>
                            <input id="lemail" name="lemail" type="email" placeholder="Entrez votre adresse e-mail"
                                   value="<?php echo isset($_POST['lemail']) ? htmlspecialchars($_POST['lemail']) : ''; ?>">
                        </div>

                        <div class="account__input-field">
                            <label for="lpassword">Mot de passe</label>
                            <div style="position:relative;">
                                <input id="lpassword" name="lpassword" type="password" placeholder="Entrez votre mot de passe" style="padding-right:42px;width:100%;box-sizing:border-box;">
                                <button type="button" onclick="togglePwd('lpassword')" title="Afficher/Masquer" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#aaa;line-height:1;">
                                    <i id="eye-lpassword" class="far fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="account__input-field" style="display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <input class="form-check-input" id="lcheckbox" name="remember" type="checkbox" checked>
                                <label class="form-check-label" for="lcheckbox">Se souvenir de moi</label>
                            </div>
                            <a href="forgot-password.php"
                               style="font-size:13px;color:#ff8717;text-decoration:none;font-weight:600;">
                                Mot de passe oublié ?
                            </a>
                        </div>

                        <div class="account__btn">
                            <button type="submit" name="login" class="thm-btn thm-btn__2">
                                <span class="btn-wrap">
                                    <span>Se connecter</span>
                                    <span>Se connecter</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</section>
<!-- account end -->
<!--***************************************************** end of sign up and log in ***********************************************-->
            
        </main>

        <!-- footer start -->
        <footer class="footer" data-background="assets/img/bg/footer_bg.jpg">
            <div class="newslater newslater__border pt-30 pb-30">
                <div class="container">
                    <div class="newslater__two ul_li">
                        <div class="newslater__content">
                            <h2 class="title">Restez informé avec <span>AtlanTech</span></h2>
                            <p>Inscrivez-vous pour recevoir nos offres et nouveautés</p>
                        </div>
                        <form class="newslater__form" action="#!">
                            <input placeholder="Entrez votre adresse e-mail" type="email">
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
                            <p>Votre spécialiste High-Tech en Haïti. Produits certifiés, service de qualité.</p>
                            <ul class="footer__info mt-30">
                                <li><i class="far fa-map-marker-alt"></i>Les Cayes, Haïti</li>
                                <li><i class="fas fa-phone"></i><a href="tel:+50944667553">+509 4466-7553</a></li>
                                <li><i class="fas fa-envelope"></i><a href="mailto:info@atlantech.ht">info@atlantech.ht</a></li>
                            </ul>
                            <div class="footer__social mt-20">
                                <a href="#!" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="#!" title="Instagram"><i class="fab fa-instagram"></i></a>
                                <a href="#!" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                <a href="#!" title="YouTube"><i class="fab fa-youtube"></i></a>
                            </div>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Nos Catégories</h2>
                            <ul class="quick-links">
                                <li><a href="shop.php?cat=ordinateurs-laptops">Ordinateurs & Laptops</a></li>
                                <li><a href="shop.php?cat=smartphones-tablettes">Smartphones & Tablettes</a></li>
                                <li><a href="shop.php?cat=cameras-photos">Caméras & Photos</a></li>
                                <li><a href="shop.php?cat=tv-audio">TV & Audio</a></li>
                                <li><a href="shop.php?cat=gaming-consoles">Gaming & Consoles</a></li>
                                <li><a href="shop.php?cat=imprimantes-scanners">Imprimantes & Scanners</a></li>
                                <li><a href="shop.php?cat=electromenagers">Électroménagers</a></li>
                            </ul>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Mon Compte</h2>
                            <ul class="quick-links">
                                <li><a href="account.php">Se connecter</a></li>
                                <li><a href="account.php">Créer un compte</a></li>
                                <li><a href="backoffice-client/dashboard.php">Tableau de bord</a></li>
                                <li><a href="backoffice-client/dashboard.php">Mes commandes</a></li>
                                <li><a href="wishlist.php">Ma liste de souhaits</a></li>
                                <li><a href="cart.php">Mon panier</a></li>
                            </ul>
                        </div>
                        <div class="footer__widget col-lg-3 col-md-6 mt-40">
                            <h2 class="title">Assistance</h2>
                            <ul class="quick-links">
                                <li><a href="contact.php">Nous contacter</a></li>
                                <li><a href="#">Conditions d'utilisation</a></li>
                                <li><a href="#">Politique de confidentialité</a></li>
                                <li><a href="#">Politique de retour</a></li>
                                <li><a href="#">Livraison & Délais</a></li>
                                <li><a href="#">FAQ</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="footer__bottom ul_li_center">
                    <div class="footer__copyright mt-15">
                        &copy; <?php echo date('Y'); ?> <a href="index.php">AtlanTech</a>. Tous droits réservés.
                    </div>
                    <div class="payment_method mt-15">
                        <img loading="lazy" src="assets/img/bg/payment_method.png" alt="Méthodes de paiement">
                    </div>
                </div>
            </div>
        </footer>
        <!-- footer end -->

        <!-- start cookies-area -->
        <div class="cookies-area">
            <p>Ce site utilise des cookies pour améliorer votre expérience. En utilisant ce site, vous acceptez notre <a href="#">Politique de confidentialité</a>.</p>
            <a href="#" class="read-more">En savoir plus</a>
            <div>
                <button class="cookie-btn">Accepter</button>
            </div>
        </div>
        <!-- end cookies-area -->


    </div>

    <!-- jquery include -->
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
    <script>
    function togglePwd(id) {
        var inp = document.getElementById(id);
        var ico = document.getElementById('eye-' + id);
        if (inp.type === 'password') {
            inp.type = 'text';
            ico.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            inp.type = 'password';
            ico.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>


</html>
