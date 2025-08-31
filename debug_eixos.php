<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "=== DEBUG SISTEMA DE EIXOS ===\n\n";

try {
    $conn = getConnection();
    
    // 1. Verificar se as tabelas existem
    echo "1. VERIFICANDO TABELAS:\n";
    $tabelas = ['eixos_veiculos', 'alocacoes_pneus_flexiveis', 'posicoes_pneus', 'status_pneus'];
    
    foreach ($tabelas as $tabela) {
        $stmt = $conn->query("SHOW TABLES LIKE '$tabela'");
        $existe = $stmt->fetch();
        if ($existe) {
            echo "   ✅ $tabela: EXISTE\n";
            
            // Contar registros
            $stmt = $conn->query("SELECT COUNT(*) as total FROM $tabela");
            $total = $stmt->fetch();
            echo "      - Registros: {$total['total']}\n";
        } else {
            echo "   ❌ $tabela: NÃO EXISTE\n";
        }
    }
    
    // 2. Verificar veículos disponíveis
    echo "\n2. VERIFICANDO VEÍCULOS:\n";
    $stmt = $conn->query("SELECT COUNT(*) as total FROM veiculos");
    $total_veiculos = $stmt->fetch();
    echo "   Total de veículos: {$total_veiculos['total']}\n";
    
    if ($total_veiculos['total'] > 0) {
        $stmt = $conn->query("SELECT id, placa, modelo, numero_eixos FROM veiculos LIMIT 3");
        $veiculos = $stmt->fetchAll();
        foreach ($veiculos as $veiculo) {
            echo "   - ID: {$veiculo['id']}, Placa: {$veiculo['placa']}, Modelo: {$veiculo['modelo']}, Eixos: {$veiculo['numero_eixos']}\n";
        }
    }
    
    // 3. Testar API de eixos
    echo "\n3. TESTANDO API DE EIXOS:\n";
    
    // Simular uma requisição para a API
    $veiculo_teste = null;
    $stmt = $conn->query("SELECT id FROM veiculos LIMIT 1");
    $result = $stmt->fetch();
    if ($result) {
        $veiculo_teste = $result['id'];
        echo "   Testando com veículo ID: $veiculo_teste\n";
        
        // Verificar se há eixos para este veículo
        $stmt = $conn->prepare("SELECT * FROM eixos_veiculos WHERE veiculo_id = ?");
        $stmt->execute([$veiculo_teste]);
        $eixos = $stmt->fetchAll();
        
        if (count($eixos) > 0) {
            echo "   ✅ Eixos encontrados: " . count($eixos) . "\n";
            foreach ($eixos as $eixo) {
                echo "      - Eixo {$eixo['numero_eixo']} ({$eixo['tipo_veiculo']}): {$eixo['quantidade_pneus']} pneus\n";
            }
        } else {
            echo "   ⚠️  Nenhum eixo encontrado para este veículo\n";
        }
    } else {
        echo "   ❌ Nenhum veículo encontrado para teste\n";
    }
    
    // 4. Verificar JavaScript e console
    echo "\n4. VERIFICANDO ARQUIVOS JAVASCRIPT:\n";
    
    $arquivos_js = [
        'js/gestao_interativa_eixos.js',
        'gestao_interativa/api/eixos_veiculos.php'
    ];
    
    foreach ($arquivos_js as $arquivo) {
        if (file_exists($arquivo)) {
            echo "   ✅ $arquivo: EXISTE\n";
        } else {
            echo "   ❌ $arquivo: NÃO EXISTE\n";
        }
    }
    
    // 5. Testar conexão da API
    echo "\n5. TESTANDO CONEXÃO DA API:\n";
    
    if ($veiculo_teste) {
        // Simular uma chamada para a API
        $url = "gestao_interativa/api/eixos_veiculos.php?action=layout_completo&veiculo_id=$veiculo_teste";
        echo "   URL de teste: $url\n";
        
        // Verificar se o arquivo da API existe e é acessível
        if (file_exists('gestao_interativa/api/eixos_veiculos.php')) {
            echo "   ✅ Arquivo da API existe\n";
            
            // Verificar se há erros de sintaxe
            $output = shell_exec("php -l gestao_interativa/api/eixos_veiculos.php 2>&1");
            if (strpos($output, 'No syntax errors') !== false) {
                echo "   ✅ Sintaxe PHP OK\n";
            } else {
                echo "   ❌ Erro de sintaxe: $output\n";
            }
        } else {
            echo "   ❌ Arquivo da API não encontrado\n";
        }
    }
    
    // 6. Verificar logs de erro
    echo "\n6. VERIFICANDO LOGS:\n";
    $log_file = 'logs/php_errors.log';
    if (file_exists($log_file)) {
        $tamanho = filesize($log_file);
        echo "   Log file: $log_file (tamanho: $tamanho bytes)\n";
        
        if ($tamanho > 0) {
            $linhas = file($log_file);
            $ultimas_linhas = array_slice($linhas, -5);
            echo "   Últimas 5 linhas do log:\n";
            foreach ($ultimas_linhas as $linha) {
                echo "      " . trim($linha) . "\n";
            }
        }
    } else {
        echo "   ❌ Arquivo de log não encontrado\n";
    }
    
    echo "\n=== SUGESTÕES DE CORREÇÃO ===\n";
    echo "1. Verifique se as tabelas foram criadas corretamente\n";
    echo "2. Verifique se há veículos cadastrados\n";
    echo "3. Verifique o console do navegador para erros JavaScript\n";
    echo "4. Verifique se a API está retornando dados corretos\n";
    echo "5. Verifique se o arquivo gestao_interativa_eixos.js está sendo carregado\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "\n";
}
?> 