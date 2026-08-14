/**
 * Atlantech Shop - Client Admin Dashboard
 * JavaScript Application
 */

// ===== SIDEBAR TOGGLE =====
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Fermer la sidebar en cliquant en dehors sur mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
});

// ===== CONFIRMATION DE SUPPRESSION =====
function confirmDelete(clientName) {
    return confirm(`Êtes-vous sûr de vouloir supprimer le client "${clientName}" ?`);
}

// ===== TOGGLE STATUS =====
function toggleStatus(clientId, clientName) {
    if (confirm(`Voulez-vous changer le statut du client "${clientName}" ?`)) {
        window.location.href = `clients-list.php?action=toggle&id=${clientId}`;
    }
}

// ===== RECHERCHE EN TEMPS RÉEL =====
const searchInput = document.getElementById('searchClients');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            document.getElementById('searchForm').submit();
        }, 500);
    });
}

// ===== GRAPHIQUE DE CROISSANCE =====
function initGrowthChart(data) {
    const canvas = document.getElementById('growthChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    const width = canvas.width = canvas.offsetWidth;
    const height = canvas.height = 200;
    
    // Trouver les valeurs min/max
    const values = data.map(d => d.count);
    const maxValue = Math.max(...values);
    const minValue = Math.min(...values);
    const range = maxValue - minValue || 1;
    
    // Calculer les points
    const points = data.map((d, i) => {
        const x = (i / (data.length - 1)) * (width - 60) + 30;
        const y = height - 40 - ((d.count - minValue) / range) * (height - 80);
        return { x, y, count: d.count, month: d.month };
    });
    
    // Dessiner le dégradé
    const gradient = ctx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, 'rgba(0, 217, 255, 0.3)');
    gradient.addColorStop(1, 'rgba(0, 217, 255, 0)');
    
    // Dessiner l'aire sous la courbe
    ctx.beginPath();
    ctx.moveTo(points[0].x, height - 40);
    points.forEach(p => ctx.lineTo(p.x, p.y));
    ctx.lineTo(points[points.length - 1].x, height - 40);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();
    
    // Dessiner la ligne
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    points.forEach(p => ctx.lineTo(p.x, p.y));
    ctx.strokeStyle = '#00d9ff';
    ctx.lineWidth = 3;
    ctx.stroke();
    
    // Dessiner les points
    points.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, 5, 0, Math.PI * 2);
        ctx.fillStyle = '#00d9ff';
        ctx.fill();
        ctx.strokeStyle = '#020817';
        ctx.lineWidth = 2;
        ctx.stroke();
    });
    
    // Dessiner les labels
    ctx.fillStyle = '#8892b0';
    ctx.font = '11px Rajdhani';
    ctx.textAlign = 'center';
    
    points.forEach((p, i) => {
        if (i % Math.ceil(data.length / 6) === 0) {
            const monthLabel = formatMonthLabel(p.month);
            ctx.fillText(monthLabel, p.x, height - 15);
            ctx.fillText(p.count, p.x, p.y - 15);
        }
    });
}

function formatMonthLabel(monthStr) {
    const months = {
        '01': 'Jan', '02': 'Fév', '03': 'Mar', '04': 'Avr',
        '05': 'Mai', '06': 'Juin', '07': 'Juil', '08': 'Août',
        '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Déc'
    };
    const [year, month] = monthStr.split('-');
    return months[month] + ' ' + year.substring(2);
}

// ===== ANIMATION DES STATISTIQUES =====
function animateValue(element, start, end, duration) {
    if (!element) return;
    
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Animer les statistiques au chargement
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-content h3');
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        stat.textContent = '0';
        animateValue(stat, 0, finalValue, 1000);
    });
});

// ===== VALIDATION DE FORMULAIRE =====
function validateClientForm() {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    if (name === '') {
        alert('Le nom est requis');
        return false;
    }
    
    if (email === '') {
        alert('L\'email est requis');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Email invalide');
        return false;
    }
    
    if (phone === '') {
        alert('Le téléphone est requis');
        return false;
    }
    
    return true;
}

// ===== FILTRES =====
const statusFilter = document.getElementById('statusFilter');
if (statusFilter) {
    statusFilter.addEventListener('change', function() {
        document.getElementById('searchForm').submit();
    });
}

// ===== SMOOTH SCROLL =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ===== AUTO-HIDE ALERTS =====
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// ===== PAGINATION =====
function changePage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

// ===== EXPORT FUNCTIONS =====
function exportClients(format) {
    window.location.href = `clients-list.php?action=export&format=${format}`;
}

// ===== TOOLTIP =====
function showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 217, 255, 0.9);
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        z-index: 10000;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
    
    setTimeout(() => {
        tooltip.remove();
    }, 2000);
}

// ===== LIVE CLOCK =====
function updateClock() {
    const clockElement = document.getElementById('liveClock');
    if (clockElement) {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        clockElement.textContent = `${hours}:${minutes}:${seconds}`;
    }
}

if (document.getElementById('liveClock')) {
    updateClock();
    setInterval(updateClock, 1000);
}

// ===== PRELOADER =====
window.addEventListener('load', function() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.style.opacity = '0';
        setTimeout(() => {
            preloader.style.display = 'none';
        }, 500);
    }
});

// ===== CONSOLE WELCOME MESSAGE =====
console.log('%c🚀 Atlantech Shop Admin Dashboard', 'color: #00d9ff; font-size: 20px; font-weight: bold;');
console.log('%cVersion 1.0.0 - Futuristic Edition', 'color: #8892b0; font-size: 12px;');
