/**
 * Header components (notifications, calendar, etc.) functionality
 */

function initHeader() {
    if (window.__sfHeaderInited) {
        return;
    }
    window.__sfHeaderInited = true;
    initNotifications();
    initProfileDropdown();
    initColorPalette();
    initEmpresaSelector();
    setupHeaderEscapeKey();
}

document.addEventListener('DOMContentLoaded', initHeader);

// Se o script for carregado no final da página, o DOM já pode estar pronto
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(initHeader, 0);
}

/**
 * Inicializar dropdown de notificações operacionais
 */
function initNotifications() {
    const notificationBtn = document.getElementById('notificationBtn');
    const badge = document.getElementById('notificationBadge');
    const sf = window.sfNotif;

    if (!notificationBtn || !sf) return;

    const notificationDropdown = document.getElementById('notificationDropdown');
    if (!notificationDropdown) return;

    const notificationClearBtn = notificationDropdown.querySelector('.notification-clear-btn');
    const notificationList = notificationDropdown.querySelector('.notification-list');

    sf.bindItemClicks(notificationList);

    notificationList.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-notif-acao]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        sf.runAction(btn.getAttribute('data-notif-acao'), btn.getAttribute('data-notif-ref'), btn.getAttribute('data-notif-id'))
            .then(data => { if (data && data.success) carregarNotificacoesOperacional(false); });
    });

    function carregarNotificacoesOperacional(verTodas = false) {
        const url = sf.buildListUrl('operacional', verTodas);
        fetch(url, { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            return res.json();
        })
        .then(data => {
            if (!notificationList) return;
            notificationList.innerHTML = '';

            if (data.success && data.notificacoes && data.notificacoes.length) {
                const html = data.notificacoes.map(n => sf.renderItem(n, { showIaTip: false })).join('');
                notificationList.innerHTML = html;
            } else if (data.sistema_ativo === false) {
                notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Notificações desativadas nas configurações.</div>';
            } else {
                notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Nenhuma notificação operacional.</div>';
            }

            const total = data.total_nao_lidas ?? data.total_real_nao_lidas ?? 0;
            sf.updateBadge(badge, total);
        })
        .catch(error => {
            console.error('Erro ao carregar notificações:', error);
            if (notificationList) {
                notificationList.innerHTML = '<div style="padding:16px;color:#dc3545">Erro ao carregar notificações. Tente novamente.</div>';
            }
            if (badge) {
                badge.textContent = '!';
                badge.style.display = 'flex';
                badge.style.backgroundColor = '#dc3545';
            }
        });
    }

    window.carregarNotificacoesOperacional = carregarNotificacoesOperacional;

    sf.refreshBadges();

    notificationBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        const isOpen = notificationDropdown.classList.contains('show');
        notificationBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) carregarNotificacoesOperacional(false);
    });

    const viewAllLink = notificationDropdown.querySelector('.notification-footer a.view-all-link')
        || notificationDropdown.querySelector('a.view-all-link');
    if (viewAllLink) {
        viewAllLink.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            carregarNotificacoesOperacional(true);
            notificationDropdown.classList.add('show');
            notificationBtn.setAttribute('aria-expanded', 'true');
            if (notificationList) notificationList.scrollTop = 0;
        });
    }

    document.addEventListener('click', function(e) {
        if (notificationDropdown.classList.contains('show') &&
            !notificationDropdown.contains(e.target) &&
            e.target !== notificationBtn) {
            notificationDropdown.classList.remove('show');
            notificationBtn.setAttribute('aria-expanded', 'false');
        }
    });

    notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    if (notificationClearBtn) {
        notificationClearBtn.addEventListener('click', function() {
            fetch(sfAppUrl('notificacoes/limpar_notificacoes.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: sf.mutationHeaders()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        notificationList.innerHTML = '<div style="padding:16px;color:#b8c2d0">Nenhuma notificação operacional.</div>';
                        sf.updateBadge(badge, data.operacional ?? 0);
                        sf.updateBadge(document.getElementById('iaNotificationBadge'), data.ia ?? 0);
                    }
                });
        });
    }

    function verificarPendenciasHeader() {
        fetch(sfAppUrl('notificacoes/sync_all.php'), { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => { if (data.success) sf.refreshBadges(); })
            .catch(err => console.error('sync notificacoes:', err));
    }

    verificarPendenciasHeader();
    setInterval(verificarPendenciasHeader, 5 * 60 * 1000);
    setInterval(function () { sf.refreshBadges(); }, 2 * 60 * 1000);
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
    
    function setProfileOpen(isOpen) {
        profileDropdown.classList.toggle('show', isOpen);
        userProfileBtn.classList.toggle('active', isOpen);
        userProfileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    // Toggle do dropdown de perfil
    userProfileBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var willOpen = !profileDropdown.classList.contains('show');
        setProfileOpen(willOpen);
    });
    
    // Fechar o dropdown ao clicar fora dele
    document.addEventListener('click', function(e) {
        if (profileDropdown.classList.contains('show') && 
            !profileDropdown.contains(e.target) && 
            e.target !== userProfileBtn && 
            !userProfileBtn.contains(e.target)) {
            setProfileOpen(false);
        }
    });
    
    // Prevenir o fechamento ao clicar dentro do dropdown
    profileDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    window.__profileDropdownInited = true;
}

