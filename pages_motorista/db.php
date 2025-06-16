<?php
// Configurações do banco de dados
define('DB_SERVER', 'localhost:3307');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'sistema_frotas');

// Função para obter conexão com o banco de dados
function getConnection() {
    try {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8",
            DB_SERVER,
            DB_NAME
        );
        
        $conn = new PDO(
            $dsn,
            DB_USERNAME,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        return $conn;
    } catch (PDOException $e) {
        error_log('Erro de conexão: ' . $e->getMessage());
        die('Erro ao conectar ao banco de dados');
    }
} 