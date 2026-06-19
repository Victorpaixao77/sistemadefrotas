<?php
/**
 * Monta XML do CT-e (layout 4.00) a partir de fiscal_cte + vínculos.
 * Para download, envolve em cteProc quando há protocolo em fiscal_cte_itens.
 */
require_once __DIR__ . '/CteContextLoader.php';
require_once __DIR__ . '/CteXmlBuilder.php';

function montarCteProcXml(PDO $conn, array $cte, array $empresa, $itens = null) {
    $empresaId = (int) ($cte['empresa_id'] ?? 0);
    if ($empresaId < 1) {
        return null;
    }

    try {
        $loader = new CteContextLoader($empresaId, $conn);
        $ctx = $loader->loadById((int) $cte['id']);
        $cteXml = CteXmlBuilder::build($ctx);
    } catch (Throwable $e) {
        error_log('montarCteProcXml builder: ' . $e->getMessage());
        return montarCteProcXmlLegado($conn, $cte, $empresa, $itens);
    }

    $itens = $ctx['itens'] ?? [];
    $tpAmb = (string) ($ctx['tp_amb'] ?? '2');
    $chave = preg_replace('/\D/', '', (string) ($cte['chave_acesso'] ?? ''));
    $nProt = trim((string) ($itens['numero_protocolo'] ?? $cte['protocolo_autorizacao'] ?? ''));

    if ($nProt === '') {
        return $cteXml;
    }

    $cStat = (string) ($itens['status_protocolo'] ?? '100');
    $xMotivo = (string) ($itens['motivo_protocolo'] ?? 'Autorizado o uso do CT-e');
    $verAplic = (string) ($itens['versao_aplicativo'] ?? 'SP-CTE-4.00');
    $dhRecbto = !empty($itens['data_protocolo'])
        ? date('Y-m-d\TH:i:sP', strtotime((string) $itens['data_protocolo']))
        : date('Y-m-d\TH:i:sP');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<cteProc xmlns="http://www.portalfiscal.inf.br/cte" versao="4.00">';
    $xml .= preg_replace('/<\?xml[^?]+\?>\s*/', '', $cteXml);
    $xml .= '<protCTe versao="4.00"><infProt>';
    $xml .= '<tpAmb>' . htmlspecialchars($tpAmb, ENT_XML1) . '</tpAmb>';
    $xml .= '<verAplic>' . htmlspecialchars($verAplic, ENT_XML1) . '</verAplic>';
    $xml .= '<chCTe>' . htmlspecialchars($chave, ENT_XML1) . '</chCTe>';
    $xml .= '<dhRecbto>' . htmlspecialchars($dhRecbto, ENT_XML1) . '</dhRecbto>';
    $xml .= '<nProt>' . htmlspecialchars($nProt, ENT_XML1) . '</nProt>';
    $xml .= '<cStat>' . htmlspecialchars($cStat, ENT_XML1) . '</cStat>';
    $xml .= '<xMotivo>' . htmlspecialchars($xMotivo, ENT_XML1) . '</xMotivo>';
    $xml .= '</infProt></protCTe></cteProc>';
    return $xml;
}

/** Fallback mínimo 3.00 — apenas se o builder 4.00 falhar. */
function montarCteProcXmlLegado(PDO $conn, array $cte, array $empresa, $itens = null) {
    if ($itens === null) {
        $stmt = $conn->prepare("SELECT * FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1");
        $stmt->execute([$cte['id']]);
        $itens = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (!is_array($itens)) {
        $itens = [];
    }

    $chave = preg_replace('/\D/', '', $cte['chave_acesso'] ?? '');
    if (strlen($chave) !== 44) {
        $chave = str_pad($chave, 44, '0', STR_PAD_LEFT);
    }
    $cUF = substr($chave, 0, 2);
    $cCT = substr($chave, 35, 8);
    $nCT = $cte['numero_cte'] ?? ltrim(substr($chave, 25, 9), '0');
    $serie = $cte['serie_cte'] ?? substr($chave, 22, 3);
    $natOp = !empty($cte['natureza_operacao']) ? $cte['natureza_operacao'] : 'TRANSPORTE DE CARGA';
    $dhEmi = ($cte['data_emissao'] ?? date('Y-m-d')) . 'T' . date('H:i:sP');
    $tpAmb = 2;
    $empresaIdCfg = $cte['empresa_id'] ?? null;
    if ($empresaIdCfg) {
        try {
            $stmtAmb = $conn->prepare("SELECT ambiente_sefaz FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1");
            $stmtAmb->execute([(int)$empresaIdCfg]);
            $amb = (string)($stmtAmb->fetchColumn() ?: '');
            $tpAmb = ($amb === 'producao') ? 1 : 2;
        } catch (Throwable $e) {
            $tpAmb = 2;
        }
    }

    $cnpjEmit = preg_replace('/\D/', '', $empresa['cnpj'] ?? '');
    $xNomeEmit = $empresa['razao_social'] ?? 'Transportadora';
    $vTPrest = number_format((float)($itens['valor_prestacao'] ?? $cte['valor_total'] ?? 0), 2, '.', '');

    $xml = '<?xml version="1.0" encoding="UTF-8"?><CTe xmlns="http://www.portalfiscal.inf.br/cte">';
    $xml .= '<infCte Id="CTe' . $chave . '" versao="4.00"><ide>';
    $xml .= '<cUF>' . $cUF . '</cUF><cCT>' . $cCT . '</cCT><CFOP>5353</CFOP>';
    $xml .= '<natOp>' . htmlspecialchars($natOp) . '</natOp><mod>57</mod><serie>' . $serie . '</serie>';
    $xml .= '<nCT>' . $nCT . '</nCT><dhEmi>' . $dhEmi . '</dhEmi><tpImp>1</tpImp><tpEmis>1</tpEmis>';
    $xml .= '<tpAmb>' . $tpAmb . '</tpAmb><tpCTe>0</tpCTe><procEmi>0</procEmi><verProc>1.0</verProc>';
    $xml .= '</ide><emit><CNPJ>' . $cnpjEmit . '</CNPJ><xNome>' . htmlspecialchars($xNomeEmit) . '</xNome></emit>';
    $xml .= '<vPrest><vTPrest>' . $vTPrest . '</vTPrest><vRec>' . $vTPrest . '</vRec></vPrest>';
    $xml .= '</infCte></CTe>';
    return $xml;
}
