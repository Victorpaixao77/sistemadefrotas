<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "=== VERIFICAÇÃO DA TABELA EIXOS_VEICULOS ===\n\n";

try {
    $conn = getConnection();
    
    // Verificar se a tabela existe
    $stmt = $conn->query("SHOW TABLES LIKE 'eixos_veiculos'");
    $tabela_existe = $stmt->fetch();
    
    if ($tabela_existe) {
        echo "✅ Tabela 'eixos_veiculos' existe!\n\n";
        
        // Verificar estrutura da tabela
        echo "Estrutura da tabela:\n";
        $stmt = $conn->query("DESCRIBE eixos_veiculos");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($colunas as $coluna) {
            echo "- {$coluna['Field']} ({$coluna['Type']}) - {$coluna['Null']} - {$coluna['Key']}\n";
        }
        
        // Verificar dados na tabela
        echo "\nDados na tabela:\n";
        $stmt = $conn->query("SELECT COUNT(*) as total FROM eixos_veiculos");
        $total = $stmt->fetch();
        echo "Total de registros: {$total['total']}\n";
        
        if ($total['total'] > 0) {
            $stmt = $conn->query("SELECT * FROM eixos_veiculos LIMIT 5");
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nPrimeiros 5 registros:\n";
            foreach ($dados as $dado) {
                echo "- ID: {$dado['id']}, Veículo: {$dado['veiculo_id']}, Tipo: {$dado['tipo_veiculo']}, Eixo: {$dado['numero_eixo']}, Pneus: {$dado['quantidade_pneus']}\n";
            }
        }
        
    } else {
        echo "❌ Tabela 'eixos_veiculos' NÃO existe!\n\n";
        
        echo "Criando tabela...\n";
        
        $sql = "CREATE TABLE eixos_veiculos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            veiculo_id INT NOT NULL,
            tipo_veiculo ENUM('caminhao', 'carreta') NOT NULL,
            numero_eixo INT NOT NULL,
            quantidade_pneus INT NOT NULL DEFAULT 2,
            empresa_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
            FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            UNIQUE KEY unique_eixo_veiculo (veiculo_id, tipo_veiculo, numero_eixo, empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        echo "✅ Tabela 'eixos_veiculos' criada com sucesso!\n";
    }
    
    // Verificar se a tabela alocacoes_pneus_flexiveis existe
    echo "\n=== VERIFICAÇÃO DA TABELA ALOCACOES_PNEUS_FLEXIVEIS ===\n";
    
    $stmt = $conn->query("SHOW TABLES LIKE 'alocacoes_pneus_flexiveis'");
    $tabela_alocacoes_existe = $stmt->fetch();
    
    if ($tabela_alocacoes_existe) {
        echo "✅ Tabela 'alocacoes_pneus_flexiveis' existe!\n\n";
        
        // Verificar estrutura
        echo "Estrutura da tabela:\n";
        $stmt = $conn->query("DESCRIBE alocacoes_pneus_flexiveis");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($colunas as $coluna) {
            echo "- {$coluna['Field']} ({$coluna['Type']}) - {$coluna['Null']} - {$coluna['Key']}\n";
        }
        
        // Verificar dados
        $stmt = $conn->query("SELECT COUNT(*) as total FROM alocacoes_pneus_flexiveis");
        $total = $stmt->fetch();
        echo "\nTotal de alocações: {$total['total']}\n";
        
    } else {
        echo "❌ Tabela 'alocacoes_pneus_flexiveis' NÃO existe!\n\n";
        
        echo "Criando tabela...\n";
        
        $sql = "CREATE TABLE alocacoes_pneus_flexiveis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            veiculo_id INT NOT NULL,
            eixo_veiculo_id INT NOT NULL,
            pneu_id INT NOT NULL,
            posicao_id INT,
            slot_id VARCHAR(50) NOT NULL,
            ativo BOOLEAN DEFAULT TRUE,
            data_alocacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_remocao TIMESTAMP NULL,
            observacoes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
            FOREIGN KEY (eixo_veiculo_id) REFERENCES eixos_veiculos(id) ON DELETE CASCADE,
            FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE,
            FOREIGN KEY (posicao_id) REFERENCES posicoes_pneus(id) ON DELETE SET NULL,
            UNIQUE KEY unique_alocacao_ativa (slot_id, ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        echo "✅ Tabela 'alocacoes_pneus_flexiveis' criada com sucesso!\n";
    }
    
    echo "\n=== VERIFICAÇÃO COMPLETA ===\n";
    echo "✅ Todas as tabelas necessárias estão criadas!\n";
    echo "✅ O sistema de eixos deve funcionar corretamente agora.\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 