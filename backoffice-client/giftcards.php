<?php
/**
 * Cartes Cadeaux - AtlanTech E-commerce
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=giftcards');
}

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors  = [];
$success = '';
$tab     = $_GET['tab'] ?? 'my-cards'; // my-cards | redeem

// ──────────────────────────────────────────────
// Action : utiliser un code de carte cadeau
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $tab  = 'redeem';
        $code = strtoupper(trim($_POST['code'] ?? ''));

        if (empty($code)) {
            $errors[] = 'Veuillez saisir un code.';
        } else {
            $stmt = $mysqli->prepare(
                "SELECT id, current_balance, is_active, is_redeemed, expires_at, redeemed_by
                 FROM gift_cards WHERE code = ? LIMIT 1"
            );
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $card = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$card) {
                $errors[] = 'Code invalide. Vérifiez le code et réessayez.';
            } elseif (!$card['is_active']) {
                $errors[] = 'Cette carte cadeau est désactivée.';
            } elseif ($card['is_redeemed'] && $card['redeemed_by'] != $user_id) {
                $errors[] = 'Cette carte cadeau a déjà été utilisée.';
            } elseif ($card['expires_at'] && strtotime($card['expires_at']) < time()) {
                $errors[] = 'Cette carte cadeau a expiré le ' . date('d/m/Y', strtotime($card['expires_at'])) . '.';
            } elseif ($card['current_balance'] <= 0) {
                $errors[] = 'Le solde de cette carte est épuisé.';
            } elseif ($card['redeemed_by'] == $user_id) {
                $success = 'Cette carte est déjà liée à votre compte. Solde disponible : '
                         . number_format($card['current_balance'], 2) . ' HTG.';
            } else {
                // Lier la carte au compte
                $stmt = $mysqli->prepare(
                    "UPDATE gift_cards SET redeemed_by = ?, redeemed_at = NOW(), is_redeemed = 1
                     WHERE id = ? AND redeemed_by IS NULL"
                );
                $stmt->bind_param('ii', $user_id, $card['id']);
                $stmt->execute();
                $stmt->close();
                $success = '🎉 Carte cadeau activée ! Solde de '
                         . number_format($card['current_balance'], 2)
                         . ' HTG disponible sur votre compte.';
            }
        }
    }
}

// ── Mes cartes achetées ──
$stmt = $mysqli->prepare(
    "SELECT id, code, initial_balance, current_balance, recipient_name, recipient_email,
            message, is_active, expires_at, created_at
     FROM gift_cards WHERE purchased_by = ? ORDER BY created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Cartes reçues / activées ──
$stmt = $mysqli->prepare(
    "SELECT id, code, initial_balance, current_balance, is_active, expires_at, created_at
     FROM gift_cards WHERE redeemed_by = ? ORDER BY created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$received_cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Solde total disponible ──
$total_balance = array_sum(array_column(array_filter($received_cards, fn($c) => $c['is_active']), 'current_balance'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Cartes Cadeaux - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body { background: #f3f3f3; }
        .gc-wrap { max-width: 900px; margin: 40px auto; padding: 0 20px 80px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 20px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }
        .page-title { font-size: 26px; font-weight: 700; color: #0F1111; margin-bottom: 24px; }

        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Solde total */
        .balance-banner {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: #fff; border-radius: 12px; padding: 24px 32px;
            display: flex; align-items: center; gap: 20px; margin-bottom: 28px; flex-wrap: wrap;
        }
        .balance-banner .icon { font-size: 48px; }
        .balance-amount { font-size: 36px; font-weight: 800; }
        .balance-label  { font-size: 14px; opacity: .8; }

        /* Tabs */
        .tabs { display: flex; gap: 0; border-bottom: 2px solid #D5D9D9; margin-bottom: 28px; }
        .tab-btn { padding: 12px 24px; font-size: 15px; font-weight: 600; color: #565959;
            text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #0F1111; border-bottom-color: #e77600; }
        .tab-btn:hover { color: #0F1111; }

        /* Cartes */
        .gc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .gc-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; border-radius: 12px; padding: 24px; position: relative; overflow: hidden;
        }
        .gc-card::before {
            content: '🎁'; position: absolute; right: -10px; top: -10px;
            font-size: 80px; opacity: .15;
        }
        .gc-card.expired { background: linear-gradient(135deg, #9e9e9e 0%, #555 100%); }
        .gc-card.sent    { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .gc-card-code  { font-size: 18px; font-weight: 800; letter-spacing: 3px; margin-bottom: 8px; font-family: monospace; }
        .gc-card-bal   { font-size: 28px; font-weight: 800; }
        .gc-card-init  { font-size: 12px; opacity: .8; }
        .gc-card-meta  { font-size: 12px; opacity: .75; margin-top: 10px; }
        .gc-card-badge {
            position: absolute; top: 12px; right: 14px;
            background: rgba(255,255,255,0.25); padding: 2px 10px;
            border-radius: 12px; font-size: 11px; font-weight: 700;
        }

        /* Formulaire */
        .redeem-box { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 32px; max-width: 480px; }
        .redeem-box h3 { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .code-input {
            width: 100%; padding: 12px 16px; border: 2px solid #888C8C; border-radius: 8px;
            font-size: 20px; font-weight: 700; letter-spacing: 4px; text-transform: uppercase;
            text-align: center; font-family: monospace;
        }
        .code-input:focus { outline: none; border-color: #e77600; box-shadow: 0 0 0 3px rgba(231,118,0,0.15); }
        .btn-redeem {
            width: 100%; margin-top: 16px; padding: 13px; background: #FFD814;
            border: 1px solid #FFA41C; border-radius: 8px; font-size: 15px; font-weight: 700;
            color: #0F1111; cursor: pointer;
        }
        .btn-redeem:hover { background: #F7CA00; }
        .empty-state { text-align: center; padding: 40px; color: #666; font-size: 14px; }

        /* Barre de progression du solde */
        .balance-bar-wrap { background: rgba(255,255,255,0.3); border-radius: 99px; height: 6px; margin-top: 8px; }
        .balance-bar-fill { height: 100%; border-radius: 99px; background: #fff; }
    </style>
</head>
<body>

<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-user-circle"></i>&nbsp;Mon compte
    </a>
</div>

<div class="gc-wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Cartes Cadeaux</span>
    </nav>

    <h1 class="page-title">Cartes Cadeaux</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Solde total -->
    <?php if ($total_balance > 0): ?>
        <div class="balance-banner">
            <div class="icon">💳</div>
            <div>
                <div class="balance-label">Solde disponible</div>
                <div class="balance-amount"><?php echo number_format($total_balance, 2); ?> HTG</div>
                <div class="balance-label">Utilisable sur votre prochaine commande</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="giftcards.php?tab=my-cards"  class="tab-btn <?php echo $tab === 'my-cards'  ? 'active' : ''; ?>">
            Mes cartes (<?php echo count($my_cards) + count($received_cards); ?>)
        </a>
        <a href="giftcards.php?tab=redeem"    class="tab-btn <?php echo $tab === 'redeem'    ? 'active' : ''; ?>">
            Utiliser un code
        </a>
    </div>

    <?php if ($tab === 'my-cards'): ?>

        <?php if (!empty($received_cards)): ?>
            <h2 style="font-size:16px; font-weight:700; margin-bottom:14px;">Cartes activées sur votre compte</h2>
            <div class="gc-grid">
                <?php foreach ($received_cards as $card): ?>
                    <?php
                    $is_exp  = $card['expires_at'] && strtotime($card['expires_at']) < time();
                    $pct     = $card['initial_balance'] > 0
                               ? min(100, round($card['current_balance'] / $card['initial_balance'] * 100))
                               : 0;
                    ?>
                    <div class="gc-card <?php echo $is_exp ? 'expired' : ''; ?>">
                        <div class="gc-card-badge">
                            <?php echo $is_exp ? 'Expirée' : ($card['is_active'] ? 'Active' : 'Inactive'); ?>
                        </div>
                        <div class="gc-card-code"><?php echo htmlspecialchars($card['code']); ?></div>
                        <div class="gc-card-bal"><?php echo number_format($card['current_balance'], 2); ?> HTG</div>
                        <div class="gc-card-init">
                            Solde initial : <?php echo number_format($card['initial_balance'], 2); ?> HTG
                        </div>
                        <div class="balance-bar-wrap">
                            <div class="balance-bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                        <div class="gc-card-meta">
                            <?php if ($card['expires_at']): ?>
                                <?php echo $is_exp ? 'Expirée le' : 'Expire le'; ?>
                                <?php echo date('d/m/Y', strtotime($card['expires_at'])); ?>
                            <?php else: ?>
                                Sans date d'expiration
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($my_cards)): ?>
            <h2 style="font-size:16px; font-weight:700; margin-bottom:14px;">Cartes offertes par vous</h2>
            <div class="gc-grid">
                <?php foreach ($my_cards as $card): ?>
                    <div class="gc-card sent">
                        <div class="gc-card-badge">Offerte</div>
                        <div class="gc-card-code"><?php echo htmlspecialchars($card['code']); ?></div>
                        <div class="gc-card-bal"><?php echo number_format($card['current_balance'], 2); ?> HTG</div>
                        <div class="gc-card-init">
                            Valeur initiale : <?php echo number_format($card['initial_balance'], 2); ?> HTG
                        </div>
                        <div class="gc-card-meta">
                            <?php if (!empty($card['recipient_name'])): ?>
                                Pour : <?php echo htmlspecialchars($card['recipient_name']); ?><br>
                            <?php endif; ?>
                            Créée le <?php echo date('d/m/Y', strtotime($card['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($my_cards) && empty($received_cards)): ?>
            <div class="empty-state">
                <div style="font-size:56px; margin-bottom:16px;">🎁</div>
                <p>Vous n'avez encore aucune carte cadeau.</p>
                <p>Demandez à un proche de vous en offrir une, ou<br>
                   entrez un code dans l'onglet « Utiliser un code ».</p>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <!-- Formulaire de saisie de code -->
        <div class="redeem-box">
            <h3>🎁 Activer une carte cadeau</h3>
            <form method="POST" action="giftcards.php?tab=redeem">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action"     value="redeem">
                <label class="form-label" for="code">Code de la carte</label>
                <input type="text" id="code" name="code" class="code-input"
                       placeholder="XXXX-XXXX-XXXX"
                       maxlength="50"
                       value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
                <p style="font-size:12px; color:#666; margin-top:6px;">
                    Le code se trouve sur l'email ou la carte physique reçus.
                </p>
                <button type="submit" class="btn-redeem">Activer la carte</button>
            </form>
        </div>

    <?php endif; ?>

    <div style="margin-top:28px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
// Formatter le code automatiquement
document.getElementById('code')?.addEventListener('input', function() {
    var v = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    // Insérer des tirets tous les 4 caractères
    v = v.match(/.{1,4}/g)?.join('-') || v;
    this.value = v;
});
</script>
</body>
</html>
