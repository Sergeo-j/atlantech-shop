</main> <!-- Fermeture main-content -->
</div> <!-- Fermeture admin-main -->
</div> <!-- Fermeture admin-layout -->

<!-- Scripts JavaScript -->
<script>
    // Toggle Sidebar pour mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        sidebar.classList.toggle('active');
    }
    
    // Fermer sidebar en cliquant en dehors sur mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('adminSidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Confirmation avant suppression
    function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
        return confirm(message);
    }
    
    // Message de succès temporaire
    function showSuccessMessage(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-success';
        alert.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        alert.style.minWidth = '300px';
        alert.style.animation = 'slideInRight 0.3s ease-out';
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            alert.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    }
    
    // Animation des alertes
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
</script>

<!-- Footer Info -->
<div style="background: var(--white); padding: 1rem 2rem; margin-top: 2rem; text-align: center; color: var(--gray-600); font-size: 0.875rem; box-shadow: var(--shadow-md);">
    © <?php echo date('Y'); ?> Atlantech Shop. Tous droits réservés. | Version 1.0.0
</div>

</body>
</html>