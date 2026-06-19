<?php
/**
 * Ações POST do módulo profissional de pneus.
 * POST ?action=registrar_sulco|registrar_recapagem|sync_km|gerar_qr|sync_km_frota
 */
require_once __DIR__ . '/../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneus_repository.php';
require_once __DIR__ . '/../includes/pneu_movimentacoes_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_method_not_allowed('Use POST.');
}

api_require_csrf_json();

$conn = getConnection();
$empresa_id = pneus_empresa_id();
$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

try {
    switch ($action) {
        case 'registrar_sulco':
            $result = pneus_registrar_medicao_sulco($conn, $empresa_id, $data);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'sync_km':
            $pneu_id = (int) ($data['pneu_id'] ?? 0);
            if ($pneu_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(pneus_sync_km_pneu($conn, $pneu_id, $empresa_id), JSON_UNESCAPED_UNICODE);
            break;

        case 'sync_km_frota':
            $st = $conn->prepare('SELECT id FROM pneus WHERE empresa_id = ?');
            $st->execute([$empresa_id]);
            $ok = 0;
            $erros = 0;
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $r = pneus_sync_km_pneu($conn, (int) $pid, $empresa_id);
                if (!empty($r['success'])) {
                    $ok++;
                } else {
                    $erros++;
                }
            }
            echo json_encode(['success' => true, 'sincronizados' => $ok, 'ignorados_ou_erro' => $erros], JSON_UNESCAPED_UNICODE);
            break;

        case 'gerar_qr':
            $pneu_id = (int) ($data['pneu_id'] ?? 0);
            if ($pneu_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $token = pneus_ensure_qr_token($conn, $pneu_id, $empresa_id);
            if (!$token) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Não foi possível gerar QR'], JSON_UNESCAPED_UNICODE);
                break;
            }
            require_once __DIR__ . '/../includes/sf_paths.php';
            $url = sf_app_url('pages/pneu_ficha.php?t=' . urlencode($token));
            echo json_encode(['success' => true, 'qr_token' => $token, 'url' => $url], JSON_UNESCAPED_UNICODE);
            break;

        case 'registrar_recapagem':
            $pneu_id = (int) ($data['pneu_id'] ?? 0);
            $custo = (float) ($data['custo'] ?? 0);
            $data_manut = trim((string) ($data['data_manutencao'] ?? date('Y-m-d')));
            if ($pneu_id <= 0 || $custo <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id e custo obrigatórios'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $tipo_id = (int) ($data['tipo_manutencao_id'] ?? 0);
            if ($tipo_id <= 0) {
                $st = $conn->prepare("SELECT id FROM tipo_manutencao_pneus WHERE LOWER(nome) LIKE '%recap%' LIMIT 1");
                $st->execute();
                $tipo_id = (int) ($st->fetchColumn() ?: 0);
            }
            if ($tipo_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cadastre um tipo de manutenção "Recapagem"'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $veiculo_id = isset($data['veiculo_id']) ? (int) $data['veiculo_id'] : pneus_get_veiculo_alocado($conn, $pneu_id, $empresa_id);
            $km = isset($data['km_veiculo']) ? (int) $data['km_veiculo'] : null;
            if ($km === null && $veiculo_id) {
                $km = pneus_get_veiculo_km($conn, $veiculo_id, $empresa_id) ?? 0;
            }
            $fornecedor_id = !empty($data['fornecedor_id']) ? (int) $data['fornecedor_id'] : null;
            $garantia = !empty($data['garantia_ate']) ? $data['garantia_ate'] : null;
            $sulco_pos = isset($data['sulco_pos_recapagem']) ? (float) $data['sulco_pos_recapagem'] : null;
            $obs = trim((string) ($data['observacoes'] ?? ''));

            $conn->beginTransaction();

            $cols = ['empresa_id', 'pneu_id', 'veiculo_id', 'data_manutencao', 'km_veiculo', 'custo', 'observacoes', 'tipo_manutencao_id'];
            $vals = [':empresa_id', ':pneu_id', ':veiculo_id', ':data_manutencao', ':km_veiculo', ':custo', ':observacoes', ':tipo_manutencao_id'];
            $params = [
                ':empresa_id' => $empresa_id,
                ':pneu_id' => $pneu_id,
                ':veiculo_id' => $veiculo_id ?: null,
                ':data_manutencao' => $data_manut,
                ':km_veiculo' => $km ?: 0,
                ':custo' => $custo,
                ':observacoes' => $obs ?: null,
                ':tipo_manutencao_id' => $tipo_id,
            ];
            if (pneus_column_exists($conn, 'pneu_manutencao', 'fornecedor_id') && $fornecedor_id) {
                $cols[] = 'fornecedor_id';
                $vals[] = ':fornecedor_id';
                $params[':fornecedor_id'] = $fornecedor_id;
            }
            if (pneus_column_exists($conn, 'pneu_manutencao', 'garantia_ate') && $garantia) {
                $cols[] = 'garantia_ate';
                $vals[] = ':garantia_ate';
                $params[':garantia_ate'] = $garantia;
            }
            if (pneus_column_exists($conn, 'pneu_manutencao', 'sulco_pos_recapagem') && $sulco_pos) {
                $cols[] = 'sulco_pos_recapagem';
                $vals[] = ':sulco_pos_recapagem';
                $params[':sulco_pos_recapagem'] = $sulco_pos;
            }

            $sql = 'INSERT INTO pneu_manutencao (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $st = $conn->prepare($sql);
            $st->execute($params);

            $st = $conn->prepare(
                'UPDATE pneus SET numero_recapagens = COALESCE(numero_recapagens, 0) + 1,
                 data_ultima_recapagem = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?'
            );
            $st->execute([$data_manut, $pneu_id, $empresa_id]);

            if ($sulco_pos) {
                pneus_atualizar_sulco_registro($conn, $pneu_id, $sulco_pos);
            }

            pneu_movimentacao_inserir($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id' => $pneu_id,
                'tipo' => 'recapagem',
                'veiculo_id' => $veiculo_id,
                'km_odometro' => $km,
                'sulco_mm' => $sulco_pos,
                'custo' => $custo,
                'fornecedor_id' => $fornecedor_id,
                'observacoes' => $obs ?: 'Recapagem registrada',
            ]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Recapagem registrada'], JSON_UNESCAPED_UNICODE);
            break;

        case 'ia_pergunta':
            $pergunta = trim((string) ($data['pergunta'] ?? ''));
            if ($pergunta === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pergunta obrigatória'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $r = pneus_ia_responder($conn, $empresa_id, $pergunta);
            echo json_encode(['success' => true, 'data' => $r], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('api/pneus_acoes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar ação'], JSON_UNESCAPED_UNICODE);
}
