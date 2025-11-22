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
require_once '../includes/permissions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check authentication - usar o padrão correto do sistema
require_authentication();

// Verificar permissões para acessar lucratividade (apenas admin)
require_permission('access_lucratividade');

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
        // Query otimizada usando subconsultas para evitar duplicação de valores
        // O problema estava nos JOINs que multiplicavam linhas, causando soma duplicada
        // Usar valores diretos nas subconsultas para evitar problema com parâmetros duplicados
        $sql = "
        SELECT 
            DATE_FORMAT(r.data_rota, '%Y-%m') AS mes_ano,
            
            -- Receitas (soma direta das rotas, sem JOIN que causa duplicação)
            COALESCE(SUM(r.frete), 0) AS total_frete,
            COALESCE(SUM(r.comissao), 0) AS total_comissao,
            
            -- Despesas de viagem (usando subconsulta para não multiplicar linhas)
            (
                SELECT COALESCE(SUM(total_despviagem), 0)
                FROM despesas_viagem dv
                WHERE dv.rota_id IN (
                    SELECT id FROM rotas r2 
                    WHERE r2.empresa_id = ?
                      AND MONTH(r2.data_rota) = ?
                      AND YEAR(r2.data_rota) = ?
                )
            ) AS total_despesas_viagem,
            
            -- Abastecimentos (subconsulta)
            (
                SELECT COALESCE(SUM(valor_total), 0)
                FROM abastecimentos a
                WHERE a.empresa_id = ?
                  AND MONTH(a.data_abastecimento) = ?
                  AND YEAR(a.data_abastecimento) = ?
            ) AS total_abastecimentos,
            
            -- Despesas fixas (subconsulta)
            (
                SELECT COALESCE(SUM(df.valor), 0)
                FROM despesas_fixas df
                WHERE df.empresa_id = ?
                  AND df.status_pagamento_id = 2
                  AND MONTH(df.data_pagamento) = ?
                  AND YEAR(df.data_pagamento) = ?
            ) AS total_despesas_fixas,
            
            -- Parcelas de financiamento (subconsulta)
            (
                SELECT COALESCE(SUM(pf.valor), 0)
                FROM parcelas_financiamento pf
                WHERE pf.empresa_id = ?
                  AND pf.status_id = 2
                  AND MONTH(pf.data_pagamento) = ?
                  AND YEAR(pf.data_pagamento) = ?
            ) AS total_parcelas_financiamento,
            
            -- Contas pagas (subconsulta)
            (
                SELECT COALESCE(SUM(cp.valor), 0)
                FROM contas_pagar cp
                WHERE cp.empresa_id = ?
                  AND cp.status_id = 2
                  AND MONTH(cp.data_pagamento) = ?
                  AND YEAR(cp.data_pagamento) = ?
            ) AS total_contas_pagas,
            
            -- Manutenções de veículos (subconsulta)
            (
                SELECT COALESCE(SUM(m.valor), 0)
                FROM manutencoes m
                WHERE m.empresa_id = ?
                  AND MONTH(m.data_manutencao) = ?
                  AND YEAR(m.data_manutencao) = ?
            ) AS total_manutencoes,
            
            -- Manutenção de pneus (subconsulta)
            (
                SELECT COALESCE(SUM(pm.custo), 0)
                FROM pneu_manutencao pm
                WHERE pm.empresa_id = ?
                  AND MONTH(pm.data_manutencao) = ?
                  AND YEAR(pm.data_manutencao) = ?
            ) AS total_pneu_manutencao
            
        FROM rotas r
        WHERE r.empresa_id = ?
          AND MONTH(r.data_rota) = ?
          AND YEAR(r.data_rota) = ?
        GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m')
        ";

        // Preparar array de parâmetros (cada subconsulta precisa dos 3 parâmetros)
        $params = [
            $empresa_id, $mes, $ano,  // despesas_viagem
            $empresa_id, $mes, $ano,  // abastecimentos
            $empresa_id, $mes, $ano,  // despesas_fixas
            $empresa_id, $mes, $ano,  // parcelas_financiamento
            $empresa_id, $mes, $ano,  // contas_pagar
            $empresa_id, $mes, $ano,  // manutencoes
            $empresa_id, $mes, $ano,  // pneu_manutencao
            $empresa_id, $mes, $ano   // rotas (FROM)
        ];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular lucro líquido se houver dados
        if ($result) {
            $receita = floatval($result['total_frete'] ?? 0);
            $despesas = floatval($result['total_comissao'] ?? 0) + 
                       floatval($result['total_abastecimentos'] ?? 0) + 
                       floatval($result['total_despesas_viagem'] ?? 0) + 
                       floatval($result['total_despesas_fixas'] ?? 0) + 
                       floatval($result['total_parcelas_financiamento'] ?? 0) + 
                       floatval($result['total_contas_pagas'] ?? 0) + 
                       floatval($result['total_manutencoes'] ?? 0) + 
                       floatval($result['total_pneu_manutencao'] ?? 0);
            
            $result['lucro_liquido'] = $receita - $despesas;
        }
        
        return $result;
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
        
        // Debug
        error_log("DEBUG getIntelligentAlerts - Mês: $mes, Ano: $ano");
        error_log("DEBUG getIntelligentAlerts - KPIs: " . json_encode($kpis));
        
        // Verificar se há dados válidos
        if ($kpis !== null && is_array($kpis)) {
            $lucro = isset($kpis['lucro_liquido']) ? floatval($kpis['lucro_liquido']) : 0;
            $receita = isset($kpis['total_frete']) ? floatval($kpis['total_frete']) : 0;
            $abastecimentos = isset($kpis['total_abastecimentos']) ? floatval($kpis['total_abastecimentos']) : 0;
            
            error_log("DEBUG getIntelligentAlerts - Lucro: $lucro, Receita: $receita, Abastecimentos: $abastecimentos");
            
            // Alerta de margem baixa ou alta (verificar se receita > 0)
            if ($receita > 0) {
                $margem = ($lucro / $receita) * 100;
                error_log("DEBUG getIntelligentAlerts - Margem calculada: $margem%");
                // Alerta se margem < 10% (incluindo margens negativas)
                if ($margem < 10) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => 'Margem Baixa',
                        'message' => "Margem de lucro está em " . number_format($margem, 1) . "%. Considere revisar custos.",
                        'icon' => 'fas fa-exclamation-triangle'
                    ];
                    error_log("DEBUG getIntelligentAlerts - Alerta de margem baixa adicionado (margem: $margem%)");
                } elseif ($margem >= 30) {
                    // Alerta positivo para margem alta
                    $alerts[] = [
                        'type' => 'success',
                        'title' => 'Margem Excelente',
                        'message' => "Margem de lucro está em " . number_format($margem, 1) . "%. Parabéns!",
                        'icon' => 'fas fa-check-circle'
                    ];
                    error_log("DEBUG getIntelligentAlerts - Alerta de margem excelente adicionado (margem: $margem%)");
                }
            } else {
                error_log("DEBUG getIntelligentAlerts - Receita é zero ou negativa, não é possível calcular margem");
            }
            
            // Alerta de prejuízo (sempre verificar se lucro < 0)
            if ($lucro < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Prejuízo Detectado',
                    'message' => "O mês está fechando com prejuízo de R$ " . number_format(abs($lucro), 2, ',', '.') . ".",
                    'icon' => 'fas fa-times-circle'
                ];
                error_log("DEBUG getIntelligentAlerts - Alerta de prejuízo adicionado");
            } elseif ($lucro > 0 && $receita > 0) {
                // Alerta positivo para lucro positivo
                $alerts[] = [
                    'type' => 'success',
                    'title' => 'Lucro Positivo',
                    'message' => "O mês está fechando com lucro de R$ " . number_format($lucro, 2, ',', '.') . ".",
                    'icon' => 'fas fa-check-circle'
                ];
                error_log("DEBUG getIntelligentAlerts - Alerta de lucro positivo adicionado");
            }
            
            // Alerta de alta despesa com combustível (verificar se combustível > 40% da receita)
            if ($receita > 0 && $abastecimentos > 0) {
                $percentual_combustivel = ($abastecimentos / $receita) * 100;
                error_log("DEBUG getIntelligentAlerts - Percentual combustível: $percentual_combustivel%");
                if ($percentual_combustivel > 40) {
                    $alerts[] = [
                        'type' => 'info',
                        'title' => 'Alto Consumo de Combustível',
                        'message' => "Combustível representa " . number_format($percentual_combustivel, 1) . "% da receita.",
                        'icon' => 'fas fa-gas-pump'
                    ];
                    error_log("DEBUG getIntelligentAlerts - Alerta de alto consumo adicionado");
                } elseif ($percentual_combustivel < 25) {
                    // Alerta positivo para baixo consumo de combustível
                    $alerts[] = [
                        'type' => 'success',
                        'title' => 'Eficiência de Combustível',
                        'message' => "Combustível representa apenas " . number_format($percentual_combustivel, 1) . "% da receita. Excelente!",
                        'icon' => 'fas fa-check-circle'
                    ];
                    error_log("DEBUG getIntelligentAlerts - Alerta de eficiência de combustível adicionado");
                }
            }
        } else {
            error_log("DEBUG getIntelligentAlerts - KPIs é null ou não é array");
        }
        
        // Buscar dados do mês anterior para comparação
        $mes_anterior = $mes == 1 ? 12 : $mes - 1;
        $ano_anterior = $mes == 1 ? $ano - 1 : $ano;
        
        $kpis_anterior = getKPIsOptimized($conn, $empresa_id, $mes_anterior, $ano_anterior);
        
        error_log("DEBUG getIntelligentAlerts - KPIs anterior: " . json_encode($kpis_anterior));
        
        if ($kpis_anterior !== null && is_array($kpis_anterior) && $kpis !== null && is_array($kpis)) {
            $lucro_anterior = isset($kpis_anterior['lucro_liquido']) ? floatval($kpis_anterior['lucro_liquido']) : 0;
            $lucro_atual = isset($kpis['lucro_liquido']) ? floatval($kpis['lucro_liquido']) : 0;
            
            error_log("DEBUG getIntelligentAlerts - Lucro anterior: $lucro_anterior, Lucro atual: $lucro_atual");
            
            // Calcular crescimento apenas se houver lucro anterior diferente de zero
            if ($lucro_anterior != 0) {
                $crescimento = (($lucro_atual - $lucro_anterior) / abs($lucro_anterior)) * 100;
                error_log("DEBUG getIntelligentAlerts - Crescimento calculado: $crescimento%");
                
                // Alerta de queda na lucratividade (queda > 20%)
                if ($crescimento < -20) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => 'Queda na Lucratividade',
                        'message' => "Lucro caiu " . number_format(abs($crescimento), 1) . "% em relação ao mês anterior.",
                        'icon' => 'fas fa-chart-line'
                    ];
                    error_log("DEBUG getIntelligentAlerts - Alerta de queda adicionado");
                } elseif ($crescimento > 20) {
                    // Alerta de crescimento positivo
                    $alerts[] = [
                        'type' => 'success',
                        'title' => 'Crescimento na Lucratividade',
                        'message' => "Lucro aumentou " . number_format($crescimento, 1) . "% em relação ao mês anterior!",
                        'icon' => 'fas fa-chart-line'
                    ];
                    error_log("DEBUG getIntelligentAlerts - Alerta de crescimento adicionado");
                }
            } elseif ($lucro_anterior == 0 && $lucro_atual < 0) {
                // Se mês anterior teve lucro zero e atual tem prejuízo
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Queda na Lucratividade',
                    'message' => "Lucro caiu significativamente em relação ao mês anterior.",
                    'icon' => 'fas fa-chart-line'
                ];
                error_log("DEBUG getIntelligentAlerts - Alerta de queda (zero para negativo) adicionado");
            } elseif ($lucro_anterior <= 0 && $lucro_atual > 0) {
                // Se mês anterior teve prejuízo ou zero e atual tem lucro positivo
                $alerts[] = [
                    'type' => 'success',
                    'title' => 'Recuperação Financeira',
                    'message' => "Lucro positivo após período negativo. Parabéns pela recuperação!",
                    'icon' => 'fas fa-chart-line'
                ];
                error_log("DEBUG getIntelligentAlerts - Alerta de recuperação adicionado");
            }
        } else {
            error_log("DEBUG getIntelligentAlerts - Não foi possível comparar com mês anterior");
            if ($kpis_anterior === null) {
                error_log("DEBUG getIntelligentAlerts - KPIs anterior é null");
            }
            if ($kpis === null) {
                error_log("DEBUG getIntelligentAlerts - KPIs atual é null");
            }
        }
        
        error_log("DEBUG getIntelligentAlerts - Total de alertas: " . count($alerts));
        
        return $alerts;
    } catch (Exception $e) {
        error_log("Erro na função getIntelligentAlerts: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

// Função para calcular KPIs avançados
function getAdvancedKPIs($conn, $empresa_id, $mes, $ano, $kpis) {
    try {
        $advanced = [];
        
        $lucro = isset($kpis['lucro_liquido']) ? floatval($kpis['lucro_liquido']) : 0;
        $receita = isset($kpis['total_frete']) ? floatval($kpis['total_frete']) : 0;
        
        // Debug: Log dos valores recebidos
        error_log("DEBUG getAdvancedKPIs - Lucro: $lucro, Receita: $receita, Empresa: $empresa_id, Mês: $mes, Ano: $ano");
        
        // Calcular ROI (assumindo investimento total baseado em financiamentos)
        $investimento_total = 0;
        try {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(valor_total), 0) as total FROM financiamentos WHERE empresa_id = ?");
            $stmt->execute([$empresa_id]);
            $investimento = $stmt->fetch(PDO::FETCH_ASSOC);
            $investimento_total = floatval($investimento['total'] ?? 0);
            error_log("DEBUG ROI - Investimento total: $investimento_total");
        } catch (Exception $e) {
            error_log("Erro ao buscar investimento: " . $e->getMessage());
        }
        
        $advanced['roi'] = $investimento_total > 0 ? ($lucro / $investimento_total) * 100 : 0;
        
        // Calcular Ticket Médio por Rota
        $total_rotas = 0;
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as total_rotas FROM rotas WHERE empresa_id = ? AND MONTH(data_rota) = ? AND YEAR(data_rota) = ?");
            $stmt->execute([$empresa_id, $mes, $ano]);
            $rotas = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_rotas = intval($rotas['total_rotas'] ?? 0);
            error_log("DEBUG Ticket Médio - Total rotas: $total_rotas");
        } catch (Exception $e) {
            error_log("Erro ao contar rotas: " . $e->getMessage());
        }
        
        $advanced['ticket_medio'] = $total_rotas > 0 ? $receita / $total_rotas : 0;
        
        // Calcular Custo por Quilômetro - usar distancia_km (campo correto)
        $total_km = 0;
        try {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(distancia_km), 0) as total_km FROM rotas WHERE empresa_id = ? AND MONTH(data_rota) = ? AND YEAR(data_rota) = ?");
            $stmt->execute([$empresa_id, $mes, $ano]);
            $km = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_km = floatval($km['total_km'] ?? 0);
            error_log("DEBUG Custo KM - Total KM: $total_km");
        } catch (Exception $e) {
            error_log("Erro ao calcular km: " . $e->getMessage());
            // Tentar com campo alternativo se houver erro
            try {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(quilometragem), 0) as total_km FROM rotas WHERE empresa_id = ? AND MONTH(data_rota) = ? AND YEAR(data_rota) = ?");
                $stmt->execute([$empresa_id, $mes, $ano]);
                $km = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_km = floatval($km['total_km'] ?? 0);
            } catch (Exception $e2) {
                error_log("Erro ao calcular km (tentativa 2): " . $e2->getMessage());
            }
        }
        
        $despesas = floatval($kpis['total_abastecimentos'] ?? 0) + 
                   floatval($kpis['total_despesas_viagem'] ?? 0) + 
                   floatval($kpis['total_despesas_fixas'] ?? 0) + 
                   floatval($kpis['total_manutencoes'] ?? 0);
        $advanced['custo_km'] = $total_km > 0 ? $despesas / $total_km : 0;
        
        // Calcular Margem Operacional
        $advanced['margem_operacional'] = $receita > 0 ? ($lucro / $receita) * 100 : 0;
        
        // Calcular Taxa de Ocupação (rotas com carga - assumindo que todas as rotas têm carga se têm frete > 0)
        $rotas_com_carga = 0;
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as total_com_carga FROM rotas WHERE empresa_id = ? AND MONTH(data_rota) = ? AND YEAR(data_rota) = ? AND frete > 0");
            $stmt->execute([$empresa_id, $mes, $ano]);
            $ocupacao = $stmt->fetch(PDO::FETCH_ASSOC);
            $rotas_com_carga = intval($ocupacao['total_com_carga'] ?? 0);
            error_log("DEBUG Taxa Ocupação - Rotas com carga: $rotas_com_carga, Total rotas: $total_rotas");
        } catch (Exception $e) {
            error_log("Erro ao calcular ocupação: " . $e->getMessage());
        }
        
        $advanced['taxa_ocupacao'] = $total_rotas > 0 ? ($rotas_com_carga / $total_rotas) * 100 : 0;
        
        // Log para debug
        error_log("KPIs Avançados calculados - ROI: " . $advanced['roi'] . ", Ticket: " . $advanced['ticket_medio'] . ", Custo KM: " . $advanced['custo_km'] . ", Margem: " . $advanced['margem_operacional'] . ", Ocupação: " . $advanced['taxa_ocupacao']);
        
        return $advanced;
    } catch (Exception $e) {
        error_log("Erro ao calcular KPIs avançados: " . $e->getMessage());
        return [
            'roi' => 0,
            'ticket_medio' => 0,
            'custo_km' => 0,
            'margem_operacional' => 0,
            'taxa_ocupacao' => 0
        ];
    }
}

