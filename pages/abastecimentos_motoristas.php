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
        $where = ['a.empresa_id = :empresa_id', "a.status = 'pendente'"];
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
    $total = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id AND status = 'pendente'")->fetchColumn();
    $pendentes = $total;
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
    <link rel="icon" type="image/png" href="../logo.png">
    
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
                        <input type="text" id="searchFuelDriver" placeholder="Buscar por motorista, veículo ou posto...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <input type="date" id="filterDataRota" title="Data da rota">
                        <select id="filterVeiculo" title="Veículo">
                            <option value="">Todos os veículos</option>
                            <?php
                            $conn = getConnection();
                            $empresa_id = $_SESSION['empresa_id'];
                            $stmt = $conn->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ? ORDER BY placa");
                            $stmt->execute([$empresa_id]);
                            while ($veiculo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$veiculo['id']}'>{$veiculo['placa']} ({$veiculo['modelo']})</option>";
                            }
                            ?>
                        </select>
                        <select id="filterRota" title="Rota">
                            <option value="">Todas as rotas</option>
                        </select>
                        <select id="statusFilter" title="Status">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendentes</option>
                            <option value="aprovado">Aprovados</option>
                            <option value="rejeitado">Rejeitados</option>
                        </select>
                        <select id="driverFilter" title="Motorista">
                            <option value="">Todos os motoristas</option>
                        </select>
                        <button type="button" class="btn-restore-layout" id="applyRefuelDriverFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearRefuelDriverFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Motorista</th>
                                <th>Veículo</th>
                                <th>Posto</th>
                                <th>Litros</th>
                                <th>Valor por Litro</th>
                                <th>Valor Total</th>
                                <th>Quilometragem</th>
                                <th>Forma de Pagamento</th>
                                <th>Rota</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abastecimentos as $abastecimento): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($abastecimento['data_abastecimento'])); ?></td>
                                <td><?php echo htmlspecialchars($abastecimento['motorista_nome']); ?></td>
                                <td><?php echo htmlspecialchars($abastecimento['veiculo_placa']); ?></td>
                                <td><?php echo htmlspecialchars($abastecimento['posto_nome']); ?></td>
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
                                    <span class="status-badge <?php echo $abastecimento['status'] === 'pendente' ? 'warning' : ($abastecimento['status'] === 'aprovado' ? 'success' : 'danger'); ?>">
                                        <?php echo ucfirst($abastecimento['status']); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Visualizar Abastecimento">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $abastecimento['id']; ?>" data-veiculo-id="<?php echo $abastecimento['veiculo_id']; ?>" data-data-rota="<?php echo isset($abastecimento['data_rota']) ? $abastecimento['data_rota'] : ''; ?>" title="Editar Abastecimento">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon accept-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Aprovar Abastecimento">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-icon reject-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Rejeitar Abastecimento">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($abastecimentos)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Nenhum abastecimento encontrado</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Visualizar Abastecimento
            document.querySelectorAll('.view-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    fetch(`../api/abastecimentos_motoristas/view.php?id=${abastecimentoId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const abastecimento = data.data;
                                // Preencher o modal com os dados do abastecimento
                                document.getElementById('viewModalTitle').textContent = 'Detalhes do Abastecimento';
                                document.getElementById('viewData').textContent = abastecimento.data_abastecimento;
                                document.getElementById('viewVeiculo').textContent = abastecimento.veiculo_placa;
                                document.getElementById('viewMotorista').textContent = abastecimento.motorista_nome;
                                document.getElementById('viewPosto').textContent = abastecimento.posto_nome;
                                document.getElementById('viewLitros').textContent = abastecimento.litros;
                                document.getElementById('viewValorTotal').textContent = `R$ ${parseFloat(abastecimento.valor_total).toFixed(2)}`;
                                document.getElementById('viewStatus').textContent = abastecimento.status;
                                
                                // Mostrar o modal
                                document.getElementById('viewModal').style.display = 'block';
                            } else {
                                alert('Erro ao carregar detalhes do abastecimento: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao carregar detalhes do abastecimento');
                        });
                });
            });

            // Função para abrir o modal de edição e preencher todos os campos corretamente
            async function openEditAbastecimentoModal(abastecimento) {
                // Preencher campos básicos
                document.getElementById('editAbastecimentoId').value = abastecimento.id;
                document.getElementById('editDataRota').value = abastecimento.data_rota ? abastecimento.data_rota.split('T')[0] : '';
                document.getElementById('editDataAbastecimento').value = abastecimento.data_abastecimento ? abastecimento.data_abastecimento.replace(' ', 'T') : '';
                document.getElementById('editTipoCombustivel').value = abastecimento.tipo_combustivel;
                document.getElementById('editLitros').value = abastecimento.litros;
                document.getElementById('editValorLitro').value = abastecimento.valor_litro;
                document.getElementById('editValorTotal').value = abastecimento.valor_total;
                document.getElementById('editKmAtual').value = abastecimento.km_atual;
                document.getElementById('editPosto').value = abastecimento.posto;
                document.getElementById('editFormaPagamento').value = abastecimento.forma_pagamento;
                document.getElementById('editObservacoes').value = abastecimento.observacoes || '';

                // Preencher select de veículo apenas com o valor do banco
                const veiculoSelect = document.getElementById('editVeiculoId');
                veiculoSelect.innerHTML = '';
                if (abastecimento.veiculo_id) {
                    const option = document.createElement('option');
                    option.value = abastecimento.veiculo_id;
                    option.textContent = abastecimento.veiculo_placa ? abastecimento.veiculo_placa : `Veículo #${abastecimento.veiculo_id}`;
                    veiculoSelect.appendChild(option);
                    veiculoSelect.value = abastecimento.veiculo_id;
                } else {
                    veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>';
                }

                // Preencher select de motorista apenas com o valor do banco
                const motoristaSelect = document.getElementById('editMotoristaId');
                motoristaSelect.innerHTML = '';
                if (abastecimento.motorista_id) {
                    const option = document.createElement('option');
                    option.value = abastecimento.motorista_id;
                    option.textContent = abastecimento.motorista_nome ? abastecimento.motorista_nome : `Motorista #${abastecimento.motorista_id}`;
                    motoristaSelect.appendChild(option);
                    motoristaSelect.value = abastecimento.motorista_id;
                } else {
                    motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>';
                }

                // Preencher o select de rotas apenas com a rota do abastecimento
                const rotaSelect = document.getElementById('editRotaId');
                rotaSelect.innerHTML = '<option value="">Selecione a rota</option>';
                if (abastecimento.rota_id) {
                    const option = document.createElement('option');
                    option.value = abastecimento.rota_id;
                    option.textContent = `${abastecimento.data_rota ? abastecimento.data_rota.split('T')[0] : ''} - ${abastecimento.cidade_origem_nome || ''} → ${abastecimento.cidade_destino_nome || ''}` || `Rota #${abastecimento.rota_id}`;
                    rotaSelect.appendChild(option);
                    rotaSelect.value = abastecimento.rota_id;
                }

                // Exibe o modal
                document.getElementById('editModal').classList.add('active');
            }

            // Substituir o evento de click dos botões de edição para usar a nova função
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    fetch(`../api/abastecimentos_motoristas/view.php?id=${abastecimentoId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                openEditAbastecimentoModal(data.data);
                            } else {
                                alert('Erro ao carregar dados do abastecimento: ' + data.error);
                            }
                        })
                        .catch(error => {
                            alert('Erro ao carregar dados do abastecimento: ' + error.message);
                        });
                });
            });

            // Aceitar Abastecimento
            document.querySelectorAll('.accept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    if (confirm('Tem certeza que deseja aprovar este abastecimento?')) {
                        fetch('../api/abastecimentos_motoristas/status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `id=${abastecimentoId}&status=aprovado`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Abastecimento aprovado com sucesso!');
                                location.reload();
                            } else {
                                alert('Erro ao aprovar abastecimento: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao processar a solicitação');
                        });
                    }
                });
            });

            // Rejeitar Abastecimento
            document.querySelectorAll('.reject-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const abastecimentoId = this.dataset.id;
                    if (confirm('Tem certeza que deseja rejeitar este abastecimento?')) {
                        fetch('../api/abastecimentos_motoristas/status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `id=${abastecimentoId}&status=rejeitado`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Abastecimento rejeitado com sucesso!');
                                location.reload();
                            } else {
                                alert('Erro ao rejeitar abastecimento: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao processar a solicitação');
                        });
                    }
                });
            });

            // Fechar modais
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.modal').style.display = 'none';
                });
            });

            // Salvar edição
            const saveEditBtn = document.getElementById('saveEditBtn');
            if (saveEditBtn) {
                saveEditBtn.addEventListener('click', function() {
                    const formData = new FormData(document.getElementById('editForm'));
                    fetch('../api/abastecimentos_motoristas/update.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Abastecimento atualizado com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro ao atualizar abastecimento: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao processar a solicitação');
                    });
                });
            }

            // Carregar tipos de combustível e formas de pagamento no modal de edição
            function carregarTiposCombustivel() {
                fetch('../api/tipos_combustivel.php?action=list')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const select = document.getElementById('editTipoCombustivel');
                            select.innerHTML = '<option value="">Selecione o Tipo de Combustível</option>';
                            data.tipos.forEach(tipo => {
                                const option = document.createElement('option');
                                option.value = tipo.nome;
                                option.textContent = tipo.nome;
                                select.appendChild(option);
                            });
                        }
                    });
            }
            function carregarFormasPagamento() {
                fetch('../api/formas_pagamento.php?action=list')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const select = document.getElementById('editFormaPagamento');
                            select.innerHTML = '<option value="">Selecione a Forma de Pagamento</option>';
                            data.formas.forEach(forma => {
                                const option = document.createElement('option');
                                option.value = forma.nome;
                                option.textContent = forma.nome;
                                select.appendChild(option);
                            });
                        }
                    });
            }
            const searchInput = document.getElementById('searchFuelDriver');
            const tableBody = document.querySelector('.data-table tbody');

            function applySearchFilter() {
                if (!tableBody) return;
                const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
                const rows = tableBody.querySelectorAll('tr');

                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    row.style.display = !term || rowText.includes(term) ? '' : 'none';
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', applySearchFilter);
            }

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

            document.getElementById('applyRefuelDriverFilters').addEventListener('click', function() {
                const data = document.getElementById('filterDataRota').value;
                const veiculo = document.getElementById('filterVeiculo').value;
                const rota = document.getElementById('filterRota').value;
                const status = document.getElementById('statusFilter').value;
                
                let url = 'abastecimentos_motoristas.php?';
                if (data) url += `data_rota=${data}&`;
                if (veiculo) url += `veiculo_id=${veiculo}&`;
                if (rota) url += `rota_id=${rota}&`;
                if (status) url += `status=${status}`;

                window.location.href = url.endsWith('?') ? 'abastecimentos_motoristas.php' : url;
            });

            document.getElementById('clearRefuelDriverFilters').addEventListener('click', function() {
                const dataInput = document.getElementById('filterDataRota');
                const veiculoSelect = document.getElementById('filterVeiculo');
                const rotaSelect = document.getElementById('filterRota');
                const statusSelect = document.getElementById('statusFilter');
                const driverSelect = document.getElementById('driverFilter');

                if (searchInput) searchInput.value = '';
                if (dataInput) dataInput.value = '';
                if (veiculoSelect) veiculoSelect.value = '';
                if (rotaSelect) rotaSelect.value = '';
                if (statusSelect) statusSelect.value = '';
                if (driverSelect) driverSelect.value = '';

                window.location.href = 'abastecimentos_motoristas.php';
            });

            // Adicionar função para carregar rotas
            function carregarRotas(veiculoId, motoristaId, data) {
                console.log('Carregando rotas com:', { veiculoId, motoristaId, data });
                
                const url = `../api/abastecimentos_motoristas/rotas.php?veiculo_id=${veiculoId}&motorista_id=${motoristaId}&data=${data}`;
                console.log('URL da API:', url);
                
                return fetch(url)
                    .then(response => {
                        console.log('Status da resposta:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Erro ao fazer parse do JSON:', e);
                                console.error('Texto recebido:', text);
                                throw new Error('Resposta inválida do servidor');
                            }
                        });
                    })
                    .then(data => {
                        console.log('Dados recebidos:', data);
                        if (!data.success) {
                            throw new Error(data.error || 'Erro ao carregar rotas');
                        }
                        return data.data;
                    });
            }

            // Atualizar o select de rotas SOMENTE se o usuário alterar veículo, motorista ou data
            function atualizarRotasPorFiltro() {
                const veiculoId = document.getElementById('editVeiculoId').value;
                const motoristaId = document.getElementById('editMotoristaId').value;
                const data = document.getElementById('editDataRota').value;
                if (!veiculoId || !motoristaId || !data) {
                    return;
                }
                carregarRotas(veiculoId, motoristaId, data)
                    .then(rotas => {
                        const rotaSelect = document.getElementById('editRotaId');
                        rotaSelect.innerHTML = '<option value="">Selecione a rota</option>' +
                            rotas.map(r => `<option value="${r.id}">${r.data_rota} - ${r.cidade_origem_nome} → ${r.cidade_destino_nome}</option>`).join('');
                    })
                    .catch(error => {
                        console.error('Erro ao carregar rotas:', error);
                        alert('Erro ao carregar rotas: ' + error.message);
                    });
            }

            document.getElementById('editVeiculoId').addEventListener('change', atualizarRotasPorFiltro);
            document.getElementById('editMotoristaId').addEventListener('change', atualizarRotasPorFiltro);
            document.getElementById('editDataRota').addEventListener('change', atualizarRotasPorFiltro);
        });
    </script>

    <!-- Modal de Visualização -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewModalTitle">Detalhes do Abastecimento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Data:</label>
                        <span id="viewData"></span>
                    </div>
                    <div class="detail-item">
                        <label>Veículo:</label>
                        <span id="viewVeiculo"></span>
                    </div>
                    <div class="detail-item">
                        <label>Motorista:</label>
                        <span id="viewMotorista"></span>
                    </div>
                    <div class="detail-item">
                        <label>Posto:</label>
                        <span id="viewPosto"></span>
                    </div>
                    <div class="detail-item">
                        <label>Litros:</label>
                        <span id="viewLitros"></span>
                    </div>
                    <div class="detail-item">
                        <label>Valor Total:</label>
                        <span id="viewValorTotal"></span>
                    </div>
                    <div class="detail-item">
                        <label>Status:</label>
                        <span id="viewStatus"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Abastecimento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm" class="form-grid" enctype="multipart/form-data">
                    <input type="hidden" id="editAbastecimentoId" name="id">
                    <div class="form-group">
                        <label for="editDataRota">Data da Rota*</label>
                        <input type="date" name="data_rota" id="editDataRota" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editDataAbastecimento">Data Abastecimento*</label>
                        <input type="datetime-local" name="data_abastecimento" id="editDataAbastecimento" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editVeiculoId">Veículo*</label>
                        <select name="veiculo_id" id="editVeiculoId" class="form-control" required>
                            <option value="">Selecione um veículo</option>
                            <?php
                            $conn = getConnection();
                            $empresa_id = $_SESSION['empresa_id'];
                            $stmt = $conn->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ? ORDER BY placa");
                            $stmt->execute([$empresa_id]);
                            while ($veiculo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$veiculo['id']}'>{$veiculo['placa']} ({$veiculo['modelo']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editMotoristaId">Motorista*</label>
                        <select name="motorista_id" id="editMotoristaId" class="form-control" required>
                            <option value="">Selecione um motorista</option>
                            <?php
                            $stmt = $conn->prepare("SELECT id, nome FROM motoristas WHERE empresa_id = ? ORDER BY nome");
                            $stmt->execute([$empresa_id]);
                            while ($motorista = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$motorista['id']}'>{$motorista['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editTipoCombustivel">Combustível*</label>
                        <select name="tipo_combustivel" id="editTipoCombustivel" class="form-control" required>
                            <option value="">Selecione o combustível</option>
                            <option value="Diesel S10">Diesel S10</option>
                            <option value="Diesel Comum">Diesel Comum</option>
                            <option value="Gasolina">Gasolina</option>
                            <option value="Etanol">Etanol</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editLitros">Litros*</label>
                        <input type="number" step="0.01" name="litros" id="editLitros" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editValorLitro">Valor por Litro*</label>
                        <input type="number" step="0.01" name="valor_litro" id="editValorLitro" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editValorTotal">Valor Total*</label>
                        <input type="number" step="0.01" name="valor_total" id="editValorTotal" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editKmAtual">Quilometragem*</label>
                        <input type="number" name="km_atual" id="editKmAtual" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editPosto">Posto*</label>
                        <input type="text" name="posto" id="editPosto" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editFormaPagamento">Forma de Pagamento*</label>
                        <input type="text" name="forma_pagamento" id="editFormaPagamento" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editRotaId">Rota*</label>
                        <select name="rota_id" id="editRotaId" class="form-control" required>
                            <option value="">Selecione a rota</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editObservacoes">Observações</label>
                        <textarea name="observacoes" id="editObservacoes" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editComprovante">Comprovante</label>
                        <input type="file" name="comprovante" id="editComprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <small>Formatos aceitos: PDF, JPG, JPEG, PNG</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary close-modal">Cancelar</button>
                <button id="saveEditBtn" class="btn-primary">Salvar Alterações</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../js/verificar_pendencias.js"></script>
</body>
</html> 