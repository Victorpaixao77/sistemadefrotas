<?php
// Habilitar logging de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Incluir arquivos necessários
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Garantir que a saída seja apenas JSON
header('Content-Type: application/json');

try {
    // Verificar autenticação usando a função do config.php
    require_authentication();

    // Obter conexão com o banco de dados
    $conn = getConnection();

    // Log da sessão
    error_log("Estado da sessão: " . print_r($_SESSION, true));

    // Processar filtros
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : null;
    $ano = isset($_GET['ano']) ? intval($_GET['ano']) : null;

    error_log("Filtros recebidos - Mês: $mes, Ano: $ano");

    // Construir a condição WHERE para os filtros
    $where_conditions = ["m.empresa_id = :empresa_id"];
    $params = [':empresa_id' => $_SESSION['empresa_id']];

    if ($mes && $ano) {
        $where_conditions[] = "MONTH(m.data_manutencao) = :mes AND YEAR(m.data_manutencao) = :ano";
        $params[':mes'] = $mes;
        $params[':ano'] = $ano;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    error_log("Cláusula WHERE: " . $where_clause);
    error_log("Parâmetros: " . print_r($params, true));

    // Custo total por mês
    $sql_custo_mensal = "SELECT 
        DATE_FORMAT(m.data_manutencao, '%Y-%m') as mes,
        COALESCE(SUM(m.custo), 0) as total_custo
        FROM pneu_manutencao m
        $where_clause
        GROUP BY DATE_FORMAT(m.data_manutencao, '%Y-%m')
        ORDER BY mes DESC
        LIMIT 12";

    error_log("SQL Custo Mensal: " . $sql_custo_mensal);
    $stmt = $conn->prepare($sql_custo_mensal);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta de custo mensal: " . $conn->errorInfo()[2]);
    }
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $custo_mensal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Custo Mensal: " . print_r($custo_mensal, true));

    // Quantidade por tipo
    $sql_quantidade_tipo = "SELECT 
        COALESCE(t.nome, 'Não especificado') as tipo,
        COUNT(*) as quantidade
        FROM pneu_manutencao m
        LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
        $where_clause
        GROUP BY t.nome
        ORDER BY quantidade DESC";

    error_log("SQL Quantidade por Tipo: " . $sql_quantidade_tipo);
    $stmt = $conn->prepare($sql_quantidade_tipo);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta de quantidade por tipo: " . $conn->errorInfo()[2]);
    }
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $quantidade_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Quantidade por Tipo: " . print_r($quantidade_tipo, true));

    // Top pneus com maior custo
    $sql_top_pneus = "SELECT 
        COALESCE(p.numero_serie, 'Não especificado') as pneu,
        COALESCE(SUM(m.custo), 0) as total_custo
        FROM pneu_manutencao m
        LEFT JOIN pneus p ON m.pneu_id = p.id
        $where_clause
        GROUP BY p.numero_serie
        ORDER BY total_custo DESC
        LIMIT 5";

    error_log("SQL Top Pneus: " . $sql_top_pneus);
    $stmt = $conn->prepare($sql_top_pneus);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta de top pneus: " . $conn->errorInfo()[2]);
    }
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $top_pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Top Pneus: " . print_r($top_pneus, true));

    // Média de custo por tipo
    $sql_media_tipo = "SELECT 
        COALESCE(t.nome, 'Não especificado') as tipo,
        COALESCE(AVG(m.custo), 0) as media_custo
        FROM pneu_manutencao m
        LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
        $where_clause
        GROUP BY t.nome
        ORDER BY media_custo DESC";

    error_log("SQL Média por Tipo: " . $sql_media_tipo);
    $stmt = $conn->prepare($sql_media_tipo);
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta de média por tipo: " . $conn->errorInfo()[2]);
    }
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $media_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Resultado Média por Tipo: " . print_r($media_tipo, true));

    $response_data = [
        'success' => true,
        'custo_mensal' => $custo_mensal,
        'quantidade_tipo' => $quantidade_tipo,
        'top_pneus' => $top_pneus,
        'media_tipo' => $media_tipo
    ];

    error_log("Dados finais a serem retornados: " . print_r($response_data, true));
    echo json_encode($response_data);

} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar dados: ' . $e->getMessage()
    ]);
}
?> 