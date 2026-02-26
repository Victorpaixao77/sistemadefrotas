<?php
/**
 * Importa XML cteProc (modelo 57) da SEFAZ: atualiza fiscal_cte e fiscal_cte_itens.
 * Retorna array ['success' => bool, 'cte_id' => int, 'message' => string] ou throw.
 */
function importarXmlCteProc(PDO $conn, $xml_content, $empresa_id) {
    if (function_exists('logCteDebug')) {
        logCteDebug('CT-e importação iniciada', ['empresa_id' => $empresa_id, 'xml_tamanho' => strlen($xml_content)]);
    }
    $ns = 'http://www.portalfiscal.inf.br/cte';
    $xml = @simplexml_load_string($xml_content);
    if ($xml === false) {
        if (function_exists('logCteDebug')) {
            logCteDebug('CT-e importação falhou: XML inválido (simplexml_load_string)', []);
        }
        throw new Exception('XML inválido.');
    }

    $infCte = null;
    $prot = null;
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

    if (!$infCte) {
        if (function_exists('logCteDebug')) {
            logCteDebug('CT-e importação falhou: XML não contém infCte', []);
        }
        throw new Exception('XML não contém infCte (não é um CT-e modelo 57).');
    }

    $v = function($node, $path = null) use ($ns) {
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

    $idAttr = (string)($infCte['Id'] ?? '');
    $chave = preg_replace('/\D/', '', str_replace('CTe', '', $idAttr));
    if (strlen($chave) !== 44) {
        throw new Exception('Chave do CT-e inválida no XML.');
    }

    $ide = $infCte->ide ?? $infCte->children($ns)->ide ?? null;
    $natOp = $ide ? $v($ide, 'natOp') : '';
    $nCT = $ide ? $v($ide, 'nCT') : ltrim(substr($chave, 25, 9), '0');
    $serie = $ide ? $v($ide, 'serie') : substr($chave, 22, 3);
    $dhEmi = $ide ? $v($ide, 'dhEmi') : '';
    $data_emissao = $dhEmi ? date('Y-m-d', strtotime(substr($dhEmi, 0, 10))) : date('Y-m-d');

    $vTPrest = 0.00;
    $vRec = 0.00;
    $compNome = '';
    $compValor = null;
    $vPrest = $infCte->vPrest ?? $infCte->children($ns)->vPrest ?? null;
    if ($vPrest) {
        $vTPrest = (float)($vPrest->vTPrest ?? 0);
        $vRec = (float)($vPrest->vRec ?? 0);
        $comp = $vPrest->Comp ?? $vPrest->children($ns)->Comp ?? null;
        if ($comp) {
            $compNome = $v($comp, 'xNome');
            $compValor = (float)($comp->vComp ?? 0);
        }
        if ($compNome === '' && $vTPrest > 0) {
            $compNome = 'FRETE';
            $compValor = $vTPrest;
        }
    }

    $tomador_cnpj = '';
    $tomador_nome = '';
    $toma = $infCte->toma4 ?? $infCte->children($ns)->toma4 ?? null;
    if ($toma) {
        $tomador_cnpj = preg_replace('/\D/', '', (string)($toma->CNPJ ?? $toma->CPF ?? ''));
        $tomador_nome = $v($toma, 'xNome');
    }

    $vBC = $pICMS = $vICMS = null;
    $icms_cst = '00';
    $imp = $infCte->imp ?? $infCte->children($ns)->imp ?? null;
    if ($imp) {
        $icms00 = $imp->ICMS->ICMS00 ?? $imp->children($ns)->ICMS->ICMS00 ?? null;
        if (!$icms00 && isset($imp->ICMS->ICMS45)) {
            $icms45 = $imp->ICMS->ICMS45 ?? $imp->children($ns)->ICMS->ICMS45 ?? null;
            if ($icms45) $icms_cst = (string)($icms45->CST ?? '45');
        } elseif ($icms00) {
            $icms_cst = (string)($icms00->CST ?? '00');
            $vBC = (float)($icms00->vBC ?? 0);
            $pICMS = (float)($icms00->pICMS ?? 0);
            $vICMS = (float)($icms00->vICMS ?? 0);
        }
    }

    $valor_carga = null;
    $proPred = '';
    $infNorm = $infCte->infCTeNorm ?? $infCte->children($ns)->infCTeNorm ?? null;
    if ($infNorm) {
        $carga = $infNorm->infCarga ?? $infNorm->children($ns)->infCarga ?? null;
        if ($carga) {
            $valor_carga = (float)($carga->vCarga ?? 0);
            $proPred = $v($carga, 'proPred');
        }
    }

    $infCpl = '';
    $infAdic = $infCte->infAdic ?? $infCte->children($ns)->infAdic ?? null;
    if ($infAdic) {
        $infCpl = $v($infAdic, 'infCpl');
    }

    $origem_cidade = '';
    $origem_estado = '';
    $destino_cidade = '';
    $destino_estado = '';
    $rem = $infCte->rem ?? $infCte->children($ns)->rem ?? null;
    if ($rem) {
        $enderReme = $rem->enderReme ?? $rem->children($ns)->enderReme ?? null;
        if ($enderReme) {
            $origem_cidade = $v($enderReme, 'xMun');
            $origem_estado = $v($enderReme, 'UF');
        }
    }
    $dest = $infCte->dest ?? $infCte->children($ns)->dest ?? null;
    if ($dest) {
        $enderDest = $dest->enderDest ?? $dest->enderDest ?? $dest->children($ns)->enderDest ?? null;
        if ($enderDest) {
            $destino_cidade = $v($enderDest, 'xMun');
            $destino_estado = $v($enderDest, 'UF');
        }
    }

    $nProt = '';
    $dhRecbto = null;
    $cStat = '';
    $xMotivo = '';
    $verAplic = '';
    if ($prot) {
        $nProt = $v($prot, 'nProt');
        $cStat = $v($prot, 'cStat');
        $xMotivo = $v($prot, 'xMotivo');
        $verAplic = $v($prot, 'verAplic');
        $dhR = $v($prot, 'dhRecbto');
        if ($dhR) $dhRecbto = date('Y-m-d H:i:s', strtotime($dhR));
    }

    $status = ($cStat === '100' || $cStat === '150') ? 'autorizado' : 'pendente';
    $protocolo_autorizacao = $nProt;

    $stmt = $conn->prepare("SELECT id, numero_cte, serie_cte FROM fiscal_cte WHERE chave_acesso = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$chave, $empresa_id]);
    $cte_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (function_exists('logCteDebug')) {
        logCteDebug('CT-e importação parse', [
            'chave' => $chave,
            'valor_prestacao' => $vTPrest,
            'tomador' => $tomador_nome,
            'origem' => $origem_cidade . '/' . $origem_estado,
            'destino' => $destino_cidade . '/' . $destino_estado,
        ]);
    }

    if ($cte_row) {
        $cte_id = (int)$cte_row['id'];
        $conn->prepare("
            UPDATE fiscal_cte SET
            valor_total = ?, data_emissao = ?, natureza_operacao = ?,
            origem_cidade = ?, origem_estado = ?, destino_cidade = ?, destino_estado = ?,
            protocolo_autorizacao = ?, status = ?, xml_cte = ?, observacoes = ?
            WHERE id = ? AND empresa_id = ?
        ")->execute([
            $vTPrest, $data_emissao, $natOp ?: null,
            $origem_cidade ?: null, $origem_estado ?: null, $destino_cidade ?: null, $destino_estado ?: null,
            $protocolo_autorizacao ?: null, $status, $xml_content, $infCpl ?: null,
            $cte_id, $empresa_id
        ]);
    } else {
        $conn->prepare("
            INSERT INTO fiscal_cte (empresa_id, numero_cte, serie_cte, chave_acesso, data_emissao, natureza_operacao,
            valor_total, origem_cidade, origem_estado, destino_cidade, destino_estado, protocolo_autorizacao, status, xml_cte, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $empresa_id, $nCT, $serie, $chave, $data_emissao, $natOp ?: null,
            $vTPrest, $origem_cidade ?: null, $origem_estado ?: null, $destino_cidade ?: null, $destino_estado ?: null,
            $protocolo_autorizacao ?: null, $status, $xml_content, $infCpl ?: null
        ]);
        $cte_id = (int)$conn->lastInsertId();
    }

    $stmt = $conn->prepare("SELECT id FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1");
    $stmt->execute([$cte_id]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        $conn->prepare("
            UPDATE fiscal_cte_itens SET
            tomador_cnpj = ?, tomador_nome = ?, valor_prestacao = ?, valor_receber = ?,
            comp_nome = ?, comp_valor = ?, icms_cst = ?, icms_vbc = ?, icms_picms = ?, icms_vicms = ?,
            valor_carga = ?, produto_predominante = ?, inf_complementar = ?,
            numero_protocolo = ?, data_protocolo = ?, status_protocolo = ?, motivo_protocolo = ?, versao_aplicativo = ?,
            updated_at = NOW()
            WHERE cte_id = ?
        ")->execute([
            $tomador_cnpj ?: null, $tomador_nome ?: null, $vTPrest, $vRec,
            $compNome ?: null, $compValor, $icms_cst, $vBC, $pICMS, $vICMS,
            $valor_carga, $proPred ?: null, $infCpl ?: null,
            $nProt ?: null, $dhRecbto, $cStat ?: null, $xMotivo ?: null, $verAplic ?: null,
            $cte_id
        ]);
    } else {
        $conn->prepare("
            INSERT INTO fiscal_cte_itens (cte_id, tomador_cnpj, tomador_nome, valor_prestacao, valor_receber,
            comp_nome, comp_valor, icms_cst, icms_vbc, icms_picms, icms_vicms,
            valor_carga, produto_predominante, inf_complementar,
            numero_protocolo, data_protocolo, status_protocolo, motivo_protocolo, versao_aplicativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $cte_id, $tomador_cnpj ?: null, $tomador_nome ?: null, $vTPrest, $vRec,
            $compNome ?: null, $compValor, $icms_cst, $vBC, $pICMS, $vICMS,
            $valor_carga, $proPred ?: null, $infCpl ?: null,
            $nProt ?: null, $dhRecbto, $cStat ?: null, $xMotivo ?: null, $verAplic ?: null
        ]);
    }

    return ['success' => true, 'cte_id' => $cte_id, 'message' => 'XML do CT-e importado. Dados e itens atualizados.'];
}
