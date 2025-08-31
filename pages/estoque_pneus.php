<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Set page title
$page_title = "Estoque de Pneus";

// Função para buscar pneus do estoque
function getEstoquePneus($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 10; // Registros por página
        $offset = ($page - 1) * $limit;
        
        error_log("=== INÍCIO DA BUSCA DE PNEUS ===");
        error_log("Empresa ID: " . $empresa_id);
        error_log("Página: " . $page);
        error_log("Limit: " . $limit);
        error_log("Offset: " . $offset);
        
        // Conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM pneus WHERE empresa_id = :empresa_id";
        error_log("SQL Count: " . $sql_count);
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Total de registros encontrados: " . $total);
        
        // Consulta paginada
        $sql = "SELECT 
                    p.id as pneu_id,
                    p.numero_serie,
                    p.marca,
                    p.modelo,
                    p.medida,
                    p.sulco_inicial,
                    p.numero_recapagens,
                    p.data_ultima_recapagem,
                    p.lote,
                    s.nome as status_nome,
                    p.status_id,
                    p.created_at,
                    p.updated_at,
                    ep.disponivel
                FROM pneus p
                LEFT JOIN status_pneus s ON p.status_id = s.id
                LEFT JOIN estoque_pneus ep ON ep.pneu_id = p.id
                WHERE p.empresa_id = :empresa_id
                ORDER BY p.id DESC
                LIMIT :limit OFFSET :offset";
        error_log("SQL Principal: " . $sql);
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Número de pneus retornados: " . count($result));
        error_log("Dados dos pneus: " . json_encode($result, JSON_PRETTY_PRINT));
        error_log("=== FIM DA BUSCA DE PNEUS ===");
        return [
            'pneus' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("ERRO na busca de pneus: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [
            'pneus' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar pneus com paginação
$resultado = getEstoquePneus($pagina_atual);
$pneus = $resultado['pneus'];
$total_paginas = $resultado['total_paginas'];

error_log("Resultado final - Total de pneus: " . count($pneus));
error_log("Dados dos pneus: " . json_encode($pneus));
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
                <div class="dashboard-grid mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $resultado['total']; ?></span>
                                <span class="metric-subtitle">Pneus em estoque</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Table Section -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Número de Série</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Medida</th>
                                <th>Status</th>
                                <th>Disponível</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pneus as $pneu): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pneu['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($pneu['marca']); ?></td>
                                <td><?php echo htmlspecialchars($pneu['modelo']); ?></td>
                                <td><?php echo htmlspecialchars($pneu['medida']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($pneu['status_nome']); ?>">
                                        <?php echo htmlspecialchars($pneu['status_nome']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isset($pneu['disponivel']) && $pneu['disponivel'] == 1): ?>
                                        <span class="status-badge status-success">Sim</span>
                                    <?php else: ?>
                                        <span class="status-badge status-danger">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $pneu['pneu_id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $pneu['pneu_id']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
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
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Filter Modal -->
    <div id="filterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtrar Estoque</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="filterForm">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <option value="1">Novo</option>
                            <option value="2">Usado</option>
                            <option value="3">Recapado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="disponivel">Disponibilidade:</label>
                        <select id="disponivel" name="disponivel">
                            <option value="">Todos</option>
                            <option value="1">Disponível</option>
                            <option value="0">Indisponível</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Aplicar Filtros</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div id="helpModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Estoque de Pneus</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Estoque de Pneus permite gerenciar todos os pneus disponíveis na sua frota. Aqui você pode:</p>
                    <ul>
                        <li>Visualizar todos os pneus em estoque</li>
                        <li>Filtrar pneus por status e disponibilidade</li>
                        <li>Ver detalhes de cada pneu</li>
                        <li>Editar informações dos pneus</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="../js/main.js"></script>
    <script>
        // Função para mudar de página
        function changePage(page) {
            window.location.href = 'estoque_pneus.php?page=' + page;
            return false;
        }
        
        // Função para aplicar filtros
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const status = document.getElementById('status').value;
            const disponivel = document.getElementById('disponivel').value;
            
            // Fazer requisição AJAX para filtrar os dados
            fetch(`../api/estoque_pneus.php?action=get_estoque&status=${status}&disponivel=${disponivel}`)
                .then(response => response.json())
                .then(data => {
                    // Atualizar a tabela com os dados filtrados
                    updateTable(data.pneus);
                })
                .catch(error => console.error('Erro ao filtrar dados:', error));
        });
        
        // Função para atualizar a tabela
        function updateTable(pneus) {
            const tbody = document.querySelector('.data-table tbody');
            tbody.innerHTML = '';
            
            pneus.forEach(pneu => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${pneu.numero_serie}</td>
                    <td>${pneu.marca}</td>
                    <td>${pneu.modelo}</td>
                    <td>${pneu.medida}</td>
                    <td>
                        <span class="status-badge status-${pneu.status_nome.toLowerCase()}">
                            ${pneu.status_nome}
                        </span>
                    </td>
                    <td>
                        <?php if (isset($pneu['disponivel']) && $pneu['disponivel'] == 1): ?>
                            <span class="status-badge status-success">Sim</span>
                        <?php else: ?>
                            <span class="status-badge status-danger">Não</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <button class="btn-icon view-btn" data-id="${pneu.id || pneu.pneu_id}" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon edit-btn" data-id="${pneu.id || pneu.pneu_id}" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    </script>
    
    <!-- JavaScript Files -->
    <script src="../js/dashboard.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/sortable.js"></script>
    <script src="../js/charts.js"></script>
</body>
</html> 