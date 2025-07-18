function initSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('overlay');
    let isSidebarOpen = true;

    function updateMainContentMargin() {
        if (window.innerWidth >= 768) {
            mainContent.style.marginLeft = isSidebarOpen ? '16rem' : '0';
        } else {
            mainContent.style.marginLeft = '0';
        }
    }

    function toggleSidebar() {
        isSidebarOpen = !isSidebarOpen;
        
        if (!isSidebarOpen) {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        } else {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            if (window.innerWidth < 768) {
                overlay.classList.remove('hidden');
            }
        }
        updateMainContentMargin();
    }

    menuToggle.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    // Gestion responsive pour mobile
    function checkScreenSize() {
        if (window.innerWidth < 768) {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            isSidebarOpen = false;
        } else {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.add('hidden');
            isSidebarOpen = true;
        }
        updateMainContentMargin();
    }

    // Vérifier la taille de l'écran au chargement et au redimensionnement
    window.addEventListener('resize', checkScreenSize);
    checkScreenSize();
}

// Initialiser au chargement de la page
document.addEventListener('DOMContentLoaded', initSidebar);

// Réinitialiser après chaque mise à jour du composant Live
document.addEventListener('live:update', initSidebar);
