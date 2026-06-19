<?php

/**
 * Serviço para consulta e download de CT-e na SEFAZ (igual ao fluxo da NF-e).
 * Requer o pacote nfephp-org/sped-cte instalado (composer require nfephp-org/sped-cte).
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/CryptoManager.php';
require_once __DIR__ . '/FiscalPhpExtensions.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/CteDebug.php';

use NFePHP\Common\Certificate;

class CTeService
{
    /** @var \NFePHP\CTe\Tools */
    private $tools;

    /** @var int */
    private $empresaId;

    /**
     * Verifica se o pacote sped-cte está instalado.
     */
    public static function disponivel(): bool
    {
        return class_exists('NFePHP\CTe\Tools');
    }

    public function __construct(?int $empresaId = null)
    {
        if (!self::disponivel()) {
            throw new RuntimeException(
                'Pacote nfephp-org/sped-cte não encontrado. Execute: composer require nfephp-org/sped-cte'
            );
        }

        fiscal_require_soap_for_sefaz();

        configure_session();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $resolvedEmpresa = $empresaId ?: (int)($_SESSION['empresa_id'] ?? 0);
        if ($resolvedEmpresa < 1) {
            throw new RuntimeException('Empresa não identificada na sessão.');
        }
        $this->empresaId = $resolvedEmpresa;
        $this->bootstrapTools();
    }

    /**
     * Consulta situação do CT-e por chave na SEFAZ.
     * Retorna array com: success, message, data (protocolo, cStat, etc.).
     */
    public function consultarPorChave(string $chave): array
    {
        try {
            $tpAmb = isset($this->tools->tpAmb) ? $this->tools->tpAmb : 2;
            $responseXml = $this->tools->sefazConsultaChave($chave, $tpAmb);

            if (!$responseXml) {
                return ['success' => false, 'message' => 'Resposta vazia da SEFAZ'];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($responseXml);
            if ($xml === false) {
                $err = libxml_get_last_error();
                return ['success' => false, 'message' => 'Retorno SEFAZ inválido: ' . ($err ? $err->message : 'erro ao ler XML')];
            }

            $ns = 'http://www.portalfiscal.inf.br/cte';
            $xml->registerXPathNamespace('cte', $ns);
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
            $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');

            $ret = $xml->xpath('//cte:retConsSitCTe')[0] ?? $xml->xpath('//retConsSitCTe')[0] ?? null;
            if (!$ret) {
                $ret = $xml->children($ns)->retConsSitCTe ?? $xml->retConsSitCTe ?? null;
            }
            if (!$ret) {
                $body = $xml->xpath('//soap:Body/*')[0] ?? $xml->xpath('//soapenv:Body/*')[0] ?? null;
                if ($body) {
                    $ret = $body->children($ns)->retConsSitCTe ?? $body->retConsSitCTe ?? $body;
                } else {
                    $ret = $xml;
                }
            }

            $cStat = isset($ret->cStat) ? (string)$ret->cStat : null;
            $xMotivo = isset($ret->xMotivo) ? (string)$ret->xMotivo : 'Sem motivo';

            if ($cStat != '100' && $cStat != '150' && $cStat != '101' && $cStat != '155') {
                return [
                    'success' => false,
                    'message' => "SEFAZ retornou $cStat - $xMotivo",
                    'data' => json_decode(json_encode($ret), true)
                ];
            }

            $protCTe = $ret->protCTe ?? $ret->children($ns)->protCTe ?? null;
            $infProt = $protCTe && isset($protCTe->infProt) ? $protCTe->infProt : ($protCTe ? $protCTe->children($ns)->infProt : null);
            if (!$infProt) {
                $infProtNode = $xml->xpath('//cte:protCTe/cte:infProt')[0] ?? $xml->xpath('//*[local-name()="infProt"]')[0] ?? null;
                if ($infProtNode) {
                    $infProt = $infProtNode;
                }
            }

            if (!$infProt) {
                return [
                    'success' => false,
                    'message' => 'protCTe/infProt não encontrado no retorno da SEFAZ',
                    'data' => json_decode(json_encode($ret), true)
                ];
            }

            $chCTe = (string)($infProt->chCTe ?? $infProt->children($ns)->chCTe ?? $chave);
            $dados = [
                'chave_acesso' => $chCTe,
                'protocolo' => (string)($infProt->nProt ?? $infProt->children($ns)->nProt ?? ''),
                'cStat' => (string)($infProt->cStat ?? $cStat),
                'xMotivo' => (string)($infProt->xMotivo ?? $xMotivo),
            ];

            return [
                'success' => true,
                'message' => 'Consulta SEFAZ realizada com sucesso',
                'data' => $dados,
                'raw' => json_decode(json_encode($ret), true)
            ];
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('Erro em CTeService::consultarPorChave: ' . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Erro na consulta SEFAZ: ' . $e->getMessage()];
        }
    }

    /**
     * Emite (autoriza) um CT-e modelo 57 a partir do XML cteProc (não assinado).
     * Retorna também o XML assinado para auditoria/localização do erro.
     */
    public function emitirCTe(string $cteProcXml): array
    {
        try {
            if (empty($cteProcXml)) {
                return ['success' => false, 'message' => 'XML do CT-e vazio.'];
            }

            $signed = $this->tools->signCTe($cteProcXml);
            $responseSoap = $this->tools->sefazEnviaCTe($signed);
            $parsed = $this->parseRetornoEnvio($responseSoap);

            $success = in_array((string) ($parsed['cStat'] ?? ''), ['100', '103', '104', '150'], true)
                || in_array((string) ($parsed['cStat_prot'] ?? ''), ['100', '150'], true);

            return [
                'success' => $success,
                'message' => $parsed['xMotivo'] ?? ($success ? 'CT-e enviado à SEFAZ.' : 'SEFAZ rejeitou ou retornou status inesperado.'),
                'signed_xml' => $signed,
                'response_xml' => $responseSoap,
                'protocolo' => $parsed['protocolo'] ?? null,
                'cStat' => $parsed['cStat'] ?? null,
                'cStat_prot' => $parsed['cStat_prot'] ?? null,
                'chave_acesso' => $parsed['chave'] ?? null,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Interpreta retorno do envio síncrono/assíncrono do CT-e.
     *
     * @return array{cStat:?string,cStat_prot:?string,xMotivo:?string,protocolo:?string,chave:?string}
     */
    private function parseRetornoEnvio(string $xml): array
    {
        $out = ['cStat' => null, 'cStat_prot' => null, 'xMotivo' => null, 'protocolo' => null, 'chave' => null];
        if ($xml === '') {
            return $out;
        }
        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xml);
        if ($sx === false) {
            return $out;
        }
        $get = function (string $name) use ($sx): ?string {
            try {
                $nodes = $sx->xpath('//*[local-name()="' . $name . '"]');
                if ($nodes && isset($nodes[0])) {
                    $v = trim((string) $nodes[0]);
                    return $v === '' ? null : $v;
                }
            } catch (Throwable $e) {
            }
            return null;
        };
        $out['cStat'] = $get('cStat');
        $out['xMotivo'] = $get('xMotivo');
        $out['protocolo'] = $get('nProt') ?? $get('nRec');
        $out['chave'] = $get('chCTe');
        $out['cStat_prot'] = $get('cStat');
        $protCstat = $sx->xpath('//*[local-name()="protCTe"]//*[local-name()="cStat"]');
        if ($protCstat && isset($protCstat[0])) {
            $out['cStat_prot'] = trim((string) $protCstat[0]);
        }
        $protMot = $sx->xpath('//*[local-name()="protCTe"]//*[local-name()="xMotivo"]');
        if ($protMot && isset($protMot[0]) && $out['xMotivo'] === null) {
            $out['xMotivo'] = trim((string) $protMot[0]);
        }
        $protN = $sx->xpath('//*[local-name()="protCTe"]//*[local-name()="nProt"]');
        if ($protN && isset($protN[0]) && $out['protocolo'] === null) {
            $out['protocolo'] = trim((string) $protN[0]);
        }
        return $out;
    }

    /**
     * Envia evento oficial de cancelamento para CT-e.
     *
     * @param string $chave 44 dígitos
     * @param string $justificativa Justificativa do cancelamento
     * @param string $nProt Número do protocolo de autorização do CT-e
     */
    public function enviarCancelamentoCTe(string $chave, string $justificativa, string $nProt): array
    {
        try {
            if (empty($chave) || strlen(preg_replace('/\D/', '', $chave)) !== 44) {
                return ['success' => false, 'message' => 'Chave do CT-e inválida (precisa 44 dígitos).'];
            }
            if (empty($justificativa)) {
                return ['success' => false, 'message' => 'Justificativa é obrigatória no cancelamento.'];
            }
            if (empty($nProt)) {
                return ['success' => false, 'message' => 'Protocolo de autorização (nProt) é obrigatório no cancelamento.'];
            }

            $responseSoap = $this->tools->sefazCancela($chave, $justificativa, $nProt);
            $parsed = self::parseRetornoEventoCTe($responseSoap);

            return array_merge([
                'success' => (bool) ($parsed['evento_aceito'] ?? false),
                'message' => $parsed['xMotivo_evento'] ?: ($parsed['evento_aceito'] ? 'Cancelamento homologado.' : 'SEFAZ não homologou o cancelamento.'),
                'response_xml' => $responseSoap,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Carta de Correção CT-e (110110).
     *
     * @param array<int,array<string,string>> $infCorrecao
     */
    public function enviarCartaCorrecaoCTe(string $chave, array $infCorrecao, int $nSeqEvento = 1): array
    {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44) {
                return ['success' => false, 'message' => 'Chave do CT-e inválida (44 dígitos).'];
            }
            if (empty($infCorrecao)) {
                return ['success' => false, 'message' => 'Dados da correção são obrigatórios.'];
            }

            $responseSoap = $this->tools->sefazCCe($chave, $infCorrecao, $nSeqEvento);
            $parsed = self::parseRetornoEventoCTe($responseSoap);

            return array_merge([
                'success' => (bool) ($parsed['evento_aceito'] ?? false),
                'message' => $parsed['xMotivo_evento'] ?: ($parsed['evento_aceito'] ? 'CC-e homologada.' : 'SEFAZ não homologou a CC-e.'),
                'response_xml' => $responseSoap,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Prestação de serviço em desacordo — tomador (610110). */
    public function enviarDesacordoTomador(string $chave, string $xObs, string $ufEvento = 'RS'): array
    {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44) {
                return ['success' => false, 'message' => 'Chave do CT-e inválida.'];
            }
            $xObs = trim($xObs);
            if (strlen($xObs) < 15) {
                return ['success' => false, 'message' => 'Observação mínima de 15 caracteres.'];
            }

            $responseSoap = $this->tools->sefazManifesta($chave, 610110, $xObs, 1, strtoupper(substr($ufEvento, 0, 2)));
            $parsed = self::parseRetornoEventoCTe($responseSoap);

            return array_merge([
                'success' => (bool) ($parsed['evento_aceito'] ?? false),
                'message' => $parsed['xMotivo_evento'] ?: ($parsed['evento_aceito'] ? 'Desacordo registrado.' : 'SEFAZ rejeitou o desacordo.'),
                'response_xml' => $responseSoap,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Comprovante de entrega (110180).
     *
     * @param array<int,string> $chavesNfe
     */
    public function enviarComprovanteEntrega(
        string $chave,
        string $nProt,
        string $xNome,
        string $nDoc,
        string $hash,
        ?string $latitude,
        ?string $longitude,
        int $nSeqEvento,
        string $dhEventoEntrega,
        array $chavesNfe = []
    ): array {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44 || $nProt === '') {
                return ['success' => false, 'message' => 'Chave e protocolo do CT-e são obrigatórios.'];
            }

            $responseSoap = $this->tools->sefazCE(
                $chave,
                $nProt,
                $xNome,
                $nDoc,
                $hash,
                $latitude,
                $longitude,
                $nSeqEvento,
                $dhEventoEntrega,
                $chavesNfe
            );
            $parsed = self::parseRetornoEventoCTe($responseSoap);

            return array_merge([
                'success' => (bool) ($parsed['evento_aceito'] ?? false),
                'message' => $parsed['xMotivo_evento'] ?: ($parsed['evento_aceito'] ? 'Comprovante registrado.' : 'SEFAZ rejeitou o comprovante.'),
                'response_xml' => $responseSoap,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancelarComprovanteEntrega(string $chave, string $nProtCte, string $nProtCE, int $nSeqEvento = 1): array
    {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44) {
                return ['success' => false, 'message' => 'Chave inválida.'];
            }
            $responseSoap = $this->tools->sefazCancelaCE($chave, $nProtCte, $nProtCE, $nSeqEvento);
            $parsed = self::parseRetornoEventoCTe($responseSoap);

            return array_merge([
                'success' => (bool) ($parsed['evento_aceito'] ?? false),
                'message' => $parsed['xMotivo_evento'] ?: ($parsed['evento_aceito'] ? 'Cancelamento do comprovante homologado.' : 'SEFAZ rejeitou.'),
                'response_xml' => $responseSoap,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Inutilização de numeração CT-e (modelo 57) quando suportado pelo pacote. */
    public function inutilizarNumeracao(int $nSerie, int $nIni, int $nFin, string $xJust, ?int $tpAmb = null, ?string $ano = null): array
    {
        try {
            if (!method_exists($this->tools, 'sefazInutiliza')) {
                return ['success' => false, 'message' => 'Inutilização não disponível nesta versão do sped-cte.'];
            }
            $xmlRet = $this->tools->sefazInutiliza($nSerie, $nIni, $nFin, $xJust, $tpAmb, $ano);
            $parsed = self::parseRetornoInutilizacaoCTe($xmlRet);
            return array_merge([
                'success' => (bool) ($parsed['inutil_aceita'] ?? false),
                'message' => $parsed['xMotivo'] ?: ($parsed['inutil_aceita'] ? 'Inutilização homologada.' : 'SEFAZ rejeitou a inutilização.'),
                'response_xml' => $xmlRet,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Consulta status do serviço CT-e na SEFAZ. */
    public function consultarStatusServico(?string $uf = null): array
    {
        try {
            if (!method_exists($this->tools, 'sefazStatus')) {
                return ['success' => false, 'message' => 'Consulta de status não disponível.'];
            }
            $xmlRet = $this->tools->sefazStatus($uf ?? '', null);
            libxml_use_internal_errors(true);
            $sx = @simplexml_load_string($xmlRet);
            $cStat = null;
            $xMotivo = null;
            if ($sx !== false) {
                $nodes = $sx->xpath('//*[local-name()="cStat"]');
                if ($nodes && isset($nodes[0])) {
                    $cStat = (string) $nodes[0];
                }
                $nodesM = $sx->xpath('//*[local-name()="xMotivo"]');
                if ($nodesM && isset($nodesM[0])) {
                    $xMotivo = (string) $nodesM[0];
                }
            }
            $online = in_array($cStat, ['107', '108'], true);
            return [
                'success' => true,
                'online' => $online,
                'cStat' => $cStat,
                'message' => $xMotivo ?: ($online ? 'Serviço em operação.' : 'Status: ' . ($cStat ?? '?')),
                'response_xml' => $xmlRet,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{cStat_lote:?string,xMotivo_lote:?string,cStat_evento:?string,xMotivo_evento:?string,protocolo_evento:?string,evento_aceito:bool}
     */
    public static function parseRetornoEventoCTe(?string $xmlRet): array
    {
        $out = [
            'cStat_lote' => null,
            'xMotivo_lote' => null,
            'cStat_evento' => null,
            'xMotivo_evento' => null,
            'protocolo_evento' => null,
            'evento_aceito' => false,
        ];
        if ($xmlRet === null || $xmlRet === '') {
            return $out;
        }
        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xmlRet);
        if ($sx === false) {
            return $out;
        }
        $nodes = $sx->xpath('//*[local-name()="retEnvEvento"]');
        $ret = ($nodes && isset($nodes[0])) ? $nodes[0] : $sx;
        $out['cStat_lote'] = isset($ret->cStat) ? (string) $ret->cStat : null;
        $out['xMotivo_lote'] = isset($ret->xMotivo) ? (string) $ret->xMotivo : null;
        $infEvNodes = $sx->xpath('//*[local-name()="infEvento"]');
        if ($infEvNodes && isset($infEvNodes[0])) {
            $inf = $infEvNodes[0];
            $out['cStat_evento'] = isset($inf->cStat) ? (string) $inf->cStat : null;
            $out['xMotivo_evento'] = isset($inf->xMotivo) ? (string) $inf->xMotivo : null;
            if (isset($inf->nProt)) {
                $out['protocolo_evento'] = (string) $inf->nProt;
            }
        }
        $cLote = $out['cStat_lote'];
        $cEv = $out['cStat_evento'];
        $out['evento_aceito'] = ($cLote === '128' || $cLote === '134') && ($cEv === '135' || $cEv === '136');
        if (!$out['evento_aceito'] && ($cEv === '135' || $cEv === '136')) {
            $out['evento_aceito'] = true;
        }
        return $out;
    }

    /** @return array{cStat:?string,xMotivo:?string,inutil_aceita:bool,nProt_inut:?string} */
    public static function parseRetornoInutilizacaoCTe(?string $xmlRet): array
    {
        $out = ['cStat' => null, 'xMotivo' => null, 'inutil_aceita' => false, 'nProt_inut' => null];
        if ($xmlRet === null || $xmlRet === '') {
            return $out;
        }
        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xmlRet);
        if ($sx === false) {
            return $out;
        }
        $nodes = $sx->xpath('//*[local-name()="infInut"]');
        if ($nodes && isset($nodes[0])) {
            $inf = $nodes[0];
            $out['cStat'] = isset($inf->cStat) ? (string) $inf->cStat : null;
            $out['xMotivo'] = isset($inf->xMotivo) ? (string) $inf->xMotivo : null;
            if (isset($inf->nProt)) {
                $out['nProt_inut'] = (string) $inf->nProt;
            }
        } else {
            $cNodes = $sx->xpath('//*[local-name()="cStat"]');
            if ($cNodes && isset($cNodes[0])) {
                $out['cStat'] = (string) $cNodes[0];
            }
            $mNodes = $sx->xpath('//*[local-name()="xMotivo"]');
            if ($mNodes && isset($mNodes[0])) {
                $out['xMotivo'] = (string) $mNodes[0];
            }
        }
        $out['inutil_aceita'] = ($out['cStat'] === '102');
        return $out;
    }

    /**
     * Baixa o XML completo do CT-e pela Distribuição DFe.
     * Retorna o XML (cteProc) ou null.
     */
    public function baixarXmlPorChave(string $chave): ?string
    {
        try {
            $responseXml = $this->tools->sefazDownload($chave);
            if (!$responseXml) {
                return null;
            }

            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($responseXml);
            if ($xml === false) {
                return null;
            }

            $ret = null;
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
            $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('cte', 'http://www.portalfiscal.inf.br/cte');
            foreach (['//soap:Body/*', '//soapenv:Body/*', '//cte:retDistDFeInt', '//retDistDFeInt', '//*[local-name()="retDistDFeInt"]'] as $path) {
                $nodes = @$xml->xpath($path);
                if ($nodes && count($nodes) > 0) {
                    foreach ($nodes as $node) {
                        $name = $node->getName();
                        if ($name === 'retDistDFeInt' || strpos((string)$node->asXML(), 'retDistDFeInt') !== false) {
                            $ret = $node;
                            break 2;
                        }
                        $inner = $node->xpath('.//*[local-name()="retDistDFeInt"]');
                        if ($inner && count($inner) > 0) {
                            $ret = $inner[0];
                            break 2;
                        }
                    }
                }
            }
            if (!$ret) {
                $ret = $xml->xpath('//*[local-name()="retDistDFeInt"]')[0] ?? null;
            }
            if (!$ret) {
                return null;
            }

            $cStat = (string)($ret->cStat ?? $ret->xpath('.//*[local-name()="cStat"]')[0] ?? '');
            $xMotivo = (string)($ret->xMotivo ?? $ret->xpath('.//*[local-name()="xMotivo"]')[0] ?? '');
            if ($cStat !== '138') {
                logCteDebug('CT-e Distribuição DFe (download) não retornou documento', [
                    'chave' => $chave,
                    'cStat' => $cStat,
                    'xMotivo' => $xMotivo,
                ]);
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("CTeService::baixarXmlPorChave SEFAZ retornou: $cStat - $xMotivo");
                }
                return null;
            }

            $lote = $ret->loteDistDFeInt ?? $ret->xpath('.//*[local-name()="loteDistDFeInt"]')[0] ?? null;
            if (!$lote) {
                return null;
            }
            $docZips = isset($lote->docZip) ? (is_array($lote->docZip) ? $lote->docZip : [$lote->docZip]) : [];
            if (empty($docZips)) {
                $docZips = $lote->xpath('.//*[local-name()="docZip"]') ?: [];
            }
            foreach ($docZips as $docZip) {
                $content = (string)$docZip;
                if ($content === '') continue;
                $decoded = @base64_decode($content, true);
                if ($decoded === false) continue;
                $unzip = @gzdecode($decoded);
                if ($unzip === false) $unzip = @gzinflate($decoded);
                if ($unzip === false || $unzip === '') continue;
                if (stripos($unzip, '<cteProc') !== false || stripos($unzip, '<CTe') !== false) {
                    return $unzip;
                }
            }

            return null;
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('CTeService::baixarXmlPorChave: ' . $e->getMessage());
            }
            return null;
        }
    }

    private function bootstrapTools(): void
    {
        $conn = getConnection();

        $stmt = $conn->prepare('SELECT * FROM fiscal_config_empresa WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->execute();
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            throw new RuntimeException('Configuração fiscal não encontrada para a empresa.');
        }

        $stmt = $conn->prepare('SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = :empresa_id AND ativo = 1 ORDER BY data_vencimento DESC, id DESC');
        $stmt->bindParam(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->execute();
        $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$certificados) {
            throw new RuntimeException('Certificado A1 não encontrado ou inativo.');
        }

        $crypto = new CryptoManager();
        $cert = null;
        $senhaCert = null;

        foreach ($certificados as $c) {
            $senha = $crypto->decrypt($c['senha_criptografada']);
            if ($senha === false) continue;
            $certPathCandidate = realpath(__DIR__ . '/../../uploads/certificados/' . $c['arquivo_certificado']);
            if (!$certPathCandidate || !file_exists($certPathCandidate)) continue;
            $cert = $c;
            $senhaCert = $senha;
            $certPath = $certPathCandidate;
            break;
        }

        if (!$cert || !$senhaCert) {
            throw new RuntimeException('Nenhum certificado A1 válido encontrado. Reenvie o certificado na tela de configurações.');
        }

        $siglaUF = 'PR';
        if (!empty($cfg['codigo_municipio'])) {
            try {
                $stmtUf = $conn->prepare('SELECT uf FROM cidades WHERE codigo_ibge = :ibge LIMIT 1');
                $stmtUf->execute([':ibge' => $cfg['codigo_municipio']]);
                $ufBanco = $stmtUf->fetchColumn();
                if ($ufBanco) $siglaUF = $ufBanco;
            } catch (Throwable $e) {}
        }

        $config = [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => ($cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao' ? 1 : 2,
            'razaosocial' => $cfg['razao_social'] ?? 'Empresa',
            'siglaUF' => $siglaUF,
            'cnpj' => preg_replace('/\D/', '', $cfg['cnpj'] ?? ''),
            'schemes' => 'PL_CTe_400',
            'versao' => '4.00',
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        $certificate = Certificate::readPfx(file_get_contents($certPath), $senhaCert);

        require_once __DIR__ . '/CTeToolsDistDFe.php';
        $this->tools = new CTeToolsDistDFe($configJson, $certificate);
        $this->tools->model(57);
    }
}
