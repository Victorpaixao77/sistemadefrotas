<?php
/**
 * API Botão de Pânico - App Android Motoristas
 * POST: aciona um alerta de pânico (localização + horário)
 * GET:  lista os últimos alertas do motorista
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(50, max(1, (int) ($_GET['limite'] ?? 20)));
        $stmt = $conn->prepare('
            SELECT id, latitude, longitude, data_hora, status
            FROM panico_alertas
            WHERE empresa_id = :e AND motorista_id = :m
            ORDER BY data_hora DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        api_success(['alertas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true);
        if (!is_array($d)) {
            $d = $_POST;
        }

        $veiculo_id = !empty($d['veiculo_id']) ? (int) $d['veiculo_id'] : null;
        $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $observacoes = trim((string) ($d['observacoes'] ?? ''));
        $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

        if ($veiculo_id !== null) {
            $stmt = $conn->prepare('SELECT id FROM veiculos WHERE id = :id AND empresa_id = :e');
            $stmt->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
            if (!$stmt->fetch()) {
                $veiculo_id = null;
            }
        }

        $stmt = $conn->prepare('
            INSERT INTO panico_alertas
                (empresa_id, motorista_id, veiculo_id, latitude, longitude, data_hora, status, observacoes)
            VALUES
                (:e, :m, :veic, :lat, :lng, :dh, "aberto", :obs)
        ');
        $stmt->execute([
            ':e' => $empresa_id,
            ':m' => $motorista_id,
            ':veic' => $veiculo_id,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':dh' => $data_hora,
            ':obs' => $observacoes !== '' ? $observacoes : null,
        ]);

        api_success(['id' => (int) $conn->lastInsertId()], 'Alerta de pânico enviado. A central foi acionada.');
    }

    api_error('Método não suportado.', 405);
} catch (PDOException $e) {
    error_log('panico.php: ' . $e->getMessage());
    api_error('Erro ao acionar o pânico.', 500);
}
