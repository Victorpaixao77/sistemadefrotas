<?php
/**
 * DAMDFE em layout oficial (NFePHP sped-da) a partir do XML MDF-e / mdfeProc.
 */
declare(strict_types=1);

function fiscal_mdfe_normalizar_xml_proc(string $xml, ?string $protocolo = null, ?string $chave = null): string
{
    $xml = trim($xml);
    if ($xml === '') {
        return '';
    }
    if (stripos($xml, '<mdfeProc') !== false) {
        return $xml;
    }
    $mdfeBody = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $xml) ?: $xml;
    if (stripos($mdfeBody, '<MDFe') === false) {
        return $xml;
    }
    $nProt = trim((string) $protocolo);
    $ch = preg_replace('/\D/', '', (string) $chave);
    if ($nProt === '') {
        return $xml;
    }
    $tpAmb = '2';
    if (preg_match('/<tpAmb>(\d)<\/tpAmb>/', $mdfeBody, $m)) {
        $tpAmb = $m[1];
    }
    $chMdfe = $ch;
    if ($chMdfe === '' && preg_match('/Id="MDFe(\d{44})"/', $mdfeBody, $m2)) {
        $chMdfe = $m2[1];
    }
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<mdfeProc xmlns="http://www.portalfiscal.inf.br/mdfe" versao="3.00">'
        . $mdfeBody
        . '<protMDFe versao="3.00"><infProt>'
        . '<tpAmb>' . htmlspecialchars($tpAmb, ENT_XML1) . '</tpAmb>'
        . '<chMDFe>' . htmlspecialchars($chMdfe, ENT_XML1) . '</chMDFe>'
        . '<dhRecbto>' . date('Y-m-d\TH:i:sP') . '</dhRecbto>'
        . '<nProt>' . htmlspecialchars($nProt, ENT_XML1) . '</nProt>'
        . '<digVal></digVal>'
        . '<cStat>100</cStat>'
        . '<xMotivo>Autorizado o uso do MDF-e</xMotivo>'
        . '</infProt></protMDFe>'
        . '</mdfeProc>';
}

function fiscal_gerar_damdfe_pdf_sped_da(?string $xml, ?string $protocolo = null, ?string $chave = null, ?string $logoPath = null): ?string
{
    if ($xml === null || trim($xml) === '' || strpos($xml, '<') === false) {
        return null;
    }
    if (!class_exists(\NFePHP\DA\MDFe\Damdfe::class)) {
        error_log(
            'fiscal_gerar_damdfe_pdf_sped_da: pacote ausente. Execute composer install (nfephp-org/sped-da).'
        );
        return null;
    }
    try {
        $xmlProc = fiscal_mdfe_normalizar_xml_proc($xml, $protocolo, $chave);
        $damdfe = new \NFePHP\DA\MDFe\Damdfe($xmlProc);
        if (method_exists($damdfe, 'printParameters')) {
            $damdfe->printParameters('P', 'A4', 2, 2);
        }
        if (method_exists($damdfe, 'debugMode')) {
            $damdfe->debugMode(false);
        }
        if ($logoPath !== null && $logoPath !== '' && is_file($logoPath) && method_exists($damdfe, 'logoParameters')) {
            $damdfe->logoParameters($logoPath, 'L', 100, 0);
        }
        $bin = $damdfe->render('');
        return is_string($bin) && $bin !== '' ? $bin : null;
    } catch (Throwable $e) {
        error_log('fiscal_gerar_damdfe_pdf_sped_da: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        return null;
    }
}
