<?php
/**
 * Caminho público da aplicação (URL path, sem domínio) para links e API.
 * Ex.: /sistema-frotas ou vazio se o app estiver na raiz do site.
 */

if (!function_exists('sf_app_web_base')) {
    function sf_app_web_base(): string
    {
        $sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        // Se a página está dentro de `fiscal/`, a base do app deve ser o diretório raiz
        // (evita que links viram `/.../fiscal/pages/...` e chamadas viram `/.../fiscal/fiscal/api/...`).
        $fiscalPos = strpos($sn, '/fiscal/');
        if ($fiscalPos !== false) {
            $base = substr($sn, 0, $fiscalPos);
        } elseif (($calPos = strpos($sn, '/calendario/')) !== false) {
            /* calendario/index.php: assets e header usam sf_app_url a partir da raiz do app */
            $base = substr($sn, 0, $calPos);
        } elseif (preg_match('#/pages/[^/]+\.php$#', $sn)) {
            $base = dirname(dirname($sn));
        } else {
            $base = dirname($sn);
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
}
