<?php
require_once '../includes/pneus_api_base.php';

$empresa_id = pneus_empresa_id();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'update_status') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        api_json_method_not_allowed('Use POST para atualizar status.');
    }
    api_require_csrf_json();
}

try {
    $conn = getConnection();
} catch (PDOException $e) {
    error_log('estoque_pneus.php conexão: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Erro de conexão com o banco de dados']);
    exit;
}

switch ($action) {
    case 'get_estoque':
        try {
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            
            $sql = "SELECT 
                p.id as pneu_id,
                p.empresa_id,
                p.numero_serie,
                p.marca,
                p.modelo,
                p.medida,
                p.sulco_inicial,
                p.dot,
                p.km_instalacao,
                p.data_instalacao,
                p.vida_util_km,
                p.numero_recapagens,
                p.data_ultima_recapagem,
                p.lote,
                p.data_entrada,
                p.status_id,
                p.observacoes,
                p.created_at as pneu_created_at,
                p.updated_at as pneu_updated_at,
                s.nome as status_nome,
                ep.disponivel
            FROM pneus p
            LEFT JOIN status_pneus s ON p.status_id = s.id
            LEFT JOIN estoque_pneus ep ON ep.pneu_id = p.id
            WHERE p.empresa_id = :empresa_id
            AND " . pneus_sql_nao_alocado('p.id');
            
            $params = [':empresa_id' => $empresa_id];
            
            if ($status !== '') {
                $sql .= " AND p.status_id = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'pneus' => $pneus]);
        } catch (PDOException $e) {
            error_log("Erro ao buscar estoque de pneus: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => 'Erro ao buscar dados do estoque']);
        }
        break;
        
    case 'update_status':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['pneu_id']) || !isset($data['status_id']) || !isset($data['disponivel'])) {
                throw new Exception('Dados incompletos');
            }
            
            $pneu_id = (int) $data['pneu_id'];
            $status_id = (int) $data['status_id'];
            $disponivel = (int) $data['disponivel'];

            if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
                throw new Exception('Pneu não encontrado');
            }

            pneus_sync_status_estoque($conn, $pneu_id, $empresa_id, $status_id, $disponivel);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
        } catch (Exception $e) {
            error_log('Erro ao atualizar status do pneu: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => 'Erro ao atualizar status do pneu']);
        }
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Ação inválida']);
        break;
} 