document.addEventListener('DOMContentLoaded', () => {
    const motoristModal = document.getElementById('motoristModal');
    const helpModal = document.getElementById('helpMotoristModal');
    const closeMotoristBtn = document.getElementById('closeMotoristBtn');
    const helpBtn = document.getElementById('helpBtn');

    const listTable = document.getElementById('motoristsTable');
    const managementTable = document.getElementById('motoristTable');

    const searchInput = document.getElementById('searchMotorist');
    const statusFilter = document.getElementById('statusFilter');
    const categoriaFilter = document.getElementById('categoriaFilter');
    const applyFiltersBtn = document.getElementById('applyMotoristFilters');
    const clearFiltersBtn = document.getElementById('clearMotoristFilters');

    const listRows = listTable
        ? Array.from(listTable.querySelectorAll('tbody tr')).filter(row => !row.classList.contains('no-data-row'))
        : [];
    const emptyRow = listTable ? listTable.querySelector('tbody .no-data-row') : null;

    if (closeMotoristBtn) {
        closeMotoristBtn.addEventListener('click', () => {
            if (motoristModal) {
                motoristModal.style.display = 'none';
            }
        });
    }

    if (helpBtn) {
        helpBtn.addEventListener('click', () => {
            if (helpModal) {
                helpModal.style.display = 'block';
            }
        });
    }

    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    document.addEventListener('click', event => {
        const viewBtn = event.target.closest('.view-btn');
        if (viewBtn) {
            const id = viewBtn.dataset.id;
            if (id) {
                viewMotorist(id);
            }
        }
    });

    const applyFilters = () => {
        const searchTerm = normalizeText(searchInput ? searchInput.value : '');
        const statusValue = normalizeText(statusFilter ? statusFilter.value : '');
        const categoriaValue = normalizeText(categoriaFilter ? categoriaFilter.value : '');

        if (listTable) {
            let visibleCount = 0;
            listRows.forEach(row => {
                const nome = normalizeText(row.cells[0]?.textContent);
                const cpf = normalizeText(row.cells[1]?.textContent);
                const cnh = normalizeText(row.cells[2]?.textContent);
                const categoria = normalizeText(row.cells[3]?.textContent);
                const telefone = normalizeText(row.cells[4]?.textContent);
                const email = normalizeText(row.cells[5]?.textContent);
                const status = normalizeText(row.cells[6]?.textContent);

                const matchesSearch = !searchTerm || [nome, cpf, cnh, categoria, telefone, email].some(field => field.includes(searchTerm));
                const matchesStatus = !statusValue || status.includes(statusValue);
                const matchesCategoria = !categoriaValue || categoria.includes(categoriaValue);

                const visible = matchesSearch && matchesStatus && matchesCategoria;
                row.style.display = visible ? '' : 'none';
                if (visible) {
                    visibleCount++;
                }
            });

        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        } else {
            const tbody = listTable.querySelector('tbody');
            if (!tbody) return;

            const existing = tbody.querySelector('.no-data-row');
            if (visibleCount === 0) {
                if (!existing) {
                    const messageRow = document.createElement('tr');
                    messageRow.className = 'no-data-row';
                    messageRow.innerHTML = '<td colspan="9" class="text-center">Nenhum motorista encontrado</td>';
                    tbody.appendChild(messageRow);
                } else {
                    existing.style.display = '';
                }
            } else if (existing) {
                existing.remove();
            }
        }
        }

        if (managementTable) {
            const rows = managementTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const nome = normalizeText(row.cells[0]?.textContent);
                const cnh = normalizeText(row.cells[1]?.textContent);
                const categoria = normalizeText(row.cells[2]?.textContent);
                const status = normalizeText(row.cells[3]?.textContent);

                const matchesSearch = !searchTerm || nome.includes(searchTerm) || cnh.includes(searchTerm);
                const matchesStatus = !statusValue || status.includes(statusValue);
                const matchesCategoria = !categoriaValue || categoria.includes(categoriaValue);

                row.style.display = (matchesSearch && matchesStatus && matchesCategoria) ? '' : 'none';
            });
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }

    if (categoriaFilter) {
        categoriaFilter.addEventListener('change', applyFilters);
    }

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyFilters);
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (categoriaFilter) categoriaFilter.value = '';
            applyFilters();
        });
    }

    applyFilters();

    function viewMotorist(id) {
        fetch(`../api/motoristas.php?action=get&id=${id}`, {
            credentials: 'include'
        })
            .then(response => {
                if (response.status === 401) {
                    window.location.href = '../login.php';
                    return Promise.reject(new Error('Unauthorized'));
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const motorist = data.data;
                    document.getElementById('modalTitle').textContent = 'Detalhes do Motorista';

                    const detailsHtml = `
                        <div class="motorist-details">
                            <div class="detail-section">
                                <h3>Informações Pessoais</h3>
                                <div class="detail-grid">
                                    <div class="detail-item"><label>Nome:</label><span>${escapeHtml(motorist.nome)}</span></div>
                                    <div class="detail-item"><label>CPF:</label><span>${escapeHtml(motorist.cpf)}</span></div>
                                    <div class="detail-item"><label>Telefone:</label><span>${escapeHtml(motorist.telefone || '-')}</span></div>
                                    <div class="detail-item"><label>E-mail:</label><span>${escapeHtml(motorist.email || '-')}</span></div>
                                    <div class="detail-item"><label>Endereço:</label><span>${escapeHtml(motorist.endereco || '-')}</span></div>
                                </div>
                            </div>
                            <div class="detail-section">
                                <h3>Informações da CNH</h3>
                                <div class="detail-grid">
                                    <div class="detail-item"><label>Número da CNH:</label><span>${escapeHtml(motorist.cnh || '-')}</span></div>
                                    <div class="detail-item"><label>Categoria:</label><span>${escapeHtml(motorist.categoria_cnh_nome || '-')}</span></div>
                                    <div class="detail-item"><label>Validade da CNH:</label><span>${motorist.data_validade_cnh ? new Date(motorist.data_validade_cnh).toLocaleDateString('pt-BR') : '-'}</span></div>
                                    <div class="detail-item"><label>Status:</label><span>${escapeHtml(motorist.disponibilidade_nome || '-')}</span></div>
                                </div>
                            </div>
                            ${motorist.observacoes ? `
                            <div class="detail-section">
                                <h3>Observações</h3>
                                <div class="detail-item"><span>${escapeHtml(motorist.observacoes)}</span></div>
                            </div>` : ''}
                        </div>
                    `;

                    const detailsContainer = document.getElementById('motoristDetails');
                    if (detailsContainer) {
                        detailsContainer.innerHTML = detailsHtml;
                    }
                    if (motoristModal) {
                        motoristModal.style.display = 'block';
                    }
                } else {
                    showAlert('Erro ao carregar dados do motorista', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showAlert('Erro ao carregar dados do motorista', 'error');
            });
    }

    function normalizeText(value) {
        return value
            ? value.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim()
            : '';
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '-';
        return value
            .toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        const dashboardContent = document.querySelector('.dashboard-content');
        if (dashboardContent) {
            dashboardContent.insertBefore(alertDiv, dashboardContent.firstChild);
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
});

