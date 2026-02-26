<?php
// Configurar exibição de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Garantir que a saída seja sempre em JSON
header('Content-Type: application/json');

// Incluir arquivos necessários
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Verificar autenticação
require_authentication();

// Obter conexão com o banco de dados
$conn = getConnection();

// Obter parâmetros
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$ano = isset($_GET['ano']) ? $_GET['ano'] : date('Y');
$action = isset($_GET['action']) ? $_GET['action'] : 'default';
$empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : 0;
// empresa_id na SQL: valor da sessão (int), seguro; :mes e :ano continuam como prepared params
$eid = $empresa_id;

if ($eid < 1) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida (empresa_id).', 'error' => 'empresa_id não definido']);
    exit;
}

// Log dos parâmetros recebidos
error_log("Parâmetros recebidos - mes: " . $mes . ", ano: " . $ano . ", action: " . $action);
error_log("Empresa ID: " . $empresa_id);

try {
    if ($action === 'charts') {
        // 1. Fretes vs Despesas (mensal)
        $sql_fretes_vs_despesas = "
            WITH dados AS (
                SELECT 
                    MONTH(data) as mes,
                    YEAR(data) as ano,
                    SUM(CASE WHEN tipo = 'frete' THEN valor ELSE 0 END) as fretes,
                    SUM(CASE WHEN tipo != 'frete' THEN valor ELSE 0 END) as despesas
                FROM (
                    SELECT data_rota as data, 'frete' as tipo, frete as valor FROM rotas WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT created_at as data, 'despesa' as tipo, 
                           (COALESCE(descarga, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                            COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                            COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
                    FROM despesas_viagem WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT vencimento as data, 'despesa' as tipo, valor FROM despesas_fixas 
                    WHERE empresa_id = " . $eid . " AND status_pagamento_id = 2
                    UNION ALL
                    SELECT data_vencimento as data, 'despesa' as tipo, valor FROM contas_pagar 
                    WHERE empresa_id = " . $eid . "
                ) as dados
                GROUP BY YEAR(data), MONTH(data)
                ORDER BY ano, mes
            )
            SELECT * FROM dados
            WHERE (ano < :ano1) OR (ano = :ano2 AND mes <= :mes)
            ORDER BY ano, mes
            LIMIT 12
        ";
        
        // 2. Distribuição das Despesas
        $sql_distribuicao_despesas = "
            SELECT 
                tipo,
                SUM(valor) as total
            FROM (
                SELECT 'Abastecimento' as tipo, valor_total as valor FROM abastecimentos WHERE empresa_id = " . $eid . "
                UNION ALL
                SELECT 'Comissão' as tipo, comissao as valor FROM rotas WHERE empresa_id = " . $eid . "
                UNION ALL
                SELECT 'Despesa Fixa' as tipo, valor FROM despesas_fixas WHERE empresa_id = " . $eid . " AND status_pagamento_id = 2
                UNION ALL
                SELECT 'Conta a Pagar' as tipo, valor FROM contas_pagar WHERE empresa_id = " . $eid . "
                UNION ALL
                SELECT 'Despesa de Viagem' as tipo, 
                       (COALESCE(descarga, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                        COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                        COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
                FROM despesas_viagem WHERE empresa_id = " . $eid . "
            ) as despesas
            GROUP BY tipo
            ORDER BY total DESC
        ";
        
        // 3. Evolução da Lucratividade
        $sql_evolucao_lucratividade = "
            WITH dados AS (
                SELECT 
                    MONTH(data) as mes,
                    YEAR(data) as ano,
                    SUM(CASE WHEN tipo = 'frete' THEN valor ELSE -valor END) as lucro
                FROM (
                    SELECT data_rota as data, 'frete' as tipo, frete as valor FROM rotas WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT created_at as data, 'despesa' as tipo, 
                           (COALESCE(descarga, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                            COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                            COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
                    FROM despesas_viagem WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT vencimento as data, 'despesa' as tipo, valor FROM despesas_fixas 
                    WHERE empresa_id = " . $eid . " AND status_pagamento_id = 2
                    UNION ALL
                    SELECT data_vencimento as data, 'despesa' as tipo, valor FROM contas_pagar 
                    WHERE empresa_id = " . $eid . "
                ) as dados
                GROUP BY YEAR(data), MONTH(data)
                ORDER BY ano, mes
            )
            SELECT * FROM dados
            WHERE (ano < :ano1) OR (ano = :ano2 AND mes <= :mes)
            ORDER BY ano, mes
            LIMIT 12
        ";
        
        // 4. Composição do Frete
        $sql_composicao_frete = "
            WITH dados AS (
                SELECT 
                    MONTH(data) as mes,
                    YEAR(data) as ano,
                    SUM(CASE WHEN tipo = 'comissao' THEN valor ELSE 0 END) as comissoes,
                    SUM(CASE WHEN tipo = 'abastecimento' THEN valor ELSE 0 END) as abastecimentos,
                    SUM(CASE WHEN tipo NOT IN ('comissao', 'abastecimento') THEN valor ELSE 0 END) as outras_despesas
                FROM (
                    SELECT data_rota as data, 'comissao' as tipo, comissao as valor FROM rotas WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT data_abastecimento as data, 'abastecimento' as tipo, valor_total as valor FROM abastecimentos WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT created_at as data, 'outra' as tipo, 
                           (COALESCE(descarga, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                            COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                            COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
                    FROM despesas_viagem WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT vencimento as data, 'outra' as tipo, valor FROM despesas_fixas 
                    WHERE empresa_id = " . $eid . " AND status_pagamento_id = 2
                    UNION ALL
                    SELECT data_vencimento as data, 'outra' as tipo, valor FROM contas_pagar 
                    WHERE empresa_id = " . $eid . "
                ) as dados
                GROUP BY YEAR(data), MONTH(data)
                ORDER BY ano, mes
            )
            SELECT * FROM dados
            WHERE (ano < :ano1) OR (ano = :ano2 AND mes <= :mes)
            ORDER BY ano, mes
            LIMIT 12
        ";
        
        // 5. Lucro por Veículo (contas_pagar não entram por veículo se a tabela não tiver veiculo_id)
        $sql_lucro_por_veiculo = "
            WITH dados AS (
                SELECT 
                    v.placa as veiculo,
                    SUM(CASE WHEN r.id IS NOT NULL THEN r.frete ELSE 0 END) as receita,
                    SUM(CASE 
                        WHEN dv.id IS NOT NULL THEN (
                            COALESCE(dv.descarga, 0) + COALESCE(dv.pedagios, 0) + COALESCE(dv.caixinha, 0) + 
                            COALESCE(dv.estacionamento, 0) + COALESCE(dv.lavagem, 0) + COALESCE(dv.borracharia, 0) + 
                            COALESCE(dv.eletrica_mecanica, 0) + COALESCE(dv.adiantamento, 0)
                        )
                        ELSE 0 
                    END) as despesa_viagem,
                    SUM(CASE WHEN df.id IS NOT NULL THEN df.valor ELSE 0 END) as despesa_fixa,
                    0 as conta_pagar
                FROM veiculos v
                LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = " . $eid . "
                LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id AND dv.empresa_id = " . $eid . "
                LEFT JOIN despesas_fixas df ON df.veiculo_id = v.id AND df.empresa_id = " . $eid . " AND df.status_pagamento_id = 2
                WHERE v.empresa_id = " . $eid . "
                GROUP BY v.id, v.placa
            )
            SELECT 
                veiculo,
                (receita - (despesa_viagem + despesa_fixa + conta_pagar)) as lucro
            FROM dados
            ORDER BY lucro DESC
        ";
        
        // 6. Lucro por Dia da Semana
        $sql_lucro_por_dia = "
            WITH dados AS (
                SELECT 
                    DAYOFWEEK(data) as dia_semana,
                    SUM(CASE WHEN tipo = 'frete' THEN valor ELSE -valor END) as lucro
                FROM (
                    SELECT data_rota as data, 'frete' as tipo, frete as valor FROM rotas WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT created_at as data, 'despesa' as tipo, 
                           (COALESCE(descarga, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) + 
                            COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) + 
                            COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0)) as valor 
                    FROM despesas_viagem WHERE empresa_id = " . $eid . "
                    UNION ALL
                    SELECT vencimento as data, 'despesa' as tipo, valor FROM despesas_fixas 
                    WHERE empresa_id = " . $eid . " AND status_pagamento_id = 2
                    UNION ALL
                    SELECT data_vencimento as data, 'despesa' as tipo, valor FROM contas_pagar 
                    WHERE empresa_id = " . $eid . "
                ) as dados
                GROUP BY DAYOFWEEK(data)
            )
            SELECT 
                CASE dia_semana
                    WHEN 1 THEN 'Domingo'
                    WHEN 2 THEN 'Segunda'
                    WHEN 3 THEN 'Terça'
                    WHEN 4 THEN 'Quarta'
                    WHEN 5 THEN 'Quinta'
                    WHEN 6 THEN 'Sexta'
                    WHEN 7 THEN 'Sábado'
                END as dia,
                lucro
            FROM dados
            ORDER BY dia_semana
        ";
        
        // Parâmetros para queries 1, 3, 4 (cada nome único: PDO não aceita mesmo nome duas vezes)
        $params = [
            ':mes' => $mes,
            ':ano1' => $ano,
            ':ano2' => $ano
        ];
        
        error_log("Parâmetros para consultas: " . print_r($params, true));
        
        // Verificar parâmetros obrigatórios
        $required_params = [':mes', ':ano1', ':ano2'];
        
        foreach ($required_params as $param) {
            if (!isset($params[$param])) {
                error_log("Parâmetro faltando: " . $param);
                throw new Exception("Parâmetro obrigatório não definido: " . $param);
            }
        }
        
        // Executar cada query individualmente para identificar qual está causando o erro
        try {
            error_log("Executando query Fretes vs Despesas...");
            $stmt = $conn->prepare($sql_fretes_vs_despesas);
            foreach ($params as $key => $value) {
                error_log("Binding parameter: " . $key . " = " . $value);
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $fretes_vs_despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Resultado Fretes vs Despesas: " . print_r($fretes_vs_despesas, true));
        } catch (Exception $e) {
            error_log("Erro na query Fretes vs Despesas: " . $e->getMessage());
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
            foreach ($params as $key => $value) {
                error_log("Binding parameter: " . $key . " = " . $value);
                $stmt->bindValue($key, $value);
            }
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
            foreach ($params as $key => $value) {
                error_log("Binding parameter: " . $key . " = " . $value);
                $stmt->bindValue($key, $value);
            }
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
        
        // Formatar dados para o gráfico de distribuição de despesas
        $distribuicao_despesas_formatted = [];
        foreach ($distribuicao_despesas as $row) {
            $distribuicao_despesas_formatted[$row['tipo']] = $row['total'];
        }
        error_log("Distribuição Despesas Formatada: " . print_r($distribuicao_despesas_formatted, true));
        
        // Formatar dados para o gráfico de lucro por dia
        $lucro_por_dia_formatted = [];
        foreach ($lucro_por_dia as $row) {
            $lucro_por_dia_formatted[$row['dia']] = $row['lucro'];
        }
        error_log("Lucro por Dia Formatado: " . print_r($lucro_por_dia_formatted, true));
        
        $response = [
            'success' => true,
            'data' => [
                'fretes_vs_despesas' => $fretes_vs_despesas,
                'distribuicao_despesas' => $distribuicao_despesas_formatted,
                'evolucao_lucratividade' => $evolucao_lucratividade,
                'composicao_frete' => $composicao_frete,
                'lucro_por_veiculo' => $lucro_por_veiculo,
                'lucro_por_dia' => $lucro_por_dia_formatted
            ]
        ];
        
        error_log("Resposta final: " . print_r($response, true));
        
        echo json_encode($response);
    } else {
        throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro em lucratividade_analytics.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição.',
        'error' => $e->getMessage()
    ]);
} 