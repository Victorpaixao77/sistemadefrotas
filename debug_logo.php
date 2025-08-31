<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

echo "<h2>Debug da Logo</h2>";

if (!isset($_SESSION['empresa_id'])) {
    echo "<p style='color: red;'>❌ Sessão não tem empresa_id</p>";
    echo "<pre>SESSION: " . print_r($_SESSION, true) . "</pre>";
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
echo "<p>✅ Empresa ID: $empresa_id</p>";

try {
    $conn = getConnection();
    
    // Verificar se a empresa existe
    $stmt = $conn->prepare('SELECT id, nome FROM empresa_clientes WHERE id = :empresa_id');
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa) {
        echo "<p>✅ Empresa encontrada: {$empresa['nome']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Empresa não encontrada</p>";
        exit;
    }
    
    // Verificar configurações
    $stmt = $conn->prepare('SELECT * FROM configuracoes WHERE empresa_id = :empresa_id');
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo "<p>✅ Configurações encontradas</p>";
        echo "<pre>Configurações: " . print_r($config, true) . "</pre>";
        
        if (!empty($config['logo_empresa'])) {
            echo "<p>✅ Logo encontrada: {$config['logo_empresa']}</p>";
            
            // Verificar se o arquivo existe
            $logo_file = $config['logo_empresa'];
            if (file_exists($logo_file)) {
                echo "<p>✅ Arquivo de logo existe no sistema</p>";
                echo "<p>📁 Caminho completo: " . realpath($logo_file) . "</p>";
                echo "<p>📏 Tamanho: " . filesize($logo_file) . " bytes</p>";
            } else {
                echo "<p style='color: red;'>❌ Arquivo de logo NÃO existe: $logo_file</p>";
                
                // Tentar caminhos alternativos
                $possible_paths = [
                    $logo_file,
                    'uploads/' . basename($logo_file),
                    'uploads/logos/' . basename($logo_file),
                    '../uploads/' . basename($logo_file),
                    '../uploads/logos/' . basename($logo_file)
                ];
                
                echo "<h3>Caminhos alternativos testados:</h3>";
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        echo "<p style='color: green;'>✅ Encontrado em: $path</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Não encontrado: $path</p>";
                    }
                }
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Campo logo_empresa está vazio</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Nenhuma configuração encontrada para empresa_id: $empresa_id</p>";
        
        // Verificar se a tabela existe
        $stmt = $conn->query("SHOW TABLES LIKE 'configuracoes'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Tabela configuracoes existe</p>";
            
            // Verificar estrutura da tabela
            $stmt = $conn->query("DESCRIBE configuracoes");
            echo "<h3>Estrutura da tabela configuracoes:</h3>";
            echo "<pre>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                print_r($row);
            }
            echo "</pre>";
            
            // Verificar se há registros
            $stmt = $conn->query("SELECT COUNT(*) as total FROM configuracoes");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo "<p>📊 Total de registros na tabela: $total</p>";
            
            if ($total > 0) {
                $stmt = $conn->query("SELECT * FROM configuracoes LIMIT 5");
                echo "<h3>Primeiros 5 registros:</h3>";
                echo "<pre>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    print_r($row);
                }
                echo "</pre>";
            }
        } else {
            echo "<p style='color: red;'>❌ Tabela configuracoes não existe</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p>Arquivo: " . $e->getFile() . "</p>";
    echo "<p>Linha: " . $e->getLine() . "</p>";
}
?>
