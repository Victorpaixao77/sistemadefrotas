<?php
/**
 * 🔄 Conversor de Certificado PFX para PEM
 * 📋 Converte certificado PFX/P12 para formato PEM compatível com cURL
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Conversor de Certificado PFX para PEM</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔄 Conversor de Certificado PFX para PEM</h1>
        <p>Converte certificado PFX/P12 para formato PEM compatível com cURL</p>";

try {
    $conn = getConnection();
    $empresa_id = 1; // Usar empresa padrão para teste
    
    // Buscar certificado digital
    $stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = ? AND ativo = 1 ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$empresa_id]);
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificado) {
        echo "<div class='card error'>
            <h3>❌ Certificado não encontrado</h3>
            <p>Nenhum certificado digital ativo encontrado para a empresa ID: $empresa_id</p>
            <p><a href='../pages/configuracoes.php' class='btn'>Configure um certificado A1</a></p>
        </div>";
        exit;
    }
    
    $caminho_certificado = '../uploads/certificados/' . $certificado['arquivo_certificado'];
    $certificado_existe = file_exists($caminho_certificado);
    
    echo "<div class='card info'>
        <h3>📋 Informações do Certificado</h3>
        <p><strong>Nome:</strong> " . htmlspecialchars($certificado['nome_certificado']) . "</p>
        <p><strong>Tipo:</strong> " . htmlspecialchars($certificado['tipo_certificado']) . "</p>
        <p><strong>Arquivo:</strong> " . htmlspecialchars($certificado['arquivo_certificado']) . "</p>
        <p><strong>Caminho:</strong> <code>$caminho_certificado</code></p>
        <p><strong>Existe:</strong> " . ($certificado_existe ? '✅ Sim' : '❌ Não') . "</p>
    </div>";
    
    if (!$certificado_existe) {
        echo "<div class='card error'>
            <h3>❌ Arquivo não encontrado</h3>
            <p>O arquivo do certificado não foi encontrado no caminho especificado.</p>
            <p><a href='../pages/configuracoes.php' class='btn'>Verificar configurações</a></p>
        </div>";
        exit;
    }
    
    $extensao = pathinfo($caminho_certificado, PATHINFO_EXTENSION);
    
    if ($extensao !== 'pfx' && $extensao !== 'p12') {
        echo "<div class='card warning'>
            <h3>⚠️ Formato não é PFX/P12</h3>
            <p>O certificado já está no formato: <strong>$extensao</strong></p>
            <p>Se for PEM, já está compatível com cURL.</p>
            <p><a href='teste_sefaz_certificado.php' class='btn'>Testar SEFAZ</a></p>
        </div>";
        exit;
    }
    
    // Verificar se OpenSSL está disponível
    if (!function_exists('openssl_pkcs12_read')) {
        echo "<div class='card error'>
            <h3>❌ OpenSSL não disponível</h3>
            <p>A extensão OpenSSL do PHP não está habilitada.</p>
            <p>Você precisa converter manualmente usando os comandos:</p>
            <div class='code'>
                openssl pkcs12 -in certificado.pfx -out certificado.pem -clcerts -nokeys<br>
                openssl pkcs12 -in certificado.pfx -out chave.pem -nocerts -nodes
            </div>
        </div>";
        exit;
    }
    
    // Tentar converter o certificado
    $senha = $certificado['senha_criptografada'] ?? '';
    
    echo "<div class='card info'>
        <h3>🔄 Convertendo Certificado</h3>
        <p><strong>Formato origem:</strong> $extensao</p>
        <p><strong>Formato destino:</strong> PEM</p>
        <p><strong>Senha:</strong> " . (empty($senha) ? '❌ Não informada' : '✅ Configurada') . "</p>
    </div>";
    
    if (empty($senha)) {
        echo "<div class='card error'>
            <h3>❌ Senha não informada</h3>
            <p>Para converter o certificado, você precisa informar a senha.</p>
            <p><a href='../pages/configuracoes.php' class='btn'>Configurar senha</a></p>
        </div>";
        exit;
    }
    
    // Ler o certificado PFX
    $pfx_content = file_get_contents($caminho_certificado);
    if (!$pfx_content) {
        echo "<div class='card error'>
            <h3>❌ Erro ao ler arquivo</h3>
            <p>Não foi possível ler o arquivo do certificado.</p>
        </div>";
        exit;
    }
    
    // Converter PFX para PEM
    $certs = [];
    $result = openssl_pkcs12_read($pfx_content, $certs, $senha);
    
    if (!$result) {
        echo "<div class='card error'>
            <h3>❌ Erro na conversão</h3>
            <p><strong>Erro OpenSSL:</strong> " . openssl_error_string() . "</p>
            <p><strong>Possíveis causas:</strong></p>
            <ul>
                <li>Senha incorreta</li>
                <li>Arquivo PFX corrompido</li>
                <li>Formato inválido</li>
            </ul>
            <p><strong>Solução manual:</strong></p>
            <div class='code'>
                openssl pkcs12 -in certificado.pfx -out certificado.pem -clcerts -nokeys<br>
                openssl pkcs12 -in certificado.pfx -out chave.pem -nocerts -nodes
            </div>
        </div>";
        exit;
    }
    
    // Salvar certificado PEM
    $cert_pem_path = '../uploads/certificados/' . pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME) . '.pem';
    $key_pem_path = '../uploads/certificados/' . pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME) . '_key.pem';
    
    $cert_saved = file_put_contents($cert_pem_path, $certs['cert']);
    $key_saved = file_put_contents($key_pem_path, $certs['pkey']);
    
    if (!$cert_saved || !$key_saved) {
        echo "<div class='card error'>
            <h3>❌ Erro ao salvar arquivos</h3>
            <p>Não foi possível salvar os arquivos PEM convertidos.</p>
            <p><strong>Verifique permissões da pasta:</strong> ../uploads/certificados/</p>
        </div>";
        exit;
    }
    
    // Atualizar banco de dados com novos caminhos
    $stmt = $conn->prepare("UPDATE fiscal_certificados_digitais SET arquivo_certificado = ? WHERE id = ?");
    $stmt->execute([pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME) . '.pem', $certificado['id']]);
    
    echo "<div class='card success'>
        <h3>✅ Conversão realizada com sucesso!</h3>
        <p><strong>Certificado PEM:</strong> <code>$cert_pem_path</code></p>
        <p><strong>Chave privada PEM:</strong> <code>$key_pem_path</code></p>
        <p><strong>Banco atualizado:</strong> ✅</p>
    </div>";
    
    echo "<div class='card info'>
        <h3>📋 Arquivos gerados</h3>
        <p><strong>Certificado (.pem):</strong></p>
        <div class='code'>" . htmlspecialchars($certs['cert']) . "</div>
        <p><strong>Chave privada (.pem):</strong></p>
        <div class='code'>" . htmlspecialchars($certs['pkey']) . "</div>
    </div>";
    
    echo "<div class='card'>
        <h3>🚀 Próximos passos</h3>
        <p>Agora você pode testar a conexão SEFAZ com o certificado convertido:</p>
        <p><a href='teste_sefaz_certificado.php' class='btn'>🧪 Testar SEFAZ</a></p>
        <p><a href='../pages/configuracoes.php' class='btn'>⚙️ Configurações</a></p>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='card error'>
        <h3>❌ Erro no Sistema</h3>
        <p><strong>Erro:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>
