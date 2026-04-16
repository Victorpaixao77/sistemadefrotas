<?php
/**
 * Base URL path for the /api folder (e.g. /sistema-frotas/api).
 * Include from pages: require_once __DIR__ . '/../includes/sf_api_base.php';
 */
require_once __DIR__ . '/sf_paths.php';
$__sf_base = sf_app_web_base();
$__sf_api_base = ($__sf_base === '') ? '/api' : ($__sf_base . '/api');

function sf_render_api_scripts(): void
{
    global $__sf_api_base;
    $app_base = sf_app_web_base();
    $csrf = '';
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!function_exists('csrf_token_get')) {
            require_once __DIR__ . '/csrf.php';
        }
        $csrf = csrf_token_get();
    }
    ?>
<script>window.__SF_API_BASE__=<?php echo json_encode($__sf_api_base, JSON_UNESCAPED_SLASHES); ?>;window.__SF_APP_BASE__=<?php echo json_encode($app_base, JSON_UNESCAPED_SLASHES); ?>;window.__SF_CSRF__=<?php echo json_encode($csrf, JSON_UNESCAPED_SLASHES); ?>;window.__SF_DEBUG__=<?php echo (defined('DEBUG_MODE') && DEBUG_MODE) ? 'true' : 'false'; ?>;</script>
<script>
function sfAppUrl(rel) {
    rel = String(rel || '').replace(/^\//, '');
    var b = typeof window.__SF_APP_BASE__ === 'string' && window.__SF_APP_BASE__ !== ''
        ? String(window.__SF_APP_BASE__).replace(/\/+$/, '')
        : '';
    if (b === '') return '/' + rel;
    return b + '/' + rel;
}
function sfApiUrl(rel) {
    rel = String(rel || '').replace(/^\//, '');
    var b = typeof window.__SF_API_BASE__ === 'string' && window.__SF_API_BASE__ !== ''
        ? String(window.__SF_API_BASE__).replace(/\/+$/, '')
        : '';
    if (b) return b + '/' + rel;
    try { return new URL('../api/' + rel, window.location.href).href; }
    catch (e) { return '../api/' + rel; }
}
/** Mensagem segura para exibir ao usuário (evita "Maximum call stack size exceeded" em toda a UI). */
function sfSafeErrorMessage(err, fallback) {
    var msg = '';
    if (err == null) {
        msg = '';
    } else if (typeof err === 'string') {
        msg = err;
    } else if (typeof err === 'object' && err.message != null && String(err.message) !== '') {
        msg = String(err.message);
    } else {
        try { msg = String(err); } catch (e) { msg = ''; }
    }
    if (!msg) {
        msg = fallback || 'Ocorreu um erro. Tente novamente.';
    }
    /* Só mensagens típicas de estouro de pilha no JS (evita confundir com textos de API que citam "stack trace") */
    if (/maximum call stack size exceeded|too much recursion/i.test(msg)) {
        return 'Erro no navegador (sobrecarga). Atualize a página.';
    }
    return msg;
}
/** Headers para mutações na API (X-CSRF-Token). Mescla com extra (ex.: Content-Type). */
function sfMutationHeaders(extra) {
    var h = {};
    if (extra && typeof extra === 'object') {
        for (var k in extra) {
            if (Object.prototype.hasOwnProperty.call(extra, k)) h[k] = extra[k];
        }
    }
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        h['X-CSRF-Token'] = window.__SF_CSRF__;
    }
    return h;
}

// Wrapper global do fetch: anexa X-CSRF-Token automaticamente em mutações.
(function () {
    if (typeof window === 'undefined' || typeof window.fetch !== 'function') return;
    if (window.__SF_FETCH_WRAPPED__) return;
    window.__SF_FETCH_WRAPPED__ = true;

    var origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};

        var method = (init.method || (input && input.method) || 'GET');
        method = String(method).toUpperCase();
        var isMutation = (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE');

        // URL (string ou Request)
        var url = '';
        if (typeof input === 'string') url = input;
        else if (input && typeof input.url === 'string') url = input.url;

        // Só forçamos credentials em requests para o nosso /api/
        var isApiCall = (typeof url === 'string' && url.indexOf('/api/') !== -1) || (typeof url === 'string' && url.indexOf('api/') !== -1);
        if (isApiCall && typeof init.credentials === 'undefined') {
            init.credentials = 'include';
        }

        if (isMutation && typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
            if (init.headers && typeof init.headers.set === 'function') {
                // Headers instance
                init.headers.set('X-CSRF-Token', window.__SF_CSRF__);
            } else {
                init.headers = init.headers || {};
                init.headers['X-CSRF-Token'] = window.__SF_CSRF__;
            }
        }

        return origFetch(input, init);
    };
})();
</script>
    <?php
}
