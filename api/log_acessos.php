<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

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
    
    if ($action === 'listar') {
        // Parâmetros de paginação e filtros
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        // Filtros
        $filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
        $filtro_status = isset($_GET['status']) ? $_GET['status'] : null;
        $filtro_usuario = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : null;
        $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
        $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
        
        // Construir query
        $where = ["l.empresa_id = :empresa_id"];
        $params = [':empresa_id' => $empresa_id];
        
        if ($filtro_tipo) {
            $where[] = "l.tipo_acesso = :tipo";
            $params[':tipo'] = $filtro_tipo;
        }
        
        if ($filtro_status) {
            $where[] = "l.status = :status";
            $params[':status'] = $filtro_status;
        }
        
        if ($filtro_usuario) {
            $where[] = "l.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filtro_usuario;
        }
        
        if ($data_inicio) {
            $where[] = "DATE(l.data_acesso) >= :data_inicio";
            $params[':data_inicio'] = $data_inicio;
        }
        
        if ($data_fim) {
            $where[] = "DATE(l.data_acesso) <= :data_fim";
            $params[':data_fim'] = $data_fim;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Contar total de registros
        $count_sql = "SELECT COUNT(*) as total 
                      FROM log_acessos l 
                      WHERE $where_clause";
        $count_stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Buscar logs
        $sql = "SELECT l.*, 
                       u.nome as usuario_nome, 
                       u.email as usuario_email,
                       DATE_FORMAT(l.data_acesso, '%d/%m/%Y %H:%i:%s') as data_formatada
                FROM log_acessos l
                LEFT JOIN usuarios u ON l.usuario_id = u.id
                WHERE $where_clause
                ORDER BY l.data_acesso DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        exit;
    }
    
    if ($action === 'estatisticas') {
        // Estatísticas gerais
        $sql = "SELECT 
                    COUNT(*) as total_acessos,
                    COUNT(DISTINCT usuario_id) as total_usuarios,
                    SUM(CASE WHEN tipo_acesso = 'login' AND status = 'sucesso' THEN 1 ELSE 0 END) as logins_sucesso,
                    SUM(CASE WHEN tipo_acesso = 'tentativa_login_falha' THEN 1 ELSE 0 END) as tentativas_falha,
                    SUM(CASE WHEN tipo_acesso = 'logout' THEN 1 ELSE 0 END) as logouts,
                    SUM(CASE WHEN DATE(data_acesso) = CURDATE() THEN 1 ELSE 0 END) as acessos_hoje,
                    SUM(CASE WHEN DATE(data_acesso) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as acessos_ontem
                FROM log_acessos
                WHERE empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Acessos por dia (últimos 7 dias)
        $sql_dias = "SELECT 
                        DATE(data_acesso) as data,
                        COUNT(*) as total,
                        SUM(CASE WHEN tipo_acesso = 'login' AND status = 'sucesso' THEN 1 ELSE 0 END) as logins
                     FROM log_acessos
                     WHERE empresa_id = :empresa_id 
                       AND data_acesso >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY DATE(data_acesso)
                     ORDER BY data DESC";
        
        $stmt_dias = $conn->prepare($sql_dias);
        $stmt_dias->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_dias->execute();
        $acessos_por_dia = $stmt_dias->fetchAll(PDO::FETCH_ASSOC);
        
        // Top usuários
        $sql_usuarios = "SELECT 
                            u.nome,
                            u.email,
                            COUNT(*) as total_acessos,
                            MAX(l.data_acesso) as ultimo_acesso
                         FROM log_acessos l
                         JOIN usuarios u ON l.usuario_id = u.id
                         WHERE l.empresa_id = :empresa_id
                           AND l.tipo_acesso = 'login'
                           AND l.status = 'sucesso'
                         GROUP BY u.id, u.nome, u.email
                         ORDER BY total_acessos DESC
                         LIMIT 10";
        
        $stmt_usuarios = $conn->prepare($sql_usuarios);
        $stmt_usuarios->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_usuarios->execute();
        $top_usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'estatisticas' => $estatisticas,
            'acessos_por_dia' => $acessos_por_dia,
            'top_usuarios' => $top_usuarios
        ]);
        exit;
    }
    
    throw new Exception('Ação inválida');
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
