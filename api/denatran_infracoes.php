<?php
/**
 * API de consulta de infrações (multas) no DETRAN via WSDenatran.
 * Configuração em: Configurações do Sistema > Consulta de Multas (DETRAN).
 */
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';
require_once $base_path . '/includes/db_connect.php';
require_once $base_path . '/includes/denatran_config.php';

configure_session();
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Carregar configuração do banco (Configurações > Consulta de Multas DETRAN)
$denatran_habilitado = DENATRAN_HABILITADO;
$denatran_base_url = DENATRAN_BASE_URL;
$denatran_cpf_padrao = DENATRAN_CPF_USUARIO;
$denatran_cert_path = DENATRAN_CERT_PATH;
$denatran_key_path = DENATRAN_KEY_PATH;
$denatran_key_pass = DENATRAN_KEY_PASS;
try {
    $conn = getConnection();
    $stmt = $conn->prepare('SELECT habilitado, base_url, cpf_usuario, certificado_denatran_id FROM configuracoes_denatran WHERE empresa_id = :empresa_id LIMIT 1');
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $denatran_habilitado = (bool)(int)$row['habilitado'];
        $denatran_base_url = $row['base_url'] ?: $denatran_base_url;
        $denatran_cpf_padrao = $row['cpf_usuario'] ?? $denatran_cpf_padrao;
        $cert_id = isset($row['certificado_denatran_id']) ? (int)$row['certificado_denatran_id'] : 0;
        if ($cert_id > 0) {
            $st = $conn->prepare('SELECT arquivo_certificado, arquivo_chave FROM certificados_denatran WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
            $st->bindParam(':id', $cert_id, PDO::PARAM_INT);
            $st->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            $cert = $st->fetch(PDO::FETCH_ASSOC);
            if ($cert && !empty($cert['arquivo_certificado'])) {
                $upload_dir = $base_path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'certificados_denatran' . DIRECTORY_SEPARATOR;
                $denatran_cert_path = $upload_dir . $cert['arquivo_certificado'];
                $denatran_key_path = !empty($cert['arquivo_chave']) ? $upload_dir . $cert['arquivo_chave'] : '';
            }
        }
    }
} catch (Exception $e) {
    // mantém valores do arquivo de config
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== 'consultar') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida. Use action=consultar.']);
    exit;
}

$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? '';
$cpf_usuario = trim($_GET['cpf_usuario'] ?? $_POST['cpf_usuario'] ?? $denatran_cpf_padrao);

// CPF do usuário que faz a requisição (obrigatório no header do WSDenatran)
$cpf_usuario = preg_replace('/\D/', '', $cpf_usuario);
if (strlen($cpf_usuario) !== 11) {
    echo json_encode(['success' => false, 'message' => 'CPF do usuário (quem consulta) é obrigatório e deve ter 11 dígitos.']);
    exit;
}

if (!$denatran_habilitado) {
    echo json_encode([
        'success' => false,
        'message' => 'Consulta ao DETRAN não está habilitada. Em Configurações do Sistema > Consulta de Multas (DETRAN), marque "Habilitar consulta DETRAN" e salve.',
        'infracoes' => [],
        'quantidadeInfracoes' => 0
    ]);
    exit;
}

$base_url = rtrim($denatran_base_url, '/');
$headers = [
    'x-cpf-usuario: ' . $cpf_usuario,
    'Accept: application/json'
];

switch ($tipo) {
    case 'cpf':
        $cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? $_POST['cpf'] ?? '');
        if (strlen($cpf) !== 11) {
            echo json_encode(['success' => false, 'message' => 'CPF do condutor/proprietário deve ter 11 dígitos.']);
            exit;
        }
        $url = $base_url . '/v1/infracoes/cpf/' . $cpf;
        $data_inicio = $_GET['dataInicio'] ?? $_POST['dataInicio'] ?? '';
        $data_fim = $_GET['dataFim'] ?? $_POST['dataFim'] ?? '';
        if ($data_inicio) $url .= '?dataInicio=' . urlencode($data_inicio);
        if ($data_fim) $url .= (strpos($url, '?') !== false ? '&' : '?') . 'dataFim=' . urlencode($data_fim);
        break;

    case 'placa':
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['placa'] ?? $_POST['placa'] ?? ''));
        if (strlen($placa) < 7) {
            echo json_encode(['success' => false, 'message' => 'Informe a placa do veículo (7 caracteres).']);
            exit;
        }
        $exigibilidade = $_GET['exigibilidade'] ?? $_POST['exigibilidade'] ?? 'T';
        $url = $base_url . '/v1/infracoes/placa/' . $placa . '/exigibilidade/' . $exigibilidade;
        $data_inicio = $_GET['dataInicio'] ?? $_POST['dataInicio'] ?? '';
        $data_fim = $_GET['dataFim'] ?? $_POST['dataFim'] ?? '';
        if ($data_inicio) $url .= '?dataInicio=' . urlencode($data_inicio);
        if ($data_fim) $url .= (strpos($url, '?') !== false ? '&' : '?') . 'dataFim=' . urlencode($data_fim);
        break;

    case 'cnpj':
        $cnpj = preg_replace('/\D/', '', $_GET['cnpj'] ?? $_POST['cnpj'] ?? '');
        if (strlen($cnpj) !== 14) {
            echo json_encode(['success' => false, 'message' => 'CNPJ deve ter 14 dígitos.']);
            exit;
        }
        $url = $base_url . '/v1/infracoes/cnpj/' . $cnpj;
        $data_inicio = $_GET['dataInicio'] ?? $_POST['dataInicio'] ?? '';
        $data_fim = $_GET['dataFim'] ?? $_POST['dataFim'] ?? '';
        if ($data_inicio) $url .= '?dataInicio=' . urlencode($data_inicio);
        if ($data_fim) $url .= (strpos($url, '?') !== false ? '&' : '?') . 'dataFim=' . urlencode($data_fim);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Tipo de consulta inválido. Use: cpf, placa ou cnpj.']);
        exit;
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

if ($denatran_cert_path && file_exists($denatran_cert_path)) {
    curl_setopt($ch, CURLOPT_SSLCERT, $denatran_cert_path);
    if ($denatran_key_path && file_exists($denatran_key_path)) {
        curl_setopt($ch, CURLOPT_SSLKEY, $denatran_key_path);
        if ($denatran_key_pass) {
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $denatran_key_pass);
        }
    }
}

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("Denatran cURL error: " . $curl_error);
    echo json_encode([
        'success' => false,
        'message' => 'Erro de conexão com o serviço DETRAN: ' . $curl_error,
        'infracoes' => [],
        'quantidadeInfracoes' => 0
    ]);
    exit;
}

$data = json_decode($response, true);

if ($http_code !== 200) {
    $msg = isset($data['message']) ? $data['message'] : 'Serviço DETRAN retornou erro.';
    if (isset($data['returnCode'])) $msg .= ' (Código: ' . $data['returnCode'] . ')';
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'http_code' => $http_code,
        'infracoes' => [],
        'quantidadeInfracoes' => 0
    ]);
    exit;
}

// Resposta 200: normalizar formato para o front
$infracoes = $data['infracoes'] ?? [];
$quantidade = (int)($data['quantidadeInfracoes'] ?? count($infracoes));

echo json_encode([
    'success' => true,
    'message' => $quantidade > 0 ? "Encontradas {$quantidade} infração(ões)." : 'Nenhuma infração encontrada no período.',
    'infracoes' => $infracoes,
    'quantidadeInfracoes' => $quantidade,
    'raw' => $data
]);
