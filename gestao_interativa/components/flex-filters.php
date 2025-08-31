<?php
/**
 * Componente Filtros - Modo Flexível
 * Renderiza o sistema de filtros avançados
 */

class FlexFiltersComponent {
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
        $options = $this->getFilterOptions();
        $this->renderHTML($options);
    }
    
    private function getFilterOptions() {
        try {
            if (!$this->db) {
                return $this->getMockOptions();
            }
            
            $options = [
                'marcas' => $this->getMarcas(),
                'tamanhos' => $this->getTamanhos(),
                'veiculos' => $this->getVeiculos(),
                'status' => $this->getStatus(),
                'posicoes' => $this->getPosicoes()
            ];
            
            return $options;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar opções de filtro: " . $e->getMessage());
            return $this->getMockOptions();
        }
    }
    
    private function getMarcas() {
        $query = "SELECT DISTINCT marca FROM pneus 
                 WHERE ativo = 1 AND marca IS NOT NULL AND marca != '' 
                 ORDER BY marca";
        $result = $this->db->query($query);
        
        $marcas = [];
        while ($row = $result->fetch_assoc()) {
            $marcas[] = $row['marca'];
        }
        
        return $marcas;
    }
    
    private function getTamanhos() {
        $query = "SELECT DISTINCT tamanho FROM pneus 
                 WHERE ativo = 1 AND tamanho IS NOT NULL AND tamanho != '' 
                 ORDER BY tamanho";
        $result = $this->db->query($query);
        
        $tamanhos = [];
        while ($row = $result->fetch_assoc()) {
            $tamanhos[] = $row['tamanho'];
        }
        
        return $tamanhos;
    }
    
    private function getVeiculos() {
        $query = "SELECT DISTINCT v.placa, v.modelo 
                 FROM veiculos v 
                 INNER JOIN pneus p ON v.id = p.veiculo_id 
                 WHERE v.ativo = 1 AND p.ativo = 1 
                 ORDER BY v.placa";
        $result = $this->db->query($query);
        
        $veiculos = [];
        while ($row = $result->fetch_assoc()) {
            $veiculos[] = $row['placa'] . ' - ' . $row['modelo'];
        }
        
        return $veiculos;
    }
    
    private function getStatus() {
        return [
            'disponivel' => 'Disponível',
            'em_uso' => 'Em Uso',
            'manutencao' => 'Manutenção',
            'critico' => 'Crítico',
            'alerta' => 'Alerta'
        ];
    }
    
    private function getPosicoes() {
        return [
            'dianteira-esquerda' => 'Dianteira Esquerda',
            'dianteira-direita' => 'Dianteira Direita',
            'traseira-esquerda' => 'Traseira Esquerda',
            'traseira-direita' => 'Traseira Direita',
            'eixo-auxiliar' => 'Eixo Auxiliar'
        ];
    }
    
    private function getMockOptions() {
        return [
            'marcas' => ['Michelin', 'Bridgestone', 'Goodyear', 'Pirelli', 'Continental'],
            'tamanhos' => ['205/55R16', '215/55R17', '225/45R17', '235/45R18', '245/40R18'],
            'veiculos' => ['ABC-1234 - Caminhão 1', 'DEF-5678 - Caminhão 2', 'GHI-9012 - Van 1'],
            'status' => [
                'disponivel' => 'Disponível',
                'em_uso' => 'Em Uso',
                'manutencao' => 'Manutenção',
                'critico' => 'Crítico',
                'alerta' => 'Alerta'
            ],
            'posicoes' => [
                'dianteira-esquerda' => 'Dianteira Esquerda',
                'dianteira-direita' => 'Dianteira Direita',
                'traseira-esquerda' => 'Traseira Esquerda',
                'traseira-direita' => 'Traseira Direita',
                'eixo-auxiliar' => 'Eixo Auxiliar'
            ]
        ];
    }
    
    private function renderHTML($options) {
        ?>
        <div class="flex-filters" id="flex-filters">
            <div class="filters-header">
                <div class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros Avançados
                </div>
                <div class="filters-actions">
                    <button class="filter-btn" data-action="expand" id="btn-expand-filters">
                        <i class="fas fa-expand"></i>
                        Expandir
                    </button>
                    <button class="filter-btn" data-action="collapse" id="btn-collapse-filters" style="display: none;">
                        <i class="fas fa-compress"></i>
                        Recolher
                    </button>
                    <button class="filter-btn" data-action="save-preset">
                        <i class="fas fa-save"></i>
                        Salvar
                    </button>
                </div>
            </div>
            
            <div class="filters-content" id="filters-content">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-search"></i>
                            Buscar Pneu
                        </label>
                        <input type="text" class="filter-input" id="filter-search" placeholder="Código, marca, modelo...">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-tag"></i>
                            Status
                        </label>
                        <div class="status-filters">
                            <?php foreach ($options['status'] as $key => $label): ?>
                                <span class="status-filter <?= $key ?>" data-status="<?= $key ?>">
                                    <i class="fas fa-circle"></i> <?= $label ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-tag"></i>
                            Marca
                        </label>
                        <select class="filter-select" id="filter-marca">
                            <option value="">Todas as marcas</option>
                            <?php foreach ($options['marcas'] as $marca): ?>
                                <option value="<?= htmlspecialchars($marca) ?>"><?= htmlspecialchars($marca) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-ruler"></i>
                            Tamanho
                        </label>
                        <select class="filter-select" id="filter-tamanho">
                            <option value="">Todos os tamanhos</option>
                            <?php foreach ($options['tamanhos'] as $tamanho): ?>
                                <option value="<?= htmlspecialchars($tamanho) ?>"><?= htmlspecialchars($tamanho) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-dollar-sign"></i>
                            Faixa de Preço
                        </label>
                        <div class="price-range">
                            <input type="number" class="filter-input" id="filter-preco-min" placeholder="Mín" step="0.01">
                            <span class="separator">-</span>
                            <input type="number" class="filter-input" id="filter-preco-max" placeholder="Máx" step="0.01">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar"></i>
                            Data de Instalação
                        </label>
                        <div class="date-filters">
                            <input type="date" class="filter-input" id="filter-data-inicio">
                            <span class="separator">até</span>
                            <input type="date" class="filter-input" id="filter-data-fim">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-truck"></i>
                            Veículo
                        </label>
                        <select class="filter-select" id="filter-veiculo">
                            <option value="">Todos os veículos</option>
                            <?php foreach ($options['veiculos'] as $veiculo): ?>
                                <option value="<?= htmlspecialchars($veiculo) ?>"><?= htmlspecialchars($veiculo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Posição
                        </label>
                        <select class="filter-select" id="filter-posicao">
                            <option value="">Todas as posições</option>
                            <?php foreach ($options['posicoes'] as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filters-actions-bottom">
                    <div class="filters-left">
                        <div class="results-counter">
                            <i class="fas fa-list"></i>
                            <span>Resultados: <span class="count" id="filter-count">0</span></span>
                        </div>
                        <div class="active-filters" id="active-filters">
                            <!-- Filtros ativos serão mostrados aqui -->
                        </div>
                    </div>
                    <div class="filters-right">
                        <button class="btn-clear-filters" onclick="flexFilters.clearFilters()">
                            <i class="fas fa-times"></i>
                            Limpar
                        </button>
                        <button class="btn-apply-filters" onclick="flexFilters.applyFilters()">
                            <i class="fas fa-check"></i>
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // Inicializar filtros quando carregado
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof flexFilters === 'undefined') {
                // Carregar script dos filtros se não estiver carregado
                const script = document.createElement('script');
                script.src = 'assets/js/flex-filters.js';
                script.onload = function() {
                    if (typeof FlexFilters !== 'undefined') {
                        window.flexFilters = new FlexFilters();
                    }
                };
                document.head.appendChild(script);
            }
            
            // Configurar botões de expandir/recolher
            const expandBtn = document.getElementById('btn-expand-filters');
            const collapseBtn = document.getElementById('btn-collapse-filters');
            const filtersContent = document.getElementById('filters-content');
            
            if (expandBtn && collapseBtn && filtersContent) {
                expandBtn.addEventListener('click', function() {
                    filtersContent.style.display = 'block';
                    expandBtn.style.display = 'none';
                    collapseBtn.style.display = 'inline-flex';
                });
                
                collapseBtn.addEventListener('click', function() {
                    filtersContent.style.display = 'none';
                    expandBtn.style.display = 'inline-flex';
                    collapseBtn.style.display = 'none';
                });
            }
        });
        </script>
        <?php
    }
}

// Renderizar componente se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $filters = new FlexFiltersComponent();
    $filters->render();
}
?> 