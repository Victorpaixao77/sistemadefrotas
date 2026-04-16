// Motorists management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...');
    
    // Initialize page
    initializePage();
    
    // Setup modal events
    setupModals();
    
    // Setup filters
    setupFilters();
    
    // Setup form submission
    setupFormSubmission();
    
    // Setup modal close buttons
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Setup help button
    setupHelpButton();

    // Garantir que o botão cancelar sempre fecha o modal, mesmo se renderizado depois
    setTimeout(function() {
        const cancelBtn = document.getElementById('cancelMotoristBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal('motoristModal');
            });
        }
    }, 500);
});

let currentPage = 1;
let totalPages = 1;
let currentMotoristId = null;
let performanceChart = null;
let currentSort = 'nome';
let currentOrder = 'ASC';

function initializePage() {
    const urlParams = new URLSearchParams(window.location.search);
    const page = parseInt(urlParams.get('page'), 10) || 1;
    const perPageFromUrl = parseInt(urlParams.get('per_page'), 10);
    const perPageEl = document.getElementById('perPageMotorists');
    if (perPageEl && !Number.isNaN(perPageFromUrl) && [5, 10, 25, 50, 100].indexOf(perPageFromUrl) >= 0) {
        perPageEl.value = String(perPageFromUrl);
    }
    if (perPageEl && document.body.classList.contains('motorists-modern')) {
        perPageEl.addEventListener('change', () => loadMotorists(1));
    }
    // Load initial data
    loadMotorists(page);
    
    // Setup button events
    document.getElementById('addMotoristBtn')?.addEventListener('click', showAddMotoristModal);
    
    // Setup table buttons
    setupTableButtons();
    
    // Delegação de cliques na lista (tabela/cards) para que Editar, Visualizar, Histórico e Excluir
    // continuem funcionando após abrir/fechar o modal de histórico
    const listContainer = document.getElementById('motoristsTableContainer');
    if (listContainer) {
        listContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.view-btn, .edit-btn, .history-btn, .delete-btn');
            if (!btn) return;
            const motoristId = btn.getAttribute('data-id');
            if (!motoristId) return;
            e.preventDefault();
            e.stopPropagation();
            if (btn.classList.contains('view-btn')) {
                loadMotoristDetails(motoristId);
            } else if (btn.classList.contains('edit-btn')) {
                loadMotoristForEdit(motoristId);
            } else if (btn.classList.contains('history-btn')) {
                openMotoristLogModal(motoristId);
            } else if (btn.classList.contains('delete-btn')) {
                showDeleteConfirmation(motoristId);
            }
        });
    }
    
    // Setup pagination
    setupPagination();
    
    // Setup help button
    setupHelpButton();
    
    // Máscaras e atalhos
    setupInputMasks();
    setupKeyboardShortcuts();
    
    // Carregar opções dos filtros categoria/tipo contrato
    loadFilterOptions();
    
    // Ordenação por coluna
    setupSortableColumns();
    
    // Exportar CSV
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) exportBtn.addEventListener('click', exportMotoristsCSV);
    
    // Alertas de vencimento de CNH
    loadCnhAlertas();
    
    // Filtro "Só favoritos" e modo de visualização (tabela/cards)
    const filterFav = document.getElementById('filterOnlyFavoritos');
    if (filterFav) filterFav.addEventListener('change', () => loadMotorists(1));
    document.querySelectorAll('.view-mode-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.getAttribute('data-mode');
            localStorage.setItem('motorists_view_mode', mode);
            document.querySelectorAll('.view-mode-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadMotorists(currentPage);
        });
    });
    const savedMode = localStorage.getItem('motorists_view_mode') || 'table';
    document.querySelectorAll('.view-mode-btn').forEach(b => {
        b.classList.toggle('active', b.getAttribute('data-mode') === savedMode);
    });
    
    // Compartilhar: se URL tiver view_id= ou id=, abrir modal de visualização
    const paramsView = new URLSearchParams(window.location.search);
    const viewId = paramsView.get('view_id') || paramsView.get('id');
    if (viewId && /^\d+$/.test(viewId)) {
        setTimeout(function() { loadMotoristDetails(viewId); }, 500);
    }
}

function loadCnhAlertas() {
    const widget = document.getElementById('cnhAlertasWidget');
    const content = document.getElementById('cnhAlertasContent');
    if (!widget || !content) return;
    fetch('../api/motorist_data.php?action=cnh_alertas')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const a30 = data.em_30_dias || [];
            const a60 = data.em_60_dias || [];
            const a90 = data.em_90_dias || [];
            if (a30.length === 0 && a60.length === 0 && a90.length === 0) {
                widget.style.display = 'none';
                return;
            }
            const parts = [];
            if (a30.length > 0) parts.push(`<span style="color: var(--danger-color,#dc3545);"><strong>${a30.length}</strong> em até 30 dias: ${a30.map(m => m.nome).join(', ')}</span>`);
            if (a60.length > 0) parts.push(`<span style="color: var(--warning-color,#f59e0b);"><strong>${a60.length}</strong> em 31–60 dias: ${a60.map(m => m.nome).join(', ')}</span>`);
            if (a90.length > 0) parts.push(`<span style="color: var(--info-color,#3b82f6);"><strong>${a90.length}</strong> em 61–90 dias: ${a90.map(m => m.nome).join(', ')}</span>`);
            content.innerHTML = parts.join(' &nbsp;|&nbsp; ');
            widget.style.display = 'block';
        })
        .catch(() => { widget.style.display = 'none'; });
}

function setupModals() {
    // Close modal when clicking outside (exclude help modal)
    document.querySelectorAll('.modal:not(#helpMotoristsModal)').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });
    });
    
    // Close modal when clicking X button (exclude help modal)
    document.querySelectorAll('.modal:not(#helpMotoristsModal) .close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Close modal when clicking cancel button (usar closeModal para não deixar inline style que impede reabrir)
    document.getElementById('cancelMotoristBtn')?.addEventListener('click', function() {
        closeModal('motoristModal');
    });
    
    // Special handling for help modal
    const helpModal = document.getElementById('helpMotoristsModal');
    if (helpModal) {
        // Close help modal when clicking outside
        helpModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
        
        // Close help modal when clicking X button
        const helpCloseBtn = helpModal.querySelector('.close-modal');
        if (helpCloseBtn) {
            helpCloseBtn.addEventListener('click', function() {
                helpModal.classList.remove('active');
            });
        }
    }
}

