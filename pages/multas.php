<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();
require_authentication();

$conn = getConnection();
$page_title = "Multas";

// Funções para buscar métricas e multas
function getMultasKPIs($conn) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $primeiro_dia_mes = date('Y-m-01');
        $ultimo_dia_mes = date('Y-m-t');
        // Total de multas do mês
        $sql_total = "SELECT COUNT(*) as total FROM multas WHERE empresa_id = :empresa_id AND data_infracao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bindParam(':empresa_id', $empresa_id);
        $stmt_total->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_total->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_total->execute();
        $total_multas = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
        // Valor total
        $sql_valor = "SELECT COALESCE(SUM(valor),0) as total FROM multas WHERE empresa_id = :empresa_id AND data_infracao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_valor = $conn->prepare($sql_valor);
        $stmt_valor->bindParam(':empresa_id', $empresa_id);
        $stmt_valor->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_valor->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_valor->execute();
        $valor_total = $stmt_valor->fetch(PDO::FETCH_ASSOC)['total'];
        // Pendentes
        $sql_pendentes = "SELECT COUNT(*) as total FROM multas WHERE empresa_id = :empresa_id AND status_pagamento = 'pendente'";
        $stmt_pendentes = $conn->prepare($sql_pendentes);
        $stmt_pendentes->bindParam(':empresa_id', $empresa_id);
        $stmt_pendentes->execute();
        $total_pendentes = $stmt_pendentes->fetch(PDO::FETCH_ASSOC)['total'];
        // Pagas
        $sql_pagas = "SELECT COUNT(*) as total FROM multas WHERE empresa_id = :empresa_id AND status_pagamento = 'pago'";
        $stmt_pagas = $conn->prepare($sql_pagas);
        $stmt_pagas->bindParam(':empresa_id', $empresa_id);
        $stmt_pagas->execute();
        $total_pagas = $stmt_pagas->fetch(PDO::FETCH_ASSOC)['total'];
        // Pontos
        $sql_pontos = "SELECT COALESCE(SUM(pontos),0) as total FROM multas WHERE empresa_id = :empresa_id AND data_infracao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_pontos = $conn->prepare($sql_pontos);
        $stmt_pontos->bindParam(':empresa_id', $empresa_id);
        $stmt_pontos->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_pontos->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_pontos->execute();
        $pontos_total = $stmt_pontos->fetch(PDO::FETCH_ASSOC)['total'];
        return [
            'total_multas' => $total_multas,
            'valor_total' => $valor_total,
            'total_pendentes' => $total_pendentes,
            'total_pagas' => $total_pagas,
            'pontos_total' => $pontos_total
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar KPIs de multas: " . $e->getMessage());
        return [
            'total_multas' => 0,
            'valor_total' => 0,
            'total_pendentes' => 0,
            'total_pagas' => 0,
            'pontos_total' => 0
        ];
    }
}

