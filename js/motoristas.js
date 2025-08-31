// Motoristas Management JavaScript - Apenas Visualização
document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const motoristModal = document.getElementById('motoristModal');
    const helpModal = document.getElementById('helpMotoristModal');
    
    // Buttons
    const closeMotoristBtn = document.getElementById('closeMotoristBtn');
    const helpBtn = document.getElementById('helpBtn');
    
    // Initialize event listeners
    initializeEventListeners();
    
    function initializeEventListeners() {
        // Close motorist button
        if (closeMotoristBtn) {
            closeMotoristBtn.addEventListener('click', closeMotoristModal);
        }
        
        // Help button
        if (helpBtn) {
            helpBtn.addEventListener('click', openHelpModal);
        }
        
        // Close modal buttons
        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(event) {
                if (event.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // Action buttons in table - only view
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-btn')) {
                const id = e.target.closest('.view-btn').dataset.id;
                viewMotorist(id);
            }
        });
        
        // Search functionality
        const searchInput = document.getElementById('searchMotorist');
        if (searchInput) {
            searchInput.addEventListener('input', filterMotorists);
        }
        
        // Filter functionality
        const statusFilter = document.getElementById('statusFilter');
        const categoriaFilter = document.getElementById('categoriaFilter');
        
        if (statusFilter) {
            statusFilter.addEventListener('change', filterMotorists);
        }
        
        if (categoriaFilter) {
            categoriaFilter.addEventListener('change', filterMotorists);
        }
    }
    
    function closeMotoristModal() {
        motoristModal.style.display = 'none';
    }
    
    function openHelpModal() {
        helpModal.style.display = 'block';
    }
    
    function viewMotorist(id) {
        // Fetch motorist data and show in modal
        fetch(`../api/motoristas.php?action=get&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const motorist = data.data;
                    document.getElementById('modalTitle').textContent = 'Detalhes do Motorista';
                    
                    // Create HTML for motorist details
                    const detailsHtml = `
                        <div class="motorist-details">
                            <div class="detail-section">
                                <h3>Informações Pessoais</h3>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Nome:</label>
                                        <span>${motorist.nome}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>CPF:</label>
                                        <span>${motorist.cpf}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Telefone:</label>
                                        <span>${motorist.telefone || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>E-mail:</label>
                                        <span>${motorist.email || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Endereço:</label>
                                        <span>${motorist.endereco || '-'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-section">
                                <h3>Informações da CNH</h3>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <label>Número da CNH:</label>
                                        <span>${motorist.cnh}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Categoria:</label>
                                        <span>${motorist.categoria_cnh_nome || '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Validade da CNH:</label>
                                        <span>${motorist.data_validade_cnh ? new Date(motorist.data_validade_cnh).toLocaleDateString('pt-BR') : '-'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Status:</label>
                                        <span>${motorist.disponibilidade_nome || '-'}</span>
                                    </div>
                                </div>
                            </div>
                            
                            ${motorist.observacoes ? `
                            <div class="detail-section">
                                <h3>Observações</h3>
                                <div class="detail-item">
                                    <span>${motorist.observacoes}</span>
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    `;
                    
                    document.getElementById('motoristDetails').innerHTML = detailsHtml;
                    motoristModal.style.display = 'block';
                } else {
                    showAlert('Erro ao carregar dados do motorista', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Erro ao carregar dados do motorista', 'error');
            });
    }
    
    function filterMotorists() {
        const searchTerm = document.getElementById('searchMotorist').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value;
        const categoriaFilter = document.getElementById('categoriaFilter').value;
        
        const rows = document.querySelectorAll('#motoristTable tbody tr');
        
        rows.forEach(row => {
            const nome = row.cells[0].textContent.toLowerCase();
            const cnh = row.cells[1].textContent.toLowerCase();
            const categoria = row.cells[2].textContent.toLowerCase();
            const status = row.cells[3].textContent.toLowerCase();
            
            const matchesSearch = nome.includes(searchTerm) || cnh.includes(searchTerm);
            const matchesStatus = !statusFilter || status.includes(statusFilter.toLowerCase());
            const matchesCategoria = !categoriaFilter || categoria.includes(categoriaFilter.toLowerCase());
            
            if (matchesSearch && matchesStatus && matchesCategoria) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    function showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Insert at the top of the dashboard content
        const dashboardContent = document.querySelector('.dashboard-content');
        if (dashboardContent) {
            dashboardContent.insertBefore(alertDiv, dashboardContent.firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
}); 