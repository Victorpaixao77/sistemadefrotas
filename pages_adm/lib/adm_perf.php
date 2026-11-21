<?php
declare(strict_types=1);

/**
 * Registra tempo de resposta por rota/módulo (shutdown).
 */
function adm_perf_bootstrap(): void
{
    if (defined('ADM_PERF_BOOTSTRAPPED')) {
        return;
    }
    define('ADM_PERF_BOOTSTRAPPED', true);
    $GLOBALS['adm_perf_started'] = microtime(true);
    $GLOBALS['adm_perf_route'] = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    register_shutdown_function('adm_perf_shutdown');
}

function adm_perf_module_from_route(string $route): string
{
    $base = strtolower(basename($route));
    static $map = [
        'index.php' => 'dashboard',
        'vehicles.php' => 'veiculos',
        'motorists.php' => 'motoristas',
        'gestao_motoristas.php' => 'motoristas',
        'routes.php' => 'rotas',
        'rotas_motoristas.php' => 'rotas',
        'contas_pagar.php' => 'financeiro',
        'lucratividade.php' => 'financeiro',
        'manutencoes.php' => 'manutencoes',
        'manutencao_pneus.php' => 'pneus',
        'pneus.php' => 'pneus',
        'ia_painel.php' => 'ia',
        'bi.php' => 'relatorios',
        'relatorios.php' => 'relatorios',
        'monitor.php' => 'adm',
        'performance.php' => 'adm',
        'saude.php' => 'adm',
    ];
    if (isset($map[$base])) {
        return $map[$base];
    }
    if (strpos($route, '/api/') !== false || strpos($route, '\\api\\') !== false) {
        return 'api';
    }
    if (strpos($route, 'pages_adm') !== false) {
        return 'adm';
    }
    return 'sistema';
}

function adm_perf_shutdown(): void
{
    if (empty($GLOBALS['adm_perf_started'])) {
        return;
    }
    $ms = (int) round((microtime(true) - $GLOBALS['adm_perf_started']) * 1000);
    if ($ms < 1 || $ms > 120000) {
        return;
    }

    $route = (string) ($GLOBALS['adm_perf_route'] ?? '');
    if ($route === '' || strpos($route, 'telemetry_ping') !== false) {
        return;
    }

    $modulo = adm_perf_module_from_route($route);
    $empresaId = null;
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['empresa_id'])) {
        $empresaId = (int) $_SESSION['empresa_id'];
    }

    try {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            $root = dirname(__DIR__, 2);
            if (is_file($root . '/includes/db_connect.php')) {
                require_once $root . '/includes/db_connect.php';
                if (function_exists('getConnection')) {
                    $pdo = getConnection();
                }
            }
            if (!($pdo instanceof PDO) && is_file($root . '/includes/conexao.php')) {
                require_once $root . '/includes/conexao.php';
            }
        }
        if (!($pdo instanceof PDO)) {
            return;
        }
        if (!function_exists('adm_schema_ensure')) {
            require_once __DIR__ . '/adm_schema.php';
        }
        adm_schema_ensure($pdo);

        $stmt = $pdo->prepare('INSERT INTO adm_request_metrics (rota, modulo, duracao_ms, memoria_kb, empresa_cliente_id) VALUES (?,?,?,?,?)');
        $memKb = (int) round(memory_get_peak_usage(true) / 1024);
        $stmt->execute([substr($route, 0, 255), $modulo, $ms, $memKb, $empresaId]);
    } catch (Throwable $e) {
        error_log('adm_perf_shutdown: ' . $e->getMessage());
    }
}
