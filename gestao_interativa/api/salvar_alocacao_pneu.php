<?php
header('Content-Type: application/json');

try {
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }
    
    // Validar campos obrigatórios
    $required_fields = ['veiculo_id', 'pneu_id', 'posicao', 'acao'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo obrigatório não fornecido: $field");
        }
    }
    
    $veiculo_id = intval($data['veiculo_id']);
    $pneu_id = intval($data['pneu_id']);
    $posicao = intval($data['posicao']);
    $acao = $data['acao'];
    $empresa_id = 1; // Empresa fixa para teste
    
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    try {
        // Verificar se o pneu pertence à empresa
        $stmt = $pdo->prepare("SELECT id FROM pneus WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$pneu_id, $empresa_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Pneu não encontrado ou não pertence à empresa');
        }
        
        // Verificar se o veículo pertence à empresa
        $stmt = $pdo->prepare("SELECT id FROM veiculos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$veiculo_id, $empresa_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Veículo não encontrado ou não pertence à empresa');
        }
        
        switch ($acao) {
            case 'manutencao':
                // Marcar instalação como removida
                $stmt = $pdo->prepare("UPDATE instalacoes_pneus SET data_remocao = NOW() WHERE pneu_id = ? AND veiculo_id = ? AND data_remocao IS NULL");
                $stmt->execute([$pneu_id, $veiculo_id]);
                
                // Atualizar status do pneu para manutenção
                $stmt = $pdo->prepare("UPDATE pneus SET status_id = 3 WHERE id = ?");
                $stmt->execute([$pneu_id]);
                
                $mensagem = 'Pneu enviado para manutenção com sucesso';
                break;
                
            case 'remover':
                // Marcar instalação como removida
                $stmt = $pdo->prepare("UPDATE instalacoes_pneus SET data_remocao = NOW() WHERE pneu_id = ? AND veiculo_id = ? AND data_remocao IS NULL");
                $stmt->execute([$pneu_id, $veiculo_id]);
                
                // Atualizar status do pneu para disponível
                $stmt = $pdo->prepare("UPDATE pneus SET status_id = 2 WHERE id = ?");
                $stmt->execute([$pneu_id]);
                
                $mensagem = 'Pneu removido com sucesso';
                break;
                
            default:
                throw new Exception('Ação não reconhecida');
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $mensagem
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 