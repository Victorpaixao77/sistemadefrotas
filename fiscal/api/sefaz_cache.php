<?php
/**
 * 💾 Cache Status SEFAZ
 * 📋 Sistema de cache para manter status SEFAZ entre páginas
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar autenticação
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Função para obter cache do status SEFAZ
function getSefazCache() {
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        
        // Verificar se o cache ainda é válido (menos de 5 minutos)
        if ($cache_data && isset($cache_data['timestamp'])) {
            $cache_time = strtotime($cache_data['timestamp']);
            $current_time = time();
            
            if (($current_time - $cache_time) < 300) { // 5 minutos
                return $cache_data;
            }
        }
    }
    
    return null;
}

// Função para salvar cache do status SEFAZ
function saveSefazCache($data) {
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    
    $cache_data = [
        'status' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'empresa_id' => $_SESSION['empresa_id'] ?? 'unknown'
    ];
    
    file_put_contents($cache_file, json_encode($cache_data, JSON_PRETTY_PRINT));
    return true;
}

// Função para limpar cache
function clearSefazCache() {
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    
    if (file_exists($cache_file)) {
        unlink($cache_file);
        return true;
    }
    
    return false;
}

// Processar requisição
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

switch ($action) {
    case 'get':
        // Obter status do cache
        $cached_status = getSefazCache();
        
        if ($cached_status) {
            echo json_encode([
                'success' => true,
                'from_cache' => true,
                'data' => $cached_status['status'],
                'cache_time' => $cached_status['timestamp']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'from_cache' => false,
                'message' => 'Cache não encontrado ou expirado'
            ]);
        }
        break;
        
    case 'save':
        // Salvar status no cache
        $status_data = $_POST['status_data'] ?? null;
        
        if ($status_data) {
            $status_array = json_decode($status_data, true);
            
            if ($status_array) {
                saveSefazCache($status_array);
                echo json_encode([
                    'success' => true,
                    'message' => 'Status salvo no cache'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Dados de status inválidos'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Dados de status não fornecidos'
            ]);
        }
        break;
        
    case 'clear':
        // Limpar cache
        if (clearSefazCache()) {
            echo json_encode([
                'success' => true,
                'message' => 'Cache limpo com sucesso'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao limpar cache'
            ]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
}
?>