function switchTab(tabId) {
    console.log('Switching to tab:', tabId, 'Current motorist ID:', currentMotoristId);
    
    if (!currentMotoristId) {
        console.error('No motorist selected');
        showNotification('Nenhum motorista selecionado', 'error');
        return;
    }

    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked tab and its content
    const tabButton = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
    const tabContent = document.getElementById(tabId);
    
    if (!tabButton || !tabContent) {
        console.error('Tab elements not found:', { tabId, tabButton, tabContent });
        return;
    }
    
    tabButton.classList.add('active');
    tabContent.classList.add('active');
    
    // Garantir que o container do gráfico existe
    if (tabId === 'performance') {
        const performanceTab = document.getElementById('performance');
        if (!performanceTab.querySelector('#performanceChart')) {
            performanceTab.innerHTML = `
                <div class="metrics-grid">
                    <div class="metric-card">
                        <h3>Avaliação Média</h3>
                        <p id="averageRating">0.0</p>
                    </div>
                    <div class="metric-card">
                        <h3>Total de Viagens</h3>
                        <p id="totalTrips">0</p>
                    </div>
                    <div class="metric-card">
                        <h3>Distância Total</h3>
                        <p id="totalDistance">0 km</p>
                    </div>
                    <div class="metric-card">
                        <h3>Consumo Médio</h3>
                        <p id="averageConsumption">0.0 L/100km</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            `;
        }
    }
    
    // Load tab-specific data
    console.log('Loading data for tab:', tabId);
    switch(tabId) {
        case 'routeHistory':
            initializeRouteHistoryTab();
            loadRouteHistory(currentMotoristId);
            break;
        case 'performance':
            loadPerformanceMetrics(currentMotoristId);
            break;
        case 'documents':
            loadDocuments(currentMotoristId);
            break;
    }
}

function loadMotorists(page = 1) {
    currentPage = page;
    const perPageSelect = document.getElementById('perPageMotorists');
    const perPageFromSelect = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const limit = [5, 10, 25, 50, 100].indexOf(perPageFromSelect) >= 0 ? perPageFromSelect : 10;

    const searchInput = document.getElementById('searchMotorist');
    const statusFilter = document.getElementById('statusFilter');

    const params = new URLSearchParams();
    params.append('action', 'list');
    params.append('page', page);
    params.append('limit', limit);

    const searchTerm = searchInput ? searchInput.value.trim() : '';
    if (searchTerm) {
        params.append('search', searchTerm);
    }

    const statusValue = statusFilter ? statusFilter.value : '';
    if (statusValue) {
        params.append('status', statusValue);
    }
    const catCnh = document.getElementById('categoriaCnhFilter');
    if (catCnh && catCnh.value) params.append('categoria_cnh', catCnh.value);
    const tipoContrato = document.getElementById('tipoContratoFilter');
    if (tipoContrato && tipoContrato.value) params.append('tipo_contrato', tipoContrato.value);
    params.append('sort', currentSort);
    params.append('order', currentOrder);

    const filterOnlyFav = document.getElementById('filterOnlyFavoritos');
    if (filterOnlyFav && filterOnlyFav.checked) params.append('only_favoritos', '1');

    fetch(`../api/motorist_data.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showNotification(data.error || 'Erro ao carregar motoristas', 'error');
                return;
            }
            
            // Update statistics
            if (data.summary) {
                document.getElementById('totalMotorists').textContent = data.summary.total_motorists || 0;
                document.getElementById('activeMotorists').textContent = data.summary.motorists_ativos || 0;
                
                // Update Status KPI - show distribution with better formatting
                const statusItems = [];
                if (data.summary.motorists_ativos > 0) statusItems.push(`${data.summary.motorists_ativos} Ativos`);
                if (data.summary.motorists_ferias > 0) statusItems.push(`${data.summary.motorists_ferias} Férias`);
                if (data.summary.motorists_licenca > 0) statusItems.push(`${data.summary.motorists_licenca} Licença`);
                if (data.summary.motorists_inativos > 0) statusItems.push(`${data.summary.motorists_inativos} Inativos`);
                if (data.summary.motorists_afastados > 0) statusItems.push(`${data.summary.motorists_afastados} Afastados`);
                
                if (statusItems.length > 0) {
                    document.getElementById('totalTrips').textContent = statusItems.join(', ');
                } else {
                    document.getElementById('totalTrips').textContent = 'N/A';
                }
                
                // Update Commission KPI - show total paid this month
                if (data.summary.total_comissao_mes > 0 && data.summary.motorists_com_comissao_mes > 0) {
                    const totalComissao = parseFloat(data.summary.total_comissao_mes) || 0;
                    document.getElementById('averageRating').textContent = `R$ ${totalComissao.toFixed(2)}`;
                    
                    const subtitle = document.querySelector('#averageRating + .metric-subtitle');
                    if (subtitle) {
                        subtitle.textContent = `${data.summary.motorists_com_comissao_mes} motoristas`;
                    }
                } else {
                    document.getElementById('averageRating').textContent = 'R$ 0,00';
                    const subtitle = document.querySelector('#averageRating + .metric-subtitle');
                    if (subtitle) {
                        subtitle.textContent = 'Sem comissão no mês';
                    }
                }
                
                // Log para debug
                console.log('📊 KPIs atualizados:', data.summary);
            }
            
            // Update pagination
            if (data.pagination) {
                totalPages = data.pagination.total_pages;

                const currentPageElement = document.getElementById('currentPage');
                if (currentPageElement) {
                    currentPageElement.textContent = page;
                }

                const totalPagesElement = document.getElementById('totalPages');
                if (totalPagesElement) {
                    totalPagesElement.textContent = totalPages;
                }

                updatePaginationButtons(data.pagination);
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', page);
                urlParams.set('per_page', limit);
                if (document.body.classList.contains('motorists-modern')) {
                    urlParams.delete('classic');
                } else {
                    urlParams.set('classic', '1');
                }
                const isDefault = parseInt(page, 10) === 1 && parseInt(limit, 10) === 10;
                let desiredSearch;
                if (isDefault && document.body.classList.contains('motorists-modern')) {
                    desiredSearch = '';
                } else if (isDefault) {
                    desiredSearch = '?classic=1';
                } else {
                    desiredSearch = '?' + urlParams.toString();
                }
                if (window.location.search !== desiredSearch) {
                    window.history.replaceState({}, '', window.location.pathname + desiredSearch);
                }
            }
            
            // Update table
            const tbody = document.querySelector('#motoristsTable tbody');
            if (!tbody) {
                console.error('Tabela de motoristas não encontrada');
                return;
            }

            tbody.innerHTML = '';
            
            const fotoUrl = (m) => m.foto_motorista ? `../uploads/motoristas/foto/${(m.foto_motorista || '').split('/').pop()}` : '';
            const fotoHtml = (m) => m.foto_motorista
                ? `<img src="${fotoUrl(m)}" alt="" class="motorist-thumb" style="width:36px;height:36px;object-fit:cover;border-radius:50%;">`
                : `<span class="motorist-thumb-placeholder" style="width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;background:var(--bg-tertiary);border-radius:50%;font-size:0.8rem;color:var(--text-muted);"><i class="fas fa-user"></i></span>`;
            data.motorists.forEach(motorist => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${fotoHtml(motorist)}</td>
                    <td>${motorist.nome}</td>
                    <td>${motorist.cpf || '-'}</td>
                    <td>${motorist.cnh || '-'}</td>
                    <td>${motorist.categoria_cnh_nome || '-'}</td>
                    <td>${motorist.telefone || '-'}</td>
                    <td>${motorist.email || '-'}</td>
                    <td><span class="status-badge ${motorist.disponibilidade_nome?.toLowerCase()}">${motorist.disponibilidade_nome || 'N/A'}</span></td>
                    <td>${motorist.porcentagem_comissao ? motorist.porcentagem_comissao + '%' : '-'}</td>
                    <td class="actions">
                        <button class="btn-icon view-btn" data-id="${motorist.id}" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon favorite-btn ${motorist.is_favorite ? 'is-favorite' : ''}" data-id="${motorist.id}" title="${motorist.is_favorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}">
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="btn-icon history-btn" data-id="${motorist.id}" title="Histórico de alterações">
                            <i class="fas fa-history"></i>
                        </button>
                        <button class="btn-icon edit-btn" data-id="${motorist.id}" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete-btn" data-id="${motorist.id}" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Setup new buttons
            setupTableButtons();
            syncMotoristsSortIndicators();

            // View mode: table vs cards
            const viewMode = localStorage.getItem('motorists_view_mode') || 'table';
            const tableEl = document.getElementById('motoristsTable');
            const cardsEl = document.getElementById('motoristsCardsContainer');
            if (viewMode === 'cards' && cardsEl) {
                if (tableEl) tableEl.style.display = 'none';
                cardsEl.style.display = 'grid';
                cardsEl.innerHTML = data.motorists.map(m => {
                    const favClass = m.is_favorite ? ' is-favorite' : '';
                    const photoSrc = fotoUrl(m);
                    const photoHtml = photoSrc
                        ? `<img class="card-foto" src="${photoSrc}" alt="">`
                        : `<span class="card-foto" style="background:var(--bg-tertiary);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--text-muted);"><i class="fas fa-user"></i></span>`;
                    return `<div class="motorist-card" data-id="${m.id}">
                        ${photoHtml}
                        <div class="card-body">
                            <div class="card-name">${(m.nome || '').replace(/</g,'&lt;')}</div>
                            <div class="card-meta">${(m.categoria_cnh_nome || '-')} · ${(m.disponibilidade_nome || '-')}</div>
                            <div class="card-actions">
                                <button class="btn-icon view-btn" data-id="${m.id}" title="Ver"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon favorite-btn${favClass}" data-id="${m.id}" title="Favorito"><i class="fas fa-star"></i></button>
                                <button class="btn-icon history-btn" data-id="${m.id}" title="Histórico"><i class="fas fa-history"></i></button>
                                <button class="btn-icon edit-btn" data-id="${m.id}" title="Editar"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon delete-btn" data-id="${m.id}" title="Excluir"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>`;
                }).join('');
                if (data.motorists.length === 0) cardsEl.innerHTML = '<p class="text-muted">Nenhum motorista encontrado.</p>';
                setupTableButtons();
            } else {
                if (tableEl) tableEl.style.display = '';
                if (cardsEl) cardsEl.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading motorists:', error);
            showNotification('Erro ao carregar motoristas', 'error');
        });
}

