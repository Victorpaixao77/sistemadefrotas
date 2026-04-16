<?php
/**
 * MDF-e service:
 * - Consulta de status/eventos na SEFAZ (SOAP manual)
 * - Emissao/autorizacao via sped-mdfe (quando instalado)
 *
 * Observação:
 * - A consulta de eventos usa SOAP manual para manter compatibilidade.
 * - A emissao depende do pacote nfephp-org/sped-mdfe.
 * - Para bloquear/corrigir regras (cancelamento/encerramento), o que importa para nós
 *   é encontrar tpEvento(s) no retorno (ex: 110112 encerramento, 110111 cancelamento).
 */

require_once __DIR__ . '/CryptoManager.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use NFePHP\Common\Certificate;

class MdfeService
{
    private int $empresaId;
    private array $cfg;
    private array $cert;
    private string $certPath;
    private string $certPass;

    public function __construct(int $empresaId)
    {
        $this->empresaId = $empresaId;

        $conn = getConnection();

        $stmt = $conn->prepare('SELECT * FROM fiscal_config_empresa WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmt->execute();
        $this->cfg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (empty($this->cfg)) {
            throw new RuntimeException('Configuração fiscal não encontrada para a empresa.');
        }

        $stmtCert = $conn->prepare('
            SELECT * FROM fiscal_certificados_digitais
            WHERE empresa_id = :empresa_id AND ativo = 1
            ORDER BY data_vencimento DESC, id DESC
            LIMIT 1
        ');
        $stmtCert->bindParam(':empresa_id', $this->empresaId, PDO::PARAM_INT);
        $stmtCert->execute();
        $this->cert = $stmtCert->fetch(PDO::FETCH_ASSOC) ?: [];
        if (empty($this->cert)) {
            throw new RuntimeException('Nenhum certificado A1 ativo encontrado.');
        }

        $crypto = new CryptoManager();
        $senhaCert = $crypto->decrypt($this->cert['senha_criptografada'] ?? '');
        if ($senhaCert === false || $senhaCert === '') {
            throw new RuntimeException('Senha do certificado inválida/indisponível.');
        }

        $certPathCandidate = realpath(__DIR__ . '/../../uploads/certificados/' . ($this->cert['arquivo_certificado'] ?? ''));
        if (!$certPathCandidate || !file_exists($certPathCandidate)) {
            throw new RuntimeException('Arquivo do certificado não encontrado: ' . (string)($this->cert['arquivo_certificado'] ?? ''));
        }

        $this->certPath = $certPathCandidate;
        $this->certPass = (string)$senhaCert;
    }

    /**
     * Consulta SEFAZ do MDF-e e retorna tpEvento(s) encontrados.
     */
    public function consultarEventosPorChave(string $chaveMDFe): array
    {
        $chaveMDFe = preg_replace('/\D/', '', $chaveMDFe);
        if (empty($chaveMDFe) || strlen($chaveMDFe) < 44) {
            throw new InvalidArgumentException('Chave MDFe inválida.');
        }

        $tpAmb = (($this->cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao') ? 1 : 2;

        // SEFAZ RS (mesmo padrão que seu validador usa para serviços)
        $wsUrl = $tpAmb === 1
            ? 'https://mdfe.svrs.rs.gov.br/ws/MDFeConsulta/MDFeConsulta.asmx'
            : 'https://mdfe-homologacao.svrs.rs.gov.br/ws/MDFeConsulta/MDFeConsulta.asmx';

        $cUF = $this->obterCUF();

        // Payload conforme exemplo "consSitMDFe" (Flexdocs / MDFe_Util)
        $bodyXml = '<consSitMDFe xmlns="http://www.portalfiscal.inf.br/mdfe" versao="1.00">'
            . '<tpAmb>' . $tpAmb . '</tpAmb>'
            . '<xServ>CONSULTAR</xServ>'
            . '<chMDFe>' . $chaveMDFe . '</chMDFe>'
            . '</consSitMDFe>';

        // Envelope SOAP mínimo (Header pode variar por UF; tentamos o padrão usado em SEFAZ DFe)
        $soapEnvelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mdfe="http://www.portalfiscal.inf.br/mdfe">'
            . '<soapenv:Header>'
            . '<mdfe:mdfeCabecMsg>'
            . '<mdfe:versaoDados>1.00</mdfe:versaoDados>'
            . '<mdfe:tpAmb>' . $tpAmb . '</mdfe:tpAmb>'
            . '<mdfe:cUF>' . $cUF . '</mdfe:cUF>'
            . '</mdfe:mdfeCabecMsg>'
            . '</soapenv:Header>'
            . '<soapenv:Body>'
            . $bodyXml
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $responseXml = $this->executarSoapConsulta($wsUrl, $soapEnvelope);

        $sx = @simplexml_load_string($responseXml);
        if ($sx === false) {
            throw new RuntimeException('Resposta SEFAZ inválida (não é XML).');
        }

        $tpEventos = [];
        try {
            $nodes = $sx->xpath('//*[local-name()="tpEvento"]');
            if ($nodes) {
                foreach ($nodes as $n) {
                    $v = trim((string)$n);
                    if ($v !== '') {
                        $tpEventos[] = $v;
                    }
                }
            }
        } catch (Throwable $e) {
            // segue sem tpEvento
        }

        $cStat = '';
        try {
            $nodesCStat = $sx->xpath('//*[local-name()="cStat"]');
            if ($nodesCStat && isset($nodesCStat[0])) {
                $cStat = (string)$nodesCStat[0];
            }
        } catch (Throwable $e) {}

        return [
            'success' => true,
            'cStat' => $cStat,
            'tpEventos' => array_values(array_unique($tpEventos)),
            'raw_xml' => $responseXml,
        ];
    }

    /**
     * Verifica se a integracao de autorizacao MDF-e esta disponivel.
     */
    public static function autorizacaoDisponivel(): bool
    {
        $viaLib = class_exists('NFePHP\\MDFe\\Tools') && class_exists('NFePHP\\Common\\Certificate');
        $viaManual = function_exists('openssl_pkcs12_read') && function_exists('curl_init');
        return $viaLib || $viaManual;
    }

    /**
     * Emite (autoriza) MDF-e na SEFAZ com base no registro fiscal_mdfe.
     * Retorna formato padrao:
     * - sucesso (bool)
     * - status (autorizado|pendente)
     * - protocolo (string|null)
     * - chave_acesso (string|null)
     * - erro (string|null)
     * - xml_assinado/xml_retorno (string|null)
     */
    public function emitir(array $mdfe): array
    {
        if (!self::autorizacaoDisponivel()) {
            return [
                'sucesso' => false,
                'erro' => 'Emissao MDF-e indisponivel: instale nfephp-org/sped-mdfe ou habilite OpenSSL/cURL para modo manual.',
            ];
        }

        try {
            $xmlBase = $this->montarXmlMdfe($mdfe);
            $xmlAssinado = '';
            $xmlRetorno = '';
            $metodoEnvio = 'manual';
            $mdfeId = (int) ($mdfe['id'] ?? 0);
            $chaveFallback = $this->apenasDigitos((string) ($mdfe['chave_acesso'] ?? ''));

            if (class_exists('NFePHP\\MDFe\\Tools') && class_exists('NFePHP\\Common\\Certificate')) {
                $tools = $this->buildMdfeTools();
                $xmlAssinado = (string) $this->callFirstAvailableMethod($tools, ['signMDFe', 'signMdfe'], [$xmlBase]);
                if (!empty($xmlAssinado)) {
                    $idLote = str_pad((string) random_int(1, 999999999999999), 15, '0', STR_PAD_LEFT);
                    try {
                        $xmlRetorno = (string) $this->callFirstAvailableMethod(
                            $tools,
                            ['sefazEnviaLote', 'sefazEnviaMDFe'],
                            [[(string) $xmlAssinado], $idLote, 1]
                        );
                        $metodoEnvio = 'nfephp_tools';
                    } catch (Throwable $e) {
                        if (!$this->isTimeoutError($e->getMessage())) {
                            throw $e;
                        }
                    }
                }
            }

            // Fallback manual (DOM + OpenSSL + cURL) quando lib nao responder.
            if ($xmlAssinado === '') {
                $xmlAssinado = $this->assinarXmlManualA1($xmlBase);
            }
            $xmlHash = hash('sha256', $xmlAssinado);
            $this->ensureMdfeEnvioSchema();

            $envioExistente = $this->buscarEnvioPorHash($xmlHash);
            if ($envioExistente && in_array((string) ($envioExistente['status_envio'] ?? ''), ['autorizado', 'pendente', 'em_processamento'], true)) {
                return $this->buildResultFromStoredEnvio($envioExistente, $xmlAssinado);
            }
            $this->registrarEnvio($mdfeId, $xmlHash, 'em_processamento', null, null, $metodoEnvio, null);

            if ($xmlRetorno === '') {
                try {
                    $xmlRetorno = $this->enviarXmlAssinadoManual($xmlAssinado);
                    $metodoEnvio = 'manual_fallback';
                } catch (Throwable $e) {
                    if ($this->isTimeoutError($e->getMessage())) {
                        $resolved = $this->resolverTimeoutPorConsulta($chaveFallback);
                        $this->registrarEnvio($mdfeId, $xmlHash, $resolved['status'] ?? 'pendente', $resolved['protocolo'] ?? null, null, 'timeout_consulta', $e->getMessage());
                        return [
                            'sucesso' => !empty($resolved['sucesso']),
                            'status' => $resolved['status'] ?? 'pendente',
                            'protocolo' => $resolved['protocolo'] ?? null,
                            'chave_acesso' => $resolved['chave_acesso'] ?? ($chaveFallback ?: null),
                            'xml_assinado' => $xmlAssinado,
                            'xml_retorno' => $resolved['xml_retorno'] ?? null,
                            'metodo_envio' => 'timeout_consulta',
                            'xml_hash' => $xmlHash,
                            'erro' => $resolved['erro'] ?? null,
                        ];
                    }
                    throw $e;
                }
            }

            if (empty($xmlRetorno) || !is_string($xmlRetorno)) {
                $this->registrarEnvio($mdfeId, $xmlHash, 'erro', null, null, $metodoEnvio, 'SEFAZ nao retornou resposta.');
                return [
                    'sucesso' => false,
                    'erro' => 'SEFAZ nao retornou resposta para o envio do MDF-e.',
                    'xml_assinado' => $xmlAssinado,
                    'metodo_envio' => $metodoEnvio,
                ];
            }

            $parsed = $this->parseRetornoSefaz($xmlRetorno);
            $cStat = $parsed['cStat'] ?? '';
            $protocolo = $parsed['protocolo'] ?? null;
            $chave = $parsed['chave'] ?? ($this->apenasDigitos((string) ($mdfe['chave_acesso'] ?? '')) ?: null);

            // 100 autorizado; 101 cancelado; 132 encerrado; 103/104/105 pendencias de lote/recibo.
            if (in_array($cStat, ['100', '150'], true)) {
                $this->registrarEnvio($mdfeId, $xmlHash, 'autorizado', $protocolo, $xmlRetorno, $metodoEnvio, null);
                return [
                    'sucesso' => true,
                    'status' => 'autorizado',
                    'protocolo' => $protocolo,
                    'chave_acesso' => $chave,
                    'xml_assinado' => $xmlAssinado,
                    'xml_retorno' => $xmlRetorno,
                    'metodo_envio' => $metodoEnvio,
                    'xml_hash' => $xmlHash,
                ];
            }

            if (in_array($cStat, ['103', '104', '105'], true)) {
                $this->registrarEnvio($mdfeId, $xmlHash, 'pendente', $protocolo, $xmlRetorno, $metodoEnvio, null);
                return [
                    'sucesso' => true,
                    'status' => 'pendente',
                    'protocolo' => $protocolo,
                    'chave_acesso' => $chave,
                    'xml_assinado' => $xmlAssinado,
                    'xml_retorno' => $xmlRetorno,
                    'metodo_envio' => $metodoEnvio,
                    'xml_hash' => $xmlHash,
                ];
            }

            $erroSefaz = 'SEFAZ retornou ' . ($cStat !== '' ? $cStat : 'status desconhecido') . ' - ' . ($parsed['xMotivo'] ?? 'sem motivo');
            $this->registrarEnvio($mdfeId, $xmlHash, 'rejeitado', $protocolo, $xmlRetorno, $metodoEnvio, $erroSefaz);
            return [
                'sucesso' => false,
                'erro' => $erroSefaz,
                'protocolo' => $protocolo,
                'chave_acesso' => $chave,
                'xml_assinado' => $xmlAssinado,
                'xml_retorno' => $xmlRetorno,
                'metodo_envio' => $metodoEnvio,
                'xml_hash' => $xmlHash,
            ];
        } catch (Throwable $e) {
            return [
                'sucesso' => false,
                'erro' => 'Falha na emissao MDF-e: ' . $e->getMessage(),
            ];
        }
    }

    private function obterCUF(): string
    {
        $mapa_cUF = [
            'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15',
            'AP' => '16', 'TO' => '17', 'MA' => '21', 'PI' => '22', 'CE' => '23',
            'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27', 'SE' => '28',
            'BA' => '29', 'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35',
            'PR' => '41', 'SC' => '42', 'RS' => '43', 'MS' => '50', 'MT' => '51',
            'GO' => '52', 'DF' => '53'
        ];

        $codigoMunicipio = $this->cfg['codigo_municipio'] ?? '';
        $ufSigla = 'PR';

        if ($codigoMunicipio !== '') {
            try {
                $conn = getConnection();
                $stmtUf = $conn->prepare('SELECT uf FROM cidades WHERE codigo_ibge = :ibge LIMIT 1');
                $stmtUf->execute([':ibge' => (string)$codigoMunicipio]);
                $ufBanco = $stmtUf->fetchColumn();
                if ($ufBanco) {
                    $ufSigla = (string)$ufBanco;
                }
            } catch (Throwable $e) {
                // fallback
            }
        }

        return $mapa_cUF[$ufSigla] ?? '41';
    }

    /**
     * Monta XML MDF-e basico (modal rodoviario) para assinatura/autorizacao.
     */
    private function montarXmlMdfe(array $mdfe): string
    {
        $conn = getConnection();

        $chave = $this->apenasDigitos((string) ($mdfe['chave_acesso'] ?? ''));
        if (strlen($chave) !== 44) {
            throw new RuntimeException('MDF-e sem chave de acesso valida (44 digitos).');
        }

        $numero = preg_replace('/\D/', '', (string) ($mdfe['numero_mdfe'] ?? ''));
        $numero = $numero !== '' ? (int) $numero : (int) ltrim(substr($chave, 25, 9), '0');
        $serie = (int) preg_replace('/\D/', '', (string) ($mdfe['serie_mdfe'] ?? '1'));
        if ($serie <= 0) {
            $serie = 1;
        }
        $tpAmb = (($this->cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao') ? '1' : '2';
        $cUF = $this->obterCUF();
        $cMDF = substr($chave, 35, 8);
        if (strlen($cMDF) !== 8) {
            $cMDF = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        }

        $ufIni = strtoupper((string) ($mdfe['uf_inicio'] ?? ''));
        $ufFim = strtoupper((string) ($mdfe['uf_fim'] ?? ''));
        if ($ufIni === '' || $ufFim === '') {
            throw new RuntimeException('MDF-e sem UF inicio/fim.');
        }

        $cidadeCarreg = trim((string) ($mdfe['municipio_carregamento'] ?? ''));
        $cidadeDesc = trim((string) ($mdfe['municipio_descarregamento'] ?? ''));
        if ($cidadeCarreg === '' || $cidadeDesc === '') {
            throw new RuntimeException('MDF-e sem municipio de carregamento/descarregamento.');
        }

        $cMunCarreg = $this->buscarCodigoMunicipio($conn, $cidadeCarreg, $ufIni);
        $cMunDesc = $this->buscarCodigoMunicipio($conn, $cidadeDesc, $ufFim);

        $stmtEmp = $conn->prepare("SELECT * FROM empresa_clientes WHERE id = ? LIMIT 1");
        $stmtEmp->execute([$this->empresaId]);
        $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: [];

        $cnpjEmit = $this->apenasDigitos((string) ($this->cfg['cnpj'] ?? ($empresa['cnpj'] ?? '')));
        if (strlen($cnpjEmit) !== 14) {
            throw new RuntimeException('CNPJ da empresa invalido para emissao MDF-e.');
        }
        $ieEmit = $this->apenasDigitos((string) ($this->cfg['inscricao_estadual'] ?? ($empresa['inscricao_estadual'] ?? '')));
        if ($ieEmit === '') {
            $ieEmit = 'ISENTO';
        }
        $xNome = trim((string) ($this->cfg['razao_social'] ?? ($empresa['razao_social'] ?? 'Transportadora')));
        $xFant = trim((string) ($this->cfg['nome_fantasia'] ?? ($empresa['nome_fantasia'] ?? $xNome)));
        $rntrc = $this->apenasDigitos((string) ($this->cfg['rntrc'] ?? ''));

        $ender = trim((string) ($this->cfg['endereco'] ?? ($empresa['endereco'] ?? '')));
        $cep = $this->apenasDigitos((string) ($this->cfg['cep'] ?? ($empresa['cep'] ?? '')));
        $fone = $this->apenasDigitos((string) ($this->cfg['telefone'] ?? ($empresa['telefone'] ?? '')));
        $email = trim((string) ($this->cfg['email'] ?? ($empresa['email'] ?? '')));
        [$xLgr, $nro, $xBairro] = $this->normalizarEndereco($ender);
        $xMunEmit = trim((string) ($empresa['cidade'] ?? $cidadeCarreg));
        $ufEmit = strtoupper((string) ($empresa['estado'] ?? $ufIni));
        if ($ufEmit === '') {
            $ufEmit = $ufIni;
        }
        $cMunEmit = $this->buscarCodigoMunicipio($conn, $xMunEmit, $ufEmit);

        $stmtVeic = $conn->prepare("SELECT * FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1");
        $stmtVeic->execute([(int) ($mdfe['veiculo_id'] ?? 0), $this->empresaId]);
        $veiculo = $stmtVeic->fetch(PDO::FETCH_ASSOC) ?: [];
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($veiculo['placa'] ?? '')));
        if ($placa === '') {
            throw new RuntimeException('Veiculo do MDF-e sem placa valida.');
        }
        $renavam = $this->apenasDigitos((string) ($veiculo['renavam'] ?? ''));
        $tara = (int) round((float) ($veiculo['tara'] ?? $veiculo['peso_bruto'] ?? 0));
        $capKg = (int) round((float) ($veiculo['capacidade_carga'] ?? 0));
        if ($tara <= 0) {
            $tara = 1;
        }
        if ($capKg <= 0) {
            $capKg = 1;
        }

        $stmtMot = $conn->prepare("SELECT * FROM motoristas WHERE id = ? AND empresa_id = ? LIMIT 1");
        $stmtMot->execute([(int) ($mdfe['motorista_id'] ?? 0), $this->empresaId]);
        $motorista = $stmtMot->fetch(PDO::FETCH_ASSOC) ?: [];
        $nomeCond = trim((string) ($motorista['nome'] ?? 'CONDUTOR'));
        $cpfCond = $this->apenasDigitos((string) ($motorista['cpf'] ?? ''));
        if (strlen($cpfCond) !== 11) {
            throw new RuntimeException('Motorista do MDF-e sem CPF valido.');
        }

        $stmtCte = $conn->prepare("
            SELECT c.chave_acesso
            FROM fiscal_mdfe_cte mc
            INNER JOIN fiscal_cte c ON c.id = mc.cte_id
            WHERE mc.mdfe_id = ?
            ORDER BY c.id ASC
        ");
        $stmtCte->execute([(int) ($mdfe['id'] ?? 0)]);
        $chavesCte = [];
        foreach ($stmtCte->fetchAll(PDO::FETCH_COLUMN) as $chCte) {
            $dig = $this->apenasDigitos((string) $chCte);
            if (strlen($dig) === 44) {
                $chavesCte[] = $dig;
            }
        }
        if (empty($chavesCte)) {
            throw new RuntimeException('MDF-e sem CT-e vinculados com chave de acesso valida.');
        }

        $dhEmi = date('Y-m-d\TH:i:sP');
        $qCTe = (string) count($chavesCte);
        $vCarga = number_format((float) ($mdfe['valor_total_carga'] ?? 0), 2, '.', '');
        $qCarga = number_format((float) ($mdfe['peso_total_carga'] ?? 0), 3, '.', '');
        if ((float) $qCarga <= 0) {
            $qCarga = '0.001';
        }
        if ((float) $vCarga < 0) {
            $vCarga = '0.00';
        }

        $id = 'MDFe' . $chave;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<MDFe xmlns="http://www.portalfiscal.inf.br/mdfe">';
        $xml .= '<infMDFe versao="4.00" Id="' . $this->xmlEscape($id) . '">';
        $xml .= '<ide>';
        $xml .= '<cUF>' . $this->xmlEscape($cUF) . '</cUF>';
        $xml .= '<tpAmb>' . $this->xmlEscape($tpAmb) . '</tpAmb>';
        $xml .= '<tpEmit>1</tpEmit>';
        $xml .= '<mod>58</mod>';
        $xml .= '<serie>' . $this->xmlEscape((string) $serie) . '</serie>';
        $xml .= '<nMDF>' . $this->xmlEscape((string) $numero) . '</nMDF>';
        $xml .= '<cMDF>' . $this->xmlEscape($cMDF) . '</cMDF>';
        $xml .= '<modal>1</modal>';
        $xml .= '<dhEmi>' . $this->xmlEscape($dhEmi) . '</dhEmi>';
        $xml .= '<tpEmis>1</tpEmis>';
        $xml .= '<procEmi>0</procEmi>';
        $xml .= '<verProc>1.0</verProc>';
        $xml .= '<UFIni>' . $this->xmlEscape($ufIni) . '</UFIni>';
        $xml .= '<UFFim>' . $this->xmlEscape($ufFim) . '</UFFim>';
        $xml .= '</ide>';

        $xml .= '<emit>';
        $xml .= '<CNPJ>' . $this->xmlEscape($cnpjEmit) . '</CNPJ>';
        $xml .= '<IE>' . $this->xmlEscape($ieEmit) . '</IE>';
        $xml .= '<xNome>' . $this->xmlEscape($xNome) . '</xNome>';
        if ($xFant !== '') {
            $xml .= '<xFant>' . $this->xmlEscape($xFant) . '</xFant>';
        }
        $xml .= '<enderEmit>';
        $xml .= '<xLgr>' . $this->xmlEscape($xLgr) . '</xLgr>';
        $xml .= '<nro>' . $this->xmlEscape($nro) . '</nro>';
        $xml .= '<xBairro>' . $this->xmlEscape($xBairro) . '</xBairro>';
        $xml .= '<cMun>' . $this->xmlEscape($cMunEmit) . '</cMun>';
        $xml .= '<xMun>' . $this->xmlEscape($xMunEmit) . '</xMun>';
        $xml .= '<CEP>' . $this->xmlEscape($cep !== '' ? $cep : '00000000') . '</CEP>';
        $xml .= '<UF>' . $this->xmlEscape($ufEmit) . '</UF>';
        if ($fone !== '') {
            $xml .= '<fone>' . $this->xmlEscape($fone) . '</fone>';
        }
        if ($email !== '') {
            $xml .= '<email>' . $this->xmlEscape($email) . '</email>';
        }
        $xml .= '</enderEmit>';
        $xml .= '</emit>';

        $xml .= '<infModal versaoModal="4.00"><rodo>';
        $xml .= '<veicTracao>';
        $xml .= '<cInt>' . $this->xmlEscape((string) ($mdfe['veiculo_id'] ?? '1')) . '</cInt>';
        $xml .= '<placa>' . $this->xmlEscape($placa) . '</placa>';
        if ($renavam !== '') {
            $xml .= '<RENAVAM>' . $this->xmlEscape($renavam) . '</RENAVAM>';
        }
        $xml .= '<tara>' . $this->xmlEscape((string) $tara) . '</tara>';
        $xml .= '<capKG>' . $this->xmlEscape((string) $capKg) . '</capKG>';
        $xml .= '<tpRod>01</tpRod>';
        $xml .= '<tpCar>00</tpCar>';
        $xml .= '<UF>' . $this->xmlEscape($ufIni) . '</UF>';
        if ($rntrc !== '') {
            $xml .= '<RNTRC>' . $this->xmlEscape($rntrc) . '</RNTRC>';
        }
        $xml .= '</veicTracao>';
        $xml .= '<condutor><xNome>' . $this->xmlEscape($nomeCond) . '</xNome><CPF>' . $this->xmlEscape($cpfCond) . '</CPF></condutor>';
        $xml .= '</rodo></infModal>';

        $xml .= '<infDoc>';
        $xml .= '<infMunDescarga cMunDescarga="' . $this->xmlEscape($cMunDesc) . '" xMunDescarga="' . $this->xmlEscape($cidadeDesc) . '">';
        foreach ($chavesCte as $ch) {
            $xml .= '<infCTe><chCTe>' . $this->xmlEscape($ch) . '</chCTe></infCTe>';
        }
        $xml .= '</infMunDescarga>';
        $xml .= '</infDoc>';

        $xml .= '<tot>';
        $xml .= '<qCTe>' . $this->xmlEscape($qCTe) . '</qCTe>';
        $xml .= '<vCarga>' . $this->xmlEscape($vCarga) . '</vCarga>';
        $xml .= '<cUnid>01</cUnid>';
        $xml .= '<qCarga>' . $this->xmlEscape($qCarga) . '</qCarga>';
        $xml .= '</tot>';

        $xml .= '</infMDFe></MDFe>';

        return $xml;
    }

    private function executarSoapConsulta(string $wsUrl, string $soapEnvelope): string
    {
        $ch = curl_init($wsUrl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "MDFeConsulta"',
        ]);

        // Certificado A1 via PFX
        curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certPass);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapEnvelope);

        $responseXml = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($responseXml === false || trim((string)$responseXml) === '') {
            throw new RuntimeException('Falha na consulta SEFAZ (HTTP ' . (string)$httpCode . '): ' . (string)$curlErr);
        }

        return (string)$responseXml;
    }

    /**
     * Instancia Tools do sped-mdfe com configuracao da empresa/certificado.
     */
    private function buildMdfeTools()
    {
        $siglaUF = 'PR';
        if (!empty($this->cfg['codigo_municipio'])) {
            try {
                $conn = getConnection();
                $stmtUf = $conn->prepare('SELECT uf FROM cidades WHERE codigo_ibge = :ibge LIMIT 1');
                $stmtUf->execute([':ibge' => (string) $this->cfg['codigo_municipio']]);
                $ufBanco = $stmtUf->fetchColumn();
                if ($ufBanco) {
                    $siglaUF = (string) $ufBanco;
                }
            } catch (Throwable $e) {
            }
        }

        $config = [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => (($this->cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao') ? 1 : 2,
            'razaosocial' => $this->cfg['razao_social'] ?? 'Empresa',
            'siglaUF' => $siglaUF,
            'cnpj' => preg_replace('/\D/', '', (string) ($this->cfg['cnpj'] ?? '')),
            'schemes' => 'PL_MDFe_400',
            'versao' => '4.00',
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        $certificate = Certificate::readPfx((string) file_get_contents($this->certPath), $this->certPass);

        $class = '\\NFePHP\\MDFe\\Tools';
        return new $class((string) $configJson, $certificate);
    }

    private function callFirstAvailableMethod($obj, array $methods, array $args = [])
    {
        foreach ($methods as $m) {
            if (method_exists($obj, $m)) {
                return $obj->{$m}(...$args);
            }
        }
        return null;
    }

    private function parseRetornoSefaz(string $xml): array
    {
        $sx = @simplexml_load_string($xml);
        if ($sx === false) {
            return ['cStat' => '', 'xMotivo' => 'Retorno SEFAZ invalido', 'protocolo' => null, 'chave' => null];
        }

        $getFirst = function (string $name) use ($sx): ?string {
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

        return [
            'cStat' => (string) ($getFirst('cStat') ?? ''),
            'xMotivo' => (string) ($getFirst('xMotivo') ?? ''),
            'protocolo' => $getFirst('nProt') ?? $getFirst('nRec'),
            'chave' => $getFirst('chMDFe'),
        ];
    }

    private function isTimeoutError(string $mensagem): bool
    {
        $m = strtolower($mensagem);
        return str_contains($m, 'timed out')
            || str_contains($m, 'timeout')
            || str_contains($m, 'operation timed out')
            || str_contains($m, 'http 504')
            || str_contains($m, 'tempo limite');
    }

    private function resolverTimeoutPorConsulta(string $chave): array
    {
        if (strlen($chave) !== 44) {
            return ['sucesso' => false, 'status' => 'pendente', 'erro' => 'Timeout no envio e chave inválida para consulta posterior.'];
        }
        try {
            $consulta = $this->consultarEventosPorChave($chave);
            $cStat = (string) ($consulta['cStat'] ?? '');
            $xmlRet = (string) ($consulta['raw_xml'] ?? '');
            $parsed = $xmlRet !== '' ? $this->parseRetornoSefaz($xmlRet) : [];
            if (in_array($cStat, ['100', '150'], true)) {
                return [
                    'sucesso' => true,
                    'status' => 'autorizado',
                    'protocolo' => $parsed['protocolo'] ?? null,
                    'chave_acesso' => $parsed['chave'] ?? $chave,
                    'xml_retorno' => $xmlRet,
                ];
            }
            return [
                'sucesso' => true,
                'status' => 'pendente',
                'protocolo' => $parsed['protocolo'] ?? null,
                'chave_acesso' => $parsed['chave'] ?? $chave,
                'xml_retorno' => $xmlRet,
            ];
        } catch (Throwable $e) {
            return ['sucesso' => false, 'status' => 'pendente', 'erro' => 'Timeout no envio e falha na consulta posterior: ' . $e->getMessage()];
        }
    }

    private function ensureMdfeEnvioSchema(): void
    {
        $conn = getConnection();
        $conn->exec("
            CREATE TABLE IF NOT EXISTS mdfe_envios (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                mdfe_id BIGINT NULL,
                xml_hash CHAR(64) NOT NULL,
                status_envio VARCHAR(30) NOT NULL,
                protocolo VARCHAR(60) NULL,
                metodo_envio VARCHAR(40) NULL,
                resposta_sefaz LONGTEXT NULL,
                erro TEXT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_empresa_xmlhash (empresa_id, xml_hash),
                INDEX idx_mdfe_id (mdfe_id),
                INDEX idx_status (status_envio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function buscarEnvioPorHash(string $xmlHash): ?array
    {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM mdfe_envios WHERE empresa_id = ? AND xml_hash = ? LIMIT 1");
        $stmt->execute([$this->empresaId, $xmlHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function registrarEnvio(
        int $mdfeId,
        string $xmlHash,
        string $statusEnvio,
        ?string $protocolo,
        ?string $respostaSefaz,
        ?string $metodoEnvio,
        ?string $erro
    ): void {
        $conn = getConnection();
        $stmt = $conn->prepare("
            INSERT INTO mdfe_envios
                (empresa_id, mdfe_id, xml_hash, status_envio, protocolo, metodo_envio, resposta_sefaz, erro, criado_em, atualizado_em)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                mdfe_id = VALUES(mdfe_id),
                status_envio = VALUES(status_envio),
                protocolo = VALUES(protocolo),
                metodo_envio = VALUES(metodo_envio),
                resposta_sefaz = VALUES(resposta_sefaz),
                erro = VALUES(erro),
                atualizado_em = NOW()
        ");
        $stmt->execute([
            $this->empresaId,
            $mdfeId > 0 ? $mdfeId : null,
            $xmlHash,
            $statusEnvio,
            $protocolo,
            $metodoEnvio,
            $respostaSefaz,
            $erro,
        ]);
    }

    private function buildResultFromStoredEnvio(array $envio, string $xmlAssinado): array
    {
        $xmlRet = (string) ($envio['resposta_sefaz'] ?? '');
        $parsed = $xmlRet !== '' ? $this->parseRetornoSefaz($xmlRet) : [];
        $status = (string) ($envio['status_envio'] ?? 'pendente');
        return [
            'sucesso' => in_array($status, ['autorizado', 'pendente', 'em_processamento'], true),
            'status' => $status === 'em_processamento' ? 'pendente' : $status,
            'protocolo' => $envio['protocolo'] ?? ($parsed['protocolo'] ?? null),
            'chave_acesso' => $parsed['chave'] ?? null,
            'xml_assinado' => $xmlAssinado,
            'xml_retorno' => $xmlRet !== '' ? $xmlRet : null,
            'metodo_envio' => (string) ($envio['metodo_envio'] ?? 'idempotente_cache'),
            'xml_hash' => (string) ($envio['xml_hash'] ?? hash('sha256', $xmlAssinado)),
            'idempotente' => true,
        ];
    }

    /**
     * Assinatura manual (A1) como fallback quando nfephp Tools falhar.
     * Observacao: para producao, prefira nfephp Tools (XMLDSig completo).
     */
    private function assinarXmlManualA1(string $xml): string
    {
        $pfx = @file_get_contents($this->certPath);
        if ($pfx === false || $pfx === '') {
            throw new RuntimeException('Certificado A1 não pôde ser lido para assinatura.');
        }
        $certs = [];
        if (!openssl_pkcs12_read($pfx, $certs, $this->certPass)) {
            throw new RuntimeException('Falha ao carregar certificado PFX para assinatura.');
        }
        $privateKey = $certs['pkey'] ?? null;
        if (!$privateKey) {
            throw new RuntimeException('Chave privada do certificado indisponível.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);
        $infMDFe = $doc->getElementsByTagName('infMDFe')->item(0);
        if (!$infMDFe) {
            throw new RuntimeException('Tag infMDFe não encontrada para assinatura.');
        }
        $canonical = $infMDFe->C14N();
        if (!openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('Falha ao assinar XML MDF-e manualmente.');
        }
        $signatureValue = base64_encode($signature);
        $signatureNode = $doc->createElement('Signature');
        $signatureNode->appendChild($doc->createElement('SignatureValue', $signatureValue));
        $infMDFe->parentNode->appendChild($signatureNode);

        return $doc->saveXML();
    }

    private function enviarXmlAssinadoManual(string $xmlAssinado): string
    {
        $tpAmb = (($this->cfg['ambiente_sefaz'] ?? 'homologacao') === 'producao') ? 1 : 2;
        $url = $this->getWsRecepcaoSincUrl($tpAmb);
        $idLote = str_pad((string) random_int(1, 999999999999999), 15, '0', STR_PAD_LEFT);
        $mdfeSemDecl = preg_replace('/<\?xml[^>]*\?>\s*/', '', $xmlAssinado) ?: $xmlAssinado;

        $xmlDados = '<enviMDFe xmlns="http://www.portalfiscal.inf.br/mdfe" versao="4.00">'
            . '<idLote>' . $idLote . '</idLote>'
            . '<indSinc>1</indSinc>'
            . $mdfeSemDecl
            . '</enviMDFe>';

        $soapEnvelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Body>'
            . '<mdfeDadosMsg xmlns="http://www.portalfiscal.inf.br/mdfe/wsdl/MDFeRecepcaoSinc">' . $xmlDados . '</mdfeDadosMsg>'
            . '</soap12:Body>'
            . '</soap12:Envelope>';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
        curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->certPass);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/soap+xml; charset=utf-8',
            'SOAPAction: "mdfeRecepcaoSinc"',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soapEnvelope);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false || trim((string)$response) === '') {
            throw new RuntimeException('Erro no envio manual MDF-e (HTTP ' . $httpCode . '): ' . $err);
        }
        return (string)$response;
    }

    private function getWsRecepcaoSincUrl(int $tpAmb): string
    {
        // Endpoint padrão SVRS (pode ser sobrescrito pela configuração da empresa).
        $cfgCustom = trim((string) ($this->cfg['url_mdfe_recepcao_sinc'] ?? ''));
        if ($cfgCustom !== '') {
            return $cfgCustom;
        }
        if ($tpAmb === 1) {
            return 'https://mdfe.svrs.rs.gov.br/ws/MDFeRecepcaoSinc/MDFeRecepcaoSinc.asmx';
        }
        return 'https://mdfe-homologacao.svrs.rs.gov.br/ws/MDFeRecepcaoSinc/MDFeRecepcaoSinc.asmx';
    }

    private function buscarCodigoMunicipio(PDO $conn, string $nomeCidade, string $uf): string
    {
        $nomeCidade = trim($nomeCidade);
        $uf = strtoupper(trim($uf));
        if ($nomeCidade === '' || $uf === '') {
            return '0000000';
        }

        try {
            $stmt = $conn->prepare("
                SELECT codigo_ibge
                FROM cidades
                WHERE UPPER(uf) = :uf AND UPPER(nome) = :nome
                LIMIT 1
            ");
            $stmt->execute([
                ':uf' => $uf,
                ':nome' => function_exists('mb_strtoupper')
                    ? mb_strtoupper($nomeCidade, 'UTF-8')
                    : strtoupper($nomeCidade),
            ]);
            $ibge = (string) ($stmt->fetchColumn() ?: '');
            $ibge = preg_replace('/\D/', '', $ibge);
            if (strlen($ibge) === 7) {
                return $ibge;
            }
        } catch (Throwable $e) {
        }

        return '0000000';
    }

    private function normalizarEndereco(string $endereco): array
    {
        $e = trim($endereco);
        if ($e === '') {
            return ['NAO INFORMADO', 'S/N', 'CENTRO'];
        }
        $partes = array_map('trim', explode(',', $e));
        $xLgr = $partes[0] ?? 'NAO INFORMADO';
        $nro = $partes[1] ?? 'S/N';
        $xBairro = $partes[2] ?? 'CENTRO';
        if ($xLgr === '') {
            $xLgr = 'NAO INFORMADO';
        }
        if ($nro === '') {
            $nro = 'S/N';
        }
        if ($xBairro === '') {
            $xBairro = 'CENTRO';
        }
        return [$xLgr, $nro, $xBairro];
    }

    private function apenasDigitos(string $v): string
    {
        return preg_replace('/\D/', '', $v);
    }

    private function xmlEscape(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

