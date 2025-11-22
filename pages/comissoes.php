<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

configure_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_authentication();

$page_title = "Comissões";
$empresa_id = $_SESSION['empresa_id'];

/**
 * Ensure commission payments table exists.
 *
 * @param PDO $conn
 * @return void
 */
function ensureCommissionPaymentsTable(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comissoes_pagamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rota_id INT NOT NULL UNIQUE,
            status ENUM('pago', 'pendente') NOT NULL DEFAULT 'pendente',
            valor DECIMAL(10,2) DEFAULT NULL,
            data_pagamento DATE DEFAULT NULL,
            observacao TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_comissoes_pag_rotas FOREIGN KEY (rota_id) REFERENCES rotas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function normalizeSearchValue(string $input): string
{
    $input = trim($input);
    if ($input === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($input, 'UTF-8');
    }

    return strtolower($input);
}

function logComissoesDebug(string $context, string $sql, array $params = []): void
{
    if (isset($_GET['debug_search'])) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $message = sprintf(
            "[%s] [comissoes][%s]\nSQL: %s\nParametros: %s\n\n",
            date('Y-m-d H:i:s'),
            $context,
            $sql,
            json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        $logFile = $logDir . '/comissoes_debug.log';
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}

function executeWithDebug(PDOStatement $stmt, array $params, string $context, string $sql): void
{
    try {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        logComissoesDebug($context, $sql, $params);
        $stmt->execute();
    } catch (PDOException $e) {
        logComissoesDebug($context . '_erro', $sql, [
            'mensagem' => $e->getMessage(),
            'params' => $params
        ]);
        throw $e;
    }
}

function buildComissoesQueryParts(array $filters, int $empresa_id): array
{
    $conditions = [
        "r.empresa_id = :empresa_id",
        "r.comissao > 0",
        "r.status = 'aprovado'"
    ];
    $params = [
        ':empresa_id' => $empresa_id
    ];

    if (!empty($filters['mes'])) {
        $conditions[] = "DATE_FORMAT(r.data_rota, '%Y-%m') = :mes";
        $params[':mes'] = $filters['mes'];
    }

    if (!empty($filters['motorista'])) {
        $conditions[] = "r.motorista_id = :motorista_id";
        $params[':motorista_id'] = (int) $filters['motorista'];
    }

    if (!empty($filters['veiculo'])) {
        $conditions[] = "r.veiculo_id = :veiculo_id";
        $params[':veiculo_id'] = (int) $filters['veiculo'];
    }

    if (!empty($filters['search'])) {
        $searchTermRaw = trim($filters['search']);
        $searchTermLower = normalizeSearchValue($filters['search']);
        $numericValue = preg_replace('/[^0-9]/', '', $searchTermRaw);
        $decimalValue = str_replace(',', '.', preg_replace('/[^0-9,]/', '', $searchTermRaw));

        $conditions[] = "(
            EXISTS (
                SELECT 1 FROM motoristas m_busca 
                WHERE m_busca.id = r.motorista_id 
                AND LOWER(m_busca.nome) LIKE :search_motorista
            )
            OR EXISTS (
                SELECT 1 FROM veiculos v_busca 
                WHERE v_busca.id = r.veiculo_id 
                AND (
                    LOWER(v_busca.placa) LIKE :search_placa
                    OR LOWER(COALESCE(v_busca.modelo, '')) LIKE :search_modelo
                    OR LOWER(CONCAT_WS(' ', v_busca.placa, COALESCE(v_busca.modelo, ''))) LIKE :search_veiculo_completo
                )
            )
            OR CAST(r.id AS CHAR) LIKE :search_id
            OR DATE_FORMAT(r.data_rota, '%d/%m/%Y') LIKE :search_data_completa
            OR DATE_FORMAT(r.data_rota, '%Y-%m') LIKE :search_data_mes
            OR CAST(r.frete AS CHAR) LIKE :search_frete
            OR CAST(r.comissao AS CHAR) LIKE :search_comissao
        )";

        $searchMotorista = '%' . $searchTermLower . '%';
        $params[':search_motorista'] = $searchMotorista;
        $params[':search_placa'] = $searchMotorista;
        $params[':search_modelo'] = $searchMotorista;
        $params[':search_veiculo_completo'] = $searchMotorista;
        $params[':search_id'] = '%' . $searchTermRaw . '%';
        $params[':search_data_completa'] = '%' . $searchTermRaw . '%';
        $params[':search_data_mes'] = '%' . $searchTermRaw . '%';
        $numericSearch = $decimalValue !== '' ? $decimalValue : $numericValue;
        $numericLike = $numericSearch !== '' ? '%' . $numericSearch . '%' : '%';
        $params[':search_frete'] = $numericLike;
        $params[':search_comissao'] = $numericLike;
    }

    return [$conditions, $params];
}

function getComissoes(array $filters, int $page, int $empresa_id, PDO $conn): array
{
    $limit = 5;
    $offset = ($page - 1) * $limit;

    [$conditions, $params] = buildComissoesQueryParts($filters, $empresa_id);
    $whereClause = implode(' AND ', $conditions);

    // Totais e contagem
    $sqlResumo = "
        SELECT
            COUNT(*) AS total_viagens,
            COALESCE(SUM(r.comissao), 0) AS total_comissao,
            COALESCE(SUM(r.frete), 0) AS total_frete,
            COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN r.comissao ELSE 0 END), 0) AS total_pago,
            COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN 1 ELSE 0 END), 0) AS viagens_pagas
        FROM rotas r
        LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
        WHERE {$whereClause}";

    $stmtResumo = $conn->prepare($sqlResumo);
    executeWithDebug($stmtResumo, $params, 'resumo', $sqlResumo);
    $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

    $sqlCount = "SELECT COUNT(*) FROM rotas r WHERE {$whereClause}";
    $stmtCount = $conn->prepare($sqlCount);
    executeWithDebug($stmtCount, $params, 'contagem', $sqlCount);
    $totalRegistros = (int) $stmtCount->fetchColumn();

    $sql = "
        SELECT 
            r.id,
            r.data_rota,
            r.data_saida,
            r.frete,
            r.comissao,
            r.no_prazo,
            r.status,
            v.placa AS veiculo_placa,
            v.modelo AS veiculo_modelo,
            m.nome AS motorista_nome,
            cp.status AS status_pagamento,
            cp.data_pagamento,
            cp.valor AS valor_pago
        FROM rotas r
        LEFT JOIN veiculos v ON r.veiculo_id = v.id
        LEFT JOIN motoristas m ON r.motorista_id = m.id
        LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
        WHERE {$whereClause}
        ORDER BY r.data_rota DESC, r.id DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    executeWithDebug(
        $stmt,
        $params + [
            ':limit' => $limit,
            ':offset' => $offset
        ],
        'listagem',
        $sql
    );

    $comissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resumo['total_pago'] = $resumo['total_pago'] ?? 0;
    $resumo['total_pendente'] = ($resumo['total_comissao'] ?? 0) - $resumo['total_pago'];
    $resumo['viagens_pagas'] = $resumo['viagens_pagas'] ?? 0;
    $resumo['viagens_pendentes'] = ($resumo['total_viagens'] ?? 0) - $resumo['viagens_pagas'];
    $resumo['percentual_pago'] = ($resumo['total_comissao'] ?? 0) > 0
        ? ($resumo['total_pago'] / $resumo['total_comissao']) * 100
        : 0;
    $resumo['percentual_medio'] = ($resumo['total_frete'] > 0)
        ? ($resumo['total_comissao'] / $resumo['total_frete']) * 100
        : 0;

    $charts = getComissoesChartsData($conditions, $params, $conn);

    return [
        'comissoes' => $comissoes,
        'total' => $totalRegistros,
        'pagina_atual' => $page,
        'total_paginas' => max(1, (int) ceil($totalRegistros / $limit)),
        'resumo' => $resumo,
        'charts' => $charts
    ];
}

