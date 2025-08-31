/**
 * Dashboard em Tempo Real - Modo Flexível
 * Gerencia estatísticas e contadores de pneus
 */

class FlexDashboard {
    constructor() {
        this.stats = {
            total: 0,
            alocados: 0,
            disponiveis: 0,
            criticos: 0,
            alerta: 0,
            manutencao: 0,
            custoTotal: 0
        };
        this.updateInterval = null;
        this.isInitialized = false;
        this.init();
    }

    init() {
        this.createDashboard();
        this.loadStats();
        this.startAutoUpdate();
        this.bindEvents();
        this.isInitialized = true;
        console.log('FlexDashboard inicializado');
    }

    createDashboard() {
        const container = document.querySelector('.flex-container');
        if (!container) {
            console.error('Container flex não encontrado');
            return;
        }

        // Criar dashboard se não existir
        if (!document.querySelector('.flex-dashboard')) {
            const dashboardHTML = `
                <div class="flex-dashboard">
                    <div class="flex-stats">
                        <div class="stat-card pneus-alocados">
                            <div class="status-indicator"></div>
                            <i class="fas fa-tire"></i>
                            <span class="stat-number" id="stat-alocados">0</span>
                            <span class="stat-label">Pneus Alocados</span>
                        </div>
                        <div class="stat-card pneus-criticos">
                            <div class="status-indicator critical"></div>
                            <i class="fas fa-exclamation-triangle"></i>
                            <span class="stat-number" id="stat-criticos">0</span>
                            <span class="stat-label">Pneus Críticos</span>
                        </div>
                        <div class="stat-card pneus-alerta">
                            <div class="status-indicator warning"></div>
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="stat-number" id="stat-alerta">0</span>
                            <span class="stat-label">Pneus em Alerta</span>
                        </div>
                        <div class="stat-card pneus-manutencao">
                            <div class="status-indicator info"></div>
                            <i class="fas fa-tools"></i>
                            <span class="stat-number" id="stat-manutencao">0</span>
                            <span class="stat-label">Em Manutenção</span>
                        </div>
                        <div class="stat-card custo-total">
                            <i class="fas fa-dollar-sign"></i>
                            <span class="stat-number" id="stat-custo">R$ 0,00</span>
                            <span class="stat-label">Custo Total</span>
                        </div>
                        <div class="stat-card pneus-disponiveis">
                            <i class="fas fa-warehouse"></i>
                            <span class="stat-number" id="stat-disponiveis">0</span>
                            <span class="stat-label">Disponíveis</span>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <button class="btn-refresh-stats" onclick="flexDashboard.refreshStats()">
                            <i class="fas fa-sync-alt"></i> Atualizar
                        </button>
                        <button class="btn-export-stats" onclick="flexDashboard.exportStats()">
                            <i class="fas fa-download"></i> Exportar
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('afterbegin', dashboardHTML);
        }
    }

    async loadStats() {
        try {
            const dashboard = document.querySelector('.flex-dashboard');
            if (dashboard) {
                dashboard.classList.add('loading');
            }

            // Buscar dados da API
            const response = await fetch('api/flex-stats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_stats',
                    mode: 'flex'
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.stats = data.stats;
                this.updateDisplay();
                this.checkAlerts();
            } else {
                console.error('Erro ao carregar estatísticas:', data.message);
                this.showError('Erro ao carregar estatísticas');
            }

        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
            this.showError('Erro de conexão');
            this.loadMockStats(); // Carregar dados mock em caso de erro
        } finally {
            const dashboard = document.querySelector('.flex-dashboard');
            if (dashboard) {
                dashboard.classList.remove('loading');
            }
        }
    }

    loadMockStats() {
        // Dados mock para desenvolvimento
        this.stats = {
            total: 150,
            alocados: 120,
            disponiveis: 20,
            criticos: 8,
            alerta: 12,
            manutencao: 10,
            custoTotal: 45000.50
        };
        this.updateDisplay();
    }

    updateDisplay() {
        // Atualizar números com animação
        this.animateNumber('stat-alocados', this.stats.alocados);
        this.animateNumber('stat-criticos', this.stats.criticos);
        this.animateNumber('stat-alerta', this.stats.alerta);
        this.animateNumber('stat-manutencao', this.stats.manutencao);
        this.animateNumber('stat-disponiveis', this.stats.disponiveis);
        this.animateCurrency('stat-custo', this.stats.custoTotal);

        // Atualizar indicadores de status
        this.updateStatusIndicators();
    }

    animateNumber(elementId, targetValue) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const currentValue = parseInt(element.textContent) || 0;
        const increment = (targetValue - currentValue) / 20;
        let current = currentValue;

        const animate = () => {
            current += increment;
            if ((increment > 0 && current >= targetValue) || 
                (increment < 0 && current <= targetValue)) {
                element.textContent = targetValue;
            } else {
                element.textContent = Math.floor(current);
                requestAnimationFrame(animate);
            }
        };

        animate();
    }

    animateCurrency(elementId, targetValue) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const currentValue = parseFloat(element.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
        const increment = (targetValue - currentValue) / 20;
        let current = currentValue;

        const animate = () => {
            current += increment;
            if ((increment > 0 && current >= targetValue) || 
                (increment < 0 && current <= targetValue)) {
                element.textContent = this.formatCurrency(targetValue);
            } else {
                element.textContent = this.formatCurrency(current);
                requestAnimationFrame(animate);
            }
        };

        animate();
    }

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    updateStatusIndicators() {
        // Atualizar indicadores baseado nos valores
        const criticalIndicator = document.querySelector('.stat-card.pneus-criticos .status-indicator');
        const alertIndicator = document.querySelector('.stat-card.pneus-alerta .status-indicator');

        if (this.stats.criticos > 0) {
            criticalIndicator.classList.add('critical');
        } else {
            criticalIndicator.classList.remove('critical');
        }

        if (this.stats.alerta > 0) {
            alertIndicator.classList.add('warning');
        } else {
            alertIndicator.classList.remove('warning');
        }
    }

    checkAlerts() {
        // Verificar alertas críticos
        if (this.stats.criticos > 5) {
            this.showNotification('Alerta Crítico', `${this.stats.criticos} pneus precisam de atenção imediata!`, 'critical');
        }

        if (this.stats.alerta > 10) {
            this.showNotification('Alerta', `${this.stats.alerta} pneus em estado de alerta`, 'warning');
        }
    }

    showNotification(title, message, type = 'info') {
        // Usar sistema de notificações se disponível
        if (window.flexNotifications) {
            window.flexNotifications.show(title, message, type);
        } else {
            // Fallback para alerta simples
            console.log(`${title}: ${message}`);
        }
    }

    showError(message) {
        const dashboard = document.querySelector('.flex-dashboard');
        if (dashboard) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'dashboard-error';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
            `;
            dashboard.appendChild(errorDiv);

            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
    }

    startAutoUpdate() {
        // Atualizar a cada 30 segundos
        this.updateInterval = setInterval(() => {
            this.loadStats();
        }, 30000);
    }

    stopAutoUpdate() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    refreshStats() {
        this.loadStats();
    }

    async exportStats() {
        try {
            const response = await fetch('api/flex-stats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'export_stats',
                    stats: this.stats
                })
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `estatisticas_pneus_${new Date().toISOString().split('T')[0]}.xlsx`;
                a.click();
                window.URL.revokeObjectURL(url);
            }
        } catch (error) {
            console.error('Erro ao exportar estatísticas:', error);
            this.showError('Erro ao exportar dados');
        }
    }

    bindEvents() {
        // Eventos de teclas de atalho
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                this.refreshStats();
            }
        });

        // Eventos de visibilidade da página
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoUpdate();
            } else {
                this.startAutoUpdate();
            }
        });
    }

    destroy() {
        this.stopAutoUpdate();
        this.isInitialized = false;
        console.log('FlexDashboard destruído');
    }

    // Métodos públicos para integração
    getStats() {
        return this.stats;
    }

    updateStats(newStats) {
        this.stats = { ...this.stats, ...newStats };
        this.updateDisplay();
    }
}

// Inicializar dashboard quando o DOM estiver pronto
let flexDashboard;

document.addEventListener('DOMContentLoaded', () => {
    // Aguardar um pouco para garantir que o modo flexível esteja carregado
    setTimeout(() => {
        if (document.querySelector('.flex-container')) {
            flexDashboard = new FlexDashboard();
        }
    }, 1000);
});

// Exportar para uso global
window.flexDashboard = flexDashboard; 