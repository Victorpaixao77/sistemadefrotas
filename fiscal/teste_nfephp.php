<?php

// Teste básico para verificar se a biblioteca NFePHP está instalada e carregando corretamente

require_once __DIR__ . '/../vendor/autoload.php';

use NFePHP\NFe\Make;

try {
    $nfe = new Make();
    echo 'NFePHP carregado com sucesso!';
} catch (Throwable $e) {
    echo 'Erro ao carregar NFePHP: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

