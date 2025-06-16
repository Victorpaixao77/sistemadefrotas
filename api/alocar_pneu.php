<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in
require_authentication();

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

try {
    error_log("=== INICIANDO ALOCAÇÃO DE PNEU ===");
    error_log("Dados recebidos: " . print_r($_POST, true));
    
    // Validação dos campos obrigatórios
    $required_fields = ['eixo_id', 'veiculo_id', 'lado', 'pneu_id', 'posicao_id'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Campo obrigatório não fornecido: $field");
        }
    }

    // Extrair e validar dados
    $eixo_id = intval($_POST['eixo_id']);
    $veiculo_id = intval($_POST['veiculo_id']);
    $lado = $_POST['lado'];
    $pneu_id = intval($_POST['pneu_id']);
    $posicao_id = intval($_POST['posicao_id']);

    // Log dos dados extraídos
    error_log("Dados extraídos: eixo_id=$eixo_id, veiculo_id=$veiculo_id, lado=$lado, pneu_id=$pneu_id, posicao_id=$posicao_id");

    // Delay extra para garantir que o eixo foi criado
    usleep(500000); // 0.5 segundos

    $conn = getConnection();
    error_log("Conexão com o banco de dados estabelecida");
    
    // Verificar se o pneu existe e está disponível
    $stmt = $conn->prepare("SELECT id FROM pneus WHERE id = :pneu_id AND empresa_id = :empresa_id");
    $stmt->bindParam(':pneu_id', $pneu_id, PDO::PARAM_INT);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Pneu não encontrado');
    }
    
    // Verificar se o eixo existe
    $stmt = $conn->prepare("SELECT id FROM eixos WHERE id = :eixo_id AND veiculo_id = :veiculo_id");
    $stmt->bindParam(':eixo_id', $eixo_id, PDO::PARAM_INT);
    $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log do resultado do SELECT
    error_log("Resultado do SELECT: " . print_r($stmt->fetchAll(), true));

    if ($stmt->rowCount() === 0) {
        // tenta de novo após 0.5s
        usleep(500000);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            throw new Exception('Eixo não encontrado');
        }
    }

    // Verificar se o pneu já está alocado
    $stmt = $conn->prepare("SELECT id FROM eixo_pneus WHERE pneu_id = :pneu_id AND status = 'alocado'");
    $stmt->bindParam(':pneu_id', $pneu_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Pneu já está alocado em outro veículo');
    }

    // Verificar se a posição já está ocupada
    $stmt = $conn->prepare("SELECT id FROM eixo_pneus WHERE eixo_id = :eixo_id AND posicao_id = :posicao_id AND status = 'alocado'");
    $stmt->bindParam(':eixo_id', $eixo_id, PDO::PARAM_INT);
    $stmt->bindParam(':posicao_id', $posicao_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Posição já está ocupada por outro pneu');
    }

    // Iniciar transação
    $conn->beginTransaction();
    error_log("Iniciando transação");

    try {
        // Atualizar o registro existente na tabela eixo_pneus
        $stmt = $conn->prepare("UPDATE eixo_pneus 
                               SET pneu_id = :pneu_id,
                                   posicao_id = :posicao_id,
                                   data_alocacao = NOW(),
                                   km_alocacao = 0,
                                   status = 'alocado',
                                   updated_at = NOW()
                               WHERE eixo_id = :eixo_id 
                               AND veiculo_id = :veiculo_id 
                               AND status = 'desalocado'
                               LIMIT 1");
        $stmt->bindParam(':eixo_id', $eixo_id, PDO::PARAM_INT);
        $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $stmt->bindParam(':pneu_id', $pneu_id, PDO::PARAM_INT);
        $stmt->bindParam(':posicao_id', $posicao_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new Exception('Não foi possível encontrar um registro desalocado para atualizar');
        }
        
        // Inserir na tabela pneus_alocacao para histórico
        $stmt = $conn->prepare("INSERT INTO pneus_alocacao (veiculo_id, pneu_id, posicao_id, data_alocacao, km_alocacao, status, created_at, updated_at) 
                               VALUES (:veiculo_id, :pneu_id, :posicao_id, NOW(), 0, 'alocado', NOW(), NOW())");
        $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $stmt->bindParam(':pneu_id', $pneu_id, PDO::PARAM_INT);
        $stmt->bindParam(':posicao_id', $posicao_id, PDO::PARAM_INT);
        $stmt->execute();

        // Atualizar status do pneu
        $stmt = $conn->prepare("UPDATE pneus SET status_id = 5 WHERE id = :pneu_id"); // 5 = status "em uso"
        $stmt->bindParam(':pneu_id', $pneu_id, PDO::PARAM_INT);
        $stmt->execute();

        // Confirmar transação
        $conn->commit();
        error_log("Transação confirmada");

        echo json_encode([
            'success' => true,
            'message' => 'Pneu alocado com sucesso'
        ]);
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollBack();
        error_log("Erro na transação: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erro ao alocar pneu: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao alocar pneu: ' . $e->getMessage()
    ]);
} 