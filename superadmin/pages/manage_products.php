<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

// Filters
$search    = trim($_GET['search'] ?? '');
$cat_filter  = intval($_GET['category'] ?? 0);
$brand_filter = intval($_GET['brand'] ?? 0);
$status_filter = $_GET['status'] ?? '';  // 'active', 'inactive', 'low_stock', 'out_of_stock'
$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = 25;
$offset    = ($page - 1) * $per_page;

// Build WHERE
$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat_filter) {
    $where .= " AND p.category_id = ?";
    $params[] = $cat_filter;
}
if ($brand_filter) {
    $where .= " AND p.brand_id = ?";
    $params[] = $brand_filter;
}
if ($status_filter === 'active')      $where .= " AND p.is_active = 1 AND p.stock > 0";
elseif ($status_filter === 'inactive') $where .= " AND p.is_active = 0";
elseif ($status_filter === 'low_stock') $where .= " AND p.is_active = 1 AND p.stock > 0 AND p.stock <= p.stock_threshold";
elseif ($status_filter === 'out_of_stock') $where .= " AND p.stock = 0";

try {
    // Count
    $count_params = $params;
    $total = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
    $total->execute($count_params);
    $total = $total->fetchColumn();

    // Products
    $stmt_params = $params;
    $stmt_params[] = $offset;
    $stmt_params[] = $per_page;
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.sku, p.price, p.old_price, p.stock, p.stock_threshold,
               p.is_active, p.is_featured, p.image, p.created_at,
               c.name as category_name, b.name as brand_name,
               (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) as img_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        $where
        ORDER BY p.created_at DESC
        LIMIT ?, ?
    ");
    $stmt->execute($stmt_params);
    $products = $stmt->fetchAll();

    // Stats
    $stats = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN stock=0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN stock>0 AND stock<=stock_threshold THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN is_featured=1 THEN 1 ELSE 0 END) as featured
        FROM products")->fetch();

    // Filters data
    $categories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
    $brands = $pdo->query("SELECT id, name FROM brands WHERE is_active=1 ORDER BY name")->fetchAll();
} catch(Exception $e) {
    $products = [];
    $total = 0;
    $stats = [];
    $categories = [];
    $brands = [];
}

$total_pages = max(1, ceil($total / $per_page));

