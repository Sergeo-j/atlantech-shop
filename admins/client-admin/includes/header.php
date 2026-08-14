<?php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - Atlantech Shop</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                </div>
                
                <div class="topbar-right">
                    <!-- Notifications -->
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                    
                    <!-- User Profile -->
                    <div class="user-profile">
                        <div class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                            <span class="user-role">Admin Clients</span>
                        </div>
                        <div class="user-dropdown">
                            <i class="fas fa-chevron-down"></i>
                            <div class="dropdown-menu">
                                <a href="#"><i class="fas fa-user"></i> Profil</a>
                                <a href="#"><i class="fas fa-cog"></i> Paramètres</a>
                                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
