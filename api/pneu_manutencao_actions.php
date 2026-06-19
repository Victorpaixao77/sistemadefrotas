<?php
require_once '../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneu_movimentacoes_helper.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = pneus_empresa_id();
$action = $_GET['action'] ?? '';

if (in_array($action, ['add', 'update', 'delete', 'create', 'edit'], true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        api_json_method_not_allowed('Use POST para alterar dados.');
    }
    api_require_csrf_json();
}

function pneu_manut_validar_fks(PDO $conn, int $empresa_id, array $data): ?string
{
    $pneu_id = (int) ($data['pneu_id'] ?? 0);
    if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
        return 'Pneu inválido para esta empresa';
    }
    $veiculo_id = isset($data['veiculo_id']) && $data['veiculo_id'] !== '' ? (int) $data['veiculo_id'] : null;
    if (!pneus_validar_veiculo_empresa($conn, $veiculo_id, $empresa_id)) {
        return 'Veículo inválido para esta empresa';
    }
    return null;
}

switch ($action) {
    case 'add':
        // Receber dados do POST
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar dados obrigatórios
        if (empty($data['data_manutencao']) || empty($data['pneu_id']) || 
            empty($data['tipo_manutencao_id']) || empty($data['km_veiculo']) || 
            empty($data['custo'])) {
            echo json_encode(['success' => false, 'error' => 'Dados obrigatórios não fornecidos']);
            exit;
        }
        $fkErr = pneu_manut_validar_fks($conn, $empresa_id, $data);
        if ($fkErr) {
            echo json_encode(['success' => false, 'error' => $fkErr]);
            exit;
        }
        
        // Inserir manutenção
        $sql = "INSERT INTO pneu_manutencao (
                    empresa_id, pneu_id, veiculo_id, data_manutencao, 
                    km_veiculo, custo, observacoes, tipo_manutencao_id
                ) VALUES (
                    :empresa_id, :pneu_id, :veiculo_id, :data_manutencao,
                    :km_veiculo, :custo, :observacoes, :tipo_manutencao_id
                )";
        
        $params = [
            ':empresa_id' => $empresa_id,
            ':pneu_id' => $data['pneu_id'],
            ':veiculo_id' => $data['veiculo_id'] ?: null,
            ':data_manutencao' => $data['data_manutencao'],
            ':km_veiculo' => $data['km_veiculo'],
            ':custo' => $data['custo'],
            ':observacoes' => $data['observacoes'] ?: null,
            ':tipo_manutencao_id' => $data['tipo_manutencao_id']
        ];
        
        try {
            executeNonQuery($conn, $sql, $params);
            // Histórico único: recapagem ou manutenção
            $tipo_nome = null;
            try {
                $st = $conn->prepare("SELECT nome FROM tipo_manutencao_pneus WHERE id = ?");
                $st->execute([$data['tipo_manutencao_id']]);
                if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $tipo_nome = trim($row['nome']);
                }
            } catch (Exception $e) {}
            $tipo_mov = (strtolower($tipo_nome ?? '') === 'recapagem') ? 'recapagem' : 'manutencao';
            pneu_movimentacao_inserir($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id'    => (int) $data['pneu_id'],
                'tipo'       => $tipo_mov,
                'veiculo_id' => !empty($data['veiculo_id']) ? (int) $data['veiculo_id'] : null,
                'custo'      => (float) $data['custo'],
                'observacoes'=> $data['observacoes'] ?? null,
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao adicionar manutenção']);
        }
        break;
        
    case 'update':
        // Receber dados do POST
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? 0;
        
        // Validar dados obrigatórios
        if (empty($id) || empty($data['data_manutencao']) || empty($data['pneu_id']) || 
            empty($data['tipo_manutencao_id']) || empty($data['km_veiculo']) || 
            empty($data['custo'])) {
            echo json_encode(['success' => false, 'error' => 'Dados obrigatórios não fornecidos']);
            exit;
        }
        $fkErr = pneu_manut_validar_fks($conn, $empresa_id, $data);
        if ($fkErr) {
            echo json_encode(['success' => false, 'error' => $fkErr]);
            exit;
        }
        
        // Atualizar manutenção
        $sql = "UPDATE pneu_manutencao SET 
                    pneu_id = :pneu_id,
                    veiculo_id = :veiculo_id,
                    data_manutencao = :data_manutencao,
                    km_veiculo = :km_veiculo,
                    custo = :custo,
                    observacoes = :observacoes,
                    tipo_manutencao_id = :tipo_manutencao_id
                WHERE id = :id AND empresa_id = :empresa_id";
        
        $params = [
            ':id' => $id,
            ':empresa_id' => $empresa_id,
            ':pneu_id' => $data['pneu_id'],
            ':veiculo_id' => $data['veiculo_id'] ?: null,
            ':data_manutencao' => $data['data_manutencao'],
            ':km_veiculo' => $data['km_veiculo'],
            ':custo' => $data['custo'],
            ':observacoes' => $data['observacoes'] ?: null,
            ':tipo_manutencao_id' => $data['tipo_manutencao_id']
        ];
        
        try {
            executeNonQuery($conn, $sql, $params);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar manutenção']);
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }
        
        // Excluir manutenção
        $sql = "DELETE FROM pneu_manutencao WHERE id = :id AND empresa_id = :empresa_id";
        
        try {
            executeNonQuery($conn, $sql, [
                ':id' => $id,
                ':empresa_id' => $_SESSION['empresa_id']
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir manutenção']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
} 