function getComissoesChartsData(array $conditions, array $params, PDO $conn): array
{
    $whereClause = implode(' AND ', $conditions);

    // Status distribution
    $sqlStatus = "
        SELECT 
            COALESCE(cp.status, 'pendente') AS status_pagamento,
            COUNT(*) AS total_viagens,
            COALESCE(SUM(r.comissao), 0) AS total_comissao
        FROM rotas r
        LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
        WHERE {$whereClause}
        GROUP BY status_pagamento";

    $stmtStatus = $conn->prepare($sqlStatus);
    executeWithDebug($stmtStatus, $params, 'grafico_status', $sqlStatus);
    $statusRows = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

    $statusTotals = [
        'pago' => ['viagens' => 0, 'valor' => 0],
        'pendente' => ['viagens' => 0, 'valor' => 0]
    ];

    foreach ($statusRows as $row) {
        $status = $row['status_pagamento'] === 'pago' ? 'pago' : 'pendente';
        $statusTotals[$status]['viagens'] += (int) $row['total_viagens'];
        $statusTotals[$status]['valor'] += (float) $row['total_comissao'];
    }

    // Monthly evolution (last 12 months by default)
    $sqlMonthly = "
        SELECT 
            DATE_FORMAT(r.data_rota, '%Y-%m') AS periodo,
            COALESCE(SUM(r.comissao), 0) AS total_comissao,
            COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN r.comissao ELSE 0 END), 0) AS total_pago,
            COALESCE(SUM(CASE WHEN cp.status = 'pendente' OR cp.status IS NULL THEN r.comissao ELSE 0 END), 0) AS total_pendente
        FROM rotas r
        LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
        WHERE {$whereClause}
        GROUP BY periodo
        ORDER BY periodo ASC";

    $stmtMonthly = $conn->prepare($sqlMonthly);
    executeWithDebug($stmtMonthly, $params, 'grafico_mensal', $sqlMonthly);
    $monthlyRows = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

    $monthlyData = [
        'labels' => [],
        'total' => [],
        'pagas' => [],
        'pendentes' => []
    ];

    foreach ($monthlyRows as $row) {
        $periodo = $row['periodo'] . '-01';
        $label = date('m/Y', strtotime($periodo));
        $monthlyData['labels'][] = $label;
        $monthlyData['total'][] = (float) $row['total_comissao'];
        $monthlyData['pagas'][] = (float) $row['total_pago'];
        $monthlyData['pendentes'][] = (float) $row['total_pendente'];
    }

    // Motorista x Comissão
    $sqlMotoristas = "
        SELECT 
            COALESCE(m.nome, 'Não informado') AS motorista_nome,
            COALESCE(SUM(r.comissao), 0) AS total_comissao,
            COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN r.comissao ELSE 0 END), 0) AS total_pago
        FROM rotas r
        LEFT JOIN motoristas m ON r.motorista_id = m.id
        LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
        WHERE {$whereClause}
        GROUP BY m.id, motorista_nome
        ORDER BY total_comissao DESC
        LIMIT 8";

    $stmtMotoristas = $conn->prepare($sqlMotoristas);
    executeWithDebug($stmtMotoristas, $params, 'grafico_motoristas', $sqlMotoristas);
    $motoristaRows = $stmtMotoristas->fetchAll(PDO::FETCH_ASSOC);

    $motoristaData = [
        'labels' => [],
        'totais' => [],
        'pagas' => [],
        'pendentes' => [],
        'top_list' => []
    ];

    foreach ($motoristaRows as $row) {
        $total = (float) $row['total_comissao'];
        $pago = (float) $row['total_pago'];
        $pendente = $total - $pago;
        $motoristaData['labels'][] = $row['motorista_nome'];
        $motoristaData['totais'][] = $total;
        $motoristaData['pagas'][] = $pago;
        $motoristaData['pendentes'][] = $pendente;
        $motoristaData['top_list'][] = [
            'nome' => $row['motorista_nome'],
            'total' => $total,
            'pago' => $pago,
            'pendente' => $pendente
        ];
    }

    return [
        'status' => [
            'labels' => ['Pagas', 'Pendentes'],
            'values' => [
                round($statusTotals['pago']['valor'], 2),
                round($statusTotals['pendente']['valor'], 2)
            ]
        ],
        'monthly' => $monthlyData,
        'motoristas' => $motoristaData
    ];
}

