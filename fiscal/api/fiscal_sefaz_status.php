<?php
/**
 * 🌐 API de Status SEFAZ
 * 📊 Retorna status de conectividade com a SEFAZ
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

// Incluir configurações
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

try {
    // Configurar sessão
    configure_session();
    session_start();
    
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $empresa_id = $input['empresa_id'] ?? 1; // Usar empresa_id padrão se não fornecido
    
    // Preferir cache já calculado pelo endpoint real `sefaz_status.php`
    $sefaz_status = [
        'status' => 'offline',
        'ultima_sincronizacao' => null,
        'mensagem' => 'Sem cache SEFAZ disponível'
    ];

    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    if (file_exists($cache_file)) {
        $cache = json_decode(file_get_contents($cache_file), true);
        $st = $cache['status'] ?? null;
        if ($st && isset($st['timestamp']) === false) {
            // Estrutura típica: cache['timestamp'] existe no topo
        }
        if ($st && isset($st['status_geral'])) {
            $sefaz_status['status'] = ($st['status_geral'] ?? 'offline') === 'online' ? 'online' : 'offline';
            $sefaz_status['ultima_sincronizacao'] = $cache['timestamp'] ?? date('Y-m-d H:i:s');
            $sefaz_status['mensagem'] = $st['status_texto'] ?? ($st['status_geral'] ?? 'Offline');
            // Se cache existe, não precisa chamar SOAP novamente.
        }
    }

    // Se ainda estiver offline sem cache, chama o endpoint real (com cache interno e refresh controlado)
    if ($sefaz_status['status'] === 'offline' && empty($sefaz_status['ultima_sincronizacao'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = dirname($_SERVER['SCRIPT_NAME']);
        $url = $scheme . '://' . $host . $dir . '/sefaz_status.php?action=status&force=true';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp !== false && empty($err)) {
            $data = json_decode($resp, true);
            if (!empty($data['success']) && !empty($data['status_geral'])) {
                $sefaz_status['status'] = ($data['status_geral'] ?? 'offline') === 'online' ? 'online' : 'offline';
                $sefaz_status['ultima_sincronizacao'] = $data['timestamp'] ?? date('Y-m-d H:i:s');
                $sefaz_status['mensagem'] = $data['status_texto'] ?? ($data['status_geral'] ?? 'Offline');
            }
        }
    }
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Status SEFAZ verificado com sucesso',
        'data' => $sefaz_status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
