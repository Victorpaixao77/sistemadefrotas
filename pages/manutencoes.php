<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once __DIR__ . '/../includes/maintenance_metrics.php';

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

// Layout moderno (fornc-page); ?classic=1 para o layout anterior
$is_modern = !isset($_GET['classic']) || (string)$_GET['classic'] !== '1';

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
        
        $mtbf_mttr = sf_mnt_compute_mtbf_mttr($conn, (int) $empresa_id, '6');
        $custo_km_data = sf_mnt_compute_custo_km($conn, (int) $empresa_id, '12');
        $custo_km = (float) ($custo_km_data['custo_km'] ?? 0);
        
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
            'mtbf' => (float) ($mtbf_mttr['mtbf'] ?? 0),
            'mttr' => (float) ($mtbf_mttr['mttr'] ?? 0),
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

// Parâmetros da URL (permalink + filtros server-side)
$list_opts = sf_mnt_parse_list_opts($_GET);
$pagina_atual = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = (int) $list_opts['per_page'];
$data_inicio = $list_opts['data_inicio'] ? (string) $list_opts['data_inicio'] : '';
$data_fim = $list_opts['data_fim'] ? (string) $list_opts['data_fim'] : '';
$order_by = (string) $list_opts['order_by'];
$order_dir = (string) $list_opts['order_dir'];
$filter_q = (string) $list_opts['q'];
$filter_veiculo_id = $list_opts['veiculo_id'];
$filter_tipo_classe = (string) $list_opts['tipo_classe'];
$filter_status_id = $list_opts['status_id'];
$filter_fornecedor = (string) $list_opts['fornecedor'];
$opts = $list_opts;

// Exportar CSV (mesmos filtros)
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="manutencoes_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    $res = sf_mnt_fetch_list($conn, (int) $_SESSION['empresa_id'], 1, array_merge($opts, ['per_page' => 5000]));
    $rows = $res['manutencoes'];
    if (count($rows)) {
        fputcsv($out, ['Data', 'Veículo', 'Tipo', 'Descrição', 'Fornecedor', 'Status', 'Valor', 'Custo 12m'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['data_manutencao'] ?? '',
                $r['veiculo_placa'] ?? '',
                $r['tipo_nome'] ?? '',
                $r['descricao'] ?? '',
                $r['fornecedor'] ?? '',
                $r['status_nome'] ?? '',
                $r['valor'] ?? '',
                $r['custo_veiculo_12m'] ?? '',
            ], ';');
        }
    } else {
        fputcsv($out, ['Data', 'Veículo', 'Tipo', 'Descrição', 'Fornecedor', 'Status', 'Valor'], ';');
    }
    fclose($out);
    exit;
}

$resultado = sf_mnt_fetch_list($conn, (int) $_SESSION['empresa_id'], $pagina_atual, $opts);
$manutencoes = $resultado['manutencoes'];
$total_paginas = $resultado['total_paginas'];
$mnt_filter_opts = sf_mnt_filter_options($conn, (int) $_SESSION['empresa_id']);
$base_params = array_filter([
    'page' => $pagina_atual,
    'per_page' => $per_page,
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim,
    'order' => $order_by,
    'dir' => $order_dir,
    'q' => $filter_q,
    'veiculo_id' => $filter_veiculo_id,
    'tipo_classe' => $filter_tipo_classe,
    'status_id' => $filter_status_id,
    'fornecedor' => $filter_fornecedor,
], static function ($v) { return $v !== '' && $v !== null; });
if (!$is_modern) {
    $base_params['classic'] = '1';
}
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