function loadMotoristDetails(id) {
    console.log('Loading motorist details for ID:', id);
    currentMotoristId = id;
    
    if (!id) {
        console.error('Invalid motorist ID');
        return;
    }

    // Carregar dados básicos do motorista
    fetch(`../api/motorist_data.php?action=view&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const motorist = data.data;
                
                // Atualizar informações básicas
                document.getElementById('viewMotoristName').textContent = motorist.nome;
                document.getElementById('viewMotoristCPF').textContent = motorist.cpf;
                document.getElementById('viewMotoristCNH').textContent = motorist.cnh;
                document.getElementById('viewMotoristCNHCategory').textContent = motorist.categoria_cnh_nome || '-';
                document.getElementById('viewMotoristCNHExpiry').textContent = motorist.data_validade_cnh ? formatDate(motorist.data_validade_cnh) : '-';
                document.getElementById('viewMotoristPhone').textContent = motorist.telefone || '-';
                document.getElementById('viewMotoristEmergencyPhone').textContent = motorist.telefone_emergencia || '-';
                document.getElementById('viewMotoristEmail').textContent = motorist.email || '-';
                document.getElementById('viewMotoristAddress').textContent = motorist.endereco || '-';
                document.getElementById('viewMotoristHireDate').textContent = motorist.data_contratacao ? formatDate(motorist.data_contratacao) : '-';
                document.getElementById('viewMotoristContract').textContent = motorist.tipo_contrato_nome || '-';
                document.getElementById('viewMotoristAvailability').textContent = motorist.disponibilidade_nome || '-';
                document.getElementById('viewMotoristCommission').textContent = motorist.porcentagem_comissao ? `${motorist.porcentagem_comissao}%` : '-';
                document.getElementById('viewMotoristNotes').textContent = motorist.observacoes || '-';
                
                const totalMultas = motorist.total_multas != null ? parseInt(motorist.total_multas, 10) : 0;
                document.getElementById('view-total-multas').textContent = totalMultas;
                const linkMultas = document.getElementById('viewLinkMultas');
                if (linkMultas) {
                    linkMultas.href = `multas.php?motorista_id=${motorist.id}`;
                    linkMultas.style.display = totalMultas >= 0 ? 'inline-block' : 'none';
                }
                const totalManut = motorist.total_manutencoes != null ? parseInt(motorist.total_manutencoes, 10) : 0;
                const elManut = document.getElementById('view-total-manutencoes');
                if (elManut) elManut.textContent = totalManut;

                // Atualizar documentos
                if (motorist.cnh_arquivo) {
                    document.getElementById('cnhLink').href = `../uploads/motoristas/cnh/${motorist.cnh_arquivo.split('/').pop()}`;
                    document.getElementById('cnhLink').style.display = 'block';
                } else {
                    document.getElementById('cnhLink').style.display = 'none';
                }

                // Atualizar status e data da CNH
                const cnhStatus = document.querySelector('#cnhDocumentStatus .status-badge');
                const cnhExpiry = document.querySelector('#cnhExpiryDate span');
                if (motorist.data_validade_cnh) {
                    const expiryDate = new Date(motorist.data_validade_cnh);
                    const today = new Date();
                    const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                    
                    if (expiryDate < today) {
                        cnhStatus.textContent = 'Vencido';
                        cnhStatus.className = 'status-badge vencido';
                    } else if (daysUntilExpiry <= 30) {
                        cnhStatus.textContent = 'Próximo ao vencimento';
                        cnhStatus.className = 'status-badge proximo';
                    } else {
                        cnhStatus.textContent = 'Válido';
                        cnhStatus.className = 'status-badge valido';
                    }
                    cnhExpiry.textContent = formatDate(motorist.data_validade_cnh);
                } else {
                    cnhStatus.textContent = 'Não informado';
                    cnhStatus.className = 'status-badge';
                    cnhExpiry.textContent = '-';
                }

                if (motorist.contrato_arquivo) {
                    document.getElementById('contractLink').href = `../uploads/motoristas/contrato/${motorist.contrato_arquivo.split('/').pop()}`;
                    document.getElementById('contractLink').style.display = 'block';
                } else {
                    document.getElementById('contractLink').style.display = 'none';
                }

                // Atualizar status e data do contrato
                const contractStatus = document.querySelector('#contractDocumentStatus .status-badge');
                const contractDate = document.querySelector('#contractDate span');
                if (motorist.data_contratacao) {
                    contractStatus.textContent = 'Ativo';
                    contractStatus.className = 'status-badge ativo';
                    contractDate.textContent = formatDate(motorist.data_contratacao);
                } else {
                    contractStatus.textContent = 'Não informado';
                    contractStatus.className = 'status-badge';
                    contractDate.textContent = '-';
                }

                // Atualizar foto do motorista
                const fotoImg = document.getElementById('motoristPhoto');
                const noFotoMsg = document.getElementById('noPhotoMessage');

                if (motorist.foto_motorista) {
                    fotoImg.src = `../uploads/motoristas/foto/${motorist.foto_motorista.split('/').pop()}`;
                    fotoImg.style.display = 'block';
                    noFotoMsg.style.display = 'none';
                } else {
                    fotoImg.style.display = 'none';
                    noFotoMsg.style.display = 'block';
                }
                
                // Checklist de documentos (OK / Pendente)
                const cnhOk = !!(motorist.cnh && motorist.data_validade_cnh && new Date(motorist.data_validade_cnh) >= new Date());
                const contratoOk = !!(motorist.contrato_arquivo || motorist.data_contratacao);
                const fotoOk = !!motorist.foto_motorista;
                const setCheck = (elId, ok) => {
                    const el = document.getElementById(elId);
                    if (!el) return;
                    const strong = el.querySelector('strong');
                    if (strong) {
                        strong.textContent = ok ? 'OK' : 'Pendente';
                        strong.style.color = ok ? 'var(--success-color, #28a745)' : 'var(--warning-color, #ffc107)';
                    }
                };
                setCheck('checkCnh', cnhOk);
                setCheck('checkContrato', contratoOk);
                setCheck('checkFoto', fotoOk);

                // Carregar e atualizar documentos (incluindo data do contrato)
                fetch(`../api/motorist_data.php?action=documents&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        // Atualizar status e data do contrato
                        const contractStatus = document.querySelector('#contractDocumentStatus .status-badge');
                        const contractDate = document.querySelector('#contractDate span');
                        if (data.contract && data.contract.date) {
                            contractStatus.textContent = data.contract.status || 'Ativo';
                            contractStatus.className = 'status-badge ativo';
                            contractDate.textContent = formatDate(data.contract.date);
                        } else {
                            contractStatus.textContent = 'Não informado';
                            contractStatus.className = 'status-badge';
                            contractDate.textContent = '-';
                        }
                    });

                // Abrir o modal
                openModal('viewMotoristModal');
            } else {
                console.error('Error loading motorist data:', data.error);
                showNotification('Erro ao carregar dados do motorista', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading motorist details:', error);
            showNotification('Erro ao carregar detalhes do motorista', 'error');
        });

    // Carregar histórico de rotas
    fetch(`../api/motorist_data.php?action=routes&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.querySelector('#routeHistoryTable tbody');
                tbody.innerHTML = '';
                
                if (data.routes && data.routes.length > 0) {
                    data.routes.forEach(route => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${route.data}</td>
                            <td>${route.origem}</td>
                            <td>${route.destino}</td>
                            <td>${route.veiculo}</td>
                            <td>${route.km_percorrido}</td>
                            <td><span class="status-badge ${route.status?.toLowerCase()}">${route.status}</span></td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma rota encontrada</td></tr>';
                }
            }
        })
        .then(data => {
            console.log('Route history data:', data);
        })
        .catch(error => {
            console.error('Error loading route history:', error);
            showNotification('Erro ao carregar histórico de rotas', 'error');
        });

    // Carregar métricas de desempenho
    fetch(`../api/motorist_performance_chart.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            console.log('Performance data received:', data);
            
            if (data.success) {
                const metrics = data.data;
                
                // Atualizar métricas
                document.getElementById('view-average-rating').textContent = metrics.average_rating.toFixed(1);
                document.getElementById('view-total-trips').textContent = metrics.total_trips;
                document.getElementById('view-total-distance').textContent = `${metrics.total_distance.toFixed(0)} km`;
                document.getElementById('view-average-consumption').textContent = `${metrics.average_consumption.toFixed(1)} L/100km`;
                
                // Atualizar gráfico
                updatePerformanceChart(metrics.monthly_metrics);
            } else {
                console.error('Error in performance data:', data.error);
                showNotification('Erro ao carregar métricas de desempenho', 'error');
                
                // Definir valores padrão
                document.getElementById('view-average-rating').textContent = '0.0';
                document.getElementById('view-total-trips').textContent = '0';
                document.getElementById('view-total-distance').textContent = '0 km';
                document.getElementById('view-average-consumption').textContent = '0.0 L/100km';
            }
        })
        .catch(error => {
            console.error('Error loading performance metrics:', error);
            showNotification('Erro ao carregar métricas de desempenho', 'error');
            
            // Definir valores padrão em caso de erro
            document.getElementById('view-average-rating').textContent = '0.0';
            document.getElementById('view-total-trips').textContent = '0';
            document.getElementById('view-total-distance').textContent = '0 km';
            document.getElementById('view-average-consumption').textContent = '0.0 L/100km';
        });
}

