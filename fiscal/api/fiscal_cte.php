<?php
/**
 * 🚛 API de CT-e
 * �� Gerencia operações de Conhecimento de Transporte Eletrônico
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

// Incluir configurações
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/../includes/CTeService.php';
require_once __DIR__ . '/../includes/CteXmlHelper.php';
require_once __DIR__ . '/../includes/CteImportHelper.php';

/**
 * Gera chave de acesso do CT-e (modelo 57).
 * Observação: este gerador segue o mesmo padrão já usado no seu documentos_fiscais_v2.php
 * e serve como base para emissão; pode precisar ajustes finos conforme suas regras fiscais.
 */
function gerarChaveAcessoCTe(PDO $conn, int $empresaId, string $numero, string $serie): string
{
    $ano = date('y');
    $mes = date('m');

    $mapa_cUF = [
        'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15',
        'AP' => '16', 'TO' => '17', 'MA' => '21', 'PI' => '22', 'CE' => '23',
        'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27', 'SE' => '28',
        'BA' => '29', 'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35',
        'PR' => '41', 'SC' => '42', 'RS' => '43', 'MS' => '50', 'MT' => '51',
        'GO' => '52', 'DF' => '53'
    ];

    // Buscar CNPJ e UF via config fiscal
    $stmt = $conn->prepare("SELECT cnpj, codigo_municipio FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1");
    $stmt->execute([$empresaId]);
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $cnpj = preg_replace('/\D/', '', (string)($cfg['cnpj'] ?? ''));
    if (strlen($cnpj) < 14) $cnpj = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
    $cnpj = substr($cnpj, 0, 14);

    $codigo_municipio = (string)($cfg['codigo_municipio'] ?? '');
    $siglaUF = '';
    if (!empty($codigo_municipio)) {
        try {
            $stmtUf = $conn->prepare("SELECT uf FROM cidades WHERE codigo_ibge = :ibge LIMIT 1");
            $stmtUf->execute([':ibge' => $codigo_municipio]);
            $siglaUF = (string)($stmtUf->fetchColumn() ?: '');
        } catch (Throwable $e) {}
    }
    $cUF = $mapa_cUF[$siglaUF] ?? '43';

    $modelo = '57';
    $serie_padrao = str_pad(preg_replace('/\D/', '', $serie), 3, '0', STR_PAD_LEFT);
    $numero_padrao = str_pad(preg_replace('/\D/', '', $numero), 9, '0', STR_PAD_LEFT);

    // tpEmis (tipo de emissão) padrão do sistema: 1 = Emissão normal
    $tpEmis = '1';
    // cCT (código numérico) com 8 dígitos (total 43 dígitos sem DV)
    $codigo_aleatorio = str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT);

    $chave_sem_dv = $cUF . $ano . $mes . $cnpj . $modelo . $serie_padrao . $numero_padrao . $tpEmis . $codigo_aleatorio; // 43 dígitos

    // Cálculo de DV (mesmo padrão do documentos_fiscais_v2.php)
    $soma = 0;
    $pesos = [4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2];
    for ($i = 0; $i < 43; $i++) {
        $soma += intval($chave_sem_dv[$i]) * $pesos[$i];
    }
    $resto = $soma % 11;
    $dv = $resto < 2 ? 0 : 11 - $resto;

    return $chave_sem_dv . (string)$dv;
}

try {
    // Configurar sessão
    configure_session();
    session_start();
    
    $empresa_id = $_SESSION['empresa_id'];
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $conn = getConnection();
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
            $stmt = $conn->prepare("
                SELECT id, numero_cte, origem_cidade, destino_cidade, data_emissao, valor_total, status
                FROM fiscal_cte
                WHERE empresa_id = ?
                ORDER BY data_emissao DESC, id DESC
                LIMIT ?
            ");
            $stmt->execute([$empresa_id, $limit]);
            $cte_list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de CT-e carregada',
                'data' => $cte_list,
                'total' => count($cte_list)
            ]);
            break;
            
        case 'emitir':
            http_response_code(410);
            echo json_encode([
                'success' => false,
                'deprecated' => true,
                'message' => 'Fluxo legado descontinuado. Use documentos_fiscais_v2.php: criar_cte + emitir_cte_sefaz (XML layout 4.00).',
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
