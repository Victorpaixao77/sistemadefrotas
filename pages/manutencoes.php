<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Require authentication
require_authentication();

// Create database connection
$conn = getConnection();

// Set page title
$page_title = "Manutenções";

// Debug session state
error_log("Session state in manutencoes.php: " . print_r($_SESSION, true));

// Função para buscar métricas do dashboard
function getDashboardMetrics($conn) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $primeiro_dia_mes = date('Y-m-01');
        $ultimo_dia_mes = date('Y-m-t');
        
        // Total de manutenções do mês
        $sql_total = "SELECT COUNT(*) as total FROM manutencoes 
                     WHERE empresa_id = :empresa_id 
                     AND data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bindParam(':empresa_id', $empresa_id);
        $stmt_total->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_total->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_total->execute();
        $total_manutencoes = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de preventivas do mês (regra unificada: LIKE '%preventiva%' – alinhado ao BI)
        $sql_preventivas = "SELECT COUNT(*) as total FROM manutencoes m
                          LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                          WHERE m.empresa_id = :empresa_id 
                          AND (tm.nome IS NOT NULL AND LOWER(TRIM(tm.nome)) LIKE '%preventiva%')
                          AND m.data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_preventivas = $conn->prepare($sql_preventivas);
        $stmt_preventivas->bindParam(':empresa_id', $empresa_id);
        $stmt_preventivas->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_preventivas->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_preventivas->execute();
        $total_preventivas = $stmt_preventivas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de corretivas do mês (demais tipos ou sem tipo)
        $sql_corretivas = "SELECT COUNT(*) as total FROM manutencoes m
                         LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                         WHERE m.empresa_id = :empresa_id 
                         AND (tm.nome IS NULL OR LOWER(TRIM(tm.nome)) NOT LIKE '%preventiva%')
                         AND m.data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_corretivas = $conn->prepare($sql_corretivas);
        $stmt_corretivas->bindParam(':empresa_id', $empresa_id);
        $stmt_corretivas->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_corretivas->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_corretivas->execute();
        $total_corretivas = $stmt_corretivas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de custos do mês
        $sql_custos = "SELECT COALESCE(SUM(valor), 0) as total FROM manutencoes 
                      WHERE empresa_id = :empresa_id 
                      AND data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_custos = $conn->prepare($sql_custos);
        $stmt_custos->bindParam(':empresa_id', $empresa_id);
        $stmt_custos->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_custos->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_custos->execute();
        $total_custos = $stmt_custos->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'total_manutencoes' => $total_manutencoes,
            'total_preventivas' => $total_preventivas,
            'total_corretivas' => $total_corretivas,
            'total_custos' => $total_custos
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar métricas do dashboard: " . $e->getMessage());
        return [
            'total_manutencoes' => 0,
            'total_preventivas' => 0,
            'total_corretivas' => 0,
            'total_custos' => 0
        ];
    }
}

