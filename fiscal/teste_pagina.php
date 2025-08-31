<?php
/**
 * 🧪 TESTE DA PÁGINA FISCAL
 * 📋 Verificar se a página está funcionando corretamente
 */

// Verificar se as tabelas fiscais existem
try {
    require_once '../includes/db_connect.php';
    $conn = getConnection();
    
    echo "<h1>🧪 Teste da Página Fiscal</h1>";
    echo "<p>Verificando estrutura do banco de dados...</p>";
    
    // Verificar tabelas fiscais
    $tabelas_fiscais = [
        'fiscal_config_empresa',
        'fiscal_nfe_clientes',
        'fiscal_cte',
        'fiscal_mdfe',
        'fiscal_logs',
        'fiscal_eventos_fiscais',
        'fiscal_status_historico',
        'fiscal_config_seguranca',
        'fiscal_certificados_digitais',
        'fiscal_alertas'
    ];
    
    echo "<h2>📊 Verificação de Tabelas</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Tabela</th><th>Status</th><th>Registros</th></tr>";
    
    foreach ($tabelas_fiscais as $tabela) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$tabela}";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = $result['total'];
            
            echo "<tr>";
            echo "<td>{$tabela}</td>";
            echo "<td style='color: green;'>✅ Existe</td>";
            echo "<td>{$total}</td>";
            echo "</tr>";
        } catch (Exception $e) {
            echo "<tr>";
            echo "<td>{$tabela}</td>";
            echo "<td style='color: red;'>❌ Não existe</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    // Verificar configurações fiscais
    echo "<h2>⚙️ Verificação de Configurações</h2>";
    
    try {
        $sql = "SELECT * FROM fiscal_config_empresa WHERE empresa_id = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            echo "<p style='color: green;'>✅ Configuração fiscal encontrada para empresa ID 1</p>";
            echo "<ul>";
            echo "<li><strong>CNPJ:</strong> " . htmlspecialchars($config['cnpj'] ?? 'N/A') . "</li>";
            echo "<li><strong>Razão Social:</strong> " . htmlspecialchars($config['razao_social'] ?? 'N/A') . "</li>";
            echo "<li><strong>Ambiente SEFAZ:</strong> " . htmlspecialchars($config['ambiente_sefaz'] ?? 'N/A') . "</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ Configuração fiscal não encontrada para empresa ID 1</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao verificar configurações: " . $e->getMessage() . "</p>";
    }
    
    // Verificar estatísticas
    echo "<h2>📈 Estatísticas Fiscais</h2>";
    
    try {
        $sql_stats = "SELECT 
            (SELECT COUNT(*) FROM fiscal_nfe_clientes WHERE empresa_id = 1) as total_nfe,
            (SELECT COUNT(*) FROM fiscal_cte WHERE empresa_id = 1) as total_cte,
            (SELECT COUNT(*) FROM fiscal_mdfe WHERE empresa_id = 1) as total_mdfe";
        
        $stmt_stats = $conn->prepare($sql_stats);
        $stmt_stats->execute();
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        echo "<li><strong>Total de NF-e:</strong> " . ($stats['total_nfe'] ?? 0) . "</li>";
        echo "<li><strong>Total de CT-e:</strong> " . ($stats['total_cte'] ?? 0) . "</li>";
        echo "<li><strong>Total de MDF-e:</strong> " . ($stats['total_mdfe'] ?? 0) . "</li>";
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro ao verificar estatísticas: " . $e->getMessage() . "</p>";
    }
    
    // Verificar arquivos de configuração
    echo "<h2>📁 Verificação de Arquivos</h2>";
    
    $arquivos = [
        '../fiscal/config/config.php' => 'Configuração Fiscal',
        '../fiscal/assets/css/fiscal.css' => 'CSS Fiscal',
        '../fiscal/assets/js/fiscal.js' => 'JavaScript Fiscal',
        '../fiscal/components/modais.php' => 'Componentes Modais',
        '../pages/fiscal.php' => 'Página Principal Fiscal'
    ];
    
    echo "<ul>";
    foreach ($arquivos as $arquivo => $descricao) {
        if (file_exists($arquivo)) {
            echo "<li style='color: green;'>✅ {$descricao}: {$arquivo}</li>";
        } else {
            echo "<li style='color: red;'>❌ {$descricao}: {$arquivo} (não encontrado)</li>";
        }
    }
    echo "</ul>";
    
    // Links de teste
    echo "<h2>🔗 Links de Teste</h2>";
    echo "<ul>";
    echo "<li><a href='../pages/fiscal.php' target='_blank'>📄 Página Principal Fiscal</a></li>";
    echo "<li><a href='../fiscal/' target='_blank'>🏠 Sistema Fiscal (pasta)</a></li>";
    echo "<li><a href='../fiscal/pages/' target='_blank'>📁 Páginas do Sistema Fiscal</a></li>";
    echo "</ul>";
    
    echo "<h2>✅ Teste Concluído</h2>";
    echo "<p>Se todas as tabelas existem e os arquivos estão presentes, o sistema fiscal está funcionando!</p>";
    
} catch (Exception $e) {
    echo "<h1>❌ Erro no Teste</h1>";
    echo "<p><strong>Erro:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
}
?>
