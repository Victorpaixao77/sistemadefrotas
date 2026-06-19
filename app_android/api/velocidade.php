<?php
/**
 * Controle de Velocidade - App Android Motoristas.
 * GET: estatísticas de velocidade do motorista a partir dos pings de GPS já coletados
 *      (gps_logs.velocidade), nos últimos N dias, com pontuação e lista de excessos.
 * Params: dias (padrão 7, máx 90), limite (km/h, padrão 90).
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

try {
    $conn = getConnection();
    $dias = min(90, max(1, (int) ($_GET['dias'] ?? 7)));
    $limite = (float) ($_GET['limite'] ?? 90);
    if ($limite < 40 || $limite > 130) {
        $limite = 90;
    }

    // Estatísticas gerais (considera apenas pontos com velocidade registrada).
    $stmt = $conn->prepare('
        SELECT
            COUNT(*) AS total,
            COALESCE(MAX(velocidade), 0) AS vmax,
            COALESCE(AVG(CASE WHEN velocidade > 5 THEN velocidade END), 0) AS vmedia,
            SUM(CASE WHEN velocidade > :lim THEN 1 ELSE 0 END) AS excessos
        FROM gps_logs
        WHERE empresa_id = :e AND motorista_id = :m
          AND velocidade IS NOT NULL
          AND data_hora >= DATE_SUB(NOW(), INTERVAL :d DAY)
    ');
    $stmt->bindValue(':lim', $limite);
    $stmt->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $stmt->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $stmt->bindValue(':d', $dias, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int) ($row['total'] ?? 0);
    $vmax = round((float) ($row['vmax'] ?? 0), 1);
    $vmedia = round((float) ($row['vmedia'] ?? 0), 1);
    $excessos = (int) ($row['excessos'] ?? 0);
    $ratio = $total > 0 ? $excessos / $total : 0.0;

    // Pontuação 0-100: penaliza proporção de pontos em excesso e pico muito acima do limite.
    $score = 100 - ($ratio * 200);
    if ($vmax > $limite + 25) {
        $score -= 12;
    } elseif ($vmax > $limite + 10) {
        $score -= 5;
    }
    $score = (int) max(0, min(100, round($score)));

    if ($score >= 90) {
        $classificacao = 'Excelente';
    } elseif ($score >= 75) {
        $classificacao = 'Bom';
    } elseif ($score >= 50) {
        $classificacao = 'Regular';
    } else {
        $classificacao = 'Atenção';
    }

    // Excessos recentes (maiores velocidades).
    $ev = $conn->prepare('
        SELECT data_hora, ROUND(velocidade, 0) AS velocidade, latitude, longitude
        FROM gps_logs
        WHERE empresa_id = :e AND motorista_id = :m
          AND velocidade > :lim
          AND data_hora >= DATE_SUB(NOW(), INTERVAL :d DAY)
        ORDER BY velocidade DESC, data_hora DESC
        LIMIT 25
    ');
    $ev->bindValue(':lim', $limite);
    $ev->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $ev->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $ev->bindValue(':d', $dias, PDO::PARAM_INT);
    $ev->execute();
    $eventos = $ev->fetchAll(PDO::FETCH_ASSOC);

    api_success([
        'dias' => $dias,
        'limite_kmh' => $limite,
        'total_pontos' => $total,
        'velocidade_maxima' => $vmax,
        'velocidade_media' => $vmedia,
        'excessos' => $excessos,
        'pct_excesso' => $total > 0 ? round($ratio * 100, 1) : 0,
        'score' => $score,
        'classificacao' => $classificacao,
        'eventos' => $eventos,
    ]);
} catch (PDOException $e) {
    error_log('velocidade.php: ' . $e->getMessage());
    api_error('Erro ao calcular velocidade.', 500);
}
