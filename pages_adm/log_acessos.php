<?php
require_once '../includes/conexao.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Parâmetros de filtro e paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros
$filtro_empresa = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : null;
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;

// Se veio via iframe, aplicar filtro automaticamente
$is_iframe = isset($_GET['empresa_id']) || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe');

// Construir query com filtros
$where = [];
$params = [];

if ($filtro_empresa) {
    $where[] = "l.empresa_id = ?";
    $params[] = $filtro_empresa;
}

if ($filtro_tipo) {
    $where[] = "l.tipo_acesso = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_status) {
    $where[] = "l.status = ?";
    $params[] = $filtro_status;
}

if ($data_inicio) {
    $where[] = "DATE(l.data_acesso) >= ?";
    $params[] = $data_inicio;
}

if ($data_fim) {
    $where[] = "DATE(l.data_acesso) <= ?";
    $params[] = $data_fim;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Contar total de registros
try {
    $count_sql = "SELECT COUNT(*) as total FROM log_acessos l $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);

    // Buscar logs
    $sql = "SELECT l.*, 
                   u.nome as usuario_nome, 
                   u.email as usuario_email,
                   e.razao_social as empresa_nome,
                   DATE_FORMAT(l.data_acesso, '%d/%m/%Y %H:%i:%s') as data_formatada
            FROM log_acessos l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN empresa_clientes e ON l.empresa_id = e.id
            $where_clause
            ORDER BY l.data_acesso DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Buscar estatísticas gerais
    $stats_sql = "SELECT 
                    COUNT(*) as total_acessos,
                    COUNT(DISTINCT l.usuario_id) as total_usuarios,
                    COUNT(DISTINCT l.empresa_id) as total_empresas,
                    SUM(CASE WHEN l.tipo_acesso = 'login' AND l.status = 'sucesso' THEN 1 ELSE 0 END) as logins_sucesso,
                    SUM(CASE WHEN l.tipo_acesso = 'tentativa_login_falha' THEN 1 ELSE 0 END) as tentativas_falha,
                    SUM(CASE WHEN l.tipo_acesso = 'logout' THEN 1 ELSE 0 END) as logouts,
                    SUM(CASE WHEN DATE(l.data_acesso) = CURDATE() THEN 1 ELSE 0 END) as acessos_hoje
                  FROM log_acessos l";
    
    $stats_stmt = $pdo->query($stats_sql);
    $estatisticas = $stats_stmt->fetch();

    // Buscar empresas para o filtro
    $empresas_stmt = $pdo->query("SELECT id, razao_social FROM empresa_clientes ORDER BY razao_social");
    $empresas = $empresas_stmt->fetchAll();

} catch (PDOException $e) {
    $mensagem = "Erro ao carregar logs: " . $e->getMessage();
    $tipo_mensagem = "error";
    $logs = [];
    $total = 0;
    $total_pages = 0;
    $estatisticas = [
        'total_acessos' => 0,
        'total_usuarios' => 0,
        'total_empresas' => 0,
        'logins_sucesso' => 0,
        'tentativas_falha' => 0,
        'logouts' => 0,
        'acessos_hoje' => 0
    ];
    $empresas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Acessos - Sistema de Frotas</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .main-content.iframe-mode {
            margin-left: 0 !important;
            padding: 15px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #333;
            margin: 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            margin: 0;
        }
        .stat-card.success .value {
            color: #28a745;
        }
        .stat-card.danger .value {
            color: #dc3545;
        }
        .stat-card.warning .value {
            color: #ffc107;
        }
        .filters-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            font-size: 0.9rem;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            background: #007bff;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
            display: inline-block;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
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
            padding: 20px;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            background: white;
            border: 1px solid #ddd;
        }
        .pagination a:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .text-small {
            font-size: 0.85rem;
            color: #666;
        }
        @media (max-width: 768px) {
            .main-content:not(.iframe-mode) {
                margin-left: 0;
            }
            .filters-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!$is_iframe): ?>
        <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>
    
    <div class="main-content <?php echo $is_iframe ? 'iframe-mode' : ''; ?>">
        <div class="header">
            <h1><i class="fas fa-history"></i> Log de Acessos</h1>
            <?php if ($is_iframe && $filtro_empresa): ?>
                <p class="text-muted mb-0">Filtrado para empresa específica</p>
            <?php endif; ?>
        </div>

        <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Acessos</h3>
                <p class="value"><?php echo number_format($estatisticas['total_acessos']); ?></p>
            </div>
            <div class="stat-card success">
                <h3>Logins com Sucesso</h3>
                <p class="value"><?php echo number_format($estatisticas['logins_sucesso']); ?></p>
            </div>
            <div class="stat-card danger">
                <h3>Tentativas Falhas</h3>
                <p class="value"><?php echo number_format($estatisticas['tentativas_falha']); ?></p>
            </div>
            <div class="stat-card warning">
                <h3>Acessos Hoje</h3>
                <p class="value"><?php echo number_format($estatisticas['acessos_hoje']); ?></p>
            </div>
            <div class="stat-card">
                <h3>Total de Usuários</h3>
                <p class="value"><?php echo number_format($estatisticas['total_usuarios']); ?></p>
            </div>
            <div class="stat-card">
                <h3>Total de Empresas</h3>
                <p class="value"><?php echo number_format($estatisticas['total_empresas']); ?></p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-container" <?php echo $is_iframe ? 'style="display: none;"' : ''; ?>>
            <h3 style="margin-top: 0; margin-bottom: 15px; color: #333;"><i class="fas fa-filter"></i> Filtros</h3>
            <form method="GET" action="">
                <?php if ($is_iframe): ?>
                    <input type="hidden" name="empresa_id" value="<?php echo $filtro_empresa; ?>">
                <?php endif; ?>
                <div class="filters-row">
                    <div class="form-group">
                        <label>Empresa</label>
                        <select name="empresa_id" <?php echo $is_iframe ? 'disabled' : ''; ?>>
                            <option value="">Todas as empresas</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?php echo $empresa['id']; ?>" <?php echo ($filtro_empresa == $empresa['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empresa['razao_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Acesso</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <option value="login" <?php echo ($filtro_tipo == 'login') ? 'selected' : ''; ?>>Login</option>
                            <option value="logout" <?php echo ($filtro_tipo == 'logout') ? 'selected' : ''; ?>>Logout</option>
                            <option value="tentativa_login_falha" <?php echo ($filtro_tipo == 'tentativa_login_falha') ? 'selected' : ''; ?>>Tentativa Falha</option>
                            <option value="sessao_expirada" <?php echo ($filtro_tipo == 'sessao_expirada') ? 'selected' : ''; ?>>Sessão Expirada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="sucesso" <?php echo ($filtro_status == 'sucesso') ? 'selected' : ''; ?>>Sucesso</option>
                            <option value="falha" <?php echo ($filtro_status == 'falha') ? 'selected' : ''; ?>>Falha</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">
                    </div>
                    <div class="form-group">
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="log_acessos.php" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Logs -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Empresa</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                Nenhum log encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['data_formatada']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($log['empresa_nome'] ?? 'N/A'); ?>
                                    <br><span class="text-small">ID: <?php echo $log['empresa_id']; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['usuario_nome'] ?? 'N/A'); ?>
                                    <?php if ($log['usuario_email']): ?>
                                        <br><span class="text-small"><?php echo htmlspecialchars($log['usuario_email']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $tipo_badges = [
                                        'login' => '<span class="badge badge-info">Login</span>',
                                        'logout' => '<span class="badge badge-warning">Logout</span>',
                                        'tentativa_login_falha' => '<span class="badge badge-danger">Tentativa Falha</span>',
                                        'sessao_expirada' => '<span class="badge badge-warning">Sessão Expirada</span>'
                                    ];
                                    echo $tipo_badges[$log['tipo_acesso']] ?? '<span class="badge">' . htmlspecialchars($log['tipo_acesso']) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($log['status'] == 'sucesso'): ?>
                                        <span class="badge badge-success">Sucesso</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Falha</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-small"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span></td>
                                <td><span class="text-small"><?php echo htmlspecialchars($log['descricao'] ?? '-'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['empresa_id' => $filtro_empresa, 'tipo' => $filtro_tipo, 'status' => $filtro_status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])); ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <span>Página <?php echo $page; ?> de <?php echo $total_pages; ?> (<?php echo number_format($total); ?> registros)</span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['empresa_id' => $filtro_empresa, 'tipo' => $filtro_tipo, 'status' => $filtro_status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])); ?>">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