// Check if filters active
$has_filters = $search || $cat_filter || $brand_filter || $status_filter;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: #020817;
            color: #e0e0e0;
            line-height: 1.6;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(168,85,247,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(255,215,0,0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: rgba(17,34,64,0.8);
            border-right: 1px solid rgba(168,85,247,0.3);
            padding: 25px 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-logo {
            padding: 0 20px 25px;
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            color: #a855f7;
            border-bottom: 1px solid rgba(168,85,247,0.2);
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .sidebar-logo i {
            margin-right: 8px;
            color: #ffd700;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: #8892b0;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 14px;
        }

        .sidebar-menu a:hover {
            color: #a855f7;
            background: rgba(168,85,247,0.1);
        }

        .sidebar-menu a.active {
            background: rgba(168,85,247,0.2);
            color: #a855f7;
            border-left: 3px solid #ffd700;
        }

        .sidebar-menu i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .sidebar-admin {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid rgba(168,85,247,0.2);
            color: #8892b0;
            font-size: 13px;
            text-align: center;
        }

        .sidebar-admin i {
            color: #ffd700;
            margin-right: 6px;
        }

        /* Main content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
        }

        .page-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            color: #a855f7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .page-header i {
            font-size: 32px;
            color: #ffd700;
        }

        /* Info banner */
        .info-banner {
            background: rgba(168,85,247,0.1);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .info-banner-text {
            flex: 1;
            font-size: 14px;
            color: #c3b1e1;
        }

        .info-banner-btn {
            background: linear-gradient(135deg, #a855f7 0%, #ffd700 100%);
            border: none;
            color: #020817;
            padding: 10px 16px;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }

        .info-banner-btn:hover {
            transform: translateY(-2px);
        }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }

        .stat-label {
            font-size: 12px;
            color: #8892b0;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-total .stat-value { color: #a855f7; }
        .stat-active .stat-value { color: #10b981; }
        .stat-inactive .stat-value { color: #ef4444; }
        .stat-out-of-stock .stat-value { color: #ef4444; }
        .stat-low-stock .stat-value { color: #ffd700; }
        .stat-featured .stat-value { color: #a855f7; }

        /* Filter bar */
        .filter-bar {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            font-size: 12px;
            color: #a855f7;
            text-transform: uppercase;
            margin-bottom: 6px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            background: rgba(17,34,64,0.8);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 8px;
            color: #e0e0e0;
            font-family: 'Rajdhani', sans-serif;
            font-size: 13px;
            transition: all 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 10px rgba(168,85,247,0.2);
        }

        .filter-group input::placeholder {
            color: #5a6b7f;
        }

        .filter-btn {
            background: linear-gradient(135deg, #a855f7 0%, #ffd700 100%);
            border: none;
            color: #020817;
            padding: 10px 24px;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform 0.2s;
            min-width: 140px;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
        }

        .filter-reset {
            color: #ffd700;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            transition: color 0.3s;
        }

        .filter-reset:hover {
            color: #a855f7;
        }

        /* Products table */
        .products-card {
            background: rgba(17,34,64,0.6);
            border: 1px solid rgba(168,85,247,0.3);
            border-radius: 15px;
            padding: 25px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            color: #a855f7;
            font-family: 'Orbitron', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(168,85,247,0.3);
            text-align: left;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(168,85,247,0.1);
            font-size: 13px;
        }

        tr:hover {
            background: rgba(168,85,247,0.05);
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: rgba(168,85,247,0.1);
        }

        .product-name {
            font-weight: 700;
            color: #e0e0e0;
            margin-bottom: 4px;
        }

        .product-sku {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #8892b0;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-right: 6px;
            margin-bottom: 4px;
        }

        .badge-green {
            background: rgba(16,185,129,0.2);
            color: #10b981;
        }

        .badge-red {
            background: rgba(239,68,68,0.2);
            color: #ef4444;
        }

        .badge-gold {
            background: rgba(255,215,0,0.2);
            color: #ffd700;
        }

        .badge-purple {
            background: rgba(168,85,247,0.2);
            color: #a855f7;
        }

        .price-current {
            font-weight: 700;
            color: #ffd700;
        }

        .price-old {
            text-decoration: line-through;
            color: #8892b0;
            font-size: 12px;
            margin-left: 8px;
        }

        .stock-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .stock-rupture {
            background: rgba(239,68,68,0.2);
            color: #ef4444;
        }

        .stock-low {
            background: rgba(255,215,0,0.2);
            color: #ffd700;
        }

        .stock-ok {
            background: rgba(16,185,129,0.2);
            color: #10b981;
        }

        .badge-images {
            background: rgba(168,85,247,0.1);
            border: 1px solid rgba(168,85,247,0.3);
            color: #a855f7;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }

        .actions-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid rgba(168,85,247,0.3);
            background: rgba(168,85,247,0.1);
            color: #a855f7;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            margin-right: 6px;
            font-size: 14px;
        }

        .actions-btn:hover {
            background: rgba(168,85,247,0.2);
            border-color: #a855f7;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(168,85,247,0.3);
            color: #a855f7;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: rgba(168,85,247,0.2);
            border-color: #a855f7;
        }

        .pagination .current {
            background: rgba(168,85,247,0.2);
            border-color: #a855f7;
            font-weight: 700;
        }

        .pagination .disabled {
            color: #5a6b7f;
            border-color: rgba(168,85,247,0.1);
            cursor: not-allowed;
        }

        .results-count {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #8892b0;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: #a855f7;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state p {
            color: #8892b0;
            font-size: 14px;
        }

        .featured-star {
            color: #ffd700;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Scrollbar */
        .sidebar::-webkit-scrollbar,
        .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .sidebar::-webkit-scrollbar-track,
        .table-responsive::-webkit-scrollbar-track {
            background: rgba(168,85,247,0.1);
        }

        .sidebar::-webkit-scrollbar-thumb,
        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(168,85,247,0.3);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover,
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(168,85,247,0.5);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
            }

            .main-content {
                margin-left: 250px;
                padding: 20px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
            }

            .filter-btn {
                min-width: 100%;
            }

            .info-banner {
                flex-direction: column;
                text-align: center;
            }

            .info-banner-btn {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .sidebar {
                width: 200px;
                padding: 15px 0;
            }

            .sidebar-logo {
                padding: 0 15px 15px;
                font-size: 12px;
            }

            .sidebar-menu a {
                padding: 10px 15px;
                font-size: 12px;
            }

            .sidebar-menu i {
                width: 16px;
                margin-right: 8px;
            }

            .main-content {
                margin-left: 200px;
                padding: 15px;
            }

            .page-header h1 {
                font-size: 18px;
            }

            .stats-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .products-card {
                padding: 15px;
            }

            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .product-image {
                width: 40px;
                height: 40px;
            }

            .actions-btn {
                width: 28px;
                height: 28px;
                font-size: 12px;
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

    <!-- Sidebar -->
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
            <li><a href="manage_users.php"><i class="fas fa-users"></i> Clients</a></li>
            <li><a href="manage_products.php" class="active"><i class="fas fa-box"></i> Produits</a></li>
            <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                            <li>
                    <a href="taux-change.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'taux-change.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Taux de Change</span>
                    </a>
                </li>
                <li style="margin-top:15px;border-top:1px solid rgba(168,85,247,0.2);padding-top:15px;">
                <a href="system-logs.php"><i class="fas fa-history"></i> Journaux</a>
            </li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            <li><a href="../logout.php" style="color:#ff006e;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
        <div class="sidebar-admin">
            <i class="fas fa-crown"></i> <?php echo htmlspecialchars($_SESSION['superadmin_name'] ?? 'Super Admin'); ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <i class="fas fa-box"></i>
            <h1>Gestion des Produits</h1>
        </div>

        <!-- Info Banner -->
        <div class="info-banner">
            <div class="info-banner-text">
                La gestion complète des produits se fait via le panneau Product Admin
            </div>
            <a href="../../admins/product-admin/pages/products-list.php" target="_blank" class="info-banner-btn">
                Accéder à Product Admin
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card stat-total">
                <div class="stat-label">Total</div>
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-card stat-active">
                <div class="stat-label">Actifs</div>
                <div class="stat-value"><?php echo $stats['active'] ?? 0; ?></div>
            </div>
            <div class="stat-card stat-inactive">
                <div class="stat-label">Inactifs</div>
                <div class="stat-value"><?php echo $stats['inactive'] ?? 0; ?></div>
            </div>
            <div class="stat-card stat-out-of-stock">
                <div class="stat-label">Rupture</div>
                <div class="stat-value"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
            </div>
            <div class="stat-card stat-low-stock">
                <div class="stat-label">Stock Faible</div>
                <div class="stat-value"><?php echo $stats['low_stock'] ?? 0; ?></div>
            </div>
            <div class="stat-card stat-featured">
                <div class="stat-label">Vedettes</div>
                <div class="stat-value"><?php echo $stats['featured'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="filter-group" style="flex: 2; min-width: 250px;">
                    <label>Rechercher</label>
                    <input type="text" name="search" placeholder="Nom, SKU..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label>Catégorie</label>
                    <select name="category">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $cat_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Marque</label>
                    <select name="brand">
                        <option value="">Toutes</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>" <?php echo $brand_filter == $brand['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select name="status">
                        <option value="">Tous</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                        <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
                        <option value="low_stock" <?php echo $status_filter === 'low_stock' ? 'selected' : ''; ?>>Stock faible</option>
                    </select>
                </div>
                <button type="submit" class="filter-btn">Filtrer</button>
                <?php if ($has_filters): ?>
                    <a href="manage_products.php" class="filter-reset">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Products Table -->
        <div class="products-card">
            <?php if (count($products) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Produit</th>
                                <th>Catégorie / Marque</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Statut</th>
                                <th>Photos</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <!-- Photo -->
                                    <td>
                                        <?php if ($p['image']): ?>
                                            <img src="../../../uploads/products/<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-image" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\"width:50px;height:50px;background:rgba(168,85,247,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;\"><i class=\"fas fa-image\" style=\"color:#a855f7;\"></i></div>'">
                                        <?php else: ?>
                                            <div style="width:50px;height:50px;background:rgba(168,85,247,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                                <i class="fas fa-image" style="color:#a855f7;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Product Info -->
                                    <td>
                                        <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div class="product-sku">SKU: <?php echo htmlspecialchars($p['sku']); ?></div>
                                    </td>

                                    <!-- Category / Brand -->
                                    <td>
                                        <?php if ($p['category_name']): ?>
                                            <div class="badge badge-purple" style="margin-bottom:6px;"><?php echo htmlspecialchars($p['category_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($p['brand_name']): ?>
                                            <div class="badge badge-gold"><?php echo htmlspecialchars($p['brand_name']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Price -->
                                    <td>
                                        <span class="price-current">$<?php echo number_format($p['price'], 2); ?></span>
                                        <?php if ($p['old_price']): ?>
                                            <span class="price-old">$<?php echo number_format($p['old_price'], 2); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Stock -->
                                    <td>
                                        <?php if ($p['stock'] == 0): ?>
                                            <div class="stock-badge stock-rupture">Rupture</div>
                                        <?php elseif ($p['stock'] <= $p['stock_threshold']): ?>
                                            <div class="stock-badge stock-low">Faible: <?php echo $p['stock']; ?></div>
                                        <?php else: ?>
                                            <div class="stock-badge stock-ok"><?php echo $p['stock']; ?> unités</div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <?php if ($p['is_active']): ?>
                                            <div class="badge badge-green">Actif</div>
                                        <?php else: ?>
                                            <div class="badge badge-red">Inactif</div>
                                        <?php endif; ?>
                                        <?php if ($p['is_featured']): ?>
                                            <div class="featured-star">⭐ Vedette</div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Images Count -->
                                    <td>
                                        <span class="badge-images"><?php echo $p['img_count']; ?>/5</span>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <a href="../../admins/product-admin/pages/product-view.php?id=<?php echo $p['id']; ?>" target="_blank" class="actions-btn" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../../admins/product-admin/pages/product-edit.php?id=<?php echo $p['id']; ?>" target="_blank" class="actions-btn" title="Éditer">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">« Première</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹ Précédente</a>
                    <?php else: ?>
                        <span class="disabled">« Première</span>
                        <span class="disabled">‹ Précédente</span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1): ?>
                        <span>...</span>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Suivante ›</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Dernière »</a>
                    <?php else: ?>
                        <span class="disabled">Suivante ›</span>
                        <span class="disabled">Dernière »</span>
                    <?php endif; ?>
                </div>

                <div class="results-count">
                    <?php echo $total; ?> produit<?php echo $total !== 1 ? 's' : ''; ?> trouvé<?php echo $total !== 1 ? 's' : ''; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box"></i>
                    <p>Aucun produit trouvé</p>
                </div>
            <?php endif; ?>
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
