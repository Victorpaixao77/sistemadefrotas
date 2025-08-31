<?php
/**
 * 🧪 TESTE DA LOGO DA EMPRESA
 * 📋 Verificar se a logo está sendo carregada corretamente
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

echo "<h1>🧪 Teste da Logo da Empresa</h1>";

// Verificar se há sessão ativa
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ Usuário não está logado</p>";
    echo "<p><a href='index.php'>Fazer login</a></p>";
    exit;
}

$empresa_id = $_SESSION['empresa_id'] ?? null;
echo "<h2>👤 Informações da Sessão</h2>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'N/A') . "</p>";
echo "<p><strong>Empresa ID:</strong> " . ($empresa_id ?? 'N/A') . "</p>";

if (!$empresa_id) {
    echo "<p style='color: red;'>❌ Empresa ID não encontrado na sessão</p>";
    exit;
}

try {
    $conn = getConnection();
    
    // Verificar configurações da empresa
    $stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>🏢 Configurações da Empresa</h2>";
    if ($row) {
        echo "<p><strong>Nome Personalizado:</strong> " . ($row['nome_personalizado'] ?? 'N/A') . "</p>";
        echo "<p><strong>Logo da Empresa:</strong> " . ($row['logo_empresa'] ?? 'N/A') . "</p>";
        
        if (!empty($row['logo_empresa'])) {
            $logo_path = $row['logo_empresa'];
            
            // Verificar se o arquivo existe
            if (file_exists('uploads/logos/' . $logo_path)) {
                echo "<p style='color: green;'>✅ Arquivo de logo encontrado: uploads/logos/{$logo_path}</p>";
                
                // Mostrar a logo
                echo "<h3>🖼️ Logo Atual</h3>";
                echo "<img src='uploads/logos/{$logo_path}' alt='Logo da Empresa' style='max-width: 200px; max-height: 200px; border: 1px solid #ccc;'>";
                
                // Testar diferentes caminhos base
                echo "<h3>🔗 Teste de Caminhos</h3>";
                $caminhos_teste = [
                    'uploads/logos/' . $logo_path,
                    '../uploads/logos/' . $logo_path,
                    '../../uploads/logos/' . $logo_path
                ];
                
                foreach ($caminhos_teste as $caminho) {
                    echo "<p><strong>{$caminho}:</strong> ";
                    if (file_exists($caminho)) {
                        echo "<span style='color: green;'>✅ Arquivo existe</span>";
                    } else {
                        echo "<span style='color: red;'>❌ Arquivo NÃO existe</span>";
                    }
                    echo "</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Arquivo de logo NÃO encontrado: uploads/logos/{$logo_path}</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Nenhuma logo configurada para esta empresa</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Nenhuma configuração encontrada para a empresa ID: {$empresa_id}</p>";
    }
    
    // Verificar se a tabela configuracoes existe
    $stmt = $conn->query("SHOW TABLES LIKE 'configuracoes'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Tabela 'configuracoes' existe</p>";
        
        // Mostrar estrutura da tabela
        $stmt = $conn->query("DESCRIBE configuracoes");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📋 Estrutura da Tabela Configurações</h3>";
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
        
    } else {
        echo "<p style='color: red;'>❌ Tabela 'configuracoes' NÃO existe</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao conectar com o banco: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='fiscal/pages/nfe.php' target='_blank'>📄 Testar NF-e (verificar logo)</a></li>";
echo "<li><a href='fiscal/pages/cte.php' target='_blank'>📄 Testar CT-e (verificar logo)</a></li>";
echo "<li><a href='fiscal/pages/mdfe.php' target='_blank'>📄 Testar MDF-e (verificar logo)</a></li>";
echo "<li><a href='fiscal/pages/eventos.php' target='_blank'>📄 Testar Eventos (verificar logo)</a></li>";
echo "</ul>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p><strong>Próximo passo:</strong> Verifique se a logo está aparecendo nas páginas fiscais!</p>";
?>
