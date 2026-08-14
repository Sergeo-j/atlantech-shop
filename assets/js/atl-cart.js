/**
 * AtlanTech — Panier & Wishlist AJAX
 * Ajoute au panier / wishlist sans quitter la page.
 */
(function () {
  'use strict';

  // ── URL de base ─────────────────────────────────────────────────────
  // Retire le fichier courant pour obtenir la racine du site
  // Ex: http://localhost/atlantech-shop/shop.php → http://localhost/atlantech-shop/
  var BASE         = window.location.href.replace(/\/[^\/]*(\?.*)?$/, '/');
  var CART_API     = BASE + 'api/cart-add.php';
  var WISHLIST_API = BASE + 'api/wishlist-add.php';

  // ── Toast notification ──────────────────────────────────────────────
  var toastContainer = null;

  function getContainer() {
    if (!toastContainer) {
      var style = document.createElement('style');
      style.textContent = [
        '#atl-toasts{position:fixed;top:80px;right:16px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none}',
        '.atl-toast{background:#1a1a2e;color:#fff;padding:13px 18px;border-radius:8px;font-size:14px;font-weight:500;',
        'box-shadow:0 4px 18px rgba(0,0,0,.35);display:flex;align-items:center;gap:10px;min-width:240px;max-width:340px;',
        'border-left:4px solid #e87c1e;opacity:0;transform:translateX(20px);transition:opacity .22s,transform .22s;pointer-events:auto}',
        '.atl-toast.show{opacity:1;transform:translateX(0)}',
        '.atl-toast.err{border-left-color:#e04040}',
        '.atl-toast.wish{border-left-color:#e04e6e}',
        '.atl-t-close{margin-left:auto;background:none;border:none;color:#fff;font-size:15px;cursor:pointer;opacity:.7;padding:0 0 0 8px;line-height:1}'
      ].join('');
      document.head.appendChild(style);

      toastContainer = document.createElement('div');
      toastContainer.id = 'atl-toasts';
      document.body.appendChild(toastContainer);
    }
    return toastContainer;
  }

  function showToast(msg, type) {
    // type: 'cart' | 'wish' | 'err'
    var c = getContainer();
    var t = document.createElement('div');
    var icon = type === 'wish' ? '♥' : (type === 'err' ? '✗' : '🛒');
    t.className = 'atl-toast' + (type === 'err' ? ' err' : '') + (type === 'wish' ? ' wish' : '');
    t.innerHTML = '<span>' + icon + '</span><span>' + msg + '</span>'
      + '<button class="atl-t-close" aria-label="fermer">✕</button>';
    c.appendChild(t);

    requestAnimationFrame(function() {
      requestAnimationFrame(function() { t.classList.add('show'); });
    });

    t.querySelector('.atl-t-close').onclick = function() { dismiss(t); };
    setTimeout(function() { dismiss(t); }, 3500);
  }

  function dismiss(t) {
    t.classList.remove('show');
    setTimeout(function() { if (t.parentNode) t.parentNode.removeChild(t); }, 250);
  }

  // ── Mise à jour des badges ──────────────────────────────────────────
  function updateCartBadge(n) {
    document.querySelectorAll('.cart_btn .count, .cart-count-badge').forEach(function(el) {
      animateBadge(el, n);
    });
  }

  function updateWishlistBadge(n) {
    document.querySelectorAll('.wishlist-icon .count, .wishlist-count-badge').forEach(function(el) {
      animateBadge(el, n);
    });
  }

  function animateBadge(el, n) {
    el.textContent = n;
    el.style.transition = 'transform 0.2s';
    el.style.transform = 'scale(1.5)';
    setTimeout(function() { el.style.transform = ''; }, 200);
  }

  // ── Appel AJAX générique ────────────────────────────────────────────
  function ajaxPost(url, params, onSuccess, onError) {
    var body = Object.keys(params).map(function(k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
    }).join('&');

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
      credentials: 'same-origin'
    })
    .then(function(r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function(data) {
      if (data.success) {
        onSuccess(data);
      } else {
        onError(data.message || 'Erreur inconnue');
      }
    })
    .catch(function(err) {
      console.error('atl-ajax:', err, url);
      onError('Erreur réseau — réessayez');
    });
  }

  // ── PANIER ──────────────────────────────────────────────────────────

  function addToCart(params, btn) {
    if (btn) btn.disabled = true;
    ajaxPost(
      CART_API,
      params,
      function(data) {
        updateCartBadge(data.cart_count);
        showToast('✓ ' + data.message, 'cart');
        if (btn) btn.disabled = false;
      },
      function(msg) {
        showToast(msg, 'err');
        if (btn) btn.disabled = false;
      }
    );
  }

  // Liens <a href="cart.php?add=ID">
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    var m = href.match(/cart\.php\?add=(\d+)/);
    if (!m) return;

    e.preventDefault();
    var params = { product_id: m[1], qty: 1, action: 'add' };
    var qm = href.match(/[&?]qty=(\d+)/);
    if (qm) params.qty = qm[1];
    addToCart(params, null);
  });

  // Formulaires POST <form action="cart.php">
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form.action || form.action.indexOf('cart.php') === -1) return;
    var actionField = form.querySelector('input[name="action"]');
    if (!actionField || actionField.value !== 'add') return;

    e.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    var params = {
      action:     'add',
      product_id: (form.querySelector('[name="product_id"]') || {}).value || '',
      qty:        (form.querySelector('[name="qty"]')        || {}).value || '1',
      color_id:   (form.querySelector('[name="color_id"]')   || {}).value || '',
      color_name: (form.querySelector('[name="color_name"]') || {}).value || ''
    };
    addToCart(params, btn);
  });

  // ── WISHLIST ─────────────────────────────────────────────────────────

  function toggleWishlist(pid, el) {
    if (el) el.style.pointerEvents = 'none';
    ajaxPost(
      WISHLIST_API,
      { product_id: pid },
      function(data) {
        updateWishlistBadge(data.wishlist_count);
        showToast(data.message, 'wish');
        // Feedback visuel sur le bouton/lien cliqué
        if (el) {
          el.classList.toggle('active', data.action === 'added');
          el.style.pointerEvents = '';
        }
      },
      function(msg) {
        showToast(msg, 'err');
        if (el) el.style.pointerEvents = '';
      }
    );
  }

  // Liens <a href="wishlist.php?add=ID">
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    var m = href.match(/wishlist\.php\?(?:add|toggle)=(\d+)/);
    if (!m) return;

    e.preventDefault();
    toggleWishlist(m[1], link);
  });

})();
