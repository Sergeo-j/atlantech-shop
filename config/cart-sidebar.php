<?php
/**
 * Cart Sidebar dynamique — AtlanTech
 * À inclure dans toutes les pages publiques (require_once 'config/config.php' doit déjà être fait)
 * Utilise $mysqli (disponible via config.php)
 */

$_cs_items = [];
$_cs_total = 0.0;
$_cs_count = 0;

if (!empty($_SESSION['cart'])) {
    $_cs_ids = array_map('intval', array_keys($_SESSION['cart']));
    if ($_cs_ids) {
        $_cs_ph   = implode(',', array_fill(0, count($_cs_ids), '?'));
        $_cs_type = str_repeat('i', count($_cs_ids));
        $_cs_stmt = $mysqli->prepare(
            "SELECT id, name, price, old_price, image FROM products WHERE id IN ($_cs_ph) AND is_active = 1"
        );
        $_cs_stmt->bind_param($_cs_type, ...$_cs_ids);
        $_cs_stmt->execute();
        $_cs_rows = $_cs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $_cs_stmt->close();
        foreach ($_cs_rows as $_cs_p) {
            $_cs_qty   = (int)$_SESSION['cart'][$_cs_p['id']];
            $_cs_unit  = (float)$_cs_p['price'];
            $_cs_items[] = array_merge($_cs_p, ['qty' => $_cs_qty, 'unit_price' => $_cs_unit]);
            $_cs_total  += $_cs_unit * $_cs_qty;
            $_cs_count  += $_cs_qty;
        }
    }
}

// Rendre disponibles comme variables standards sur la page
$cart_count = $_cs_count;
$cart_total = $_cs_total;
$cart_items = $_cs_items;
?>
<!-- sidebar-info start -->
<div class="cart_sidebar">
    <button type="button" class="cart_close_btn"><i class="fal fa-times"></i></button>
    <h2 class="heading_title text-uppercase">Mon Panier — <span><?php echo $_cs_count; ?></span></h2>
    <div class="cart_items_list">
        <?php if (empty($_cs_items)): ?>
            <p style="padding:20px 15px;color:#999;font-size:14px;">Votre panier est vide.</p>
        <?php else: foreach ($_cs_items as $_ci): ?>
        <div class="cart_item">
            <div class="item_image">
                <img loading="lazy" src="uploads/products/<?php echo htmlspecialchars($_ci['image'] ?? ''); ?>"
                     alt="<?php echo htmlspecialchars($_ci['name']); ?>"
                     onerror="this.src='assets/img/product/placeholder.png';this.onerror=null;">
            </div>
            <div class="item_content">
                <h4 class="item_title"><?php echo htmlspecialchars($_ci['name']); ?></h4>
                <span class="item_price">
                    <?= fmt_price($_ci['unit_price']) ?>
                    <?php if ($_ci['qty'] > 1): ?><small>× <?php echo $_ci['qty']; ?></small><?php endif; ?>
                </span>
                <a href="cart.php?remove=<?php echo (int)$_ci['id']; ?>" class="remove_btn" title="Retirer">
                    <i class="fal fa-times"></i>
                </a>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <div class="total_price text-uppercase">
        <span>Total :</span>
        <span><?= fmt_price($_cs_total) ?></span>
    </div>
    <ul class="btns_group ul_li">
        <li>
            <a href="cart.php" class="thm-btn">
                <span class="btn-wrap"><span>Voir le panier</span><span>Voir le panier</span></span>
            </a>
        </li>
        <li>
            <a href="checkout.php" class="thm-btn thm-btn__black">
                <span class="btn-wrap"><span>Commander</span><span>Commander</span></span>
            </a>
        </li>
    </ul>
</div>
<!-- sidebar-info end -->
