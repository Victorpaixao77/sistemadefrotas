<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Lucratividade";

// Obter conexão com o banco de dados
$conn = getConnection();

// Validar empresa_id
if (!isset($_SESSION['empresa_id'])) {
    throw new Exception("Empresa não identificada");
}
$empresa_id = $_SESSION['empresa_id'];

// Obter parâmetros de mês e ano
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y');

// Buscar dados para os KPIs
$sql_kpis = "
SELECT 
    DATE_FORMAT(r.data_rota, '%Y-%m') AS mes_ano,
    
    -- Receitas
    COALESCE(SUM(r.frete), 0) AS total_frete,
    COALESCE(SUM(r.comissao), 0) AS total_comissao,
    
    -- Despesas de viagem
    COALESCE(SUM(dv.total_despviagem), 0) AS total_despesas_viagem,
    
    -- Total de abastecimentos
    (
        SELECT COALESCE(SUM(valor_total), 0)
        FROM abastecimentos
        WHERE 
            empresa_id = r.empresa_id
            AND YEAR(data_abastecimento) = YEAR(r.data_rota)
            AND MONTH(data_abastecimento) = MONTH(r.data_rota)
    ) AS total_abastecimentos,
    
    -- Despesas fixas pagas no mês
    (
        SELECT COALESCE(SUM(df.valor), 0)
        FROM despesas_fixas df
        WHERE 
            df.empresa_id = r.empresa_id
            AND df.status_pagamento_id = 2
            AND YEAR(df.data_pagamento) = YEAR(r.data_rota)
            AND MONTH(df.data_pagamento) = MONTH(r.data_rota)
    ) AS total_despesas_fixas,
    
    -- Parcelas de financiamento pagas no mês
    (
        SELECT COALESCE(SUM(pf.valor), 0)
        FROM parcelas_financiamento pf
        WHERE 
            pf.empresa_id = r.empresa_id
            AND pf.status_id = 2
            AND YEAR(pf.data_pagamento) = YEAR(r.data_rota)
            AND MONTH(pf.data_pagamento) = MONTH(r.data_rota)
    ) AS total_parcelas_financiamento,
    
    -- Contas pagas no mês
    (
        SELECT COALESCE(SUM(cp.valor), 0)
        FROM contas_pagar cp
        WHERE 
            cp.empresa_id = r.empresa_id
            AND cp.status_id = 2
            AND YEAR(cp.data_pagamento) = YEAR(r.data_rota)
            AND MONTH(cp.data_pagamento) = MONTH(r.data_rota)
    ) AS total_contas_pagas,
    
    -- Manutenções de veículos no mês
    (
        SELECT COALESCE(SUM(m.valor), 0)
        FROM manutencoes m
        WHERE 
            m.empresa_id = r.empresa_id
            AND YEAR(m.data_manutencao) = YEAR(r.data_rota)
            AND MONTH(m.data_manutencao) = MONTH(r.data_rota)
    ) AS total_manutencoes,
    
    -- Manutenção de pneus no mês
    (
        SELECT COALESCE(SUM(pm.custo), 0)
        FROM pneu_manutencao pm
        WHERE 
            pm.empresa_id = r.empresa_id
            AND YEAR(pm.data_manutencao) = YEAR(r.data_rota)
            AND MONTH(pm.data_manutencao) = MONTH(r.data_rota)
    ) AS total_pneu_manutencao,
    
    -- Cálculo do lucro
    (
        COALESCE(SUM(r.frete), 0)
        - COALESCE(SUM(r.comissao), 0)
        - COALESCE(SUM(dv.total_despviagem), 0)
        - (
            SELECT COALESCE(SUM(valor_total), 0)
            FROM abastecimentos
            WHERE 
                empresa_id = r.empresa_id
                AND YEAR(data_abastecimento) = YEAR(r.data_rota)
                AND MONTH(data_abastecimento) = MONTH(r.data_rota)
        )
        - (
            SELECT COALESCE(SUM(df.valor), 0)
            FROM despesas_fixas df
            WHERE 
                df.empresa_id = r.empresa_id
                AND df.status_pagamento_id = 2
                AND YEAR(df.data_pagamento) = YEAR(r.data_rota)
                AND MONTH(df.data_pagamento) = MONTH(r.data_rota)
        )
        - (
            SELECT COALESCE(SUM(pf.valor), 0)
            FROM parcelas_financiamento pf
            WHERE 
                pf.empresa_id = r.empresa_id
                AND pf.status_id = 2
                AND YEAR(pf.data_pagamento) = YEAR(r.data_rota)
                AND MONTH(pf.data_pagamento) = MONTH(r.data_rota)
        )
        - (
            SELECT COALESCE(SUM(cp.valor), 0)
            FROM contas_pagar cp
            WHERE 
                cp.empresa_id = r.empresa_id
                AND cp.status_id = 2
                AND YEAR(cp.data_pagamento) = YEAR(r.data_rota)
                AND MONTH(cp.data_pagamento) = MONTH(r.data_rota)
        )
        - (
            SELECT COALESCE(SUM(m.valor), 0)
            FROM manutencoes m
            WHERE 
                m.empresa_id = r.empresa_id
                AND YEAR(m.data_manutencao) = YEAR(r.data_rota)
                AND MONTH(m.data_manutencao) = MONTH(r.data_rota)
        )
        - (
            SELECT COALESCE(SUM(pm.custo), 0)
            FROM pneu_manutencao pm
            WHERE 
                pm.empresa_id = r.empresa_id
                AND YEAR(pm.data_manutencao) = YEAR(r.data_rota)
                AND MONTH(pm.data_manutencao) = MONTH(r.data_rota)
        )
    ) AS lucro_liquido
