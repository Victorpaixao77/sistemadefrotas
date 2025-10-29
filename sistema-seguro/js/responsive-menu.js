// Menu Responsivo - Sistema Seguro
document.addEventListener('DOMContentLoaded', function() {
    // Elementos do menu
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Verificar se os elementos existem
    if (!menuToggle || !sidebar || !sidebarOverlay) {
        console.warn('Elementos do menu responsivo não encontrados');
        return;
    }

    // Função para abrir/fechar menu
    function toggleMenu() {
        console.log('Toggle menu clicked');
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
        
        // Debug info
        console.log('Sidebar classes:', sidebar.className);
        console.log('Overlay classes:', sidebarOverlay.className);
    }

    // Função para fechar menu
    function closeMenu() {
        console.log('Closing menu');
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    }

    // Event listeners
    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleMenu();
    });

    sidebarOverlay.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
    });

    // Fechar menu ao clicar em um link (mobile)
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                console.log('Link clicked, closing menu');
                closeMenu();
            }
        });
    });

    // Fechar menu ao redimensionar para desktop
    window.addEventListener('resize', function() {
        console.log('Window resized to:', window.innerWidth);
        if (window.innerWidth > 768) {
            closeMenu();
        }
    });

    // Debug: Adicionar informações de debug (remover em produção)
    function addDebugInfo() {
        const debugDiv = document.createElement('div');
        debugDiv.className = 'debug-info';
        debugDiv.innerHTML = `
            <div>Width: ${window.innerWidth}px</div>
            <div>Sidebar: ${sidebar.classList.contains('show') ? 'Open' : 'Closed'}</div>
            <div>Overlay: ${sidebarOverlay.classList.contains('show') ? 'Visible' : 'Hidden'}</div>
        `;
        document.body.appendChild(debugDiv);
        
        // Atualizar debug info a cada segundo
        setInterval(() => {
            debugDiv.innerHTML = `
                <div>Width: ${window.innerWidth}px</div>
                <div>Sidebar: ${sidebar.classList.contains('show') ? 'Open' : 'Closed'}</div>
                <div>Overlay: ${sidebarOverlay.classList.contains('show') ? 'Visible' : 'Hidden'}</div>
            `;
        }, 1000);
    }

    // Adicionar debug info apenas em desenvolvimento
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        addDebugInfo();
    }

    console.log('Menu responsivo inicializado');
});
