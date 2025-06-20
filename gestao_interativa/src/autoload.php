<?php

spl_autoload_register(function ($class) {
    // Mapeia o namespace para o diretório
    $prefix = 'GestaoInterativa\\';
    $base_dir = __DIR__ . '/';

    // Verifica se a classe usa o prefixo do namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtém o caminho relativo da classe
    $relative_class = substr($class, $len);

    // Substitui o namespace por diretórios
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Se o arquivo existir, carrega-o
    if (file_exists($file)) {
        require $file;
    }
});

// Carrega as configurações
$config = [
    'database' => require __DIR__ . '/../config/database.php',
    'app' => require __DIR__ . '/../config/app.php',
];

// Define constantes globais
define('APP_ROOT', dirname(__DIR__));
define('APP_CONFIG', $config);

// Configura o tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
ini_set('display_startup_errors', $config['app']['debug'] ? '1' : '0');

// Configura o timezone
date_default_timezone_set($config['app']['timezone']);

// Configura a sessão
session_set_cookie_params(
    $config['app']['session']['lifetime'],
    $config['app']['session']['path'],
    $config['app']['session']['domain'],
    $config['app']['session']['secure'],
    $config['app']['session']['http_only']
);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configura o tratamento de exceções
set_exception_handler(function ($exception) {
    if (APP_CONFIG['app']['debug']) {
        echo '<h1>Error</h1>';
        echo '<p>' . $exception->getMessage() . '</p>';
        echo '<pre>' . $exception->getTraceAsString() . '</pre>';
    } else {
        error_log($exception->getMessage());
        echo '<h1>Error</h1>';
        echo '<p>An error occurred. Please try again later.</p>';
    }
});

// Configura o tratamento de erros fatais
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (APP_CONFIG['app']['debug']) {
            echo '<h1>Fatal Error</h1>';
            echo '<p>' . $error['message'] . '</p>';
            echo '<pre>' . $error['file'] . ':' . $error['line'] . '</pre>';
        } else {
            error_log($error['message']);
            echo '<h1>Error</h1>';
            echo '<p>An error occurred. Please try again later.</p>';
        }
    }
});

// Configura o locale
setlocale(LC_ALL, $config['app']['locale']);

// Configura o logging
if ($config['logging']['enabled']) {
    if (!is_dir($config['logging']['path'])) {
        mkdir($config['logging']['path'], 0777, true);
    }
    
    ini_set('log_errors', 1);
    ini_set('error_log', $config['logging']['path'] . '/php_errors.log');
}

// Configura o upload
if (!is_dir($config['upload']['path'])) {
    mkdir($config['upload']['path'], 0777, true);
}

// Retorna as configurações
return [
    'config' => $config,
    'db_config' => $config['database']
]; 