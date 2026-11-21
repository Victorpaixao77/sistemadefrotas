<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_intel.php';
require_once dirname(__DIR__, 2) . '/lib/adm_ia_usage.php';

try {
    $pdo = adm_api_boot();
    $tipo = $_GET['tipo'] ?? 'auditoria';

    if ($tipo === 'comercial') {
        $ops = adm_ia_usage_timed($pdo, 'rules-engine/comercial', static fn () => adm_ia_comercial_oportunidades($pdo));
        adm_api_ok(['oportunidades' => $ops]);
    }
    $empresas = adm_ia_usage_timed($pdo, 'rules-engine/auditoria', static fn () => adm_auditoria_empresas($pdo));
    adm_api_ok(['empresas' => $empresas]);
} catch (Throwable $e) {
    error_log('intel.php: ' . $e->getMessage());
    api_json_error('Erro ao carregar inteligência.', 500);
}
