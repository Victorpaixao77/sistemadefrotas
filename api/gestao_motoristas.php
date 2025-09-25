<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

// Obtém a ação e os dados da requisição
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {
    case 'aprovar_rota':
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID da rota não fornecido']);
            exit;
        }

        $stmt = $conn->prepare('
            UPDATE rotas 
            SET status = "aprovado", 
                data_atualizacao = NOW() 
            WHERE id = :id 
            AND empresa_id = :empresa_id
        ');
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao aprovar rota']);
        }
        break;

    case 'rejeitar_rota':
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID da rota não fornecido']);
            exit;
        }

        $stmt = $conn->prepare('
            UPDATE rotas 
            SET status = "rejeitado", 
                data_atualizacao = NOW() 
            WHERE id = :id 
            AND empresa_id = :empresa_id
        ');
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao rejeitar rota']);
        }
        break;

    case 'aprovar_abastecimento':
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID do abastecimento não fornecido']);
            exit;
        }

        $stmt = $conn->prepare('
            UPDATE abastecimentos 
            SET status = "aprovado", 
                data_atualizacao = NOW() 
            WHERE id = :id 
            AND empresa_id = :empresa_id
        ');
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao aprovar abastecimento']);
        }
        break;

    case 'rejeitar_abastecimento':
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID do abastecimento não fornecido']);
            exit;
        }

        $stmt = $conn->prepare('
            UPDATE abastecimentos 
            SET status = "rejeitado", 
                data_atualizacao = NOW() 
            WHERE id = :id 
            AND empresa_id = :empresa_id
        ');
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao rejeitar abastecimento']);
        }
        break;

    case 'ver_checklist':
        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID do checklist não fornecido']);
            exit;
        }

        // Busca os dados do checklist
        $stmt = $conn->prepare('
            SELECT c.*, v.placa, m.nome as motorista_nome 
            FROM checklists c 
            JOIN veiculos v ON c.veiculo_id = v.id 
            JOIN usuarios_motoristas m ON c.motorista_id = m.id 
            WHERE c.id = :id 
            AND c.empresa_id = :empresa_id
        ');
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $checklist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($checklist) {
            // Busca os itens do checklist
            $stmt = $conn->prepare('
                SELECT item, status 
                FROM checklist_itens 
                WHERE checklist_id = :checklist_id
            ');
            $stmt->bindParam(':checklist_id', $data['id'], PDO::PARAM_INT);
            $stmt->execute();
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'checklist' => [
                    'data' => date('d/m/Y', strtotime($checklist['data_checklist'])),
                    'placa' => $checklist['placa'],
                    'motorista' => $checklist['motorista_nome'],
                    'tipo' => $checklist['tipo_checklist'],
                    'km' => number_format($checklist['km_atual'], 0, ',', '.'),
                    'observacoes' => $checklist['observacoes'],
                    'itens' => $itens
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Checklist não encontrado']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
} 