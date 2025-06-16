<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

configure_session();
session_start();
require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$pneu_id = $data['pneu_id'] ?? null;

if (!$pneu_id) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];

    // Verifica se o pneu pertence à empresa
    $stmt = $conn->prepare('SELECT id FROM pneus WHERE id = :pneu_id AND empresa_id = :empresa_id');
    $stmt->bindParam(':pneu_id', $pneu_id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Pneu não encontrado ou não pertence à empresa']);
        exit;
    }

    // Inicia a transação
    $conn->beginTransaction();

    try {
        // Busca o veículo_id antes de desalocar
        $stmt = $conn->prepare('SELECT veiculo_id FROM eixo_pneus WHERE pneu_id = :pneu_id AND status = "alocado"');
        $stmt->bindParam(':pneu_id', $pneu_id);
        $stmt->execute();
        $eixo_pneu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$eixo_pneu) {
            throw new Exception('Pneu não está alocado');
        }

        // Atualiza o registro na tabela eixo_pneus
        $stmt = $conn->prepare('UPDATE eixo_pneus SET 
            pneu_id = NULL,
            posicao_id = NULL,
            data_alocacao = NULL,
            km_alocacao = NULL,
            data_desalocacao = NULL,
            km_desalocacao = NULL,
            status = "desalocado",
            observacoes = NULL,
            updated_at = NOW()
            WHERE pneu_id = :pneu_id AND veiculo_id = :veiculo_id AND status = "alocado"');
        $stmt->bindParam(':pneu_id', $pneu_id);
        $stmt->bindParam(':veiculo_id', $eixo_pneu['veiculo_id']);
        $stmt->execute();

        // Atualiza o status da alocação para desalocado
        $stmt = $conn->prepare('UPDATE pneus_alocacao SET 
            status = "desalocado", 
            data_desalocacao = NOW() 
            WHERE pneu_id = :pneu_id AND status = "alocado"');
        $stmt->bindParam(':pneu_id', $pneu_id);
        $stmt->execute();

        // Atualiza o status do pneu para disponível
        $stmt = $conn->prepare('UPDATE pneus SET status_id = 2 WHERE id = :pneu_id'); // 2 = status "disponível"
        $stmt->bindParam(':pneu_id', $pneu_id);
        $stmt->execute();

        // Marca como disponível no estoque
        $stmt = $conn->prepare('UPDATE estoque_pneus SET 
            disponivel = 1, 
            updated_at = NOW() 
            WHERE pneu_id = :pneu_id');
        $stmt->bindParam(':pneu_id', $pneu_id);
        $stmt->execute();

        // Confirma a transação
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Pneu deslocado com sucesso']);
    } catch (Exception $e) {
        // Em caso de erro, desfaz a transação
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log('Erro ao deslocar pneu: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao deslocar pneu: ' . $e->getMessage()]);
} 