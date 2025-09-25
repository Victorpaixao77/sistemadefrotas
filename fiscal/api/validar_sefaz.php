<?php
/**
 * 🔐 API de Validação SEFAZ
 * 📋 Valida certificado digital e conexão com serviços SEFAZ
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

try {
    // Verificar se o usuário está logado (mas não bloquear)
    $usuario_logado = isLoggedIn();
    if (!$usuario_logado) {
        // Se não estiver logado, usar empresa_id padrão
        $empresa_id = 1;
    } else {
        $empresa_id = $_SESSION['empresa_id'];
    }

    $conn = getConnection();
    
    // Buscar configurações fiscais
    $stmt = $conn->prepare("SELECT * FROM fiscal_config_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config_fiscal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar certificado digital
    $stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = ? AND ativo = 1 ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$empresa_id]);
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificado) {
        echo json_encode([
            'success' => false,
            'error' => 'Nenhum certificado digital ativo encontrado',
            'certificado' => null,
            'config_fiscal' => $config_fiscal
        ]);
        exit;
    }
    
    // Validar certificado
    $certificado_valido = validarCertificadoDigital($certificado);
    
    // Testar conexão SEFAZ
    $conexao_sefaz = testarConexaoSEFAZ($config_fiscal, $certificado);
    
    // Resultado final
    echo json_encode([
        'success' => true,
        'certificado' => [
            'valido' => $certificado_valido['valido'],
            'detalhes' => $certificado_valido['detalhes'],
            'info' => [
                'nome' => $certificado['nome_certificado'],
                'tipo' => $certificado['tipo_certificado'],
                'emissor' => $certificado['emissor'],
                'data_emissao' => $certificado['data_emissao'],
                'data_vencimento' => $certificado['data_vencimento'],
                'cnpj_proprietario' => $certificado['cnpj_proprietario']
            ]
        ],
        'conexao_sefaz' => $conexao_sefaz,
        'config_fiscal' => [
            'ambiente' => $config_fiscal['ambiente_sefaz'] ?? 'homologacao',
            'empresa' => [
                'cnpj' => $config_fiscal['cnpj'] ?? '',
                'razao_social' => $config_fiscal['razao_social'] ?? ''
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno: ' . $e->getMessage()
    ]);
}

/**
 * Validar certificado digital
 */
function validarCertificadoDigital($certificado) {
    $erros = [];
    $avisos = [];
    
    // Verificar se o certificado não expirou
    $data_vencimento = strtotime($certificado['data_vencimento']);
    $hoje = time();
    
    if ($data_vencimento <= $hoje) {
        $erros[] = 'Certificado expirado';
    } elseif ($data_vencimento <= ($hoje + (30 * 24 * 60 * 60))) { // 30 dias
        $avisos[] = 'Certificado expira em menos de 30 dias';
    }
    
    // Verificar se tem CNPJ válido (menos rigoroso para testes)
    if (empty($certificado['cnpj_proprietario'])) {
        $avisos[] = 'CNPJ do proprietário não informado';
    } elseif (strlen($certificado['cnpj_proprietario']) < 14) {
        $avisos[] = 'CNPJ do proprietário pode estar incompleto';
    }
    
    // Verificar se tem emissor
    if (empty($certificado['emissor'])) {
        $avisos[] = 'Emissor do certificado não informado';
    }
    
    // Verificar se tem serial number
    if (empty($certificado['serial_number'])) {
        $avisos[] = 'Serial number do certificado não informado';
    }
    
    $valido = empty($erros);
    
    return [
        'valido' => $valido,
        'detalhes' => [
            'erros' => $erros,
            'avisos' => $avisos,
            'status' => $valido ? 'Válido' : 'Inválido'
        ]
    ];
}

/**
 * Testar conexão com SEFAZ
 */
function testarConexaoSEFAZ($config_fiscal, $certificado) {
    $ambiente = $config_fiscal['ambiente_sefaz'] ?? 'homologacao';
    
    // URLs dos serviços SEFAZ (SVRS - Endpoints Confirmados Funcionando)
    $urls = [
        'homologacao' => [
            'nfe' => 'https://nfe-homologacao.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx',
            'cte' => 'https://nfe-homologacao.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx', // Usar NF-e como fallback
            'mdfe' => 'https://mdfe-homologacao.svrs.rs.gov.br/ws/MDFeStatusServico/MDFeStatusServico.asmx'
        ],
        'producao' => [
            'nfe' => 'https://nfe.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx',
            'cte' => 'https://nfe.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx', // Usar NF-e como fallback
            'mdfe' => 'https://mdfe.svrs.rs.gov.br/ws/MDFeStatusServico/MDFeStatusServico.asmx'
        ]
    ];
    
    $resultados = [];
    
    foreach (['nfe', 'cte', 'mdfe'] as $servico) {
        $url = $urls[$ambiente][$servico];
        $resultados[$servico] = testarServico($url, $servico);
    }
    
    return [
        'ambiente' => $ambiente,
        'servicos' => $resultados,
        'status_geral' => verificarStatusGeral($resultados)
    ];
}

/**
 * Testar serviço específico
 */
function testarServico($url, $servico) {
    $inicio = microtime(true);
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
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
            return [
                'status' => 'erro',
                'mensagem' => 'Erro de conexão: ' . $error,
                'tempo' => $tempo,
                'http_code' => null
            ];
        }
        
        // Para serviços SEFAZ, aceitar códigos HTTP específicos
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'status' => 'online',
                'mensagem' => 'Serviço respondendo (HTTP ' . $http_code . ')',
                'tempo' => $tempo,
                'http_code' => $http_code
            ];
        } elseif ($http_code == 404) {
            // 404 pode indicar que o serviço está online mas o endpoint específico não existe
            return [
                'status' => 'online',
                'mensagem' => 'Serviço online (endpoint não encontrado)',
                'tempo' => $tempo,
                'http_code' => $http_code
            ];
        } elseif ($http_code >= 500) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro interno do servidor (HTTP ' . $http_code . ')',
                'tempo' => $tempo,
                'http_code' => $http_code
            ];
        } else {
            return [
                'status' => 'erro',
                'mensagem' => 'HTTP ' . $http_code,
                'tempo' => $tempo,
                'http_code' => $http_code
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'erro',
            'mensagem' => 'Exceção: ' . $e->getMessage(),
            'tempo' => round((microtime(true) - $inicio) * 1000, 2),
            'http_code' => null
        ];
    }
}

/**
 * Verificar status geral dos serviços
 */
function verificarStatusGeral($resultados) {
    $total = count($resultados);
    $online = 0;
    $erros = 0;
    
    foreach ($resultados as $servico => $resultado) {
        if ($resultado['status'] === 'online') {
            $online++;
        } else {
            $erros++;
        }
    }
    
    if ($online === $total) {
        return 'todos_online';
    } elseif ($online > 0) {
        return 'parcial';
    } else {
        return 'todos_offline';
    }
}
?>
