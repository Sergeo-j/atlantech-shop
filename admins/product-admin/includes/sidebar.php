<div class="sidebar">
    <div class="logo">
        <img src="../assets/img/logo.png" alt="Atlantech" style="width:54px;height:54px;object-fit:contain;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
        <span>ATLANTECH</span>
        <small>Product Admin</small>
    </div>
    
    <nav class="menu">
        <a href="dashboard.php" class="menu-item <?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="products-list.php" class="menu-item <?php echo ($current_page ?? '') === 'products' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i>
            <span>Produits</span>
        </a>
        
        <a href="product-add.php" class="menu-item <?php echo ($current_page ?? '') === 'add-product' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Ajouter Produit</span>
        </a>
        
        <a href="categories-list.php" class="menu-item <?php echo ($current_page ?? '') === 'categories' ? 'active' : ''; ?>">
            <i class="fas fa-folder"></i>
            <span>Catégories</span>
        </a>
        
        <a href="brands-list.php" class="menu-item <?php echo ($current_page ?? '') === 'brands' ? 'active' : ''; ?>">
            <i class="fas fa-tag"></i>
            <span>Marques</span>
        </a>
        
        <a href="stock-alerts.php" class="menu-item <?php echo ($current_page ?? '') === 'stock-alerts' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Alertes Stock</span>
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
            <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'PA', 0, 2)); ?>
        </div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Product Admin'); ?></div>
            <div class="user-role">Product Administrator</div>
        </div>
    </div>
</div>