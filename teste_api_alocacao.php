<?php
echo "<h2>Teste da API de Alocação de Pneus</h2>";

// Simular uma requisição POST para a API
$url = 'http://localhost/sistema-frotas/api/salvar_alocacao_pneu.php';

// Dados de teste
$dados_teste = [
    'veiculo_id' => 55,
    'pneu_id' => 10,
    'posicao' => 1,
    'acao' => 'alocar'
];

echo "<h3>1. Testando API com dados:</h3>";
echo "<pre>" . json_encode($dados_teste, JSON_PRETTY_PRINT) . "</pre>";

// Fazer requisição POST
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados_teste));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($dados_teste))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>2. Resposta da API:</h3>";
echo "<p>HTTP Code: $http_code</p>";
echo "<p>Response: $response</p>";

if ($response) {
    $json_response = json_decode($response, true);
    if ($json_response) {
        echo "<h3>3. Resposta decodificada:</h3>";
        echo "<pre>" . json_encode($json_response, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($json_response['success']) && $json_response['success']) {
            echo "<p style='color: green;'>✅ API funcionando corretamente!</p>";
        } else {
            echo "<p style='color: red;'>❌ Erro na API: " . ($json_response['error'] ?? 'Erro desconhecido') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Resposta não é JSON válido</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Nenhuma resposta da API</p>";
}

echo "<h3>4. Verificando se a alocação foi salva:</h3>";

try {
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar se a instalação foi criada
    $stmt = $pdo->prepare("SELECT * FROM instalacoes_pneus WHERE pneu_id = ? AND veiculo_id = ? AND posicao = ? AND data_remocao IS NULL");
    $stmt->execute([$dados_teste['pneu_id'], $dados_teste['veiculo_id'], $dados_teste['posicao']]);
    $instalacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($instalacao) {
        echo "<p style='color: green;'>✅ Instalação encontrada no banco:</p>";
        echo "<pre>" . json_encode($instalacao, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p style='color: orange;'>⚠️ Instalação não encontrada no banco</p>";
    }
    
    // Verificar status do pneu
    $stmt = $pdo->prepare("SELECT id, status_id FROM pneus WHERE id = ?");
    $stmt->execute([$dados_teste['pneu_id']]);
    $pneu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pneu) {
        echo "<p>Status do pneu ID {$pneu['id']}: {$pneu['status_id']}</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar banco: " . $e->getMessage() . "</p>";
}
?> 