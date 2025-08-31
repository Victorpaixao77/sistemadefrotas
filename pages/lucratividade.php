<?php
// Include configuration and functions first
$required_files = [
    '../includes/config.php',
    '../includes/functions.php',
    '../includes/db_connect.php'
];

// Verificar se todos os arquivos necessários existem
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        error_log("Arquivo não encontrado: " . $file);
        http_response_code(500);
        die('Erro interno do servidor');
    }
}

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check authentication - usar o padrão correto do sistema
require_authentication();

// Set page title
$page_title = "Lucratividade - Dashboard Inteligente";

// Obter empresa_id da sessão (já validada pelo require_authentication)
$empresa_id = $_SESSION['empresa_id'];

// Obter conexão com o banco de dados
try {
    $conn = getConnection();
} catch (Exception $e) {
    error_log("Erro de conexão com banco: " . $e->getMessage());
    if (DEBUG_MODE) {
        throw $e;
    } else {
        http_response_code(500);
        die('Erro interno do servidor');
    }
}

// Obter e validar parâmetros de mês e ano
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

// Validar range dos parâmetros
if ($mes < 1 || $mes > 12) {
    $mes = date('m');
}
if ($ano < 2020 || $ano > 2030) {
    $ano = date('Y');
}

// Função otimizada para buscar KPIs (nova função do improved)
function getKPIsOptimized($conn, $empresa_id, $mes, $ano) {
    try {
        // Query otimizada usando JOINs em vez de subconsultas
        $sql = "
        SELECT 
            DATE_FORMAT(r.data_rota, '%Y-%m') AS mes_ano,
            
            -- Receitas
            COALESCE(SUM(r.frete), 0) AS total_frete,
            COALESCE(SUM(r.comissao), 0) AS total_comissao,
            
            -- Despesas de viagem
            COALESCE(SUM(dv.total_despviagem), 0) AS total_despesas_viagem,
            
            -- Abastecimentos
            COALESCE(SUM(a.valor_total), 0) AS total_abastecimentos,
            
            -- Despesas fixas
            COALESCE(SUM(df.valor), 0) AS total_despesas_fixas,
            
            -- Parcelas de financiamento
            COALESCE(SUM(pf.valor), 0) AS total_parcelas_financiamento,
            
            -- Contas pagas
            COALESCE(SUM(cp.valor), 0) AS total_contas_pagas,
            
            -- Manutenções de veículos
            COALESCE(SUM(m.valor), 0) AS total_manutencoes,
            
            -- Manutenção de pneus
            COALESCE(SUM(pm.custo), 0) AS total_pneu_manutencao,
            
            -- Cálculo do lucro otimizado
            (
                COALESCE(SUM(r.frete), 0)
                - COALESCE(SUM(r.comissao), 0)
                - COALESCE(SUM(dv.total_despviagem), 0)
                - COALESCE(SUM(a.valor_total), 0)
                - COALESCE(SUM(df.valor), 0)
                - COALESCE(SUM(pf.valor), 0)
                - COALESCE(SUM(cp.valor), 0)
                - COALESCE(SUM(m.valor), 0)
                - COALESCE(SUM(pm.custo), 0)
            ) AS lucro_liquido
        FROM rotas r
        LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id
        LEFT JOIN abastecimentos a ON a.veiculo_id = r.veiculo_id 
            AND YEAR(a.data_abastecimento) = YEAR(r.data_rota)
            AND MONTH(a.data_abastecimento) = MONTH(r.data_rota)
        LEFT JOIN despesas_fixas df ON df.empresa_id = r.empresa_id 
            AND df.status_pagamento_id = 2
            AND YEAR(df.data_pagamento) = YEAR(r.data_rota)
            AND MONTH(df.data_pagamento) = MONTH(r.data_rota)
        LEFT JOIN parcelas_financiamento pf ON pf.empresa_id = r.empresa_id 
            AND pf.status_id = 2
            AND YEAR(pf.data_pagamento) = YEAR(r.data_rota)
            AND MONTH(pf.data_pagamento) = MONTH(r.data_rota)
        LEFT JOIN contas_pagar cp ON cp.empresa_id = r.empresa_id 
            AND cp.status_id = 2
            AND YEAR(cp.data_pagamento) = YEAR(r.data_rota)
            AND MONTH(cp.data_pagamento) = MONTH(r.data_rota)
        LEFT JOIN manutencoes m ON m.empresa_id = r.empresa_id 
            AND YEAR(m.data_manutencao) = YEAR(r.data_rota)
            AND MONTH(m.data_manutencao) = MONTH(r.data_rota)
        LEFT JOIN pneu_manutencao pm ON pm.empresa_id = r.empresa_id 
            AND YEAR(pm.data_manutencao) = YEAR(r.data_rota)
            AND MONTH(pm.data_manutencao) = MONTH(r.data_rota)
        WHERE r.empresa_id = :empresa_id
          AND MONTH(r.data_rota) = :mes
          AND YEAR(r.data_rota) = :ano
        GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m')
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            error_log("Erro na função getKPIsOptimized: " . $e->getMessage());
        }
        return null;
    }
}

