<?php
/**
 * Biblioteca central de segurança.
 *
 * Reúne: políticas por empresa (configuracoes_seguranca), 2FA TOTP (RFC 6238),
 * validação de senha forte, bloqueio de tentativas de login, geo-bloqueio,
 * whitelist de IP de administrador, detecção de novo acesso e envio de e-mail
 * (SMTP nativo, sem dependências externas).
 *
 * Requer sql/security_features.sql aplicado.
 */

require_once __DIR__ . '/db_connect.php';

// ---------------------------------------------------------------------------
// Política de segurança por empresa
// ---------------------------------------------------------------------------

if (!function_exists('sf_security_defaults')) {
    function sf_security_defaults(): array
    {
        return [
            'empresa_id' => 0,
            'idle_timeout_min' => 0,
            'login_max_tentativas' => 5,
            'login_bloqueio_min' => 15,
            'senha_forte' => 1,
            'two_factor_obrigatorio' => 0,
            'recaptcha_enabled' => 0,
            'recaptcha_site_key' => null,
            'recaptcha_secret_key' => null,
            'recaptcha_apos_tentativas' => 3,
            'novo_acesso_email' => 0,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_user' => null,
            'smtp_pass' => null,
            'smtp_secure' => null,
            'smtp_from' => null,
            'ip_whitelist_enabled' => 0,
            'geo_block_enabled' => 0,
            'geo_paises_permitidos' => 'BR',
        ];
    }
}

if (!function_exists('sf_security_config')) {
    /**
     * Carrega (e cria, se faltar) a política de segurança da empresa.
     * Para empresa_id 0/null retorna os defaults.
     */
    function sf_security_config(?int $empresaId): array
    {
        $defaults = sf_security_defaults();
        if (empty($empresaId)) {
            return $defaults;
        }
        try {
            $conn = getConnection();
            $stmt = $conn->prepare('SELECT * FROM configuracoes_seguranca WHERE empresa_id = :e LIMIT 1');
            $stmt->execute([':e' => $empresaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $ins = $conn->prepare('INSERT INTO configuracoes_seguranca (empresa_id) VALUES (:e)');
                $ins->execute([':e' => $empresaId]);
                $defaults['empresa_id'] = $empresaId;
                return $defaults;
            }
            return array_merge($defaults, $row);
        } catch (Throwable $e) {
            error_log('sf_security_config: ' . $e->getMessage());
            $defaults['empresa_id'] = (int) $empresaId;
            return $defaults;
        }
    }
}

if (!function_exists('sf_security_secret')) {
    /** Segredos: variável de ambiente tem prioridade sobre o valor do banco. */
    function sf_security_secret(string $envName, ?string $dbValue): string
    {
        $env = getenv($envName);
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        return is_string($dbValue) ? trim($dbValue) : '';
    }
}

// ---------------------------------------------------------------------------
// Senha forte
// ---------------------------------------------------------------------------

if (!function_exists('sf_password_validate')) {
    /**
     * Valida complexidade. Retorna ['ok'=>bool, 'erros'=>string[]].
     * Regras: 8+ chars, maiúscula, minúscula, número e caractere especial.
     */
    function sf_password_validate(string $senha): array
    {
        $erros = [];
        if (mb_strlen($senha) < 8) {
            $erros[] = 'Mínimo de 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $senha)) {
            $erros[] = 'Pelo menos uma letra maiúscula.';
        }
        if (!preg_match('/[a-z]/', $senha)) {
            $erros[] = 'Pelo menos uma letra minúscula.';
        }
        if (!preg_match('/[0-9]/', $senha)) {
            $erros[] = 'Pelo menos um número.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            $erros[] = 'Pelo menos um caractere especial.';
        }
        return ['ok' => empty($erros), 'erros' => $erros];
    }
}

// ---------------------------------------------------------------------------
// TOTP (Google/Microsoft Authenticator) — RFC 6238
// ---------------------------------------------------------------------------

if (!function_exists('sf_base32_encode')) {
    function sf_base32_encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $bits = 0;
        $value = 0;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $out .= $alphabet[($value >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($value << (5 - $bits)) & 31];
        }
        return $out;
    }
}

