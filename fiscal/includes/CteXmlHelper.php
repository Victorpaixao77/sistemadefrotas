<?php
/**
 * Monta o XML cteProc (modelo 57) a partir de fiscal_cte + fiscal_cte_itens + dados da empresa.
 * Retorna string XML ou null em caso de erro.
 */
function montarCteProcXml(PDO $conn, array $cte, array $empresa, $itens = null) {
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
    $cCT = substr($chave, 25, 9); // nCT na chave (9 dígitos)
    $nCT = $cte['numero_cte'] ?? ltrim(substr($chave, 25, 9), '0');
    if ($nCT === '') {
        $nCT = substr($chave, 25, 9);
    }
    $serie = $cte['serie_cte'] ?? substr($chave, 22, 3);
    $cfop = '5353';
    $natOp = !empty($cte['natureza_operacao']) ? $cte['natureza_operacao'] : 'TRANSPORTE DE CARGA';
    $dataEmissao = $cte['data_emissao'] ?? date('Y-m-d');
    $dhEmi = $dataEmissao . 'T' . date('H:i:sP');

    $cnpjEmit = preg_replace('/\D/', '', $empresa['cnpj'] ?? '');
    $xNomeEmit = $empresa['razao_social'] ?? 'Transportadora';
    $xFantEmit = $empresa['nome_fantasia'] ?? $xNomeEmit;
    $ieEmit = $empresa['inscricao_estadual'] ?? '';
    $ender = $empresa['endereco'] ?? '';
    $cepEmit = preg_replace('/\D/', '', $empresa['cep'] ?? '');
    $xLgr = $ender;
    $nro = '';
    $xMun = $empresa['cidade'] ?? $empresa['municipio'] ?? 'MARINGA';
    $ufEmit = $empresa['estado'] ?? $empresa['uf'] ?? 'PR';
    if (strlen($ufEmit) > 2) {
        $ufEmit = substr($ufEmit, 0, 2);
    }

    $tomadorCnpj = preg_replace('/\D/', '', $itens['tomador_cnpj'] ?? '');
    $tomadorNome = $itens['tomador_nome'] ?? '';

    $vTPrest = isset($itens['valor_prestacao']) && $itens['valor_prestacao'] !== null && $itens['valor_prestacao'] !== ''
        ? number_format((float)$itens['valor_prestacao'], 2, '.', '')
        : number_format((float)($cte['valor_total'] ?? 0), 2, '.', '');
    $vRec = isset($itens['valor_receber']) && $itens['valor_receber'] !== null && $itens['valor_receber'] !== ''
        ? number_format((float)$itens['valor_receber'], 2, '.', '')
        : $vTPrest;
    $compNome = $itens['comp_nome'] ?? 'FRETE VALOR BASE';
    $compValor = isset($itens['comp_valor']) && $itens['comp_valor'] !== null
        ? number_format((float)$itens['comp_valor'], 2, '.', '')
        : $vTPrest;

    $vCarga = isset($itens['valor_carga']) && $itens['valor_carga'] !== null
        ? number_format((float)$itens['valor_carga'], 2, '.', '')
        : '0.00';
    $proPred = $itens['produto_predominante'] ?? 'CARGA GERAL';

    $icmsCst = $itens['icms_cst'] ?? '00';
    $vBC = isset($itens['icms_vbc']) && $itens['icms_vbc'] !== null
        ? number_format((float)$itens['icms_vbc'], 2, '.', '')
        : $vTPrest;
    $pICMS = isset($itens['icms_picms']) && $itens['icms_picms'] !== null
        ? number_format((float)$itens['icms_picms'], 2, '.', '')
        : '12.00';
    $vICMS = isset($itens['icms_vicms']) && $itens['icms_vicms'] !== null
        ? number_format((float)$itens['icms_vicms'], 2, '.', '')
        : number_format((float)$vTPrest * 0.12, 2, '.', '');

    $infCpl = $itens['inf_complementar'] ?? '';
    if ($infCpl === '' && (!empty($cte['observacoes']))) {
        $infCpl = $cte['observacoes'];
    }

    $dhRecbto = '';
    $nProt = '';
    $cStat = '';
    $xMotivo = '';
    $verAplic = '';
    if (!empty($itens['numero_protocolo'])) {
        $nProt = $itens['numero_protocolo'];
        $cStat = $itens['status_protocolo'] ?? '100';
        $xMotivo = $itens['motivo_protocolo'] ?? 'Autorizado o uso do CT-e';
        $verAplic = $itens['versao_aplicativo'] ?? 'SP-CTE-3.00';
        $dhRecbto = !empty($itens['data_protocolo'])
            ? date('Y-m-d\TH:i:sP', strtotime($itens['data_protocolo']))
            : $dhEmi;
    }

    $idInfCte = 'CTe' . $chave;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<cteProc xmlns="http://www.portalfiscal.inf.br/cte" versao="3.00">' . "\n";
    $xml .= '  <CTe>' . "\n";
    $xml .= '    <infCte Id="' . htmlspecialchars($idInfCte) . '" versao="3.00">' . "\n";
    $xml .= "\n      <ide>\n";
    $xml .= '        <cUF>' . $cUF . '</cUF>' . "\n";
    $xml .= '        <cCT>' . $cCT . '</cCT>' . "\n";
    $xml .= '        <CFOP>' . $cfop . '</CFOP>' . "\n";
    $xml .= '        <natOp>' . htmlspecialchars($natOp) . '</natOp>' . "\n";
    $xml .= '        <mod>57</mod>' . "\n";
    $xml .= '        <serie>' . $serie . '</serie>' . "\n";
    $xml .= '        <nCT>' . $nCT . '</nCT>' . "\n";
    $xml .= '        <dhEmi>' . $dhEmi . '</dhEmi>' . "\n";
    $xml .= '        <tpImp>1</tpImp>' . "\n";
    $xml .= '        <tpEmis>1</tpEmis>' . "\n";
    $xml .= '        <tpAmb>1</tpAmb>' . "\n";
    $xml .= '        <tpCTe>0</tpCTe>' . "\n";
    $xml .= '        <procEmi>0</procEmi>' . "\n";
    $xml .= '        <verProc>1.0</verProc>' . "\n";
    $xml .= "      </ide>\n\n";

    $xml .= '      <emit>' . "\n";
    $xml .= '        <CNPJ>' . $cnpjEmit . '</CNPJ>' . "\n";
    $xml .= '        <xNome>' . htmlspecialchars($xNomeEmit) . '</xNome>' . "\n";
    $xml .= '        <xFant>' . htmlspecialchars($xFantEmit) . '</xFant>' . "\n";
    $xml .= '        <IE>' . htmlspecialchars($ieEmit) . '</IE>' . "\n";
    $xml .= '        <enderEmit>' . "\n";
    $xml .= '          <xLgr>' . htmlspecialchars($xLgr) . '</xLgr>' . "\n";
    $xml .= '          <nro>' . htmlspecialchars($nro) . '</nro>' . "\n";
    $xml .= '          <xMun>' . htmlspecialchars($xMun) . '</xMun>' . "\n";
    $xml .= '          <UF>' . $ufEmit . '</UF>' . "\n";
    $xml .= '          <CEP>' . $cepEmit . '</CEP>' . "\n";
    $xml .= "        </enderEmit>\n";
    $xml .= "      </emit>\n\n";

    if ($tomadorCnpj !== '' || $tomadorNome !== '') {
        $xml .= '      <toma4>' . "\n";
        if ($tomadorCnpj !== '') {
            $xml .= '        <CNPJ>' . $tomadorCnpj . '</CNPJ>' . "\n";
        }
        $xml .= '        <xNome>' . htmlspecialchars($tomadorNome ?: 'Tomador') . '</xNome>' . "\n";
        $xml .= "      </toma4>\n\n";
    }

    $xml .= '      <vPrest>' . "\n";
    $xml .= '        <vTPrest>' . $vTPrest . '</vTPrest>' . "\n";
    $xml .= '        <vRec>' . $vRec . '</vRec>' . "\n";
    $xml .= '        <Comp>' . "\n";
    $xml .= '          <xNome>' . htmlspecialchars($compNome) . '</xNome>' . "\n";
    $xml .= '          <vComp>' . $compValor . '</vComp>' . "\n";
    $xml .= "        </Comp>\n";
    $xml .= "      </vPrest>\n\n";

    $xml .= '      <imp>' . "\n";
    $xml .= '        <ICMS>' . "\n";
    $xml .= '          <ICMS00>' . "\n";
    $xml .= '            <CST>' . $icmsCst . '</CST>' . "\n";
    $xml .= '            <vBC>' . $vBC . '</vBC>' . "\n";
    $xml .= '            <pICMS>' . $pICMS . '</pICMS>' . "\n";
    $xml .= '            <vICMS>' . $vICMS . '</vICMS>' . "\n";
    $xml .= "          </ICMS00>\n";
    $xml .= "        </ICMS>\n";
    $xml .= "      </imp>\n\n";

    $xml .= '      <infCTeNorm>' . "\n";
    $xml .= '        <infCarga>' . "\n";
    $xml .= '          <vCarga>' . $vCarga . '</vCarga>' . "\n";
    $xml .= '          <proPred>' . htmlspecialchars($proPred) . '</proPred>' . "\n";
    $xml .= "        </infCarga>\n";
    $xml .= "      </infCTeNorm>\n\n";

    if ($infCpl !== '') {
        $xml .= '      <infAdic>' . "\n";
        $xml .= '        <infCpl>' . htmlspecialchars($infCpl) . '</infCpl>' . "\n";
        $xml .= "      </infAdic>\n\n";
    }

    $xml .= "    </infCte>\n  </CTe>\n";

    if ($nProt !== '') {
        $xml .= "\n  <protCTe>\n";
        $xml .= "    <infProt>\n";
        $xml .= '      <tpAmb>1</tpAmb>' . "\n";
        $xml .= '      <verAplic>' . htmlspecialchars($verAplic) . '</verAplic>' . "\n";
        $xml .= '      <chCTe>' . $chave . '</chCTe>' . "\n";
        $xml .= '      <dhRecbto>' . $dhRecbto . '</dhRecbto>' . "\n";
        $xml .= '      <nProt>' . $nProt . '</nProt>' . "\n";
        $xml .= '      <cStat>' . $cStat . '</cStat>' . "\n";
        $xml .= '      <xMotivo>' . htmlspecialchars($xMotivo) . '</xMotivo>' . "\n";
        $xml .= "    </infProt>\n";
        $xml .= "  </protCTe>\n";
    }

    $xml .= "</cteProc>\n";
    return $xml;
}
