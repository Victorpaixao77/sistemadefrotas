<?php

// Configurações de banco de dados para PRODUÇÃO
// Copie este arquivo para database.php quando for para produção

return [
    'host' => 'seu-servidor-producao.com',  // IP ou domínio do servidor
    'port' => 3306,                         // Porta padrão MySQL
    'database' => 'sistema_frotas',         // Nome do banco de dados
    'username' => 'usuario_producao',       // Usuário específico para produção
    'password' => 'senha_forte_producao',   // Senha forte e segura
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ],
]; 