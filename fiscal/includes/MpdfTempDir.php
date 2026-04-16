<?php

/**
 * Diretório gravável para cache/temporários do mPDF (evita falha em produção quando vendor/mpdf/mpdf/tmp não tem permissão).
 */
function fiscal_mpdf_temp_dir(): string
{
    $root = dirname(__DIR__, 2);
    $uploads = $root . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploads)) {
        @mkdir($uploads, 0775, true);
    }
    $dir = $uploads . DIRECTORY_SEPARATOR . 'mpdf_tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        return $dir;
    }

    $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sistema_frotas_mpdf';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0775, true);
    }
    if (is_dir($fallback) && is_writable($fallback)) {
        return $fallback;
    }

    return sys_get_temp_dir();
}
