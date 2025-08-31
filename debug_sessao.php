<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "=== DEBUG DE SESSÃO ===\n\n";

// Configurar sessão
configure_session();
session_start();

echo "1. DADOS DA SESSÃO:\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";

// Verificar se o usuário está logado
echo "\n2. VERIFICAÇÃO DE AUTENTICAÇÃO:\n";
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    echo "✅ Usuário está logado\n";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "\n";
    echo "Empresa ID: " . ($_SESSION['empresa_id'] ?? 'NÃO DEFINIDO') . "\n";
} else {
    echo "❌ Usuário NÃO está logado\n";
}

// Verificar cookies
echo "\n3. COOKIES:\n";
if (isset($_COOKIE[session_name()])) {
    echo "✅ Cookie de sessão encontrado: " . $_COOKIE[session_name()] . "\n";
} else {
    echo "❌ Cookie de sessão NÃO encontrado\n";
}

// Verificar configurações de sessão
echo "\n4. CONFIGURAÇÕES DE SESSÃO:\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";

// Testar se conseguimos acessar a API
echo "\n5. TESTE DE ACESSO À API:\n";

// Simular uma requisição para a API
$url = 'gestao_interativa/api/eixos_veiculos.php?action=layout_completo&veiculo_id=55';

// Fazer uma requisição cURL para testar
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/sistema-frotas/' . $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";

// Tentar decodificar JSON
$data = json_decode($response, true);
if ($data) {
    if (isset($data['success']) && $data['success']) {
        echo "✅ API funcionando corretamente!\n";
    } else {
        echo "❌ API retornou erro: " . ($data['error'] ?? 'Erro desconhecido') . "\n";
        if (isset($data['debug'])) {
            echo "Debug info: " . print_r($data['debug'], true) . "\n";
        }
    }
} else {
    echo "❌ Erro ao decodificar resposta da API\n";
}

echo "\n=== FIM DO DEBUG ===\n";
?> 