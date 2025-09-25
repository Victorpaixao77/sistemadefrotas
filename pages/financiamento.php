<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php'; // Add database connection

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Require authentication
require_authentication();

// Create database connection
$conn = getConnection();

// Set page title
$page_title = "Financiamentos";

// Função para buscar financiamentos do banco de dados
function getFinanciamentos($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM financiamentos WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT f.*, v.placa, v.modelo, b.nome as banco_nome, sp.nome as status_nome 
               FROM financiamentos f 
               LEFT JOIN veiculos v ON f.veiculo_id = v.id 
               LEFT JOIN bancos b ON f.banco_id = b.id 
               LEFT JOIN status_pagamento sp ON f.status_pagamento_id = sp.id 
               WHERE f.empresa_id = :empresa_id 
               ORDER BY f.data_proxima_parcela DESC, f.id DESC
               LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'financiamentos' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar financiamentos: " . $e->getMessage());
        return [
            'financiamentos' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Função para buscar métricas do dashboard
function getDashboardMetrics($conn, $mes = null, $ano = null) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        
        // Valor total financiado
        $sql_total = "SELECT COALESCE(SUM(valor_total), 0) as total 
                     FROM financiamentos f
                     WHERE f.empresa_id = :empresa_id";
        if ($mes && $ano) {
            $sql_total .= " AND MONTH(f.data_inicio) = :mes AND YEAR(f.data_inicio) = :ano";
        }
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bindParam(':empresa_id', $empresa_id);
        if ($mes && $ano) {
            $stmt_total->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_total->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_total->execute();
        $total_financiado = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Valor total pago
        $sql_pago = "SELECT COALESCE(SUM(pf.valor), 0) as total_pago
                    FROM parcelas_financiamento pf
                    JOIN financiamentos f ON pf.financiamento_id = f.id
                    WHERE f.empresa_id = :empresa_id
                    AND pf.status_id = 2";
        if ($mes && $ano) {
            $sql_pago .= " AND MONTH(pf.data_pagamento) = :mes AND YEAR(pf.data_pagamento) = :ano";
        }
        $stmt_pago = $conn->prepare($sql_pago);
        $stmt_pago->bindParam(':empresa_id', $empresa_id);
        if ($mes && $ano) {
            $stmt_pago->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_pago->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_pago->execute();
        $total_pago = $stmt_pago->fetch(PDO::FETCH_ASSOC)['total_pago'];
        
        // Valor total em aberto
        $sql_aberto = "SELECT COALESCE(SUM(f.valor_total - COALESCE(pagos.total_pago, 0)), 0) as total_aberto
                      FROM financiamentos f
                      LEFT JOIN (
                          SELECT financiamento_id, SUM(valor) as total_pago
                          FROM parcelas_financiamento
                          WHERE status_id = 2
                          GROUP BY financiamento_id
                      ) pagos ON f.id = pagos.financiamento_id
                      WHERE f.empresa_id = :empresa_id";
        if ($mes && $ano) {
            $sql_aberto .= " AND MONTH(f.data_inicio) = :mes AND YEAR(f.data_inicio) = :ano";
        }
        $stmt_aberto = $conn->prepare($sql_aberto);
        $stmt_aberto->bindParam(':empresa_id', $empresa_id);
        if ($mes && $ano) {
            $stmt_aberto->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_aberto->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_aberto->execute();
        $total_aberto = $stmt_aberto->fetch(PDO::FETCH_ASSOC)['total_aberto'];
        
        // Percentual de quitação
        $percentual_quitacao = $total_financiado > 0 ? 
            round(($total_pago / $total_financiado) * 100, 2) : 0;
        
        return [
            'total_financiado' => $total_financiado,
            'total_pago' => $total_pago,
            'total_aberto' => $total_aberto,
            'percentual_quitacao' => $percentual_quitacao
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar métricas do dashboard: " . $e->getMessage());
        return [
            'total_financiado' => 0,
            'total_pago' => 0,
            'total_aberto' => 0,
            'percentual_quitacao' => 0
        ];
    }
}

// Função para buscar dados dos gráficos
function getChartData($conn, $mes = null, $ano = null) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        
        // Distribuição por caminhão
        $sql_veiculos = "SELECT 
            v.placa,
            v.modelo,
            COALESCE(SUM(CASE WHEN pf.status_id = 1 THEN pf.valor ELSE 0 END), 0) as valor_aberto,
            COALESCE(SUM(CASE WHEN pf.status_id = 2 THEN pf.valor ELSE 0 END), 0) as valor_pago
            FROM veiculos v
            LEFT JOIN financiamentos f ON v.id = f.veiculo_id AND f.empresa_id = :empresa_id1
            LEFT JOIN parcelas_financiamento pf ON f.id = pf.financiamento_id
            WHERE v.empresa_id = :empresa_id2";
        
        if ($mes && $ano) {
            $sql_veiculos .= " AND (
                (pf.status_id = 1 AND MONTH(pf.data_vencimento) = :mes AND YEAR(pf.data_vencimento) = :ano)
                OR (pf.status_id = 2 AND MONTH(pf.data_pagamento) = :mes AND YEAR(pf.data_pagamento) = :ano)
            )";
        }
        
        $sql_veiculos .= " GROUP BY v.id, v.placa, v.modelo
            HAVING valor_aberto > 0 OR valor_pago > 0
            ORDER BY valor_aberto DESC, valor_pago DESC";
        
        $stmt_veiculos = $conn->prepare($sql_veiculos);
        $stmt_veiculos->bindParam(':empresa_id1', $empresa_id);
        $stmt_veiculos->bindParam(':empresa_id2', $empresa_id);
        if ($mes && $ano) {
            $stmt_veiculos->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_veiculos->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_veiculos->execute();
        $veiculos = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);
        
        // Histórico de pagamentos
        $sql_historico = "SELECT 
            DATE_FORMAT(pf.data_pagamento, '%Y-%m') as mes,
            COUNT(*) as quantidade_parcelas,
            COALESCE(SUM(pf.valor), 0) as valor_pago
            FROM parcelas_financiamento pf
            JOIN financiamentos f ON pf.financiamento_id = f.id
            WHERE f.empresa_id = :empresa_id
            AND pf.status_id = 2";
        
        if ($mes && $ano) {
            $sql_historico .= " AND MONTH(pf.data_pagamento) = :mes AND YEAR(pf.data_pagamento) = :ano";
        } else {
            $sql_historico .= " AND pf.data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
        }
        
        $sql_historico .= " GROUP BY DATE_FORMAT(pf.data_pagamento, '%Y-%m')
            ORDER BY mes ASC";
        
        $stmt_historico = $conn->prepare($sql_historico);
        $stmt_historico->bindParam(':empresa_id', $empresa_id);
        if ($mes && $ano) {
            $stmt_historico->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_historico->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_historico->execute();
        $historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);
        
        // Parcelas vencidas vs. a vencer
        $sql_parcelas = "SELECT 
            'Vencidas' as tipo,
            COUNT(*) as quantidade,
            COALESCE(SUM(valor), 0) as valor_total,
            COUNT(DISTINCT f.id) as total_financiamentos
            FROM parcelas_financiamento pf
            JOIN financiamentos f ON pf.financiamento_id = f.id
            WHERE f.empresa_id = :empresa_id1
            AND pf.status_id = 1
            AND pf.data_vencimento < CURDATE()";
        
        if ($mes && $ano) {
            $sql_parcelas .= " AND MONTH(pf.data_vencimento) = :mes AND YEAR(pf.data_vencimento) = :ano";
        }
        
        $sql_parcelas .= " UNION ALL
            SELECT 
            'A Vencer' as tipo,
            COUNT(*) as quantidade,
            COALESCE(SUM(valor), 0) as valor_total,
            COUNT(DISTINCT f.id) as total_financiamentos
            FROM parcelas_financiamento pf
            JOIN financiamentos f ON pf.financiamento_id = f.id
            WHERE f.empresa_id = :empresa_id2
            AND pf.status_id = 1
            AND pf.data_vencimento >= CURDATE()";
        
        if ($mes && $ano) {
            $sql_parcelas .= " AND MONTH(pf.data_vencimento) = :mes AND YEAR(pf.data_vencimento) = :ano";
        }
        
        $stmt_parcelas = $conn->prepare($sql_parcelas);
        $stmt_parcelas->bindParam(':empresa_id1', $empresa_id);
        $stmt_parcelas->bindParam(':empresa_id2', $empresa_id);
        if ($mes && $ano) {
            $stmt_parcelas->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_parcelas->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_parcelas->execute();
        $parcelas = $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);
        
        // Comparação entre financiadoras
        $sql_financiadoras = "SELECT 
            b.nome as banco,
            COUNT(DISTINCT f.id) as total_contratos,
            COALESCE(SUM(f.valor_total), 0) as valor_total,
            COUNT(DISTINCT CASE WHEN f.status_pagamento_id = 2 THEN f.id END) as contratos_quitados,
            COUNT(DISTINCT CASE WHEN f.status_pagamento_id = 1 THEN f.id END) as contratos_ativos
            FROM bancos b
            LEFT JOIN financiamentos f ON b.id = f.banco_id AND f.empresa_id = :empresa_id
            WHERE 1=1";
        
        if ($mes && $ano) {
            $sql_financiadoras .= " AND MONTH(f.data_inicio) = :mes AND YEAR(f.data_inicio) = :ano";
        }
        
        $sql_financiadoras .= " GROUP BY b.id, b.nome
            HAVING total_contratos > 0
            ORDER BY valor_total DESC";
        
        $stmt_financiadoras = $conn->prepare($sql_financiadoras);
        $stmt_financiadoras->bindParam(':empresa_id', $empresa_id);
        if ($mes && $ano) {
            $stmt_financiadoras->bindParam(':mes', $mes, PDO::PARAM_INT);
            $stmt_financiadoras->bindParam(':ano', $ano, PDO::PARAM_INT);
        }
        $stmt_financiadoras->execute();
        $financiadoras = $stmt_financiadoras->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'veiculos' => $veiculos,
            'historico' => $historico,
            'parcelas' => $parcelas,
            'financiadoras' => $financiadoras
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar dados dos gráficos: " . $e->getMessage());
        return [
            'veiculos' => [],
            'historico' => [],
            'parcelas' => [],
            'financiadoras' => []
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Pegar o mês e ano dos filtros
$mes_filtro = isset($_GET['mes']) ? intval($_GET['mes']) : null;
$ano_filtro = isset($_GET['ano']) ? intval($_GET['ano']) : null;

// Buscar financiamentos com paginação
$resultado = getFinanciamentos($pagina_atual);
$financiamentos = $resultado['financiamentos'];
$total_paginas = $resultado['total_paginas'];

// Buscar métricas do dashboard
$metricas = getDashboardMetrics($conn, $mes_filtro, $ano_filtro);

// Buscar dados dos gráficos
$chart_data = getChartData($conn, $mes_filtro, $ano_filtro);

// Debug information
error_log("Chart Data: " . print_r($chart_data, true));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <button id="addFinanciamentoBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Financiamento
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
                
                <!-- Help Modal -->
                <div id="helpModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Ajuda - Financiamentos</h2>
                            <span class="close">&times;</span>
                        </div>
                        <div class="modal-body">
                            <h3>O que é o módulo de Financiamentos?</h3>
                            <p>O módulo de Financiamentos permite gerenciar todos os financiamentos de veículos da sua frota, incluindo:</p>
                            <ul>
                                <li>Cadastro de novos financiamentos</li>
                                <li>Acompanhamento de parcelas</li>
                                <li>Controle de pagamentos</li>
                                <li>Análise de custos e métricas</li>
                            </ul>

                            <h3>Como funciona?</h3>
                            <p>Para utilizar o módulo:</p>
                            <ol>
                                <li>Clique em "Novo Financiamento" para cadastrar um novo financiamento</li>
                                <li>Preencha os dados do veículo, banco e condições do financiamento</li>
                                <li>O sistema gerará automaticamente o plano de parcelas</li>
                                <li>Acompanhe os pagamentos e status das parcelas no dashboard</li>
                            </ol>

                            <h3>Dicas importantes:</h3>
                            <ul>
                                <li>Mantenha os dados do financiamento sempre atualizados</li>
                                <li>Registre os pagamentos assim que forem realizados</li>
                                <li>Utilize os filtros para encontrar financiamentos específicos</li>
                                <li>Acompanhe os gráficos para análise de custos e tendências</li>
                                <li>Exporte relatórios para análise detalhada dos dados</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor Total Financiado</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalFinanciado">R$ <?php echo number_format($metricas['total_financiado'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Total em contratos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor Total Pago</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalPago">R$ <?php echo number_format($metricas['total_pago'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Total já quitado</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor em Aberto</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalAberto">R$ <?php echo number_format($metricas['total_aberto'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Total a pagar</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Percentual de Quitação</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="percentualQuitacao"><?php echo $metricas['percentual_quitacao']; ?>%</span>
                                <span class="metric-subtitle">Do valor total</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchFinanciamento" placeholder="Buscar financiamento...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Em dia">Em dia</option>
                            <option value="Atrasado">Atrasado</option>
                            <option value="Quitado">Quitado</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                        
                        <select id="bancoFilter">
                            <option value="">Todos os bancos</option>
                            <option value="Banco A">Banco A</option>
                            <option value="Banco B">Banco B</option>
                            <option value="Banco C">Banco C</option>
                        </select>
                        
                        <select id="mesFilter">
                            <option value="">Todos os meses</option>
                            <?php
                            for ($i = 1; $i <= 12; $i++) {
                                $selected = ($i == $mes_filtro) ? 'selected' : '';
                                echo "<option value='$i' $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                            }
                            ?>
                        </select>
                        
                        <select id="anoFilter">
                            <option value="">Todos os anos</option>
                            <?php
                            $ano_atual = date('Y');
                            for ($i = $ano_atual; $i >= $ano_atual - 5; $i--) {
                                $selected = ($i == $ano_filtro) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>
                        
                        <button id="applyFilters" class="btn-secondary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
                
                <!-- Financing Table -->
                <div class="data-table-container">
                    <table class="data-table" id="financiamentosTable">
                        <thead>
                            <tr>
                                <th>Contrato</th>
                                <th>Veículo</th>
                                <th>Banco</th>
                                <th>Valor Total</th>
                                <th>Parcelas</th>
                                <th>Próx. Vencimento</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($financiamentos)): ?>
                                <?php foreach ($financiamentos as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['contrato']); ?></td>
                                        <td><?php echo htmlspecialchars($row['placa'] . " - " . $row['modelo']); ?></td>
                                        <td><?php echo htmlspecialchars($row['banco_nome']); ?></td>
                                        <td>R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['numero_parcelas']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['data_proxima_parcela'])); ?></td>
                                        <td><span class='status-badge status-<?php echo strtolower($row['status_nome']); ?>'><?php echo htmlspecialchars($row['status_nome']); ?></span></td>
                                        <td>
                                            <div class="table-actions">
                                                <button class="btn-icon view-btn" data-id="<?php echo $row['id']; ?>" title="Ver detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon edit-btn" data-id="<?php echo $row['id']; ?>" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon payment-btn" data-id="<?php echo $row['id']; ?>" title="Registrar Pagamento">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </button>
                                                <button class="btn-icon delete-btn" data-id="<?php echo $row['id']; ?>" title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">Nenhum financiamento encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($total_paginas > 1): ?>
                        <a href="#" class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" 
                           onclick="return changePage(<?php echo $pagina_atual - 1; ?>)">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <span class="pagination-info">
                            Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                        </span>
                        
                        <a href="#" class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>"
                           onclick="return changePage(<?php echo $pagina_atual + 1; ?>)">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Financing Analytics -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Financiamentos</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Distribuição por Caminhão</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="veiculosChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Histórico de Pagamentos</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="historicoChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Parcelas Vencidas vs. A Vencer</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="parcelasChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Comparação entre Financiadoras</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="financiadorasChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Add/Edit Financing Modal -->
    <div class="modal" id="financiamentoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Financiamento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="financiamentoForm">
                    <input type="hidden" id="financiamentoId">
                    <input type="hidden" id="empresa_id" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="veiculo_id">Veículo*</label>
                            <select id="veiculo_id" name="veiculo_id" required>
                                <option value="">Selecione um veículo</option>
                                <?php
                                try {
                                    $conn = getConnection();
                                    // Buscar veículos da empresa
                                    $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute([$_SESSION['empresa_id']]);
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . $row['placa'] . " - " . $row['modelo'] . "</option>";
                                    }
                                } catch(PDOException $e) {
                                    error_log("Erro ao buscar veículos: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="banco_id">Banco*</label>
                            <select id="banco_id" name="banco_id" required>
                                <option value="">Selecione um banco</option>
                                <?php
                                try {
                                    // Buscar bancos
                                    $sql = "SELECT id, nome FROM bancos ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . $row['nome'] . "</option>";
                                    }
                                } catch(PDOException $e) {
                                    error_log("Erro ao buscar bancos: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_total">Valor Total*</label>
                            <input type="number" id="valor_total" name="valor_total" step="0.01" required onchange="calcularParcela()">
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_parcelas">Número de Parcelas*</label>
                            <input type="number" id="numero_parcelas" name="numero_parcelas" required onchange="calcularParcela()">
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_parcela">Valor da Parcela*</label>
                            <input type="number" id="valor_parcela" name="valor_parcela" step="0.01" required readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_inicio">Data de Início*</label>
                            <input type="date" id="data_inicio" name="data_inicio" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="taxa_juros">Taxa de Juros (%)</label>
                            <input type="number" id="taxa_juros" name="taxa_juros" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="status_pagamento_id">Status*</label>
                            <select id="status_pagamento_id" name="status_pagamento_id" required>
                                <option value="">Selecione o status</option>
                                <?php
                                try {
                                    // Buscar status de pagamento
                                    $sql = "SELECT id, nome FROM status_pagamento ORDER BY id";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . $row['nome'] . "</option>";
                                    }
                                } catch(PDOException $e) {
                                    error_log("Erro ao buscar status: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_proxima_parcela">Data Próxima Parcela</label>
                            <input type="date" id="data_proxima_parcela" name="data_proxima_parcela">
                        </div>
                        
                        <div class="form-group">
                            <label for="contrato">Número do Contrato</label>
                            <input type="text" id="contrato" name="contrato">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelFinanciamentoBtn" class="btn-secondary">Cancelar</button>
                <button id="saveFinanciamentoBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- View Financing Modal -->
    <div class="modal" id="viewFinanciamentoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes do Financiamento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="view-grid">
                    <div class="view-group">
                        <label>Veículo:</label>
                        <span id="view_veiculo"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Banco:</label>
                        <span id="view_banco"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Valor Total:</label>
                        <span id="view_valor_total"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Número de Parcelas:</label>
                        <span id="view_numero_parcelas"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Valor da Parcela:</label>
                        <span id="view_valor_parcela"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Data de Início:</label>
                        <span id="view_data_inicio"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Taxa de Juros:</label>
                        <span id="view_taxa_juros"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Status:</label>
                        <span id="view_status"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Data Próxima Parcela:</label>
                        <span id="view_data_proxima_parcela"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Número do Contrato:</label>
                        <span id="view_contrato"></span>
                    </div>
                    
                    <div class="view-group">
                        <label>Empresa:</label>
                        <span id="view_empresa"></span>
                    </div>
                </div>
                
                <div class="view-group full-width">
                    <label>Observações:</label>
                    <span id="view_observacoes"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary close-modal">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal Registrar Pagamento -->
    <div class="modal" id="paymentModal">
        <div class="modal-content payment-modal-content">
            <div class="modal-header">
                <h2>Registrar Pagamento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Parcela</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Data Pagamento</th>
                                <th>Forma Pagamento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="parcelasTableBody">
                            <!-- Parcelas serão carregadas aqui via JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <form id="paymentForm" enctype="multipart/form-data">
                    <input type="hidden" id="paymentFinanciamentoId" name="financiamento_id">
                    <input type="hidden" id="paymentEmpresaId" name="empresa_id">
                    <input type="hidden" id="paymentNumeroParcela" name="numero_parcela">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="paymentValor">Valor da Parcela</label>
                            <input type="text" id="paymentValor" name="valor" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="paymentDataVencimento">Data de Vencimento</label>
                            <input type="text" id="paymentDataVencimento" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="paymentDataPagamento">Data do Pagamento*</label>
                            <input type="date" id="paymentDataPagamento" name="data_pagamento" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="paymentFormaPagamento">Forma de Pagamento*</label>
                            <select id="paymentFormaPagamento" name="forma_pagamento_id" required>
                                <option value="">Selecione...</option>
                                <!-- Opções serão carregadas via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="paymentComprovante">Comprovante de Pagamento</label>
                            <input type="file" id="paymentComprovante" name="comprovante_pagamento" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="paymentObservacoes">Observações</label>
                        <textarea id="paymentObservacoes" name="observacoes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelPaymentBtn" class="btn-secondary">Cancelar</button>
                <button id="savePaymentBtn" class="btn-primary">Registrar Pagamento</button>
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
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <style>
        .payment-modal-content {
            width: 90% !important;
            max-width: 1200px !important;
        }
        
        .payment-modal-content .table-responsive {
            margin-bottom: 20px;
        }
        
        .payment-modal-content .data-table {
            width: 100%;
        }
        
        .payment-modal-content .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Estilos para paginação */
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

        /* Estilos para os gráficos */
        .analytics-section {
            margin-top: 30px;
        }

        .analytics-section .section-header {
            margin-bottom: 20px;
        }

        .analytics-section .section-header h2 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin: 0;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .analytics-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .analytics-card .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-tertiary);
        }

        .analytics-card .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-color);
        }

        .analytics-card .card-body {
            padding: 20px;
            height: 300px;
            position: relative;
        }

        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            setupModals();
            setupFilters();
            initializeCharts();
        });
        
        function calcularParcela() {
            const valorTotal = parseFloat(document.getElementById('valor_total').value) || 0;
            const numeroParcelas = parseInt(document.getElementById('numero_parcelas').value) || 0;
            
            if (valorTotal > 0 && numeroParcelas > 0) {
                const valorParcela = valorTotal / numeroParcelas;
                document.getElementById('valor_parcela').value = valorParcela.toFixed(2);
            } else {
                document.getElementById('valor_parcela').value = '';
            }
        }
        
        function initializePage() {
            document.getElementById('addFinanciamentoBtn').addEventListener('click', showAddFinanciamentoModal);
            setupTableButtons();
            
            // Add help button click handler
            document.getElementById('helpBtn').addEventListener('click', function() {
                document.getElementById('helpModal').classList.add('active');
            });
        }
        
        function setupTableButtons() {
            const viewButtons = document.querySelectorAll('.view-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const financiamentoId = this.getAttribute('data-id');
                    showViewFinanciamentoModal(financiamentoId);
                });
            });
            
            const editButtons = document.querySelectorAll('.edit-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const financiamentoId = this.getAttribute('data-id');
                    showEditFinanciamentoModal(financiamentoId);
                });
            });
            
            const paymentButtons = document.querySelectorAll('.payment-btn');
            paymentButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const financiamentoId = this.getAttribute('data-id');
                    showPaymentModal(financiamentoId);
                });
            });
            
            const deleteButtons = document.querySelectorAll('.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const financiamentoId = this.getAttribute('data-id');
                    showDeleteConfirmation(financiamentoId);
                });
            });
        }
        
        function setupModals() {
            const closeButtons = document.querySelectorAll('.close-modal, .close');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            document.getElementById('cancelFinanciamentoBtn').addEventListener('click', closeAllModals);
            document.getElementById('saveFinanciamentoBtn').addEventListener('click', saveFinanciamento);
            
            document.getElementById('cancelPaymentBtn').addEventListener('click', closeAllModals);
            document.getElementById('savePaymentBtn').addEventListener('click', registrarPagamento);
        }
        
        function setupFilters() {
            const searchBox = document.getElementById('searchFinanciamento');
            searchBox.addEventListener('input', filterFinanciamentos);
            
            // Add filter button click handler
            document.getElementById('filterBtn').addEventListener('click', function() {
                document.getElementById('filterModal').classList.add('active');
            });
            
            // Add clear filter button click handler
            document.getElementById('clearFilterBtn').addEventListener('click', function() {
                document.getElementById('filterMonth').value = '';
            });
            
            // Add apply filter button click handler
            document.getElementById('applyFilterBtn').addEventListener('click', function() {
                const monthYear = document.getElementById('filterMonth').value;
                if (monthYear) {
                    const [year, month] = monthYear.split('-');
                    window.location.href = `?mes=${month}&ano=${year}`;
                } else {
                    window.location.href = window.location.pathname;
                }
            });
            
            // Set the current month/year in the filter if it exists
            const urlParams = new URLSearchParams(window.location.search);
            const mes = urlParams.get('mes');
            const ano = urlParams.get('ano');
            if (mes && ano) {
                document.getElementById('filterMonth').value = `${ano}-${mes.padStart(2, '0')}`;
            }
        }
        
        function filterFinanciamentos() {
            const searchText = document.getElementById('searchFinanciamento').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const bancoFilter = document.getElementById('bancoFilter').value;
            const mesFilter = document.getElementById('mesFilter').value;
            
            const tableRows = document.querySelectorAll('#financiamentosTable tbody tr');
            
            tableRows.forEach(row => {
                const contrato = row.cells[0].textContent.toLowerCase();
                const veiculo = row.cells[1].textContent.toLowerCase();
                const banco = row.cells[2].textContent;
                const status = row.cells[6].textContent.trim();
                const vencimento = row.cells[5].textContent;
                
                const matchesSearch = contrato.includes(searchText) || veiculo.includes(searchText);
                const matchesStatus = statusFilter === '' || status === statusFilter;
                const matchesBanco = bancoFilter === '' || banco === bancoFilter;
                const matchesMes = mesFilter === '' || vencimento.includes(mesFilter);
                
                if (matchesSearch && matchesStatus && matchesBanco && matchesMes) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function initializeCharts() {
            // Debug information
            console.log("Chart Data:", <?php echo json_encode($chart_data); ?>);
            
            // Distribuição por Caminhão
            const veiculosCtx = document.getElementById('veiculosChart').getContext('2d');
            const veiculosData = {
                labels: <?php echo json_encode(array_map(function($v) { 
                    return $v['placa'] . ' - ' . $v['modelo']; 
                }, $chart_data['veiculos'])); ?>,
                datasets: [{
                    label: 'Valor em Aberto',
                    data: <?php echo json_encode(array_map(function($v) {
                        return floatval($v['valor_aberto']);
                    }, $chart_data['veiculos'])); ?>,
                    backgroundColor: '#3b82f6',
                    borderColor: '#2563eb',
                    borderWidth: 1
                }, {
                    label: 'Valor Pago',
                    data: <?php echo json_encode(array_map(function($v) {
                        return floatval($v['valor_pago']);
                    }, $chart_data['veiculos'])); ?>,
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 1
                }]
            };
            console.log("Veículos Data:", veiculosData);
            
            new Chart(veiculosCtx, {
                type: 'bar',
                data: veiculosData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: R$ ${context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                },
                                title: function(context) {
                                    return `Veículo: ${context[0].label}`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Valor em Aberto e Pago por Veículo',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            },
                            title: {
                                display: true,
                                text: 'Valor (R$)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Veículos'
                            }
                        }
                    }
                }
            });
            
            // Histórico de Pagamentos
            const historicoCtx = document.getElementById('historicoChart').getContext('2d');
            const historicoData = {
                labels: <?php echo json_encode(array_map(function($h) {
                    return date('M/Y', strtotime($h['mes'] . '-01'));
                }, $chart_data['historico'])); ?>,
                datasets: [{
                    label: 'Valor Pago',
                    data: <?php echo json_encode(array_map(function($h) {
                        return floatval($h['valor_pago']);
                    }, $chart_data['historico'])); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }, {
                    label: 'Quantidade de Parcelas',
                    data: <?php echo json_encode(array_map(function($h) {
                        return intval($h['quantidade_parcelas']);
                    }, $chart_data['historico'])); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'y1'
                }]
            };
            console.log("Histórico Data:", historicoData);
            
            new Chart(historicoCtx, {
                type: 'line',
                data: historicoData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Valor Pago') {
                                        return `R$ ${context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                    }
                                    return `${context.raw} parcelas`;
                                },
                                title: function(context) {
                                    return `Mês: ${context[0].label}`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Evolução dos Pagamentos',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Valor Pago (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Quantidade de Parcelas'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
            
            // Parcelas Vencidas vs. A Vencer
            const parcelasCtx = document.getElementById('parcelasChart').getContext('2d');
            const parcelasData = {
                labels: <?php echo json_encode(array_column($chart_data['parcelas'], 'tipo')); ?>,
                datasets: [{
                    label: 'Quantidade de Parcelas',
                    data: <?php echo json_encode(array_map(function($p) {
                        return intval($p['quantidade']);
                    }, $chart_data['parcelas'])); ?>,
                    backgroundColor: '#f59e0b',
                    borderColor: '#d97706',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Valor Total',
                    data: <?php echo json_encode(array_map(function($p) {
                        return floatval($p['valor_total']);
                    }, $chart_data['parcelas'])); ?>,
                    backgroundColor: '#ef4444',
                    borderColor: '#dc2626',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }, {
                    label: 'Total de Financiamentos',
                    data: <?php echo json_encode(array_map(function($p) {
                        return intval($p['total_financiamentos']);
                    }, $chart_data['parcelas'])); ?>,
                    backgroundColor: '#8b5cf6',
                    borderColor: '#7c3aed',
                    borderWidth: 1,
                    yAxisID: 'y'
                }]
            };
            console.log("Parcelas Data:", parcelasData);
            
            new Chart(parcelasCtx, {
                type: 'bar',
                data: parcelasData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    if (context.dataset.label === 'Valor Total') {
                                        return `R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                    }
                                    return `${value} ${context.dataset.label.toLowerCase()}`;
                                },
                                title: function(context) {
                                    return `Status: ${context[0].label}`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Análise de Parcelas',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Quantidade'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Valor Total (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
            
            // Comparação entre Financiadoras
            const financiadorasCtx = document.getElementById('financiadorasChart').getContext('2d');
            const financiadorasData = {
                labels: <?php echo json_encode(array_column($chart_data['financiadoras'], 'banco')); ?>,
                datasets: [{
                    label: 'Valor Total',
                    data: <?php echo json_encode(array_map(function($f) {
                        return floatval($f['valor_total']);
                    }, $chart_data['financiadoras'])); ?>,
                    backgroundColor: '#8b5cf6',
                    borderColor: '#7c3aed',
                    borderWidth: 1
                }, {
                    label: 'Contratos Ativos',
                    data: <?php echo json_encode(array_map(function($f) {
                        return intval($f['contratos_ativos']);
                    }, $chart_data['financiadoras'])); ?>,
                    backgroundColor: '#3b82f6',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }, {
                    label: 'Contratos Quitados',
                    data: <?php echo json_encode(array_map(function($f) {
                        return intval($f['contratos_quitados']);
                    }, $chart_data['financiadoras'])); ?>,
                    backgroundColor: '#10b981',
                    borderColor: '#059669',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            };
            console.log("Financiadoras Data:", financiadorasData);
            
            new Chart(financiadorasCtx, {
                type: 'bar',
                data: financiadorasData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    if (context.dataset.label === 'Valor Total') {
                                        return `R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                    }
                                    return `${value} ${context.dataset.label.toLowerCase()}`;
                                },
                                title: function(context) {
                                    return `Financiadora: ${context[0].label}`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Análise por Financiadora',
                            font: {
                                size: 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Valor Total (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Número de Contratos'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
        
        function showAddFinanciamentoModal() {
            document.getElementById('financiamentoForm').reset();
            document.getElementById('financiamentoId').value = '';
            document.getElementById('modalTitle').textContent = 'Adicionar Financiamento';
            document.getElementById('financiamentoModal').classList.add('active');
        }
        
        function showPaymentModal(financiamentoId) {
            // Reset the form
            document.getElementById('paymentForm').reset();
            
            // Set the financiamento ID
            document.getElementById('paymentFinanciamentoId').value = financiamentoId;
            document.getElementById('paymentEmpresaId').value = document.getElementById('empresa_id').value;
            
            // Load the installments
            carregarParcelas(financiamentoId);
            
            // Show the modal
            document.getElementById('paymentModal').classList.add('active');
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('active');
            });
        }
        
        function saveFinanciamento() {
            const form = document.getElementById('financiamentoForm');
            const formData = new FormData(form);
            
            // Adiciona a empresa_id ao formData
            formData.append('empresa_id', document.getElementById('empresa_id').value);
            
            // Se estiver editando, adiciona o ID do financiamento
            const financiamentoId = document.getElementById('financiamentoId').value;
            if (financiamentoId) {
                formData.append('financiamentoId', financiamentoId);
            }
            
            // Adiciona a action para identificar a operação
            formData.append('action', 'save_financiamento');
            
            // Desabilita o botão de salvar durante o processamento
            const saveButton = document.getElementById('saveFinanciamentoBtn');
            saveButton.disabled = true;
            saveButton.textContent = 'Salvando...';
            
            fetch('../api/financiamentos.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `Erro ao salvar financiamento (${response.status})`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
            closeAllModals();
            window.location.reload();
                } else {
                    throw new Error(data.message || 'Erro ao salvar financiamento');
                }
            })
            .catch(error => {
                console.error('Erro ao salvar financiamento:', error);
                alert('Erro ao salvar financiamento: ' + error.message);
            })
            .finally(() => {
                // Reabilita o botão de salvar
                saveButton.disabled = false;
                saveButton.textContent = 'Salvar';
            });
        }
        
        function registrarPagamento() {
            const form = document.getElementById('paymentForm');
            const formData = new FormData(form);
            formData.append('action', 'registrar_pagamento');
            
            // Desabilita o botão de salvar durante o processamento
            const saveButton = document.getElementById('savePaymentBtn');
            saveButton.disabled = true;
            saveButton.textContent = 'Registrando...';
            
            fetch('../api/financiamentos.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `Erro ao registrar pagamento (${response.status})`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Pagamento registrado com sucesso!');
                    // Recarrega as parcelas sem fechar o modal
                    carregarParcelas(formData.get('financiamento_id'));
                    // Limpa o formulário
                    form.reset();
                } else {
                    throw new Error(data.message || 'Erro ao registrar pagamento');
                }
            })
            .catch(error => {
                console.error('Erro ao registrar pagamento:', error);
                // Verifica se é um erro de campo obrigatório
                if (error.message.includes('Campo obrigatório')) {
                    alert('Por favor, preencha todos os campos obrigatórios: ' + error.message);
                } else {
                    alert('Erro ao registrar pagamento: ' + error.message);
                }
            })
            .finally(() => {
                // Reabilita o botão de salvar
                saveButton.disabled = false;
                saveButton.textContent = 'Registrar Pagamento';
            });
        }
        
        function showViewFinanciamentoModal(id) {
            fetch(`../api/financiamentos.php?id=${id}`, {
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Erro ao carregar dados do financiamento');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const financiamento = data.data;
                    document.getElementById('view_veiculo').textContent = financiamento.veiculo_nome;
                    document.getElementById('view_banco').textContent = financiamento.banco_nome;
                    document.getElementById('view_valor_total').textContent = `R$ ${parseFloat(financiamento.valor_total).toFixed(2)}`;
                    document.getElementById('view_numero_parcelas').textContent = financiamento.numero_parcelas;
                    document.getElementById('view_valor_parcela').textContent = `R$ ${parseFloat(financiamento.valor_parcela).toFixed(2)}`;
                    document.getElementById('view_data_inicio').textContent = new Date(financiamento.data_inicio).toLocaleDateString();
                    document.getElementById('view_taxa_juros').textContent = financiamento.taxa_juros ? `${financiamento.taxa_juros}%` : 'N/A';
                    document.getElementById('view_status').textContent = financiamento.status_nome;
                    document.getElementById('view_data_proxima_parcela').textContent = financiamento.data_proxima_parcela ? new Date(financiamento.data_proxima_parcela).toLocaleDateString() : 'N/A';
                    document.getElementById('view_contrato').textContent = financiamento.contrato || 'N/A';
                    document.getElementById('view_observacoes').textContent = financiamento.observacoes || 'N/A';
                    document.getElementById('view_empresa').textContent = financiamento.empresa_nome;
                    
                    // Abre o modal
                    document.getElementById('viewFinanciamentoModal').classList.add('active');
                } else {
                    throw new Error(data.message || 'Erro ao carregar dados do financiamento');
                }
            })
            .catch(error => {
                console.error('Erro detalhado:', error);
                alert(error.message || 'Erro ao carregar dados do financiamento. Por favor, tente novamente.');
            });
        }
        
        function showDeleteConfirmation(id) {
            if (confirm('Tem certeza que deseja excluir este financiamento?')) {
                // Envia a requisição de exclusão
            fetch(`../api/financiamentos.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Financiamento excluído com sucesso!');
                        location.reload(); // Recarrega a página para atualizar a lista
                    } else {
                        alert('Erro ao excluir financiamento: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao excluir financiamento');
                });
            }
        }

        function carregarParcelas(financiamentoId) {
            fetch(`../api/financiamentos.php?id=${financiamentoId}&action=parcelas`, {
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('parcelasTableBody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(parcela => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${parcela.numero_parcela}</td>
                            <td>R$ ${parseFloat(parcela.valor).toFixed(2)}</td>
                            <td>${formatarData(parcela.data_vencimento)}</td>
                            <td>${parcela.status_nome}</td>
                            <td>${parcela.data_pagamento ? formatarData(parcela.data_pagamento) : '-'}</td>
                            <td>${parcela.forma_pagamento_nome || '-'}</td>
                            <td>
                                ${parcela.status_id === 1 ? 
                                    `<button class="btn-icon" onclick="prepararPagamento(${JSON.stringify(parcela).replace(/"/g, '&quot;')})" title="Registrar Pagamento">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>` : 
                                    `<div class="table-actions">
                                        <button class="btn-icon" onclick="editarPagamento(${JSON.stringify(parcela).replace(/"/g, '&quot;')})" title="Editar Pagamento">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon" onclick="reverterPagamento(${JSON.stringify(parcela).replace(/"/g, '&quot;')})" title="Reverter Pagamento">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        ${parcela.comprovante_pagamento ? 
                                            `<button class="btn-icon" onclick="visualizarComprovante('${parcela.comprovante_pagamento}')" title="Ver Comprovante">
                                                <i class="fas fa-file-alt"></i>
                                            </button>` : 
                                            ''
                                        }
                                    </div>`
                                }
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    throw new Error(data.message || 'Erro ao carregar parcelas');
                }
            })
            .catch(error => {
                console.error('Erro ao carregar parcelas:', error);
                alert('Erro ao carregar parcelas: ' + error.message);
            });
        }

        function prepararPagamento(parcela) {
            document.getElementById('paymentFinanciamentoId').value = parcela.financiamento_id;
            document.getElementById('paymentNumeroParcela').value = parcela.numero_parcela;
            document.getElementById('paymentEmpresaId').value = parcela.empresa_id;
            document.getElementById('paymentValor').value = `R$ ${parseFloat(parcela.valor).toFixed(2)}`;
            document.getElementById('paymentDataVencimento').value = formatarData(parcela.data_vencimento);
            document.getElementById('paymentDataPagamento').value = '';
            document.getElementById('paymentComprovante').value = '';
            document.getElementById('paymentObservacoes').value = '';
            
            // Carregar formas de pagamento
            carregarFormasPagamento();
        }

        function carregarFormasPagamento() {
            fetch('../api/formas_pagamento.php?action=list', {
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const select = document.getElementById('paymentFormaPagamento');
                select.innerHTML = '<option value="">Selecione...</option>';
                
                data.forEach(forma => {
                    const option = document.createElement('option');
                    option.value = forma.id;
                    option.textContent = forma.nome;
                    select.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Erro ao carregar formas de pagamento:', error);
                alert('Erro ao carregar formas de pagamento. Por favor, tente novamente.');
            });
        }

        function formatarData(data) {
            if (!data) return '-';
            return new Date(data).toLocaleDateString('pt-BR');
        }

        // Função para mostrar o modal de edição
        function showEditFinanciamentoModal(id) {
            fetch(`../api/financiamentos.php?id=${id}`, {
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Erro ao carregar dados do financiamento');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const financiamento = data.data;
                    document.getElementById('financiamentoId').value = financiamento.id;
                    document.getElementById('veiculo_id').value = financiamento.veiculo_id;
                    document.getElementById('banco_id').value = financiamento.banco_id;
                    document.getElementById('valor_total').value = financiamento.valor_total;
                    document.getElementById('numero_parcelas').value = financiamento.numero_parcelas;
                    document.getElementById('valor_parcela').value = financiamento.valor_parcela;
                    document.getElementById('data_inicio').value = financiamento.data_inicio;
                    document.getElementById('taxa_juros').value = financiamento.taxa_juros || '';
                    document.getElementById('status_pagamento_id').value = financiamento.status_pagamento_id;
                    document.getElementById('data_proxima_parcela').value = financiamento.data_proxima_parcela || '';
                    document.getElementById('contrato').value = financiamento.contrato || '';
                    document.getElementById('observacoes').value = financiamento.observacoes || '';
                    document.getElementById('empresa_id').value = financiamento.empresa_id;
                    
                    // Abre o modal
                    document.getElementById('modalTitle').textContent = 'Editar Financiamento';
                    document.getElementById('financiamentoModal').classList.add('active');
                } else {
                    throw new Error(data.message || 'Erro ao carregar dados do financiamento');
                }
            })
            .catch(error => {
                console.error('Erro detalhado:', error);
                alert(error.message || 'Erro ao carregar dados do financiamento. Por favor, tente novamente.');
            });
        }

        function editarPagamento(parcela) {
            prepararPagamento(parcela);
            document.getElementById('paymentDataPagamento').value = parcela.data_pagamento;
            document.getElementById('paymentFormaPagamento').value = parcela.forma_pagamento_id;
            document.getElementById('paymentComprovante').value = parcela.comprovante_pagamento || '';
            document.getElementById('paymentObservacoes').value = parcela.observacoes || '';
        }

        function reverterPagamento(parcela) {
            if (confirm('Tem certeza que deseja reverter o pagamento desta parcela?')) {
                const formData = new FormData();
                formData.append('action', 'reverter_pagamento');
                formData.append('financiamento_id', parcela.financiamento_id);
                formData.append('numero_parcela', parcela.numero_parcela);
                formData.append('empresa_id', parcela.empresa_id);
                
                fetch('../api/financiamentos.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Pagamento revertido com sucesso!');
                        carregarParcelas(parcela.financiamento_id);
                    } else {
                        throw new Error(data.message || 'Erro ao reverter pagamento');
                    }
                })
                .catch(error => {
                    console.error('Erro ao reverter pagamento:', error);
                    alert('Erro ao reverter pagamento: ' + error.message);
                });
            }
        }

        function changePage(page) {
            window.location.href = `?page=${page}`;
            return false;
        }

        function visualizarComprovante(caminho) {
            // Abre o comprovante em uma nova aba
            window.open('../' + caminho, '_blank');
        }
    </script>
</body>
</html> 