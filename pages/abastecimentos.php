<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Abastecimentos";

// Função para buscar abastecimentos do banco de dados
function getAbastecimentos($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM abastecimentos WHERE empresa_id = :empresa_id AND status = 'aprovado'";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada com JOIN para obter informações das cidades
        $sql = "SELECT 
                a.*,
                v.placa as veiculo_placa,
                m.nome as motorista_nome,
                r.id as rota_id,
                co.nome as cidade_origem_nome,
                cd.nome as cidade_destino_nome
                FROM abastecimentos a
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                LEFT JOIN motoristas m ON a.motorista_id = m.id
                LEFT JOIN rotas r ON a.rota_id = r.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado'
                ORDER BY a.data_abastecimento DESC, a.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log para debug
        error_log("Dados dos abastecimentos (PHP): " . print_r($result, true));
        
        return [
            'abastecimentos' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar abastecimentos: " . $e->getMessage());
        return [
            'abastecimentos' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar abastecimentos com paginação
$resultado = getAbastecimentos($pagina_atual);
$abastecimentos = $resultado['abastecimentos'];
$total_paginas = $resultado['total_paginas'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addRefuelBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Abastecimento
                        </button>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Litros</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ 0,00</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Médias</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ 0,00/L</span>
                                <span class="metric-subtitle">0,0 km/L média</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchRefueling" placeholder="Buscar abastecimento...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                        </select>
                        <select id="fuelFilter">
                            <option value="">Todos os combustíveis</option>
                            <option value="Diesel S10">Diesel S10</option>
                            <option value="Diesel Comum">Diesel Comum</option>
                            <option value="Gasolina">Gasolina</option>
                            <option value="Etanol">Etanol</option>
                        </select>
                        <select id="paymentFilter">
                            <option value="">Todas as formas de pagamento</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão">Cartão</option>
                            <option value="Boleto">Boleto</option>
                            <option value="PIX">PIX</option>
                        </select>
                    </div>
                </div>

                <!-- Refueling Table -->
                <div class="data-table-container">
                    <table class="data-table" id="refuelingTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Motorista</th>
                                <th>Posto</th>
                                <th>Litros</th>
                                <th>Valor/L</th>
                                <th>Valor Total</th>
                                <th>ARLA</th>
                                <th>Km</th>
                                <th>Forma Pgto</th>
                                <th>Rota</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($abastecimentos)): ?>
                                <?php foreach ($abastecimentos as $abastecimento): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($abastecimento['data_abastecimento'])); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['veiculo_placa']); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['motorista_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['posto']); ?></td>
                                    <td><?php echo number_format($abastecimento['litros'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($abastecimento['valor_litro'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($abastecimento['valor_total'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php if (!empty($abastecimento['inclui_arla']) && $abastecimento['inclui_arla'] == 1): ?>
                                            <?php 
                                            $percentual_arla = 0;
                                            if ($abastecimento['litros'] > 0) {
                                                $percentual_arla = ($abastecimento['litros_arla'] / $abastecimento['litros']) * 100;
                                            }
                                            $classe_percentual = '';
                                            if ($percentual_arla >= 3 && $percentual_arla <= 5) {
                                                $classe_percentual = 'percentual-ok';
                                            } elseif ($percentual_arla > 5) {
                                                $classe_percentual = 'percentual-alto';
                                            } else {
                                                $classe_percentual = 'percentual-baixo';
                                            }
                                            
                                            ?>
                                            <span class="percentual-arla <?php echo $classe_percentual; ?>">
                                                <?php echo number_format($percentual_arla, 1, ',', '.'); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($abastecimento['km_atual'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['forma_pagamento']); ?></td>
                                    <td><?php 
                                        error_log("Dados da rota para abastecimento ID {$abastecimento['id']}: " . 
                                                  "Origem: {$abastecimento['cidade_origem_nome']}, " . 
                                                  "Destino: {$abastecimento['cidade_destino_nome']}");
                                        if (!empty($abastecimento['cidade_origem_nome']) && !empty($abastecimento['cidade_destino_nome'])) {
                                            echo htmlspecialchars($abastecimento['cidade_origem_nome'] . ' → ' . $abastecimento['cidade_destino_nome']);
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td class="actions">
                                        <button class="btn-icon edit-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!empty($abastecimento['comprovante'])): ?>
                                            <button class="btn-icon view-comprovante-btn" data-comprovante="<?php echo htmlspecialchars($abastecimento['comprovante']); ?>" title="Ver Comprovante">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center">Nenhum abastecimento encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <div class="pagination">
                    <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>" 
                       class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <span class="pagination-info">
                        Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                    </span>
                    
                    <a href="?page=<?php echo min($total_paginas, $pagina_atual + 1); ?>" 
                       class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Analytics Section (gráficos) -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Consumo</h2>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Consumo de Combustível (Últimos 6 meses)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="fuelConsumptionChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Rendimento por Veículo (km/L)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="fuelEfficiencyChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Anomalias de Consumo</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="anomaliesChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Consumo por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="driverConsumptionChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Eficiência por Veículo (R$/km)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="vehicleEfficiencyChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Evolução do Custo Mensal</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyCostChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
    
    <!-- Add/Edit Refueling Modal -->
    <div class="modal" id="refuelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Abastecimento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="refuelForm">
                    <input type="hidden" id="refuelId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="data_rota_filtro">Data da Rota*</label>
                            <input type="date" id="data_rota_filtro" name="data_rota_filtro" required>
                        </div>
                        <div class="form-group">
                            <label for="data_abastecimento">Data Abastecimento*</label>
                            <input type="datetime-local" id="data_abastecimento" name="data_abastecimento" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="veiculo_id">Veículo*</label>
                            <select id="veiculo_id" name="veiculo_id" required>
                                <option value="">Selecione um veículo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="motorista_id">Motorista*</label>
                            <select id="motorista_id" name="motorista_id" required>
                                <option value="">Selecione um motorista</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="rota_id">Rota*</label>
                            <select id="rota_id" name="rota_id" required>
                                <option value="">Selecione a rota</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_combustivel">Combustível*</label>
                            <select id="tipo_combustivel" name="tipo_combustivel" required>
                                <option value="">Selecione o combustível</option>
                                <option value="Diesel S10">Diesel S10</option>
                                <option value="Diesel Comum">Diesel Comum</option>
                                <option value="Gasolina">Gasolina</option>
                                <option value="Etanol">Etanol</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="litros">Litros*</label>
                            <input type="text" id="litros" name="litros" class="numeric-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_litro">Valor por Litro*</label>
                            <input type="text" id="valor_litro" name="valor_litro" class="numeric-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_total">Valor Total*</label>
                            <input type="text" id="valor_total" name="valor_total" class="numeric-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="km_atual">Quilometragem*</label>
                            <input type="number" id="km_atual" name="km_atual" required placeholder="Ex: 150000">
                            <small class="form-text" style="color: #6c757d; font-size: 0.875rem; margin-top: 4px;">
                                <i class="fas fa-info-circle"></i> <span id="km_atual_help">Selecione uma rota para validar a quilometragem</span>
                            </small>
                            <div id="km_atual_validation" style="margin-top: 5px; font-size: 0.875rem;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="posto">Posto*</label>
                            <input type="text" id="posto" name="posto" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="forma_pagamento">Forma de Pagamento*</label>
                            <select id="forma_pagamento" name="forma_pagamento" required>
                                <option value="">Selecione a forma de pagamento</option>
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="Cartão">Cartão</option>
                                <option value="Boleto">Boleto</option>
                                <option value="PIX">PIX</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Seção ARLA -->
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="inclui_arla" name="inclui_arla" value="1">
                                <span class="checkmark"></span>
                                Este abastecimento incluiu ARLA?
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle"></i>
                            A porcentagem ideal de ARLA é de 3% a 5% em relação ao volume de diesel.
                        </small>
                    </div>
                    
                    <!-- Campos ARLA (inicialmente ocultos) -->
                    <div id="campos_arla" class="form-grid" style="display: none;">
                        <div class="form-group">
                            <label for="litros_arla">Litros ARLA</label>
                            <input type="text" id="litros_arla" name="litros_arla" class="numeric-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_litro_arla">Valor/Litro ARLA</label>
                            <input type="text" id="valor_litro_arla" name="valor_litro_arla" class="numeric-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_total_arla">Valor Total ARLA</label>
                            <input type="text" id="valor_total_arla" name="valor_total_arla" class="numeric-input" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="comprovante">Comprovante</label>
                        <input type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text text-muted">Formatos aceitos: PDF, JPG, JPEG, PNG</small>
                        <div id="comprovante_atual"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelRefuelBtn" class="btn-secondary">Cancelar</button>
                <button id="saveRefuelBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal" id="filterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtros</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="filterMonth">Mês/Ano</label>
                        <input type="month" id="filterMonth" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary">Limpar</button>
                <button id="applyFilterBtn" class="btn-primary">Aplicar</button>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal" id="helpModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Abastecimentos</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>O módulo de Abastecimentos permite gerenciar todos os abastecimentos da sua frota, oferecendo análises detalhadas de consumo e custos.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Novo Abastecimento:</strong> Registre novos abastecimentos com informações como:
                            <ul>
                                <li>Data e hora</li>
                                <li>Veículo e motorista</li>
                                <li>Tipo de combustível e quantidade</li>
                                <li>Valor por litro e total</li>
                                <li>Quilometragem atual</li>
                                <li>Posto e forma de pagamento</li>
                            </ul>
                        </li>
                        <li><strong>Filtros:</strong> Filtre os dados por:
                            <ul>
                                <li>Mês/Ano</li>
                                <li>Veículo</li>
                                <li>Motorista</li>
                                <li>Tipo de combustível</li>
                                <li>Forma de pagamento</li>
                            </ul>
                        </li>
                        <li><strong>Análises:</strong> Visualize:
                            <ul>
                                <li>Total de abastecimentos no período</li>
                                <li>Consumo total em litros</li>
                                <li>Valor total gasto</li>
                                <li>Média de consumo (km/L)</li>
                                <li>Gráficos de consumo e eficiência</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Como Usar</h3>
                    <ol>
                        <li><strong>Registrar Abastecimento:</strong>
                            <ul>
                                <li>Clique no botão "Novo Abastecimento"</li>
                                <li>Preencha todos os campos obrigatórios (*)</li>
                                <li>Clique em "Salvar"</li>
                            </ul>
                        </li>
                        <li><strong>Filtrar Dados:</strong>
                            <ul>
                                <li>Clique no botão de filtro (ícone de funil)</li>
                                <li>Selecione o mês/ano desejado</li>
                                <li>Clique em "Aplicar" para ver os dados filtrados</li>
                            </ul>
                        </li>
                        <li><strong>Gerenciar Registros:</strong>
                            <ul>
                                <li>Use os botões de editar (lápis) para modificar um registro</li>
                                <li>Use os botões de excluir (lixeira) para remover um registro</li>
                            </ul>
                        </li>
                    </ol>
                </div>

                <div class="help-section">
                    <h3>Dicas</h3>
                    <ul>
                        <li>Mantenha os registros de quilometragem atualizados para obter análises precisas de consumo</li>
                        <li>Use os filtros para comparar o consumo entre diferentes períodos</li>
                        <li>Acompanhe regularmente os gráficos para identificar variações no consumo dos veículos</li>
                        <li>Exporte os dados quando precisar fazer análises mais detalhadas em planilhas</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        let consumptionChart = null;
        let efficiencyChart = null;
        let currentFilter = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa a página
            initializePage();
            
            // Configura eventos dos modais
            setupModals();
            
            // Configura filtros
            setupFilters();

            // Garante que o filtro está no mês atual e carrega o resumo
            const today = new Date();
            const defaultDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
            document.getElementById('filterMonth').value = defaultDate;
            currentFilter = defaultDate;
            loadRefuelingSummary();
        });
        
        function initializePage() {
            // Load refuel data from API
            loadRefuelingData();
            
            // Load summary data
            loadRefuelingSummary();
            
            // Load chart data
            loadConsumptionChart().then(() => {
                // Carrega o gráfico de eficiência apenas depois que o gráfico de consumo for carregado
                loadEfficiencyChart();
            });
            
            // Setup button events
            document.getElementById('addRefuelBtn').addEventListener('click', showAddRefuelModal);
            document.getElementById('filterBtn').addEventListener('click', showFilterModal);
            document.getElementById('helpBtn').addEventListener('click', showHelpModal);
            
            // Setup search
            const searchInput = document.getElementById('searchRefueling');
            searchInput.addEventListener('input', debounce(() => {
                loadRefuelingData();
            }, 300));
            
            // Setup table buttons
            setupTableButtons();
        }
        
        function loadRefuelingData() {
            // Obtém valores dos filtros
            const search = document.getElementById('searchRefueling').value;
            const currentPage = new URLSearchParams(window.location.search).get('page') || 1;
            const vehicleFilter = document.getElementById('vehicleFilter').value;
            const driverFilter = document.getElementById('driverFilter').value;
            const fuelFilter = document.getElementById('fuelFilter').value;
            const paymentFilter = document.getElementById('paymentFilter').value;
            
            // Constrói URL com filtros e paginação
            let url = `../api/refuel_data.php?action=list&page=${currentPage}&limit=5`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }
            if (vehicleFilter) url += `&veiculo=${encodeURIComponent(vehicleFilter)}`;
            if (driverFilter) url += `&motorista=${encodeURIComponent(driverFilter)}`;
            if (fuelFilter) url += `&combustivel=${encodeURIComponent(fuelFilter)}`;
            if (paymentFilter) url += `&pagamento=${encodeURIComponent(paymentFilter)}`;
            
            // Carrega dados dos abastecimentos
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateRefuelingsTable(data.data);
                        updatePagination(data.pagination);
                    } else {
                        throw new Error(data.error || 'Erro ao carregar dados dos abastecimentos');
                    }
                })
                .catch(error => {
                    console.error('Error loading refueling data:', error);
                    alert('Erro ao carregar dados dos abastecimentos: ' + error.message);
                });
        }
        
        function updateRefuelingsTable(refuelings) {
            const tbody = document.querySelector('.data-table tbody');
            tbody.innerHTML = '';
            
            if (refuelings && refuelings.length > 0) {
                refuelings.forEach(refuel => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(refuel.data_abastecimento)}</td>
                        <td>${refuel.veiculo_placa || '-'}</td>
                        <td>${refuel.motorista_nome || '-'}</td>
                        <td>${refuel.posto || '-'}</td>
                        <td>${formatNumber(refuel.litros, 1)} L</td>
                        <td>R$ ${formatNumber(refuel.valor_litro, 2)}</td>
                        <td>R$ ${formatNumber(refuel.valor_total, 2)}</td>
                        <td>
                            ${refuel.inclui_arla == 1 ? 
                                (() => {
                                    const percentual = refuel.litros > 0 ? (refuel.litros_arla / refuel.litros) * 100 : 0;
                                    let classePercentual = '';
                                    if (percentual >= 3 && percentual <= 5) {
                                        classePercentual = 'percentual-ok';
                                    } else if (percentual > 5) {
                                        classePercentual = 'percentual-alto';
                                    } else {
                                        classePercentual = 'percentual-baixo';
                                    }
                                    return `<span class="percentual-arla ${classePercentual}">
                                        ${formatNumber(percentual, 1)}%
                                    </span>`;
                                })() : 
                                '<span class="text-muted">-</span>'
                            }
                        </td>
                        <td>${formatNumber(refuel.km_atual, 0)}</td>
                        <td>${refuel.forma_pagamento || '-'}</td>
                        <td>${refuel.cidade_origem_nome || '-'} → ${refuel.cidade_destino_nome || '-'}</td>
                        <td class="actions">
                            <button class="btn-icon edit-btn" data-id="${refuel.id}" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (!empty($abastecimento['comprovante'])): ?>
                                <button class="btn-icon view-comprovante-btn" data-comprovante="${refuel.comprovante}" title="Ver Comprovante">
                                    <i class="fas fa-file-alt"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn-icon delete-btn" data-id="${refuel.id}" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                
                // Configura eventos dos botões
                setupTableButtons();
            } else {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center">Nenhum abastecimento encontrado</td></tr>';
            }
        }
        
        function updatePagination(pagination) {
            const paginationContainer = document.querySelector('.pagination');
            if (!paginationContainer) return;
            
            const prevBtn = paginationContainer.querySelector('a:first-child');
            const nextBtn = paginationContainer.querySelector('a:last-child');
            const paginationInfo = paginationContainer.querySelector('.pagination-info');
            
            // Atualiza informações da página
            paginationInfo.textContent = `Página ${pagination.page} de ${pagination.totalPages}`;
            
            // Atualiza estado dos botões
            prevBtn.classList.toggle('disabled', pagination.page <= 1);
            nextBtn.classList.toggle('disabled', pagination.page >= pagination.totalPages);
            
            // Atualiza URLs dos botões
            prevBtn.href = `?page=${Math.max(1, pagination.page - 1)}`;
            nextBtn.href = `?page=${Math.min(pagination.totalPages, pagination.page + 1)}`;
        }
        
        function loadRefuelingSummary() {
            let url = '../api/refuel_data.php?action=summary';
            
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar resumo dos abastecimentos');
                    }

                    // Atualiza os cards de métricas com os dados filtrados
                    updateMetricCards(data.data);
                })
                .catch(error => {
                    console.error('Error loading refueling summary:', error);
                });
        }

        function updateMetricCards(data) {
            // Atualiza os valores nos cards de métricas
            document.querySelector('.metric-value').textContent = `R$ ${formatNumber(data.total_gasto, 2)}`;
            document.querySelectorAll('.metric-value')[1].textContent = `${formatNumber(data.total_litros, 2)}L`;
            document.querySelectorAll('.metric-value')[2].textContent = `${formatNumber(data.total_abastecimentos)}`;
            document.querySelectorAll('.metric-value')[3].textContent = `R$ ${formatNumber(data.media_valor_litro, 2)}/L`;
            document.querySelectorAll('.metric-subtitle')[3].textContent = `${formatNumber(data.media_km_litro, 1)} km/L média`;
        }
        
        function loadConsumptionChart() {
            let url = '../api/refuel_data.php?action=consumption_chart';
            
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }

            return fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar dados do gráfico de consumo');
                    }

                    // Atualiza o gráfico de consumo com os dados filtrados
                    if (consumptionChart) {
                        consumptionChart.destroy();
                    }

                    const ctx = document.getElementById('fuelConsumptionChart').getContext('2d');
                    consumptionChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Consumo de Combustível (L)',
                                data: data.values,
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading consumption chart:', error);
                });
        }
        
        function loadEfficiencyChart() {
            let url = '../api/refuel_data.php?action=efficiency_chart';
            
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }

            return fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar dados do gráfico de eficiência');
                    }

                    // Atualiza o gráfico de eficiência com os dados filtrados
                    if (efficiencyChart) {
                        efficiencyChart.destroy();
                    }

                    const ctx = document.getElementById('fuelEfficiencyChart').getContext('2d');
                    efficiencyChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Eficiência (km/L)',
                                data: data.values,
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading efficiency chart:', error);
                });
        }
        
        function setupTableButtons() {
            // Setup edit buttons
            document.querySelectorAll('.btn-icon.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    showEditRefuelModal(id);
                });
            });
            
            // Setup delete buttons
            document.querySelectorAll('.btn-icon.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    showDeleteConfirmation(id);
                });
            });
        }
        
        function showDeleteConfirmation(refuelId) {
            // Load refuel data to show details in confirmation
            fetch(`../api/refuel_data.php?action=get&id=${refuelId}`)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
                    }
                    
                    const refuel = data.data;
                    
                    if (confirm(`Deseja realmente excluir o abastecimento do veículo ${refuel.veiculo_placa} realizado em ${formatDate(refuel.data_abastecimento)}?`)) {
                        deleteRefuel(refuelId);
                    }
                })
                .catch(error => {
                    console.error('Error loading refuel data:', error);
                    alert('Erro ao carregar dados do abastecimento: ' + error.message);
                });
        }
        
        function deleteRefuel(refuelId) {
            fetch(`../api/refuel_actions.php?action=delete&id=${refuelId}`, {
                method: 'GET',
                credentials: 'include'
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Erro ao excluir abastecimento');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erro ao excluir abastecimento');
                }
                
                alert(data.message);
                loadRefuelingData();
                loadRefuelingSummary();
                loadConsumptionChart();
                loadEfficiencyChart();
            })
            .catch(error => {
                console.error('Error deleting refuel:', error);
                alert('Erro ao excluir abastecimento: ' + error.message);
            });
        }
        
        // Funções auxiliares
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString.replace(' ', 'T'));
            return date.toLocaleDateString('pt-BR');
        }
        
        function formatNumber(value, decimals = 0) {
            if (!value) return '0';
            return Number(value).toLocaleString('pt-BR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        function setupModals() {
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            document.getElementById('cancelRefuelBtn').addEventListener('click', closeAllModals);
        }
        
        function showAddRefuelModal() {
            document.getElementById('refuelForm').reset();
            document.getElementById('refuelId').value = '';
            document.getElementById('modalTitle').textContent = 'Adicionar Abastecimento';
            document.getElementById('refuelModal').classList.add('active');
            
            // Set current date and time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const dateInput = document.getElementById('data_abastecimento');
            dateInput.value = now.toISOString().slice(0, 16);
            
            // Reset and disable fields initially
            const veiculo = document.getElementById('veiculo_id');
            const motorista = document.getElementById('motorista_id');
            const rota = document.getElementById('rota_id');
            
            veiculo.value = '';
            motorista.value = '';
            rota.value = '';
            
            // Setup automatic total calculation
            setupValorTotalCalc();
            
            // Setup ARLA fields
            setupArlaFields();
            
            // Setup numeric inputs
            setupNumericInputs();
        }
        
        function showEditRefuelModal(refuelId) {
            document.getElementById('refuelForm').reset();
            // Carrega os dados completos do abastecimento
            fetch(`../api/refuel_data.php?action=get&id=${refuelId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
                    // Chama a função global do JS externo para preencher e abrir o modal corretamente
                    window.openEditRefuelModal(data.data);
                })
                .catch(error => {
                    alert('Erro ao carregar dados do abastecimento: ' + error.message);
                });
        }
        
        function setupValorTotalCalc() {
            const litrosInput = document.getElementById('litros');
            const valorLitroInput = document.getElementById('valor_litro');
            const valorTotalInput = document.getElementById('valor_total');
            
            function calcularValorTotal() {
                const litros = parseFloat(litrosInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorLitro = parseFloat(valorLitroInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorTotal = litros * valorLitro;
                
                if (!isNaN(valorTotal)) {
                    valorTotalInput.value = valorTotal.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
            
            litrosInput.addEventListener('input', calcularValorTotal);
            valorLitroInput.addEventListener('input', calcularValorTotal);
        }
        
        function setupArlaFields() {
            const incluiArlaCheckbox = document.getElementById('inclui_arla');
            const camposArla = document.getElementById('campos_arla');
            const litrosArlaInput = document.getElementById('litros_arla');
            const valorLitroArlaInput = document.getElementById('valor_litro_arla');
            const valorTotalArlaInput = document.getElementById('valor_total_arla');
            
            // Controla a exibição dos campos ARLA
            incluiArlaCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    camposArla.style.display = 'grid';
                    // Torna os campos obrigatórios quando ARLA está marcado
                    litrosArlaInput.required = true;
                    valorLitroArlaInput.required = true;
                } else {
                    camposArla.style.display = 'none';
                    // Remove obrigatoriedade e limpa os campos
                    litrosArlaInput.required = false;
                    valorLitroArlaInput.required = false;
                    valorTotalArlaInput.required = false;
                    litrosArlaInput.value = '';
                    valorLitroArlaInput.value = '';
                    valorTotalArlaInput.value = '';
                }
            });
            
            // Cálculo automático do valor total ARLA
            function calcularValorTotalArla() {
                const litros = parseFloat(litrosArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorLitro = parseFloat(valorLitroArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorTotal = litros * valorLitro;
                
                if (!isNaN(valorTotal)) {
                    valorTotalArlaInput.value = valorTotal.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
            
            litrosArlaInput.addEventListener('input', calcularValorTotalArla);
            valorLitroArlaInput.addEventListener('input', calcularValorTotalArla);
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('active');
            });
        }
        
        function setupNumericInputs() {
            const numericInputs = document.querySelectorAll('.numeric-input');
            
            numericInputs.forEach(input => {
                // Formata o valor inicial se houver
                if (input.value) {
                    const numericValue = parseFloat(input.value.replace(',', '.'));
                    if (!isNaN(numericValue)) {
                        input.value = numericValue.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
                
                input.addEventListener('input', function(e) {
                    let value = e.target.value;
                    
                    // Remove tudo exceto números, vírgula e ponto
                    value = value.replace(/[^\d,.]/g, '');
                    
                    // Remove todos os pontos e substitui vírgula por ponto temporariamente
                    value = value.replace(/\./g, '');
                    
                    // Garante apenas uma vírgula
                    const parts = value.split(',');
                    if (parts.length > 2) {
                        value = parts[0] + ',' + parts.slice(1).join('');
                    }
                    
                    // Limita a 2 casas decimais
                    if (parts[1] && parts[1].length > 2) {
                        value = parts[0] + ',' + parts[1].slice(0, 2);
                    }
                    
                    // Atualiza o valor do campo
                    e.target.value = value;
                });
                
                input.addEventListener('blur', function(e) {
                    let value = e.target.value;
                    
                    if (value) {
                        // Converte para número (troca vírgula por ponto)
                        const numericValue = parseFloat(value.replace(/\./g, '').replace(',', '.'));
                        if (!isNaN(numericValue)) {
                            // Formata o número de volta para exibição
                            e.target.value = numericValue.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                });
            });
        }

        function showHelpModal() {
            document.getElementById('helpModal').classList.add('active');
        }

        function setupFilters() {
            // Set default value to current month/year
            const today = new Date();
            const defaultDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
            document.getElementById('filterMonth').value = defaultDate;
            currentFilter = defaultDate;
            updateFilterButtonState();

            // Setup filter modal buttons
            document.getElementById('applyFilterBtn').addEventListener('click', () => {
                const filterMonth = document.getElementById('filterMonth').value;
                currentFilter = filterMonth;
                loadRefuelingData();
                loadRefuelingSummary();
                loadConsumptionChart();
                loadEfficiencyChart();
                closeAllModals();
                updateFilterButtonState();
            });

            document.getElementById('clearFilterBtn').addEventListener('click', () => {
                document.getElementById('filterMonth').value = '';
                currentFilter = null;
                loadRefuelingData();
                loadRefuelingSummary();
                loadConsumptionChart();
                loadEfficiencyChart();
                closeAllModals();
                updateFilterButtonState();
            });
        }
        
        function updateFilterButtonState() {
            const filterBtn = document.getElementById('filterBtn');
            if (currentFilter) {
                filterBtn.classList.add('active');
                filterBtn.title = `Filtro: ${formatMonthYear(currentFilter)}`;
            } else {
                filterBtn.classList.remove('active');
                filterBtn.title = 'Filtros';
            }
        }

        function formatMonthYear(dateString) {
            const [year, month] = dateString.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        }

        function showFilterModal() {
            document.getElementById('filterModal').classList.add('active');
        }

        // Torna as funções globais para o JS externo
        window.loadRefuelingData = loadRefuelingData;
        window.loadRefuelingSummary = loadRefuelingSummary;
        window.loadConsumptionChart = loadConsumptionChart;
        window.loadEfficiencyChart = loadEfficiencyChart;

        // ===== VALIDAÇÃO DE QUILOMETRAGEM PARA ABASTECIMENTOS =====
        
        // Função para validar quilometragem do abastecimento
        async function validarKmAbastecimento(rotaId, kmAbastecimento) {
            if (!rotaId || !kmAbastecimento) {
                return { valido: false, mensagem: 'Dados insuficientes para validação' };
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'validar_km_abastecimento');
                formData.append('rota_id', rotaId);
                formData.append('km_abastecimento', kmAbastecimento);
                
                const response = await fetch('../api/validar_quilometragem.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Erro na validação de quilometragem:', error);
                return { valido: false, mensagem: 'Erro na validação' };
            }
        }
        
        // Função para obter informações de abastecimentos da rota
        async function obterAbastecimentosRota(rotaId) {
            if (!rotaId) return null;
            
            try {
                const response = await fetch(`../api/validar_quilometragem.php?action=obter_abastecimentos_rota&rota_id=${rotaId}`);
                const data = await response.json();
                return data.success ? data : null;
            } catch (error) {
                console.error('Erro ao obter abastecimentos da rota:', error);
                return null;
            }
        }
        
        // Configurar validação quando rota for selecionada
        function configurarValidacaoKmAbastecimento() {
            const rotaSelect = document.getElementById('rota_id');
            const kmAtualInput = document.getElementById('km_atual');
            const kmAtualHelp = document.getElementById('km_atual_help');
            const kmAtualValidation = document.getElementById('km_atual_validation');
            
            if (!rotaSelect || !kmAtualInput) return;
            
            // Quando rota for selecionada
            rotaSelect.addEventListener('change', async function() {
                const rotaId = this.value;
                
                if (rotaId) {
                    const dadosRota = await obterAbastecimentosRota(rotaId);
                    if (dadosRota) {
                        let helpText = `<i class="fas fa-info-circle"></i> KM Saída da rota: ${dadosRota.km_saida_rota.toLocaleString('pt-BR')} km`;
                        let minValue = dadosRota.km_saida_rota + 1;
                        
                        if (dadosRota.total_abastecimentos > 0) {
                            helpText += `<br><i class="fas fa-gas-pump"></i> Último abastecimento: ${dadosRota.km_ultimo_abastecimento.toLocaleString('pt-BR')} km`;
                            helpText += `<br><i class="fas fa-list"></i> Total de abastecimentos: ${dadosRota.total_abastecimentos}`;
                            minValue = dadosRota.km_ultimo_abastecimento + 1;
                        } else {
                            helpText += `<br><i class="fas fa-plus"></i> Primeiro abastecimento da rota`;
                        }
                        
                        helpText += `<br><i class="fas fa-exclamation-triangle"></i> Quilometragem deve ser maior que ${minValue.toLocaleString('pt-BR')} km`;
                        
                        kmAtualHelp.innerHTML = helpText;
                        kmAtualInput.placeholder = `Mín: ${minValue.toLocaleString('pt-BR')}`;
                        kmAtualInput.min = minValue;
                    }
                } else {
                    kmAtualHelp.innerHTML = '<i class="fas fa-info-circle"></i> Selecione uma rota para validar a quilometragem';
                    kmAtualInput.placeholder = 'Ex: 150000';
                    kmAtualInput.min = '';
                }
                
                // Limpar validação anterior
                kmAtualValidation.innerHTML = '';
            });
            
            // Quando quilometragem for digitada
            kmAtualInput.addEventListener('blur', async function() {
                const rotaId = rotaSelect.value;
                const kmAbastecimento = this.value;
                
                if (rotaId && kmAbastecimento) {
                    const validacao = await validarKmAbastecimento(rotaId, kmAbastecimento);
                    
                    if (validacao.valido) {
                        kmAtualValidation.innerHTML = `<div style="color: #28a745;"><i class="fas fa-check-circle"></i> ${validacao.mensagem}</div>`;
                        kmAtualInput.style.borderColor = '#28a745';
                    } else {
                        kmAtualValidation.innerHTML = `<div style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> ${validacao.mensagem}</div>`;
                        kmAtualInput.style.borderColor = '#dc3545';
                    }
                } else {
                    kmAtualValidation.innerHTML = '';
                    kmAtualInput.style.borderColor = '';
                }
            });
        }
        
        // Inicializar validação quando DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Aguardar um pouco para garantir que todos os elementos estejam carregados
            setTimeout(() => {
                configurarValidacaoKmAbastecimento();
            }, 500);
        });
    </script>
    <script src="../js/abastecimentos.js"></script>
    <style>
    /* Estilos adicionais para a página de abastecimentos */
    /* Removendo estilos dos botões que já estão no styles.css */
    
    /* Estilos para a seção de análise */
    .analytics-section {
        margin-top: 20px;
    }

    /* Estilo para o botão de filtro quando ativo */
    .btn-restore-layout.active {
        background-color: var(--primary-color);
        color: white;
    }
    
    .analytics-section .section-header {
        margin-bottom: 20px;
    }
    
    .analytics-section .section-header h2 {
        font-size: 1.25rem;
        color: var(--text-primary);
        margin: 0;
    }
    
    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .analytics-card {
        background: var(--bg-secondary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }
    
    .analytics-card .card-header {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .analytics-card .card-header h3 {
        margin: 0;
        font-size: 1rem;
        color: var(--text-primary);
    }
    
    .analytics-card .card-body {
        padding: 15px;
        height: 400px; /* Altura fixa para os gráficos */
        position: relative; /* Necessário para o Chart.js */
    }
    
    .search-box {
        position: relative;
        width: 200px;
    }
    
    .search-box input {
        width: 100%;
        padding: 6px 12px 6px 30px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
        font-size: 0.875rem;
    }
    
    .search-box i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 20px;
        gap: 15px;
    }
    
    .pagination-btn {
        padding: 8px 16px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        background: var(--bg-secondary);
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .pagination-btn:hover:not(.disabled) {
        background: var(--bg-tertiary);
    }
    
    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .pagination-info {
        font-size: 0.9rem;
        color: var(--text-color);
    }

    .numeric-input {
        text-align: right;
    }

    /* Estilos para checkbox personalizado */
    .checkbox-group {
        margin: 15px 0;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        font-size: 1rem;
        color: var(--text-primary);
        user-select: none;
    }

    .checkbox-label input[type="checkbox"] {
        display: none;
    }

    .checkmark {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        margin-right: 10px;
        position: relative;
        background-color: var(--bg-secondary);
        transition: all 0.3s ease;
    }

    .checkbox-label input[type="checkbox"]:checked + .checkmark {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 14px;
        font-weight: bold;
    }

    .checkbox-label:hover .checkmark {
        border-color: var(--primary-color);
    }

    /* Estilos para campos ARLA */
    #campos_arla {
        margin-top: 15px;
        padding: 15px;
        background-color: var(--bg-tertiary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    #campos_arla .form-group {
        margin-bottom: 15px;
    }

    #campos_arla .form-group:last-child {
        margin-bottom: 0;
    }

    /* Estilos para informação ARLA na tabela */
    .arla-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-size: 0.875rem;
        line-height: 1.2;
    }

    .arla-info i {
        margin-bottom: 2px;
    }

    .arla-info small {
        color: var(--text-secondary);
        font-size: 0.75rem;
    }

    /* Estilos para porcentagem ARLA */
    .percentual-arla {
        font-weight: bold;
        font-size: 0.9rem;
        padding: 4px 8px;
        border-radius: 12px;
        display: inline-block;
        text-align: center;
        min-width: 50px;
    }

    .percentual-ok {
        background-color: #28a745 !important;
        color: #ffffff !important;
        border: 2px solid #28a745 !important;
        font-weight: bold !important;
    }

    .percentual-alto {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        border: 2px solid #dc3545 !important;
        font-weight: bold !important;
    }

    .percentual-baixo {
        background-color: #ffc107 !important;
        color: #000000 !important;
        border: 2px solid #ffc107 !important;
        font-weight: bold !important;
    }

    /* Estilos para campos de input com validação ARLA */
    #litros_arla.percentual-ok {
        border-color: #28a745;
        background-color: #d4edda;
    }

    #litros_arla.percentual-alto {
        border-color: #dc3545;
        background-color: #f8d7da;
    }

    #litros_arla.percentual-baixo {
        border-color: #ffc107;
        background-color: #fff3cd;
    }

    @media (max-width: 768px) {
        .analytics-grid {
            grid-template-columns: 1fr;
        }
        
        .analytics-card .card-body {
            height: 300px;
        }
    }

    /* Estilos para o modal de ajuda */
    .help-section {
        margin-bottom: 24px;
    }

    .help-section h3 {
        color: var(--primary-color);
        margin-bottom: 12px;
        font-size: 1.1rem;
    }

    .help-section p {
        margin-bottom: 12px;
        line-height: 1.5;
    }

    .help-section ul, .help-section ol {
        padding-left: 20px;
        margin-bottom: 12px;
    }

    .help-section ul ul, .help-section ol ul {
        margin-top: 8px;
        margin-bottom: 8px;
    }

    .help-section li {
        margin-bottom: 8px;
        line-height: 1.5;
    }

    .help-section strong {
        color: var(--text-primary);
    }

    /* Ajuste do modal para conteúdo longo */
    #helpModal .modal-content {
        max-width: 700px;
        max-height: 80vh;
    }

    #helpModal .modal-body {
        overflow-y: auto;
        padding: 20px;
    }
    </style>
</body>
</html>