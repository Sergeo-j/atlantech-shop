<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-bolt"></i>
            <span class="logo-text">ATLANTECH</span>
        </div>
        <div class="logo-subtitle">CLIENT ADMIN</div>
    </div>
    
    <nav class="sidebar-menu">
        <ul>
            <li class="<?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Tableau de Bord</span>
                </a>
            </li>
            
            <li class="<?php echo ($current_page == 'clients') ? 'active' : ''; ?>">
                <a href="clients-list.php">
                    <i class="fas fa-users"></i>
                    <span>Liste des Clients</span>
                </a>
            </li>

            <li class="<?php echo ($current_page == 'reviews') ? 'active' : ''; ?>">
                <a href="reviews-manager.php">
                    <i class="fas fa-star"></i>
                    <span>Avis Produits</span>
                </a>
            </li>
            
            <li class="menu-separator"></li>
            
            <li>
                <a href="#">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistiques</span>
                </a>
            </li>
            
            <li>
                <a href="#">
                    <i class="fas fa-file-export"></i>
                    <span>Exporter</span>
                </a>
            </li>
            
            <li class="menu-separator"></li>
            
            <li>
                <a href="#">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            
            <li>
                <a href="../logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="admin-card">
            <div class="admin-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                <div class="admin-status">
                    <span class="status-dot"></span>
                    En ligne
                </div>
            </div>
        </div>
    </div>
</aside>
