<?php
/**
 * Monta o HTML do DACTE (Documento Auxiliar do CT-e) a partir do XML cteProc (modelo 57).
 * Retorna array com 'html' e 'numero' (para título).
 */
function montarDacteCompletoHtml($xml_content, array $cte_row = []) {
    $ns = 'http://www.portalfiscal.inf.br/cte';
    $xml = @simplexml_load_string($xml_content);
    $infCte = null;
    $prot = null;

    if ($xml) {
        if (isset($xml->CTe->infCte)) {
            $infCte = $xml->CTe->infCte;
        } elseif (isset($xml->children($ns)->CTe->infCte)) {
            $infCte = $xml->children($ns)->CTe->infCte;
        }
        if (isset($xml->protCTe->infProt)) {
            $prot = $xml->protCTe->infProt;
        } elseif (isset($xml->children($ns)->protCTe->infProt)) {
            $prot = $xml->children($ns)->protCTe->infProt;
        }
    }

    $val = function($node, $path = null) use ($ns) {
        if ($node === null) return '';
        if ($path !== null) {
            $parts = explode('/', $path);
            foreach ($parts as $p) {
                $node = $node->{$p} ?? $node->children($ns)->{$p} ?? null;
                if ($node === null) return '';
            }
        }
        return trim((string)$node);
    };

    $numero = $cte_row['numero_cte'] ?? '';
    $serie = $cte_row['serie_cte'] ?? '';
    $chave = $cte_row['chave_acesso'] ?? '';
    $dataEmissao = '';
    $natOp = '';
    $protocolo = '';
    $emitente = '';
    $emitCnpj = '';
    $emitEnder = '';
    $emitMun = '';
    $emitUf = '';
    $emitCep = '';
    $tomadorNome = '';
    $tomadorCnpj = '';
    $vTPrest = '0,00';
    $vRec = '0,00';
    $vCarga = '';
    $proPred = '';
    $vBC = '';
    $pICMS = '';
    $vICMS = '';
    $infCpl = '';

    if ($infCte) {
        $ide = $infCte->ide ?? $infCte->children($ns)->ide ?? null;
        $emit = $infCte->emit ?? $infCte->children($ns)->emit ?? null;
        $toma = $infCte->toma4 ?? $infCte->children($ns)->toma4 ?? null;
        $vPrest = $infCte->vPrest ?? $infCte->children($ns)->vPrest ?? null;
        $imp = $infCte->imp ?? $infCte->children($ns)->imp ?? null;
        $infNorm = $infCte->infCTeNorm ?? $infCte->children($ns)->infCTeNorm ?? null;
        $infAdic = $infCte->infAdic ?? $infCte->children($ns)->infAdic ?? null;

        if ($ide) {
            $numero = $val($ide, 'nCT') ?: $numero;
            $serie = $val($ide, 'serie') ?: $serie;
            $natOp = $val($ide, 'natOp');
            $dh = $ide->dhEmi ?? $ide->children($ns)->dhEmi ?? null;
            $dataEmissao = $dh ? date('d/m/Y H:i', strtotime((string)$dh)) : (!empty($cte_row['data_emissao']) ? date('d/m/Y', strtotime($cte_row['data_emissao'])) : '');
        }
        $chave = $chave ?: $val($infCte, '@Id');
        $chave = str_replace('CTe', '', $chave);

        if ($emit) {
            $emitente = $val($emit, 'xNome');
            $emitCnpj = $val($emit, 'CNPJ') ?: $val($emit, 'CPF');
            $ender = $emit->enderEmit ?? $emit->children($ns)->enderEmit ?? null;
            if ($ender) {
                $emitEnder = trim($val($ender, 'xLgr') . ', ' . $val($ender, 'nro') . ' - ' . $val($ender, 'xMun') . '/' . $val($ender, 'UF'), ' ,-');
                $emitMun = $val($ender, 'xMun');
                $emitUf = $val($ender, 'UF');
                $emitCep = $val($ender, 'CEP');
            }
        }
        if ($toma) {
            $tomadorNome = $val($toma, 'xNome');
            $tomadorCnpj = $val($toma, 'CNPJ') ?: $val($toma, 'CPF');
        }
        if ($vPrest) {
            $vTPrest = number_format((float)($vPrest->vTPrest ?? 0), 2, ',', '.');
            $vRec = number_format((float)($vPrest->vRec ?? 0), 2, ',', '.');
        }
        if ($imp) {
            $icms = $imp->ICMS->ICMS00 ?? $imp->children($ns)->ICMS->ICMS00 ?? null;
            if ($icms) {
                $vBC = number_format((float)($icms->vBC ?? 0), 2, ',', '.');
                $pICMS = number_format((float)($icms->pICMS ?? 0), 2, ',', '.');
                $vICMS = number_format((float)($icms->vICMS ?? 0), 2, ',', '.');
            }
        }
        if ($infNorm) {
            $carga = $infNorm->infCarga ?? $infNorm->children($ns)->infCarga ?? null;
            if ($carga) {
                $vCarga = number_format((float)($carga->vCarga ?? 0), 2, ',', '.');
                $proPred = $val($carga, 'proPred');
            }
        }
        if ($infAdic) {
            $infCpl = $val($infAdic, 'infCpl');
        }
        if ($prot) {
            $protocolo = $val($prot, 'nProt');
        }
    }

    $numero = $numero ?: ($cte_row['numero_cte'] ?? '-');
    $serie = $serie ?: ($cte_row['serie_cte'] ?? '-');
    $dataEmissao = $dataEmissao ?: (!empty($cte_row['data_emissao']) ? date('d/m/Y', strtotime($cte_row['data_emissao'])) : '-');
    $valorTotal = (float)($cte_row['valor_total'] ?? 0);
    if ($vTPrest === '0,00' && $valorTotal > 0) {
        $vTPrest = number_format($valorTotal, 2, ',', '.');
        $vRec = $vTPrest;
    }

    $fmtDoc = function($d) {
        $d = preg_replace('/\D/', '', $d);
        if (strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        if (strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        return $d;
    };

    $html = '
<style>
    body { font-family: sans-serif; font-size: 9px; }
    .dacte-title { font-size: 14px; text-align: center; font-weight: bold; border: 2px solid #000; padding: 8px; margin-bottom: 10px; }
    .bloco { border: 1px solid #333; margin-bottom: 8px; padding: 6px; }
    .bloco th { text-align: left; padding: 2px 6px; background: #e0e0e0; width: 140px; }
    .bloco td { padding: 2px 6px; }
    .col-num { text-align: right; }
    .chave-box { font-size: 7px; word-break: break-all; padding: 4px; border: 1px solid #333; margin: 4px 0; }
    .rodape { margin-top: 12px; font-size: 8px; color: #666; text-align: center; }
</style>
<div class="dacte-title">DACTE - DOCUMENTO AUXILIAR DO CONHECIMENTO DE TRANSPORTE ELETRÔNICO</div>

<table width="100%"><tr><td width="50%" valign="top">
<div class="bloco"><strong>Emitente (Transportador)</strong>
<table width="100%"><tr><th>CNPJ</th><td>' . $fmtDoc($emitCnpj) . '</td></tr>
<tr><th>Razão Social</th><td>' . htmlspecialchars($emitente ?: 'Emitente') . '</td></tr>
<tr><th>Endereço</th><td>' . htmlspecialchars($emitEnder ?: '-') . '</td></tr>
<tr><th>Município/UF</th><td>' . htmlspecialchars($emitMun . ' / ' . $emitUf) . '</td></tr>
<tr><th>CEP</th><td>' . htmlspecialchars($emitCep) . '</td></tr></table></div>
</td><td width="50%" valign="top">
<div class="bloco"><strong>Tomador do Serviço</strong>
<table width="100%"><tr><th>CNPJ</th><td>' . $fmtDoc($tomadorCnpj) . '</td></tr>
<tr><th>Razão Social</th><td>' . htmlspecialchars($tomadorNome ?: '-') . '</td></tr></table></div>
</td></tr></table>

<div class="bloco">
<table width="100%"><tr><th>Natureza da operação</th><td colspan="3">' . htmlspecialchars($natOp) . '</td></tr>
<tr><th>Número CT-e</th><td>' . htmlspecialchars($numero) . '</td><th>Série</th><td>' . htmlspecialchars($serie) . '</td></tr>
<tr><th>Data/Hora emissão</th><td>' . htmlspecialchars($dataEmissao) . '</td><th>Protocolo</th><td>' . htmlspecialchars($protocolo) . '</td></tr>
<tr><th colspan="4">Chave de acesso</th></tr>
<tr><td colspan="4" class="chave-box">' . htmlspecialchars($chave) . '</td></tr></table>
</div>

<div class="bloco"><strong>Valores</strong>
<table width="100%">
<tr><th>Valor total da prestação (vTPrest)</th><td class="col-num">R$ ' . $vTPrest . '</td></tr>
<tr><th>Valor a receber (vRec)</th><td class="col-num">R$ ' . $vRec . '</td></tr>
' . ($vCarga !== '' ? '<tr><th>Valor da carga</th><td class="col-num">R$ ' . $vCarga . '</td></tr>' : '') . '
' . (($vBC !== '' && $vICMS !== '') ? '<tr><th>Base ICMS</th><td class="col-num">R$ ' . $vBC . '</td></tr><tr><th>Alíquota ICMS</th><td class="col-num">' . $pICMS . '%</td></tr><tr><th>Valor ICMS</th><td class="col-num">R$ ' . $vICMS . '</td></tr>' : '') . '
</table></div>
';

    if ($proPred !== '') {
        $html .= '<div class="bloco"><strong>Carga</strong><table width="100%"><tr><th>Produto predominante</th><td>' . htmlspecialchars($proPred) . '</td></tr></table></div>';
    }
    if ($infCpl !== '') {
        $html .= '<div class="bloco"><strong>Informações complementares</strong><p>' . nl2br(htmlspecialchars($infCpl)) . '</p></div>';
    }

    $html .= '<p class="rodape">Documento gerado a partir do XML do CT-e. Consulte o XML para validade fiscal.</p>';

    return ['html' => $html, 'numero' => $numero];
}
