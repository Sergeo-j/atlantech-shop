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
                  <?php foreach (array_slice($rootCategories ?? [], 0, 7) as $cat): ?>
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

      <!-- cookies-area -->
      <div class="cookies-area">
        <p>Ce site utilise des cookies pour améliorer votre expérience. En utilisant ce site, vous acceptez notre <a href="#">Politique de confidentialité</a>.</p>
        <a href="#" class="read-more">En savoir plus</a>
        <div><button class="cookie-btn">Accepter</button></div>
      </div>

    </div><!-- /.body_wrap -->

    <!-- JS bundle (15 fichiers → 1 requête) -->
    <script src="assets/js/bundle.min.js"></script>
  </body>
</html>