// Função para buscar alertas inteligentes (nova função do improved)
function getIntelligentAlerts($conn, $empresa_id, $mes, $ano) {
    try {
        $alerts = [];
        
        // Buscar dados do mês atual
        $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
        
        if ($kpis) {
            $lucro = $kpis['lucro_liquido'];
            $receita = $kpis['total_frete'];
            $despesas = $kpis['total_abastecimentos'] + $kpis['total_despesas_viagem'] + 
                       $kpis['total_despesas_fixas'] + $kpis['total_manutencoes'];
            
            // Alerta de margem baixa
            if ($receita > 0) {
                $margem = ($lucro / $receita) * 100;
                if ($margem < 10) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => 'Margem Baixa',
                        'message' => "Margem de lucro está em " . number_format($margem, 1) . "%. Considere revisar custos.",
                        'icon' => 'fas fa-exclamation-triangle'
                    ];
                }
            }
            
            // Alerta de prejuízo
            if ($lucro < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Prejuízo Detectado',
                    'message' => "O mês está fechando com prejuízo de R$ " . number_format(abs($lucro), 2, ',', '.') . ".",
                    'icon' => 'fas fa-times-circle'
                ];
            }
            
            // Alerta de alta despesa com combustível
            if ($kpis['total_abastecimentos'] > $receita * 0.4) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Alto Consumo de Combustível',
                    'message' => "Combustível representa " . number_format(($kpis['total_abastecimentos'] / $receita) * 100, 1) . "% da receita.",
                    'icon' => 'fas fa-gas-pump'
                ];
            }
        }
        
        // Buscar dados do mês anterior para comparação
        $mes_anterior = $mes == 1 ? 12 : $mes - 1;
        $ano_anterior = $mes == 1 ? $ano - 1 : $ano;
        
        $kpis_anterior = getKPIsOptimized($conn, $empresa_id, $mes_anterior, $ano_anterior);
        
        if ($kpis_anterior && $kpis) {
            $crescimento = (($kpis['lucro_liquido'] - $kpis_anterior['lucro_liquido']) / $kpis_anterior['lucro_liquido']) * 100;
            
            if ($crescimento < -20) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Queda na Lucratividade',
                    'message' => "Lucro caiu " . number_format(abs($crescimento), 1) . "% em relação ao mês anterior.",
                    'icon' => 'fas fa-chart-line'
                ];
            } elseif ($crescimento > 20) {
                $alerts[] = [
                    'type' => 'success',
                    'title' => 'Crescimento na Lucratividade',
                    'message' => "Lucro aumentou " . number_format($crescimento, 1) . "% em relação ao mês anterior!",
                    'icon' => 'fas fa-chart-line'
                ];
            }
        }
        
        return $alerts;
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            error_log("Erro na função getIntelligentAlerts: " . $e->getMessage());
        }
        return [];
    }
}

// Buscar dados otimizados (nova implementação)
try {
    $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
    $alerts = getIntelligentAlerts($conn, $empresa_id, $mes, $ano);
    
    // Calcular Lucratividade
    $lucro = isset($kpis) && is_array($kpis) && isset($kpis['lucro_liquido']) ? $kpis['lucro_liquido'] : 0;
    
    // Calcular tendências comparando com mês anterior
    $mes_anterior = $mes == 1 ? 12 : $mes - 1;
    $ano_anterior = $mes == 1 ? $ano - 1 : $ano;
    
    $kpis_anterior = getKPIsOptimized($conn, $empresa_id, $mes_anterior, $ano_anterior);
    
    // Calcular tendências
    $crescimento_lucro = 0;
    $crescimento_receita = 0;
    $variacao_combustivel = 0;
    $tendencias = [];
    
    if ($kpis_anterior && $kpis) {
        // Tendência do lucro
        if ($kpis_anterior['lucro_liquido'] != 0) {
            $crescimento_lucro = (($kpis['lucro_liquido'] - $kpis_anterior['lucro_liquido']) / $kpis_anterior['lucro_liquido']) * 100;
        }
        
        // Tendência da receita
        if ($kpis_anterior['total_frete'] != 0) {
            $crescimento_receita = (($kpis['total_frete'] - $kpis_anterior['total_frete']) / $kpis_anterior['total_frete']) * 100;
        }
        
        // Tendência do combustível (quanto menor, melhor)
        if ($kpis_anterior['total_abastecimentos'] != 0) {
            $variacao_combustivel = (($kpis['total_abastecimentos'] - $kpis_anterior['total_abastecimentos']) / $kpis_anterior['total_abastecimentos']) * 100;
        }
        
        // Calcular tendências para cada item do resumo financeiro
        $items = [
            'total_frete' => 'Total de Fretes',
            'total_comissao' => 'Total de Comissões',
            'total_despesas_viagem' => 'Despesas de Viagem',
            'total_abastecimentos' => 'Abastecimentos',
            'total_despesas_fixas' => 'Despesas Fixas',
            'total_parcelas_financiamento' => 'Parcelas de Financiamento',
            'total_contas_pagas' => 'Contas Pagas',
            'total_manutencoes' => 'Manutenções de Veículos',
            'total_pneu_manutencao' => 'Manutenções de Pneus'
        ];
        
        foreach ($items as $key => $label) {
            $valor_atual = isset($kpis[$key]) ? $kpis[$key] : 0;
            $valor_anterior = isset($kpis_anterior[$key]) ? $kpis_anterior[$key] : 0;
            
            if ($valor_anterior != 0) {
                $tendencia = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
            } else {
                $tendencia = 0;
            }
            
            $tendencias[$key] = $tendencia;
        }
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro ao buscar dados de lucratividade: " . $e->getMessage());
    }
    $kpis = [];
    $alerts = [];
    $lucro = 0;
    $crescimento_lucro = 0;
    $crescimento_receita = 0;
    $variacao_combustivel = 0;
    $tendencias = [];
}

