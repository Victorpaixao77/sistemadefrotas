<?php
/**
 * Caminho público da aplicação (URL path, sem domínio) para links e API.
 * Ex.: /sistema-frotas ou vazio se o app estiver na raiz do site.
 */

if (!function_exists('sf_app_web_base')) {
    function sf_app_web_base(): string
    {
        $sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        // Módulos sob a raiz do app: links devem apontar para /app/..., não /app/modulo/...
        $modulePrefixes = [
            '/fiscal/',
            '/calendario/',
            '/notificacoes/',
            '/api/',
            '/pages_motorista/',
            '/pages_adm/',
            '/IA/',
            '/google-maps/',
        ];
        $base = null;
        foreach ($modulePrefixes as $prefix) {
            $pos = strpos($sn, $prefix);
            if ($pos !== false) {
                $base = substr($sn, 0, $pos);
                break;
            }
        }
        if ($base === null) {
            if (preg_match('#/pages/[^/]+\.php$#', $sn)) {
                $base = dirname(dirname($sn));
            } else {
                $base = dirname($sn);
            }
        }
        $base = str_replace('\\', '/', (string) $base);
        if ($base === '/' || $base === '.' || $base === '') {
            return '';
        }
        return rtrim($base, '/');
    }

    /**
     * URL relativa ao host: /sistema-frotas/pages/foo.php
     *
     * @param string $rel Ex.: index.php, pages/routes.php, fiscal/pages/nfe.php
     */
    function sf_app_url(string $rel): string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $base = sf_app_web_base();
        if ($base === '') {
            return '/' . $rel;
        }
        return $base . '/' . $rel;
    }

    /**
     * Path do cookie de sessão (ex.: /sistema-frotas ou / se o docroot for a pasta do app).
     * Sobrescreva com env SF_SESSION_PATH se necessário.
     */
    function sf_session_cookie_path(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $override = getenv('SF_SESSION_PATH');
        if (is_string($override) && $override !== '') {
            $cached = $override;
            return $cached;
        }
        $rootFs = realpath(__DIR__ . '/..');
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $doc = $docRoot !== '' ? realpath($docRoot) : false;
        if ($rootFs && $doc) {
            $rootFs = str_replace('\\', '/', $rootFs);
            $doc = str_replace('\\', '/', $doc);
            if (strpos($rootFs, $doc) === 0) {
                $rel = trim(substr($rootFs, strlen($doc)), '/');
                $cached = $rel === '' ? '/' : '/' . $rel;
                return $cached;
            }
        }
        $cached = '/sistema-frotas';
        return $cached;
    }

    /** Query string para bust de cache de assets (APP_VERSION em config). */
    function sf_asset_v(): string
    {
        if (defined('APP_VERSION')) {
            return (string) APP_VERSION;
        }
        return '1';
    }

    /**
     * URL do site institucional (landing) para links “Voltar ao site”.
     * Padrão: https://frotec.online (constante SF_MARKETING_SITE em config.php).
     * Sobrescreva com SF_MARKETING_SITE_URL no ambiente (ex.: http://localhost:8080 em dev).
     */
    function sf_marketing_site_url(): string
    {
        $raw = '';
        $env = getenv('SF_MARKETING_SITE_URL');
        if (is_string($env) && trim($env) !== '') {
            $raw = trim($env);
        } elseif (defined('SF_MARKETING_SITE')) {
            $raw = trim((string) SF_MARKETING_SITE);
        }
        if ($raw === '') {
            return 'https://frotec.online';
        }
        $out = rtrim($raw, '/');
        return $out !== '' ? $out : 'https://frotec.online';
    }

    /** CDN Chart.js 4.x (UMD) — versão fixa em todo o sistema. */
    function sf_chartjs_cdn_url(): string
    {
        return 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
    }

    function sf_chartjs_script_tag(): string
    {
        return '<script src="' . htmlspecialchars(sf_chartjs_cdn_url(), ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    /**
     * WhatsApp de suporte (menu lateral). Sobrescreva com SF_SUPPORT_WHATSAPP no ambiente
     * (somente dígitos, ex.: 5544997263823). Padrão: mesmo contato do site Frotec.
     */
    function sf_support_whatsapp_digits(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $raw = getenv('SF_SUPPORT_WHATSAPP');
        if (!is_string($raw) || trim($raw) === '') {
            if (defined('SF_SUPPORT_WHATSAPP') && is_string(SF_SUPPORT_WHATSAPP) && trim(SF_SUPPORT_WHATSAPP) !== '') {
                $raw = SF_SUPPORT_WHATSAPP;
            } else {
                $cached = '5544997263823';
                return $cached;
            }
        }
        $digits = preg_replace('/\D/', '', $raw);
        $cached = (strlen($digits) >= 10) ? $digits : '5544997263823';
        return $cached;
    }

    /** Link wa.me para abrir conversa de suporte no WhatsApp. */
    function sf_support_whatsapp_url(?string $message = null): string
    {
        if ($message === null || $message === '') {
            $message = 'Olá! Preciso de ajuda com o Sistema Frotec.';
        }
        return 'https://wa.me/' . sf_support_whatsapp_digits() . '?text=' . rawurlencode($message);
    }
}
