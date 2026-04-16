<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/CryptoManager.php';
require_once __DIR__ . '/FiscalPhpExtensions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

class NFeService
{
    /** Tipos de manifestação do destinatário (tpEvento oficial) */
    public const MANIFEST_CIENCIA = 210210;
    public const MANIFEST_CONFIRMACAO = 210200;
    public const MANIFEST_DESCONHECIMENTO = 210220;
    public const MANIFEST_NAO_REALIZADA = 210240;

    /** @var Tools */
    private $tools;

    /** @var int */
    private $empresaId;

    public function __construct(?int $empresaId = null)
    {
        configure_session();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->empresaId = $empresaId ?: ($_SESSION['empresa_id'] ?? 1);
        $this->bootstrapTools();
    }

    /**
     * Consulta situação da NF-e por chave na SEFAZ.
     * Retorna array com:
     *  - success (bool)
     *  - message (string)
     *  - data (array) em caso de sucesso
     */
    public function consultarPorChave(string $chave): array
    {
        try {
            $responseXml = $this->tools->sefazConsultaChave($chave);

            if (!$responseXml) {
                return ['success' => false, 'message' => 'Resposta vazia da SEFAZ'];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($responseXml);
            if ($xml === false) {
                $err = libxml_get_last_error();
                return ['success' => false, 'message' => 'Retorno SEFAZ inválido: ' . ($err ? $err->message : 'erro ao ler XML')];
            }

            // Registrar namespace padrão da NF-e (SOAP pode envolver o retorno)
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            $ret = $xml->xpath('//nfe:retConsSitNFe')[0] ?? null;
            if (!$ret) {
                $local = $xml->xpath('//*[local-name()="retConsSitNFe"]');
                $ret = $local[0] ?? null;
            }
            if (!$ret) {
                return ['success' => false, 'message' => 'retConsSitNFe não encontrado no XML da SEFAZ'];
            }

            $cStatRet = isset($ret->cStat) ? (string)$ret->cStat : '';
            $xMotivoRet = isset($ret->xMotivo) ? (string)$ret->xMotivo : 'Sem motivo';

            // No retConsSitNFe o cStat do *serviço* costuma ser 138 ("Documento localizado"), não 100.
            // 100/101/150/155 referem-se ao protocolo em infProt, não ao envelope da consulta.
            $cStatRetNum = $cStatRet !== '' ? (int)$cStatRet : 0;
            if ($cStatRetNum >= 200) {
                return [
                    'success' => false,
                    'message' => "SEFAZ retornou $cStatRet - $xMotivoRet",
                    'data' => json_decode(json_encode($ret), true),
                ];
            }

            // Tenta obter protNFe/infProt
            $protNFe = $ret->protNFe ?? null;
            $infProt = $protNFe && isset($protNFe->infProt) ? $protNFe->infProt : null;

            if (!$infProt) {
                $infProtNode = $xml->xpath('//nfe:protNFe/nfe:infProt')[0] ?? null;
                if ($infProtNode) {
                    $infProt = $infProtNode;
                }
            }
            if (!$infProt) {
                $infLocal = $xml->xpath('//*[local-name()="protNFe"]/*[local-name()="infProt"]');
                $infProt = $infLocal[0] ?? null;
            }

            if (!$infProt) {
                return [
                    'success' => false,
                    'message' => 'ProtNFe não encontrado no retorno da SEFAZ (cStat da consulta: ' . $cStatRet . ' - ' . $xMotivoRet . ')',
                    'data' => json_decode(json_encode($ret), true),
                ];
            }

            $cStatDoc = isset($infProt->cStat) ? (string)$infProt->cStat : '';
            $xMotivoDoc = isset($infProt->xMotivo) ? (string)$infProt->xMotivo : $xMotivoRet;
            // Situação do documento: autorizado / cancelado / denegado etc.
            if (!in_array($cStatDoc, ['100', '150', '101', '155'], true)) {
                return [
                    'success' => false,
                    'message' => "SEFAZ (documento): $cStatDoc - $xMotivoDoc",
                    'data' => json_decode(json_encode($infProt), true),
                ];
            }

            $dados = [
                'chave_acesso' => isset($infProt->chNFe) ? (string)$infProt->chNFe : $chave,
                'numero_nfe' => null,
                'protocolo' => isset($infProt->nProt) ? (string)$infProt->nProt : null,
                'cStat' => $cStatDoc,
                'xMotivo' => $xMotivoDoc,
            ];

            return [
                'success' => true,
                'message' => 'Consulta SEFAZ realizada com sucesso',
                'data' => $dados,
                'raw' => json_decode(json_encode($ret), true)
            ];
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('Erro em NFeService::consultarPorChave: ' . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Erro na consulta SEFAZ: ' . $e->getMessage()];
        }
    }

    /**
     * Baixa o XML completo da NF-e pela Distribuição DFe (consChNFe).
     * Só funciona para o destinatário da NF-e (certificado deve ser do CNPJ destinatário).
     * Retorna o XML (nfeProc) ou null se não conseguir.
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

            // Resposta pode vir em envelope SOAP; extrair retDistDFeInt
            $ret = null;
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
            $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            foreach (['//soap:Body/*', '//soapenv:Body/*', '//nfe:retDistDFeInt', '//retDistDFeInt'] as $path) {
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
                $ret = $xml->xpath('//nfe:retDistDFeInt')[0] ?? $xml->xpath('//retDistDFeInt')[0] ?? null;
            }
            if (!$ret) {
                return null;
            }

            $cStat = (string)($ret->cStat ?? $ret->xpath('.//*[local-name()="cStat"]')[0] ?? '');
            $xMotivo = (string)($ret->xMotivo ?? $ret->xpath('.//*[local-name()="xMotivo"]')[0] ?? '');
            // 138 = documento localizado com conteúdo no docZip
            if ($cStat !== '138') {
                error_log("NFeService::baixarXmlPorChave chave={$chave} cStat={$cStat} xMotivo={$xMotivo}");
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
                if ($content === '') {
                    continue;
                }
                $decoded = @base64_decode($content, true);
                if ($decoded === false) {
                    continue;
                }
                $unzip = @gzdecode($decoded);
                if ($unzip === false) {
                    $unzip = @gzinflate($decoded);
                }
                if ($unzip === false || $unzip === '') {
                    continue;
                }
                if (stripos($unzip, '<nfeProc') !== false || stripos($unzip, '<NFe') !== false) {
                    return $unzip;
                }
            }

            return null;
        } catch (Throwable $e) {
            error_log('NFeService::baixarXmlPorChave: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lista chaves de NF-e disponíveis para o CNPJ (destinatário) via Distribuição DFe.
     * Retorna apenas modelo 55. Use ultNSU = 0 na primeira vez ou o último NSU gravado.
     *
     * @param int $ultNSU Último NSU já conhecido (0 para buscar desde o início)
     * @return array { success, message, cStat, ultNSU, chaves[], raw }
     */
    public function listarChavesPorDistribuicao(int $ultNSU = 0): array
    {
        try {
            $responseXml = $this->tools->sefazDistDFe($ultNSU, 0);
            if (!$responseXml) {
                return ['success' => false, 'message' => 'Resposta vazia da SEFAZ', 'chaves' => [], 'ultNSU' => (string)$ultNSU];
            }

            $xml = @simplexml_load_string($responseXml);
            if ($xml === false) {
                return ['success' => false, 'message' => 'Resposta SEFAZ inválida', 'chaves' => [], 'ultNSU' => (string)$ultNSU];
            }

            $ret = null;
            $xml->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');
            $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            foreach (['//soap:Body/*', '//soapenv:Body/*', '//nfe:retDistDFeInt', '//retDistDFeInt', '//*[local-name()="retDistDFeInt"]'] as $path) {
                $nodes = @$xml->xpath($path);
                if ($nodes && count($nodes) > 0) {
                    foreach ($nodes as $node) {
                        $name = $node->getName();
                        if ($name === 'retDistDFeInt' || strpos((string)$node->asXML(), 'retDistDFeInt') !== false) {
                            $ret = $node;
                            break 2;
                        }
                        $inner = @$node->xpath('.//*[local-name()="retDistDFeInt"]');
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
                return ['success' => false, 'message' => 'retDistDFeInt não encontrado na resposta SEFAZ', 'chaves' => [], 'ultNSU' => (string)$ultNSU];
            }

            $cStat = (string)($ret->cStat ?? $ret->xpath('.//*[local-name()="cStat"]')[0] ?? '');
            $xMotivo = (string)($ret->xMotivo ?? $ret->xpath('.//*[local-name()="xMotivo"]')[0] ?? '');
            $ultNSUResp = (string)($ret->ultNSU ?? $ret->xpath('.//*[local-name()="ultNSU"]')[0] ?? $ultNSU);

            // 139 = nenhum documento; 137 = documentos no lote; 138 = documento único
            if ($cStat === '139') {
                return [
                    'success' => true,
                    'message' => 'Nenhum documento novo',
                    'cStat' => $cStat,
                    'xMotivo' => $xMotivo,
                    'ultNSU' => $ultNSUResp,
                    'chaves' => [],
                    'numDocZip' => 0,
                    'numResNFe' => 0,
                ];
            }

            $chaves = [];
            $numDocZip = 0;
            $numResNFe = 0;
            $lote = $ret->loteDistDFeInt ?? $ret->xpath('.//*[local-name()="loteDistDFeInt"]')[0] ?? null;
            if ($lote) {
                $docZips = isset($lote->docZip) ? (is_array($lote->docZip) ? $lote->docZip : [$lote->docZip]) : [];
                if (empty($docZips)) {
                    $docZips = $lote->xpath('.//*[local-name()="docZip"]') ?: [];
                }
                $numDocZip = count($docZips);
                foreach ($docZips as $docZip) {
                    $schema = (string)($docZip['schema'] ?? '');
                    $content = (string)$docZip;
                    if ($content === '') continue;
                    $decoded = @base64_decode($content, true);
                    if ($decoded === false) continue;
                    $unzip = @gzdecode($decoded);
                    if ($unzip === false) $unzip = @gzinflate($decoded);
                    if ($unzip === false || $unzip === '') continue;
                    // resumo = resNFe com chNFe (pode ter namespace)
                    if (stripos($schema, 'resNFe') !== false) {
                        $numResNFe++;
                        $res = @simplexml_load_string($unzip);
                        if ($res) {
                            $ch = (string)($res->chNFe ?? $res->children('http://www.portalfiscal.inf.br/nfe')->chNFe ?? '');
                            $ch = trim($ch);
                            if (strlen($ch) === 44 && substr($ch, 20, 2) === '55') {
                                $chaves[] = $ch;
                            }
                        }
                    }
                }
            }

            $chaves = array_unique($chaves);
            return [
                'success' => true,
                'message' => $xMotivo ?: 'Consulta realizada',
                'cStat' => $cStat,
                'xMotivo' => $xMotivo,
                'ultNSU' => $ultNSUResp,
                'chaves' => array_values($chaves),
                'numDocZip' => $numDocZip,
                'numResNFe' => $numResNFe,
            ];
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('NFeService::listarChavesPorDistribuicao: ' . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'Erro na Distribuição DFe: ' . $e->getMessage(),
                'chaves' => [],
                'ultNSU' => (string)$ultNSU,
            ];
        }
    }

    private function bootstrapTools(): void
    {
        fiscal_require_soap_for_sefaz();

        $conn = getConnection();

        // Buscar config fiscal
        $stmt = $conn->prepare('SELECT * FROM fiscal_config_empresa WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->execute();
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            throw new RuntimeException('Configuração fiscal não encontrada para a empresa.');
        }

        // Buscar certificados A1 ativos (pode haver mais de um; usar o primeiro válido)
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
            if ($senha === false) {
                // Registro antigo (ex: password_hash) ou dado corrompido; tenta próximo
                continue;
            }
            $certPathCandidate = realpath(__DIR__ . '/../../uploads/certificados/' . $c['arquivo_certificado']);
            if (!$certPathCandidate || !file_exists($certPathCandidate)) {
                // Arquivo ausente; tenta próximo
                continue;
            }
            $cert = $c;
            $senhaCert = $senha;
            $certPath = $certPathCandidate;
            break;
        }

        if (!$cert || !$senhaCert) {
            throw new RuntimeException('Nenhum certificado A1 válido encontrado. Reenvie o certificado na tela de configurações.');
        }

        // Descobrir UF pelo código do município, se possível
        $siglaUF = 'RO';
        if (!empty($cfg['codigo_municipio'])) {
            try {
                $stmtUf = $conn->prepare('SELECT uf FROM cidades WHERE codigo_ibge = :ibge LIMIT 1');
                $stmtUf->execute([':ibge' => $cfg['codigo_municipio']]);
                $ufBanco = $stmtUf->fetchColumn();
                if ($ufBanco) {
                    $siglaUF = $ufBanco;
                }
            } catch (Throwable $e) {
                // fallback RO
            }
        }

        $config = [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => ($cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao' ? 1 : 2,
            'razaosocial' => $cfg['razao_social'] ?? 'Empresa',
            'siglaUF' => $siglaUF,
            'cnpj' => preg_replace('/\D/', '', $cfg['cnpj'] ?? ''),
            'schemes' => 'PL_010v1.20b',
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => '',
            'CSCid' => '',
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);

        $certificate = Certificate::readPfx(file_get_contents($certPath), $senhaCert);

        $this->tools = new Tools($configJson, $certificate);
        $this->tools->model('55');
    }

    /**
     * Cancelamento de NF-e (emitente). Exige certificado do CNPJ emitente da nota.
     */
    public function enviarCancelamentoNFe(string $chave, string $xJust, string $nProt): array
    {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44) {
                return ['success' => false, 'message' => 'Chave da NF-e inválida (44 dígitos).'];
            }
            $xJust = trim($xJust);
            if ($xJust === '') {
                return ['success' => false, 'message' => 'Justificativa é obrigatória no cancelamento.'];
            }
            if ($nProt === '' || $nProt === '0') {
                return ['success' => false, 'message' => 'Protocolo de autorização (nProt) é obrigatório no cancelamento.'];
            }

            $xmlRet = $this->tools->sefazCancela($chave, $xJust, $nProt);
            $parsed = self::parseRetornoEventoNFe($xmlRet);
            $msg = $parsed['evento_aceito']
                ? ($parsed['xMotivo_evento'] ?: 'Cancelamento homologado pela SEFAZ.')
                : ($parsed['xMotivo_evento'] ?: $parsed['xMotivo_lote'] ?: 'SEFAZ não homologou o cancelamento.');

            return array_merge([
                'success' => (bool)($parsed['evento_aceito'] ?? false),
                'message' => $msg,
                'response_xml' => $xmlRet,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'response_xml' => null];
        }
    }

    /**
     * Carta de Correção (emitente).
     */
    public function enviarCartaCorrecaoNFe(string $chave, string $xCorrecao, int $nSeqEvento = 1): array
    {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44) {
                return ['success' => false, 'message' => 'Chave da NF-e inválida (44 dígitos).'];
            }
            if (trim($xCorrecao) === '') {
                return ['success' => false, 'message' => 'Texto da correção é obrigatório.'];
            }

            $xmlRet = $this->tools->sefazCCe($chave, $xCorrecao, $nSeqEvento);
            $parsed = self::parseRetornoEventoNFe($xmlRet);
            $msg = $parsed['evento_aceito']
                ? ($parsed['xMotivo_evento'] ?: 'CC-e homologada pela SEFAZ.')
                : ($parsed['xMotivo_evento'] ?: $parsed['xMotivo_lote'] ?: 'SEFAZ não homologou a CC-e.');

            return array_merge([
                'success' => (bool)($parsed['evento_aceito'] ?? false),
                'message' => $msg,
                'response_xml' => $xmlRet,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'response_xml' => null];
        }
    }

    /**
     * Inutilização de numeração (emitente).
     */
    public function inutilizarNumeracao(
        int $nSerie,
        int $nIni,
        int $nFin,
        string $xJust,
        ?int $tpAmb = null,
        ?string $ano = null
    ): array {
        try {
            if ($nIni < 1 || $nFin < 1 || trim($xJust) === '') {
                return ['success' => false, 'message' => 'Série, faixa de numeração e justificativa são obrigatórios.'];
            }

            $xmlRet = $this->tools->sefazInutiliza($nSerie, $nIni, $nFin, $xJust, $tpAmb, $ano);
            $parsed = self::parseRetornoInutilizacao($xmlRet);
            $msg = $parsed['inutil_aceita']
                ? ($parsed['xMotivo'] ?: 'Inutilização homologada pela SEFAZ.')
                : ($parsed['xMotivo'] ?: 'SEFAZ não homologou a inutilização.');

            return array_merge([
                'success' => (bool)($parsed['inutil_aceita'] ?? false),
                'message' => $msg,
                'response_xml' => $xmlRet,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'response_xml' => null];
        }
    }

    /**
     * Manifestação do destinatário (certificado do destinatário da NF-e).
     *
     * @param int $tpEvento Uma das constantes MANIFEST_* ou Tools::EVT_*
     */
    public function manifestarDestinatario(
        string $chave,
        int $tpEvento,
        string $xJust = '',
        int $nSeqEvento = 1
    ): array {
        try {
            $chave = preg_replace('/\D/', '', $chave);
            if (strlen($chave) !== 44) {
                return ['success' => false, 'message' => 'Chave da NF-e inválida (44 dígitos).'];
            }
            if ($tpEvento === self::MANIFEST_NAO_REALIZADA && trim($xJust) === '') {
                return ['success' => false, 'message' => 'Justificativa é obrigatória para operação não realizada.'];
            }

            $xmlRet = $this->tools->sefazManifesta($chave, $tpEvento, $xJust, $nSeqEvento);
            $parsed = self::parseRetornoEventoNFe($xmlRet);
            $msg = $parsed['evento_aceito']
                ? ($parsed['xMotivo_evento'] ?: 'Manifestação registrada pela SEFAZ.')
                : ($parsed['xMotivo_evento'] ?: $parsed['xMotivo_lote'] ?: 'SEFAZ não homologou a manifestação.');

            return array_merge([
                'success' => (bool)($parsed['evento_aceito'] ?? false),
                'message' => $msg,
                'response_xml' => $xmlRet,
            ], $parsed);
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'response_xml' => null];
        }
    }

    /**
     * Interpreta retorno de evento (retEnvEvento / envEvento).
     *
     * @return array{cStat_lote:?string,xMotivo_lote:?string,cStat_evento:?string,xMotivo_evento:?string,protocolo_evento:?string,evento_aceito:bool}
     */
    public static function parseRetornoEventoNFe(?string $xmlRet): array
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

        $out['cStat_lote'] = isset($ret->cStat) ? (string)$ret->cStat : null;
        $out['xMotivo_lote'] = isset($ret->xMotivo) ? (string)$ret->xMotivo : null;

        $infEvNodes = $sx->xpath('//*[local-name()="infEvento"]');
        if ($infEvNodes && isset($infEvNodes[0])) {
            $inf = $infEvNodes[0];
            $out['cStat_evento'] = isset($inf->cStat) ? (string)$inf->cStat : null;
            $out['xMotivo_evento'] = isset($inf->xMotivo) ? (string)$inf->xMotivo : null;
            if (isset($inf->nProt)) {
                $out['protocolo_evento'] = (string)$inf->nProt;
            }
        }

        $cLote = $out['cStat_lote'];
        $cEv = $out['cStat_evento'];
        // 128 = lote processado; 135/136 = evento vinculado / registrado
        $out['evento_aceito'] = ($cLote === '128' || $cLote === '134')
            && ($cEv === '135' || $cEv === '136');
        // Fallback: alguns retornos trazem só infEvento homologado
        if (!$out['evento_aceito'] && ($cEv === '135' || $cEv === '136')) {
            $out['evento_aceito'] = true;
        }

        return $out;
    }

    /**
     * @return array{cStat:?string,xMotivo:?string,inutil_aceita:bool,nProt_inut:?string}
     */
    public static function parseRetornoInutilizacao(?string $xmlRet): array
    {
        $out = [
            'cStat' => null,
            'xMotivo' => null,
            'inutil_aceita' => false,
            'nProt_inut' => null,
        ];
        if ($xmlRet === null || $xmlRet === '') {
            return $out;
        }

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xmlRet);
        if ($sx === false) {
            return $out;
        }

        $nodes = $sx->xpath('//*[local-name()="retInutNFe"]');
        $ret = ($nodes && isset($nodes[0])) ? $nodes[0] : $sx;

        $infNodes = $sx->xpath('//*[local-name()="infInut"]');
        $inf = ($infNodes && isset($infNodes[0])) ? $infNodes[0] : null;

        $out['cStat'] = isset($ret->cStat) ? (string)$ret->cStat : null;
        $out['xMotivo'] = isset($ret->xMotivo) ? (string)$ret->xMotivo : null;
        if ($inf && isset($inf->cStat)) {
            $out['cStat'] = (string)$inf->cStat;
        }
        if ($inf && isset($inf->xMotivo)) {
            $out['xMotivo'] = (string)$inf->xMotivo;
        }
        if ($inf && isset($inf->nProt)) {
            $out['nProt_inut'] = (string)$inf->nProt;
        }

        // 102 = inutilização homologada
        $cs = $out['cStat'];
        $out['inutil_aceita'] = ($cs === '102');

        return $out;
    }

