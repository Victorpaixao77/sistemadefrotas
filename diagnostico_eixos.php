<?php
// Script de diagnóstico para problemas com eixos em produção
session_start();
$_SESSION['usuario_id'] = 16;
$_SESSION['empresa_id'] = 1;

echo "<h2>Diagnóstico de Eixos - Produção</h2>";

// 1. Verificar configuração do banco
echo "<h3>1. Configuração do Banco</h3>";
echo "DB_SERVER: " . (defined('DB_SERVER') ? DB_SERVER : 'NÃO DEFINIDO') . "<br>";
echo "DB_USERNAME: " . (defined('DB_USERNAME') ? DB_USERNAME : 'NÃO DEFINIDO') . "<br>";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NÃO DEFINIDO') . "<br>";

// 2. Testar conexão
echo "<h3>2. Teste de Conexão</h3>";
try {
    // Usar configurações da API
    if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost:3306');
    if (!defined('DB_USERNAME')) define('DB_USERNAME', 'root');
    if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'SenhaForte@2024');
    if (!defined('DB_NAME')) define('DB_NAME', 'sistema_frotas');
    
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexão com banco OK<br>";
    
    // 3. Verificar se as tabelas existem
    echo "<h3>3. Verificação de Tabelas</h3>";
    $tabelas = ['eixos_veiculos', 'alocacoes_pneus_flexiveis', 'pneus', 'veiculos'];
    
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabela '$tabela' existe<br>";
        } else {
            echo "❌ Tabela '$tabela' NÃO existe<br>";
        }
    }
    
    // 4. Verificar dados do veículo
    echo "<h3>4. Dados do Veículo 55</h3>";
    $stmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([55, 1]);
    $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($veiculo) {
        echo "✅ Veículo encontrado: " . $veiculo['placa'] . "<br>";
    } else {
        echo "❌ Veículo 55 não encontrado<br>";
    }
    
    // 5. Verificar eixos do veículo
    echo "<h3>5. Eixos do Veículo 55</h3>";
    $stmt = $pdo->prepare("SELECT * FROM eixos_veiculos WHERE veiculo_id = ? AND empresa_id = ?");
    $stmt->execute([55, 1]);
    $eixos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($eixos) > 0) {
        echo "✅ " . count($eixos) . " eixos encontrados:<br>";
        foreach ($eixos as $eixo) {
            echo "- ID: {$eixo['id']}, Tipo: {$eixo['tipo_veiculo']}, Pneus: {$eixo['quantidade_pneus']}<br>";
        }
    } else {
        echo "❌ Nenhum eixo encontrado para o veículo 55<br>";
    }
    
    // 6. Verificar alocações de pneus
    echo "<h3>6. Alocações de Pneus</h3>";
    $stmt = $pdo->prepare("
        SELECT apf.*, p.numero_serie, ev.tipo_veiculo
        FROM alocacoes_pneus_flexiveis apf
        INNER JOIN pneus p ON apf.pneu_id = p.id
        INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
        WHERE ev.veiculo_id = ? AND apf.empresa_id = ? AND apf.ativo = 1
    ");
    $stmt->execute([55, 1]);
    $alocacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($alocacoes) > 0) {
        echo "✅ " . count($alocacoes) . " alocações encontradas:<br>";
        foreach ($alocacoes as $alocacao) {
            echo "- Pneu: {$alocacao['numero_serie']}, Slot: {$alocacao['slot_id']}, Tipo: {$alocacao['tipo_veiculo']}<br>";
        }
    } else {
        echo "❌ Nenhuma alocação encontrada<br>";
    }
    
    // 7. Simular resposta da API
    echo "<h3>7. Simulação da API</h3>";
    $_GET['action'] = 'layout_completo';
    $_GET['veiculo_id'] = 55;
    
    ob_start();
    include 'gestao_interativa/api/eixos_veiculos.php';
    $output = ob_get_clean();
    
    echo "Resposta da API:<br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $data = json_decode($output, true);
    if ($data && $data['success']) {
        echo "✅ API retornou sucesso<br>";
        echo "Eixos Caminhão: " . count($data['layout']['eixosCaminhao']) . "<br>";
        echo "Eixos Carreta: " . count($data['layout']['eixosCarreta']) . "<br>";
        echo "Pneus Alocados: " . count($data['layout']['pneusFlexAlocados']) . "<br>";
    } else {
        echo "❌ API retornou erro: " . ($data['error'] ?? 'Erro desconhecido') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

echo "<h3>8. Verificação de Sessão</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Empresa ID: " . $_SESSION['empresa_id'] . "<br>";
echo "Usuário ID: " . $_SESSION['usuario_id'] . "<br>";

echo "<h3>9. Verificação de Arquivos</h3>";
$arquivos = [
    'gestao_interativa/api/eixos_veiculos.php',
    'includes/db_connect.php',
    'includes/config.php'
];

foreach ($arquivos as $arquivo) {
    if (file_exists($arquivo)) {
        echo "✅ $arquivo existe<br>";
    } else {
        echo "❌ $arquivo NÃO existe<br>";
    }
}
?> 