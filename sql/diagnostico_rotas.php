<?php
/**
 * Script de Diagnóstico para Inserção de Rotas
 * Execute este arquivo no navegador para verificar o banco de dados
 */

// Configuração do banco de dados
require_once '../includes/config.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Inserção de Rotas</title>
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
            max-width: 1200px;
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
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
            font-weight: 600;
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
        .resumo {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
        }
        .resumo h3 {
            color: #007bff;
            margin-bottom: 10px;
        }
        .status-box {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.9em;
            margin: 2px;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        .status-erro {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico - Inserção de Rotas</h1>
        
        <?php
        try {
            $conn = getConnection();
            
            // 1. Verificar Empresa
            echo '<div class="secao">';
            echo '<h2>1. Verificação da Empresa</h2>';
            try {
                $stmt = $conn->query("SELECT id, razao_social, nome_fantasia, cnpj FROM empresa_clientes WHERE id = 1");
                $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($empresa) {
                    $nome = $empresa['nome_fantasia'] ?: $empresa['razao_social'];
                    echo '<p class="ok">✅ Empresa ID 1 encontrada: ' . htmlspecialchars($nome) . ' (' . htmlspecialchars($empresa['razao_social']) . ')</p>';
                } else {
                    echo '<p class="erro">❌ ERRO: Empresa ID 1 NÃO encontrada em empresa_clientes!</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="erro">❌ ERRO: Tabela empresa_clientes não existe ou não foi possível acessar: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            echo '</div>';
            
            // 2. Verificar Veículos
            echo '<div class="secao">';
            echo '<h2>2. Verificação dos Veículos</h2>';
            $placas = ['ABC-1234', 'XYZ-5678', 'DEF-9012'];
            $veiculos_encontrados = [];
            
            foreach ($placas as $placa) {
                $stmt = $conn->prepare("SELECT id, placa, modelo, marca, status_id FROM veiculos WHERE empresa_id = 1 AND placa = ?");
                $stmt->execute([$placa]);
                $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($veiculo) {
                    $veiculos_encontrados[$placa] = $veiculo['id'];
                    echo '<p class="ok">✅ Veículo ' . htmlspecialchars($placa) . ' encontrado (ID: ' . $veiculo['id'] . ') - ' . htmlspecialchars($veiculo['modelo']) . '</p>';
                } else {
                    echo '<p class="erro">❌ Veículo ' . htmlspecialchars($placa) . ' NÃO encontrado!</p>';
                }
            }
            echo '</div>';
            
            // 3. Verificar Motoristas
            echo '<div class="secao">';
            echo '<h2>3. Verificação dos Motoristas</h2>';
            $cpfs = [
                '12345678901' => 'João Silva',
                '98765432109' => 'Maria Santos',
                '11122233344' => 'Pedro Oliveira'
            ];
            $motoristas_encontrados = [];
            
            foreach ($cpfs as $cpf => $nome) {
                $stmt = $conn->prepare("SELECT id, nome, cpf FROM motoristas WHERE empresa_id = 1 AND cpf = ?");
                $stmt->execute([$cpf]);
                $motorista = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($motorista) {
                    $motoristas_encontrados[$cpf] = $motorista['id'];
                    echo '<p class="ok">✅ Motorista ' . htmlspecialchars($nome) . ' encontrado (ID: ' . $motorista['id'] . ') - CPF: ' . htmlspecialchars($cpf) . '</p>';
                } else {
                    echo '<p class="erro">❌ Motorista ' . htmlspecialchars($nome) . ' (CPF: ' . htmlspecialchars($cpf) . ') NÃO encontrado!</p>';
                }
            }
            echo '</div>';
            
            // 4. Verificar Estrutura da Tabela Rotas
            echo '<div class="secao">';
            echo '<h2>4. Estrutura da Tabela Rotas</h2>';
            $stmt = $conn->query("
                SELECT 
                    COLUMN_NAME,
                    DATA_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    COLUMN_KEY
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'rotas'
                ORDER BY ORDINAL_POSITION
            ");
            $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table>';
            echo '<tr><th>Coluna</th><th>Tipo</th><th>Permite NULL</th><th>Default</th><th>Chave</th></tr>';
            foreach ($colunas as $coluna) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($coluna['COLUMN_NAME']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($coluna['DATA_TYPE']) . '</td>';
                echo '<td>' . ($coluna['IS_NULLABLE'] === 'YES' ? '✅ Sim' : '❌ Não') . '</td>';
                echo '<td>' . ($coluna['COLUMN_DEFAULT'] ?? 'NULL') . '</td>';
                echo '<td>' . ($coluna['COLUMN_KEY'] ?: '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
            
            // 5. Testar INSERT
            echo '<div class="secao">';
            echo '<h2>5. Teste de INSERT</h2>';
            
            if (count($veiculos_encontrados) === 3 && count($motoristas_encontrados) === 3) {
                // Tentar fazer um INSERT de teste com ROLLBACK
                $conn->beginTransaction();
                
                try {
                    $veiculo1_id = $veiculos_encontrados['ABC-1234'];
                    $motorista1_id = $motoristas_encontrados['12345678901'];
                    
                    $stmt = $conn->prepare("
                        INSERT INTO rotas (
                            empresa_id, veiculo_id, motorista_id,
                            estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
                            data_saida, data_chegada, data_rota,
                            km_saida, km_chegada, distancia_km, km_vazio, total_km,
                            frete, comissao, peso_carga, descricao_carga,
                            percentual_vazio, eficiencia_viagem, no_prazo,
                            status, fonte, observacoes
                        ) VALUES (
                            ?, ?, ?,
                            ?, ?, ?, ?,
                            DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 
                            DATE_SUB(CURDATE(), INTERVAL 1 MONTH - INTERVAL 1 DAY), 
                            DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?
                        )
                    ");
                    
                    $resultado = $stmt->execute([
                        1, $veiculo1_id, $motorista1_id,
                        'SP', null, 'PR', null,
                        48000, 48450, 450, 60, 510,
                        11250.00, 1125.00, 20000, 'Móveis',
                        11.8, 88.2, 1,
                        'aprovado', 'sistema', 'Rota exemplo mês -1 - 1 - TESTE'
                    ]);
                    
                    if ($resultado) {
                        echo '<p class="ok">✅ INSERT de teste executado com SUCESSO!</p>';
                        echo '<p>O INSERT está funcionando corretamente. O registro de teste foi revertido (ROLLBACK).</p>';
                    }
                    
                    $conn->rollBack();
                    
                } catch (PDOException $e) {
                    $conn->rollBack();
                    echo '<p class="erro">❌ ERRO no INSERT de teste:</p>';
                    echo '<p style="background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 10px;">';
                    echo '<strong>Código:</strong> ' . $e->getCode() . '<br>';
                    echo '<strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage());
                    echo '</p>';
                    
                    // Tentar identificar o campo problemático
                    if (strpos($e->getMessage(), 'Column') !== false) {
                        echo '<p class="aviso">⚠️ Parece ser um problema com um campo específico. Verifique a estrutura da tabela acima.</p>';
                    }
                    if (strpos($e->getMessage(), 'foreign key') !== false) {
                        echo '<p class="aviso">⚠️ Problema de foreign key. Verifique se os IDs de veículo e motorista são válidos.</p>';
                    }
                }
            } else {
                echo '<p class="erro">❌ Não é possível testar o INSERT porque faltam veículos ou motoristas.</p>';
            }
            echo '</div>';
            
            // 6. Resumo Final
            echo '<div class="resumo">';
            echo '<h3>📊 Resumo Final</h3>';
            
            $todos_ok = (count($veiculos_encontrados) === 3 && count($motoristas_encontrados) === 3);
            
            if ($todos_ok) {
                echo '<p class="ok" style="font-size: 1.1em; margin-top: 10px;">✅ TUDO OK! Você pode executar o script de inserção de rotas.</p>';
            } else {
                echo '<p class="erro" style="font-size: 1.1em; margin-top: 10px;">❌ ERRO ENCONTRADO! Corrija os problemas acima antes de continuar.</p>';
            }
            
            echo '<p style="margin-top: 15px;"><strong>Status:</strong></p>';
            echo '<p>';
            echo '<span class="status-box ' . (isset($veiculos_encontrados['ABC-1234']) ? 'status-ok' : 'status-erro') . '">Veículo 1</span>';
            echo '<span class="status-box ' . (isset($veiculos_encontrados['XYZ-5678']) ? 'status-ok' : 'status-erro') . '">Veículo 2</span>';
            echo '<span class="status-box ' . (isset($veiculos_encontrados['DEF-9012']) ? 'status-ok' : 'status-erro') . '">Veículo 3</span>';
            echo '<span class="status-box ' . (isset($motoristas_encontrados['12345678901']) ? 'status-ok' : 'status-erro') . '">Motorista 1</span>';
            echo '<span class="status-box ' . (isset($motoristas_encontrados['98765432109']) ? 'status-ok' : 'status-erro') . '">Motorista 2</span>';
            echo '<span class="status-box ' . (isset($motoristas_encontrados['11122233344']) ? 'status-ok' : 'status-erro') . '">Motorista 3</span>';
            echo '</p>';
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="secao">';
            echo '<h2 class="erro">❌ Erro de Conexão</h2>';
            echo '<p class="erro">Não foi possível conectar ao banco de dados:</p>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
    </div>
</body>
</html>