/**
 * Fecha dropdowns do header com Escape e devolve o foco ao botão correspondente
 */
function setupHeaderEscapeKey() {
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') {
            return;
        }

        var notificationDropdown = document.getElementById('notificationDropdown');
        var notificationBtn = document.getElementById('notificationBtn');
        if (notificationDropdown && notificationDropdown.classList.contains('show') && notificationBtn) {
            notificationDropdown.classList.remove('show');
            notificationBtn.setAttribute('aria-expanded', 'false');
            notificationBtn.focus();
            e.preventDefault();
            return;
        }

        var empresaDropdown = document.getElementById('empresaDropdown');
        var empresaSelectorBtn = document.getElementById('empresaSelectorBtn');
        if (empresaDropdown && empresaDropdown.classList.contains('show') && empresaSelectorBtn) {
            empresaDropdown.classList.remove('show');
            empresaSelectorBtn.setAttribute('aria-expanded', 'false');
            empresaSelectorBtn.focus();
            e.preventDefault();
            return;
        }

        var profileDropdown = document.getElementById('profileDropdown');
        var userProfileBtn = document.getElementById('userProfileBtn');
        if (profileDropdown && profileDropdown.classList.contains('show') && userProfileBtn) {
            profileDropdown.classList.remove('show');
            userProfileBtn.classList.remove('active');
            userProfileBtn.setAttribute('aria-expanded', 'false');
            userProfileBtn.focus();
            e.preventDefault();
        }
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
        var isOpen = empresaDropdown.classList.contains('show');
        empresaSelectorBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        // Se abrindo, carregar empresas
        if (isOpen) {
            carregarEmpresas();
        }
    });
    
    // Fechar ao clicar fora
    document.addEventListener('click', function(e) {
        if (!empresaDropdown.contains(e.target) && !empresaSelectorBtn.contains(e.target)) {
            empresaDropdown.classList.remove('show');
            empresaSelectorBtn.setAttribute('aria-expanded', 'false');
        }
    });
    
    // Carregar lista de empresas
    function carregarEmpresas() {
        empresaDropdownList.innerHTML = '<div style="padding: 12px; text-align: center; color: #999;">Carregando...</div>';
        
        fetch(sfApiUrl('trocar_empresa.php?action=listar'), {
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
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
            formData.append('csrf_token', window.__SF_CSRF__);
        }
        
        fetch(sfApiUrl('trocar_empresa.php?action=trocar'), {
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