<?php
/**
 * Conferência de Carga - App Android Motoristas.
 * GET:  lista conferências do motorista.
 * POST: registra uma conferência (JSON ou multipart com foto opcional "foto").
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(100, max(1, (int) ($_GET['limite'] ?? 50)));
        $stmt = $conn->prepare('
            SELECT c.*, v.placa
            FROM conferencia_carga c
            LEFT JOIN veiculos v ON c.veiculo_id = v.id
            WHERE c.empresa_id = :e AND c.motorista_id = :m
            ORDER BY c.data_hora DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        api_success(['conferencias' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'POST') {
        $d = [];
        $foto_path = null;
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (stripos($ct, 'multipart/form-data') !== false) {
            $d = $_POST;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $uploadRoot = dirname(__DIR__, 2);
                $uploadInc = $uploadRoot . '/includes/upload_comprovante.php';
                if (is_file($uploadInc)) {
                    require_once $uploadInc;
                    $uploadAbs = $uploadRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'conferencia';
                    if (!is_dir($uploadAbs)) {
                        mkdir($uploadAbs, 0775, true);
                    }
                    try {
                        $foto_path = sf_save_comprovante_upload($_FILES['foto'], $uploadAbs, 'uploads/conferencia', false);
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

        $tipo = strtolower(trim((string) ($d['tipo'] ?? 'coleta')));
        if (!in_array($tipo, ['coleta', 'entrega'], true)) {
            $tipo = 'coleta';
        }
        $rota_id = !empty($d['rota_id']) ? (int) $d['rota_id'] : null;
        $veiculo_id = !empty($d['veiculo_id']) ? (int) $d['veiculo_id'] : null;
        $nf_numero = trim((string) ($d['nf_numero'] ?? ''));
        $nf_valor = isset($d['nf_valor']) && $d['nf_valor'] !== '' ? (float) $d['nf_valor'] : null;
        $volumes = isset($d['volumes']) && $d['volumes'] !== '' ? (int) $d['volumes'] : null;
        $lacre = trim((string) ($d['lacre'] ?? ''));
        $conforme = !empty($d['conforme']) ? 1 : 0;
        $observacoes = trim((string) ($d['observacoes'] ?? ''));
        $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

        if ($nf_numero === '' && $lacre === '' && $volumes === null && $foto_path === null) {
            api_error('Informe ao menos a NF, o lacre, os volumes ou uma foto.');
        }

        if ($rota_id !== null) {
            $stmt = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
            $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
            if (!$stmt->fetch()) {
                api_error('Rota não encontrada ou não pertence ao motorista.');
            }
        }
        if ($veiculo_id !== null) {
            $stmt = $conn->prepare('SELECT id FROM veiculos WHERE id = :id AND empresa_id = :e');
            $stmt->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
            if (!$stmt->fetch()) {
                api_error('Veículo inválido.');
            }
        }

        $stmt = $conn->prepare('
            INSERT INTO conferencia_carga
                (empresa_id, motorista_id, rota_id, veiculo_id, tipo, nf_numero, nf_valor,
                 volumes, lacre, conforme, observacoes, foto_path, latitude, longitude, data_hora, fonte)
            VALUES
                (:e, :m, :rota, :veic, :tipo, :nfn, :nfv,
                 :vol, :lacre, :conf, :obs, :foto, :lat, :lng, :dh, "motorista")
        ');
        $stmt->execute([
            ':e' => $empresa_id,
            ':m' => $motorista_id,
            ':rota' => $rota_id,
            ':veic' => $veiculo_id,
            ':tipo' => $tipo,
            ':nfn' => $nf_numero !== '' ? $nf_numero : null,
            ':nfv' => $nf_valor,
            ':vol' => $volumes,
            ':lacre' => $lacre !== '' ? $lacre : null,
            ':conf' => $conforme,
            ':obs' => $observacoes !== '' ? $observacoes : null,
            ':foto' => $foto_path,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':dh' => $data_hora,
        ]);
        $id = (int) $conn->lastInsertId();

        api_success(['id' => $id], 'Conferência registrada com sucesso.');
    }

    api_error('Método não suportado.', 405);
} catch (PDOException $e) {
    error_log('conferencia_carga.php: ' . $e->getMessage());
    api_error('Erro ao processar a conferência.', 500);
}
