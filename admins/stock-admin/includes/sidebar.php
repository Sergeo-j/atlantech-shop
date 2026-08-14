<div class="sidebar">
    <div class="logo">
        <i class="fas fa-warehouse"></i>
        <span>ATLANTECH</span>
        <small>Stock Admin</small>
    </div>
    
    <nav class="menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="inventory.php" class="menu-item <?php echo ($current_page ?? '') === 'inventory' ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i>
            <span>Inventaire</span>
        </a>
        
        <a href="movements-list.php" class="menu-item <?php echo ($current_page ?? '') === 'movements' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Mouvements</span>
        </a>
        
        <a href="movement-add.php" class="menu-item <?php echo ($current_page ?? '') === 'add-movement' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Nouveau Mouvement</span>
        </a>
        
        <a href="reports.php" class="menu-item <?php echo ($current_page ?? '') === 'reports' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Rapports</span>
        </a>
        
        <div class="menu-divider"></div>
        
        <a href="settings.php" class="menu-item <?php echo ($current_page ?? '') === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Paramètres</span>
        </a>
        
        <a href="../logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Déconnexion</span>
        </a>
    </nav>
    
    <div class="user-info">
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'SA', 0, 2)); ?>
        </div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Stock Admin'); ?></div>
            <div class="user-role">Stock Administrator</div>
        </div>
    </div>
</div>
