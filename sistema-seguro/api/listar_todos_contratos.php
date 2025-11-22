<?php
/**
 * API - Listar Todos os Contratos
 * Retorna todos os contratos com informações do cliente
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Verificar se a tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'seguro_contratos_clientes'");
    if (!$stmt->fetch()) {
        echo json_encode([
            'sucesso' => true,
            'contratos' => [],
            'aviso' => 'Tabela de contratos não existe'
        ]);
        exit;
    }
    
    // Buscar porcentagem da empresa
    $stmt_empresa = $db->prepare("SELECT porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
    $stmt_empresa->execute([$empresa_id]);
    $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    $porcentagemEmpresa = floatval($empresa['porcentagem_fixa'] ?? 0);
    
    // Buscar todos os contratos com informações do cliente e atendimentos
    $sql = "SELECT 
                cc.*,
                c.nome_razao_social as cliente_nome,
                c.codigo as cliente_codigo,
                c.matricula as cliente_matricula,
                c.cpf_cnpj as cliente_cpf_cnpj,
                COUNT(DISTINCT CASE 
                    WHEN a.status IN ('aberto', 'em_andamento', 'aguardando') 
                    THEN a.id 
                END) as atendimentos_abertos,
                COUNT(DISTINCT a.id) as total_atendimentos
            FROM seguro_contratos_clientes cc
            INNER JOIN seguro_clientes c ON cc.cliente_id = c.id
            LEFT JOIN seguro_atendimentos a 
                ON a.matricula_conjunto COLLATE utf8mb4_general_ci = cc.matricula COLLATE utf8mb4_general_ci
                AND a.seguro_empresa_id = ?
            WHERE cc.empresa_id = ?
              AND cc.ativo = 'sim'
            GROUP BY cc.id
            ORDER BY cc.data_criacao DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$empresa_id, $empresa_id]);
    
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'contratos' => $contratos,
        'total' => count($contratos),
        'porcentagem_empresa' => $porcentagemEmpresa
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Erro ao listar contratos: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao buscar contratos',
        'erro_detalhado' => $e->getMessage(),
        'codigo_erro' => $e->getCode()
    ]);
} catch (Exception $e) {
    error_log("Erro geral ao listar contratos: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao buscar contratos',
        'erro_detalhado' => $e->getMessage()
    ]);
}
?>

