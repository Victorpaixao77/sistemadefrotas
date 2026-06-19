<?php
/**
 * Cliente REST ANTT — Pagamento Eletrônico de Frete (PEF) v1.1.
 * TLS 1.2+ com autenticação mútua (certificado cliente ICP-Brasil).
 *
 * @see docs/PEF_CIOT_INTEGRACAO.md
 * @see docs/pef/dcs_pef_v1-1.pdf
 */
declare(strict_types=1);

if (!class_exists('PefAnttClient')) {
    class PefAnttClient
    {
        public const URL_HML = 'https://appservices-hml.antt.gov.br/pefServices';
        public const URL_PROD = 'https://appservices.antt.gov.br/pefServices';

        private string $baseUrl;
        private ?string $certPem;
        private ?string $keyPem;
        private ?string $keyPass;
        private int $timeout;

        public function __construct(string $ambiente = 'homologacao', ?string $certPem = null, ?string $keyPem = null, ?string $keyPass = null, int $timeout = 45)
        {
            $this->baseUrl = rtrim($ambiente === 'producao' ? self::URL_PROD : self::URL_HML, '/');
            $this->certPem = $certPem;
            $this->keyPem = $keyPem;
            $this->keyPass = $keyPass;
            $this->timeout = $timeout;
        }

        public static function fromEmpresaConfig(array $cfg): self
        {
            $pass = null;
            if (function_exists('sf_security_secret')) {
                $pass = sf_security_secret('SF_PEF_CERT_PASS', $cfg['cert_key_pass'] ?? null);
            } else {
                $pass = trim((string) ($cfg['cert_key_pass'] ?? ''));
            }
            if ($pass === '') {
                $pass = null;
            }
            return new self(
                (string) ($cfg['ambiente'] ?? 'homologacao'),
                self::resolvePath($cfg['cert_pem_path'] ?? null),
                self::resolvePath($cfg['cert_key_path'] ?? null),
                $pass
            );
        }

        private static function resolvePath(?string $path): ?string
        {
            $path = trim((string) $path);
            if ($path === '') {
                $env = getenv('SF_PEF_CERT_PEM');
                if ($env && is_string($env) && trim($env) !== '') {
                    return trim($env);
                }
                return null;
            }
            if (preg_match('~^[A-Za-z]:\\\\~', $path) || str_starts_with($path, '/')) {
                return is_file($path) ? $path : null;
            }
            $root = dirname(__DIR__);
            $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            return is_file($full) ? $full : null;
        }

        /** @return array{ok:bool,http_code:int,data:array|null,raw:string,error:?string} */
        public function post(string $servico, array $payload): array
        {
            $url = $this->baseUrl . '/' . ltrim($servico, '/');
            return $this->request('POST', $url, $payload);
        }

        /** @return array{ok:bool,http_code:int,data:array|null,raw:string,error:?string} */
        public function get(string $servico, array $query = []): array
        {
            $url = $this->baseUrl . '/' . ltrim($servico, '/');
            if ($query) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
            }
            return $this->request('GET', $url, null);
        }

        /** @return array{ok:bool,http_code:int,data:array|null,raw:string,error:?string} */
        private function request(string $method, string $url, ?array $payload): array
        {
            if (!function_exists('curl_init')) {
                return ['ok' => false, 'http_code' => 0, 'data' => null, 'raw' => '', 'error' => 'Extensão cURL não disponível no PHP.'];
            }
            if (!$this->certPem || !$this->keyPem || !is_file($this->certPem) || !is_file($this->keyPem)) {
                return ['ok' => false, 'http_code' => 0, 'data' => null, 'raw' => '', 'error' => 'Certificado cliente PEM não configurado (PEF exige mTLS ICP-Brasil).'];
            }

            $ch = curl_init($url);
            $headers = ['Accept: application/json', 'Content-Type: application/json'];
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSLCERT => $this->certPem,
                CURLOPT_SSLKEY => $this->keyPem,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if ($this->keyPass !== null && $this->keyPass !== '') {
                $opts[CURLOPT_KEYPASSWD] = $this->keyPass;
            }
            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
            } else {
                $opts[CURLOPT_HTTPGET] = true;
            }
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                return ['ok' => false, 'http_code' => $http, 'data' => null, 'raw' => '', 'error' => $err ?: 'Falha na comunicação com a ANTT.'];
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return ['ok' => false, 'http_code' => $http, 'data' => null, 'raw' => (string) $raw, 'error' => 'Resposta inválida da ANTT (não é JSON).'];
            }

            $codigo = $data['Codigo'] ?? $data['codigo'] ?? null;
            $sucesso = in_array((string) $codigo, ['110', '111'], true)
                || (is_array($codigo) && in_array('110', $codigo, true));

            return [
                'ok' => $sucesso || ($http >= 200 && $http < 300 && empty($data['Mensagem'])),
                'http_code' => $http,
                'data' => $data,
                'raw' => (string) $raw,
                'error' => $sucesso ? null : (is_string($data['Mensagem'] ?? null) ? $data['Mensagem'] : null),
            ];
        }

        // --- Serviços PEF (8 métodos) ---

        public function consultarSituacaoTransportador(array $body): array
        {
            return $this->post('ConsultarSituacaoTransportador', $body);
        }

        public function consultarFrotaTransportador(array $body): array
        {
            return $this->post('ConsultarFrotaTransportador', $body);
        }

        public function declaracaoOperacaoTransporte(array $body): array
        {
            return $this->post('DeclaracaoOperacaoTransporte', $body);
        }

        public function cancelamentoOperacaoTransporte(array $body): array
        {
            return $this->post('CancelamentoOperacaoTransporte', $body);
        }

        public function retificacaoOperacaoTransporte(array $body): array
        {
            return $this->post('RetificacaoOperacaoTransporte', $body);
        }

        public function encerramentoOperacaoTransporte(array $body): array
        {
            return $this->post('EncerramentoOperacaoTransporte', $body);
        }

        public function consultarCIOTGerado(array $body): array
        {
            return $this->post('ConsultarCIOTGerado', $body);
        }

        public function consultarExcecao(string $cpfCnpjTransportador): array
        {
            return $this->get('ConsultarExcecao', ['CPFCNPJTransportador' => preg_replace('/\D/', '', $cpfCnpjTransportador)]);
        }
    }
}
