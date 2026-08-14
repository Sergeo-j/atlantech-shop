<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) { header('Location: manage_users.php'); exit; }

// Load user
try {
    $stmt = $pdo->prepare("SELECT u.*,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id AND o.status != 'cancelled'
        WHERE u.id = ?
        GROUP BY u.id LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch(Exception $e) { $user = null; }

if (!$user) { header('Location: manage_users.php?error=not_found'); exit; }

// Recent orders
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch(Exception $e) { $orders = []; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Client - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #020817;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            font-family: 'Rajdhani', sans-serif;
            color: #e6f1ff;
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: rgba(17, 34, 64, 0.8);
            border-right: 1px solid rgba(168, 85, 247, 0.3);
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-logo {
            padding: 0 20px 30px;
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #a855f7;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo i {
            color: #ffd700;
            font-size: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            flex: 1;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: #b0b9c3;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .sidebar-menu li a i {
            width: 20px;
            text-align: center;
        }

        .sidebar-menu li a:hover {
            color: #a855f7;
            background: rgba(168, 85, 247, 0.1);
        }

        .sidebar-menu li.active a {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border-left: 3px solid #ffd700;
            padding-left: 17px;
        }

        .sidebar > div:last-child {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(168, 85, 247, 0.2);
            color: #8892b0;
            font-size: 13px;
            text-align: center;
            padding: 20px;
        }

        .sidebar > div:last-child i {
            color: #ffd700;
            margin-right: 5px;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            flex: 1;
            width: calc(100% - 280px);
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #a855f7;
            flex: 1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid rgba(168, 85, 247, 0.3);
            color: #a855f7;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .back-btn:hover {
            background: rgba(168, 85, 247, 0.3);
            color: #ffd700;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: rgba(17, 34, 64, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .avatar {
            width: 80px;
            height: 80px;
            background: #00d4aa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #020817;
            margin-bottom: 15px;
        }

        .profile-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #e6f1ff;
            margin-bottom: 5px;
        }

        .profile-email {
            color: #8892b0;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
            margin-bottom: 20px;
        }

        .profile-meta-item {
            font-size: 13px;
            color: #b0b9c3;
        }

        .profile-meta-item strong {
            color: #a855f7;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(168, 85, 247, 0.2);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.tier-bronze { background: rgba(205, 127, 50, 0.3); color: #cd7f32; }
        .badge.tier-silver { background: rgba(192, 192, 192, 0.3); color: #c0c0c0; }
        .badge.tier-gold { background: rgba(255, 215, 0, 0.3); color: #ffd700; }
        .badge.tier-platinum { background: rgba(229, 228, 226, 0.3); color: #e5e4e2; }
        .badge.verified { background: rgba(0, 212, 170, 0.3); color: #00d4aa; }
        .badge.blocked { background: rgba(255, 0, 110, 0.3); color: #ff006e; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .info-item {
            background: rgba(17, 34, 64, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 20px;
        }

        .info-label {
            font-size: 12px;
            color: #8892b0;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 16px;
            color: #e6f1ff;
            font-weight: 500;
        }

        .info-value.highlight {
            color: #00d4aa;
            font-weight: 600;
        }

        .orders-section {
            background: rgba(17, 34, 64, 0.6);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            background: rgba(168, 85, 247, 0.1);
            padding: 15px;
            text-align: left;
            font-size: 13px;
            color: #8892b0;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
        }

        .orders-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.1);
            font-size: 14px;
        }

        .orders-table tr:hover {
            background: rgba(168, 85, 247, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: rgba(255, 215, 0, 0.3); color: #ffd700; }
        .status-completed { background: rgba(0, 212, 170, 0.3); color: #00d4aa; }
        .status-cancelled { background: rgba(255, 0, 110, 0.3); color: #ff006e; }
        .status-processing { background: rgba(100, 150, 255, 0.3); color: #6496ff; }

        .no-orders {
            text-align: center;
            padding: 40px;
            color: #8892b0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            font-family: 'Rajdhani', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: rgba(168, 85, 247, 0.3);
            border: 1px solid rgba(168, 85, 247, 0.5);
            color: #a855f7;
        }

        .btn-primary:hover {
            background: rgba(168, 85, 247, 0.4);
            color: #ffd700;
        }

        .btn-danger {
            background: rgba(255, 0, 110, 0.3);
            border: 1px solid rgba(255, 0, 110, 0.5);
            color: #ff006e;
        }

        .btn-danger:hover {
            background: rgba(255, 0, 110, 0.4);
            color: #ff4488;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 1200px) {
            .profile-container, .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
                width: calc(100% - 220px);
                padding: 20px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .page-header h1 {
                font-size: 24px;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

<!-- Mobile top bar (hamburger) -->
<div class="sa-mobile-header">
    <span class="sa-mobile-logo"><i class="fas fa-shield-alt" style="margin-right:6px;color:#ffd700;-webkit-text-fill-color:#ffd700"></i>ATLANTECH SA</span>
    <button class="sa-hamburger" id="sa-hamburger-btn" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
    </button>
</div>
<!-- Sidebar overlay -->
<div class="sa-sidebar-overlay" id="sa-sidebar-overlay"></div>

    <div class="sidebar">
    <!-- Close button (mobile) -->
    <button class="sa-sidebar-close" id="sa-sidebar-close-btn" aria-label="Fermer">
        <i class="fas fa-times"></i>
    </button>

        <div class="sidebar-logo"><i class="fas fa-crown"></i> SUPER ADMIN</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admins-list.php"><i class="fas fa-user-shield"></i> Administrateurs</a></li>
            <li><a href="admin-create.php"><i class="fas fa-user-plus"></i> Créer Admin</a></li>
            <li class="active"><a href="manage_users.php"><i class="fas fa-users"></i> Clients</a></li>
            <li><a href="manage_products.php"><i class="fas fa-box"></i> Produits</a></li>
            <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
            <li style="margin-top:15px; border-top:1px solid rgba(168,85,247,0.2); padding-top:15px;">
                <a href="system-logs.php"><i class="fas fa-history"></i> Journaux</a>
            </li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            <li><a href="../logout.php" style="color:#ff006e; margin-top:20px;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
        <div style="margin-top:auto; padding-top:20px; border-top:1px solid rgba(168,85,247,0.2); color:#8892b0; font-size:13px; text-align:center;">
            <i class="fas fa-crown" style="color:#ffd700;"></i> <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-header">
            <a href="manage_users.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour</a>
            <h1>Fiche Client #<?php echo htmlspecialchars($user['id']); ?></h1>
        </div>

        <div class="profile-container">
            <div class="profile-card">
                <div class="avatar">
                    <?php
                    $first = substr($user['first_name'] ?? 'U', 0, 1);
                    $last = substr($user['last_name'] ?? '', 0, 1);
                    echo htmlspecialchars(strtoupper($first . $last));
                    ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                <div class="profile-meta">
                    <div class="profile-meta-item"><strong>Téléphone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                    <div class="profile-meta-item"><strong>Inscrit:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($user['created_at'] ?? ''))); ?></div>
                </div>
                <div class="badges">
                    <?php
                    $tier = $user['tier'] ?? 'Bronze';
                    echo '<span class="badge tier-' . strtolower($tier) . '">' . htmlspecialchars($tier) . '</span>';
                    if ($user['email_verified'] ?? false) {
                        echo '<span class="badge verified"><i class="fas fa-check-circle"></i> Vérifié</span>';
                    }
                    if ($user['blocked'] ?? false) {
                        echo '<span class="badge blocked"><i class="fas fa-ban"></i> Bloqué</span>';
                    }
                    ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="info-item">
                    <div class="info-label">ID Client</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['id']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Adresse</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Ville</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['city'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Inscrit le</div>
                    <div class="info-value"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at'] ?? ''))); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dernière connexion</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Points de fidélité</div>
                    <div class="info-value highlight"><?php echo htmlspecialchars($user['loyalty_points'] ?? 0); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Commandes totales</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['total_orders']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dépenses totales</div>
                    <div class="info-value highlight"><?php echo htmlspecialchars(number_format($user['total_spent'], 2, ',', ' ') . ' HTG'); ?></div>
                </div>
            </div>
        </div>

        <div class="orders-section">
            <div class="section-title"><i class="fas fa-shopping-bag"></i> Commandes récentes</div>
            <?php if (!empty($orders)): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>ID Commande</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at'] ?? ''))); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['status'] ?? 'pending'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($order['status'] ?? 'Inconnu')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(number_format($order['total_amount'], 2, ',', ' ') . ' HTG'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-orders"><i class="fas fa-inbox"></i> Aucune commande trouvée</div>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <form method="POST" action="../ajax/toggle_user_block.php" style="display: inline;">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <button type="submit" class="btn <?php echo ($user['blocked'] ?? false) ? 'btn-primary' : 'btn-danger'; ?>">
                    <i class="fas fa-<?php echo ($user['blocked'] ?? false) ? 'lock-open' : 'ban'; ?>"></i>
                    <?php echo ($user['blocked'] ?? false) ? 'Débloquer' : 'Bloquer'; ?>
                </button>
            </form>
        </div>
    </div>
<script>
(function(){
    var overlay   = document.getElementById('sa-sidebar-overlay');
    var sidebar   = document.querySelector('.sidebar');
    var hamburger = document.getElementById('sa-hamburger-btn');
    var closeBtn  = document.getElementById('sa-sidebar-close-btn');
    function openSidebar()  { if(sidebar){sidebar.classList.add('sa-open');}    if(overlay){overlay.classList.add('active');} }
    function closeSidebar() { if(sidebar){sidebar.classList.remove('sa-open');} if(overlay){overlay.classList.remove('active');} }
    if(hamburger) hamburger.addEventListener('click', openSidebar);
    if(closeBtn)  closeBtn.addEventListener('click', closeSidebar);
    if(overlay)   overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