// Buscar dados para os KPIs (manter a query original como backup)
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

// Executar a query original como backup
try {
    if (DEBUG_MODE) {
        error_log("Executando query backup KPIs");
    }
    
    $stmt = $conn->prepare($sql_kpis);
    $stmt->execute();
    
    $kpis_backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Usar dados do backup se os otimizados falharam
    if (!$kpis || empty($kpis)) {
        $kpis = $kpis_backup;
        $lucro = isset($kpis) && is_array($kpis) && isset($kpis['lucro_liquido']) ? $kpis['lucro_liquido'] : 0;
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query backup KPIs: " . $e->getMessage());
    }
    // Continuar com dados vazios se necessário
}

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
        if (DEBUG_MODE) {
            error_log("Parâmetro faltando: " . $param);
        }
        // Definir valores padrão em vez de lançar exceção
        if ($param === ':empresa_id') $params[$param] = 1;
        if ($param === ':mes') $params[$param] = date('m');
        if ($param === ':ano') $params[$param] = date('Y');
    }
}

// Queries para os gráficos - Simplificadas temporariamente
$sql_fretes_vs_despesas = "
        SELECT 
        MONTH(data_rota) as mes,
        YEAR(data_rota) as ano,
        SUM(frete) as fretes,
        0 as despesas
    FROM rotas 
            WHERE empresa_id = " . intval($empresa_id) . "
    AND MONTH(data_rota) = " . intval($mes) . "
    AND YEAR(data_rota) = " . intval($ano) . "
    GROUP BY YEAR(data_rota), MONTH(data_rota)
";

$sql_distribuicao_despesas = "
    SELECT 
        'Abastecimento' as tipo,
        COALESCE(SUM(valor_total), 0) as total
    FROM abastecimentos 
    WHERE empresa_id = " . intval($empresa_id) . "
    AND MONTH(data_abastecimento) = " . intval($mes) . "
    AND YEAR(data_abastecimento) = " . intval($ano) . "
        UNION ALL
    SELECT 
        'Comissao' as tipo,
        COALESCE(SUM(comissao), 0) as total
    FROM rotas 
    WHERE empresa_id = " . intval($empresa_id) . "
    AND MONTH(data_rota) = " . intval($mes) . "
    AND YEAR(data_rota) = " . intval($ano) . "
";

$sql_evolucao_lucratividade = "
        SELECT 
        MONTH(data_rota) as mes,
        YEAR(data_rota) as ano,
        SUM(frete) as lucro
    FROM rotas 
            WHERE empresa_id = " . intval($empresa_id) . "
    AND MONTH(data_rota) = " . intval($mes) . "
    AND YEAR(data_rota) = " . intval($ano) . "
    GROUP BY YEAR(data_rota), MONTH(data_rota)
";

$sql_composicao_frete = "
        SELECT 
        MONTH(data_rota) as mes,
        YEAR(data_rota) as ano,
        SUM(comissao) as comissoes,
        0 as abastecimentos,
        0 as outras_despesas
    FROM rotas 
            WHERE empresa_id = " . intval($empresa_id) . "
    AND MONTH(data_rota) = " . intval($mes) . "
    AND YEAR(data_rota) = " . intval($ano) . "
    GROUP BY YEAR(data_rota), MONTH(data_rota)
";

$sql_lucro_por_veiculo = "
        SELECT 
            v.placa,
        COALESCE(SUM(r.frete), 0) as faturamento,
        0 as despesas,
        COALESCE(SUM(r.frete), 0) as lucro
    FROM veiculos v
    LEFT JOIN rotas r ON r.veiculo_id = v.id
    WHERE v.empresa_id = " . intval($empresa_id) . "
    AND (r.data_rota IS NULL OR (MONTH(r.data_rota) = " . intval($mes) . " AND YEAR(r.data_rota) = " . intval($ano) . "))
        GROUP BY v.placa
    ORDER BY lucro DESC
    LIMIT 10
";

$sql_lucro_por_dia = "
        SELECT 
        DATE(data_rota) as data,
        SUM(frete) as faturamento,
        0 as despesas,
        SUM(frete) as lucro
            FROM rotas 
            WHERE empresa_id = " . intval($empresa_id) . "
    AND MONTH(data_rota) = " . intval($mes) . "
    AND YEAR(data_rota) = " . intval($ano) . "
    GROUP BY DATE(data_rota)
    ORDER BY data
    LIMIT 30
";

// Executar cada query individualmente com tratamento de erro adequado
try {
    if (DEBUG_MODE) {
        error_log("Executando query Fretes vs Despesas");
    }
    
    $stmt = $conn->prepare($sql_fretes_vs_despesas);
    $stmt->execute();
    
    $fretes_vs_despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query Fretes vs Despesas: " . $e->getMessage());
    }
    $fretes_vs_despesas = [];
}

try {
    if (DEBUG_MODE) {
        error_log("Executando query Distribuição Despesas");
    }
    
    $stmt = $conn->prepare($sql_distribuicao_despesas);
    $stmt->execute();
    $distribuicao_despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query Distribuição Despesas: " . $e->getMessage());
    }
    $distribuicao_despesas = [];
}

