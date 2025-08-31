<?php
// Conexão com o banco de dados

// Inclui arquivo de configuração
require_once 'config.php';

/**
 * Cria uma nova conexão PDO com o banco de dados
 * @return PDO Objeto de conexão com o banco de dados
 */
function getConnection() {
    try {
        if (DEBUG_MODE) {
            error_log("Iniciando conexão com o banco de dados");
        }
        
        // Configura a string de conexão DSN
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8",
            DB_SERVER,
            DB_NAME
        );
        
        if (DEBUG_MODE) {
            error_log("DSN configurado: " . $dsn);
        }
        
        // Cria conexão PDO
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
        
        if (DEBUG_MODE) {
            error_log("Conexão com o banco de dados estabelecida com sucesso");
        }
        return $conn;
    } catch(PDOException $e) {
        error_log("Falha na conexão: " . $e->getMessage());
        if (DEBUG_MODE) {
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new PDOException("Erro de conexão: " . $e->getMessage());
        } else {
            throw new PDOException("Falha na conexão com o banco de dados. Tente novamente mais tarde.");
        }
    }
}

/**
 * Executa uma consulta SQL e retorna todos os resultados
 * 
 * @param PDO $conn Conexão com o banco de dados
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros para a consulta
 * @return array Array com os resultados
 */
function executeQuery($conn, $sql, $params = []) {
    try {
        if (DEBUG_MODE) {
            error_log("Executando consulta: " . $sql);
            error_log("Parâmetros: " . print_r($params, true));
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        
        if (DEBUG_MODE) {
            error_log("Resultados encontrados: " . count($result));
        }
        return $result;
    } catch(PDOException $e) {
        error_log("Erro na consulta: " . $e->getMessage());
        if (DEBUG_MODE) {
            error_log("Stack trace: " . $e->getTraceAsString());
        }
        throw $e;
    }
}

/**
 * Busca uma única linha do resultado
 * 
 * @param PDO $conn Conexão com o banco de dados
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros para a consulta
 * @return array|null Array com os dados ou null se não encontrado
 */
function fetchOne($conn, $sql, $params = []) {
    try {
        if (DEBUG_MODE) {
            error_log("Executando consulta para uma linha: " . $sql);
            error_log("Parâmetros: " . print_r($params, true));
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if (DEBUG_MODE) {
            error_log("Resultado encontrado: " . ($result ? "Sim" : "Não"));
        }
        return $result;
    } catch(PDOException $e) {
        error_log("Erro na consulta: " . $e->getMessage());
        if (DEBUG_MODE) {
            error_log("Stack trace: " . $e->getTraceAsString());
        }
        throw $e;
    }
}

/**
 * Executa uma consulta SQL que não retorna resultados (INSERT, UPDATE, DELETE)
 * 
 * @param PDO $conn Conexão com o banco de dados
 * @param string $sql Consulta SQL
 * @param array $params Parâmetros para a consulta
 * @return int Número de linhas afetadas
 */
function executeNonQuery($conn, $sql, $params = []) {
    try {
        if (DEBUG_MODE) {
            error_log("Executando consulta sem retorno: " . $sql);
            error_log("Parâmetros: " . print_r($params, true));
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $affected = $stmt->rowCount();
        
        if (DEBUG_MODE) {
            error_log("Linhas afetadas: " . $affected);
        }
        return $affected;
    } catch(PDOException $e) {
        error_log("Erro na execução: " . $e->getMessage());
        if (DEBUG_MODE) {
            error_log("Stack trace: " . $e->getTraceAsString());
        }
        throw $e;
    }
}

/**
 * Sanitiza e valida dados de entrada
 * 
 * @param string $data Dados a serem sanitizados
 * @return string Dados sanitizados
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