// Função para buscar métricas técnicas (KPIs)
function getKPIMetrics($conn) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        
        // MTBF (6 meses) e MTTR só para manutenções com data_conclusao
        $sql_mtbf_mttr = "SELECT 
            COALESCE(SUM(v.km_atual), 0) as total_km,
            COUNT(DISTINCT m.id) as total_falhas,
            SUM(CASE WHEN m.data_conclusao IS NOT NULL THEN TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_conclusao) ELSE 0 END) as total_horas_manutencao,
            COUNT(DISTINCT CASE WHEN m.data_conclusao IS NOT NULL THEN m.id END) as falhas_com_conclusao
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id 
            AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
        $stmt = $conn->prepare($sql_mtbf_mttr);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $mtbf_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_horas = (float)($mtbf_data['total_horas_manutencao'] ?? 0);
        $falhas_conclusao = (int)($mtbf_data['falhas_com_conclusao'] ?? 0);
        
        // Custo/km últimos 12 meses (manutenções / km rotas)
        $stmt = $conn->prepare("SELECT COALESCE(SUM(m.valor), 0) as c FROM manutencoes m WHERE m.empresa_id = :eid AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)");
        $stmt->bindParam(':eid', $empresa_id);
        $stmt->execute();
        $custo_12 = (float)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        $stmt = $conn->prepare("SELECT COALESCE(SUM(r.distancia_km), 0) as k FROM rotas r WHERE r.empresa_id = :eid AND r.data_saida IS NOT NULL AND r.data_saida >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)");
        $stmt->bindParam(':eid', $empresa_id);
        $stmt->execute();
        $km_12 = (float)($stmt->fetch(PDO::FETCH_ASSOC)['k'] ?? 0);
        $custo_km = $km_12 > 0 ? ($custo_12 / $km_12) : 0;
        
        // Top 5 veículos com mais manutenções
        $sql_top_veiculos = "SELECT 
            v.placa,
            COUNT(*) as total_manutencoes,
            SUM(m.valor) as custo_total
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id
            GROUP BY v.id
            ORDER BY total_manutencoes DESC
            LIMIT 5";
        
        $stmt = $conn->prepare($sql_top_veiculos);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $top_veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Componentes com mais falhas
        $sql_componentes = "SELECT 
            cm.nome as componente,
            COUNT(*) as total_falhas
            FROM manutencoes m
            JOIN componentes_manutencao cm ON m.componente_id = cm.id
            WHERE m.empresa_id = :empresa_id
            GROUP BY cm.id
            ORDER BY total_falhas DESC
            LIMIT 10";
        
        $stmt = $conn->prepare($sql_componentes);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $componentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Status das manutenções
        $sql_status = "SELECT 
            sm.nome as status,
            COUNT(*) as total
            FROM manutencoes m
            JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
            WHERE m.empresa_id = :empresa_id
            GROUP BY sm.id";
        
        $stmt = $conn->prepare($sql_status);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Evolução mensal
        $sql_evolucao = "SELECT 
            DATE_FORMAT(data_manutencao, '%Y-%m') as mes,
            COUNT(*) as total
            FROM manutencoes
            WHERE empresa_id = :empresa_id
            AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
            ORDER BY mes ASC";
        
        $stmt = $conn->prepare($sql_evolucao);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'mtbf' => (!empty($mtbf_data['total_falhas']) && $mtbf_data['total_falhas'] > 0) ? ($mtbf_data['total_km'] / $mtbf_data['total_falhas']) : 0,
            'mttr' => ($falhas_conclusao > 0 && $total_horas > 0) ? ($total_horas / $falhas_conclusao) : 0,
            'custo_km' => $custo_km,
            'top_veiculos' => $top_veiculos,
            'componentes' => $componentes,
            'status' => $status,
            'evolucao' => $evolucao
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar KPIs: " . $e->getMessage());
        return [
            'mtbf' => 0,
            'mttr' => 0,
            'custo_km' => 0,
            'top_veiculos' => [],
            'componentes' => [],
            'status' => [],
            'evolucao' => []
        ];
    }
}

