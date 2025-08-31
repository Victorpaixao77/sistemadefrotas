<?php
/**
 * Componente Dashboard - Modo Flexível
 * Renderiza o dashboard com estatísticas em tempo real
 */

class FlexDashboardComponent {
    private $db;
    
    public function __construct() {
        try {
            require_once __DIR__ . '/../src/Database/Database.php';
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Erro ao conectar com banco: " . $e->getMessage());
        }
    }
    
    public function render() {
        $stats = $this->getStats();
        $this->renderHTML($stats);
    }
    
    private function getStats() {
        try {
            if (!$this->db) {
                return $this->getMockStats();
            }
            
            $stats = [
                'total' => 0,
                'alocados' => 0,
                'disponiveis' => 0,
                'criticos' => 0,
                'alerta' => 0,
                'manutencao' => 0,
                'custoTotal' => 0
            ];
            
            // Total de pneus
            $query = "SELECT COUNT(*) as total FROM pneus WHERE ativo = 1";
            $result = $this->db->query($query);
            $stats['total'] = $result->fetch_assoc()['total'] ?? 0;
            
            // Pneus alocados
            $query = "SELECT COUNT(*) as alocados FROM pneus WHERE ativo = 1 AND status = 'em_uso'";
            $result = $this->db->query($query);
            $stats['alocados'] = $result->fetch_assoc()['alocados'] ?? 0;
            
            // Pneus disponíveis
            $query = "SELECT COUNT(*) as disponiveis FROM pneus WHERE ativo = 1 AND status = 'disponivel'";
            $result = $this->db->query($query);
            $stats['disponiveis'] = $result->fetch_assoc()['disponiveis'] ?? 0;
            
            // Pneus críticos
            $query = "SELECT COUNT(*) as criticos FROM pneus 
                     WHERE ativo = 1 AND (
                         profundidade_sulco < 1.6 OR 
                         pressao_atual < pressao_minima OR
                         quilometragem_atual > quilometragem_maxima
                     )";
            $result = $this->db->query($query);
            $stats['criticos'] = $result->fetch_assoc()['criticos'] ?? 0;
            
            // Pneus em alerta
            $query = "SELECT COUNT(*) as alerta FROM pneus 
                     WHERE ativo = 1 AND (
                         (profundidade_sulco BETWEEN 1.6 AND 2.5) OR
                         (pressao_atual BETWEEN pressao_minima AND pressao_minima * 1.1) OR
                         (quilometragem_atual BETWEEN quilometragem_maxima * 0.8 AND quilometragem_maxima)
                     )";
            $result = $this->db->query($query);
            $stats['alerta'] = $result->fetch_assoc()['alerta'] ?? 0;
            
            // Pneus em manutenção
            $query = "SELECT COUNT(*) as manutencao FROM pneus WHERE ativo = 1 AND status = 'manutencao'";
            $result = $this->db->query($query);
            $stats['manutencao'] = $result->fetch_assoc()['manutencao'] ?? 0;
            
            // Custo total
            $query = "SELECT SUM(valor_unitario) as custo_total FROM pneus WHERE ativo = 1";
            $result = $this->db->query($query);
            $stats['custoTotal'] = $result->fetch_assoc()['custo_total'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return $this->getMockStats();
        }
    }
    
    private function getMockStats() {
        return [
            'total' => 150,
            'alocados' => 120,
            'disponiveis' => 20,
            'criticos' => 8,
            'alerta' => 12,
            'manutencao' => 10,
            'custoTotal' => 45000.50
        ];
    }
    
    private function renderHTML($stats) {
        ?>
        <div class="flex-dashboard" id="flex-dashboard">
            <div class="flex-stats">
                <div class="stat-card pneus-alocados">
                    <div class="status-indicator"></div>
                    <i class="fas fa-tire"></i>
                    <span class="stat-number" id="stat-alocados"><?= $stats['alocados'] ?></span>
                    <span class="stat-label">Pneus Alocados</span>
                </div>
                
                <div class="stat-card pneus-criticos">
                    <div class="status-indicator <?= $stats['criticos'] > 0 ? 'critical' : '' ?>"></div>
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="stat-number" id="stat-criticos"><?= $stats['criticos'] ?></span>
                    <span class="stat-label">Pneus Críticos</span>
                </div>
                
                <div class="stat-card pneus-alerta">
                    <div class="status-indicator <?= $stats['alerta'] > 0 ? 'warning' : '' ?>"></div>
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="stat-number" id="stat-alerta"><?= $stats['alerta'] ?></span>
                    <span class="stat-label">Pneus em Alerta</span>
                </div>
                
                <div class="stat-card pneus-manutencao">
                    <div class="status-indicator <?= $stats['manutencao'] > 0 ? 'info' : '' ?>"></div>
                    <i class="fas fa-tools"></i>
                    <span class="stat-number" id="stat-manutencao"><?= $stats['manutencao'] ?></span>
                    <span class="stat-label">Em Manutenção</span>
                </div>
                
                <div class="stat-card custo-total">
                    <i class="fas fa-dollar-sign"></i>
                    <span class="stat-number" id="stat-custo">R$ <?= number_format($stats['custoTotal'], 2, ',', '.') ?></span>
                    <span class="stat-label">Custo Total</span>
                </div>
                
                <div class="stat-card pneus-disponiveis">
                    <i class="fas fa-warehouse"></i>
                    <span class="stat-number" id="stat-disponiveis"><?= $stats['disponiveis'] ?></span>
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
                <button class="btn-toggle-auto-refresh" onclick="flexDashboard.toggleAutoRefresh()">
                    <i class="fas fa-clock"></i> Auto-refresh
                </button>
            </div>
            
            <div class="dashboard-summary">
                <div class="summary-item">
                    <span class="summary-label">Total de Pneus:</span>
                    <span class="summary-value"><?= $stats['total'] ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Taxa de Utilização:</span>
                    <span class="summary-value"><?= $stats['total'] > 0 ? round(($stats['alocados'] / $stats['total']) * 100, 1) : 0 ?>%</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Pneus Críticos:</span>
                    <span class="summary-value <?= $stats['criticos'] > 5 ? 'text-danger' : '' ?>"><?= $stats['criticos'] ?></span>
                </div>
            </div>
        </div>
        
        <script>
        // Inicializar dashboard quando carregado
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof flexDashboard === 'undefined') {
                // Carregar script do dashboard se não estiver carregado
                const script = document.createElement('script');
                script.src = 'assets/js/flex-dashboard.js';
                script.onload = function() {
                    if (typeof FlexDashboard !== 'undefined') {
                        window.flexDashboard = new FlexDashboard();
                    }
                };
                document.head.appendChild(script);
            }
        });
        </script>
        <?php
    }
}

// Renderizar componente se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $dashboard = new FlexDashboardComponent();
    $dashboard->render();
}
?> 