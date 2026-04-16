<?php
/**
 * Página canônica para "Fornecedores" (layout moderno).
 * Mantém a URL como /pages/fornecedores.php.
 */
define('FORNECEDORES_CANONICAL_RENDER', true);

// Renderiza a página moderna mantendo toda a lógica original.
require_once __DIR__ . '/fornecedores_moderno.php';
exit;