    /**
     * Assina o XML da NF-e e envia em lote síncrono (1 nota, indSinc=1).
     * Retorna XML assinado e resultado do parse do retorno SEFAZ.
     */
    public function emitirNFeAutorizacao(string $xmlNfe): array
    {
        try {
            $signed = $this->tools->signNFe($xmlNfe);
            $idLote = str_pad((string) random_int(1, 999999999999999), 15, '0', STR_PAD_LEFT);
            $xmls = [];
            $resp = $this->tools->sefazEnviaLote([$signed], $idLote, 1, false, $xmls);

            return self::parseRetornoEnvioNfe($resp, $signed);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response_xml' => null,
                'xml_signed' => null,
                'chave' => null,
                'protocolo' => null,
            ];
        }
    }

    /**
     * Interpreta retorno do envio (retEnviNFe / protNFe).
     *
     * @return array{
     *   success:bool,
     *   message:string,
     *   cStat_lote:?string,
     *   cStat_prot:?string,
     *   chave:?string,
     *   protocolo:?string,
     *   xMotivo:?string,
     *   response_xml:?string,
     *   xml_signed:?string
     * }
     */
    public static function parseRetornoEnvioNfe(?string $responseSoap, ?string $xmlSigned = null): array
    {
        $out = [
            'success' => false,
            'message' => 'Resposta SEFAZ vazia ou inválida.',
            'cStat_lote' => null,
            'cStat_prot' => null,
            'chave' => null,
            'protocolo' => null,
            'xMotivo' => null,
            'response_xml' => $responseSoap,
            'xml_signed' => $xmlSigned,
        ];

        if ($responseSoap === null || $responseSoap === '') {
            return $out;
        }

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($responseSoap);
        if ($sx === false) {
            return $out;
        }

        $retNodes = $sx->xpath('//*[local-name()="retEnviNFe"]');
        $ret = ($retNodes && isset($retNodes[0])) ? $retNodes[0] : null;
        if (!$ret) {
            $out['message'] = 'retEnviNFe não encontrado na resposta.';
            return $out;
        }

        $out['cStat_lote'] = isset($ret->cStat) ? (string)$ret->cStat : null;
        $out['xMotivo'] = isset($ret->xMotivo) ? (string)$ret->xMotivo : null;

        $protNodes = $sx->xpath('//*[local-name()="protNFe"]//*[local-name()="infProt"]');
        if ($protNodes && isset($protNodes[0])) {
            $inf = $protNodes[0];
            $out['cStat_prot'] = isset($inf->cStat) ? (string)$inf->cStat : null;
            $out['chave'] = isset($inf->chNFe) ? (string)$inf->chNFe : null;
            $out['protocolo'] = isset($inf->nProt) ? (string)$inf->nProt : null;
            if (isset($inf->xMotivo)) {
                $out['xMotivo'] = (string)$inf->xMotivo;
            }
        }

        $cLote = $out['cStat_lote'];
        $cProt = $out['cStat_prot'];

        // 104 = Lote processado (síncrono); 100 = Autorizado o uso da NF-e
        if ($cLote === '104' && $cProt === '100') {
            $out['success'] = true;
            $out['message'] = $out['xMotivo'] ?: 'NF-e autorizada.';
            return $out;
        }

        // Lote recebido, processamento assíncrono (consultar recibo)
        if ($cLote === '103') {
            $out['message'] = 'Lote recebido (processamento assíncrono). Consulte o recibo na SEFAZ. ' . ($out['xMotivo'] ?? '');
            return $out;
        }

        $out['success'] = false;
        $out['message'] = $out['xMotivo'] ?: ('SEFAZ retornou cStat lote=' . ($cLote ?? '-') . ' prot=' . ($cProt ?? '-'));

        return $out;
    }
}

