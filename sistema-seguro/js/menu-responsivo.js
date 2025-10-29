// ===== MENU LATERAL RESPONSIVO =====

console.log('=== SISTEMA SEGURO - MENU LATERAL ===');

// Elementos
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const mainContent = document.querySelector('.main-content');

console.log('✅ Elementos carregados');
console.log('Largura da tela:', window.innerWidth);

// Função para verificar se é mobile
function isMobile() {
    return window.innerWidth <= 768;
}

// Função para abrir/fechar menu
function toggleMenu() {
    const isOpen = sidebar.classList.contains('show');
    
    if (isOpen) {
        // Fechar menu
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        mainContent.classList.remove('menu-open');
        console.log('Menu fechado');
    } else {
        // Abrir menu
        sidebar.classList.add('show');
        mainContent.classList.add('menu-open');
        
        // Só mostra overlay em mobile
        if (isMobile()) {
            sidebarOverlay.classList.add('show');
        }
        console.log('Menu aberto');
    }
}

// Função para fechar menu
function closeMenu() {
    sidebar.classList.remove('show');
    sidebarOverlay.classList.remove('show');
    mainContent.classList.remove('menu-open');
    console.log('Menu fechado');
}

// Event listeners
if (menuToggle) {
    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleMenu();
    });
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeMenu();
    });
}

// Fechar menu ao clicar em um link
document.querySelectorAll('.sidebar .nav-link').forEach(link => {
    link.addEventListener('click', function() {
        closeMenu();
    });
});

// Ao redimensionar, ajustar comportamento
window.addEventListener('resize', function() {
    const isOpen = sidebar.classList.contains('show');
    
    if (isOpen && !isMobile()) {
        // Se está aberto e mudou para desktop, remover overlay
        sidebarOverlay.classList.remove('show');
    }
    
    console.log('Tela redimensionada:', window.innerWidth);
});

console.log('✅ Script carregado com sucesso!');

