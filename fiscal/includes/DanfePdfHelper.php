<?php
/**
 * Monta o HTML do DANFE completo a partir do XML da NF-e (para geração de PDF).
 * Retorna array com 'html' e 'numero' (para título).
 */
function montarDanfeCompletoHtml($xml_content, array $row) {
    $ns = 'http://www.portalfiscal.inf.br/nfe';
    $xml = null;
    if (is_string($xml_content) && trim($xml_content) !== '') {
        $xml = @simplexml_load_string($xml_content);
    }
    $nfe_node = null;
    if ($xml) {
        if (isset($xml->NFe)) {
            $nfe_node = $xml->NFe;
        } elseif (isset($xml->nfeProc->NFe)) {
            $nfe_node = $xml->nfeProc->NFe;
        }
        if (!$nfe_node && isset($xml->children($ns)->NFe)) {
            $nfe_node = $xml->children($ns)->NFe;
        }
    }
    $inf = $nfe_node ? ($nfe_node->infNFe ?? $nfe_node->children($ns)->infNFe ?? null) : null;

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

    $numero = $serie = $chave = $data_emissao = $natOp = $protocolo = '';
    $emitente = $emit_cnpj = $emit_ie = $emit_ender = $emit_mun = $emit_uf = $emit_cep = '';
    $dest_nome = $dest_cnpj = $dest_ie = $dest_ender = $dest_mun = $dest_uf = $dest_cep = '';
    $totais = [];
    $itens = [];
    $transp = [];
    $inf_adic = '';

    if ($inf) {
        $ide = $inf->ide ?? $inf->children($ns)->ide ?? null;
        $emit = $inf->emit ?? $inf->children($ns)->emit ?? null;
        $dest = $inf->dest ?? $inf->children($ns)->dest ?? null;
        $total = $inf->total->ICMSTot ?? $inf->children($ns)->total->ICMSTot ?? null;
        $transp_node = $inf->transp ?? $inf->children($ns)->transp ?? null;
        $inf_adic_node = $inf->infAdic ?? $inf->children($ns)->infAdic ?? null;

        if ($ide) {
            $numero = $val($ide, 'nNF') ?: ($row['numero_nfe'] ?? '');
            $serie = $val($ide, 'serie') ?: ($row['serie_nfe'] ?? '');
            $data_emissao = $ide->dhEmi ? date('d/m/Y H:i', strtotime((string)$ide->dhEmi)) : (!empty($row['data_emissao']) ? date('d/m/Y', strtotime($row['data_emissao'])) : '');
            $natOp = $val($ide, 'natOp');
        }
        $chave = $row['chave_acesso'] ?? '';
        $protocolo = $row['protocolo_autorizacao'] ?? '';

        if ($emit) {
            $emitente = $val($emit, 'xNome');
            $emit_cnpj = $val($emit, 'CNPJ') ?: $val($emit, 'CPF');
            $emit_ie = $val($emit, 'IE');
            $ender = $emit->enderEmit ?? $emit->children($ns)->enderEmit ?? null;
            if ($ender) {
                $emit_ender = trim($val($ender, 'xLgr') . ', ' . $val($ender, 'nro') . ' - ' . $val($ender, 'xBairro') . ' - ' . $val($ender, 'xMun') . '/' . $val($ender, 'UF'), ' ,-');
                $emit_cep = $val($ender, 'CEP');
                $emit_mun = $val($ender, 'xMun');
                $emit_uf = $val($ender, 'UF');
            }
        }
        if ($dest) {
            $dest_nome = $val($dest, 'xNome');
            $dest_cnpj = $val($dest, 'CNPJ') ?: $val($dest, 'CPF');
            $dest_ie = $val($dest, 'IE');
            $ender = $dest->enderDest ?? $dest->children($ns)->enderDest ?? null;
            if ($ender) {
                $dest_ender = trim($val($ender, 'xLgr') . ', ' . $val($ender, 'nro') . ' - ' . $val($ender, 'xBairro') . ' - ' . $val($ender, 'xMun') . '/' . $val($ender, 'UF'), ' ,-');
                $dest_cep = $val($ender, 'CEP');
                $dest_mun = $val($ender, 'xMun');
                $dest_uf = $val($ender, 'UF');
            }
        }
        if ($total) {
            $totais = [
                'vBC' => (float)($total->vBC ?? 0), 'vICMS' => (float)($total->vICMS ?? 0),
                'vICMSDeson' => (float)($total->vICMSDeson ?? 0), 'vFCP' => (float)($total->vFCP ?? 0),
                'vBCST' => (float)($total->vBCST ?? 0), 'vST' => (float)($total->vST ?? 0),
                'vFCPST' => (float)($total->vFCPST ?? 0), 'vFCPSTRet' => (float)($total->vFCPSTRet ?? 0),
                'qBCMonoRet' => (float)($total->qBCMonoRet ?? 0),
                'vICMSMonoRet' => (float)($total->vICMSMonoRet ?? 0),
                'vProd' => (float)($total->vProd ?? 0), 'vFrete' => (float)($total->vFrete ?? 0),
                'vSeg' => (float)($total->vSeg ?? 0), 'vDesc' => (float)($total->vDesc ?? 0),
                'vII' => (float)($total->vII ?? 0), 'vIPI' => (float)($total->vIPI ?? 0),
                'vIPIDevol' => (float)($total->vIPIDevol ?? 0), 'vPIS' => (float)($total->vPIS ?? 0),
                'vCOFINS' => (float)($total->vCOFINS ?? 0), 'vOutro' => (float)($total->vOutro ?? 0),
                'vNF' => (float)($total->vNF ?? 0), 'vTotTrib' => (float)($total->vTotTrib ?? 0),
            ];
        }
        $dets = $inf->det ?? $inf->children($ns)->det ?? [];
        if (!is_array($dets) && !($dets instanceof \Traversable)) {
            $dets = $dets ? [$dets] : [];
        }
        foreach ($dets as $det) {
            $prod = $det->prod ?? $det->children($ns)->prod ?? null;
            if (!$prod) continue;
            $itens[] = [
                'nItem' => $val($det, 'nItem'),
                'cProd' => $val($prod, 'cProd'),
                'xProd' => $val($prod, 'xProd'),
                'NCM' => $val($prod, 'NCM'),
                'CFOP' => $val($prod, 'CFOP'),
                'uCom' => $val($prod, 'uCom'),
                'qCom' => (float)($prod->qCom ?? 0),
                'vUnCom' => (float)($prod->vUnCom ?? 0),
                'vProd' => (float)($prod->vProd ?? 0),
                'uTrib' => $val($prod, 'uTrib'),
                'qTrib' => (float)($prod->qTrib ?? 0),
            ];
        }
        if ($transp_node) {
            $modFrete = $val($transp_node, 'modFrete');
            $transporta = $transp_node->transporta ?? $transp_node->children($ns)->transporta ?? null;
            if ($transporta) {
                $transp['transportador'] = $val($transporta, 'xNome');
                $transp['cnpj'] = $val($transporta, 'CNPJ') ?: $val($transporta, 'CPF');
                $transp['ender'] = $val($transporta, 'xEnder');
                $transp['mun'] = $val($transporta, 'xMun');
                $transp['uf'] = $val($transporta, 'UF');
            }
            $veic = $transp_node->veicTransp ?? $transp_node->children($ns)->veicTransp ?? null;
            if ($veic) {
                $transp['placa'] = $val($veic, 'placa');
                $transp['uf_veic'] = $val($veic, 'UF');
                $transp['rntc'] = $val($veic, 'RNTC');
            }
            $vol = $transp_node->vol ?? $transp_node->children($ns)->vol ?? null;
            if ($vol) {
                $transp['qVol'] = $val($vol, 'qVol');
                $transp['esp'] = $val($vol, 'esp');
                $transp['marca'] = $val($vol, 'marca');
                $transp['nVol'] = $val($vol, 'nVol');
                $transp['pesoL'] = $val($vol, 'pesoL');
                $transp['pesoB'] = $val($vol, 'pesoB');
            }
            $transp['modFrete'] = $modFrete;
        }
        if ($inf_adic_node) {
            $fisco = trim($val($inf_adic_node, 'infAdFisco'));
            $cpl = trim($val($inf_adic_node, 'infCpl'));
            $inf_adic = implode("\n\n", array_filter([$fisco, $cpl]));
        }
    }

    $fromXml = $inf !== null;

    $numero = $numero ?: ($row['numero_nfe'] ?? '-');
    $serie = $serie ?: ($row['serie_nfe'] ?? '-');
    $chave = $chave ?: ($row['chave_acesso'] ?? '');
    $data_emissao = $data_emissao ?: (!empty($row['data_emissao']) ? date('d/m/Y H:i', strtotime($row['data_emissao'])) : '-');
    $emitente = $emitente ?: ($row['cliente_razao_social'] ?? 'Emitente');
    $valor = (isset($totais['vNF']) && $totais['vNF'] > 0)
        ? number_format($totais['vNF'], 2, ',', '.')
        : number_format((float)($row['valor_total'] ?? 0), 2, ',', '.');
    $protocolo = $protocolo ?: ($row['protocolo_autorizacao'] ?? '');
    if (empty($dest_nome)) $dest_nome = 'Destinatário';
    if (empty($emit_ender)) $emit_ender = '-';
    if (empty($dest_ender)) $dest_ender = '-';

    $fmtDoc = function($d) {
        $d = preg_replace('/\D/', '', $d);
        if (strlen($d) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
        if (strlen($d) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
        return $d;
    };

    $html_itens = '';
    foreach ($itens as $i) {
        $vUn = $i['vUnCom'] > 0 ? number_format($i['vUnCom'], 4, ',', '.') : '-';
        $qCom = $i['qCom'] != 0 ? number_format($i['qCom'], 4, ',', '.') : '-';
        $vProd = number_format($i['vProd'], 2, ',', '.');
        $html_itens .= '<tr><td>' . htmlspecialchars($i['nItem']) . '</td><td>' . htmlspecialchars($i['cProd']) . '</td><td>' . htmlspecialchars(mb_substr($i['xProd'], 0, 120)) . '</td><td>' . htmlspecialchars($i['NCM']) . '</td><td>' . htmlspecialchars($i['CFOP']) . '</td><td>' . htmlspecialchars($i['uCom']) . '</td><td class="col-num">' . $qCom . '</td><td class="col-num">' . $vUn . '</td><td class="col-num">' . $vProd . '</td></tr>';
    }
    if ($html_itens === '') {
        $html_itens = '<tr><td colspan="9">Nenhum item informado no XML.</td></tr>';
    }

    $html_totais = '';
    if (!empty($totais)) {
        $labels = [
            'vBC' => 'Base de Cálculo do ICMS', 'vICMS' => 'Valor do ICMS', 'vICMSDeson' => 'ICMS Desoneração',
            'vFCP' => 'Valor do FCP', 'vBCST' => 'Base de Cálculo do ICMS ST', 'vST' => 'Valor do ICMS ST',
            'vFCPST' => 'Valor do FCP ST', 'vFCPSTRet' => 'FCP ST Retido',
            'qBCMonoRet' => 'Qtd BC ICMS monofásico retido', 'vICMSMonoRet' => 'Valor ICMS monofásico retido',
            'vProd' => 'Valor dos Produtos',
            'vFrete' => 'Valor do Frete', 'vSeg' => 'Valor do Seguro', 'vDesc' => 'Desconto',
            'vII' => 'Valor do II', 'vIPI' => 'Valor do IPI', 'vIPIDevol' => 'IPI Devolvido',
            'vPIS' => 'Valor do PIS', 'vCOFINS' => 'Valor do COFINS', 'vOutro' => 'Outras Despesas',
            'vNF' => 'Valor Total da NF-e', 'vTotTrib' => 'Valor Total dos Tributos'
        ];
        foreach ($labels as $k => $l) {
            if (isset($totais[$k]) && $totais[$k] != 0) {
                $html_totais .= '<tr><td>' . $l . '</td><td class="col-num">R$ ' . number_format($totais[$k], 2, ',', '.') . '</td></tr>';
            }
        }
    }
    if ($html_totais === '') {
        $html_totais = '<tr><td>Valor Total da NF-e</td><td class="col-num"><strong>R$ ' . $valor . '</strong></td></tr>';
    }

    $html_transp = '';
    if (!empty($transp)) {
        $modFreteL = ['0' => 'Emitente', '1' => 'Destinatário', '2' => 'Terceiros', '9' => 'Sem frete'];
        $html_transp = '<tr><th colspan="2">Transporte</th></tr>';
        $html_transp .= '<tr><td>Modalidade do frete</td><td>' . ($modFreteL[$transp['modFrete'] ?? ''] ?? $transp['modFrete'] ?? '-') . '</td></tr>';
        if (!empty($transp['transportador'])) $html_transp .= '<tr><td>Transportador</td><td>' . htmlspecialchars($transp['transportador']) . '</td></tr>';
        if (!empty($transp['cnpj'])) $html_transp .= '<tr><td>CNPJ/CPF</td><td>' . $fmtDoc($transp['cnpj']) . '</td></tr>';
        if (!empty($transp['placa'])) $html_transp .= '<tr><td>Placa / UF</td><td>' . htmlspecialchars($transp['placa'] . ' / ' . ($transp['uf_veic'] ?? '')) . '</td></tr>';
        if (!empty($transp['qVol'])) $html_transp .= '<tr><td>Volumes</td><td>' . htmlspecialchars($transp['qVol']) . '</td></tr>';
        if (!empty($transp['pesoB'])) $html_transp .= '<tr><td>Peso Bruto (kg)</td><td>' . htmlspecialchars($transp['pesoB']) . '</td></tr>';
    }

    $html_adic = $inf_adic ? '<div class="bloco"><strong>Informações adicionais</strong><p>' . nl2br(htmlspecialchars($inf_adic)) . '</p></div>' : '';

    $html = '
<style>
    body { font-family: sans-serif; font-size: 9px; }
    .danfe-title { font-size: 14px; text-align: center; font-weight: bold; border: 2px solid #000; padding: 8px; margin-bottom: 10px; }
    .bloco { border: 1px solid #333; margin-bottom: 8px; padding: 6px; }
    .bloco th { text-align: left; padding: 2px 6px; background: #e0e0e0; width: 140px; }
    .bloco td { padding: 2px 6px; }
    table.tabela { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 8px; }
    table.tabela th, table.tabela td { border: 1px solid #333; padding: 4px; }
    table.tabela th { background: #e0e0e0; }
    .col-num { text-align: right; }
    .chave-box { font-size: 7px; word-break: break-all; padding: 4px; border: 1px solid #333; margin: 4px 0; }
    .rodape { margin-top: 12px; font-size: 8px; color: #666; text-align: center; }
</style>
<div class="danfe-title">DANFE - NOTA FISCAL ELETRÔNICA</div>

<table width="100%"><tr><td width="50%" valign="top">
<div class="bloco"><strong>Emitente</strong>
<table width="100%"><tr><th>CNPJ/CPF</th><td>' . $fmtDoc($emit_cnpj) . '</td></tr>
<tr><th>Inscrição Estadual</th><td>' . htmlspecialchars($emit_ie) . '</td></tr>
<tr><th>Razão Social</th><td>' . htmlspecialchars($emitente) . '</td></tr>
<tr><th>Endereço</th><td>' . htmlspecialchars($emit_ender) . '</td></tr>
<tr><th>Município/UF</th><td>' . htmlspecialchars($emit_mun . ' / ' . $emit_uf) . '</td></tr>
<tr><th>CEP</th><td>' . htmlspecialchars($emit_cep) . '</td></tr></table></div>
</td><td width="50%" valign="top">
<div class="bloco"><strong>Destinatário / Remetente</strong>
<table width="100%"><tr><th>CNPJ/CPF</th><td>' . $fmtDoc($dest_cnpj) . '</td></tr>
<tr><th>Inscrição Estadual</th><td>' . htmlspecialchars($dest_ie) . '</td></tr>
<tr><th>Razão Social</th><td>' . htmlspecialchars($dest_nome) . '</td></tr>
<tr><th>Endereço</th><td>' . htmlspecialchars($dest_ender) . '</td></tr>
<tr><th>Município/UF</th><td>' . htmlspecialchars(($dest_mun ?? '') . ' / ' . ($dest_uf ?? '')) . '</td></tr>
<tr><th>CEP</th><td>' . htmlspecialchars($dest_cep ?? '') . '</td></tr></table></div>
</td></tr></table>

<div class="bloco">
<table width="100%"><tr><th>Natureza da operação</th><td colspan="3">' . htmlspecialchars($natOp) . '</td></tr>
<tr><th>Número</th><td>' . htmlspecialchars($numero) . '</td><th>Série</th><td>' . htmlspecialchars($serie) . '</td></tr>
<tr><th>Data/Hora emissão</th><td>' . htmlspecialchars($data_emissao) . '</td><th>Protocolo</th><td>' . htmlspecialchars($protocolo) . '</td></tr>
<tr><th colspan="4">Chave de acesso</th></tr>
<tr><td colspan="4" class="chave-box">' . htmlspecialchars($chave) . '</td></tr></table>
</div>

<div class="bloco"><strong>Produtos / Serviços</strong>
<table class="tabela"><tr><th>Item</th><th>Cód.</th><th>Descrição</th><th>NCM</th><th>CFOP</th><th>Un.</th><th>Qtd</th><th>Vl. Unit.</th><th>Vl. Total</th></tr>
' . $html_itens . '
</table></div>

<div class="bloco"><strong>Totais</strong>
<table width="100%">' . $html_totais . '</table></div>
' . (!empty($html_transp) ? '<div class="bloco"><table width="100%">' . $html_transp . '</table></div>' : '') . '
' . $html_adic . '
<p class="rodape">' . ($fromXml
        ? 'Documento gerado a partir do XML da NF-e. Consulte o XML para validade fiscal.'
        : 'Resumo gerado a partir dos dados cadastrados (sem XML completo no sistema). Quando possível, use o download de XML ou consulte a SEFAZ para obter o nfeProc.') . '</p>
';

    return ['html' => $html, 'numero' => $numero];
}
