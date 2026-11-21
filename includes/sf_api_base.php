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
    if (!function_exists('csrf_token_get')) {
        require_once __DIR__ . '/csrf.php';
    }
    $csrf = csrf_token_get();
    $refuel_ai_ocr = false;
    $deepseekKey = function_exists('sf_deepseek_api_key') ? sf_deepseek_api_key() : '';
    $openaiKeyEnv = function_exists('sf_openai_api_key') ? sf_openai_api_key() : '';
    $googleVisionKeyEnv = trim((string) getenv('SF_GOOGLE_VISION_API_KEY'));
    if ($deepseekKey !== '' || $openaiKeyEnv !== '' || $googleVisionKeyEnv !== '') {
        $refuel_ai_ocr = true;
    }
    if (!$refuel_ai_ocr && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['empresa_id'])) {
        // Provedor de visão configurável por empresa (Gemini/OpenAI/OpenRouter/custom)
        if (!function_exists('sf_vision_ai_available') && is_file(__DIR__ . '/sf_vision_ai.php')) {
            require_once __DIR__ . '/sf_vision_ai.php';
        }
        if (function_exists('sf_vision_ai_available')) {
            try {
                require_once __DIR__ . '/db_connect.php';
                $refuel_ai_ocr = sf_vision_ai_available(getConnection(), (int) $_SESSION['empresa_id']);
            } catch (Throwable $e) {
                $refuel_ai_ocr = false;
            }
        }
        // Chave OpenAI legada por empresa salva no banco (UI Configurações)
        if (!$refuel_ai_ocr) {
            if (!function_exists('sf_openai_ocr_available') && is_file(__DIR__ . '/sf_openai_resolve.php')) {
                require_once __DIR__ . '/sf_openai_resolve.php';
            }
            if (function_exists('sf_openai_ocr_available')) {
                $refuel_ai_ocr = sf_openai_ocr_available((int) $_SESSION['empresa_id']);
            }
        }
    }
    if (!$refuel_ai_ocr && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['empresa_id'])) {
        try {
            require_once __DIR__ . '/db_connect.php';
            $conn = getConnection();
            try {
                $conn->exec('ALTER TABLE configuracoes ADD COLUMN google_vision_api_key TEXT NULL DEFAULT NULL');
            } catch (Throwable $e) {
            }
            $stmt = $conn->prepare('SELECT google_vision_api_key FROM configuracoes WHERE empresa_id = :e LIMIT 1');
            $stmt->execute([':e' => (int) $_SESSION['empresa_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $refuel_ai_ocr = trim((string) ($row['google_vision_api_key'] ?? '')) !== '';
        } catch (Throwable $e) {
            $refuel_ai_ocr = false;
        }
    }
    ?>
<script>window.__SF_API_BASE__=<?php echo json_encode($__sf_api_base, JSON_UNESCAPED_SLASHES); ?>;window.__SF_APP_BASE__=<?php echo json_encode($app_base, JSON_UNESCAPED_SLASHES); ?>;window.__SF_CSRF__=<?php echo json_encode($csrf, JSON_UNESCAPED_SLASHES); ?>;window.__SF_DEBUG__=<?php echo (defined('DEBUG_MODE') && DEBUG_MODE) ? 'true' : 'false'; ?>;window.__SF_REFUEL_AI_OCR__=<?php echo $refuel_ai_ocr ? 'true' : 'false'; ?>;</script>
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
/**
 * Anti-flash de paginação: mantém as linhas atuais visíveis ao paginar e só
 * esmaece a tabela (sem limpar) se a resposta demorar mais que `delay` ms.
 * Retorna uma função para chamar quando os dados chegarem (ou em erro), que
 * restaura a opacidade. Uso:
 *   var done = sfDeferTableFlash(tabela);
 *   fetch(...).then(...).catch(...).finally(done);  // ou chame done() nos callbacks
 */
function sfDeferTableFlash(el, delay) {
    if (typeof delay !== 'number') delay = 180;
    if (el) { el.style.transition = 'opacity 0.15s ease'; }
    var t = setTimeout(function () { if (el) el.style.opacity = '0.55'; }, delay);
    return function () { clearTimeout(t); if (el) el.style.opacity = ''; };
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
        var isApiCall = (typeof url === 'string' && (url.indexOf('/api/') !== -1 || url.indexOf('api/') !== -1 || url.indexOf('/notificacoes/') !== -1 || url.indexOf('notificacoes/') !== -1));
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
<script src="<?php echo htmlspecialchars(sf_app_url('js/sf-core.js')); ?>?v=<?php echo htmlspecialchars(sf_asset_v()); ?>" defer></script>
    <?php
}