$conn = getConnection();
ensureCommissionPaymentsTable($conn);

/**
 * Obtém dados auxiliares para filtros.
 *
 * @param int $empresa_id
 * @return array
 */
function getComissoesFilterData(int $empresa_id): array
{
    $conn = getConnection();

    $motoristasStmt = $conn->prepare("
        SELECT id, nome
        FROM motoristas
        WHERE empresa_id = :empresa_id
        ORDER BY nome ASC");
    $motoristasStmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $motoristasStmt->execute();
    $motoristas = $motoristasStmt->fetchAll(PDO::FETCH_ASSOC);

    $veiculosStmt = $conn->prepare("
        SELECT id, placa, modelo
        FROM veiculos
        WHERE empresa_id = :empresa_id
        ORDER BY placa ASC");
    $veiculosStmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $veiculosStmt->execute();
    $veiculos = $veiculosStmt->fetchAll(PDO::FETCH_ASSOC);

    $periodosStmt = $conn->prepare("
        SELECT DISTINCT DATE_FORMAT(data_rota, '%Y-%m') AS periodo
        FROM rotas
        WHERE empresa_id = :empresa_id
          AND comissao > 0
          AND status = 'aprovado'
        ORDER BY periodo DESC
        LIMIT 18");
    $periodosStmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $periodosStmt->execute();
    $periodos = $periodosStmt->fetchAll(PDO::FETCH_COLUMN);

    return [
        'motoristas' => $motoristas,
        'veiculos' => $veiculos,
        'periodos' => $periodos
    ];
}

$pagina_atual = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$filters = [
    'mes' => '',
    'motorista' => '',
    'veiculo' => '',
    'search' => ''
];

if (!empty($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes'])) {
    $filters['mes'] = $_GET['mes'];
}

if (!empty($_GET['motorista'])) {
    $filters['motorista'] = $_GET['motorista'];
}

if (!empty($_GET['veiculo'])) {
    $filters['veiculo'] = $_GET['veiculo'];
}

$filters['search'] = trim($_GET['search'] ?? '');

$resultado = getComissoes($filters, $pagina_atual, $empresa_id, $conn);
$comissoes = $resultado['comissoes'];
$resumo = $resultado['resumo'];
$total_paginas = $resultado['total_paginas'];
$charts = $resultado['charts'] ?? [];

$chartStatusData = $charts['status'] ?? [
    'labels' => ['Pagas', 'Pendentes'],
    'values' => [0, 0]
];

$chartMonthlyData = $charts['monthly'] ?? [
    'labels' => [],
    'total' => [],
    'pagas' => [],
    'pendentes' => []
];

$chartMotoristaData = $charts['motoristas'] ?? [
    'labels' => [],
    'totais' => [],
    'pagas' => [],
    'pendentes' => [],
    'top_list' => []
];

$topMotoristas = $chartMotoristaData['top_list'] ?? [];

$filterData = getComissoesFilterData($empresa_id);
$motoristas = $filterData['motoristas'];
$veiculos = $filterData['veiculos'];
$periodos = $filterData['periodos'];

function formatarDataRota(?string $data): string
{
    if (empty($data)) {
        return '-';
    }
    return date('d/m/Y', strtotime($data));
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .commission-charts {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 20px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .chart-card h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .chart-card canvas {
            width: 100% !important;
            height: 260px !important;
        }

        .chart-card-wide canvas {
            height: 280px;
        }

        .top-motoristas-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 20px;
        }

        .top-motoristas-card .card-header {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .top-motoristas-card .card-body {
            padding: 0;
        }

        .top-motoristas-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .top-motoristas-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .top-motoristas-list li:last-child {
            border-bottom: none;
        }

        .top-motoristas-list .values {
            text-align: right;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .charts-row {
                grid-template-columns: 1fr;
            }

            .chart-card canvas,
            .chart-card-wide canvas {
                height: 260px;
            }
        }

        .filter-section.highlight-filter {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.4);
            overflow-y: auto;
        }

        .modal-content {
            background-color: var(--bg-secondary);
            margin: 5% auto;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            width: min(600px, 90%);
            overflow: hidden;
            color: var(--text-primary);
        }

        .modal-header, .modal-footer {
            padding: 16px 24px;
            background-color: var(--bg-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .modal-body {
            padding: 24px;
            line-height: 1.6;
        }

        .modal-body ul {
            margin: 12px 0 0 18px;
            padding: 0;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--text-secondary);
        }

        .close-modal:hover {
            color: var(--text-primary);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--accent-primary);
            color: #fff;
        }

        .filter-section {
            margin: 20px 0;
        }

        .filter-section .search-box {
            position: relative;
            max-width: 320px;
        }

        .filter-section .search-box input {
            width: 100%;
            padding: 10px 36px 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }

        .filter-section .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-options {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .filter-options select {
            min-width: 180px;
            padding: 8px 12px;
            border-radius: 10px;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: border-color 0.2s ease;
        }

        .filter-options select:focus {
            border-color: var(--accent-primary);
            outline: none;
        }

        .filter-options .btn-restore-layout {
            border: none;
            background: none;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .filter-options .btn-restore-layout i {
            font-size: 1rem;
        }

        .filter-options .btn-restore-layout:hover {
            color: var(--accent-primary);
        }

        .light-theme .filter-options .btn-restore-layout {
            color: var(--text-secondary);
        }

        .light-theme .filter-options .btn-restore-layout:hover {
            color: var(--accent-primary);
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
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <div class="dashboard-actions">
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportCsvBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($resumo['total_comissao'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle"><?php echo (int) ($resumo['total_viagens'] ?? 0); ?> viagens</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissões Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($resumo['total_pago'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle"><?php echo (int) ($resumo['viagens_pagas'] ?? 0); ?> viagens pagas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissões Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($resumo['total_pendente'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle"><?php echo (int) ($resumo['viagens_pendentes'] ?? 0); ?> viagens pendentes</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Percentual Pago</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo number_format($resumo['percentual_pago'] ?? 0, 2, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Do total de comissões</span>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="filter-section" method="GET">
                    <div class="search-box">
                        <input type="text" id="searchCommission" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Buscar por motorista, veículo ou data...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <select id="mes" name="mes" title="Período">
                            <option value="">Todos os períodos</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?php echo htmlspecialchars($periodo); ?>" <?php echo $filters['mes'] === $periodo ? 'selected' : ''; ?>>
                                    <?php echo date('m/Y', strtotime($periodo . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="motorista" name="motorista" title="Motorista">
                            <option value="">Todos os motoristas</option>
                            <?php foreach ($motoristas as $motorista): ?>
                                <option value="<?php echo (int) $motorista['id']; ?>" <?php echo $filters['motorista'] == $motorista['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($motorista['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="veiculo" name="veiculo" title="Veículo">
                            <option value="">Todos os veículos</option>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <option value="<?php echo (int) $veiculo['id']; ?>" <?php echo $filters['veiculo'] == $veiculo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($veiculo['placa'] . ' - ' . $veiculo['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-restore-layout" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="comissoes.php" class="btn-restore-layout" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>

                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Motorista</th>
                                <th>Veículo</th>
                                <th>Frete</th>
                                <th>Comissão</th>
                                <th>% Comissão</th>
                                <th>Status Pagamento</th>
                                <th>Prazo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($comissoes)): ?>
                                <?php foreach ($comissoes as $comissao): ?>
                                    <?php
                                        $percentual = ($comissao['frete'] > 0)
                                            ? ($comissao['comissao'] / $comissao['frete']) * 100
                                            : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo formatarDataRota($comissao['data_rota'] ?? $comissao['data_saida']); ?></td>
                                        <td><?php echo htmlspecialchars($comissao['motorista_nome'] ?? 'Motorista não informado'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($comissao['veiculo_placa'] ?? '-') . ' ' . ($comissao['veiculo_modelo'] ?? ''))); ?></td>
                                        <td>R$ <?php echo number_format($comissao['frete'], 2, ',', '.'); ?></td>
                                        <td>R$ <?php echo number_format($comissao['comissao'], 2, ',', '.'); ?></td>
                                        <td><?php echo number_format($percentual, 2, ',', '.'); ?>%</td>
                                        <td>
                                            <?php
                                                $statusPagamento = $comissao['status_pagamento'] ?? 'pendente';
                                                $badgeClass = $statusPagamento === 'pago' ? 'status-success' : 'status-warning';
                                                $label = $statusPagamento === 'pago' ? 'Pago' : 'Pendente';
                                            ?>
                                            <span class="status-badge <?php echo $badgeClass; ?>">
                                                <?php echo $label; ?>
                                            </span>
                                            <?php if (!empty($comissao['data_pagamento'])): ?>
                                                <div class="small text-muted">
                                                    Pago em <?php echo date('d/m/Y', strtotime($comissao['data_pagamento'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo !empty($comissao['no_prazo']) ? 'status-success' : 'status-warning'; ?>">
                                                <?php echo !empty($comissao['no_prazo']) ? 'No prazo' : 'Fora do prazo'; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <?php if (($comissao['status_pagamento'] ?? 'pendente') === 'pago'): ?>
                                                <button 
                                                    class="btn-icon mark-pending-btn" 
                                                    data-rota-id="<?php echo (int) $comissao['id']; ?>"
                                                    title="Marcar como pendente">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    class="btn-icon mark-paid-btn" 
                                                    data-rota-id="<?php echo (int) $comissao['id']; ?>"
                                                    title="Marcar como pago">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">Nenhuma comissão encontrada para os filtros selecionados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <button 
                        class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" 
                        id="prevPageBtn"
                        <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="pagination-info">
                        Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                    </span>
                    <button 
                        class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" 
                        id="nextPageBtn"
                        <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="commission-charts">
                    <div class="charts-row">
                        <div class="chart-card">
                            <h3>Situação de Pagamento</h3>
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="chart-card chart-card-wide">
                            <h3>Evolução Mensal de Comissões</h3>
                            <canvas id="monthlyChart"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3>Motoristas x Comissão</h3>
                            <canvas id="motoristaChart"></canvas>
                        </div>
                    </div>
                    <div class="top-motoristas-card">
                        <div class="card-header">
                            <h3>Top Motoristas por Comissão</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($topMotoristas)): ?>
                                <ul class="top-motoristas-list">
                                    <?php foreach ($topMotoristas as $motorista): ?>
                                        <li>
                                            <div>
                                                <strong><?php echo htmlspecialchars($motorista['nome']); ?></strong>
                                                <div class="text-muted small">
                                                    Pago: R$ <?php echo number_format($motorista['pago'], 2, ',', '.'); ?> •
                                                    Pendente: R$ <?php echo number_format($motorista['pendente'], 2, ',', '.'); ?>
                                                </div>
                                            </div>
                                            <div class="values">
                                                <span class="total">R$ <?php echo number_format($motorista['total'], 2, ',', '.'); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Nenhum motorista com comissão registrada para os filtros selecionados.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Help Modal -->
                <div class="modal" id="helpModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Ajuda - Controle de Comissões</h2>
                            <span class="close-modal" id="closeHelpBtn">&times;</span>
                        </div>
                        <div class="modal-body">
                            <p>Utilize esta página para acompanhar o pagamento das comissões de viagens aprovadas:</p>
                            <ul>
                                <li><strong>Filtros:</strong> Defina período, motorista ou veículo para refinar o histórico.</li>
                                <li><strong>Histórico:</strong> Use os botões de ação para marcar cada comissão como paga ou pendente.</li>
                                <li><strong>Dashboards:</strong> Analise a distribuição de pagamentos, a evolução mensal e o ranking dos motoristas.</li>
                                <li><strong>Exportação:</strong> Gere um CSV com os resultados filtrados para controle externo.</li>
                            </ul>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-secondary" id="closeHelpFooterBtn">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusData = <?php echo json_encode($chartStatusData); ?>;
            const monthlyData = <?php echo json_encode($chartMonthlyData); ?>;
            const motoristaData = <?php echo json_encode($chartMotoristaData); ?>;

            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            const currentPage = <?php echo $pagina_atual; ?>;
            const totalPages = <?php echo $total_paginas; ?>;

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    if (!prevBtn.disabled && currentPage > 1) {
                        const params = new URLSearchParams(window.location.search);
                        params.set('page', currentPage - 1);
                        window.location.search = params.toString();
                    }
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    if (!nextBtn.disabled && currentPage < totalPages) {
                        const params = new URLSearchParams(window.location.search);
                        params.set('page', currentPage + 1);
                        window.location.search = params.toString();
                    }
                });
            }

            const exportBtn = document.getElementById('exportCsvBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    const params = new URLSearchParams(window.location.search);
                    params.set('export', 'csv');
                    window.location.href = `comissoes_export.php?${params.toString()}`;
                });
            }

            const filterBtn = document.getElementById('filterBtn');
            const filterSection = document.querySelector('.filter-section');
            if (filterBtn && filterSection) {
                filterBtn.addEventListener('click', function () {
                    filterSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    filterSection.classList.add('highlight-filter');
                    setTimeout(() => filterSection.classList.remove('highlight-filter'), 1000);
                });
            }

            const helpBtn = document.getElementById('helpBtn');
            const helpModal = document.getElementById('helpModal');
            const closeHelpBtn = document.getElementById('closeHelpBtn');
            const closeHelpFooterBtn = document.getElementById('closeHelpFooterBtn');

            const closeHelpModal = () => {
                if (helpModal) {
                    helpModal.style.display = 'none';
                }
            };

            if (helpBtn && helpModal) {
                helpBtn.addEventListener('click', function () {
                    helpModal.style.display = 'block';
                });
            }

            if (closeHelpBtn) {
                closeHelpBtn.addEventListener('click', closeHelpModal);
            }
            if (closeHelpFooterBtn) {
                closeHelpFooterBtn.addEventListener('click', closeHelpModal);
            }

            window.addEventListener('click', function (event) {
                if (event.target === helpModal) {
                    closeHelpModal();
                }
            });

            function handleCommissionAction(rotaId, action) {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('rota_id', rotaId);

                fetch('../api/commissions_actions.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.error || 'Não foi possível atualizar o status da comissão.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar comissão:', error);
                    alert('Erro ao atualizar comissão.');
                });
            }

            document.querySelectorAll('.mark-paid-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const rotaId = this.getAttribute('data-rota-id');
                    handleCommissionAction(rotaId, 'mark_paid');
                });
            });

            document.querySelectorAll('.mark-pending-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const rotaId = this.getAttribute('data-rota-id');
                    handleCommissionAction(rotaId, 'mark_pending');
                });
            });

            // Charts
            let statusChartInstance = null;
            let monthlyChartInstance = null;
            let motoristaChartInstance = null;

            function initStatusChart() {
                const statusCanvas = document.getElementById('statusChart');
                if (!statusCanvas || !statusData || !(statusData.values || []).some(v => v > 0)) {
                    return;
                }

                if (statusChartInstance) {
                    statusChartInstance.destroy();
                }

                statusChartInstance = new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: statusData.labels,
                        datasets: [{
                            data: statusData.values,
                            backgroundColor: ['#2ecc71', '#f39c12'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = context.raw || 0;
                                        return `${context.label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function initMonthlyChart() {
                const monthlyCanvas = document.getElementById('monthlyChart');
                if (!monthlyCanvas || !monthlyData || !monthlyData.labels || !monthlyData.labels.length) {
                    return;
                }

                if (monthlyChartInstance) {
                    monthlyChartInstance.destroy();
                }

                monthlyChartInstance = new Chart(monthlyCanvas, {
                    type: 'line',
                    data: {
                        labels: monthlyData.labels,
                        datasets: [
                            {
                                label: 'Total',
                                data: monthlyData.total,
                                borderColor: '#3498db',
                                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                fill: false,
                                tension: 0.3
                            },
                            {
                                label: 'Pagas',
                                data: monthlyData.pagas,
                                borderColor: '#2ecc71',
                                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                                fill: false,
                                tension: 0.3
                            },
                            {
                                label: 'Pendentes',
                                data: monthlyData.pendentes,
                                borderColor: '#e67e22',
                                backgroundColor: 'rgba(230, 126, 34, 0.1)',
                                fill: false,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function (value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = context.raw || 0;
                                        return `${context.dataset.label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                                    }
                                }
                            },
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            function initMotoristaChart() {
                const motoristaCanvas = document.getElementById('motoristaChart');
                if (!motoristaCanvas || !motoristaData || !motoristaData.labels || !motoristaData.labels.length) {
                    return;
                }

                if (motoristaChartInstance) {
                    motoristaChartInstance.destroy();
                }

                motoristaChartInstance = new Chart(motoristaCanvas, {
                    type: 'bar',
                    data: {
                        labels: motoristaData.labels,
                        datasets: [
                            {
                                label: 'Total',
                                data: motoristaData.totais,
                                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                                borderColor: '#3498db',
                                borderWidth: 1
                            },
                            {
                                label: 'Pagas',
                                data: motoristaData.pagas,
                                backgroundColor: 'rgba(46, 204, 113, 0.7)',
                                borderColor: '#2ecc71',
                                borderWidth: 1
                            },
                            {
                                label: 'Pendentes',
                                data: motoristaData.pendentes,
                                backgroundColor: 'rgba(230, 126, 34, 0.7)',
                                borderColor: '#e67e22',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function (value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = context.raw || 0;
                                        return `${context.dataset.label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            setTimeout(() => {
                initStatusChart();
                initMonthlyChart();
                initMotoristaChart();
            }, 0);
        });
    </script>
</body>
</html>

