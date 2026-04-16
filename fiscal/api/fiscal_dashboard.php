<?php
/**
 * 🏢 API do Dashboard Fiscal
 * 📊 Retorna KPIs e estatísticas do sistema fiscal
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
    
    $conn = getConnection();

    // SEFAZ: tentar ler cache local (evita fazer chamada SOAP a cada refresh do dashboard)
    $sefaz_status = ['status' => 'offline', 'ultima_sincronizacao' => null, 'mensagem' => 'Sem cache SEFAZ disponível'];
    $cache_file = __DIR__ . '/sefaz_status_cache.json';
    if (file_exists($cache_file)) {
        $cache = json_decode(file_get_contents($cache_file), true);
        // Estrutura esperada (sefaz_status.php): { "status": {...}, "timestamp": ... }
        $st = $cache['status'] ?? null;
        if ($st && isset($st['status_geral'])) {
            $sefaz_status['status'] = ($st['status_geral'] ?? 'offline') === 'online' ? 'online' : 'offline';
            $sefaz_status['mensagem'] = $st['status_texto'] ?? ($st['status_geral'] ?? 'Offline');
            $sefaz_status['ultima_sincronizacao'] = $cache['timestamp'] ?? date('Y-m-d H:i:s');
        }
    }

    // NF-e
    $stmtNfe = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('pendente', 'recebida', 'consultada_sefaz', 'recebida_manual') THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'autorizada' THEN 1 ELSE 0 END) as autorizadas,
            COALESCE(SUM(valor_total), 0) as valor_total
        FROM fiscal_nfe_clientes
        WHERE empresa_id = ?
    ");
    $stmtNfe->execute([$empresa_id]);
    $nfe = $stmtNfe->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'pendentes' => 0, 'autorizadas' => 0, 'valor_total' => 0];

    // CT-e
    $stmtCte = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('pendente', 'rascunho') THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
            SUM(CASE WHEN status IN ('em_transporte', 'em_viagem') THEN 1 ELSE 0 END) as em_transito
        FROM fiscal_cte
        WHERE empresa_id = ?
    ");
    $stmtCte->execute([$empresa_id]);
    $cte = $stmtCte->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'pendentes' => 0, 'autorizados' => 0, 'em_transito' => 0];

    // MDF-e
    $stmtMdfe = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('pendente', 'rascunho') THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
            SUM(CASE WHEN status IN ('em_viagem', 'em_transporte') THEN 1 ELSE 0 END) as em_transito,
            SUM(CASE WHEN status = 'encerrado' THEN 1 ELSE 0 END) as encerrados
        FROM fiscal_mdfe
        WHERE empresa_id = ?
    ");
    $stmtMdfe->execute([$empresa_id]);
    $mdfe = $stmtMdfe->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'pendentes' => 0, 'autorizados' => 0, 'em_transito' => 0, 'encerrados' => 0];

    // Eventos
    $stmtEv = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status = 'aceito' THEN 1 ELSE 0 END) as aceitos,
            SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
        FROM fiscal_eventos_fiscais
        WHERE empresa_id = ?
    ");
    $stmtEv->execute([$empresa_id]);
    $ev = $stmtEv->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'pendentes' => 0, 'aceitos' => 0, 'rejeitados' => 0];

    // Alertas
    $stmtAlert = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN nivel = 'critico' THEN 1 ELSE 0 END) as criticos,
            SUM(CASE WHEN nivel = 'alto' THEN 1 ELSE 0 END) as importantes,
            SUM(CASE WHEN nivel = 'baixo' THEN 1 ELSE 0 END) as informativos
        FROM fiscal_alertas
        WHERE empresa_id = ? AND status = 'ativo'
    ");
    $stmtAlert->execute([$empresa_id]);
    $alertas = $stmtAlert->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'criticos' => 0, 'importantes' => 0, 'informativos' => 0];

    $dashboard_data = [
        'nfe' => [
            'total' => (int)($nfe['total'] ?? 0),
            'pendentes' => (int)($nfe['pendentes'] ?? 0),
            'autorizadas' => (int)($nfe['autorizadas'] ?? 0),
            'valor_total' => (float)($nfe['valor_total'] ?? 0),
        ],
        'cte' => [
            'total' => (int)($cte['total'] ?? 0),
            'pendentes' => (int)($cte['pendentes'] ?? 0),
            'autorizados' => (int)($cte['autorizados'] ?? 0),
            'em_transito' => (int)($cte['em_transito'] ?? 0),
        ],
        'mdfe' => [
            'total' => (int)($mdfe['total'] ?? 0),
            'pendentes' => (int)($mdfe['pendentes'] ?? 0),
            'autorizados' => (int)($mdfe['autorizados'] ?? 0),
            'em_transito' => (int)($mdfe['em_transito'] ?? 0),
            'encerrados' => (int)($mdfe['encerrados'] ?? 0),
        ],
        'eventos' => [
            'total' => (int)($ev['total'] ?? 0),
            'pendentes' => (int)($ev['pendentes'] ?? 0),
            // Mantém compatibilidade com o frontend (processados)
            'processados' => (int)(($ev['aceitos'] ?? 0) + ($ev['rejeitados'] ?? 0)),
            'aceitos' => (int)($ev['aceitos'] ?? 0),
            'rejeitados' => (int)($ev['rejeitados'] ?? 0),
        ],
        'sefaz_status' => [
            'status' => $sefaz_status['status'] ?? 'offline',
            'ultima_sincronizacao' => $sefaz_status['ultima_sincronizacao'] ?? null,
            'mensagem' => $sefaz_status['mensagem'] ?? 'Offline',
        ],
        'alertas' => [
            'total' => (int)($alertas['total'] ?? 0),
            'criticos' => (int)($alertas['criticos'] ?? 0),
            'importantes' => (int)($alertas['importantes'] ?? 0),
            'informativos' => (int)($alertas['informativos'] ?? 0),
        ],
    ];
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Dashboard carregado com sucesso',
        'data' => $dashboard_data,
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
