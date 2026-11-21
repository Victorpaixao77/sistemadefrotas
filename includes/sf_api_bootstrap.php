<?php
/**
 * Bootstrap padrão para endpoints de API (leitura).
 *
 * Centraliza: sessão + autenticação + LIBERAÇÃO DO LOCK DE SESSÃO (session_write_close).
 * Liberar o lock cedo permite que as várias chamadas de uma página rodem em paralelo
 * (sem isso, elas serializam no lock de sessão de arquivo, deixando o carregamento lento).
 *
 * Uso recomendado em endpoints NOVOS (que só LEEM a sessão):
 *
 *     require_once __DIR__ . '/../includes/sf_api_bootstrap.php';
 *     $ctx = sf_api_boot();            // ['empresa_id'=>int, 'usuario_id'=>?int]
 *     $empresa_id = $ctx['empresa_id'];
 *
 * Para endpoints que ESCREVEM na sessão (login, troca de empresa, 2FA), passe
 * ['release_session' => false] e chame session_write_close() manualmente depois das escritas.
 *
 * Observação: o $_SESSION continua LEGÍVEL após o session_write_close() — apenas novas
 * ESCRITAS deixam de persistir. Por isso CSRF (que lê o token) segue funcionando.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

if (!function_exists('sf_api_boot')) {
    /**
     * @param array{auth?:bool, json?:bool, release_session?:bool} $opts
     * @return array{empresa_id:int, usuario_id:?int}
     */
    function sf_api_boot(array $opts = []): array
    {
        $auth    = $opts['auth'] ?? true;
        $json    = $opts['json'] ?? true;
        $release = $opts['release_session'] ?? true;

        if ($json && !headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        if (function_exists('configure_session')) {
            configure_session();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($auth && function_exists('require_authentication')) {
            require_authentication();
        }

        $empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
        $usuario_id = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null;

        // Libera o lock: a partir daqui só lemos a sessão.
        if ($release && function_exists('session_write_close')) {
            session_write_close();
        }

        return ['empresa_id' => $empresa_id, 'usuario_id' => $usuario_id];
    }
}
