<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
$page_title = "Checklists";

// Função para buscar checklists do banco de dados
function getChecklists($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM checklist_viagem WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT c.*, 
                v.placa as veiculo_placa,
                m.nome as motorista_nome
                FROM checklist_viagem c
                LEFT JOIN veiculos v ON c.veiculo_id = v.id
                LEFT JOIN motoristas m ON c.motorista_id = m.id
                WHERE c.empresa_id = :empresa_id
                ORDER BY c.data_checklist DESC, c.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'checklists' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar checklists: " . $e->getMessage());
        return [
            'checklists' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar checklists com paginação
$resultado = getChecklists($pagina_atual);
$checklists = $resultado['checklists'];
$total_paginas = $resultado['total_paginas'];

// Processa a aprovação/rejeição do checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn = getConnection();
        
        if ($_POST['action'] === 'aprovar') {
            $sql = "UPDATE checklists SET status = 'aprovado' WHERE id = :id AND empresa_id = :empresa_id";
            $message = 'Checklist aprovado com sucesso!';
        } else if ($_POST['action'] === 'rejeitar') {
            $sql = "UPDATE checklists SET status = 'rejeitado' WHERE id = :id AND empresa_id = :empresa_id";
            $message = 'Checklist rejeitado com sucesso!';
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id' => $_POST['id'],
            'empresa_id' => $_SESSION['empresa_id']
        ]);
        
        setFlashMessage('success', $message);
    } catch (Exception $e) {
        setFlashMessage('error', 'Erro ao processar checklist: ' . $e->getMessage());
    }
    
    header('Location: checklists.php');
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
    <style>
        /* ... existing ... */
        .analytics-section {
            margin-top: 40px;
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
            height: 300px;
            position: relative;
        }
        .analytics-card.full-width {
            grid-column: 1 / -1;
        }
        @media (max-width: 768px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            .analytics-card .card-body {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Checklists de Viagem</h1>
                </div>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Total de Checklists</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value"><?php echo $resultado['total']; ?></span><span class="metric-subtitle">Checklists</span></div></div>
                    </div>
                </div>

                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchChecklist" placeholder="Buscar checklist...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                            <!-- Preencher via JS se desejar -->
                        </select>
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                            <!-- Preencher via JS se desejar -->
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Motorista</th>
                                <th>Fonte</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklists as $checklist): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($checklist['data_checklist'])); ?></td>
                                <td><?php echo htmlspecialchars($checklist['veiculo_placa']); ?></td>
                                <td><?php echo htmlspecialchars($checklist['motorista_nome']); ?></td>
                                <td><?php echo htmlspecialchars($checklist['fonte']); ?></td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $checklist['id']; ?>" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($checklists)): ?>
                            <tr>
                                <td colspan="5" class="text-center">Nenhum checklist encontrado</td>
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

                <!-- SEÇÃO DE GRÁFICOS E ANÁLISES -->
                <div class="analytics-section">
                    <div class="section-header"><h2>Análises Inteligentes de Checklists</h2></div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header"><h3>Conformidade do Checklist por Motorista</h3></div>
                            <div class="card-body"><canvas id="chartConformidadeMotorista"></canvas></div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header"><h3>Itens Mais Negligenciados</h3></div>
                            <div class="card-body"><canvas id="chartItensNegligenciados"></canvas></div>
                        </div>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header"><h3>Histórico Diário de Checklists</h3></div>
                            <div class="card-body"><canvas id="chartHistoricoDiario"></canvas></div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header"><h3>Ranking de Veículos com Mais Riscos</h3></div>
                            <div class="card-body"><canvas id="chartRankingVeiculos"></canvas></div>
                        </div>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card full-width">
                            <div class="card-header"><h3>Checklists com Risco Alto</h3></div>
                            <div class="card-body" style="overflow-x:auto; max-height:300px;">
                                <table class="data-table" id="tableRiscoAlto">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Motorista</th>
                                            <th>Veículo</th>
                                            <th>Score Segurança</th>
                                            <th>Observações</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    // Confirmação antes de aprovar/rejeitar
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const action = this.querySelector('input[name="action"]').value;
            const message = action === 'aprovar' ? 
                'Tem certeza que deseja aprovar este checklist?' : 
                'Tem certeza que deseja rejeitar este checklist?';
            
            Swal.fire({
                title: 'Confirmação',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim',
                cancelButtonText: 'Não'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    });
    </script>

    <!-- Modal de Visualização do Checklist -->
    <div id="viewChecklistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes do Checklist</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="detail-item"><label>Data:</label> <span id="viewDataChecklist"></span></div>
                    <div class="detail-item"><label>Motorista:</label> <span id="viewMotorista"></span></div>
                    <div class="detail-item"><label>Veículo:</label> <span id="viewVeiculo"></span></div>
                    <div class="detail-item"><label>Fonte:</label> <span id="viewFonte"></span></div>
                    <div class="detail-item"><label>Observações:</label> <span id="viewObservacoes"></span></div>
                </div>
                <hr>
                <div class="details-grid">
                    <div class="detail-item"><label>Óleo Motor:</label> <span id="viewOleoMotor"></span></div>
                    <div class="detail-item"><label>Água Radiador:</label> <span id="viewAguaRadiador"></span></div>
                    <div class="detail-item"><label>Fluido Freio:</label> <span id="viewFluidoFreio"></span></div>
                    <div class="detail-item"><label>Fluido Direção:</label> <span id="viewFluidoDirecao"></span></div>
                    <div class="detail-item"><label>Combustível:</label> <span id="viewCombustivel"></span></div>
                    <div class="detail-item"><label>Pneus:</label> <span id="viewPneus"></span></div>
                    <div class="detail-item"><label>Estepe:</label> <span id="viewEstepe"></span></div>
                    <div class="detail-item"><label>Luzes:</label> <span id="viewLuzes"></span></div>
                    <div class="detail-item"><label>Buzina:</label> <span id="viewBuzina"></span></div>
                    <div class="detail-item"><label>Limpador Para-brisa:</label> <span id="viewLimpador"></span></div>
                    <div class="detail-item"><label>Água Limpador:</label> <span id="viewAguaLimpador"></span></div>
                    <div class="detail-item"><label>Freios:</label> <span id="viewFreios"></span></div>
                    <div class="detail-item"><label>Vazamentos:</label> <span id="viewVazamentos"></span></div>
                    <div class="detail-item"><label>Rastreador:</label> <span id="viewRastreador"></span></div>
                    <div class="detail-item"><label>Triângulo:</label> <span id="viewTriangulo"></span></div>
                    <div class="detail-item"><label>Extintor:</label> <span id="viewExtintor"></span></div>
                    <div class="detail-item"><label>Chave/Macaco:</label> <span id="viewChaveMacaco"></span></div>
                    <div class="detail-item"><label>Cintas:</label> <span id="viewCintas"></span></div>
                    <div class="detail-item"><label>Primeiros Socorros:</label> <span id="viewPrimeirosSocorros"></span></div>
                    <div class="detail-item"><label>Doc. Veículo:</label> <span id="viewDocVeiculo"></span></div>
                    <div class="detail-item"><label>CNH:</label> <span id="viewCNH"></span></div>
                    <div class="detail-item"><label>Licenciamento:</label> <span id="viewLicenciamento"></span></div>
                    <div class="detail-item"><label>Seguro:</label> <span id="viewSeguro"></span></div>
                    <div class="detail-item"><label>Manifesto Carga:</label> <span id="viewManifestoCarga"></span></div>
                    <div class="detail-item"><label>Doc. Empresa:</label> <span id="viewDocEmpresa"></span></div>
                    <div class="detail-item"><label>Carga Amarrada:</label> <span id="viewCargaAmarrada"></span></div>
                    <div class="detail-item"><label>Peso Correto:</label> <span id="viewPesoCorreto"></span></div>
                    <div class="detail-item"><label>Motorista Descansado:</label> <span id="viewMotoristaDescansado"></span></div>
                    <div class="detail-item"><label>Motorista Sóbrio:</label> <span id="viewMotoristaSobrio"></span></div>
                    <div class="detail-item"><label>Celular Carregado:</label> <span id="viewCelularCarregado"></span></div>
                    <div class="detail-item"><label>EPI:</label> <span id="viewEPI"></span></div>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Abrir modal de visualização ao clicar no botão
    function boolToSimNao(val) {
        if (val === null || val === undefined) return '-';
        return val == 1 ? 'Sim' : 'Não';
    }
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const checklistId = this.dataset.id;
            fetch(`../api/checklist_viagem/view.php?id=${checklistId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const c = data.data;
                        document.getElementById('viewDataChecklist').textContent = c.data_checklist ? c.data_checklist.split(' ')[0].split('-').reverse().join('/') : '';
                        document.getElementById('viewMotorista').textContent = c.motorista_nome || '';
                        document.getElementById('viewVeiculo').textContent = c.veiculo_placa || '';
                        document.getElementById('viewFonte').textContent = c.fonte || '';
                        document.getElementById('viewObservacoes').textContent = c.observacoes || '';
                        document.getElementById('viewOleoMotor').textContent = boolToSimNao(c.oleo_motor);
                        document.getElementById('viewAguaRadiador').textContent = boolToSimNao(c.agua_radiador);
                        document.getElementById('viewFluidoFreio').textContent = boolToSimNao(c.fluido_freio);
                        document.getElementById('viewFluidoDirecao').textContent = boolToSimNao(c.fluido_direcao);
                        document.getElementById('viewCombustivel').textContent = boolToSimNao(c.combustivel);
                        document.getElementById('viewPneus').textContent = boolToSimNao(c.pneus);
                        document.getElementById('viewEstepe').textContent = boolToSimNao(c.estepe);
                        document.getElementById('viewLuzes').textContent = boolToSimNao(c.luzes);
                        document.getElementById('viewBuzina').textContent = boolToSimNao(c.buzina);
                        document.getElementById('viewLimpador').textContent = boolToSimNao(c.limpador_para_brisa);
                        document.getElementById('viewAguaLimpador').textContent = boolToSimNao(c.agua_limpador);
                        document.getElementById('viewFreios').textContent = boolToSimNao(c.freios);
                        document.getElementById('viewVazamentos').textContent = boolToSimNao(c.vazamentos);
                        document.getElementById('viewRastreador').textContent = boolToSimNao(c.rastreador);
                        document.getElementById('viewTriangulo').textContent = boolToSimNao(c.triangulo);
                        document.getElementById('viewExtintor').textContent = boolToSimNao(c.extintor);
                        document.getElementById('viewChaveMacaco').textContent = boolToSimNao(c.chave_macaco);
                        document.getElementById('viewCintas').textContent = boolToSimNao(c.cintas);
                        document.getElementById('viewPrimeirosSocorros').textContent = boolToSimNao(c.primeiros_socorros);
                        document.getElementById('viewDocVeiculo').textContent = boolToSimNao(c.doc_veiculo);
                        document.getElementById('viewCNH').textContent = boolToSimNao(c.cnh);
                        document.getElementById('viewLicenciamento').textContent = boolToSimNao(c.licenciamento);
                        document.getElementById('viewSeguro').textContent = boolToSimNao(c.seguro);
                        document.getElementById('viewManifestoCarga').textContent = boolToSimNao(c.manifesto_carga);
                        document.getElementById('viewDocEmpresa').textContent = boolToSimNao(c.doc_empresa);
                        document.getElementById('viewCargaAmarrada').textContent = boolToSimNao(c.carga_amarrada);
                        document.getElementById('viewPesoCorreto').textContent = boolToSimNao(c.peso_correto);
                        document.getElementById('viewMotoristaDescansado').textContent = boolToSimNao(c.motorista_descansado);
                        document.getElementById('viewMotoristaSobrio').textContent = boolToSimNao(c.motorista_sobrio);
                        document.getElementById('viewCelularCarregado').textContent = boolToSimNao(c.celular_carregado);
                        document.getElementById('viewEPI').textContent = boolToSimNao(c.epi);
                        document.getElementById('viewChecklistModal').style.display = 'block';
                    } else {
                        alert('Checklist não encontrado.');
                    }
                });
        });
    });
    // Fechar modal
    if (document.querySelector('#viewChecklistModal .close-modal')) {
        document.querySelector('#viewChecklistModal .close-modal').onclick = function() {
            document.getElementById('viewChecklistModal').style.display = 'none';
        };
    }
    </script>

    <!-- DASHBOARDS INTELIGENTES CHECKLIST -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Conformidade por Motorista
        fetch('../api/checklist_dashboard.php?action=conformidade_motorista')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const labels = res.data.map(x => x.motorista_nome || 'Sem nome');
                    const total = res.data.map(x => parseInt(x.total_checklists));
                    const oks = res.data.map(x => parseInt(x.total_oks));
                    const percent = oks.map((ok, i) => total[i] ? Math.round((ok/ (total[i]*30))*100) : 0); // 30 itens por checklist
                    new Chart(document.getElementById('chartConformidadeMotorista'), {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: '% Itens OK',
                                data: percent,
                                backgroundColor: 'rgba(54, 162, 235, 0.6)'
                            }]
                        },
                        options: {scales: {y: {beginAtZero: true, max: 100}}, plugins: {legend: {display: false}}}
                    });
                }
            });
        // 2. Itens Mais Negligenciados
        fetch('../api/checklist_dashboard.php?action=itens_negligenciados')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const labels = res.data.map(x => x.item);
                    const data = res.data.map(x => parseInt(x.vezes_com_problema));
                    new Chart(document.getElementById('chartItensNegligenciados'), {
                        type: 'bar',
                        data: {labels, datasets: [{label: 'Vezes com problema', data, backgroundColor: 'rgba(255, 99, 132, 0.6)'}]},
                        options: {scales: {y: {beginAtZero: true}}, plugins: {legend: {display: false}}}
                    });
                }
            });
        // 3. Histórico Diário
        fetch('../api/checklist_dashboard.php?action=historico_diario')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const labels = res.data.map(x => x.data.split('-').reverse().join('/'));
                    const data = res.data.map(x => parseInt(x.total_checklists));
                    new Chart(document.getElementById('chartHistoricoDiario'), {
                        type: 'line',
                        data: {labels, datasets: [{label: 'Checklists', data, borderColor: 'rgba(54, 162, 235, 1)', backgroundColor: 'rgba(54,162,235,0.1)', fill: true}]},
                        options: {scales: {y: {beginAtZero: true}}}
                    });
                }
            });
        // 4. Ranking de Veículos
        fetch('../api/checklist_dashboard.php?action=ranking_veiculos')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const labels = res.data.map(x => x.veiculo_placa || 'Sem placa');
                    const data = res.data.map(x => parseInt(x.possiveis_riscos));
                    new Chart(document.getElementById('chartRankingVeiculos'), {
                        type: 'bar',
                        data: {labels, datasets: [{label: 'Possíveis Riscos', data, backgroundColor: 'rgba(255, 206, 86, 0.7)'}]},
                        options: {scales: {y: {beginAtZero: true}}, plugins: {legend: {display: false}}}
                    });
                }
            });
        // 5. Checklists com Risco Alto (tabela)
        fetch('../api/checklist_dashboard.php?action=risco_alto')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const tbody = document.querySelector('#tableRiscoAlto tbody');
                    tbody.innerHTML = '';
                    res.data.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.data_checklist ? row.data_checklist.split(' ')[0].split('-').reverse().join('/') : ''}</td>
                            <td>${row.motorista_nome || '-'}</td>
                            <td>${row.veiculo_placa || '-'}</td>
                            <td>${row.score_seguranca}</td>
                            <td>${row.observacoes || ''}</td>`;
                        tbody.appendChild(tr);
                    });
                }
            });
    });
    </script>
</body>
</html> 