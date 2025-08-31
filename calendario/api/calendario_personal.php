<?php
// Desabilitar TODOS os warnings e erros para APIs
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Verificar se a sessão já está ativa
if (session_status() === PHP_SESSION_NONE) {
    // Configurar sessão igual ao sistema principal
    session_name('sistema_frotas_session');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/sistema-frotas',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Iniciar sessão
    session_start();
}

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Debug da sessão (apenas no log, não na tela)
error_log("=== DEBUG API PERSONAL ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Data: " . print_r($_SESSION, true));

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    error_log("❌ Sessão inválida");
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
error_log("✅ Empresa ID: $empresa_id");

try {
    $conn = getConnection();
    error_log("✅ Conexão com banco OK");
    
    // Buscar eventos personalizados da empresa
    $sql = "SELECT 
                ce.id,
                ce.titulo as title,
                cc.nome as category,
                ce.data_inicio as start,
                ce.data_fim as end,
                ce.descricao as description,
                ce.cor as backgroundColor,
                ce.cor as borderColor,
                COALESCE(cc.nome, 'Personalizado') as extendedProps_category,
                'manual' as extendedProps_source
            FROM calendario_eventos ce
            LEFT JOIN categorias_calendario cc ON ce.categoria_id = cc.id
            WHERE ce.empresa_id = :empresa_id
            ORDER BY ce.data_inicio DESC";
    
    error_log("SQL: $sql");
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Eventos encontrados: " . count($eventos));
    
    // Formatar dados para o FullCalendar
    $formatted_events = [];
    foreach ($eventos as $evento) {
        $formatted_event = [
            'id' => $evento['id'],
            'title' => $evento['title'],
            'start' => $evento['start'],
            'end' => $evento['end'],
            'backgroundColor' => $evento['backgroundColor'],
            'borderColor' => $evento['borderColor'],
            'extendedProps' => [
                'category' => $evento['extendedProps_category'],
                'source' => $evento['extendedProps_source'],
                'description' => $evento['description']
            ]
        ];
        
        // Debug: mostrar cada evento formatado
        error_log("=== EVENTO FORMATADO ===");
        error_log("ID: " . $evento['id']);
        error_log("Title: " . $evento['title']);
        error_log("Category: " . $evento['extendedProps_category']);
        error_log("ExtendedProps: " . json_encode($formatted_event['extendedProps']));
        error_log("Evento completo: " . json_encode($formatted_event));
        
        $formatted_events[] = $formatted_event;
    }
    
    error_log("Eventos formatados: " . count($formatted_events));
    error_log("JSON final: " . json_encode($formatted_events));
    
    // Garantir que nenhum output foi feito antes
    if (ob_get_length()) ob_clean();
    
    // Definir header JSON
    header('Content-Type: application/json');
    
    // Retornar JSON limpo
    echo json_encode($formatted_events);
    
} catch (Exception $e) {
    error_log("❌ ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}

error_log("=== FINALIZANDO API PERSONAL ===");
?>