function loadRouteHistory(motoristId) {
    fetch(`../api/motorist_data.php?action=routes&id=${motoristId}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#routeHistoryTable tbody');
            const noDataMessage = document.getElementById('noRouteHistoryMessage');
            
            if (!tbody || !noDataMessage) {
                console.error('Route history elements not found');
                return;
            }
            
            tbody.innerHTML = '';
            
            if (data.success && data.routes && data.routes.length > 0) {
                data.routes.forEach(route => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(route.data)}</td>
                        <td>${route.origem || '-'}</td>
                        <td>${route.destino || '-'}</td>
                        <td>${route.veiculo || '-'}</td>
                        <td>${formatKm(route.km_percorrido)}</td>
                        <td><span class="status-badge ${route.status?.toLowerCase() || ''}">${route.status || 'N/A'}</span></td>
                    `;
                    tbody.appendChild(row);
                });
                noDataMessage.style.display = 'none';
                tbody.closest('.route-history-container').style.display = 'block';
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma rota encontrada</td></tr>';
                noDataMessage.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading route history:', error);
            showNotification('Erro ao carregar histórico de rotas', 'error');
        });
}

function destroyChart() {
    if (performanceChart) {
        try {
            performanceChart.destroy();
        } catch (error) {
            console.warn('Error destroying chart:', error);
        } finally {
            performanceChart = null;
        }
    }
}

function updatePerformanceChart(monthlyData) {
    console.log('Updating performance chart with data:', monthlyData);
    
    // Primeiro, destruir o gráfico anterior
    destroyChart();

    // Garantir que o container do gráfico existe
    const chartContainer = document.getElementById('performanceChart')?.parentElement;
    if (!chartContainer) {
        console.error('Chart container not found');
        return;
    }

    // Limpar o container e criar um novo canvas
    chartContainer.innerHTML = '<canvas id="performanceChart"></canvas>';
    
    // Pequeno delay para garantir que o canvas esteja pronto
    setTimeout(() => {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) {
            console.error('Canvas element not found after creation');
            return;
        }

        try {
            // Preparar dados para o gráfico
            const labels = monthlyData.map(item => {
                const [year, month] = item.month.split('-');
                return `${month}/${year}`;
            }).reverse();

            const tripsData = monthlyData.map(item => item.trips).reverse();
            const ratingData = monthlyData.map(item => (item.rating * 10)).reverse();

            // Configuração do gráfico
            const config = {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Viagens',
                            data: tripsData,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Avaliação (x10)',
                            data: ratingData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            };

            // Criar novo gráfico
            performanceChart = new Chart(ctx, config);
            console.log('Performance chart updated successfully');

        } catch (error) {
            console.error('Error creating performance chart:', error);
            chartContainer.innerHTML = '<div class="error">Erro ao criar gráfico de desempenho</div>';
        }
    }, 100);
}

