<?php
/**
 * Mes codes promo — Vue client
 * AtlanTech E-commerce
 *
 * Affiche la liste des codes promo actifs et disponibles pour le client connecté.
 * Lecture seule : la validation/application se fait dans le checkout.
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=coupons');

$user_id = (int)$_SESSION['user_id'];

// Charger les codes actifs depuis la nouvelle table promo_codes
$codes = [];
$today = date('Y-m-d');
try {
    $st = $mysqli->prepare("
        SELECT id, code, description, discount_percent, valid_from, valid_until
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
    error_log('mes codes promo: ' . $e->getMessage());
}

// Combien de codes "à venir" (pour information)
$upcoming = 0;
try {
    $st2 = $mysqli->prepare("SELECT COUNT(*) FROM promo_codes WHERE is_active = 1 AND valid_from > ?");
    if ($st2) { $st2->bind_param('s', $today); $st2->execute(); $st2->bind_result($upcoming); $st2->fetch(); $st2->close(); }
} catch (\Throwable $e) {}

// Compte d'utilisations totales (mes commandes avec coupon)
$my_uses = 0;
try {
    $st3 = $mysqli->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND coupon_code IS NOT NULL AND coupon_code != ''");
    if ($st3) { $st3->bind_param('i', $user_id); $st3->execute(); $st3->bind_result($my_uses); $st3->fetch(); $st3->close(); }
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Mes codes promo - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f3f3f3; font-family: 'Inter', 'Segoe UI', sans-serif; color: #111827; }
        .topbar { background: #131921; padding: 12px 20px; display: flex; align-items: center; gap: 20px; }
        .topbar img { height: 38px; }
        .topbar a { color: #fff; text-decoration: none; font-weight: 500; }
        .topbar a:hover { color: #fbbf24; }

        .wrap { max-width: 1100px; margin: 30px auto; padding: 0 20px 80px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 18px; }
        .breadcrumb-nav a { color: #6d28d9; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }

        .page-title { font-size: 24px; font-weight: 800; color: #111827; margin-bottom: 6px; }
        .page-sub { color: #6b7280; font-size: 14px; margin-bottom: 24px; }

        /* KPI ligne */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 26px; }
        .stat { background: #fff; border-radius: 10px; padding: 16px 20px; border-left: 4px solid #6d28d9; }
        .stat .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .stat .value { font-size: 1.6rem; font-weight: 800; color: #111827; margin-top: 4px; }
        .stat.success { border-left-color: #10b981; }
        .stat.warning { border-left-color: #f59e0b; }

        /* Cards de codes */
        .codes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 18px; }
        .code-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            display: flex; flex-direction: column;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .code-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(109, 40, 217, 0.12);
        }

        .card-top {
            background: linear-gradient(135deg, #6d28d9, #f59e0b);
            color: #fff;
            padding: 22px 20px;
            text-align: center;
            position: relative;
        }
        .card-top .discount {
            font-size: 2.6rem; font-weight: 800; line-height: 1;
        }
        .card-top .discount small { font-size: 1.1rem; font-weight: 700; }
        .card-top .pct-label {
            font-size: 0.78rem; opacity: 0.9; letter-spacing: 1px;
            text-transform: uppercase; font-weight: 600; margin-top: 4px;
        }

        .card-body { padding: 18px 20px; flex: 1; display: flex; flex-direction: column; }
        .card-desc { color: #4b5563; font-size: 0.92rem; margin-bottom: 14px; min-height: 38px; }

        .code-box {
            display: flex; align-items: center; gap: 8px;
            background: #faf5ff;
            border: 2px dashed #c4b5fd;
            border-radius: 9px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .code-box .code {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 1rem; color: #4c1d95;
            letter-spacing: 1px; flex: 1; text-align: center;
        }
        .copy-btn {
            background: #6d28d9; color: #fff;
            border: none; border-radius: 7px;
            padding: 6px 12px; cursor: pointer;
            font-weight: 600; font-size: 0.82rem;
            white-space: nowrap; transition: background 0.15s;
        }
        .copy-btn:hover { background: #4c1d95; }
        .copy-btn.copied { background: #10b981; }

        .validity {
            font-size: 0.78rem; color: #6b7280;
            text-align: center; margin-bottom: 12px;
        }
        .validity i { color: #f59e0b; margin-right: 3px; }

        .card-actions {
            display: flex; gap: 8px; margin-top: auto;
        }
        .btn { flex: 1; text-align: center; padding: 9px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: all 0.15s; }
        .btn-primary { background: #6d28d9; color: #fff; }
        .btn-primary:hover { background: #4c1d95; color: #fff; }
        .btn-secondary { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        .btn-secondary:hover { background: #e5e7eb; }

        /* État vide */
        .empty {
            background: #fff;
            border-radius: 14px;
            padding: 50px 30px;
            text-align: center;
            color: #6b7280;
            border: 2px dashed #e5e7eb;
        }
        .empty i { font-size: 3rem; color: #d1d5db; margin-bottom: 14px; display: block; }
        .empty h3 { color: #374151; margin-bottom: 8px; }

        /* Astuce d'utilisation */
        .tip {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 14px 18px;
            border-radius: 0 10px 10px 0;
            margin-bottom: 24px;
            color: #78350f;
            font-size: 0.88rem;
        }
        .tip i { margin-right: 6px; color: #f59e0b; }
    </style>
</head>
<body>
    <div class="topbar">
        <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech"></a>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Mon tableau de bord</a>
        <a href="../shop.php"><i class="fas fa-store"></i> Boutique</a>
        <a href="../cart.php"><i class="fas fa-shopping-cart"></i> Mon panier</a>
        <a href="../promotions.php"><i class="fas fa-tag"></i> Toutes les promos</a>
    </div>

    <div class="wrap">
        <div class="breadcrumb-nav">
            <a href="dashboard.php">Mon compte</a>
            <span> &raquo; </span>
            <span>Mes codes promo</span>
        </div>

        <h1 class="page-title">🎁 Mes codes promo disponibles</h1>
        <p class="page-sub">
            Voici tous les codes promo actifs que vous pouvez utiliser sur votre prochaine commande.
            Copiez-les et collez-les au moment du paiement.
        </p>

        <!-- Stats -->
        <div class="stats">
            <div class="stat success">
                <div class="label">Codes disponibles</div>
                <div class="value"><?= count($codes) ?></div>
            </div>
            <?php if ($upcoming > 0): ?>
            <div class="stat warning">
                <div class="label">À venir</div>
                <div class="value"><?= (int)$upcoming ?></div>
            </div>
            <?php endif; ?>
            <div class="stat">
                <div class="label">Mes utilisations</div>
                <div class="value"><?= (int)$my_uses ?></div>
            </div>
        </div>

        <?php if (empty($codes)): ?>
            <div class="empty">
                <i class="fas fa-tags"></i>
                <h3>Aucun code disponible pour le moment</h3>
                <p>Revenez bientôt — de nouvelles offres apparaîtront !</p>
                <a href="../shop.php" class="btn btn-primary" style="display:inline-block;margin-top:18px;max-width:200px">
                    <i class="fas fa-store"></i> Voir la boutique
                </a>
            </div>
        <?php else: ?>

            <div class="tip">
                <i class="fas fa-lightbulb"></i>
                <strong>Comment utiliser un code ?</strong>
                Cliquez sur « Copier » → Ajoutez vos produits au panier → Allez au paiement → Collez le code dans le champ « Code promo ». La réduction s'applique automatiquement !
            </div>

            <div class="codes-grid">
                <?php foreach ($codes as $c):
                    $code     = htmlspecialchars($c['code']);
                    $desc     = htmlspecialchars($c['description'] ?? '');
                    $percent  = (float)$c['discount_percent'];
                    $pct_disp = rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',');
                ?>
                <article class="code-card">
                    <div class="card-top">
                        <div class="discount">–<?= $pct_disp ?><small>%</small></div>
                        <div class="pct-label">de réduction</div>
                    </div>
                    <div class="card-body">
                        <p class="card-desc"><?= $desc ?: 'Offre exclusive AtlanTech' ?></p>

                        <div class="code-box">
                            <span class="code"><?= $code ?></span>
                            <button class="copy-btn" type="button" onclick="copyCode(this, '<?= addslashes($code) ?>')">
                                <i class="fas fa-copy"></i> Copier
                            </button>
                        </div>

                        <?php if (!empty($c['valid_until'])): ?>
                        <div class="validity">
                            <i class="far fa-calendar-alt"></i>
                            Valable jusqu'au <strong><?= htmlspecialchars(date('d/m/Y', strtotime($c['valid_until']))) ?></strong>
                        </div>
                        <?php else: ?>
                        <div class="validity">
                            <i class="far fa-infinity"></i>
                            <strong>Sans date limite</strong>
                        </div>
                        <?php endif; ?>

                        <div class="card-actions">
                            <a href="../shop.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Boutique</a>
                            <a href="../cart.php" class="btn btn-secondary"><i class="fas fa-cart-arrow-down"></i> Panier</a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyCode(btn, code) {
            navigator.clipboard.writeText(code).then(function() {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copier';
                }, 2000);
            }).catch(function() {
                var i = document.createElement('input');
                i.value = code;
                document.body.appendChild(i);
                i.select();
                document.execCommand('copy');
                document.body.removeChild(i);
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i> Copier'; }, 2000);
            });
        }
    </script>
</body>
</html>
