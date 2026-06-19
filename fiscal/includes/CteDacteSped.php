<?php
/**
 * DACTE em layout oficial (NFePHP sped-da) a partir do XML CT-e / cteProc.
 */
declare(strict_types=1);

require_once __DIR__ . '/cte_wizard_helpers.php';

function fiscal_cte_normalizar_xml_proc(string $xml, ?string $protocolo = null, ?string $chave = null): string
{
    $xml = trim($xml);
    if ($xml === '') {
        return '';
    }
    if (stripos($xml, '<cteProc') !== false) {
        return $xml;
    }
    $body = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $xml) ?: $xml;
    if (stripos($body, '<CTe') === false) {
        return $xml;
    }
    $nProt = trim((string) $protocolo);
    if ($nProt === '') {
        return $xml;
    }
    $ch = preg_replace('/\D/', '', (string) $chave);
    $tpAmb = '2';
    if (preg_match('/<tpAmb>(\d)<\/tpAmb>/', $body, $m)) {
        $tpAmb = $m[1];
    }
    if ($ch === '' && preg_match('/Id="CTe(\d{44})"/', $body, $m2)) {
        $ch = $m2[1];
    }
    $ver = '4.00';
    if (preg_match('/versao="([\d.]+)"/', $body, $mv)) {
        $ver = $mv[1];
    }
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<cteProc xmlns="http://www.portalfiscal.inf.br/cte" versao="' . htmlspecialchars($ver, ENT_XML1) . '">'
        . $body
        . '<protCTe versao="' . htmlspecialchars($ver, ENT_XML1) . '"><infProt>'
        . '<tpAmb>' . htmlspecialchars($tpAmb, ENT_XML1) . '</tpAmb>'
        . '<verAplic>Frotec-CTe-1.0</verAplic>'
        . '<chCTe>' . htmlspecialchars($ch, ENT_XML1) . '</chCTe>'
        . '<dhRecbto>' . date('Y-m-d\TH:i:sP') . '</dhRecbto>'
        . '<nProt>' . htmlspecialchars($nProt, ENT_XML1) . '</nProt>'
        . '<cStat>100</cStat>'
        . '<xMotivo>Autorizado o uso do CT-e</xMotivo>'
        . '</infProt></protCTe>'
        . '</cteProc>';
}

function fiscal_gerar_dacte_pdf_sped_da(?string $xml, ?string $protocolo = null, ?string $chave = null, ?int $empresaId = null): ?string
{
    if ($xml === null || trim($xml) === '' || strpos($xml, '<') === false) {
        return null;
    }
    if (!class_exists(\NFePHP\DA\CTe\Dacte::class)) {
        error_log('fiscal_gerar_dacte_pdf_sped_da: pacote ausente. Execute composer install (nfephp-org/sped-da).');
        return null;
    }
    try {
        $xmlProc = fiscal_cte_normalizar_xml_proc($xml, $protocolo, $chave);
        $dacte = new \NFePHP\DA\CTe\Dacte($xmlProc);
        if (method_exists($dacte, 'printParameters')) {
            $dacte->printParameters('P', 'A4', 2, 2);
        }
        if (method_exists($dacte, 'debugMode')) {
            $dacte->debugMode(false);
        }
        $logoPath = $empresaId > 0 ? fiscal_cte_logo_path($empresaId) : null;
        if ($logoPath !== null && method_exists($dacte, 'logoParameters')) {
            $dacte->logoParameters($logoPath, 'L', 100, 0);
        } elseif ($logoPath !== null && method_exists($dacte, 'setLogo')) {
            $dacte->setLogo($logoPath);
        }
        $bin = $dacte->render('');
        return is_string($bin) && $bin !== '' ? $bin : null;
    } catch (Throwable $e) {
        error_log('fiscal_gerar_dacte_pdf_sped_da: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}

function fiscal_cte_logo_path(int $empresaId): ?string
{
    if (function_exists('fiscal_mdfe_logo_path')) {
        require_once __DIR__ . '/mdfe_wizard_helpers.php';
        return fiscal_mdfe_logo_path($empresaId);
    }
    $path = dirname(__DIR__, 2) . '/logo.png';
    return is_file($path) ? $path : null;
}
