<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

// Obter conexão com o banco de dados
$conn = getConnection();

// Verificar se a empresa existe
$sql = "SELECT id FROM empresa_clientes WHERE id = :empresa_id";
$empresa = fetchOne($conn, $sql, [':empresa_id' => $_SESSION['empresa_id']]);

if (!$empresa) {
    echo json_encode(['success' => false, 'error' => 'Empresa não encontrada']);
    exit;
}

// Obter a ação solicitada
$action = $_GET['action'] ?? '';

// Processar a ação
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
        
        // Inserir manutenção
        $sql = "INSERT INTO pneu_manutencao (
                    empresa_id, pneu_id, veiculo_id, data_manutencao, 
                    km_veiculo, custo, observacoes, tipo_manutencao_id
                ) VALUES (
                    :empresa_id, :pneu_id, :veiculo_id, :data_manutencao,
                    :km_veiculo, :custo, :observacoes, :tipo_manutencao_id
                )";
        
        $params = [
            ':empresa_id' => $_SESSION['empresa_id'],
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
            echo json_encode(['success' => false, 'error' => 'Erro ao adicionar manutenção: ' . $e->getMessage()]);
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
            ':empresa_id' => $_SESSION['empresa_id'],
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
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar manutenção: ' . $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir manutenção: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
} 