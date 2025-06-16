<?php
require_once 'includes/config.php';

try {
    $conn = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME,
        DB_USERNAME,
        DB_PASSWORD,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
    );
    
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexão bem sucedida com o banco de dados!<br>";
    echo "Servidor: " . DB_SERVER . "<br>";
    echo "Banco de dados: " . DB_NAME . "<br>";
    echo "Usuário: " . DB_USERNAME . "<br>";
    
    // Test if the database exists and has tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<br>📋 Tabelas encontradas:<br>";
    if (count($tables) > 0) {
        foreach ($tables as $table) {
            echo "- " . $table . "<br>";
        }
    } else {
        echo "Nenhuma tabela encontrada no banco de dados.<br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
    echo "<br>Verifique se:<br>";
    echo "1. O servidor MySQL está rodando<br>";
    echo "2. As credenciais em includes/config.php estão corretas<br>";
    echo "3. O banco de dados '" . DB_NAME . "' existe<br>";
    
    // Tentar conectar sem selecionar o banco para ver se é problema de credenciais ou do banco
    try {
        new PDO(
            "mysql:host=" . DB_SERVER,
            DB_USERNAME,
            DB_PASSWORD
        );
        echo "<br>✅ Conexão com o servidor MySQL está OK, mas o banco '" . DB_NAME . "' pode não existir.<br>";
        echo "Execute o seguinte SQL para criar o banco:<br>";
        echo "<code>CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code>";
    } catch(PDOException $e2) {
        echo "<br>❌ Erro na conexão com o servidor MySQL: " . $e2->getMessage() . "<br>";
        echo "Verifique se o usuário e senha estão corretos.";
    }
} 