<?php
/**
 * Monta XML MDF-e layout 4.00 a partir do contexto carregado (MdfeContextLoader).
 */
declare(strict_types=1);

class MdfeXmlBuilder
{
    /** @param array<string,mixed> $ctx */
    public static function build(array $ctx): string
    {
        $mdfe = $ctx['mdfe'];
        $cfg = $ctx['config'] ?? [];
        $empresa = $ctx['empresa'] ?? [];

        $chave = preg_replace('/\D/', '', (string) ($mdfe['chave_acesso'] ?? ''));
        if (strlen($chave) !== 44) {
            throw new RuntimeException('MDF-e sem chave de acesso válida (44 dígitos).');
        }

        $tpEmit = (string) ($ctx['tipo_emitente'] ?? '1');
        $tpTransp = trim((string) ($ctx['tipo_transportador'] ?? ''));
        $tpAmb = (($cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao') ? '1' : '2';
        $cUF = self::cufFromConfig($cfg, $ctx);

        $numero = (int) preg_replace('/\D/', '', (string) ($mdfe['numero_mdfe'] ?? '0'));
        if ($numero <= 0) {
            $numero = (int) ltrim(substr($chave, 25, 9), '0');
        }
        $serie = (int) preg_replace('/\D/', '', (string) ($mdfe['serie_mdfe'] ?? '1'));
        if ($serie <= 0) {
            $serie = 1;
        }
        $cMDF = substr($chave, 35, 8);
        $dhEmi = date('Y-m-d\TH:i:sP');
        $tipoViagem = trim((string) ($mdfe['tipo_viagem'] ?? '1'));
        if (!in_array($tipoViagem, ['1', '2', '3'], true)) {
            $tipoViagem = '1';
        }

        $cnpjEmit = self::digits((string) ($cfg['cnpj'] ?? ($empresa['cnpj'] ?? '')));
        if (strlen($cnpjEmit) !== 14) {
            throw new RuntimeException('CNPJ da empresa inválido para emissão MDF-e.');
        }
        $ieEmit = self::digits((string) ($cfg['inscricao_estadual'] ?? ($empresa['inscricao_estadual'] ?? '')));
        if ($ieEmit === '') {
            $ieEmit = 'ISENTO';
        }
        $xNome = trim((string) ($cfg['razao_social'] ?? ($empresa['razao_social'] ?? 'Transportadora')));
        $xFant = trim((string) ($cfg['nome_fantasia'] ?? ($empresa['nome_fantasia'] ?? $xNome)));

        $ender = self::enderEmit($cfg, $empresa, $ctx);
        $rodo = self::blocoRodoviario($ctx, $cfg);
        $infDoc = self::blocoInfDoc($ctx);
        $tot = self::blocoTot($ctx);
        $prodPred = self::blocoProdPred($ctx);
        $seg = self::blocoSeg($ctx);
        $lacres = self::blocoLacres($ctx);
        $autXML = self::blocoAutXML($ctx);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<MDFe xmlns="http://www.portalfiscal.inf.br/mdfe">';
        $xml .= '<infMDFe versao="4.00" Id="MDFe' . $chave . '">';
        $xml .= '<ide>';
        $xml .= '<cUF>' . self::esc($cUF) . '</cUF>';
        $xml .= '<tpAmb>' . self::esc($tpAmb) . '</tpAmb>';
        $xml .= '<tpEmit>' . self::esc($tpEmit) . '</tpEmit>';
        if ($tpTransp !== '' && in_array($tpTransp, ['1', '2', '3'], true)) {
            $xml .= '<tpTransp>' . self::esc($tpTransp) . '</tpTransp>';
        }
        $xml .= '<mod>58</mod>';
        $xml .= '<serie>' . self::esc((string) $serie) . '</serie>';
        $xml .= '<nMDF>' . self::esc((string) $numero) . '</nMDF>';
        $xml .= '<cMDF>' . self::esc($cMDF) . '</cMDF>';
        $xml .= '<cDV>' . self::esc(substr($chave, -1)) . '</cDV>';
        $xml .= '<modal>1</modal>';
        $xml .= '<dhEmi>' . self::esc($dhEmi) . '</dhEmi>';
        $xml .= '<tpEmis>1</tpEmis>';
        $xml .= '<procEmi>0</procEmi>';
        $xml .= '<verProc>Frotec-MDFe-1.0</verProc>';
        $xml .= '<UFIni>' . self::esc((string) $ctx['uf_inicio']) . '</UFIni>';
        $xml .= '<UFFim>' . self::esc((string) $ctx['uf_fim']) . '</UFFim>';
        $xml .= '<infMunCarrega><cMunCarrega>' . self::esc((string) $ctx['cod_mun_carreg_ibge']) . '</cMunCarrega>';
        $xml .= '<xMunCarrega>' . self::esc((string) $ctx['municipio_carregamento']) . '</xMunCarrega></infMunCarrega>';
        foreach ($ctx['ufs_percurso'] ?? [] as $ufPer) {
            $ufPer = strtoupper(trim((string) $ufPer));
            if (strlen($ufPer) === 2) {
                $xml .= '<infPercurso><UFPer>' . self::esc($ufPer) . '</UFPer></infPercurso>';
            }
        }
        if (empty($ctx['ufs_percurso'])) {
            $xml .= '<infPercurso><UFPer>' . self::esc((string) $ctx['uf_inicio']) . '</UFPer></infPercurso>';
        }
        if ($tipoViagem !== '') {
            $xml .= '<tpViagem>' . self::esc($tipoViagem) . '</tpViagem>';
        }
        $xml .= '</ide>';

        $xml .= '<emit>';
        $xml .= '<CNPJ>' . self::esc($cnpjEmit) . '</CNPJ>';
        $xml .= '<IE>' . self::esc($ieEmit) . '</IE>';
        $xml .= '<xNome>' . self::esc($xNome) . '</xNome>';
        if ($xFant !== '') {
            $xml .= '<xFant>' . self::esc($xFant) . '</xFant>';
        }
        $xml .= $ender;
        $xml .= '</emit>';

        if ($prodPred !== '') {
            $xml .= $prodPred;
        }

        $xml .= '<infModal versaoModal="4.00">' . $rodo . '</infModal>';
        $xml .= $infDoc;
        $xml .= $seg;
        $xml .= $tot;
        if ($lacres !== '') {
            $xml .= $lacres;
        }
        if ($autXML !== '') {
            $xml .= $autXML;
        }

        $infAd = trim((string) ($mdfe['observacoes'] ?? ''));
        if ($infAd !== '') {
            $xml .= '<infAdic><infCpl>' . self::esc($infAd) . '</infCpl></infAdic>';
        }

        $xml .= '</infMDFe></MDFe>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoRodoviario(array $ctx, array $cfg): string
    {
        $mdfe = $ctx['mdfe'];
        $veiculo = $ctx['veiculo'] ?? [];
        $motorista = $ctx['motorista'] ?? [];

        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($mdfe['rod_placa'] ?? ($veiculo['placa'] ?? ''))));
        if ($placa === '') {
            throw new RuntimeException('Placa do veículo obrigatória no MDF-e.');
        }
        $rntrc = self::digits((string) ($mdfe['rod_rntrc'] ?? ($cfg['rntrc'] ?? '')));
        $renavam = self::digits((string) ($veiculo['renavam'] ?? ''));
        $tara = max(1, (int) round((float) ($veiculo['tara'] ?? $veiculo['peso_bruto'] ?? 1)));
        $capKg = max(1, (int) round((float) ($veiculo['capacidade_carga'] ?? 1)));
        $tpRod = str_pad(substr((string) ($mdfe['rod_tipo_rodado'] ?? '01'), 0, 2), 2, '0', STR_PAD_LEFT);
        $tpCar = str_pad(substr((string) ($mdfe['rod_tipo_carroceria'] ?? '00'), 0, 2), 2, '0', STR_PAD_LEFT);
        $ufVeic = strtoupper((string) ($mdfe['rod_uf_licenciamento'] ?? $veiculo['estado'] ?? $ctx['uf_inicio'] ?? 'PR'));

        $xml = '<rodo>';
        if ($rntrc !== '') {
            $xml .= '<infANTT><RNTRC>' . self::esc(strlen($rntrc) === 8 ? '0' . $rntrc : $rntrc) . '</RNTRC>';
            foreach ($ctx['ciots'] ?? [] as $ciot) {
                $num = preg_replace('/\D/', '', (string) ($ciot['numero_ciot'] ?? ''));
                if ($num === '') {
                    continue;
                }
                $xml .= '<infCIOT><CIOT>' . self::esc($num) . '</CIOT>';
                $tac = self::digits((string) ($ciot['cpf_cnpj_tac'] ?? ''));
                if ($tac !== '') {
                    if (strlen($tac) === 11) {
                        $xml .= '<CPF>' . self::esc($tac) . '</CPF>';
                    } elseif (strlen($tac) === 14) {
                        $xml .= '<CNPJ>' . self::esc($tac) . '</CNPJ>';
                    }
                }
                $xml .= '</infCIOT>';
            }
            $xml .= '</infANTT>';
        }

        $xml .= '<veicTracao>';
        $xml .= '<cInt>' . self::esc((string) ($mdfe['veiculo_id'] ?? '1')) . '</cInt>';
        $xml .= '<placa>' . self::esc($placa) . '</placa>';
        if ($renavam !== '') {
            $xml .= '<RENAVAM>' . self::esc($renavam) . '</RENAVAM>';
        }
        $xml .= '<tara>' . self::esc((string) $tara) . '</tara>';
        $xml .= '<capKG>' . self::esc((string) $capKg) . '</capKG>';
        $xml .= '<tpRod>' . self::esc($tpRod) . '</tpRod>';
        $xml .= '<tpCar>' . self::esc($tpCar) . '</tpCar>';
        $xml .= '<UF>' . self::esc($ufVeic) . '</UF>';
        $xml .= '</veicTracao>';

        $condutoresXml = [];
        foreach ($ctx['condutores'] ?? [] as $cond) {
            $nome = trim((string) ($cond['nome'] ?? ''));
            $cpf = self::digits((string) ($cond['cpf'] ?? ''));
            if ($nome !== '' && strlen($cpf) === 11) {
                $condutoresXml[$cpf] = ['nome' => $nome, 'cpf' => $cpf];
            }
        }
        $nomeCond = trim((string) ($motorista['nome'] ?? ''));
        $cpfCond = self::digits((string) ($motorista['cpf'] ?? ''));
        if ($nomeCond !== '' && strlen($cpfCond) === 11) {
            $condutoresXml[$cpfCond] = ['nome' => $nomeCond, 'cpf' => $cpfCond];
        }
        foreach ($condutoresXml as $c) {
            $xml .= '<condutor><xNome>' . self::esc($c['nome']) . '</xNome><CPF>' . self::esc($c['cpf']) . '</CPF></condutor>';
        }

        foreach ($ctx['reboques'] ?? [] as $reb) {
            $placaReb = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($reb['placa'] ?? '')));
            if ($placaReb === '') {
                continue;
            }
            $xml .= '<veicReboque>';
            $cInt = trim((string) ($reb['cod_interno'] ?? ''));
            if ($cInt !== '') {
                $xml .= '<cInt>' . self::esc($cInt) . '</cInt>';
            }
            $xml .= '<placa>' . self::esc($placaReb) . '</placa>';
            $renReb = self::digits((string) ($reb['renavam'] ?? ''));
            if ($renReb !== '') {
                $xml .= '<RENAVAM>' . self::esc($renReb) . '</RENAVAM>';
            }
            $taraReb = max(1, (int) ($reb['tara'] ?? 1));
            $capReb = max(1, (int) ($reb['cap_kg'] ?? 1));
            $xml .= '<tara>' . self::esc((string) $taraReb) . '</tara>';
            $xml .= '<capKG>' . self::esc((string) $capReb) . '</capKG>';
            $xml .= '<tpRod>' . self::esc(str_pad(substr((string) ($reb['tp_rod'] ?? '01'), 0, 2), 2, '0', STR_PAD_LEFT)) . '</tpRod>';
            $xml .= '<tpCar>' . self::esc(str_pad(substr((string) ($reb['tp_car'] ?? '00'), 0, 2), 2, '0', STR_PAD_LEFT)) . '</tpCar>';
            $ufReb = strtoupper(substr((string) ($reb['uf'] ?? $ufVeic), 0, 2));
            $xml .= '<UF>' . self::esc($ufReb) . '</UF>';
            $xml .= '</veicReboque>';
        }

