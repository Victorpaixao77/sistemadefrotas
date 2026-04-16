<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/sf_api_base.php';

configure_session();
session_start();
require_authentication();

$conn = getConnection();
$page_title = "Multas";

// Layout moderno (fornc-page); ?classic=1 para o layout anterior
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';

$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
    $per_page = 10;
}

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

function getMultas($conn, $page = 1, $per_page = 10, $sort_field = 'data_infracao', $sort_dir = 'DESC') {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $limit = in_array($per_page, [5, 10, 25, 50, 100], true) ? $per_page : 10;
        $offset = ($page - 1) * $limit;
        $allowedSort = [
            'data_infracao' => 'm.data_infracao',
            'veiculo_placa' => 'v.placa',
            'motorista_nome' => 'mo.nome',
            'rota' => 'm.rota_id',
            'tipo_infracao' => 'm.tipo_infracao',
            'pontos' => 'm.pontos',
            'valor' => 'm.valor',
            'status_pagamento' => 'm.status_pagamento',
            'vencimento' => 'm.vencimento',
        ];
        if (!isset($allowedSort[$sort_field])) {
            $sort_field = 'data_infracao';
        }
        $orderCol = $allowedSort[$sort_field];
        $dir = ($sort_dir === 'ASC') ? 'ASC' : 'DESC';
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
                ORDER BY " . $orderCol . " " . $dir . ", m.id DESC
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

$mul_sort = isset($_GET['sort']) ? preg_replace('/[^a-z_]/', '', (string) $_GET['sort']) : 'data_infracao';
$mul_dir = (isset($_GET['dir']) && strtoupper((string) $_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';

function multas_sort_link_url($field, $mul_sort, $mul_dir, $per_page, $is_modern) {
    $next_dir = 'DESC';
    if ($mul_sort === $field) {
        $next_dir = $mul_dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
        $text_like = ['veiculo_placa', 'motorista_nome', 'tipo_infracao', 'status_pagamento'];
        $next_dir = in_array($field, $text_like, true) ? 'ASC' : 'DESC';
    }
    $q = ['page' => 1, 'per_page' => $per_page, 'sort' => $field, 'dir' => $next_dir];
    if (!$is_modern) {
        $q['classic'] = '1';
    }
    return '?' . http_build_query($q);
}

function multas_sort_indicator($field, $mul_sort, $mul_dir) {
    if ($mul_sort !== $field) {
        return '⇅';
    }
    return $mul_dir === 'ASC' ? '▲' : '▼';
}

$resultado = getMultas($conn, $pagina_atual, $per_page, $mul_sort, $mul_dir);
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
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <style>
        /* Modal financeiro: deixa os campos em 2 colunas (desktop) e 1 coluna (mobile) */
        #multaModal .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
            margin-bottom: 4px;
        }
        @media (max-width: 640px) {
            #multaModal .form-grid {
                grid-template-columns: 1fr;
            }
        }
        body.multas-modern .dashboard-header { display: none; }
        body.multas-modern .dashboard-content.fornc-page { overflow-x: auto; }
        body.multas-modern #multasTable.fornc-table { min-width: 1020px; }
        body.multas-modern #multasTable.fornc-table th.sortable .th-sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        body.multas-modern #multasTable.fornc-table th.sortable .th-sort-link:hover {
            color: var(--accent-primary, #3b82f6);
        }
        body.multas-modern #multasTable.fornc-table th.sortable.sorted .th-sort-link {
            font-weight: 600;
        }
        #multasTable .sort-ind { font-size: 0.75rem; opacity: 0.85; }
        .fornc-toolbar.highlight-filter,
        .filter-section.highlight-filter {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
            border-radius: 12px;
        }
    </style>