function getMultas($conn, $page = 1) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5;
        $offset = ($page - 1) * $limit;
        $sql_count = "SELECT COUNT(*) as total FROM multas WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        $sql = "SELECT m.*, v.placa as veiculo_placa, mo.nome as motorista_nome, 
                       CONCAT('Rota #', r.id) as rota_codigo
                FROM multas m
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN motoristas mo ON m.motorista_id = mo.id
                LEFT JOIN rotas r ON m.rota_id = r.id
                WHERE m.empresa_id = :empresa_id
                ORDER BY m.data_infracao DESC, m.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'multas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar multas: " . $e->getMessage());
        return [
            'multas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$resultado = getMultas($conn, $pagina_atual);
$multas = $resultado['multas'];
$total_paginas = $resultado['total_paginas'];
$kpis = getMultasKPIs($conn);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addMultaBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Multa
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
                            <h3>Multas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $kpis['total_multas']; ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($kpis['valor_total'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $kpis['total_pendentes']; ?></span>
                                <span class="metric-subtitle">A pagar</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pontos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $kpis['pontos_total']; ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMulta" placeholder="Buscar multa...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                        </select>
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="recurso">Recurso</option>
                        </select>
                    </div>
                </div>

                <!-- Multas Table -->
                <div class="data-table-container">
                    <table class="data-table" id="multasTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Motorista</th>
                                <th>Rota</th>
                                <th>Tipo</th>
                                <th>Pontos</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Vencimento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($multas)): ?>
                                <?php foreach ($multas as $multa): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($multa['data_infracao'])); ?></td>
                                    <td><?php echo htmlspecialchars($multa['veiculo_placa'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($multa['motorista_nome'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($multa['rota_codigo'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($multa['tipo_infracao']); ?></td>
                                    <td><?php echo $multa['pontos']; ?></td>
                                    <td>R$ <?php echo number_format($multa['valor'], 2, ',', '.'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $multa['status_pagamento'] === 'pago' ? 'success' : ($multa['status_pagamento'] === 'pendente' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($multa['status_pagamento']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $multa['vencimento'] ? date('d/m/Y', strtotime($multa['vencimento'])) : '-'; ?></td>
                                    <td class="actions">
                                        <button class="btn-icon edit-btn" data-id="<?php echo $multa['id']; ?>" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!empty($multa['comprovante'])): ?>
                                            <button class="btn-icon view-comprovante-btn" data-comprovante="<?php echo htmlspecialchars($multa['comprovante']); ?>" title="Ver Comprovante">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $multa['id']; ?>" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">Nenhuma multa encontrada</td>
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

                <!-- Analytics Section -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Multas</h2>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Multas por Mês</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="multasPorMesChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Valor Total por Mês</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="valorPorMesChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Multas por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="multasPorMotoristaChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Pontos por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="pontosPorMotoristaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
    
    <!-- Modal de Multa -->
    <div class="modal" id="multaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nova Multa</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="multaForm">
                    <input type="hidden" id="multaId" name="id">
                    <input type="hidden" id="empresaId" name="empresa_id" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-section">
                        <h3>Informações da Infração</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="data_infracao">Data da Infração*</label>
                                <input type="date" id="data_infracao" name="data_infracao" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="veiculo_id">Veículo*</label>
                                <select id="veiculo_id" name="veiculo_id" required>
                                    <option value="">Selecione um veículo</option>
                                    <?php
                                    $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
                                    $stmt->execute();
                                    while ($veiculo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $veiculo['id'] . "'>" . htmlspecialchars($veiculo['placa'] . ' - ' . $veiculo['modelo']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="motorista_id">Motorista*</label>
                                <select id="motorista_id" name="motorista_id" required>
                                    <option value="">Selecione um motorista</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM motoristas WHERE empresa_id = :empresa_id ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
                                    $stmt->execute();
                                    while ($motorista = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $motorista['id'] . "'>" . htmlspecialchars($motorista['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="rota_id">Rota (Opcional)</label>
                                <select id="rota_id" name="rota_id">
                                    <option value="">Selecione uma rota</option>
                                    <?php
                                    $sql = "SELECT r.id, r.data_rota, co.nome as cidade_origem, cd.nome as cidade_destino
                                            FROM rotas r
                                            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                                            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                                            WHERE r.empresa_id = :empresa_id 
                                            ORDER BY r.data_rota DESC, r.id DESC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
                                    $stmt->execute();
                                    while ($rota = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $data = $rota['data_rota'] ? date('d/m/Y', strtotime($rota['data_rota'])) : '';
                                        $desc = $data . ' - ' . ($rota['cidade_origem'] ?? '-') . ' → ' . ($rota['cidade_destino'] ?? '-');
                                        echo "<option value='" . $rota['id'] . "'>" . htmlspecialchars($desc) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Detalhes da Infração</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tipo_infracao">Tipo de Infração*</label>
                                <input type="text" id="tipo_infracao" name="tipo_infracao" required maxlength="255" placeholder="Ex: Excesso de velocidade, Estacionamento irregular">
                            </div>
                            
                            <div class="form-group">
                                <label for="pontos">Pontos na CNH</label>
                                <input type="number" id="pontos" name="pontos" min="0" max="20" value="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="valor">Valor da Multa*</label>
                                <input type="number" id="valor" name="valor" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="vencimento">Data de Vencimento</label>
                                <input type="date" id="vencimento" name="vencimento">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Status e Observações</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="status_pagamento">Status do Pagamento*</label>
                                <select id="status_pagamento" name="status_pagamento" required>
                                    <option value="pendente">Pendente</option>
                                    <option value="pago">Pago</option>
                                    <option value="recurso">Recurso</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_pagamento">Data do Pagamento</label>
                                <input type="date" id="data_pagamento" name="data_pagamento">
                            </div>
                            
                            <div class="form-group">
                                <label for="comprovante">Comprovante</label>
                                <input type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="descricao">Descrição da Infração</label>
                                <textarea id="descricao" name="descricao" rows="3" placeholder="Detalhes sobre a infração cometida"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelMultaBtn">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <button type="button" class="btn-primary" id="saveMultaBtn">
                    <i class="fas fa-save"></i>
                    <span>Salvar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal" id="deleteMultaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Tem certeza que deseja excluir esta multa?</p>
                    <p class="warning-text">Esta ação não pode ser desfeita.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelDeleteBtn">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i>
                    <span>Excluir</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Ajuda -->
    <div class="modal" id="helpMultaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Multas</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Multas permite gerenciar todas as infrações cometidas pelos veículos e motoristas da frota. Aqui você pode cadastrar, editar, visualizar e excluir registros de multas, além de acompanhar métricas importantes.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Nova Multa:</strong> Cadastre uma nova infração com informações detalhadas sobre veículo, motorista e tipo de infração.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar multas específicas por veículo, motorista ou status.</li>
                        <li><strong>Exportar:</strong> Exporte os dados das multas para análise externa.</li>
                        <li><strong>Relatórios:</strong> Visualize relatórios e estatísticas de multas.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Total de Multas:</strong> Número total de multas no mês atual.</li>
                        <li><strong>Valor Total:</strong> Soma dos valores de todas as multas do mês.</li>
                        <li><strong>Pendentes:</strong> Quantidade de multas ainda não pagas.</li>
                        <li><strong>Pontos:</strong> Total de pontos na CNH acumulados no mês.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos da multa, incluindo valor e status.</li>
                        <li><strong>Editar:</strong> Modifique informações de uma multa existente.</li>
                        <li><strong>Excluir:</strong> Remova um registro de multa do sistema (ação irreversível).</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Mantenha um registro detalhado das infrações para histórico.</li>
                        <li>Acompanhe os pontos na CNH dos motoristas para evitar suspensões.</li>
                        <li>Monitore o valor total das multas para controle de custos.</li>
                        <li>Utilize os relatórios para identificar padrões de infrações.</li>
                        <li>Configure alertas para multas próximas do vencimento.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpMultaModal')">Fechar</button>
            </div>
        </div>
    </div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/multas.js"></script>
</body>
</html> 