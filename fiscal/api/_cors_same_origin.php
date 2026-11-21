<?php
/**
 * CORS restrito à MESMA ORIGEM para as APIs fiscais.
 * Substitui o antigo "Access-Control-Allow-Origin: *": reflete o Origin da requisição
 * apenas quando ele bate exatamente com o próprio host (scheme://host). Requisições
 * de mesma origem (o front do sistema) continuam funcionando; qualquer site externo
 * fica sem o cabeçalho Allow-Origin e é bloqueado pelo navegador.
 */
$__sf_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($__sf_origin !== '') {
    $__sf_scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $__sf_self = $__sf_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (strcasecmp($__sf_origin, $__sf_self) === 0) {
        header('Access-Control-Allow-Origin: ' . $__sf_origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    unset($__sf_scheme, $__sf_self);
}
unset($__sf_origin);