</head>
<body class="<?php echo $is_modern ? 'multas-modern' : ''; ?>">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <p class="fornc-modern-hint">
                    Multas e consulta DETRAN. Use <code>?classic=1</code> para o layout anterior.
                </p>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><span class="lbl">Multas (mês)</span><span class="val"><?php echo (int) $kpis['total_multas']; ?></span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Valor total</span><span class="val">R$ <?php echo number_format($kpis['valor_total'], 2, ',', '.'); ?></span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Pendentes</span><span class="val"><?php echo (int) $kpis['total_pendentes']; ?></span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Pontos (mês)</span><span class="val"><?php echo (int) $kpis['pontos_total']; ?></span></div>
                </div>
                <p class="fornc-kpi-summary">Indicadores do mês corrente.</p>
                <?php else: ?>
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
                <?php endif; ?>
                
                <!-- Consulta DETRAN (WSDenatran) -->
                <div class="dashboard-card denatran-consulta-card">
                    <div class="card-header">
                        <h3><i class="fas fa-search"></i> Consulta de Multas no DETRAN</h3>
                    </div>
                    <div class="card-body">
                        <p class="denatran-desc">Consulte infrações diretamente na base do Denatran (WSDenatran). É necessário certificado cadastrado e CPF do usuário autorizado.</p>
                        <form id="denatranForm" class="denatran-form">
                            <!-- Bloco separado: Tipo de consulta -->
                            <div class="denatran-section denatran-section-tipo">
                                <h4 class="denatran-section-title">Tipo de consulta</h4>
                                <div class="denatran-tipo-select-wrap">
                                    <select id="denatran_tipo" name="tipo" required>
                                        <option value="cpf">Por CPF (condutor/proprietário)</option>
                                        <option value="placa">Por Placa do veículo</option>
                                        <option value="cnpj">Por CNPJ (proprietário)</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Demais campos -->
                            <div class="denatran-section denatran-section-campos">
                                <div class="form-grid denatran-grid">
                                    <div class="form-group" id="denatran_cpf_usuario_group">
                                        <label for="denatran_cpf_usuario">CPF do usuário (quem consulta) *</label>
                                        <input type="text" id="denatran_cpf_usuario" name="cpf_usuario" placeholder="000.000.000-00" maxlength="14" required>
                                        <small class="form-text denatran-cpf-hint">Se já estiver configurado em Configurações do sistema, será preenchido automaticamente.</small>
                                    </div>
                                    <div class="form-group denatran-tipo-field" id="denatran_cpf_group">
                                        <label for="denatran_cpf">CPF do condutor/proprietário</label>
                                        <input type="text" id="denatran_cpf" name="cpf" placeholder="000.000.000-00" maxlength="14">
                                    </div>
                                    <div class="form-group denatran-tipo-field" id="denatran_placa_group" style="display:none;">
                                        <label for="denatran_placa">Placa do veículo</label>
                                        <input type="text" id="denatran_placa" name="placa" placeholder="ABC1D23" maxlength="7">
                                    </div>
                                    <div class="form-group denatran-tipo-field" id="denatran_exigibilidade_group" style="display:none;">
                                        <label for="denatran_exigibilidade">Exigibilidade</label>
                                        <select id="denatran_exigibilidade" name="exigibilidade">
                                            <option value="T">Todas (T)</option>
                                            <option value="S">Exigível (S)</option>
                                            <option value="N">Não exigível (N)</option>
                                        </select>
                                    </div>
                                    <div class="form-group denatran-tipo-field" id="denatran_cnpj_group" style="display:none;">
                                        <label for="denatran_cnpj">CNPJ do proprietário</label>
                                        <input type="text" id="denatran_cnpj" name="cnpj" placeholder="00.000.000/0000-00" maxlength="18">
                                    </div>
                                    <div class="form-group">
                                        <label for="denatran_data_inicio">Data início (opcional)</label>
                                        <input type="date" id="denatran_data_inicio" name="dataInicio">
                                    </div>
                                    <div class="form-group">
                                        <label for="denatran_data_fim">Data fim (opcional)</label>
                                        <input type="date" id="denatran_data_fim" name="dataFim">
                                    </div>
                                </div>
                                <div class="denatran-actions">
                                    <button type="submit" class="btn-primary" id="denatranConsultarBtn">
                                        <i class="fas fa-search"></i> Consultar no DETRAN
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div id="denatranResultContainer" class="denatran-result-container" style="display:none;">
                            <h4 id="denatranResultTitle">Resultado da consulta</h4>
                            <div id="denatranResultMessage" class="denatran-result-message"></div>
                            <div class="table-responsive denatran-table-wrap">
                                <table class="data-table" id="denatranResultTable">
                                    <thead>
                                        <tr>
                                            <th>Auto / AIT</th>
                                            <th>Placa</th>
                                            <th>Infração</th>
                                            <th>Data</th>
                                            <th>Valor (R$)</th>
                                            <th>Exigibilidade</th>
                                            <th>Órgão</th>
                                        </tr>
                                    </thead>
                                    <tbody id="denatranResultBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($is_modern): ?>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchMulta">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchMulta" placeholder="Buscar multa..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="vehicleFilter">Veículo</label>
                            <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="driverFilter">Motorista</label>
                            <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="recurso">Recurso</option>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="per_page_mul">Por página</label>
                            <form method="get" action="" style="display:inline-flex; align-items:center; gap:0.35rem;">
                                <input type="hidden" name="page" value="1">
                                <select name="per_page" id="per_page_mul" class="filter-per-page" onchange="this.form.submit()">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            </form>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" id="addMultaBtn" class="fornc-btn fornc-btn--primary"><i class="fas fa-plus"></i> Nova multa</button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyFinesFilters" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn" title="Destacar filtros"><i class="fas fa-sliders-h"></i> Opções</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearFinesFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar"><i class="fas fa-file-export"></i> Exportar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMulta" placeholder="Buscar multa...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <form method="get" action="" style="display:inline-flex; align-items:center; gap:0.5rem;">
                            <span class="filter-label">Por página</span>
                            <input type="hidden" name="page" value="1">
                            <input type="hidden" name="classic" value="1">
                            <select name="per_page" class="filter-per-page" onchange="this.form.submit()">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
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
                        <button type="button" class="btn-restore-layout" id="applyFinesFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearFinesFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Multas Table -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'data-table-container'; ?>">
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="multasTable">
                        <thead>
                            <tr>
                                <?php if ($is_modern): ?>
                                <th class="sortable<?php echo $mul_sort === 'data_infracao' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('data_infracao', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Data <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('data_infracao', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'veiculo_placa' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('veiculo_placa', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Veículo <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('veiculo_placa', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'motorista_nome' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('motorista_nome', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Motorista <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('motorista_nome', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'rota' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('rota', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Rota <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('rota', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'tipo_infracao' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('tipo_infracao', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Tipo <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('tipo_infracao', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'pontos' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('pontos', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Pontos <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('pontos', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'valor' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('valor', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Valor <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('valor', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'status_pagamento' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('status_pagamento', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Status <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('status_pagamento', $mul_sort, $mul_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $mul_sort === 'vencimento' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(multas_sort_link_url('vencimento', $mul_sort, $mul_dir, $per_page, $is_modern)); ?>" class="th-sort-link">Vencimento <span class="sort-ind"><?php echo htmlspecialchars(multas_sort_indicator('vencimento', $mul_sort, $mul_dir)); ?></span></a></th>
                                <?php else: ?>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Motorista</th>
                                <th>Rota</th>
                                <th>Tipo</th>
                                <th>Pontos</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Vencimento</th>
                                <?php endif; ?>
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
                <?php
                $total_reg_mul = (int)($resultado['total'] ?? 0);
                $mul_q = ['per_page' => (int) $per_page];
                if ($mul_sort !== 'data_infracao' || $mul_dir !== 'DESC') {
                    $mul_q['sort'] = $mul_sort;
                    $mul_q['dir'] = $mul_dir;
                }
                if (!$is_modern) {
                    $mul_q['classic'] = '1';
                }
                $mul_prev = array_merge($mul_q, ['page' => max(1, $pagina_atual - 1)]);
                $mul_next = array_merge($mul_q, ['page' => min($total_paginas, $pagina_atual + 1)]);
                ?>
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
                <div class="pagination<?php echo $is_modern ? ' fornc-modern-pagination' : ''; ?>">
                    <a href="?<?php echo htmlspecialchars(http_build_query($mul_prev)); ?>"
                       class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo $total_reg_mul; ?> registros)</span>
                    <a href="?<?php echo htmlspecialchars(http_build_query($mul_next)); ?>"
                       class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if ($is_modern): ?></div><?php endif; ?>

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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="multaModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="deleteMultaModal">
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="helpMultaModal">
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
                        <li><strong>Consulta DETRAN:</strong> Consulte infrações diretamente na base do Denatran (WSDenatran) por CPF, placa ou CNPJ. Requer certificado cadastrado e configuração em <code>includes/denatran_config.php</code>.</li>
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
    <?php sf_render_api_scripts(); ?>
    <script src="../js/multas.js"></script>

    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html> 