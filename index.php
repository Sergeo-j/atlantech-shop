<?php
/**
 * Page d'Accueil — AtlanTech E-commerce
 * Toutes les sections connectées à la base de données
 */

// ── Affichage d'erreurs : uniquement en développement ───────────────
$_atl_env = getenv('APP_ENV') ?: 'production';
if ($_atl_env === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

require_once 'config/config.php';
require_once 'includes/header_counters.php';

// Infos utilisateur connecté
$user_name       = $_SESSION['user_name'] ?? null;
$user_first_name = $user_name ? explode(' ', $user_name)[0] : null;

// ── Helper : générer étoiles ─────────────────────────────────────────
function stars(float $rating): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<li><i class="' . ($i <= round($rating) ? 'fas' : 'far') . ' fa-star"></i></li>';
    }
    return $html;
}

// ── Helper : carte produit type "tab-product" ────────────────────────
function product_card_tab(array $p): string {
    $fb_n = (((int)$p['id'] - 1) % 177) + 1;
    $fb   = sprintf('assets/img/product/img_%02d.png', $fb_n);
    $img  = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $fb;
    $url = 'shop-single.php?id=' . (int)$p['id'];
    $price = fmt_price((float)$p['price']);
    $old   = !empty($p['old_price']) && $p['old_price'] > $p['price']
           ? '<span class="old-price">' . number_format((float)$p['old_price'], 2) . '</span>' : '';
    $badge = $p['is_new'] ? '<span class="badge-skew">Nouveau</span>' : '';
    return '
    <div class="tab-product__item tx-product text-center">
      <div class="thumb"><a href="' . $url . '"><img loading="lazy" src="' . $img . '" onerror="this.src=\'' . $fb . '\';" alt="' . htmlspecialchars($p['name']) . '"></a></div>
      <div class="content">
        <div class="product__review ul_li_center">
          <ul class="rating-star ul_li mr-10">' . stars((float)$p['rating']) . '</ul>
          <span>(' . (int)$p['sold_count'] . ')</span>
        </div>
        <h3 class="title"><a href="' . $url . '">' . htmlspecialchars($p['name']) . '</a></h3>
        <span class="price">( ' . $price . ($old ? ' - ' . $old : '') . ' )</span>
      </div>
      <ul class="product__action">
        <li><a href="#!" title="Aperçu"><i class="far fa-compress-alt"></i></a></li>
        <li><a href="cart.php?add=' . (int)$p['id'] . '"><i class="far fa-shopping-basket"></i></a></li>
        <li><a href="wishlist.php?add=' . (int)$p['id'] . '"><i class="far fa-heart"></i></a></li>
      </ul>
      ' . $badge . '
    </div>';
}

// ── Helper : carte produit type "recent-product" ─────────────────────
function product_card_recent(array $p): string {
    $fb_n = (((int)$p['id'] - 1) % 177) + 1;
    $fb   = sprintf('assets/img/product/img_%02d.png', $fb_n);
    $img  = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $fb;
    $url = 'shop-single.php?id=' . (int)$p['id'];
    $old = !empty($p['old_price']) && $p['old_price'] > $p['price']
         ? '<span class="old">' . number_format((float)$p['old_price'], 2) . '</span>' : '';
    return '
    <div class="col-lg-4 col-md-6">
      <div class="recent-product__item tx-product ul_li mt-30">
        <div class="thumb"><a href="' . $url . '"><img loading="lazy" src="' . $img . '" onerror="this.src=\'' . $fb . '\';" alt="' . htmlspecialchars($p['name']) . '"></a></div>
        <div class="recent-product__content">
          <ul class="rating-star ul_li mr-10">' . stars((float)$p['rating']) . '</ul>
          <h3><a href="' . $url . '">' . htmlspecialchars($p['name']) . '</a></h3>
          <h4 class="product__price">
            <span class="new">' . number_format((float)$p['price'], 2) . '</span>' . $old . '
          </h4>
        </div>
      </div>
    </div>';
}

// ════════════════════════════════════════════════════════════════════
// REQUÊTES BASE DE DONNÉES — OPTIMISÉES (16 → 6 requêtes)
//
// STRATÉGIE :
//  - 1 requête maître produits (top 200) → tri PHP pour chaque vue
//  - 1 requête catégories (racines + sous-catégories en une passe)
//  - 1 requête produits par catégorie (loop 6×1 → 1 GROUP)
//  - 1 requête produits par marque (loop 5×1 → 1 GROUP)
//  - APCu cache 5 min si disponible (graceful fallback si désactivé)
// ════════════════════════════════════════════════════════════════════

/**
 * Récupère les données homepage avec cache APCu si disponible.
 * TTL : 300 s (5 minutes) — suffisant pour une homepage e-commerce.
 * Si APCu n'est pas installé/activé, exécute les requêtes directement.
 */
function _atl_homepage_data(mysqli $db): array
{
    $cache_key = 'atl_homepage_v1';
    $ttl       = 300;

    // ── Lire depuis APCu si disponible ─────────────────────────────
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cache_key, $ok);
        if ($ok && is_array($cached)) {
            return $cached;
        }
    }

    $data = [];

    // ── Q1 : Toutes les catégories actives en une seule requête ─────
    try {
        $r = $db->query(
            "SELECT id, name, slug, icon, image, parent_id, display_order
             FROM categories WHERE is_active = 1
             ORDER BY display_order ASC, name ASC"
        );
        $all_cats = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    } catch (\Throwable $e) { error_log("Index Q1: " . $e->getMessage()); $all_cats = []; }

    $roots = [];
    $byParent = [];
    foreach ($all_cats as $c) {
        if ($c['parent_id'] === null) {
            $roots[] = $c;
        } else {
            $byParent[$c['parent_id']][] = $c;
        }
    }
    $data['rootCategories']    = $roots;
    $data['categoriesByParent'] = $byParent;
    $data['rd_categories']     = array_slice($roots, 0, 8);

    // ── Q2 : Requête maître produits (top 200 actifs) ───────────────
    // Une seule requête charge tous les champs nécessaires.
    // Le tri PHP remplace les 9 requêtes ORDER BY différentes.
    try {
        $r = $db->query(
            "SELECT id, name, image, price, old_price, rating, sold_count,
                    is_new, is_featured, is_bestseller, is_hot_deal,
                    discount_percent, short_description, category_id, brand_id,
                    stock, views, created_at
             FROM products WHERE is_active = 1
             ORDER BY created_at DESC
             LIMIT 200"
        );
        $master = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    } catch (\Throwable $e) { error_log("Index Q2: " . $e->getMessage()); $master = []; }

    fill_product_images($master);

    // Tri PHP pour chaque vue (copies légères — pas de duplication mémoire grâce aux refs)
    $by_recent     = $master; // déjà ORDER BY created_at DESC
    $by_bestseller = $master;
    $by_top        = $master;
    $by_new        = $master;
    $by_toprated   = $master;

    usort($by_bestseller, fn($a,$b) =>
        $b['sold_count'] <=> $a['sold_count'] ?: $b['views'] <=> $a['views']
    );
    usort($by_top, fn($a,$b) =>
        $b['is_featured'] <=> $a['is_featured'] ?:
        $b['is_bestseller'] <=> $a['is_bestseller'] ?:
        $b['is_hot_deal'] <=> $a['is_hot_deal'] ?:
        $b['rating'] <=> $a['rating']
    );
    usort($by_new, fn($a,$b) =>
        $b['is_new'] <=> $a['is_new'] ?: strcmp($b['created_at'], $a['created_at'])
    );
    usort($by_toprated, fn($a,$b) =>
        $b['rating'] <=> $a['rating'] ?: $b['sold_count'] <=> $a['sold_count']
    );

    $data['products_recent']         = array_slice($by_recent,     0, 10);
    $data['products_bestseller']     = array_slice($by_bestseller, 0, 10) ?: $data['products_recent'];
    $data['products_top']            = array_slice($by_top,        0, 10) ?: $data['products_recent'];
    $data['products_new']            = array_slice($by_new,        0, 10) ?: $data['products_recent'];
    $data['products_toprated']       = array_slice($by_toprated,   0, 10) ?: $data['products_top'];
    $data['products_recent_section'] = array_slice($by_recent,     0,  9);

    // Featured : filtre PHP sur le master, complété jusqu'à 24 produits
    $featured_all  = array_values(array_filter($master, fn($p) => $p['is_featured']));
    $featured_ids  = array_column($featured_all, 'id');
    $non_featured  = array_values(array_filter($master, fn($p) => !in_array($p['id'], $featured_ids)));
    $needed        = max(0, 24 - count($featured_all));
    $data['products_featured'] = array_slice(
        array_merge($featured_all, array_slice($non_featured, 0, $needed)), 0, 24
    );

    // Hero : featured récents (5 premiers)
    $data['hero_products'] = array_slice(
        $featured_all ?: $master, 0, 5
    );

    // Trending (pour carrousel principal) — bestsellers + bien notés
    $trending = $by_bestseller;
    usort($trending, fn($a,$b) =>
        ($b['sold_count'] * 2 + $b['rating']) <=> ($a['sold_count'] * 2 + $a['rating'])
    );
    $data['products_trending'] = array_slice($trending, 0, 12);

    // ── Q3 : Marques actives ─────────────────────────────────────────
    try {
        $r = $db->query("SELECT id, name, logo FROM brands WHERE is_active = 1 ORDER BY name ASC");
        $data['brands'] = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    } catch (\Throwable $e) { error_log("Index Q3: " . $e->getMessage()); $data['brands'] = []; }

    // ── Q4 : Produits par catégorie (1 requête au lieu de 6) ─────────
    $data['products_by_cat'] = [];
    if (!empty($roots)) {
        $top_cat_ids = array_column(array_slice($roots, 0, 6), 'id');
        $ids_in      = implode(',', array_map('intval', $top_cat_ids));
        try {
            $r = $db->query(
                "SELECT id, name, image, price, old_price, rating, sold_count, is_new, category_id
                 FROM products
                 WHERE is_active = 1 AND category_id IN ($ids_in)
                 ORDER BY category_id ASC, sold_count DESC"
            );
            $cat_rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
            fill_product_images($cat_rows);
            // Distribuer par catégorie (max 4 produits chacune)
            foreach ($cat_rows as $p) {
                $cid = (int)$p['category_id'];
                if (!isset($data['products_by_cat'][$cid])) {
                    $data['products_by_cat'][$cid] = [];
                }
                if (count($data['products_by_cat'][$cid]) < 4) {
                    $data['products_by_cat'][$cid][] = $p;
                }
            }
        } catch (\Throwable $e) { error_log("Index Q4: " . $e->getMessage()); }
    }

    // ── Q5 : Produits par marque (1 requête au lieu de 5) ───────────
    $data['products_by_brand'] = [];
    if (!empty($data['brands'])) {
        $top_brand_ids = array_column(array_slice($data['brands'], 0, 5), 'id');
        $bids_in       = implode(',', array_map('intval', $top_brand_ids));
        try {
            $r = $db->query(
                "SELECT id, name, image, price, old_price, rating, sold_count, is_new, brand_id
                 FROM products
                 WHERE is_active = 1 AND brand_id IN ($bids_in)
                 ORDER BY brand_id ASC, sold_count DESC"
            );
            $brand_rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
            fill_product_images($brand_rows);
            foreach ($brand_rows as $p) {
                $bid = (int)$p['brand_id'];
                if (!isset($data['products_by_brand'][$bid])) {
                    $data['products_by_brand'][$bid] = [];
                }
                if (count($data['products_by_brand'][$bid]) < 8) {
                    $data['products_by_brand'][$bid][] = $p;
                }
            }
        } catch (\Throwable $e) { error_log("Index Q5: " . $e->getMessage()); }
    }
    if (empty($data['products_by_brand'])) {
        $data['products_by_brand'][0] = $data['products_trending'];
    }

    // ── Q6 : Banners ────────────────────────────────────────────────
    try {
        $r = $db->query(
            "SELECT id, title, subtitle, image, link FROM banners WHERE is_active = 1 ORDER BY id ASC LIMIT 6"
        );
        $data['banners'] = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    } catch (\Throwable $e) { error_log("Index Q6: " . $e->getMessage()); $data['banners'] = []; }

    // ── Stocker en cache APCu si disponible ─────────────────────────
    if (function_exists('apcu_store')) {
        apcu_store($cache_key, $data, $ttl);
    }

    return $data;
}

