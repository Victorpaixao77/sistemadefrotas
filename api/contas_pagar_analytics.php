<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configurar log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Garantir que a saída será sempre JSON
header('Content-Type: application/json');

// Verificar autenticação
require_authentication();

// Obter conexão com o banco de dados
$conn = getConnection();

// Obter parâmetros
$mes = $_GET['mes'] ?? null;
$action = $_GET['action'] ?? null;

error_log("Parâmetros recebidos - mes: " . ($mes ? $mes : 'vazio') . ", action: " . ($action ? $action : 'vazio'));
error_log("GET completo: " . print_r($_GET, true));

// Construir condições do WHERE
$where_conditions = [];
$params = [];

// Adicionar filtro de empresa
$where_conditions[] = "cp.empresa_id = :empresa_id";
$params[':empresa_id'] = $_SESSION['empresa_id'];

// Adicionar filtro de mês se fornecido
if ($mes) {
    // Extrair mês e ano do parâmetro (formato esperado: YYYY-MM)
    $mes_ano = explode('-', $mes);
    if (count($mes_ano) === 2) {
        $ano = $mes_ano[0];
        $mes_num = $mes_ano[1];
        error_log("Filtro de mês aplicado - Mês: $mes_num, Ano: $ano");
        
        // Adicionar condição para filtrar por mês e ano
        $where_conditions[] = "MONTH(cp.data_vencimento) = :mes AND YEAR(cp.data_vencimento) = :ano";
        $params[':mes'] = $mes_num;
        $params[':ano'] = $ano;
        
        // Para contas pagas, usar data_pagamento
        $where_conditions[] = "(cp.status_id != 2 OR (cp.status_id = 2 AND MONTH(cp.data_pagamento) = :mes AND YEAR(cp.data_pagamento) = :ano))";
    } else {
        error_log("Formato de mês inválido: $mes");
    }
}

$where_clause = implode(' AND ', $where_conditions);
error_log("Where clause gerada: " . $where_clause);
error_log("Parâmetros da query: " . print_r($params, true));

try {
    if ($action === 'kpis') {
        error_log("Iniciando busca de KPIs");
        $sql = "SELECT 
            -- Total a Pagar: soma de todas as contas em aberto (status_id = 1) do mês filtrado
            COALESCE(SUM(CASE 
                WHEN cp.status_id = 1 
                THEN cp.valor 
                ELSE 0 
            END), 0) as total_pagar,

            -- Contas Vencidas: contagem e soma de contas vencidas (status_id = 4) do mês filtrado
            COALESCE(COUNT(CASE 
                WHEN cp.status_id = 4 
                THEN 1 
            END), 0) as contas_vencidas,
            COALESCE(SUM(CASE 
                WHEN cp.status_id = 4 
                THEN cp.valor 
                ELSE 0 
            END), 0) as valor_vencidas,

            -- A Vencer (7 dias): sempre mostra os próximos 7 dias a partir de hoje
            COALESCE(COUNT(CASE 
                WHEN cp.status_id = 1 
                AND cp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                THEN 1 
            END), 0) as contas_vencer,
            COALESCE(SUM(CASE 
                WHEN cp.status_id = 1 
                AND cp.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                THEN cp.valor 
                ELSE 0 
            END), 0) as valor_vencer,

            -- Pagas (Este Mês): contagem e soma de contas pagas (status_id = 2) do mês filtrado
            COALESCE(COUNT(CASE 
                WHEN cp.status_id = 2 
                THEN 1 
            END), 0) as contas_pagas,
            COALESCE(SUM(CASE 
                WHEN cp.status_id = 2 
                THEN cp.valor 
                ELSE 0 
            END), 0) as valor_pagas
        FROM contas_pagar cp 
        WHERE $where_clause";

        error_log("SQL KPI: " . $sql);
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            error_log("Binding parameter: $key = $value");
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Resultado KPIs: " . print_r($result, true));
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } elseif ($action === 'charts') {
        error_log("Iniciando busca de dados para gráficos");
        
        // Gráfico de categorias
        $sql_categorias = "SELECT 
            COALESCE(s.nome, 'Sem Status') as status_nome,
            COUNT(*) as quantidade,
            COALESCE(SUM(cp.valor), 0) as total
        FROM contas_pagar cp
        LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
        WHERE $where_clause
        GROUP BY cp.status_id, s.nome
        ORDER BY s.nome";
        
        error_log("SQL Categorias: " . $sql_categorias);
        
        $stmt = $conn->prepare($sql_categorias);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Resultado Categorias: " . print_r($categorias, true));
        
        // Gráfico de evolução
        $sql_evolucao = "SELECT 
            MONTH(cp.data_vencimento) as mes,
            YEAR(cp.data_vencimento) as ano,
            COALESCE(SUM(cp.valor), 0) as total
        FROM contas_pagar cp
        WHERE $where_clause
        GROUP BY YEAR(cp.data_vencimento), MONTH(cp.data_vencimento)
        ORDER BY ano, mes";
        
        error_log("SQL Evolução: " . $sql_evolucao);
        
        $stmt = $conn->prepare($sql_evolucao);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Resultado Evolução: " . print_r($evolucao, true));
        
        echo json_encode([
            'success' => true,
            'data' => [
                'categorias' => $categorias,
                'evolucao' => $evolucao
            ]
        ]);
    } else {
        throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 