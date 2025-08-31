<?php
/**
 * 🧪 TESTE DA VALIDAÇÃO SEFAZ
 * 📋 Verificar se a página e API de validação estão funcionando
 */

echo "<h1>🧪 Teste da Validação SEFAZ</h1>";

// Verificar se os arquivos existem
echo "<h2>📁 Verificação de Arquivos</h2>";

$arquivos = [
    'fiscal/validar_sefaz.php' => 'Página de Validação SEFAZ',
    'fiscal/api/validar_sefaz.php' => 'API de Validação SEFAZ',
    'includes/sidebar_pages.php' => 'Sidebar com Menu Fiscal'
];

foreach ($arquivos as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "<p style='color: green;'>✅ {$descricao}: {$arquivo}</p>";
    } else {
        echo "<p style='color: red;'>❌ {$descricao}: {$arquivo} - NÃO ENCONTRADO</p>";
    }
}

// Verificar se o menu foi adicionado ao sidebar
echo "<h2>🔍 Verificação do Menu</h2>";

$sidebar_content = file_get_contents('includes/sidebar_pages.php');
if (strpos($sidebar_content, 'Validar SEFAZ') !== false) {
    echo "<p style='color: green;'>✅ Menu 'Validar SEFAZ' encontrado no sidebar</p>";
} else {
    echo "<p style='color: red;'>❌ Menu 'Validar SEFAZ' NÃO encontrado no sidebar</p>";
}

if (strpos($sidebar_content, 'fiscal/validar_sefaz.php') !== false) {
    echo "<p style='color: green;'>✅ Link para validação SEFAZ encontrado no sidebar</p>";
} else {
    echo "<p style='color: red;'>❌ Link para validação SEFAZ NÃO encontrado no sidebar</p>";
}

// Verificar estrutura da API
echo "<h2>🔧 Verificação da API</h2>";

if (file_exists('fiscal/api/validar_sefaz.php')) {
    $api_content = file_get_contents('fiscal/api/validar_sefaz.php');
    
    $funcoes_necessarias = [
        'validarCertificadoDigital',
        'testarConexaoSEFAZ',
        'testarServico',
        'verificarStatusGeral'
    ];
    
    foreach ($funcoes_necessarias as $funcao) {
        if (strpos($api_content, "function {$funcao}") !== false) {
            echo "<p style='color: green;'>✅ Função {$funcao} encontrada na API</p>";
        } else {
            echo "<p style='color: red;'>❌ Função {$funcao} NÃO encontrada na API</p>";
        }
    }
    
    // Verificar headers da API
    if (strpos($api_content, 'Content-Type: application/json') !== false) {
        echo "<p style='color: green;'>✅ Headers JSON configurados na API</p>";
    } else {
        echo "<p style='color: red;'>❌ Headers JSON NÃO configurados na API</p>";
    }
    
    if (strpos($api_content, 'Access-Control-Allow-Origin') !== false) {
        echo "<p style='color: green;'>✅ CORS configurado na API</p>";
    } else {
        echo "<p style='color: red;'>❌ CORS NÃO configurado na API</p>";
    }
}

// Verificar estrutura da página
echo "<h2>📄 Verificação da Página</h2>";

if (file_exists('fiscal/validar_sefaz.php')) {
    $pagina_content = file_get_contents('fiscal/validar_sefaz.php');
    
    $elementos_necessarios = [
        'Validação da Conexão SEFAZ',
        'Status Geral do Sistema Fiscal',
        'Validação do Certificado Digital A1',
        'Teste de Conexão com SEFAZ',
        'Log de Validações'
    ];
    
    foreach ($elementos_necessarios as $elemento) {
        if (strpos($pagina_content, $elemento) !== false) {
            echo "<p style='color: green;'>✅ Elemento '{$elemento}' encontrado na página</p>";
        } else {
            echo "<p style='color: red;'>❌ Elemento '{$elemento}' NÃO encontrado na página</p>";
        }
    }
    
    // Verificar funções JavaScript
    $funcoes_js = [
        'validarCertificado',
        'testarStatusServicos',
        'testarConsultaNFe',
        'testarConsultaCTe',
        'testarConsultaMDFe',
        'adicionarLog'
    ];
    
    foreach ($funcoes_js as $funcao) {
        if (strpos($pagina_content, "function {$funcao}") !== false) {
            echo "<p style='color: green;'>✅ Função JavaScript {$funcao} encontrada</p>";
        } else {
            echo "<p style='color: red;'>❌ Função JavaScript {$funcao} NÃO encontrada</p>";
        }
    }
}

// Verificar se há certificado configurado
echo "<h2>🔐 Verificação de Certificado</h2>";

try {
    require_once 'includes/config.php';
    require_once 'includes/db_connect.php';
    
    $conn = getConnection();
    
    // Verificar se há certificado ativo
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM fiscal_certificados_digitais WHERE ativo = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        echo "<p style='color: green;'>✅ Certificado digital ativo encontrado no banco</p>";
        
        // Verificar detalhes do certificado
        $stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE ativo = 1 LIMIT 1");
        $stmt->execute();
        $certificado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Nome:</strong> " . ($certificado['nome_certificado'] ?? 'N/A') . "</p>";
        echo "<p><strong>Tipo:</strong> " . ($certificado['tipo_certificado'] ?? 'N/A') . "</p>";
        echo "<p><strong>Vencimento:</strong> " . ($certificado['data_vencimento'] ?? 'N/A') . "</p>";
        
    } else {
        echo "<p style='color: orange;'>⚠️ Nenhum certificado digital ativo encontrado</p>";
        echo "<p>Configure um certificado A1 em <strong>Configurações → Certificado Digital A1</strong></p>";
    }
    
    // Verificar configuração fiscal
    $stmt = $conn->prepare("SELECT * FROM fiscal_config_empresa WHERE empresa_id = 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo "<p style='color: green;'>✅ Configuração fiscal encontrada</p>";
        echo "<p><strong>Ambiente:</strong> " . ucfirst($config['ambiente_sefaz']) . "</p>";
        echo "<p><strong>CNPJ:</strong> " . ($config['cnpj'] ?? 'N/A') . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Configuração fiscal não encontrada</p>";
        echo "<p>Configure o ambiente fiscal em <strong>Configurações → Ambiente do Sistema Fiscal</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao verificar banco de dados: " . $e->getMessage() . "</p>";
}

echo "<h2>📝 Próximos Passos</h2>";
echo "<p>1. Acesse <strong>http://localhost/sistema-frotas/fiscal/validar_sefaz.php</strong></p>";
echo "<p>2. Clique em <strong>Validar Certificado</strong> para testar a validação</p>";
echo "<p>3. Verifique se a API está retornando dados corretos</p>";
echo "<p>4. Teste os serviços SEFAZ (Status, NF-e, CT-e, MDF-e)</p>";

echo "<h2>🔗 Links Úteis</h2>";
echo "<p><a href='fiscal/validar_sefaz.php' target='_blank'>🧪 Página de Validação SEFAZ</a></p>";
echo "<p><a href='pages/configuracoes.php' target='_blank'>⚙️ Configurações do Sistema</a></p>";
echo "<p><a href='fiscal/' target='_blank'>🏠 Sistema Fiscal</a></p>";

echo "<h2>✅ Resumo</h2>";
echo "<p>Se todos os arquivos foram encontrados e o banco está configurado, a validação SEFAZ está pronta para uso!</p>";
echo "<p>A página permite validar certificados digitais e testar a conexão com os serviços SEFAZ.</p>";
?>