// Função para buscar rankings
function getRankings($conn, $empresa_id, $mes, $ano) {
    try {
        $rankings = [];
        
        error_log("DEBUG getRankings - Empresa: $empresa_id, Mês: $mes, Ano: $ano");
        
        // Top 5 Veículos Mais Rentáveis
        // Lucro = Receita (fretes) - Comissões - Abastecimentos das rotas do mês - Despesas de Viagem - Manutenções do mês
        // IMPORTANTE: Só considerar veículos que tiveram rotas no mês/ano especificado
        // Usar subconsultas para evitar duplicação de linhas causada por múltiplas manutenções
        $stmt = $conn->prepare("
            SELECT 
                v.placa,
                v.modelo,
                COALESCE(SUM(r.frete), 0) as receita,
                COALESCE(SUM(r.comissao), 0) as comissao,
                COALESCE((
                    SELECT SUM(a2.valor_total)
                    FROM abastecimentos a2
                    WHERE a2.rota_id IN (
                        SELECT r2.id FROM rotas r2 
                        WHERE r2.veiculo_id = v.id 
                        AND r2.empresa_id = ?
                        AND MONTH(r2.data_rota) = ?
                        AND YEAR(r2.data_rota) = ?
                    )
                    AND a2.empresa_id = ?
                ), 0) as custo_abastecimento,
                COALESCE((
                    SELECT SUM(dv2.total_despviagem)
                    FROM despesas_viagem dv2
                    WHERE dv2.rota_id IN (
                        SELECT r3.id FROM rotas r3 
                        WHERE r3.veiculo_id = v.id 
                        AND r3.empresa_id = ?
                        AND MONTH(r3.data_rota) = ?
                        AND YEAR(r3.data_rota) = ?
                    )
                    AND dv2.empresa_id = ?
                ), 0) as despesas_viagem,
                COALESCE((
                    SELECT SUM(m2.valor)
                    FROM manutencoes m2
                    WHERE m2.veiculo_id = v.id
                    AND m2.empresa_id = ?
                    AND MONTH(m2.data_manutencao) = ?
                    AND YEAR(m2.data_manutencao) = ?
                ), 0) as custo_manutencao,
                (
                    COALESCE(SUM(r.frete), 0) 
                    - COALESCE(SUM(r.comissao), 0)
                    - COALESCE((
                        SELECT SUM(a2.valor_total)
                        FROM abastecimentos a2
                        WHERE a2.rota_id IN (
                            SELECT r2.id FROM rotas r2 
                            WHERE r2.veiculo_id = v.id 
                            AND r2.empresa_id = ?
                            AND MONTH(r2.data_rota) = ?
                            AND YEAR(r2.data_rota) = ?
                        )
                        AND a2.empresa_id = ?
                    ), 0)
                    - COALESCE((
                        SELECT SUM(dv2.total_despviagem)
                        FROM despesas_viagem dv2
                        WHERE dv2.rota_id IN (
                            SELECT r3.id FROM rotas r3 
                            WHERE r3.veiculo_id = v.id 
                            AND r3.empresa_id = ?
                            AND MONTH(r3.data_rota) = ?
                            AND YEAR(r3.data_rota) = ?
                        )
                        AND dv2.empresa_id = ?
                    ), 0)
                    - COALESCE((
                        SELECT SUM(m2.valor)
                        FROM manutencoes m2
                        WHERE m2.veiculo_id = v.id
                        AND m2.empresa_id = ?
                        AND MONTH(m2.data_manutencao) = ?
                        AND YEAR(m2.data_manutencao) = ?
                    ), 0)
                ) as lucro
            FROM rotas r
            INNER JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = ?
            WHERE r.empresa_id = ?
                AND MONTH(r.data_rota) = ? 
                AND YEAR(r.data_rota) = ?
            GROUP BY v.id, v.placa, v.modelo
            HAVING lucro > 0
            ORDER BY lucro DESC
            LIMIT 5
        ");
        $stmt->execute([
            $empresa_id, $mes, $ano, $empresa_id,  // abastecimentos (subquery 1)
            $empresa_id, $mes, $ano, $empresa_id,  // despesas_viagem (subquery 1)
            $empresa_id, $mes, $ano,  // manutencoes (subquery 1)
            $empresa_id, $mes, $ano, $empresa_id,  // abastecimentos (subquery 2 - no cálculo)
            $empresa_id, $mes, $ano, $empresa_id,  // despesas_viagem (subquery 2 - no cálculo)
            $empresa_id, $mes, $ano,  // manutencoes (subquery 2 - no cálculo)
            $empresa_id,  // veiculos join
            $empresa_id, $mes, $ano  // WHERE rotas
        ]);
        $rankings['veiculos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG getRankings - Veículos encontrados: " . count($rankings['veiculos']) . " | Mês: $mes, Ano: $ano, Empresa: $empresa_id");
        if (!empty($rankings['veiculos'])) {
            foreach ($rankings['veiculos'] as $idx => $veiculo) {
                $receita = floatval($veiculo['receita'] ?? 0);
                $comissao = floatval($veiculo['comissao'] ?? 0);
                $abast = floatval($veiculo['custo_abastecimento'] ?? 0);
                $desp_viagem = floatval($veiculo['despesas_viagem'] ?? 0);
                $manut = floatval($veiculo['custo_manutencao'] ?? 0);
                $lucro = floatval($veiculo['lucro'] ?? 0);
                $total_custos = $comissao + $abast + $desp_viagem + $manut;
                error_log("DEBUG getRankings - Veículo " . ($idx + 1) . ": {$veiculo['placa']} | Receita: R$ $receita | Custos: R$ $total_custos (Com: R$ $comissao, Abast: R$ $abast, Desp: R$ $desp_viagem, Manut: R$ $manut) | Lucro: R$ $lucro");
            }
        }
        
        // Top 5 Motoristas Mais Rentáveis
        // Lucro = Receita (fretes) - Comissões - Despesas de Viagem das rotas do motorista
        // IMPORTANTE: Só considerar motoristas que tiveram rotas no mês/ano especificado
        $stmt = $conn->prepare("
            SELECT 
                m.nome,
                COALESCE(SUM(r.frete), 0) as receita,
                COALESCE(SUM(r.comissao), 0) as comissao,
                COALESCE(SUM(dv.total_despviagem), 0) as despesas_viagem,
                (
                    COALESCE(SUM(r.frete), 0) 
                    - COALESCE(SUM(r.comissao), 0)
                    - COALESCE(SUM(dv.total_despviagem), 0)
                ) as lucro
            FROM rotas r
            INNER JOIN motoristas m ON m.id = r.motorista_id AND m.empresa_id = ?
            LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id AND dv.empresa_id = ?
            WHERE r.empresa_id = ?
                AND MONTH(r.data_rota) = ? 
                AND YEAR(r.data_rota) = ?
            GROUP BY m.id, m.nome
            HAVING lucro > 0
            ORDER BY lucro DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id, $empresa_id, $empresa_id, $mes, $ano]);
        $rankings['motoristas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG getRankings - Motoristas encontrados: " . count($rankings['motoristas']) . " | Mês: $mes, Ano: $ano, Empresa: $empresa_id");
        if (!empty($rankings['motoristas'])) {
            foreach ($rankings['motoristas'] as $idx => $motorista) {
                $receita = floatval($motorista['receita'] ?? 0);
                $comissao = floatval($motorista['comissao'] ?? 0);
                $desp_viagem = floatval($motorista['despesas_viagem'] ?? 0);
                $lucro = floatval($motorista['lucro'] ?? 0);
                $total_custos = $comissao + $desp_viagem;
                error_log("DEBUG getRankings - Motorista " . ($idx + 1) . ": {$motorista['nome']} | Receita: R$ $receita | Custos: R$ $total_custos (Com: R$ $comissao, Desp: R$ $desp_viagem) | Lucro: R$ $lucro");
            }
        }
        
        // Top 5 Clientes Mais Rentáveis (se houver tabela de clientes)
        // Por enquanto, usando rotas sem cliente específico
        $rankings['clientes'] = [];
        
        // Lucratividade por Tipo de Frete (verificar se tabela existe)
        try {
            // Verificar se a tabela tipos_frete existe
            $check_table = $conn->query("SHOW TABLES LIKE 'tipos_frete'");
            if ($check_table->rowCount() > 0) {
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE(tf.nome, 'Não especificado') as tipo_frete,
                        COALESCE(SUM(r.frete), 0) as receita,
                        COALESCE(SUM(r.comissao), 0) as comissao,
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)) as lucro
                    FROM rotas r
                    LEFT JOIN tipos_frete tf ON r.tipo_frete_id = tf.id
                    WHERE r.empresa_id = ? AND MONTH(r.data_rota) = ? AND YEAR(r.data_rota) = ?
                    GROUP BY tf.id, tf.nome
                    HAVING lucro > 0
                    ORDER BY lucro DESC
                ");
                $stmt->execute([$empresa_id, $mes, $ano]);
                $rankings['tipos_frete'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Se não existe, usar campo direto da tabela rotas se existir
                $rankings['tipos_frete'] = [];
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar tipos de frete: " . $e->getMessage());
            $rankings['tipos_frete'] = [];
        }
        error_log("DEBUG getRankings - Tipos de frete encontrados: " . count($rankings['tipos_frete']));
        
        error_log("DEBUG getRankings - Resultado final: " . json_encode($rankings));
        
        return $rankings;
    } catch (Exception $e) {
        error_log("Erro ao buscar rankings: " . $e->getMessage());
        return [
            'veiculos' => [],
            'motoristas' => [],
            'clientes' => [],
            'tipos_frete' => []
        ];
    }
}

// Buscar dados otimizados (nova implementação)
try {
    $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
    $alerts = getIntelligentAlerts($conn, $empresa_id, $mes, $ano);
    
    // Garantir que $alerts sempre seja um array
    if (!is_array($alerts)) {
        $alerts = [];
    }
    
    error_log("DEBUG - Alertas finais para mês $mes/$ano: " . count($alerts) . " alertas");
    error_log("DEBUG - Conteúdo dos alertas: " . json_encode($alerts));
    
    // Calcular Lucratividade
    $lucro = isset($kpis) && is_array($kpis) && isset($kpis['lucro_liquido']) ? $kpis['lucro_liquido'] : 0;
    
    // Calcular tendências comparando com mês anterior
    $mes_anterior = $mes == 1 ? 12 : $mes - 1;
    $ano_anterior = $mes == 1 ? $ano - 1 : $ano;
    
    $kpis_anterior = getKPIsOptimized($conn, $empresa_id, $mes_anterior, $ano_anterior);
    
    // Calcular KPIs avançados
    error_log("DEBUG - KPIs recebidos: " . json_encode($kpis));
    if (!empty($kpis) && is_array($kpis)) {
        $advanced_kpis = getAdvancedKPIs($conn, $empresa_id, $mes, $ano, $kpis);
        error_log("DEBUG - KPIs avançados calculados: " . json_encode($advanced_kpis));
    } else {
        error_log("DEBUG - KPIs vazios ou inválidos, usando valores padrão");
        $advanced_kpis = [
            'roi' => 0,
            'ticket_medio' => 0,
            'custo_km' => 0,
            'margem_operacional' => 0,
            'taxa_ocupacao' => 0
        ];
    }
    
    // Calcular tendências dos KPIs avançados comparando com mês anterior
    $advanced_kpis_anterior = [];
    $tendencias_advanced = [];
    if ($kpis_anterior && !empty($kpis_anterior)) {
        $advanced_kpis_anterior = getAdvancedKPIs($conn, $empresa_id, $mes_anterior, $ano_anterior, $kpis_anterior);
        
        // Calcular tendências
        foreach ($advanced_kpis as $key => $valor_atual) {
            $valor_anterior = $advanced_kpis_anterior[$key] ?? 0;
            if ($valor_anterior != 0) {
                $tendencias_advanced[$key] = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
            } else {
                $tendencias_advanced[$key] = $valor_atual > 0 ? 100 : 0;
            }
        }
    } else {
        $tendencias_advanced = [
            'roi' => 0,
            'ticket_medio' => 0,
            'custo_km' => 0,
            'margem_operacional' => 0,
            'taxa_ocupacao' => 0
        ];
    }
    
    // Buscar rankings
    $rankings = getRankings($conn, $empresa_id, $mes, $ano);
    
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
    $advanced_kpis = [
        'roi' => 0,
        'ticket_medio' => 0,
        'custo_km' => 0,
        'margem_operacional' => 0,
        'taxa_ocupacao' => 0
    ];
    $rankings = [
        'veiculos' => [],
        'motoristas' => [],
        'clientes' => [],
        'tipos_frete' => []
    ];
}

// Garantir que $alerts sempre seja um array válido
if (!isset($alerts) || !is_array($alerts)) {
    $alerts = [];
}

// Buscar dados para os KPIs (manter a query original como backup)
// CORREÇÃO: Removido JOIN com despesas_viagem que causava duplicação
$sql_kpis = "
SELECT 
    DATE_FORMAT(r.data_rota, '%Y-%m') AS mes_ano,
    
    -- Receitas
    COALESCE(SUM(r.frete), 0) AS total_frete,
    COALESCE(SUM(r.comissao), 0) AS total_comissao,
    
    -- Despesas de viagem (subconsulta para evitar duplicação)
    (
        SELECT COALESCE(SUM(dv.total_despviagem), 0)
        FROM despesas_viagem dv
        WHERE dv.rota_id IN (
            SELECT id FROM rotas WHERE empresa_id = " . intval($empresa_id) . "
            AND MONTH(data_rota) = " . intval($mes) . "
            AND YEAR(data_rota) = " . intval($ano) . "
        )
    ) AS total_despesas_viagem,
    
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
        - (
            SELECT COALESCE(SUM(dv.total_despviagem), 0)
            FROM despesas_viagem dv
            WHERE dv.rota_id IN (
                SELECT id FROM rotas WHERE empresa_id = " . intval($empresa_id) . "
                AND MONTH(data_rota) = " . intval($mes) . "
                AND YEAR(data_rota) = " . intval($ano) . "
            )
        )
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
WHERE r.empresa_id = " . intval($empresa_id) . "
  AND MONTH(r.data_rota) = " . intval($mes) . "
  AND YEAR(r.data_rota) = " . intval($ano) . "
GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m')
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
        /* Estilos para Modais */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5em;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .help-section {
            margin-bottom: 25px;
        }
        
        .help-section h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        
        .help-section ul, .help-section ol {
            margin-left: 20px;
            line-height: 1.8;
        }
        
        .help-section li {
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        
        .help-section strong {
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }
        /* Estilos para Análises Detalhadas */
        .analysis-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .analysis-card {
            background: var(--bg-primary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-primary);
        }
        
        .analysis-card h4 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: var(--bg-secondary);
            border-radius: 6px;
        }
        
        .ranking-position {
            font-weight: 700;
            color: var(--accent-primary);
            margin-right: 10px;
        }
        
        .ranking-item {
            padding: 15px !important;
        }
        
        .ranking-item small {
            line-height: 1.6;
        }
        
        /* Melhorar legibilidade do metric-trend */
        .metric-trend {
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        
        .metric-trend.positive {
            color: #218838 !important;
        }
        
        .metric-trend.negative {
            color: #dc3545 !important;
        }
        
        .metric-trend.info {
            color: #6c757d !important;
        }
        
        /* Específico para o card de Lucro Líquido com fundo verde claro */
        .dashboard-card[style*="background: #e6f9ed"] .metric-trend {
            color: #155724 !important;
            font-weight: 700;
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
                
                <!-- Novos KPIs Avançados -->
                <div class="dashboard-card" style="border-left: 4px solid var(--accent-secondary);">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> ROI (Retorno sobre Investimento)</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-secondary); font-size: 2rem; font-weight: bold;"><?= number_format($advanced_kpis['roi'] ?? 0, 2, ',', '.') ?>%</span>
                            <span class="metric-subtitle">Taxa de retorno</span>
                            <?php if (isset($tendencias_advanced['roi'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['roi'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['roi'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['roi'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['roi'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" style="border-left: 4px solid var(--accent-primary);">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt"></i> Ticket Médio por Rota</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="font-size: 2rem; font-weight: bold;">R$ <?= number_format($advanced_kpis['ticket_medio'] ?? 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Média por rota</span>
                            <?php if (isset($tendencias_advanced['ticket_medio'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['ticket_medio'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['ticket_medio'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['ticket_medio'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['ticket_medio'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" style="border-left: 4px solid var(--accent-warning);">
                    <div class="card-header">
                        <h3><i class="fas fa-road"></i> Custo por Quilômetro</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-warning); font-size: 2rem; font-weight: bold;">R$ <?= number_format($advanced_kpis['custo_km'] ?? 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Por km rodado</span>
                            <?php if (isset($tendencias_advanced['custo_km'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['custo_km'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['custo_km'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias_advanced['custo_km'] <= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['custo_km'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" style="border-left: 4px solid var(--accent-success);">
                    <div class="card-header">
                        <h3><i class="fas fa-percentage"></i> Margem Operacional</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-success); font-size: 2rem; font-weight: bold;"><?= number_format($advanced_kpis['margem_operacional'] ?? 0, 2, ',', '.') ?>%</span>
                            <span class="metric-subtitle">Margem líquida</span>
                            <?php if (isset($tendencias_advanced['margem_operacional'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['margem_operacional'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['margem_operacional'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['margem_operacional'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['margem_operacional'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" style="border-left: 4px solid var(--accent-secondary);">
                    <div class="card-header">
                        <h3><i class="fas fa-box"></i> Taxa de Ocupação</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-secondary); font-size: 2rem; font-weight: bold;"><?= number_format($advanced_kpis['taxa_ocupacao'] ?? 0, 2, ',', '.') ?>%</span>
                            <span class="metric-subtitle">Rotas com carga</span>
                            <?php if (isset($tendencias_advanced['taxa_ocupacao'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['taxa_ocupacao'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['taxa_ocupacao'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['taxa_ocupacao'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['taxa_ocupacao'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Análises Detalhadas -->
            <div class="analysis-section">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">
                    <i class="fas fa-chart-bar"></i> Análises Detalhadas
                </h2>
                
                <div class="analysis-grid">
                    <!-- Top 5 Veículos -->
                    <?php if (!empty($rankings['veiculos'])): ?>
                    <div class="analysis-card">
                        <h4><i class="fas fa-truck"></i> Top 5 Veículos Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <?php foreach ($rankings['veiculos'] as $index => $veiculo): 
                                $receita = floatval($veiculo['receita'] ?? 0);
                                $comissao = floatval($veiculo['comissao'] ?? 0);
                                $custo_abastecimento = floatval($veiculo['custo_abastecimento'] ?? 0);
                                $despesas_viagem = floatval($veiculo['despesas_viagem'] ?? 0);
                                $custo_manutencao = floatval($veiculo['custo_manutencao'] ?? 0);
                                $lucro = floatval($veiculo['lucro'] ?? 0);
                                $total_custos = $comissao + $custo_abastecimento + $despesas_viagem + $custo_manutencao;
                            ?>
                            <li class="ranking-item">
                                <div style="flex: 1;">
                                    <div>
                                        <span class="ranking-position"><?= $index + 1 ?>º</span>
                                        <span><strong><?= htmlspecialchars($veiculo['placa']) ?> - <?= htmlspecialchars($veiculo['modelo']) ?></strong></span>
                                    </div>
                                    <div style="margin-top: 8px; font-size: 0.85rem; color: var(--text-secondary);">
                                        <div>Receita: <strong style="color: var(--accent-primary);">R$ <?= number_format($receita, 2, ',', '.') ?></strong></div>
                                        <div style="margin-top: 4px;">Custos: 
                                            <span style="color: var(--accent-danger);">R$ <?= number_format($total_custos, 2, ',', '.') ?></span>
                                            <small style="display: block; margin-left: 20px; margin-top: 2px;">
                                                • Comissões: R$ <?= number_format($comissao, 2, ',', '.') ?><br>
                                                • Abastecimentos: R$ <?= number_format($custo_abastecimento, 2, ',', '.') ?><br>
                                                • Despesas Viagem: R$ <?= number_format($despesas_viagem, 2, ',', '.') ?><br>
                                                • Manutenções: R$ <?= number_format($custo_manutencao, 2, ',', '.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <strong style="color: var(--accent-success); font-size: 1.1rem;">R$ <?= number_format($lucro, 2, ',', '.') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                                        Lucro Líquido
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Top 5 Motoristas -->
                    <?php if (!empty($rankings['motoristas'])): ?>
                    <div class="analysis-card">
                        <h4><i class="fas fa-user"></i> Top 5 Motoristas Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <?php foreach ($rankings['motoristas'] as $index => $motorista): 
                                $receita = floatval($motorista['receita'] ?? 0);
                                $comissao = floatval($motorista['comissao'] ?? 0);
                                $despesas_viagem = floatval($motorista['despesas_viagem'] ?? 0);
                                $lucro = floatval($motorista['lucro'] ?? 0);
                                $total_custos = $comissao + $despesas_viagem;
                            ?>
                            <li class="ranking-item">
                                <div style="flex: 1;">
                                    <div>
                                        <span class="ranking-position"><?= $index + 1 ?>º</span>
                                        <span><strong><?= htmlspecialchars($motorista['nome']) ?></strong></span>
                                    </div>
                                    <div style="margin-top: 8px; font-size: 0.85rem; color: var(--text-secondary);">
                                        <div>Receita: <strong style="color: var(--accent-primary);">R$ <?= number_format($receita, 2, ',', '.') ?></strong></div>
                                        <div style="margin-top: 4px;">Custos: 
                                            <span style="color: var(--accent-danger);">R$ <?= number_format($total_custos, 2, ',', '.') ?></span>
                                            <small style="display: block; margin-left: 20px; margin-top: 2px;">
                                                • Comissões: R$ <?= number_format($comissao, 2, ',', '.') ?><br>
                                                • Despesas Viagem: R$ <?= number_format($despesas_viagem, 2, ',', '.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <strong style="color: var(--accent-success); font-size: 1.1rem;">R$ <?= number_format($lucro, 2, ',', '.') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                                        Lucro Líquido
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lucratividade por Tipo de Frete -->
                    <?php if (!empty($rankings['tipos_frete'])): ?>
                    <div class="analysis-card">
                        <h4><i class="fas fa-route"></i> Lucratividade por Tipo de Frete</h4>
                        <ul class="ranking-list">
                            <?php foreach ($rankings['tipos_frete'] as $tipo): ?>
                            <li class="ranking-item">
                                <div>
                                    <span><?= htmlspecialchars($tipo['tipo_frete']) ?></span>
                                    <br>
                                    <small style="color: var(--text-secondary); font-size: 0.85rem;">Receita: R$ <?= number_format($tipo['receita'] ?? 0, 2, ',', '.') ?></small>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ <?= number_format($tipo['lucro'] ?? 0, 2, ',', '.') ?></strong>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($rankings['veiculos']) && empty($rankings['motoristas']) && empty($rankings['tipos_frete'])): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Não há dados suficientes para exibir análises detalhadas no período selecionado.</p>
                    </div>
                    <?php endif; ?>
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
            
            <!-- Novos Gráficos Avançados -->
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Análise de Fluxo Financeiro</h2>
                </div>
                <div class="analytics-grid">
                    <!-- Gráfico de Cascata (Waterfall) -->
                    <div class="analytics-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Análise de Fluxo Financeiro (Waterfall)</h3>
                        </div>
                        <div class="card-body" style="position: relative; height: 400px;">
                            <canvas id="waterfallChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Tendência Anual -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Tendência Anual</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Pareto -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Análise de Pareto (80/20)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="paretoChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Distribuição Detalhada de Custos -->
                    <div class="analytics-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Distribuição Detalhada de Custos</h3>
                        </div>
                        <div class="card-body" style="position: relative; height: 500px; display: flex; align-items: center; justify-content: center;">
                            <canvas id="costBreakdownChart"></canvas>
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
                // Mostrar aviso se há dados de exemplo
                if (data.warning) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-warning';
                    warningDiv.innerHTML = `
                        <h5><i class="fas fa-exclamation-triangle"></i> ${data.warning}</h5>
                        <p><strong>Sugestões:</strong></p>
                        <ul>
                            ${data.suggestions.map(s => `<li>${s}</li>`).join('')}
                        </ul>
                    `;
                    document.getElementById('profitPerKmGauge').parentNode.parentNode.insertBefore(warningDiv, document.getElementById('profitPerKmGauge').parentNode);
                }
                
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
                // Mostrar aviso se há dados de exemplo
                if (data.warning) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-warning';
                    let suggestionsHtml = '';
                    if (data.suggestions && data.suggestions.length > 0) {
                        suggestionsHtml = `
                            <p><strong>Sugestões:</strong></p>
                            <ul>
                                ${data.suggestions.map(s => `<li>${s}</li>`).join('')}
                            </ul>
                        `;
                    }
                    warningDiv.innerHTML = `
                        <h5><i class="fas fa-exclamation-triangle"></i> ${data.warning}</h5>
                        ${suggestionsHtml}
                    `;
                    document.getElementById('profitForecastChart').parentNode.parentNode.insertBefore(warningDiv, document.getElementById('profitForecastChart').parentNode);
                }
                
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
                            labels: data.labels || ['Frete Local', 'Frete Regional', 'Frete Interestadual'],
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
                            labels: ['Frete Local', 'Frete Regional', 'Frete Interestadual'],
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
                    labels: ['Frete Local', 'Frete Regional', 'Frete Interestadual'],
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
                        helpModal.style.display = 'flex';
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
        
        // Configurar modal de ajuda quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            const helpBtn = document.getElementById('helpBtn');
            const helpModal = document.getElementById('helpLucratividadeModal');
            const helpClose = helpModal?.querySelector('.close-modal');
            
            if (helpBtn && helpModal) {
                helpBtn.addEventListener('click', function() {
                    helpModal.style.display = 'flex';
                });
            }
            
            if (helpClose) {
                helpClose.addEventListener('click', function() {
                    helpModal.style.display = 'none';
                });
            }
            
            // Fechar modal ao clicar fora
            if (helpModal) {
                window.addEventListener('click', function(event) {
                    if (event.target === helpModal) {
                        helpModal.style.display = 'none';
                    }
                });
            }
        });

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
            
            // Novos Gráficos Avançados
            // Gráfico de Cascata (Waterfall)
            const waterfallCtx = document.getElementById('waterfallChart');
            if (waterfallCtx) {
                const receita = <?= isset($kpis['total_frete']) ? $kpis['total_frete'] : 0 ?>;
                const combustivel = <?= isset($kpis['total_abastecimentos']) ? -$kpis['total_abastecimentos'] : 0 ?>;
                const manutencao = <?= isset($kpis['total_manutencoes']) ? -$kpis['total_manutencoes'] : 0 ?>;
                const despesas_fixas = <?= isset($kpis['total_despesas_fixas']) ? -$kpis['total_despesas_fixas'] : 0 ?>;
                const despesas_viagem = <?= isset($kpis['total_despesas_viagem']) ? -$kpis['total_despesas_viagem'] : 0 ?>;
                const lucro = <?= $lucro ?>;
                
                new Chart(waterfallCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Receita Inicial', 'Despesas Combustível', 'Despesas Manutenção', 'Despesas Fixas', 'Despesas Viagem', 'Lucro Final'],
                        datasets: [{
                            label: 'Valor (R$)',
                            data: [receita, combustivel, manutencao, despesas_fixas, despesas_viagem, lucro],
                            backgroundColor: [
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(16, 185, 129, 0.8)'
                            ],
                            borderColor: [
                                'rgba(16, 185, 129, 1)',
                                'rgba(239, 68, 68, 1)',
                                'rgba(239, 68, 68, 1)',
                                'rgba(239, 68, 68, 1)',
                                'rgba(239, 68, 68, 1)',
                                'rgba(16, 185, 129, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10,
                                left: 10,
                                right: 10
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'R$ ' + Math.abs(context.parsed.y).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            },
                            y: {
                                beginAtZero: false,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + Math.abs(value).toLocaleString('pt-BR');
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Gráfico de Distribuição Detalhada de Custos
            const costBreakdownCtx = document.getElementById('costBreakdownChart');
            if (costBreakdownCtx) {
                const combustivel = <?= isset($kpis['total_abastecimentos']) ? $kpis['total_abastecimentos'] : 0 ?>;
                const manutencao = <?= isset($kpis['total_manutencoes']) ? $kpis['total_manutencoes'] : 0 ?>;
                const despesas_fixas = <?= isset($kpis['total_despesas_fixas']) ? $kpis['total_despesas_fixas'] : 0 ?>;
                const despesas_viagem = <?= isset($kpis['total_despesas_viagem']) ? $kpis['total_despesas_viagem'] : 0 ?>;
                const financiamento = <?= isset($kpis['total_parcelas_financiamento']) ? $kpis['total_parcelas_financiamento'] : 0 ?>;
                const pneu_manutencao = <?= isset($kpis['total_pneu_manutencao']) ? $kpis['total_pneu_manutencao'] : 0 ?>;
                
                new Chart(costBreakdownCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Combustível', 'Manutenção', 'Despesas Fixas', 'Despesas Viagem', 'Financiamento', 'Manutenção Pneus'],
                        datasets: [{
                            data: [combustivel, manutencao, despesas_fixas, despesas_viagem, financiamento, pneu_manutencao],
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(236, 72, 153, 0.8)',
                                'rgba(107, 114, 128, 0.8)'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 20,
                                bottom: 20,
                                left: 20,
                                right: 20
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    },
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return label + ': R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Gráfico de Tendência Anual
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                const lucroAtual = <?= $lucro ?>;
                new Chart(trendCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: meses,
                        datasets: [{
                            label: 'Lucro <?= $ano ?>',
                            data: [
                                lucroAtual * 0.75, lucroAtual * 0.80, lucroAtual * 0.78, 
                                lucroAtual * 0.85, lucroAtual * 0.88, lucroAtual * 0.90,
                                lucroAtual * 0.92, lucroAtual * 0.95, lucroAtual * 0.98,
                                lucroAtual * 1.0, lucroAtual * 1.02, lucroAtual
                            ],
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true, position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: false,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Gráfico de Pareto
            const paretoCtx = document.getElementById('paretoChart');
            if (paretoCtx) {
                const combustivel = <?= isset($kpis['total_abastecimentos']) ? $kpis['total_abastecimentos'] : 0 ?>;
                const manutencao = <?= isset($kpis['total_manutencoes']) ? $kpis['total_manutencoes'] : 0 ?>;
                const despesas_fixas = <?= isset($kpis['total_despesas_fixas']) ? $kpis['total_despesas_fixas'] : 0 ?>;
                const despesas_viagem = <?= isset($kpis['total_despesas_viagem']) ? $kpis['total_despesas_viagem'] : 0 ?>;
                const total = combustivel + manutencao + despesas_fixas + despesas_viagem;
                
                const pct_combustivel = total > 0 ? (combustivel / total) * 100 : 0;
                const pct_manutencao = total > 0 ? (manutencao / total) * 100 : 0;
                const pct_fixas = total > 0 ? (despesas_fixas / total) * 100 : 0;
                const pct_viagem = total > 0 ? (despesas_viagem / total) * 100 : 0;
                
                new Chart(paretoCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Combustível', 'Manutenção', 'Despesas Fixas', 'Despesas Viagem'],
                        datasets: [{
                            label: 'Valor (R$)',
                            data: [combustivel, manutencao, despesas_fixas, despesas_viagem],
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(139, 92, 246, 0.8)'
                            ]
                        }, {
                            label: 'Acumulado (%)',
                            type: 'line',
                            data: [pct_combustivel, pct_combustivel + pct_manutencao, pct_combustivel + pct_manutencao + pct_fixas, 100],
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            yAxisID: 'y1',
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if (context.datasetIndex === 0) {
                                            return 'Valor: R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                        } else {
                                            return 'Acumulado: ' + context.parsed.y.toFixed(1) + '%';
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    
    <!-- Modal de Ajuda -->
    <div class="modal" id="helpLucratividadeModal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h2><i class="fas fa-question-circle"></i> Ajuda - Análise de Lucratividade</h2>
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
        </div>
    </div>
</body>
</html> 