FROM rotas r
LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id
WHERE r.empresa_id = " . intval($empresa_id) . "
  AND MONTH(r.data_rota) = " . intval($mes) . "
  AND YEAR(r.data_rota) = " . intval($ano) . "
";

// Executar a query
try {
    error_log("=== INÍCIO DA EXECUÇÃO DA QUERY KPIs ===");
    error_log("SQL Query: " . $sql_kpis);
    error_log("Valor de empresa_id: " . $empresa_id);
    
    $stmt = $conn->prepare($sql_kpis);
    error_log("Statement preparado com sucesso");
    
    $stmt->execute();
    error_log("Query executada com sucesso");
    
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Resultado KPIs: " . print_r($kpis, true));
    error_log("=== FIM DA EXECUÇÃO DA QUERY KPIs ===");
} catch (Exception $e) {
    error_log("=== ERRO NA EXECUÇÃO DA QUERY KPIs ===");
    error_log("Mensagem de erro: " . $e->getMessage());
    error_log("Código do erro: " . $e->getCode());
    error_log("Arquivo: " . $e->getFile());
    error_log("Linha: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("=== FIM DO LOG DE ERRO ===");
    throw $e;
}

// Calcular Lucratividade
$lucro = isset($kpis) && is_array($kpis) && isset($kpis['lucro_liquido']) ? $kpis['lucro_liquido'] : 0;

// Executar consultas
$params = [
    ':empresa_id' => $empresa_id,
    ':mes' => $mes,
    ':ano' => $ano
];

// Verificar se todos os parâmetros necessários estão definidos
$required_params = [':empresa_id', ':mes', ':ano'];

foreach ($required_params as $param) {
    if (!isset($params[$param])) {
        error_log("Parâmetro faltando: " . $param);
        throw new Exception("Parâmetro obrigatório não definido: " . $param);
    }
}

// Queries para os gráficos
$sql_fretes_vs_despesas = "
    WITH dados AS (
        SELECT 
            MONTH(data) as mes,
            YEAR(data) as ano,
            SUM(CASE WHEN tipo = 'frete' THEN valor ELSE 0 END) as fretes,
            SUM(CASE WHEN tipo != 'frete' THEN valor ELSE 0 END) as despesas
        FROM (
            SELECT data_rota as data, 'frete' as tipo, frete as valor FROM rotas WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT created_at as data, 'despesa' as tipo, 
                   (COALESCE(arla, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                    COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                    COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
            FROM despesas_viagem WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT vencimento as data, 'despesa' as tipo, valor FROM despesas_fixas 
            WHERE empresa_id = " . intval($empresa_id) . " AND status_pagamento_id = 2
            UNION ALL
            SELECT data_vencimento as data, 'despesa' as tipo, valor FROM contas_pagar 
            WHERE empresa_id = " . intval($empresa_id) . "
        ) as dados
        GROUP BY YEAR(data), MONTH(data)
        ORDER BY ano, mes
    )
    SELECT * FROM dados
    WHERE (ano < " . intval($ano) . ") OR (ano = " . intval($ano) . " AND mes <= " . intval($mes) . ")
    ORDER BY ano, mes
    LIMIT 12
";

$sql_distribuicao_despesas = "
    SELECT 
        tipo,
        SUM(valor) as total
    FROM (
        SELECT 'Abastecimento' as tipo, valor_total as valor FROM abastecimentos WHERE empresa_id = " . intval($empresa_id) . "
        UNION ALL
        SELECT 'Comissão' as tipo, comissao as valor FROM rotas WHERE empresa_id = " . intval($empresa_id) . "
        UNION ALL
        SELECT 'Despesa Fixa' as tipo, valor FROM despesas_fixas WHERE empresa_id = " . intval($empresa_id) . " AND status_pagamento_id = 2
        UNION ALL
        SELECT 'Conta a Pagar' as tipo, valor FROM contas_pagar WHERE empresa_id = " . intval($empresa_id) . "
        UNION ALL
        SELECT 'Despesa de Viagem' as tipo, 
               (COALESCE(arla, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
        FROM despesas_viagem WHERE empresa_id = " . intval($empresa_id) . "
    ) as despesas
    GROUP BY tipo
    ORDER BY total DESC
";

$sql_evolucao_lucratividade = "
    WITH dados AS (
        SELECT 
            MONTH(data) as mes,
            YEAR(data) as ano,
            SUM(CASE WHEN tipo = 'frete' THEN valor ELSE -valor END) as lucro
        FROM (
            SELECT data_rota as data, 'frete' as tipo, frete as valor FROM rotas WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT created_at as data, 'despesa' as tipo, 
                   (COALESCE(arla, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                    COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                    COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
            FROM despesas_viagem WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT vencimento as data, 'despesa' as tipo, valor FROM despesas_fixas 
            WHERE empresa_id = " . intval($empresa_id) . " AND status_pagamento_id = 2
            UNION ALL
            SELECT data_vencimento as data, 'despesa' as tipo, valor FROM contas_pagar 
            WHERE empresa_id = " . intval($empresa_id) . "
        ) as dados
        GROUP BY YEAR(data), MONTH(data)
        ORDER BY ano, mes
    )
    SELECT * FROM dados
    WHERE (ano < " . intval($ano) . ") OR (ano = " . intval($ano) . " AND mes <= " . intval($mes) . ")
    ORDER BY ano, mes
    LIMIT 12
";

$sql_composicao_frete = "
    WITH dados AS (
        SELECT 
            MONTH(data) as mes,
            YEAR(data) as ano,
            SUM(CASE WHEN tipo = 'comissao' THEN valor ELSE 0 END) as comissoes,
            SUM(CASE WHEN tipo = 'abastecimento' THEN valor ELSE 0 END) as abastecimentos,
            SUM(CASE WHEN tipo NOT IN ('comissao', 'abastecimento') THEN valor ELSE 0 END) as outras_despesas
        FROM (
            SELECT data_rota as data, 'comissao' as tipo, comissao as valor FROM rotas WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT data_abastecimento as data, 'abastecimento' as tipo, valor_total as valor FROM abastecimentos WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT created_at as data, 'outra' as tipo, 
                   (COALESCE(arla, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                    COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                    COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
            FROM despesas_viagem WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT vencimento as data, 'outra' as tipo, valor FROM despesas_fixas 
            WHERE empresa_id = " . intval($empresa_id) . " AND status_pagamento_id = 2
            UNION ALL
            SELECT data_vencimento as data, 'outra' as tipo, valor FROM contas_pagar 
            WHERE empresa_id = " . intval($empresa_id) . "
        ) as dados
        GROUP BY YEAR(data), MONTH(data)
        ORDER BY ano, mes
    )
    SELECT * FROM dados
    WHERE (ano < " . intval($ano) . ") OR (ano = " . intval($ano) . " AND mes <= " . intval($mes) . ")
    ORDER BY ano, mes
    LIMIT 12
";

$sql_lucro_por_veiculo = "
    WITH dados AS (
        SELECT 
            v.placa,
            SUM(CASE WHEN tipo = 'faturamento' THEN valor ELSE 0 END) as faturamento,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as despesas
        FROM (
            SELECT 
                r.veiculo_id,
                'faturamento' as tipo,
                r.frete as valor,
                r.data_rota as data
            FROM rotas r
            WHERE r.empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT 
                a.veiculo_id,
                'despesa' as tipo,
                a.valor_total as valor,
                a.data_abastecimento as data
            FROM abastecimentos a
            WHERE a.empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT 
                r.veiculo_id,
                'despesa' as tipo,
                (COALESCE(dv.arla, 0) + COALESCE(dv.pedagios, 0) + COALESCE(dv.caixinha, 0) + 
                 COALESCE(dv.estacionamento, 0) + COALESCE(dv.lavagem, 0) + COALESCE(dv.borracharia, 0) + 
                 COALESCE(dv.eletrica_mecanica, 0) + COALESCE(dv.adiantamento, 0)) as valor,
                dv.created_at as data
            FROM despesas_viagem dv
            JOIN rotas r ON r.id = dv.rota_id
            WHERE dv.empresa_id = " . intval($empresa_id) . "
        ) as dados
        JOIN veiculos v ON v.id = dados.veiculo_id
        WHERE (YEAR(data) < " . intval($ano) . ") OR (YEAR(data) = " . intval($ano) . " AND MONTH(data) <= " . intval($mes) . ")
        GROUP BY v.placa
    )
    SELECT 
        placa,
        faturamento,
        despesas,
        (faturamento - despesas) as lucro
    FROM dados
    ORDER BY lucro DESC
    LIMIT 10
";

$sql_lucro_por_dia = "
    WITH dados AS (
        SELECT 
            DATE(data) as data,
            SUM(CASE WHEN tipo = 'faturamento' THEN valor ELSE 0 END) as faturamento,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as despesas
        FROM (
            SELECT 
                data_rota as data,
                'faturamento' as tipo,
                frete as valor
            FROM rotas 
            WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT 
                data_abastecimento as data,
                'despesa' as tipo,
                valor_total as valor
            FROM abastecimentos 
            WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT 
                created_at as data,
                'despesa' as tipo,
                (COALESCE(arla, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                 COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                 COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor
            FROM despesas_viagem 
            WHERE empresa_id = " . intval($empresa_id) . "
            UNION ALL
            SELECT 
                vencimento as data,
                'despesa' as tipo,
                valor
            FROM despesas_fixas 
            WHERE empresa_id = " . intval($empresa_id) . " AND status_pagamento_id = 2
            UNION ALL
            SELECT 
                data_vencimento as data,
                'despesa' as tipo,
                valor
            FROM contas_pagar 
            WHERE empresa_id = " . intval($empresa_id) . "
        ) as dados
        WHERE (YEAR(data) < " . intval($ano) . ") OR (YEAR(data) = " . intval($ano) . " AND MONTH(data) <= " . intval($mes) . ")
        GROUP BY DATE(data)
    )
    SELECT 
        data,
        faturamento,
        despesas,
        (faturamento - despesas) as lucro
    FROM dados
    ORDER BY data
";

// Executar cada query individualmente para identificar qual está causando o erro
try {
    error_log("=== INÍCIO DA EXECUÇÃO DA QUERY Fretes vs Despesas ===");
    error_log("SQL Query: " . $sql_fretes_vs_despesas);
    
    $stmt = $conn->prepare($sql_fretes_vs_despesas);
    error_log("Statement preparado com sucesso");
    
    $stmt->execute();
    error_log("Query executada com sucesso");
    
    $fretes_vs_despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Fretes vs Despesas: " . print_r($fretes_vs_despesas, true));
    error_log("=== FIM DA EXECUÇÃO DA QUERY Fretes vs Despesas ===");
} catch (Exception $e) {
    error_log("=== ERRO NA EXECUÇÃO DA QUERY Fretes vs Despesas ===");
    error_log("Mensagem de erro: " . $e->getMessage());
    error_log("Código do erro: " . $e->getCode());
    error_log("Arquivo: " . $e->getFile());
    error_log("Linha: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("=== FIM DO LOG DE ERRO ===");
    throw $e;
}

try {
    error_log("Executando query Distribuição Despesas...");
    $stmt = $conn->prepare($sql_distribuicao_despesas);
    $stmt->execute();
    $distribuicao_despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Distribuição Despesas: " . print_r($distribuicao_despesas, true));
} catch (Exception $e) {
    error_log("Erro na query Distribuição Despesas: " . $e->getMessage());
    throw $e;
}

try {
    error_log("Executando query Evolução Lucratividade...");
    $stmt = $conn->prepare($sql_evolucao_lucratividade);
    $stmt->execute();
    $evolucao_lucratividade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Evolução Lucratividade: " . print_r($evolucao_lucratividade, true));
} catch (Exception $e) {
    error_log("Erro na query Evolução Lucratividade: " . $e->getMessage());
    throw $e;
}

try {
    error_log("Executando query Composição Frete...");
    $stmt = $conn->prepare($sql_composicao_frete);
    $stmt->execute();
    $composicao_frete = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Composição Frete: " . print_r($composicao_frete, true));
} catch (Exception $e) {
    error_log("Erro na query Composição Frete: " . $e->getMessage());
    throw $e;
}

try {
    error_log("Executando query Lucro por Veículo...");
    $stmt = $conn->prepare($sql_lucro_por_veiculo);
    $stmt->execute();
    $lucro_por_veiculo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Lucro por Veículo: " . print_r($lucro_por_veiculo, true));
} catch (Exception $e) {
    error_log("Erro na query Lucro por Veículo: " . $e->getMessage());
    throw $e;
}

try {
    error_log("Executando query Lucro por Dia...");
    $stmt = $conn->prepare($sql_lucro_por_dia);
    $stmt->execute();
    $lucro_por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Lucro por Dia: " . print_r($lucro_por_dia, true));
} catch (Exception $e) {
    error_log("Erro na query Lucro por Dia: " . $e->getMessage());
    throw $e;
}
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
</head>
<body>
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1 class="mb-0"><i class="fas fa-chart-line"></i> <?php echo $page_title; ?></h1>
                <div class="dashboard-actions">
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
            <!-- Dashboard KPIs -->
            <div class="dashboard-grid mb-4">
                <div class="dashboard-card" style="background: #e6f9ed; border: 2px solid #2ecc40;">
                    <div class="card-header">
                        <h3 style="color: #218838;">Lucro Líquido</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: #218838; font-size: 2rem; font-weight: bold;">R$ <?= number_format($lucro, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Lucro do período</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Total de Fretes</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_frete']) && $kpis['total_frete'] !== null ? $kpis['total_frete'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Receita bruta</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Total de Comissões</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_comissao']) && $kpis['total_comissao'] !== null ? $kpis['total_comissao'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pagas a motoristas</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Despesas de Viagem</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_despesas_viagem']) && $kpis['total_despesas_viagem'] !== null ? $kpis['total_despesas_viagem'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Custos variáveis</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Abastecimentos</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_abastecimentos']) && $kpis['total_abastecimentos'] !== null ? $kpis['total_abastecimentos'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Combustível</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Despesas Fixas</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_despesas_fixas']) && $kpis['total_despesas_fixas'] !== null ? $kpis['total_despesas_fixas'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pagas no mês</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Parcelas de Financiamento</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_parcelas_financiamento']) && $kpis['total_parcelas_financiamento'] !== null ? $kpis['total_parcelas_financiamento'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pagas no mês</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Contas Pagas</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_contas_pagas']) && $kpis['total_contas_pagas'] !== null ? $kpis['total_contas_pagas'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Outros pagamentos</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Manutenções de Veículos</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_manutencoes']) && $kpis['total_manutencoes'] !== null ? $kpis['total_manutencoes'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Veículos</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Manutenções de Pneus</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_pneu_manutencao']) && $kpis['total_pneu_manutencao'] !== null ? $kpis['total_pneu_manutencao'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pneus</span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Resumo Financeiro -->
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="card-title mb-3">Resumo Financeiro</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>Total de Fretes</td><td class="text-end">R$ <?= number_format(isset($kpis['total_frete']) && $kpis['total_frete'] !== null ? $kpis['total_frete'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Total de Comissões</td><td class="text-end">R$ <?= number_format(isset($kpis['total_comissao']) && $kpis['total_comissao'] !== null ? $kpis['total_comissao'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Despesas de Viagem</td><td class="text-end">R$ <?= number_format(isset($kpis['total_despesas_viagem']) && $kpis['total_despesas_viagem'] !== null ? $kpis['total_despesas_viagem'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Abastecimentos</td><td class="text-end">R$ <?= number_format(isset($kpis['total_abastecimentos']) && $kpis['total_abastecimentos'] !== null ? $kpis['total_abastecimentos'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Despesas Fixas</td><td class="text-end">R$ <?= number_format(isset($kpis['total_despesas_fixas']) && $kpis['total_despesas_fixas'] !== null ? $kpis['total_despesas_fixas'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Parcelas de Financiamento</td><td class="text-end">R$ <?= number_format(isset($kpis['total_parcelas_financiamento']) && $kpis['total_parcelas_financiamento'] !== null ? $kpis['total_parcelas_financiamento'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Contas Pagas</td><td class="text-end">R$ <?= number_format(isset($kpis['total_contas_pagas']) && $kpis['total_contas_pagas'] !== null ? $kpis['total_contas_pagas'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Manutenções de Veículos</td><td class="text-end">R$ <?= number_format(isset($kpis['total_manutencoes']) && $kpis['total_manutencoes'] !== null ? $kpis['total_manutencoes'] : 0, 2, ',', '.') ?></td></tr>
                                <tr><td>Manutenções de Pneus</td><td class="text-end">R$ <?= number_format(isset($kpis['total_pneu_manutencao']) && $kpis['total_pneu_manutencao'] !== null ? $kpis['total_pneu_manutencao'] : 0, 2, ',', '.') ?></td></tr>
                                <tr class="table-primary"><td><strong>Lucro Líquido</strong></td><td class="text-end"><strong>R$ <?= number_format($lucro, 2, ',', '.') ?></strong></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Custo por KM -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">Custo Médio por KM Rodado</h4>
                    <p class="card-subtitle text-muted">Evolução do custo médio por quilômetro rodado no mês atual</p>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:400px;">
                        <canvas id="costPerKmChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Eficiência Operacional -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">Eficiência Operacional</h4>
                    <p class="card-subtitle text-muted">Lucro Líquido por KM Rodado</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="gauge-container" style="position: relative; height:300px;">
                                <canvas id="profitPerKmGauge"></canvas>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="profitPerKmLine"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Projeção de Lucro -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">Projeção de Lucro</h4>
                    <p class="card-subtitle text-muted">Histórico e projeção para os próximos 3 meses</p>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:400px;">
                        <canvas id="profitForecastChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <!-- Filter Modal -->
    <div class="modal" id="filterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtros</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="filterMonth">Mês/Ano</label>
                        <input type="month" id="filterMonth" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary">Limpar</button>
                <button id="applyFilterBtn" class="btn-primary">Aplicar</button>
            </div>
        </div>
    </div>

    <script>
    // Elementos do DOM
    const filterBtn = document.getElementById('filterBtn');
    const filterModal = document.getElementById('filterModal');
    const closeBtn = filterModal.querySelector('.close-modal');
    const filterMonth = document.getElementById('filterMonth');
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    const applyFilterBtn = document.getElementById('applyFilterBtn');

    // Definir valor inicial do filtro
    const urlParams = new URLSearchParams(window.location.search);
    const mes = urlParams.get('mes');
    const ano = urlParams.get('ano');
    if (mes && ano) {
        filterMonth.value = `${ano}-${mes.padStart(2, '0')}`;
    } else {
        const currentDate = new Date();
        filterMonth.value = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`;
    }

    // Abrir modal
    filterBtn.addEventListener('click', () => {
        filterModal.style.display = 'block';
    });

    // Fechar modal
    closeBtn.addEventListener('click', () => {
        filterModal.style.display = 'none';
    });

    // Fechar modal ao clicar fora
    window.addEventListener('click', (e) => {
        if (e.target === filterModal) {
            filterModal.style.display = 'none';
        }
    });

    // Limpar filtro
    clearFilterBtn.addEventListener('click', () => {
        filterMonth.value = '';
        window.location.href = window.location.pathname;
    });

    // Aplicar filtro
    applyFilterBtn.addEventListener('click', () => {
        const monthYear = filterMonth.value;
        if (monthYear) {
            const [year, month] = monthYear.split('-');
            window.location.href = `?mes=${month}&ano=${year}`;
        } else {
            window.location.href = window.location.pathname;
        }
    });

    // Configuração do gráfico de custo por KM
    document.addEventListener('DOMContentLoaded', function() {
        const costPerKmCtx = document.getElementById('costPerKmChart').getContext('2d');
        
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Get month and year from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const mes = urlParams.get('mes');
        const ano = urlParams.get('ano');
        
        // Build API URL with parameters
        let costPerKmUrl = '../api/cost_per_km_analytics.php';
        if (mes && ano) {
            costPerKmUrl += `?mes=${mes}&ano=${ano}`;
        }
        
        // Carregar dados do gráfico de custo por KM
        fetch(costPerKmUrl)
            .then(response => response.json())
            .then(data => {
                new Chart(costPerKmCtx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados do gráfico de custo por KM:', error);
                costPerKmCtx.canvas.parentNode.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do gráfico</div>';
            });
    });

    // Configuração do gráfico de eficiência operacional
    document.addEventListener('DOMContentLoaded', function() {
        // Registrar o plugin datalabels
        Chart.register(ChartDataLabels);
        
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Get month and year from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const mes = urlParams.get('mes');
        const ano = urlParams.get('ano');
        
        // Build API URL with parameters
        let profitPerKmUrl = '../api/profit_per_km_analytics.php';
        if (mes && ano) {
            profitPerKmUrl += `?mes=${mes}&ano=${ano}`;
        }
        
        // Carregar dados do gráfico de eficiência operacional
        fetch(profitPerKmUrl)
            .then(response => response.json())
            .then(data => {
                // Configurar gráfico gauge (usando doughnut como base)
                const gaugeCtx = document.getElementById('profitPerKmGauge').getContext('2d');
                new Chart(gaugeCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [
                                data.gauge.value - data.gauge.min,
                                data.gauge.max - data.gauge.value
                            ],
                            backgroundColor: [
                                data.gauge.value < data.gauge.thresholds.red ? '#e74c3c' :
                                data.gauge.value < data.gauge.thresholds.yellow ? '#f1c40f' : '#2ecc40',
                                '#f8f9fa'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        circumference: 180,
                        rotation: -90,
                        cutout: '80%',
                        plugins: {
                            title: {
                                display: true,
                                text: 'Lucro por KM (Atual)',
                                fontSize: 16,
                                padding: {
                                    bottom: 30
                                }
                            },
                            datalabels: {
                                display: true,
                                formatter: function(value, context) {
                                    if (context.dataIndex === 0) {
                                        return formatCurrency(data.gauge.value);
                                    }
                                    return '';
                                },
                                color: '#000',
                                font: {
                                    weight: 'bold',
                                    size: 16
                                }
                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                
                // Configurar gráfico de linha
                const lineCtx = document.getElementById('profitPerKmLine').getContext('2d');
                new Chart(lineCtx, {
                    type: 'line',
                    data: data.line,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de eficiência operacional:', error);
                document.getElementById('profitPerKmGauge').parentNode.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do gráfico gauge</div>';
                document.getElementById('profitPerKmLine').parentNode.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do gráfico de linha</div>';
            });
    });

    // Configuração do gráfico de projeção de lucro
    document.addEventListener('DOMContentLoaded', function() {
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Carregar dados do gráfico de projeção
        fetch('../api/profit_forecast_analytics.php')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('profitForecastChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de projeção:', error);
                document.getElementById('profitForecastChart').parentNode.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados do gráfico de projeção</div>';
            });
    });
    </script>
</body>
</html> 