function loadPerformanceMetrics(motoristId) {
    console.log('Loading performance metrics for motorist ID:', motoristId);
    
    if (!motoristId) {
        console.error('Motorist ID is required');
        showNotification('ID do motorista é obrigatório', 'error');
        return;
    }

    // Primeiro, destruir qualquer gráfico existente
    destroyChart();

    // Garantir que o container do gráfico existe
    const chartContainer = document.getElementById('performanceChart')?.parentElement;
    if (!chartContainer) {
        console.error('Chart container not found, creating it...');
        const performanceTab = document.getElementById('performance');
        if (!performanceTab) {
            console.error('Performance tab not found');
            return;
        }
        performanceTab.innerHTML = `
            <div class="metrics-grid">
                <div class="metric-card">
                    <h3>Avaliação Média</h3>
                    <p id="averageRating">0.0</p>
                </div>
                <div class="metric-card">
                    <h3>Total de Viagens</h3>
                    <p id="totalTrips">0</p>
                </div>
                <div class="metric-card">
                    <h3>Distância Total</h3>
                    <p id="totalDistance">0 km</p>
                </div>
                <div class="metric-card">
                    <h3>Consumo Médio</h3>
                    <p id="averageConsumption">0.0 L/100km</p>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        `;
    }

    // Mostrar indicador de carregamento
    const newChartContainer = document.getElementById('performanceChart')?.parentElement;
    if (!newChartContainer) {
        console.error('Failed to create chart container');
        return;
    }
    
    newChartContainer.innerHTML = '<div class="loading">Carregando dados...</div>';

    // Atualizar os elementos de métricas antes de fazer a requisição
    const updateMetricElements = (metrics) => {
        console.log('Updating metric elements with data:', metrics);
        
        const averageRating = document.getElementById('averageRating');
        const totalTrips = document.getElementById('totalTrips');
        const totalDistance = document.getElementById('totalDistance');
        const averageConsumption = document.getElementById('averageConsumption');

        if (averageRating) {
            averageRating.textContent = metrics.average_rating?.toFixed(1) || '0.0';
            console.log('Updated average rating:', averageRating.textContent);
        }

        if (totalTrips) {
            totalTrips.textContent = metrics.total_trips || '0';
            console.log('Updated total trips:', totalTrips.textContent);
        }

        if (totalDistance) {
            totalDistance.textContent = formatKm(metrics.total_distance);
            console.log('Updated total distance:', totalDistance.textContent);
        }

        if (averageConsumption) {
            averageConsumption.textContent = `${metrics.average_consumption?.toFixed(1) || '0.0'} L/100km`;
            console.log('Updated average consumption:', averageConsumption.textContent);
        }
    };

    fetch(`../api/motorist_performance_chart.php?id=${motoristId}`)
        .then(response => {
            console.log('Performance API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Performance metrics response:', data);
            
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar métricas');
            }
            
            const metrics = data.data;
            console.log('Performance metrics data:', metrics);
            
            // Update metrics
            updateMetricElements(metrics);
            
            // Restaurar o canvas
            newChartContainer.innerHTML = '<canvas id="performanceChart"></canvas>';
            
            // Update chart if exists and has data
            if (metrics.monthly_metrics && metrics.monthly_metrics.length > 0) {
                console.log('Updating performance chart with data:', metrics.monthly_metrics);
                // Pequeno delay para garantir que o canvas esteja pronto
                setTimeout(() => {
                    updatePerformanceChart(metrics.monthly_metrics);
                }, 100);
            } else {
                console.log('No monthly metrics data available');
                newChartContainer.innerHTML = '<div class="no-data">Nenhum dado disponível</div>';
            }
        })
        .catch(error => {
            console.error('Error loading performance metrics:', error);
            showNotification('Erro ao carregar métricas de desempenho', 'error');
            newChartContainer.innerHTML = '<div class="error">Erro ao carregar dados</div>';
            
            // Set default values
            updateMetricElements({
                average_rating: 0,
                total_trips: 0,
                total_distance: 0,
                average_consumption: 0
            });
        });
}

function loadDocuments(motoristId) {
    fetch(`../api/motorist_data.php?action=documents&id=${motoristId}`)
        .then(response => response.json())
        .then(data => {
            // Update CNH status
            const cnhStatus = document.querySelector('#cnhDocumentStatus .status-badge');
            const cnhExpiry = document.querySelector('#cnhExpiryDate span');
            
            if (data.cnh) {
                cnhStatus.textContent = data.cnh.status;
                cnhStatus.className = `status-badge ${data.cnh.status.toLowerCase()}`;
                cnhExpiry.textContent = formatDate(data.cnh.expiry_date);
            }
            
            // Update contract status
            const contractStatus = document.querySelector('#contractDocumentStatus .status-badge');
            const contractDate = document.querySelector('#contractDate span');
            
            if (data.contract) {
                contractStatus.textContent = data.contract.status;
                contractStatus.className = `status-badge ${data.contract.status.toLowerCase()}`;
                contractDate.textContent = formatDate(data.contract.date);
            }
        })
        .catch(error => {
            console.error('Error loading documents:', error);
            showNotification('Erro ao carregar documentos', 'error');
        });
}

function setupPagination() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPage > 1) {
                loadMotorists(currentPage - 1);
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPage < totalPages) {
                loadMotorists(currentPage + 1);
            }
        });
    }
}

function updatePaginationButtons(pagination) {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const perPageSelect = document.getElementById('perPageMotorists');
    const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const perPageParam = [5, 10, 25, 50, 100].indexOf(perPage) >= 0 ? perPage : 10;
    
    if (prevBtn) {
        prevBtn.classList.toggle('disabled', currentPage <= 1);
        prevBtn.href = `?page=${Math.max(1, currentPage - 1)}&per_page=${perPageParam}`;
    }
    
    if (nextBtn) {
        nextBtn.classList.toggle('disabled', currentPage >= totalPages);
        nextBtn.href = `?page=${Math.min(totalPages, currentPage + 1)}&per_page=${perPageParam}`;
    }
    
    const paginationInfo = document.getElementById('paginationMotoristsInfo');
    if (paginationInfo && totalPages > 0) {
        const total = (pagination && pagination.total) ? pagination.total : (totalPages * perPageParam);
        paginationInfo.innerHTML = `Página <span id="currentPage">${currentPage}</span> de <span id="totalPages">${totalPages}</span> (${total} registros)`;
    }
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function formatKm(km) {
    if (!km) return '0 km';
    return `${Number(km).toLocaleString('pt-BR')} km`;
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
}

function showNotification(message, type = 'info') {
    console.log(`${type.toUpperCase()}: ${message}`);
    // You can implement a more visual notification system here
    alert(message);
}

function setupTableButtons() {
    console.log('Setting up table buttons...');
    
    // View, Edit, Delete e History são tratados por delegação em #motoristsTableContainer (initializePage).
    // Aqui só configuramos o botão de favorito que precisa de toggle de estado.
    
    // Favorite buttons
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const motoristId = btn.getAttribute('data-id');
            if (!motoristId) return;
            fetch(`../api/motorist_data.php?action=favorito_toggle&id=${motoristId}`, {
                method: 'POST',
                credentials: 'include',
                headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        btn.classList.toggle('is-favorite', data.favorito);
                        btn.title = data.favorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
                    }
                });
        });
    });
}

