<?php
/**
 * Préférences de Notification - AtlanTech E-commerce
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=notifications');

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// Charger les préférences de notification depuis la session
// (persistées en session — pas de table user_notification_prefs dans ce projet)
if (!isset($_SESSION['notif_prefs'])) {
    $_SESSION['notif_prefs'] = [
        // Commandes
        'order_confirmed'  => '1',
        'order_paid'       => '1',
        'order_shipped'    => '1',
        'order_delivered'  => '1',
        'order_cancelled'  => '1',
        // Promotions
        'promo_flash'      => '1',
        'promo_coupons'    => '1',
        'new_arrivals'     => '0',
        // Compte
        'security_alert'   => '1',
        'login_new_device' => '1',
        'password_changed' => '1',
        // Fidélité
        'points_earned'    => '1',
        'tier_upgraded'    => '1',
        // Canaux
        'channel_email'    => '1',
        'channel_sms'      => '0',
        'channel_push'     => '1',
    ];
}
$prefs = $_SESSION['notif_prefs'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        // ignorer
    } else {
        $keys = [
            'order_confirmed','order_paid','order_shipped','order_delivered','order_cancelled',
            'promo_flash','promo_coupons','new_arrivals',
            'security_alert','login_new_device','password_changed',
            'points_earned','tier_upgraded',
            'channel_email','channel_sms','channel_push',
        ];
        foreach ($keys as $k) {
            $prefs[$k] = isset($_POST[$k]) ? '1' : '0';
        }
        // La sécurité ne peut pas être désactivée
        $prefs['security_alert']   = '1';
        $prefs['password_changed'] = '1';
        // L'email non plus (canal minimum)
        $prefs['channel_email']    = '1';

        $_SESSION['notif_prefs'] = $prefs;
        $success = 'Vos préférences de notification ont été enregistrées.';
    }
}

// Compter les notifications non lues
$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$unread = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$groups = [
    [
        'icon'  => '📦',
        'title' => 'Commandes',
        'desc'  => 'Mises à jour sur le statut de vos commandes',
        'items' => [
            ['key'=>'order_confirmed', 'label'=>'Commande confirmée',        'forced'=>false],
            ['key'=>'order_paid',      'label'=>'Paiement reçu',             'forced'=>false],
            ['key'=>'order_shipped',   'label'=>'Commande expédiée',         'forced'=>false],
            ['key'=>'order_delivered', 'label'=>'Commande livrée',           'forced'=>false],
            ['key'=>'order_cancelled', 'label'=>'Commande annulée',          'forced'=>false],
        ],
    ],
    [
        'icon'  => '🏷️',
        'title' => 'Promotions & Nouveautés',
        'desc'  => 'Offres spéciales, ventes flash et nouvelles arrivées',
        'items' => [
            ['key'=>'promo_flash',   'label'=>'Ventes flash & soldes',      'forced'=>false],
            ['key'=>'promo_coupons', 'label'=>'Coupons personnalisés',       'forced'=>false],
            ['key'=>'new_arrivals',  'label'=>'Nouveaux produits',           'forced'=>false],
        ],
    ],
    [
        'icon'  => '🔒',
        'title' => 'Sécurité du compte',
        'desc'  => 'Alertes importantes (ne peuvent pas être désactivées)',
        'items' => [
            ['key'=>'security_alert',   'label'=>'Alertes de sécurité',        'forced'=>true],
            ['key'=>'login_new_device', 'label'=>'Connexion depuis un nouvel appareil', 'forced'=>false],
            ['key'=>'password_changed', 'label'=>'Mot de passe modifié',        'forced'=>true],
        ],
    ],
    [
        'icon'  => '⭐',
        'title' => 'Programme de fidélité',
        'desc'  => 'Points gagnés et changements de palier VIP',
        'items' => [
            ['key'=>'points_earned', 'label'=>'Points gagnés sur une commande', 'forced'=>false],
            ['key'=>'tier_upgraded', 'label'=>'Palier VIP atteint',             'forced'=>false],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Préférences de notification - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:780px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}

        /* Canaux */
        .channels-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:22px;margin-bottom:20px;}
        .channels-card h3{font-size:16px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #E7E7E7;}
        .channels-row{display:flex;gap:14px;flex-wrap:wrap;}
        .channel-chip{display:flex;align-items:center;gap:8px;padding:10px 16px;border:2px solid #D5D9D9;border-radius:8px;cursor:pointer;transition:all .2s;font-size:14px;font-weight:600;}
        .channel-chip input{display:none;}
        .channel-chip.active{border-color:#e77600;background:#fffde7;}
        .channel-chip.locked{opacity:.6;cursor:not-allowed;}
        .channel-icon{font-size:20px;}

        /* Groupes */
        .group-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;overflow:hidden;margin-bottom:16px;}
        .group-header{padding:16px 20px;background:#F0F2F2;display:flex;align-items:center;gap:10px;}
        .group-icon{font-size:22px;}
        .group-title{font-size:15px;font-weight:700;color:#0F1111;}
        .group-desc{font-size:12px;color:#666;}
        .notif-row{display:flex;justify-content:space-between;align-items:center;padding:13px 20px;border-top:1px solid #F0F2F2;}
        .notif-label{font-size:14px;color:#0F1111;}
        .forced-badge{font-size:11px;color:#ef4444;background:#fee2e2;padding:2px 7px;border-radius:8px;margin-left:6px;}

        /* Toggle */
        .toggle{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;}
        .toggle input{opacity:0;width:0;height:0;}
        .toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s;}
        .toggle-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
        .toggle input:checked + .toggle-slider{background:#e77600;}
        .toggle input:checked + .toggle-slider:before{transform:translateX(20px);}
        .toggle input:disabled + .toggle-slider{background:#10b981;cursor:not-allowed;opacity:.7;}
        .toggle input:disabled + .toggle-slider:before{transform:translateX(20px);}

        /* Lien messages */
        .messages-banner{background:#fffde7;border:1px solid #e77600;border-radius:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap;}

        .btn-save{width:100%;padding:13px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:15px;font-weight:700;color:#0F1111;cursor:pointer;margin-top:4px;}
        .btn-save:hover{background:#F7CA00;}
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
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Préférences de notification</span>
    </nav>
    <h1 class="page-title">Préférences de notification</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- Lien vers les messages -->
    <?php if ($unread > 0): ?>
    <div class="messages-banner">
        <div>
            <strong>🔔 <?php echo $unread; ?> notification<?php echo $unread>1?'s non lues':' non lue'; ?></strong>
            en attente dans votre boîte.
        </div>
        <a href="messages.php?tab=unread" style="padding:8px 16px;background:#e77600;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;font-weight:700;">
            Voir mes messages
        </a>
    </div>
    <?php endif; ?>

    <form method="POST" action="notifications.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <!-- Canaux de notification -->
        <div class="channels-card">
            <h3>📡 Canaux de notification</h3>
            <div class="channels-row">
                <label class="channel-chip locked active" title="Toujours activé">
                    <input type="checkbox" name="channel_email" checked disabled>
                    <span class="channel-icon">✉️</span>
                    <span>E-mail <span style="font-size:11px;color:#888;">(requis)</span></span>
                </label>
                <label class="channel-chip <?php echo $prefs['channel_sms']==='1'?'active':''; ?>" id="chip-sms">
                    <input type="checkbox" name="channel_sms" id="chk-sms"
                           <?php echo $prefs['channel_sms']==='1'?'checked':''; ?>
                           onchange="document.getElementById('chip-sms').classList.toggle('active',this.checked)">
                    <span class="channel-icon">💬</span>
                    <span>SMS</span>
                </label>
                <label class="channel-chip <?php echo $prefs['channel_push']==='1'?'active':''; ?>" id="chip-push">
                    <input type="checkbox" name="channel_push" id="chk-push"
                           <?php echo $prefs['channel_push']==='1'?'checked':''; ?>
                           onchange="document.getElementById('chip-push').classList.toggle('active',this.checked)">
                    <span class="channel-icon">🔔</span>
                    <span>Notifications app</span>
                </label>
            </div>
        </div>

        <!-- Groupes -->
        <?php foreach ($groups as $group): ?>
            <div class="group-card">
                <div class="group-header">
                    <span class="group-icon"><?php echo $group['icon']; ?></span>
                    <div>
                        <div class="group-title"><?php echo $group['title']; ?></div>
                        <div class="group-desc"><?php echo $group['desc']; ?></div>
                    </div>
                </div>
                <?php foreach ($group['items'] as $item): ?>
                    <div class="notif-row">
                        <div class="notif-label">
                            <?php echo $item['label']; ?>
                            <?php if ($item['forced']): ?>
                                <span class="forced-badge">Obligatoire</span>
                            <?php endif; ?>
                        </div>
                        <label class="toggle">
                            <input type="checkbox"
                                   name="<?php echo $item['key']; ?>"
                                   <?php echo $prefs[$item['key']]==='1'?'checked':''; ?>
                                   <?php echo $item['forced']?'disabled checked':''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Actions groupées -->
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
            <button type="button" onclick="toggleAll(true)"
                    style="padding:8px 16px;border:1px solid #D5D9D9;border-radius:6px;background:#fff;font-size:13px;cursor:pointer;">
                ✓ Tout activer
            </button>
            <button type="button" onclick="toggleAll(false)"
                    style="padding:8px 16px;border:1px solid #D5D9D9;border-radius:6px;background:#fff;font-size:13px;cursor:pointer;">
                ✕ Tout désactiver (sauf obligatoires)
            </button>
        </div>

        <button type="submit" class="btn-save">Enregistrer mes préférences</button>
    </form>

    <div style="margin-top:20px;font-size:13px;color:#666;text-align:center;">
        Vous pouvez aussi consulter vos notifications dans <a href="messages.php" style="color:#007185;">Vos Messages</a>.
    </div>

    <div style="margin-top:16px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleAll(state) {
    document.querySelectorAll('.toggle input[type=checkbox]:not(:disabled)').forEach(function(cb) {
        cb.checked = state;
    });
}
</script>
</body>
</html>