// Função para buscar manutenções (com período, paginação e ordenação)
function getManutencoes($conn, $page = 1, $opts = []) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $per_page = isset($opts['per_page']) ? max(5, min(100, (int)$opts['per_page'])) : 10;
        $offset = ($page - 1) * $per_page;
        $data_inicio = isset($opts['data_inicio']) && $opts['data_inicio'] !== '' ? $opts['data_inicio'] : null;
        $data_fim = isset($opts['data_fim']) && $opts['data_fim'] !== '' ? $opts['data_fim'] : null;
        $order_by = isset($opts['order_by']) && in_array($opts['order_by'], ['data_manutencao', 'valor', 'veiculo_placa', 'tipo_nome', 'status_nome', 'fornecedor', 'descricao'], true) ? $opts['order_by'] : 'data_manutencao';
        $order_dir = isset($opts['order_dir']) && strtoupper($opts['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
        $cols = ['data_manutencao' => 'm.data_manutencao', 'valor' => 'm.valor', 'veiculo_placa' => 'v.placa', 'tipo_nome' => 'tm.nome', 'status_nome' => 'sm.nome', 'fornecedor' => 'm.fornecedor', 'descricao' => 'm.descricao'];
        $order_col = $cols[$order_by] ?? 'm.data_manutencao';
        $where = "m.empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresa_id];
        if ($data_inicio) { $where .= " AND m.data_manutencao >= :data_inicio"; $params[':data_inicio'] = $data_inicio; }
        if ($data_fim) { $where .= " AND m.data_manutencao <= :data_fim"; $params[':data_fim'] = $data_fim; }
        $sql_count = "SELECT COUNT(*) as total FROM manutencoes m LEFT JOIN veiculos v ON m.veiculo_id = v.id LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id WHERE $where";
        $stmt_count = $conn->prepare($sql_count);
        foreach ($params as $k => $v) $stmt_count->bindValue($k, $v);
        $stmt_count->execute();
        $total = (int)$stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        $sql = "SELECT m.*, v.placa as veiculo_placa, tm.nome as tipo_nome, sm.nome as status_nome
                FROM manutencoes m LEFT JOIN veiculos v ON m.veiculo_id = v.id LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
                WHERE $where ORDER BY $order_col $order_dir, m.id $order_dir LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Custo por veículo (últimos 12 meses) para coluna na listagem
        $custo_12m = [];
        try {
            $st = $conn->prepare("SELECT veiculo_id, COALESCE(SUM(valor), 0) as custo_12m FROM manutencoes WHERE empresa_id = :eid AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) GROUP BY veiculo_id");
            $st->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $custo_12m[(int)$row['veiculo_id']] = (float)$row['custo_12m'];
            }
        } catch (PDOException $e) { /* ignore */ }
        foreach ($result as &$r) {
            $r['custo_veiculo_12m'] = $custo_12m[(int)($r['veiculo_id'] ?? 0)] ?? 0;
        }
        unset($r);
        return [
            'manutencoes' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => $per_page > 0 ? (int)ceil($total / $per_page) : 1,
            'per_page' => $per_page
        ];
    } catch (PDOException $e) {
        error_log("Erro ao buscar manutenções: " . $e->getMessage());
        return ['manutencoes' => [], 'total' => 0, 'pagina_atual' => 1, 'total_paginas' => 1, 'per_page' => 10];
    }
}

// Parâmetros da URL (permalink)
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) $per_page = 10;
$data_inicio = isset($_GET['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim']) ? $_GET['data_fim'] : '';
$order_by = isset($_GET['order']) && in_array($_GET['order'], ['data_manutencao', 'valor', 'veiculo_placa', 'tipo_nome', 'status_nome', 'fornecedor', 'descricao'], true) ? $_GET['order'] : 'data_manutencao';
$order_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
$opts = ['per_page' => $per_page, 'data_inicio' => $data_inicio ?: null, 'data_fim' => $data_fim ?: null, 'order_by' => $order_by, 'order_dir' => $order_dir];

// Exportar CSV (mesmos filtros)
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="manutencoes_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    $res = getManutencoes($conn, 1, array_merge($opts, ['per_page' => 5000]));
    $rows = $res['manutencoes'];
    if (count($rows)) {
        fputcsv($out, array_keys($rows[0]), ';');
        foreach ($rows as $r) fputcsv($out, $r, ';');
    } else {
        fputcsv($out, ['Data', 'Veículo', 'Tipo', 'Descrição', 'Fornecedor', 'Status', 'Valor'], ';');
    }
    fclose($out);
    exit;
}

$resultado = getManutencoes($conn, $pagina_atual, $opts);
$manutencoes = $resultado['manutencoes'];
$total_paginas = $resultado['total_paginas'];
$base_params = array_filter(['page' => $pagina_atual, 'per_page' => $per_page, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim, 'order' => $order_by, 'dir' => $order_dir], function($v) { return $v !== '' && $v !== null; });
$export_params = $base_params;
$export_params['format'] = 'csv';

// Buscar métricas do dashboard e KPIs
$metricas = getDashboardMetrics($conn);
$kpis = getKPIMetrics($conn);

