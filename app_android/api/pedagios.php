<?php
/**
 * API Controle de Pedágios - App Android Motoristas
 * GET:  lista pedágios do motorista (com total)
 * POST: registra um pedágio (JSON ou multipart com foto opcional "foto")
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
            SELECT p.*, v.placa
            FROM pedagios_viagem p
            LEFT JOIN veiculos v ON p.veiculo_id = v.id
            WHERE p.empresa_id = :e AND p.motorista_id = :m
            ORDER BY p.data_hora DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total dos últimos 30 dias.
        $tot = $conn->prepare('
            SELECT COALESCE(SUM(valor), 0) AS total
            FROM pedagios_viagem
            WHERE empresa_id = :e AND motorista_id = :m
              AND data_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        $tot->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $total30 = (float) ($tot->fetchColumn() ?: 0);

        api_success(['pedagios' => $lista, 'total_30d' => $total30]);
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
                    $uploadAbs = $uploadRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pedagios';
                    if (!is_dir($uploadAbs)) {
                        mkdir($uploadAbs, 0775, true);
                    }
                    try {
                        $foto_path = sf_save_comprovante_upload($_FILES['foto'], $uploadAbs, 'uploads/pedagios', false);
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

        $valor = isset($d['valor']) && $d['valor'] !== '' ? (float) $d['valor'] : 0.0;
        if ($valor <= 0) {
            api_error('Informe o valor do pedágio.');
        }

        $praca = trim((string) ($d['praca'] ?? ''));
        $rota_id = !empty($d['rota_id']) ? (int) $d['rota_id'] : null;
        $veiculo_id = !empty($d['veiculo_id']) ? (int) $d['veiculo_id'] : null;
        $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

        // Valida vínculos (se informados) contra empresa/motorista.
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
            INSERT INTO pedagios_viagem
                (empresa_id, motorista_id, rota_id, veiculo_id, praca, valor,
                 latitude, longitude, data_hora, foto_path, fonte)
            VALUES
                (:e, :m, :rota, :veic, :praca, :valor,
                 :lat, :lng, :dh, :foto, "motorista")
        ');
        $stmt->execute([
            ':e' => $empresa_id,
            ':m' => $motorista_id,
            ':rota' => $rota_id,
            ':veic' => $veiculo_id,
            ':praca' => $praca !== '' ? $praca : null,
            ':valor' => $valor,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':dh' => $data_hora,
            ':foto' => $foto_path,
        ]);
        $id = (int) $conn->lastInsertId();

        api_success(['id' => $id], 'Pedágio registrado com sucesso.');
    }

    api_error('Método não suportado.', 405);
} catch (PDOException $e) {
    error_log('pedagios.php: ' . $e->getMessage());
    api_error('Erro ao processar o pedágio.', 500);
}
