<?php
/**
 * SISTEMA SEGURO - Configuração de Banco de Dados
 * 
 * Este arquivo gerencia a conexão com o banco de dados
 * do Sistema Seguro (Plano Premium)
 */

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_PORT', '3307'); // Porta do MySQL no XAMPP
define('DB_NAME', 'sistema_frotas'); // Mesmo banco do sistema de frotas
define('DB_USER', 'root');
define('DB_PASS', ''); // Senha do MySQL (vazio no XAMPP padrão)
define('DB_CHARSET', 'utf8mb4');

/**
 * Classe de conexão com o banco de dados
 */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Configurar charset explicitamente
            $this->conn->exec('SET NAMES utf8mb4');
            
        } catch(PDOException $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    /**
     * Retorna a instância única da conexão (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Retorna a conexão PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Previne clonagem da instância
     */
    private function __clone() {}
    
    /**
     * Previne deserialização da instância
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Função auxiliar para obter a conexão
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

?>

