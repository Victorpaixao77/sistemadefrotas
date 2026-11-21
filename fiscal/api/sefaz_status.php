<?php
/**
 * 🌐 API Status SEFAZ Real
 * 📋 Verifica status real da SEFAZ usando certificados
 */

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/_cors_same_origin.php';
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar autenticação
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado - Sessão inválida']);
    exit;
}

// Verificar se está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado - Usuário não logado']);
    exit;
}

// Função para obter cache do status SEFAZ
function getSefazCache() {
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        
        // Verificar se o cache ainda é válido (menos de 5 minutos)
        if ($cache_data && isset($cache_data['timestamp'])) {
            $cache_time = strtotime($cache_data['timestamp']);
            $current_time = time();
            
            if (($current_time - $cache_time) < 300) { // 5 minutos
                return $cache_data;
            }
        }
    }
    
    return null;
}

// Função para salvar cache do status SEFAZ
function saveSefazCache($data) {
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    
    $cache_data = [
        'status' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'empresa_id' => $_SESSION['empresa_id'] ?? 'unknown'
    ];
    
    file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
    return true;
}

// Função para limpar cache
function clearSefazCache() {
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    if (file_exists($cache_file)) {
        return unlink($cache_file);
    }
    return true; // Não há erro se o arquivo não existe
}

// Função para testar conexão SEFAZ
function testarConexaoSefaz($ambiente = 'homologacao') {
    $urls = [
        'homologacao' => 'https://nfe-homologacao.sefazrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx',
        'producao' => 'https://nfe.sefazrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx'
    ];
    
    $test_url = $urls[$ambiente] ?? $urls['homologacao'];
    
    // Teste 1: Conexão básica
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    $time = round(($end_time - $start_time) * 1000, 2);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    $resultado = [
        'conexao_basica' => [
            'sucesso' => ($response !== false && !$error),
            'tempo' => $time,
            'http_code' => $http_code,
            'erro' => $error,
            'ip_destino' => $info['primary_ip'] ?? 'N/A'
        ]
    ];
    
    // Teste 2: Com certificado se disponível
    $pasta_certificados = realpath('../../uploads/certificados/');
    $cert_icp = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_icp.pem';
    $key_icp = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_icp.pem';
    
    if (file_exists($cert_icp) && file_exists($key_icp)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');
        curl_setopt($ch, CURLOPT_SSLCERT, $cert_icp);
        curl_setopt($ch, CURLOPT_SSLKEY, $key_icp);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        
        $start_time = microtime(true);
        $response_cert = curl_exec($ch);
        $end_time = microtime(true);
        $time_cert = round(($end_time - $start_time) * 1000, 2);
        
        $http_code_cert = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_cert = curl_error($ch);
        $info_cert = curl_getinfo($ch);
        
        curl_close($ch);
        
        $resultado['conexao_certificado'] = [
            'sucesso' => ($response_cert !== false && !$error_cert),
            'tempo' => $time_cert,
            'http_code' => $http_code_cert,
            'erro' => $error_cert,
            'ip_destino' => $info_cert['primary_ip'] ?? 'N/A',
            'certificado_usado' => 'certificado_icp.pem'
        ];
    } else {
        $resultado['conexao_certificado'] = [
            'sucesso' => false,
            'erro' => 'Certificado ICP-Brasil não encontrado',
            'certificado_usado' => 'Nenhum'
        ];
    }
    
    // Teste 3: Requisição SOAP
    $soap_request = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfe="http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4">
    <soap:Body>
        <nfe:nfeStatusServicoNF>
            <nfe:nfeDadosMsg>
                <consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
                    <tpAmb>2</tpAmb>
                    <xServ>STATUS</xServ>
                </consStatServ>
            </nfe:nfeDadosMsg>
        </nfe:nfeStatusServicoNF>
    </soap:Body>
</soap:Envelope>';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_request);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF',
        'Content-Length: ' . strlen($soap_request)
    ]);
    
    // Usar certificado se disponível
    if (file_exists($cert_icp) && file_exists($key_icp)) {
        curl_setopt($ch, CURLOPT_SSLCERT, $cert_icp);
        curl_setopt($ch, CURLOPT_SSLKEY, $key_icp);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
    }
    
    $start_time = microtime(true);
    $response_soap = curl_exec($ch);
    $end_time = microtime(true);
    $time_soap = round(($end_time - $start_time) * 1000, 2);
    
    $http_code_soap = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error_soap = curl_error($ch);
    $info_soap = curl_getinfo($ch);
    
    curl_close($ch);
    
    $resultado['requisicao_soap'] = [
        'sucesso' => ($response_soap !== false && !$error_soap),
        'tempo' => $time_soap,
        'http_code' => $http_code_soap,
        'erro' => $error_soap,
        'ip_destino' => $info_soap['primary_ip'] ?? 'N/A',
        'resposta_soap' => strpos($response_soap, 'soap:Envelope') !== false
    ];
    
    return $resultado;
}