// ── Exécuter et extraire les variables ───────────────────────────────
$_hp = _atl_homepage_data($mysqli);

$rootCategories      = $_hp['rootCategories'];
$categoriesByParent  = $_hp['categoriesByParent'];
$rd_categories       = $_hp['rd_categories'];
$products_recent     = $_hp['products_recent'];
$products_bestseller = $_hp['products_bestseller'];
$products_top        = $_hp['products_top'];
$products_new        = $_hp['products_new'];
$products_toprated   = $_hp['products_toprated'];
$products_recent_section = $_hp['products_recent_section'];
$products_featured   = $_hp['products_featured'];
$hero_products       = $_hp['hero_products'];
$products_trending   = $_hp['products_trending'];
$brands              = $_hp['brands'];
$products_by_cat     = $_hp['products_by_cat'];
$products_by_brand   = $_hp['products_by_brand'];
$banners             = $_hp['banners'];
unset($_hp);

// ── Variable first_hero utilisée dans le HTML ─────────────────────
$first_hero = $hero_products[0] ?? null;

?>

<!DOCTYPE html>
<html lang="fr">
  <head>
    <!--========= Required meta tags =========-->
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

    <?php
    $site_url   = rtrim(env('SITE_URL', 'http://localhost/atlantech-shop'), '/');
    $page_title = 'AtlanTech — Technologie &amp; Innovation en Haïti';
    $page_desc  = 'AtlanTech est la première grande plateforme e-commerce de technologie en Haïti. Téléphones, ordinateurs, accessoires et bien plus — livraison rapide partout en Haïti.';
    $og_image   = $site_url . '/assets/img/logo/logo.png';
    ?>

    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_desc, ENT_QUOTES) ?>" />
    <meta name="keywords" content="technologie haïti, e-commerce haïti, téléphones port-au-prince, ordinateurs haïti, electronique haiti, acheter en ligne haiti, atlantech" />
    <meta name="robots" content="index, follow" />
    <meta name="author" content="AtlanTech" />
    <link rel="canonical" href="<?= $site_url ?>/" />

    <!-- Open Graph (Facebook, WhatsApp, LinkedIn) -->
    <meta property="og:type"        content="website" />
    <meta property="og:url"         content="<?= $site_url ?>/" />
    <meta property="og:title"       content="AtlanTech — Technologie &amp; Innovation en Haïti" />
    <meta property="og:description" content="<?= htmlspecialchars($page_desc, ENT_QUOTES) ?>" />
    <meta property="og:image"       content="<?= $og_image ?>" />
    <meta property="og:image:width"  content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:locale"      content="fr_HT" />
    <meta property="og:site_name"   content="AtlanTech" />

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image" />
    <meta name="twitter:title"       content="AtlanTech — Technologie &amp; Innovation en Haïti" />
    <meta name="twitter:description" content="<?= htmlspecialchars($page_desc, ENT_QUOTES) ?>" />
    <meta name="twitter:image"       content="<?= $og_image ?>" />

    <!-- Schema.org — WebSite + SearchAction -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebSite",
          "@id": "<?= $site_url ?>/#website",
          "url": "<?= $site_url ?>/",
          "name": "AtlanTech",
          "description": "Première grande plateforme e-commerce de technologie en Haïti",
          "inLanguage": "fr",
          "potentialAction": {
            "@type": "SearchAction",
            "target": {
              "@type": "EntryPoint",
              "urlTemplate": "<?= $site_url ?>/shop.php?search={search_term_string}"
            },
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "Organization",
          "@id": "<?= $site_url ?>/#organization",
          "name": "AtlanTech",
          "url": "<?= $site_url ?>/",
          "logo": {
            "@type": "ImageObject",
            "url": "<?= $og_image ?>"
          },
          "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer service",
            "availableLanguage": ["French", "Haitian Creole"]
          },
          "areaServed": {
            "@type": "Country",
            "name": "Haiti"
          }
        }
        <?php if (!empty($products_featured)): ?>
        ,{
          "@type": "ItemList",
          "name": "Produits vedettes AtlanTech",
          "itemListElement": [
            <?php
            $schema_items = [];
            foreach (array_slice($products_featured, 0, 6) as $idx => $p):
                $p_url   = $site_url . '/shop-single.php?id=' . (int)$p['id'];
                $p_img   = !empty($p['image']) ? $site_url . '/uploads/products/' . $p['image'] : $og_image;
                $p_name  = addslashes(htmlspecialchars_decode($p['name']));
                $schema_items[] = sprintf(
                    '{"@type":"ListItem","position":%d,"name":"%s","url":"%s","image":"%s","offers":{"@type":"Offer","price":"%.2f","priceCurrency":"HTG","availability":"https://schema.org/%s"}}',
                    $idx + 1, $p_name, $p_url, $p_img,
                    (float)$p['price'],
                    ((int)($p['stock'] ?? 1) > 0 ? 'InStock' : 'OutOfStock')
                );
            endforeach;
            echo implode(",\n            ", $schema_items);
            ?>
          ]
        }
        <?php endif; ?>
      ]
    }
    </script>

    <link rel="shortcut icon" href="assets/img/favicon.png" type="image/x-icon" />

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
  /* Produits Par Marque — images cover (ciblé uniquement) */
  #brandTabContent .product__img{overflow:hidden!important;height:160px!important;position:relative!important;display:block!important}
  #brandTabContent .product__img a{display:block!important;width:100%!important;height:100%!important}
  #brandTabContent .product__img img{width:100%!important;height:100%!important;object-fit:cover!important;display:block!important;margin:0!important;max-width:none!important;max-height:none!important}
  /* Restore carousels Top Produits + Produits Tendance */
  .hot-deal__slide .thumb,.hot-deal__slide .thumb a,.rd-product__slide .product__img,.rd-product__slide .product__img a{display:flex!important;align-items:center!important;justify-content:center!important;height:auto!important;width:100%!important}
  .hot-deal__slide .thumb img,.rd-product__slide .product__img img{width:auto!important;height:auto!important;max-width:100%!important;max-height:180px!important;object-fit:contain!important;display:block!important;margin:0 auto!important}
  /* Produits Par Marque — 2 colonnes sur mobile */
  @media(max-width:767px){
    #brandTabContent .col-lg-3,#brandTabContent .col-md-6{width:50%!important;padding-left:6px!important;padding-right:6px!important}
  }
  /* ── Section Produits Vedettes — 6 col PC / 3 col mobile ── */
  .atl-vedettes{padding:36px 0;background:#f3f3f3}
  .atl-vedettes .atl-vhdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
  .atl-vedettes .atl-vhdr h2{font-size:22px;font-weight:700;color:#111;margin:0}
  .atl-vedettes .atl-vhdr a{color:#007185;font-size:14px;text-decoration:none}
  .atl-vedettes .atl-vhdr a:hover{text-decoration:underline;color:#c7511f}
  .atl-vgrid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
  @media(max-width:767px){.atl-vgrid{grid-template-columns:repeat(3,1fr);gap:8px}}
  .atl-vcard{background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px;display:flex;flex-direction:column;transition:box-shadow .15s}
  .atl-vcard:hover{box-shadow:0 2px 12px rgba(0,0,0,.16);border-color:#bbb}
  .atl-vcard-img{display:flex;align-items:center;justify-content:center;height:120px;overflow:hidden;margin-bottom:8px}
  .atl-vcard-img img{max-height:100%;max-width:100%;object-fit:contain;transition:transform .2s}
  .atl-vcard:hover .atl-vcard-img img{transform:scale(1.05)}
  .atl-vcard-stars{display:flex;align-items:center;gap:4px;margin-bottom:4px}
  .atl-vcard-stars .rating-star{display:flex;flex-direction:row;list-style:none;margin:0;padding:0;gap:1px}
  .atl-vcard-stars .rating-star li{display:inline;font-size:10px;color:#f0a500}
  .atl-vcard-stars .atl-rcount{font-size:10px;color:#555}
  .atl-vcard-name{font-size:12px;color:#0066c0;line-height:1.35;margin-bottom:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-decoration:none}
  .atl-vcard-name:hover{color:#c7511f;text-decoration:underline}
  .atl-vcard-price{font-size:14px;font-weight:700;color:#0F1111;margin-bottom:2px}
  .atl-vcard-old{font-size:11px;color:#888;text-decoration:line-through;margin-bottom:8px}
  .atl-vcard-btn{display:flex;align-items:center;justify-content:center;gap:5px;background:#FFD814;border:1px solid #FCD200;border-radius:16px;padding:5px 8px;font-size:11px;font-weight:600;color:#0F1111;text-decoration:none;margin-top:auto;transition:background .15s}
  .atl-vcard-btn:hover{background:#F7CA00;color:#0F1111}
  @media(max-width:767px){
    .atl-vcard-img{height:90px}
    .atl-vcard-name{font-size:11px}
    .atl-vcard-price{font-size:12px}
    .atl-vcard-btn{font-size:10px;padding:4px 6px}
  }
  /* Petits écrans Android (<=380px) : contenu compacté pour éviter les débordements */
  @media(max-width:380px){
    .atl-vgrid{gap:6px}
    .atl-vcard{padding:7px}
    .atl-vcard-img{height:80px}
    .atl-vcard-name{font-size:10px}
    .atl-vcard-price{font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .atl-vcard-old{font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px}
    .atl-vcard-btn{font-size:9px;padding:4px 4px;gap:3px}
    .atl-vcard-stars .rating-star li{font-size:8px}
    .atl-vcard-stars .atl-rcount{font-size:8px}
  }
  /* ── Catégories De Produits — 2 colonnes mobile, style pro ── */
  @media(max-width:767px){
    .product-cat{padding-top:28px!important;padding-bottom:10px!important}
    .product-cat .section-heading{font-size:17px!important;margin-bottom:14px!important}
    .product-cat .row.mt-none-50{margin-top:0!important;display:flex;flex-wrap:wrap}
    /* 2 colonnes */
    .product-cat .col-lg-4{width:50%!important;margin-top:0!important;padding:0 5px 12px!important;flex:0 0 50%;max-width:50%}
    .product-cat__item{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.09);overflow:hidden;border:1px solid #eee;height:100%;display:flex;flex-direction:column}
    /* Image principale */
    .product-cat__img{height:120px!important;overflow:hidden!important;display:flex!important;align-items:center!important;justify-content:center!important;background:#f8f8f8}
    .product-cat__img a{display:block!important;width:100%!important;height:100%!important}
    .product-cat__img img{width:100%!important;height:100%!important;object-fit:cover!important;display:block!important}
    /* Miniatures */
    .product-cat__nav{padding:4px 6px!important;border-top:1px solid #eee;background:#fff;display:flex!important;gap:4px!important;flex-wrap:nowrap;overflow-x:auto}
    .product-cat__nav .nav-link{padding:2px!important;border-radius:3px!important;border:1px solid #ddd!important;min-width:28px}
    .product-cat__nav .nav-link.active{border-color:#ff9100!important;background:#fff8f0!important}
    .product-cat__nav .nav-link img{width:26px!important;height:26px!important;object-fit:cover!important;border-radius:2px!important;display:block}
    /* Contenu */
    .product-cat__content{padding:8px!important;flex:1;display:flex;flex-direction:column}
    .product-cat__content .title{font-size:12px!important;font-weight:700!important;margin-bottom:6px!important;padding-bottom:4px;border-bottom:2px solid #ff9100}
    .product-cat__content .title a{color:#111!important}
    .product-cat__content ul{padding:0!important;margin:0 0 6px!important;list-style:none!important;flex:1}
    .product-cat__content ul li{display:flex;justify-content:space-between;align-items:baseline;padding:3px 0;border-bottom:1px solid #f5f5f5;font-size:10px;line-height:1.3}
    .product-cat__content ul li a{color:#444!important;text-decoration:none!important;flex:1;margin-right:4px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
    .product-cat__content ul li span{color:#ff9100;font-weight:700;white-space:nowrap;font-size:10px}
    .product-cat__link{font-size:11px!important;font-weight:600;color:#ff9100!important;text-decoration:none;margin-top:auto}
  }
  </style>
  </head>

  <body>
<?php include __DIR__ . '/includes/header_mobile_v2.php'; ?>
    <?php include __DIR__ . '/includes/promo_banner.php'; ?>
    <div class="body_wrap">
      <!-- preloder start  -->
      <div class="preloder_part" id="site-preloader">
        <div class="spinner">
          <div class="dot1"></div>
          <div class="dot2"></div>
        </div>
      </div>
      <!-- preloder end  -->
      <script>
        // Fallback : cache la préloader après 4s max, même si jQuery/window.load échoue
        setTimeout(function(){
          var p = document.getElementById('site-preloader');
          if (p) { p.style.display = 'none'; }
        }, 4000);
        // Cache immédiatement dès que le DOM est prêt (sécurité supplémentaire)
        document.addEventListener('DOMContentLoaded', function(){
          setTimeout(function(){
            var p = document.getElementById('site-preloader');
            if (p) { p.style.transition = 'opacity 0.5s'; p.style.opacity = '0'; setTimeout(function(){ p.style.display='none'; }, 500); }
          }, 500);
        });
      </script>

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
<style>
/* ── Compte dropdown ────────────────────────────── */
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
  border-bottom: 5px solid #eee; padding-bottom: .5rem; margin-bottom: .75rem;
  font-size: 1rem;
}
.dropdown-header .blue { color: #ff9100ff; text-decoration: none; }
.dropdown-header .blue:hover { text-decoration: underline; }

.menu-columns { display: flex; justify-content: space-between; gap: 1rem; }
.menu-group { width: 50%; }
.menu-group h4 { font-size: .85rem; font-weight: 700; margin-bottom: .25rem; }
.menu-group ul { list-style: none; padding: 0; margin: 0; }
.menu-group li { margin: .25rem 0; }
.menu-group a { color: #111; text-decoration: none; font-size: .8rem; }
.menu-group a:hover { text-decoration: underline; }

/* ── SLIDE-BAR PANEL ────────────────────────────── */
.slide-bar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0 18px 0;
  margin-bottom: 18px;
  border-bottom: 1px solid #f0f0f0;
}
.slide-bar__logo img { max-height: 36px; width: auto; }
.slide-bar__header .close-mobile-menu a {
  font-size: 22px;
  color: #333;
  line-height: 1;
}
.slide-bar__header .close-mobile-menu a:hover { color: var(--color-primary, #ff9100); }

/* ── MOBILE HEADER ──────────────────────────────── */
.mobile-logo { display: none; }
.mobile-logo img { max-height: 32px; width: auto; }
.mobile-icons { display: none; gap: 10px; align-items: center; margin-right: 8px; }
.mobile-icon-btn { position: relative; display: flex; align-items: center; }
.mobile-icon-btn img { width: 22px; height: 22px; filter: brightness(0) invert(1); }
.mobile-header-right { gap: 0; }

/* ── RESPONSIVE ─────────────────────────────────── */
@media (max-width: 767px) {
  /* Afficher logo + icônes sur mobile */
  .mobile-logo { display: block; }
  .mobile-icons { display: flex; }

  /* Header : tout sur une ligne — le logo rétrécit si nécessaire */
  .header__cat { flex-wrap: nowrap !important; align-items: center !important; }
  .mobile-logo { min-width: 0; overflow: hidden; flex-shrink: 1; }
  .mobile-logo img { max-width: 100%; height: auto; }

  /* Dropdown compte : full width sur mobile */
  .account-dropdown {
    width: calc(100vw - 20px);
    right: -60px;
    max-width: 360px;
  }
  .menu-columns { flex-direction: column; }
  .menu-group { width: 100%; }

  /* Cacher le menu compte sur mobile (accessible via slide-bar) */
  .account-menu { display: none; }

  /* Ajuster la barre de catégories */
  .header__wrap { flex-wrap: nowrap; }

  /* Hero : réduire la hauteur */
  .hero.hero__height { min-height: auto !important; padding: 30px 0; }
  .hero .container { padding-left: 15px; padding-right: 15px; }

  /* Section catégories produits : pleine largeur */
  .product-cat__images, .product-cat__content { width: 100% !important; }
  .product-cat__images { padding-right: 0 !important; margin-bottom: 15px; }
  .product-cat__item { flex-direction: column; }

  /* Espacement général des sections */
  .pt-60 { padding-top: 35px !important; }
  .pt-80 { padding-top: 40px !important; }
  .pb-60 { padding-bottom: 35px !important; }
  .mt-50 { margin-top: 30px !important; }

  /* Titre section */
  .section-heading { font-size: 20px !important; }

  /* Bouton connexion taille */
  .login-sign-btn a { padding: 10px 12px; font-size: 12px; }

  /* Product card : éviter le débordement */
  .product__item { margin-bottom: 20px; }
}

@media (max-width: 480px) {
  /* Très petits écrans */
  .mobile-logo img { max-height: 26px; }
  .product-cat__wrap { padding: 15px; }
  .hero__content h1, .hero__content .title { font-size: 22px !important; }
  .login-sign-btn a { padding: 8px 10px; font-size: 11px; }
}
/* ── Produits Tendance — paires 2 produits par slide ── */
.atl-trend-pair{display:flex;gap:0;width:100%}
.atl-trend-pair .product__item{flex:1;min-width:0;border-right:1px solid #eee}
.atl-trend-pair .product__item:last-child{border-right:none}
@media(max-width:767px){
  .atl-trend-pair{background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.09)}
  .atl-trend-pair .product__img img{max-height:95px!important}
  .atl-trend-pair .product__action{display:none!important}
  .atl-trend-pair .product__content{padding:5px 4px 3px!important}
  .atl-trend-pair .product__review{display:flex!important;flex-wrap:wrap!important;justify-content:center!important;gap:2px!important;margin-bottom:2px!important}
  .atl-trend-pair .product__review span{display:block!important;font-size:9px!important;color:#777!important}
  .atl-trend-pair .rating-star li{font-size:7px!important;color:#f0a500}
  .atl-trend-pair .product__title{font-size:10px!important;line-height:1.3!important;margin-bottom:2px!important;text-align:center}
  .atl-trend-pair .product__price{display:flex!important;flex-direction:column!important;align-items:center!important;gap:0!important;margin-bottom:1px!important}
  .atl-trend-pair .product__price .new{font-size:14px!important;color:#0F1111!important;font-weight:800!important;letter-spacing:-.3px}
  .atl-trend-pair .product__price .old,.atl-trend-pair .product__price .old .p-usd,.atl-trend-pair .product__price .old .p-htg{font-size:9px!important;color:#e74c3c!important;text-decoration:line-through!important}
  .atl-disc-badge{display:block;text-align:center;background:#27ae60;color:#fff;font-size:8px;font-weight:700;padding:1px 0;margin:1px 4px 3px}
  .atl-trend-pair .product-card-btn{display:flex!important;align-items:center!important;justify-content:center!important;gap:3px!important;padding:5px!important;font-size:9px!important;font-weight:700!important;background:#ff9100!important;border:none!important;color:#fff!important;border-radius:0!important;margin-top:auto!important;text-decoration:none;width:100%!important;box-sizing:border-box!important}
  .atl-trend-pair .product-card-btn i{font-size:10px!important}
}
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
        $fr_jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
        $fr_mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin',
                     'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $now_dt   = new DateTime('now', new DateTimeZone('America/Port-au-Prince'));
        echo $fr_jours[(int)$now_dt->format('w')] . ' '
           . $now_dt->format('d') . ' '
           . $fr_mois[(int)$now_dt->format('n')] . ' '
           . $now_dt->format('Y');
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
      <!-- ++++++++++++++++++++++++++++++++++++++++Catalogue container++++++++++++++++++++++++++++ --> 
      <form class="header__search-box" action="shop.php" method="get">
        <div class="select-box">
          <div class="select-box">
            <select id="category" name="cat">
              <option value="">All Categories</option>
                  <?php foreach ($rootCategories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['slug']) ?>">
                <?= htmlspecialchars($cat['name']) ?>
               </option>
              <?php endforeach; ?>
            </select>
          </div>
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
<!--************************************************************ icon user*********************************** -->
  <div class="account-menu">
  <button class="account-trigger" aria-haspopup="true" aria-expanded="false">
    <img loading="lazy" src="assets/img/icon/user.svg" alt="Compte" />
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
      <a href="switch.php">Qui fait ses achats ? <span class="blue"> Profil</span></a>
      <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
        <a href="logout.php" class="blue">Se déconnecter</a>
      <?php else: ?>
        <a href="account.php" class="blue">Se connecter</a>
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
          <li><a href="#">Votre compte</a></li>
          <li><a href="#">Vos commandes</a></li>
          <li><a href="#">Vos adresses</a></li>
          <li><a href="#">Vos paiements</a></li>
          <li><a href="#">Préférences d’achat</a></li>
          <li><a href="#">Listes de souhaits</a></li>
          <li><a href="#">Paramètres de sécurité</a></li>
          <li><a href="#">Abonnements &amp; Services</a></li>
          <li><a href="#">Service client</a></li>
        </ul>
      </div>
    </div>
  </div>
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
          <!-- Logo visible uniquement sur mobile -->
          <div class="mobile-logo">
            <a href="index.php">
              <img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech" />
            </a>
          </div>
        </div>
        <!-- Icônes mobiles (panier, favoris) + bouton connexion -->
        <div class="mobile-header-right d-flex align-items-center">
          <div class="mobile-icons">
            <a href="wishlist.php" class="mobile-icon-btn">
              <img loading="lazy" src="assets/img/icon/heart.svg" alt="Favoris" />
            </a>
            <div class="cart_btn mobile-icon-btn">
              <img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt="Panier" />
              <span class="count"><?= (int)$cart_count ?></span>
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
                  <span>Connexion</span>
                  <span>Connexion</span>
                </span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>
  <!--=++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ befor body-->
  <script>
document.addEventListener('DOMContentLoaded', ()=>{
  const menu = document.querySelector('.account-menu');
  const trigger = menu.querySelector('.account-trigger');
  const dropdown = menu.querySelector('.account-dropdown');

  trigger.addEventListener('click', (e)=>{
    e.stopPropagation();
    menu.classList.toggle('open');
  });

  document.addEventListener('click', (e)=>{
    if (!menu.contains(e.target)) menu.classList.remove('open');
  });

  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') menu.classList.remove('open');
  });
});
</script>

      <!-- header end -->

      <!-- slide-bar start -->
      <aside class="slide-bar">
        <div class="slide-bar__header">
          <a href="index.php" class="slide-bar__logo">
            <img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech" />
          </a>
          <div class="close-mobile-menu">
            <a href="javascript:void(0);"><i class="fal fa-times"></i></a>
          </div>
        </div>
        <?php include 'config/cart-sidebar.php'; ?>


        <!-- side-mobile-menu start -->
        <nav class="side-mobile-menu">
          <div class="header-mobile-search">
            <form role="search" method="get" action="shop.php">
              <input type="text" name="q" placeholder="Rechercher un produit..." />
              <button type="submit"><i class="far fa-search"></i></button>
            </form>
          </div>
          <ul id="mobile-menu-active">
            <li><a href="index.php">Accueil</a></li>
            <!-- Catégories dynamiques -->
            <li class="dropdown">
              <a href="shop.php">Boutique</a>
              <ul class="sub-menu">
                <?php foreach ($rootCategories as $cat): ?>
                <li>
                  <a href="shop.php?category=<?php echo (int)$cat['id']; ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                  </a>
                  <?php if (!empty($categoriesByParent[$cat['id']])): ?>
                  <ul class="sub-menu">
                    <?php foreach ($categoriesByParent[$cat['id']] as $subCat): ?>
                    <li>
                      <a href="shop.php?category=<?php echo (int)$subCat['id']; ?>">
                        <?= htmlspecialchars($subCat['name']) ?>
                      </a>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
            </li>
            <?php if (isLoggedIn()): ?>
            <li><a href="backoffice-client/dashboard.php">Mon Compte</a></li>
            <li><a href="logout.php">Se Déconnecter</a></li>
            <?php else: ?>
            <li><a href="account.php">Connexion / Inscription</a></li>
            <?php endif; ?>
            <li><a href="contact.php">Contact</a></li>
          </ul>
        </nav>
        <!-- side-mobile-menu end -->
      </aside>
      <div class="body-overlay"></div>
      <!-- slide-bar end -->

      <main>
        <!-- hero start -->
        <div class="hero hero__height ul_li" data-background="assets/img/bg/hero_bg.jpg">
          <div class="container">
            <div class="row align-items-center mt-none-30">
              <div class="col-lg-9 mt-30">
                <div class="row align-items-center flex-row-reverse mt-none-30">
                  <div class="col-lg-7 mt-30">
                    <div class="hero__product">
                      <div class="hero__product-wrap">
                        <!-- Carousel principal : images des produits vedettes -->
                        <div class="hero__product-carousel">
                          <?php if (!empty($hero_products)): ?>
                            <?php foreach ($hero_products as $hp):
                                $hp_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$hp['id']-1)%177)+1)));
                                $hp_img = !empty($hp['image']) ? 'uploads/products/' . htmlspecialchars($hp['image']) : $hp_fb;
                            ?>
                            <div class="hero__product-item">
                              <img loading="lazy" src="<?php echo $hp_img; ?>" onerror="this.src='<?php echo $hp_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($hp['name']); ?>">
                            </div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="hero__product-item"><img loading="lazy" src="assets/img/product/img_52.png" alt=""></div>
                            <div class="hero__product-item"><img loading="lazy" src="assets/img/product/img_53.png" alt=""></div>
                          <?php endif; ?>
                        </div>
                        <!-- Nav miniatures -->
                        <div class="hero__product-carousel-nav">
                          <?php foreach ($hero_products as $hp):
                              $hp_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$hp['id']-1)%177)+1)));
                              $hp_img = !empty($hp['image']) ? 'uploads/products/' . htmlspecialchars($hp['image']) : $hp_fb;
                          ?>
                          <div class="hero__product-item-nav">
                            <div class="image"><img loading="lazy" src="<?php echo $hp_img; ?>" onerror="this.src='<?php echo $hp_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($hp['name']); ?>"></div>
                          </div>
                          <?php endforeach; ?>
                        </div>
                        <?php $first_hero = $hero_products[0] ?? null; ?>
                        <?php if ($first_hero && !empty($first_hero['discount_percent'])): ?>
                        <span class="hero__product-offer">
                          <span class="discount"><?php echo (int)$first_hero['discount_percent']; ?><span>%</span></span>
                          <span>off</span>
                        </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <!-- Hero content : premier produit vedette -->
                  <div class="col-lg-5 mt-30">
                    <?php if ($first_hero): ?>
                    <div class="hero__content">
                      <span class="subtitle">100% Produit Certifié</span>
                      <h2 class="title"><?php echo htmlspecialchars(mb_substr($first_hero['name'], 0, 40)); ?></h2>
                      <p><?php echo htmlspecialchars($first_hero['short_description'] ?? 'Qualité professionnelle garantie'); ?></p>
                      <h3 class="price">
                        <?= fmt_price((float)$first_hero['price']) ?>
                        <?php if (!empty($first_hero['old_price'])): ?>
                        / <span><?= fmt_price((float)$first_hero['old_price']) ?></span>
                        <?php endif; ?>
                      </h3>
                      <div class="mxw_343 mb-20">
                        <div class="product__progress progress h-16 color-primary">
                          <?php $stock_pct = $first_hero['stock'] > 0 ? min(100, ($first_hero['stock'] / 50) * 100) : 10; ?>
                          <div class="progress-bar" role="progressbar" style="width: <?php echo $stock_pct; ?>%" aria-valuenow="<?php echo $stock_pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="ul_li_between mb-6">
                          <span class="product__available">En stock : <span><?php echo (int)$first_hero['stock']; ?></span></span>
                        </div>
                      </div>
                      <a class="hero__btn" href="shop-single.php?id=<?php echo (int)$first_hero['id']; ?>">
                        Voir le produit <i class="far fa-long-arrow-right"></i>
                      </a>
                    </div>
                    <?php else: ?>
                    <div class="hero__content">
                      <span class="subtitle">Technologie &amp; Innovation</span>
                      <h2 class="title">AtlanTech<br>Haïti</h2>
                      <p>Votre spécialiste High-Tech aux Cayes</p>
                      <a class="hero__btn" href="shop.php">Voir la boutique <i class="far fa-long-arrow-right"></i></a>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <!-- Sidebar hero : Top Produits -->
              <div class="col-lg-3 col-md-6 mt-30">
                <div class="hot-deal__slide-wrap style-2 bg-white">
                  <h2 class="section-heading mb-25"><span>Top Produits</span></h2>
                  <div class="hot-deal__slide tx-arrow">
                    <?php foreach (array_slice($products_bestseller, 0, 5) as $p):
                        $p_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$p['id']-1)%177)+1)));
                        $p_img = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                        $p_url = 'shop-single.php?id=' . (int)$p['id'];
                        $has_old = !empty($p['old_price']) && $p['old_price'] > $p['price'];
                    ?>
                    <div class="hot-deal__item text-center">
                      <div class="thumb">
                        <a href="<?php echo $p_url; ?>"><img loading="lazy" src="<?php echo $p_img; ?>" onerror="this.src='<?php echo $p_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                      </div>
                      <div class="content">
                        <ul class="rating-star ul_li_center mr-10"><?php echo stars((float)$p['rating']); ?></ul>
                        <h2 class="title mb-15"><a href="<?php echo $p_url; ?>"><?php echo htmlspecialchars(mb_substr($p['name'], 0, 45)); ?></a></h2>
                        <h4 class="product__price mb-20">
                          <span class="new"><?= fmt_price((float)$p['price']) ?></span>
                          <?php if ($has_old): ?><span class="old"><?= fmt_price((float)$p['old_price']) ?></span><?php endif; ?>
                        </h4>
                        <div class="mxw_216 m-auto">
                          <div class="product__progress progress mb-6 h-8 color-primary">
                            <?php $sold_pct = min(100, ($p['sold_count'] / 50) * 100); ?>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $sold_pct; ?>%" aria-valuenow="<?php echo $sold_pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                          <div class="ul_li_between">
                            <span class="product__available">Vendus : <span><?php echo (int)$p['sold_count']; ?></span></span>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($products_bestseller)): ?>
                    <div class="hot-deal__item text-center" style="padding:30px;">
                      <p style="color:#999;">Produits bientôt disponibles.</p>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- hero end -->

        <!-- feature start -->
        <div class="feature pt-40 pb-30">
          <div class="container">
            <div class="feature__wrap ul_li">
              <div class="feature__item ul_li">
                <div class="icon">
                  <img loading="lazy" src="assets/img/icon/feat_01.svg" alt="" />
                </div>
                <div class="content">
                  <h3>Free Shipping</h3>
                  <p>Free shipping over $100</p>
                </div>
              </div>
              <div class="feature__item ul_li">
                <div class="icon">
                  <img loading="lazy" src="assets/img/icon/feat_02.svg" alt="" />
                </div>
                <div class="content">
                  <h3>Payment Secure</h3>
                  <p>Got 100% Payment Safe</p>
                </div>
              </div>
              <div class="feature__item ul_li">
                <div class="icon">
                  <img loading="lazy" src="assets/img/icon/feat_03.svg" alt="" />
                </div>
                <div class="content">
                  <h3>Support 24/7</h3>
                  <p>Top quialty 24/7 Support</p>
                </div>
              </div>
              <div class="feature__item ul_li">
                <div class="icon">
                  <img loading="lazy" src="assets/img/icon/feat_04.svg" alt="" />
                </div>
                <div class="content">
                  <h3>100% Money Back</h3>
                  <p>Cutomers Money Backs</p>
                </div>
              </div>
              <div class="feature__item ul_li">
                <div class="icon">
                  <img loading="lazy" src="assets/img/icon/feat_05.svg" alt="" />
                </div>
                <div class="content">
                  <h3>Quality Products</h3>
                  <p>We Insure Product Quailty</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- feature end -->

        <!-- tab product start -->
        <div class="tab-product pt-40 pb-40">
          <div class="container">
            <div class="product__nav-wrap ul_li_between mb-20">
              <h2 class="section-heading">
                <span>Hot <span>New Arrival</span> You May Like</span>
              </h2>
              <ul
                class="product__nav rd-tab-nav nav nav-tabs"
                id="vd-myTab"
                role="tablist"
              >
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link"
                    id="vd-tab-01"
                    data-bs-toggle="tab"
                    data-bs-target="#vd-tab1"
                    type="button"
                    role="tab"
                    aria-controls="vd-tab1"
                    aria-selected="true"
                  >
                    Recent
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link"
                    id="vd-tab-02"
                    data-bs-toggle="tab"
                    data-bs-target="#vd-tab2"
                    type="button"
                    role="tab"
                    aria-controls="vd-tab2"
                    aria-selected="false"
                  >
                    Best Seller
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link"
                    id="vd-tab-03"
                    data-bs-toggle="tab"
                    data-bs-target="#vd-tab3"
                    type="button"
                    role="tab"
                    aria-controls="vd-tab3"
                    aria-selected="false"
                  >
                    Top
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link active"
                    id="vd-tab-04"
                    data-bs-toggle="tab"
                    data-bs-target="#vd-tab4"
                    type="button"
                    role="tab"
                    aria-controls="vd-tab4"
                    aria-selected="false"
                  >
                    New Arrivals
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link"
                    id="vd-tab-05"
                    data-bs-toggle="tab"
                    data-bs-target="#vd-tab5"
                    type="button"
                    role="tab"
                    aria-controls="vd-tab5"
                    aria-selected="false"
                  >
                    top rating
                  </button>
                </li>
              </ul>
            </div>
            <div class="vd-products">
              <div class="tab-content tab_has_slider" id="vd-myTabContent">

                <?php
                $tabs = [
                    ['id' => 'vd-tab1', 'label' => 'vd-tab-01', 'products' => $products_recent,     'active' => false],
                    ['id' => 'vd-tab2', 'label' => 'vd-tab-02', 'products' => $products_bestseller, 'active' => false],
                    ['id' => 'vd-tab3', 'label' => 'vd-tab-03', 'products' => $products_top,        'active' => false],
                    ['id' => 'vd-tab4', 'label' => 'vd-tab-04', 'products' => $products_new,        'active' => true],
                    ['id' => 'vd-tab5', 'label' => 'vd-tab-05', 'products' => $products_toprated,   'active' => false],
                ];
                foreach ($tabs as $tab):
                    $active_class = $tab['active'] ? 'show active' : '';
                ?>
                <div class="tab-pane fade <?php echo $active_class; ?>"
                     id="<?php echo $tab['id']; ?>"
                     role="tabpanel"
                     aria-labelledby="<?php echo $tab['label']; ?>">
                  <div class="tab-product__slide tx-arrow">
                    <?php if (empty($tab['products'])): ?>
                      <div class="tab-product__item tx-product text-center" style="padding:30px;">
                        <p style="color:#999;">Aucun produit disponible dans cette catégorie.</p>
                      </div>
                    <?php else: ?>
                      <?php foreach ($tab['products'] as $p): echo product_card_tab($p); endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>

              </div>
            </div>
          </div>
        </div>
        <!-- tab product end -->

        <!-- rd slide product start 
                      ================================ Categories ======================================== -->
        <div class="rd-slide-product">
          <div class="container">
            <div class="row mt-none-30">
              <div class="col-lg-12 mt-30">
                <div class="rd-slide-products">
                  <h2 class="section-heading mb-25">
                    <span>Produits Tendance</span>
                  </h2>
                  <div class="rd-product__slide tx-arrow">
                    <?php if (!empty($products_trending)): ?>
                      <?php foreach (array_chunk($products_trending, 2) as $pair): ?>
                      <div class="rd-product__slide-item">
                        <div class="atl-trend-pair">
                        <?php foreach ($pair as $p):
                            $p_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$p['id']-1)%177)+1)));
                            $p_img = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                            $p_url = 'shop-single.php?id=' . (int)$p['id'];
                            $has_old = !empty($p['old_price']) && $p['old_price'] > $p['price'];
                        ?>
                          <div class="product__item">
                          <div class="product__img text-center pos-rel">
                            <a href="<?php echo $p_url; ?>"><img loading="lazy" src="<?php echo $p_img; ?>" onerror="this.src='<?php echo $p_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                          </div>
                          <div class="product__content">
                            <div class="product__review ul_li_center">
                              <ul class="rating-star ul_li mr-10"><?php echo stars((float)$p['rating']); ?></ul>
                              <span>(<?php echo (int)$p['sold_count']; ?> vendus)</span>
                            </div>
                            <h2 class="product__title">
                              <a href="<?php echo $p_url; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                            </h2>
                            <h4 class="product__price">
                              <span class="new"><?= fmt_price((float)$p['price']) ?></span>
                              <?php if ($has_old): ?><span class="old"><?= fmt_price((float)$p['old_price']) ?></span><?php endif; ?>
                            </h4>
                            <?php if ($has_old && $p['old_price']>0): ?><span class="atl-disc-badge">-<?= round((1-$p['price']/$p['old_price'])*100) ?>%</span><?php endif; ?>
                          </div>
                          <ul class="product__action">
                            <li><a href="#!" title="Aperçu"><i class="far fa-compress-alt"></i></a></li>
                            <li><a href="cart.php?add=<?php echo (int)$p['id']; ?>"><i class="far fa-shopping-basket"></i></a></li>
                            <li><a href="wishlist.php?add=<?php echo (int)$p['id']; ?>"><i class="far fa-heart"></i></a></li>
                          </ul>
                          <a href="cart.php?add=<?php echo (int)$p['id']; ?>" class="product-card-btn">
                            <i class="far fa-shopping-basket"></i> Ajouter au panier
                          </a>
                          <?php if ($p['is_new']): ?><span class="badge-skew">Nouveau</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <div class="rd-product__slide-item" style="padding:30px; text-align:center;">
                        <p style="color:#999;">Produits bientôt disponibles.</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- rd slide product end -->

        <!-- rd tab product start -->
        <div class="rd-tab-product mt-60">
          <div class="container">
            <div class="product__nav-wrap ul_li_between mb-25">
              <h2 class="section-heading">
                <span><span>Produits</span> par Marque</span>
              </h2>
              <ul class="product__nav nav nav-tabs" id="brandTab" role="tablist">
                <?php foreach (array_slice($brands, 0, 5) as $bidx => $brand): ?>
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link <?php echo $bidx === 0 ? 'active' : ''; ?>"
                    id="brand-tab-<?php echo (int)$brand['id']; ?>"
                    data-bs-toggle="tab"
                    data-bs-target="#brand-pane-<?php echo (int)$brand['id']; ?>"
                    type="button" role="tab">
                    <?php echo htmlspecialchars($brand['name']); ?>
                  </button>
                </li>
                <?php endforeach; ?>
                <?php if (empty($brands)): ?>
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#brand-pane-all">Tous</button></li>
                <?php endif; ?>
              </ul>
            </div>
            <div class="tab-content" id="brandTabContent">
              <?php foreach (array_slice($brands, 0, 5) as $bidx => $brand):
                  $bid = (int)$brand['id'];
                  $b_products = $products_by_brand[$bid] ?? [];
                  // fallback : si aucun produit pour cette marque, utiliser les trending
                  if (empty($b_products)) $b_products = array_slice($products_trending, 0, 8);
              ?>
              <div class="tab-pane fade <?php echo $bidx === 0 ? 'show active' : ''; ?>"
                   id="brand-pane-<?php echo $bid; ?>"
                   role="tabpanel">
                <div class="row mt-none-30">
                  <?php foreach ($b_products as $p):
                      $p_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$p['id']-1)%177)+1)));
                      $p_img = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                      $p_url = 'shop-single.php?id=' . (int)$p['id'];
                      $has_old = !empty($p['old_price']) && $p['old_price'] > $p['price'];
                  ?>
                  <div class="col-lg-3 col-md-6 mt-30">
                    <div class="product__item2 tx-product">
                      <div class="product__img text-center pos-rel">
                        <a href="<?php echo $p_url; ?>">
                          <img loading="lazy" src="<?php echo $p_img; ?>" onerror="this.src='<?php echo $p_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($p['name']); ?>">
                        </a>
                        <ul class="product__action">
                          <li><a href="#!" title="Aperçu"><i class="far fa-compress-alt"></i></a></li>
                          <li><a href="cart.php?add=<?php echo (int)$p['id']; ?>"><i class="far fa-shopping-basket"></i></a></li>
                          <li><a href="wishlist.php?add=<?php echo (int)$p['id']; ?>"><i class="far fa-heart"></i></a></li>
                        </ul>
                        <?php if ($p['is_new']): ?><span class="badge-skew">Nouveau</span><?php endif; ?>
                      </div>
                      <div class="product__content">
                        <div class="product__review ul_li">
                          <ul class="rating-star ul_li mr-10"><?php echo stars((float)$p['rating']); ?></ul>
                          <span>(<?php echo (int)$p['sold_count']; ?>)</span>
                        </div>
                        <h2 class="product__title">
                          <a href="<?php echo $p_url; ?>"><?php echo htmlspecialchars(mb_substr($p['name'], 0, 50)); ?></a>
                        </h2>
                        <h4 class="product__price">
                          <span class="new"><?= fmt_price((float)$p['price']) ?></span>
                          <?php if ($has_old): ?><span class="old"><?= fmt_price((float)$p['old_price']) ?></span><?php endif; ?>
                        </h4>
                        <a href="cart.php?add=<?php echo (int)$p['id']; ?>" class="product-card-btn">
                          <i class="far fa-shopping-basket"></i> Ajouter au panier
                        </a>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                  <?php if (empty($b_products)): ?>
                  <div class="col-12 text-center py-4"><p style="color:#999;">Aucun produit disponible pour cette marque.</p></div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if (empty($brands)): ?>
              <div class="tab-pane fade show active" id="brand-pane-all">
                <div class="row mt-none-30">
                  <?php foreach (array_slice($products_trending, 0, 8) as $p):
                      $p_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$p['id']-1)%177)+1)));
                      $p_img = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                      $p_url = 'shop-single.php?id=' . (int)$p['id'];
                  ?>
                  <div class="col-lg-3 col-md-6 mt-30">
                    <div class="product__item2 tx-product">
                      <div class="product__img text-center pos-rel">
                        <a href="<?php echo $p_url; ?>"><img loading="lazy" src="<?php echo $p_img; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                      </div>
                      <div class="product__content">
                        <h2 class="product__title"><a href="<?php echo $p_url; ?>"><?php echo htmlspecialchars($p['name']); ?></a></h2>
                        <h4 class="product__price"><span class="new"><?= fmt_price((float)$p['price']) ?></span></h4>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <!-- rd tab product end -->

        <!-- add start -->
        <div class="add pt-50 pb-50">
          <div class="container">
            <div
              class="add__wrap bg_img"
              data-background="assets/img/bg/add_bg_01.jpg"
            >
              <div class="add__text">
                <span
                  ><span>10%</span> Free Shipping On All Order Over
                  <span>$99</span></span
                >
              </div>
            </div>
          </div>
        </div>
        <!-- add end -->

        <!-- featured product start -->
        <div class="atl-vedettes">
          <div class="container">
            <div class="atl-vhdr">
              <h2>Produits Vedettes</h2>
              <a href="shop.php">Voir tout ›</a>
            </div>
            <?php if (!empty($products_featured)): ?>
            <div class="atl-vgrid">
              <?php foreach ($products_featured as $p):
                  $p_fb    = sprintf('assets/img/product/img_%02d.png', ((((int)$p['id']-1)%177)+1));
                  $img     = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                  $url     = 'shop-single.php?id=' . (int)$p['id'];
                  $has_old = !empty($p['old_price']) && $p['old_price'] > $p['price'];
              ?>
              <div class="atl-vcard">
                <a href="<?php echo $url; ?>" class="atl-vcard-img">
                  <img loading="lazy" src="<?php echo $img; ?>" onerror="this.src='<?php echo $p_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($p['name']); ?>">
                </a>
                <div class="atl-vcard-stars">
                  <ul class="rating-star"><?php echo stars((float)$p['rating']); ?></ul>
                  <span class="atl-rcount">(<?php echo (int)$p['sold_count']; ?>)</span>
                </div>
                <a href="<?php echo $url; ?>" class="atl-vcard-name"><?php echo htmlspecialchars(mb_substr($p['name'], 0, 55)); ?></a>
                <div class="atl-vcard-price"><?= fmt_price((float)$p['price']) ?></div>
                <?php if ($has_old): ?>
                <div class="atl-vcard-old"><?= fmt_price((float)$p['old_price']) ?></div>
                <?php endif; ?>
                <a href="cart.php?add=<?php echo (int)$p['id']; ?>" class="atl-vcard-btn">
                  <i class="far fa-shopping-basket"></i> Ajouter
                </a>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#999;text-align:center;padding:40px 0;">Aucun produit vedette disponible pour le moment.</p>
            <?php endif; ?>
          </div>
        </div>
        <!-- featured product end -->

        <!-- product category start -->
        <div class="product-cat pt-60">
          <div class="container">
            <h2 class="section-heading mb-25">
              <span>Catégories de Produits</span>
            </h2>
            <div class="product-cat__wrap">
              <div class="row mt-none-50">
                <?php
                $cat_list = array_slice($rootCategories, 0, 6);
                $cat_fb  = 'assets/img/bg/cat_bg.jpg';            // fallback ultime (existe sur disque)
                foreach ($cat_list as $ci => $cat):
                    $cid = (int)$cat['id'];
                    $cat_prods = $products_by_cat[$cid] ?? [];
                    // Image de la catégorie : si fichier défini → uploads/categories/, sinon fallback
                    $cat_img_raw = !empty($cat['image']) ? 'uploads/categories/' . htmlspecialchars($cat['image']) : $cat_fb;
                    $cat_img_abs = __DIR__ . '/' . $cat_img_raw;
                    // Si le fichier n'existe pas physiquement → on prend directement le fallback
                    $cat_img = is_file($cat_img_abs) ? $cat_img_raw : $cat_fb;
                    $col_class = ($ci < 2) ? 'col-lg-4' : 'col-lg-4';
                ?>
                <div class="<?php echo $col_class; ?> mt-50">
                  <div class="product-cat__item">
                    <!-- Images des produits de la catégorie (tabs) -->
                    <div class="product-cat__images">
                      <div class="tab-content" id="cat-tab-<?php echo $cid; ?>">
                        <?php if (!empty($cat_prods)): ?>
                          <?php foreach ($cat_prods as $pi => $p):
                              $p_fb = sprintf('assets/img/product/img_%02d.png', (((((int)$p['id']-1)%177)+1)));
                              $p_img = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                              $p_url = 'shop-single.php?id=' . (int)$p['id'];
                          ?>
                          <div class="tab-pane fade <?php echo $pi === 0 ? 'show active' : ''; ?>"
                               id="cat-<?php echo $cid; ?>-prod-<?php echo (int)$p['id']; ?>"
                               role="tabpanel">
                            <div class="product-cat__img">
                              <a href="<?php echo $p_url; ?>">
                                <img loading="lazy" src="<?php echo $p_img; ?>" onerror="this.src='<?php echo $p_fb; ?>'; this.onerror=null;" alt="<?php echo htmlspecialchars($p['name']); ?>">
                              </a>
                            </div>
                          </div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <div class="tab-pane fade show active" id="cat-<?php echo $cid; ?>-empty">
                            <div class="product-cat__img product-cat__img--empty"
                                 style="position:relative; background:linear-gradient(135deg,#fff8f0 0%,#ffeacc 100%);
                                        border-radius:8px; height:200px; display:flex; flex-direction:column;
                                        align-items:center; justify-content:center; color:#ff9100; text-align:center;">
                              <img loading="lazy" src="<?php echo $cat_img; ?>"
                                   onerror="this.style.display='none';"
                                   alt="<?php echo htmlspecialchars($cat['name']); ?>"
                                   style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;
                                          border-radius:8px; opacity:.35;">
                              <i class="fas fa-box-open" style="position:relative; font-size:2.5rem; margin-bottom:8px;"></i>
                              <span style="position:relative; font-size:.85rem; font-weight:600; color:#555;">
                                Produits bientôt disponibles
                              </span>
                            </div>
                          </div>
                        <?php endif; ?>
                      </div>
                      <!-- Tab nav miniatures -->
                      <?php if (count($cat_prods) > 1): ?>
                      <ul class="product-cat__nav nav nav-tabs" id="catTab-<?php echo $cid; ?>" role="tablist">
                        <?php foreach ($cat_prods as $pi => $p): ?>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link <?php echo $pi === 0 ? 'active' : ''; ?>"
                                  data-bs-toggle="tab"
                                  data-bs-target="#cat-<?php echo $cid; ?>-prod-<?php echo (int)$p['id']; ?>"
                                  type="button">
                            <?php
                            $p_fb  = sprintf('assets/img/product/img_%02d.png', ((((int)$p['id']-1)%177)+1));
                            $thumb = !empty($p['image']) ? 'uploads/products/' . htmlspecialchars($p['image']) : $p_fb;
                            ?>
                            <img loading="lazy" src="<?php echo $thumb; ?>" onerror="this.src='<?php echo $p_fb; ?>'; this.onerror=null;" alt="">
                          </button>
                        </li>
                        <?php endforeach; ?>
                      </ul>
                      <?php endif; ?>
                    </div>
                    <!-- Infos catégorie + liste produits -->
                    <div class="product-cat__content">
                      <h3 class="title">
                        <a href="shop.php?category=<?php echo $cid; ?>">
                          <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                      </h3>
                      <ul>
                        <?php foreach ($cat_prods as $p):
                            $p_url = 'shop-single.php?id=' . (int)$p['id'];
                        ?>
                        <li>
                          <a href="<?php echo $p_url; ?>"><?php echo htmlspecialchars(mb_substr($p['name'], 0, 40)); ?></a>
                          <span><?= fmt_price((float)$p['price']) ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($cat_prods)): ?>
                        <li><span style="color:#999;">Aucun produit disponible</span></li>
                        <?php endif; ?>
                      </ul>
                      <a class="product-cat__link" href="shop.php?category=<?php echo $cid; ?>">
                        Voir tout <i class="far fa-long-arrow-right"></i>
                      </a>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($cat_list)): ?>
                <div class="col-12 text-center py-5">
                  <p style="color:#999;">Catégories bientôt disponibles.</p>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <!-- product category end -->

        <!-- recent product start -->
        <div class="recent-product pt-60 pb-80">
          <div class="container">
            <div class="row mt-none-30">
              <div class="col-lg-3 col-md-6 mt-30">
                <div class="add-banner add-banner__3 bg_img add-banner__h555" data-background="assets/img/bg/bg_01.jpg">
                  <div class="add-banner__content">
                    <span>Technologie &amp; Innovation</span>
                    <h3>AtlanTech<br>Haïti</h3>
                    <span class="price">Depuis Les Cayes</span>
                    <a class="add-banner__btn" href="shop.php">Voir la boutique</a>
                  </div>
                  <div class="add-banner__img">
                    <img loading="lazy" src="assets/img/product/img_48.png" alt="">
                  </div>
                </div>
              </div>
              <div class="col-lg-9 mt-30">
                <div class="product__nav-wrap style-2 ul_li_between">
                  <h2 class="section-heading"><span>Produits Récents</span></h2>
                </div>
                <div class="tab-content" id="vdr-myTabContent">
                  <div class="tab-pane animated fadeInUp show active" id="vdr-tab1" role="tabpanel" aria-labelledby="vdr-tab-01">
                    <div class="row justify-content-md-center">
                      <?php foreach ($products_recent_section as $p): echo product_card_recent($p); endforeach; ?>
                      <?php if (empty($products_recent_section)): ?>
                        <div class="col-12 text-center py-5"><p style="color:#999;">Aucun produit récent disponible.</p></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- recent product end -->

        <!-- banner start -->
        <div class="vd-banner">
          <div class="container">
            <div class="row mt-none-30">
              <?php
              // Banner principal (position home, col-7)
              $b1 = $banners[0] ?? null;
              $b2 = $banners[1] ?? null;
              ?>
              <div class="col-lg-7 mt-30">
                <div class="vd-banner__single pos-rel ul_li_between bg_img"
                     data-background="<?php echo $b1 && $b1['image'] ? 'uploads/banners/' . htmlspecialchars($b1['image']) : 'assets/img/bg/bg_02.jpg'; ?>">
                  <div class="content">
                    <h2><?php echo htmlspecialchars($b1['title'] ?? 'Livraison gratuite sur toutes commandes'); ?></h2>
                    <p><?php echo htmlspecialchars($b1['subtitle'] ?? 'Pour toute commande supérieure à 5 000 HTG'); ?></p>
                    <div class="banner__btn mt-20">
                      <a class="thm-btn thm-btn__black" href="<?php echo htmlspecialchars($b1['link'] ?? 'shop.php'); ?>">
                        <span class="btn-wrap"><span>Voir la boutique</span><span>Voir la boutique</span></span>
                        <i class="far fa-long-arrow-right"></i>
                      </a>
                    </div>
                  </div>
                  <div class="thumb">
                    <img loading="lazy" src="assets/img/product/img_49.png" alt="">
                  </div>
                </div>
              </div>
              <div class="col-lg-5 mt-30">
                <div class="vd-banner__single vd-banner__single-two pos-rel ul_li_between bg_img"
                     data-background="<?php echo $b2 && $b2['image'] ? 'uploads/banners/' . htmlspecialchars($b2['image']) : 'assets/img/bg/bg_03.jpg'; ?>">
                  <div class="content">
                    <h2><?php echo htmlspecialchars($b2['title'] ?? 'Produits certifiés'); ?></h2>
                    <p><?php echo htmlspecialchars($b2['subtitle'] ?? 'Technologie de qualité professionnelle'); ?></p>
                    <div class="banner__btn mt-20">
                      <a class="thm-btn thm-btn__black thm-btn__md text-lowercase" href="<?php echo htmlspecialchars($b2['link'] ?? 'shop.php'); ?>">
                        <span class="btn-wrap"><span>Voir maintenant</span><span>Voir maintenant</span></span>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- banner end -->

        <!-- rd category start -->
        <div class="rd-category pt-60">
          <div class="container">
            <div class="row mt-none-30">
              <!-- Bannière promo -->
              <?php $b3 = $banners[2] ?? null; ?>
              <div class="col-lg-6 col-md-12 mt-30">
                <div class="rd-banner ul_li"
                     data-background="<?php echo $b3 && $b3['image'] ? 'uploads/banners/' . htmlspecialchars($b3['image']) : 'assets/img/bg/bg_05.jpg'; ?>">
                  <div class="rd-banner__content">
                    <span>AtlanTech — Technologie &amp; Innovation</span>
                    <h3><?php echo htmlspecialchars($b3['title'] ?? 'Meilleurs prix garantis'); ?></h3>
                    <p><?php echo htmlspecialchars($b3['subtitle'] ?? 'Sur toute notre gamme de produits'); ?></p>
                    <div class="banner__btn mt-40">
                      <a class="thm-btn thm-btn__red" href="<?php echo htmlspecialchars($b3['link'] ?? 'shop.php'); ?>">
                        <span class="btn-wrap"><span>Voir les offres</span><span>Voir les offres</span></span>
                        <i class="far fa-long-arrow-right"></i>
                      </a>
                    </div>
                  </div>
                  <div class="rd-banner__img">
                    <img loading="lazy" src="assets/img/product/img_51.png" alt="">
                  </div>
                </div>
              </div>
              <!-- 2 colonnes de catégories dynamiques -->
              <?php foreach (array_slice($rootCategories, 0, 2) as $rdCat): ?>
              <div class="col-lg-3 col-md-6 mt-30">
                <div class="rd-category__wrap">
                  <h2 class="section-heading mb-25">
                    <span><?php echo htmlspecialchars($rdCat['name']); ?></span>
                  </h2>
                  <ul class="rd-category__list list-unstyled" data-background="assets/img/bg/bg_04.jpg">
                    <?php
                    $sub = $categoriesByParent[$rdCat['id']] ?? [];
                    if (!empty($sub)):
                        foreach (array_slice($sub, 0, 6) as $sc):
                    ?>
                    <li><a href="shop.php?category=<?php echo (int)$sc['id']; ?>"><?php echo htmlspecialchars($sc['name']); ?></a></li>
                    <?php endforeach; else: ?>
                    <li><a href="shop.php?category=<?php echo (int)$rdCat['id']; ?>">Voir tous les produits</a></li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <!-- rd category end -->

        <!-- brand start -->
        <div class="brand pt-80 pb-80">
          <div class="container">
            <div class="brand__active">
              <?php if (!empty($brands)): ?>
                <?php foreach ($brands as $brand): ?>
                <div class="brand__item">
                  <a href="shop.php?brand=<?php echo (int)$brand['id']; ?>">
                    <?php if (!empty($brand['logo'])): ?>
                      <img loading="lazy" src="uploads/brands/<?php echo htmlspecialchars($brand['logo']); ?>" alt="<?php echo htmlspecialchars($brand['name']); ?>">
                    <?php else: ?>
                      <span style="font-weight:700; font-size:1.1rem; color:#333;"><?php echo htmlspecialchars($brand['name']); ?></span>
                    <?php endif; ?>
                  </a>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
                <?php for ($bi = 1; $bi <= 6; $bi++): ?>
                <div class="brand__item"><a href="#!"><img loading="lazy" src="assets/img/brand/img_0<?php echo $bi; ?>.png" alt=""></a></div>
                <?php endfor; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <!-- brand end -->
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
                  <?php foreach (array_slice($rootCategories, 0, 7) as $cat): ?>
                  <li><a href="shop.php?category=<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
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
                  <li><a href="shop.php">Boutique</a></li>
                </ul>
              </div>
              <div class="footer__widget col-lg-3 col-md-6 mt-40">
                <h2 class="title">Service client</h2>
                <ul class="category">
                  <li><a href="contact.php">Centre d'aide</a></li>
                  <li><a href="#">Conditions d'utilisation</a></li>
                  <li><a href="#">Livraison &amp; Expédition</a></li>
                  <li><a href="#">Politique de confidentialité</a></li>
                  <li><a href="#">Retours &amp; Remboursements</a></li>
                  <li><a href="about.php">À propos</a></li>
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
              <a href="https://instagram.com/atlantech.service" target="_blank">fab fa-instagram"></i></a>
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
            <div class="img-holder"><img loading="lazy" src="assets/img/bg/newsletter.jpg" alt=""></div>
            <div class="details">
              <h4>Obtenez 45% de réduction livré dans votre boîte mail</h4>
              <p>Abonnez-vous à la newsletter AtlanTech pour recevoir les meilleures offres sur vos produits préférés.</p>
              <form>
                <div>
                  <input type="email" placeholder="Entrez votre email">
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
      <!-- end newsletter-popup -->

      <!-- cookies-area -->
      <div class="cookies-area">
        <p>Ce site utilise des cookies pour améliorer votre expérience. En utilisant ce site, vous acceptez notre <a href="#">Politique de confidentialité</a>.</p>
        <a href="#" class="read-more">En savoir plus</a>
        <div><button class="cookie-btn">Accepter</button></div>
      </div>
    </div>

    <!-- JS bundle (15 fichiers → 1 requête) -->
    <script src="assets/js/bundle.min.js"></script>

    <!-- Fix: recalcul de la position du slider Slick quand on change d'onglet -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof jQuery === 'undefined') return;
      jQuery('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = jQuery(e.target).attr('data-bs-target');
        if (!target) return;
        jQuery(target).find('.tab-product__slide, .rd-tab-product__slide, .tab-product__slide2').each(function () {
          var $s = jQuery(this);
          if ($s.hasClass('slick-initialized')) {
            $s.slick('setPosition');
          }
        });
      });
    });
    </script>
    <script>
    /* Produits Tendance — réinit Slick 2 produits/slide */
    $(function(){
      setTimeout(function(){
        var $s=$('.rd-product__slide');
        if($s.hasClass('slick-initialized'))$s.slick('destroy');
        $s.slick({
          infinite:true,speed:400,slidesToShow:2,slidesToScroll:1,
          autoplay:true,autoplaySpeed:3500,dots:false,arrows:true,
          prevArrow:'<i class="slick-arrow slick-prev far fa-angle-left"></i>',
          nextArrow:'<i class="slick-arrow slick-next far fa-angle-right"></i>',
          responsive:[
            {breakpoint:1025,settings:{slidesToShow:2,slidesToScroll:1}},
            {breakpoint:769,settings:{slidesToShow:1,slidesToScroll:1,arrows:true,dots:false}},
            {breakpoint:480,settings:{slidesToShow:1,slidesToScroll:1,arrows:true,dots:false}}
          ]
        });
      },150);
    });
    </script>

  </body>
</html>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           