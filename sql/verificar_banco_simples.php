<?php
/**
 * Script SIMPLES para verificar estrutura do banco
 * Versão independente que não depende de config.php
 */

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Configurações do banco (ajuste se necessário)
$db_config = [
    'host' => 'localhost:3307',  // Porta 3307 conforme config.php
    'dbname' => 'sistema_frotas',
    'user' => 'root',
    'pass' => 'mudar123',
    'charset' => 'utf8mb4'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação Banco - Simples</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .secao { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
        .ok { color: green; font-weight: bold; }
        .erro { color: red; font-weight: bold; }
        .aviso { color: orange; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificação Simples do Banco de Dados</h1>
        
        <?php
        $conn = null;
        
        // Tentar conexão
        echo '<div class="secao">';
        echo '<h2>1. Teste de Conexão</h2>';
        
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
            $conn = new PDO($dsn, $db_config['user'], $db_config['pass']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo '<p class="ok">✅ Conexão estabelecida com sucesso!</p>';
            echo '<p><strong>Banco:</strong> ' . htmlspecialchars($db_config['dbname']) . '</p>';
            echo '<p><strong>Host:</strong> ' . htmlspecialchars($db_config['host']) . '</p>';
        } catch (PDOException $e) {
            echo '<p class="erro">❌ Erro de conexão: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>DSN tentado:</strong> ' . htmlspecialchars($dsn) . '</p>';
            echo '</div>';
            exit;
        }
        echo '</div>';
        
        // Listar todas as tabelas
        echo '<div class="secao">';
        echo '<h2>2. Todas as Tabelas do Banco</h2>';
        try {
            $stmt = $conn->query("SHOW TABLES");
            $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tabelas) > 0) {
                echo '<p><strong>Total de tabelas:</strong> ' . count($tabelas) . '</p>';
                echo '<table>';
                echo '<tr><th>#</th><th>Nome da Tabela</th></tr>';
                foreach ($tabelas as $index => $tabela) {
                    $necessaria = in_array($tabela, ['empresa_clientes', 'veiculos', 'motoristas', 'rotas', 'abastecimentos', 'despesas_viagem', 'despesas_fixas']);
                    $classe = $necessaria ? 'ok' : '';
                    echo '<tr>';
                    echo '<td>' . ($index + 1) . '</td>';
                    echo '<td class="' . $classe . '">' . htmlspecialchars($tabela) . ($necessaria ? ' ✅' : '') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="erro">❌ Nenhuma tabela encontrada!</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="erro">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        echo '</div>';
        
        // Verificar estrutura das tabelas principais
        $tabelas_principais = [
            'empresa_clientes' => 'Empresa Clientes',
            'veiculos' => 'Veículos',
            'motoristas' => 'Motoristas',
            'rotas' => 'Rotas',
            'abastecimentos' => 'Abastecimentos',
            'despesas_viagem' => 'Despesas de Viagem',
            'despesas_fixas' => 'Despesas Fixas'
        ];
        
        foreach ($tabelas_principais as $tabela => $nome) {
            echo '<div class="secao">';
            echo '<h2>3.' . (array_search($tabela, array_keys($tabelas_principais)) + 1) . '. Estrutura: ' . htmlspecialchars($nome) . '</h2>';
            
            try {
                // Verificar se tabela existe
                $stmt = $conn->query("SHOW TABLES LIKE '$tabela'");
                if ($stmt->rowCount() == 0) {
                    echo '<p class="erro">❌ Tabela não existe!</p>';
                    echo '</div>';
                    continue;
                }
                
                // Mostrar estrutura
                $stmt = $conn->query("DESCRIBE `$tabela`");
                $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<p class="ok">✅ Tabela existe com ' . count($campos) . ' campos</p>';
                
                echo '<table>';
                echo '<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
                foreach ($campos as $campo) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($campo['Field']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($campo['Type']) . '</td>';
                    echo '<td>' . ($campo['Null'] === 'YES' ? '✅' : '❌') . '</td>';
                    echo '<td>' . ($campo['Key'] ? htmlspecialchars($campo['Key']) : '-') . '</td>';
                    echo '<td>' . ($campo['Default'] !== null ? htmlspecialchars($campo['Default']) : 'NULL') . '</td>';
                    echo '<td>' . ($campo['Extra'] ? htmlspecialchars($campo['Extra']) : '-') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
            } catch (PDOException $e) {
                echo '<p class="erro">❌ Erro ao acessar tabela: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            echo '</div>';
        }
        
        // Verificar dados
        echo '<div class="secao">';
        echo '<h2>4. Dados Existentes</h2>';
        
        try {
            // Empresa
            $stmt = $conn->query("SELECT COUNT(*) as total FROM empresa_clientes WHERE id = 1");
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<p><strong>Empresa ID 1:</strong> ' . ($empresa['total'] > 0 ? '<span class="ok">✅ Existe</span>' : '<span class="erro">❌ Não existe</span>') . '</p>';
            
            // Veículos
            $stmt = $conn->query("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = 1");
            $veiculos = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<p><strong>Veículos empresa_id = 1:</strong> ' . $veiculos['total'] . '</p>';
            
            // Motoristas
            $stmt = $conn->query("SELECT COUNT(*) as total FROM motoristas WHERE empresa_id = 1");
            $motoristas = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<p><strong>Motoristas empresa_id = 1:</strong> ' . $motoristas['total'] . '</p>';
            
            // Rotas
            $stmt = $conn->query("SELECT COUNT(*) as total FROM rotas WHERE empresa_id = 1");
            $rotas = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<p><strong>Rotas empresa_id = 1:</strong> ' . $rotas['total'] . '</p>';
            
        } catch (PDOException $e) {
            echo '<p class="erro">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        echo '</div>';
        
        // Teste de INSERT
        echo '<div class="secao">';
        echo '<h2>5. Teste de INSERT (Simulação)</h2>';
        
        try {
            // Obter IDs
            $stmt = $conn->query("SELECT id FROM veiculos WHERE empresa_id = 1 LIMIT 1");
            $veiculo_id = $stmt->fetchColumn();
            
            $stmt = $conn->query("SELECT id FROM motoristas WHERE empresa_id = 1 LIMIT 1");
            $motorista_id = $stmt->fetchColumn();
            
            echo '<p><strong>Veículo ID encontrado:</strong> ' . ($veiculo_id ?: 'NULL') . '</p>';
            echo '<p><strong>Motorista ID encontrado:</strong> ' . ($motorista_id ?: 'NULL') . '</p>';
            
            if ($veiculo_id && $motorista_id) {
                echo '<p class="ok">✅ IDs encontrados - Pronto para inserir rotas!</p>';
            } else {
                echo '<p class="erro">❌ Faltam veículos ou motoristas!</p>';
            }
            
        } catch (PDOException $e) {
            echo '<p class="erro">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        echo '</div>';
        
        ?>
    </div>
</body>
</html>
