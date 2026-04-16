<?php
/**
 * XML da NF-e recebida para download (PDF ou arquivo .xml):
 * usa xml_nfe do banco somente se for nfeProc completo; senão Distribuição DFe e grava.
 *
 * @param array|null $meta Se passado, recebe ['source' => 'banco'|'sefaz'|'banco_fallback', 'detail' => string]
 */
function fiscal_nfe_obter_xml_para_download(PDO $conn, int $empresa_id, int $id, array $row, bool $forcarSefaz = false, ?array &$meta = null): ?string
{
    if ($meta !== null) {
        $meta = ['source' => '', 'detail' => ''];
    }

    $raw = $row['xml_nfe'] ?? null;
    if (is_resource($raw)) {
        $raw = stream_get_contents($raw);
    }
    $dbXml = is_string($raw) ? trim($raw) : '';
    $chave = preg_replace('/\D/', '', $row['chave_acesso'] ?? '');

    $cacheOk = !$forcarSefaz
        && $dbXml !== ''
        && strlen($dbXml) >= 200
        && stripos($dbXml, 'nfeProc') !== false
        && stripos($dbXml, 'protNFe') !== false;

    if ($cacheOk) {
        if ($meta !== null) {
            $meta['source'] = 'banco';
            $meta['detail'] = 'nfeProc+prot em cache';
        }
        return $dbXml;
    }

    if (strlen($chave) !== 44) {
        if ($meta !== null) {
            $meta['source'] = $dbXml !== '' ? 'banco_fallback' : '';
            $meta['detail'] = 'chave invalida ou ausente';
        }
        return $dbXml !== '' ? $dbXml : null;
    }

    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/NFeService.php';
    try {
        $svc = new NFeService($empresa_id);
        $baixado = $svc->baixarXmlPorChave($chave);
        if (!is_string($baixado) || $baixado === '' || strpos($baixado, '<') === false) {
            if ($meta !== null) {
                $meta['source'] = $dbXml !== '' ? 'banco_fallback' : '';
                $meta['detail'] = 'sefaz sem docZip ou cStat!=138 (ver error_log NFeService)';
            }
            return $dbXml !== '' ? $dbXml : null;
        }
        try {
            $conn->query('SELECT xml_nfe FROM fiscal_nfe_clientes LIMIT 1');
            $up = $conn->prepare('UPDATE fiscal_nfe_clientes SET xml_nfe = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
            $up->execute([$baixado, $id, $empresa_id]);
        } catch (Throwable $e) {
            error_log('fiscal_nfe_obter_xml_para_download gravar xml_nfe: ' . $e->getMessage());
        }
        if ($meta !== null) {
            $meta['source'] = 'sefaz';
            $meta['detail'] = 'distDFe consChNFe';
        }
        return $baixado;
    } catch (Throwable $e) {
        error_log('fiscal_nfe_obter_xml_para_download SEFAZ: ' . $e->getMessage());
        if ($meta !== null) {
            $meta['source'] = $dbXml !== '' ? 'banco_fallback' : '';
            $meta['detail'] = 'excecao: ' . $e->getMessage();
        }
        return $dbXml !== '' ? $dbXml : null;
    }
}