// Ordena o Score Técnico pior-primeiro (crítico → atenção → saudável; menor score antes).
$__score_ordem = ['critico' => 0, 'atencao' => 1, 'saudavel' => 2];
usort($score_veiculos, function ($a, $b) use ($__score_ordem) {
    $oa = $__score_ordem[$a['status'] ?? ''] ?? 3;
    $ob = $__score_ordem[$b['status'] ?? ''] ?? 3;
    if ($oa !== $ob) return $oa - $ob;
    return ((int) ($a['score'] ?? 0)) - ((int) ($b['score'] ?? 0));
});
// Contagens por status (para o cabeçalho).
$__score_cont = ['critico' => 0, 'atencao' => 0, 'saudavel' => 0];
foreach ($score_veiculos as $__s) {
    $__st = $__s['status'] ?? '';
    if (isset($__score_cont[$__st])) $__score_cont[$__st]++;
}
$__alertas_total = count($alertas_inteligentes) + count($alertas_proximas);
$__alertas_vencidos = 0;
foreach ($alertas_proximas as $__p) { if (!empty($__p['vencido'])) $__alertas_vencidos++; }
foreach ($alertas_inteligentes as $__a) { if (($__a['nivel'] ?? '') === 'danger') $__alertas_vencidos++; }

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
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <style>
        body.manutencoes-modern .dashboard-content.fornc-page { padding-top: 8px; overflow-x: auto; }
        body.manutencoes-modern .dashboard-header { display: none; }
        body.manutencoes-modern .fornc-page .mann-table-wrap { max-height: min(70vh, 560px); }
        body.manutencoes-modern .fornc-page .mann-table-wrap .fornc-table thead th {
            position: sticky; top: 0; z-index: 2;
            background: var(--forn-table-head);
            box-shadow: 0 1px 0 var(--border-color);
        }
        body.manutencoes-modern .fornc-page #maintenanceTable.fornc-table { min-width: 960px; table-layout: auto; }
        body.manutencoes-modern #maintenanceTable .mann-sort-link {
            color: inherit;
            text-decoration: none;
            font-weight: inherit;
        }
        body.manutencoes-modern #maintenanceTable th.sortable .sort-ind {
            opacity: 0.45;
            margin-left: 0.15rem;
            font-size: 0.65rem;
        }
        body.manutencoes-modern #maintenanceTable th.sortable.sorted .sort-ind {
            opacity: 1;
            color: var(--accent-primary, #15803d);
        }
        body.manutencoes-modern .mann-finance-analytics {
            margin-top: 0.75rem;
        }
        body.manutencoes-modern .mann-finance-analytics .section-header h2 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }
        body.manutencoes-modern .mann-finance-analytics .analytics-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        body.manutencoes-modern .mann-finance-analytics .analytics-card .card-header {
            padding: 0.5rem 0.65rem;
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
        }
        body.manutencoes-modern .mann-finance-analytics .analytics-card .card-header h3 {
            font-size: 0.78rem;
            font-weight: 600;
            margin: 0;
        }
        body.manutencoes-modern .mann-finance-analytics .analytics-card .card-body {
            padding: 0.5rem;
        }
        body.manutencoes-modern #secao-alertas-score.mnt-panels-stack {
            margin-top: 0.65rem;
            margin-bottom: 1.25rem;
        }
        body.manutencoes-modern .mann-toolbar-main { margin-top: 0.75rem; }
        body.manutencoes-modern .mann-analytics-modern .analytics-card:nth-child(1),
        body.manutencoes-modern .mann-analytics-modern .analytics-card:nth-child(5) {
            border: 1px solid rgba(16, 185, 129, 0.35);
            box-shadow: 0 1px 8px rgba(16, 185, 129, 0.12);
        }
    </style>
    <?php endif; ?>
    
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

        /* Modal: Descrição, Descrição do serviço, Observações e Responsável em duas colunas */
        #maintenanceModal .mann-detalhes-servico-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
            align-items: start;
        }
        @media (max-width: 640px) {
            #maintenanceModal .mann-detalhes-servico-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Modal: Campos financeiros (Fornecedor, Valor, Custo Total, Nota Fiscal) em duas colunas */
        #maintenanceModal .mann-finance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
            align-items: start;
        }
        @media (max-width: 640px) {
            #maintenanceModal .mann-finance-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Painéis colapsáveis (alertas, score, ranking) — lado a lado */
        .mnt-panels-stack {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
            align-items: start;
        }
        .mnt-panels-stack .mnt-insights { margin-bottom: 0; min-width: 0; }
        .mnt-panels-stack .mnt-toggle { font-size: 0.85rem; align-items: flex-start; }
        .mnt-panels-stack .mnt-summary { flex: 1; min-width: 0; line-height: 1.35; }
        .mnt-panels-stack .mnt-summary .mnt-pill { margin-top: 2px; }
        @media (max-width: 768px) {
            .mnt-panels-stack { grid-template-columns: 1fr; }
        }
        .mnt-insights { border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 1rem; overflow: hidden; background: var(--card-bg, var(--bg-secondary)); }
        .mnt-insights.has-erros { border-color: #dc2626; }
        .mnt-insights.has-avisos { border-color: #f59e0b; }
        .mnt-head { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.6rem 0.5rem 0.85rem; }
        .mnt-toggle { flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-primary); font-weight: 600; font-size: 0.9rem; text-align: left; padding: 0.2rem 0; }
        .mnt-caret { transition: transform 0.2s ease; color: var(--text-muted); }
        .mnt-toggle[aria-expanded="true"] .mnt-caret { transform: rotate(180deg); }
        .mnt-summary .mnt-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; margin-left: 6px; }
        .mnt-pill.erro { background: rgba(220,38,38,0.12); color: #dc2626; }
        .mnt-pill.aviso { background: rgba(245,158,11,0.14); color: #b45309; }
        .mnt-pill.ok { background: rgba(22,163,74,0.12); color: #16a34a; }
        .mnt-body { padding: 0.25rem 0.85rem 0.7rem; max-height: 420px; overflow: auto; }
        .mnt-section { padding: 0.5rem 0; border-top: 1px solid var(--border-color); }
        .mnt-section:first-child { border-top: none; }
        .mnt-section h4 { margin: 0 0 0.4rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; color: var(--text-primary); }
        .mnt-section h4 i { color: var(--accent-primary, #3b82f6); }
        .mnt-item { display: flex; gap: 0.55rem; padding: 0.4rem 0; border-top: 1px dashed var(--border-color); font-size: 0.85rem; }
        .mnt-item:first-child { border-top: none; }
        .mnt-item .mnt-sev { flex: 0 0 auto; }
        .mnt-item .mnt-sev.erro { color: #dc2626; }
        .mnt-item .mnt-sev.aviso { color: #f59e0b; }
        .mnt-item .mnt-txt { flex: 1; min-width: 0; }
        .mnt-item .mnt-txt strong { color: var(--text-primary); }
        .mnt-item .mnt-edit { flex: 0 0 auto; }
        .mnt-empty { font-size: 0.83rem; color: var(--text-muted); padding: 0.2rem 0; }
        .mnt-custo-row { display: flex; justify-content: space-between; gap: 0.5rem; padding: 0.3rem 0; border-top: 1px dashed var(--border-color); font-size: 0.85rem; }
        .mnt-custo-row:first-child { border-top: none; }

        /* Resumo IA */
        .mnt-resumo-ia { border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 1rem; padding: 0.75rem 0.85rem; background: var(--card-bg, var(--bg-secondary)); }
        .mnt-resumo-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.4rem; }
        .mnt-resumo-head h4 { margin: 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem; }
        .mnt-resumo-head h4 i { color: var(--accent-primary, #3b82f6); }
        .mnt-resumo-texto { font-size: 0.9rem; line-height: 1.5; color: var(--text-primary); }
        .mnt-resumo-texto.text-muted { color: var(--text-muted); }

        /* Cabeçalho com badge de contagem (Alertas) */
        .mann-count-badge { margin-left: auto; min-width: 1.4rem; height: 1.4rem; padding: 0 0.4rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.78rem; font-weight: 700; color: #fff; }
        .mann-count-badge.is-danger { background: #dc2626; }
        .mann-count-badge.is-warning { background: #f59e0b; }

        /* Score Técnico — legenda + barras */
        .score-legend { margin-left: auto; display: flex; gap: 0.3rem; flex-wrap: wrap; }
        .score-chip { padding: 1px 7px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; }
        .score-chip.score-critico { background: rgba(220,38,38,0.14); color: #dc2626; }
        .score-chip.score-atencao { background: rgba(245,158,11,0.16); color: #b45309; }
        .score-chip.score-saudavel { background: rgba(22,163,74,0.14); color: #16a34a; }
        .score-card-body { display: flex; flex-direction: column; gap: 0.35rem; }
        .score-row { display: flex; align-items: center; gap: 0.5rem; }
        .score-row-placa { flex: 0 0 5.5rem; font-size: 0.82rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .score-bar { flex: 1; height: 8px; border-radius: 999px; background: var(--bg-tertiary, rgba(0,0,0,0.08)); overflow: hidden; }
        .score-bar-fill { display: block; height: 100%; border-radius: 999px; transition: width 0.4s ease; }
        .score-row.score-critico .score-bar-fill { background: #dc2626; }
        .score-row.score-atencao .score-bar-fill { background: #f59e0b; }
        .score-row.score-saudavel .score-bar-fill { background: #16a34a; }
        .score-row-val { flex: 0 0 2rem; text-align: right; font-size: 0.85rem; font-weight: 700; color: var(--text-primary); }
        .score-row.score-critico .score-row-val { color: #dc2626; }
        .score-help { margin: 0.35rem 0 0; font-size: 0.72rem; color: var(--text-muted); }

        /* Itens de alerta com leve realce por nível */
        .alertas-card-body .alerta-item { display: flex; align-items: flex-start; gap: 0.4rem; padding: 0.35rem 0.5rem; border-radius: 6px; margin-bottom: 0.3rem; font-size: 0.84rem; }
        .alertas-card-body .alerta-item i { margin-top: 0.15rem; }
        .alertas-card-body .alerta-danger { background: rgba(220,38,38,0.08); border-left: 3px solid #dc2626; }
        .alertas-card-body .alerta-warning { background: rgba(245,158,11,0.10); border-left: 3px solid #f59e0b; }
        .alertas-card-body .alerta-danger i { color: #dc2626; }
        .alertas-card-body .alerta-warning i { color: #f59e0b; }
        .alertas-card-body .alerta-clickable { cursor: pointer; transition: background 0.15s ease; }
        .alertas-card-body .alerta-clickable:hover { filter: brightness(0.97); }
        .alertas-card-body .alerta-clickable .alerta-action { display: block; font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem; }
        .score-row.score-clickable { cursor: pointer; border-radius: 6px; padding: 0.15rem 0.25rem; }
        .score-row.score-clickable:hover { background: var(--bg-tertiary, rgba(0,0,0,0.04)); }
        .mann-ranking-card .mann-ranking-table { width: 100%; font-size: 0.82rem; border-collapse: collapse; }
        .mann-ranking-card .mann-ranking-table th, .mann-ranking-card .mann-ranking-table td { padding: 0.35rem 0.4rem; border-bottom: 1px solid var(--border-color); text-align: left; }
        .mann-ranking-card .mann-ranking-table tr.mann-ranking-row { cursor: pointer; }
        .mann-ranking-card .mann-ranking-table tr.mann-ranking-row:hover { background: var(--bg-tertiary); }
    </style>
    
    <!-- Custom scripts -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/maintenance.js?v=1.6"></script>
    <?php sf_page_help_css('..'); ?>
</head>
<body<?php echo $is_modern ? ' class="manutencoes-modern"' : ''; ?>>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-screwdriver-wrench fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Manutenções (mês)</span></div><span class="val"><?php echo (int)$metricas['total_manutencoes']; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-calendar-check fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Preventivas</span></div><span class="val"><?php echo (int)$metricas['total_preventivas']; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-wrench fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Corretivas</span></div><span class="val"><?php echo (int)$metricas['total_corretivas']; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-coins fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Custos (mês)</span></div><span class="val">R$ <?php echo number_format((float)$metricas['total_custos'], 2, ',', '.'); ?></span></div>
                </div>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-road fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">MTBF</span></div><span class="val" id="mannKpiMtbf"><?php echo number_format((float)$kpis['mtbf'], 1, ',', '.'); ?> km</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-stopwatch fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">MTTR</span></div><span class="val" id="mannKpiMttr"><?php echo number_format((float)$kpis['mttr'], 1, ',', '.'); ?> h</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-ruler-combined fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Custo / KM</span></div><span class="val" id="mannKpiCustoKm">R$ <?php echo number_format((float)$kpis['custo_km'], 2, ',', '.'); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-calendar-days fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Estimativa próx. mês</span></div><span class="val"><?php echo ($previsao_proximo_mes !== null && $previsao_proximo_mes > 0) ? ('R$ ' . number_format((float)$previsao_proximo_mes, 2, ',', '.')) : '—'; ?></span></div>
                </div>
                <p class="fornc-kpi-summary text-muted" id="mannKpiPeriodHint">MTBF, MTTR e Custo/KM conforme período dos gráficos (<?php echo htmlspecialchars(sf_mnt_period_label('6')); ?>).</p>
                <?php if ($impacto_lucro !== null): ?>
                <p class="fornc-kpi-summary" id="mannKpiImpactoResumo">
                    <strong>Impacto no lucro (12m):</strong> <?php echo (int)($impacto_lucro['impacto_pct'] ?? 0); ?>% — manutenção sobre lucro bruto.
                    <?php if (!empty($impacto_lucro['mensagem'])): ?><span class="text-muted"> <?php echo htmlspecialchars($impacto_lucro['mensagem']); ?></span><?php endif; ?>
                </p>
                <?php else: ?>
                <p class="fornc-kpi-summary text-muted">Impacto no lucro: dados de rotas/lucro indisponíveis para o cálculo.</p>
                <?php endif; ?>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addMaintenanceBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Manutenção
                        </button>
                        <button type="button" id="mntImportBtn" class="btn-restore-layout" title="Importar CSV">
                            <i class="fas fa-file-import"></i>
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
                            <i class="fas fa-screwdriver-wrench kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-calendar-check kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-hammer kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-sack-dollar kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-gauge-high kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-stopwatch kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-road kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-chart-column kpi-card-icon" aria-hidden="true"></i>
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
                <?php endif; ?>

                <?php
                $__alertas_panel_class = '';
                if ($__alertas_vencidos > 0) $__alertas_panel_class = 'has-erros';
                elseif ($__alertas_total > 0) $__alertas_panel_class = 'has-avisos';
                $__score_panel_class = '';
                if ($__score_cont['critico'] > 0) $__score_panel_class = 'has-erros';
                elseif ($__score_cont['atencao'] > 0) $__score_panel_class = 'has-avisos';
                $__worst_score = !empty($score_veiculos) ? $score_veiculos[0] : null;
                $__ranking_panel_class = '';
                if ($__worst_score) {
                    if (($__worst_score['status'] ?? '') === 'critico') $__ranking_panel_class = 'has-erros';
                    elseif (($__worst_score['status'] ?? '') === 'atencao') $__ranking_panel_class = 'has-avisos';
                }
                ?>
                <!-- Alertas, Score e Ranking — colapsáveis (minimizados por padrão) -->
                <div id="secao-alertas-score" class="mnt-panels-stack"<?php echo $is_modern ? '' : ' style="margin-top: 1rem;"'; ?>>
                    <div class="mnt-insights alertas-card <?php echo $__alertas_panel_class; ?>" id="mntAlertasPanel">
                        <div class="mnt-head">
                            <button type="button" class="mnt-toggle" id="mntAlertasToggle" aria-expanded="false" aria-controls="mntAlertasBody">
                                <span class="mnt-summary">
                                    <i class="fas fa-triangle-exclamation" style="color:<?php echo $__alertas_vencidos > 0 ? '#dc2626' : ($__alertas_total > 0 ? '#f59e0b' : '#16a34a'); ?>"></i>
                                    Alertas e Próximas
                                    <?php if ($__alertas_total > 0): ?>
                                    <span class="mnt-pill <?php echo $__alertas_vencidos > 0 ? 'erro' : 'aviso'; ?>"><?php echo (int)$__alertas_total; ?> alerta(s)</span>
                                    <?php if ($__alertas_vencidos > 0): ?>
                                    <span class="mnt-pill erro"><?php echo (int)$__alertas_vencidos; ?> vencido(s)</span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="mnt-pill ok">tudo ok</span>
                                    <?php endif; ?>
                                </span>
                                <i class="fas fa-chevron-down mnt-caret" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="mnt-body alertas-card-body" id="mntAlertasBody" style="display:none;">
                            <?php foreach (array_slice($alertas_inteligentes, 0, 3) as $al): ?>
                            <div class="alerta-item alerta-clickable alerta-<?php echo htmlspecialchars($al['nivel']); ?>"
                                 role="button" tabindex="0"
                                 data-mnt-alert-intel="1"
                                 data-veiculo-id="<?php echo (int)($al['veiculo_id'] ?? 0); ?>"
                                 data-componente-id="<?php echo (int)($al['componente_id'] ?? 0); ?>"
                                 data-placa="<?php echo htmlspecialchars($al['placa'] ?? ''); ?>"
                                 data-alert-tipo="<?php echo htmlspecialchars($al['tipo'] ?? ''); ?>"
                                 title="Clique para ver histórico ou registrar manutenção">
                                <?php if ($al['nivel'] === 'danger'): ?><i class="fas fa-times-circle"></i>
                                <?php else: ?><i class="fas fa-exclamation-circle"></i><?php endif; ?>
                                <?php echo htmlspecialchars($al['mensagem']); ?>
                                <span class="alerta-action">Clique para abrir histórico do veículo</span>
                            </div>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($alertas_proximas, 0, 4) as $p): ?>
                            <div class="alerta-item alerta-clickable <?php echo $p['vencido'] ? 'alerta-danger' : 'alerta-warning'; ?>"
                                 role="button" tabindex="0"
                                 data-mnt-alert-plano="1"
                                 data-veiculo-id="<?php echo (int)($p['veiculo_id'] ?? 0); ?>"
                                 data-componente-id="<?php echo (int)($p['componente_id'] ?? 0); ?>"
                                 data-tipo-id="<?php echo (int)($p['tipo_manutencao_id'] ?? 0); ?>"
                                 data-km="<?php echo (int)($p['km_atual'] ?? 0); ?>"
                                 data-placa="<?php echo htmlspecialchars($p['placa'] ?? ''); ?>"
                                 data-componente="<?php echo htmlspecialchars($p['componente'] ?? ''); ?>"
                                 data-tipo="<?php echo htmlspecialchars($p['tipo'] ?? ''); ?>"
                                 title="Clique para registrar manutenção a partir do plano">
                                <?php if ($p['vencido']): ?><i class="fas fa-times-circle"></i>
                                <?php else: ?><i class="fas fa-calendar-alt"></i><?php endif; ?>
                                <strong><?php echo htmlspecialchars($p['placa']); ?></strong> – <?php echo htmlspecialchars($p['componente']); ?>: <?php echo htmlspecialchars($p['msg']); ?>
                                <span class="alerta-action">Clique para abrir nova manutenção</span>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($alertas_inteligentes) && empty($alertas_proximas)): ?>
                            <p class="text-muted mnt-empty">Nenhum alerta. <a href="planos_manutencao.php">Cadastre planos de manutenção</a> para ver próximas e vencidas.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mnt-insights score-card <?php echo $__score_panel_class; ?>" id="mntScorePanel">
                        <div class="mnt-head">
                            <button type="button" class="mnt-toggle" id="mntScoreToggle" aria-expanded="false" aria-controls="mntScoreBody">
                                <span class="mnt-summary">
                                    <i class="fas fa-gauge-high" style="color:<?php echo $__score_cont['critico'] > 0 ? '#dc2626' : ($__score_cont['atencao'] > 0 ? '#f59e0b' : '#16a34a'); ?>"></i>
                                    Score Técnico
                                    <?php if ($__score_cont['critico']): ?><span class="mnt-pill erro"><?php echo (int)$__score_cont['critico']; ?> crítico</span><?php endif; ?>
                                    <?php if ($__score_cont['atencao']): ?><span class="mnt-pill aviso"><?php echo (int)$__score_cont['atencao']; ?> atenção</span><?php endif; ?>
                                    <?php if ($__score_cont['saudavel']): ?><span class="mnt-pill ok"><?php echo (int)$__score_cont['saudavel']; ?> ok</span><?php endif; ?>
                                    <?php if (empty($score_veiculos)): ?><span class="mnt-pill ok">sem dados</span><?php endif; ?>
                                </span>
                                <i class="fas fa-chevron-down mnt-caret" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="mnt-body score-card-body" id="mntScoreBody" style="display:none;">
                            <?php if (!empty($score_veiculos)): ?>
                                <?php foreach (array_slice($score_veiculos, 0, 8) as $s): $sv = max(0, min(100, (int) $s['score'])); ?>
                                <div class="score-row score-clickable score-<?php echo htmlspecialchars($s['status']); ?>"
                                     data-veiculo-id="<?php echo (int)($s['veiculo_id'] ?? 0); ?>"
                                     data-placa="<?php echo htmlspecialchars($s['placa']); ?>"
                                     title="Clique para ver histórico · Custo/km 12m: R$ <?php echo number_format($s['custo_km_12m'], 2, ',', '.'); ?> · Corretivas 12m: <?php echo (int) $s['qtd_corretivas_12m']; ?>">
                                    <span class="score-row-placa"><?php echo htmlspecialchars($s['placa']); ?></span>
                                    <span class="score-bar"><span class="score-bar-fill" style="width: <?php echo $sv; ?>%"></span></span>
                                    <span class="score-row-val"><?php echo (int) $s['score']; ?></span>
                                </div>
                                <?php endforeach; ?>
                                <p class="score-help">Saudável ≥70 · Atenção 40–69 · Crítico &lt;40</p>
                            <?php else: ?>
                                <p class="text-muted mnt-empty">Nenhum veículo com manutenções nos últimos 12 meses.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($score_veiculos)): ?>
                    <div class="mnt-insights mann-ranking-card <?php echo $__ranking_panel_class; ?>" id="mntRankingPanel">
                        <div class="mnt-head">
                            <button type="button" class="mnt-toggle" id="mntRankingToggle" aria-expanded="false" aria-controls="mntRankingBody">
                                <span class="mnt-summary">
                                    <i class="fas fa-ranking-star" style="color:<?php echo ($__worst_score['status'] ?? '') === 'critico' ? '#dc2626' : (($__worst_score['status'] ?? '') === 'atencao' ? '#f59e0b' : '#16a34a'); ?>"></i>
                                    Veículos que mais demandam atenção
                                    <span class="mnt-pill <?php echo ($__worst_score['status'] ?? '') === 'critico' ? 'erro' : (($__worst_score['status'] ?? '') === 'atencao' ? 'aviso' : 'ok'); ?>">
                                        pior: <?php echo htmlspecialchars($__worst_score['placa']); ?> (<?php echo (int)$__worst_score['score']; ?>)
                                    </span>
                                    <span class="mnt-pill aviso"><?php echo min(5, count($score_veiculos)); ?> veículo(s)</span>
                                </span>
                                <i class="fas fa-chevron-down mnt-caret" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="mnt-body" id="mntRankingBody" style="display:none;">
                            <table class="mann-ranking-table">
                                <thead>
                                    <tr><th>Placa</th><th>Score</th><th>Corretivas 12m</th><th>Custo/km</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($score_veiculos, 0, 5) as $rk): ?>
                                    <tr class="mann-ranking-row" data-veiculo-id="<?php echo (int)($rk['veiculo_id'] ?? 0); ?>" data-placa="<?php echo htmlspecialchars($rk['placa']); ?>">
                                        <td><strong><?php echo htmlspecialchars($rk['placa']); ?></strong></td>
                                        <td><span class="score-chip score-<?php echo htmlspecialchars($rk['status']); ?>"><?php echo (int)$rk['score']; ?></span></td>
                                        <td><?php echo (int)$rk['qtd_corretivas_12m']; ?></td>
                                        <td>R$ <?php echo number_format((float)$rk['custo_km_12m'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="score-help" style="margin-top:0.5rem;">Clique em uma linha para ver o histórico do veículo.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$is_modern): ?>
                <!-- Card Impacto no Lucro (no layout moderno o resumo vai na faixa KPI) -->
                <div class="dashboard-card impacto-card" style="margin-bottom: 1rem;">
                    <div class="card-header">
                        <i class="fas fa-chart-line kpi-card-icon" aria-hidden="true"></i>
                        <h3>Impacto no Lucro</h3>
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
                <?php endif; ?>
                
                <?php if ($is_modern): ?>
                <div class="fornc-toolbar mann-toolbar-main">
                    <form method="get" action="" id="formFiltroPeriodo" class="fornc-filters-inline">
                        <div class="fornc-search-block">
                            <label for="searchMaintenance">Busca</label>
                            <div class="fornc-search-inner">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="text" id="searchMaintenance" name="q" value="<?php echo htmlspecialchars($filter_q); ?>" placeholder="Placa, descrição, fornecedor..." autocomplete="off">
                            </div>
                        </div>
                        <div class="fg">
                            <label for="mannDataInicio">De</label>
                            <input type="date" id="mannDataInicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" title="Data inicial" class="filter-date">
                        </div>
                        <div class="fg">
                            <label for="mannDataFim">Até</label>
                            <input type="date" id="mannDataFim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" title="Data final" class="filter-date">
                        </div>
                        <div class="fg">
                            <label for="mannPerPage">Por página</label>
                            <select id="mannPerPage" name="per_page" class="filter-per-page" title="Registros por página" onchange="this.form.submit()">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order_by); ?>">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($order_dir); ?>">
                        <div class="fg">
                            <label for="vehicleFilter">Veículo</label>
                            <select id="vehicleFilter" name="veiculo_id">
                                <option value="">Todos</option>
                                <?php foreach ($mnt_filter_opts['veiculos'] as $fv): ?>
                                <option value="<?php echo (int) $fv['id']; ?>"<?php echo ($filter_veiculo_id === (int) $fv['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($fv['placa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="maintenanceTypeFilter">Tipo</label>
                            <select id="maintenanceTypeFilter" name="tipo_classe">
                                <option value="">Todos</option>
                                <option value="preventiva"<?php echo $filter_tipo_classe === 'preventiva' ? ' selected' : ''; ?>>Preventiva</option>
                                <option value="corretiva"<?php echo $filter_tipo_classe === 'corretiva' ? ' selected' : ''; ?>>Corretiva</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter" name="status_id">
                                <option value="">Todos</option>
                                <?php foreach ($mnt_filter_opts['status'] as $fs): ?>
                                <option value="<?php echo (int) $fs['id']; ?>"<?php echo ($filter_status_id === (int) $fs['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($fs['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="supplierFilter">Fornecedor</label>
                            <select id="supplierFilter" name="fornecedor">
                                <option value="">Todos</option>
                                <?php foreach ($mnt_filter_opts['fornecedores'] as $ff): ?>
                                <option value="<?php echo htmlspecialchars($ff); ?>"<?php echo $filter_fornecedor === $ff ? ' selected' : ''; ?>><?php echo htmlspecialchars($ff); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="fornc-btn fornc-btn--accent" title="Aplicar filtros"><i class="fas fa-filter"></i> Filtrar</button>
                    </form>
                    <div class="fornc-btn-row">
                        <button type="button" id="addMaintenanceBtn" class="fornc-btn fornc-btn--primary"><i class="fas fa-plus"></i> Nova</button>
                        <button type="button" id="mntImportBtn" class="fornc-btn fornc-btn--ghost" title="Importar CSV da oficina"><i class="fas fa-file-import"></i> Importar</button>
                        <a href="planos_manutencao.php" class="fornc-btn fornc-btn--ghost" title="Planos de manutenção"><i class="fas fa-clipboard-list"></i> Planos</a>
                        <a href="../calendario/index.php" class="fornc-btn fornc-btn--ghost" title="Calendário de manutenções"><i class="fas fa-calendar-alt"></i> Calendário</a>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearMaintenanceFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <a href="?<?php echo htmlspecialchars(http_build_query($export_params)); ?>" class="fornc-btn fornc-btn--muted" title="Exportar CSV" id="exportBtn"><i class="fas fa-file-export"></i> Exportar</a>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <!-- Search and Filter -->
                <div class="filter-section">
                    <form method="get" action="" id="formFiltroPeriodo" class="filter-options">
                    <div class="search-box">
                        <input type="text" id="searchMaintenance" name="q" value="<?php echo htmlspecialchars($filter_q); ?>" placeholder="Buscar manutenção...">
                        <i class="fas fa-search"></i>
                    </div>
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
                        <input type="hidden" name="classic" value="1">
                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order_by); ?>">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($order_dir); ?>">
                        <button type="submit" class="btn-restore-layout" title="Filtrar período"><i class="fas fa-filter"></i> Filtrar período</button>
                        
                        <select id="vehicleFilter" name="veiculo_id">
                            <option value="">Todos os veículos</option>
                            <?php foreach ($mnt_filter_opts['veiculos'] as $fv): ?>
                            <option value="<?php echo (int) $fv['id']; ?>"<?php echo ($filter_veiculo_id === (int) $fv['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($fv['placa']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="maintenanceTypeFilter" name="tipo_classe">
                            <option value="">Todos os tipos</option>
                            <option value="preventiva"<?php echo $filter_tipo_classe === 'preventiva' ? ' selected' : ''; ?>>Preventiva</option>
                            <option value="corretiva"<?php echo $filter_tipo_classe === 'corretiva' ? ' selected' : ''; ?>>Corretiva</option>
                        </select>
                        <select id="statusFilter" name="status_id">
                            <option value="">Todos os status</option>
                            <?php foreach ($mnt_filter_opts['status'] as $fs): ?>
                            <option value="<?php echo (int) $fs['id']; ?>"<?php echo ($filter_status_id === (int) $fs['id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($fs['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="supplierFilter" name="fornecedor">
                            <option value="">Todos os fornecedores</option>
                            <?php foreach ($mnt_filter_opts['fornecedores'] as $ff): ?>
                            <option value="<?php echo htmlspecialchars($ff); ?>"<?php echo $filter_fornecedor === $ff ? ' selected' : ''; ?>><?php echo htmlspecialchars($ff); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-restore-layout" title="Aplicar filtros"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="planos_manutencao.php" class="btn-restore-layout" title="Planos"><i class="fas fa-clipboard-list"></i></a>
                        <a href="../calendario/index.php" class="btn-restore-layout" title="Calendário"><i class="fas fa-calendar-alt"></i></a>
                        <button type="button" class="btn-restore-layout" id="clearMaintenanceFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                    </form>
                </div>
                <?php endif; ?>
                <?php
                $mann_modern_empty = !empty($is_modern) && empty($manutencoes);
                ?>
                <?php if (!empty($is_modern)): ?>
                <div class="fornc-empty-state<?php echo $mann_modern_empty ? ' visible' : ''; ?>" id="mannEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhuma manutenção encontrada</p>
                    <p class="fornc-empty-state-hint">Ajuste o período na URL, use os filtros da lista ou cadastre uma nova manutenção.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="mannEmptyCreateBtn"><i class="fas fa-plus"></i> Registrar primeira manutenção</button>
                </div>
                <?php endif; ?>
                
                <!-- Insights de manutenção: preventiva + anomalias + custo -->
                <div class="mnt-insights" id="mntInsights" style="display:none;">
                    <div class="mnt-head">
                        <button type="button" class="mnt-toggle" id="mntToggle" aria-expanded="false" aria-controls="mntBody">
                            <span class="mnt-summary" id="mntSummary"></span>
                            <i class="fas fa-chevron-down mnt-caret" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="mntRefresh" title="Reanalisar" aria-label="Reanalisar manutenções"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="mnt-body" id="mntBody" style="display:none;">
                        <div class="mnt-section">
                            <h4><i class="fas fa-calendar-check"></i> Manutenção preventiva</h4>
                            <div id="mntPrevList"></div>
                        </div>
                        <div class="mnt-section">
                            <h4><i class="fas fa-triangle-exclamation"></i> Anomalias</h4>
                            <div id="mntAnomList"></div>
                        </div>
                        <div class="mnt-section">
                            <h4><i class="fas fa-coins"></i> Maior custo de manutenção (R$/km)</h4>
                            <div id="mntCustoList"></div>
                        </div>
                    </div>
                </div>

                <!-- Resumo executivo por IA -->
                <div class="mnt-resumo-ia" id="mntResumoIa" style="display:none;">
                    <div class="mnt-resumo-head">
                        <h4><i class="fas fa-wand-magic-sparkles"></i> Resumo executivo (IA)</h4>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="mntResumoBtn"><i class="fas fa-bolt"></i> Gerar resumo</button>
                    </div>
                    <div id="mntResumoTexto" class="mnt-resumo-texto text-muted">Clique em “Gerar resumo” para uma análise das manutenções pela IA.</div>
                </div>

                <!-- Maintenance Table -->
                <div class="<?php echo $is_modern ? 'mann-table-wrap fornc-table-wrap' : 'data-table-container'; ?>" id="mannTableWrap"<?php echo $mann_modern_empty ? ' style="display:none"' : ''; ?>>
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="maintenanceTable">
                        <thead>
                            <tr>
                                <?php
                                $manut_text_cols = ['veiculo_placa', 'tipo_nome', 'descricao', 'fornecedor', 'status_nome'];
                                $sort_url = function ($col) use ($base_params, $order_by, $order_dir, $manut_text_cols) {
                                    $p = $base_params;
                                    $p['order'] = $col;
                                    if ($order_by === $col) {
                                        $p['dir'] = $order_dir === 'ASC' ? 'DESC' : 'ASC';
                                    } else {
                                        $p['dir'] = in_array($col, $manut_text_cols, true) ? 'ASC' : 'DESC';
                                    }
                                    $p['page'] = 1;
                                    return '?' . http_build_query($p);
                                };
                                $th = function ($col, $label) use ($sort_url, $order_by, $order_dir) {
                                    $url = $sort_url($col);
                                    $ind = ($order_by === $col)
                                        ? ($order_dir === 'ASC' ? '▲' : '▼')
                                        : '⇅';
                                    $cls = 'sortable' . ($order_by === $col ? ' sorted' : '');
                                    return '<th class="' . $cls . '"><a href="' . htmlspecialchars($url) . '" class="mann-sort-link">' . htmlspecialchars($label) . ' <span class="sort-ind">' . $ind . '</span></a></th>';
                                };
                                ?>
                                <?php echo $th('data_manutencao', 'Data'); ?>
                                <?php echo $th('veiculo_placa', 'Veículo'); ?>
                                <?php echo $th('tipo_nome', 'Tipo'); ?>
                                <?php echo $th('descricao', 'Descrição'); ?>
                                <?php echo $th('fornecedor', 'Fornecedor'); ?>
                                <?php echo $th('status_nome', 'Status'); ?>
                                <?php echo $th('valor', 'Valor'); ?>
                                <?php echo $th('custo_12m', 'Custo 12m'); ?>
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
                                    <button class="btn-icon os-btn" data-id="<?php echo $manutencao['id']; ?>" title="Imprimir ordem de serviço">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn-icon duplicate-btn" data-id="<?php echo $manutencao['id']; ?>" title="Duplicar manutenção">
                                        <i class="fas fa-copy"></i>
                                    </button>
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
                            <?php if (empty($manutencoes) && empty($is_modern)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Nenhuma manutenção encontrada</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                $prev_params = $base_params; $prev_params['page'] = $pagina_atual - 1;
                $next_params = $base_params; $next_params['page'] = $pagina_atual + 1;
                ?>
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
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
                <?php if ($is_modern): ?></div><?php endif; ?>

                <!-- Analytics Section (oculta até gráficos prontos para evitar tremida) -->
                <div class="analytics-section charts-pending<?php echo $is_modern ? ' mann-analytics-modern' : ''; ?>" id="analyticsSection">
                    <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <h2><?php echo $is_modern ? 'Análise financeira e operacional' : 'Análise de Manutenções'; ?></h2>
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="maintenanceModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
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

                            <div class="form-group full-width" id="wrap_gerar_conta" style="display:none;">
                                <label class="checklist-item">
                                    <input type="checkbox" id="gerar_conta_pagar" name="gerar_conta_pagar" value="1" checked>
                                    Gerar conta a pagar ao concluir
                                </label>
                                <div class="form-group" style="margin-top:0.5rem;">
                                    <label for="conta_vencimento">Vencimento da conta</label>
                                    <input type="date" id="conta_vencimento" name="conta_vencimento">
                                    <small class="text-muted">Padrão: 30 dias após a data de conclusão</small>
                                </div>
                                <p id="contaPagarLinkWrap" class="text-muted" style="display:none;margin-top:0.35rem;">
                                    <a href="contas_pagar.php" id="contaPagarLink" target="_blank" rel="noopener">Ver conta a pagar vinculada</a>
                                </p>
                            </div>

                            <div class="form-group">
                                <label for="km_atual">Quilometragem Atual*</label>
                                <input type="number" id="km_atual" name="km_atual" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Dados do Serviço</h3>
                        <div class="form-grid mann-finance-grid">
                            <div class="form-group">
                                <label for="fornecedor">Fornecedor</label>
                                <input type="text" id="fornecedor" name="fornecedor" list="fornecedoresDatalist" maxlength="255" placeholder="Digite ou selecione do cadastro">
                                <datalist id="fornecedoresDatalist"></datalist>
                            </div>
                            
                            <div class="form-group">
                                <label for="valor">Valor*</label>
                                <input type="number" id="valor" name="valor" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="custo_total">Custo Total</label>
                                <input type="number" id="custo_total" name="custo_total" step="0.01">
                            </div>
                            
                            <div class="form-group mann-nf-ocr-wrap">
                                <label for="nota_fiscal">Nota Fiscal</label>
                                <div class="mann-nf-row">
                                    <input type="text" id="nota_fiscal" name="nota_fiscal" maxlength="255" placeholder="Número da NF">
                                    <input type="file" id="nfOcrFile" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                                    <button type="button" class="btn-secondary mann-nf-ocr-btn" id="nfOcrBtn" title="Ler NF com IA (foto)"><i class="fas fa-camera"></i></button>
                                </div>
                                <small class="text-muted">Envie foto da NF para preencher valor, fornecedor e número automaticamente.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Detalhes do Serviço</h3>
                        <div class="form-grid mann-detalhes-servico-grid">
                            <div class="form-group">
                                <label for="descricao">Descrição*</label>
                                <textarea id="descricao" name="descricao" rows="3" required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="descricao_servico">Descrição do Serviço*</label>
                                <textarea id="descricao_servico" name="descricao_servico" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-group">
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="deleteMaintenanceModal">
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

    <!-- Modal Importação CSV -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="mntImportModal">
        <div class="modal-content fornc-modal--wide">
            <div class="modal-header">
                <h2>Importar manutenções (CSV)</h2>
                <button type="button" class="close-modal" data-close-mnt-import aria-label="Fechar"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Colunas: <code>data;placa;tipo;componente;fornecedor;valor;km;descricao;nota_fiscal;status</code></p>
                <p><a href="../examples/manutencao_import_modelo.csv" download>Baixar modelo CSV</a></p>
                <input type="file" id="mntImportFile" accept=".csv,text/csv" class="form-control" style="margin-bottom:0.75rem;">
                <p id="mntImportInfo" class="text-muted"></p>
                <div class="fornc-table-wrap" style="max-height:280px;overflow:auto;">
                    <table class="fornc-table">
                        <thead><tr><th>Data</th><th>Placa</th><th>Tipo</th><th>Componente</th><th>Fornecedor</th><th>Valor</th></tr></thead>
                        <tbody id="mntImportPreview"></tbody>
                    </table>
                </div>
                <p id="mntImportResult" style="margin-top:0.75rem;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close-mnt-import>Fechar</button>
                <button type="button" class="btn-primary" id="mntImportSaveBtn" disabled><i class="fas fa-file-import"></i> Importar</button>
            </div>
        </div>
    </div>

    <!-- Modal Ordem de Serviço (impressão) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="osPrintModal">
        <div class="modal-content fornc-modal--wide mann-os-print-wrap">
            <div class="modal-header no-print">
                <h2>Ordem de Serviço</h2>
                <button type="button" class="close-modal" aria-label="Fechar"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="osPrintBody"></div>
            <div class="modal-footer no-print">
                <button type="button" class="btn-secondary" data-close-modal>Fechar</button>
                <button type="button" class="btn-primary" id="osPrintBtn"><i class="fas fa-print"></i> Imprimir</button>
            </div>
        </div>
    </div>

    <!-- Modal Relatório por veículo (histórico + resumo) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="historicoVeiculoModal">
        <div class="modal-content relatorio-veiculo-print<?php echo $is_modern ? ' fornc-modal--wide' : ''; ?>" style="<?php echo $is_modern ? '' : 'max-width: 720px;'; ?>">
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
                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'data-table-container'; ?>" style="max-height: 360px; overflow-y: auto;">
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>">
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
    <?php $sf_help_key = 'manutencoes'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>


    <!-- Initialize page -->
    <script>
        async function loadChartsForPeriod(period) {
            const url = period ? ('../includes/get_maintenance_data.php?period=' + encodeURIComponent(period)) : '../includes/get_maintenance_data.php';
            const response = await fetch(url);
            if (!response.ok) throw new Error('Erro ao buscar dados: ' + response.statusText);
            const data = await response.json();
            if (data.success) {
                initializeMaintenanceCharts(data);
                updateMannPeriodKpis(data);
            }
            return data;
        }
        function updateMannPeriodKpis(data) {
            if (!data) return;
            var mtbf = document.getElementById('mannKpiMtbf');
            var mttr = document.getElementById('mannKpiMttr');
            var ckm = document.getElementById('mannKpiCustoKm');
            var hint = document.getElementById('mannKpiPeriodHint');
            if (mtbf && data.mtbf != null) mtbf.textContent = Number(data.mtbf).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' km';
            if (mttr && data.mttr != null) mttr.textContent = Number(data.mttr).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' h';
            if (ckm && data.cost_per_km != null) ckm.textContent = 'R$ ' + Number(data.cost_per_km).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (hint && data.period_label) hint.textContent = 'MTBF, MTTR e Custo/KM conforme período dos gráficos (' + data.period_label + ').';
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

    <!-- Modal de confirmação (substitui confirm() nativo) -->
    <div class="mnt-confirm" id="mntConfirmModal" role="dialog" aria-modal="true" aria-labelledby="mntConfirmTitle" style="display:none;">
        <div class="mnt-confirm-box">
            <div class="mnt-confirm-ico"><i class="fas fa-triangle-exclamation"></i></div>
            <h4 id="mntConfirmTitle">Confirmar</h4>
            <p id="mntConfirmMsg">Tem certeza?</p>
            <div class="mnt-confirm-actions">
                <button type="button" class="fornc-btn fornc-btn--ghost" id="mntConfirmCancel"><i class="fas fa-times"></i> Cancelar</button>
                <button type="button" class="fornc-btn fornc-btn--primary" id="mntConfirmOk" style="background:#dc2626;"><i class="fas fa-check"></i> Confirmar</button>
            </div>
        </div>
    </div>

    <?php include '../includes/scroll_to_top.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>