function loadMotoristForEdit(id) {
    console.log('Loading motorist for edit:', id);
    
    // Load dropdown options first
    loadDropdownOptions().then(() => {
        // After dropdowns are loaded, fetch motorist data
        fetch(`../api/motorist_data.php?action=view&id=${id}`)
            .then(response => {
                console.log('Edit load response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Edit load data:', data);
                if (!data.success) {
                    showNotification(data.error || 'Erro ao carregar dados do motorista', 'error');
                    return;
                }
                
                const motorist = data.data;
                
                // Update modal title
                document.getElementById('modalTitle').textContent = 'Editar Motorista';
                
                // Fill form fields
                document.getElementById('motoristId').value = motorist.id;
                document.getElementById('nome').value = motorist.nome || '';
                document.getElementById('cpf').value = motorist.cpf || '';
                document.getElementById('cnh').value = motorist.cnh || '';
                document.getElementById('categoria_cnh_id').value = motorist.categoria_cnh_id || '';
                document.getElementById('data_validade_cnh').value = motorist.data_validade_cnh ? motorist.data_validade_cnh.split(' ')[0] : '';
                document.getElementById('telefone').value = motorist.telefone || '';
                document.getElementById('telefone_emergencia').value = motorist.telefone_emergencia || '';
                document.getElementById('email').value = motorist.email || '';
                document.getElementById('endereco').value = motorist.endereco || '';
                document.getElementById('data_contratacao').value = motorist.data_contratacao ? motorist.data_contratacao.split(' ')[0] : '';
                document.getElementById('tipo_contrato_id').value = motorist.tipo_contrato_id || '';
                document.getElementById('disponibilidade_id').value = motorist.disponibilidade_id || '';
                document.getElementById('porcentagem_comissao').value = motorist.porcentagem_comissao || '';
                document.getElementById('observacoes').value = motorist.observacoes || '';
                
                // Limpar os campos de arquivo
                document.getElementById('foto_motorista').value = '';
                document.getElementById('cnh_arquivo').value = '';
                document.getElementById('contrato_arquivo').value = '';
                
                // Show modal
                openModal('motoristModal');
            })
            .catch(error => {
                console.error('Error loading motorist for edit:', error);
                showNotification('Erro ao carregar dados do motorista', 'error');
            });
    });
}

function loadDropdownOptions() {
    console.log('Loading dropdown options...');
    
    // Return a promise that resolves when all dropdowns are loaded
    return Promise.all([
        // Load contract types
        fetch('../api/motorist_data.php?action=get_contract_types')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('tipo_contrato_id');
                if (!select) {
                    console.error('Contract type select element not found');
                    return;
                }
                
                if (data.success && data.types) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    data.types.forEach(type => {
                        select.innerHTML += `<option value="${type.id}">${type.nome}</option>`;
                    });
                    console.log('Contract types loaded:', data.types.length);
                } else {
                    console.error('Error loading contract types:', data.error);
                }
            }),
        
        // Load availability status
        fetch('../api/motorist_data.php?action=get_availability_status')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('disponibilidade_id');
                if (!select) {
                    console.error('Availability select element not found');
                    return;
                }
                
                if (data.success && data.status) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    data.status.forEach(status => {
                        select.innerHTML += `<option value="${status.id}">${status.nome}</option>`;
                    });
                    console.log('Availability options loaded:', data.status.length);
                } else {
                    console.error('Error loading availability status:', data.error);
                }
            }),
        
        // Load CNH categories
        fetch('../api/motorist_data.php?action=get_cnh_categories')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('categoria_cnh_id');
                if (!select) {
                    console.error('CNH category select element not found');
                    return;
                }
                
                if (data.success && data.categories) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    data.categories.forEach(category => {
                        select.innerHTML += `<option value="${category.id}">${category.nome}</option>`;
                    });
                    console.log('CNH categories loaded:', data.categories.length);
                } else {
                    console.error('Error loading CNH categories:', data.error);
                }
            })
    ]).catch(error => {
        console.error('Error loading dropdown options:', error);
        showNotification('Erro ao carregar opções dos campos', 'error');
    });
}

function setupFilters() {
    const searchInput = document.getElementById('searchMotorist');
    const statusFilter = document.getElementById('statusFilter');
    const applyFiltersBtn = document.getElementById('applyMotoristFilters');
    const clearFiltersBtn = document.getElementById('clearMotoristFilters');

    let debounceTimer = null;
    const debounceDelay = 300;

    const triggerFilter = () => {
        loadMotorists(1);
    };

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(triggerFilter, debounceDelay);
        });

        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                triggerFilter();
            }
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', () => { triggerFilter(); });
    }
    const catCnh = document.getElementById('categoriaCnhFilter');
    if (catCnh) catCnh.addEventListener('change', () => { triggerFilter(); });
    const tipoContrato = document.getElementById('tipoContratoFilter');
    if (tipoContrato) tipoContrato.addEventListener('change', () => { triggerFilter(); });

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', event => {
            event.preventDefault();
            triggerFilter();
        });
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', event => {
            event.preventDefault();
            if (searchInput) {
                searchInput.value = '';
            }
            if (statusFilter) statusFilter.value = '';
            const catCnh = document.getElementById('categoriaCnhFilter');
            if (catCnh) catCnh.value = '';
            const tipoContrato = document.getElementById('tipoContratoFilter');
            if (tipoContrato) tipoContrato.value = '';
            const onlyFav = document.getElementById('filterOnlyFavoritos');
            if (onlyFav) onlyFav.checked = false;
            triggerFilter();
        });
    }
}

function loadDashboardData() {
    // Implementation of loadDashboardData function
}

function initializeRouteHistoryTab() {
    const routeHistoryTab = document.getElementById('routeHistory');
    if (!routeHistoryTab) {
        console.error('Route history tab not found');
        return;
    }
    
    // Make sure the table exists
    if (!routeHistoryTab.querySelector('#routeHistoryTable')) {
        const tableHTML = `
            <div class="route-history-container">
                <table class="data-table" id="routeHistoryTable">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Veículo</th>
                            <th>Km Percorrido</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="no-data-message" id="noRouteHistoryMessage" style="display: none;">
                    Nenhuma rota encontrada para este motorista.
                </div>
            </div>
        `;
        routeHistoryTab.innerHTML = tableHTML;
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = '';
        modal.style.pointerEvents = '';
        modal.classList.add('active');
    } else {
        console.error('Modal not found:', modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        if (modalId === 'motoristLogModal') {
            modal.style.display = 'none';
            modal.style.pointerEvents = 'none';
        }
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        // Destruir o gráfico quando o modal for fechado
        if (modalId === 'viewMotoristModal') {
            destroyChart();
        }
    } else {
        console.error('Modal not found:', modalId);
    }
}

function setupFormSubmission() {
    const saveBtn = document.getElementById('saveMotoristBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Save button clicked');
            saveMotorist();
        });
    } else {
        console.error('Save button not found');
    }
}

