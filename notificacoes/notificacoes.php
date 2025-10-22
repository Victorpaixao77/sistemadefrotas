<?php
// Incluir arquivos na ordem correta usando caminhos absolutos
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// NOTA: ia_regras.php foi removido porque executa análises pesadas automaticamente
// O arquivo de notificações apenas busca e retorna dados, não precisa executar análises
// require_once dirname(__DIR__) . '/IA/ia_regras.php';

// Prevenir qualquer saída antes do JSON
ob_start();

// Configurar sessão
configure_session();

// Verificar se a sessão já está ativa antes de iniciá-la
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir header antes de qualquer saída
header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

$todas = isset($_GET['todas']) && $_GET['todas'] == '1';

try {
    // Verificar se a conexão com o banco está funcionando
    if (!$conn) {
        throw new Exception("Falha na conexão com o banco de dados");
    }
    
    if ($todas) {
        // Para "Ver todas": mostrar apenas notificações dos últimos 30 dias
        $stmt = $conn->prepare("SELECT * FROM notificacoes 
                               WHERE empresa_id = ? 
                               AND data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                               ORDER BY data_criacao DESC 
                               LIMIT 100");
        $stmt->execute([$empresa_id]);
    } else {
        // Para notificações não lidas: mostrar apenas dos últimos 7 dias
        $stmt = $conn->prepare("SELECT * FROM notificacoes 
                               WHERE empresa_id = ? 
                               AND lida = 0 
                               AND data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                               ORDER BY data_criacao DESC 
                               LIMIT 200");
        $stmt->execute([$empresa_id]);
    }
    
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obter total real de notificações não lidas dos últimos 7 dias
    $stmt_total = $conn->prepare("SELECT COUNT(*) as total FROM notificacoes 
                                 WHERE empresa_id = ? 
                                 AND lida = 0 
                                 AND data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt_total->execute([$empresa_id]);
    $total_real = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Filtrar notificações duplicadas baseado em tipo, título e data
    $notificacoes_filtradas = [];
    $chaves_vistas = [];
    
    foreach ($notificacoes as $notif) {
        // Criar chave única baseada em tipo, título e data (dia)
        $data_dia = date('Y-m-d', strtotime($notif['data_criacao']));
        $chave = $notif['tipo'] . '_' . $notif['titulo'] . '_' . $data_dia;
        
        if (!in_array($chave, $chaves_vistas)) {
            $chaves_vistas[] = $chave;
            $notificacoes_filtradas[] = $notif;
        }
    }
    
    // Limpar buffer antes de enviar resposta
    ob_clean();
    
    echo json_encode([
        'success' => true, 
        'notificacoes' => $notificacoes_filtradas,
        'total_original' => count($notificacoes),
        'total_filtrado' => count($notificacoes_filtradas),
        'total_real_nao_lidas' => (int)$total_real
    ]);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erro ao buscar notificações',
        'details' => $e->getMessage()
    ]);
} 