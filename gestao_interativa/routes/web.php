<?php
use GestaoInterativa\Controllers\PneuController;
use GestaoInterativa\Repositories\PneuRepository;

$repo = new PneuRepository();
$controller = new PneuController($repo);

// Simulação de roteamento simples
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if (preg_match('#^/gestao_interativa/pneus$#', $uri) && $method === 'GET') {
    $controller->index();
} elseif (preg_match('#^/gestao_interativa/pneus/create$#', $uri) && $method === 'GET') {
    $controller->create();
} elseif (preg_match('#^/gestao_interativa/pneus$#', $uri) && $method === 'POST') {
    $controller->store($_POST);
} elseif (preg_match('#^/gestao_interativa/pneus/(\d+)$#', $uri, $matches) && $method === 'GET') {
    $controller->show($matches[1]);
} elseif (preg_match('#^/gestao_interativa/pneus/(\d+)/edit$#', $uri, $matches) && $method === 'GET') {
    $controller->edit($matches[1]);
} elseif (preg_match('#^/gestao_interativa/pneus/(\d+)$#', $uri, $matches) && $method === 'POST') {
    $controller->update($matches[1], $_POST);
} elseif (preg_match('#^/gestao_interativa/pneus/(\d+)/delete$#', $uri, $matches) && $method === 'POST') {
    $controller->delete($matches[1]);
} else {
    http_response_code(404);
    echo 'Página não encontrada';
} 