<?php
/**
 * 🧪 TESTE DA TABELA FISCAL
 * 📋 Verificar se a tabela fiscal_config_empresa existe e sua estrutura
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h1>🧪 Teste da Tabela Fiscal</h1>";

try {
    $conn = getConnection();
    
    echo "<h2>🔍 Verificando Tabela fiscal_config_empresa</h2>";
    
    // Verificar se a tabela existe
    $stmt = $conn->prepare("SHOW TABLES LIKE 'fiscal_config_empresa'");
    $stmt->execute();
    $tabela_existe = $stmt->rowCount() > 0;
    
    if ($tabela_existe) {
        echo "<p style='color: green;'>✅ Tabela fiscal_config_empresa EXISTE</p>";
        
        // Mostrar estrutura da tabela
        echo "<h3>📋 Estrutura da Tabela</h3>";
        $stmt = $conn->prepare("DESCRIBE fiscal_config_empresa");
        $stmt->execute();
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
        
        foreach ($colunas as $coluna) {
            echo "<tr>";
            echo "<td>{$coluna['Field']}</td>";
            echo "<td>{$coluna['Type']}</td>";
            echo "<td>{$coluna['Null']}</td>";
            echo "<td>{$coluna['Key']}</td>";
            echo "<td>{$coluna['Default']}</td>";
            echo "<td>{$coluna['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar se há dados
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM fiscal_config_empresa");
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<p><strong>Total de registros:</strong> {$total}</p>";
        
    } else {
        echo "<p style='color: red;'>❌ Tabela fiscal_config_empresa NÃO EXISTE</p>";
        
        // Criar a tabela se não existir
        echo "<h3>🔧 Criando Tabela</h3>";
        
        $create_table = "
        CREATE TABLE IF NOT EXISTS fiscal_config_empresa (
            id INT(11) NOT NULL AUTO_INCREMENT,
            empresa_id INT(11) NOT NULL,
            ambiente_sefaz ENUM('homologacao', 'producao') NOT NULL DEFAULT 'homologacao',
            cnpj VARCHAR(18) NULL,
            razao_social VARCHAR(255) NULL,
            nome_fantasia VARCHAR(255) NULL,
            inscricao_estadual VARCHAR(50) NULL,
            codigo_municipio VARCHAR(10) NULL,
            cep VARCHAR(10) NULL,
            endereco VARCHAR(255) NULL,
            telefone VARCHAR(20) NULL,
            email VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_empresa (empresa_id),
            FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $conn->exec($create_table);
            echo "<p style='color: green;'>✅ Tabela fiscal_config_empresa criada com sucesso!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erro ao criar tabela: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
}
?>
