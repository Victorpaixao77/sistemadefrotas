<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../db.php';

// Verifica se o motorista está logado
validar_sessao_motorista();

// Obtém dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['empresa_id'];

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $conn = getConnection();
    
    // Obter dados do formulário
    $rota_id = $_POST['rota_id'];
    $action = $_POST['action'];
    
    // Verificar se a rota pertence ao motorista
    $stmt = $conn->prepare('
        SELECT id
        FROM rotas
        WHERE id = :rota_id
        AND empresa_id = :empresa_id
        AND motorista_id = :motorista_id
    ');
    $stmt->execute([
        'rota_id' => $rota_id,
        'empresa_id' => $empresa_id,
        'motorista_id' => $motorista_id
    ]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Rota não encontrada ou não pertence ao motorista');
    }
    
    // Preparar dados das despesas
    $dados = [
        'empresa_id' => $empresa_id,
        'rota_id' => $rota_id,
        'descarga' => $_POST['descarga'] ?: 0,
        'pedagios' => $_POST['pedagios'] ?: 0,
        'caixinha' => $_POST['caixinha'] ?: 0,
        'estacionamento' => $_POST['estacionamento'] ?: 0,
        'lavagem' => $_POST['lavagem'] ?: 0,
        'borracharia' => $_POST['borracharia'] ?: 0,
        'eletrica_mecanica' => $_POST['eletrica_mecanica'] ?: 0,
        'adiantamento' => $_POST['adiantamento'] ?: 0,
        'total_despviagem' => $_POST['total'] ?: 0,
        'status' => 'pendente',
        'fonte' => 'motorista'
    ];
    
    if ($action === 'create') {
        // Inserir despesas
        $sql = "INSERT INTO despesas_viagem (
                    empresa_id, rota_id, descarga, pedagios, caixinha,
                    estacionamento, lavagem, borracharia, eletrica_mecanica,
                    adiantamento, total_despviagem, status, fonte
                ) VALUES (
                    :empresa_id, :rota_id, :descarga, :pedagios, :caixinha,
                    :estacionamento, :lavagem, :borracharia, :eletrica_mecanica,
                    :adiantamento, :total_despviagem, :status, :fonte
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($dados);
        
        $message = 'Despesas registradas com sucesso!';
    } else {
        // Atualizar despesas
        $sql = "UPDATE despesas_viagem SET
                    descarga = :descarga,
                    pedagios = :pedagios,
                    caixinha = :caixinha,
                    estacionamento = :estacionamento,
                    lavagem = :lavagem,
                    borracharia = :borracharia,
                    eletrica_mecanica = :eletrica_mecanica,
                    adiantamento = :adiantamento,
                    total_despviagem = :total_despviagem,
                    status = :status,
                    fonte = :fonte
                WHERE id = :id
                AND empresa_id = :empresa_id
                AND rota_id = :rota_id";
        
        $dados['id'] = $_POST['id'];
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($dados);
        
        $message = 'Despesas atualizadas com sucesso!';
    }
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao processar despesas: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar despesas: ' . $e->getMessage()
    ]);
} 