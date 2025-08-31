<?php
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['empresa_id'] = 1;

// Simular GET parameters
$_GET['action'] = 'layout_completo';
$_GET['veiculo_id'] = 55;

// Incluir a API
include 'gestao_interativa/api/eixos_veiculos.php';
?> 