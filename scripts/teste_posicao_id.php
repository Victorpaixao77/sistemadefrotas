<?php
// Script para testar se a correção do posicao_id está funcionando
echo "=== TESTE CORREÇÃO POSIÇÃO_ID ===\n\n";

// Configuração de produção
$config = [
    'host' => 'localhost:3307',
    'username' => 'root',
    'password' => '',
    'database' => 'sistema_frotas'
];

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexão com banco estabelecida\n\n";
    
    // 1. Verificar alocação existente
    echo "1. Verificando alocação existente:\n";
    $stmt = $pdo->query("SELECT * FROM alocacoes_pneus_flexiveis WHERE ativo = 1");
    $alocacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($alocacao) {
        echo "   📊 Alocação encontrada:\n";
        echo "      - ID: {$alocacao['id']}\n";
        echo "      - Slot: {$alocacao['slot_id']}\n";
        echo "      - Pneu ID: {$alocacao['pneu_id']}\n";
        echo "      - Posição ID: " . ($alocacao['posicao_id'] ?? 'NULL') . "\n";
        echo "      - Empresa ID: {$alocacao['empresa_id']}\n";
        
        // 2. Verificar dados do pneu
        $stmt = $pdo->prepare("SELECT p.*, sp.nome as status_nome FROM pneus p LEFT JOIN status_pneus sp ON p.status_id = sp.id WHERE p.id = ?");
        $stmt->execute([$alocacao['pneu_id']]);
        $pneu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\n2. Dados do pneu:\n";
        echo "   📊 Pneu encontrado:\n";
        echo "      - ID: {$pneu['id']}\n";
        echo "      - Série: {$pneu['numero_serie']}\n";
        echo "      - Marca: {$pneu['marca']}\n";
        echo "      - Status: {$pneu['status_nome']}\n";
        
        // 3. Verificar posição
        if ($alocacao['posicao_id']) {
            $stmt = $pdo->prepare("SELECT * FROM posicoes_pneus WHERE id = ?");
            $stmt->execute([$alocacao['posicao_id']]);
            $posicao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "\n3. Dados da posição:\n";
            echo "   📊 Posição encontrada:\n";
            echo "      - ID: {$posicao['id']}\n";
            echo "      - Nome: {$posicao['nome']}\n";
        } else {
            echo "\n3. Dados da posição:\n";
            echo "   ❌ Posição ID é NULL\n";
        }
        
        // 4. Simular query da API
        echo "\n4. Simulando query da API eixos_veiculos.php:\n";
        $veiculo_id = 55; // Veículo de teste
        
        // Verificar se a coluna posicao_id existe
        $stmt = $pdo->prepare("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
        $stmt->execute();
        $posicao_id_exists = $stmt->rowCount() > 0;
        
        if ($posicao_id_exists) {
            $sql = "SELECT apf.*, p.numero_serie, p.marca, p.modelo, p.medida, p.status_id, sp.nome as status_nome, pp.nome as posicao_nome
                    FROM alocacoes_pneus_flexiveis apf
                    INNER JOIN pneus p ON apf.pneu_id = p.id
                    LEFT JOIN status_pneus sp ON p.status_id = sp.id
                    LEFT JOIN posicoes_pneus pp ON apf.posicao_id = pp.id
                    INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                    WHERE ev.veiculo_id = ? AND apf.empresa_id = 1 AND apf.ativo = 1";
        } else {
            $sql = "SELECT apf.*, p.numero_serie, p.marca, p.modelo, p.medida, p.status_id, sp.nome as status_nome
                    FROM alocacoes_pneus_flexiveis apf
                    INNER JOIN pneus p ON apf.pneu_id = p.id
                    LEFT JOIN status_pneus sp ON p.status_id = sp.id
                    INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                    WHERE ev.veiculo_id = ? AND apf.empresa_id = 1 AND apf.ativo = 1";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$veiculo_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            echo "   📊 Resultado da API:\n";
            echo "      - Slot: {$resultado['slot_id']}\n";
            echo "      - Pneu: {$resultado['numero_serie']}\n";
            echo "      - Posição ID: " . ($resultado['posicao_id'] ?? 'NULL') . "\n";
            echo "      - Posição Nome: " . ($resultado['posicao_nome'] ?? 'NULL') . "\n";
            
            if ($resultado['posicao_id'] && $resultado['posicao_nome']) {
                echo "   ✅ POSIÇÃO_ID ESTÁ SENDO RETORNADA CORRETAMENTE!\n";
            } else {
                echo "   ❌ POSIÇÃO_ID NÃO ESTÁ SENDO RETORNADA!\n";
            }
        } else {
            echo "   ❌ Nenhum resultado encontrado para o veículo\n";
        }
        
    } else {
        echo "   ❌ Nenhuma alocação ativa encontrada\n";
    }
    
    echo "\n=== FIM DO TESTE ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 