        foreach ($ctx['vales_pedagio'] ?? [] as $vale) {
            $cnpjFor = self::digits((string) ($vale['cnpj_fornecedor'] ?? ''));
            $valor = number_format((float) ($vale['valor'] ?? 0), 2, '.', '');
            if ($cnpjFor === '' || (float) $valor <= 0) {
                continue;
            }
            $xml .= '<valePed>';
            $xml .= '<CNPJForn>' . self::esc($cnpjFor) . '</CNPJForn>';
            $nCom = trim((string) ($vale['numero_comprovante'] ?? ''));
            if ($nCom !== '') {
                $xml .= '<nCompra>' . self::esc($nCom) . '</nCompra>';
            }
            $resp = self::digits((string) ($vale['responsavel_pagamento'] ?? ''));
            if (strlen($resp) === 14) {
                $xml .= '<CNPJPg>' . self::esc($resp) . '</CNPJPg>';
            } elseif (strlen($resp) === 11) {
                $xml .= '<CPFPg>' . self::esc($resp) . '</CPFPg>';
            }
            $xml .= '<vValePed>' . self::esc($valor) . '</vValePed>';
            $tipo = trim((string) ($vale['tipo'] ?? ''));
            if ($tipo !== '') {
                $xml .= '<tpValePed>' . self::esc(str_pad($tipo, 2, '0', STR_PAD_LEFT)) . '</tpValePed>';
            }
            $xml .= '</valePed>';
        }

