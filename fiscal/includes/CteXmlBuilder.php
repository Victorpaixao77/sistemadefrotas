<?php
/**
 * Monta XML CT-e layout 4.00 (modal rodoviário, rem/dest, infNFe).
 */
declare(strict_types=1);

class CteXmlBuilder
{
    /** @param array<string,mixed> $ctx */
    public static function build(array $ctx): string
    {
        $cte = $ctx['cte'];
        $cfg = $ctx['config'] ?? [];
        $empresa = $ctx['empresa'] ?? [];
        $itens = $ctx['itens'] ?? [];

        $chave = preg_replace('/\D/', '', (string) ($cte['chave_acesso'] ?? ''));
        if (strlen($chave) !== 44) {
            throw new RuntimeException('CT-e sem chave de acesso válida (44 dígitos).');
        }

        $tpAmb = (string) ($ctx['tp_amb'] ?? '2');
        $cUF = substr($chave, 0, 2);
        $cCT = substr($chave, 35, 8);
        $cDV = substr($chave, -1);

        $numero = (int) preg_replace('/\D/', '', (string) ($cte['numero_cte'] ?? '0'));
        if ($numero <= 0) {
            $numero = (int) ltrim(substr($chave, 25, 9), '0');
        }
        $serie = (int) preg_replace('/\D/', '', (string) ($cte['serie_cte'] ?? '1'));
        if ($serie <= 0) {
            $serie = 1;
        }

        $cfop = (string) ($ctx['cfop'] ?? '5353');
        $natOp = trim((string) ($cte['natureza_operacao'] ?? 'TRANSPORTE DE CARGA'));
        $dhEmi = date('Y-m-d\TH:i:sP');

        $cnpjEmit = self::digits((string) ($cfg['cnpj'] ?? ($empresa['cnpj'] ?? '')));
        if (strlen($cnpjEmit) !== 14) {
            throw new RuntimeException('CNPJ da empresa inválido para emissão CT-e.');
        }
        $ieEmit = self::digits((string) ($cfg['inscricao_estadual'] ?? ($empresa['inscricao_estadual'] ?? '')));
        if ($ieEmit === '') {
            $ieEmit = 'ISENTO';
        }
        $xNomeEmit = trim((string) ($cfg['razao_social'] ?? ($empresa['razao_social'] ?? 'Transportadora')));
        $xFantEmit = trim((string) ($cfg['nome_fantasia'] ?? ($empresa['nome_fantasia'] ?? $xNomeEmit)));

        $vTPrest = number_format((float) ($ctx['valor_frete'] ?? 0), 2, '.', '');
        $vRec = isset($itens['valor_receber']) && $itens['valor_receber'] !== null && $itens['valor_receber'] !== ''
            ? number_format((float) $itens['valor_receber'], 2, '.', '')
            : $vTPrest;
        $compNome = trim((string) ($itens['comp_nome'] ?? 'FRETE VALOR'));
        $compValor = isset($itens['comp_valor']) && $itens['comp_valor'] !== null
            ? number_format((float) $itens['comp_valor'], 2, '.', '')
            : $vTPrest;

        $icmsCst = (string) ($itens['icms_cst'] ?? '00');
        $vBC = isset($itens['icms_vbc']) ? number_format((float) $itens['icms_vbc'], 2, '.', '') : $vTPrest;
        $pICMS = isset($itens['icms_picms']) ? number_format((float) $itens['icms_picms'], 2, '.', '') : '12.00';
        $vICMS = isset($itens['icms_vicms'])
            ? number_format((float) $itens['icms_vicms'], 2, '.', '')
            : number_format((float) $vTPrest * 0.12, 2, '.', '');

        $vCarga = number_format((float) ($ctx['valor_carga'] ?? 0), 2, '.', '');
        $proPred = trim((string) ($itens['produto_predominante'] ?? 'CARGA GERAL'));
        $peso = (float) ($ctx['peso_carga'] ?? 0);

        $infCpl = trim((string) ($itens['inf_complementar'] ?? ($cte['observacoes'] ?? '')));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<CTe xmlns="http://www.portalfiscal.inf.br/cte">';
        $xml .= '<infCte versao="4.00" Id="CTe' . $chave . '">';

        $xml .= '<ide>';
        $xml .= '<cUF>' . self::esc($cUF) . '</cUF>';
        $xml .= '<cCT>' . self::esc($cCT) . '</cCT>';
        $xml .= '<CFOP>' . self::esc($cfop) . '</CFOP>';
        $xml .= '<natOp>' . self::esc($natOp) . '</natOp>';
        $xml .= '<mod>57</mod>';
        $xml .= '<serie>' . self::esc((string) $serie) . '</serie>';
        $xml .= '<nCT>' . self::esc((string) $numero) . '</nCT>';
        $xml .= '<dhEmi>' . self::esc($dhEmi) . '</dhEmi>';
        $xml .= '<tpImp>1</tpImp>';
        $xml .= '<tpEmis>1</tpEmis>';
        $xml .= '<cDV>' . self::esc($cDV) . '</cDV>';
        $xml .= '<tpAmb>' . self::esc($tpAmb) . '</tpAmb>';
        $xml .= '<tpCTe>' . self::esc((string) ($cte['tp_cte'] ?? '0')) . '</tpCTe>';
        $xml .= '<procEmi>0</procEmi>';
        $xml .= '<verProc>Frotec-CTe-1.0</verProc>';
        $xml .= '<cMunEnv>' . self::esc((string) $ctx['cod_mun_env']) . '</cMunEnv>';
        $xml .= '<xMunEnv>' . self::esc((string) $ctx['mun_env']) . '</xMunEnv>';
        $xml .= '<UFEnv>' . self::esc((string) $ctx['uf_env']) . '</UFEnv>';
        $xml .= '<modal>01</modal>';
        $xml .= '<tpServ>0</tpServ>';
        $xml .= '<cMunIni>' . self::esc((string) $ctx['cod_mun_ini']) . '</cMunIni>';
        $xml .= '<xMunIni>' . self::esc((string) $ctx['mun_ini']) . '</xMunIni>';
        $xml .= '<UFIni>' . self::esc((string) $ctx['uf_ini']) . '</UFIni>';
        $xml .= '<cMunFim>' . self::esc((string) $ctx['cod_mun_fim']) . '</cMunFim>';
        $xml .= '<xMunFim>' . self::esc((string) $ctx['mun_fim']) . '</xMunFim>';
        $xml .= '<UFFim>' . self::esc((string) $ctx['uf_fim']) . '</UFFim>';
        $xml .= '<retira>1</retira>';
        $xml .= '<indIEToma>1</indIEToma>';
        $xml .= '<toma3><toma>' . self::esc((string) ($ctx['tp_toma'] ?? '0')) . '</toma></toma3>';
        $xml .= '</ide>';

        $xml .= '<emit>';
        $xml .= '<CNPJ>' . self::esc($cnpjEmit) . '</CNPJ>';
        $xml .= '<IE>' . self::esc($ieEmit) . '</IE>';
        $xml .= '<xNome>' . self::esc($xNomeEmit) . '</xNome>';
        if ($xFantEmit !== '') {
            $xml .= '<xFant>' . self::esc($xFantEmit) . '</xFant>';
        }
        $xml .= self::enderEmit($cfg, $empresa, $ctx);
        $xml .= '</emit>';

        $xml .= self::blocoParty('rem', 'enderReme', $ctx['rem'] ?? []);
        $xml .= self::blocoParty('dest', 'enderDest', $ctx['dest'] ?? []);

        $xml .= '<vPrest>';
        $xml .= '<vTPrest>' . self::esc($vTPrest) . '</vTPrest>';
        $xml .= '<vRec>' . self::esc($vRec) . '</vRec>';
        $xml .= '<Comp><xNome>' . self::esc($compNome) . '</xNome><vComp>' . self::esc($compValor) . '</vComp></Comp>';
        $xml .= '</vPrest>';

        $xml .= '<imp><ICMS><ICMS00>';
        $xml .= '<CST>' . self::esc($icmsCst) . '</CST>';
        $xml .= '<vBC>' . self::esc($vBC) . '</vBC>';
        $xml .= '<pICMS>' . self::esc($pICMS) . '</pICMS>';
        $xml .= '<vICMS>' . self::esc($vICMS) . '</vICMS>';
        $xml .= '</ICMS00></ICMS></imp>';

        $xml .= '<infCTeNorm>';
        $xml .= '<infCarga>';
        $xml .= '<vCarga>' . self::esc($vCarga) . '</vCarga>';
        $xml .= '<proPred>' . self::esc($proPred) . '</proPred>';
        if ($peso > 0) {
            $xml .= '<infQ><cUnid>01</cUnid><tpMed>PESO BRUTO</tpMed><qCarga>' . self::esc(number_format($peso, 4, '.', '')) . '</qCarga></infQ>';
        }
        $xml .= '</infCarga>';
        $xml .= self::blocoInfDoc($ctx);
        $xml .= '<infModal versaoModal="4.00">' . self::blocoRodo($ctx) . '</infModal>';
        $xml .= '</infCTeNorm>';

        if ($infCpl !== '') {
            $xml .= '<infAdic><infCpl>' . self::esc($infCpl) . '</infCpl></infAdic>';
        }

        $xml .= '</infCte></CTe>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoInfDoc(array $ctx): string
    {
        $xml = '<infDoc>';
        foreach ($ctx['nfes'] ?? [] as $nfe) {
            $ch = preg_replace('/\D/', '', (string) ($nfe['chave_acesso'] ?? ''));
            if (strlen($ch) !== 44) {
                continue;
            }
            $xml .= '<infNFe><chave>' . self::esc($ch) . '</chave></infNFe>';
        }
        $xml .= '</infDoc>';
        return $xml;
    }

    /** @param array<string,mixed> $ctx */
    private static function blocoRodo(array $ctx): string
    {
        $rntrc = self::digits((string) ($ctx['rntrc'] ?? ''));
        $veic = $ctx['veiculo'] ?? [];
        $mot = $ctx['motorista'] ?? [];
        $cfg = $ctx['config'] ?? [];
        $empresa = $ctx['empresa'] ?? [];

        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($veic['placa'] ?? '')));
        if ($placa === '') {
            throw new RuntimeException('Placa do veículo obrigatória no modal rodoviário.');
        }

