<?php

/**
 * Monta XML de NF-e 55 (sem assinatura) para emissão via NFePHP Make.
 * Requer dados completos de emitente, destinatário e pelo menos um item.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use NFePHP\Common\Keys;
use NFePHP\Common\UFList;
use NFePHP\NFe\Make;

class NFeEmissaoBuilder
{
    /**
     * @param array $cfg Dados da empresa + município emitente (cMun, xMun, UF, ender...)
     * @param array $pedido natOp, dest, itens[], serie, nNF, crt, idDest, modFrete, tPag, csosn...
     * @return array{xml:string,chave:string,errors:array,vNF:float}
     */
    public static function montarXml(array $cfg, array $pedido): array
    {
        $errors = [];

        $cnpjEmit = preg_replace('/\D/', '', (string)($cfg['cnpj'] ?? ''));
        if (strlen($cnpjEmit) !== 14) {
            $errors[] = 'CNPJ do emitente inválido na configuração fiscal.';
        }

        $siglaUF = strtoupper((string)($cfg['sigla_uf'] ?? $cfg['siglaUF'] ?? ''));
        if ($siglaUF === '') {
            $errors[] = 'UF do emitente não informada.';
        }

        $ieEmit = (string)($cfg['inscricao_estadual'] ?? $cfg['IE'] ?? '');
        if ($ieEmit === '') {
            $errors[] = 'Inscrição estadual do emitente é obrigatória para emissão de NF-e.';
        }

        $crt = (int)($pedido['crt'] ?? $cfg['crt'] ?? 1);
        if ($crt < 1 || $crt > 3) {
            $crt = 1;
        }

        $serie = (int)($pedido['serie'] ?? 1);
        $nNF = (int)($pedido['nNF'] ?? 0);
        if ($nNF < 1) {
            $errors[] = 'Número da nota (nNF) inválido.';
        }

        $natOp = trim((string)($pedido['natOp'] ?? $pedido['natureza_operacao'] ?? ''));
        if ($natOp === '') {
            $errors[] = 'Natureza da operação (natOp) é obrigatória.';
        }

        $dest = $pedido['dest'] ?? null;
        if (!is_array($dest)) {
            $errors[] = 'Dados do destinatário (dest) são obrigatórios.';
        }

        $itens = $pedido['itens'] ?? [];
        if (!is_array($itens) || count($itens) < 1) {
            $errors[] = 'Informe pelo menos um item na NF-e.';
        }

        if ($errors) {
            return ['xml' => '', 'chave' => '', 'errors' => $errors, 'vNF' => 0.0];
        }

        $tpAmb = (int)($cfg['tpAmb'] ?? 2);
        if (!in_array($tpAmb, [1, 2], true)) {
            $tpAmb = 2;
        }

        $cUF = (string)UFList::getCodeByUF($siglaUF);
        $dh = new DateTime('now', new DateTimeZone(self::timezonePorUf($siglaUF)));
        $ano = (int)$dh->format('y');
        $mes = (int)$dh->format('m');
        $dhEmi = $dh->format('Y-m-d\TH:i:sP');

        $tpEmis = 1;
        $codigo = Keys::random((string)$nNF);
        $chave = Keys::build($cUF, $ano, $mes, $cnpjEmit, '55', (string)$serie, (string)$nNF, (string)$tpEmis, $codigo);

        $emitEnder = self::resolverEnderEmit($cfg, $pedido);
        if (empty($emitEnder['cMun']) || empty($emitEnder['xLgr'])) {
            return [
                'xml' => '',
                'chave' => '',
                'errors' => ['Endereço do emitente incompleto. Informe emitente.enderEmit na requisição ou preencha endereço/código IBGE do município na configuração fiscal.'],
                'vNF' => 0.0,
            ];
        }

        $cMunFG = (string)$emitEnder['cMun'];

        $make = new Make('PL_010v1.20b');
        $make->setOnlyAscii(false);

        $stdInf = new stdClass();
        $stdInf->versao = '4.00';
        $stdInf->Id = 'NFe' . $chave;
        $make->taginfNFe($stdInf);

        $stdIde = new stdClass();
        $stdIde->cUF = (int)$cUF;
        $stdIde->natOp = $natOp;
        $stdIde->mod = 55;
        $stdIde->serie = $serie;
        $stdIde->nNF = $nNF;
        $stdIde->dhEmi = $dhEmi;
        $stdIde->tpNF = (int)($pedido['tpNF'] ?? 1);
        $stdIde->idDest = (int)($pedido['idDest'] ?? 1);
        $stdIde->cMunFG = $cMunFG;
        $stdIde->tpImp = 1;
        $stdIde->tpEmis = $tpEmis;
        $stdIde->tpAmb = $tpAmb;
        $stdIde->finNFe = (int)($pedido['finNFe'] ?? 1);
        $stdIde->indFinal = (int)($pedido['indFinal'] ?? 0);
        $stdIde->indPres = (int)($pedido['indPres'] ?? 1);
        $stdIde->procEmi = 0;
        $stdIde->verProc = 'SistemaFrotas 1.0';
        $make->tagide($stdIde);

        $stdEmit = new stdClass();
        $stdEmit->xNome = (string)($cfg['razao_social'] ?? '');
        $stdEmit->xFant = (string)($cfg['nome_fantasia'] ?? '');
        $stdEmit->IE = $ieEmit;
        $stdEmit->CRT = $crt;
        $stdEmit->CNPJ = $cnpjEmit;
        $make->tagEmit($stdEmit);

        $stdEe = new stdClass();
        foreach (['xLgr', 'nro', 'xCpl', 'xBairro', 'cMun', 'xMun', 'UF', 'CEP', 'cPais', 'xPais', 'fone'] as $f) {
            $stdEe->$f = $emitEnder[$f] ?? null;
        }
        $stdEe->cPais = $stdEe->cPais ?? '1058';
        $stdEe->xPais = $stdEe->xPais ?? 'Brasil';
        $make->tagenderEmit($stdEe);

        $stdDest = new stdClass();
        $cnpjDest = preg_replace('/\D/', '', (string)($dest['CNPJ'] ?? ''));
        $cpfDest = preg_replace('/\D/', '', (string)($dest['CPF'] ?? ''));
        if (strlen($cnpjDest) === 14) {
            $stdDest->CNPJ = $cnpjDest;
        } elseif (strlen($cpfDest) === 11) {
            $stdDest->CPF = $cpfDest;
        } else {
            return ['xml' => '', 'chave' => '', 'errors' => ['Informe CNPJ ou CPF válido do destinatário.'], 'vNF' => 0.0];
        }
        $stdDest->xNome = (string)($dest['xNome'] ?? '');
        $stdDest->indIEDest = (int)($dest['indIEDest'] ?? 1);
        if (!empty($dest['IE']) && strtoupper((string)$dest['IE']) !== 'ISENTO') {
            $stdDest->IE = preg_replace('/\D/', '', (string)$dest['IE']);
        }
        $stdDest->email = (string)($dest['email'] ?? '');
        $make->tagdest($stdDest);

        $ed = $dest['enderDest'] ?? [];
        if (!is_array($ed) || empty($ed['cMun'])) {
            return ['xml' => '', 'chave' => '', 'errors' => ['Endereço do destinatário (enderDest) incompleto.'], 'vNF' => 0.0];
        }
        $stdEd = new stdClass();
        foreach (['xLgr', 'nro', 'xCpl', 'xBairro', 'cMun', 'xMun', 'UF', 'CEP', 'cPais', 'xPais', 'fone'] as $f) {
            $stdEd->$f = $ed[$f] ?? null;
        }
        $stdEd->cPais = $stdEd->cPais ?? '1058';
        $stdEd->xPais = $stdEd->xPais ?? 'Brasil';
        $make->tagenderDest($stdEd);

        $vProdTotal = 0.0;
        $nItem = 0;
        $csosn = (string)($pedido['csosn'] ?? '102');
        /**
         * CRT 3 = regime normal → ICMS00 + PISAliq + COFINSAliq (como exemplo da NT).
         * CRT 1 = Simples → ICMSSN + PIS/COFINS sem incidência (CST 07).
         * CRT 2 = Simples excesso: padrão igual CRT 1; use pedido.imposto_regime_normal=true para tributar como normal (casos específicos).
         */
        $regimeNormalItens = ($crt === 3) || ($crt === 2 && !empty($pedido['imposto_regime_normal']));
        $pICMSPad = (float)($pedido['pICMS_padrao'] ?? 18);
        $pPISPad = (float)($pedido['pPIS_padrao'] ?? 1.65);
        $pCOFINSPad = (float)($pedido['pCOFINS_padrao'] ?? 7.6);

        foreach ($itens as $row) {
            $nItem++;
            $q = (float)($row['qCom'] ?? 1);
            $vUn = (float)($row['vUnCom'] ?? 0);
            $vProd = isset($row['vProd']) ? (float)$row['vProd'] : round($q * $vUn, 2);
            $vProdTotal += $vProd;

            $stdP = new stdClass();
            $stdP->item = $nItem;
            $stdP->cProd = (string)($row['cProd'] ?? (string)$nItem);
            $stdP->cEAN = (string)($row['cEAN'] ?? 'SEM GTIN');
            $stdP->xProd = (string)($row['xProd'] ?? 'Produto');
            $stdP->NCM = preg_replace('/\D/', '', (string)($row['NCM'] ?? ''));
            if (strlen($stdP->NCM) < 8) {
                return ['xml' => '', 'chave' => '', 'errors' => ["Item $nItem: NCM inválido."], 'vNF' => 0.0];
            }
            $stdP->CFOP = (string)($row['CFOP'] ?? '5102');
            $stdP->uCom = (string)($row['uCom'] ?? 'UN');
            $stdP->qCom = $q;
            $stdP->vUnCom = $vUn;
            $stdP->vProd = $vProd;
            $stdP->cEANTrib = (string)($row['cEANTrib'] ?? $stdP->cEAN);
            $stdP->uTrib = (string)($row['uTrib'] ?? $stdP->uCom);
            $stdP->qTrib = (float)($row['qTrib'] ?? $q);
            $stdP->vUnTrib = (float)($row['vUnTrib'] ?? $vUn);
            $stdP->indTot = isset($row['indTot']) ? (int)$row['indTot'] : 1;
            $make->tagprod($stdP);

            $infAd = trim((string)($row['infAdProd'] ?? ''));
            if ($infAd !== '') {
                $stdInfAd = new stdClass();
                $stdInfAd->item = $nItem;
                $stdInfAd->infAdProd = $infAd;
                $make->taginfAdProd($stdInfAd);
            }

            $stdImp = new stdClass();
            $stdImp->item = $nItem;
            $stdImp->vTotTrib = (float)($row['vTotTrib'] ?? 0);
            $make->tagimposto($stdImp);

            $itemRegimeNormal = $regimeNormalItens || !empty($row['icms_regime_normal']);
            if ($crt === 1 && $itemRegimeNormal && !empty($row['icms_regime_normal'])) {
                return [
                    'xml' => '',
                    'chave' => '',
                    'errors' => ["Item $nItem: CRT do emitente é Simples Nacional (1); não use icms_regime_normal no item sem ajustar o CRT na configuração fiscal."],
                    'vNF' => 0.0,
                ];
            }

            if ($itemRegimeNormal) {
                $vBC = isset($row['icms_vBC']) ? (float)$row['icms_vBC'] : $vProd;
                $pICMS = isset($row['icms_pICMS']) ? (float)$row['icms_pICMS'] : $pICMSPad;
                $vICMS = isset($row['icms_vICMS']) ? (float)$row['icms_vICMS'] : round($vBC * $pICMS / 100, 2);
                $stdIcms = new stdClass();
                $stdIcms->item = $nItem;
                $stdIcms->orig = (string)($row['orig'] ?? $row['icms_orig'] ?? '0');
                $stdIcms->CST = (string)($row['icms_CST'] ?? $row['CST'] ?? '00');
                $stdIcms->modBC = (string)($row['icms_modBC'] ?? $row['modBC'] ?? '3');
                $stdIcms->vBC = $vBC;
                $stdIcms->pICMS = $pICMS;
                $stdIcms->vICMS = $vICMS;
                $make->tagICMS($stdIcms);

                $vBCPis = isset($row['pis_vBC']) ? (float)$row['pis_vBC'] : $vProd;
                $pPIS = isset($row['pis_pPIS']) ? (float)$row['pis_pPIS'] : $pPISPad;
                $vPIS = isset($row['pis_vPIS']) ? (float)$row['pis_vPIS'] : round($vBCPis * $pPIS / 100, 2);
                $stdPis = new stdClass();
                $stdPis->item = $nItem;
                $stdPis->CST = (string)($row['pis_CST'] ?? '01');
                $stdPis->vBC = $vBCPis;
                $stdPis->pPIS = $pPIS;
                $stdPis->vPIS = $vPIS;
                $make->tagPIS($stdPis);

                $vBCCof = isset($row['cofins_vBC']) ? (float)$row['cofins_vBC'] : $vProd;
                $pCOF = isset($row['cofins_pCOFINS']) ? (float)$row['cofins_pCOFINS'] : $pCOFINSPad;
                $vCOF = isset($row['cofins_vCOFINS']) ? (float)$row['cofins_vCOFINS'] : round($vBCCof * $pCOF / 100, 2);
                $stdCof = new stdClass();
                $stdCof->item = $nItem;
                $stdCof->CST = (string)($row['cofins_CST'] ?? '01');
                $stdCof->vBC = $vBCCof;
                $stdCof->pCOFINS = $pCOF;
                $stdCof->vCOFINS = $vCOF;
                $make->tagCOFINS($stdCof);
            } else {
                $stdIcmsSn = new stdClass();
                $stdIcmsSn->item = $nItem;
                $stdIcmsSn->orig = (string)($row['orig'] ?? '0');
                $stdIcmsSn->CSOSN = (string)($row['csosn'] ?? $csosn);
                $make->tagICMSSN($stdIcmsSn);

                $stdPis = new stdClass();
                $stdPis->item = $nItem;
                $stdPis->CST = (string)($row['pis_CST'] ?? '07');
                $make->tagPIS($stdPis);

                $stdCof = new stdClass();
                $stdCof->item = $nItem;
                $stdCof->CST = (string)($row['cofins_CST'] ?? '07');
                $make->tagCOFINS($stdCof);
            }
        }

        $modFrete = (string)($pedido['modFrete'] ?? '9');
        $stdT = new stdClass();
        $stdT->modFrete = $modFrete;
        $make->tagtransp($stdT);

        $stdPag = new stdClass();
        $stdPag->vTroco = null;
        $make->tagpag($stdPag);

        $stdDetPag = new stdClass();
        $stdDetPag->indPag = 0;
        $stdDetPag->tPag = (string)($pedido['tPag'] ?? '01');
        $stdDetPag->vPag = round($vProdTotal, 2);
        $make->tagdetPag($stdDetPag);

        $xml = $make->getXML();
        $mkErr = $make->getErrors();
        if (!empty($mkErr)) {
            $errors = array_merge($errors, $mkErr);
        }
        if ($xml === '' || $xml === null) {
            $errors[] = 'Falha ao montar XML da NF-e.';
        }

        return [
            'xml' => (string)$xml,
            'chave' => $chave,
            'errors' => $errors,
            'vNF' => round($vProdTotal, 2),
        ];
    }

    /**
     * Monta endereço do emitente a partir da config ou de pedido.emitente.enderEmit.
     *
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $pedido
     * @return array<string,string|null>
     */
    private static function resolverEnderEmit(array $cfg, array $pedido): array
    {
        $ov = $pedido['emitente']['enderEmit'] ?? $pedido['emitente_ender'] ?? null;
        if (is_array($ov) && !empty($ov['cMun']) && !empty($ov['xLgr'])) {
            return array_map('strval', $ov);
        }

        $cMun = preg_replace('/\D/', '', (string)($cfg['codigo_municipio'] ?? $cfg['cMun'] ?? ''));
        $xMun = (string)($cfg['municipio_nome'] ?? $cfg['xMun'] ?? '');
        $uf = strtoupper((string)($cfg['sigla_uf'] ?? $cfg['siglaUF'] ?? ''));
        $cep = preg_replace('/\D/', '', (string)($cfg['cep'] ?? ''));
        $end = trim((string)($cfg['endereco'] ?? ''));

        $xLgr = 'NAO INFORMADO';
        $nro = 'S/N';
        $xBairro = 'CENTRO';
        if ($end !== '') {
            $parts = array_map('trim', explode(',', $end, 2));
            $xLgr = $parts[0] !== '' ? $parts[0] : $xLgr;
            if (isset($parts[1]) && $parts[1] !== '') {
                $nro = $parts[1];
            }
        }

        return [
            'xLgr' => $xLgr,
            'nro' => $nro,
            'xCpl' => null,
            'xBairro' => $xBairro,
            'cMun' => strlen($cMun) === 7 ? $cMun : null,
            'xMun' => $xMun,
            'UF' => $uf,
            'CEP' => strlen($cep) === 8 ? $cep : null,
            'cPais' => '1058',
            'xPais' => 'Brasil',
            'fone' => preg_replace('/\D/', '', (string)($cfg['telefone'] ?? '')) ?: null,
        ];
    }

    private static function timezonePorUf(string $uf): string
    {
        $map = [
            'RO' => 'America/Porto_Velho', 'AC' => 'America/Rio_Branco', 'AM' => 'America/Manaus',
            'RR' => 'America/Boa_Vista', 'PA' => 'America/Belem', 'AP' => 'America/Belem',
            'TO' => 'America/Araguaina', 'MA' => 'America/Fortaleza', 'PI' => 'America/Fortaleza',
            'CE' => 'America/Fortaleza', 'RN' => 'America/Fortaleza', 'PB' => 'America/Fortaleza',
            'PE' => 'America/Recife', 'AL' => 'America/Maceio', 'SE' => 'America/Maceio',
            'BA' => 'America/Bahia', 'MG' => 'America/Sao_Paulo', 'ES' => 'America/Sao_Paulo',
            'RJ' => 'America/Sao_Paulo', 'SP' => 'America/Sao_Paulo', 'PR' => 'America/Sao_Paulo',
            'SC' => 'America/Sao_Paulo', 'RS' => 'America/Sao_Paulo', 'MS' => 'America/Campo_Grande',
            'MT' => 'America/Cuiaba', 'GO' => 'America/Sao_Paulo', 'DF' => 'America/Sao_Paulo',
        ];
        return $map[$uf] ?? 'America/Sao_Paulo';
    }
}
