<?php
/**
 * 📋 API de MDF-e
 * 🚛 Gerencia operações de Manifesto de Documentos Fiscais Eletrônicos
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
    
    $empresa_id = $_SESSION['empresa_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $conn = getConnection();
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
            $stmt = $conn->prepare("
                SELECT
                    id,
                    numero_mdfe,
                    tipo_transporte,
                    peso_total_carga,
                    data_emissao,
                    valor_total_carga AS valor_total,
                    status
                FROM fiscal_mdfe
                WHERE empresa_id = ?
                ORDER BY data_emissao DESC, id DESC
                LIMIT ?
            ");
            $stmt->execute([$empresa_id, $limit]);
            $mdfe_list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de MDF-e carregada',
                'data' => $mdfe_list,
                'total' => count($mdfe_list)
            ]);
            break;
            
        case 'emitir':
            $conn = getConnection();

            $numero_mdfe = $_POST['numero_mdfe'] ?? '';
            $serie_mdfe = $_POST['serie_mdfe'] ?? '1';
            $data_emissao = $_POST['data_emissao'] ?? date('Y-m-d');
            $tipo_transporte = $_POST['tipo_transporte'] ?? 'rodoviario';
            $peso_total_carga = $_POST['peso_total_carga'] ?? 0.00;
            $qtd_total_volumes = $_POST['qtd_total_volumes'] ?? 0;
            $motorista_id = $_POST['motorista_id'] ?? null;
            $veiculo_id = $_POST['veiculo_id'] ?? null;
            $observacoes = $_POST['observacoes'] ?? '';
            $cte_ids = $_POST['cte_ids'] ?? null;

            if ($numero_mdfe === '') {
                throw new Exception('numero_mdfe é obrigatório');
            }
            if ($qtd_total_volumes === '' || (int)$qtd_total_volumes <= 0) {
                $qtd_total_volumes = 0;
            }

            $valor_total_carga = 0.00;
            if (is_array($cte_ids) && !empty($cte_ids)) {
                $placeholders = str_repeat('?,', count($cte_ids) - 1) . '?';
                $stmtCte = $conn->prepare("
                    SELECT COALESCE(SUM(valor_total), 0) AS total
                    FROM fiscal_cte
                    WHERE empresa_id = ? AND id IN ($placeholders)
                ");
                // params: empresa_id first, then ids
                $params = array_merge([$empresa_id], array_map('intval', $cte_ids));
                $stmtCte->execute($params);
                $rowTotal = $stmtCte->fetch(PDO::FETCH_ASSOC);
                $valor_total_carga = (float)($rowTotal['total'] ?? 0);
            } elseif (isset($_POST['valor_total_carga'])) {
                $valor_total_carga = (float)$_POST['valor_total_carga'];
            }

            $stmt = $conn->prepare("
                INSERT INTO fiscal_mdfe (
                    empresa_id, numero_mdfe, serie_mdfe, chave_acesso, data_emissao,
                    tipo_transporte, protocolo_autorizacao, status,
                    valor_total_carga, peso_total_carga, qtd_total_volumes, qtd_total_peso,
                    motorista_id, veiculo_id, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $empresa_id,
                $numero_mdfe,
                $serie_mdfe,
                null,
                $data_emissao,
                $tipo_transporte,
                null,
                'pendente',
                (float)$valor_total_carga,
                (float)$peso_total_carga,
                (int)$qtd_total_volumes,
                (float)$peso_total_carga,
                $motorista_id !== '' ? (int)$motorista_id : null,
                $veiculo_id !== '' ? (int)$veiculo_id : null,
                $observacoes
            ]);

            $mdfe_id = (int)$conn->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'MDF-e criado com sucesso (rascunho/pendente).',
                'data' => [
                    'id' => $mdfe_id,
                    'numero_mdfe' => $numero_mdfe,
                    'status' => 'pendente',
                ]
            ]);
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
