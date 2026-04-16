<?php
/**
 * 📄 API de NF-e
 * 🏢 Gerencia operações de Nota Fiscal Eletrônica
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
    
    // Obter dados do POST (esta rota recebe tanto JSON quanto FormData/multipart)
    $empresa_id = $_SESSION['empresa_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $conn = getConnection();
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
            $stmt = $conn->prepare("
                SELECT id, numero_nfe, cliente_razao_social, data_emissao, valor_total, status
                FROM fiscal_nfe_clientes
                WHERE empresa_id = ?
                ORDER BY data_emissao DESC, id DESC
                LIMIT ?
            ");
            $stmt->execute([$empresa_id, $limit]);
            $nfe_list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de NF-e carregada',
                'data' => $nfe_list,
                'total' => count($nfe_list)
            ]);
            break;
            
        case 'importar_xml':
            if (empty($_FILES['xml_file']) || empty($_FILES['xml_file']['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Arquivo XML não enviado (campo xml_file).']);
                break;
            }
            // Encaminhar para a implementação completa do fluxo no V2
            $_POST['action'] = 'receber_nfe_xml';
            require_once __DIR__ . '/documentos_fiscais_v2.php';
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
