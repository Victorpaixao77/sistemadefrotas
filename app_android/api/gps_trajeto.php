<?php
/**
 * Trajeto GPS do motorista (mapa da viagem).
 * GET ?horas=24&limite=500
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_error('Método não permitido.', 405);
}

$horas = min(72, max(1, (int) ($_GET['horas'] ?? 24)));
$limite = min(1000, max(10, (int) ($_GET['limite'] ?? 400)));

try {
    $conn = getConnection();
    $stmt = $conn->prepare('
        SELECT latitude AS lat, longitude AS lng, velocidade AS velocidade_kmh, data_hora
        FROM gps_logs
        WHERE empresa_id = :e AND motorista_id = :m
          AND data_hora >= DATE_SUB(NOW(), INTERVAL :h HOUR)
          AND latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY data_hora ASC
        LIMIT ' . $limite
    );
    $stmt->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $stmt->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $stmt->bindValue(':h', $horas, PDO::PARAM_INT);
    $stmt->execute();
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    api_success([
        'pontos' => $pontos,
        'total' => count($pontos),
        'horas' => $horas,
    ]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_logs') !== false) {
        api_success(['pontos' => [], 'total' => 0, 'horas' => $horas]);
    }
    error_log('gps_trajeto.php: ' . $e->getMessage());
    api_error('Erro ao carregar trajeto.', 500);
}
