<!-- SIDEBAR ADMIN -->
<aside class="admin-sidebar" id="adminSidebar">
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">
            <div class="nav-section-title">Principal</div>
            <a href="admin_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="analytics_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
        </div>
        
        <!-- E-commerce -->
        <div class="nav-section">
            <div class="nav-section-title">E-commerce</div>
            <a href="products-manager.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'products-manager.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Produits</span>
                <span class="nav-badge"><?php echo fetchColumn("SELECT COUNT(*) FROM products") ?? 0; ?></span>
            </a>
            <a href="categories-manager.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories-manager.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Catégories</span>
            </a>
            <a href="orders-manager.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
                <span class="nav-badge bg-danger"><?php echo fetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'") ?? 0; ?></span>
            </a>
            <a href="customers-manager.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Clients</span>
            </a>
            <a href="reviews-manager.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Avis</span>
            </a>
        </div>
        
        <!-- Marketing -->
        <div class="nav-section">
            <div class="nav-section-title">Marketing</div>
            <a href="coupons-manager.php" class="nav-item">
                <i class="fas fa-ticket-alt"></i>
                <span>Coupons</span>
            </a>
            <a href="flash-sales-manager.php" class="nav-item">
                <i class="fas fa-bolt"></i>
                <span>Ventes Flash</span>
            </a>
            <a href="banners-manager.php" class="nav-item">
                <i class="fas fa-image"></i>
                <span>Bannières</span>
            </a>
            <a href="email-campaigns.php" class="nav-item">
                <i class="fas fa-envelope"></i>
                <span>Campagnes Email</span>
            </a>
        </div>
        
        <!-- Paramètres -->
        <div class="nav-section">
            <div class="nav-section-title">Système</div>
            <a href="taux-change.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'taux-change.php' ? 'active' : ''; ?>">
                <i class="fas fa-dollar-sign"></i>
                <span>Taux de Change</span>
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <a href="users_management.php" class="nav-item">
                <i class="fas fa-user-shield"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="storage-info">
            <div class="storage-header">
                <span>Stockage</span>
                <span>67%</span>
            </div>
            <div class="storage-bar">
                <div class="storage-fill" style="width: 67%"></div>
            </div>
            <small>6.7 GB utilisés sur 10 GB</small>
        </div>
    </div>
</aside>

<style>
    /* SIDEBAR */
    .admin-sidebar {
        position: fixed;
        left: 0;
        top: 60px;
        width: var(--sidebar-width);
        height: calc(100vh - 60px);
        background: white;
        border-right: 1px solid #e5e7eb;
        overflow-y: auto;
        z-index: 999;
        display: flex;
        flex-direction: column;
    }
    
    .sidebar-nav {
        flex: 1;
        padding: 16px;
    }
    
    .nav-section {
        margin-bottom: 24px;
    }
    
    .nav-section-title {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: 8px;
        padding: 0 12px;
        letter-spacing: 0.5px;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        color: #6b7280;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 4px;
        transition: all 0.2s;
        position: relative;
    }
    
    .nav-item:hover {
        background: #f3f4f6;
        color: #111827;
    }
    
    .nav-item.active {
        background: #eff6ff;
        color: var(--primary-color);
    }
    
    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 20px;
        background: var(--primary-color);
        border-radius: 0 3px 3px 0;
    }
    
    .nav-item i {
        font-size: 18px;
        width: 20px;
        text-align: center;
    }
    
    .nav-item span {
        flex: 1;
    }
    
    .nav-badge {
        padding: 2px 8px;
        background: #e5e7eb;
        color: #6b7280;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .nav-badge.bg-danger {
        background: #fee2e2;
        color: var(--danger-color);
    }
    
    /* Sidebar Footer */
    .sidebar-footer {
        padding: 16px;
        border-top: 1px solid #e5e7eb;
    }
    
    .storage-info {
        padding: 12px;
        background: #f9fafb;
        border-radius: 8px;
    }
    
    .storage-header {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 8px;
    }
    
    .storage-bar {
        height: 6px;
        background: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: 6px;
    }
    
    .storage-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        border-radius: 3px;
    }
    
    .storage-info small {
        font-size: 11px;
        color: #9ca3af;
    }
    
    /* Scrollbar personnalisé */
    .admin-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .admin-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .admin-sidebar::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }
    
    .admin-sidebar::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
    
    /* Mobile */
    @media (max-width: 768px) {
        .admin-sidebar {
            left: calc(-1 * var(--sidebar-width));
            transition: left 0.3s;
        }
        
        .admin-sidebar.mobile-show {
            left: 0;
            box-shadow: 4px 0 12px rgba(0,0,0,0.1);
        }
    }
</style>

<script>
// Toggle mobile menu
document.getElementById('menuToggle')?.addEventListener('click', function() {
    document.getElementById('adminSidebar')?.classList.toggle('mobile-show');
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('adminSidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    if (window.innerWidth <= 768 && 
        sidebar?.classList.contains('mobile-show') && 
        !sidebar.contains(e.target) && 
        !menuToggle?.contains(e.target)) {
        sidebar.classList.remove('mobile-show');
    }
});
</script>