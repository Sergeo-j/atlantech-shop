<?php
/**
 * Wishlist — AtlanTech E-commerce
 */
require_once 'config/config.php';

// Initialiser la wishlist si elle n'existe pas encore
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Pages sûres pour le retour après action (fallback sans JS)
$_atl_safe = ['index', 'shop', 'shop-single', 'wishlist', 'promotions'];
function _atl_wish_back(): string {
    global $_atl_safe;
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref) {
        $page = strtolower(basename(parse_url($ref, PHP_URL_PATH), '.php'));
        if (in_array($page, $_atl_safe, true)) return $ref;
    }
    return 'index.php';
}

// Ajouter un produit (fallback sans JS)
if (isset($_GET['add'])) {
    $product_id = intval($_GET['add']);
    if (!in_array($product_id, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = $product_id;
    }
    redirect(_atl_wish_back());
}

// Supprimer un produit
if (isset($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    $_SESSION['wishlist'] = array_values(array_diff($_SESSION['wishlist'], [$product_id]));
    redirect('wishlist.php');
}

// Vider la wishlist
if (isset($_GET['clear'])) {
    unset($_SESSION['wishlist']);
    redirect('wishlist.php');
}

// Charger les produits depuis la base de données
$wishlist_items = [];
if (!empty($_SESSION['wishlist'])) {
    $ids = implode(',', array_map('intval', $_SESSION['wishlist']));
    try {
        $result = $mysqli->query("
            SELECT p.*,
                   (SELECT image FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_img
            FROM products p
            WHERE p.id IN ($ids) AND p.is_active = 1
        ");
        while ($row = $result->fetch_assoc()) {
            // Priorité à l'image primaire de la galerie
            if (!empty($row['primary_img'])) {
                $row['image'] = $row['primary_img'];
            }
            $wishlist_items[] = $row;
        }
    } catch (Exception $e) {
        $wishlist_items = [];
    }
}

$page_title = 'Ma Wishlist';
?>
<?php include 'includes/header.php'; ?>

<main>
  <!-- breadcrumb start -->
  <section class="breadcrumb-area" data-background="assets/img/bg/breadcrumb_bg.jpg">
    <div class="container">
      <div class="breadcrumb-text text-center">
        <h2 class="breadcrumb-title">Ma Wishlist</h2>
        <ul class="breadcrumb-menu ul_li_center">
          <li><a href="index.php">Accueil</a></li>
          <li>Wishlist</li>
        </ul>
      </div>
    </div>
  </section>
  <!-- breadcrumb end -->

  <!-- wishlist section start -->
  <section class="wishlist-area pt-100 pb-100">
    <div class="container">

      <?php if (empty($wishlist_items)): ?>
        <!-- Wishlist vide -->
        <div class="text-center py-5">
          <div style="font-size: 80px; margin-bottom: 20px;">💔</div>
          <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 10px;">Votre wishlist est vide</h3>
          <p style="color: #777; margin-bottom: 30px;">Ajoutez des produits à votre liste de souhaits pour les retrouver facilement.</p>
          <a href="shop.php" class="thm-btn">
            <span class="btn-wrap">
              <span>Découvrir la boutique</span>
              <span>Découvrir la boutique</span>
            </span>
          </a>
        </div>

      <?php else: ?>

        <!-- En-tête -->
        <div class="ul_li_between mb-40" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px;">
          <h2 style="font-size:22px; font-weight:700;">
            <i class="fas fa-heart" style="color:#ff5c5c; margin-right:8px;"></i>
            Ma Wishlist <span style="font-size:16px; color:#777; font-weight:400;">(<?= count($wishlist_items) ?> article<?= count($wishlist_items) > 1 ? 's' : '' ?>)</span>
          </h2>
          <a href="wishlist.php?clear=1" onclick="return confirm('Vider toute la wishlist ?')"
             style="display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border:1px solid #ddd; border-radius:4px; color:#777; font-size:14px; text-decoration:none; transition:0.2s;"
             onmouseover="this.style.borderColor='#ff5c5c';this.style.color='#ff5c5c';"
             onmouseout="this.style.borderColor='#ddd';this.style.color='#777';">
            <i class="fas fa-trash-alt"></i> Tout vider
          </a>
        </div>

        <!-- Table produits -->
        <div class="table-responsive">
          <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr style="border-bottom:2px solid #eee;">
                <th style="padding:15px 10px; text-align:left; font-size:13px; text-transform:uppercase; color:#777; font-weight:600; width:60px;">Photo</th>
                <th style="padding:15px 10px; text-align:left; font-size:13px; text-transform:uppercase; color:#777; font-weight:600;">Produit</th>
                <th style="padding:15px 10px; text-align:center; font-size:13px; text-transform:uppercase; color:#777; font-weight:600;">Prix</th>
                <th style="padding:15px 10px; text-align:center; font-size:13px; text-transform:uppercase; color:#777; font-weight:600;">Stock</th>
                <th style="padding:15px 10px; text-align:center; font-size:13px; text-transform:uppercase; color:#777; font-weight:600;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($wishlist_items as $item):
                $img_path = !empty($item['image']) ? 'uploads/products/' . e($item['image']) : 'assets/img/product/img_01.png';
                $in_stock = (int)($item['stock'] ?? 0) > 0;
                $has_discount = !empty($item['old_price']) && (float)$item['old_price'] > (float)$item['price'];
                $discount_pct = $has_discount ? round((1 - $item['price'] / $item['old_price']) * 100) : 0;
              ?>
              <tr style="border-bottom:1px solid #f0f0f0; transition:background 0.2s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                <!-- Image -->
                <td style="padding:15px 10px;">
                  <a href="shop-single.php?id=<?= (int)$item['id'] ?>">
                    <img loading="lazy" src="<?= $img_path ?>" alt="<?= e($item['name']) ?>"
                         onerror="this.src='assets/img/product/img_01.png'"
                         style="width:70px; height:70px; object-fit:contain; border-radius:6px; border:1px solid #eee;" />
                  </a>
                </td>
                <!-- Nom -->
                <td style="padding:15px 10px;">
                  <a href="shop-single.php?id=<?= (int)$item['id'] ?>"
                     style="font-size:15px; font-weight:600; color:#222; text-decoration:none; display:block; margin-bottom:4px;"
                     onmouseover="this.style.color='var(--theme-color, #ff9100)'" onmouseout="this.style.color='#222'">
                    <?= e($item['name']) ?>
                  </a>
                  <?php if (!empty($item['sku'])): ?>
                  <span style="font-size:12px; color:#999;">SKU : <?= e($item['sku']) ?></span>
                  <?php endif; ?>
                  <?php if ($has_discount): ?>
                  <span style="display:inline-block; margin-top:4px; background:#ff5c5c; color:#fff; font-size:11px; padding:2px 7px; border-radius:3px; font-weight:700;">-<?= $discount_pct ?>%</span>
                  <?php endif; ?>
                </td>
                <!-- Prix -->
                <td style="padding:15px 10px; text-align:center;">
                  <span style="font-size:16px; font-weight:700; color:#222;"><?= fmt_price((float)$item['price']) ?></span>
                  <?php if ($has_discount): ?>
                  <br><span style="font-size:13px; color:#999; text-decoration:line-through;"><?= fmt_price((float)$item['old_price']) ?></span>
                  <?php endif; ?>
                </td>
                <!-- Stock -->
                <td style="padding:15px 10px; text-align:center;">
                  <?php if ($in_stock): ?>
                    <span style="display:inline-block; padding:4px 12px; background:#e8f5e9; color:#2e7d32; border-radius:20px; font-size:12px; font-weight:600;">
                      <i class="fas fa-check-circle"></i> En stock
                    </span>
                  <?php else: ?>
                    <span style="display:inline-block; padding:4px 12px; background:#fce4ec; color:#c62828; border-radius:20px; font-size:12px; font-weight:600;">
                      <i class="fas fa-times-circle"></i> Rupture
                    </span>
                  <?php endif; ?>
                </td>
                <!-- Actions -->
                <td style="padding:15px 10px; text-align:center;">
                  <div style="display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap;">
                    <?php if ($in_stock): ?>
                    <a href="cart.php?add=<?= (int)$item['id'] ?>" class="thm-btn" style="padding:8px 16px; font-size:13px;">
                      <span class="btn-wrap" style="font-size:13px;">
                        <span><i class="fas fa-shopping-cart"></i> Panier</span>
                        <span><i class="fas fa-shopping-cart"></i> Panier</span>
                      </span>
                    </a>
                    <?php endif; ?>
                    <a href="wishlist.php?remove=<?= (int)$item['id'] ?>"
                       title="Retirer de la wishlist"
                       style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border:1px solid #ddd; border-radius:4px; color:#999; text-decoration:none; transition:0.2s;"
                       onmouseover="this.style.borderColor='#ff5c5c';this.style.color='#ff5c5c';"
                       onmouseout="this.style.borderColor='#ddd';this.style.color='#999';">
                      <i class="fas fa-times"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Bouton continuer les achats -->
        <div style="margin-top:40px; text-align:center;">
          <a href="shop.php" style="display:inline-flex; align-items:center; gap:8px; padding:12px 30px; border:2px solid #222; border-radius:4px; color:#222; font-weight:600; text-