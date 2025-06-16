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

// Obter a ação solicitada
$action = $_GET['action'] ?? '';

// Processar a ação
switch ($action) {
    case 'view':
        $id = $_GET['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }
        
        // Buscar dados da manutenção
        $sql = "SELECT m.*, p.numero_serie as pneu_numero, v.placa as veiculo_placa, t.nome as tipo_nome
                FROM pneu_manutencao m
                LEFT JOIN pneus p ON m.pneu_id = p.id
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
                WHERE m.id = :id AND m.empresa_id = :empresa_id";
        
        try {
            $manutencao = fetchOne($conn, $sql, [
                ':id' => $id,
                ':empresa_id' => $_SESSION['empresa_id']
            ]);
            
            if ($manutencao) {
                echo json_encode(['success' => true, 'data' => $manutencao]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Manutenção não encontrada']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar manutenção: ' . $e->getMessage()]);
        }
        break;
        
    case 'list':
        // Buscar todas as manutenções
        $sql = "SELECT m.*, p.numero_serie as pneu_numero, v.placa as veiculo_placa, t.nome as tipo_nome
                FROM pneu_manutencao m
                LEFT JOIN pneus p ON m.pneu_id = p.id
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
                WHERE m.empresa_id = :empresa_id
                ORDER BY m.data_manutencao DESC";
        
        try {
            $manutencoes = executeQuery($conn, $sql, [':empresa_id' => $_SESSION['empresa_id']]);
            echo json_encode(['success' => true, 'data' => $manutencoes]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao listar manutenções: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
} 