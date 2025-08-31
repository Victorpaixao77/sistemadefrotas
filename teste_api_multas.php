<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h2>Teste da API de Multas</h2>";

try {
    $conn = getConnection();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Simular dados de uma multa
    $test_data = [
        'action' => 'create',
        'data_infracao' => '2025-08-23',
        'veiculo_id' => 1,
        'motorista_id' => 1,
        'tipo_infracao' => 'Teste API - Excesso de velocidade',
        'valor' => 100.00,
        'status_pagamento' => 'pendente',
        'pontos' => 4,
        'descricao' => 'Teste da API de multas'
    ];
    
    echo "<h3>Dados de teste:</h3>";
    echo "<pre>" . print_r($test_data, true) . "</pre>";
    
    // Verificar se existem veículos e motoristas
    $sql_veiculos = "SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = 1";
    $stmt = $conn->prepare($sql_veiculos);
    $stmt->execute();
    $total_veiculos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_motoristas = "SELECT COUNT(*) as total FROM motoristas WHERE empresa_id = 1";
    $stmt = $conn->prepare($sql_motoristas);
    $stmt->execute();
    $total_motoristas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "<p>📊 Veículos disponíveis: <strong>{$total_veiculos}</strong></p>";
    echo "<p>📊 Motoristas disponíveis: <strong>{$total_motoristas}</strong></p>";
    
    if ($total_veiculos == 0 || $total_motoristas == 0) {
        echo "<p>⚠️ <strong>ATENÇÃO:</strong> Não há veículos ou motoristas cadastrados para a empresa ID 1</p>";
        echo "<p>Você precisa cadastrar pelo menos um veículo e um motorista antes de testar as multas</p>";
    }
    
    // Testar inserção direta no banco
    echo "<hr>";
    echo "<h3>Teste de Inserção Direta no Banco:</h3>";
    
    $sql_insert = "INSERT INTO multas (
        empresa_id, veiculo_id, motorista_id, data_infracao, 
        tipo_infracao, valor, status_pagamento, pontos, descricao
    ) VALUES (
        :empresa_id, :veiculo_id, :motorista_id, :data_infracao,
        :tipo_infracao, :valor, :status_pagamento, :pontos, :descricao
    )";
    
    $stmt = $conn->prepare($sql_insert);
    
    // Buscar IDs válidos de veículos e motoristas
    $sql_veiculo = "SELECT id FROM veiculos WHERE empresa_id = 1 LIMIT 1";
    $stmt_veiculo = $conn->prepare($sql_veiculo);
    $stmt_veiculo->execute();
    $veiculo = $stmt_veiculo->fetch(PDO::FETCH_ASSOC);
    
    $sql_motorista = "SELECT id FROM motoristas WHERE empresa_id = 1 LIMIT 1";
    $stmt_motorista = $conn->prepare($sql_motorista);
    $stmt_motorista->execute();
    $motorista = $stmt_motorista->fetch(PDO::FETCH_ASSOC);
    
    if (!$veiculo || !$motorista) {
        echo "<p>❌ <strong>Erro:</strong> Não foi possível encontrar veículo ou motorista válidos</p>";
        return;
    }
    
    // Usar variáveis para bindParam com IDs válidos
    $empresa_id = 1;
    $veiculo_id = $veiculo['id'];
    $motorista_id = $motorista['id'];
    $data_infracao = '2025-08-23';
    $tipo_infracao = 'Teste Direto - Excesso de velocidade';
    $valor = 150.00;
    $status_pagamento = 'pendente';
    $pontos = 5;
    $descricao = 'Teste de inserção direta no banco';
    
    echo "<p>🔍 Usando Veículo ID: <strong>{$veiculo_id}</strong></p>";
    echo "<p>🔍 Usando Motorista ID: <strong>{$motorista_id}</strong></p>";
    
    // Preparar e executar a inserção
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bindParam(':empresa_id', $empresa_id);
    $stmt_insert->bindParam(':veiculo_id', $veiculo_id);
    $stmt_insert->bindParam(':motorista_id', $motorista_id);
    $stmt_insert->bindParam(':data_infracao', $data_infracao);
    $stmt_insert->bindParam(':tipo_infracao', $tipo_infracao);
    $stmt_insert->bindParam(':valor', $valor);
    $stmt_insert->bindParam(':status_pagamento', $status_pagamento);
    $stmt_insert->bindParam(':pontos', $pontos);
    $stmt_insert->bindParam(':descricao', $descricao);
    
    if ($stmt_insert->execute()) {
        $multa_id = $conn->lastInsertId();
        echo "<p>✅ <strong>Teste de inserção direta funcionou!</strong> Multa criada com ID: {$multa_id}</p>";
        
        // Limpar o teste
        $sql_delete = "DELETE FROM multas WHERE id = :id";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bindParam(':id', $multa_id);
        $stmt_delete->execute();
        echo "<p>🧹 Teste removido do banco</p>";
        
    } else {
        $error_info = $stmt_insert->errorInfo();
        echo "<p>❌ <strong>Erro na inserção direta:</strong> " . $error_info[2] . "</p>";
    }
    
    // Verificar logs de erro
    echo "<hr>";
    echo "<h3>Verificação de Logs:</h3>";
    
    $log_file = 'logs/php_errors.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $log_lines = explode("\n", $log_content);
        $recent_logs = array_slice($log_lines, -10); // Últimas 10 linhas
        
        echo "<p>📋 Últimas 10 linhas do log de erros:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        foreach ($recent_logs as $line) {
            if (trim($line)) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>⚠️ Arquivo de log não encontrado: {$log_file}</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h3>Próximos Passos:</h3>";
echo "<ul>";
echo "<li>Se não há veículos/motoristas, cadastre-os primeiro</li>";
echo "<li>Teste o formulário de multas no navegador</li>";
echo "<li>Verifique o console do navegador (F12) para erros JavaScript</li>";
echo "<li>Verifique os logs do PHP para erros do servidor</li>";
echo "</ul>";
?>
