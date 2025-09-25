<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session
configure_session();
session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            // Listar alertas com filtros
            $status = $_GET['status'] ?? 'ativo';
            $tipo = $_GET['tipo'] ?? '';
            $prioridade = $_GET['prioridade'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT a.*, 
                           v.placa as veiculo_placa,
                           m.nome as motorista_nome,
                           u1.nome as usuario_tratamento_nome,
                           u2.nome as usuario_resolucao_nome
                    FROM alertas_sistema a
                    LEFT JOIN veiculos v ON a.veiculo_id = v.id
                    LEFT JOIN motoristas m ON a.motorista_id = m.id
                    LEFT JOIN usuarios u1 ON a.usuario_tratamento = u1.id
                    LEFT JOIN usuarios u2 ON a.usuario_resolucao = u2.id
                    WHERE a.empresa_id = :empresa_id";
            
            $params = [':empresa_id' => $empresa_id];
            
            if ($status !== 'todos') {
                $sql .= " AND a.status = :status";
                $params[':status'] = $status;
            }
            
            if (!empty($tipo)) {
                $sql .= " AND a.tipo = :tipo";
                $params[':tipo'] = $tipo;
            }
            
            if (!empty($prioridade)) {
                $sql .= " AND a.prioridade = :prioridade";
                $params[':prioridade'] = $prioridade;
            }
            
            $sql .= " ORDER BY a.prioridade DESC, a.data_criacao DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total
            $count_sql = str_replace("LIMIT :limit OFFSET :offset", "", $sql);
            $count_sql = str_replace("ORDER BY a.prioridade DESC, a.data_criacao DESC", "", $count_sql);
            $count_sql = "SELECT COUNT(*) as total FROM (" . $count_sql . ") as count_query";
            
            $count_stmt = $conn->prepare($count_sql);
            foreach ($params as $key => $value) {
                if ($key !== ':limit' && $key !== ':offset') {
                    $count_stmt->bindValue($key, $value);
                }
            }
            $count_stmt->execute();
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'alertas' => $alertas,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]);
            break;
            
        case 'marcar_tratado':
            $id = $_POST['id'] ?? 0;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do alerta não fornecido');
            }
            
            $sql = "UPDATE alertas_sistema SET 
                    status = 'tratado',
                    data_tratamento = NOW(),
                    usuario_tratamento = :usuario_id,
                    observacoes_tratamento = :observacoes
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Alerta marcado como tratado']);
            break;
            
        case 'marcar_resolvido':
            $id = $_POST['id'] ?? 0;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do alerta não fornecido');
            }
            
            $sql = "UPDATE alertas_sistema SET 
                    status = 'resolvido',
                    data_resolucao = NOW(),
                    usuario_resolucao = :usuario_id,
                    observacoes_resolucao = :observacoes
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Alerta marcado como resolvido']);
            break;
            
        case 'ignorar':
            $id = $_POST['id'] ?? 0;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (empty($id)) {
                throw new Exception('ID do alerta não fornecido');
            }
            
            $sql = "UPDATE alertas_sistema SET 
                    status = 'ignorado',
                    data_tratamento = NOW(),
                    usuario_tratamento = :usuario_id,
                    observacoes_tratamento = :observacoes
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Alerta ignorado']);
            break;
            
        case 'criar':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required_fields = ['tipo', 'titulo', 'mensagem'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Campo obrigatório não fornecido: $field");
                }
            }
            
            $sql = "INSERT INTO alertas_sistema (
                        empresa_id, tipo, prioridade, titulo, mensagem, dados,
                        veiculo_id, motorista_id, rota_id
                    ) VALUES (
                        :empresa_id, :tipo, :prioridade, :titulo, :mensagem, :dados,
                        :veiculo_id, :motorista_id, :rota_id
                    )";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $data['tipo']);
            $stmt->bindParam(':prioridade', $data['prioridade'] ?? 'media');
            $stmt->bindParam(':titulo', $data['titulo']);
            $stmt->bindParam(':mensagem', $data['mensagem']);
            $stmt->bindParam(':dados', json_encode($data['dados'] ?? []));
            $stmt->bindParam(':veiculo_id', $data['veiculo_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindParam(':motorista_id', $data['motorista_id'] ?? null, PDO::PARAM_INT);
            $stmt->bindParam(':rota_id', $data['rota_id'] ?? null, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Alerta criado com sucesso', 'id' => $conn->lastInsertId()]);
            break;
            
        case 'estatisticas':
            $sql = "SELECT 
                        status,
                        prioridade,
                        COUNT(*) as total
                    FROM alertas_sistema 
                    WHERE empresa_id = :empresa_id 
                    GROUP BY status, prioridade";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'estatisticas' => $stats]);
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
