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
            $numero_cte = $_POST['numero_cte'] ?? '';
            $serie_cte = $_POST['serie_cte'] ?? '1';
            $data_emissao = $_POST['data_emissao'] ?? date('Y-m-d');
            $tipo_servico = $_POST['tipo_servico'] ?? 'normal';
            $natureza_operacao = $_POST['natureza_operacao'] ?? '';
            $valor_total = $_POST['valor_total'] ?? 0.00;
            $peso_total = $_POST['peso_total'] ?? 0.00;
            $origem_estado = $_POST['origem_estado'] ?? null;
            $origem_cidade = $_POST['origem_cidade'] ?? null;
            $destino_estado = $_POST['destino_estado'] ?? null;
            $destino_cidade = $_POST['destino_cidade'] ?? null;
            $observacoes = $_POST['observacoes'] ?? '';

            if ($numero_cte === '') {
                throw new Exception('numero_cte é obrigatório');
            }
            if ($natureza_operacao === '') {
                throw new Exception('natureza_operacao é obrigatório');
            }

            $conn = getConnection();

            // Montar chave e salvar no CT-e antes de emitir
            $chave_acesso = gerarChaveAcessoCTe($conn, (int)$empresa_id, (string)$numero_cte, (string)$serie_cte);

            $stmt = $conn->prepare("
                INSERT INTO fiscal_cte (
                    empresa_id, numero_cte, serie_cte, chave_acesso, data_emissao,
                    tipo_servico, natureza_operacao, protocolo_autorizacao, status,
                    valor_total, peso_total,
                    origem_estado, origem_cidade,
                    destino_estado, destino_cidade,
                    observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $empresa_id,
                $numero_cte,
                $serie_cte,
                $chave_acesso,
                $data_emissao,
                $tipo_servico,
                $natureza_operacao,
                null,
                'pendente',
                (float)$valor_total,
                (float)$peso_total,
                $origem_estado,
                $origem_cidade,
                $destino_estado,
                $destino_cidade,
                $observacoes
            ]);

            $cte_id = (int)$conn->lastInsertId();

            // Criar itens mínimos do CT-e (tomador/impostos/defaults) para montar XML.
            // O modal atual não coleta tomador; então usamos a própria empresa como tomador (mínimo).
            try {
                $stmtEmp = $conn->prepare("SELECT * FROM empresa_clientes WHERE id = ? LIMIT 1");
                $stmtEmp->execute([$empresa_id]);
                $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: [];

                $tomador_cnpj = preg_replace('/\D/', '', (string)($empresa['cnpj'] ?? ''));
                $tomador_nome = trim((string)($empresa['razao_social'] ?? ($empresa['nome_fantasia'] ?? '')));

                $icms_picms = 12.00;
                $valor_prestacao = (float)$valor_total;
                $icms_vbc = $valor_prestacao;
                $icms_vicms = round($valor_prestacao * ($icms_picms / 100), 2);

                $stmtInsItens = $conn->prepare("
                    INSERT INTO fiscal_cte_itens (
                        cte_id, tomador_cnpj, tomador_nome,
                        valor_prestacao, valor_receber,
                        comp_nome, comp_valor,
                        icms_cst, icms_vbc, icms_picms, icms_vicms,
                        valor_carga, produto_predominante, inf_complementar
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsItens->execute([
                    $cte_id,
                    $tomador_cnpj !== '' ? $tomador_cnpj : null,
                    $tomador_nome !== '' ? $tomador_nome : null,
                    $valor_prestacao,
                    $valor_prestacao,
                    'FRETE VALOR BASE',
                    $valor_prestacao,
                    '00',
                    $icms_vbc,
                    $icms_picms,
                    $icms_vicms,
                    (float)$peso_total,
                    'CARGA GERAL',
                    $observacoes !== '' ? $observacoes : null
                ]);
            } catch (Throwable $e) {
                // Se a tabela ainda não existir, seguimos e tentamos emitir apenas com defaults do XML.
            }

            // Emissão SEFAZ (modelo 57)
            $cteService = new CTeService((int)$empresa_id);
            $stmtFetch = $conn->prepare("SELECT * FROM fiscal_cte WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtFetch->execute([$cte_id, $empresa_id]);
            $cte = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            $stmtEmp = $conn->prepare("SELECT * FROM empresa_clientes WHERE id = ? LIMIT 1");
            $stmtEmp->execute([$empresa_id]);
            $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            if (!$empresa) {
                $empresa = ['cnpj' => '', 'razao_social' => 'Transportadora', 'nome_fantasia' => '', 'inscricao_estadual' => '', 'endereco' => '', 'cep' => '', 'cidade' => '', 'estado' => 'PR'];
            }

            $xmlProc = montarCteProcXml($conn, $cte, $empresa);
            if (empty($xmlProc)) {
                throw new Exception('Erro ao gerar XML (cteProc) do CT-e.');
            }

            $envio = $cteService->emitirCTe($xmlProc);
            if (!$envio['success']) {
                throw new Exception('Falha ao enviar CT-e para SEFAZ: ' . ($envio['message'] ?? 'erro desconhecido'));
            }

            // Salvar xml assinado (se coluna existir)
            try {
                if (!empty($envio['signed_xml'])) {
                    $upXml = $conn->prepare("UPDATE fiscal_cte SET xml_cte = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?");
                    $upXml->execute([$envio['signed_xml'], $cte_id, $empresa_id]);
                }
            } catch (Throwable $e) {}

            // Consultar para obter cStat/protocolo
            $consulta = $cteService->consultarPorChave($chave_acesso);
            $cStat = (string)((($consulta['data'] ?? [])['cStat'] ?? '') ?: '');
            $protocolo = $consulta['data']['protocolo'] ?? null;
            $status = ($cStat === '100' || $cStat === '150') ? 'autorizado' : 'pendente';

            try {
                $up = $conn->prepare("UPDATE fiscal_cte SET protocolo_autorizacao = ?, status = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?");
                $up->execute([$protocolo, $status, $cte_id, $empresa_id]);
            } catch (Throwable $e) {}

            // Se autorizado, tenta baixar e importar xml completo
            $xml_baixado = false;
            try {
                if ($status === 'autorizado') {
                    $xml_completo = $cteService->baixarXmlPorChave($chave_acesso);
                    if (!empty($xml_completo)) {
                        $xml_baixado = true;
                        importarXmlCteProc($conn, $xml_completo, $empresa_id);
                    }
                }
            } catch (Throwable $e) {}

            echo json_encode([
                'success' => true,
                'message' => $status === 'autorizado' ? 'CT-e autorizado e processado.' : 'CT-e enviado para autorização (pendente).',
                'data' => [
                    'id' => $cte_id,
                    'numero_cte' => $numero_cte,
                    'status' => $status,
                    'chave_acesso' => $chave_acesso,
                    'protocolo' => $protocolo,
                    'cStat' => $cStat,
                    'xml_baixado' => $xml_baixado,
                ]
            ]);
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
