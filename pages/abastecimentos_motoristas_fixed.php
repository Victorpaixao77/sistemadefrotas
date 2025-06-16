<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check authentication
require_authentication();

// Set page title
$page_title = "Abastecimentos";

// Pegar filtros da URL
$data_rota_filtro = isset($_GET['data_rota']) ? $_GET['data_rota'] : '';
$veiculo_id_filtro = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : '';
$rota_id_filtro = isset($_GET['rota_id']) ? intval($_GET['rota_id']) : '';

// Função para buscar abastecimentos do banco de dados
function getAbastecimentos($page = 1, $data_rota = '', $veiculo_id = '', $rota_id = '') {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5;
        $offset = ($page - 1) * $limit;
        
        // Construir a cláusula WHERE
        $where = ['a.empresa_id = :empresa_id'];
        $params = [':empresa_id' => $empresa_id];
        
        if ($data_rota) {
            $where[] = 'DATE(a.data_rota) = :data_rota';
            $params[':data_rota'] = $data_rota;
        }
        if ($veiculo_id) {
            $where[] = 'a.veiculo_id = :veiculo_id';
            $params[':veiculo_id'] = $veiculo_id;
        }
        if ($rota_id) {
            $where[] = 'a.rota_id = :rota_id';
            $params[':rota_id'] = $rota_id;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Query para contar total
        $sql_count = "SELECT COUNT(*) as total 
                     FROM abastecimentos a 
                     WHERE $whereClause";
        
        $stmt_count = $conn->prepare($sql_count);
        foreach ($params as $key => $val) {
            $stmt_count->bindValue($key, $val);
        }
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Query principal com JOINs
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
                WHERE $whereClause
                ORDER BY a.data_abastecimento DESC, a.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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

// Adicionar cálculo dos cartões do dashboard
function getDashboardCountsAbastecimentos() {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    $total = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id")->fetchColumn();
    $pendentes = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id AND status = 'pendente'")->fetchColumn();
    $aprovados = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id AND status = 'aprovado'")->fetchColumn();
    $rejeitados = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id AND status = 'rejeitado'")->fetchColumn();
    return [
        'total' => $total,
        'pendentes' => $pendentes,
        'aprovados' => $aprovados,
        'rejeitados' => $rejeitados
    ];
}

$counts = getDashboardCountsAbastecimentos();

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar abastecimentos com filtros
$resultado = getAbastecimentos($pagina_atual, $data_rota_filtro, $veiculo_id_filtro, $rota_id_filtro);
$abastecimentos = $resultado['abastecimentos'];
$total_paginas = $resultado['total_paginas'];

// Processa a aprovação/rejeição do abastecimento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn = getConnection();
        
        if ($_POST['action'] === 'aprovar') {
            $sql = "UPDATE abastecimentos SET status = 'aprovado' WHERE id = :id AND empresa_id = :empresa_id";
            $message = 'Abastecimento aprovado com sucesso!';
        } else if ($_POST['action'] === 'rejeitar') {
            $sql = "UPDATE abastecimentos SET status = 'rejeitado' WHERE id = :id AND empresa_id = :empresa_id";
            $message = 'Abastecimento rejeitado com sucesso!';
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id' => $_POST['id'],
            'empresa_id' => $_SESSION['empresa_id']
        ]);
        
        setFlashMessage('success', $message);
    } catch (Exception $e) {
        setFlashMessage('error', 'Erro ao processar abastecimento: ' . $e->getMessage());
    }
    
    header('Location: abastecimentos_motoristas.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Gestão de Abastecimentos</h1>
                </div>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Abastecimentos Pendentes</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['pendentes']; ?></span><span class="metric-subtitle">Pendentes</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Abastecimentos Aprovados</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['aprovados']; ?></span><span class="metric-subtitle">Aprovados</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Abastecimentos Rejeitados</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['rejeitados']; ?></span><span class="metric-subtitle">Rejeitados</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Total de Abastecimentos</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['total']; ?></span><span class="metric-subtitle">Total</span></div></div>
                    </div>
                </div>
                <div class="filter-section">
                    <div class="search-box">
                        <input type="date" id="filterDataRota" placeholder="Data da Rota" value="<?php echo $data_rota_filtro; ?>">
                        <select id="filterVeiculo">
                            <option value="">Todos os veículos</option>
                            <?php
                            $conn = getConnection();
                            $empresa_id = $_SESSION['empresa_id'];
                            $stmt = $conn->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ? ORDER BY placa");
                            $stmt->execute([$empresa_id]);
                            while ($veiculo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = $veiculo['id'] == $veiculo_id_filtro ? 'selected' : '';
                                echo "<option value='{$veiculo['id']}' {$selected}>{$veiculo['placa']} ({$veiculo['modelo']})</option>";
                            }
                            ?>
                        </select>
                        <select id="filterRota">
                            <option value="">Todas as rotas</option>
                        </select>
                        <button id="btnFiltrar" class="btn-primary">Filtrar</button>
                    </div>
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendentes</option>
                            <option value="aprovado">Aprovados</option>
                            <option value="rejeitado">Rejeitados</option>
                        </select>
                    </div>
                </div>
                
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Motorista</th>
                                <th>Posto</th>
                                <th>Litros</th>
                                <th>Valor/L</th>
                                <th>Valor Total</th>
                                <th>Km</th>
                                <th>Forma Pgto</th>
                                <th>Rota</th>
                                <th>Status</th>
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
                                    <td><?php echo number_format($abastecimento['km_atual'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['forma_pagamento']); ?></td>
                                    <td><?php 
                                        if (!empty($abastecimento['cidade_origem_nome']) && !empty($abastecimento['cidade_destino_nome'])) {
                                            echo htmlspecialchars($abastecimento['cidade_origem_nome'] . ' → ' . $abastecimento['cidade_destino_nome']);
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $abastecimento['status']; ?>">
                                            <?php echo ucfirst($abastecimento['status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn-icon view-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($abastecimento['status'] === 'pendente'): ?>
                                            <button class="btn-icon approve-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Aprovar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn-icon reject-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Rejeitar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
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
            </div>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes do Abastecimento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Data do Abastecimento</label>
                        <p id="viewData"></p>
                    </div>
                    <div class="form-group">
                        <label>Veículo</label>
                        <p id="viewVeiculo"></p>
                    </div>
                    <div class="form-group">
                        <label>Motorista</label>
                        <p id="viewMotorista"></p>
                    </div>
                    <div class="form-group">
                        <label>Posto</label>
                        <p id="viewPosto"></p>
                    </div>
                    <div class="form-group">
                        <label>Litros</label>
                        <p id="viewLitros"></p>
                    </div>
                    <div class="form-group">
                        <label>Valor por Litro</label>
                        <p id="viewValorLitro"></p>
                    </div>
                    <div class="form-group">
                        <label>Valor Total</label>
                        <p id="viewValorTotal"></p>
                    </div>
                    <div class="form-group">
                        <label>Quilometragem</label>
                        <p id="viewKm"></p>
                    </div>
                    <div class="form-group">
                        <label>Forma de Pagamento</label>
                        <p id="viewFormaPagamento"></p>
                    </div>
                    <div class="form-group">
                        <label>Rota</label>
                        <p id="viewRota"></p>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <p id="viewStatus"></p>
                    </div>
                    <div class="form-group full-width">
                        <label>Observações</label>
                        <p id="viewObservacoes"></p>
                    </div>
                    <div class="form-group full-width">
                        <label>Comprovante</label>
                        <div id="viewComprovante"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Configuração dos modais
            const modals = document.querySelectorAll('.modal');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.modal').style.display = 'none';
                });
            });
            
            window.addEventListener('click', function(event) {
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });
            
            // Visualizar Abastecimento
            document.querySelectorAll('.view-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    fetch(`../api/abastecimentos_motoristas/view.php?id=${abastecimentoId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const abastecimento = data.data;
                                document.getElementById('viewData').textContent = new Date(abastecimento.data_abastecimento).toLocaleDateString('pt-BR');
                                document.getElementById('viewVeiculo').textContent = abastecimento.veiculo_placa;
                                document.getElementById('viewMotorista').textContent = abastecimento.motorista_nome;
                                document.getElementById('viewPosto').textContent = abastecimento.posto;
                                document.getElementById('viewLitros').textContent = parseFloat(abastecimento.litros).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                document.getElementById('viewValorLitro').textContent = 'R$ ' + parseFloat(abastecimento.valor_litro).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                document.getElementById('viewValorTotal').textContent = 'R$ ' + parseFloat(abastecimento.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                document.getElementById('viewKm').textContent = parseFloat(abastecimento.km_atual).toLocaleString('pt-BR');
                                document.getElementById('viewFormaPagamento').textContent = abastecimento.forma_pagamento;
                                document.getElementById('viewRota').textContent = abastecimento.cidade_origem_nome + ' → ' + abastecimento.cidade_destino_nome;
                                document.getElementById('viewStatus').textContent = abastecimento.status.charAt(0).toUpperCase() + abastecimento.status.slice(1);
                                document.getElementById('viewObservacoes').textContent = abastecimento.observacoes || '-';
                                
                                if (abastecimento.comprovante) {
                                    document.getElementById('viewComprovante').innerHTML = `
                                        <a href="../uploads/comprovantes/${abastecimento.comprovante}" target="_blank" class="btn-link">
                                            <i class="fas fa-file-alt"></i> Ver Comprovante
                                        </a>`;
                                } else {
                                    document.getElementById('viewComprovante').textContent = 'Nenhum comprovante';
                                }
                                
                                document.getElementById('viewModal').style.display = 'block';
                            } else {
                                Swal.fire('Erro', 'Erro ao carregar detalhes do abastecimento', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            Swal.fire('Erro', 'Erro ao carregar detalhes do abastecimento', 'error');
                        });
                });
            });
            
            // Aprovar Abastecimento
            document.querySelectorAll('.approve-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    Swal.fire({
                        title: 'Confirmar Aprovação',
                        text: 'Deseja aprovar este abastecimento?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sim, aprovar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="aprovar">
                                <input type="hidden" name="id" value="${abastecimentoId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });
            
            // Rejeitar Abastecimento
            document.querySelectorAll('.reject-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    Swal.fire({
                        title: 'Confirmar Rejeição',
                        text: 'Deseja rejeitar este abastecimento?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sim, rejeitar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="rejeitar">
                                <input type="hidden" name="id" value="${abastecimentoId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });
            
            // Eventos para filtro dinâmico
            document.getElementById('filterDataRota').addEventListener('change', function() {
                const data = this.value;
                if (data) {
                    // Carregar veículos disponíveis para a data
                    fetch(`../api/refuel_data.php?action=get_veiculos_by_data&data=${data}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const veiculoSelect = document.getElementById('filterVeiculo');
                                veiculoSelect.innerHTML = '<option value="">Todos os veículos</option>' +
                                    data.data.map(v => `<option value="${v.id}">${v.placa} (${v.modelo})</option>`).join('');
                                veiculoSelect.disabled = false;
                            }
                        });
                }
            });

            document.getElementById('filterVeiculo').addEventListener('change', function() {
                const veiculoId = this.value;
                const data = document.getElementById('filterDataRota').value;
                if (veiculoId && data) {
                    // Carregar rotas disponíveis para o veículo e data
                    fetch(`../api/refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${veiculoId}&data=${data}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const rotaSelect = document.getElementById('filterRota');
                                rotaSelect.innerHTML = '<option value="">Todas as rotas</option>' +
                                    data.data.map(r => `<option value="${r.id}">${r.data_rota} - ${r.cidade_origem_nome} → ${r.cidade_destino_nome}</option>`).join('');
                                rotaSelect.disabled = false;
                            }
                        });
                }
            });

            document.getElementById('btnFiltrar').addEventListener('click', function() {
                const data = document.getElementById('filterDataRota').value;
                const veiculo = document.getElementById('filterVeiculo').value;
                const rota = document.getElementById('filterRota').value;
                const status = document.getElementById('statusFilter').value;
                
                let url = 'abastecimentos_motoristas.php?';
                if (data) url += `data_rota=${data}&`;
                if (veiculo) url += `veiculo_id=${veiculo}&`;
                if (rota) url += `rota_id=${rota}&`;
                if (status) url += `status=${status}`;
                
                window.location.href = url;
            });
        });
    </script>
</body>
</html> 