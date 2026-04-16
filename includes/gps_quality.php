<?php
/**
 * Qualidade GPS: accuracy, salto impossível, status (parado/movimento/ocioso) + parado_desde.
 *
 * Env opcionais (getenv):
 * - SF_GPS_MAX_ACCURACY_M — rejeita ponto se accuracy_metros > X (0 = desliga; padrão 0)
 * - SF_GPS_MAX_IMPLIED_SPEED_KMH — salto prev→atual (padrão 220)
 * - SF_GPS_RECUSAR_MOCK — "1" rejeita location_mock=1
 * - SF_GPS_VELOCIDADE_PARADO_KMH — limite movimento (padrão 3)
 * - SF_GPS_OCIOSO_SEGUNDOS — tempo parado no mesmo lugar para ocioso (padrão 420 = 7 min)
 * - SF_GPS_RAIO_MESMO_LUGAR_M — cluster parado (padrão 40)
 */

require_once __DIR__ . '/gps_geofence.php';

if (!function_exists('gps_ultima_ler_contexto')) {
    /**
     * Lê última posição gravada (antes do upsert) para salto, ocioso e cercas.
     *
     * @return array{
     *   latitude: float,
     *   longitude: float,
     *   data_hora: string,
     *   velocidade: ?float,
     *   parado_desde: ?string,
     *   status: ?string
     * }|null
     */
    function gps_ultima_ler_contexto(PDO $conn, int $veiculo_id, int $empresa_id): ?array
    {
        try {
            $st = $conn->prepare('
                SELECT latitude, longitude, data_hora, velocidade, parado_desde, status
                FROM gps_ultima_posicao
                WHERE veiculo_id = :vid AND empresa_id = :eid
                LIMIT 1
            ');
            $st->execute([':vid' => $veiculo_id, ':eid' => $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'data_hora' => (string) $row['data_hora'],
                'velocidade' => isset($row['velocidade']) && $row['velocidade'] !== null && $row['velocidade'] !== ''
                    ? (float) $row['velocidade'] : null,
                'parado_desde' => !empty($row['parado_desde']) ? (string) $row['parado_desde'] : null,
                'status' => isset($row['status']) && $row['status'] !== '' ? (string) $row['status'] : null,
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                return gps_ultima_ler_contexto_legacy($conn, $veiculo_id, $empresa_id);
            }
            return null;
        }
    }

    function gps_ultima_ler_contexto_legacy(PDO $conn, int $veiculo_id, int $empresa_id): ?array
    {
        try {
            $st = $conn->prepare('
                SELECT latitude, longitude, data_hora, velocidade FROM gps_ultima_posicao
                WHERE veiculo_id = :vid AND empresa_id = :eid LIMIT 1
            ');
            $st->execute([':vid' => $veiculo_id, ':eid' => $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'data_hora' => (string) $row['data_hora'],
                'velocidade' => isset($row['velocidade']) && $row['velocidade'] !== null && $row['velocidade'] !== ''
                    ? (float) $row['velocidade'] : null,
                'parado_desde' => null,
                'status' => null,
            ];
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('gps_quality_validar_entrada')) {
    function gps_quality_validar_entrada(
        ?float $accuracy_metros,
        ?int $location_mock,
        float $latitude,
        float $longitude,
        string $data_hora,
        ?array $prev
    ): void {
        if (getenv('SF_GPS_RECUSAR_MOCK') === '1' && $location_mock === 1) {
            throw new InvalidArgumentException('GPS recusado: aplicativo reportou localização fictícia (mock).');
        }

        $maxAcc = (float) (getenv('SF_GPS_MAX_ACCURACY_M') ?: 0);
        if ($maxAcc > 0 && $accuracy_metros !== null && $accuracy_metros > $maxAcc) {
            throw new InvalidArgumentException(
                'Precisão GPS insuficiente (' . round($accuracy_metros, 1) . ' m). Aguarde sinal melhor ou saia de local coberto.'
            );
        }

        $maxSpeed = (float) (getenv('SF_GPS_MAX_IMPLIED_SPEED_KMH') ?: 220);
        if ($maxSpeed <= 0 || $prev === null) {
            return;
        }

        $t0 = strtotime($prev['data_hora']);
        $t1 = strtotime($data_hora);
        if ($t0 === false || $t1 === false) {
            return;
        }
        $dt = abs($t1 - $t0);
        if ($dt < 2 || $dt > 86400) {
            return;
        }

        $dist = gps_haversine_metros($prev['latitude'], $prev['longitude'], $latitude, $longitude);
        $impliedKmh = ($dist / (float) $dt) * 3.6;
        if ($impliedKmh > $maxSpeed) {
            throw new InvalidArgumentException(
                'Movimento inconsistente entre dois pontos (≈' . round($impliedKmh, 0) . ' km/h implícitos). Verifique data/hora ou GPS.'
            );
        }
    }
}

if (!function_exists('gps_quality_calcular_status_e_parado_desde')) {
    /**
     * @return array{status: string, parado_desde: ?string, status_log: string}
     */
    function gps_quality_calcular_status_e_parado_desde(
        ?float $velocidade_kmh,
        float $latitude,
        float $longitude,
        string $data_hora,
        ?array $prev
    ): array {
        $limite = (float) (getenv('SF_GPS_VELOCIDADE_PARADO_KMH') ?: 3);
        $raioM = (float) (getenv('SF_GPS_RAIO_MESMO_LUGAR_M') ?: 40);
        $ociosoSec = (int) (getenv('SF_GPS_OCIOSO_SEGUNDOS') ?: 420);
        if ($ociosoSec < 60) {
            $ociosoSec = 60;
        }

        $ts = strtotime($data_hora);
        if ($ts === false) {
            $ts = time();
        }
        $dhSql = date('Y-m-d H:i:s', $ts);

        $distPrev = null;
        $dtPrev = null;
        if ($prev !== null) {
            $distPrev = gps_haversine_metros($prev['latitude'], $prev['longitude'], $latitude, $longitude);
            $tp = strtotime($prev['data_hora']);
            if ($tp !== false) {
                $dtPrev = max(0, $ts - $tp);
            }
        }

        $vel = $velocidade_kmh;
        $implied = null;
        if ($distPrev !== null && $dtPrev !== null && $dtPrev > 0) {
            $implied = min(400.0, ($distPrev / (float) $dtPrev) * 3.6);
        }

        $emMovimento = ($vel !== null && $vel > $limite)
            || ($implied !== null && $implied > $limite)
            || ($distPrev !== null && $distPrev > max($raioM, 55.0));

        if ($emMovimento) {
            return ['status' => 'movimento', 'parado_desde' => null, 'status_log' => 'movimento'];
        }

        // Parado no mesmo lugar (cluster)
        $mesmoLugar = $prev === null || ($distPrev !== null && $distPrev <= $raioM);

        if (!$mesmoLugar) {
            // Parado mas mudou de lugar (ex.: arrastou pouco o mapa / drift)
            return ['status' => 'parado', 'parado_desde' => $dhSql, 'status_log' => 'parado'];
        }

        $pd = $prev['parado_desde'] ?? null;
        if ($pd === null || $pd === '') {
            $pd = $dhSql;
        } else {
            $tPd = strtotime($pd);
            if ($tPd === false) {
                $pd = $dhSql;
            }
        }

        $tPd2 = strtotime($pd);
        $durParada = ($tPd2 !== false) ? max(0, $ts - $tPd2) : 0;

        if ($durParada >= $ociosoSec) {
            return ['status' => 'ocioso', 'parado_desde' => $pd, 'status_log' => 'ocioso'];
        }

        return ['status' => 'parado', 'parado_desde' => $pd, 'status_log' => 'parado'];
    }
}
