<?php
/**
 * Script para verificar diretamente a estrutura do banco de dados
 * Acessa o banco e mostra a estrutura real das tabelas
 */

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Tentar carregar o config
$config_loaded = false;
$config_paths = [
    '../includes/config.php',
    '../../includes/config.php',
    'includes/config.php'
];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            $config_loaded = true;
            break;
        } catch (Exception $e) {
            // Continuar tentando outros caminhos
        }
    }
}

// Se não conseguiu carregar, tentar conexão direta
if (!$config_loaded) {
    // Tentar conexão direta com configurações padrão
    $db_host = 'localhost';
    $db_port = '3307';  // Porta 3307 conforme config.php
    $db_name = 'sistema_frotas';
    $db_user = 'root';
    $db_pass = 'mudar123';
    
    try {
        $conn = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $config_loaded = true;
    } catch (PDOException $e) {
        // Mostrar erro
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação Estrutura do Banco</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .secao {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .secao h2 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.9em;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .ok {
            color: #28a745;
            font-weight: bold;
        }
        .erro {
            color: #dc3545;
            font-weight: bold;
        }
        .aviso {
            color: #ffc107;
            font-weight: bold;
        }
        .campo-obrigatorio {
            background: #fff3cd;
        }
        .campo-opcional {
            background: #d1ecf1;
        }
        .tipo-enum {
            background: #d4edda;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificação Direta da Estrutura do Banco de Dados</h1>
        
        <?php
        // Verificar se conseguiu carregar configuração
        if (!$config_loaded) {
            echo '<div class="secao">';
            echo '<h2 class="erro">❌ Erro ao Carregar Configuração</h2>';
            echo '<p class="erro">Não foi possível carregar o arquivo de configuração.</p>';
            echo '<p>Tentou os seguintes caminhos:</p><ul>';
            foreach ($config_paths as $path) {
                $exists = file_exists($path) ? '✅ Existe' : '❌ Não existe';
                echo '<li>' . htmlspecialchars($path) . ' - ' . $exists . '</li>';
            }
            echo '</ul>';
            echo '<p><strong>Tentando conexão direta...</strong></p>';
            echo '</div>';
        }
        
        try {
            // Tentar usar getConnection se disponível, senão usar conexão direta
            if (function_exists('getConnection')) {
                $conn = getConnection();
            } elseif (isset($conn)) {
                // Já tem conexão direta
            } else {
                throw new Exception('Função getConnection não encontrada e conexão direta não configurada');
            }
            
            // Informações do banco
            echo '<div class="secao">';
            echo '<h2>📊 Informações do Banco</h2>';
            $stmt = $conn->query("SELECT DATABASE() as banco_atual");
            $banco = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<p><strong>Banco de dados atual:</strong> ' . htmlspecialchars($banco['banco_atual']) . '</p>';
            echo '</div>';
            
            // Listar todas as tabelas
            echo '<div class="secao">';
            echo '<h2>📋 Todas as Tabelas do Banco</h2>';
            $stmt = $conn->query("SHOW TABLES");
            $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tabelas) > 0) {
                echo '<table>';
                echo '<tr><th>Tabela</th><th>Status</th></tr>';
                foreach ($tabelas as $tabela) {
                    $classe = in_array($tabela, ['empresa_clientes', 'veiculos', 'motoristas', 'rotas', 'abastecimentos', 'despesas_viagem', 'despesas_fixas']) 
                        ? 'ok' : '';
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($tabela) . '</strong></td>';
                    echo '<td class="' . $classe . '">' . ($classe ? '✅ Tabela necessária' : 'ℹ️ Outra tabela') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="erro">❌ Nenhuma tabela encontrada no banco de dados!</p>';
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
                echo '<h2>🔍 Estrutura: ' . htmlspecialchars($nome) . ' (' . htmlspecialchars($tabela) . ')</h2>';
                
                try {
                    $stmt = $conn->query("DESCRIBE `$tabela`");
                    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($campos) > 0) {
                        echo '<table>';
                        echo '<tr>';
                        echo '<th>Campo</th>';
                        echo '<th>Tipo</th>';
                        echo '<th>Null</th>';
                        echo '<th>Key</th>';
                        echo '<th>Default</th>';
                        echo '<th>Extra</th>';
                        echo '</tr>';
                        
                        foreach ($campos as $campo) {
                            $classe = '';
                            if ($campo['Null'] === 'NO' && $campo['Default'] === null && $campo['Extra'] !== 'auto_increment') {
                                $classe = 'campo-obrigatorio';
                            } elseif ($campo['Null'] === 'YES') {
                                $classe = 'campo-opcional';
                            }
                            
                            if (strpos($campo['Type'], 'enum') !== false) {
                                $classe .= ' tipo-enum';
                            }
                            
                            echo '<tr class="' . $classe . '">';
                            echo '<td><strong>' . htmlspecialchars($campo['Field']) . '</strong></td>';
                            echo '<td>' . htmlspecialchars($campo['Type']) . '</td>';
                            echo '<td>' . ($campo['Null'] === 'YES' ? '✅ Sim' : '❌ Não') . '</td>';
                            echo '<td>' . ($campo['Key'] ? htmlspecialchars($campo['Key']) : '-') . '</td>';
                            echo '<td>' . ($campo['Default'] !== null ? htmlspecialchars($campo['Default']) : 'NULL') . '</td>';
                            echo '<td>' . ($campo['Extra'] ? htmlspecialchars($campo['Extra']) : '-') . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                        
                        // Mostrar índices e foreign keys
                        $stmt = $conn->query("SHOW INDEX FROM `$tabela`");
                        $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($indices) > 0) {
                            echo '<h3 style="margin-top: 20px; color: #007bff;">Índices e Chaves:</h3>';
                            echo '<table style="margin-top: 10px;">';
                            echo '<tr><th>Nome</th><th>Tipo</th><th>Coluna</th><th>Único</th></tr>';
                            foreach ($indices as $indice) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($indice['Key_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($indice['Index_type']) . '</td>';
                                echo '<td>' . htmlspecialchars($indice['Column_name']) . '</td>';
                                echo '<td>' . ($indice['Non_unique'] == 0 ? '✅ Sim' : '❌ Não') . '</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        }
                        
                    } else {
                        echo '<p class="erro">❌ Tabela vazia ou não encontrada</p>';
                    }
                } catch (PDOException $e) {
                    echo '<p class="erro">❌ Erro ao acessar tabela: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                
                echo '</div>';
            }
            
            // Verificar dados existentes
            echo '<div class="secao">';
            echo '<h2>📊 Dados Existentes</h2>';
            
            $tabelas_dados = ['empresa_clientes', 'veiculos', 'motoristas', 'rotas', 'abastecimentos', 'despesas_viagem', 'despesas_fixas'];
            
            echo '<table>';
            echo '<tr><th>Tabela</th><th>Total de Registros</th><th>Registros empresa_id = 1</th></tr>';
            
            foreach ($tabelas_dados as $tabela) {
                try {
                    // Contar total
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM `$tabela`");
                    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    // Contar por empresa_id = 1
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM `$tabela` WHERE empresa_id = 1");
                    $total_empresa1 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($tabela) . '</strong></td>';
                    echo '<td>' . $total . '</td>';
                    echo '<td class="' . ($total_empresa1 > 0 ? 'ok' : 'aviso') . '">' . $total_empresa1 . '</td>';
                    echo '</tr>';
                } catch (PDOException $e) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($tabela) . '</strong></td>';
                    echo '<td class="erro">Erro: ' . htmlspecialchars($e->getMessage()) . '</td>';
                    echo '<td>-</td>';
                    echo '</tr>';
                }
            }
            echo '</table>';
            echo '</div>';
            
            // Verificar empresa ID 1 especificamente
            echo '<div class="secao">';
            echo '<h2>🏢 Verificação Específica: Empresa ID 1</h2>';
            
            try {
                $stmt = $conn->query("SELECT * FROM empresa_clientes WHERE id = 1");
                $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($empresa) {
                    echo '<p class="ok">✅ Empresa ID 1 encontrada!</p>';
                    echo '<table>';
                    foreach ($empresa as $campo => $valor) {
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($campo) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($valor ?? 'NULL') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p class="erro">❌ Empresa ID 1 NÃO encontrada em empresa_clientes</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="erro">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            echo '</div>';
            
            // Verificar veículos e motoristas da empresa 1
            echo '<div class="secao">';
            echo '<h2>🚛 Veículos e Motoristas - Empresa ID 1</h2>';
            
            try {
                $stmt = $conn->query("SELECT id, placa, modelo, marca FROM veiculos WHERE empresa_id = 1");
                $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h3>Veículos (Total: ' . count($veiculos) . ')</h3>';
                if (count($veiculos) > 0) {
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Placa</th><th>Modelo</th><th>Marca</th></tr>';
                    foreach ($veiculos as $veiculo) {
                        echo '<tr>';
                        echo '<td>' . $veiculo['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($veiculo['placa']) . '</td>';
                        echo '<td>' . htmlspecialchars($veiculo['modelo']) . '</td>';
                        echo '<td>' . htmlspecialchars($veiculo['marca']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p class="aviso">⚠️ Nenhum veículo encontrado para empresa_id = 1</p>';
                }
                
                $stmt = $conn->query("SELECT id, nome, cpf FROM motoristas WHERE empresa_id = 1");
                $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<h3 style="margin-top: 20px;">Motoristas (Total: ' . count($motoristas) . ')</h3>';
                if (count($motoristas) > 0) {
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Nome</th><th>CPF</th></tr>';
                    foreach ($motoristas as $motorista) {
                        echo '<tr>';
                        echo '<td>' . $motorista['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($motorista['nome']) . '</td>';
                        echo '<td>' . htmlspecialchars($motorista['cpf']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<p class="aviso">⚠️ Nenhum motorista encontrado para empresa_id = 1</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="erro">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="secao">';
            echo '<h2 class="erro">❌ Erro de Conexão</h2>';
            echo '<p class="erro">Não foi possível conectar ao banco de dados:</p>';
            echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>Código:</strong> ' . $e->getCode() . '</p>';
            echo '<p><strong>Arquivo:</strong> ' . $e->getFile() . '</p>';
            echo '<p><strong>Linha:</strong> ' . $e->getLine() . '</p>';
            echo '<h3>Stack Trace:</h3>';
            echo '<pre style="background: #f8d7da; padding: 10px; border-radius: 5px; overflow-x: auto;">';
            echo htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="secao">';
            echo '<h2 class="erro">❌ Erro Geral</h2>';
            echo '<p class="erro">Erro ao executar o script:</p>';
            echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>Arquivo:</strong> ' . $e->getFile() . '</p>';
            echo '<p><strong>Linha:</strong> ' . $e->getLine() . '</p>';
            echo '<h3>Stack Trace:</h3>';
            echo '<pre style="background: #f8d7da; padding: 10px; border-radius: 5px; overflow-x: auto;">';
            echo htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
            echo '</div>';
        }
        ?>
        
    </div>
</body>
</html>