function saveMotorist() {
    console.log('Saving motorist...');
    const form = document.getElementById('motoristForm');
    const formData = new FormData(form);
    const motoristId = document.getElementById('motoristId').value;
    
    // Debug: Log form data
    console.log('Form data values:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Validar CPF se preenchido (campo opcional)
    const cpfVal = formData.get('cpf') || '';
    if (cpfVal.replace(/\D/g, '').length === 11 && !validateCPF(cpfVal)) {
        showNotification('CPF inválido. Verifique os dígitos.', 'error');
        return;
    }
    // Validar CNH: número 11 dígitos se preenchido; validade não vencida se preenchida
    const cnhVal = (formData.get('cnh') || '').replace(/\D/g, '');
    if (cnhVal.length > 0 && cnhVal.length !== 11) {
        showNotification('CNH deve ter 11 dígitos.', 'error');
        return;
    }
    const dataValidadeCnh = formData.get('data_validade_cnh') || '';
    if (dataValidadeCnh) {
        const d = new Date(dataValidadeCnh);
        if (isNaN(d.getTime())) {
            showNotification('Data de validade da CNH inválida.', 'error');
            return;
        }
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);
        if (d < hoje) {
            showNotification('Data de validade da CNH já está vencida. Corrija ou deixe em branco.', 'error');
            return;
        }
    }
    
    // Add empresa_id from session if needed
    if (!formData.get('empresa_id')) {
        formData.append('empresa_id', document.getElementById('empresaId').value);
    }
    
    const action = motoristId ? 'update' : 'add';
    console.log('Action:', action, 'ID:', motoristId);
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        formData.append('csrf_token', window.__SF_CSRF__);
    }
    
    fetch(`../api/motorist_data.php?action=${action}${motoristId ? '&id=' + motoristId : ''}`, {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
    })
    .then(response => {
        console.log('Save response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Save response data:', data);
        if (!data.success) {
            showNotification(data.error || 'Erro ao salvar motorista', 'error');
            return;
        }
        
        // Close modal and reload data
        closeModal('motoristModal');
        loadMotorists(currentPage);
        showNotification(motoristId ? 'Motorista atualizado com sucesso' : 'Motorista adicionado com sucesso', 'success');
    })
    .catch(error => {
        console.error('Error saving motorist:', error);
        showNotification('Erro ao salvar motorista', 'error');
    });
}

function showDeleteConfirmation(motoristId) {
    if (confirm('Tem certeza que deseja excluir este motorista?')) {
        console.log('Attempting to delete motorist:', motoristId);
        
        fetch(`../api/motorist_data.php?action=delete&id=${motoristId}`, {
            method: 'POST',
            credentials: 'include',
            headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
        })
        .then(response => {
            console.log('Delete response status:', response.status);
            return response.json().catch(error => {
                console.error('Error parsing JSON:', error);
                throw new Error('Failed to parse server response');
            });
        })
        .then(data => {
            console.log('Delete response data:', data);
            if (!data.success) {
                showNotification(data.error || 'Erro ao excluir motorista', 'error');
                return;
            }
            
            showNotification(data.message || 'Motorista excluído com sucesso', 'success');
            loadMotorists(currentPage);
        })
        .catch(error => {
            console.error('Error in delete operation:', error);
            showNotification('Erro ao excluir motorista: ' + error.message, 'error');
        });
    }
}

function showAddMotoristModal() {
    const modal = document.getElementById('motoristModal');
    document.getElementById('modalTitle').textContent = 'Adicionar Motorista';
    document.getElementById('motoristForm').reset();
    document.getElementById('motoristId').value = '';
    
    // Limpar os campos de arquivo
    document.getElementById('foto_motorista').value = '';
    document.getElementById('cnh_arquivo').value = '';
    document.getElementById('contrato_arquivo').value = '';
    
    // Load dropdown options and then show modal
    loadDropdownOptions().then(() => {
        modal.classList.add('active');
    }).catch(error => {
        console.error('Error preparing add motorist modal:', error);
        showNotification('Erro ao preparar formulário', 'error');
    });
}

function initializeCharts() {
    // Initialize any charts if needed
    console.log('Charts initialized');
}

// Função para configurar botão de ajuda
function setupHelpButton() {
    const helpBtn = document.getElementById('helpBtn');
    if (helpBtn) {
        helpBtn.addEventListener('click', function() {
            const helpModal = document.getElementById('helpMotoristsModal');
            if (helpModal) {
                helpModal.classList.add('active');
            }
        });
    }
}

