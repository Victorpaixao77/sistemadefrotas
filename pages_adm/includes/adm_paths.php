<?php
/**
 * Raiz do projeto a partir de pages_adm/includes (evita paths relativos errados).
 */
declare(strict_types=1);

if (!defined('SF_PROJECT_ROOT')) {
    define('SF_PROJECT_ROOT', dirname(__DIR__, 2));
}

if (!function_exists('sf_project_include')) {
    /**
     * Inclui arquivos que só registram funções/constantes (ex.: sf_paths.php).
     * Não use para arquivos que definem variáveis ($pdo etc.) — use require_once no escopo global.
     */
    function sf_project_include(string $rel): void
    {
        require_once SF_PROJECT_ROOT . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    }
}
