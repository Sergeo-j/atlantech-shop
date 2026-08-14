<?php
/**
 * Vos Messages & Notifications - AtlanTech E-commerce
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=messages');

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$success = '';

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        // Ignorer silencieusement
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {
            $notif_id = (int)($_POST['notif_id'] ?? 0);
            if ($notif_id > 0) {
                $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $notif_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($action === 'mark_all_read') {
            $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Toutes les notifications ont été marquées comme lues.';
        } elseif ($action === 'delete') {
            $notif_id = (int)($_POST['notif_id'] ?? 0);
            if ($notif_id > 0) {
                $stmt = $mysqli->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $notif_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($action === 'delete_all_read') {
            $stmt = $mysqli->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Notifications lues supprimées.';
        }
    }
    // Rediriger pour éviter re-soumission
    if (empty($success)) {
        header("Location: messages.php" . (isset($_GET['tab']) ? '?tab='.$_GET['tab'] : ''));
        exit();
    }
}

$tab = $_GET['tab'] ?? 'all'; // all | unread

// Filtrer
$where = "user_id = ?";
if ($tab === 'unread') $where .= " AND is_read = 0";

$stmt = $mysqli->prepare(
    "SELECT id, type, title, message, is_read, created_at
     FROM notifications WHERE $where ORDER BY created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Compter les non-lues
$stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$unread_count = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Icônes et couleurs par type
$type_config = [
    'order'       => ['icon' => '📦', 'color' => '#3b82f6', 'bg' => '#dbeafe', 'label' => 'Commande'],
    'payment'     => ['icon' => '💳', 'color' => '#10b981', 'bg' => '#d1fae5', 'label' => 'Paiement'],
    'promotion'   => ['icon' => '🏷️', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Promotion'],
    'system'      => ['icon' => '⚙️', 'color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => 'Système'],
    'delivery'    => ['icon' => '🚚', 'color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => 'Livraison'],
    'loyalty'     => ['icon' => '⭐', 'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Fidélité'],
    'welcome'     => ['icon' => '👋', 'color' => '#ec4899', 'bg' => '#fce7f3', 'label' => 'Bienvenue'],
    'security'    => ['icon' => '🔒', 'color' => '#ef4444', 'bg' => '#fee2e2', 'label' => 'Sécurité'],
];
$default_type = ['icon' => '🔔', 'color' => '#0F1111', 'bg' => '#F0F2F2', 'label' => 'Notification'];

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return floor($diff/60).' min';
    if ($diff < 86400)  return floor($diff/3600).'h';
    if ($diff < 604800) return floor($diff/86400).' jour'.(floor($diff/86400)>1?'s':'');
    return date('d/m/Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Messages & Notifications - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:860px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}

        /* Header stats */
        .stats-row{display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
        .stat{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:14px 20px;display:flex;align-items:center;gap:10px;}
        .stat .icon{font-size:24px;}
        .stat-val{font-size:20px;font-weight:800;color:#0F1111;}
        .stat-lbl{font-size:12px;color:#666;}

        /* Tabs + actions */
        .tabs-row{display:flex;align-items:center;border-bottom:2px solid #D5D9D9;margin-bottom:20px;}
        .tab-btn{padding:12px 22px;font-size:14px;font-weight:600;color:#565959;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;}
        .tab-btn.active{color:#0F1111;border-bottom-color:#e77600;}
        .tab-btn:hover{color:#0F1111;}
        .actions-right{margin-left:auto;display:flex;gap:10px;}
        .btn-sm{padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #D5D9D9;background:#fff;color:#0F1111;}
        .btn-sm:hover{background:#F0F2F2;}
        .btn-sm.danger{color:#ef4444;border-color:#fca5a5;}
        .btn-sm.danger:hover{background:#fee2e2;}

        /* Liste notifications */
        .notif-list{background:#fff;border:1px solid #D5D9D9;border-radius:8px;overflow:hidden;}
        .notif-item{display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border-bottom:1px solid #F0F2F2;position:relative;transition:background .15s;}
        .notif-item:last-child{border-bottom:none;}
        .notif-item.unread{background:#fffde7;}
        .notif-item:hover{background:#fafafa;}
        .notif-item.unread:hover{background:#fffbe6;}
        .unread-dot{width:8px;height:8px;border-radius:50%;background:#e77600;flex-shrink:0;margin-top:6px;}
        .read-dot{width:8px;height:8px;flex-shrink:0;}
        .type-badge{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
        .notif-body{flex:1;}
        .notif-title{font-size:14px;font-weight:700;color:#0F1111;margin-bottom:3px;}
        .notif-item.unread .notif-title{font-weight:800;}
        .notif-message{font-size:13px;color:#555;line-height:1.4;margin-bottom:4px;}
        .notif-meta{display:flex;align-items:center;gap:10px;font-size:11px;color:#888;}
        .type-label{padding:2px 8px;border-radius:10px;font-weight:600;font-size:11px;}
        .notif-actions{display:flex;flex-direction:column;gap:4px;align-items:flex-end;flex-shrink:0;}
        .notif-time{font-size:11px;color:#888;white-space:nowrap;}
        .icon-btn{background:none;border:none;cursor:pointer;padding:4px 6px;border-radius:4px;font-size:13px;color:#888;}
        .icon-btn:hover{background:#F0F2F2;color:#0F1111;}

        /* Vide */
        .empty{text-align:center;padding:50px 20px;color:#666;}
        .empty .icon{font-size:56px;display:block;margin-bottom:14px;}
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
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Messages</span>
    </nav>
    <h1 class="page-title">Messages &amp; Notifications</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat">
            <div class="icon">🔔</div>
            <div><div class="stat-val"><?php echo count($notifications); ?></div><div class="stat-lbl">Total</div></div>
        </div>
        <?php if ($unread_count > 0): ?>
        <div class="stat" style="border-color:#e77600;background:#fffde7;">
            <div class="icon">🔴</div>
            <div><div class="stat-val" style="color:#e77600;"><?php echo $unread_count; ?></div><div class="stat-lbl">Non lue<?php echo $unread_count>1?'s':''; ?></div></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabs + actions globales -->
    <div class="tabs-row">
        <a href="messages.php?tab=all"    class="tab-btn <?php echo $tab==='all'   ?'active':''; ?>">
            Toutes
        </a>
        <a href="messages.php?tab=unread" class="tab-btn <?php echo $tab==='unread'?'active':''; ?>">
            Non lues <?php echo $unread_count>0 ? "<span style='background:#e77600;color:#fff;padding:1px 7px;border-radius:10px;font-size:11px;margin-left:4px;'>$unread_count</span>" : ''; ?>
        </a>
        <div class="actions-right">
            <?php if ($unread_count > 0): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-sm">✓ Tout marquer lu</button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Supprimer toutes les notifications lues ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="delete_all_read">
                <button type="submit" class="btn-sm danger">🗑 Vider les lues</button>
            </form>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty">
            <span class="icon">🔔</span>
            <p>
                <?php echo $tab === 'unread'
                    ? 'Aucune notification non lue. Vous êtes à jour !'
                    : 'Aucune notification pour l\'instant.'; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $n): ?>
                <?php
                $tc = $type_config[$n['type']] ?? $default_type;
                $is_unread = !$n['is_read'];
                ?>
                <div class="notif-item <?php echo $is_unread ? 'unread' : ''; ?>">
                    <!-- Indicateur lu/non-lu -->
                    <?php if ($is_unread): ?><div class="unread-dot"></div>
                    <?php else: ?><div class="read-dot"></div><?php endif; ?>

                    <!-- Icône type -->
                    <div class="type-badge" style="background:<?php echo $tc['bg']; ?>;">
                        <?php echo $tc['icon']; ?>
                    </div>

                    <!-- Corps -->
                    <div class="notif-body">
                        <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <?php if (!empty($n['message'])): ?>
                            <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                        <?php endif; ?>
                        <div class="notif-meta">
                            <span class="type-label"
                                  style="color:<?php echo $tc['color']; ?>;background:<?php echo $tc['bg']; ?>;">
                                <?php echo $tc['label']; ?>
                            </span>
                            <span><?php echo time_ago($n['created_at']); ?></span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="notif-actions">
                        <span class="notif-time"><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></span>
                        <div style="display:flex;gap:4px;margin-top:4px;">
                            <?php if ($is_unread): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action"      value="mark_read">
                                <input type="hidden" name="notif_id"    value="<?php echo $n['id']; ?>">
                                <button type="submit" class="icon-btn" title="Marquer comme lu">✓</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Supprimer cette notification ?');">
                                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action"      value="delete">
                                <input type="hidden" name="notif_id"    value="<?php echo $n['id']; ?>">
                                <button type="submit" class="icon-btn" title="Supprimer" style="color:#ef4444;">✕</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p style="font-size:12px;color:#888;margin-top:12px;text-align:center;">
            <?php echo count($notifications); ?> notification<?php echo count($notifications)>1?'s':''; ?> affichée<?php echo count($notifications)>1?'s':''; ?>
        </p>
    <?php endif; ?>

    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
