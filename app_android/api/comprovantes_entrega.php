<?php
/**
 * API Comprovante de Entrega (POD) - App Android Motoristas
 * GET:  lista comprovantes do motorista
 * POST: registra comprovante (JSON ou multipart com fotos opcionais:
 *       "foto_nf", "foto_carga", "assinatura")
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

/** Salva um arquivo enviado (se presente) e devolve o caminho web, ou null. */
function pod_salvar_upload(string $campo): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $uploadRoot = dirname(__DIR__, 2);
    $uploadInc = $uploadRoot . '/includes/upload_comprovante.php';
    if (!is_file($uploadInc)) {
        return null;
    }
    require_once $uploadInc;
    $uploadAbs = $uploadRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'entregas';
    if (!is_dir($uploadAbs)) {
        mkdir($uploadAbs, 0775, true);
    }
    return sf_save_comprovante_upload($_FILES[$campo], $uploadAbs, 'uploads/entregas', false);
}

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(100, max(1, (int) ($_GET['limite'] ?? 50)));
        $stmt = $conn->prepare('
            SELECT ce.*, v.placa
            FROM comprovantes_entrega ce
            LEFT JOIN rotas r ON ce.rota_id = r.id
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            WHERE ce.empresa_id = :e AND ce.motorista_id = :m
            ORDER BY ce.data_hora DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        api_success(['comprovantes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'POST') {
        $d = [];
        $fotoNf = null;
        $fotoCarga = null;
        $assinatura = null;
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        if (stripos($ct, 'multipart/form-data') !== false) {
            $d = $_POST;
            try {
                $fotoNf = pod_salvar_upload('foto_nf');
                $fotoCarga = pod_salvar_upload('foto_carga');
                $assinatura = pod_salvar_upload('assinatura');
            } catch (Exception $ex) {
                api_error($ex->getMessage(), 400);
            }
        } else {
            $raw = file_get_contents('php://input');
            $d = json_decode($raw, true);
            if (!is_array($d)) {
                $d = $_POST;
            }
        }

        $rota_id = !empty($d['rota_id']) ? (int) $d['rota_id'] : null;
        $recebedor_nome = trim((string) ($d['recebedor_nome'] ?? ''));
        $recebedor_doc = trim((string) ($d['recebedor_doc'] ?? ''));
        $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $observacoes = trim((string) ($d['observacoes'] ?? ''));
        $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

        if ($rota_id !== null) {
            $stmt = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
            $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
            if (!$stmt->fetch()) {
                api_error('Rota não encontrada ou não pertence ao motorista.');
            }
        }

        $stmt = $conn->prepare('
            INSERT INTO comprovantes_entrega
                (empresa_id, motorista_id, rota_id, recebedor_nome, recebedor_doc,
                 foto_nf_path, foto_carga_path, assinatura_path,
                 latitude, longitude, data_hora, observacoes, status, fonte)
            VALUES
                (:e, :m, :rota, :rnome, :rdoc,
                 :fnf, :fcarga, :assin,
                 :lat, :lng, :dh, :obs, "entregue", "motorista")
        ');
        $stmt->execute([
            ':e' => $empresa_id,
            ':m' => $motorista_id,
            ':rota' => $rota_id,
            ':rnome' => $recebedor_nome !== '' ? $recebedor_nome : null,
            ':rdoc' => $recebedor_doc !== '' ? $recebedor_doc : null,
            ':fnf' => $fotoNf,
            ':fcarga' => $fotoCarga,
            ':assin' => $assinatura,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':dh' => $data_hora,
            ':obs' => $observacoes !== '' ? $observacoes : null,
        ]);

        api_success(['id' => (int) $conn->lastInsertId()], 'Comprovante de entrega registrado.');
    }

    api_error('Método não suportado.', 405);
} catch (PDOException $e) {
    error_log('comprovantes_entrega.php: ' . $e->getMessage());
    api_error('Erro ao registrar o comprovante.', 500);
}
