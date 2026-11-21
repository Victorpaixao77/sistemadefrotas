<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/sf_saas.php';

configure_session();
session_start();

$conn = getConnection();
sf_sair_modo_suporte($conn);

header('Location: ' . sf_app_url('pages_adm/suporte.php?encerrado=1'));
exit;
