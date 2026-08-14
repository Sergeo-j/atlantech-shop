<?php
/**
 * Header mobile v2 (style Ubuy) — affiché UNIQUEMENT si l'utilisateur est connecté.
 * Sur mobile (≤767px) : remplace la barre noire actuelle.
 * Sur PC (≥768px) : invisible, le header existant reste intact.
 * Déconnecté : ce fichier n'affiche rien, l'ancien header s'applique.
 */
if (function_exists('isLoggedIn') && isLoggedIn()):
  $mh2_cart = isset($cart_count) ? (int)$cart_count : 0;
?>
<style>
.atl-mh2{display:none}
@media(max-width:767px){
  .atl-mh2{display:block;background:#fff;border-bottom:1px solid #eee;position:relative;z-index:999}
  body.atl-mh2-on .header__cat-wrap{display:none !important}
  .atl-mh2-row1{display:flex;align-items:center;gap:8px;padding:8px 12px 4px}
  .atl-mh2-burger{display:flex;align-items:center;justify-content:center;width:36px;height:36px;flex-shrink:0;color:#111;font-size:20px;text-decoration:none}
  .atl-mh2-logo{flex-shrink:1;min-width:0;overflow:hidden;margin-right:auto}
  .atl-mh2-logo img{height:30px;width:auto;max-width:100%;display:block}
  .atl-mh2-icons{display:flex;align-items:center;gap:12px;flex-shrink:0}
  .atl-mh2-icons a{display:flex;align-items:center;gap:3px;color:#111;font-size:17px;text-decoration:none;position:relative}
  .atl-mh2-icons a span.lbl{font-size:11px;font-weight:600;color:#111}
  .atl-mh2-badge{position:absolute;top:-7px;right:-9px;background:#111;color:#fff;font-size:9px;font-weight:700;min-width:15px;height:15px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 3px}
  .atl-mh2-row2{padding:6px 12px 10px}
  .atl-mh2-search{display:flex;align-items:center;background:#fff;border:1px solid #e5e5e5;border-radius:24px;box-shadow:0 1px 6px rgba(0,0,0,.08);overflow:hidden;height:42px;padding-left:14px}
  .atl-mh2-search input{border:none;outline:none;background:transparent;flex:1;min-width:0;font-size:13px;color:#333;height:100%}
  .atl-mh2-search input::placeholder{color:#aaa}
  .atl-mh2-search button{width:38px;height:34px;border:none;border-radius:18px;background:#f97316;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;cursor:pointer;flex-shrink:0;margin-right:4px}
  /* Shop : masquer la recherche de la toolbar (doublon avec celle du header) */
  body.atl-mh2-on .shop-toolbar-row .shop-search-wrap{display:none !important}
  body.atl-mh2-on .shop-toolbar-row .shop-sort-wrap{margin-left:auto !important}
}
</style>
<div class="atl-mh2">
  <div class="atl-mh2-row1">
    <a href="javascript:void(0);" class="atl-mh2-burger" onclick="var h=document.querySelector('.hamburger_menu a'); if(h){h.click();}"><i class="fal fa-bars"></i></a>
    <a href="index.php" class="atl-mh2-logo"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
    <div class="atl-mh2-icons">
      <a href="backoffice-client/orders.php" title="Suivre ma commande"><i class="far fa-truck"></i><span class="lbl">HT</span></a>
      <a href="#!" title="Français"><i class="far fa-globe"></i><span class="lbl">FR</span></a>
      <a href="backoffice-client/dashboard.php" title="Mon compte"><i class="far fa-user"></i></a>
      <a href="cart.php" title="Panier"><i class="far fa-shopping-cart"></i><span class="atl-mh2-badge"><?php echo $mh2_cart; ?></span></a>
    </div>
  </div>
  <div class="atl-mh2-row2">
    <form action="shop.php" method="get" class="atl-mh2-search">
      <input type="text" name="search" placeholder="Rechercher un produit..." value="">
      <button type="submit" aria-label="Rechercher"><i class="far fa-search"></i></button>
    </form>
  </div>
</div>
<script>document.body.classList.add('atl-mh2-on');</script>
<?php endif; ?>
