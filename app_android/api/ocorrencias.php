<?php
/**
 * API Ocorrências de Viagem - App Android Motoristas
 * GET:  lista ocorrências do motorista
 * POST: registra ocorrência (JSON ou multipart com foto opcional "foto")
 *
 * Tipos: acidente, multa, roubo, tentativa_roubo, mecanico, carga, bloqueio, outro
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

const OCORRENCIA_TIPOS = [
    'acidente', 'multa', 'roubo', 'tentativa_roubo', 'mecanico', 'carga', 'bloqueio', 'outro',
];

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(100, max(1, (int) ($_GET['limite'] ?? 50)));
        $stmt = $conn->prepare('
            SELECT o.*, v.placa
            FROM ocorrencias_viagem o
            LEFT JOIN veiculos v ON o.veiculo_id = v.id
            WHERE o.empresa_id = :e AND o.motorista_id = :m
            ORDER BY o.data_hora DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        api_success(['ocorrencias' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'POST') {
        $d = [];
        $foto_path = null;
        $video_path = null;
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (stripos($ct, 'multipart/form-data') !== false) {
            $d = $_POST;
            $uploadRoot = dirname(__DIR__, 2);
            $uploadInc = $uploadRoot . '/includes/upload_comprovante.php';
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK && is_file($uploadInc)) {
                require_once $uploadInc;
                $uploadAbs = $uploadRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ocorrencias';
                if (!is_dir($uploadAbs)) {
                    mkdir($uploadAbs, 0775, true);
                }
                try {
                    $foto_path = sf_save_comprovante_upload($_FILES['foto'], $uploadAbs, 'uploads/ocorrencias', false);
                } catch (Exception $ex) {
                    api_error($ex->getMessage(), 400);
                }
            }
            $video_path = null;
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK && is_file($uploadInc)) {
                require_once $uploadInc;
                $uploadAbsV = $uploadRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ocorrencias' . DIRECTORY_SEPARATOR . 'videos';
                if (!is_dir($uploadAbsV)) {
                    mkdir($uploadAbsV, 0775, true);
                }
                try {
                    $video_path = sf_save_comprovante_upload($_FILES['video'], $uploadAbsV, 'uploads/ocorrencias/videos', false);
                } catch (Exception $ex) {
                    api_error($ex->getMessage(), 400);
                }
            }
        } else {
            $raw = file_get_contents('php://input');
            $d = json_decode($raw, true);
            if (!is_array($d)) {
                $d = $_POST;
            }
        }

        $tipo = strtolower(trim((string) ($d['tipo'] ?? '')));
        if (!in_array($tipo, OCORRENCIA_TIPOS, true)) {
            api_error('Tipo de ocorrência inválido.');
        }

        $descricao = trim((string) ($d['descricao'] ?? ''));
        $rota_id = !empty($d['rota_id']) ? (int) $d['rota_id'] : null;
        $veiculo_id = !empty($d['veiculo_id']) ? (int) $d['veiculo_id'] : null;
        $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

        // Valida vínculos (se informados) contra a empresa/motorista.
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
            INSERT INTO ocorrencias_viagem
                (empresa_id, motorista_id, rota_id, veiculo_id, tipo, descricao,
                 latitude, longitude, data_hora, foto_path, video_path, status, fonte)
            VALUES
                (:e, :m, :rota, :veic, :tipo, :desc,
                 :lat, :lng, :dh, :foto, :video, "aberta", "motorista")
        ');
        $stmt->execute([
            ':e' => $empresa_id,
            ':m' => $motorista_id,
            ':rota' => $rota_id,
            ':veic' => $veiculo_id,
            ':tipo' => $tipo,
            ':desc' => $descricao !== '' ? $descricao : null,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':dh' => $data_hora,
            ':foto' => $foto_path,
            ':video' => $video_path ?? null,
        ]);
        $id = (int) $conn->lastInsertId();

        api_success(['id' => $id], 'Ocorrência registrada com sucesso.');
    }

    api_error('Método não suportado.', 405);
} catch (PDOException $e) {
    error_log('ocorrencias.php: ' . $e->getMessage());
    api_error('Erro ao processar a ocorrência.', 500);
}
