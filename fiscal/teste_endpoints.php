<?php
/**
 * 🔍 Teste de Endpoints SEFAZ Atualizados
 * 📋 Verifica conectividade com os novos endpoints
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste de Endpoints SEFAZ</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .endpoint { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        .status { font-weight: bold; }
        .online { color: #155724; }
        .offline { color: #721c24; }
        .time { color: #0c5460; }
    </style>
</head>
<body>
    <h1>🔍 Teste de Endpoints SEFAZ Atualizados</h1>
    <p>Verificando conectividade com os novos endpoints...</p>";

// Função para testar endpoint
function testarEndpoint($url, $nome) {
    echo "<div class='endpoint'>";
    echo "<h3>🌐 $nome</h3>";
    echo "<p><strong>URL:</strong> <code>$url</code></p>";
    
    $inicio = microtime(true);
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $tempo = round((microtime(true) - $inicio) * 1000, 2);
        
        curl_close($ch);
        
        if ($error) {
            echo "<p class='status offline'>❌ Erro de Conexão</p>";
            echo "<p><strong>Erro:</strong> $error</p>";
            echo "<p class='time'><strong>Tempo:</strong> {$tempo}ms</p>";
            echo "</div>";
            return false;
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            echo "<p class='status online'>✅ Online (HTTP $http_code)</p>";
            echo "<p><strong>Resposta:</strong> Serviço respondendo corretamente</p>";
        } elseif ($http_code == 404) {
            echo "<p class='status online'>✅ Online (HTTP $http_code)</p>";
            echo "<p><strong>Resposta:</strong> Serviço online (endpoint não encontrado - normal para alguns serviços)</p>";
        } elseif ($http_code >= 500) {
            echo "<p class='status offline'>❌ Erro do Servidor (HTTP $http_code)</p>";
            echo "<p><strong>Resposta:</strong> Erro interno do servidor SEFAZ</p>";
        } else {
            echo "<p class='status offline'>❌ Status Inesperado (HTTP $http_code)</p>";
            echo "<p><strong>Resposta:</strong> Código HTTP não esperado</p>";
        }
        
        echo "<p class='time'><strong>Tempo:</strong> {$tempo}ms</p>";
        echo "<p><strong>HTTP Code:</strong> $http_code</p>";
        
    } catch (Exception $e) {
        echo "<p class='status offline'>❌ Exceção</p>";
        echo "<p><strong>Erro:</strong> " . $e->getMessage() . "</p>";
        echo "<p class='time'><strong>Tempo:</strong> {$tempo}ms</p>";
    }
    
    echo "</div>";
    return true;
}

// Testar endpoints de homologação
echo "<h2>🧪 Teste de Homologação</h2>";

$endpoints_homologacao = [
    'NF-e (SVRS)' => 'https://nfe-homologacao.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx',
    'CT-e (MS)' => 'https://homologacao.cte.ms.gov.br/ws/CTeStatusServicoV4',
    'MDF-e (SVRS)' => 'https://mdfe-homologacao.svrs.rs.gov.br/ws/MDFeStatusServico/MDFeStatusServico.asmx'
];

foreach ($endpoints_homologacao as $nome => $url) {
    testarEndpoint($url, $nome);
}

// Testar endpoints de produção
echo "<h2>🚀 Teste de Produção</h2>";

$endpoints_producao = [
    'NF-e (SVRS)' => 'https://nfe.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx',
    'CT-e (MS)' => 'https://cte.ms.gov.br/ws/CTeStatusServicoV4',
    'MDF-e (SVRS)' => 'https://mdfe.svrs.rs.gov.br/ws/MDFeStatusServico/MDFeStatusServico.asmx'
];

foreach ($endpoints_producao as $nome => $url) {
    testarEndpoint($url, $nome);
}

echo "<h2>📋 Resumo</h2>";
echo "<p>Este teste verifica se os novos endpoints SEFAZ estão acessíveis.</p>";
echo "<p><strong>Nota:</strong> Alguns endpoints podem retornar HTTP 404, o que é normal e indica que o serviço está online.</p>";

echo "</body></html>";
?>