// Máscara CPF: 000.000.000-00
function maskCPF(value) {
    return value.replace(/\D/g, '').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2').substring(0, 14);
}
// Máscara telefone: (00) 00000-0000 ou (00) 0000-0000
function maskPhone(value) {
    const d = value.replace(/\D/g, '');
    if (d.length <= 10) return d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').replace(/-$/, '');
    return d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3').substring(0, 15);
}
// Validação CPF (dígitos verificadores)
function validateCPF(cpf) {
    const d = (cpf || '').replace(/\D/g, '');
    if (d.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(d)) return false;
    let s = 0; for (let i = 0; i < 9; i++) s += parseInt(d[i]) * (10 - i);
    let r = (s * 10) % 11; if (r === 10) r = 0; if (r !== parseInt(d[9])) return false;
    s = 0; for (let i = 0; i < 10; i++) s += parseInt(d[i]) * (11 - i);
    r = (s * 10) % 11; if (r === 10) r = 0; if (r !== parseInt(d[10])) return false;
    return true;
}
function setupInputMasks() {
    const cpfEl = document.getElementById('cpf');
    const telEl = document.getElementById('telefone');
    const telEmergEl = document.getElementById('telefone_emergencia');
    if (cpfEl) cpfEl.addEventListener('input', function() { this.value = maskCPF(this.value); });
    if (telEl) telEl.addEventListener('input', function() { this.value = maskPhone(this.value); });
    if (telEmergEl) telEmergEl.addEventListener('input', function() { this.value = maskPhone(this.value); });
}
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const addBtn = document.getElementById('addMotoristBtn');
            if (addBtn && !document.getElementById('motoristModal').classList.contains('active')) addBtn.click();
        }
    });
}
function loadFilterOptions() {
    Promise.all([
        fetch('../api/motorist_data.php?action=get_cnh_categories').then(r => r.json()),
        fetch('../api/motorist_data.php?action=get_contract_types').then(r => r.json())
    ]).then(([catData, tipoData]) => {
        const catSel = document.getElementById('categoriaCnhFilter');
        const tipoSel = document.getElementById('tipoContratoFilter');
        if (catSel && catData.success && catData.categories) {
            const selected = catSel.value;
            catSel.innerHTML = '<option value="">Todas categorias CNH</option>' +
                catData.categories.map(c => `<option value="${c.id}">${(c.nome || '').replace(/</g, '&lt;').replace(/"/g, '&quot;')}</option>`).join('');
            if (selected) catSel.value = selected;
        }
        if (tipoSel && tipoData.success && tipoData.types) {
            const selected = tipoSel.value;
            tipoSel.innerHTML = '<option value="">Todos tipos de contrato</option>' +
                tipoData.types.map(t => `<option value="${t.id}">${(t.nome || '').replace(/</g, '&lt;').replace(/"/g, '&quot;')}</option>`).join('');
            if (selected) tipoSel.value = selected;
        }
    });
}
function exportMotoristsCSV() {
    const params = new URLSearchParams();
    params.append('action', 'list');
    params.append('page', 1);
    params.append('limit', 9999);
    const searchInput = document.getElementById('searchMotorist');
    const statusFilter = document.getElementById('statusFilter');
    if (searchInput && searchInput.value.trim()) params.append('search', searchInput.value.trim());
    if (statusFilter && statusFilter.value) params.append('status', statusFilter.value);
    const catCnh = document.getElementById('categoriaCnhFilter');
    if (catCnh && catCnh.value) params.append('categoria_cnh', catCnh.value);
    const tipoContrato = document.getElementById('tipoContratoFilter');
    if (tipoContrato && tipoContrato.value) params.append('tipo_contrato', tipoContrato.value);
    params.append('sort', currentSort);
    params.append('order', currentOrder);
    fetch(`../api/motorist_data.php?${params.toString()}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.motorists) {
                showNotification('Nenhum dado para exportar', 'error');
                return;
            }
            const cols = ['nome','cpf','cnh','categoria_cnh_nome','telefone','email','disponibilidade_nome','porcentagem_comissao'];
            const headers = ['Nome','CPF','CNH','Categoria','Telefone','Email','Status','Comissão %'];
            let csv = '\uFEFF' + headers.join(';') + '\n';
            data.motorists.forEach(m => {
                csv += cols.map(c => '"' + (String(m[c] || '').replace(/"/g, '""')) + '"').join(';') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'motoristas_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
            URL.revokeObjectURL(a.href);
            showNotification('Exportação concluída', 'success');
        })
        .catch(() => showNotification('Erro ao exportar', 'error'));
}

function motoristsDefaultOrder(field) {
    if (field === 'porcentagem_comissao') return 'DESC';
    return 'ASC';
}

function syncMotoristsSortIndicators() {
    document.querySelectorAll('#motoristsTable th.sortable').forEach(th => {
        const field = th.getAttribute('data-sort');
        const ind = th.querySelector('.sort-ind');
        if (!ind) return;
        const on = field === currentSort;
        th.classList.toggle('sorted', on);
        ind.textContent = on ? (currentOrder === 'ASC' ? '▲' : '▼') : '⇅';
    });
}

function setupSortableColumns() {
    document.querySelectorAll('#motoristsTable th.sortable').forEach(th => {
        th.title = 'Clique para ordenar (A–Z ou maior/menor conforme a coluna)';
        th.addEventListener('click', function() {
            const sort = this.getAttribute('data-sort');
            if (!sort) return;
            if (currentSort === sort) {
                currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSort = sort;
                currentOrder = motoristsDefaultOrder(sort);
            }
            loadMotorists(currentPage);
        });
    });
}

// Editar motorista a partir do modal de visualização
function editarMotoristaDoModalVisualizacao() {
    if (!currentMotoristId) {
        showNotification('ID do motorista não encontrado', 'error');
        return;
    }
    closeModal('viewMotoristModal');
    loadMotoristForEdit(currentMotoristId);
}

// Abrir histórico a partir do modal de visualização
function abrirHistoricoDoModalVisualizacao() {
    if (!currentMotoristId) return;
    closeModal('viewMotoristModal');
    openMotoristLogModal(currentMotoristId);
}

function compartilharMotorista() {
    if (!currentMotoristId) return;
    const url = window.location.origin + window.location.pathname + '?view_id=' + currentMotoristId;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showNotification('Link copiado para a área de transferência.', 'success');
        }).catch(function() {
            fallbackCopyLink(url);
        });
    } else {
        fallbackCopyLink(url);
    }
}
function fallbackCopyLink(url) {
    const ta = document.createElement('textarea');
    ta.value = url;
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showNotification('Link copiado.', 'success');
    } catch (e) {
        showNotification('Link: ' + url, 'info');
    }
    document.body.removeChild(ta);
}

// Abrir modal de histórico de alterações do motorista
function openMotoristLogModal(motoristId) {
    if (!motoristId) return;
    const nameEl = document.getElementById('motoristLogName');
    const tbody = document.getElementById('motoristLogTbody');
    const emptyEl = document.getElementById('motoristLogEmpty');
    if (!tbody || !emptyEl) return;
    nameEl.textContent = 'Carregando...';
    tbody.innerHTML = '';
    emptyEl.style.display = 'none';
    const fieldLabels = {
        nome: 'Nome', cpf: 'CPF', cnh: 'CNH', data_validade_cnh: 'Validade CNH',
        telefone: 'Telefone', telefone_emergencia: 'Telefone emergência', email: 'E-mail',
        endereco: 'Endereço', data_contratacao: 'Data contratação', tipo_contrato_id: 'Tipo contrato',
        disponibilidade_id: 'Disponibilidade', porcentagem_comissao: 'Comissão %', observacoes: 'Observações',
        cnh_arquivo: 'Documento CNH', contrato_arquivo: 'Documento contrato', foto_motorista: 'Foto'
    };
    function formatVal(v) {
        if (v === null || v === undefined || v === '') return '—';
        if (v === true || v === '1') return 'Sim';
        if (v === false || v === '0') return 'Não';
        return String(v);
    }
    function buildDetailsHtml(entry) {
        const ant = entry.dados_anteriores || {};
        const nov = entry.dados_novos || {};
        const keys = new Set([...Object.keys(ant), ...Object.keys(nov)]);
        const lines = [];
        keys.forEach(k => {
            const a = ant[k];
            const b = nov[k];
            if (formatVal(a) === formatVal(b)) return;
            const label = fieldLabels[k] || k;
            lines.push(`${label}: ${formatVal(a)} → ${formatVal(b)}`);
        });
        return lines.length ? lines.join('<br>') : '—';
    }
    fetch(`../api/motorist_data.php?action=log&id=${motoristId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.entries && data.entries.length > 0) {
                nameEl.textContent = '';
                data.entries.forEach(entry => {
                    const tr = document.createElement('tr');
                    const acaoLabel = entry.acao === 'create' ? 'Cadastro' : (entry.acao === 'update' ? 'Alteração' : 'Exclusão');
                    const alteradoPor = entry.nome_usuario || (entry.ip_origem ? `IP ${entry.ip_origem}` : '—');
                    const detalhes = buildDetailsHtml(entry);
                    tr.innerHTML = `<td>${entry.created_at_formatted || '-'}</td><td>${acaoLabel}</td><td>${alteradoPor}</td><td>${entry.descricao || '-'}</td><td class="log-details">${detalhes}</td>`;
                    tbody.appendChild(tr);
                });
                emptyEl.style.display = 'none';
            } else {
                nameEl.textContent = '';
                emptyEl.style.display = 'block';
            }
            openModal('motoristLogModal');
        })
        .catch(err => {
            console.error(err);
            nameEl.textContent = '';
            emptyEl.textContent = 'Erro ao carregar histórico.';
            emptyEl.style.display = 'block';
            openModal('motoristLogModal');
        });
}

// Função específica para fechar modal de ajuda
function closeHelpModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
} 