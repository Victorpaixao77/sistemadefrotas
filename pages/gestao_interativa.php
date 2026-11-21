<?php
/**
 * Redirecionamento legado → Gestão Interativa (versão atual).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Location: ' . sf_app_url('pages/gestao_interativa_pro.php'), true, 301);
exit;