try {
    if (DEBUG_MODE) {
        error_log("Executando query Evolução Lucratividade");
    }
    
    $stmt = $conn->prepare($sql_evolucao_lucratividade);
    $stmt->execute();
    $evolucao_lucratividade = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query Evolução Lucratividade: " . $e->getMessage());
    }
    $evolucao_lucratividade = [];
}

try {
    if (DEBUG_MODE) {
        error_log("Executando query Composição Frete");
    }
    
    $stmt = $conn->prepare($sql_composicao_frete);
    $stmt->execute();
    $composicao_frete = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query Composição Frete: " . $e->getMessage());
    }
    $composicao_frete = [];
}

try {
    if (DEBUG_MODE) {
        error_log("Executando query Lucro por Veículo");
    }
    
    $stmt = $conn->prepare($sql_lucro_por_veiculo);
    $stmt->execute();
    $lucro_por_veiculo = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query Lucro por Veículo: " . $e->getMessage());
    }
    $lucro_por_veiculo = [];
}

try {
    if (DEBUG_MODE) {
        error_log("Executando query Lucro por Dia");
    }
    
    $stmt = $conn->prepare($sql_lucro_por_dia);
    $stmt->execute();
    $lucro_por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        error_log("Erro na query Lucro por Dia: " . $e->getMessage());
    }
    $lucro_por_dia = [];
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
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">

    <style>
        /* Estilos específicos para os gráficos de lucratividade */
        .analytics-card canvas {
            max-height: 280px !important;
            width: 100% !important;
        }
        
        /* Ajuste para o gráfico gauge */
        #profitPerKmGauge {
            max-height: 250px !important;
        }
        
        /* Responsividade para telas menores */
        @media (max-width: 768px) {
            .analytics-grid {
                grid-template-columns: 1fr !important;
            }
            
            .analytics-card canvas {
                max-height: 250px !important;
            }
        }
        
        /* Estilos para Alertas Inteligentes */
        .alerts-section {
            margin-bottom: 2rem;
        }
        
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .alert-card.alert-success {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f8fff9, #e8f5e8);
        }
        
        .alert-card.alert-warning {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fffdf8, #fef9e7);
        }
        
        .alert-card.alert-danger {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff8f8, #f8e8e8);
        }
        
        .alert-card.alert-info {
            border-left-color: #17a2b8;
            background: linear-gradient(135deg, #f8fdff, #e8f4f8);
        }
        
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .alert-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .alert-success .alert-icon {
            background: #28a745;
        }
        
        .alert-warning .alert-icon {
            background: #ffc107;
            color: #000;
        }
        
        .alert-danger .alert-icon {
            background: #dc3545;
        }
        
        .alert-info .alert-icon {
            background: #17a2b8;
        }
        
        .alert-content h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
        }
        
        .alert-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Estilos para badges na tabela */
        .badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
        
        .bg-success {
            background-color: #198754 !important;
            color: white;
        }
        
        .bg-warning {
            background-color: #ffc107 !important;
            color: #000;
        }
        
        .bg-danger {
            background-color: #dc3545 !important;
            color: white;
        }
        
        .bg-info {
            background-color: #0dcaf0 !important;
            color: #000;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .alerts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Estilos para KPIs Avançados */
        .advanced-kpis {
            margin-bottom: 2rem;
        }
        
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #6610f2);
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .kpi-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #6610f2);
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .kpi-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: #212529;
        }
        
        .kpi-content p {
            margin: 0 0 0.5rem 0;
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .kpi-trend {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .kpi-trend.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .kpi-trend.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .alerts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Espaçamento adicional para tabelas */
        .table-responsive .table th,
        .table-responsive .table td {
            padding: 0.75rem 1rem !important;
        }
        
        .table-responsive .table th {
            padding: 1rem 1.25rem !important;
        }
        
        /* Estilos para tendências */
        .trend-up {
            color: #28a745;
            font-weight: 600;
        }
        
        .trend-down {
            color: #dc3545;
            font-weight: 600;
        }
        
        .trend-neutral {
            color: #6c757d;
            font-weight: 600;
        }
        
        .trend-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .trend-badge.trend-up {
            background-color: #d4edda;
            color: #155724;
        }
        
        .trend-badge.trend-down {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .trend-badge.trend-neutral {
            background-color: #e2e3e5;
            color: #495057;
        }
        
        /* Estilos para exportação PDF */
        @media print {
            .modal {
                display: none !important;
            }
            
            .sidebar {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .dashboard-actions {
                display: none !important;
            }
            
            .btn-restore-layout,
            .btn-toggle-layout,
            .btn-help {
                display: none !important;
            }
        }
        
        /* Estilos para melhorar a captura de tela */
        .exporting {
            overflow: visible !important;
        }
        
        .exporting .modal {
            display: none !important;
        }
        
        .exporting .sidebar {
            display: none !important;
        }
        
        .exporting .main-content {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        .exporting .dashboard-actions {
            display: none !important;
        }
    </style>
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
            
            <!-- Alertas Inteligentes (MOVIDO PARA CIMA) -->
            <?php if (!empty($alerts)): ?>
            <div class="alerts-section mb-4">
                <div class="section-header">
                    <h2><i class="fas fa-bell"></i> Alertas Inteligentes</h2>
                </div>
                <div class="alerts-grid">
                    <?php foreach ($alerts as $alert): ?>
                    <div class="alert-card alert-<?php echo $alert['type']; ?>">
                        <div class="alert-icon">
                            <i class="<?php echo $alert['icon']; ?>"></i>
                        </div>
                        <div class="alert-content">
                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
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
                            <?php if (isset($crescimento_lucro)): ?>
                            <div class="metric-trend <?= $crescimento_lucro >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $crescimento_lucro >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $crescimento_lucro >= 0 ? '+' : '' ?><?= number_format($crescimento_lucro, 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($crescimento_receita)): ?>
                            <div class="metric-trend <?= $crescimento_receita >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $crescimento_receita >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $crescimento_receita >= 0 ? '+' : '' ?><?= number_format($crescimento_receita, 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_comissao'])): ?>
                            <div class="metric-trend <?= $tendencias['total_comissao'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_comissao'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias['total_comissao'] >= 0 ? '+' : '' ?><?= number_format($tendencias['total_comissao'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_despesas_viagem'])): ?>
                            <div class="metric-trend <?= $tendencias['total_despesas_viagem'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_viagem'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_despesas_viagem'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_viagem'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($variacao_combustivel)): ?>
                            <div class="metric-trend <?= $variacao_combustivel <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $variacao_combustivel <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $variacao_combustivel <= 0 ? '+' : '' ?><?= number_format($variacao_combustivel, 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_despesas_fixas'])): ?>
                            <div class="metric-trend <?= $tendencias['total_despesas_fixas'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_fixas'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_despesas_fixas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_fixas'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_parcelas_financiamento'])): ?>
                            <div class="metric-trend <?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_parcelas_financiamento'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_parcelas_financiamento'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_contas_pagas'])): ?>
                            <div class="metric-trend <?= $tendencias['total_contas_pagas'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_contas_pagas'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_contas_pagas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_contas_pagas'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_manutencoes'])): ?>
                            <div class="metric-trend <?= $tendencias['total_manutencoes'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_manutencoes'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_manutencoes'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_manutencoes'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
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
                            <?php if (isset($tendencias['total_pneu_manutencao'])): ?>
                            <div class="metric-trend <?= $tendencias['total_pneu_manutencao'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_pneu_manutencao'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_pneu_manutencao'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_pneu_manutencao'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Resumo Financeiro -->
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="card-title mb-3"><i class="fas fa-table"></i> Resumo Financeiro</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-center">% da Receita</th>
                                    <th class="text-center">Tendência</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $receita_total = isset($kpis['total_frete']) ? $kpis['total_frete'] : 0;
                                $percentual_frete = $receita_total > 0 ? 100 : 0;
                                $percentual_comissao = $receita_total > 0 ? ($kpis['total_comissao'] / $receita_total) * 100 : 0;
                                $percentual_abastecimento = $receita_total > 0 ? ($kpis['total_abastecimentos'] / $receita_total) * 100 : 0;
                                $percentual_despesas_viagem = $receita_total > 0 ? ($kpis['total_despesas_viagem'] / $receita_total) * 100 : 0;
                                $percentual_despesas_fixas = $receita_total > 0 ? ($kpis['total_despesas_fixas'] / $receita_total) * 100 : 0;
                                $percentual_manutencoes = $receita_total > 0 ? ($kpis['total_manutencoes'] / $receita_total) * 100 : 0;
                                $percentual_lucro = $receita_total > 0 ? ($lucro / $receita_total) * 100 : 0;
                                ?>
                                <tr class="table-success">
                                    <td><strong>Total de Fretes</strong></td>
                                    <td class="text-end"><strong>R$ <?= number_format(isset($kpis['total_frete']) && $kpis['total_frete'] !== null ? $kpis['total_frete'] : 0, 2, ',', '.') ?></strong></td>
                                    <td class="text-center"><strong><?= number_format($percentual_frete, 1) ?>%</strong></td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_frete'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_frete'] >= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_frete'] >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= $tendencias['total_frete'] >= 0 ? '+' : '' ?><?= number_format($tendencias['total_frete'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Total de Comissões</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_comissao']) && $kpis['total_comissao'] !== null ? $kpis['total_comissao'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_comissao, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_comissao'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_comissao'] >= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_comissao'] >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= $tendencias['total_comissao'] >= 0 ? '+' : '' ?><?= number_format($tendencias['total_comissao'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Despesas de Viagem</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_despesas_viagem']) && $kpis['total_despesas_viagem'] !== null ? $kpis['total_despesas_viagem'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_despesas_viagem, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_despesas_viagem'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_despesas_viagem'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_viagem'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_despesas_viagem'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_viagem'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Abastecimentos</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_abastecimentos']) && $kpis['total_abastecimentos'] !== null ? $kpis['total_abastecimentos'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_abastecimento, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_abastecimentos'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_abastecimentos'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_abastecimentos'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_abastecimentos'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_abastecimentos'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Despesas Fixas</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_despesas_fixas']) && $kpis['total_despesas_fixas'] !== null ? $kpis['total_despesas_fixas'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_despesas_fixas, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_despesas_fixas'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_despesas_fixas'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_fixas'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_despesas_fixas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_fixas'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Parcelas de Financiamento</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_parcelas_financiamento']) && $kpis['total_parcelas_financiamento'] !== null ? $kpis['total_parcelas_financiamento'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $receita_total > 0 ? number_format(($kpis['total_parcelas_financiamento'] / $receita_total) * 100, 1) : 0 ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_parcelas_financiamento'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_parcelas_financiamento'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_parcelas_financiamento'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Contas Pagas</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_contas_pagas']) && $kpis['total_contas_pagas'] !== null ? $kpis['total_contas_pagas'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $receita_total > 0 ? number_format(($kpis['total_contas_pagas'] / $receita_total) * 100, 1) : 0 ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_contas_pagas'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_contas_pagas'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_contas_pagas'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_contas_pagas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_contas_pagas'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Manutenções de Veículos</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_manutencoes']) && $kpis['total_manutencoes'] !== null ? $kpis['total_manutencoes'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_manutencoes, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_manutencoes'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_manutencoes'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_manutencoes'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_manutencoes'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_manutencoes'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Manutenções de Pneus</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_pneu_manutencao']) && $kpis['total_pneu_manutencao'] !== null ? $kpis['total_pneu_manutencao'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $receita_total > 0 ? number_format(($kpis['total_pneu_manutencao'] / $receita_total) * 100, 1) : 0 ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_pneu_manutencao'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_pneu_manutencao'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_pneu_manutencao'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_pneu_manutencao'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_pneu_manutencao'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>Lucro Líquido</strong></td>
                                    <td class="text-end"><strong>R$ <?= number_format($lucro, 2, ',', '.') ?></strong></td>
                                    <td class="text-center"><strong><?= number_format($percentual_lucro, 1) ?>%</strong></td>
                                    <td class="text-center">
                                        <?php if (isset($crescimento_lucro)): ?>
                                            <span class="trend-badge <?= $crescimento_lucro >= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $crescimento_lucro >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= $crescimento_lucro >= 0 ? '+' : '' ?><?= number_format($crescimento_lucro, 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Gráficos de Análise -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Análise de Lucratividade</h2>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Custo Médio por KM Rodado</h3>
                </div>
                <div class="card-body">
                        <canvas id="costPerKmChart"></canvas>
                </div>
            </div>

                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Eficiência Operacional - Gauge</h3>
                </div>
                <div class="card-body">
                                <canvas id="profitPerKmGauge"></canvas>
                            </div>
                        </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Eficiência Operacional - Linha</h3>
                        </div>
                        <div class="card-body">
                                <canvas id="profitPerKmLine"></canvas>
                            </div>
                        </div>

                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Projeção de Lucro</h3>
                </div>
                <div class="card-body">
                        <canvas id="profitForecastChart"></canvas>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos de Análise Avançada -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Análise de Lucratividade Avançada</h2>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Evolução da Lucratividade</h3>
                </div>
                <div class="card-body">
                            <canvas id="profitEvolutionChart"></canvas>
                    </div>
                </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Distribuição de Custos</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="costDistributionChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Margem por Tipo de Frete</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="marginByFreightChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Projeção de Lucro (Próximos 3 meses)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="profitForecastAdvancedChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumo Financeiro Detalhado -->
            <div class="financial-summary mb-4">
                <!-- Seção removida conforme solicitado -->
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
    <!-- Bibliotecas para exportar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

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

    // Configuração dos novos gráficos avançados
    document.addEventListener('DOMContentLoaded', function() {
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
        
        // Parâmetros para as APIs
        const apiParams = mes && ano ? `?mes=${mes}&ano=${ano}` : '';
        
        // 1. Gráfico de Evolução da Lucratividade
        const evolucaoCtx = document.getElementById('evolucaoLucratividadeChart');
        if (evolucaoCtx) {
            fetch(`../api/evolucao_lucratividade.php${apiParams}`)
                .then(response => response.json())
                .then(data => {
                    new Chart(evolucaoCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                            datasets: [{
                                label: 'Lucro Líquido',
                                data: data.lucro || [0, 0, 0, 0, 0, 0],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Receita Bruta',
                                data: data.receita || [0, 0, 0, 0, 0, 0],
                                borderColor: '#007bff',
                                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Evolução da Lucratividade'
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
                    console.error('Erro ao carregar dados de evolução:', error);
                    // Criar gráfico com dados vazios
                    new Chart(evolucaoCtx, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                            datasets: [{
                                label: 'Lucro Líquido',
                                data: [0, 0, 0, 0, 0, 0],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Evolução da Lucratividade'
                                }
                            }
                        }
                    });
                });
        }
        
        // 2. Gráfico de Distribuição de Custos
        const distribuicaoCtx = document.getElementById('distribuicaoCustosChart');
        if (distribuicaoCtx) {
            fetch(`../api/distribuicao_custos.php${apiParams}`)
                .then(response => response.json())
                .then(data => {
                    new Chart(distribuicaoCtx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels || ['Combustível', 'Manutenções', 'Despesas Fixas', 'Comissões'],
                            datasets: [{
                                data: data.values || [0, 0, 0, 0],
                                backgroundColor: [
                                    '#ff6384',
                                    '#36a2eb',
                                    '#ffce56',
                                    '#4bc0c0'
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                },
                                title: {
                                    display: true,
                                    text: 'Distribuição de Custos'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados de distribuição:', error);
                    // Criar gráfico com dados vazios
                    new Chart(distribuicaoCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Combustível', 'Manutenções', 'Despesas Fixas', 'Comissões'],
                            datasets: [{
                                data: [0, 0, 0, 0],
                                backgroundColor: [
                                    '#ff6384',
                                    '#36a2eb',
                                    '#ffce56',
                                    '#4bc0c0'
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                },
                                title: {
                                    display: true,
                                    text: 'Distribuição de Custos'
                                }
                            }
                        }
                    });
                });
        }
        
        // 3. Gráfico de Margem por Tipo de Frete
        const margemCtx = document.getElementById('margemTipoFreteChart');
        if (margemCtx) {
            fetch(`../api/margem_tipo_frete.php${apiParams}`)
                .then(response => response.json())
                .then(data => {
                    new Chart(margemCtx, {
                        type: 'bar',
                        data: {
                            labels: data.labels || ['Frete Local', 'Frete Interestadual', 'Frete Internacional'],
                            datasets: [{
                                label: 'Margem de Lucro (%)',
                                data: data.margens || [0, 0, 0],
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.8)',
                                    'rgba(54, 162, 235, 0.8)',
                                    'rgba(255, 205, 86, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(255, 99, 132, 1)',
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(255, 205, 86, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Margem por Tipo de Frete'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados de margem:', error);
                    // Criar gráfico com dados vazios
                    new Chart(margemCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Frete Local', 'Frete Interestadual', 'Frete Internacional'],
                            datasets: [{
                                label: 'Margem de Lucro (%)',
                                data: [0, 0, 0],
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.8)',
                                    'rgba(54, 162, 235, 0.8)',
                                    'rgba(255, 205, 86, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(255, 99, 132, 1)',
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(255, 205, 86, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Margem por Tipo de Frete'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
        }
        
        // 4. Gráfico de Projeção de Lucro
        const projecaoCtx = document.getElementById('projecaoLucroChart');
        if (projecaoCtx) {
            fetch(`../api/projecao_lucro.php${apiParams}`)
                .then(response => response.json())
                .then(data => {
                    new Chart(projecaoCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels || ['Mês Atual', 'Próximo Mês', '2º Mês', '3º Mês'],
                            datasets: [{
                                label: 'Projeção de Lucro',
                                data: data.projecao || [0, 0, 0, 0],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#28a745',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 6
                            }, {
                                label: 'Meta de Lucro',
                                data: data.meta || [0, 0, 0, 0],
                                borderColor: '#ffc107',
                                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: '#ffc107',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Projeção de Lucro (Próximos 3 meses)'
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
                    // Criar gráfico com dados vazios
                    new Chart(projecaoCtx, {
                        type: 'line',
                        data: {
                            labels: ['Mês Atual', 'Próximo Mês', '2º Mês', '3º Mês'],
                            datasets: [{
                                label: 'Projeção de Lucro',
                                data: [0, 0, 0, 0],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#28a745',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Projeção de Lucro (Próximos 3 meses)'
                                }
                            }
                        }
                    });
                });
        }
        
        // 5. Gráfico de Evolução da Lucratividade
        const profitEvolutionCtx = document.getElementById('profitEvolutionChart');
        if (profitEvolutionCtx) {
            new Chart(profitEvolutionCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                    datasets: [{
                        label: 'Lucro Líquido',
                        data: [15000, 18000, 22000, 19000, 25000, <?= $lucro ?>],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
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
                                    return formatCurrency(context.parsed.y);
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
        }
        
        // 6. Gráfico de Distribuição de Custos
        const costDistributionCtx = document.getElementById('costDistributionChart');
        if (costDistributionCtx) {
            new Chart(costDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Combustível', 'Manutenções', 'Despesas Fixas', 'Comissões', 'Outros'],
                    datasets: [{
                        data: [
                            <?= isset($kpis['total_abastecimentos']) ? $kpis['total_abastecimentos'] : 0 ?>,
                            <?= isset($kpis['total_manutencoes']) ? $kpis['total_manutencoes'] : 0 ?>,
                            <?= isset($kpis['total_despesas_fixas']) ? $kpis['total_despesas_fixas'] : 0 ?>,
                            <?= isset($kpis['total_comissao']) ? $kpis['total_comissao'] : 0 ?>,
                            <?= isset($kpis['total_contas_pagas']) ? $kpis['total_contas_pagas'] : 0 ?>
                        ],
                        backgroundColor: [
                            '#ff6384',
                            '#36a2eb',
                            '#ffce56',
                            '#4bc0c0',
                            '#9966ff'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 7. Gráfico de Margem por Tipo de Frete
        const marginByFreightCtx = document.getElementById('marginByFreightChart');
        if (marginByFreightCtx) {
            new Chart(marginByFreightCtx, {
                type: 'bar',
                data: {
                    labels: ['Frete Local', 'Frete Interestadual', 'Frete Internacional'],
                    datasets: [{
                        label: 'Margem de Lucro (%)',
                        data: [25, 35, 45],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 205, 86, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 205, 86, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 8. Gráfico de Projeção de Lucro Avançada
        const profitForecastAdvancedCtx = document.getElementById('profitForecastAdvancedChart');
        if (profitForecastAdvancedCtx) {
            new Chart(profitForecastAdvancedCtx, {
                type: 'line',
                data: {
                    labels: ['Mês Atual', 'Próximo Mês', '2º Mês', '3º Mês'],
                    datasets: [{
                        label: 'Projeção de Lucro',
                        data: [<?= $lucro ?>, <?= $lucro * 1.1 ?>, <?= $lucro * 1.2 ?>, <?= $lucro * 1.3 ?>],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }, {
                        label: 'Meta de Lucro',
                        data: [<?= $lucro * 1.2 ?>, <?= $lucro * 1.3 ?>, <?= $lucro * 1.4 ?>, <?= $lucro * 1.5 ?>],
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: '#ffc107',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
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
        }
    });
    </script>
    
    <script>
        // Função para configurar botão de ajuda
        function setupHelpButton() {
            const helpBtn = document.getElementById('helpBtn');
            if (helpBtn) {
                helpBtn.addEventListener('click', function() {
                    const helpModal = document.getElementById('helpLucratividadeModal');
                    if (helpModal) {
                        helpModal.style.display = 'block';
                    }
                });
            }

            // Close modal functionality for help modal
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // Close modal when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        this.style.display = 'none';
                    }
                });
            });
        }

        // Função para fechar modal específico
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Função para exportar PDF da tela
        function exportToPDF() {
            // Mostrar loading
            const exportBtn = document.getElementById('exportBtn');
            const originalHTML = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            exportBtn.disabled = true;

            // Adicionar classe para esconder elementos durante exportação
            document.body.classList.add('exporting');

            // Aguardar um pouco para aplicar os estilos
            setTimeout(() => {
                // Configurações do html2canvas
                const options = {
                    scale: 1.5,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    width: window.innerWidth,
                    height: document.documentElement.scrollHeight,
                    scrollX: 0,
                    scrollY: 0,
                    ignoreElements: function(element) {
                        // Ignorar modais e elementos de navegação
                        return element.classList.contains('modal') || 
                               element.classList.contains('sidebar') ||
                               element.classList.contains('dashboard-actions');
                    }
                };

                // Capturar a tela
                html2canvas(document.body, options).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // Criar PDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'a4'
                });

                // Calcular dimensões para caber na página
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                const imgWidth = canvas.width;
                const imgHeight = canvas.height;
                
                // Calcular escala para caber na página
                const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
                const finalWidth = imgWidth * ratio;
                const finalHeight = imgHeight * ratio;
                
                // Centralizar na página
                const x = (pdfWidth - finalWidth) / 2;
                const y = (pdfHeight - finalHeight) / 2;

                // Adicionar imagem ao PDF
                pdf.addImage(imgData, 'PNG', x, y, finalWidth, finalHeight);
                
                // Gerar nome do arquivo com data e hora
                const now = new Date();
                const dateStr = now.toLocaleDateString('pt-BR').replace(/\//g, '-');
                const timeStr = now.toLocaleTimeString('pt-BR').replace(/:/g, '-');
                const fileName = `Lucratividade_${dateStr}_${timeStr}.pdf`;
                
                // Baixar o PDF
                pdf.save(fileName);
                
                // Remover classe de exportação
                document.body.classList.remove('exporting');
                
                // Restaurar botão
                exportBtn.innerHTML = originalHTML;
                exportBtn.disabled = false;
                
                // Mostrar sucesso
                alert('PDF exportado com sucesso!');
                
            }).catch(error => {
                console.error('Erro ao exportar PDF:', error);
                
                // Remover classe de exportação
                document.body.classList.remove('exporting');
                
                // Restaurar botão
                exportBtn.innerHTML = originalHTML;
                exportBtn.disabled = false;
                
                // Mostrar erro
                alert('Erro ao exportar PDF. Tente novamente.');
            });
            }, 500); // Aguardar 500ms para aplicar estilos
        }

        // Inicializar botão de ajuda quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Setup help button
            setupHelpButton();
            
            // Setup export button
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', exportToPDF);
            }
        });
    </script>
    
    <!-- Modal de Ajuda -->
    <div class="modal" id="helpLucratividadeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Análise de Lucratividade</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Lucratividade oferece uma análise completa da performance financeira da empresa. Aqui você pode acompanhar receitas, despesas, lucros e tendências para tomar decisões estratégicas.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>KPIs Financeiros:</strong> Visualize indicadores-chave como receita, despesas, lucro e margem.</li>
                        <li><strong>Alertas Inteligentes:</strong> Receba notificações sobre tendências e anomalias financeiras.</li>
                        <li><strong>Análise de Tendências:</strong> Acompanhe a evolução dos resultados ao longo do tempo.</li>
                        <li><strong>Relatórios Detalhados:</strong> Acesse análises profundas de cada componente financeiro.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Receita Total:</strong> Soma de todas as receitas do período.</li>
                        <li><strong>Despesas Totais:</strong> Soma de todas as despesas do período.</li>
                        <li><strong>Lucro Líquido:</strong> Diferença entre receitas e despesas.</li>
                        <li><strong>Margem de Lucro:</strong> Percentual de lucro em relação à receita.</li>
                        <li><strong>ROI:</strong> Retorno sobre investimento da operação.</li>
                        <li><strong>Tendências:</strong> Análise da evolução dos resultados.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Gráficos Disponíveis</h3>
                    <ul>
                        <li><strong>Evolução da Lucratividade:</strong> Gráfico de linha mostrando a evolução do lucro ao longo do tempo.</li>
                        <li><strong>Distribuição de Custos:</strong> Gráfico de pizza mostrando a composição das despesas.</li>
                        <li><strong>Margem por Tipo de Frete:</strong> Comparação da rentabilidade por tipo de serviço.</li>
                        <li><strong>Projeção de Lucro:</strong> Previsões baseadas em tendências históricas.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Monitore regularmente os KPIs para identificar tendências.</li>
                        <li>Analise os alertas inteligentes para tomar ações preventivas.</li>
                        <li>Compare períodos diferentes para entender sazonalidades.</li>
                        <li>Use os gráficos para apresentações e relatórios.</li>
                        <li>Configure metas baseadas nos dados históricos.</li>
                        <li>Acompanhe a evolução da margem de lucro para otimizar preços.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpLucratividadeModal')">Fechar</button>
            </div>
        </div>
    </div>
</body>
</html> 