        $renavam = self::digits((string) ($veic['renavam'] ?? $veic['renavam_veiculo'] ?? ''));
        $ufVeic = strtoupper(substr(trim((string) ($veic['uf'] ?? $veic['estado'] ?? $ctx['uf_env'] ?? 'PR')), 0, 2));

        $nomeMot = trim((string) ($mot['nome'] ?? ''));
        $cpfMot = self::digits((string) ($mot['cpf'] ?? ''));
        if ($nomeMot === '' || strlen($cpfMot) !== 11) {
            throw new RuntimeException('Motorista com nome e CPF válidos é obrigatório no modal rodoviário.');
        }

        $xml = '<rodo>';
        if ($rntrc !== '') {
            $xml .= '<RNTRC>' . self::esc($rntrc) . '</RNTRC>';
        }
        $xml .= '<veicTracao>';
        $xml .= '<placa>' . self::esc($placa) . '</placa>';
        if ($renavam !== '') {
            $xml .= '<RENAVAM>' . self::esc($renavam) . '</RENAVAM>';
        }
        $xml .= '<UF>' . self::esc($ufVeic) . '</UF>';
        $xml .= '<condutor><xNome>' . self::esc($nomeMot) . '</xNome><CPF>' . self::esc($cpfMot) . '</CPF></condutor>';
        $xml .= '</veicTracao>';
        $xml .= '</rodo>';
        return $xml;
    }

    /** @param array<string,string> $party */
    private static function blocoParty(string $tag, string $enderTag, array $party): string
    {
        $doc = self::digits((string) ($party['doc'] ?? ''));
        $nome = trim((string) ($party['nome'] ?? ''));
        if ($doc === '' && $nome === '') {
            throw new RuntimeException("Dados do {$tag}ente ausentes no CT-e.");
        }
        $xml = '<' . $tag . '>';
        if (strlen($doc) === 14) {
            $xml .= '<CNPJ>' . self::esc($doc) . '</CNPJ>';
        } elseif (strlen($doc) === 11) {
            $xml .= '<CPF>' . self::esc($doc) . '</CPF>';
        }
        $ie = trim((string) ($party['ie'] ?? ''));
        if ($ie !== '') {
            $xml .= '<IE>' . self::esc($ie) . '</IE>';
        }
        $xml .= '<xNome>' . self::esc($nome !== '' ? $nome : 'NAO INFORMADO') . '</xNome>';
        $xml .= '<' . $enderTag . '>';
        $log = trim((string) ($party['logradouro'] ?? ''));
        $xml .= '<xLgr>' . self::esc($log !== '' ? $log : 'S/N') . '</xLgr>';
        $xml .= '<nro>S/N</nro>';
        $cmun = trim((string) ($party['cmun'] ?? ''));
        if ($cmun !== '') {
            $xml .= '<cMun>' . self::esc($cmun) . '</cMun>';
        }
        $xml .= '<xMun>' . self::esc(trim((string) ($party['mun'] ?? 'NAO INFORMADO'))) . '</xMun>';
        $xml .= '<UF>' . self::esc(trim((string) ($party['uf'] ?? 'PR'))) . '</UF>';
        $xml .= '</' . $enderTag . '>';
        $xml .= '</' . $tag . '>';
        return $xml;
    }

    /** @param array<string,mixed> $cfg @param array<string,mixed> $empresa @param array<string,mixed> $ctx */
    private static function enderEmit(array $cfg, array $empresa, array $ctx): string
    {
        $log = trim((string) ($cfg['endereco'] ?? $empresa['endereco'] ?? 'S/N'));
        $nro = trim((string) ($cfg['numero'] ?? $empresa['numero'] ?? 'S/N'));
        $mun = trim((string) ($ctx['mun_env'] ?? $cfg['municipio'] ?? $empresa['cidade'] ?? ''));
        $uf = strtoupper(substr(trim((string) ($ctx['uf_env'] ?? $cfg['uf'] ?? $empresa['estado'] ?? 'PR')), 0, 2));
        $cep = self::digits((string) ($cfg['cep'] ?? $empresa['cep'] ?? ''));
        $cmun = trim((string) ($ctx['cod_mun_env'] ?? $cfg['codigo_municipio'] ?? ''));

        $xml = '<enderEmit>';
        $xml .= '<xLgr>' . self::esc($log) . '</xLgr>';
        $xml .= '<nro>' . self::esc($nro !== '' ? $nro : 'S/N') . '</nro>';
        if ($cmun !== '') {
            $xml .= '<cMun>' . self::esc($cmun) . '</cMun>';
        }
        $xml .= '<xMun>' . self::esc($mun !== '' ? $mun : 'NAO INFORMADO') . '</xMun>';
        $xml .= '<UF>' . self::esc($uf) . '</UF>';
        if ($cep !== '') {
            $xml .= '<CEP>' . self::esc(str_pad(substr($cep, 0, 8), 8, '0', STR_PAD_LEFT)) . '</CEP>';
        }
        $xml .= '</enderEmit>';
        return $xml;
    }

    private static function digits(string $v): string
    {
        return preg_replace('/\D/', '', $v);
    }

    private static function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