if (!function_exists('sf_base32_decode')) {
    function sf_base32_decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $out = '';
        $bits = 0;
        $value = 0;
        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $value = ($value << 5) | strpos($alphabet, $b32[$i]);
            $bits += 5;
            if ($bits >= 8) {
                $out .= chr(($value >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }
        return $out;
    }
}

if (!function_exists('sf_totp_generate_secret')) {
    function sf_totp_generate_secret(): string
    {
        return sf_base32_encode(random_bytes(20)); // 160 bits
    }
}

if (!function_exists('sf_totp_code')) {
    function sf_totp_code(string $secretB32, ?int $counter = null): string
    {
        $key = sf_base32_decode($secretB32);
        if ($counter === null) {
            $counter = (int) floor(time() / 30);
        }
        $bin = pack('N*', 0) . pack('N*', $counter); // 8 bytes big-endian
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $part = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        return str_pad((string) ($part % 1000000), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('sf_totp_verify')) {
    /** Verifica o código com janela de tolerância (±$window passos de 30s). */
    function sf_totp_verify(string $secretB32, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $current = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(sf_totp_code($secretB32, $current + $i), $code)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sf_totp_uri')) {
    /** URI otpauth:// para gerar o QR Code. */
    function sf_totp_uri(string $secretB32, string $accountName, string $issuer = 'Frotec'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountName)
            . '?secret=' . $secretB32
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }
}

// ---------------------------------------------------------------------------
// Tentativas de login (bloqueio persistente por e-mail+IP)
// ---------------------------------------------------------------------------

if (!function_exists('sf_login_attempt_status')) {
    /**
     * Retorna ['bloqueado'=>bool,'segundos_restantes'=>int,'tentativas'=>int].
     */
    function sf_login_attempt_status(string $email, string $ip): array
    {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare('SELECT tentativas, bloqueado_ate FROM login_attempts WHERE email = :e AND ip = :ip LIMIT 1');
            $stmt->execute([':e' => $email, ':ip' => $ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['bloqueado' => false, 'segundos_restantes' => 0, 'tentativas' => 0];
            }
            $restante = 0;
            $bloqueado = false;
            if (!empty($row['bloqueado_ate'])) {
                $restante = strtotime($row['bloqueado_ate']) - time();
                $bloqueado = $restante > 0;
            }
            return [
                'bloqueado' => $bloqueado,
                'segundos_restantes' => max(0, $restante),
                'tentativas' => (int) $row['tentativas'],
            ];
        } catch (Throwable $e) {
            error_log('sf_login_attempt_status: ' . $e->getMessage());
            return ['bloqueado' => false, 'segundos_restantes' => 0, 'tentativas' => 0];
        }
    }
}

if (!function_exists('sf_login_attempt_fail')) {
    /** Registra uma falha; bloqueia quando atinge o limite. Retorna nº de tentativas. */
    function sf_login_attempt_fail(string $email, string $ip, int $max, int $bloqueioMin): int
    {
        try {
            $conn = getConnection();
            $conn->prepare(
                'INSERT INTO login_attempts (email, ip, tentativas) VALUES (:e, :ip, 1)
                 ON DUPLICATE KEY UPDATE tentativas = tentativas + 1'
            )->execute([':e' => $email, ':ip' => $ip]);

            $stmt = $conn->prepare('SELECT tentativas FROM login_attempts WHERE email = :e AND ip = :ip LIMIT 1');
            $stmt->execute([':e' => $email, ':ip' => $ip]);
            $tentativas = (int) ($stmt->fetchColumn() ?: 0);

            if ($tentativas >= $max) {
                $ate = date('Y-m-d H:i:s', time() + $bloqueioMin * 60);
                $conn->prepare('UPDATE login_attempts SET bloqueado_ate = :ate WHERE email = :e AND ip = :ip')
                    ->execute([':ate' => $ate, ':e' => $email, ':ip' => $ip]);
            }
            return $tentativas;
        } catch (Throwable $e) {
            error_log('sf_login_attempt_fail: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('sf_login_attempt_reset')) {
    function sf_login_attempt_reset(string $email, string $ip): void
    {
        try {
            getConnection()->prepare('DELETE FROM login_attempts WHERE email = :e AND ip = :ip')
                ->execute([':e' => $email, ':ip' => $ip]);
        } catch (Throwable $e) {
            error_log('sf_login_attempt_reset: ' . $e->getMessage());
        }
    }
}

// ---------------------------------------------------------------------------
// Login global (reCAPTCHA — todas as empresas)
// ---------------------------------------------------------------------------

if (!function_exists('sf_sistema_login_defaults')) {
    function sf_sistema_login_defaults(): array
    {
        return [
            'recaptcha_enabled' => 0,
            'recaptcha_site_key' => null,
            'recaptcha_secret_key' => null,
            'recaptcha_apos_tentativas' => 3,
        ];
    }
}

if (!function_exists('sf_sistema_login_config')) {
    /**
     * Configuração global do login (tabela sistema_login_config, id=1).
     * Cria a linha se a tabela existir e estiver vazia.
     */
    function sf_sistema_login_config(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $defaults = sf_sistema_login_defaults();
        try {
            $conn = getConnection();
            $stmt = $conn->query('SELECT recaptcha_enabled, recaptcha_site_key, recaptcha_secret_key, recaptcha_apos_tentativas FROM sistema_login_config WHERE id = 1 LIMIT 1');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                try {
                    $conn->exec('INSERT IGNORE INTO sistema_login_config (id) VALUES (1)');
                    $stmt = $conn->query('SELECT recaptcha_enabled, recaptcha_site_key, recaptcha_secret_key, recaptcha_apos_tentativas FROM sistema_login_config WHERE id = 1 LIMIT 1');
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    error_log('sf_sistema_login_config insert: ' . $e->getMessage());
                }
            }
            $cache = $row ? array_merge($defaults, $row) : $defaults;
            return $cache;
        } catch (Throwable $e) {
            error_log('sf_sistema_login_config: ' . $e->getMessage());
            $cache = $defaults;
            return $cache;
        }
    }
}

if (!function_exists('sf_sistema_login_config_save')) {
    /**
     * @param array{recaptcha_enabled?:bool|int,recaptcha_site_key?:string,recaptcha_secret_key?:string,recaptcha_apos_tentativas?:int} $data
     */
    function sf_sistema_login_config_save(array $data): bool
    {
        $atual = sf_sistema_login_config();
        $enabled = isset($data['recaptcha_enabled'])
            ? (!empty($data['recaptcha_enabled']) ? 1 : 0)
            : (int) ($atual['recaptcha_enabled'] ?? 0);
        $siteKey = array_key_exists('recaptcha_site_key', $data)
            ? trim((string) $data['recaptcha_site_key'])
            : (string) ($atual['recaptcha_site_key'] ?? '');
        $secret = array_key_exists('recaptcha_secret_key', $data)
            ? trim((string) $data['recaptcha_secret_key'])
            : (string) ($atual['recaptcha_secret_key'] ?? '');
        if ($secret === '' || $secret === '********') {
            $secret = (string) ($atual['recaptcha_secret_key'] ?? '');
        }
        $threshold = isset($data['recaptcha_apos_tentativas'])
            ? max(1, min(10, (int) $data['recaptcha_apos_tentativas']))
            : max(1, min(10, (int) ($atual['recaptcha_apos_tentativas'] ?? 3)));

        try {
            $conn = getConnection();
            $conn->exec('INSERT IGNORE INTO sistema_login_config (id) VALUES (1)');
            $stmt = $conn->prepare(
                'UPDATE sistema_login_config SET
                    recaptcha_enabled = :en,
                    recaptcha_site_key = :site,
                    recaptcha_secret_key = :sec,
                    recaptcha_apos_tentativas = :thr
                 WHERE id = 1'
            );
            $stmt->execute([
                ':en' => $enabled,
                ':site' => $siteKey !== '' ? $siteKey : null,
                ':sec' => $secret !== '' ? $secret : null,
                ':thr' => $threshold,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('sf_sistema_login_config_save: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sf_login_recaptcha_config')) {
    /**
     * reCAPTCHA do login compartilhado (global).
     * Segredos: variável de ambiente > sistema_login_config (painel ADM).
     *
     * @return array{active:bool,site_key:string,secret_key:string,threshold:int}
     */
    function sf_login_recaptcha_config(?string $email = null): array
    {
        unset($email);
        $cfg = sf_sistema_login_config();

        $siteKey = sf_security_secret('SF_RECAPTCHA_SITE_KEY', $cfg['recaptcha_site_key'] ?? null);
        $secretKey = sf_security_secret('SF_RECAPTCHA_SECRET_KEY', $cfg['recaptcha_secret_key'] ?? null);

        $thresholdEnv = getenv('SF_RECAPTCHA_APOS_TENTATIVAS');
        $thresholdDefault = is_string($thresholdEnv) && trim($thresholdEnv) !== ''
            ? max(1, (int) $thresholdEnv)
            : max(1, min(10, (int) ($cfg['recaptcha_apos_tentativas'] ?? 3)));

        $enabledDb = !empty($cfg['recaptcha_enabled']);
        $hasEnvKeys = trim((string) getenv('SF_RECAPTCHA_SITE_KEY')) !== ''
            && trim((string) getenv('SF_RECAPTCHA_SECRET_KEY')) !== '';

        $threshold = $enabledDb ? $thresholdDefault : (
            $hasEnvKeys
                ? (is_string($thresholdEnv) && trim($thresholdEnv) !== '' ? max(1, (int) $thresholdEnv) : 3)
                : $thresholdDefault
        );

        if ($enabledDb && $siteKey !== '' && $secretKey !== '') {
            $active = true;
        } elseif ($hasEnvKeys && $siteKey !== '' && $secretKey !== '') {
            $active = true;
        } else {
            $active = false;
        }

        return [
            'active' => $active,
            'site_key' => $siteKey,
            'secret_key' => $secretKey,
            'threshold' => $threshold,
        ];
    }
}

if (!function_exists('sf_login_recaptcha_should_show')) {
    function sf_login_recaptcha_should_show(?string $email, string $ip): bool
    {
        $email = trim((string) $email);
        $cfg = sf_login_recaptcha_config();
        if (!$cfg['active']) {
            return false;
        }
        if ($email === '') {
            return false;
        }
        $tentativas = (int) sf_login_attempt_status($email, $ip)['tentativas'];
        return $tentativas >= $cfg['threshold'];
    }
}

if (!function_exists('sf_recaptcha_verify')) {
    function sf_recaptcha_verify(string $secret, string $response, string $ip = ''): bool
    {
        if ($secret === '' || $response === '') {
            return false;
        }
        try {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_POSTFIELDS => http_build_query([
                    'secret' => $secret,
                    'response' => $response,
                    'remoteip' => $ip,
                ]),
            ]);
            $out = curl_exec($ch);
            curl_close($ch);
            if ($out === false) {
                return false;
            }
            $json = json_decode($out, true);
            return !empty($json['success']);
        } catch (Throwable $e) {
            error_log('sf_recaptcha_verify: ' . $e->getMessage());
            return false;
        }
    }
}

// ---------------------------------------------------------------------------
// Geolocalização de IP / geo-bloqueio
// ---------------------------------------------------------------------------

if (!function_exists('sf_ip_is_private')) {
    function sf_ip_is_private(string $ip): bool
    {
        if ($ip === '' || $ip === '::1' || $ip === '127.0.0.1') {
            return true;
        }
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}

if (!function_exists('sf_geo_lookup')) {
    /**
     * Retorna ['country'=>'BR','city'=>'Maringá'] (vazio se indisponível).
     * Usa ip-api.com (sem chave) com cache em arquivo por 7 dias.
     */
    function sf_geo_lookup(string $ip): array
    {
        if (sf_ip_is_private($ip)) {
            return ['country' => '', 'city' => '', 'local' => true];
        }
        $cacheDir = __DIR__ . '/../cache/geoip';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir . '/' . md5($ip) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 7 * 86400) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        try {
            $ch = curl_init('http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode,city');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
            $out = curl_exec($ch);
            curl_close($ch);
            $json = is_string($out) ? json_decode($out, true) : null;
            $result = ['country' => '', 'city' => ''];
            if (is_array($json) && ($json['status'] ?? '') === 'success') {
                $result = ['country' => $json['countryCode'] ?? '', 'city' => $json['city'] ?? ''];
                @file_put_contents($cacheFile, json_encode($result));
            }
            return $result;
        } catch (Throwable $e) {
            error_log('sf_geo_lookup: ' . $e->getMessage());
            return ['country' => '', 'city' => ''];
        }
    }
}

if (!function_exists('sf_geo_allowed')) {
    /** True se o país do IP está na lista (CSV de códigos, ex.: 'BR,PT'). IP local sempre permitido. */
    function sf_geo_allowed(string $ip, string $paisesCsv): bool
    {
        if (sf_ip_is_private($ip)) {
            return true;
        }
        $geo = sf_geo_lookup($ip);
        if (empty($geo['country'])) {
            return true; // não conseguiu determinar — não bloqueia (fail-open)
        }
        $permitidos = array_filter(array_map('trim', explode(',', strtoupper($paisesCsv))));
        return in_array(strtoupper($geo['country']), $permitidos, true);
    }
}

// ---------------------------------------------------------------------------
// Whitelist de IP de administrador
// ---------------------------------------------------------------------------

if (!function_exists('sf_admin_ip_allowed')) {
    function sf_admin_ip_allowed(int $empresaId, string $ip): bool
    {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare('SELECT COUNT(*) FROM admin_ip_whitelist WHERE empresa_id = :e');
            $stmt->execute([':e' => $empresaId]);
            if ((int) $stmt->fetchColumn() === 0) {
                return true; // nenhuma regra cadastrada = não restringe (evita auto-trancamento)
            }
            $stmt = $conn->prepare('SELECT COUNT(*) FROM admin_ip_whitelist WHERE empresa_id = :e AND ip = :ip');
            $stmt->execute([':e' => $empresaId, ':ip' => $ip]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('sf_admin_ip_allowed: ' . $e->getMessage());
            return true;
        }
    }
}

// ---------------------------------------------------------------------------
// Detecção de novo acesso (dispositivo/IP)
// ---------------------------------------------------------------------------

if (!function_exists('sf_acesso_e_conhecido')) {
    /**
     * Retorna true se o par (IP, user agent) já é conhecido do usuário.
     * Em ambos os casos registra/atualiza o dispositivo.
     */
    function sf_acesso_e_conhecido(int $usuarioId, string $ip, string $ua, array $geo = []): bool
    {
        try {
            $conn = getConnection();
            $uaHash = sha1($ua);
            $stmt = $conn->prepare('SELECT id FROM acessos_conhecidos WHERE usuario_id = :u AND ip = :ip AND user_agent_hash = :h LIMIT 1');
            $stmt->execute([':u' => $usuarioId, ':ip' => $ip, ':h' => $uaHash]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $conn->prepare('UPDATE acessos_conhecidos SET ultimo_acesso = NOW() WHERE id = :id')
                    ->execute([':id' => $existing]);
                return true;
            }

            $conn->prepare(
                'INSERT INTO acessos_conhecidos (usuario_id, ip, user_agent_hash, cidade, pais)
                 VALUES (:u, :ip, :h, :c, :p)'
            )->execute([
                ':u' => $usuarioId,
                ':ip' => $ip,
                ':h' => $uaHash,
                ':c' => $geo['city'] ?? null,
                ':p' => $geo['country'] ?? null,
            ]);
            return false;
        } catch (Throwable $e) {
            error_log('sf_acesso_e_conhecido: ' . $e->getMessage());
            return true; // em erro, não alarmar
        }
    }
}

// ---------------------------------------------------------------------------
// E-mail (SMTP nativo, sem dependências)
// ---------------------------------------------------------------------------

if (!function_exists('sf_smtp_send')) {
    /**
     * Envia e-mail via SMTP. $cfg: host, port, user, pass, secure('ssl'|'tls'|''), from.
     * Retorna true em sucesso.
     */
    function sf_smtp_send(array $cfg, string $to, string $subject, string $bodyHtml): bool
    {
        $host = $cfg['smtp_host'] ?? '';
        $port = (int) ($cfg['smtp_port'] ?? 0);
        $user = $cfg['smtp_user'] ?? '';
        $pass = sf_security_secret('SF_SMTP_PASS', $cfg['smtp_pass'] ?? '');
        $secure = strtolower((string) ($cfg['smtp_secure'] ?? ''));
        $from = $cfg['smtp_from'] ?: $user;

        if ($host === '' || $port === 0 || $from === '') {
            error_log('sf_smtp_send: SMTP não configurado');
            return false;
        }

        $transport = ($secure === 'ssl') ? "ssl://$host" : $host;
        $errno = 0;
        $errstr = '';
        try {
            $fp = @stream_socket_client("$transport:$port", $errno, $errstr, 12);
            if (!$fp) {
                error_log("sf_smtp_send: conexão falhou ($errno) $errstr");
                return false;
            }
            $read = function () use ($fp) {
                $data = '';
                while ($line = fgets($fp, 515)) {
                    $data .= $line;
                    if (isset($line[3]) && $line[3] === ' ') {
                        break;
                    }
                }
                return $data;
            };
            $cmd = function ($c) use ($fp, $read) {
                fwrite($fp, $c . "\r\n");
                return $read();
            };

            $read(); // banner
            $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            if ($secure === 'tls') {
                $cmd('STARTTLS');
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($fp);
                    error_log('sf_smtp_send: STARTTLS falhou');
                    return false;
                }
                $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            }
            if ($user !== '') {
                $cmd('AUTH LOGIN');
                $cmd(base64_encode($user));
                $authResp = $cmd(base64_encode($pass));
                if (strpos($authResp, '235') !== 0) {
                    fclose($fp);
                    error_log('sf_smtp_send: autenticação SMTP recusada');
                    return false;
                }
            }
            $cmd("MAIL FROM:<$from>");
            $cmd("RCPT TO:<$to>");
            $cmd('DATA');
            $headers = "From: Frotec <$from>\r\n"
                . "To: <$to>\r\n"
                . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n";
            $cmd($headers . "\r\n" . $bodyHtml . "\r\n.");
            $cmd('QUIT');
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            error_log('sf_smtp_send: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sf_enviar_email_novo_acesso')) {
    function sf_enviar_email_novo_acesso(array $cfg, string $to, string $nome, string $ip, array $geo): bool
    {
        $cidade = trim(($geo['city'] ?? '') . (!empty($geo['country']) ? ' - ' . $geo['country'] : ''));
        $cidade = $cidade !== '' ? $cidade : 'Não identificada';
        $quando = date('d/m/Y H:i');
        $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
            . '<h2 style="color:#1a73e8">Novo acesso detectado</h2>'
            . '<p>Olá, ' . htmlspecialchars($nome) . '. Detectamos um acesso à sua conta Frotec de um dispositivo/local novo:</p>'
            . '<ul>'
            . '<li><strong>IP:</strong> ' . htmlspecialchars($ip) . '</li>'
            . '<li><strong>Local:</strong> ' . htmlspecialchars($cidade) . '</li>'
            . '<li><strong>Data:</strong> ' . $quando . '</li>'
            . '</ul>'
            . '<p>Se foi você, pode ignorar este e-mail. Caso não reconheça, altere sua senha imediatamente.</p>'
            . '</div>';
        return sf_smtp_send($cfg, $to, 'Frotec — Novo acesso detectado', $body);
    }
}

// ---------------------------------------------------------------------------
// IP do cliente (fallback se getClientIp() não existir)
// ---------------------------------------------------------------------------

if (!function_exists('sf_client_ip')) {
    function sf_client_ip(): string
    {
        if (function_exists('getClientIp')) {
            return (string) getClientIp();
        }
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}
