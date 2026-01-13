<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Log de Acessos';
$empresa_id = $_SESSION['empresa_id'];
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
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .filters {
            background: #1a1f2e;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .filters-row:last-child {
            margin-bottom: 0;
        }
        .table-container {
            background: #1a1f2e;
            border-radius: 10px;
            overflow: hidden;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination button {
            padding: 8px 15px;
            background: #3fa6ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .pagination button:disabled {
            background: #555;
            cursor: not-allowed;
        }
        .pagination span {
            color: #b0b8c9;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-history"></i> <?php echo $page_title; ?></h1>
        </div>
        
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card success">
                <div class="stat-label">Total de Acessos</div>
                <div class="stat-value" id="totalAcessos">-</div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Logins com Sucesso</div>
                <div class="stat-value" id="loginsSucesso">-</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Tentativas Falhas</div>
                <div class="stat-value" id="tentativasFalha">-</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Acessos Hoje</div>
                <div class="stat-value" id="acessosHoje">-</div>
            </div>
        </div>
        
        <div class="filters">
            <h3 style="margin-bottom: 15px; color: #b0b8c9;"><i class="fas fa-filter"></i> Filtros</h3>
            <div class="filters-row">
                <div>
                    <label style="color: #b0b8c9; display: block; margin-bottom: 5px;">Tipo de Acesso</label>
                    <select id="filtroTipo" class="form-control">
                        <option value="">Todos</option>
                        <option value="login">Login</option>
                        <option value="logout">Logout</option>
                        <option value="tentativa_login_falha">Tentativa Falha</option>
                        <option value="sessao_expirada">Sessão Expirada</option>
                    </select>
                </div>
                <div>
                    <label style="color: #b0b8c9; display: block; margin-bottom: 5px;">Status</label>
                    <select id="filtroStatus" class="form-control">
                        <option value="">Todos</option>
                        <option value="sucesso">Sucesso</option>
                        <option value="falha">Falha</option>
                    </select>
                </div>
                <div>
                    <label style="color: #b0b8c9; display: block; margin-bottom: 5px;">Data Início</label>
                    <input type="date" id="dataInicio" class="form-control">
                </div>
                <div>
                    <label style="color: #b0b8c9; display: block; margin-bottom: 5px;">Data Fim</label>
                    <input type="date" id="dataFim" class="form-control">
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button onclick="aplicarFiltros()" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <button onclick="limparFiltros()" class="btn btn-secondary" style="margin-left: 10px;">
                    <i class="fas fa-times"></i> Limpar
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            <i class="fas fa-spinner fa-spin"></i> Carregando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="pagination" id="pagination"></div>
    </div>
    
    <script>
        let currentPage = 1;
        let currentFilters = {};
        
        function getTipoBadge(tipo) {
            const badges = {
                'login': '<span class="badge badge-info">Login</span>',
                'logout': '<span class="badge badge-warning">Logout</span>',
                'tentativa_login_falha': '<span class="badge badge-danger">Tentativa Falha</span>',
                'sessao_expirada': '<span class="badge badge-warning">Sessão Expirada</span>'
            };
            return badges[tipo] || '<span class="badge">' + tipo + '</span>';
        }
        
        function getStatusBadge(status) {
            if (status === 'sucesso') {
                return '<span class="badge badge-success">Sucesso</span>';
            } else {
                return '<span class="badge badge-danger">Falha</span>';
            }
        }
        
        function carregarLogs(page = 1) {
            currentPage = page;
            const params = new URLSearchParams({
                action: 'listar',
                page: page,
                limit: 50,
                ...currentFilters
            });
            
            fetch(`../api/log_acessos.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        atualizarTabela(data.data);
                        atualizarPaginacao(data.pagination);
                    } else {
                        document.getElementById('logsTableBody').innerHTML = 
                            '<tr><td colspan="6" style="text-align: center;">Erro ao carregar logs</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('logsTableBody').innerHTML = 
                        '<tr><td colspan="6" style="text-align: center;">Erro ao carregar logs</td></tr>';
                });
        }
        
        function atualizarTabela(logs) {
            const tbody = document.getElementById('logsTableBody');
            
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum log encontrado</td></tr>';
                return;
            }
            
            tbody.innerHTML = logs.map(log => `
                <tr>
                    <td>${log.data_formatada || log.data_acesso}</td>
                    <td>${log.usuario_nome || 'N/A'}<br><small style="color: #888;">${log.usuario_email || ''}</small></td>
                    <td>${getTipoBadge(log.tipo_acesso)}</td>
                    <td>${getStatusBadge(log.status)}</td>
                    <td><small>${log.ip_address || 'N/A'}</small></td>
                    <td><small>${log.descricao || '-'}</small></td>
                </tr>
            `).join('');
        }
        
        function atualizarPaginacao(pagination) {
            const paginationDiv = document.getElementById('pagination');
            const { page, pages, total } = pagination;
            
            paginationDiv.innerHTML = `
                <button onclick="carregarLogs(${page - 1})" ${page <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i> Anterior
                </button>
                <span>Página ${page} de ${pages} (${total} registros)</span>
                <button onclick="carregarLogs(${page + 1})" ${page >= pages ? 'disabled' : ''}>
                    Próxima <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }
        
        function carregarEstatisticas() {
            fetch('../api/log_acessos.php?action=estatisticas')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.estatisticas;
                        document.getElementById('totalAcessos').textContent = stats.total_acessos || 0;
                        document.getElementById('loginsSucesso').textContent = stats.logins_sucesso || 0;
                        document.getElementById('tentativasFalha').textContent = stats.tentativas_falha || 0;
                        document.getElementById('acessosHoje').textContent = stats.acessos_hoje || 0;
                    }
                })
                .catch(error => console.error('Erro ao carregar estatísticas:', error));
        }
        
        function aplicarFiltros() {
            currentFilters = {};
            
            const tipo = document.getElementById('filtroTipo').value;
            if (tipo) currentFilters.tipo = tipo;
            
            const status = document.getElementById('filtroStatus').value;
            if (status) currentFilters.status = status;
            
            const dataInicio = document.getElementById('dataInicio').value;
            if (dataInicio) currentFilters.data_inicio = dataInicio;
            
            const dataFim = document.getElementById('dataFim').value;
            if (dataFim) currentFilters.data_fim = dataFim;
            
            carregarLogs(1);
        }
        
        function limparFiltros() {
            document.getElementById('filtroTipo').value = '';
            document.getElementById('filtroStatus').value = '';
            document.getElementById('dataInicio').value = '';
            document.getElementById('dataFim').value = '';
            currentFilters = {};
            carregarLogs(1);
        }
        
        // Carregar dados ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarLogs();
            carregarEstatisticas();
        });
    </script>
</body>
</html>
