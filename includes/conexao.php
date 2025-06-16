<?php
try {
    // Configurações de conexão com o banco de dados
    $host = 'localhost';
    $port = '3307';
    $dbname = 'sistema_frotas';
    $username = 'root';
    $password = '';

    // Criando a conexão usando PDO
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Se chegou até aqui, a conexão foi estabelecida com sucesso
    // echo "Conexão realizada com sucesso!";
    
} catch(PDOException $e) {
    // Em caso de erro, exibe a mensagem
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?> 