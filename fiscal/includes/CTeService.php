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

        $this->empresaId = $empresaId ?: ($_SESSION['empresa_id'] ?? 1);
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

            // signCTe assina e adiciona QR quando aplicável (dependendo do modelo/tpCTe/modal).
            $signed = $this->tools->signCTe($cteProcXml);
            $responseSoap = $this->tools->sefazEnviaCTe($signed);

            return [
                'success' => true,
                'signed_xml' => $signed,
                'response_xml' => $responseSoap,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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

            return [
                'success' => true,
                'response_xml' => $responseSoap,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
