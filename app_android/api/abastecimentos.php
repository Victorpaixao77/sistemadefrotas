<?php
/**
 * API Abastecimentos - App Android Motoristas
 * GET: lista abastecimentos do motorista
 * POST: registra abastecimento (pendente, fonte motorista)
 *       — JSON (Content-Type application/json) ou multipart/form-data com arquivo opcional "comprovante"
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(100, max(1, (int)($_GET['limite'] ?? 50)));
        $stmt = $conn->prepare('
            SELECT a.*, v.placa, v.modelo
            FROM abastecimentos a
            JOIN veiculos v ON a.veiculo_id = v.id
            WHERE a.empresa_id = :e AND a.motorista_id = :m
            ORDER BY a.data_abastecimento DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
        api_success(['abastecimentos' => $lista]);
    }

    if ($method === 'POST') {
        $d = [];
        $comprovante_path = null;
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (stripos($ct, 'multipart/form-data') !== false) {
            $d = $_POST;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $uploadRoot = dirname(__DIR__, 2);
                $uploadInc = $uploadRoot . '/includes/upload_comprovante.php';
                if (is_file($uploadInc)) {
                    require_once $uploadInc;
                    $uploadAbs = $uploadRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprovantes';
                    if (!is_dir($uploadAbs)) {
                        mkdir($uploadAbs, 0775, true);
                    }
                    try {
                        $comprovante_path = sf_save_comprovante_upload($_FILES['comprovante'], $uploadAbs, 'uploads/comprovantes', false);
                    } catch (Exception $ex) {
                        api_error($ex->getMessage(), 400);
                    }
                }
            }
        } else {
            $raw = file_get_contents('php://input');
            $d = json_decode($raw, true);
            if (!is_array($d)) {
                $d = $_POST;
            }
        }

        $veiculo_id = (int)($d['veiculo_id'] ?? 0);
        $rota_id = isset($d['rota_id']) && $d['rota_id'] !== '' ? (int)$d['rota_id'] : null;
        $data_abastecimento = $d['data_abastecimento'] ?? date('Y-m-d');
        $posto = $d['posto'] ?? '';
        $litros = (float)($d['litros'] ?? $d['quantidade'] ?? 0);
        $valor_litro = (float)($d['valor_litro'] ?? $d['preco_litro'] ?? 0);
        $valor_total = (float)($d['valor_total'] ?? 0);
        $km_atual = (float)($d['km_atual'] ?? 0);
        $tipo_combustivel = $d['tipo_combustivel'] ?? 'diesel';
        $forma_pagamento = $d['forma_pagamento'] ?? 'dinheiro';
        $observacoes = $d['observacoes'] ?? '';

        if (!$veiculo_id || $litros <= 0 || $valor_total <= 0 || $km_atual <= 0) {
            api_error('Preencha veiculo_id, litros, valor_total e km_atual.');
        }

        $stmt = $conn->prepare('SELECT id FROM veiculos WHERE id = :id AND empresa_id = :e');
        $stmt->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
        if (!$stmt->fetch()) {
            api_error('Veículo inválido.');
        }

        $hasComp = false;
        try {
            $chk = $conn->query("SHOW COLUMNS FROM abastecimentos LIKE 'comprovante'");
            $hasComp = $chk && $chk->rowCount() > 0;
        } catch (PDOException $e) {
            $hasComp = false;
        }

        if ($hasComp && $comprovante_path !== null) {
            $stmt = $conn->prepare('
                INSERT INTO abastecimentos (
                    empresa_id, veiculo_id, motorista_id, rota_id,
                    posto, data_abastecimento, litros, valor_litro, valor_total,
                    km_atual, tipo_combustivel, forma_pagamento, observacoes,
                    comprovante, status, fonte
                ) VALUES (
                    :e, :veiculo_id, :m, :rota_id,
                    :posto, :data_abastecimento, :litros, :valor_litro, :valor_total,
                    :km_atual, :tipo_combustivel, :forma_pagamento, :observacoes,
                    :comprovante, "pendente", "motorista"
                )
            ');
            $stmt->execute([
                ':e' => $empresa_id,
                ':veiculo_id' => $veiculo_id,
                ':m' => $motorista_id,
                ':rota_id' => $rota_id,
                ':posto' => $posto,
                ':data_abastecimento' => $data_abastecimento,
                ':litros' => $litros,
                ':valor_litro' => $valor_litro,
                ':valor_total' => $valor_total,
                ':km_atual' => $km_atual,
                ':tipo_combustivel' => $tipo_combustivel,
                ':forma_pagamento' => $forma_pagamento,
                ':observacoes' => $observacoes,
                ':comprovante' => $comprovante_path,
            ]);
        } else {
            $stmt = $conn->prepare('
                INSERT INTO abastecimentos (
                    empresa_id, veiculo_id, motorista_id, rota_id,
                    posto, data_abastecimento, litros, valor_litro, valor_total,
                    km_atual, tipo_combustivel, forma_pagamento, observacoes,
                    status, fonte
                ) VALUES (
                    :e, :veiculo_id, :m, :rota_id,
                    :posto, :data_abastecimento, :litros, :valor_litro, :valor_total,
                    :km_atual, :tipo_combustivel, :forma_pagamento, :observacoes,
                    "pendente", "motorista"
                )
            ');
            $stmt->execute([
                ':e' => $empresa_id,
                ':veiculo_id' => $veiculo_id,
                ':m' => $motorista_id,
                ':rota_id' => $rota_id,
                ':posto' => $posto,
                ':data_abastecimento' => $data_abastecimento,
                ':litros' => $litros,
                ':valor_litro' => $valor_litro,
                ':valor_total' => $valor_total,
                ':km_atual' => $km_atual,
                ':tipo_combustivel' => $tipo_combustivel,
                ':forma_pagamento' => $forma_pagamento,
                ':observacoes' => $observacoes,
            ]);
        }
        $id = $conn->lastInsertId();
        api_success(['id' => (int)$id], 'Abastecimento registrado com sucesso.');
    }

    api_error('Método não permitido.', 405);
} catch (PDOException $e) {
    error_log('API abastecimentos: ' . $e->getMessage());
    api_error('Erro ao processar abastecimentos.', 500);
}
