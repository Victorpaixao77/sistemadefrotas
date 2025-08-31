/**
 * Header components (notifications, calendar, etc.) functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar dropdown de notificações
    initNotifications();
    
    // Inicializar dropdown de perfil
    initProfileDropdown();
    
    // Inicializar seletor de cores
    initColorPalette();
});

/**
 * Inicializar dropdown de notificações
 */
function initNotifications() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationClearBtn = document.querySelector('.notification-clear-btn');
    const notificationList = notificationDropdown.querySelector('.notification-list');
    const badge = document.getElementById('notificationBadge');

    if (!notificationBtn || !notificationDropdown) return;

    // Função para carregar notificações (padrão: só não lidas, todas se true)
    function carregarNotificacoesIA(verTodas = false) {
        const url = verTodas ? '/sistema-frotas/notificacoes/notificacoes.php?todas=1' : '/sistema-frotas/notificacoes/notificacoes.php';
        fetch(url)
            .then(res => res.json())
            .then(data => {
                notificationList.innerHTML = '';
                let unreadCount = 0;
                if (data.success && data.notificacoes.length)
                    data.notificacoes.forEach(n => {
                        let iconClass = 'info';
                        if (n.tipo === 'manutencao') iconClass = 'warning';
                        if (n.tipo === 'alerta') iconClass = 'warning';
                        if (n.tipo === 'pneu') iconClass = 'success';
                        const unread = n.lida == 0 ? 'unread' : 'lida';
                        if (unread === 'unread') unreadCount++;
                        notificationList.innerHTML += `
                            <div class="notification-item ${unread}">
                                <div class="notification-icon ${iconClass}">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">${n.titulo}</div>
                                    <div class="notification-text">${n.mensagem}</div>
                                    <div class="notification-time">${new Date(n.data_criacao).toLocaleString('pt-BR')}</div>
                                </div>
                            </div>
                        `;
                    });
                else {
                    notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Nenhuma notificação encontrada.</div>';
                }
                badge.textContent = unreadCount;
                badge.style.display = unreadCount > 0 ? 'flex' : 'none';
            });
    }

    // Toggle do dropdown de notificações
    notificationBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (notificationDropdown.classList.contains('show')) {
            carregarNotificacoesIA(false);
        }
    });

    // 'Ver todas' mostra todas as notificações
    const viewAllLink = document.querySelector('.view-all-link');
    if (viewAllLink) {
        viewAllLink.addEventListener('click', function(e) {
            e.preventDefault();
            carregarNotificacoesIA(true);
        });
    }

    // Fechar o dropdown ao clicar fora dele
    document.addEventListener('click', function(e) {
        if (notificationDropdown.classList.contains('show') && 
            !notificationDropdown.contains(e.target) && 
            e.target !== notificationBtn) {
            notificationDropdown.classList.remove('show');
        }
    });

    // Prevenir o fechamento ao clicar dentro do dropdown
    notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Limpar todas as notificações (marca como lidas no banco e visual)
    if (notificationClearBtn) {
        notificationClearBtn.addEventListener('click', function() {
            fetch('/sistema-frotas/notificacoes/limpar_notificacoes.php', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Nenhuma notificação encontrada.</div>';
                        badge.textContent = '0';
                        badge.style.display = 'none';
                    }
                });
        });
    }
}

/**
 * Atualizar o contador de notificações não lidas
 */
function updateNotificationCount() {
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    const badge = document.querySelector('.notification-badge');
    
    if (badge) {
        badge.textContent = unreadItems.length;
        
        // Ocultar o badge se não houver notificações não lidas
        if (unreadItems.length === 0) {
            badge.style.display = 'none';
        } else {
            badge.style.display = 'flex';
        }
    }
}



/**
 * Inicializar dropdown de perfil
 */
function initProfileDropdown() {
    const userProfileBtn = document.getElementById('userProfileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (!userProfileBtn || !profileDropdown) return;
    
    // Toggle do dropdown de perfil
    userProfileBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
        userProfileBtn.classList.toggle('active');
    });
    
    // Fechar o dropdown ao clicar fora dele
    document.addEventListener('click', function(e) {
        if (profileDropdown.classList.contains('show') && 
            !profileDropdown.contains(e.target) && 
            e.target !== userProfileBtn && 
            !userProfileBtn.contains(e.target)) {
            profileDropdown.classList.remove('show');
            userProfileBtn.classList.remove('active');
        }
    });
    
    // Prevenir o fechamento ao clicar dentro do dropdown
    profileDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

/**
 * Inicializar seletor de cores
 */
function initColorPalette() {
    // Obter todos os inputs de cores
    const colorInputs = document.querySelectorAll('input[type="color"]');
    const restoreBtn = document.getElementById('restoreDefaultColors');
    
    if (!colorInputs.length || !restoreBtn) return;
    
    // Carregar cores salvas do localStorage
    loadSavedColors();
    
    // Adicionar event listeners para os inputs de cores
    colorInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Obter a variável CSS a ser alterada
            const cssVar = this.dataset.colorVar;
            if (cssVar) {
                document.documentElement.style.setProperty(cssVar, this.value);
                saveColorPreference(cssVar, this.value);
            }
        });
    });
    
    // Restaurar cores padrão
    restoreBtn.addEventListener('click', function() {
        colorInputs.forEach(input => {
            const cssVar = input.dataset.colorVar;
            if (cssVar) {
                const defaultValue = getDefaultColorValue(cssVar);
                input.value = defaultValue;
                document.documentElement.style.setProperty(cssVar, defaultValue);
                localStorage.removeItem(cssVar);
            }
        });
    });
}

/**
 * Carregar cores salvas do localStorage
 */
function loadSavedColors() {
    const colorInputs = document.querySelectorAll('input[type="color"]');
    
    colorInputs.forEach(input => {
        const cssVar = input.dataset.colorVar;
        if (cssVar) {
            const savedColor = localStorage.getItem(cssVar);
            if (savedColor) {
                input.value = savedColor;
                document.documentElement.style.setProperty(cssVar, savedColor);
            }
        }
    });
}

/**
 * Salvar preferência de cor no localStorage
 */
function saveColorPreference(variable, value) {
    localStorage.setItem(variable, value);
}

/**
 * Obter valor padrão de uma variável de cor
 */
function getDefaultColorValue(variable) {
    const defaultColors = {
        '--accent-primary': '#3b82f6',
        '--bg-secondary': '#1a2332',
        '--bg-primary': '#0f1824',
        '--bg-sidebar': '#121a29'
    };
    
    return defaultColors[variable] || '#000000';
}