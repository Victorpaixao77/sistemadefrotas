<?php
/**
 * 📄 API Documentos Fiscais V2 - SISTEMA DE FROTA
 * 📋 Fluxo correto: Recebe NF-e do cliente, emite CT-e e MDF-e
 * 🏷️  Compatível com tabelas existentes (fiscal_*)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Sem vendor o require em NFeService gera fatal error antes do try — vira 500 sem JSON no cliente.
$fiscalVendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (!is_readable($fiscalVendorAutoload)) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Dependências PHP (Composer) não encontradas. Na raiz do projeto (sistema-frotas), execute composer install --no-dev --optimize-autoloader ou envie a pasta vendor completa.',
        'code' => 'missing_vendor',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/NFeService.php';
require_once __DIR__ . '/../includes/NFeEmissaoBuilder.php';
require_once __DIR__ . '/../includes/CTeService.php';
require_once __DIR__ . '/../includes/MdfeService.php';
require_once __DIR__ . '/../includes/FiscalQueueService.php';
require_once __DIR__ . '/../includes/CteDebug.php';

// Configurar sessão
configure_session();
session_start();

// CSRF: se o cliente enviar X-CSRF-Token ou POST csrf_token, valida em mutações (NF-e / CT-e / MDF-e).
$csrfTok = null;
if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrfTok = $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (!empty($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
    $csrfTok = $_POST['csrf_token'];
}
if ($csrfTok !== null && $csrfTok !== '') {
    require_once __DIR__ . '/../../includes/csrf.php';
    require_once __DIR__ . '/../../includes/api_json.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !csrf_token_validate($csrfTok)) {
        api_json_error('Token CSRF inválido. Recarregue a página.', 403, 'csrf_invalid');
    }
}

// Verificar autenticação
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

require_once __DIR__ . '/../../includes/rate_limit.php';

if (!function_exists('fiscal_api_rate_limit_or_json_429')) {
    /**
     * Limita abuso de endpoints fiscais (emissão, SEFAZ, criação).
     */
    function fiscal_api_rate_limit_or_json_429(string $bucket, int $maxAttempts, int $windowSeconds, ?string $errorMessage = null): void
    {
        if (!sf_rate_limit_allow($bucket, $maxAttempts, $windowSeconds)) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            $msg = $errorMessage ?? 'Muitas requisições. Aguarde alguns instantes e tente novamente.';
            echo json_encode([
                'success' => false,
                'error' => $msg,
                'message' => $msg,
                'code' => 'rate_limited',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Função para obter próximo número de documento
function getProximoNumero($tipo_documento, $serie = '1') {
    global $conn, $empresa_id;
    
    $ano_atual = date('Y');
    
    // Verificar se já existe sequência para este ano
    $stmt = $conn->prepare("
        SELECT proximo_numero FROM sequencias_documentos 
        WHERE empresa_id = ? AND tipo_documento = ? AND serie = ? AND ano_exercicio = ?
    ");
    $stmt->execute([$empresa_id, $tipo_documento, $serie, $ano_atual]);
    $sequencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sequencia) {
        // Atualizar próximo número
        $proximo = $sequencia['proximo_numero'];
        $stmt = $conn->prepare("
            UPDATE sequencias_documentos 
            SET proximo_numero = proximo_numero + 1, ultimo_numero = ? 
            WHERE empresa_id = ? AND tipo_documento = ? AND serie = ? AND ano_exercicio = ?
        ");
        $stmt->execute([$proximo, $empresa_id, $tipo_documento, $serie, $ano_atual]);
        
        return $proximo;
    } else {
        // Criar nova sequência
        $stmt = $conn->prepare("
            INSERT INTO sequencias_documentos (empresa_id, tipo_documento, serie, ultimo_numero, proximo_numero, ano_exercicio)
            VALUES (?, ?, ?, 1, 2, ?)
        ");
        $stmt->execute([$empresa_id, $tipo_documento, $serie, $ano_atual]);
        
        return 1;
    }
}

// Função para gerar chave de acesso (baseada na config da empresa)
function gerarChaveAcesso($tipo_documento, $numero, $serie) {
    global $conn, $empresa_id;

    $ano = date('y');
    $mes = date('m');

    // UF numérico (cUF) para chave de acesso. Mapeamento oficial (UF => cUF).
    $mapa_cUF = [
        'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15',
        'AP' => '16', 'TO' => '17', 'MA' => '21', 'PI' => '22', 'CE' => '23',
        'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27', 'SE' => '28',
        'BA' => '29', 'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35',
        'PR' => '41', 'SC' => '42', 'RS' => '43', 'MS' => '50', 'MT' => '51',
        'GO' => '52', 'DF' => '53'
    ];

    // Buscar CNPJ e UF a partir da configuração fiscal da empresa.
    $cnpj = '';
    $codigo_municipio = '';
    $siglaUF = '';
    try {
        $stmt = $conn->prepare("SELECT cnpj, codigo_municipio FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1");
        $stmt->execute([$empresa_id]);
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnpj = (string)($cfg['cnpj'] ?? '');
        $codigo_municipio = (string)($cfg['codigo_municipio'] ?? '');
    } catch (Throwable $e) {}

    $cnpj = preg_replace('/\D/', '', $cnpj);
    // Garantir tamanho 14 dígitos no CNPJ da chave.
    if (strlen($cnpj) < 14) $cnpj = str_pad($cnpj, 14, '0', STR_PAD_LEFT);
    $cnpj = substr($cnpj, 0, 14);

    // Descobrir UF (sigla) pelo código do município (IBGE) quando disponível.
    if (!empty($codigo_municipio)) {
        try {
            $stmtUf = $conn->prepare("SELECT uf FROM cidades WHERE codigo_ibge = :ibge LIMIT 1");
            $stmtUf->execute([':ibge' => $codigo_municipio]);
            $siglaUF = (string)($stmtUf->fetchColumn() ?? '');
        } catch (Throwable $e) {}
    }

    $cUF = $mapa_cUF[$siglaUF] ?? '43'; // fallback RS
    $modelo = $tipo_documento === 'CTE' ? '57' : '58'; // CT-e ou MDF-e
    $serie_padrao = str_pad($serie, 3, '0', STR_PAD_LEFT);
    $numero_padrao = str_pad($numero, 9, '0', STR_PAD_LEFT);
    // tpEmis (tipo de emissão) padrão do sistema: 1 = Emissão normal
    $tpEmis = '1';
    // cCT/cMDf (código numérico) precisa ter 8 dígitos para totalizar 44 dígitos na chave
    $codigo_aleatorio = str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT);

    // Sem DV (43 dígitos): cUF2 + AAMM4 + CNPJ14 + mod2 + serie3 + num9 + tpEmis1 + cNum8
    $chave = $cUF . $ano . $mes . $cnpj . $modelo . $serie_padrao . $numero_padrao . $tpEmis . $codigo_aleatorio;
    
    // Calcular dígito verificador (simplificado)
    $soma = 0;
    $pesos = [4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2];
    
    // A chave sem DV tem 43 dígitos
    for ($i = 0; $i < 43; $i++) {
        $soma += intval($chave[$i]) * $pesos[$i];
    }
    
    $resto = $soma % 11;
    $dv = $resto < 2 ? 0 : 11 - $resto;
    
    return $chave . $dv;
}

/**
 * Grava itens da NF-e em fiscal_nfe_itens a partir do XML (notas recebidas/consultadas).
 * Estrutura mínima NF-e 55: qualquer tipo (combustível, peças, pneus, material).
 * Impostos, ANP (combustível) e infCpl (frota) quando existirem no XML.
 */
function salvarItensNFeDoXml($conn, $nfe_id, $xml_content) {
    if (empty($xml_content)) return;
    try {
        $xml = @simplexml_load_string($xml_content);
        if (!$xml) return;
        $ns = 'http://www.portalfiscal.inf.br/nfe';
        $nfe_node = null;
        if (isset($xml->NFe)) $nfe_node = $xml->NFe;
        elseif (isset($xml->nfeProc->NFe)) $nfe_node = $xml->nfeProc->NFe;
        if (!$nfe_node && isset($xml->children($ns)->NFe)) $nfe_node = $xml->children($ns)->NFe;
        if (!$nfe_node) return;
        $inf = $nfe_node->infNFe ?? $nfe_node->children($ns)->infNFe ?? null;
        if (!$inf) return;

        $infCpl = (string)($inf->infCpl ?? $inf->children($ns)->infCpl ?? '');
        $placa = null;
        $motorista_nome = null;
        $motorista_cpf = null;
        $km_veiculo = null;
        if (preg_match('/Placa[:\s]*([A-Z]{3}[-]?[0-9A-Z]{4})/i', $infCpl, $m)) $placa = preg_replace('/[^A-Z0-9]/', '', strtoupper($m[1]));
        if (preg_match('/Motorista[:\s]*([^\n\r]+?)(?:\s+CPF|$)/i', $infCpl, $m)) $motorista_nome = trim($m[1]);
        if (preg_match('/CPF[:\s]*[\d.\-]+[\s]*([\d.\-]{11,14})/i', $infCpl, $m)) $motorista_cpf = preg_replace('/\D/', '', $m[1]);
        if (preg_match('/\bKM[:\s]*(\d+)/i', $infCpl, $m)) $km_veiculo = (int)$m[1];

        $inf->registerXPathNamespace('nfe', $ns);
        $dets = @$inf->xpath('nfe:det');
        if (!is_array($dets) || count($dets) === 0) return;

        $conn->prepare("DELETE FROM fiscal_nfe_itens WHERE nfe_id = ?")->execute([$nfe_id]);

        $sql = "INSERT INTO fiscal_nfe_itens (
            nfe_id, numero_item_nfe, gtin, codigo_produto, descricao_produto, ncm, cest, cfop,
            unidade_comercial, quantidade_comercial, valor_unitario, valor_total_item,
            peso_bruto, peso_liquido,
            cst_icms, cst_pis, cst_cofins, cst_ipi,
            valor_icms, valor_icms_st, valor_ipi, valor_pis, valor_cofins, valor_total_tributos,
            valor_desconto, valor_frete, valor_seguro, valor_outros,
            informacao_adicional_item,
            anp_codigo, anp_descricao, percentual_biodiesel, uf_consumo,
            icms_monofasico_valor, icms_monofasico_aliquota_adrem,
            placa, motorista_nome, motorista_cpf, km_veiculo
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?
        )";

        $stmt = $conn->prepare($sql);
        foreach ($dets as $det) {
            $prod = $det->prod ?? $det->children($ns)->prod ?? null;
            if (!$prod) continue;

            $nItem = isset($det['nItem']) ? (int)(string)$det['nItem'] : 0;
            $codigo = (string)($prod->cProd ?? '');
            $descricao = (string)($prod->xProd ?? '');
            $ncm = (string)($prod->NCM ?? '');
            $cest = (string)($prod->CEST ?? '');
            $cfop = (string)($prod->CFOP ?? '');
            $gtin = (string)($prod->cEAN ?? $prod->cEANTrib ?? '');
            if (strtoupper($gtin) === 'SEM GTIN') $gtin = '';
            $unidade = (string)($prod->uCom ?? 'UN');
            $qtde = (float)($prod->qCom ?? 0);
            $vlr_unit = (float)($prod->vUnCom ?? 0);
            $vlr_total = (float)($prod->vProd ?? 0);
            $peso_bruto = isset($prod->pesoB) ? (float)$prod->pesoB : null;
            $peso_liq = isset($prod->pesoL) ? (float)$prod->pesoL : null;
            $vDesc = isset($prod->vDesc) ? (float)$prod->vDesc : null;
            $vFrete = isset($prod->vFrete) ? (float)$prod->vFrete : null;
            $vSeg = isset($prod->vSeg) ? (float)$prod->vSeg : null;
            $vOutro = isset($prod->vOutro) ? (float)$prod->vOutro : null;
            $infAdProd = isset($prod->infAdProd) ? (string)$prod->infAdProd : (isset($det->infAdProd) ? (string)$det->infAdProd : null);

            $imposto = $det->imposto ?? $det->children($ns)->imposto ?? null;
            $cst_icms = $cst_pis = $cst_cofins = $cst_ipi = null;
            $vICMS = $vICMSST = $vIPI = $vPIS = $vCOFINS = $vTotTrib = null;
            $vICMSMonoRet = $adRemICMSRet = null;
            if ($imposto) {
                $vTotTrib = isset($imposto->vTotTrib) ? (float)$imposto->vTotTrib : null;
                $icms = $imposto->ICMS ?? $imposto->children($ns)->ICMS ?? null;
                if ($icms) {
                    $icmsChild = null;
                    foreach ($icms->children($ns) as $c) { $icmsChild = $c; break; }
                    if (!$icmsChild && $icms->children()) { $arr = $icms->children(); $icmsChild = $arr[0] ?? null; }
                    if ($icmsChild) {
                        $cst_icms = (string)($icmsChild->CST ?? $icmsChild->CSOSN ?? '');
                        $vICMS = isset($icmsChild->vICMS) ? (float)$icmsChild->vICMS : null;
                        $vICMSST = isset($icmsChild->vICMSST) ? (float)$icmsChild->vICMSST : (isset($icmsChild->vST) ? (float)$icmsChild->vST : null);
                        $vICMSMonoRet = isset($icmsChild->vICMSMonoRet) ? (float)$icmsChild->vICMSMonoRet : null;
                        $adRemICMSRet = isset($icmsChild->adRemICMSRet) ? (float)$icmsChild->adRemICMSRet : null;
                    }
                }
                $pis = $imposto->PIS ?? $imposto->children($ns)->PIS ?? null;
                if ($pis) {
                    $pisChild = null;
                    foreach ($pis->children($ns) as $c) { $pisChild = $c; break; }
                    if (!$pisChild && $pis->children()) { $arr = $pis->children(); $pisChild = $arr[0] ?? null; }
                    if ($pisChild) {
                        $cst_pis = (string)($pisChild->CST ?? $pisChild->CSLL ?? '');
                        $vPIS = isset($pisChild->vPIS) ? (float)$pisChild->vPIS : null;
                    }
                }
                $cofins = $imposto->COFINS ?? $imposto->children($ns)->COFINS ?? null;
                if ($cofins) {
                    $cofChild = null;
                    foreach ($cofins->children($ns) as $c) { $cofChild = $c; break; }
                    if (!$cofChild && $cofins->children()) { $arr = $cofins->children(); $cofChild = $arr[0] ?? null; }
                    if ($cofChild) {
                        $cst_cofins = (string)($cofChild->CST ?? $cofChild->CSLL ?? '');
                        $vCOFINS = isset($cofChild->vCOFINS) ? (float)$cofChild->vCOFINS : null;
                    }
                }
                $ipi = $imposto->IPI ?? $imposto->children($ns)->IPI ?? null;
                if ($ipi) {
                    $ipiChild = $ipi->IPITrib ?? $ipi->IPINT ?? null;
                    if ($ipiChild) {
                        $cst_ipi = (string)($ipiChild->CST ?? '');
                        $vIPI = isset($ipiChild->vIPI) ? (float)$ipiChild->vIPI : null;
                    }
                }
            }

            $anp_codigo = $anp_descricao = $pBio = $UFCons = null;
            $comb = $prod->comb ?? $prod->children($ns)->comb ?? null;
            if ($comb) {
                $anp_codigo = (string)($comb->cProdANP ?? '');
                $anp_descricao = (string)($comb->descANP ?? '');
                $pBio = isset($comb->pBio) ? (float)$comb->pBio : null;
                $UFCons = (string)($comb->UFCons ?? '');
            }

            if ($descricao === '' && $vlr_total == 0) continue;

            $stmt->execute([
                $nfe_id, $nItem ?: null, $gtin ?: null, $codigo ?: null, $descricao ?: ' ', $ncm ?: null, $cest ?: null, $cfop ?: null,
                $unidade ?: 'UN', $qtde, $vlr_unit, $vlr_total,
                $peso_bruto, $peso_liq,
                $cst_icms ?: null, $cst_pis ?: null, $cst_cofins ?: null, $cst_ipi ?: null,
                $vICMS, $vICMSST, $vIPI, $vPIS, $vCOFINS, $vTotTrib,
                $vDesc, $vFrete, $vSeg, $vOutro,
                $infAdProd,
                $anp_codigo ?: null, $anp_descricao ?: null, $pBio, $UFCons ?: null,
                $vICMSMonoRet, $adRemICMSRet,
                $placa, $motorista_nome, $motorista_cpf ?: null, $km_veiculo ?: null
            ]);
        }
    } catch (Throwable $e) {
        // Fallback: colunas novas podem não existir ainda; grava só campos originais
        try {
            $xml = @simplexml_load_string($xml_content);
            if (!$xml) return;
            $ns = 'http://www.portalfiscal.inf.br/nfe';
            $nfe_node = isset($xml->NFe) ? $xml->NFe : (isset($xml->nfeProc->NFe) ? $xml->nfeProc->NFe : null);
            if (!$nfe_node) return;
            $inf = $nfe_node->infNFe ?? $nfe_node->children($ns)->infNFe ?? null;
            if (!$inf) return;
            $inf->registerXPathNamespace('nfe', $ns);
            $dets = @$inf->xpath('nfe:det');
            if (!is_array($dets) || count($dets) === 0) return;
            $conn->prepare("DELETE FROM fiscal_nfe_itens WHERE nfe_id = ?")->execute([$nfe_id]);
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_itens (nfe_id, codigo_produto, descricao_produto, ncm, cfop, unidade_comercial, quantidade_comercial, valor_unitario, valor_total_item, peso_bruto, peso_liquido)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($dets as $det) {
                $prod = $det->prod ?? $det->children($ns)->prod ?? null;
                if (!$prod) continue;
                $codigo = (string)($prod->cProd ?? '');
                $descricao = (string)($prod->xProd ?? '');
                $ncm = (string)($prod->NCM ?? '');
                $cfop = (string)($prod->CFOP ?? '');
                $unidade = (string)($prod->uCom ?? 'UN');
                $qtde = (float)($prod->qCom ?? 0);
                $vlr_unit = (float)($prod->vUnCom ?? 0);
                $vlr_total = (float)($prod->vProd ?? 0);
                $peso_bruto = isset($prod->pesoB) ? (float)$prod->pesoB : null;
                $peso_liq = isset($prod->pesoL) ? (float)$prod->pesoL : null;
                if ($descricao === '' && $vlr_total == 0) continue;
                $stmt->execute([$nfe_id, $codigo ?: null, $descricao ?: ' ', $ncm ?: null, $cfop ?: null, $unidade ?: 'UN', $qtde, $vlr_unit, $vlr_total, $peso_bruto, $peso_liq]);
            }
        } catch (Throwable $e2) {}
    }
}

/**
 * Extrai peso total (pesoB) e quantidade de volumes a partir de transp/vol (vários vol somados).
 */
function fiscal_nfe_agregar_transp_vol(?SimpleXMLElement $transp): array
{
    $peso = 0.0;
    $qVolTotal = 0;
    if (!$transp) {
        return [$peso, 1];
    }
    $vols = @$transp->xpath('.//*[local-name()="vol"]');
    if (!is_array($vols) || count($vols) === 0) {
        return [$peso, 1];
    }
    foreach ($vols as $child) {
        $qv = (string)($child->qVol ?? '');
        if ($qv !== '' && is_numeric($qv)) {
            $qVolTotal += (int)max(1, (float)$qv);
        } else {
            $qVolTotal += 1;
        }
        $peso += (float)($child->pesoB ?? 0);
    }
    return [$peso, max(1, $qVolTotal)];
}

/**
 * Aplica XML (NFe ou nfeProc) em fiscal_nfe_clientes — só colunas que existem na tabela — e regrava itens.
 */
function fiscal_aplicarXmlNfeRecebidaNoBanco(PDO $conn, int $empresa_id, int $nfe_id, string $xml_content, bool $promoverStatusRecebida = false): bool
{
    static $colCache = null;
    if ($colCache === null) {
        $colCache = [];
        try {
            $q = $conn->query('SHOW COLUMNS FROM fiscal_nfe_clientes');
            while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                $colCache[$r['Field']] = true;
            }
        } catch (Throwable $e) {
            $colCache = [];
        }
    }
    if (trim($xml_content) === '') {
        return false;
    }
    $xml = @simplexml_load_string($xml_content);
    if (!$xml) {
        return false;
    }
    $ns = 'http://www.portalfiscal.inf.br/nfe';
    $nfe_node = null;
    if (isset($xml->NFe)) {
        $nfe_node = $xml->NFe;
    } elseif (isset($xml->nfeProc->NFe)) {
        $nfe_node = $xml->nfeProc->NFe;
    }
    if (!$nfe_node && isset($xml->children($ns)->NFe)) {
        $nfe_node = $xml->children($ns)->NFe;
    }
    if (!$nfe_node) {
        return false;
    }
    $inf = $nfe_node->infNFe ?? $nfe_node->children($ns)->infNFe ?? null;
    if (!$inf) {
        return false;
    }

    $ide = $inf->ide ?? $inf->children($ns)->ide ?? null;
    $emit = $inf->emit ?? $inf->children($ns)->emit ?? null;
    $dest = $inf->dest ?? $inf->children($ns)->dest ?? null;
    $total = $inf->total ?? $inf->children($ns)->total ?? null;
    $transp = $inf->transp ?? $inf->children($ns)->transp ?? null;

    $numero_nfe = $ide ? (string)($ide->nNF ?? '') : '';
    $serie_nfe = $ide ? (string)($ide->serie ?? '') : '';
    $dh = $ide && isset($ide->dhEmi) ? (string)$ide->dhEmi : '';
    $data_emissao = $dh !== '' ? date('Y-m-d', strtotime($dh)) : date('Y-m-d');

    $emitente = $emit ? (string)($emit->xNome ?? '') : '';
    $cnpj_emitente = $emit ? (string)($emit->CNPJ ?? '') : '';
    if ($cnpj_emitente === '' && $emit) {
        $cnpj_emitente = (string)($emit->CPF ?? '');
    }
    $emit_fant = $emit ? (string)($emit->xFant ?? '') : '';

    $destinatario = $dest ? (string)($dest->xNome ?? '') : '';
    $doc_dest = $dest ? (string)($dest->CNPJ ?? '') : '';
    if ($doc_dest === '' && $dest) {
        $doc_dest = (string)($dest->CPF ?? '');
    }

    $valor_total = 0.0;
    if ($total) {
        $icmsTot = $total->ICMSTot ?? $total->children($ns)->ICMSTot ?? null;
        if ($icmsTot) {
            $valor_total = (float)($icmsTot->vNF ?? 0);
        }
    }

    [$peso_carga, $volumes] = fiscal_nfe_agregar_transp_vol($transp);

    $idAttr = '';
    if (isset($inf['Id'])) {
        $idAttr = (string)$inf['Id'];
    } else {
        foreach ($inf->attributes() as $k => $v) {
            if (strcasecmp((string)$k, 'Id') === 0) {
                $idAttr = (string)$v;
                break;
            }
        }
    }
    $chave_xml = preg_replace('/^NFe/i', '', $idAttr);
    $chave_xml = preg_replace('/\D/', '', $chave_xml);

    $nProt = '';
    $protNodes = $xml->xpath('//*[local-name()="protNFe"]/*[local-name()="infProt"]/*[local-name()="nProt"]');
    if ($protNodes && isset($protNodes[0])) {
        $nProt = trim((string)$protNodes[0]);
    }

    $xmlCompleto = stripos($xml_content, 'nfeProc') !== false && stripos($xml_content, 'protNFe') !== false;

    $sets = [];
    $params = [];
    $set = function (string $col, $val) use (&$sets, &$params, $colCache) {
        if (empty($colCache[$col])) {
            return;
        }
        $sets[] = "`$col` = ?";
        $params[] = $val;
    };

    if ($numero_nfe !== '') {
        $set('numero_nfe', $numero_nfe);
    }
    $set('serie_nfe', $serie_nfe !== '' ? $serie_nfe : null);
    if (strlen($chave_xml) === 44) {
        $set('chave_acesso', $chave_xml);
    }
    $set('data_emissao', $data_emissao);
    $set('cliente_razao_social', $emitente !== '' ? $emitente : null);
    $set('cliente_cnpj', $cnpj_emitente !== '' ? preg_replace('/\D/', '', $cnpj_emitente) : null);
    $set('cliente_nome_fantasia', $emit_fant !== '' ? $emit_fant : null);
    $set('cliente_destinatario', $destinatario !== '' ? $destinatario : null);
    $set('cnpj_destinatario', $doc_dest !== '' ? preg_replace('/\D/', '', $doc_dest) : null);
    $set('valor_total', $valor_total);
    $set('peso_carga', $peso_carga);
    $set('volumes', $volumes);
    $set('protocolo_autorizacao', $nProt !== '' ? $nProt : null);
    $set('xml_nfe', $xml_content);

    if ($promoverStatusRecebida && $xmlCompleto && !empty($colCache['status'])) {
        $sets[] = "`status` = 'recebida'";
    }

    if (!empty($sets)) {
        $sets[] = '`updated_at` = NOW()';
        $params[] = $nfe_id;
        $params[] = $empresa_id;
        $sql = 'UPDATE fiscal_nfe_clientes SET ' . implode(', ', $sets) . ' WHERE id = ? AND empresa_id = ?';
        try {
            $conn->prepare($sql)->execute($params);
        } catch (Throwable $e) {
            error_log('fiscal_aplicarXmlNfeRecebidaNoBanco UPDATE: ' . $e->getMessage());
        }
    }

    salvarItensNFeDoXml($conn, $nfe_id, $xml_content);
    return true;
}

// Função para validar CNPJ
function validarCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    $pesos = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $pesos[$i];
    }
    
    $resto = $soma % 11;
    $dv1 = $resto < 2 ? 0 : 11 - $resto;
    
    if ($cnpj[12] != $dv1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $pesos[$i];
    }
    
    $resto = $soma % 11;
    $dv2 = $resto < 2 ? 0 : 11 - $resto;
    
    return $cnpj[13] == $dv2;
}

