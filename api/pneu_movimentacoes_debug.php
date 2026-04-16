<?php
/**
 * Debug e listagem de pneu_movimentacoes.
 * action=status  → tabela existe?, total de registros (por empresa)
 * action=by_pneu → movimentações de um pneu (para detalhes e debug)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro de conexão', 'detail' => $e->getMessage()]);
    exit;
}

// Verifica se a tabela existe
$table_exists = false;
try {
    $r = $pdo->query("SHOW TABLES LIKE 'pneu_movimentacoes'");
    $table_exists = $r && $r->rowCount() > 0;
} catch (PDOException $e) {
    // ignore
}

if (!$table_exists) {
    echo json_encode([
        'success'       => true,
        'table_exists'  => false,
        'message'       => 'Tabela pneu_movimentacoes não existe. Execute sql/create_pneu_movimentacoes.sql',
        'total'         => 0,
        'movimentacoes' => [],
    ]);
    exit;
}

if ($action === 'status') {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM pneu_movimentacoes WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($row['total'] ?? 0);
    } catch (PDOException $e) {
        $total = 0;
    }
    echo json_encode([
        'success'      => true,
        'table_exists' => true,
        'empresa_id'   => $empresa_id,
        'total'        => $total,
    ]);
    exit;
}

if ($action === 'by_pneu') {
    $pneu_id = isset($_GET['pneu_id']) ? (int) $_GET['pneu_id'] : 0;
    if ($pneu_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.id, m.tipo, m.veiculo_id, m.eixo_id, m.posicao_id,
                m.km_odometro, m.km_rodado, m.sulco_mm, m.custo, m.observacoes,
                m.data_movimentacao, m.created_at,
                v.placa AS veiculo_placa
            FROM pneu_movimentacoes m
            LEFT JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
            WHERE m.pneu_id = ? AND m.empresa_id = ?
            ORDER BY m.data_movimentacao DESC, m.id DESC
        ");
        $stmt->execute([$pneu_id, $empresa_id]);
        $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $movimentacoes = [];
    }
    echo json_encode([
        'success'        => true,
        'table_exists'   => true,
        'pneu_id'        => $pneu_id,
        'empresa_id'     => $empresa_id,
        'movimentacoes'  => $movimentacoes,
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'action inválida. Use action=status ou action=by_pneu']);
