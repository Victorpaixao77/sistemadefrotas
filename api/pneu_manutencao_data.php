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
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $allowed = [5, 10, 20, 50, 100];
        if (!in_array($per_page, $allowed)) {
            $per_page = 20;
        }
        $offset = ($page - 1) * $per_page;
        
        $count_sql = "SELECT COUNT(*) as total FROM pneu_manutencao WHERE empresa_id = :empresa_id";
        $stmt = $conn->prepare($count_sql);
        $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
        $stmt->execute();
        $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $sql = "SELECT m.*, p.numero_serie as pneu_numero, v.placa as veiculo_placa, t.nome as tipo_nome
                FROM pneu_manutencao m
                LEFT JOIN pneus p ON m.pneu_id = p.id
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
                WHERE m.empresa_id = :empresa_id
                ORDER BY m.data_manutencao DESC
                LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $manutencoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'data' => $manutencoes,
                'pagination' => [
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 1
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro ao listar manutenções: ' . $e->getMessage()]);
        }
        break;

    case 'list_by_pneu':
        $pneu_id = isset($_GET['pneu_id']) ? (int)$_GET['pneu_id'] : 0;
        if ($pneu_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID do pneu não fornecido']);
            exit;
        }
        $sql = "SELECT m.id, m.data_manutencao, m.custo, m.km_veiculo, m.observacoes, m.pneu_id, m.veiculo_id,
                       v.placa as veiculo_placa, t.nome as tipo_nome
                FROM pneu_manutencao m
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
                WHERE m.pneu_id = :pneu_id AND m.empresa_id = :empresa_id
                ORDER BY m.data_manutencao DESC";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':pneu_id', $pneu_id, PDO::PARAM_INT);
            $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
            $stmt->execute();
            $manutencoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $manutencoes]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
} 