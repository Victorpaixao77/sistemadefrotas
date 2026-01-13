<?php
// Common functions for the application

/**
 * Format a number as currency (Brazilian Real)
 * 
 * @param float $value The value to format
 * @return string Formatted value
 */
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Format a date in Brazilian format (dd/mm/yyyy)
 * 
 * @param string $date Date in MySQL format (yyyy-mm-dd)
 * @return string Formatted date
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Format a date and time in Brazilian format (dd/mm/yyyy HH:MM)
 * 
 * @param string $datetime Date and time in MySQL format
 * @return string Formatted date and time
 */
function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Converts a Brazilian date format (dd/mm/yyyy) to MySQL format (yyyy-mm-dd)
 * 
 * @param string $date Date in Brazilian format
 * @return string Date in MySQL format
 */
function dateToMySql($date) {
    if (empty($date)) return null;
    $date = str_replace('/', '-', $date);
    return date('Y-m-d', strtotime($date));
}

/**
 * Generate a random string
 * 
 * @param int $length Length of the string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Check if user is logged in
 * 
 * @return boolean True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && 
           isset($_SESSION['empresa_id']) && !empty($_SESSION['empresa_id']);
}

/**
 * Redirect to a URL
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message in session
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display flash message if exists
 * 
 * @return string HTML for flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        unset($_SESSION['flash_message']);
        
        return '<div class="alert alert-' . $type . '">' . $message . '</div>';
    }
    
    return '';
}

/**
 * Calculate the percentage of two values
 * 
 * @param float $value Current value
 * @param float $total Total value
 * @return float Percentage
 */
function calculatePercentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100, 2);
}

/**
 * Truncate text to a specific length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $append Text to append if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    
    return $text . $append;
}

/**
 * Check if a value exists in an array of objects
 * 
 * @param array $array Array of objects
 * @param string $key Key to check
 * @param mixed $value Value to find
 * @return boolean True if value exists
 */
function arrayObjectValueExists($array, $key, $value) {
    foreach ($array as $object) {
        if (isset($object->$key) && $object->$key == $value) {
            return true;
        }
    }
    
    return false;
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } else if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } else if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get current page URL
 * 
 * @return string Current URL
 */
function getCurrentUrl() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

/**
 * Get client IP address
 * 
 * @return string Client IP
 */
function getClientIp() {
    $ip = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return $ip;
}

// Database connection
require_once 'db_connect.php';

// Function to get company data
function getCompanyData() {
    try {
        $conn = getConnection();
        $sql = "SELECT * FROM empresa_clientes WHERE id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    } catch(PDOException $e) {
        error_log("Error fetching company data: " . $e->getMessage());
        return null;
    }
}

// Function to update company data
function updateCompanyData($data) {
    try {
        $conn = getConnection();
        
        // Update existing company
        $sql = "UPDATE empresa_clientes SET 
                razao_social = :razao_social,
                nome_fantasia = :nome_fantasia,
                cnpj = :cnpj,
                inscricao_estadual = :inscricao_estadual,
                telefone = :telefone,
                email = :email,
                endereco = :endereco,
                cidade = :cidade,
                estado = :estado,
                cep = :cep,
                responsavel = :responsavel
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $_SESSION['empresa_id']);
        
        // Bind parameters
        $stmt->bindValue(':razao_social', $data['razao_social']);
        $stmt->bindValue(':nome_fantasia', $data['nome_fantasia']);
        $stmt->bindValue(':cnpj', $data['cnpj']);
        $stmt->bindValue(':inscricao_estadual', $data['inscricao_estadual']);
        $stmt->bindValue(':telefone', $data['telefone']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':endereco', $data['endereco']);
        $stmt->bindValue(':cidade', $data['cidade']);
        $stmt->bindValue(':estado', $data['estado']);
        $stmt->bindValue(':cep', $data['cep']);
        $stmt->bindValue(':responsavel', $data['responsavel']);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Error updating company data: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra um log de acesso no sistema
 * 
 * @param int $usuario_id ID do usuário
 * @param int $empresa_id ID da empresa
 * @param string $tipo Tipo de acesso (login, logout, tentativa_login_falha)
 * @param string $status Status do acesso (sucesso, falha)
 * @param string $descricao Descrição adicional (opcional)
 * @return boolean True se registrado com sucesso
 */
function registrarLogAcesso($usuario_id, $empresa_id, $tipo = 'login', $status = 'sucesso', $descricao = null) {
    try {
        $conn = getConnection();
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS log_acessos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            empresa_id INT NOT NULL,
            tipo_acesso ENUM('login', 'logout', 'tentativa_login_falha', 'sessao_expirada') DEFAULT 'login',
            status ENUM('sucesso', 'falha') DEFAULT 'sucesso',
            ip_address VARCHAR(45),
            user_agent TEXT,
            descricao TEXT,
            data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario_id),
            INDEX idx_empresa (empresa_id),
            INDEX idx_tipo (tipo_acesso),
            INDEX idx_data (data_acesso)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        // Obter IP do cliente
        $ip_address = getClientIp();
        
        // Obter User Agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
        
        // Inserir log
        $sql = "INSERT INTO log_acessos (usuario_id, empresa_id, tipo_acesso, status, ip_address, user_agent, descricao) 
                VALUES (:usuario_id, :empresa_id, :tipo_acesso, :status, :ip_address, :user_agent, :descricao)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo_acesso', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $ip_address, PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $user_agent, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Erro ao registrar log de acesso: " . $e->getMessage());
        return false;
    }
}
