<?php
/**
 * Cadastro de cercas foi integrado em Configurações.
 * Mantido para links antigos / favoritos.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/sf_api_base.php';

configure_session();
session_start();
require_authentication();

header('Location: ' . sf_app_url('pages/configuracoes.php#config-gps-cercas'), true, 302);
exit;
