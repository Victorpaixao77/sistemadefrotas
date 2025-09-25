<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../includes/config.php';
require_once '../includes/functions.php';
configure_session();
session_start();
require_authentication();
$page_title = "Rotas";
function getRotasPendentes($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5;
        $offset = ($page - 1) * $limit;
        $sql_count = "SELECT COUNT(*) as total FROM rotas WHERE empresa_id = :empresa_id AND status = 'pendente'";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        $sql = "SELECT r.*, v.placa as veiculo_placa, m.nome as motorista_nome,
                co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :empresa_id AND r.status = 'pendente'
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'rotas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar rotas: " . $e->getMessage());
        return [
            'rotas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}
function getDashboardCounts() {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    $total = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();
    $pendentes = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id AND status = 'pendente'")->fetchColumn();
    $aceitas = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id AND status = 'aprovado'")->fetchColumn();
    $rejeitadas = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id AND status = 'rejeitado'")->fetchColumn();
    return [
        'total' => $total,
        'pendentes' => $pendentes,
        'aceitas' => $aceitas,
        'rejeitadas' => $rejeitadas
    ];
}
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$resultado = getRotasPendentes($pagina_atual);
$rotas = $resultado['rotas'];
$total_paginas = $resultado['total_paginas'];
$counts = getDashboardCounts();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Gestão de Rotas Pendentes</h1>
                </div>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Rotas Pendentes</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['pendentes']; ?></span><span class="metric-subtitle">Pendentes</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Rotas Aceitas</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['aceitas']; ?></span><span class="metric-subtitle">Aceitas</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Rotas Rejeitadas</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['rejeitadas']; ?></span><span class="metric-subtitle">Rejeitadas</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Total de Rotas</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $counts['total']; ?></span><span class="metric-subtitle">Total</span></div></div>
                    </div>
                </div>
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchRoute" placeholder="Buscar rota...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendentes</option>
                            <option value="aprovado">Aceitas</option>
                            <option value="rejeitado">Rejeitadas</option>
                        </select>
                        <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                        </select>
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Motorista</th>
                                <th>Veículo</th>
                                <th>Rota</th>
                                <th>Distância</th>
                                <th>Frete</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rotas as $rota): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($rota['data_rota']); ?>
                                    (<?php echo date('d/m/Y', strtotime($rota['data_rota'])); ?>)
                                </td>
                                <td><?php echo htmlspecialchars($rota['motorista_nome'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rota['veiculo_placa'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rota['cidade_origem_nome'] ?? '-') . ' → ' . htmlspecialchars($rota['cidade_destino_nome'] ?? '-'); ?></td>
                                <td><?php echo number_format($rota['distancia_km'], 0, ',', '.') . ' km'; ?></td>
                                <td>R$ <?php echo number_format($rota['frete'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge warning">Pendente</span>
                                </td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $rota['id']; ?>" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $rota['id']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon expenses-btn" data-id="<?php echo $rota['id']; ?>" title="Despesas de Viagem">
                                        <i class="fas fa-money-bill"></i>
                                    </button>
                                    <button class="btn-icon accept-btn" data-id="<?php echo $rota['id']; ?>" title="Aceitar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-icon reject-btn" data-id="<?php echo $rota['id']; ?>" title="Rejeitar">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rotas)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Nenhuma rota pendente encontrada</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <?php if ($total_paginas > 1): ?>
                        <a href="?page=<?php echo $pagina_atual - 1; ?>" class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="pagination-info">
                            Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                        </span>
                        <a href="?page=<?php echo $pagina_atual + 1; ?>" class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/verificar_pendencias.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Visualizar Rota
            document.querySelectorAll('.view-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const rotaId = this.dataset.id;
                    fetch(`../api/rotas/view.php?id=${rotaId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const rota = data.data;
                                // Preencher o modal com os dados da rota
                                document.getElementById('viewModalTitle').textContent = 'Detalhes da Rota';
                                document.getElementById('viewData').textContent = rota.data_rota;
                                document.getElementById('viewMotorista').textContent = rota.motorista_nome;
                                document.getElementById('viewVeiculo').textContent = rota.veiculo_placa;
                                document.getElementById('viewRota').textContent = `${rota.cidade_origem_nome} → ${rota.cidade_destino_nome}`;
                                document.getElementById('viewDistancia').textContent = `${rota.distancia_km} km`;
                                document.getElementById('viewFrete').textContent = `R$ ${parseFloat(rota.frete).toFixed(2)}`;
                                document.getElementById('viewStatus').textContent = rota.status;
                                
                                // Mostrar o modal
                                document.getElementById('viewModal').style.display = 'block';
                            } else {
                                alert('Erro ao carregar detalhes da rota: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao carregar detalhes da rota');
                        });
                });
            });

            // Função para carregar cidades de um estado
            function loadCidades(uf, targetSelectId, selectedId = null) {
                return fetch(`../api/route_actions.php?action=get_cidades&uf=${uf}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const cidadeSelect = document.getElementById(targetSelectId);
                            if (cidadeSelect) {
                                cidadeSelect.disabled = false;
                                const options = data.data.map(cidade => 
                                    `<option value="${cidade.id}">${cidade.nome}</option>`
                                ).join('');
                                cidadeSelect.innerHTML = '<option value="">Selecione a cidade</option>' + options;
                                if (selectedId) cidadeSelect.value = selectedId;
                            }
                        }
                    });
            }

            // Função para preencher o formulário de edição
            function fillEditForm(data) {
                document.getElementById('editRotaId').value = data.id || '';
                document.getElementById('editDataRota').value = data.data_rota || '';
                document.getElementById('editMotoristaId').value = data.motorista_id || '';
                document.getElementById('editVeiculoId').value = data.veiculo_id || '';
                // Estado e cidade de origem
                document.getElementById('editEstadoOrigem').value = data.estado_origem || '';
                if (data.estado_origem) {
                    loadCidades(data.estado_origem, 'editCidadeOrigemId', data.cidade_origem_id);
                }
                // Estado e cidade de destino
                document.getElementById('editEstadoDestino').value = data.estado_destino || '';
                if (data.estado_destino) {
                    loadCidades(data.estado_destino, 'editCidadeDestinoId', data.cidade_destino_id);
                }
                document.getElementById('editDataSaida').value = data.data_saida ? data.data_saida.replace(' ', 'T') : '';
                document.getElementById('editDataChegada').value = data.data_chegada ? data.data_chegada.replace(' ', 'T') : '';
                document.getElementById('editKmSaida').value = data.km_saida || '';
                document.getElementById('editKmChegada').value = data.km_chegada || '';
                document.getElementById('editDistanciaKm').value = data.distancia_km || '';
                document.getElementById('editKmVazio').value = data.km_vazio || '';
                document.getElementById('editTotalKm').value = data.total_km || '';
                document.getElementById('editFrete').value = data.frete || '';
                document.getElementById('editComissao').value = data.comissao || '';
                document.getElementById('editPercentualVazio').value = data.percentual_vazio || '';
                document.getElementById('editEficienciaViagem').value = data.eficiencia_viagem || '';
                document.getElementById('editNoPrazo').value = data.no_prazo || '0';
                document.getElementById('editPesoCarga').value = data.peso_carga || '';
                document.getElementById('editDescricaoCarga').value = data.descricao_carga || '';
                document.getElementById('editObservacoes').value = data.observacoes || '';
            }

            // Carregar estados no select (pode ser feito ao abrir o modal)
            function loadEstados(selectId, selectedUf = null) {
                fetch('../api/route_actions.php?action=get_estados')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const select = document.getElementById(selectId);
                            if (select) {
                                // data.data pode ser array de objetos {uf, nome}
                                let options = '';
                                if (typeof data.data[0] === 'object') {
                                    options = data.data.map(uf => `<option value="${uf.uf}">${uf.nome}</option>`).join('');
                                } else {
                                    options = data.data.map(uf => `<option value="${uf}">${uf}</option>`).join('');
                                }
                                select.innerHTML = '<option value="">Selecione o estado</option>' + options;
                                if (selectedUf) select.value = selectedUf;
                            }
                        }
                    });
            }

            // Listeners para carregar cidades ao trocar estado
            function setupEstadoCidadeListeners() {
                const estadoOrigem = document.getElementById('editEstadoOrigem');
                const cidadeOrigem = document.getElementById('editCidadeOrigemId');
                if (estadoOrigem && cidadeOrigem) {
                    estadoOrigem.addEventListener('change', function() {
                        if (this.value) {
                            loadCidades(this.value, 'editCidadeOrigemId');
                        } else {
                            cidadeOrigem.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                        }
                    });
                }
                const estadoDestino = document.getElementById('editEstadoDestino');
                const cidadeDestino = document.getElementById('editCidadeDestinoId');
                if (estadoDestino && cidadeDestino) {
                    estadoDestino.addEventListener('change', function() {
                        if (this.value) {
                            loadCidades(this.value, 'editCidadeDestinoId');
                        } else {
                            cidadeDestino.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                        }
                    });
                }
            }

            // Editar Rota
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const rotaId = this.dataset.id;
                    fetch(`../api/rotas/view.php?id=${rotaId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Carregar estados antes de preencher
                                loadEstados('editEstadoOrigem', data.data.estado_origem);
                                loadEstados('editEstadoDestino', data.data.estado_destino);
                                fillEditForm(data.data);
                                setupFormCalculations();
                                setupEstadoCidadeListeners();
                                document.getElementById('editModal').style.display = 'block';
                            } else {
                                alert('Erro ao carregar dados da rota: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao carregar dados da rota');
                        });
                });
            });

            // Despesas de Viagem
            document.querySelectorAll('.expenses-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const rotaId = this.dataset.id;
                    document.getElementById('expenseRouteId').value = rotaId;
                    // Buscar despesas da API
                    fetch(`../api/despesas_viagem/view.php?rota_id=${rotaId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data) {
                                fillExpensesForm(data.data);
                                calculateTotalExpenses();
                            } else {
                                fillExpensesForm({});
                                calculateTotalExpenses();
                            }
                            document.getElementById('expensesModal').style.display = 'block';
                        })
                        .catch(() => {
                            fillExpensesForm({});
                            calculateTotalExpenses();
                            document.getElementById('expensesModal').style.display = 'block';
                        });
                });
            });

            // Aceitar Rota
            document.querySelectorAll('.accept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const rotaId = this.dataset.id;
                    if (confirm('Tem certeza que deseja aceitar esta rota?')) {
                        fetch('../api/rotas/status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `id=${rotaId}&status=aprovado`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Rota aceita com sucesso!');
                                location.reload();
                            } else {
                                alert('Erro ao aceitar rota: ' + data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao processar a solicitação');
                        });
                    }
                });
            });

            // Rejeitar Rota
            document.querySelectorAll('.reject-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const rotaId = this.dataset.id;
                    if (confirm('Tem certeza que deseja rejeitar esta rota?')) {
                        fetch('../api/rotas/status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `id=${rotaId}&status=rejeitado`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Rota rejeitada com sucesso!');
                                location.reload();
                            } else {
                                alert('Erro ao rejeitar rota: ' + data.error);
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
                    fetch('../api/rotas/update.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Rota atualizada com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro ao atualizar rota: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Erro ao processar a solicitação');
                    });
                });
            }

            // Cálculos automáticos no formulário de edição
            function setupFormCalculations() {
                const kmSaida = document.getElementById('editKmSaida');
                const kmChegada = document.getElementById('editKmChegada');
                const distanciaKm = document.getElementById('editDistanciaKm');
                const kmVazio = document.getElementById('editKmVazio');
                const totalKm = document.getElementById('editTotalKm');
                const frete = document.getElementById('editFrete');
                const comissao = document.getElementById('editComissao');
                const percentualVazio = document.getElementById('editPercentualVazio');
                const eficienciaViagem = document.getElementById('editEficienciaViagem');

                function calcularDistancia() {
                    if (kmSaida.value && kmChegada.value) {
                        distanciaKm.value = (parseFloat(kmChegada.value) - parseFloat(kmSaida.value)).toFixed(2);
                    }
                    calcularTotais();
                    calcularPercentualVazio();
                    calcularEficiencia();
                }
                function calcularTotais() {
                    if (distanciaKm.value && kmVazio.value) {
                        totalKm.value = (parseFloat(distanciaKm.value) + parseFloat(kmVazio.value)).toFixed(2);
                    }
                    calcularEficiencia();
                }
                function calcularComissao() {
                    if (frete.value) {
                        comissao.value = (parseFloat(frete.value) * 0.1).toFixed(2); // Exemplo: 10% do frete
                    }
                }
                function calcularPercentualVazio() {
                    if (kmVazio.value && distanciaKm.value) {
                        percentualVazio.value = ((parseFloat(kmVazio.value) / parseFloat(distanciaKm.value)) * 100).toFixed(2);
                    }
                }
                function calcularEficiencia() {
                    if (distanciaKm.value && totalKm.value && parseFloat(totalKm.value) > 0) {
                        eficienciaViagem.value = ((parseFloat(distanciaKm.value) / parseFloat(totalKm.value)) * 100).toFixed(2);
                    } else {
                        eficienciaViagem.value = '';
                    }
                }

                kmSaida.addEventListener('input', calcularDistancia);
                kmChegada.addEventListener('input', calcularDistancia);
                distanciaKm.addEventListener('input', calcularTotais);
                kmVazio.addEventListener('input', calcularTotais);
                frete.addEventListener('input', calcularComissao);
                kmVazio.addEventListener('input', calcularPercentualVazio);
                distanciaKm.addEventListener('input', calcularPercentualVazio);
                distanciaKm.addEventListener('input', calcularEficiencia);
                totalKm.addEventListener('input', calcularEficiencia);
            }

            // Filtros superiores igual ao routes.php
            function loadFilterOptions() {
                // Motoristas
                fetch('../api/route_actions.php?action=get_motoristas')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const select = document.getElementById('driverFilter');
                            if (select) {
                                select.innerHTML = '<option value="">Todos os motoristas</option>' +
                                    data.data.map(m => `<option value="${m.id}">${m.nome}</option>`).join('');
                            }
                        }
                    });
                // Veículos
                fetch('../api/route_actions.php?action=get_veiculos')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const select = document.getElementById('vehicleFilter');
                            if (select) {
                                select.innerHTML = '<option value="">Todos os veículos</option>' +
                                    data.data.map(v => `<option value="${v.id}">${v.placa} (${v.modelo})</option>`).join('');
                            }
                        }
                    });
            }
            document.addEventListener('DOMContentLoaded', loadFilterOptions);

            // Função para preencher o formulário de despesas
            function fillExpensesForm(despesas) {
                const fields = [
                    'arla', 'pedagios', 'caixinha', 'estacionamento', 'lavagem',
                    'borracharia', 'eletrica_mecanica', 'adiantamento', 'total_despviagem'
                ];
                fields.forEach(f => {
                    document.getElementById(f).value = despesas && despesas[f] ? despesas[f] : '';
                });
            }

            // Função para calcular o total das despesas
            function calculateTotalExpenses() {
                const fields = [
                    'arla', 'pedagios', 'caixinha', 'estacionamento', 'lavagem',
                    'borracharia', 'eletrica_mecanica', 'adiantamento'
                ];
                let total = 0;
                fields.forEach(f => {
                    const val = parseFloat(document.getElementById(f).value.replace(',', '.')) || 0;
                    total += val;
                });
                document.getElementById('total_despviagem').value = total.toFixed(2);
            }

            // Listeners para cálculo automático
            ['arla','pedagios','caixinha','estacionamento','lavagem','borracharia','eletrica_mecanica','adiantamento']
                .forEach(f => {
                    document.getElementById(f).addEventListener('input', calculateTotalExpenses);
                });

            // Botão Cancelar
            if (document.getElementById('cancelExpensesBtn')) {
                document.getElementById('cancelExpensesBtn').onclick = function() {
                    document.getElementById('expensesModal').style.display = 'none';
                };
            }
            // Botão Limpar
            if (document.getElementById('clearExpensesBtn')) {
                document.getElementById('clearExpensesBtn').onclick = function() {
                    document.getElementById('expensesForm').reset();
                    calculateTotalExpenses();
                };
            }
            // Botão Salvar
            if (document.getElementById('saveExpensesBtn')) {
                document.getElementById('saveExpensesBtn').onclick = function(e) {
                    e.preventDefault();
                    const formData = new FormData(document.getElementById('expensesForm'));
                    fetch('../api/despesas_viagem/update.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Despesas salvas com sucesso!');
                            document.getElementById('expensesModal').style.display = 'none';
                        } else {
                            alert('Erro ao salvar despesas: ' + data.error);
                        }
                    })
                    .catch(error => {
                        alert('Erro ao salvar despesas.');
                    });
                };
            }
        });
    </script>

    <!-- Modal de Visualização -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewModalTitle">Detalhes da Rota</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Data:</label>
                        <span id="viewData"></span>
                    </div>
                    <div class="detail-item">
                        <label>Motorista:</label>
                        <span id="viewMotorista"></span>
                    </div>
                    <div class="detail-item">
                        <label>Veículo:</label>
                        <span id="viewVeiculo"></span>
                    </div>
                    <div class="detail-item">
                        <label>Rota:</label>
                        <span id="viewRota"></span>
                    </div>
                    <div class="detail-item">
                        <label>Distância:</label>
                        <span id="viewDistancia"></span>
                    </div>
                    <div class="detail-item">
                        <label>Frete:</label>
                        <span id="viewFrete"></span>
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
                <h2>Editar Rota</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm" class="form-grid">
                    <input type="hidden" id="editRotaId" name="id">
                    <div class="form-group">
                        <label for="editDataRota">Data da Rota*</label>
                        <input type="date" name="data_rota" id="editDataRota" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editMotoristaId">Motorista*</label>
                        <select name="motorista_id" id="editMotoristaId" class="form-control" required>
                            <option value="">Selecione um motorista</option>
                            <?php
                            $conn = getConnection();
                            $empresa_id = $_SESSION['empresa_id'];
                            $stmt = $conn->prepare("SELECT id, nome FROM motoristas WHERE empresa_id = ? ORDER BY nome");
                            $stmt->execute([$empresa_id]);
                            while ($motorista = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$motorista['id']}'>{$motorista['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editVeiculoId">Veículo*</label>
                        <select name="veiculo_id" id="editVeiculoId" class="form-control" required>
                            <option value="">Selecione um veículo</option>
                            <?php
                            $stmt = $conn->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ? ORDER BY placa");
                            $stmt->execute([$empresa_id]);
                            while ($veiculo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$veiculo['id']}'>{$veiculo['placa']} ({$veiculo['modelo']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editEstadoOrigem">Estado de Origem*</label>
                        <select name="estado_origem" id="editEstadoOrigem" class="form-control" required>
                            <option value="">Selecione o estado</option>
                            <!-- Estados preenchidos via JS -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editCidadeOrigemId">Cidade de Origem*</label>
                        <select name="cidade_origem_id" id="editCidadeOrigemId" class="form-control" required>
                            <option value="">Selecione primeiro o estado</option>
                            <!-- Cidades preenchidas via JS -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editEstadoDestino">Estado de Destino*</label>
                        <select name="estado_destino" id="editEstadoDestino" class="form-control" required>
                            <option value="">Selecione o estado</option>
                            <!-- Estados preenchidos via JS -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editCidadeDestinoId">Cidade de Destino*</label>
                        <select name="cidade_destino_id" id="editCidadeDestinoId" class="form-control" required>
                            <option value="">Selecione primeiro o estado</option>
                            <!-- Cidades preenchidas via JS -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editDataSaida">Data/Hora Saída*</label>
                        <input type="datetime-local" name="data_saida" id="editDataSaida" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="editDataChegada">Data/Hora Chegada</label>
                        <input type="datetime-local" name="data_chegada" id="editDataChegada" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editKmSaida">KM Saída</label>
                        <input type="number" step="0.01" name="km_saida" id="editKmSaida" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editKmChegada">KM Chegada</label>
                        <input type="number" step="0.01" name="km_chegada" id="editKmChegada" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editDistanciaKm">Distância (km)</label>
                        <input type="number" step="0.01" name="distancia_km" id="editDistanciaKm" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editKmVazio">KM Vazio</label>
                        <input type="number" step="0.01" name="km_vazio" id="editKmVazio" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editTotalKm">Total KM</label>
                        <input type="number" step="0.01" name="total_km" id="editTotalKm" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editFrete">Valor do Frete (R$)</label>
                        <input type="number" step="0.01" name="frete" id="editFrete" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editComissao">Comissão (R$)</label>
                        <input type="number" step="0.01" name="comissao" id="editComissao" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editPercentualVazio">Percentual Vazio (%)</label>
                        <input type="number" step="0.01" name="percentual_vazio" id="editPercentualVazio" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editEficienciaViagem">Eficiência da Viagem (%)</label>
                        <input type="number" step="0.01" name="eficiencia_viagem" id="editEficienciaViagem" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editNoPrazo">Entrega no Prazo</label>
                        <select name="no_prazo" id="editNoPrazo" class="form-control">
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editPesoCarga">Peso da Carga (kg)</label>
                        <input type="number" step="0.01" name="peso_carga" id="editPesoCarga" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editDescricaoCarga">Descrição da Carga</label>
                        <textarea name="descricao_carga" id="editDescricaoCarga" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="editObservacoes">Observações</label>
                        <textarea name="observacoes" id="editObservacoes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary close-modal">Cancelar</button>
                <button id="saveEditBtn" class="btn-primary">Salvar Alterações</button>
            </div>
        </div>
    </div>

    <!-- Expenses Modal -->
    <div class="modal" id="expensesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Despesas de Viagem</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="expensesForm">
                    <input type="hidden" id="expenseRouteId" name="rota_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="arla">ARLA</label>
                            <input type="number" id="arla" name="arla" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="pedagios">Pedágios</label>
                            <input type="number" id="pedagios" name="pedagios" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="caixinha">Caixinha</label>
                            <input type="number" id="caixinha" name="caixinha" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="estacionamento">Estacionamento</label>
                            <input type="number" id="estacionamento" name="estacionamento" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="lavagem">Lavagem</label>
                            <input type="number" id="lavagem" name="lavagem" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="borracharia">Borracharia</label>
                            <input type="number" id="borracharia" name="borracharia" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="eletrica_mecanica">Elétrica/Mecânica</label>
                            <input type="number" id="eletrica_mecanica" name="eletrica_mecanica" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="adiantamento">Adiantamento</label>
                            <input type="number" id="adiantamento" name="adiantamento" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="total_despviagem">Total</label>
                            <input type="number" id="total_despviagem" name="total_despviagem" step="0.01" readonly>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelExpensesBtn" class="btn-secondary">Cancelar</button>
                <button id="clearExpensesBtn" class="btn-danger">Limpar</button>
                <button id="saveExpensesBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</body>
</html>