<?php
/**
 * Alertas operacionais (gps_alertas_operacionais): bateria baixa, velocidade, mock.
 */

if (!function_exists('gps_operacional_alerta_inserir')) {
    function gps_operacional_alerta_inserir(
        PDO $conn,
        int $empresa_id,
        int $veiculo_id,
        int $motorista_id,
        string $tipo,
        string $mensagem,
        string $data_hora,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $extra = null
    ): void {
        try {
            $st = $conn->prepare('
                INSERT INTO gps_alertas_operacionais (
                    empresa_id, veiculo_id, motorista_id, tipo, mensagem,
                    latitude, longitude, data_hora, extra_json
                ) VALUES (
                    :eid, :vid, :mid, :tipo, :msg, :lat, :lng, :dh, :ex
                )
            ');
            $st->execute([
                ':eid' => $empresa_id,
                ':vid' => $veiculo_id,
                ':mid' => $motorista_id,
                ':tipo' => mb_substr($tipo, 0, 32),
                ':msg' => mb_substr($mensagem, 0, 255),
                ':lat' => $latitude,
                ':lng' => $longitude,
                ':dh' => $data_hora,
                ':ex' => $extra !== null ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (PDOException $e) {
            // tabela não criada
        }
    }
}

if (!function_exists('gps_operacional_alerta_recente')) {
    function gps_operacional_alerta_recente(
        PDO $conn,
        int $veiculo_id,
        string $tipo,
        string $data_hora_ref,
        int $segundos
    ): bool {
        if ($segundos <= 0) {
            return false;
        }
        try {
            $sql = '
                SELECT 1 FROM gps_alertas_operacionais
                WHERE veiculo_id = :vid AND tipo = :tipo
                  AND data_hora > DATE_SUB(:dh, INTERVAL ' . max(1, (int) $segundos) . ' SECOND)
                LIMIT 1
            ';
            $st = $conn->prepare($sql);
            $st->execute([':vid' => $veiculo_id, ':tipo' => $tipo, ':dh' => $data_hora_ref]);

            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('gps_operacional_processar_pos_gravada')) {
    /**
     * Chamado após commit do ponto GPS.
     *
     * Env (além das já documentadas): SF_GPS_ALERTA_BATERIA_CRITICA_PCT, SF_GPS_ALERTA_GAP_MINUTOS,
     * SF_GPS_ALERTA_VELOCIDADE_IMPOSSIVEL_KMH, SF_GPS_ALERTA_SALTO_SUAVE_MIN_KMH,
     * SF_GPS_ALERTA_IGNICAO_PARADO (1 para ativar).
     */
    function gps_operacional_processar_pos_gravada(
        PDO $conn,
        int $empresa_id,
        int $veiculo_id,
        int $motorista_id,
        string $data_hora,
        ?float $latitude,
        ?float $longitude,
        ?float $velocidade_kmh,
        ?int $bateria_pct,
        ?int $location_mock,
        ?float $accuracy_metros,
        ?array $prev_ctx = null,
        ?string $status = null,
        ?int $ignicao = null
    ): void {
        if (getenv('SF_GPS_ALERTAS_OPERACIONAIS') !== '1') {
            return;
        }

        require_once __DIR__ . '/gps_geofence.php';

        $batLim = (int) (getenv('SF_GPS_ALERTA_BATERIA_PCT') ?: 15);
        $batCrit = (int) (getenv('SF_GPS_ALERTA_BATERIA_CRITICA_PCT') ?: 5);
        $dedupeBat = (int) (getenv('SF_GPS_ALERTA_BATERIA_DEDUP_SEG') ?: 1800);
        $dedupeBatCrit = (int) (getenv('SF_GPS_ALERTA_BATERIA_CRITICA_DEDUP_SEG') ?: 3600);
        $velLim = (float) (getenv('SF_GPS_ALERTA_VELOCIDADE_KMH') ?: 110);
        $dedupeVel = (int) (getenv('SF_GPS_ALERTA_VELOCIDADE_DEDUP_SEG') ?: 600);
        $dedupeMock = (int) (getenv('SF_GPS_ALERTA_MOCK_DEDUP_SEG') ?: 3600);
        $gapMin = (int) (getenv('SF_GPS_ALERTA_GAP_MINUTOS') ?: 25);
        $dedupeGap = (int) (getenv('SF_GPS_ALERTA_PERDA_SINAL_DEDUP_SEG') ?: 3600);
        $velImp = (float) (getenv('SF_GPS_ALERTA_VELOCIDADE_IMPOSSIVEL_KMH') ?: 180);
        $dedupeVelImp = (int) (getenv('SF_GPS_ALERTA_VEL_IMP_DEDUP_SEG') ?: 600);
        $saltoMin = (float) (getenv('SF_GPS_ALERTA_SALTO_SUAVE_MIN_KMH') ?: 130);
        $saltoMax = (float) (getenv('SF_GPS_MAX_IMPLIED_SPEED_KMH') ?: 220);
        $dedupeSalto = (int) (getenv('SF_GPS_ALERTA_SALTO_DEDUP_SEG') ?: 900);
        $dedupeIgn = (int) (getenv('SF_GPS_ALERTA_IGNICAO_DEDUP_SEG') ?: 600);

        $accOkVel = $accuracy_metros === null || $accuracy_metros <= (float) (getenv('SF_GPS_ALERTA_VEL_MAX_ACCURACY_M') ?: 65);

        if ($bateria_pct !== null && $bateria_pct >= 0 && $bateria_pct <= $batCrit) {
            if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'bateria_critica', $data_hora, $dedupeBatCrit)) {
                gps_operacional_alerta_inserir(
                    $conn,
                    $empresa_id,
                    $veiculo_id,
                    $motorista_id,
                    'bateria_critica',
                    'Bateria crítica no celular: ' . $bateria_pct . '%.',
                    $data_hora,
                    $latitude,
                    $longitude,
                    ['bateria_pct' => $bateria_pct]
                );
            }
        } elseif ($bateria_pct !== null && $bateria_pct <= $batLim && $bateria_pct >= 0) {
            if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'bateria_baixa', $data_hora, $dedupeBat)) {
                gps_operacional_alerta_inserir(
                    $conn,
                    $empresa_id,
                    $veiculo_id,
                    $motorista_id,
                    'bateria_baixa',
                    'Bateria do celular em ' . $bateria_pct . '% (veículo ' . $veiculo_id . ').',
                    $data_hora,
                    $latitude,
                    $longitude,
                    ['bateria_pct' => $bateria_pct]
                );
            }
        }

        if ($velocidade_kmh !== null && $velocidade_kmh >= $velImp && $accOkVel) {
            if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'velocidade_impossivel', $data_hora, $dedupeVelImp)) {
                gps_operacional_alerta_inserir(
                    $conn,
                    $empresa_id,
                    $veiculo_id,
                    $motorista_id,
                    'velocidade_impossivel',
                    'Velocidade reportada muito alta: ' . round($velocidade_kmh, 0) . ' km/h (verificar GPS ou fraude).',
                    $data_hora,
                    $latitude,
                    $longitude,
                    ['velocidade_kmh' => round($velocidade_kmh, 2)]
                );
            }
        } elseif ($velocidade_kmh !== null && $velocidade_kmh >= $velLim && $accOkVel) {
            if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'velocidade_alta', $data_hora, $dedupeVel)) {
                gps_operacional_alerta_inserir(
                    $conn,
                    $empresa_id,
                    $veiculo_id,
                    $motorista_id,
                    'velocidade_alta',
                    'Velocidade reportada ' . round($velocidade_kmh, 0) . ' km/h.',
                    $data_hora,
                    $latitude,
                    $longitude,
                    ['velocidade_kmh' => round($velocidade_kmh, 2)]
                );
            }
        }

        if ($location_mock === 1) {
            if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'gps_mock', $data_hora, $dedupeMock)) {
                gps_operacional_alerta_inserir(
                    $conn,
                    $empresa_id,
                    $veiculo_id,
                    $motorista_id,
                    'gps_mock',
                    'App reportou localização fictícia (mock) para este ponto.',
                    $data_hora,
                    $latitude,
                    $longitude,
                    null
                );
            }
        }

        if ($prev_ctx !== null && $gapMin > 0 && $latitude !== null && $longitude !== null) {
            $t0 = strtotime($prev_ctx['data_hora'] ?? '');
            $t1 = strtotime($data_hora);
            if ($t0 !== false && $t1 !== false) {
                $gapSec = $t1 - $t0;
                if ($gapSec >= $gapMin * 60 && $gapSec <= 86400 * 2) {
                    if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'perda_sinal_gps', $data_hora, $dedupeGap)) {
                        $minutos = (int) round($gapSec / 60);
                        gps_operacional_alerta_inserir(
                            $conn,
                            $empresa_id,
                            $veiculo_id,
                            $motorista_id,
                            'perda_sinal_gps',
                            'Sem pontos GPS por ~' . $minutos . ' min (retomada de sinal).',
                            $data_hora,
                            $latitude,
                            $longitude,
                            ['gap_segundos' => $gapSec]
                        );
                    }
                }
            }
        }

        if ($prev_ctx !== null && $saltoMax > $saltoMin && $latitude !== null && $longitude !== null) {
            $t0 = strtotime($prev_ctx['data_hora'] ?? '');
            $t1 = strtotime($data_hora);
            if ($t0 !== false && $t1 !== false) {
                $dt = abs($t1 - $t0);
                if ($dt >= 3 && $dt <= 86400) {
                    $dist = gps_haversine_metros(
                        (float) $prev_ctx['latitude'],
                        (float) $prev_ctx['longitude'],
                        $latitude,
                        $longitude
                    );
                    $impliedKmh = ($dist / (float) $dt) * 3.6;
                    if ($impliedKmh >= $saltoMin && $impliedKmh < $saltoMax && $accOkVel) {
                        if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'salto_suspeito', $data_hora, $dedupeSalto)) {
                            gps_operacional_alerta_inserir(
                                $conn,
                                $empresa_id,
                                $veiculo_id,
                                $motorista_id,
                                'salto_suspeito',
                                'Deslocamento rápido entre pontos (~' . round($impliedKmh, 0) . ' km/h implícitos).',
                                $data_hora,
                                $latitude,
                                $longitude,
                                [
                                    'implied_kmh' => round($impliedKmh, 1),
                                    'dist_metros' => round($dist, 0),
                                ]
                            );
                        }
                    }
                }
            }
        }

        if (getenv('SF_GPS_ALERTA_IGNICAO_PARADO') === '1'
            && $ignicao === 1
            && $status !== null
            && ($status === 'parado' || $status === 'ocioso')
            && ($velocidade_kmh === null || $velocidade_kmh < 8.0)
        ) {
            if (!gps_operacional_alerta_recente($conn, $veiculo_id, 'ignicao_parado', $data_hora, $dedupeIgn)) {
                gps_operacional_alerta_inserir(
                    $conn,
                    $empresa_id,
                    $veiculo_id,
                    $motorista_id,
                    'ignicao_parado',
                    'Ignição ligada com veículo parado/ocioso (telemetria).',
                    $data_hora,
                    $latitude,
                    $longitude,
                    ['status' => $status]
                );
            }
        }
    }
}
