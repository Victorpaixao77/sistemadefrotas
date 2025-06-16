<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar se os dados necessários foram enviados
$required_fields = ['id', 'motorista_id', 'veiculo_id', 'cidade_origem_id', 'cidade_destino_id', 'data_rota', 'distancia_km', 'frete'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo obrigatório não fornecido: $field"]);
        exit;
    }
}

// Log dos dados recebidos
error_log('POST update rota: ' . print_r($_POST, true));

$rota_id = $_POST['id'];
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    // Verificar se a rota pertence à empresa do usuário
    $stmt = $conn->prepare("SELECT id FROM rotas WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$rota_id, $empresa_id]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Rota não encontrada ou sem permissão']);
        exit;
    }
    
    // Atualizar a rota
    $stmt = $conn->prepare("
        UPDATE rotas 
        SET motorista_id = ?,
            veiculo_id = ?,
            cidade_origem_id = ?,
            cidade_destino_id = ?,
            data_rota = ?,
            distancia_km = ?,
            frete = ?,
            observacoes = ?,
            estado_origem = ?,
            estado_destino = ?,
            data_saida = ?,
            data_chegada = ?,
            km_saida = ?,
            km_chegada = ?,
            km_vazio = ?,
            total_km = ?,
            comissao = ?,
            percentual_vazio = ?,
            eficiencia_viagem = ?,
            peso_carga = ?,
            descricao_carga = ?,
            no_prazo = ?
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->execute([
        $_POST['motorista_id'],
        $_POST['veiculo_id'],
        $_POST['cidade_origem_id'],
        $_POST['cidade_destino_id'],
        $_POST['data_rota'],
        $_POST['distancia_km'],
        $_POST['frete'],
        $_POST['observacoes'] ?? null,
        $_POST['estado_origem'] ?? null,
        $_POST['estado_destino'] ?? null,
        $_POST['data_saida'] ?? null,
        $_POST['data_chegada'] ?? null,
        $_POST['km_saida'] ?? null,
        $_POST['km_chegada'] ?? null,
        $_POST['km_vazio'] ?? null,
        $_POST['total_km'] ?? null,
        $_POST['comissao'] ?? null,
        $_POST['percentual_vazio'] ?? null,
        $_POST['eficiencia_viagem'] ?? null,
        $_POST['peso_carga'] ?? null,
        $_POST['descricao_carga'] ?? null,
        $_POST['no_prazo'] ?? 0,
        $rota_id,
        $empresa_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Rota atualizada com sucesso']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhuma alteração realizada. Verifique os dados enviados.']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao atualizar rota: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar rota']);
}
?> 