        foreach ($ctx['contratantes'] ?? [] as $contr) {
            $doc = self::digits((string) ($contr['documento'] ?? ''));
            if ($doc === '') {
                continue;
            }
            $xml .= '<infContratante>';
            if (strlen($doc) === 11) {
                $xml .= '<CPF>' . self::esc($doc) . '</CPF>';
            } else {
                $xml .= '<CNPJ>' . self::esc($doc) . '</CNPJ>';
            }
            $xml .= '</infContratante>';
        }

        foreach ($ctx['pagamentos'] ?? [] as $pag) {
            $xml .= self::blocoInfPag($pag);
        }

        $xml .= '</rodo>';
        return $xml;
    }

    /** @param array<string,mixed> $pag */
    private static function blocoInfPag(array $pag): string
    {
        $doc = self::digits((string) ($pag['documento'] ?? ''));
        if ($doc === '') {
            return '';
        }
        $vContrato = number_format((float) ($pag['valor_total_contrato'] ?? 0), 2, '.', '');
        $xml = '<infPag>';
        if (strlen($doc) === 11) {
            $xml .= '<CPF>' . self::esc($doc) . '</CPF>';
        } else {
            $xml .= '<CNPJ>' . self::esc($doc) . '</CNPJ>';
        }
        $xml .= '<vContrato>' . self::esc($vContrato) . '</vContrato>';
        $indPag = trim((string) ($pag['indicador_forma_pagamento'] ?? '0'));
        if ($indPag !== '') {
            $xml .= '<indPag>' . self::esc($indPag) . '</indPag>';
        }
        foreach ($pag['componentes'] ?? [] as $comp) {
            $cod = trim((string) ($comp['codigo'] ?? ''));
            $val = number_format((float) ($comp['valor'] ?? 0), 2, '.', '');
            if ($cod === '') {
                continue;
            }
            $xml .= '<Comp><tpComp>' . self::esc($cod) . '</tpComp><vComp>' . self::esc($val) . '</vComp></Comp>';
        }
        $xml .= '</infPag>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoInfDoc(array $ctx): string
    {
        $grupos = [];
        foreach ($ctx['documentos'] ?? [] as $doc) {
            $munNome = trim((string) ($doc['municipio_descarga'] ?? $ctx['municipio_descarregamento'] ?? ''));
            $cMun = trim((string) ($doc['cod_mun_descarga'] ?? $ctx['cod_mun_desc_ibge'] ?? ''));
            if ($cMun === '' || $cMun === '0000000') {
                continue;
            }
            $key = $cMun;
            if (!isset($grupos[$key])) {
                $grupos[$key] = ['cMun' => $cMun, 'xMun' => $munNome !== '' ? $munNome : 'MUNICIPIO', 'ctes' => [], 'nfes' => []];
            }
            $chCte = self::digits((string) ($doc['chave_cte'] ?? ''));
            $chNfe = self::digits((string) ($doc['chave_nfe'] ?? ''));
            if (strlen($chCte) === 44) {
                $grupos[$key]['ctes'][$chCte] = $chCte;
            }
            if (strlen($chNfe) === 44) {
                $grupos[$key]['nfes'][$chNfe] = $chNfe;
            }
        }

        if (!$grupos) {
            $cMun = (string) ($ctx['cod_mun_desc_ibge'] ?? '0000000');
            $grupos[$cMun] = [
                'cMun' => $cMun,
                'xMun' => (string) ($ctx['municipio_descarregamento'] ?? 'MUNICIPIO'),
                'ctes' => [],
                'nfes' => [],
            ];
            foreach ($ctx['ctes'] ?? [] as $c) {
                $grupos[$cMun]['ctes'][$c['chave']] = $c['chave'];
            }
            foreach ($ctx['nfes'] ?? [] as $n) {
                $grupos[$cMun]['nfes'][$n['chave']] = $n['chave'];
            }
        }

        $xml = '<infDoc>';
        foreach ($grupos as $g) {
            $xml .= '<infMunDescarga cMunDescarga="' . self::esc($g['cMun']) . '" xMunDescarga="' . self::esc($g['xMun']) . '">';
            foreach ($g['ctes'] as $ch) {
                $xml .= '<infCTe><chCTe>' . self::esc($ch) . '</chCTe></infCTe>';
            }
            foreach ($g['nfes'] as $ch) {
                $xml .= '<infNFe><chNFe>' . self::esc($ch) . '</chNFe></infNFe>';
            }
            $xml .= '</infMunDescarga>';
        }
        $xml .= '</infDoc>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoTot(array $ctx): string
    {
        $mdfe = $ctx['mdfe'];
        $qCte = count($ctx['ctes'] ?? []);
        $qNfe = count($ctx['nfes'] ?? []);
        $chavesCte = [];
        $chavesNfe = [];
        foreach ($ctx['ctes'] ?? [] as $c) {
            $chavesCte[$c['chave']] = true;
        }
        foreach ($ctx['nfes'] ?? [] as $n) {
            $chavesNfe[$n['chave']] = true;
        }
        foreach ($ctx['documentos'] ?? [] as $d) {
            $chCte = self::digits((string) ($d['chave_cte'] ?? ''));
            $chNfe = self::digits((string) ($d['chave_nfe'] ?? ''));
            if (strlen($chCte) === 44) {
                $chavesCte[$chCte] = true;
            }
            if (strlen($chNfe) === 44) {
                $chavesNfe[$chNfe] = true;
            }
        }
        $qCte = count($chavesCte);
        $qNfe = count($chavesNfe);
        $vCarga = number_format((float) ($mdfe['valor_total_carga'] ?? 0), 2, '.', '');
        $qCarga = number_format((float) ($mdfe['peso_total_carga'] ?? 0), 4, '.', '');
        if ((float) $qCarga <= 0) {
            $qCarga = '0.0001';
        }
        $xml = '<tot>';
        if ($qCte > 0) {
            $xml .= '<qCTe>' . self::esc((string) $qCte) . '</qCTe>';
        }
        if ($qNfe > 0) {
            $xml .= '<qNFe>' . self::esc((string) $qNfe) . '</qNFe>';
        }
        $xml .= '<vCarga>' . self::esc($vCarga) . '</vCarga>';
        $xml .= '<cUnid>01</cUnid>';
        $xml .= '<qCarga>' . self::esc($qCarga) . '</qCarga>';
        $xml .= '</tot>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoProdPred(array $ctx): string
    {
        $produtos = $ctx['produtos'] ?? [];
        if (!$produtos) {
            return '';
        }
        $p = $produtos[0];
        $tpCarga = trim((string) ($p['tipo_carga'] ?? ''));
        $xProd = trim((string) ($p['descricao_produto'] ?? ''));
        $ncm = self::digits((string) ($p['ncm'] ?? ''));
        if ($tpCarga === '' && $xProd === '') {
            return '';
        }
        $xml = '<prodPred>';
        if ($tpCarga !== '') {
            $xml .= '<tpCarga>' . self::esc($tpCarga) . '</tpCarga>';
        }
        if ($xProd !== '') {
            $xml .= '<xProd>' . self::esc($xProd) . '</xProd>';
        }
        if (strlen($ncm) >= 2) {
            $xml .= '<NCM>' . self::esc(substr($ncm, 0, 8)) . '</NCM>';
        }
        $xml .= '</prodPred>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoSeg(array $ctx): string
    {
        $seguros = $ctx['seguros'] ?? [];
        if (!$seguros) {
            return '';
        }
        $xml = '';
        foreach ($seguros as $s) {
            $resp = trim((string) ($s['responsavel'] ?? ''));
            $cnpjSeg = self::digits((string) ($s['cnpj_seguradora'] ?? ''));
            if ($resp === '' && $cnpjSeg === '') {
                continue;
            }
            $xml .= '<seg>';
            $xml .= '<infResp><respSeg>' . self::esc($resp !== '' ? $resp : '1') . '</respSeg></infResp>';
            if ($cnpjSeg !== '') {
                $xml .= '<infSeg><CNPJ>' . self::esc($cnpjSeg) . '</CNPJ>';
                $nome = trim((string) ($s['nome_seguradora'] ?? ''));
                if ($nome !== '') {
                    $xml .= '<xSeg>' . self::esc($nome) . '</xSeg>';
                }
                $xml .= '</infSeg>';
            }
            $nApol = trim((string) ($s['numero_apolice'] ?? ''));
            if ($nApol !== '') {
                $xml .= '<nApol>' . self::esc($nApol) . '</nApol>';
            }
            $xml .= '</seg>';
        }
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoLacres(array $ctx): string
    {
        $lacres = $ctx['lacres'] ?? [];
        if (!$lacres) {
            return '';
        }
        $xml = '<lacres>';
        foreach ($lacres as $l) {
            $n = trim((string) ($l['numero_lacre'] ?? $l));
            if ($n === '') {
                continue;
            }
            $xml .= '<nLacre>' . self::esc($n) . '</nLacre>';
        }
        $xml .= '</lacres>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoAutXML(array $ctx): string
    {
        $aut = $ctx['autorizados'] ?? [];
        if (!$aut) {
            return '';
        }
        $xml = '';
        foreach ($aut as $a) {
            $doc = self::digits((string) ($a['documento'] ?? ''));
            if ($doc === '') {
                continue;
            }
            $xml .= '<autXML>';
            if (strlen($doc) === 11) {
                $xml .= '<CPF>' . self::esc($doc) . '</CPF>';
            } else {
                $xml .= '<CNPJ>' . self::esc($doc) . '</CNPJ>';
            }
            $xml .= '</autXML>';
        }
        return $xml;
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $ctx */
    private static function enderEmit(array $cfg, array $empresa, array $ctx): string
    {
        $ender = trim((string) ($cfg['endereco'] ?? ($empresa['endereco'] ?? '')));
        $cep = self::digits((string) ($cfg['cep'] ?? ($empresa['cep'] ?? '')));
        $fone = self::digits((string) ($cfg['telefone'] ?? ($empresa['telefone'] ?? '')));
        $email = trim((string) ($cfg['email'] ?? ($empresa['email'] ?? '')));
        [$xLgr, $nro, $xBairro] = self::splitEndereco($ender);
        $xMun = trim((string) ($empresa['cidade'] ?? $ctx['municipio_carregamento'] ?? ''));
        $uf = strtoupper((string) ($empresa['estado'] ?? $ctx['uf_inicio'] ?? 'PR'));
        $cMun = trim((string) ($ctx['cod_mun_carreg_ibge'] ?? ''));

        $xml = '<enderEmit>';
        $xml .= '<xLgr>' . self::esc($xLgr) . '</xLgr>';
        $xml .= '<nro>' . self::esc($nro) . '</nro>';
        $xml .= '<xBairro>' . self::esc($xBairro) . '</xBairro>';
        $xml .= '<cMun>' . self::esc($cMun !== '' ? $cMun : '0000000') . '</cMun>';
        $xml .= '<xMun>' . self::esc($xMun !== '' ? $xMun : 'NAO INFORMADO') . '</xMun>';
        $xml .= '<CEP>' . self::esc($cep !== '' ? $cep : '00000000') . '</CEP>';
        $xml .= '<UF>' . self::esc($uf) . '</UF>';
        if ($fone !== '') {
            $xml .= '<fone>' . self::esc($fone) . '</fone>';
        }
        if ($email !== '') {
            $xml .= '<email>' . self::esc($email) . '</email>';
        }
        $xml .= '</enderEmit>';
        return $xml;
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $ctx */
    private static function cufFromConfig(array $cfg, array $ctx): string
    {
        $mapa = [
            'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15', 'AP' => '16', 'TO' => '17',
            'MA' => '21', 'PI' => '22', 'CE' => '23', 'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27',
            'SE' => '28', 'BA' => '29', 'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35', 'PR' => '41',
            'SC' => '42', 'RS' => '43', 'MS' => '50', 'MT' => '51', 'GO' => '52', 'DF' => '53',
        ];
        $uf = strtoupper((string) ($ctx['uf_inicio'] ?? 'PR'));
        $cod = trim((string) ($cfg['codigo_municipio'] ?? ''));
        if (strlen($cod) >= 2) {
            $cuf = substr($cod, 0, 2);
            if (isset($mapa[array_search($cuf, $mapa, true) ?: '']) || strlen($cuf) === 2) {
                return $cuf;
            }
        }
        return $mapa[$uf] ?? '41';
    }

    /** @return array{0:string,1:string,2:string} */
    private static function splitEndereco(string $endereco): array
    {
        $e = trim($endereco);
        if ($e === '') {
            return ['NAO INFORMADO', 'S/N', 'CENTRO'];
        }
        $partes = array_map('trim', explode(',', $e));
        return [
            $partes[0] !== '' ? $partes[0] : 'NAO INFORMADO',
            $partes[1] ?? 'S/N',
            $partes[2] ?? 'CENTRO',
        ];
    }

    private static function digits(string $v): string
    {
        return preg_replace('/\D/', '', $v);
    }

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
