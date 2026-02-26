<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/CryptoManager.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

class NFeService
{
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

            // Registrar namespace padrão da NF-e
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            $ret = $xml->xpath('//nfe:retConsSitNFe')[0] ?? $xml;

            $cStat = isset($ret->cStat) ? (string)$ret->cStat : null;
            $xMotivo = isset($ret->xMotivo) ? (string)$ret->xMotivo : 'Sem motivo';

            // 100/150 = autorizado, 101/155 = cancelado
            if ($cStat != '100' && $cStat != '150' && $cStat != '101' && $cStat != '155') {
                return [
                    'success' => false,
                    'message' => "SEFAZ retornou $cStat - $xMotivo",
                    'data' => json_decode(json_encode($ret), true)
                ];
            }

            // Tenta obter protNFe/infProt
            $protNFe = $ret->protNFe ?? null;
            $infProt = $protNFe && isset($protNFe->infProt) ? $protNFe->infProt : null;

            if (!$infProt) {
                // fallback: tenta achar infProt via XPath
                $infProtNode = $xml->xpath('//nfe:protNFe/nfe:infProt')[0] ?? null;
                if ($infProtNode) {
                    $infProt = $infProtNode;
                }
            }

            if (!$infProt) {
                return [
                    'success' => false,
                    'message' => 'ProtNFe não encontrado no retorno da SEFAZ',
                    'data' => json_decode(json_encode($ret), true)
                ];
            }

            $dados = [
                'chave_acesso' => isset($infProt->chNFe) ? (string)$infProt->chNFe : $chave,
                'numero_nfe' => null, // A consulta de protocolo nem sempre traz o número da NF
                'protocolo' => isset($infProt->nProt) ? (string)$infProt->nProt : null,
                'cStat' => isset($infProt->cStat) ? (string)$infProt->cStat : $cStat,
                'xMotivo' => isset($infProt->xMotivo) ? (string)$infProt->xMotivo : $xMotivo,
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
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("NFeService::baixarXmlPorChave SEFAZ retornou: $cStat - $xMotivo");
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
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('NFeService::baixarXmlPorChave: ' . $e->getMessage());
            }
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
}