// Processar requisição
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

switch ($action) {
    case 'status':
        $ambiente = $_GET['ambiente'] ?? 'homologacao';
        $force_refresh = isset($_GET['force']) && $_GET['force'] === 'true';
        
        // Verificar cache primeiro (se não for refresh forçado)
        if (!$force_refresh) {
            $cached_status = getSefazCache();
            if ($cached_status) {
                echo json_encode([
                    'success' => true,
                    'from_cache' => true,
                    'ambiente' => $ambiente,
                    'cache_time' => $cached_status['timestamp'],
                    'status_geral' => $cached_status['status']['status_geral'],
                    'status_texto' => $cached_status['status']['status_texto'],
                    'status_cor' => $cached_status['status']['status_cor'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'detalhes' => $cached_status['status']['detalhes']
                ]);
                exit;
            }
        }
        
        // Se não há cache ou é refresh forçado, fazer teste real
        $resultado = testarConexaoSefaz($ambiente);
        
        // Determinar status geral
        $conexao_basica = $resultado['conexao_basica']['sucesso'];
        $conexao_certificado = $resultado['conexao_certificado']['sucesso'];
        $requisicao_soap = $resultado['requisicao_soap']['sucesso'];
        
        // Status geral
        if ($conexao_basica && $conexao_certificado && $requisicao_soap) {
            $status_geral = 'online';
            $status_texto = 'Sistema SEFAZ funcionando perfeitamente';
            $status_cor = 'success';
        } elseif ($conexao_basica && $conexao_certificado) {
            $status_geral = 'online';
            $status_texto = 'SEFAZ funcionando (erro SOAP pode ser normal)';
            $status_cor = 'warning';
        } elseif ($conexao_basica) {
            $status_geral = 'online';
            $status_texto = 'SEFAZ funcionando (certificado não aceito)';
            $status_cor = 'warning';
        } else {
            $status_geral = 'offline';
            $status_texto = 'SEFAZ não está respondendo';
            $status_cor = 'danger';
        }
        
        $response_data = [
            'success' => true,
            'from_cache' => false,
            'ambiente' => $ambiente,
            'status_geral' => $status_geral,
            'status_texto' => $status_texto,
            'status_cor' => $status_cor,
            'timestamp' => date('Y-m-d H:i:s'),
            'detalhes' => $resultado
        ];
        
        // Salvar no cache
        saveSefazCache($response_data);
        
        echo json_encode($response_data);
        break;
        
    case 'ping':
        // Teste rápido de conectividade
        $resultado = testarConexaoSefaz('homologacao');
        $online = $resultado['conexao_basica']['sucesso'];
        
        echo json_encode([
            'success' => true,
            'online' => $online,
            'tempo' => $resultado['conexao_basica']['tempo'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
        
    case 'clear_cache':
        // Limpar cache
        if (clearSefazCache()) {
            echo json_encode([
                'success' => true,
                'message' => 'Cache limpo com sucesso'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao limpar cache'
            ]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
}
?>
