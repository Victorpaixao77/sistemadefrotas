<?php
/**
 * API para Google Maps
 * Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session
configure_session();
session_start();

// Debug: verificar sessão (apenas em caso de erro)
// error_log("Google Maps API - Sessão debug:");
// error_log("usuario_id: " . ($_SESSION['usuario_id'] ?? 'NOT_SET'));
// error_log("empresa_id: " . ($_SESSION['empresa_id'] ?? 'NOT_SET'));
// error_log("session_id: " . session_id());

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    error_log("Google Maps API - Usuário não autenticado");
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_config':
            getGoogleMapsConfig();
            break;
            
        case 'save_config':
            saveGoogleMapsConfig();
            break;
            
        case 'test_api_key':
            testGoogleMapsApiKey();
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    error_log("Google Maps API - Erro geral: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'action' => $action,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}

function getGoogleMapsConfig() {
    $pdo = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    $sql = "SELECT google_maps_api_key FROM configuracoes WHERE empresa_id = :empresa_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'google_maps_api_key' => $config['google_maps_api_key'] ?? ''
        ]
    ]);
}

function saveGoogleMapsConfig() {
    try {
        $pdo = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $google_maps_api_key = $_POST['google_maps_api_key'] ?? '';
        
        // Validar se a chave não está vazia
        if (empty($google_maps_api_key)) {
            throw new Exception('Chave da API do Google Maps é obrigatória');
        }
        
        // Verificar se já existe configuração para esta empresa
        $sql = "SELECT id FROM configuracoes WHERE empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Atualizar configuração existente
            $sql = "UPDATE configuracoes SET google_maps_api_key = :api_key, updated_at = NOW() WHERE empresa_id = :empresa_id";
        } else {
            // Criar nova configuração
            $sql = "INSERT INTO configuracoes (empresa_id, google_maps_api_key, cor_menu, nome_personalizado, data_criacao, updated_at) 
                    VALUES (:empresa_id, :api_key, '#343a40', 'Empresa', NOW(), NOW())";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':api_key', $google_maps_api_key);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Configuração do Google Maps salva com sucesso!'
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Erro ao salvar configuração: ' . $errorInfo[2]);
        }
    } catch (PDOException $e) {
        error_log("Google Maps API - Erro PDO: " . $e->getMessage());
        throw new Exception('Erro de banco de dados: ' . $e->getMessage());
    } catch (Exception $e) {
        error_log("Google Maps API - Erro: " . $e->getMessage());
        throw $e;
    }
}

function testGoogleMapsApiKey() {
    $api_key = $_POST['api_key'] ?? '';
    
    if (empty($api_key)) {
        throw new Exception('Chave da API é obrigatória');
    }
    
    // Verificar formato básico da chave
    if (strpos($api_key, 'AIza') !== 0) {
        throw new Exception('Formato de chave inválido. Deve começar com "AIza"');
    }
    
    // Testar a chave fazendo uma requisição simples para a API do Google Maps
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=Brasil&key=" . urlencode($api_key);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Erro ao conectar com a API do Google Maps');
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error_message'])) {
        throw new Exception('Chave inválida: ' . $data['error_message']);
    }
    
    if (isset($data['status']) && $data['status'] === 'OK') {
        echo json_encode([
            'success' => true,
            'message' => 'Chave da API válida!'
        ]);
    } else {
        throw new Exception('Chave da API inválida ou sem permissões');
    }
}
?>