// Função para verificar duplicação de CT-e
function verificarDuplicacaoCTE($numero_cte, $serie_cte, $id_atual, $empresa_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM fiscal_cte 
        WHERE numero_cte = ? AND serie_cte = ? AND empresa_id = ? AND id != ?
    ");
    $stmt->execute([$numero_cte, $serie_cte, $empresa_id, $id_atual]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $resultado['total'] == 0;
}

/**
 * Validação automática antes de emitir MDF-e (evitar rejeição SEFAZ).
 * Retorna true se válido, ou string com mensagem de erro.
 */
function validarMDFe($conn, $empresa_id, $mdfe_id) {
    $stmt = $conn->prepare("
        SELECT m.id, m.uf_inicio, m.uf_fim, m.veiculo_id, m.motorista_id,
               v.placa AS veiculo_placa,
               mtr.cpf AS motorista_cpf,
               m.status AS mdfe_status
        FROM fiscal_mdfe m
        LEFT JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
        LEFT JOIN motoristas mtr ON mtr.id = m.motorista_id AND mtr.empresa_id = m.empresa_id
        WHERE m.id = ? AND m.empresa_id = ?
    ");
    $stmt->execute([$mdfe_id, $empresa_id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dados) {
        return 'MDF-e não encontrado.';
    }
    $rawStatus = (string)($dados['mdfe_status'] ?? '');
    // Alguns bancos/integrações podem retornar espaço invisível (NBSP) ou null.
    $mdfeStatus = strtolower(trim(str_replace("\xC2\xA0", ' ', $rawStatus)));
    $mdfeStatus = preg_replace('/\s+/u', ' ', (string)$mdfeStatus);

    if ($mdfeStatus === '') {
        // Se vier vazio no banco, não bloqueamos o envio.
        $mdfeStatus = 'pendente';
    }

    if ($mdfeStatus !== 'rascunho' && $mdfeStatus !== 'pendente') {
        return 'Apenas documentos com status rascunho ou pendente podem ser enviados para SEFAZ';
    }
    if (empty($dados['veiculo_placa'])) {
        return 'Veículo não informado ou inválido.';
    }
    $cpf = preg_replace('/\D/', '', $dados['motorista_cpf'] ?? '');
    if (empty($cpf) || strlen($cpf) != 11) {
        return 'CPF do motorista inválido ou não informado (deve ter 11 dígitos).';
    }
    if (empty($dados['uf_inicio']) || empty($dados['uf_fim'])) {
        return 'UF de início ou fim não informada.';
    }
    $stmt = $conn->prepare("
        SELECT c.chave_acesso
        FROM fiscal_mdfe_cte mc
        JOIN fiscal_cte c ON c.id = mc.cte_id AND c.empresa_id = ?
        WHERE mc.mdfe_id = ?
    ");
    $stmt->execute([$empresa_id, $mdfe_id]);
    $chaves = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($chaves)) {
        return 'Nenhum CT-e vinculado ao MDF-e.';
    }
    foreach ($chaves as $chave) {
        if (strlen($chave) != 44 || !ctype_digit($chave)) {
            return 'Chave de CT-e inválida (deve ter 44 dígitos): ' . substr($chave ?? '', 0, 20) . '...';
        }
    }
    // RNTRC (opcional: só valida se a coluna existir em fiscal_config_empresa)
    try {
        $stmt = $conn->prepare("SELECT rntrc FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1");
        $stmt->execute([$empresa_id]);
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cfg && (trim($cfg['rntrc'] ?? '') === '')) {
            return 'RNTRC não cadastrado. Cadastre em Configurações Fiscais da empresa.';
        }
    } catch (Exception $e) {
        // Coluna rntrc pode não existir; ignorar
    }
    // MDF-e aberto para o mesmo veículo (autorizado, ainda não encerrado)
    $stmt = $conn->prepare("
        SELECT m.id FROM fiscal_mdfe m
        JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
        WHERE v.placa = ? AND m.empresa_id = ? AND m.id != ?
        AND m.status = 'autorizado'
    ");
    $stmt->execute([$dados['veiculo_placa'], $empresa_id, $mdfe_id]);
    if ($stmt->fetch()) {
        return 'Já existe MDF-e autorizado em aberto para o veículo ' . $dados['veiculo_placa'] . '. Encerre-o antes de emitir outro.';
    }
    // Chave de CT-e já vinculada a outro MDF-e aberto
    foreach ($chaves as $chave) {
        $stmt = $conn->prepare("
            SELECT m.id FROM fiscal_mdfe m
            JOIN fiscal_mdfe_cte mc ON mc.mdfe_id = m.id
            JOIN fiscal_cte c ON c.id = mc.cte_id AND c.chave_acesso = ? AND c.empresa_id = m.empresa_id
            WHERE m.empresa_id = ? AND m.id != ? AND m.status = 'autorizado'
        ");
        $stmt->execute([$chave, $empresa_id, $mdfe_id]);
        if ($stmt->fetch()) {
            return 'A chave do CT-e já está vinculada a outro MDF-e em aberto.';
        }
    }
    return true;
}

// Envio para SEFAZ
function enviarParaSefaz($documento, $tipo) {
    global $empresa_id;

    if ($tipo === 'mdfe') {
        $mdfeService = new MdfeService((int) $empresa_id);
        return $mdfeService->emitir((array) $documento);
    }

    if ($tipo === 'cte') {
        return [
            'sucesso' => false,
            'erro' => 'Use a ação emitir_cte_sefaz para CT-e (fluxo dedicado com sped-cte).',
        ];
    }

    return [
        'sucesso' => false,
        'erro' => 'Tipo de documento não suportado para envio SEFAZ.',
    ];
}

function erroTemporarioSefaz(string $mensagem): bool
{
    $m = mb_strtolower($mensagem, 'UTF-8');
    $padroes = [
        'timeout', 'timed out', 'tempor', 'indispon', 'falha na conex',
        'erro de conex', 'connection', 'soap', 'http 5', 'lote recebido',
        'pendente', 'aguarde', 'processamento'
    ];
    foreach ($padroes as $p) {
        if (strpos($m, $p) !== false) {
            return true;
        }
    }
    return false;
}

// Função para log de operações
function logOperacao($tipo_operacao, $descricao, $status = 'sucesso', $documento_id = null, $dados_entrada = null, $dados_saida = null) {
    global $conn, $empresa_id;
    
    try {
        // Se documento_id for null, usar 0 para operações sem documento específico
        $doc_id = $documento_id ?? 0;
        
        $stmt = $conn->prepare("
            INSERT INTO fiscal_logs (empresa_id, documento_tipo, documento_id, acao, status, mensagem, detalhes, usuario_id, ip_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $documento_tipo = 'cte'; // Padrão para sistema de frota
        $usuario_id = $_SESSION['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt->execute([
            $empresa_id, $documento_tipo, $doc_id, $tipo_operacao, $status, $descricao,
            json_encode(['entrada' => $dados_entrada, 'saida' => $dados_saida]),
            $usuario_id, $ip
        ]);
    } catch (Exception $e) {
        // Se não conseguir logar, não falhar a operação principal
        error_log("Erro ao logar operação fiscal: " . $e->getMessage());
    }
}

/**
 * Validar se evento NF-e é permitido para o documento (chamado antes do INSERT do evento).
 */
function validarEventoPermitidoNfe($documento, $tipo_evento) {
    if ($tipo_evento === 'inutilizacao') {
        return true;
    }

    $status = (string)($documento['status'] ?? '');

    switch ($tipo_evento) {
        case 'cancelamento':
            return in_array($status, ['recebida', 'validada', 'autorizada', 'autorizado', 'consultada_sefaz', 'pendente'], true);

        case 'cce':
            return in_array($status, ['recebida', 'validada', 'autorizada', 'autorizado', 'consultada_sefaz', 'pendente'], true);

        case 'manifestacao':
            return in_array($status, ['recebida', 'validada', 'consultada_sefaz', 'pendente', 'em_transporte'], true);

        case 'encerramento':
            return in_array($status, ['em_transporte', 'autorizada', 'autorizado'], true);

        default:
            return false;
    }
}

/**
 * Envia evento NF-e à SEFAZ e atualiza fiscal_eventos_fiscais / documento quando aplicável.
 * Deve estar definida antes do switch (evita "undefined function" com funções após o case).
 */
function processarEventoEspecificoNfe($evento_id, $tipo_evento, $documento_id, $justificativa, $documento = null) {
    global $conn, $empresa_id;

    try {
        $nfeService = new NFeService((int)$empresa_id);
    } catch (Throwable $e) {
        $msg = 'Certificado/config fiscal: ' . $e->getMessage();
        $stmt = $conn->prepare("UPDATE fiscal_eventos_fiscais SET status = 'rejeitado', observacoes = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$msg, $evento_id, $empresa_id]);
        return ['sucesso' => false, 'erro' => $msg];
    }

    $post = $_POST ?? [];
    $correcao = trim((string)($post['correcao'] ?? $post['cce_texto'] ?? $justificativa));
    $nSeq = max(1, (int)($post['n_seq_evento'] ?? 1));
    $manifestTipo = strtolower((string)($post['manifestacao_tipo'] ?? 'ciencia'));

    $atualizarEvento = function (string $statusEv, ?string $xmlRet, ?string $prot, string $obs = '') use ($conn, $evento_id, $empresa_id) {
        $stmt = $conn->prepare("
            UPDATE fiscal_eventos_fiscais
            SET status = ?, xml_retorno = ?, protocolo_evento = ?, observacoes = NULLIF(?, ''), data_processamento = NOW()
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$statusEv, $xmlRet, $prot, $obs, $evento_id, $empresa_id]);
    };

    if ($tipo_evento === 'inutilizacao') {
        $serie = (int)($post['serie'] ?? 0);
        $nIni = (int)($post['n_ini'] ?? $post['nIni'] ?? 0);
        $nFin = (int)($post['n_fin'] ?? $post['nFin'] ?? 0);
        $ano = isset($post['ano']) ? (string)$post['ano'] : null;
        $xJust = trim((string)($post['justificativa_inutil'] ?? $justificativa));
        if ($serie < 0 || $nIni < 1 || $nFin < 1 || $xJust === '') {
            $erro = 'Inutilização: informe serie, n_ini, n_fin e justificativa.';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }

        $resp = $nfeService->inutilizarNumeracao($serie, $nIni, $nFin, $xJust, null, $ano);
        $xmlRet = (string)($resp['response_xml'] ?? '');
        if (!empty($resp['success'])) {
            $prot = $resp['nProt_inut'] ?? null;
            $atualizarEvento('aceito', $xmlRet ?: null, $prot, 'Inutilização homologada.');
            return [
                'sucesso' => true,
                'mensagem' => $resp['message'] ?? 'Inutilização homologada.',
                'protocolo' => $prot,
                'cStat' => $resp['cStat'] ?? null,
            ];
        }
        $mot = $resp['message'] ?? ($resp['xMotivo'] ?? 'Rejeição SEFAZ');
        $atualizarEvento('rejeitado', $xmlRet ?: null, null, $mot);
        return ['sucesso' => false, 'erro' => $mot];
    }

    if (!$documento || $documento_id < 1) {
        $erro = 'Documento inválido para este evento.';
        $atualizarEvento('rejeitado', null, null, $erro);
        return ['sucesso' => false, 'erro' => $erro];
    }

    $chave = preg_replace('/\D/', '', (string)($documento['chave_acesso'] ?? ''));
    $nProt = (string)($documento['protocolo_autorizacao'] ?? '');

    if ($tipo_evento === 'cancelamento') {
        if (strlen($chave) !== 44) {
            $erro = 'Chave de acesso inválida para cancelamento.';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }
        if ($nProt === '' || $nProt === '0') {
            $erro = 'NF-e sem protocolo de autorização; não é possível cancelar na SEFAZ.';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }
        $xJust = trim((string)$justificativa);
        if ($xJust === '') {
            $erro = 'Justificativa é obrigatória para cancelamento.';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }

        $resp = $nfeService->enviarCancelamentoNFe($chave, $xJust, $nProt);
        $xmlRet = (string)($resp['response_xml'] ?? '');
        $prot = $resp['protocolo_evento'] ?? null;
        if (!empty($resp['success'])) {
            $stmtN = $conn->prepare("UPDATE fiscal_nfe_clientes SET status = 'cancelada', updated_at = NOW() WHERE id = ? AND empresa_id = ?");
            $stmtN->execute([$documento_id, $empresa_id]);
            $atualizarEvento('aceito', $xmlRet ?: null, $prot, '');
            return [
                'sucesso' => true,
                'mensagem' => $resp['message'] ?? 'Cancelamento homologado.',
                'protocolo_evento' => $prot,
            ];
        }
        $mot = $resp['message'] ?? 'Rejeição SEFAZ';
        $atualizarEvento('rejeitado', $xmlRet ?: null, null, $mot);
        return ['sucesso' => false, 'erro' => $mot];
    }

    if ($tipo_evento === 'cce') {
        if (strlen($chave) !== 44) {
            $erro = 'Chave de acesso inválida para CC-e.';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }
        if ($correcao === '') {
            $erro = 'Informe o texto da correção (campo correcao ou justificativa).';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }

        $resp = $nfeService->enviarCartaCorrecaoNFe($chave, $correcao, $nSeq);
        $xmlRet = (string)($resp['response_xml'] ?? '');
        $prot = $resp['protocolo_evento'] ?? null;
        if (!empty($resp['success'])) {
            $atualizarEvento('aceito', $xmlRet ?: null, $prot, '');
            return [
                'sucesso' => true,
                'mensagem' => $resp['message'] ?? 'CC-e homologada.',
                'protocolo_evento' => $prot,
                'n_seq_evento' => $nSeq,
            ];
        }
        $mot = $resp['message'] ?? 'Rejeição SEFAZ';
        $atualizarEvento('rejeitado', $xmlRet ?: null, null, $mot);
        return ['sucesso' => false, 'erro' => $mot];
    }

    if ($tipo_evento === 'manifestacao') {
        if (strlen($chave) !== 44) {
            $erro = 'Chave de acesso inválida para manifestação.';
            $atualizarEvento('rejeitado', null, null, $erro);
            return ['sucesso' => false, 'erro' => $erro];
        }

        $map = [
            'ciencia' => NFeService::MANIFEST_CIENCIA,
            'confirmacao' => NFeService::MANIFEST_CONFIRMACAO,
            'desconhecimento' => NFeService::MANIFEST_DESCONHECIMENTO,
            'nao_realizada' => NFeService::MANIFEST_NAO_REALIZADA,
        ];
        $tpEv = $map[$manifestTipo] ?? NFeService::MANIFEST_CIENCIA;
        $xJustMan = ($tpEv === NFeService::MANIFEST_NAO_REALIZADA) ? trim((string)$justificativa) : '';

        $resp = $nfeService->manifestarDestinatario($chave, $tpEv, $xJustMan, 1);
        $xmlRet = (string)($resp['response_xml'] ?? '');
        $prot = $resp['protocolo_evento'] ?? null;
        if (!empty($resp['success'])) {
            $atualizarEvento('aceito', $xmlRet ?: null, $prot, '');
            return [
                'sucesso' => true,
                'mensagem' => $resp['message'] ?? 'Manifestação registrada.',
                'protocolo_evento' => $prot,
                'manifestacao_tipo' => $manifestTipo,
            ];
        }
        $mot = $resp['message'] ?? 'Rejeição SEFAZ';
        $atualizarEvento('rejeitado', $xmlRet ?: null, null, $mot);
        return ['sucesso' => false, 'erro' => $mot];
    }

    $erro = 'Tipo de evento não suportado neste fluxo.';
    $atualizarEvento('rejeitado', null, null, $erro);
    return ['sucesso' => false, 'erro' => $erro];
}

function mdfeParseJsonAny($raw, $default)
{
    if ($raw === null || $raw === '') return $default;
    if (is_array($raw)) return $raw;
    if (!is_string($raw)) return $default;
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return $default;
    return $decoded;
}

/**
 * Validação centralizada de regras MDF-e (SEFAZ/ANTT).
 * Front ajuda, backend garante.
 */
function validarMDFeRegras(array $dados): array
{
    $versao = 'NT2025.001';
    $modo = strtolower((string)($dados['modo'] ?? 'emissao'));
    $tipoEmitente = (string)($dados['tipo_emitente'] ?? '');
    $tipoTransportador = (string)($dados['tipo_transportador'] ?? '');
    $cteIds = array_values(array_filter(array_map('intval', (array)($dados['cte_ids'] ?? [])), function($v){ return $v > 0; }));
    $documentos = is_array($dados['documentos'] ?? null) ? $dados['documentos'] : [];
    $pagamentos = is_array($dados['pagamentos'] ?? null) ? $dados['pagamentos'] : [];
    $contratantes = is_array($dados['contratantes'] ?? null) ? $dados['contratantes'] : [];
    $ciots = is_array($dados['ciots'] ?? null) ? $dados['ciots'] : [];
    $vales = is_array($dados['vales_pedagio'] ?? null) ? $dados['vales_pedagio'] : [];
    $produtos = is_array($dados['produtos'] ?? null) ? $dados['produtos'] : [];
    $rod = is_array($dados['rodoviario'] ?? null) ? $dados['rodoviario'] : [];
    $tot = is_array($dados['totais'] ?? null) ? $dados['totais'] : [];
    $rotaTemPedagio = !empty($dados['rota_tem_pedagio']);
    $strictVehicle = !empty($dados['strict_vehicle']);
    $strictDocs = !empty($dados['strict_docs']);

    $erros = [];
    $warnings = [];
    $addErro = function(string $codigo, string $id, string $mensagem) use (&$erros) {
        $erros[] = ['codigo' => $codigo, 'id' => $id, 'mensagem' => $mensagem];
    };
    $addWarn = function(string $codigo, string $id, string $mensagem) use (&$warnings) {
        $warnings[] = ['codigo' => $codigo, 'id' => $id, 'mensagem' => $mensagem];
    };

    if ($tipoEmitente === '') {
        // Retrocompatibilidade: fluxo antigo com CT-e direto.
        $tipoEmitente = !empty($cteIds) ? '1' : '2';
    }

    $cteDocsCount = 0;
    $nfeDocsCount = 0;
    $docsSemMunicipio = 0;
    foreach ($documentos as $doc) {
        if (!is_array($doc)) continue;
        $mun = trim((string)($doc['municipioDescarregamento'] ?? $doc['municipio_descarga'] ?? ''));
        if ($mun === '') $docsSemMunicipio++;
        $chaveNfe = trim((string)($doc['chaveNfe'] ?? $doc['chave_nfe'] ?? ''));
        $chaveCte = trim((string)($doc['chaveCte'] ?? $doc['chave_cte'] ?? ''));
        if ($chaveNfe !== '') $nfeDocsCount++;
        if ($chaveCte !== '') $cteDocsCount++;
    }
    if (!empty($cteIds)) $cteDocsCount += count($cteIds);

    if ($strictDocs && $docsSemMunicipio > 0) {
        $addErro('E010', 'E010_MDFe_DOCUMENTO_SEM_MUNICIPIO_DESCARGA', 'Há documento fiscal sem município de descarregamento vinculado.');
    }

    $placa = trim((string)($rod['placa'] ?? ''));
    $tipoRodado = trim((string)($rod['tipo_rodado'] ?? ''));
    $tipoCarroceria = trim((string)($rod['tipo_carroceria'] ?? ''));
    if ($strictVehicle) {
        if ($placa === '') $addErro('E020', 'E020_MDFe_PLACA_OBRIGATORIA', 'Placa do veículo obrigatória.');
        if ($tipoRodado === '') $addErro('E021', 'E021_MDFe_TIPO_RODADO_OBRIGATORIO', 'Tipo de rodado obrigatório.');
        if ($tipoCarroceria === '') $addErro('E022', 'E022_MDFe_TIPO_CARROCERIA_OBRIGATORIO', 'Tipo de carroceria obrigatório.');
    }

    $rntrc = trim((string)($rod['rntrc'] ?? ''));
    if (in_array($tipoEmitente, ['1', '3'], true) && $rntrc === '') {
        $addErro('E023', 'E023_MDFe_RNTRC_OBRIGATORIO', 'RNTRC obrigatório para tipo de emitente 1 e 3.');
    }
    if ($tipoEmitente === '2' && $rntrc === '') {
        $addWarn('W001', 'W001_MDFe_RNTRC_OPCIONAL_CARGA_PROPRIA', 'RNTRC opcional para carga própria (tipo 2).');
    }

    $pesoTotal = (float)($tot['peso_total'] ?? ($dados['peso_total_calculado'] ?? 0));
    if (array_key_exists('peso_total', $tot) || array_key_exists('peso_total_calculado', $dados)) {
        if ($pesoTotal <= 0) $addErro('E024', 'E024_MDFe_PESO_TOTAL_INVALIDO', 'Peso total inválido (deve ser maior que zero).');
    }

    $temPagamento = count($pagamentos) > 0;
    $temContratante = count($contratantes) > 0;
    $temValePedagio = count($vales) > 0;
    $temCiot = count($ciots) > 0;

    foreach ($pagamentos as $pg) {
        $componentes = is_array($pg['componentes'] ?? null) ? $pg['componentes'] : [];
        $temFrete = false;
        foreach ($componentes as $comp) {
            $codigo = (string)($comp['codigo'] ?? '');
            if ($codigo === '04') { $temFrete = true; break; }
        }
        if (!$temFrete) {
            $addErro('E030', 'E030_MDFe_COMPONENTE_FRETE_OBRIGATORIO', 'Pagamento de frete deve conter componente 04 - Frete.');
            break;
        }
    }

    $temCargaLotacao = false;
    $temNcmProduto = false;
    foreach ($produtos as $prod) {
        if (!is_array($prod)) continue;
        if ((string)($prod['cargaLotacao'] ?? $prod['carga_lotacao'] ?? '') === 'sim') $temCargaLotacao = true;
        if (trim((string)($prod['ncm'] ?? '')) !== '') $temNcmProduto = true;
    }
    if ($temCargaLotacao && $temPagamento && !$temNcmProduto) {
        $addErro('E040', 'E040_MDFe_NCM_OBRIGATORIO_CARGA_LOTACAO', 'Carga lotação com pagamento exige NCM no produto predominante.');
    }

    if ($tipoEmitente === '1') {
        if ($cteDocsCount <= 0) $addErro('E001', 'E001_MDFe_CTE_OBRIGATORIO', 'CT-e obrigatório para tipo de emitente 1.');
        if (!$temPagamento) $addErro('E004', 'E004_MDFe_PAGAMENTO_FRETE_OBRIGATORIO', 'Pagamento de frete obrigatório para tipo de emitente 1.');
        if (!$temContratante) $addErro('E005', 'E005_MDFe_CONTRATANTE_OBRIGATORIO', 'Contratante obrigatório para tipo de emitente 1.');
        if ($rotaTemPedagio && !$temValePedagio) $addErro('E006', 'E006_MDFe_VALE_PEDAGIO_OBRIGATORIO', 'Vale pedágio obrigatório para rota com pedágio.');
        if (!$rotaTemPedagio && !$temValePedagio) $addWarn('W002', 'W002_MDFe_VALE_PEDAGIO_NAO_INFORMADO', 'Vale pedágio não informado (verifique se a rota possui pedágio).');
    } elseif ($tipoEmitente === '2') {
        if ($nfeDocsCount <= 0 && empty($documentos)) $addErro('E002', 'E002_MDFe_NFE_OBRIGATORIA', 'NF-e obrigatória para tipo de emitente 2.');
        if ($cteDocsCount > 0) $addErro('E003', 'E003_MDFe_CTE_PROIBIDO_CARGA_PROPRIA', 'Tipo emitente 2 não permite CT-e.');
        if ($temPagamento) $addErro('E007', 'E007_MDFe_PAGAMENTO_PROIBIDO_CARGA_PROPRIA', 'Tipo emitente 2 não permite pagamento de frete.');
        if ($temContratante) $addErro('E008', 'E008_MDFe_CONTRATANTE_PROIBIDO_CARGA_PROPRIA', 'Tipo emitente 2 não permite contratante.');
        if ($temCiot) $addErro('E009', 'E009_MDFe_CIOT_PROIBIDO_CARGA_PROPRIA', 'Tipo emitente 2 não permite CIOT.');
        if ($temValePedagio) $addErro('E011', 'E011_MDFe_VALE_PEDAGIO_PROIBIDO_CARGA_PROPRIA', 'Tipo emitente 2 não permite vale pedágio.');
    } elseif ($tipoEmitente === '3') {
        if ($cteDocsCount <= 0) $addErro('E012', 'E012_MDFe_CTE_GLOBALIZADO_OBRIGATORIO', 'CT-e obrigatório para tipo de emitente 3.');
        if ($nfeDocsCount <= 0 && empty($documentos)) $addErro('E013', 'E013_MDFe_NFE_OBRIGATORIA_GLOBALIZADO', 'NF-e obrigatória para tipo de emitente 3.');
        if (!$temPagamento) $addErro('E014', 'E014_MDFe_PAGAMENTO_FRETE_OBRIGATORIO_GLOBALIZADO', 'Pagamento de frete obrigatório para tipo de emitente 3.');
        if (!$temContratante) $addErro('E015', 'E015_MDFe_CONTRATANTE_OBRIGATORIO_GLOBALIZADO', 'Contratante obrigatório para tipo de emitente 3.');
        if ($rotaTemPedagio && !$temValePedagio) $addErro('E016', 'E016_MDFe_VALE_PEDAGIO_OBRIGATORIO_GLOBALIZADO', 'Vale pedágio obrigatório para rota com pedágio.');
        if (!$rotaTemPedagio && !$temValePedagio) $addWarn('W003', 'W003_MDFe_VALE_PEDAGIO_NAO_INFORMADO_GLOBALIZADO', 'Vale pedágio não informado (verifique se a rota possui pedágio).');
    }

    // CIOT só obrigatório para TAC com pagamento (não carga própria).
    if ($tipoTransportador === '2' && $tipoEmitente !== '2' && $temPagamento && !$temCiot) {
        $addErro('E017', 'E017_MDFe_CIOT_OBRIGATORIO_TAC_COM_PAGAMENTO', 'CIOT obrigatório para TAC quando houver pagamento de frete.');
    }

    if (!function_exists('doc_validar_cpf')) {
        require_once __DIR__ . '/../../includes/doc_validators.php';
    }
    foreach ($contratantes as $idx => $c) {
        if (!is_array($c)) {
            continue;
        }
        $doc = doc_only_digits((string)($c['documento'] ?? ''));
        if ($doc === '') {
            continue;
        }
        $tp = strtolower((string)($c['tipoPessoa'] ?? $c['tipo_pessoa'] ?? 'juridica'));
        if ($tp === 'estrangeiro') {
            continue;
        }
        if ($tp === 'fisica') {
            if (!doc_validar_cpf($doc)) {
                $addErro('E050', 'E050_MDFe_CONTRATANTE_CPF_INVALIDO', 'Contratante #' . ($idx + 1) . ': CPF inválido.');
            }
        } elseif (!doc_validar_cnpj($doc)) {
            $addErro('E051', 'E051_MDFe_CONTRATANTE_CNPJ_INVALIDO', 'Contratante #' . ($idx + 1) . ': CNPJ inválido.');
        }
    }
    foreach ($pagamentos as $idx => $pg) {
        if (!is_array($pg)) {
            continue;
        }
        $doc = doc_only_digits((string)($pg['documento'] ?? ''));
        if ($doc === '') {
            continue;
        }
        $tp = strtolower((string)($pg['tipoPessoa'] ?? $pg['tipo_pessoa'] ?? 'juridica'));
        if ($tp === 'estrangeiro') {
            continue;
        }
        if ($tp === 'fisica') {
            if (!doc_validar_cpf($doc)) {
                $addErro('E052', 'E052_MDFe_PAGADOR_CPF_INVALIDO', 'Pagamento #' . ($idx + 1) . ': CPF do pagador inválido.');
            }
        } elseif (!doc_validar_cnpj($doc)) {
            $addErro('E053', 'E053_MDFe_PAGADOR_CNPJ_INVALIDO', 'Pagamento #' . ($idx + 1) . ': CNPJ do pagador inválido.');
        }
    }
    foreach ($vales as $idx => $v) {
        if (!is_array($v)) {
            continue;
        }
        $cnpjF = doc_only_digits((string)($v['cnpjFornecedor'] ?? $v['cnpj_fornecedor'] ?? ''));
        if ($cnpjF !== '' && !doc_validar_cnpj($cnpjF)) {
            $addErro('E054', 'E054_MDFe_VALE_CNPJ_FORNECEDOR_INVALIDO', 'Vale pedágio #' . ($idx + 1) . ': CNPJ do fornecedor inválido.');
        }
        $resp = doc_only_digits((string)($v['responsavelPagamento'] ?? $v['responsavel_pagamento'] ?? ''));
        if ($resp === '') {
            continue;
        }
        $len = strlen($resp);
        if ($len === 11 && !doc_validar_cpf($resp)) {
            $addErro('E055', 'E055_MDFe_VALE_RESP_CPF_INVALIDO', 'Vale pedágio #' . ($idx + 1) . ': CPF/CNPJ do responsável inválido.');
        } elseif ($len === 14 && !doc_validar_cnpj($resp)) {
            $addErro('E055', 'E055_MDFe_VALE_RESP_CNPJ_INVALIDO', 'Vale pedágio #' . ($idx + 1) . ': CPF/CNPJ do responsável inválido.');
        } elseif ($len !== 11 && $len !== 14) {
            $addErro('E056', 'E056_MDFe_VALE_RESP_DOCUMENTO_TAMANHO', 'Vale pedágio #' . ($idx + 1) . ': responsável deve ser CPF (11) ou CNPJ (14 dígitos).');
        }
    }
    foreach ($ciots as $idx => $cio) {
        if (!is_array($cio)) {
            continue;
        }
        $tac = doc_only_digits((string)($cio['cpfCnpjTac'] ?? $cio['cpf_cnpj_tac'] ?? ''));
        if ($tac === '') {
            continue;
        }
        $len = strlen($tac);
        if ($len === 11 && !doc_validar_cpf($tac)) {
            $addErro('E057', 'E057_MDFe_CIOT_CPF_INVALIDO', 'CIOT #' . ($idx + 1) . ': CPF/CNPJ TAC inválido.');
        } elseif ($len === 14 && !doc_validar_cnpj($tac)) {
            $addErro('E057', 'E057_MDFe_CIOT_CNPJ_INVALIDO', 'CIOT #' . ($idx + 1) . ': CPF/CNPJ TAC inválido.');
        } elseif ($len !== 11 && $len !== 14) {
            $addErro('E058', 'E058_MDFe_CIOT_DOCUMENTO_TAMANHO', 'CIOT #' . ($idx + 1) . ': informe CPF (11) ou CNPJ (14 dígitos) no TAC.');
        }
    }

    $segurosLista = is_array($dados['seguros'] ?? null) ? $dados['seguros'] : [];
    foreach ($segurosLista as $idx => $sg) {
        if (!is_array($sg)) {
            continue;
        }
        $docResp = doc_only_digits((string)($sg['cpfCnpjResponsavel'] ?? $sg['cpf_cnpj_responsavel'] ?? ''));
        if ($docResp !== '') {
            $lenR = strlen($docResp);
            if ($lenR === 11 && !doc_validar_cpf($docResp)) {
                $addErro('E061', 'E061_MDFe_SEGURO_RESP_CPF_INVALIDO', 'Seguro #' . ($idx + 1) . ': CPF/CNPJ do responsável inválido.');
            } elseif ($lenR === 14 && !doc_validar_cnpj($docResp)) {
                $addErro('E061', 'E061_MDFe_SEGURO_RESP_CNPJ_INVALIDO', 'Seguro #' . ($idx + 1) . ': CPF/CNPJ do responsável inválido.');
            } elseif ($lenR !== 11 && $lenR !== 14) {
                $addErro('E062', 'E062_MDFe_SEGURO_RESP_DOCUMENTO_TAMANHO', 'Seguro #' . ($idx + 1) . ': responsável deve ser CPF (11) ou CNPJ (14 dígitos).');
            }
        }
        $cnpjSeg = doc_only_digits((string)($sg['cnpjSeguradora'] ?? $sg['cnpj_seguradora'] ?? ''));
        if ($cnpjSeg !== '' && !doc_validar_cnpj($cnpjSeg)) {
            $addErro('E063', 'E063_MDFe_SEGURO_CNPJ_SEGURADORA_INVALIDO', 'Seguro #' . ($idx + 1) . ': CNPJ da seguradora inválido.');
        }
    }

    $autorizadosTot = [];
    if (isset($tot['autorizadosDownload']) && is_array($tot['autorizadosDownload'])) {
        $autorizadosTot = $tot['autorizadosDownload'];
    } elseif (isset($tot['autorizados_download']) && is_array($tot['autorizados_download'])) {
        $autorizadosTot = $tot['autorizados_download'];
    }
    foreach ($autorizadosTot as $idx => $au) {
        if (!is_array($au)) {
            continue;
        }
        $doc = doc_only_digits((string)($au['documento'] ?? ''));
        if ($doc === '') {
            continue;
        }
        $len = strlen($doc);
        if ($len === 11 && !doc_validar_cpf($doc)) {
            $addErro('E059', 'E059_MDFe_AUTORIZADO_CPF_INVALIDO', 'Autorizado download #' . ($idx + 1) . ': CPF inválido.');
        } elseif ($len === 14 && !doc_validar_cnpj($doc)) {
            $addErro('E059', 'E059_MDFe_AUTORIZADO_CNPJ_INVALIDO', 'Autorizado download #' . ($idx + 1) . ': CNPJ inválido.');
        } elseif ($len !== 11 && $len !== 14) {
            $addErro('E060', 'E060_MDFe_AUTORIZADO_DOCUMENTO_TAMANHO', 'Autorizado download #' . ($idx + 1) . ': use CPF (11) ou CNPJ (14 dígitos).');
        }
    }

    // Modo rascunho: não bloqueia, converte erros em warnings.
    if ($modo === 'rascunho' && !empty($erros)) {
        foreach ($erros as $e) {
            $warnings[] = [
                'codigo' => 'RASCUNHO_' . $e['codigo'],
                'id' => 'RASCUNHO_' . ($e['id'] ?? $e['codigo']),
                'mensagem' => $e['mensagem']
            ];
        }
        $erros = [];
    }

    return [
        'versao_regra' => $versao,
        'valido' => empty($erros),
        'erros' => $erros,
        'warnings' => $warnings,
    ];
}

/**
 * Validação centralizada CT-e (criação rascunho / envio SEFAZ).
 * Retorno alinhado a validarMDFeRegras (erros/warnings com codigo, id, mensagem).
 */
function validarCTeRegras(array $dados): array
{
    $versao = 'CTE-INT-2026.001';
    $modo = strtolower((string)($dados['modo'] ?? 'rascunho'));
    $isEmissao = ($modo === 'emissao');

    $erros = [];
    $warnings = [];
    $addErro = function (string $codigo, string $id, string $mensagem) use (&$erros): void {
        $erros[] = ['codigo' => $codigo, 'id' => $id, 'mensagem' => $mensagem];
    };
    $addWarn = function (string $codigo, string $id, string $mensagem) use (&$warnings): void {
        $warnings[] = ['codigo' => $codigo, 'id' => $id, 'mensagem' => $mensagem];
    };

    $nfe_ids = array_values(array_unique(array_filter(array_map('intval', (array)($dados['nfe_ids'] ?? [])), function ($v) {
        return $v > 0;
    })));
    $nfes = is_array($dados['nfes'] ?? null) ? $dados['nfes'] : [];

    if (empty($nfe_ids) && empty($nfes)) {
        $addErro('C001', 'C001_CTE_NFE_OBRIGATORIA', 'Informe pelo menos uma NF-e vinculada ao CT-e.');
    }

    if (!empty($nfe_ids) && count($nfes) !== count($nfe_ids)) {
        $addErro('C004', 'C004_CTE_NFE_NAO_ENCONTRADA', 'Alguma NF-e informada não pertence à empresa ou não foi encontrada.');
    }

    $allowedCriar = ['recebida'];
    $allowedEmissao = ['recebida', 'em_transporte'];

    foreach ($nfes as $row) {
        if (!is_array($row)) {
            continue;
        }
        $st = strtolower(trim((string)($row['status'] ?? '')));
        $nid = (int)($row['id'] ?? 0);
        $idTxt = $nid > 0 ? (string)$nid : '?';
        if ($isEmissao) {
            if ($st !== '' && !in_array($st, $allowedEmissao, true)) {
                $addErro('C002', 'C002_CTE_NFE_STATUS', 'NF-e ' . $idTxt . ' com status inadequado para envio: ' . $st . '.');
            }
        } else {
            if ($st !== '' && !in_array($st, $allowedCriar, true)) {
                $addErro('C002', 'C002_CTE_NFE_STATUS_RASCUNHO', 'NF-e ' . $idTxt . ' deve estar com status "recebida" para criar o CT-e.');
            }
        }
    }

    $veiculo_id = (int)($dados['veiculo_id'] ?? 0);
    $motorista_id = (int)($dados['motorista_id'] ?? 0);
    if ($veiculo_id <= 0) {
        $addErro('C008', 'C008_CTE_VEICULO', 'Veículo obrigatório (modal rodoviário).');
    }
    if ($motorista_id <= 0) {
        $addErro('C008b', 'C008b_CTE_MOTORISTA', 'Motorista obrigatório.');
    }

    $valor_frete = (float)($dados['valor_frete'] ?? 0);
    $peso_total = (float)($dados['peso_total'] ?? 0);

    if ($valor_frete <= 0) {
        if ($isEmissao) {
            $addErro('C006', 'C006_CTE_VALOR_FRETE', 'Valor do frete deve ser maior que zero para envio à SEFAZ.');
        } else {
            $addWarn('W006', 'W006_CTE_VALOR_FRETE', 'Valor do frete zerado; complete antes de enviar à SEFAZ.');
        }
    }
    if ($peso_total <= 0) {
        if ($isEmissao) {
            $addErro('C007', 'C007_CTE_PESO', 'Peso total deve ser maior que zero para envio à SEFAZ.');
        } else {
            $addWarn('W007', 'W007_CTE_PESO', 'Peso total zerado; complete antes de enviar à SEFAZ.');
        }
    }

    $origem = trim((string)($dados['origem'] ?? ''));
    $destino = trim((string)($dados['destino'] ?? ''));
    if ($isEmissao) {
        if ($origem === '') {
            $addErro('C011', 'C011_CTE_ORIGEM', 'Origem obrigatória para envio à SEFAZ.');
        }
        if ($destino === '') {
            $addErro('C011b', 'C011b_CTE_DESTINO', 'Destino obrigatório para envio à SEFAZ.');
        }
    }

    if (!function_exists('doc_validar_cnpj')) {
        require_once __DIR__ . '/../../includes/doc_validators.php';
    }

    $tomadorDigits = '';
    foreach ($nfes as $row) {
        if (!is_array($row)) {
            continue;
        }
        $c = doc_only_digits((string)($row['cliente_cnpj'] ?? ''));
        if ($c !== '') {
            $tomadorDigits = $c;
            break;
        }
    }
    $itens = isset($dados['fiscal_cte_itens']) && is_array($dados['fiscal_cte_itens']) ? $dados['fiscal_cte_itens'] : null;
    if ($itens !== null) {
        $tc = doc_only_digits((string)($itens['tomador_cnpj'] ?? ''));
        if ($tc !== '') {
            $tomadorDigits = $tc;
        }
    }

    if ($tomadorDigits === '') {
        if ($isEmissao) {
            $addErro('C003', 'C003_CTE_TOMADOR', 'Tomador do serviço (CNPJ/CPF na NF-e ou em fiscal_cte_itens) ausente.');
        } else {
            $addWarn('W003', 'W003_CTE_TOMADOR', 'Tomador não identificado nas NF-e selecionadas.');
        }
    } else {
        $len = strlen($tomadorDigits);
        if ($len === 14) {
            if (!doc_validar_cnpj($tomadorDigits)) {
                $addErro('C003a', 'C003a_CTE_TOMADOR_CNPJ', 'CNPJ do tomador inválido.');
            }
        } elseif ($len === 11) {
            if (!doc_validar_cpf($tomadorDigits)) {
                $addErro('C003b', 'C003b_CTE_TOMADOR_CPF', 'CPF do tomador inválido.');
            }
        } else {
            $addErro('C003c', 'C003c_CTE_TOMADOR_DOC', 'Documento do tomador deve ter 11 (CPF) ou 14 (CNPJ) dígitos.');
        }
    }

    if ($isEmissao) {
        $temItens = !empty($dados['tem_fiscal_cte_itens']);
        if (!$temItens) {
            $addErro('C009', 'C009_CTE_ITENS', 'Registro fiscal_cte_itens ausente; complete o CT-e antes do envio.');
        }
    }

    return [
        'versao_regra' => $versao,
        'valido' => empty($erros),
        'erros' => $erros,
        'warnings' => $warnings,
    ];
}

function ensureMdfeValidationLogSchema(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS mdfe_validacao_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NULL,
            empresa_id INT NOT NULL,
            versao_regra VARCHAR(30) NULL,
            contexto VARCHAR(30) NOT NULL,
            payload_hash CHAR(64) NULL,
            payload_json LONGTEXT NULL,
            erros_json LONGTEXT NULL,
            warnings_json LONGTEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa_data (empresa_id, criado_em),
            INDEX idx_payload_hash (payload_hash),
            INDEX idx_criado_em (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    try {
        $conn->exec("ALTER TABLE mdfe_validacao_log ADD COLUMN IF NOT EXISTS payload_hash CHAR(64) NULL");
    } catch (Throwable $e) {}
    try {
        $conn->exec("CREATE INDEX idx_payload_hash ON mdfe_validacao_log (payload_hash)");
    } catch (Throwable $e) {}
    try {
        $conn->exec("CREATE INDEX idx_criado_em ON mdfe_validacao_log (criado_em)");
    } catch (Throwable $e) {}
}

function registrarLogValidacaoMDFe(PDO $conn, int $empresa_id, ?int $mdfe_id, string $contexto, array $resultado, array $payload): void
{
    try {
        ensureMdfeValidationLogSchema($conn);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadHash = hash('sha256', (string)$payloadJson);
        $stmt = $conn->prepare("
            INSERT INTO mdfe_validacao_log
                (mdfe_id, empresa_id, versao_regra, contexto, payload_hash, payload_json, erros_json, warnings_json, criado_em)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $mdfe_id,
            $empresa_id,
            (string)($resultado['versao_regra'] ?? ''),
            $contexto,
            $payloadHash,
            $payloadJson,
            json_encode($resultado['erros'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($resultado['warnings'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        // Nunca quebrar fluxo fiscal por erro de log.
    }
}

function ensureCteValidationLogSchema(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS cte_validacao_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            cte_id BIGINT NULL,
            empresa_id INT NOT NULL,
            versao_regra VARCHAR(30) NULL,
            contexto VARCHAR(30) NOT NULL,
            payload_hash CHAR(64) NULL,
            payload_json LONGTEXT NULL,
            erros_json LONGTEXT NULL,
            warnings_json LONGTEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cte_log_cte (cte_id),
            INDEX idx_cte_log_empresa (empresa_id),
            INDEX idx_cte_log_empresa_data (empresa_id, criado_em),
            INDEX idx_cte_log_payload_hash (payload_hash),
            INDEX idx_cte_log_criado (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function registrarLogValidacaoCTe(PDO $conn, int $empresa_id, ?int $cte_id, string $contexto, array $resultado, array $payload): void
{
    try {
        ensureCteValidationLogSchema($conn);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadHash = hash('sha256', (string)$payloadJson);
        $stmt = $conn->prepare("
            INSERT INTO cte_validacao_log
                (cte_id, empresa_id, versao_regra, contexto, payload_hash, payload_json, erros_json, warnings_json, criado_em)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $cte_id,
            $empresa_id,
            (string)($resultado['versao_regra'] ?? ''),
            $contexto,
            $payloadHash,
            $payloadJson,
            json_encode($resultado['erros'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($resultado['warnings'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        // Nunca quebrar fluxo fiscal por erro de log.
    }
}

function ensureMdfeWizardPersistenceSchema(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_ciot (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            numero_ciot VARCHAR(60) NULL,
            valor_frete DECIMAL(15,2) NULL,
            cpf_cnpj_tac VARCHAR(20) NULL,
            ipef VARCHAR(120) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_vale_pedagio (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            eixos INT NULL,
            valor DECIMAL(15,2) NULL,
            tipo VARCHAR(80) NULL,
            cnpj_fornecedor VARCHAR(20) NULL,
            numero_comprovante VARCHAR(100) NULL,
            responsavel_pagamento VARCHAR(20) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_contratantes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            tipo_pessoa VARCHAR(20) NULL,
            documento VARCHAR(30) NULL,
            razao_social VARCHAR(255) NULL,
            numero_contrato VARCHAR(80) NULL,
            valor DECIMAL(15,2) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_pagamentos (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            tipo_pessoa VARCHAR(20) NULL,
            documento VARCHAR(30) NULL,
            razao_social VARCHAR(255) NULL,
            considerar_componentes TINYINT(1) NOT NULL DEFAULT 0,
            valor_total_contrato DECIMAL(15,2) NULL,
            indicador_forma_pagamento VARCHAR(30) NULL,
            forma_financiamento VARCHAR(100) NULL,
            alto_desempenho VARCHAR(30) NULL,
            tipo_pagamento VARCHAR(60) NULL,
            indicador_status_pagamento VARCHAR(30) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_pagamento_componentes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            pagamento_id BIGINT NULL,
            empresa_id INT NOT NULL,
            codigo VARCHAR(10) NULL,
            tipo VARCHAR(120) NULL,
            valor DECIMAL(15,2) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_pagamento (pagamento_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_seguros (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            responsavel VARCHAR(255) NULL,
            cpf_cnpj_responsavel VARCHAR(20) NULL,
            emitente VARCHAR(255) NULL,
            cnpj_seguradora VARCHAR(20) NULL,
            nome_seguradora VARCHAR(255) NULL,
            tomador_contratante VARCHAR(255) NULL,
            numero_apolice VARCHAR(80) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_seguros_averbacoes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            seguro_id BIGINT NULL,
            empresa_id INT NOT NULL,
            numero_averbacao VARCHAR(80) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_seguro (seguro_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_produtos (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            tipo_carga VARCHAR(120) NULL,
            descricao_produto VARCHAR(255) NULL,
            gtin VARCHAR(20) NULL,
            ncm VARCHAR(12) NULL,
            carga_lotacao VARCHAR(5) NULL,
            local_carregamento_cep VARCHAR(12) NULL,
            local_descarregamento_cep VARCHAR(12) NULL,
            cep_descarregamento VARCHAR(12) NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_lacres (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            numero_lacre VARCHAR(80) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fiscal_mdfe_autorizados_download (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            mdfe_id BIGINT NOT NULL,
            empresa_id INT NOT NULL,
            documento VARCHAR(20) NOT NULL,
            motorista VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mdfe (mdfe_id),
            INDEX idx_empresa (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function persistMdfeWizardEstruturado(PDO $conn, int $empresaId, int $mdfeId, array $payload): void
{
    if ($mdfeId <= 0) return;
    ensureMdfeWizardPersistenceSchema($conn);

    $ciots = is_array($payload['ciots'] ?? null) ? $payload['ciots'] : [];
    $vales = is_array($payload['vales_pedagio'] ?? null) ? $payload['vales_pedagio'] : [];
    $contratantes = is_array($payload['contratantes'] ?? null) ? $payload['contratantes'] : [];
    $pagamentos = is_array($payload['pagamentos'] ?? null) ? $payload['pagamentos'] : [];
    $seguros = is_array($payload['seguros'] ?? null) ? $payload['seguros'] : [];
    $produtos = is_array($payload['produtos'] ?? null) ? $payload['produtos'] : [];
    $totais = is_array($payload['totais'] ?? null) ? $payload['totais'] : [];
    $lacres = is_array($totais['lacres'] ?? null) ? $totais['lacres'] : [];
    $autorizados = is_array($totais['autorizadosDownload'] ?? null) ? $totais['autorizadosDownload'] : [];

    $conn->prepare("DELETE FROM fiscal_mdfe_pagamento_componentes WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_pagamentos WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_seguros_averbacoes WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_seguros WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_ciot WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_vale_pedagio WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_contratantes WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_produtos WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_lacres WHERE mdfe_id = ?")->execute([$mdfeId]);
    $conn->prepare("DELETE FROM fiscal_mdfe_autorizados_download WHERE mdfe_id = ?")->execute([$mdfeId]);

    $stCiot = $conn->prepare("
        INSERT INTO fiscal_mdfe_ciot (mdfe_id, empresa_id, numero_ciot, valor_frete, cpf_cnpj_tac, ipef, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($ciots as $item) {
        if (!is_array($item)) continue;
        $stCiot->execute([
            $mdfeId, $empresaId,
            (string)($item['numeroCiot'] ?? $item['numero_ciot'] ?? ''),
            (float)($item['valorFrete'] ?? $item['valor_frete'] ?? 0),
            (string)($item['cpfCnpjTac'] ?? $item['cpf_cnpj_tac'] ?? ''),
            (string)($item['ipef'] ?? ''),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    $stVale = $conn->prepare("
        INSERT INTO fiscal_mdfe_vale_pedagio (mdfe_id, empresa_id, eixos, valor, tipo, cnpj_fornecedor, numero_comprovante, responsavel_pagamento, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($vales as $item) {
        if (!is_array($item)) continue;
        $stVale->execute([
            $mdfeId, $empresaId,
            (int)($item['eixos'] ?? 0),
            (float)($item['valor'] ?? 0),
            (string)($item['tipo'] ?? ''),
            (string)($item['cnpjFornecedor'] ?? $item['cnpj_fornecedor'] ?? ''),
            (string)($item['numeroComprovante'] ?? $item['numero_comprovante'] ?? ''),
            (string)($item['responsavelPagamento'] ?? $item['responsavel_pagamento'] ?? ''),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    $stContr = $conn->prepare("
        INSERT INTO fiscal_mdfe_contratantes (mdfe_id, empresa_id, tipo_pessoa, documento, razao_social, numero_contrato, valor, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($contratantes as $item) {
        if (!is_array($item)) continue;
        $stContr->execute([
            $mdfeId, $empresaId,
            (string)($item['tipoPessoa'] ?? $item['tipo_pessoa'] ?? ''),
            (string)($item['documento'] ?? ''),
            (string)($item['razaoSocial'] ?? $item['razao_social'] ?? ''),
            (string)($item['numeroContrato'] ?? $item['numero_contrato'] ?? ''),
            (float)($item['valor'] ?? 0),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    $stPag = $conn->prepare("
        INSERT INTO fiscal_mdfe_pagamentos
            (mdfe_id, empresa_id, tipo_pessoa, documento, razao_social, considerar_componentes, valor_total_contrato, indicador_forma_pagamento, forma_financiamento, alto_desempenho, tipo_pagamento, indicador_status_pagamento, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stComp = $conn->prepare("
        INSERT INTO fiscal_mdfe_pagamento_componentes (mdfe_id, pagamento_id, empresa_id, codigo, tipo, valor, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($pagamentos as $item) {
        if (!is_array($item)) continue;
        $stPag->execute([
            $mdfeId, $empresaId,
            (string)($item['tipoPessoa'] ?? $item['tipo_pessoa'] ?? ''),
            (string)($item['documento'] ?? ''),
            (string)($item['razaoSocial'] ?? $item['razao_social'] ?? ''),
            !empty($item['considerarComponentes']) ? 1 : 0,
            (float)($item['valorTotalContrato'] ?? $item['valor_total_contrato'] ?? 0),
            (string)($item['indicadorFormaPagamento'] ?? $item['indicador_forma_pagamento'] ?? ''),
            (string)($item['formaFinanciamento'] ?? $item['forma_financiamento'] ?? ''),
            (string)($item['altoDesempenho'] ?? $item['alto_desempenho'] ?? ''),
            (string)($item['tipoPagamento'] ?? $item['tipo_pagamento'] ?? ''),
            (string)($item['indicadorStatusPagamento'] ?? $item['indicador_status_pagamento'] ?? ''),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $pagamentoId = (int)$conn->lastInsertId();
        $componentes = is_array($item['componentes'] ?? null) ? $item['componentes'] : [];
        foreach ($componentes as $comp) {
            if (!is_array($comp)) continue;
            $stComp->execute([
                $mdfeId,
                $pagamentoId > 0 ? $pagamentoId : null,
                $empresaId,
                (string)($comp['codigo'] ?? ''),
                (string)($comp['tipo'] ?? ''),
                (float)($comp['valor'] ?? 0),
                json_encode($comp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    $stSeg = $conn->prepare("
        INSERT INTO fiscal_mdfe_seguros
            (mdfe_id, empresa_id, responsavel, cpf_cnpj_responsavel, emitente, cnpj_seguradora, nome_seguradora, tomador_contratante, numero_apolice, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stAv = $conn->prepare("
        INSERT INTO fiscal_mdfe_seguros_averbacoes (mdfe_id, seguro_id, empresa_id, numero_averbacao)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($seguros as $item) {
        if (!is_array($item)) continue;
        $stSeg->execute([
            $mdfeId, $empresaId,
            (string)($item['responsavel'] ?? ''),
            (string)($item['cpfCnpjResponsavel'] ?? $item['cpf_cnpj_responsavel'] ?? ''),
            (string)($item['emitente'] ?? ''),
            (string)($item['cnpjSeguradora'] ?? $item['cnpj_seguradora'] ?? ''),
            (string)($item['nomeSeguradora'] ?? $item['nome_seguradora'] ?? ''),
            (string)($item['tomadorContratante'] ?? $item['tomador_contratante'] ?? ''),
            (string)($item['numeroApolice'] ?? $item['numero_apolice'] ?? ''),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $seguroId = (int)$conn->lastInsertId();
        $averbacoes = is_array($item['averbacoes'] ?? null) ? $item['averbacoes'] : [];
        foreach ($averbacoes as $av) {
            $numeroAv = trim((string)$av);
            if ($numeroAv === '') continue;
            $stAv->execute([$mdfeId, $seguroId > 0 ? $seguroId : null, $empresaId, $numeroAv]);
        }
    }

    $stProd = $conn->prepare("
        INSERT INTO fiscal_mdfe_produtos
            (mdfe_id, empresa_id, tipo_carga, descricao_produto, gtin, ncm, carga_lotacao, local_carregamento_cep, local_descarregamento_cep, cep_descarregamento, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($produtos as $item) {
        if (!is_array($item)) continue;
        $stProd->execute([
            $mdfeId, $empresaId,
            (string)($item['tipoCarga'] ?? $item['tipo_carga'] ?? ''),
            (string)($item['descricaoProduto'] ?? $item['descricao_produto'] ?? ''),
            (string)($item['gtin'] ?? ''),
            (string)($item['ncm'] ?? ''),
            (string)($item['cargaLotacao'] ?? $item['carga_lotacao'] ?? ''),
            (string)($item['localCarregamentoCep'] ?? $item['local_carregamento_cep'] ?? ''),
            (string)($item['localDescarregamentoCep'] ?? $item['local_descarregamento_cep'] ?? ''),
            (string)($item['cepDescarregamento'] ?? $item['cep_descarregamento'] ?? ''),
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    $stLacre = $conn->prepare("INSERT INTO fiscal_mdfe_lacres (mdfe_id, empresa_id, numero_lacre) VALUES (?, ?, ?)");
    foreach ($lacres as $lacre) {
        $numero = trim((string)$lacre);
        if ($numero === '') continue;
        $stLacre->execute([$mdfeId, $empresaId, $numero]);
    }

    $stAut = $conn->prepare("
        INSERT INTO fiscal_mdfe_autorizados_download (mdfe_id, empresa_id, documento, motorista)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($autorizados as $item) {
        if (!is_array($item)) continue;
        $doc = trim((string)($item['documento'] ?? ''));
        if ($doc === '') continue;
        $stAut->execute([
            $mdfeId,
            $empresaId,
            $doc,
            (string)($item['motorista'] ?? ''),
        ]);
    }
}

// Processar requisição
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Listar documentos
            $tipo = $_GET['tipo'] ?? 'cte';
            $status = $_GET['status'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            
            // Mapear tipo para tabela
            $tabela = '';
            switch ($tipo) {
                case 'nfe':
                    $tabela = 'fiscal_nfe_clientes';
                    break;
                case 'cte':
                    $tabela = 'fiscal_cte';
                    break;
                case 'mdfe':
                    $tabela = 'fiscal_mdfe';
                    break;
                case 'nfe_emitida':
                    $tabela = 'fiscal_nfe_emitidas';
                    break;
                default:
                    throw new Exception('Tipo de documento inválido');
            }
            
            $sql = "SELECT * FROM $tabela WHERE empresa_id = ?";
            $params = [$empresa_id];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $orderCol = ($tipo === 'nfe_emitida') ? 'id' : 'data_emissao';
            $sql .= " ORDER BY $orderCol DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($tipo === 'mdfe' && !empty($documentos)) {
                $mdfeIds = array_values(array_filter(array_map('intval', array_column($documentos, 'id')), function ($v) {
                    return $v > 0;
                }));
                if (!empty($mdfeIds)) {
                    $nfeCountMap = [];
                    try {
                        $phN = implode(',', array_fill(0, count($mdfeIds), '?'));
                        $stN = $conn->prepare("
                            SELECT mdfe_id, COUNT(*) AS total_nfe
                            FROM fiscal_mdfe_nfe
                            WHERE mdfe_id IN ($phN)
                            GROUP BY mdfe_id
                        ");
                        $stN->execute($mdfeIds);
                        foreach ($stN->fetchAll(PDO::FETCH_ASSOC) as $rowN) {
                            $nfeCountMap[(int)($rowN['mdfe_id'] ?? 0)] = (int)($rowN['total_nfe'] ?? 0);
                        }
                    } catch (Throwable $e) {
                        // Tabela pode não existir em ambientes sem migração.
                        $nfeCountMap = [];
                    }

                    foreach ($documentos as &$docMdfe) {
                        $idMdfe = (int)($docMdfe['id'] ?? 0);
                        $qtdCte = (int)($docMdfe['total_cte'] ?? 0);
                        $qtdNfe = (int)($nfeCountMap[$idMdfe] ?? 0);
                        $origem = 'manual';
                        if ($qtdCte > 0 && $qtdNfe > 0) {
                            $origem = 'misto';
                        } elseif ($qtdCte > 0) {
                            $origem = 'cte';
                        } elseif ($qtdNfe > 0) {
                            $origem = 'nfe';
                        }
                        $docMdfe['qtd_nfe_origem'] = $qtdNfe;
                        $docMdfe['origem_documental'] = $origem;
                    }
                    unset($docMdfe);
                }
            }
            
            // Contar totais
            if ($tipo === 'nfe_emitida') {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                        SUM(CASE WHEN status = 'autorizada' THEN 1 ELSE 0 END) as autorizados,
                        SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                    FROM fiscal_nfe_emitidas 
                    WHERE empresa_id = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                        SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                        SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                    FROM $tabela 
                    WHERE empresa_id = ?
                ");
            }
            $stmt->execute([$empresa_id]);
            $totais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'documentos' => $documentos,
                'totais' => $totais,
                'tipo' => $tipo
            ]);
            
            logOperacao('listar', "Listou documentos $tipo", 'sucesso');
            break;
            
        case 'receber_nfe':
            // RECEBER NF-e do cliente (não emitir!)
            $numero_nfe = $_POST['numero_nfe'] ?? '';
            $serie_nfe = $_POST['serie_nfe'] ?? '';
            $chave_acesso = $_POST['chave_acesso'] ?? '';
            $cliente_remetente = $_POST['cliente_remetente'] ?? '';
            $cliente_destinatario = $_POST['cliente_destinatario'] ?? '';
            $valor_carga = $_POST['valor_carga'] ?? 0.00;
            $peso_carga = $_POST['peso_carga'] ?? 0.00;
            $volumes = $_POST['volumes'] ?? 0;
            
            if (!$numero_nfe || !$chave_acesso) {
                throw new Exception('Número da NF-e e chave de acesso são obrigatórios');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Inserir NF-e recebida do cliente
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, cliente_cnpj, valor_total, peso_carga, volumes,
                    status, tipo_operacao, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, date('Y-m-d'),
                $cliente_remetente, $_POST['cnpj_remetente'] ?? '', $valor_carga, $peso_carga, $volumes,
                'recebida', 'recebida', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $nfe_id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'NF-e recebida com sucesso!',
                'nfe_id' => $nfe_id,
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe', "Recebeu NF-e #$numero_nfe do cliente", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'receber_nfe_xml':
            fiscal_api_rate_limit_or_json_429('fiscal_receber_nfe_xml', 30, 300);
            // RECEBER NF-e via upload de XML (método recomendado)
            $xml_file = $_FILES['xml_file'] ?? null;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$xml_file || $xml_file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload do arquivo XML');
            }
            
            if (!in_array($xml_file['type'], ['text/xml', 'application/xml']) && 
                !str_ends_with(strtolower($xml_file['name']), '.xml')) {
                throw new Exception('Arquivo deve ser um XML válido');
            }
            
            // Validar tamanho (máximo 5MB)
            if ($xml_file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Arquivo XML muito grande. Máximo: 5MB');
            }
            
            // Ler e validar XML
            $xml_content_original = file_get_contents($xml_file['tmp_name']);
            if (!$xml_content_original) {
                throw new Exception('Não foi possível ler o arquivo XML');
            }
            
            // Remover namespace padrão apenas para o parse (mantém original para gravar)
            $xml_content = preg_replace('/\sxmlns=[\"\\\'][^\"\\\']*[\"\\\']/', '', $xml_content_original);
            
            // Parsear XML
            $xml_data = @simplexml_load_string($xml_content);
            if (!$xml_data) {
                throw new Exception('XML inválido ou malformado');
            }
            
            // Estruturas comuns: <nfeProc><NFe><infNFe>...</infNFe></NFe></nfeProc>
            // ou diretamente <NFe><infNFe>...</infNFe></NFe>
            $nfe_data = null;
            if (isset($xml_data->NFe)) {
                $nfe_data = $xml_data->NFe;
            } elseif (isset($xml_data->infNFe)) {
                // XML já está no nível de infNFe
                $nfe_data = $xml_data;
            }
            
            if (!$nfe_data) {
                throw new Exception('Estrutura XML não reconhecida como NF-e válida');
            }
            
            $inf_nfe = isset($nfe_data->infNFe) ? $nfe_data->infNFe : $nfe_data;
            if (!$inf_nfe) {
                throw new Exception('Dados da NF-e não encontrados no XML');
            }
            
            // Extrair informações básicas
            $chave_acesso = (string)($inf_nfe->Id ?? '');
            $chave_acesso = str_replace('NFe', '', $chave_acesso); // Remove prefixo NFe
            
            if (strlen($chave_acesso) !== 44) {
                throw new Exception('Chave de acesso inválida no XML');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Extrair dados do XML
            $ide = $inf_nfe->ide ?? null;
            $emit = $inf_nfe->emit ?? null;
            $dest = $inf_nfe->dest ?? null;
            $total = $inf_nfe->total ?? null;
            $transp = $inf_nfe->transp ?? null;
            
            $numero_nfe = (string)($ide->nNF ?? '');
            $serie_nfe = (string)($ide->serie ?? '');
            $data_emissao = (string)($ide->dhEmi ?? '');
            $data_emissao = date('Y-m-d', strtotime($data_emissao));
            
            $emitente = (string)($emit->xNome ?? '');
            $cnpj_emitente = (string)($emit->CNPJ ?? '');
            $destinatario = (string)($dest->xNome ?? '');
            $cnpj_destinatario = (string)($dest->CNPJ ?? '');
            
            $valor_total = (float)($total->ICMSTot->vNF ?? 0);
            $peso_total = (float)($transp->vol->pesoB ?? 0);
            $volumes = (int)($transp->vol->qVol ?? 1);
            
            // Salvar arquivo XML
            $upload_dir = '../../uploads/nfe_xml/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $xml_filename = 'nfe_' . $chave_acesso . '_' . date('YmdHis') . '.xml';
            $xml_path = $upload_dir . $xml_filename;
            
            if (!move_uploaded_file($xml_file['tmp_name'], $xml_path)) {
                throw new Exception('Erro ao salvar arquivo XML');
            }
            
            // Inserir NF-e: gravar XML no banco (xml_nfe) e/ou caminho (xml_path)
            $has_xml_nfe = false;
            try {
                $conn->query("SELECT xml_nfe FROM fiscal_nfe_clientes LIMIT 1");
                $has_xml_nfe = true;
            } catch (Throwable $e) {}
            
            if ($has_xml_nfe) {
                $stmt = $conn->prepare("
                    INSERT INTO fiscal_nfe_clientes (
                        empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                        cliente_razao_social, cliente_cnpj, cliente_destinatario, cnpj_destinatario,
                        valor_total, peso_carga, volumes, status, xml_nfe, observacoes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, $data_emissao,
                    $emitente, $cnpj_emitente, $destinatario, $cnpj_destinatario,
                    $valor_total, $peso_total, $volumes, 'recebida',
                    $xml_content_original, $observacoes, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
                ]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO fiscal_nfe_clientes (
                        empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                        cliente_razao_social, cliente_cnpj, cliente_destinatario, cnpj_destinatario,
                        valor_total, peso_carga, volumes, status, xml_path, observacoes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, $data_emissao,
                    $emitente, $cnpj_emitente, $destinatario, $cnpj_destinatario,
                    $valor_total, $peso_total, $volumes, 'recebida',
                    $xml_filename, $observacoes, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
                ]);
            }
            
            $nfe_id = $conn->lastInsertId();
            fiscal_aplicarXmlNfeRecebidaNoBanco($conn, $empresa_id, $nfe_id, $xml_content_original, false);

            echo json_encode([
                'success' => true,
                'message' => 'NF-e recebida via XML com sucesso!',
                'nfe_id' => $nfe_id,
                'chave_acesso' => $chave_acesso,
                'numero_nfe' => $numero_nfe,
                'emitente' => $emitente,
                'valor_total' => $valor_total,
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe_xml', "Recebeu NF-e #$numero_nfe via XML", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'receber_nfe_manual':
            fiscal_api_rate_limit_or_json_429('fiscal_receber_nfe_manual', 30, 300);
            // RECEBER NF-e via digitação manual (plano B)
            $numero_nfe = $_POST['numero_nfe'] ?? '';
            $serie_nfe = $_POST['serie_nfe'] ?? '';
            $chave_acesso = $_POST['chave_acesso'] ?? '';
            $cliente_remetente = $_POST['cliente_remetente'] ?? '';
            $cliente_destinatario = $_POST['cliente_destinatario'] ?? '';
            $valor_carga = $_POST['valor_carga'] ?? 0.00;
            $peso_carga = $_POST['peso_carga'] ?? 0.00;
            $volumes = $_POST['volumes'] ?? 0;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$numero_nfe || !$chave_acesso || !$cliente_remetente || !$cliente_destinatario) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Validar formato da chave de acesso
            if (!preg_match('/^\d{44}$/', $chave_acesso)) {
                throw new Exception('Chave de acesso deve ter exatamente 44 dígitos numéricos');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Inserir NF-e recebida manualmente
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, cliente_destinatario, valor_total, peso_carga, volumes,
                    status, tipo_operacao, observacoes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, date('Y-m-d'),
                $cliente_remetente, $cliente_destinatario, $valor_carga, $peso_carga, $volumes,
                'recebida', 'recebida_manual', $observacoes, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $nfe_id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'NF-e recebida manualmente com sucesso!',
                'nfe_id' => $nfe_id,
                'numero_nfe' => $numero_nfe,
                'chave_acesso' => $chave_acesso,
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe_manual', "Recebeu NF-e #$numero_nfe manualmente", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'receber_nfe_sefaz':
            // Limite por sessão+IP: evita abuso na SEFAZ; janela maior que 5 min reduz 429 em retentativas legítimas
            fiscal_api_rate_limit_or_json_429(
                'fiscal_receber_nfe_sefaz_v2',
                50,
                600,
                'Muitas consultas por chave em pouco tempo. Aguarde até 10 minutos e tente novamente (limite de proteção da SEFAZ / servidor).'
            );
            // RECEBER / CONSULTAR NF-e via SEFAZ + cache local (integração real com NFePHP)
            $chave_acesso = $_POST['chave_acesso'] ?? '';
            $validar_certificado = $_POST['validar_certificado'] ?? '1';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$chave_acesso) {
                throw new Exception('Chave de acesso é obrigatória');
            }
            
            // Validar formato da chave de acesso
            if (!preg_match('/^\d{44}$/', $chave_acesso)) {
                throw new Exception('Chave de acesso deve ter exatamente 44 dígitos numéricos');
            }
            
            // 1) Tentar localizar NF-e já existente no banco (consulta local)
            $stmt = $conn->prepare("
                SELECT id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                       cliente_razao_social, valor_total, protocolo_autorizacao, status
                  FROM fiscal_nfe_clientes
                 WHERE chave_acesso = ? AND empresa_id = ?
                 LIMIT 1
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existente) {
                echo json_encode([
                    'success' => true,
                    'message' => 'NF-e encontrada no sistema (consulta local).',
                    'nfe_id' => $existente['id'],
                    'chave_acesso' => $existente['chave_acesso'],
                    'numero_nfe' => $existente['numero_nfe'],
                    'emitente' => $existente['cliente_razao_social'],
                    'valor_total' => $existente['valor_total'],
                    'protocolo' => $existente['protocolo_autorizacao'],
                    'status' => $existente['status'] ?: 'recebida'
                ]);
                
                logOperacao('consulta_nfe_local', "Consultou NF-e localmente: $chave_acesso", 'sucesso', $existente['id'], $_POST);
                break;
            }
            
            // 2) Consultar SEFAZ via NFePHP (NFeService)
            $service = new NFeService((int)$empresa_id);
            $resultado = $service->consultarPorChave($chave_acesso);
            
            if (!$resultado['success']) {
                echo json_encode([
                    'success' => false,
                    'error' => $resultado['message'] ?? 'Erro na consulta SEFAZ',
                    'chave_acesso' => $chave_acesso
                ]);
                logOperacao(
                    'consulta_nfe_sefaz_erro',
                    "Falha ao consultar NF-e na SEFAZ: $chave_acesso - " . ($resultado['message'] ?? ''),
                    'erro',
                    null,
                    $_POST
                );
                break;
            }
            
            $dados = $resultado['data'] ?? [];
            
            // Inserir registro mínimo em fiscal_nfe_clientes após consulta bem-sucedida
            // (sem depender de coluna tipo_operacao, que pode não existir na base atual)
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, valor_total, status,
                    observacoes, protocolo_autorizacao, created_at, updated_at
                ) VALUES (
                    :empresa_id, :numero_nfe, :serie_nfe, :chave_acesso, :data_emissao,
                    :cliente_razao_social, :valor_total, :status,
                    :observacoes, :protocolo_autorizacao, NOW(), NOW()
                )
            ");
            
            // Tentar obter número da NF-e; se não vier da SEFAZ, extrai da chave de acesso (posições 26 a 34)
            $numero_nfe = $dados['numero_nfe'] ?? null;
            if (empty($numero_nfe) && preg_match('/^\d{44}$/', $chave_acesso)) {
                $numero_nfe = ltrim(substr($chave_acesso, 25, 9), '0'); // 9 dígitos da chave, sem zeros à esquerda
                if ($numero_nfe === '') {
                    $numero_nfe = substr($chave_acesso, 25, 9); // garante algo
                }
            }
            $protocolo = $dados['protocolo'] ?? null;
            // Valor total pode não vir na consulta de protocolo; garante valor 0.00 para não violar NOT NULL
            $valor_total = $dados['valor_total'] ?? 0.00;
            if ($valor_total === null || $valor_total === '') {
                $valor_total = 0.00;
            }
            
            $stmt->execute([
                ':empresa_id' => $empresa_id,
                ':numero_nfe' => $numero_nfe,
                ':serie_nfe' => null,
                ':chave_acesso' => $chave_acesso,
                ':data_emissao' => date('Y-m-d'),
                ':cliente_razao_social' => null,
                ':valor_total' => $valor_total,
                ':status' => 'consultada_sefaz',
                ':observacoes' => $observacoes,
                ':protocolo_autorizacao' => $protocolo
            ]);
            
            $nfe_id = $conn->lastInsertId();

            // Tentar baixar XML completo pela Distribuição DFe (destinatário) e atualizar registro
            $xml_completo = $service->baixarXmlPorChave($chave_acesso);
            $emitente_atualizado = null;
            $valor_atualizado = null;
            $numero_resposta = $numero_nfe;
            $status_resposta = 'consultada_sefaz';
            if ($xml_completo) {
                fiscal_aplicarXmlNfeRecebidaNoBanco($conn, $empresa_id, $nfe_id, $xml_completo, true);
                $status_resposta = 'recebida';
                $rowAt = $conn->prepare('SELECT cliente_razao_social, valor_total, numero_nfe FROM fiscal_nfe_clientes WHERE id = ? AND empresa_id = ? LIMIT 1');
                $rowAt->execute([$nfe_id, $empresa_id]);
                $rUp = $rowAt->fetch(PDO::FETCH_ASSOC);
                if ($rUp) {
                    $emitente_atualizado = $rUp['cliente_razao_social'] ?? null;
                    $valor_atualizado = isset($rUp['valor_total']) ? (float)$rUp['valor_total'] : null;
                    if (!empty($rUp['numero_nfe'])) {
                        $numero_resposta = $rUp['numero_nfe'];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => $resultado['message'] ?? 'NF-e consultada na SEFAZ.',
                'nfe_id' => $nfe_id,
                'chave_acesso' => $chave_acesso,
                'numero_nfe' => $numero_resposta,
                'emitente' => $emitente_atualizado ?? $dados['emitente'] ?? null,
                'valor_total' => $valor_atualizado !== null ? $valor_atualizado : $valor_total,
                'protocolo' => $protocolo,
                'status' => $status_resposta,
                'xml_baixado' => !empty($xml_completo)
            ]);
            
            logOperacao(
                'recebimento_nfe_sefaz',
                "Consultou NF-e na SEFAZ: $chave_acesso",
                'sucesso',
                $nfe_id,
                $_POST,
                $resultado
            );
            break;

        case 'sincronizar_nfe_cnpj':
            fiscal_api_rate_limit_or_json_429('fiscal_sincronizar_nfe_cnpj', 8, 600);
            // Buscar automaticamente todas as NF-e do CNPJ (destinatário) via Distribuição DFe
            $forcar_zero = !empty($_POST['forcar_zero']) || !empty($_GET['forcar_zero']);
            $conn->exec("
                CREATE TABLE IF NOT EXISTS fiscal_distribuicao_nsu (
                    empresa_id INT UNSIGNED NOT NULL PRIMARY KEY,
                    ult_nsu VARCHAR(15) NOT NULL DEFAULT '0',
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $ultNSU = 0;
            if (!$forcar_zero) {
                $stmt = $conn->prepare("SELECT ult_nsu FROM fiscal_distribuicao_nsu WHERE empresa_id = ?");
                $stmt->execute([$empresa_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $ultNSU = $row ? (int)$row['ult_nsu'] : 0;
            }

            $service = new NFeService($empresa_id);
            $inseridas = 0;
            // Para evitar cStat=656 (consumo indevido) por excesso de consultas dentro
            // de um único "clique" do usuário, fazemos apenas 1 rodada por sincronização.
            $maxRodadas = 1;
            $has_xml_col = false;
            try {
                $conn->query("SELECT xml_nfe FROM fiscal_nfe_clientes LIMIT 1");
                $has_xml_col = true;
            } catch (Throwable $e) {}

            $lastCstat = null;
            $lastXMotivo = null;
            $lastNumDocZip = null;
            $lastNumResNFe = null;

            for ($r = 0; $r < $maxRodadas; $r++) {
                $resp = $service->listarChavesPorDistribuicao($ultNSU);
                if (!$resp['success']) {
                    echo json_encode([
                        'success' => false,
                        'error' => $resp['message'],
                        'inseridas' => $inseridas,
                        'ult_nsu' => $ultNSU
                    ]);
                    exit;
                }
                $ultNSU = (int)($resp['ultNSU'] ?? $ultNSU);
                $chaves = $resp['chaves'] ?? [];
                $lastCstat = $resp['cStat'] ?? null;
                $lastXMotivo = $resp['xMotivo'] ?? null;
                $lastNumDocZip = $resp['numDocZip'] ?? null;
                $lastNumResNFe = $resp['numResNFe'] ?? null;
                foreach ($chaves as $chave) {
                    $stmt = $conn->prepare("SELECT id FROM fiscal_nfe_clientes WHERE chave_acesso = ? AND empresa_id = ?");
                    $stmt->execute([$chave, $empresa_id]);
                    if ($stmt->fetch()) continue;
                    $xml_completo = $service->baixarXmlPorChave($chave);
                    if (!$xml_completo) continue;
                    $numero_nfe = preg_match('/^\d{44}$/', $chave) ? ltrim(substr($chave, 25, 9), '0') : '0';
                    if ($numero_nfe === '') $numero_nfe = substr($chave, 25, 9);
                    $stmt = $conn->prepare("
                        INSERT INTO fiscal_nfe_clientes (
                            empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                            cliente_razao_social, valor_total, status, observacoes, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $empresa_id, $numero_nfe, null, $chave, date('Y-m-d'),
                        null, 0.00, 'consultada_sefaz', 'Sincronizado pelo CNPJ'
                    ]);
                    $nfe_id = $conn->lastInsertId();
                    if ($has_xml_col) {
                        fiscal_aplicarXmlNfeRecebidaNoBanco($conn, $empresa_id, $nfe_id, $xml_completo, true);
                    } else {
                        salvarItensNFeDoXml($conn, $nfe_id, $xml_completo);
                    }
                    $inseridas++;
                }
                $cStatAtual = $resp['cStat'] ?? '';
                // 656 = Consumo Indevido (ex.: ultNSU=0 usado em excesso). Não atualizar NSU; usuário deve aguardar ~1h.
                if ($cStatAtual !== '656') {
                    $stmt = $conn->prepare("INSERT INTO fiscal_distribuicao_nsu (empresa_id, ult_nsu, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE ult_nsu = ?, updated_at = NOW()");
                    $stmt->execute([$empresa_id, (string)$ultNSU, (string)$ultNSU]);
                } else {
                    // Mesmo com 656, a SEFAZ pode retornar ultNSU na resposta.
                    // Gravamos o valor retornado (quando presente) para a próxima tentativa após ~1h.
                    if (isset($resp['ultNSU'])) {
                        $ultNSU656 = (string)$resp['ultNSU'];
                        $stmt = $conn->prepare("INSERT INTO fiscal_distribuicao_nsu (empresa_id, ult_nsu, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE ult_nsu = ?, updated_at = NOW()");
                        $stmt->execute([$empresa_id, $ultNSU656, $ultNSU656]);
                    }
                }
                if ($cStatAtual === '656') {
                    $lastCstat = '656';
                    $lastXMotivo = $resp['xMotivo'] ?? 'Consumo Indevido. Use ultNSU nas solicitações subsequentes. Tente após 1 hora.';
                    break;
                }
                if ($cStatAtual === '139' || empty($chaves)) {
                    break;
                }
            }

            $msg = $inseridas > 0
                ? "Sincronização concluída. $inseridas NF-e(s) incluída(s)."
                : 'Nenhuma NF-e nova encontrada para o seu CNPJ.';
            $debug = [];
            if ($inseridas === 0 && ($lastCstat !== null || $lastXMotivo !== null)) {
                $debug['cStat'] = $lastCstat;
                $debug['xMotivo'] = $lastXMotivo;
                $debug['ult_nsu'] = (string)$ultNSU;
                if ($lastNumDocZip !== null) $debug['numDocZip'] = $lastNumDocZip;
                if ($lastNumResNFe !== null) $debug['numResNFe'] = $lastNumResNFe;
                if ($lastCstat === '656') {
                    $stmt = $conn->prepare("SELECT ult_nsu FROM fiscal_distribuicao_nsu WHERE empresa_id = ?");
                    $stmt->execute([$empresa_id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $debug['ult_nsu_gravado'] = $row ? $row['ult_nsu'] : null;
                    $debug['dica'] = 'Aguarde cerca de 1 hora e use apenas "Buscar NF-e do meu CNPJ" (sem "desde o início") para buscar notas novas.';
                }
            }
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'inseridas' => $inseridas,
                'ult_nsu' => (string)$ultNSU,
                'sefaz' => $debug
            ]);
            logOperacao('sincronizar_nfe_cnpj', "Sincronizou NF-e pelo CNPJ: $inseridas nova(s)", $inseridas > 0 ? 'sucesso' : 'info', null, ['inseridas' => $inseridas]);
            break;
            
        case 'criar_cte':
            fiscal_api_rate_limit_or_json_429('fiscal_criar_cte', 20, 300);
            // CRIAR CT-e (Conhecimento de Transporte Eletrônico)
            $nfe_ids_raw = $_POST['nfe_ids'] ?? [];
            if (is_string($nfe_ids_raw)) {
                $dec = json_decode($nfe_ids_raw, true);
                if (is_array($dec)) {
                    $nfe_ids_raw = $dec;
                }
            }
            $nfe_ids = array_values(array_unique(array_filter(array_map('intval', (array)$nfe_ids_raw), function ($v) {
                return $v > 0;
            })));

            $veiculo_id = isset($_POST['veiculo_id']) ? (int)$_POST['veiculo_id'] : 0;
            $motorista_id = isset($_POST['motorista_id']) ? (int)$_POST['motorista_id'] : 0;
            $origem = $_POST['origem'] ?? '';
            $destino = $_POST['destino'] ?? '';
            $valor_frete = isset($_POST['valor_frete']) ? (float)$_POST['valor_frete'] : 0.0;
            $peso_total = isset($_POST['peso_total']) ? (float)$_POST['peso_total'] : 0.0;
            $volumes_total = isset($_POST['volumes_total']) ? (int)$_POST['volumes_total'] : 0;

            $nfes = [];
            if (!empty($nfe_ids)) {
                $phNfe = str_repeat('?,', count($nfe_ids) - 1) . '?';
                $stmt = $conn->prepare("
                    SELECT id, status, cliente_cnpj, cliente_razao_social
                    FROM fiscal_nfe_clientes
                    WHERE id IN ($phNfe) AND empresa_id = ?
                ");
                $stmt->execute(array_merge($nfe_ids, [$empresa_id]));
                $nfes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $dadosValidacaoCte = [
                'modo' => 'rascunho',
                'nfe_ids' => $nfe_ids,
                'nfes' => $nfes,
                'veiculo_id' => $veiculo_id,
                'motorista_id' => $motorista_id,
                'valor_frete' => $valor_frete,
                'peso_total' => $peso_total,
                'volumes_total' => $volumes_total,
                'origem' => $origem,
                'destino' => $destino,
            ];
            $resultadoValidacaoCte = validarCTeRegras($dadosValidacaoCte);
            registrarLogValidacaoCTe($conn, (int)$empresa_id, null, 'criar_cte', $resultadoValidacaoCte, $dadosValidacaoCte);
            if (!$resultadoValidacaoCte['valido']) {
                $mensagensCte = array_map(function ($e) {
                    return ($e['id'] ?? $e['codigo'] ?? 'ERRO') . ': ' . ($e['mensagem'] ?? '');
                }, $resultadoValidacaoCte['erros'] ?? []);
                echo json_encode([
                    'success' => false,
                    'error' => 'Validação fiscal do CT-e falhou.',
                    'validation_version' => $resultadoValidacaoCte['versao_regra'] ?? null,
                    'erros' => $resultadoValidacaoCte['erros'] ?? [],
                    'warnings' => $resultadoValidacaoCte['warnings'] ?? [],
                    'detalhes' => $mensagensCte,
                ], JSON_UNESCAPED_UNICODE);
                logOperacao('validacao_cte_bloqueada', 'Validação CT-e (criar) bloqueada', 'erro', null, $_POST, [
                    'versao_regra' => $resultadoValidacaoCte['versao_regra'] ?? null,
                    'erros' => $resultadoValidacaoCte['erros'] ?? [],
                    'warnings' => $resultadoValidacaoCte['warnings'] ?? [],
                ]);
                break;
            }

            $conn->beginTransaction();
            try {
                $numero_cte = getProximoNumero('CTE', '1');
                $chave_acesso = gerarChaveAcesso('CTE', $numero_cte, '1');

                $stmt = $conn->prepare("
                    INSERT INTO fiscal_cte (
                        empresa_id, numero_cte, serie_cte, chave_acesso, data_emissao,
                        natureza_operacao, valor_total, peso_carga, volumes_carga,
                        origem, destino, veiculo_id, motorista_id, status,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empresa_id, $numero_cte, '1', $chave_acesso, date('Y-m-d'),
                    'Transporte de mercadorias', $valor_frete, $peso_total, $volumes_total,
                    $origem, $destino, $veiculo_id, $motorista_id, 'rascunho',
                    date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
                ]);
                $cte_id = (int)$conn->lastInsertId();

                foreach ($nfe_ids as $nfe_id) {
                    $stmt = $conn->prepare("
                        UPDATE fiscal_nfe_clientes
                        SET cte_id = ?, status = 'em_transporte', updated_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$cte_id, date('Y-m-d H:i:s'), $nfe_id]);
                }

                $phTom = str_repeat('?,', count($nfe_ids) - 1) . '?';
                $stmtTomador = $conn->prepare("
                    SELECT cliente_cnpj, cliente_razao_social
                    FROM fiscal_nfe_clientes
                    WHERE id IN ($phTom) AND empresa_id = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $stmtTomador->execute(array_merge($nfe_ids, [$empresa_id]));
                $nfeTomador = $stmtTomador->fetch(PDO::FETCH_ASSOC) ?: [];
                $tomador_cnpj = preg_replace('/\D/', '', (string)($nfeTomador['cliente_cnpj'] ?? ''));
                $tomador_nome = trim((string)($nfeTomador['cliente_razao_social'] ?? ''));

                $placa = '';
                $stmtVeiculo = $conn->prepare('SELECT placa FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1');
                $stmtVeiculo->execute([$veiculo_id, $empresa_id]);
                $placa = (string)($stmtVeiculo->fetchColumn() ?: '');

                $motorista_nome = '';
                $motorista_cpf = '';
                $stmtMotorista = $conn->prepare('SELECT nome, cpf FROM motoristas WHERE id = ? AND empresa_id = ? LIMIT 1');
                $stmtMotorista->execute([$motorista_id, $empresa_id]);
                $m = $stmtMotorista->fetch(PDO::FETCH_ASSOC) ?: [];
                $motorista_nome = (string)($m['nome'] ?? '');
                $motorista_cpf = preg_replace('/\D/', '', (string)($m['cpf'] ?? ''));

                $inf_complementar = trim(
                    'Placa: ' . $placa .
                    '; Motorista: ' . $motorista_nome .
                    ($motorista_cpf ? ('; CPF: ' . $motorista_cpf) : '')
                );

                $stmtEx = $conn->prepare('SELECT id FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1');
                $stmtEx->execute([$cte_id]);
                $existe = $stmtEx->fetch(PDO::FETCH_ASSOC);

                $icms_picms = 12.00;
                $valor_prestacao = (float)$valor_frete;
                $valor_receber = (float)$valor_frete;
                $icms_vbc = $valor_prestacao;
                $icms_vicms = round($valor_prestacao * ($icms_picms / 100), 2);

                if ($existe) {
                    $stmtUp = $conn->prepare("
                        UPDATE fiscal_cte_itens SET
                            tomador_cnpj = ?, tomador_nome = ?,
                            valor_prestacao = ?, valor_receber = ?,
                            comp_nome = ?, comp_valor = ?,
                            icms_cst = ?, icms_vbc = ?, icms_picms = ?, icms_vicms = ?,
                            valor_carga = ?, produto_predominante = ?, inf_complementar = ?,
                            updated_at = NOW()
                        WHERE cte_id = ?
                    ");
                    $stmtUp->execute([
                        $tomador_cnpj !== '' ? $tomador_cnpj : null,
                        $tomador_nome !== '' ? $tomador_nome : null,
                        $valor_prestacao,
                        $valor_receber,
                        'FRETE VALOR BASE',
                        $valor_prestacao,
                        '00',
                        $icms_vbc,
                        $icms_picms,
                        $icms_vicms,
                        (float)$peso_total,
                        'CARGA GERAL',
                        $inf_complementar !== '' ? $inf_complementar : null,
                        $cte_id,
                    ]);
                } else {
                    $stmtIns = $conn->prepare("
                        INSERT INTO fiscal_cte_itens (
                            cte_id, tomador_cnpj, tomador_nome,
                            valor_prestacao, valor_receber,
                            comp_nome, comp_valor,
                            icms_cst, icms_vbc, icms_picms, icms_vicms,
                            valor_carga, produto_predominante, inf_complementar
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([
                        $cte_id,
                        $tomador_cnpj !== '' ? $tomador_cnpj : null,
                        $tomador_nome !== '' ? $tomador_nome : null,
                        $valor_prestacao,
                        $valor_receber,
                        'FRETE VALOR BASE',
                        $valor_prestacao,
                        '00',
                        $icms_vbc,
                        $icms_picms,
                        $icms_vicms,
                        (float)$peso_total,
                        'CARGA GERAL',
                        $inf_complementar !== '' ? $inf_complementar : null,
                    ]);
                }

                $conn->commit();
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }

            echo json_encode([
                'success' => true,
                'message' => 'CT-e criado com sucesso!',
                'cte_id' => $cte_id,
                'numero_cte' => $numero_cte,
                'status' => 'rascunho',
                'warnings' => $resultadoValidacaoCte['warnings'] ?? [],
                'validation_version' => $resultadoValidacaoCte['versao_regra'] ?? null,
            ], JSON_UNESCAPED_UNICODE);

            logOperacao('criacao_cte', "Criou CT-e #$numero_cte", 'sucesso', $cte_id, $_POST);
            break;

        case 'emitir_nfe_sefaz':
            fiscal_api_rate_limit_or_json_429('fiscal_emitir_nfe_sefaz', 15, 300);
            /**
             * Emissão própria NF-e 55.
             * Itens: CRT 3 (config fiscal) → prod + imposto com ICMS00, PISAliq, COFINSAliq; CRT 1/2 → ICMSSN + PIS/COFINS CST 07.
             * pedido: dest, itens[] (cProd, cEAN, xProd, NCM, CFOP, uCom, qCom, vUnCom, vProd, infAdProd, indTot, icms_*, pis_*, cofins_*),
             * natOp, serie, nNF (opc.), crt, csosn, pICMS_padrao, pPIS_padrao, pCOFINS_padrao, imposto_regime_normal (CRT 2), tPag, emitente.enderEmit.
             */
            $input = [];
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($ct, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true);
                if (!is_array($input)) {
                    $input = [];
                }
            }
            if (empty($input)) {
                $input = $_POST;
            }
            if (!empty($input['pedido_json']) && is_string($input['pedido_json'])) {
                $pj = json_decode($input['pedido_json'], true);
                if (is_array($pj)) {
                    $input = array_merge($input, ['pedido' => $pj]);
                }
            }
            $pedido = (isset($input['pedido']) && is_array($input['pedido'])) ? $input['pedido'] : $input;

            $stmtCfg = $conn->prepare("
                SELECT fc.*, c.nome AS municipio_nome, c.uf AS cidade_uf
                FROM fiscal_config_empresa fc
                LEFT JOIN cidades c ON c.codigo_ibge = fc.codigo_municipio
                WHERE fc.empresa_id = ?
                LIMIT 1
            ");
            $stmtCfg->execute([$empresa_id]);
            $cfgRow = $stmtCfg->fetch(PDO::FETCH_ASSOC);
            if (!$cfgRow) {
                throw new Exception('Configuração fiscal não encontrada.');
            }

            $cfg = $cfgRow;
            $cfg['tpAmb'] = ($cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao' ? 1 : 2;
            if (!empty($cfg['cidade_uf'])) {
                $cfg['sigla_uf'] = $cfg['cidade_uf'];
            }
            if (empty($cfg['sigla_uf']) && !empty($cfg['codigo_municipio'])) {
                $stmtUf = $conn->prepare('SELECT uf FROM cidades WHERE codigo_ibge = ? LIMIT 1');
                $stmtUf->execute([(string)$cfg['codigo_municipio']]);
                $ufCol = $stmtUf->fetchColumn();
                if ($ufCol) {
                    $cfg['sigla_uf'] = $ufCol;
                }
            }

            $serie = (int)($pedido['serie'] ?? 1);
            $nNF = (int)($pedido['nNF'] ?? 0);
            if ($nNF < 1) {
                $nNF = (int) getProximoNumero('NFE', (string)$serie);
            }
            $pedido['nNF'] = $nNF;
            $pedido['serie'] = $serie;

            $built = NFeEmissaoBuilder::montarXml($cfg, $pedido);
            if (!empty($built['errors'])) {
                throw new Exception(implode(' ', $built['errors']));
            }

            $nfeService = new NFeService((int)$empresa_id);
            $res = $nfeService->emitirNFeAutorizacao($built['xml']);

            $dest = $pedido['dest'] ?? [];
            $cnpjD = preg_replace('/\D/', '', (string)($dest['CNPJ'] ?? ''));
            $cpfD = preg_replace('/\D/', '', (string)($dest['CPF'] ?? ''));
            $nomeD = (string)($dest['xNome'] ?? '');
            $chaveFin = $res['chave'] ?? $built['chave'];

            try {
                $stmtIns = $conn->prepare("
                    INSERT INTO fiscal_nfe_emitidas (
                        empresa_id, serie, numero_nfe, chave_acesso, protocolo_autorizacao, valor_total, status,
                        destinatario_cnpj, destinatario_cpf, destinatario_nome,
                        xml_nfe, xml_retorno_sefaz, motivo_rejeicao, data_emissao
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
                ");
                $stmtIns->execute([
                    $empresa_id,
                    $serie,
                    $nNF,
                    $chaveFin,
                    $res['protocolo'] ?? null,
                    $built['vNF'],
                    !empty($res['success']) ? 'autorizada' : 'rejeitada',
                    strlen($cnpjD) === 14 ? $cnpjD : null,
                    strlen($cpfD) === 11 ? $cpfD : null,
                    $nomeD,
                    $res['xml_signed'] ?? null,
                    is_string($res['response_xml'] ?? null) ? $res['response_xml'] : null,
                    !empty($res['success']) ? null : ($res['message'] ?? null),
                ]);
            } catch (Throwable $e) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log('emitir_nfe_sefaz: persistência fiscal_nfe_emitidas: ' . $e->getMessage());
                }
            }

            echo json_encode([
                'success' => (bool)($res['success'] ?? false),
                'message' => $res['message'] ?? '',
                'chave_acesso' => $chaveFin,
                'protocolo' => $res['protocolo'] ?? null,
                'cStat_lote' => $res['cStat_lote'] ?? null,
                'cStat_prot' => $res['cStat_prot'] ?? null,
                'numero_nfe' => $nNF,
                'serie' => $serie,
                'valor_total' => $built['vNF'],
            ]);

            logOperacao('emitir_nfe_sefaz', 'Emissão NF-e SEFAZ', !empty($res['success']) ? 'sucesso' : 'erro', null, $pedido);
            break;

        case 'emitir_cte_sefaz':
            fiscal_api_rate_limit_or_json_429('fiscal_emitir_cte_sefaz', 15, 300);
            // AUTORIZAR CT-e via SEFAZ (modelo 57) usando sped-cte
            $cte_id = (int)($_POST['cte_id'] ?? $_POST['id'] ?? 0);
            if ($cte_id <= 0) {
                throw new Exception('ID do CT-e (cte_id) é obrigatório.');
            }

            $stmt = $conn->prepare("SELECT * FROM fiscal_cte WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$cte_id, $empresa_id]);
            $cte = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cte) {
                throw new Exception('CT-e não encontrado.');
            }

            if (!CTeService::disponivel()) {
                throw new Exception('Pacote nfephp-org/sped-cte não instalado no servidor. Execute composer require nfephp-org/sped-cte');
            }

            // Garantir chave de acesso
            $chave = preg_replace('/\D/', '', (string)($cte['chave_acesso'] ?? ''));
            if (empty($chave) || strlen($chave) !== 44) {
                $numero_cte = (string)($cte['numero_cte'] ?? '');
                $serie_cte = (string)($cte['serie_cte'] ?? '1');
                if ($numero_cte === '') {
                    throw new Exception('CT-e sem chave_acesso válida e sem numero_cte para gerar a chave.');
                }
                $chave = gerarChaveAcesso('CTE', $numero_cte, $serie_cte);
                try {
                    $up = $conn->prepare("UPDATE fiscal_cte SET chave_acesso = ? WHERE id = ? AND empresa_id = ?");
                    $up->execute([$chave, $cte_id, $empresa_id]);
                    $cte['chave_acesso'] = $chave;
                } catch (Throwable $e) {}
            }

            $stmtItVal = $conn->prepare('SELECT * FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1');
            $stmtItVal->execute([$cte_id]);
            $fiscal_cte_itens_val = $stmtItVal->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmtNfVal = $conn->prepare('
                SELECT id, status, cliente_cnpj, cliente_razao_social
                FROM fiscal_nfe_clientes
                WHERE cte_id = ? AND empresa_id = ?
            ');
            $stmtNfVal->execute([$cte_id, $empresa_id]);
            $nfes_emit_val = $stmtNfVal->fetchAll(PDO::FETCH_ASSOC);
            $nfe_ids_emit_val = array_values(array_filter(array_map('intval', array_column($nfes_emit_val, 'id'))));

            $peso_cte_val = (float)($cte['peso_carga'] ?? $cte['peso_total'] ?? 0);

            $dadosValidacaoEmitCte = [
                'modo' => 'emissao',
                'nfe_ids' => $nfe_ids_emit_val,
                'nfes' => $nfes_emit_val,
                'veiculo_id' => (int)($cte['veiculo_id'] ?? 0),
                'motorista_id' => (int)($cte['motorista_id'] ?? 0),
                'valor_frete' => (float)($cte['valor_total'] ?? 0),
                'peso_total' => $peso_cte_val,
                'origem' => trim((string)($cte['origem'] ?? '')),
                'destino' => trim((string)($cte['destino'] ?? '')),
                'fiscal_cte_itens' => $fiscal_cte_itens_val,
                'tem_fiscal_cte_itens' => !empty($fiscal_cte_itens_val),
            ];
            $resultadoValidacaoEmitCte = validarCTeRegras($dadosValidacaoEmitCte);
            registrarLogValidacaoCTe($conn, (int)$empresa_id, $cte_id, 'emitir_cte_sefaz', $resultadoValidacaoEmitCte, $dadosValidacaoEmitCte);
            if (!$resultadoValidacaoEmitCte['valido']) {
                $mensagensEmit = array_map(function ($e) {
                    return ($e['id'] ?? $e['codigo'] ?? 'ERRO') . ': ' . ($e['mensagem'] ?? '');
                }, $resultadoValidacaoEmitCte['erros'] ?? []);
                echo json_encode([
                    'success' => false,
                    'error' => 'Validação fiscal do CT-e falhou. Ajuste os dados antes de enviar à SEFAZ.',
                    'validation_version' => $resultadoValidacaoEmitCte['versao_regra'] ?? null,
                    'erros' => $resultadoValidacaoEmitCte['erros'] ?? [],
                    'warnings' => $resultadoValidacaoEmitCte['warnings'] ?? [],
                    'detalhes' => $mensagensEmit,
                ], JSON_UNESCAPED_UNICODE);
                logOperacao('validacao_cte_emitir_bloqueada', 'Validação CT-e (emitir) bloqueada', 'erro', $cte_id, $_POST, [
                    'versao_regra' => $resultadoValidacaoEmitCte['versao_regra'] ?? null,
                    'erros' => $resultadoValidacaoEmitCte['erros'] ?? [],
                    'warnings' => $resultadoValidacaoEmitCte['warnings'] ?? [],
                ]);
                break;
            }

            // Carregar dados da empresa (para montar XML)
            $stmtEmp = $conn->prepare("SELECT * FROM empresa_clientes WHERE id = ? LIMIT 1");
            $stmtEmp->execute([$empresa_id]);
            $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            if (!$empresa) {
                $empresa = ['cnpj' => '', 'razao_social' => 'Transportadora', 'nome_fantasia' => '', 'inscricao_estadual' => '', 'endereco' => '', 'cep' => '', 'cidade' => '', 'estado' => 'PR'];
            }

            // Montar cteProc XML
            require_once __DIR__ . '/../includes/CteXmlHelper.php';
            $xml_proc = montarCteProcXml($conn, $cte, $empresa);
            if (empty($xml_proc)) {
                throw new Exception('Erro ao gerar XML (cteProc) do CT-e.');
            }

            $cteService = new CTeService((int)$empresa_id);

            // Enviar autorização
            $envio = $cteService->emitirCTe($xml_proc);
            if (empty($envio['success'])) {
                throw new Exception('Falha ao enviar CT-e para SEFAZ: ' . ($envio['message'] ?? 'erro desconhecido'));
            }

            // Salvar XML assinado para auditoria
            try {
                if (!empty($envio['signed_xml'])) {
                    $up = $conn->prepare("UPDATE fiscal_cte SET xml_cte = ?, updated_at = ? WHERE id = ? AND empresa_id = ?");
                    $up->execute([$envio['signed_xml'], date('Y-m-d H:i:s'), $cte_id, $empresa_id]);
                }
            } catch (Throwable $e) {}

            // Consultar para obter cStat/protocolo após o envio
            $consulta = $cteService->consultarPorChave($chave);
            if (!$consulta['success']) {
                // Envio pode ter sido aceito na recepção, mas a autorização pode não estar disponível ainda.
                echo json_encode([
                    'success' => true,
                    'message' => 'CT-e enviado. Autorização ainda não consultável no momento.',
                    'status' => 'pendente',
                    'cte_id' => $cte_id,
                    'chave_acesso' => $chave,
                    'consulta' => $consulta,
                ]);
                logOperacao('emitir_cte_sefaz', "Enviou CT-e #{$cte['numero_cte']} mas consulta falhou/pendente", 'info', $cte_id, $_POST, ['consulta' => $consulta]);
                break;
            }

            $dados = $consulta['data'] ?? [];
            $protocolo = $dados['protocolo'] ?? null;
            $cStat = (string)($dados['cStat'] ?? '');
            $status = ($cStat === '100' || $cStat === '150') ? 'autorizado' : 'pendente';

            try {
                $up = $conn->prepare("
                    UPDATE fiscal_cte
                    SET protocolo_autorizacao = ?, status = ?, data_emissao = COALESCE(data_emissao, ?), updated_at = ?
                    WHERE id = ? AND empresa_id = ?
                ");
                $up->execute([
                    $protocolo,
                    $status,
                    date('Y-m-d'),
                    date('Y-m-d H:i:s'),
                    $cte_id,
                    $empresa_id
                ]);
            } catch (Throwable $e) {}

            // Se autorizado, tenta baixar XML completo da SEFAZ (cteProc) e importar
            $xml_baixado = false;
            try {
                $xml_completo = $cteService->baixarXmlPorChave($chave);
                if (!empty($xml_completo)) {
                    $xml_baixado = true;
                    require_once __DIR__ . '/../includes/CteImportHelper.php';
                    importarXmlCteProc($conn, $xml_completo, $empresa_id);
                }
            } catch (Throwable $e) {}

            echo json_encode([
                'success' => true,
                'message' => $status === 'autorizado' ? 'CT-e autorizado e XML importado (se disponível).' : 'CT-e recebido para autorização (pendente).',
                'cte_id' => $cte_id,
                'numero_cte' => $cte['numero_cte'] ?? null,
                'chave_acesso' => $chave,
                'status' => $status,
                'protocolo' => $protocolo,
                'cStat' => $cStat,
                'xml_baixado' => $xml_baixado,
            ]);

            logOperacao('emitir_cte_sefaz', "Autorizou/enviou CT-e #{$cte['numero_cte']}", 'sucesso', $cte_id, $_POST, ['cStat' => $cStat, 'protocolo' => $protocolo]);
            break;

        case 'consultar_cte_sefaz':
            // Consultar CT-e por chave na SEFAZ (igual NF-e): consulta situação + tenta baixar XML e gravar fiscal_cte + fiscal_cte_itens
            $chave_acesso = preg_replace('/\D/', '', $_POST['chave_acesso'] ?? '');
            if (strlen($chave_acesso) !== 44) {
                throw new Exception('Chave de acesso deve ter 44 dígitos.');
            }
            $stmt = $conn->prepare("SELECT id, numero_cte, status FROM fiscal_cte WHERE chave_acesso = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$chave_acesso, $empresa_id]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                echo json_encode([
                    'success' => true,
                    'message' => 'CT-e já consta no sistema.',
                    'cte_id' => $existente['id'],
                    'numero_cte' => $existente['numero_cte'],
                    'status' => $existente['status'],
                ]);
                break;
            }

            $serie_cte = substr($chave_acesso, 22, 3);
            $numero_cte = ltrim(substr($chave_acesso, 25, 9), '0');
            if ($numero_cte === '') $numero_cte = substr($chave_acesso, 25, 9);

            if (CTeService::disponivel()) {
                try {
                    $cteService = new CTeService((int)$empresa_id);
                    $resultado = $cteService->consultarPorChave($chave_acesso);
                    if (!$resultado['success']) {
                        logCteDebug('CT-e consulta SEFAZ falhou', ['chave' => $chave_acesso, 'message' => $resultado['message'] ?? '']);
                        echo json_encode([
                            'success' => false,
                            'error' => $resultado['message'] ?? 'Erro na consulta SEFAZ',
                            'chave_acesso' => $chave_acesso,
                        ]);
                        logOperacao('consultar_cte_sefaz_erro', "Falha SEFAZ: $chave_acesso - " . ($resultado['message'] ?? ''), 'erro', null, $_POST);
                        break;
                    }
                    $dados = $resultado['data'] ?? [];
                    $protocolo = $dados['protocolo'] ?? null;
                    $status = (isset($dados['cStat']) && ($dados['cStat'] === '100' || $dados['cStat'] === '150')) ? 'autorizado' : 'pendente';

                    $stmt = $conn->prepare("
                        INSERT INTO fiscal_cte (
                            empresa_id, numero_cte, serie_cte, chave_acesso, data_emissao,
                            natureza_operacao, valor_total, protocolo_autorizacao, status, observacoes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $empresa_id, $numero_cte, $serie_cte, $chave_acesso, date('Y-m-d'),
                        null, 0.00, $protocolo, $status,
                        'Consultado na SEFAZ. Tentando baixar XML completo...'
                    ]);
                    $cte_id = (int)$conn->lastInsertId();

                    $xml_completo = $cteService->baixarXmlPorChave($chave_acesso);
                    logCteDebug('CT-e consulta + download', [
                        'chave' => $chave_acesso,
                        'consulta_ok' => true,
                        'cStat' => $dados['cStat'] ?? null,
                        'protocolo' => $protocolo,
                        'xml_baixado' => !empty($xml_completo),
                        'motivo' => empty($xml_completo) ? 'Distribuição DFe não retornou XML (CNPJ pode não ser interessado no CT-e)' : 'OK',
                    ]);
                    if ($xml_completo) {
                        require_once __DIR__ . '/../includes/CteImportHelper.php';
                        $result = importarXmlCteProc($conn, $xml_completo, $empresa_id);
                        $cte_id = $result['cte_id'];
                        $msg = 'CT-e consultado na SEFAZ e XML baixado. Dados e itens gravados.';
                    } else {
                        $msg = 'CT-e consultado na SEFAZ. O XML completo não está disponível para este CNPJ (Distribuição DFe). Use "Importar XML" se tiver o arquivo.';
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $msg,
                        'cte_id' => $cte_id,
                        'numero_cte' => $numero_cte,
                        'status' => $status,
                        'xml_baixado' => !empty($xml_completo),
                    ]);
                    logOperacao('consultar_cte_sefaz', "Consultou CT-e na SEFAZ: $chave_acesso", 'sucesso', $cte_id, $_POST);
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, 'Pacote nfephp-org/sped-cte') !== false) {
                        $msg = 'Para consultar CT-e na SEFAZ, instale o pacote: composer require nfephp-org/sped-cte';
                    }
                    echo json_encode([
                        'success' => false,
                        'error' => $msg,
                        'chave_acesso' => $chave_acesso,
                    ]);
                    logOperacao('consultar_cte_sefaz_erro', "Erro: $chave_acesso - $msg", 'erro', null, $_POST);
                }
                break;
            }

            // Fallback sem pacote: apenas registra pela chave (comportamento anterior)
            $stmt = $conn->prepare("
                INSERT INTO fiscal_cte (
                    empresa_id, numero_cte, serie_cte, chave_acesso, data_emissao,
                    natureza_operacao, valor_total, status, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $empresa_id, $numero_cte, $serie_cte, $chave_acesso, date('Y-m-d'),
                'Consultado pela chave de acesso', 0.00, 'pendente',
                'Consultado pela chave. Instale nfephp-org/sped-cte para puxar dados da SEFAZ.'
            ]);
            $cte_id = $conn->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'CT-e registrado. Para puxar dados da SEFAZ, execute: composer require nfephp-org/sped-cte',
                'cte_id' => $cte_id,
                'numero_cte' => $numero_cte,
                'status' => 'pendente',
            ]);
            logOperacao('consultar_cte_sefaz', "Consultou CT-e pela chave (sem SEFAZ): $chave_acesso", 'sucesso', $cte_id, $_POST);
            break;

        case 'importar_xml_cte':
            fiscal_api_rate_limit_or_json_429('fiscal_importar_xml_cte', 25, 300);
            // Importar XML cteProc da SEFAZ: atualiza fiscal_cte e fiscal_cte_itens com dados reais
            $xml_content = $_POST['xml_content'] ?? '';
            if (isset($_POST['xml_base64']) && $xml_content === '') {
                $decoded = base64_decode($_POST['xml_base64'], true);
                if ($decoded !== false) $xml_content = $decoded;
            }
            $xml_content = trim($xml_content);
            if ($xml_content === '') {
                throw new Exception('Envie o conteúdo do XML do CT-e (campo xml_content ou xml_base64).');
            }
            if (strpos($xml_content, '<?xml') === 0) {
                // ok
            } elseif (strpos($xml_content, '<cteProc') !== false || strpos($xml_content, '<CTe') !== false) {
                // ok
            } else {
                throw new Exception('Conteúdo não parece ser um XML de CT-e (cteProc).');
            }
            require_once __DIR__ . '/../includes/CteImportHelper.php';
            $result = importarXmlCteProc($conn, $xml_content, $empresa_id);
            $cte_id = $result['cte_id'];
            $st = $conn->prepare('SELECT valor_total, origem_cidade, destino_cidade FROM fiscal_cte WHERE id = ?');
            $st->execute([$cte_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $st2 = $conn->prepare('SELECT tomador_nome, valor_prestacao FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1');
            $st2->execute([$cte_id]);
            $item = $st2->fetch(PDO::FETCH_ASSOC);
            logCteDebug('CT-e importação XML', [
                'cte_id' => $cte_id,
                'valor_total' => $row['valor_total'] ?? null,
                'origem' => $row['origem_cidade'] ?? null,
                'destino' => $row['destino_cidade'] ?? null,
                'tomador' => $item['tomador_nome'] ?? null,
                'valor_prestacao' => $item['valor_prestacao'] ?? null,
            ]);
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'cte_id' => $result['cte_id'],
            ]);
            logOperacao('importar_xml_cte', 'Importou XML CT-e cte_id=' . $result['cte_id'], 'sucesso', $result['cte_id']);
            break;

        case 'salvar_cte_itens':
            // Gravar ou atualizar fiscal_cte_itens (tomador, valores, carga, ICMS, infAdic, protocolo)
            $cte_id = (int)($_POST['cte_id'] ?? 0);
            if ($cte_id <= 0) {
                throw new Exception('ID do CT-e é obrigatório.');
            }
            $stmt = $conn->prepare("SELECT id FROM fiscal_cte WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$cte_id, $empresa_id]);
            if (!$stmt->fetch()) {
                throw new Exception('CT-e não encontrado.');
            }
            $stmt = $conn->prepare("SELECT id FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1");
            $stmt->execute([$cte_id]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            $tomador_cnpj = preg_replace('/\D/', '', $_POST['tomador_cnpj'] ?? '');
            $tomador_nome = trim($_POST['tomador_nome'] ?? '');
            $valor_prestacao = isset($_POST['valor_prestacao']) ? (float)$_POST['valor_prestacao'] : null;
            $valor_receber = isset($_POST['valor_receber']) ? (float)$_POST['valor_receber'] : null;
            $comp_nome = trim($_POST['comp_nome'] ?? '');
            $comp_valor = isset($_POST['comp_valor']) ? (float)$_POST['comp_valor'] : null;
            $icms_cst = trim($_POST['icms_cst'] ?? '00');
            $icms_vbc = isset($_POST['icms_vbc']) ? (float)$_POST['icms_vbc'] : null;
            $icms_picms = isset($_POST['icms_picms']) ? (float)$_POST['icms_picms'] : null;
            $icms_vicms = isset($_POST['icms_vicms']) ? (float)$_POST['icms_vicms'] : null;
            $valor_carga = isset($_POST['valor_carga']) ? (float)$_POST['valor_carga'] : null;
            $produto_predominante = trim($_POST['produto_predominante'] ?? '');
            $inf_complementar = trim($_POST['inf_complementar'] ?? '');
            $numero_protocolo = trim($_POST['numero_protocolo'] ?? '');
            $data_protocolo = trim($_POST['data_protocolo'] ?? '');
            $status_protocolo = trim($_POST['status_protocolo'] ?? '');
            $motivo_protocolo = trim($_POST['motivo_protocolo'] ?? '');
            $versao_aplicativo = trim($_POST['versao_aplicativo'] ?? 'SP-CTE-3.00');
            if ($existe) {
                $conn->prepare("
                    UPDATE fiscal_cte_itens SET
                    tomador_cnpj = ?, tomador_nome = ?, valor_prestacao = ?, valor_receber = ?,
                    comp_nome = ?, comp_valor = ?, icms_cst = ?, icms_vbc = ?, icms_picms = ?, icms_vicms = ?,
                    valor_carga = ?, produto_predominante = ?, inf_complementar = ?,
                    numero_protocolo = ?, data_protocolo = NULLIF(?,''), status_protocolo = ?, motivo_protocolo = ?, versao_aplicativo = ?,
                    updated_at = NOW()
                    WHERE cte_id = ?
                ")->execute([
                    $tomador_cnpj ?: null, $tomador_nome ?: null, $valor_prestacao, $valor_receber,
                    $comp_nome ?: null, $comp_valor, $icms_cst ?: null, $icms_vbc, $icms_picms, $icms_vicms,
                    $valor_carga, $produto_predominante ?: null, $inf_complementar ?: null,
                    $numero_protocolo ?: null, $data_protocolo ?: null, $status_protocolo ?: null, $motivo_protocolo ?: null, $versao_aplicativo ?: null,
                    $cte_id
                ]);
            } else {
                $conn->prepare("
                    INSERT INTO fiscal_cte_itens (cte_id, tomador_cnpj, tomador_nome, valor_prestacao, valor_receber,
                    comp_nome, comp_valor, icms_cst, icms_vbc, icms_picms, icms_vicms,
                    valor_carga, produto_predominante, inf_complementar,
                    numero_protocolo, data_protocolo, status_protocolo, motivo_protocolo, versao_aplicativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,''), ?, ?, ?)
                ")->execute([
                    $cte_id, $tomador_cnpj ?: null, $tomador_nome ?: null, $valor_prestacao, $valor_receber,
                    $comp_nome ?: null, $comp_valor, $icms_cst ?: null, $icms_vbc, $icms_picms, $icms_vicms,
                    $valor_carga, $produto_predominante ?: null, $inf_complementar ?: null,
                    $numero_protocolo ?: null, $data_protocolo ?: null, $status_protocolo ?: null, $motivo_protocolo ?: null, $versao_aplicativo ?: null
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Dados do CT-e salvos.']);
            logOperacao('salvar_cte_itens', "Salvou itens do CT-e #$cte_id", 'sucesso', $cte_id, $_POST);
            break;
            
        case 'criar_mdfe':
            fiscal_api_rate_limit_or_json_429('fiscal_criar_mdfe', 25, 300);
            // CRIAR MDF-e (Manifesto Eletrônico de Documentos Fiscais)
            $cte_ids = $_POST['cte_ids'] ?? [];
            if (!is_array($cte_ids)) {
                $cte_ids = $cte_ids !== '' && $cte_ids !== null ? [intval($cte_ids)] : [];
            } else {
                $cte_ids = array_values(array_filter(array_map('intval', $cte_ids), function($v) { return $v > 0; }));
            }
            $cte_ids = array_values(array_unique($cte_ids));
            $origem_mdfe = trim((string)($_POST['origem_mdfe'] ?? ''));
            $origem_nfe_ids_raw = $_POST['origem_nfe_ids'] ?? [];
            if (is_string($origem_nfe_ids_raw)) {
                $tmp = json_decode($origem_nfe_ids_raw, true);
                $origem_nfe_ids_raw = is_array($tmp) ? $tmp : [];
            }
            if (!is_array($origem_nfe_ids_raw)) {
                $origem_nfe_ids_raw = [];
            }
            $origem_nfe_ids = array_values(array_unique(array_filter(array_map('intval', $origem_nfe_ids_raw), function($v) {
                return $v > 0;
            })));
            $veiculo_id = $_POST['veiculo_id'] ?? null;
            $motorista_id = $_POST['motorista_id'] ?? null;
            $rota_id = $_POST['rota_id'] ?? null;
            $data_viagem = $_POST['data_viagem'] ?? date('Y-m-d');
            
            if (empty($cte_ids) && empty($origem_nfe_ids)) {
                throw new Exception('É necessário selecionar pelo menos um CT-e ou NF-e para o manifesto');
            }
            
            if (!$veiculo_id || !$motorista_id) {
                throw new Exception('Veículo e motorista são obrigatórios');
            }
            
            // Capturar campos adicionais
            $uf_inicio = $_POST['uf_inicio'] ?? null;
            $uf_fim = $_POST['uf_fim'] ?? null;
            $municipio_carregamento = $_POST['municipio_carregamento'] ?? null;
            $municipio_descarregamento = $_POST['municipio_descarregamento'] ?? null;
            $tipo_viagem = $_POST['tipo_viagem'] ?? '1';
            $observacoes = $_POST['observacoes'] ?? '';
            $tipo_emitente = trim((string)($_POST['tipo_emitente'] ?? ''));
            $tipo_transportador = trim((string)($_POST['tipo_transportador'] ?? ''));
            $modo_validacao = trim((string)($_POST['modo'] ?? 'emissao'));
            $rota_tem_pedagio = !empty($_POST['rota_tem_pedagio']) && (string)$_POST['rota_tem_pedagio'] !== '0';

            $documentos_mdfe = mdfeParseJsonAny($_POST['doc_documentos_json'] ?? '', []);
            $pagamentos_mdfe = mdfeParseJsonAny($_POST['rod_pagamentos_frete_json'] ?? '', []);
            $contratantes_mdfe = mdfeParseJsonAny($_POST['rod_contratantes_json'] ?? '', []);
            $ciot_mdfe = mdfeParseJsonAny($_POST['rod_ciot_json'] ?? '', []);
            $vales_mdfe = mdfeParseJsonAny($_POST['rod_vales_pedagio_json'] ?? '', []);
            $seguros_mdfe = mdfeParseJsonAny($_POST['seg_seguros_json'] ?? '', []);
            $produtos_mdfe = mdfeParseJsonAny($_POST['prod_predominantes_json'] ?? '', []);
            $totais_mdfe = mdfeParseJsonAny($_POST['tot_totalizadores_json'] ?? '', []);
            $rodoviario_mdfe = [
                'rntrc' => trim((string)($_POST['rod_rntrc'] ?? '')),
                'placa' => trim((string)($_POST['rod_placa'] ?? '')),
                'tipo_rodado' => trim((string)($_POST['rod_tipo_rodado'] ?? '')),
                'tipo_carroceria' => trim((string)($_POST['rod_tipo_carroceria'] ?? '')),
            ];
            
            if (!$uf_inicio || !$uf_fim) {
                throw new Exception('UF de início e fim são obrigatórias');
            }
            
            if (!$municipio_carregamento || !$municipio_descarregamento) {
                throw new Exception('Municípios de carregamento e descarregamento são obrigatórios');
            }
            
            // Verificar documentos e calcular totais (CT-e e/ou NF-e).
            $ctes = [];
            $nfes = [];
            
            $peso_total = 0;
            $volumes_total = 0;
            $valor_total = 0;

            if (!empty($cte_ids)) {
                $placeholders = str_repeat('?,', count($cte_ids) - 1) . '?';
                $stmt = $conn->prepare("
                    SELECT id, status, valor_total, peso_total
                    FROM fiscal_cte
                    WHERE id IN ($placeholders) AND empresa_id = ?
                ");
                $params = array_merge($cte_ids, [$empresa_id]);
                $stmt->execute($params);
                $ctes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($ctes) != count($cte_ids)) {
                    $encontrados = array_column($ctes, 'id');
                    $faltando = array_diff($cte_ids, $encontrados);
                    $msg = 'Alguns CT-e não foram encontrados para sua empresa.';
                    $msg .= ' IDs solicitados: ' . implode(', ', $cte_ids) . '.';
                    $msg .= ' Encontrados: ' . (count($encontrados) ? implode(', ', $encontrados) : 'nenhum') . '.';
                    if (count($faltando)) {
                        $msg .= ' Não encontrados: ' . implode(', ', $faltando) . '.';
                    }
                    $msg .= ' Confirme que os CT-e estão autorizados e pertencem à empresa selecionada (trocar empresa no topo da página pode alterar isso).';
                    throw new Exception($msg);
                }

                foreach ($ctes as $cte) {
                    if ($cte['status'] !== 'autorizado') {
                        throw new Exception('Todos os CT-e devem estar autorizados para criar MDF-e');
                    }
                    $peso_total += floatval($cte['peso_total'] ?? 0);
                    $volumes_total += intval($cte['volumes_carga'] ?? 0);
                    $valor_total += floatval($cte['valor_total'] ?? 0);
                }
            }

            if (!empty($origem_nfe_ids)) {
                $placeholdersN = str_repeat('?,', count($origem_nfe_ids) - 1) . '?';
                $stmtN = $conn->prepare("
                    SELECT id, status, valor_total, peso_carga
                    FROM fiscal_nfe_clientes
                    WHERE id IN ($placeholdersN) AND empresa_id = ?
                ");
                $stmtN->execute(array_merge($origem_nfe_ids, [$empresa_id]));
                $nfes = $stmtN->fetchAll(PDO::FETCH_ASSOC);
                if (count($nfes) != count($origem_nfe_ids)) {
                    throw new Exception('Algumas NF-e de origem não foram encontradas para esta empresa.');
                }
                foreach ($nfes as $nfe) {
                    $valor_total += floatval($nfe['valor_total'] ?? 0);
                    $peso_total += floatval($nfe['peso_carga'] ?? 0);
                }
            }

            if ($peso_total <= 0) {
                throw new Exception('Peso total inválido para emissão do MDF-e (deve ser maior que zero).');
            }

            $tem_payload_wizard = $tipo_emitente !== ''
                || !empty($documentos_mdfe) || !empty($pagamentos_mdfe) || !empty($contratantes_mdfe)
                || !empty($ciot_mdfe) || !empty($vales_mdfe) || !empty($seguros_mdfe) || !empty($produtos_mdfe);

            $dadosValidacao = [
                'modo' => $modo_validacao,
                'tipo_emitente' => $tipo_emitente,
                'tipo_transportador' => $tipo_transportador,
                'cte_ids' => $cte_ids,
                'documentos' => $documentos_mdfe,
                'pagamentos' => $pagamentos_mdfe,
                'contratantes' => $contratantes_mdfe,
                'ciots' => $ciot_mdfe,
                'vales_pedagio' => $vales_mdfe,
                'seguros' => $seguros_mdfe,
                'produtos' => $produtos_mdfe,
                'rodoviario' => $rodoviario_mdfe,
                'totais' => is_array($totais_mdfe) ? $totais_mdfe : [],
                'peso_total_calculado' => $peso_total,
                'rota_tem_pedagio' => $rota_tem_pedagio,
                'strict_vehicle' => $tem_payload_wizard,
                'strict_docs' => $tem_payload_wizard,
            ];
            $resultadoValidacao = validarMDFeRegras($dadosValidacao);
            registrarLogValidacaoMDFe($conn, (int)$empresa_id, null, 'criar_mdfe', $resultadoValidacao, $dadosValidacao);
            if (!$resultadoValidacao['valido']) {
                $mensagens = array_map(function($e) {
                    return ($e['id'] ?? $e['codigo'] ?? 'ERRO') . ': ' . ($e['mensagem'] ?? '');
                }, $resultadoValidacao['erros'] ?? []);
                echo json_encode([
                    'success' => false,
                    'error' => 'Validação fiscal do MDF-e falhou.',
                    'validation_version' => $resultadoValidacao['versao_regra'] ?? null,
                    'erros' => $resultadoValidacao['erros'] ?? [],
                    'warnings' => $resultadoValidacao['warnings'] ?? [],
                    'detalhes' => $mensagens,
                ]);
                logOperacao('validacao_mdfe_bloqueada', 'Validação MDF-e bloqueada', 'erro', null, $_POST, [
                    'versao_regra' => $resultadoValidacao['versao_regra'] ?? null,
                    'erros' => $resultadoValidacao['erros'] ?? [],
                    'warnings' => $resultadoValidacao['warnings'] ?? [],
                ]);
                break;
            }

            $conn->beginTransaction();
            try {
            // Criar MDF-e (colunas alinhadas à tabela: valor_total_carga, peso_total_carga, qtd_total_volumes + uf/município/total_cte)
            $numero_mdfe = getProximoNumero('MDFE', '1');
            $chave_acesso = gerarChaveAcesso('MDFE', $numero_mdfe, '1');
            
            $stmt = $conn->prepare("
                INSERT INTO fiscal_mdfe (
                    empresa_id, numero_mdfe, serie_mdfe, chave_acesso, data_emissao,
                    tipo_transporte, veiculo_id, motorista_id, uf_inicio, uf_fim,
                    municipio_carregamento, municipio_descarregamento, tipo_viagem, total_cte,
                    peso_total_carga, qtd_total_volumes, valor_total_carga, observacoes,
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_mdfe, '1', $chave_acesso, date('Y-m-d'),
                'rodoviario', $veiculo_id, $motorista_id, $uf_inicio, $uf_fim,
                $municipio_carregamento, $municipio_descarregamento, $tipo_viagem, count($cte_ids),
                $peso_total, $volumes_total, $valor_total, $observacoes,
                'rascunho', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $mdfe_id = $conn->lastInsertId();
            
            // Vincular CT-e ao MDF-e: tabela de relacionamento fiscal_mdfe_cte
            $stmtMdfeCte = $conn->prepare("INSERT INTO fiscal_mdfe_cte (mdfe_id, cte_id) VALUES (?, ?)");
            foreach ($cte_ids as $cte_id) {
                $stmtMdfeCte->execute([$mdfe_id, $cte_id]);
            }

            // Vincular NF-e de origem ao MDF-e para rastreabilidade.
            if (!empty($origem_nfe_ids)) {
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS fiscal_mdfe_nfe (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        mdfe_id INT NOT NULL,
                        nfe_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uniq_mdfe_nfe (mdfe_id, nfe_id),
                        INDEX idx_mdfe (mdfe_id),
                        INDEX idx_nfe (nfe_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $stInsNfe = $conn->prepare("INSERT IGNORE INTO fiscal_mdfe_nfe (mdfe_id, nfe_id) VALUES (?, ?)");
                foreach ($origem_nfe_ids as $nfeId) {
                    $stInsNfe->execute([$mdfe_id, $nfeId]);
                }
            }

            $wizardKeys = [
                'rod_ciot_json',
                'rod_vales_pedagio_json',
                'rod_contratantes_json',
                'rod_pagamentos_frete_json',
                'seg_seguros_json',
                'prod_predominantes_json',
                'tot_totalizadores_json',
            ];
            $persistirWizard = false;
            foreach ($wizardKeys as $wk) {
                if (array_key_exists($wk, $_POST)) { $persistirWizard = true; break; }
            }
            if ($persistirWizard) {
                persistMdfeWizardEstruturado($conn, (int)$empresa_id, (int)$mdfe_id, [
                    'ciots' => $ciot_mdfe,
                    'vales_pedagio' => $vales_mdfe,
                    'contratantes' => $contratantes_mdfe,
                    'pagamentos' => $pagamentos_mdfe,
                    'seguros' => $seguros_mdfe,
                    'produtos' => $produtos_mdfe,
                    'totais' => is_array($totais_mdfe) ? $totais_mdfe : [],
                ]);
            }
            
            // Atualizar mdfe_id no fiscal_cte (se a coluna existir)
            try {
                foreach ($cte_ids as $cte_id) {
                    $stmt = $conn->prepare("
                        UPDATE fiscal_cte 
                        SET mdfe_id = ?, updated_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$mdfe_id, date('Y-m-d H:i:s'), $cte_id]);
                }
            } catch (Exception $e) {
                // Coluna mdfe_id pode não existir; fiscal_mdfe_cte já vinculou
            }

                $conn->commit();
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'MDF-e criado com sucesso!',
                'mdfe_id' => $mdfe_id,
                'numero_mdfe' => $numero_mdfe,
                'status' => 'rascunho',
                'peso_total' => $peso_total,
                'volumes_total' => $volumes_total,
                'valor_total' => $valor_total,
                'total_cte' => count($cte_ids),
                'origem_mdfe' => $origem_mdfe !== '' ? $origem_mdfe : (empty($cte_ids) ? 'nfe' : 'cte'),
                'nfe_ids' => $origem_nfe_ids,
                'validation_version' => $resultadoValidacao['versao_regra'] ?? null,
                'warnings' => $resultadoValidacao['warnings'] ?? [],
            ]);
            
            logOperacao('criacao_mdfe', "Criou MDF-e #$numero_mdfe com " . count($cte_ids) . " CT-e", 'sucesso', $mdfe_id, $_POST);
            break;
            
        case 'get':
            // Obter documento específico
            $id = $_GET['id'] ?? null;
            $tipo = $_GET['tipo'] ?? 'cte';
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            // Mapear tipo para tabela
            $tabela = '';
            switch ($tipo) {
                case 'nfe':
                    $tabela = 'fiscal_nfe_clientes';
                    break;
                case 'cte':
                    $tabela = 'fiscal_cte';
                    break;
                case 'mdfe':
                    $tabela = 'fiscal_mdfe';
                    break;
                default:
                    throw new Exception('Tipo de documento inválido');
            }
            
            $stmt = $conn->prepare("SELECT * FROM $tabela WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            if ($tipo === 'mdfe') {
                $stmt = $conn->prepare("SELECT cte_id FROM fiscal_mdfe_cte WHERE mdfe_id = ?");
                $stmt->execute([$id]);
                $documento['cte_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
                try {
                    $stN = $conn->prepare("SELECT nfe_id FROM fiscal_mdfe_nfe WHERE mdfe_id = ?");
                    $stN->execute([$id]);
                    $documento['nfe_ids'] = $stN->fetchAll(PDO::FETCH_COLUMN);
                } catch (Throwable $e) {
                    $documento['nfe_ids'] = [];
                }
                $qtdCte = count($documento['cte_ids'] ?? []);
                $qtdNfe = count($documento['nfe_ids'] ?? []);
                $origem = 'manual';
                if ($qtdCte > 0 && $qtdNfe > 0) {
                    $origem = 'misto';
                } elseif ($qtdCte > 0) {
                    $origem = 'cte';
                } elseif ($qtdNfe > 0) {
                    $origem = 'nfe';
                }
                $documento['qtd_nfe_origem'] = $qtdNfe;
                $documento['origem_documental'] = $origem;

                try {
                    ensureMdfeWizardPersistenceSchema($conn);
                    $documento['wizard_estruturado'] = [
                        'ciots' => $conn->prepare("SELECT * FROM fiscal_mdfe_ciot WHERE mdfe_id = ? ORDER BY id ASC"),
                        'vales_pedagio' => $conn->prepare("SELECT * FROM fiscal_mdfe_vale_pedagio WHERE mdfe_id = ? ORDER BY id ASC"),
                        'contratantes' => $conn->prepare("SELECT * FROM fiscal_mdfe_contratantes WHERE mdfe_id = ? ORDER BY id ASC"),
                        'pagamentos' => $conn->prepare("SELECT * FROM fiscal_mdfe_pagamentos WHERE mdfe_id = ? ORDER BY id ASC"),
                        'pagamento_componentes' => $conn->prepare("SELECT * FROM fiscal_mdfe_pagamento_componentes WHERE mdfe_id = ? ORDER BY id ASC"),
                        'seguros' => $conn->prepare("SELECT * FROM fiscal_mdfe_seguros WHERE mdfe_id = ? ORDER BY id ASC"),
                        'seguros_averbacoes' => $conn->prepare("SELECT * FROM fiscal_mdfe_seguros_averbacoes WHERE mdfe_id = ? ORDER BY id ASC"),
                        'produtos' => $conn->prepare("SELECT * FROM fiscal_mdfe_produtos WHERE mdfe_id = ? ORDER BY id ASC"),
                        'lacres' => $conn->prepare("SELECT * FROM fiscal_mdfe_lacres WHERE mdfe_id = ? ORDER BY id ASC"),
                        'autorizados_download' => $conn->prepare("SELECT * FROM fiscal_mdfe_autorizados_download WHERE mdfe_id = ? ORDER BY id ASC"),
                    ];
                    foreach ($documento['wizard_estruturado'] as $k => $stW) {
                        $stW->execute([$id]);
                        $documento['wizard_estruturado'][$k] = $stW->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    }
                } catch (Throwable $e) {
                    $documento['wizard_estruturado'] = [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'documento' => $documento
            ]);
            
            logOperacao('consultar', "Consultou documento #$id", 'sucesso', $id);
            break;
            
        case 'update':
            fiscal_api_rate_limit_or_json_429('fiscal_update_documento', 120, 300);
            // Atualizar documento existente
            $id = $_POST['id'] ?? null;
            $tipo = $_POST['tipo_documento'] ?? 'cte';
            $validationWarnings = [];
            $validationVersion = null;
            $wizardPayloadEstruturado = null;
            $persistirWizardEstruturado = false;
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            // Mapear tipo para tabela
            $tabela = '';
            $campos_update = [];
            $valores_update = [];
            
            switch ($tipo) {
                case 'cte':
                    $tabela = 'fiscal_cte';
                    $campos_update = [
                        'natureza_operacao' => $_POST['natureza_operacao'] ?? '',
                        'valor_total' => $_POST['valor_total'] ?? 0.00,
                        'peso_carga' => $_POST['peso_carga'] ?? 0.00,
                        'volumes_carga' => $_POST['volumes_carga'] ?? 0,
                        'origem' => $_POST['origem'] ?? '',
                        'destino' => $_POST['destino'] ?? '',
                        'status' => $_POST['status'] ?? 'rascunho',
                        'observacoes' => $_POST['observacoes'] ?? '',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    break;
                    
                case 'mdfe':
                    $tabela = 'fiscal_mdfe';
                    $stAtual = $conn->prepare("SELECT status FROM fiscal_mdfe WHERE id = ? AND empresa_id = ? LIMIT 1");
                    $stAtual->execute([$id, $empresa_id]);
                    $statusAtualMdfe = strtolower(trim((string)($stAtual->fetchColumn() ?? '')));
                    if ($statusAtualMdfe === '') {
                        throw new Exception('MDF-e não encontrado para atualização.');
                    }
                    if (in_array($statusAtualMdfe, ['autorizado', 'emitido', 'em_viagem', 'encerrado', 'cancelado'], true)) {
                        throw new Exception('E050_MDFe_EDICAO_BLOQUEADA_POS_EMISSAO: MDF-e já emitido/autorizado. Edição bloqueada.');
                    }
                    $campos_update = [
                        'veiculo_id' => $_POST['veiculo_id'] ?? null,
                        'motorista_id' => $_POST['motorista_id'] ?? null,
                        'uf_inicio' => $_POST['uf_inicio'] ?? null,
                        'uf_fim' => $_POST['uf_fim'] ?? null,
                        'municipio_carregamento' => $_POST['municipio_carregamento'] ?? null,
                        'municipio_descarregamento' => $_POST['municipio_descarregamento'] ?? null,
                        'tipo_viagem' => $_POST['tipo_viagem'] ?? '1',
                        'tipo_transporte' => $_POST['tipo_transporte'] ?? 'rodoviario',
                        'observacoes' => $_POST['observacoes'] ?? '',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    // Validação centralizada também no UPDATE (garantia backend).
                    $tipo_emitente = trim((string)($_POST['tipo_emitente'] ?? ''));
                    $tipo_transportador = trim((string)($_POST['tipo_transportador'] ?? ''));
                    $modo_validacao = trim((string)($_POST['modo'] ?? 'emissao'));
                    $rota_tem_pedagio = !empty($_POST['rota_tem_pedagio']) && (string)$_POST['rota_tem_pedagio'] !== '0';

                    $documentos_mdfe = mdfeParseJsonAny($_POST['doc_documentos_json'] ?? '', []);
                    $pagamentos_mdfe = mdfeParseJsonAny($_POST['rod_pagamentos_frete_json'] ?? '', []);
                    $contratantes_mdfe = mdfeParseJsonAny($_POST['rod_contratantes_json'] ?? '', []);
                    $ciot_mdfe = mdfeParseJsonAny($_POST['rod_ciot_json'] ?? '', []);
                    $vales_mdfe = mdfeParseJsonAny($_POST['rod_vales_pedagio_json'] ?? '', []);
                    $seguros_mdfe = mdfeParseJsonAny($_POST['seg_seguros_json'] ?? '', []);
                    $produtos_mdfe = mdfeParseJsonAny($_POST['prod_predominantes_json'] ?? '', []);
                    $totais_mdfe = mdfeParseJsonAny($_POST['tot_totalizadores_json'] ?? '', []);
                    $rodoviario_mdfe = [
                        'rntrc' => trim((string)($_POST['rod_rntrc'] ?? '')),
                        'placa' => trim((string)($_POST['rod_placa'] ?? '')),
                        'tipo_rodado' => trim((string)($_POST['rod_tipo_rodado'] ?? '')),
                        'tipo_carroceria' => trim((string)($_POST['rod_tipo_carroceria'] ?? '')),
                    ];

                    $cte_ids_update = [];
                    if (isset($_POST['cte_ids'])) {
                        if (is_array($_POST['cte_ids'])) {
                            $cte_ids_update = array_values(array_unique(array_filter(array_map('intval', $_POST['cte_ids']), function($v) { return $v > 0; })));
                        } else {
                            $cte_ids_update = array_values(array_unique(array_filter([intval($_POST['cte_ids'])], function($v) { return $v > 0; })));
                        }
                    } else {
                        // Se não enviou cte_ids, usa vínculos já existentes no MDF-e.
                        $stCteVinc = $conn->prepare("SELECT cte_id FROM fiscal_mdfe_cte WHERE mdfe_id = ?");
                        $stCteVinc->execute([$id]);
                        $cte_ids_update = array_values(array_filter(array_map('intval', $stCteVinc->fetchAll(PDO::FETCH_COLUMN)), function($v) { return $v > 0; }));
                    }

                    $peso_total_update = 0.0;
                    if (!empty($cte_ids_update)) {
                        $ph = str_repeat('?,', count($cte_ids_update) - 1) . '?';
                        $stCte = $conn->prepare("SELECT id, peso_total FROM fiscal_cte WHERE id IN ($ph) AND empresa_id = ?");
                        $stCte->execute(array_merge($cte_ids_update, [$empresa_id]));
                        foreach ($stCte->fetchAll(PDO::FETCH_ASSOC) as $rowCte) {
                            $peso_total_update += (float)($rowCte['peso_total'] ?? 0);
                        }
                    }

                    $tem_payload_wizard = $tipo_emitente !== ''
                        || !empty($documentos_mdfe) || !empty($pagamentos_mdfe) || !empty($contratantes_mdfe)
                        || !empty($ciot_mdfe) || !empty($vales_mdfe) || !empty($seguros_mdfe) || !empty($produtos_mdfe);

                    $dadosValidacao = [
                        'modo' => $modo_validacao,
                        'tipo_emitente' => $tipo_emitente,
                        'tipo_transportador' => $tipo_transportador,
                        'cte_ids' => $cte_ids_update,
                        'documentos' => $documentos_mdfe,
                        'pagamentos' => $pagamentos_mdfe,
                        'contratantes' => $contratantes_mdfe,
                        'ciots' => $ciot_mdfe,
                        'vales_pedagio' => $vales_mdfe,
                        'seguros' => $seguros_mdfe,
                        'produtos' => $produtos_mdfe,
                        'rodoviario' => $rodoviario_mdfe,
                        'totais' => is_array($totais_mdfe) ? $totais_mdfe : [],
                        'peso_total_calculado' => $peso_total_update,
                        'rota_tem_pedagio' => $rota_tem_pedagio,
                        'strict_vehicle' => $tem_payload_wizard,
                        'strict_docs' => $tem_payload_wizard,
                    ];
                    $resultadoValidacao = validarMDFeRegras($dadosValidacao);
                    registrarLogValidacaoMDFe($conn, (int)$empresa_id, (int)$id, 'update_mdfe', $resultadoValidacao, $dadosValidacao);
                    $validationVersion = $resultadoValidacao['versao_regra'] ?? null;
                    $validationWarnings = $resultadoValidacao['warnings'] ?? [];
                    if (!$resultadoValidacao['valido']) {
                        $mensagens = array_map(function($e) {
                            return ($e['id'] ?? $e['codigo'] ?? 'ERRO') . ': ' . ($e['mensagem'] ?? '');
                        }, $resultadoValidacao['erros'] ?? []);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Validação fiscal do MDF-e falhou.',
                            'validation_version' => $validationVersion,
                            'erros' => $resultadoValidacao['erros'] ?? [],
                            'warnings' => $validationWarnings,
                            'detalhes' => $mensagens,
                        ]);
                        logOperacao('validacao_mdfe_update_bloqueada', 'Validação MDF-e (update) bloqueada', 'erro', $id, $_POST, [
                            'versao_regra' => $validationVersion,
                            'erros' => $resultadoValidacao['erros'] ?? [],
                            'warnings' => $validationWarnings,
                        ]);
                        break;
                    }

                    $wizardKeys = [
                        'rod_ciot_json',
                        'rod_vales_pedagio_json',
                        'rod_contratantes_json',
                        'rod_pagamentos_frete_json',
                        'seg_seguros_json',
                        'prod_predominantes_json',
                        'tot_totalizadores_json',
                    ];
                    foreach ($wizardKeys as $wk) {
                        if (array_key_exists($wk, $_POST)) {
                            $persistirWizardEstruturado = true;
                            break;
                        }
                    }
                    $wizardPayloadEstruturado = [
                        'ciots' => $ciot_mdfe,
                        'vales_pedagio' => $vales_mdfe,
                        'contratantes' => $contratantes_mdfe,
                        'pagamentos' => $pagamentos_mdfe,
                        'seguros' => $seguros_mdfe,
                        'produtos' => $produtos_mdfe,
                        'totais' => is_array($totais_mdfe) ? $totais_mdfe : [],
                    ];
                    break;
                    
                default:
                    throw new Exception('Tipo de documento inválido');
            }

            $useTxnMdfeUpdate = ($tipo === 'mdfe');
            if ($useTxnMdfeUpdate) {
                $conn->beginTransaction();
            }
            try {
            // Construir query de atualização
            $set_clause = [];
            $valores_update = [];
            
            foreach ($campos_update as $campo => $valor) {
                $set_clause[] = "$campo = ?";
                $valores_update[] = $valor;
            }
            
            // Adicionar valores para WHERE
            $valores_update[] = $empresa_id;
            $valores_update[] = $id;
            
            $sql = "UPDATE $tabela SET " . implode(', ', $set_clause) . " WHERE empresa_id = ? AND id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores_update);
            
            if ($tipo === 'mdfe' && isset($_POST['cte_ids']) && is_array($_POST['cte_ids'])) {
                $cte_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['cte_ids']), function($v) { return $v > 0; })));
                $conn->prepare("DELETE FROM fiscal_mdfe_cte WHERE mdfe_id = ?")->execute([$id]);
                if (!empty($cte_ids)) {
                    $placeholders = str_repeat('?,', count($cte_ids) - 1) . '?';
                    $stmtCte = $conn->prepare("SELECT id, valor_total, peso_total FROM fiscal_cte WHERE id IN ($placeholders) AND empresa_id = ?");
                    $stmtCte->execute(array_merge($cte_ids, [$empresa_id]));
                    $ctes = $stmtCte->fetchAll(PDO::FETCH_ASSOC);
                    $totalCt = count($ctes);
                    $peso = 0;
                    $valor = 0;
                    $ins = $conn->prepare("INSERT INTO fiscal_mdfe_cte (mdfe_id, cte_id) VALUES (?, ?)");
                    foreach ($cte_ids as $cid) {
                        $ins->execute([$id, $cid]);
                    }
                    foreach ($ctes as $c) {
                        $peso += floatval($c['peso_total'] ?? 0);
                        $valor += floatval($c['valor_total'] ?? 0);
                    }
                    $vol = 0;
                    try {
                        $stmtV = $conn->prepare("SELECT SUM(volumes_carga) as v FROM fiscal_cte WHERE id IN ($placeholders) AND empresa_id = ?");
                        $stmtV->execute(array_merge($cte_ids, [$empresa_id]));
                        $row = $stmtV->fetch(PDO::FETCH_ASSOC);
                        $vol = (int)($row['v'] ?? 0);
                    } catch (Exception $e) { /* volumes_carga pode não existir */ }
                    $conn->prepare("UPDATE fiscal_mdfe SET total_cte = ?, peso_total_carga = ?, qtd_total_volumes = ?, valor_total_carga = ?, updated_at = ? WHERE id = ? AND empresa_id = ?")
                        ->execute([$totalCt, $peso, $vol, $valor, date('Y-m-d H:i:s'), $id, $empresa_id]);
                } else {
                    $conn->prepare("UPDATE fiscal_mdfe SET total_cte = 0, peso_total_carga = NULL, qtd_total_volumes = NULL, valor_total_carga = NULL, updated_at = ? WHERE id = ? AND empresa_id = ?")
                        ->execute([date('Y-m-d H:i:s'), $id, $empresa_id]);
                }
            }

            if ($tipo === 'mdfe' && $persistirWizardEstruturado && is_array($wizardPayloadEstruturado)) {
                persistMdfeWizardEstruturado($conn, (int)$empresa_id, (int)$id, $wizardPayloadEstruturado);
            }

                if ($useTxnMdfeUpdate) {
                    $conn->commit();
                }
            } catch (Throwable $e) {
                if ($useTxnMdfeUpdate && $conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
            
            if ($stmt->rowCount() > 0 || ($tipo === 'mdfe' && isset($_POST['cte_ids']))) {
                // Buscar documento atualizado
                $stmt = $conn->prepare("SELECT * FROM $tabela WHERE id = ?");
                $stmt->execute([$id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($tipo) . ' atualizado com sucesso!',
                    'documento' => $documento,
                    'validation_version' => $validationVersion,
                    'warnings' => $validationWarnings
                ]);
                
                logOperacao('atualizacao', "Atualizou documento $tipo #$id", 'sucesso', $id, $_POST);
            } else {
                throw new Exception('Nenhuma alteração foi feita ou documento não encontrado');
            }
            break;
            
        case 'status_fila_fiscal':
            FiscalQueueService::ensureSchema($conn);
            $stmt = $conn->prepare("
                SELECT status, COUNT(*) as total
                FROM fiscal_fila_processamento
                WHERE empresa_id = ?
                GROUP BY status
            ");
            $stmt->execute([$empresa_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $resumo = [
                'pendente' => 0,
                'processando' => 0,
                'sucesso' => 0,
                'erro' => 0,
                'falha' => 0,
            ];
            foreach ($rows as $r) {
                $k = strtolower((string)($r['status'] ?? ''));
                if (!isset($resumo[$k])) {
                    $resumo[$k] = 0;
                }
                $resumo[$k] = (int)($r['total'] ?? 0);
            }
            echo json_encode([
                'success' => true,
                'fila' => $resumo,
            ]);
            break;

        case 'totals':
            // Obter totais gerais
            $totais = [];
            
            // NF-e recebidas
            $stmt = $conn->prepare("
                SELECT 
                    'nfe' as tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'recebida' THEN 1 ELSE 0 END) as recebidas,
                    SUM(CASE WHEN status = 'em_transporte' THEN 1 ELSE 0 END) as em_transporte,
                    SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues
                FROM fiscal_nfe_clientes 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais['nfe'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // CT-e
            $stmt = $conn->prepare("
                SELECT 
                    'cte' as tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                    SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                FROM fiscal_cte 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais['cte'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // MDF-e
            $stmt = $conn->prepare("
                SELECT 
                    'mdfe' as tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                    SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                FROM fiscal_mdfe 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais['mdfe'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'totais' => $totais
            ]);
            
            logOperacao('consultar', "Consultou totais gerais", 'sucesso');
            break;
            
        case 'validar_mdfe':
            $id = $_GET['id'] ?? $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception('ID do MDF-e não fornecido');
            }
            $resultado = validarMDFe($conn, $empresa_id, $id);
            if ($resultado === true) {
                echo json_encode(['success' => true, 'valid' => true, 'message' => 'MDF-e validado. Pode enviar para a SEFAZ.']);
            } else {
                echo json_encode(['success' => true, 'valid' => false, 'message' => $resultado]);
            }
            break;

        case 'enfileirar_envio_fiscal':
            fiscal_api_rate_limit_or_json_429('fiscal_enfileirar_envio', 20, 300);
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $tipo = $_POST['tipo_documento'] ?? $_GET['tipo_documento'] ?? 'mdfe';
            if ($id <= 0) {
                throw new Exception('ID do documento não fornecido');
            }
            if (!in_array($tipo, ['mdfe'], true)) {
                throw new Exception('No momento, apenas MDF-e suporta fila assíncrona.');
            }

            $stmt = $conn->prepare("SELECT id, status FROM fiscal_mdfe WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmt->execute([$id, $empresa_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('Documento não encontrado para enfileirar.');
            }

            FiscalQueueService::ensureSchema($conn);
            $jobId = FiscalQueueService::enqueue(
                $conn,
                (int)$empresa_id,
                'mdfe',
                'emitir',
                ['id' => $id, 'tipo_documento' => 'mdfe'],
                5
            );

            echo json_encode([
                'success' => true,
                'message' => 'Documento enfileirado para envio assíncrono.',
                'job_id' => $jobId,
            ]);
            break;
            
        case 'enviar_sefaz':
            fiscal_api_rate_limit_or_json_429('fiscal_enviar_sefaz', 30, 300);
            // Enviar CT-e ou MDF-e para SEFAZ
            $id = $_POST['id'] ?? null;
            $tipo = $_POST['tipo_documento'] ?? 'cte';
            $validationVersionEnvio = null;
            $validationWarningsEnvio = [];
            $mdfeEnvioLockAtivo = false;
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            if ($tipo !== 'cte' && $tipo !== 'mdfe') {
                throw new Exception('Apenas CT-e e MDF-e podem ser enviados para SEFAZ');
            }
            
            // Buscar documento
            $tabela = $tipo === 'cte' ? 'fiscal_cte' : 'fiscal_mdfe';
            $stmt = $conn->prepare("SELECT * FROM $tabela WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }

            $docStatus = strtolower(trim((string)($documento['status'] ?? '')));
            $statusPermitidoEnvio = ($docStatus === 'rascunho' || $docStatus === 'pendente' || ($tipo === 'mdfe' && $docStatus === 'em_envio'));
            if (!$statusPermitidoEnvio) {
                throw new Exception('Apenas documentos com status rascunho ou pendente podem ser enviados para SEFAZ');
            }
            
            if ($tipo === 'mdfe') {
                // Lock de concorrencia + marca de envio em andamento (idempotencia operacional).
                try {
                    $conn->beginTransaction();
                    $stmtLock = $conn->prepare("SELECT id, status, updated_at FROM fiscal_mdfe WHERE id = ? AND empresa_id = ? FOR UPDATE");
                    $stmtLock->execute([$id, $empresa_id]);
                    $rowLock = $stmtLock->fetch(PDO::FETCH_ASSOC);
                    if (!$rowLock) {
                        $conn->rollBack();
                        throw new Exception('MDF-e não encontrado para envio.');
                    }
                    $stLock = strtolower(trim((string)($rowLock['status'] ?? '')));
                    if ($stLock === 'em_envio') {
                        $updatedAt = strtotime((string)($rowLock['updated_at'] ?? ''));
                        if ($updatedAt !== false && $updatedAt > (time() - 600)) {
                            $conn->rollBack();
                            throw new Exception('E060_MDFe_ENVIO_CONCORRENTE: MDF-e já está em processamento de envio.');
                        }
                    }
                    $stmtSet = $conn->prepare("UPDATE fiscal_mdfe SET status = 'em_envio', updated_at = ? WHERE id = ? AND empresa_id = ?");
                    $stmtSet->execute([date('Y-m-d H:i:s'), $id, $empresa_id]);
                    $conn->commit();
                    $mdfeEnvioLockAtivo = true;
                } catch (Throwable $lockErr) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    throw $lockErr;
                }

                // Nova validação centralizada (com fallback legado quando não houver payload do wizard).
                $tipo_emitente = trim((string)($_POST['tipo_emitente'] ?? ''));
                $tipo_transportador = trim((string)($_POST['tipo_transportador'] ?? ''));
                $modo_validacao = trim((string)($_POST['modo'] ?? 'emissao'));
                $rota_tem_pedagio = !empty($_POST['rota_tem_pedagio']) && (string)$_POST['rota_tem_pedagio'] !== '0';

                $documentos_mdfe = mdfeParseJsonAny($_POST['doc_documentos_json'] ?? '', []);
                $pagamentos_mdfe = mdfeParseJsonAny($_POST['rod_pagamentos_frete_json'] ?? '', []);
                $contratantes_mdfe = mdfeParseJsonAny($_POST['rod_contratantes_json'] ?? '', []);
                $ciot_mdfe = mdfeParseJsonAny($_POST['rod_ciot_json'] ?? '', []);
                $vales_mdfe = mdfeParseJsonAny($_POST['rod_vales_pedagio_json'] ?? '', []);
                $seguros_mdfe = mdfeParseJsonAny($_POST['seg_seguros_json'] ?? '', []);
                $produtos_mdfe = mdfeParseJsonAny($_POST['prod_predominantes_json'] ?? '', []);
                $totais_mdfe = mdfeParseJsonAny($_POST['tot_totalizadores_json'] ?? '', []);
                $rodoviario_mdfe = [
                    'rntrc' => trim((string)($_POST['rod_rntrc'] ?? '')),
                    'placa' => trim((string)($_POST['rod_placa'] ?? '')),
                    'tipo_rodado' => trim((string)($_POST['rod_tipo_rodado'] ?? '')),
                    'tipo_carroceria' => trim((string)($_POST['rod_tipo_carroceria'] ?? '')),
                ];

                $stmtCteIds = $conn->prepare("SELECT cte_id FROM fiscal_mdfe_cte WHERE mdfe_id = ?");
                $stmtCteIds->execute([$id]);
                $cte_ids_mdfe = array_values(array_filter(array_map('intval', $stmtCteIds->fetchAll(PDO::FETCH_COLUMN)), function($v) { return $v > 0; }));

                $tem_payload_wizard = $tipo_emitente !== ''
                    || !empty($documentos_mdfe) || !empty($pagamentos_mdfe) || !empty($contratantes_mdfe)
                    || !empty($ciot_mdfe) || !empty($vales_mdfe) || !empty($seguros_mdfe) || !empty($produtos_mdfe);

                if (!$tem_payload_wizard) {
                    // Fluxo antigo: mantém validação legado para não quebrar documentos já existentes.
                    $validacao = validarMDFe($conn, $empresa_id, $id);
                    if ($validacao !== true) {
                        throw new Exception('Validação antes de emitir: ' . $validacao);
                    }
                } else {
                    $dadosValidacao = [
                        'modo' => $modo_validacao,
                        'tipo_emitente' => $tipo_emitente,
                        'tipo_transportador' => $tipo_transportador,
                        'cte_ids' => $cte_ids_mdfe,
                        'documentos' => $documentos_mdfe,
                        'pagamentos' => $pagamentos_mdfe,
                        'contratantes' => $contratantes_mdfe,
                        'ciots' => $ciot_mdfe,
                        'vales_pedagio' => $vales_mdfe,
                        'seguros' => $seguros_mdfe,
                        'produtos' => $produtos_mdfe,
                        'rodoviario' => $rodoviario_mdfe,
                        'totais' => is_array($totais_mdfe) ? $totais_mdfe : [],
                        'peso_total_calculado' => (float)($documento['peso_total_carga'] ?? 0),
                        'rota_tem_pedagio' => $rota_tem_pedagio,
                        'strict_vehicle' => true,
                        'strict_docs' => true,
                    ];
                    $resultadoValidacao = validarMDFeRegras($dadosValidacao);
                    registrarLogValidacaoMDFe($conn, (int)$empresa_id, (int)$id, 'enviar_sefaz_mdfe', $resultadoValidacao, $dadosValidacao);
                    $validationVersionEnvio = $resultadoValidacao['versao_regra'] ?? null;
                    $validationWarningsEnvio = $resultadoValidacao['warnings'] ?? [];
                    if (!$resultadoValidacao['valido']) {
                        $mensagens = array_map(function($e) {
                            return ($e['id'] ?? $e['codigo'] ?? 'ERRO') . ': ' . ($e['mensagem'] ?? '');
                        }, $resultadoValidacao['erros'] ?? []);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Validação fiscal do MDF-e falhou antes do envio SEFAZ.',
                            'validation_version' => $validationVersionEnvio,
                            'erros' => $resultadoValidacao['erros'] ?? [],
                            'warnings' => $validationWarningsEnvio,
                            'detalhes' => $mensagens,
                        ]);
                        logOperacao('validacao_mdfe_envio_bloqueada', 'Validação MDF-e (enviar_sefaz) bloqueada', 'erro', $id, $_POST, [
                            'versao_regra' => $validationVersionEnvio,
                            'erros' => $resultadoValidacao['erros'] ?? [],
                            'warnings' => $validationWarningsEnvio,
                        ]);
                        if ($mdfeEnvioLockAtivo) {
                            $conn->prepare("UPDATE fiscal_mdfe SET status = 'pendente', updated_at = ? WHERE id = ? AND empresa_id = ?")
                                ->execute([date('Y-m-d H:i:s'), $id, $empresa_id]);
                            $mdfeEnvioLockAtivo = false;
                        }
                        break;
                    }
                }
            }
            
            // Enviar para SEFAZ (integração pode estar ausente no modo atual)
            $resultado_sefaz = enviarParaSefaz($documento, $tipo);
            
            if ($resultado_sefaz['sucesso']) {
                // Atualizar status do documento
                if ($tipo === 'mdfe') {
                    $stmt = $conn->prepare("
                        UPDATE $tabela 
                        SET status = ?, protocolo_autorizacao = ?, data_autorizacao = ?, xml_mdfe = COALESCE(?, xml_mdfe), chave_acesso = COALESCE(NULLIF(?, ''), chave_acesso), updated_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $resultado_sefaz['status'],
                        $resultado_sefaz['protocolo'],
                        date('Y-m-d H:i:s'),
                        $resultado_sefaz['xml_assinado'] ?? null,
                        $resultado_sefaz['chave_acesso'] ?? '',
                        date('Y-m-d H:i:s'),
                        $id
                    ]);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE $tabela 
                        SET status = ?, protocolo_autorizacao = ?, data_autorizacao = ?, updated_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $resultado_sefaz['status'],
                        $resultado_sefaz['protocolo'],
                        date('Y-m-d H:i:s'),
                        date('Y-m-d H:i:s'),
                        $id
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst(strtoupper($tipo)) . ' enviado para SEFAZ com sucesso!',
                    'status' => $resultado_sefaz['status'],
                    'protocolo' => $resultado_sefaz['protocolo'],
                    'validation_version' => $validationVersionEnvio,
                    'warnings' => $validationWarningsEnvio
                ]);
                
                $numero_doc = $tipo === 'cte' ? $documento['numero_cte'] : $documento['numero_mdfe'];
                logOperacao('envio_sefaz', "Enviou $tipo #$numero_doc para SEFAZ", 'sucesso', $id);
            } else {
                $erroEnvio = (string)($resultado_sefaz['erro'] ?? 'erro desconhecido');
                if ($tipo === 'mdfe' && erroTemporarioSefaz($erroEnvio)) {
                    if ($mdfeEnvioLockAtivo) {
                        $conn->prepare("UPDATE fiscal_mdfe SET status = 'pendente', updated_at = ? WHERE id = ? AND empresa_id = ?")
                            ->execute([date('Y-m-d H:i:s'), $id, $empresa_id]);
                        $mdfeEnvioLockAtivo = false;
                    }
                    FiscalQueueService::ensureSchema($conn);
                    $jobId = FiscalQueueService::enqueue(
                        $conn,
                        (int)$empresa_id,
                        'mdfe',
                        'emitir',
                        ['id' => (int)$id, 'tipo_documento' => 'mdfe'],
                        5
                    );
                    echo json_encode([
                        'success' => false,
                        'queued' => true,
                        'job_id' => $jobId,
                        'message' => 'Envio SEFAZ falhou temporariamente. Documento enfileirado para retry automático.',
                        'error' => $erroEnvio
                    ]);
                    break;
                }
                if ($tipo === 'mdfe' && $mdfeEnvioLockAtivo) {
                    $conn->prepare("UPDATE fiscal_mdfe SET status = 'pendente', updated_at = ? WHERE id = ? AND empresa_id = ?")
                        ->execute([date('Y-m-d H:i:s'), $id, $empresa_id]);
                    $mdfeEnvioLockAtivo = false;
                }
                throw new Exception('Erro ao enviar para SEFAZ: ' . $erroEnvio);
            }
            break;
            
        case 'cancelar_mdfe':
            fiscal_api_rate_limit_or_json_429('fiscal_cancelar_mdfe', 20, 300);
            // Cancelar MDF-e: só se AUTORIZADO, não encerrado e dentro de 24h da autorização
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID do MDF-e é obrigatório');
            }
            $stmt = $conn->prepare("
                SELECT id, status, chave_acesso, data_autorizacao, data_emissao, data_encerramento
                FROM fiscal_mdfe
                WHERE id = ? AND empresa_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $empresa_id]);
            $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mdfe) {
                throw new Exception('MDF-e não encontrado');
            }
            if (strtolower(trim((string)($mdfe['status'] ?? ''))) !== 'autorizado') {
                throw new Exception('Só é possível cancelar MDF-e com status AUTORIZADO');
            }
            if (!empty($mdfe['data_encerramento'])) {
                throw new Exception('MDF-e já encerrado. Não é possível cancelar.');
            }

            // Regra crítica: validar com SEFAZ antes de cancelar (bloqueia se já houver encerramento)
            $chaveAc = preg_replace('/\D/', '', (string)($mdfe['chave_acesso'] ?? ''));
            if (empty($chaveAc) || strlen($chaveAc) < 44) {
                throw new Exception('MDF-e sem chave_acesso válida. Faça emissão/autorização antes de cancelar.');
            }

            $mdfeService = new MdfeService((int)$empresa_id);
            $consulta = $mdfeService->consultarEventosPorChave($chaveAc);
            $tpEventos = $consulta['tpEventos'] ?? [];
            $cStat = (string)($consulta['cStat'] ?? '');

            // 110112 = encerramento; 110111 = cancelamento (conforme tpEvento do MDF-e)
            if (in_array('110112', $tpEventos, true) || $cStat === '132') {
                throw new Exception('MDF-e já encerrado na SEFAZ. Não é permitido cancelar.');
            }
            if (in_array('110111', $tpEventos, true) || $cStat === '101') {
                throw new Exception('MDF-e já cancelado na SEFAZ.');
            }

            $dataRef = !empty($mdfe['data_autorizacao']) ? strtotime($mdfe['data_autorizacao']) : strtotime($mdfe['data_emissao'] . ' 00:00:00');
            $limite24h = time() - (24 * 3600);
            if ($dataRef < $limite24h) {
                throw new Exception('Prazo para cancelamento expirado (24 horas após autorização). Use Encerrar MDF-e.');
            }
            $stmt = $conn->prepare("UPDATE fiscal_mdfe SET status = 'cancelado', updated_at = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), $id, $empresa_id]);
            echo json_encode(['success' => true, 'message' => 'MDF-e cancelado com sucesso.', 'status' => 'cancelado']);
            logOperacao('cancelar_mdfe', "Cancelou MDF-e #$id", 'sucesso', $id);
            break;
            
        case 'encerrar_mdfe':
            fiscal_api_rate_limit_or_json_429('fiscal_encerrar_mdfe', 15, 300);
            // Encerrar MDF-e: finaliza a viagem (obrigatório por lei)
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID do MDF-e é obrigatório');
            }
            $stmt = $conn->prepare("
                SELECT id, status, chave_acesso, data_autorizacao, data_emissao, data_encerramento
                FROM fiscal_mdfe
                WHERE id = ? AND empresa_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $empresa_id]);
            $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mdfe) {
                throw new Exception('MDF-e não encontrado');
            }
            if (strtolower(trim((string)($mdfe['status'] ?? ''))) !== 'autorizado') {
                throw new Exception('Só é possível encerrar MDF-e com status AUTORIZADO');
            }
            if (!empty($mdfe['data_encerramento'])) {
                throw new Exception('MDF-e já está encerrado.');
            }

            // Regra crítica: validar com SEFAZ antes de encerrar (evita duplicidade e garante consistência)
            $chaveAc = preg_replace('/\D/', '', (string)($mdfe['chave_acesso'] ?? ''));
            if (empty($chaveAc) || strlen($chaveAc) < 44) {
                throw new Exception('MDF-e sem chave_acesso válida. Faça emissão/autorização antes de encerrar.');
            }

            $mdfeService = new MdfeService((int)$empresa_id);
            $consulta = $mdfeService->consultarEventosPorChave($chaveAc);
            $tpEventos = $consulta['tpEventos'] ?? [];
            $cStat = (string)($consulta['cStat'] ?? '');

            if (in_array('110112', $tpEventos, true) || $cStat === '132') {
                throw new Exception('MDF-e já encerrado na SEFAZ.');
            }
            if (in_array('110111', $tpEventos, true) || $cStat === '101') {
                throw new Exception('MDF-e já cancelado na SEFAZ. Encerramento não permitido.');
            }

            $stmt = $conn->prepare("UPDATE fiscal_mdfe SET status = 'encerrado', data_encerramento = ?, updated_at = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id, $empresa_id]);
            echo json_encode(['success' => true, 'message' => 'MDF-e encerrado com sucesso.', 'status' => 'encerrado']);
            logOperacao('encerrar_mdfe', "Encerrou MDF-e #$id", 'sucesso', $id);
            break;
            
        case 'incluir_condutor_mdfe':
            fiscal_api_rate_limit_or_json_429('fiscal_incluir_condutor_mdfe', 30, 300);
            // Incluir/trocar condutor durante a viagem (evento SEFAZ - aqui só atualiza o banco; integração SEFAZ pode ser feita depois)
            $id = $_POST['id'] ?? null;
            $motorista_id = isset($_POST['motorista_id']) ? (int)$_POST['motorista_id'] : null;
            if (!$id) {
                throw new Exception('ID do MDF-e é obrigatório');
            }
            if (!$motorista_id) {
                throw new Exception('Novo motorista é obrigatório para inclusão de condutor');
            }
            $stmt = $conn->prepare("SELECT id, status, data_encerramento FROM fiscal_mdfe WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mdfe) {
                throw new Exception('MDF-e não encontrado');
            }
            if ($mdfe['status'] !== 'autorizado') {
                throw new Exception('Só é possível incluir condutor em MDF-e AUTORIZADO');
            }
            if (!empty($mdfe['data_encerramento'])) {
                throw new Exception('MDF-e já encerrado. Não é possível incluir condutor.');
            }
            $stmt = $conn->prepare("UPDATE fiscal_mdfe SET motorista_id = ?, updated_at = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$motorista_id, date('Y-m-d H:i:s'), $id, $empresa_id]);
            echo json_encode(['success' => true, 'message' => 'Condutor incluído/atualizado no MDF-e.']);
            logOperacao('incluir_condutor_mdfe', "Incluiu condutor no MDF-e #$id", 'sucesso', $id);
            break;
            
        case 'processar_evento':
            // PROCESSAR EVENTOS FISCAIS (CC-e, Cancelamento, Inutilização, Manifestação)
            $tipo_evento = $_POST['tipo_evento'] ?? null;
            $justificativa = $_POST['justificativa'] ?? '';
            $xml_evento = $_POST['xml_evento'] ?? null;
            $documento_id = isset($_POST['documento_id']) ? (int)$_POST['documento_id'] : 0;

            if (!$tipo_evento) {
                throw new Exception('tipo_evento é obrigatório');
            }

            $tipos_validos = ['cancelamento', 'encerramento', 'cce', 'inutilizacao', 'manifestacao'];
            if (!in_array($tipo_evento, $tipos_validos, true)) {
                throw new Exception('Tipo de evento inválido');
            }

            if ($tipo_evento !== 'inutilizacao' && $documento_id < 1) {
                throw new Exception('ID do documento é obrigatório para este tipo de evento');
            }

            $documento = null;
            if ($documento_id > 0) {
                $stmt = $conn->prepare("
                    SELECT id, status, chave_acesso, protocolo_autorizacao, numero_nfe, serie_nfe
                    FROM fiscal_nfe_clientes
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$documento_id, $empresa_id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$documento) {
                    throw new Exception('Documento não encontrado');
                }
            } else {
                $documento = ['status' => 'inutilizacao', 'chave_acesso' => null];
            }

            if (!validarEventoPermitidoNfe($documento, $tipo_evento)) {
                throw new Exception('Evento não permitido para o status atual do documento');
            }

            $stmt = $conn->prepare("
                INSERT INTO fiscal_eventos_fiscais (
                    empresa_id, tipo_evento, documento_tipo, documento_id,
                    justificativa, xml_evento, status, data_evento, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, ?)
            ");
            $documento_tipo = 'nfe';
            $usuario_id = $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
            $stmt->execute([
                $empresa_id,
                $tipo_evento,
                $documento_tipo,
                $documento_id,
                $justificativa,
                $xml_evento,
                date('Y-m-d H:i:s'),
                $usuario_id,
            ]);

            $evento_id = (int)$conn->lastInsertId();

            $resultado_evento = processarEventoEspecificoNfe($evento_id, $tipo_evento, $documento_id, $justificativa, $documento);

            if (!empty($resultado_evento['sucesso'])) {
                echo json_encode([
                    'success' => true,
                    'message' => $resultado_evento['mensagem'] ?? 'Evento processado com sucesso!',
                    'evento_id' => $evento_id,
                    'tipo_evento' => $tipo_evento,
                    'resultado' => $resultado_evento,
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $resultado_evento['erro'] ?? 'Falha ao processar evento na SEFAZ.',
                    'evento_id' => $evento_id,
                    'tipo_evento' => $tipo_evento,
                    'resultado' => $resultado_evento,
                ]);
            }

            logOperacao('evento_fiscal', "Processou evento $tipo_evento para documento #$documento_id", 'sucesso', $evento_id, $_POST);
            break;
            
        case 'listar_eventos':
            // LISTAR EVENTOS FISCAIS
            $documento_id = $_GET['documento_id'] ?? null;
            $tipo_evento = $_GET['tipo_evento'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            
            $sql = "SELECT e.*, d.numero_nfe, d.chave_acesso 
                    FROM fiscal_eventos_fiscais e 
                    LEFT JOIN fiscal_nfe_clientes d ON e.documento_id = d.id AND d.empresa_id = e.empresa_id
                    WHERE e.empresa_id = ?";
            $params = [$empresa_id];
            
            if ($documento_id) {
                $sql .= " AND e.documento_id = ?";
                $params[] = $documento_id;
            }
            
            if ($tipo_evento) {
                $sql .= " AND e.tipo_evento = ?";
                $params[] = $tipo_evento;
            }
            
            $sql .= " ORDER BY e.data_evento DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'eventos' => $eventos
            ]);
            
            logOperacao('listar', "Listou eventos fiscais", 'sucesso');
            break;
            
        case 'acompanhar_viagem':
            // ACOMPANHAR VIAGEM COMPLETA
            $mdfe_id = $_GET['mdfe_id'] ?? null;
            
            if (!$mdfe_id) {
                throw new Exception('ID do MDF-e é obrigatório');
            }
            
            // Buscar MDF-e
            $stmt = $conn->prepare("
                SELECT * FROM fiscal_mdfe 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$mdfe_id, $empresa_id]);
            $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mdfe) {
                throw new Exception('MDF-e não encontrado');
            }
            
            // Buscar CT-e vinculados
            $stmt = $conn->prepare("
                SELECT * FROM fiscal_cte 
                WHERE mdfe_id = ? AND empresa_id = ?
                ORDER BY numero_cte
            ");
            $stmt->execute([$mdfe_id, $empresa_id]);
            $ctes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar NF-e vinculadas aos CT-e
            $nfes = [];
            foreach ($ctes as $cte) {
                if (!empty($cte['nfe_ids'])) {
                    $nfe_ids = json_decode($cte['nfe_ids'], true);
                    if (is_array($nfe_ids)) {
                        $placeholders = str_repeat('?,', count($nfe_ids) - 1) . '?';
                        $stmt = $conn->prepare("
                            SELECT * FROM fiscal_nfe_clientes 
                            WHERE id IN ($placeholders) AND empresa_id = ?
                        ");
                        $params = array_merge($nfe_ids, [$empresa_id]);
                        $stmt->execute($params);
                        $nfes_cte = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $nfes = array_merge($nfes, $nfes_cte);
                    }
                }
            }

            // Complementar NF-e por vínculo direto MDF-e x NF-e (quando existir).
            try {
                $stmtNfeVinc = $conn->prepare("
                    SELECT n.*
                    FROM fiscal_mdfe_nfe mn
                    INNER JOIN fiscal_nfe_clientes n ON n.id = mn.nfe_id
                    WHERE mn.mdfe_id = ? AND n.empresa_id = ?
                ");
                $stmtNfeVinc->execute([$mdfe_id, $empresa_id]);
                $nfesDiretas = $stmtNfeVinc->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (!empty($nfesDiretas)) {
                    $mapNfe = [];
                    foreach ($nfes as $n) {
                        $idN = (int)($n['id'] ?? 0);
                        if ($idN > 0) {
                            $mapNfe[$idN] = $n;
                        }
                    }
                    foreach ($nfesDiretas as $n) {
                        $idN = (int)($n['id'] ?? 0);
                        if ($idN > 0) {
                            $mapNfe[$idN] = $n;
                        }
                    }
                    $nfes = array_values($mapNfe);
                }
            } catch (Throwable $e) {
                // tabela pode não existir em ambiente sem migração
            }

            // Origem documental para acompanhar/relatórios.
            $qtdCteOrigem = count($ctes);
            $qtdNfeOrigem = count($nfes);
            $origemDocumental = 'manual';
            if ($qtdCteOrigem > 0 && $qtdNfeOrigem > 0) {
                $origemDocumental = 'misto';
            } elseif ($qtdCteOrigem > 0) {
                $origemDocumental = 'cte';
            } elseif ($qtdNfeOrigem > 0) {
                $origemDocumental = 'nfe';
            }
            $mdfe['origem_documental'] = $origemDocumental;
            $mdfe['qtd_cte_origem'] = $qtdCteOrigem;
            $mdfe['qtd_nfe_origem'] = $qtdNfeOrigem;
            
            // Calcular estatísticas da viagem
            $estatisticas = calcularEstatisticasViagem($mdfe, $ctes, $nfes);
            
            echo json_encode([
                'success' => true,
                'viagem' => [
                    'mdfe' => $mdfe,
                    'ctes' => $ctes,
                    'nfes' => $nfes,
                    'estatisticas' => $estatisticas
                ]
            ]);
            
            logOperacao('acompanhar', "Acompanhou viagem MDF-e #$mdfe_id", 'sucesso');
            break;
            
        case 'atualizar_status_viagem':
            // ATUALIZAR STATUS DA VIAGEM
            $mdfe_id = $_POST['mdfe_id'] ?? null;
            $novo_status = $_POST['status'] ?? null;
            $localizacao_atual = $_POST['localizacao_atual'] ?? '';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$mdfe_id || !$novo_status) {
                throw new Exception('ID do MDF-e e novo status são obrigatórios');
            }
            
            // Validar status
            $status_validos = ['rascunho', 'pendente', 'autorizado', 'em_viagem', 'entregue', 'encerrado', 'cancelado'];
            if (!in_array($novo_status, $status_validos)) {
                throw new Exception('Status inválido');
            }
            
            // Atualizar MDF-e
            $stmt = $conn->prepare("
                UPDATE fiscal_mdfe 
                SET status = ?, localizacao_atual = ?, observacoes = ?, updated_at = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$novo_status, $localizacao_atual, $observacoes, date('Y-m-d H:i:s'), $mdfe_id, $empresa_id]);
            
            if ($stmt->rowCount() > 0) {
                // Se a viagem foi finalizada, atualizar status das NF-e
                if (in_array($novo_status, ['entregue', 'encerrado'])) {
                    atualizarStatusNFesViagem($mdfe_id, 'entregue');
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Status da viagem atualizado com sucesso!',
                    'status' => $novo_status
                ]);
                
                logOperacao('atualizar_viagem', "Atualizou status da viagem MDF-e #$mdfe_id para $novo_status", 'sucesso', $mdfe_id);
            } else {
                throw new Exception('Nenhuma alteração foi feita');
            }
            break;
            
        case 'validar_consistencia':
            // VALIDAÇÕES AVANÇADAS E ALERTAS
            $tipo_validacao = $_GET['tipo'] ?? 'geral';
            $documento_id = $_GET['documento_id'] ?? null;
            
            $alertas = [];
            
            switch ($tipo_validacao) {
                case 'geral':
                    $alertas = validarConsistenciaGeral();
                    break;
                    
                case 'documento':
                    if (!$documento_id) {
                        throw new Exception('ID do documento é obrigatório para validação específica');
                    }
                    $alertas = validarConsistenciaDocumento($documento_id);
                    break;
                    
                case 'viagens':
                    $alertas = validarConsistenciaViagens();
                    break;
                    
                default:
                    throw new Exception('Tipo de validação inválido');
            }
            
            echo json_encode([
                'success' => true,
                'alertas' => $alertas,
                'total_alertas' => count($alertas),
                'tipo_validacao' => $tipo_validacao
            ]);
            
            logOperacao('validacao', "Executou validação de consistência ($tipo_validacao)", 'sucesso');
            break;
            
        case 'timeline_documento':
            // TIMELINE DE EVENTOS DO DOCUMENTO
            $documento_id = $_GET['documento_id'] ?? null;
            $tipo_documento = $_GET['tipo_documento'] ?? 'nfe';
            
            if (!$documento_id) {
                throw new Exception('ID do documento é obrigatório');
            }
            
            $timeline = gerarTimelineDocumento($documento_id, $tipo_documento);
            
            echo json_encode([
                'success' => true,
                'timeline' => $timeline,
                'total_eventos' => count($timeline)
            ]);
            
            logOperacao('timeline', "Gerou timeline do documento #$documento_id", 'sucesso');
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
    // Consulta SEFAZ de NF-e (nesta versão sem mocks)
    function consultarNFeSefaz($chave_acesso) {
        // Sem mocks: esta rotina não está integrada neste modo.
        throw new Exception('Consulta SEFAZ de NF-e não implementada nesta versão (sem dados fictícios).');
    }
    
    /**
     * Validar integridade dos dados da NF-e
     */
    function validarIntegridadeNFe($dados) {
        // Validações básicas de integridade
        if (!isset($dados['numero_nfe']) || empty($dados['numero_nfe'])) {
            return false;
        }
        
        if (!isset($dados['valor_total']) || $dados['valor_total'] <= 0) {
            return false;
        }
        
        if (!isset($dados['emitente']) || empty($dados['emitente'])) {
            return false;
        }
        
        if (!isset($dados['data_emissao']) || empty($dados['data_emissao'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar se NF-e pode ser vinculada a CT-e
     */
    function verificarVinculacaoNFe($nfe_id, $empresa_id) {
        global $conn;
        
        // Verificar se NF-e existe e está em status adequado
        $stmt = $conn->prepare("
            SELECT status, chave_acesso 
            FROM fiscal_nfe_clientes 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$nfe_id, $empresa_id]);
        $nfe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nfe) {
            return ['valida' => false, 'erro' => 'NF-e não encontrada'];
        }
        
        // Verificar se já está vinculada a algum CT-e
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM fiscal_cte 
            WHERE JSON_CONTAINS(nfe_ids, ?) AND empresa_id = ?
        ");
        $stmt->execute([json_encode([$nfe_id]), $empresa_id]);
        $vinculada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vinculada['total'] > 0) {
            return ['valida' => false, 'erro' => 'NF-e já está vinculada a outro CT-e'];
        }
        
        // Verificar status
        $status_validos = ['recebida', 'validada', 'em_transporte'];
        if (!in_array($nfe['status'], $status_validos)) {
            return ['valida' => false, 'erro' => 'Status da NF-e não permite vinculação'];
        }
        
        return ['valida' => true, 'nfe' => $nfe];
    }
    
    /**
     * Calcular estatísticas da viagem
     */
    function calcularEstatisticasViagem($mdfe, $ctes, $nfes) {
        $estatisticas = [
            'total_ctes' => count($ctes),
            'total_nfes' => count($nfes),
            'peso_total' => 0,
            'volumes_total' => 0,
            'valor_total_carga' => 0,
            'valor_total_frete' => 0,
            'distancia_estimada' => 0,
            'tempo_viagem' => null,
            'status_geral' => $mdfe['status']
        ];
        
        // Somar totais dos CT-e
        foreach ($ctes as $cte) {
            $estatisticas['peso_total'] += floatval($cte['peso_carga'] ?? 0);
            $estatisticas['volumes_total'] += intval($cte['volumes_carga'] ?? 0);
            $estatisticas['valor_total_frete'] += floatval($cte['valor_total'] ?? 0);
        }
        
        // Somar valores das NF-e
        foreach ($nfes as $nfe) {
            $estatisticas['valor_total_carga'] += floatval($nfe['valor_total'] ?? 0);
        }
        
        // Distância estimada depende de cálculo/rota real (nesta versão fica nulo)
        $estatisticas['distancia_estimada'] = null; // Sem simulação: depende de cálculo/rota real
        
        // Calcular tempo de viagem se em trânsito
        if (in_array($mdfe['status'], ['em_viagem', 'autorizado'])) {
            $inicio_viagem = strtotime($mdfe['data_emissao']);
            $agora = time();
            $estatisticas['tempo_viagem'] = gmdate('H:i:s', $agora - $inicio_viagem);
        }
        
        return $estatisticas;
    }
    
    /**
     * Atualizar status das NF-e quando viagem é finalizada
     */
    function atualizarStatusNFesViagem($mdfe_id, $novo_status) {
        global $conn, $empresa_id;
        
        // Buscar CT-e do MDF-e
        $stmt = $conn->prepare("
            SELECT nfe_ids FROM fiscal_cte 
            WHERE mdfe_id = ? AND empresa_id = ?
        ");
        $stmt->execute([$mdfe_id, $empresa_id]);
        $ctes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $nfe_ids_total = [];
        foreach ($ctes as $cte) {
            if (!empty($cte['nfe_ids'])) {
                $nfe_ids = json_decode($cte['nfe_ids'], true);
                if (is_array($nfe_ids)) {
                    $nfe_ids_total = array_merge($nfe_ids_total, $nfe_ids);
                }
            }
        }
        
        // Atualizar status das NF-e
        if (!empty($nfe_ids_total)) {
            $nfe_ids_total = array_unique($nfe_ids_total);
            $placeholders = str_repeat('?,', count($nfe_ids_total) - 1) . '?';
            
            $stmt = $conn->prepare("
                UPDATE fiscal_nfe_clientes 
                SET status = ?, updated_at = ?
                WHERE id IN ($placeholders) AND empresa_id = ?
            ");
            
            $params = array_merge([$novo_status, date('Y-m-d H:i:s')], $nfe_ids_total, [$empresa_id]);
            $stmt->execute($params);
        }
    }
    
    /**
     * Gerar timeline de eventos do documento
     */
    function gerarTimelineDocumento($documento_id, $tipo_documento = 'nfe') {
        global $conn, $empresa_id;
        
        $timeline = [];
        
        // Buscar eventos do documento
        $stmt = $conn->prepare("
            SELECT 'evento' as tipo, tipo_evento as acao, data_evento as data, 
                   justificativa as descricao, status, protocolo_evento as protocolo
            FROM fiscal_eventos_fiscais 
            WHERE documento_id = ? AND documento_tipo = 'nfe'
            ORDER BY data_evento ASC
        ");
        $stmt->execute([$documento_id]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($eventos as $evento) {
            $timeline[] = [
                'tipo' => 'evento',
                'acao' => $evento['acao'],
                'data' => $evento['data'],
                'descricao' => $evento['descricao'],
                'status' => $evento['status'],
                'protocolo' => $evento['protocolo'],
                'icone' => getIconeEvento($evento['acao']),
                'cor' => getCorEvento($evento['status'])
            ];
        }
        
        // Buscar mudanças de status (logs)
        $stmt = $conn->prepare("
            SELECT 'log' as tipo, tipo_operacao as acao, created_at as data,
                   descricao, status
            FROM logs_fiscais 
            WHERE documento_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$documento_id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($logs as $log) {
            $timeline[] = [
                'tipo' => 'log',
                'acao' => $log['acao'],
                'data' => $log['data'],
                'descricao' => $log['descricao'],
                'status' => $log['status'],
                'icone' => getIconeLog($log['acao']),
                'cor' => getCorLog($log['status'])
            ];
        }
        
        // Ordenar por data
        usort($timeline, function($a, $b) {
            return strtotime($a['data']) - strtotime($b['data']);
        });
        
        return $timeline;
    }
    
    /**
     * Obter ícone para evento
     */
    function getIconeEvento($tipo_evento) {
        $icones = [
            'cancelamento' => 'fas fa-ban',
            'carta_correcao' => 'fas fa-edit',
            'correcao' => 'fas fa-pen',
            'manifestacao' => 'fas fa-clipboard-check',
            'inutilizacao' => 'fas fa-trash'
        ];
        
        return $icones[$tipo_evento] ?? 'fas fa-calendar-alt';
    }
    
    /**
     * Obter cor para evento
     */
    function getCorEvento($status) {
        $cores = [
            'pendente' => 'warning',
            'aceito' => 'success',
            'rejeitado' => 'danger'
        ];
        
        return $cores[$status] ?? 'info';
    }
    
    /**
     * Obter ícone para log
     */
    function getIconeLog($tipo_operacao) {
        $icones = [
            'criacao' => 'fas fa-plus-circle',
            'atualizacao' => 'fas fa-edit',
            'envio_sefaz' => 'fas fa-paper-plane',
            'consulta' => 'fas fa-search',
            'validacao' => 'fas fa-check-circle'
        ];
        
        return $icones[$tipo_operacao] ?? 'fas fa-info-circle';
    }
    
    /**
     * Obter cor para log
     */
    function getCorLog($status) {
        $cores = [
            'sucesso' => 'success',
            'erro' => 'danger',
            'aviso' => 'warning'
        ];
        
        return $cores[$status] ?? 'info';
    }
    
    /**
     * Validar consistência geral do sistema
     */
    function validarConsistenciaGeral() {
        global $conn, $empresa_id;
        
        $alertas = [];
        
        // 1. NF-e sem CT-e há muito tempo
        $stmt = $conn->prepare("
            SELECT n.id, n.numero_nfe, n.data_emissao, n.status
            FROM fiscal_nfe_clientes n
            LEFT JOIN fiscal_cte c ON JSON_CONTAINS(c.nfe_ids, CAST(n.id AS JSON))
            WHERE n.empresa_id = ? 
                AND c.id IS NULL 
                AND n.status IN ('recebida', 'validada')
                AND DATEDIFF(CURDATE(), n.data_emissao) > 7
            ORDER BY n.data_emissao ASC
            LIMIT 10
        ");
        $stmt->execute([$empresa_id]);
        $nfes_sem_cte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($nfes_sem_cte as $nfe) {
            $dias = floor((time() - strtotime($nfe['data_emissao'])) / (60 * 60 * 24));
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'nfe_pendente',
                'titulo' => 'NF-e sem CT-e há ' . $dias . ' dias',
                'descricao' => "NF-e {$nfe['numero_nfe']} está sem CT-e há {$dias} dias",
                'documento_id' => $nfe['id'],
                'acao_sugerida' => 'Criar CT-e para esta NF-e',
                'prioridade' => $dias > 15 ? 'alta' : 'media'
            ];
        }
        
        // 2. CT-e sem MDF-e autorizados
        $stmt = $conn->prepare("
            SELECT id, numero_cte, data_emissao, status
            FROM fiscal_cte
            WHERE empresa_id = ? 
                AND status = 'autorizado'
                AND (mdfe_id IS NULL OR mdfe_id = 0)
                AND DATEDIFF(CURDATE(), data_emissao) > 3
            ORDER BY data_emissao ASC
            LIMIT 10
        ");
        $stmt->execute([$empresa_id]);
        $ctes_sem_mdfe = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ctes_sem_mdfe as $cte) {
            $dias = floor((time() - strtotime($cte['data_emissao'])) / (60 * 60 * 24));
            $alertas[] = [
                'tipo' => 'info',
                'categoria' => 'cte_pendente',
                'titulo' => 'CT-e autorizado sem MDF-e',
                'descricao' => "CT-e {$cte['numero_cte']} autorizado há {$dias} dias sem MDF-e",
                'documento_id' => $cte['id'],
                'acao_sugerida' => 'Incluir em MDF-e para viagem',
                'prioridade' => 'baixa'
            ];
        }
        
        // 3. MDF-e em viagem há muito tempo
        $stmt = $conn->prepare("
            SELECT id, numero_mdfe, data_emissao, status
            FROM fiscal_mdfe
            WHERE empresa_id = ? 
                AND status IN ('autorizado', 'em_viagem')
                AND DATEDIFF(CURDATE(), data_emissao) > 10
            ORDER BY data_emissao ASC
        ");
        $stmt->execute([$empresa_id]);
        $mdfe_viagem_longa = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($mdfe_viagem_longa as $mdfe) {
            $dias = floor((time() - strtotime($mdfe['data_emissao'])) / (60 * 60 * 24));
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'viagem_longa',
                'titulo' => 'Viagem em andamento há ' . $dias . ' dias',
                'descricao' => "MDF-e {$mdfe['numero_mdfe']} em viagem há {$dias} dias",
                'documento_id' => $mdfe['id'],
                'acao_sugerida' => 'Verificar status da viagem',
                'prioridade' => $dias > 20 ? 'alta' : 'media'
            ];
        }
        
        // 4. Eventos fiscais com erro
        $stmt = $conn->prepare("
            SELECT e.id, e.tipo_evento, e.data_evento, n.numero_nfe
            FROM fiscal_eventos_fiscais e
            JOIN fiscal_nfe_clientes n ON e.documento_id = n.id
            WHERE e.empresa_id = ? 
                AND e.status = 'rejeitado'
                AND DATEDIFF(CURDATE(), e.data_evento) <= 30
            ORDER BY e.data_evento DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id]);
        $eventos_erro = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($eventos_erro as $evento) {
            $alertas[] = [
                'tipo' => 'danger',
                'categoria' => 'evento_erro',
                'titulo' => 'Evento fiscal com erro',
                'descricao' => "Evento {$evento['tipo_evento']} da NF-e {$evento['numero_nfe']} com erro",
                'documento_id' => $evento['id'],
                'acao_sugerida' => 'Reprocessar evento fiscal',
                'prioridade' => 'alta'
            ];
        }
        
        return $alertas;
    }
    
    /**
     * Validar consistência de um documento específico
     */
    function validarConsistenciaDocumento($documento_id) {
        global $conn, $empresa_id;
        
        $alertas = [];
        
        // Buscar documento
        $stmt = $conn->prepare("
            SELECT * FROM fiscal_nfe_clientes 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$documento_id, $empresa_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            return [['tipo' => 'danger', 'titulo' => 'Documento não encontrado', 'descricao' => 'O documento especificado não foi encontrado']];
        }
        
        // Validar chave de acesso
        if (strlen($documento['chave_acesso']) !== 44) {
            $alertas[] = [
                'tipo' => 'danger',
                'categoria' => 'chave_invalida',
                'titulo' => 'Chave de acesso inválida',
                'descricao' => 'A chave de acesso não possui 44 dígitos',
                'prioridade' => 'alta'
            ];
        }
        
        // Validar valores
        if ($documento['valor_total'] <= 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'valor_zerado',
                'titulo' => 'Valor total zerado',
                'descricao' => 'O valor total da NF-e está zerado ou negativo',
                'prioridade' => 'media'
            ];
        }
        
        // Verificar se está vinculada a CT-e
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM fiscal_cte 
            WHERE JSON_CONTAINS(nfe_ids, ?) AND empresa_id = ?
        ");
        $stmt->execute([json_encode([$documento_id]), $empresa_id]);
        $vinculada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vinculada['total'] == 0 && in_array($documento['status'], ['recebida', 'validada'])) {
            $dias = floor((time() - strtotime($documento['data_emissao'])) / (60 * 60 * 24));
            if ($dias > 3) {
                $alertas[] = [
                    'tipo' => 'info',
                    'categoria' => 'sem_cte',
                    'titulo' => 'NF-e não vinculada a CT-e',
                    'descricao' => "NF-e está há {$dias} dias sem vinculação a CT-e",
                    'prioridade' => 'baixa'
                ];
            }
        }
        
        return $alertas;
    }
    
    /**
     * Validar consistência das viagens
     */
    function validarConsistenciaViagens() {
        global $conn, $empresa_id;
        
        $alertas = [];
        
        // Viagens sem CT-e
        $stmt = $conn->prepare("
            SELECT m.id, m.numero_mdfe, m.data_emissao, COUNT(c.id) as total_ctes
            FROM fiscal_mdfe m
            LEFT JOIN fiscal_cte c ON c.mdfe_id = m.id
            WHERE m.empresa_id = ?
            GROUP BY m.id, m.numero_mdfe, m.data_emissao
            HAVING total_ctes = 0
            ORDER BY m.data_emissao DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id]);
        $mdfe_sem_cte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($mdfe_sem_cte as $mdfe) {
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'mdfe_vazio',
                'titulo' => 'MDF-e sem CT-e',
                'descricao' => "MDF-e {$mdfe['numero_mdfe']} não possui CT-e vinculados",
                'documento_id' => $mdfe['id'],
                'acao_sugerida' => 'Adicionar CT-e ao manifesto',
                'prioridade' => 'media'
            ];
        }
        
        // Viagens com discrepâncias de peso
        $stmt = $conn->prepare("
            SELECT m.id, m.numero_mdfe, m.peso_total as peso_mdfe, 
                   SUM(c.peso_carga) as peso_ctes
            FROM fiscal_mdfe m
            JOIN fiscal_cte c ON c.mdfe_id = m.id
            WHERE m.empresa_id = ?
            GROUP BY m.id, m.numero_mdfe, m.peso_total
            HAVING ABS(peso_mdfe - peso_ctes) > 100
            ORDER BY ABS(peso_mdfe - peso_ctes) DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id]);
        $discrepancias_peso = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($discrepancias_peso as $disc) {
            $diferenca = abs($disc['peso_mdfe'] - $disc['peso_ctes']);
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'discrepancia_peso',
                'titulo' => 'Discrepância de peso na viagem',
                'descricao' => "MDF-e {$disc['numero_mdfe']} com diferença de {$diferenca}kg entre MDF-e e CT-e",
                'documento_id' => $disc['id'],
                'acao_sugerida' => 'Revisar pesos dos documentos',
                'prioridade' => 'media'
            ];
        }
        
        return $alertas;
    }
    
} catch (Throwable $e) {
    // Throwable: inclui Exception e Error (TypeError etc.), que Exception sozinho não captura
    if (strpos($e->getMessage(), 'Nenhuma alteração foi feita') === false) {
        logOperacao('erro', $e->getMessage(), 'erro', null);
    }
    error_log('documentos_fiscais_v2: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
?>
