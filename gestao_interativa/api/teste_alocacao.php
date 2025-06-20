<?php
header('Content-Type: application/json');

// Função para log personalizado
function debug_log($message) {
    $log_file = __DIR__ . '/../../logs/gestao_interativa.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Log inicial
debug_log("=== INICIANDO API TESTE ALOCAÇÃO ===");
debug_log("URL: " . $_SERVER['REQUEST_URI']);
debug_log("Método: " . $_SERVER['REQUEST_METHOD']);
debug_log("Headers: " . print_r(getallheaders(), true));

try {
    // Verificar se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Obter dados do POST
    $raw_data = file_get_contents('php://input');
    debug_log("Dados brutos recebidos: " . $raw_data);
    
    $data = json_decode($raw_data, true);
    debug_log("Dados decodificados: " . print_r($data, true));
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }
    
    // Validar campos obrigatórios
    $required_fields = ['veiculo_id', 'pneu_id', 'posicao'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo obrigatório não fornecido: $field");
        }
    }
    
    $veiculo_id = intval($data['veiculo_id']);
    $pneu_id = intval($data['pneu_id']);
    $posicao = intval($data['posicao']);
    $acao = $data['acao'] ?? 'alocar';
    $empresa_id = 1; // Empresa fixa para teste
    
    debug_log("Dados extraídos: veiculo_id=$veiculo_id, pneu_id=$pneu_id, posicao=$posicao, acao='$acao'");
    
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    debug_log("Conexão com banco estabelecida");
    
    $pdo->beginTransaction();
    
    try {
        // Verificar se o pneu pertence à empresa
        $stmt = $pdo->prepare("SELECT id FROM pneus WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$pneu_id, $empresa_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Pneu não encontrado ou não pertence à empresa');
        }
        
        debug_log("Pneu validado");
        
        // Verificar se o veículo pertence à empresa
        $stmt = $pdo->prepare("SELECT id FROM veiculos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$veiculo_id, $empresa_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Veículo não encontrado ou não pertence à empresa');
        }
        
        debug_log("Veículo validado");
        debug_log("Ação recebida: '$acao'");
        
        switch ($acao) {
            case 'alocar':
                debug_log("Executando ação: alocar");
                
                // Verificar se o pneu já está instalado
                $stmt = $pdo->prepare("SELECT id FROM instalacoes_pneus WHERE pneu_id = ? AND data_remocao IS NULL");
                $stmt->execute([$pneu_id]);
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Pneu já está instalado em outro veículo');
                }
                
                debug_log("Pneu não está instalado em outro veículo");
                
                // Verificar se a posição já está ocupada
                $stmt = $pdo->prepare("SELECT id FROM instalacoes_pneus WHERE veiculo_id = ? AND posicao = ? AND data_remocao IS NULL");
                $stmt->execute([$veiculo_id, $posicao]);
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Posição já está ocupada por outro pneu');
                }
                
                debug_log("Posição não está ocupada");
                
                // Inserir nova instalação (sem eixo_id)
                $stmt = $pdo->prepare("INSERT INTO instalacoes_pneus (pneu_id, veiculo_id, posicao, data_instalacao, status) VALUES (?, ?, ?, NOW(), 'bom')");
                $stmt->execute([$pneu_id, $veiculo_id, $posicao]);
                
                debug_log("Instalação inserida com sucesso");
                
                // Atualizar status do pneu
                $stmt = $pdo->prepare("UPDATE pneus SET status_id = 5 WHERE id = ?");
                $stmt->execute([$pneu_id]);
                
                debug_log("Status do pneu atualizado");
                
                $mensagem = 'Pneu alocado com sucesso';
                break;
                
            case 'remover':
                debug_log("Executando ação: remover");
                
                // Marcar instalação como removida
                $stmt = $pdo->prepare("UPDATE instalacoes_pneus SET data_remocao = NOW() WHERE pneu_id = ? AND veiculo_id = ? AND data_remocao IS NULL");
                $stmt->execute([$pneu_id, $veiculo_id]);
                
                // Atualizar status do pneu para disponível
                $stmt = $pdo->prepare("UPDATE pneus SET status_id = 2 WHERE id = ?");
                $stmt->execute([$pneu_id]);
                
                $mensagem = 'Pneu removido com sucesso';
                break;
                
            default:
                debug_log("Ação não reconhecida: '$acao'");
                debug_log("Ações válidas: alocar, remover");
                throw new Exception("Ação não reconhecida: '$acao'. Ações válidas: alocar, remover");
        }
        
        $pdo->commit();
        debug_log("Transação commitada com sucesso");
        
        $response = [
            'success' => true,
            'message' => $mensagem
        ];
        
        debug_log("Resposta: " . json_encode($response));
        echo json_encode($response);
        
        debug_log("Resposta enviada com sucesso");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        debug_log("Erro na transação: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    debug_log("Erro final: " . $e->getMessage());
    $error_response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    debug_log("Resposta de erro: " . json_encode($error_response));
    echo json_encode($error_response);
}

debug_log("=== FIM API TESTE ALOCAÇÃO ===");
?> 