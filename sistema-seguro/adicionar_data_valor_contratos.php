<?php
/**
 * Script para adicionar campos data_inicio e valor na tabela seguro_contratos_clientes
 */

require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>🔧 Atualizar Tabela de Contratos</h2>";
    echo "<p>Adicionando campos 'data_inicio' e 'valor'...</p>";
    
    // Ler e executar o arquivo SQL
    $sql = file_get_contents('database/adicionar_data_valor_contratos.sql');
    
    // Remover comentários e dividir por ponto-e-vírgula
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   strpos($stmt, '--') !== 0 && 
                   strpos($stmt, 'USE ') !== 0;
        }
    );
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
                echo "<p style='color: green;'>✅ Executado com sucesso</p>";
            } catch (PDOException $e) {
                // Ignorar erros de "já existe"
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "<p style='color: orange;'>⚠️ Campo já existe - OK</p>";
                } else {
                    throw $e;
                }
            }
        }
    }
    
    // Verificar estrutura final
    $stmt = $db->query("
        SELECT 
            COLUMN_NAME as campo,
            COLUMN_TYPE as tipo,
            IS_NULLABLE as permite_nulo,
            COLUMN_DEFAULT as valor_padrao
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'seguro_contratos_clientes'
        ORDER BY ORDINAL_POSITION
    ");
    
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Estrutura da Tabela:</h3>";
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Campo</th><th>Tipo</th><th>Permite NULL</th><th>Padrão</th></tr>";
    
    foreach ($colunas as $col) {
        $destaque = ($col['campo'] == 'data_inicio' || $col['campo'] == 'valor') ? 
            "style='background: #d4edda; font-weight: bold;'" : "";
        
        echo "<tr {$destaque}>";
        echo "<td>{$col['campo']}</td>";
        echo "<td>{$col['tipo']}</td>";
        echo "<td>{$col['permite_nulo']}</td>";
        echo "<td>" . ($col['valor_padrao'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><p style='color: green; font-size: 1.2rem; font-weight: bold;'>✅ Tabela atualizada com sucesso!</p>";
    echo "<p>Próximo passo: Atualizar interface em <strong>clientes.php</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

