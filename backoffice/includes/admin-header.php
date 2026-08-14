<?php
/**
 * Header Admin - AtlanTech
 * En-tête réutilisable pour toutes les pages admin
 */

// Vérifier si l'utilisateur est connecté et est admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// Récupérer les infos de l'admin connecté
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$admin_email = $_SESSION['email'] ?? '';
$admin_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Administration'; ?> | AtlanTech Admin</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --dark-color: #1f2937;
            --light-color: #f3f4f6;
            --sidebar-width: 260px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f9fafb;
            color: #111827;
        }
        
        /* TOPBAR */
        .admin-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 1000;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .menu-toggle {
            display: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        
        .admin-logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .topbar-search {
            position: relative;
            margin-left: 24px;
        }
        
        .topbar-search input {
            width: 300px;
            padding: 8px 16px 8px 40px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .topbar-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .topbar-icon {
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .topbar-icon:hover {
            background: #f3f4f6;
        }
        
        .topbar-icon .badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--danger-color);
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .admin-profile:hover {
            background: #f3f4f6;
        }
        
        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .admin-info {
            display: flex;
            flex-direction: column;
        }
        
        .admin-info .name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }
        
        .admin-info .role {
            font-size: 12px;
            color: #6b7280;
        }
        
        /* MAIN LAYOUT */
        .admin-layout {
            display: flex;
            margin-top: 60px;
        }
        
        /* CONTENT */
        .admin-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 24px;
            min-height: calc(100vh - 60px);
        }
        
        .page-header {
            margin-bottom: 24px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 8px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        /* CARDS */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        
        /* BUTTONS */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #e5e7eb;
            color: #6b7280;
        }
        
        .btn-outline:hover {
            background: #f3f4f6;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .admin-content {
                margin-left: 0;
            }
            
            .topbar-search input {
                width: 200px;
            }
            
            .admin-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- TOPBAR -->
    <div class="admin-topbar">
        <div class="topbar-left">
            <div class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>
            <a href="admin_dashboard.php" class="admin-logo">
                <i class="fas fa-bolt"></i> AtlanTech Admin
            </a>
            <div class="topbar-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher...">
            </div>
        </div>
        
        <div class="topbar-right">
            <div class="topbar-icon" title="Notifications">
                <i class="far fa-bell"></i>
                <span class="badge"></span>
            </div>
            
            <div class="topbar-icon" title="Messages">
                <i class="far fa-envelope"></i>
            </div>
            
            <div class="topbar-icon" title="Paramètres">
                <i class="fas fa-cog"></i>
            </div>
            
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
                </div>
                <div class="admin-info">
                    <span class="name"><?php echo htmlspecialchars($admin_name); ?></span>
                    <span class="role">Administrateur</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="admin-layout">
        <!-- SIDEBAR (sera inclus séparément) -->
        <?php include __DIR__ . '/admin-sidebar.php'; ?>
        
        <!-- CONTENT (le contenu de chaque page ira ici) -->
        <div class="admin-content">