// Alertas, próximas manutenções, score dos veículos e impacto no lucro
require_once __DIR__ . '/../includes/maintenance_alertas_score.php';
// Garantir variáveis definidas mesmo se o include retornar cedo (ex.: conn/session)
if (!isset($alertas_inteligentes)) $alertas_inteligentes = [];
if (!isset($alertas_proximas)) $alertas_proximas = [];
if (!isset($score_veiculos)) $score_veiculos = [];
if (!isset($impacto_lucro)) $impacto_lucro = null;
if (!isset($previsao_proximo_mes)) $previsao_proximo_mes = null;
// Sincronizar alertas dos planos para o sino de notificações do header
require_once __DIR__ . '/../includes/sync_manutencao_notificacoes.php';
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
    <link rel="stylesheet" href="../css/maintenance.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Estilos responsivos para mobile -->
    <style>
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background: var(--bg-primary);
        }
        
        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        .dashboard-content {
            padding: 20px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
    </style>
    
    <!-- Custom scripts -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/maintenance.js"></script>
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
                        <button id="addMaintenanceBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Manutenção
                        </button>
                        <a href="planos_manutencao.php" class="btn-restore-layout" title="Planos de manutenção">
                            <i class="fas fa-clipboard-list"></i>
                        </a>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <a href="?<?php echo htmlspecialchars(http_build_query($export_params)); ?>" class="btn-toggle-layout" title="Exportar" id="exportBtn">
                                <i class="fas fa-file-export"></i>
                            </a>
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
                            <h3>Manutenções</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $metricas['total_manutencoes']; ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Preventivas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $metricas['total_preventivas']; ?></span>
                                <span class="metric-subtitle">Manutenções preventivas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Corretivas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $metricas['total_corretivas']; ?></span>
                                <span class="metric-subtitle">Manutenções corretivas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Custos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($metricas['total_custos'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- New KPI Cards -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>MTBF</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo number_format($kpis['mtbf'], 1); ?> km</span>
                                <span class="metric-subtitle">Tempo Médio entre Falhas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>MTTR</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo number_format($kpis['mttr'], 1); ?> h</span>
                                <span class="metric-subtitle">Tempo Médio para Reparos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Custo/KM</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($kpis['custo_km'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custo por KM rodado</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($previsao_proximo_mes !== null && $previsao_proximo_mes > 0): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Estimativa próximo mês</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($previsao_proximo_mes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Média dos últimos 6 meses</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Seção Alertas + Score + Impacto em uma linha (Alertas menor, Score e Impacto ao lado) -->
                <div id="secao-alertas-score" class="dashboard-grid alertas-score-row" style="margin-top: 1rem;">
                    <!-- Card Alertas (compacto, altura limitada) -->
                    <div class="dashboard-card alertas-card">
                        <div class="card-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Alertas e Próximas</h3>
                        </div>
                        <div class="card-body alertas-card-body">
                            <?php foreach (array_slice($alertas_inteligentes, 0, 3) as $al): ?>
                            <div class="alerta-item alerta-<?php echo htmlspecialchars($al['nivel']); ?>">
                                <?php if ($al['nivel'] === 'danger'): ?><i class="fas fa-times-circle"></i>
                                <?php else: ?><i class="fas fa-exclamation-circle"></i><?php endif; ?>
                                <?php echo htmlspecialchars($al['mensagem']); ?>
                            </div>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($alertas_proximas, 0, 4) as $p): ?>
                            <div class="alerta-item <?php echo $p['vencido'] ? 'alerta-danger' : 'alerta-warning'; ?>">
                                <?php if ($p['vencido']): ?><i class="fas fa-times-circle"></i>
                                <?php else: ?><i class="fas fa-calendar-alt"></i><?php endif; ?>
                                <strong><?php echo htmlspecialchars($p['placa']); ?></strong> – <?php echo htmlspecialchars($p['componente']); ?>: <?php echo htmlspecialchars($p['msg']); ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($alertas_inteligentes) && empty($alertas_proximas)): ?>
                            <p class="text-muted">Nenhum alerta. Cadastre planos de manutenção para ver próximas e vencidas.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Score Técnico -->
                    <div class="dashboard-card score-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tachometer-alt"></i> Score Técnico</h3>
                            <span class="metric-subtitle">Saudável ≥70 | Atenção 40–69 | Crítico &lt;40</span>
                        </div>
                        <div class="card-body score-card-body">
                            <?php if (!empty($score_veiculos)): ?>
                                <?php foreach (array_slice($score_veiculos, 0, 8) as $s): ?>
                                <span class="score-pill score-<?php echo $s['status']; ?>" title="Custo/km 12m: R$ <?php echo number_format($s['custo_km_12m'], 2, ',', '.'); ?> | Corretivas: <?php echo $s['qtd_corretivas_12m']; ?>">
                                    <?php echo htmlspecialchars($s['placa']); ?>: <strong><?php echo $s['score']; ?></strong>
                                    (<?php echo $s['status'] === 'saudavel' ? 'Saudável' : ($s['status'] === 'atencao' ? 'Atenção' : 'Crítico'); ?>)
                                </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Nenhum veículo com manutenções nos últimos 12 meses.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Impacto no Lucro -->
                    <div class="dashboard-card impacto-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Impacto no Lucro</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($impacto_lucro !== null): ?>
                            <div class="metric">
                                <span class="metric-value"><?php echo (int)$impacto_lucro['impacto_pct']; ?>%</span>
                                <span class="metric-subtitle">Manutenção sobre lucro bruto (12 meses)</span>
                            </div>
                            <?php if (!empty($impacto_lucro['mensagem'])): ?>
                                <p class="text-muted" style="font-size: 0.85rem; margin-top: 0.5rem;"><?php echo htmlspecialchars($impacto_lucro['mensagem']); ?></p>
                            <?php endif; ?>
                            <?php if ($impacto_lucro['total_manutencao_12m'] <= 0 && $impacto_lucro['impacto_pct'] <= 0): ?>
                                <p class="text-muted" style="font-size: 0.85rem;">Sem manutenções ou lucro no período para calcular.</p>
                            <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">Dados de rotas/lucro não disponíveis para calcular o impacto.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMaintenance" placeholder="Buscar manutenção...">
                        <i class="fas fa-search"></i>
                    </div>
                    <form method="get" action="" id="formFiltroPeriodo" class="filter-options">
                        <span class="filter-label">De</span>
                        <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" title="dd/mm/aaaa" class="filter-date">
                        <span class="filter-label">Até</span>
                        <input type="date" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" title="dd/mm/aaaa" class="filter-date">
                        <span class="filter-label">Por página</span>
                        <select name="per_page" class="filter-per-page" onchange="this.form.submit()">
                            <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order_by); ?>">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($order_dir); ?>">
                        <button type="submit" class="btn-restore-layout" title="Filtrar período"><i class="fas fa-filter"></i> Filtrar período</button>
                        
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        <select id="maintenanceTypeFilter">
                            <option value="">Todos os tipos</option>
                            <option value="Preventiva">Preventiva</option>
                            <option value="Corretiva">Corretiva</option>
                        </select>
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Agendada">Agendada</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Concluída">Concluída</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                        <select id="supplierFilter">
                            <option value="">Todos os fornecedores</option>
                        </select>
                        <button type="button" class="btn-restore-layout" id="applyMaintenanceFilters" title="Aplicar filtros"><i class="fas fa-filter"></i></button>
                        <button type="button" class="btn-restore-layout" id="clearMaintenanceFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                    </form>
                </div>
                
                <!-- Maintenance Table -->
                <div class="data-table-container">
                    <table class="data-table" id="maintenanceTable">
                        <thead>
                            <tr>
                                <?php
                                $sort_url = function($col) use ($base_params, $order_by, $order_dir) {
                                    $p = $base_params;
                                    $p['order'] = $col;
                                    $p['dir'] = ($order_by === $col && $order_dir === 'ASC') ? 'DESC' : 'ASC';
                                    $p['page'] = 1;
                                    return '?' . http_build_query($p);
                                };
                                $th = function($col, $label) use ($sort_url, $order_by, $order_dir) {
                                    $url = $sort_url($col);
                                    $arrow = ($order_by === $col) ? ($order_dir === 'ASC' ? ' ↑' : ' ↓') : '';
                                    return '<th><a href="' . htmlspecialchars($url) . '" class="sortable">' . htmlspecialchars($label) . $arrow . '</a></th>';
                                };
                                ?>
                                <?php echo $th('data_manutencao', 'Data'); ?>
                                <?php echo $th('veiculo_placa', 'Veículo'); ?>
                                <?php echo $th('tipo_nome', 'Tipo'); ?>
                                <?php echo $th('descricao', 'Descrição'); ?>
                                <?php echo $th('fornecedor', 'Fornecedor'); ?>
                                <?php echo $th('status_nome', 'Status'); ?>
                                <?php echo $th('valor', 'Valor'); ?>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="maintenanceTableBody">
                            <?php foreach ($manutencoes as $manutencao): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($manutencao['data_manutencao'])); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['veiculo_placa']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['tipo_nome']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['descricao']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['fornecedor']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['status_nome']); ?></td>
                                <td>R$ <?php echo number_format($manutencao['valor'], 2, ',', '.'); ?></td>
                                <td title="Custo do veículo nos últimos 12 meses">R$ <?php echo number_format($manutencao['custo_veiculo_12m'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="actions">
                                    <button class="btn-icon historico-veiculo-btn" data-veiculo-id="<?php echo (int)($manutencao['veiculo_id'] ?? 0); ?>" data-placa="<?php echo htmlspecialchars($manutencao['veiculo_placa'] ?? ''); ?>" title="Histórico do veículo">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <button class="btn-icon view-btn" data-id="<?php echo $manutencao['id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $manutencao['id']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete-btn" data-id="<?php echo $manutencao['id']; ?>" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                $prev_params = $base_params; $prev_params['page'] = $pagina_atual - 1;
                $next_params = $base_params; $next_params['page'] = $pagina_atual + 1;
                $export_params = $base_params; $export_params['format'] = 'csv';
                ?>
                <div class="pagination" id="paginationContainer" data-page="<?php echo $pagina_atual; ?>" data-total-paginas="<?php echo $total_paginas; ?>" data-total="<?php echo (int)($resultado['total'] ?? 0); ?>">
                    <a href="?<?php echo htmlspecialchars(http_build_query($prev_params)); ?>" class="pagination-btn pagination-prev <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" data-page="<?php echo $pagina_atual - 1; ?>" aria-label="Página anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="paginationInfo">
                        <?php if ($total_paginas > 1): ?>Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo (int)($resultado['total'] ?? 0); ?> registros)
                        <?php else: ?><?php echo (int)($resultado['total'] ?? 0); ?> registros<?php endif; ?>
                    </span>
                    <a href="?<?php echo htmlspecialchars(http_build_query($next_params)); ?>" class="pagination-btn pagination-next <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" data-page="<?php echo $pagina_atual + 1; ?>" aria-label="Próxima página">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Analytics Section (oculta até gráficos prontos para evitar tremida) -->
                <div class="analytics-section charts-pending" id="analyticsSection">
                    <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <h2>Análise de Manutenções</h2>
                        <div class="chart-period-selector">
                            <label for="chartPeriod" class="text-muted" style="margin-right: 0.5rem;">Período:</label>
                            <select id="chartPeriod" style="padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid var(--border-color, #ddd); background: var(--bg-primary, #fff);">
                                <option value="3">Últimos 3 meses</option>
                                <option value="6" selected>Últimos 6 meses</option>
                                <option value="12">Últimos 12 meses</option>
                                <option value="ano_atual">Ano atual</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Custos de Manutenção</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceCostsChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Tipos de Manutenção</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceTypesChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- New charts -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Status das Manutenções</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceStatusChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Evolução Mensal de Manutenções</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceEvolutionChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Top 5 Veículos - Custos</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="topVehiclesChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Componentes com Mais Falhas</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="componentsHeatmapChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Frotas. Todos os direitos reservados.</p>
            </footer>
        </div>
    </div>
    
    <!-- Modal de Manutenção -->
    <div class="modal" id="maintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nova Manutenção</h2>
                <button class="close-modal" type="button" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <input type="hidden" id="manutencaoId" name="id">
                    <input type="hidden" id="empresaId" name="empresa_id" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-section">
                        <h3>Informações Básicas</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="data_manutencao">Data da Manutenção*</label>
                                <input type="date" id="data_manutencao" name="data_manutencao" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="veiculo_id">Veículo*</label>
                                <select id="veiculo_id" name="veiculo_id" required>
                                    <option value="">Selecione um veículo</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tipo_manutencao_id">Tipo de Manutenção*</label>
                                <select id="tipo_manutencao_id" name="tipo_manutencao_id" required>
                                    <option value="">Selecione o tipo</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM tipos_manutencao ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($tipo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="componente_id">Componente*</label>
                                <select id="componente_id" name="componente_id" required>
                                    <option value="">Selecione o componente</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM componentes_manutencao ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($componente = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $componente['id'] . "'>" . htmlspecialchars($componente['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="status_manutencao_id">Status*</label>
                                <select id="status_manutencao_id" name="status_manutencao_id" required>
                                    <option value="">Selecione o status</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM status_manutencao ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($status = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $status['id'] . "'>" . htmlspecialchars($status['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group" id="wrap_data_conclusao" style="display:none;">
                                <label for="data_conclusao">Data de Conclusão* <small>(obrigatório quando status é Concluída)</small></label>
                                <input type="date" id="data_conclusao" name="data_conclusao">
                            </div>
                            <div class="form-group full-width" id="wrap_checklist" style="display:none;">
                                <label>Checklist ao concluir (opcional)</label>
                                <div class="checklist-options">
                                    <label class="checklist-item"><input type="checkbox" id="checklist_oleo" name="checklist_oleo" value="1"> Óleo trocado/verificado?</label>
                                    <label class="checklist-item"><input type="checkbox" id="checklist_filtro" name="checklist_filtro" value="1"> Filtro trocado?</label>
                                    <label class="checklist-item"><input type="checkbox" id="checklist_teste" name="checklist_teste" value="1"> Teste realizado?</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="km_atual">Quilometragem Atual*</label>
                                <input type="number" id="km_atual" name="km_atual" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Dados do Serviço</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fornecedor">Fornecedor</label>
                                <input type="text" id="fornecedor" name="fornecedor" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label for="valor">Valor*</label>
                                <input type="number" id="valor" name="valor" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="custo_total">Custo Total</label>
                                <input type="number" id="custo_total" name="custo_total" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="nota_fiscal">Nota Fiscal</label>
                                <input type="text" id="nota_fiscal" name="nota_fiscal" maxlength="255">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Detalhes do Serviço</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="descricao">Descrição*</label>
                                <textarea id="descricao" name="descricao" rows="3" required></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="descricao_servico">Descrição do Serviço*</label>
                                <textarea id="descricao_servico" name="descricao_servico" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="observacoes">Observações</label>
                                <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="responsavel_aprovacao">Responsável pela Aprovação*</label>
                                <input type="text" id="responsavel_aprovacao" name="responsavel_aprovacao" required maxlength="255">
                            </div>
                        </div>
                    </div>
                </form>
                <div id="anexosSection" class="form-section" style="display: none; margin-top: 1rem;">
                    <h3>Anexos (NF / foto)</h3>
                    <div id="anexosList" class="anexos-list" style="margin-bottom: 0.75rem;"></div>
                    <div class="anexos-upload">
                        <input type="file" id="anexoFile" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx" style="margin-right: 0.5rem;">
                        <button type="button" class="btn-secondary" id="uploadAnexoBtn"><i class="fas fa-upload"></i> Enviar</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelMaintenanceBtn">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <button type="button" class="btn-primary" id="saveMaintenanceBtn">
                    <i class="fas fa-save"></i>
                    <span>Salvar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal" id="deleteMaintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <button class="close-modal" type="button" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Tem certeza que deseja excluir esta manutenção?</p>
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

    <!-- Modal Relatório por veículo (histórico + resumo) -->
    <div class="modal" id="historicoVeiculoModal">
        <div class="modal-content relatorio-veiculo-print" style="max-width: 720px;">
            <div class="modal-header">
                <h2 id="historicoVeiculoTitulo">Relatório - Veículo</h2>
                <button type="button" class="close-modal" onclick="document.getElementById('historicoVeiculoModal').classList.remove('active')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div id="historicoVeiculoResumo" class="relatorio-veiculo-resumo" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; padding: 0.75rem; background: var(--bg-secondary, #f5f5f5); border-radius: 8px;">
                    <div><span class="text-muted">Total gasto</span><br><strong id="historicoVeiculoTotal">-</strong></div>
                    <div><span class="text-muted">Custo 12m</span><br><strong id="historicoVeiculoCusto12m">-</strong></div>
                    <div><span class="text-muted">Preventivas</span><br><strong id="historicoVeiculoPreventivas">-</strong></div>
                    <div><span class="text-muted">Corretivas</span><br><strong id="historicoVeiculoCorretivas">-</strong></div>
                </div>
                <p id="historicoVeiculoCusto" class="text-muted"></p>
                <div class="data-table-container" style="max-height: 360px; overflow-y: auto;">
                    <table class="data-table">
                        <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Valor</th></tr></thead>
                        <tbody id="historicoVeiculoBody"></tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn-secondary" onclick="window.print();"><i class="fas fa-print"></i> Imprimir relatório</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ajuda -->
    <div class="modal" id="helpMaintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Manutenções</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Manutenções permite gerenciar todas as manutenções realizadas nos veículos da frota. Aqui você pode cadastrar, editar, visualizar e excluir registros de manutenções, além de acompanhar métricas importantes.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Nova Manutenção:</strong> Cadastre uma nova manutenção com informações detalhadas do serviço e componentes.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar manutenções específicas por veículo, tipo ou status.</li>
                        <li><strong>Exportar:</strong> Exporte os dados das manutenções para análise externa.</li>
                        <li><strong>Relatórios:</strong> Visualize relatórios e estatísticas de manutenções.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Total de Manutenções:</strong> Número total de manutenções no mês atual.</li>
                        <li><strong>Preventivas/Corretivas:</strong> Distribuição entre manutenções preventivas e corretivas.</li>
                        <li><strong>MTBF:</strong> Tempo médio entre falhas (Mean Time Between Failures).</li>
                        <li><strong>MTTR:</strong> Tempo médio para reparo (Mean Time To Repair).</li>
                        <li><strong>Custo por KM:</strong> Valor médio gasto em manutenções por quilômetro rodado.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos da manutenção, incluindo custos e serviços.</li>
                        <li><strong>Editar:</strong> Modifique informações de uma manutenção existente.</li>
                        <li><strong>Excluir:</strong> Remova um registro de manutenção do sistema (ação irreversível).</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Mantenha um registro detalhado dos serviços realizados para histórico.</li>
                        <li>Acompanhe os custos por veículo para identificar tendências.</li>
                        <li>Programe manutenções preventivas baseadas na quilometragem.</li>
                        <li>Monitore os componentes com mais falhas para ações preventivas.</li>
                        <li>Utilize os relatórios para otimizar o planejamento de manutenções.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpMaintenanceModal')">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Initialize page -->
    <script>
        async function loadChartsForPeriod(period) {
            const url = period ? ('../includes/get_maintenance_data.php?period=' + encodeURIComponent(period)) : '../includes/get_maintenance_data.php';
            const response = await fetch(url);
            if (!response.ok) throw new Error('Erro ao buscar dados: ' + response.statusText);
            const data = await response.json();
            if (data.success) initializeMaintenanceCharts(data);
            return data;
        }
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const periodSelect = document.getElementById('chartPeriod');
                const period = periodSelect ? periodSelect.value : '6';
                await loadChartsForPeriod(period);
                if (periodSelect) {
                    periodSelect.addEventListener('change', async function() {
                        const sec = document.getElementById('analyticsSection');
                        if (sec) sec.classList.add('charts-pending');
                        await loadChartsForPeriod(this.value);
                        if (sec) sec.classList.remove('charts-pending');
                    });
                }
            } catch (error) {
                console.error('Erro ao inicializar página:', error);
            }
        });
    </script>
</body>
</html>