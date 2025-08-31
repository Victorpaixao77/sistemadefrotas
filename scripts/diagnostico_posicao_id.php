<?php
// Script para diagnosticar problema com posicao_id em produção
echo "=== DIAGNÓSTICO POSIÇÃO_ID ===\n\n";

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
    
    // 1. Verificar se a tabela posicoes_pneus existe
    echo "1. Verificando tabela posicoes_pneus:\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'posicoes_pneus'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Tabela posicoes_pneus existe\n";
        
        // Verificar conteúdo
        $stmt = $pdo->query("SELECT * FROM posicoes_pneus ORDER BY id");
        $posicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   📊 Posições encontradas: " . count($posicoes) . "\n";
        foreach ($posicoes as $pos) {
            echo "      - ID: {$pos['id']}, Nome: {$pos['nome']}\n";
        }
    } else {
        echo "   ❌ Tabela posicoes_pneus NÃO existe\n";
    }
    
    echo "\n";
    
    // 2. Verificar se a coluna posicao_id existe na tabela alocacoes_pneus_flexiveis
    echo "2. Verificando coluna posicao_id em alocacoes_pneus_flexiveis:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Coluna posicao_id existe\n";
        
        // Verificar estrutura da coluna
        $stmt = $pdo->query("DESCRIBE alocacoes_pneus_flexiveis posicao_id");
        $coluna = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   📊 Tipo: {$coluna['Type']}, Null: {$coluna['Null']}, Default: {$coluna['Default']}\n";
    } else {
        echo "   ❌ Coluna posicao_id NÃO existe\n";
    }
    
    echo "\n";
    
    // 3. Verificar alocações existentes
    echo "3. Verificando alocações existentes:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM alocacoes_pneus_flexiveis WHERE ativo = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   📊 Total de alocações ativas: {$result['total']}\n";
    
    if ($result['total'] > 0) {
        // Verificar algumas alocações
        $stmt = $pdo->query("SELECT * FROM alocacoes_pneus_flexiveis WHERE ativo = 1 LIMIT 5");
        $alocacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📋 Amostra de alocações:\n";
        foreach ($alocacoes as $aloc) {
            echo "      - ID: {$aloc['id']}, Slot: {$aloc['slot_id']}, Pneu: {$aloc['pneu_id']}";
            if (isset($aloc['posicao_id'])) {
                echo ", Posição ID: " . ($aloc['posicao_id'] ?? 'NULL');
            } else {
                echo ", Posição ID: COLUNA NÃO EXISTE";
            }
            echo "\n";
        }
    }
    
    echo "\n";
    
    // 4. Verificar se há dados de teste
    echo "4. Verificando dados de teste:\n";
    $stmt = $pdo->query("SELECT v.id, v.placa, COUNT(ev.id) as eixos FROM veiculos v LEFT JOIN eixos_veiculos ev ON v.id = ev.veiculo_id WHERE v.empresa_id = 1 GROUP BY v.id LIMIT 5");
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📊 Veículos com eixos:\n";
    foreach ($veiculos as $veic) {
        echo "      - ID: {$veic['id']}, Placa: {$veic['placa']}, Eixos: {$veic['eixos']}\n";
    }
    
    echo "\n";
    
    // 5. Testar query de layout completo
    echo "5. Testando query de layout completo:\n";
    if (count($veiculos) > 0) {
        $veiculo_id = $veiculos[0]['id'];
        echo "   🧪 Testando com veículo ID: {$veiculo_id}\n";
        
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
        $alocacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📊 Alocações encontradas: " . count($alocacoes) . "\n";
        foreach ($alocacoes as $aloc) {
            echo "      - Slot: {$aloc['slot_id']}, Pneu: {$aloc['numero_serie']}";
            if (isset($aloc['posicao_id'])) {
                echo ", Posição ID: " . ($aloc['posicao_id'] ?? 'NULL');
                echo ", Posição Nome: " . ($aloc['posicao_nome'] ?? 'NULL');
            }
            echo "\n";
        }
    }
    
    echo "\n=== FIM DO DIAGNÓSTICO ===\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 