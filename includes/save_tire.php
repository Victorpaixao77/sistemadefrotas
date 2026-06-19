<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/api_json.php';
require_once __DIR__ . '/pneus_helpers.php';
require_once __DIR__ . '/pneu_movimentacoes_helper.php';

header('Content-Type: application/json; charset=utf-8');

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_authentication();
pneus_require_page_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_method_not_allowed('Use POST para salvar pneu.');
}
api_require_csrf_json();

try {
    $pdo = getConnection();
    $empresa_id = pneus_empresa_id();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Payload inválido');
    }

    $id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
    $numero_serie = trim((string) ($data['numero_serie'] ?? ''));
    if ($numero_serie === '') {
        throw new InvalidArgumentException('Número de série é obrigatório');
    }

    $marca = trim((string) ($data['marca'] ?? ''));
    $modelo = trim((string) ($data['modelo'] ?? ''));
    $dot = $data['dot'] ?? null;
    $km_instalacao = $data['km_instalacao'] ?? 0;
    $data_instalacao = $data['data_instalacao'] ?: null;
    $vida_util_km = $data['vida_util_km'] ?? 0;
    $status_id = (int) ($data['status_id'] ?? 0);
    $observacoes = $data['observacoes'] ?: null;
    $medida = $data['medida'] ?? null;
    $sulco_inicial = $data['sulco_inicial'] ?? null;
    $numero_recapagens = (int) ($data['numero_recapagens'] ?? 0);
    $data_ultima_recapagem = $data['data_ultima_recapagem'] ?: null;
    $lote = $data['lote'] ?: null;
    $data_entrada = $data['data_entrada'] ?: null;
    $valor_compra = isset($data['valor_compra']) ? (float) $data['valor_compra'] : null;
    $nota_fiscal = isset($data['nota_fiscal']) ? trim((string) $data['nota_fiscal']) : null;
    $garantia_ate = $data['garantia_ate'] ?: null;
    $carcaca_codigo = isset($data['carcaca_codigo']) ? trim((string) $data['carcaca_codigo']) : null;
    if ($carcaca_codigo === '') {
        $carcaca_codigo = null;
    }
    $tipo_rodado_ideal = $data['tipo_rodado_ideal'] ?? 'qualquer';
    $tipos_rodado = ['direcional', 'tracao', 'livre', 'implemento', 'qualquer'];
    if (!in_array($tipo_rodado_ideal, $tipos_rodado, true)) {
        $tipo_rodado_ideal = 'qualquer';
    }
    $filial_id = isset($data['filial_id']) && $data['filial_id'] !== '' ? (int) $data['filial_id'] : null;

    if ($id && !pneus_validar_pneu_empresa($pdo, $id, $empresa_id)) {
        throw new InvalidArgumentException('Pneu não encontrado');
    }

    $pdo->beginTransaction();

    if ($id) {
        $sets = [
            'numero_serie = ?', 'marca = ?', 'modelo = ?', 'dot = ?', 'km_instalacao = ?', 'data_instalacao = ?',
            'vida_util_km = ?', 'status_id = ?', 'observacoes = ?', 'medida = ?', 'sulco_inicial = ?',
            'numero_recapagens = ?', 'data_ultima_recapagem = ?', 'lote = ?', 'data_entrada = ?',
            'updated_at = CURRENT_TIMESTAMP',
        ];
        $params = [
            $numero_serie, $marca, $modelo, $dot, $km_instalacao, $data_instalacao,
            $vida_util_km, $status_id, $observacoes, $medida, $sulco_inicial,
            $numero_recapagens, $data_ultima_recapagem, $lote, $data_entrada,
        ];
        if (pneus_column_exists($pdo, 'pneus', 'valor_compra')) {
            $sets[] = 'valor_compra = ?';
            $params[] = $valor_compra;
        }
        if (pneus_column_exists($pdo, 'pneus', 'nota_fiscal')) {
            $sets[] = 'nota_fiscal = ?';
            $params[] = $nota_fiscal ?: null;
        }
        if (pneus_column_exists($pdo, 'pneus', 'garantia_ate')) {
            $sets[] = 'garantia_ate = ?';
            $params[] = $garantia_ate;
        }
        if (pneus_column_exists($pdo, 'pneus', 'carcaca_codigo')) {
            $sets[] = 'carcaca_codigo = ?';
            $params[] = $carcaca_codigo;
        }
        if (pneus_column_exists($pdo, 'pneus', 'tipo_rodado_ideal')) {
            $sets[] = 'tipo_rodado_ideal = ?';
            $params[] = $tipo_rodado_ideal;
        }
        if (pneus_column_exists($pdo, 'pneus', 'filial_id')) {
            $sets[] = 'filial_id = ?';
            $params[] = $filial_id;
        }
        $params[] = $id;
        $params[] = $empresa_id;
        $sql = 'UPDATE pneus SET ' . implode(', ', $sets) . ' WHERE id = ? AND empresa_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        pneus_ensure_estoque_row($pdo, $id, $status_id, pneus_esta_alocado($pdo, $id) ? 0 : 1);
        $stE = $pdo->prepare('UPDATE estoque_pneus SET status_id = ?, updated_at = NOW() WHERE pneu_id = ?');
        $stE->execute([$status_id, $id]);
    } else {
        $cols = [
            'empresa_id', 'numero_serie', 'marca', 'modelo', 'dot', 'km_instalacao', 'data_instalacao',
            'vida_util_km', 'status_id', 'observacoes', 'medida', 'sulco_inicial', 'numero_recapagens',
            'data_ultima_recapagem', 'lote', 'data_entrada',
        ];
        $placeholders = array_fill(0, count($cols), '?');
        $insertParams = [
            $empresa_id, $numero_serie, $marca, $modelo, $dot, $km_instalacao, $data_instalacao,
            $vida_util_km, $status_id, $observacoes, $medida, $sulco_inicial,
            $numero_recapagens, $data_ultima_recapagem, $lote, $data_entrada,
        ];
        if (pneus_column_exists($pdo, 'pneus', 'valor_compra')) {
            $cols[] = 'valor_compra';
            $placeholders[] = '?';
            $insertParams[] = $valor_compra;
        }
        if (pneus_column_exists($pdo, 'pneus', 'nota_fiscal')) {
            $cols[] = 'nota_fiscal';
            $placeholders[] = '?';
            $insertParams[] = $nota_fiscal ?: null;
        }
        if (pneus_column_exists($pdo, 'pneus', 'garantia_ate')) {
            $cols[] = 'garantia_ate';
            $placeholders[] = '?';
            $insertParams[] = $garantia_ate;
        }
        if (pneus_column_exists($pdo, 'pneus', 'carcaca_codigo')) {
            $cols[] = 'carcaca_codigo';
            $placeholders[] = '?';
            $insertParams[] = $carcaca_codigo;
        }
        if (pneus_column_exists($pdo, 'pneus', 'tipo_rodado_ideal')) {
            $cols[] = 'tipo_rodado_ideal';
            $placeholders[] = '?';
            $insertParams[] = $tipo_rodado_ideal;
        }
        if (pneus_column_exists($pdo, 'pneus', 'filial_id')) {
            $cols[] = 'filial_id';
            $placeholders[] = '?';
            $insertParams[] = $filial_id;
        }
        $sql = 'INSERT INTO pneus (' . implode(', ', $cols) . ', created_at, updated_at) VALUES ('
            . implode(', ', $placeholders) . ', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertParams);
        $id = (int) $pdo->lastInsertId();
        pneus_ensure_estoque_row($pdo, $id, $status_id, 1);
        pneus_ensure_qr_token($pdo, $id, $empresa_id);
        $custo_entrada = $valor_compra ?? 0.0;
        pneu_movimentacao_inserir($pdo, [
            'empresa_id' => $empresa_id,
            'pneu_id' => $id,
            'tipo' => 'entrada_estoque',
            'custo' => $custo_entrada,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('save_tire.php: ' . $e->getMessage());
    $code = $e instanceof InvalidArgumentException ? 400 : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Erro ao salvar pneu',
    ], JSON_UNESCAPED_UNICODE);
}
