<?php
/**
 * Vos Listes - AtlanTech E-commerce
 * Liste de souhaits (DB) + liste de courses (session)
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=lists');

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$success = ''; $errors = [];

// Actions : ajouter/retirer de la wishlist DB
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token invalide.';
    } else {
        $action     = $_POST['action']     ?? '';
        $product_id = (int)($_POST['product_id'] ?? 0);

        if ($action === 'remove_wishlist' && $product_id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Produit retiré de votre liste.';
        } elseif ($action === 'move_to_cart' && $product_id > 0) {
            // Ajouter au panier session
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;
            // Retirer de la wishlist
            $stmt = $mysqli->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param('ii', $user_id, $product_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Produit déplacé vers votre panier.';
        }
    }
}

// ── Wishlist depuis la BD ──
$stmt = $mysqli->prepare(
    "SELECT w.product_id, p.name, p.price, p.image, p.stock, p.is_active
     FROM wishlist w
     JOIN products p ON p.id = w.product_id
     WHERE w.user_id = ?
     ORDER BY w.id DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$wishlist_db = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Panier session ──
$cart_items = [];
if (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $mysqli->prepare("SELECT id, name, price, image FROM products WHERE id IN ($ph) AND is_active = 1");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($products as $p) {
            $qty = (int)($_SESSION['cart'][$p['id']] ?? 1);
            $cart_items[] = array_merge($p, ['qty' => $qty, 'subtotal' => $p['price'] * $qty]);
        }
    }
}
$cart_total = array_sum(array_column($cart_items, 'subtotal'));

$tab = $_GET['tab'] ?? 'wishlist'; // wishlist | cart
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Vos Listes - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:960px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

        .tabs{display:flex;gap:0;border-bottom:2px solid #D5D9D9;margin-bottom:28px;}
        .tab-btn{padding:12px 22px;font-size:15px;font-weight:600;color:#565959;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;}
        .tab-btn.active{color:#0F1111;border-bottom-color:#e77600;}
        .tab-btn:hover{color:#0F1111;}

        /* Grid produits */
        .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;}
        .product-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;overflow:hidden;transition:box-shadow .2s;}
        .product-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1);}
        .product-card img{width:100%;height:180px;object-fit:contain;padding:12px;background:#fafafa;}
        .product-info{padding:14px;}
        .product-name{font-size:14px;font-weight:700;color:#0F1111;margin-bottom:6px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .product-price{font-size:18px;font-weight:800;color:#B12704;margin-bottom:10px;}
        .stock-ok{font-size:11px;color:#10b981;margin-bottom:10px;}
        .stock-no{font-size:11px;color:#ef4444;margin-bottom:10px;}
        .btn-cart{width:100%;padding:9px;background:#FFD814;border:1px solid #FFA41C;border-radius:6px;font-size:13px;font-weight:700;color:#0F1111;cursor:pointer;margin-bottom:6px;}
        .btn-cart:hover{background:#F7CA00;}
        .btn-remove{width:100%;padding:6px;background:#fff;border:1px solid #D5D9D9;border-radius:6px;font-size:12px;color:#ef4444;cursor:pointer;}
        .btn-remove:hover{background:#fee2e2;}

        /* Liste du panier */
        .cart-list{background:#fff;border:1px solid #D5D9D9;border-radius:8px;overflow:hidden;margin-bottom:20px;}
        .cart-row{display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #F0F2F2;}
        .cart-row:last-child{border-bottom:none;}
        .cart-row img{width:60px;height:60px;object-fit:contain;border-radius:4px;background:#fafafa;flex-shrink:0;}
        .cart-row-info{flex:1;}
        .cart-name{font-size:14px;font-weight:700;color:#0F1111;}
        .cart-price{font-size:13px;color:#565959;}
        .cart-subtotal{font-size:15px;font-weight:800;color:#B12704;white-space:nowrap;}
        .cart-qty-wrap{display:flex;align-items:center;gap:6px;margin-top:4px;}
        .qty-btn{width:26px;height:26px;border:1px solid #D5D9D9;border-radius:4px;background:#F0F2F2;font-size:14px;cursor:pointer;line-height:1;}
        .qty-display{font-size:13px;font-weight:700;min-width:20px;text-align:center;}
        .cart-footer{padding:16px 18px;background:#fafafa;border-top:1px solid #E7E7E7;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
        .total-label{font-size:16px;font-weight:700;}
        .btn-checkout{padding:11px 28px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:14px;font-weight:700;color:#0F1111;text-decoration:none;}
        .btn-checkout:hover{background:#F7CA00;}

        .empty-state{text-align:center;padding:50px 20px;color:#666;background:#fff;border:1px solid #D5D9D9;border-radius:8px;}
        .empty-state .icon{font-size:56px;display:block;margin-bottom:14px;}
        .empty-state p{margin-bottom:16px;font-size:15px;}
        .btn-shop{display:inline-block;padding:11px 24px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-weight:700;color:#0F1111;text-decoration:none;}
    </style>
</head>
<body>
<div style="background:#131921;padding:12px 20px;display:flex;align-items:center;gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff;font-size:13px;text-decoration:none;"><i class="fas fa-user-circle"></i>&nbsp;Mon compte</a>
</div>

<div class="wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Vos Listes</span>
    </nav>
    <h1 class="page-title">Vos Listes</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

    <div class="tabs">
        <a href="lists.php?tab=wishlist" class="tab-btn <?php echo $tab==='wishlist'?'active':''; ?>">
            ❤️ Liste de souhaits (<?php echo count($wishlist_db); ?>)
        </a>
        <a href="lists.php?tab=cart" class="tab-btn <?php echo $tab==='cart'?'active':''; ?>">
            🛒 Panier (<?php echo count($cart_items); ?>)
        </a>
    </div>

    <?php if ($tab === 'wishlist'): ?>

        <?php if (empty($wishlist_db)): ?>
            <div class="empty-state">
                <span class="icon">❤️</span>
                <p>Votre liste de souhaits est vide.</p>
                <p style="font-size:13px;color:#888;">Ajoutez des produits en cliquant sur le cœur sur les pages produits.</p>
                <a href="../shop.php" class="btn-shop">Découvrir nos produits</a>
            </div>
        <?php else: ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <span style="font-size:14px;color:#555;"><?php echo count($wishlist_db); ?> article<?php echo count($wishlist_db)>1?'s':''; ?></span>
                <a href="../shop.php" style="color:#007185;font-size:13px;">+ Continuer mes achats</a>
            </div>
            <div class="products-grid">
                <?php foreach ($wishlist_db as $p): ?>
                    <div class="product-card">
                        <a href="../shop-single.php?id=<?php echo $p['product_id']; ?>">
                            <img src="../uploads/products/<?php echo htmlspecialchars($p['image'] ?? ''); ?>"
                                 alt="<?php echo htmlspecialchars($p['name']); ?>"
                                 onerror="this.src='../assets/img/product/placeholder.png'">
                        </a>
                        <div class="product-info">
                            <div class="product-name">
                                <a href="../shop-single.php?id=<?php echo $p['product_id']; ?>" style="color:#0F1111;text-decoration:none;">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </a>
                            </div>
                            <div class="product-price"><?php echo number_format($p['price'], 2); ?> HTG</div>
                            <?php if ($p['is_active'] && $p['stock'] > 0): ?>
                                <div class="stock-ok">✓ En stock</div>
                            <?php else: ?>
                                <div class="stock-no">✗ Rupture de stock</div>
                            <?php endif; ?>

                            <?php if ($p['is_active'] && $p['stock'] > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action"      value="move_to_cart">
                                <input type="hidden" name="product_id"  value="<?php echo $p['product_id']; ?>">
                                <button type="submit" class="btn-cart">🛒 Ajouter au panier</button>
                            </form>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action"      value="remove_wishlist">
                                <input type="hidden" name="product_id"  value="<?php echo $p['product_id']; ?>">
                                <button type="submit" class="btn-remove">✕ Retirer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: // cart ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-state">
                <span class="icon">🛒</span>
                <p>Votre panier est vide.</p>
                <a href="../shop.php" class="btn-shop">Découvrir nos produits</a>
            </div>
        <?php else: ?>
            <div class="cart-list">
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-row">
                        <img src="../uploads/products/<?php echo htmlspecialchars($item['image'] ?? ''); ?>"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.src='../assets/img/product/placeholder.png'">
                        <div class="cart-row-info">
                            <div class="cart-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="cart-price"><?php echo number_format($item['price'], 2); ?> HTG / unité</div>
                            <div class="cart-qty-wrap">
                                <a href="../cart.php?update=<?php echo $item['id']; ?>&qty=<?php echo max(1,$item['qty']-1); ?>" class="qty-btn">−</a>
                                <span class="qty-display"><?php echo $item['qty']; ?></span>
                                <a href="../cart.php?update=<?php echo $item['id']; ?>&qty=<?php echo $item['qty']+1; ?>" class="qty-btn">+</a>
                                <a href="../cart.php?remove=<?php echo $item['id']; ?>" style="margin-left:8px;font-size:12px;color:#ef4444;">Retirer</a>
                            </div>
                        </div>
                        <div class="cart-subtotal"><?php echo number_format($item['subtotal'], 2); ?> HTG</div>
                    </div>
                <?php endforeach; ?>
                <div class="cart-footer">
                    <div class="total-label">Total : <span style="color:#B12704;"><?php echo number_format($cart_total, 2); ?> HTG</span></div>
                    <div style="display:flex;gap:10px;">
                        <a href="../cart.php" style="color:#007185;text-decoration:none;font-size:14px;">Modifier le panier</a>
                        <a href="../checkout.php" class="btn-checkout">Commander →</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
