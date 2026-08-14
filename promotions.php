<?php
/**
 * Promotions en cours — AtlanTech
 * Liste publique des codes promo actifs.
 */
require_once 'config/config.php';
require_once 'includes/header_counters.php';

// ─── Charger les codes actifs ────────────────────────────────────────────
$codes = [];
$today = date('Y-m-d');
try {
    $st = $mysqli->prepare("
        SELECT code, description, discount_percent, valid_from, valid_until
        FROM promo_codes
        WHERE is_active = 1
          AND (valid_from  IS NULL OR valid_from  <= ?)
          AND (valid_until IS NULL OR valid_until >= ?)
        ORDER BY discount_percent DESC, valid_until ASC
    ");
    if ($st) {
        $st->bind_param('ss', $today, $today);
        $st->execute();
        $res = $st->get_result();
        while ($res && $row = $res->fetch_assoc()) $codes[] = $row;
        $st->close();
    }
} catch (\Throwable $e) {
    error_log('promotions page: ' . $e->getMessage());
}

// Pour le header counters
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions en cours — AtlanTech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime(__DIR__.'/assets/css/mobile.css'); ?>" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f9fafb;
            color: #111827;
            min-height: 100vh;
            line-height: 1.5;
        }

        /* ── Header simple ── */
        .promo-header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .promo-header .container {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .promo-header .brand {
            font-size: 1.5rem; font-weight: 800;
            color: #6d28d9; text-decoration: none;
        }
        .promo-header .brand i { color: #f59e0b; margin-right: 6px; }
        .promo-header .nav { display: flex; gap: 18px; align-items: center; font-size: 0.92rem; }
        .promo-header .nav a {
            color: #4b5563; text-decoration: none;
            padding: 8px 14px; border-radius: 8px; font-weight: 500;
        }
        .promo-header .nav a:hover { background: #f3f4f6; color: #6d28d9; }
        .promo-header .nav .cart-link {
            background: #6d28d9; color: #fff; position: relative;
        }
        .promo-header .nav .cart-link:hover { background: #4c1d95; color: #fff; }
        .cart-badge {
            background: #f59e0b; color: #fff;
            border-radius: 12px; padding: 1px 8px;
            font-size: 0.7rem; font-weight: 700;
            margin-left: 6px;
        }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(135deg, #6d28d9 0%, #8b5cf6 50%, #f59e0b 100%);
            color: #fff;
            padding: 60px 24px;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.5rem; font-weight: 800;
            margin-bottom: 12px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .hero p {
            font-size: 1.1rem; opacity: 0.95;
            max-width: 600px; margin: 0 auto;
        }
        @media (max-width: 640px) { .hero h1 { font-size: 1.8rem; } }

        /* ── Grid de cards ── */
        .promo-container {
            max-width: 1200px; margin: -40px auto 60px;
            padding: 0 24px; position: relative; z-index: 2;
        }
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .promo-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex; flex-direction: column;
        }
        .promo-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(109, 40, 217, 0.15);
        }

        .promo-card .card-head {
            background: linear-gradient(135deg, #6d28d9, #f59e0b);
            color: #fff;
            padding: 26px 22px;
            text-align: center;
            position: relative;
        }
        .promo-card .discount {
            font-size: 3rem; font-weight: 800;
            line-height: 1; margin-bottom: 4px;
            text-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .promo-card .discount small { font-size: 1.3rem; font-weight: 700; }
        .promo-card .label-off { font-size: 0.85rem; opacity: 0.9; letter-spacing: 1px; text-transform: uppercase; font-weight: 600; }

        .promo-card .card-body { padding: 22px; flex: 1; display: flex; flex-direction: column; }
        .promo-card .desc {
            color: #4b5563;
            font-size: 0.95rem;
            margin-bottom: 18px;
            min-height: 40px;
        }

        .code-box {
            display: flex; align-items: center; gap: 10px;
            background: #faf5ff;
            border: 2px dashed #c4b5fd;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .code-box .code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 1.15rem;
            color: #4c1d95;
            letter-spacing: 1px;
            flex: 1;
            text-align: center;
        }
        .copy-btn {
            background: #6d28d9; color: #fff;
            border: none; border-radius: 8px;
            padding: 8px 14px; cursor: pointer;
            font-weight: 600; font-size: 0.85rem;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .copy-btn:hover { background: #4c1d95; }
        .copy-btn.copied { background: #10b981; }

        .validity {
            font-size: 0.82rem;
            color: #6b7280;
            text-align: center;
            margin-bottom: 14px;
        }
        .validity i { color: #f59e0b; margin-right: 4px; }

        .cta {
            display: block; text-align: center;
            background: #f59e0b; color: #fff;
            text-decoration: none; font-weight: 700;
            padding: 12px; border-radius: 10px;
            transition: background 0.15s;
            margin-top: auto;
        }
        .cta:hover { background: #d97706; color: #fff; }

        /* État vide */
        .empty-state {
            background: #fff;
            border-radius: 16px;
            padding: 60px 30px;
            text-align: center;
            color: #6b7280;
            border: 2px dashed #e5e7eb;
        }
        .empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 16px; display: block; }

        /* Footer */
        .promo-footer {
            background: #1f2937;
            color: #d1d5db;
            text-align: center;
            padding: 30px 24px;
            font-size: 0.9rem;
        }
        .promo-footer a { color: #fbbf24; text-decoration: none; }

        /* Astuce */
        .tip {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 14px 18px;
            border-radius: 0 10px 10px 0;
            margin-bottom: 30px;
            color: #78350f;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/promo_banner.php'; ?>

    <!-- Header simple -->
    <header class="promo-header">
        <div class="container">
            <a href="index.php" class="brand">
                <i class="fas fa-bolt"></i> AtlanTech
            </a>
            <nav class="nav">
                <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
                <a href="shop.php"><i class="fas fa-store"></i> Boutique</a>
                <a href="promotions.php" style="color:#6d28d9;font-weight:700"><i class="fas fa-tag"></i> Promotions</a>
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i> Panier
                    <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?= (int)$cart_count ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </header>

    <!-- Hero -->
    <section class="hero">
        <h1>🎉 Promotions en cours</h1>
        <p>Profitez de nos meilleurs codes promo actifs. Copiez-collez au moment du paiement et économisez sur votre commande.</p>
    </section>

    <!-- Cards -->
    <main class="promo-container">
        <?php if (empty($codes)): ?>
            <div class="empty-state">
                <i class="fas fa-tags"></i>
                <h2 style="color:#374151;margin-bottom:10px">Aucune promotion en cours</h2>
                <p>Revenez bientôt — de nouveaux codes apparaîtront très vite !</p>
                <a href="shop.php" style="display:inline-block;margin-top:20px;background:#6d28d9;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600">
                    <i class="fas fa-store"></i> Aller à la boutique
                </a>
            </div>
        <?php else: ?>
            <div class="tip">
                <i class="fas fa-lightbulb"></i>
                <strong>Comment ça marche ?</strong> Copiez le code, ajoutez vos produits au panier puis collez le code dans la case « Code promo » au moment du paiement. La réduction s'applique automatiquement.
            </div>

            <div class="promo-grid">
                <?php foreach ($codes as $c):
                    $code     = htmlspecialchars($c['code']);
                    $desc     = htmlspecialchars($c['description'] ?? '');
                    $percent  = (float)$c['discount_percent'];
                    $valid_to = $c['valid_until'];
                ?>
                <article class="promo-card">
                    <div class="card-head">
                        <div class="discount">–<?= rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',') ?><small>%</small></div>
                        <div class="label-off">de réduction</div>
                    </div>
                    <div class="card-body">
                        <p class="desc"><?= $desc ?: 'Offre exclusive AtlanTech' ?></p>

                        <div class="code-box">
                            <span class="code"><?= $code ?></span>
                            <button class="copy-btn" type="button"
                                onclick="copyPromoCode(this, '<?= addslashes($code) ?>')">
                                <i class="fas fa-copy"></i> Copier
                            </button>
                        </div>

                        <?php if ($valid_to): ?>
                        <div class="validity">
                            <i class="far fa-calendar-alt"></i>
                            Valable jusqu'au <strong><?= htmlspecialchars(date('d/m/Y', strtotime($valid_to))) ?></strong>
                        </div>
                        <?php else: ?>
                        <div class="validity">
                            <i class="far fa-infinity"></i>
                            <strong>Sans date limite</strong>
                        </div>
                        <?php endif; ?>

                        <a href="shop.php" class="cta">
                            <i class="fas fa-shopping-bag"></i> J'en profite
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="promo-footer">
        <p>© <?= date('Y') ?> AtlanTech — Spécialiste High-Tech Haïti · <a href="index.php">Retour à l'accueil</a></p>
    </footer>

    <script>
        function copyPromoCode(btn, code) {
            navigator.clipboard.writeText(code).then(function() {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copier';
                }, 2000);
            }).catch(function() {
                // Fallback : sélectionner le texte
                var input = document.createElement('input');
                input.value = code;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                setTimeout(function() {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copier';
                }, 2000);
            });
        }
    </script>
</body>
</html>
