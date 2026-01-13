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
    
    // Inicializar seletor de empresa (se existir)
    initEmpresaSelector();
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

    // Carregar notificações no carregamento inicial da página
    carregarNotificacoesIA(false);

    // Função para carregar notificações (padrão: só não lidas, todas se true)
    function carregarNotificacoesIA(verTodas = false) {
        const url = verTodas ? '/sistema-frotas/notificacoes/notificacoes.php?todas=1' : '/sistema-frotas/notificacoes/notificacoes.php';
        fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            return res.json();
        })
        .then(data => {
            notificationList.innerHTML = '';
            let unreadCount = 0;
            
            if (data.success && data.notificacoes && data.notificacoes.length) {
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
            } else {
                notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Nenhuma notificação encontrada.</div>';
            }
            
            // Usar o total real de notificações não lidas se disponível
            const totalReal = data.total_real_nao_lidas || unreadCount;
            badge.textContent = totalReal;
            badge.style.display = totalReal > 0 ? 'flex' : 'none';
        })
        .catch(error => {
            console.error('Erro ao carregar notificações:', error);
            notificationList.innerHTML = '<div style="padding:16px;color:#dc3545">Erro ao carregar notificações. Tente novamente.</div>';
            badge.textContent = '!';
            badge.style.display = 'flex';
            badge.style.backgroundColor = '#dc3545';
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

    // Limpar notificações regulares (marca como lidas no banco e visual)
    if (notificationClearBtn) {
        notificationClearBtn.addEventListener('click', function() {
            fetch('/sistema-frotas/notificacoes/limpar_notificacoes.php', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Nenhuma notificação encontrada.</div>';
                        badge.textContent = '0';
                        badge.style.display = 'none';
                        
                        // Atualiza também o badge de IA se existir
                        const iaBadge = document.getElementById('iaNotificationBadge');
                        if (iaBadge) {
                            // Recarrega as notificações de IA para atualizar o contador
                            if (typeof window.carregarIANotificacoes === 'function') {
                                window.carregarIANotificacoes();
                            }
                        }
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
        '--bg-sidebar': '#121a29',
        '--card-bg': '#1e293b',
        '--bg-tertiary': '#243041'
    };
    
    return defaultColors[variable] || '#000000';
}

/**
 * Inicializar seletor de empresa (para usuários com acesso global)
 */
function initEmpresaSelector() {
    const empresaSelectorBtn = document.getElementById('empresaSelectorBtn');
    const empresaDropdown = document.getElementById('empresaDropdown');
    const empresaDropdownList = document.getElementById('empresaDropdownList');
    
    if (!empresaSelectorBtn || !empresaDropdown || !empresaDropdownList) return;
    
    // Toggle dropdown
    empresaSelectorBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        empresaDropdown.classList.toggle('show');
        
        // Se abrindo, carregar empresas
        if (empresaDropdown.classList.contains('show')) {
            carregarEmpresas();
        }
    });
    
    // Fechar ao clicar fora
    document.addEventListener('click', function(e) {
        if (!empresaDropdown.contains(e.target) && !empresaSelectorBtn.contains(e.target)) {
            empresaDropdown.classList.remove('show');
        }
    });
    
    // Carregar lista de empresas
    function carregarEmpresas() {
        empresaDropdownList.innerHTML = '<div style="padding: 12px; text-align: center; color: #999;">Carregando...</div>';
        
        fetch('/sistema-frotas/api/trocar_empresa.php?action=listar', {
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.empresas) {
                empresaDropdownList.innerHTML = '';
                
                if (data.empresas.length === 0) {
                    empresaDropdownList.innerHTML = '<div style="padding: 12px; text-align: center; color: #999;">Nenhuma empresa encontrada</div>';
                    return;
                }
                
                data.empresas.forEach(empresa => {
                    const item = document.createElement('div');
                    item.className = 'empresa-dropdown-item';
                    if (empresa.id == data.empresa_atual) {
                        item.classList.add('active');
                    }
                    
                    item.innerHTML = `
                        <h4>${empresa.razao_social}</h4>
                        ${empresa.nome_fantasia ? `<p>${empresa.nome_fantasia}</p>` : ''}
                    `;
                    
                    item.addEventListener('click', function() {
                        if (empresa.id != data.empresa_atual) {
                            trocarEmpresa(empresa.id);
                        }
                    });
                    
                    empresaDropdownList.appendChild(item);
                });
            } else {
                empresaDropdownList.innerHTML = '<div style="padding: 12px; text-align: center; color: #c33;">Erro ao carregar empresas</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar empresas:', error);
            empresaDropdownList.innerHTML = '<div style="padding: 12px; text-align: center; color: #c33;">Erro ao carregar empresas</div>';
        });
    }
    
    // Trocar de empresa
    function trocarEmpresa(empresaId) {
        const formData = new FormData();
        formData.append('empresa_id', empresaId);
        
        fetch('/sistema-frotas/api/trocar_empresa.php?action=trocar', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Recarregar a página para atualizar dados
                window.location.reload();
            } else {
                alert('Erro ao trocar de empresa: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro ao trocar empresa:', error);
            alert('Erro ao trocar de empresa');
        });
    }
}