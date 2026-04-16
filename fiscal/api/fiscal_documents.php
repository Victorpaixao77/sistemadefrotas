<?php
/**
 * 📋 API de Documentos Fiscais
 * 📄 Retorna documentos recentes e por tipo
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

// Incluir configurações
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

try {
    // Configurar sessão
    configure_session();
    session_start();
    
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $empresa_id = $input['empresa_id'] ?? 1; // Usar empresa_id padrão se não fornecido
    $action = $input['action'] ?? 'get_recent';
    
    $conn = getConnection();
    $limit = 20;

    // NF-e
    $stmtNfe = $conn->prepare("
        SELECT id, numero_nfe, cliente_razao_social, data_emissao, valor_total, status, protocolo_autorizacao
        FROM fiscal_nfe_clientes
        WHERE empresa_id = ?
        ORDER BY data_emissao DESC, id DESC
        LIMIT $limit
    ");
    $stmtNfe->execute([$empresa_id]);
    $nfe = $stmtNfe->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // CT-e
    $stmtCte = $conn->prepare("
        SELECT id, numero_cte, origem_cidade, destino_cidade, data_emissao, valor_total, status, protocolo_autorizacao
        FROM fiscal_cte
        WHERE empresa_id = ?
        ORDER BY data_emissao DESC, id DESC
        LIMIT $limit
    ");
    $stmtCte->execute([$empresa_id]);
    $cte = $stmtCte->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // MDF-e
    $stmtMdfe = $conn->prepare("
        SELECT
            id,
            numero_mdfe,
            tipo_transporte,
            peso_total_carga,
            data_emissao,
            valor_total_carga AS valor_total,
            status,
            protocolo_autorizacao
        FROM fiscal_mdfe
        WHERE empresa_id = ?
        ORDER BY data_emissao DESC, id DESC
        LIMIT $limit
    ");
    $stmtMdfe->execute([$empresa_id]);
    $mdfe = $stmtMdfe->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $documents_data = [
        'nfe' => $nfe,
        'cte' => $cte,
        'mdfe' => $mdfe,
    ];
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Documentos carregados com sucesso',
        'data' => $documents_data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
