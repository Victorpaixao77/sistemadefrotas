<?php
/**
 * Persistência de ponto GPS enviado pelo app do motorista (token API).
 * Usado por app_android/api/gps_salvar.php e api/gps/salvar.php.
 */

require_once __DIR__ . '/gps_geofence.php';
require_once __DIR__ . '/gps_redis_queue.php';
require_once __DIR__ . '/gps_validators.php';
require_once __DIR__ . '/gps_quality.php';
require_once __DIR__ . '/gps_reverse_geocode.php';
require_once __DIR__ . '/gps_operational_alerts.php';

if (!function_exists('gps_motorista_salvar_posicao')) {
    /**
     * @return array{id: int|string}
     */
    function gps_motorista_salvar_posicao(
        PDO $conn,
        int $empresa_id,
        int $motorista_id,
        int $veiculo_id,
        float $latitude,
        float $longitude,
        ?float $velocidade_kmh,
        ?string $data_hora_cliente,
        ?int $ignicao = null,
        ?string $endereco = null,
        ?int $bateria_pct = null,
        ?float $accuracy_metros = null,
        ?string $provider = null,
        ?int $location_mock = null
    ): array {
        $st = $conn->prepare('SELECT id FROM veiculos WHERE id = :vid AND empresa_id = :eid LIMIT 1');
        $st->execute([':vid' => $veiculo_id, ':eid' => $empresa_id]);
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException('Veículo inválido ou não pertence à empresa.');
        }

        gps_validar_coordenacao_entrada($latitude, $longitude);

        if (getenv('SF_GPS_EXIGIR_VINCULO_ROTA') === '1') {
            try {
                $chk = $conn->prepare('
                    SELECT 1 FROM rotas
                    WHERE empresa_id = :eid AND motorista_id = :mid AND veiculo_id = :vid
                    LIMIT 1
                ');
                $chk->execute([':eid' => $empresa_id, ':mid' => $motorista_id, ':vid' => $veiculo_id]);
                if (!$chk->fetchColumn()) {
                    throw new InvalidArgumentException(
                        'Motorista e veículo sem rota associada nesta empresa. Registre uma rota ou defina SF_GPS_EXIGIR_VINCULO_ROTA=0.'
                    );
                }
            } catch (PDOException $e) {
                error_log('gps_motorista_salvar_posicao rotas: ' . $e->getMessage());
            }
        }

        $data_hora = date('Y-m-d H:i:s');
        if (is_string($data_hora_cliente) && $data_hora_cliente !== '') {
            $ts = strtotime($data_hora_cliente);
            if ($ts !== false) {
                $data_hora = date('Y-m-d H:i:s', $ts);
            }
        }

        $ultimaAtual = date('Y-m-d H:i:s');
        $prevCtx = gps_ultima_ler_contexto($conn, $veiculo_id, $empresa_id);

        gps_quality_validar_entrada(
            $accuracy_metros,
            $location_mock,
            $latitude,
            $longitude,
            $data_hora,
            $prevCtx
        );

        $pack = gps_quality_calcular_status_e_parado_desde(
            $velocidade_kmh,
            $latitude,
            $longitude,
            $data_hora,
            $prevCtx
        );
        $status = $pack['status'];
        $parado_desde = $pack['parado_desde'];
        $status_log = $pack['status_log'];

        $bat = null;
        if ($bateria_pct !== null && $bateria_pct >= 0 && $bateria_pct <= 100) {
            $bat = $bateria_pct;
        }

        $mockVal = ($location_mock === 1) ? 1 : null;
        $prov = $provider !== null && $provider !== '' ? mb_substr(trim($provider), 0, 32) : null;
        $acc = $accuracy_metros !== null && $accuracy_metros >= 0 ? round($accuracy_metros, 2) : null;

        $enderecoFinal = $endereco !== null && $endereco !== '' ? mb_substr(trim($endereco), 0, 255) : null;
        if (($enderecoFinal === null || $enderecoFinal === '') && getenv('SF_GPS_REVERSE_GEOCODE') === '1') {
            $geoN = max(1, (int) (getenv('SF_GPS_GEOCODE_EVERY_N') ?: 8));
            if (random_int(1, $geoN) === 1) {
                $geo = gps_reverse_geocode_endereco($conn, $latitude, $longitude);
                if (is_string($geo) && $geo !== '') {
                    $enderecoFinal = $geo;
                }
            }
        }

        $prevUltimaGeo = $prevCtx !== null
            ? ['latitude' => $prevCtx['latitude'], 'longitude' => $prevCtx['longitude']]
            : null;

        $conn->beginTransaction();
        try {
            $insParams = [
                ':eid' => $empresa_id,
                ':vid' => $veiculo_id,
                ':mid' => $motorista_id,
                ':lat' => round($latitude, 8),
                ':lng' => round($longitude, 8),
                ':vel' => $velocidade_kmh !== null ? round($velocidade_kmh, 2) : null,
                ':dh' => $data_hora,
            ];

            $newId = gps_motorista_insert_log($conn, $insParams, $bat, $acc, $prov, $mockVal, $status_log);

            $paramsUltima = [
                ':vid' => $veiculo_id,
                ':eid' => $empresa_id,
                ':mid' => $motorista_id,
                ':lat' => round($latitude, 8),
                ':lng' => round($longitude, 8),
                ':vel' => $velocidade_kmh !== null ? round($velocidade_kmh, 2) : null,
                ':st' => $status,
                ':ign' => $ignicao,
                ':ua' => $ultimaAtual,
                ':end' => $enderecoFinal,
                ':dh' => $data_hora,
                ':pd' => $parado_desde,
                ':acc' => $acc,
                ':prov' => $prov,
                ':mock' => $mockVal,
            ];

            gps_motorista_upsert_ultima($conn, $paramsUltima, $bat);

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        try {
            gps_geofence_registrar_transicoes(
                $conn,
                $empresa_id,
                $veiculo_id,
                $motorista_id,
                $latitude,
                $longitude,
                $prevUltimaGeo,
                $data_hora
            );
        } catch (Throwable $e) {
            error_log('gps_geofence_registrar: ' . $e->getMessage());
        }

        gps_redis_queue_try_push([
            'tipo' => 'gps_ponto',
            'empresa_id' => $empresa_id,
            'veiculo_id' => $veiculo_id,
            'motorista_id' => $motorista_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'data_hora' => $data_hora,
            'log_id' => $newId,
        ]);

        $cacheUltima = [
            'veiculo_id' => $veiculo_id,
            'motorista_id' => $motorista_id,
            'latitude' => round($latitude, 8),
            'longitude' => round($longitude, 8),
            'velocidade' => $velocidade_kmh,
            'data_hora' => $data_hora,
            'ultima_atualizacao' => $ultimaAtual,
            'status' => $status,
            'ignicao' => $ignicao,
            'endereco' => $enderecoFinal,
        ];
        if ($bat !== null) {
            $cacheUltima['bateria_pct'] = $bat;
        }
        if ($acc !== null) {
            $cacheUltima['accuracy_metros'] = $acc;
        }
        if ($prov !== null) {
            $cacheUltima['provider'] = $prov;
        }
        if ($mockVal !== null) {
            $cacheUltima['location_mock'] = $mockVal;
        }
        gps_redis_cache_set_ultima($empresa_id, $veiculo_id, $cacheUltima);

        try {
            gps_operacional_processar_pos_gravada(
                $conn,
                $empresa_id,
                $veiculo_id,
                $motorista_id,
                $data_hora,
                $latitude,
                $longitude,
                $velocidade_kmh,
                $bat,
                $mockVal,
                $acc,
                $prevCtx,
                $status,
                $ignicao
            );
        } catch (Throwable $e) {
            error_log('gps_operacional_processar: ' . $e->getMessage());
        }

        if (random_int(1, 120) === 1) {
            try {
                $conn->exec('DELETE FROM gps_logs WHERE data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)');
            } catch (PDOException $e) {
                error_log('gps_logs purge: ' . $e->getMessage());
            }
        }

        return ['id' => $newId];
    }
}

if (!function_exists('gps_motorista_insert_log')) {
    /**
     * @param array<string, mixed> $insParams
     */
    function gps_motorista_insert_log(
        PDO $conn,
        array $insParams,
        ?int $bat,
        ?float $acc,
        ?string $prov,
        ?int $mockVal,
        string $status_log
    ): int|string {
        $attempts = [
            function () use ($conn, $insParams, $bat, $acc, $prov, $mockVal, $status_log) {
                $sql = '
                    INSERT INTO gps_logs (
                        empresa_id, veiculo_id, motorista_id, latitude, longitude, velocidade,
                        bateria_pct, accuracy_metros, provider, location_mock, status, data_hora
                    ) VALUES (
                        :eid, :vid, :mid, :lat, :lng, :vel,
                        :bat, :acc, :prov, :mock, :stlog, :dh
                    )';
                $p = $insParams + [
                    ':bat' => $bat,
                    ':acc' => $acc,
                    ':prov' => $prov,
                    ':mock' => $mockVal,
                    ':stlog' => $status_log,
                ];
                $conn->prepare($sql)->execute($p);
            },
            function () use ($conn, $insParams, $bat, $acc, $prov, $mockVal) {
                $sql = '
                    INSERT INTO gps_logs (
                        empresa_id, veiculo_id, motorista_id, latitude, longitude, velocidade,
                        bateria_pct, accuracy_metros, provider, location_mock, data_hora
                    ) VALUES (
                        :eid, :vid, :mid, :lat, :lng, :vel,
                        :bat, :acc, :prov, :mock, :dh
                    )';
                $p = $insParams + [
                    ':bat' => $bat,
                    ':acc' => $acc,
                    ':prov' => $prov,
                    ':mock' => $mockVal,
                ];
                $conn->prepare($sql)->execute($p);
            },
            function () use ($conn, $insParams, $bat, $status_log) {
                $sql = '
                    INSERT INTO gps_logs (
                        empresa_id, veiculo_id, motorista_id, latitude, longitude, velocidade,
                        bateria_pct, status, data_hora
                    ) VALUES (
                        :eid, :vid, :mid, :lat, :lng, :vel, :bat, :stlog, :dh
                    )';
                $conn->prepare($sql)->execute($insParams + [
                    ':bat' => $bat,
                    ':stlog' => $status_log,
                ]);
            },
            function () use ($conn, $insParams, $bat) {
                if ($bat !== null) {
                    $sql = '
                        INSERT INTO gps_logs (empresa_id, veiculo_id, motorista_id, latitude, longitude, velocidade, bateria_pct, data_hora)
                        VALUES (:eid, :vid, :mid, :lat, :lng, :vel, :bat, :dh)';
                    $conn->prepare($sql)->execute($insParams + [':bat' => $bat]);
                } else {
                    $sql = '
                        INSERT INTO gps_logs (empresa_id, veiculo_id, motorista_id, latitude, longitude, velocidade, data_hora)
                        VALUES (:eid, :vid, :mid, :lat, :lng, :vel, :dh)';
                    $conn->prepare($sql)->execute($insParams);
                }
            },
        ];

        $lastEx = null;
        foreach ($attempts as $fn) {
            try {
                $fn();

                return $conn->lastInsertId();
            } catch (PDOException $e) {
                $lastEx = $e;
                $msg = $e->getMessage();
                if (strpos($msg, 'Unknown column') === false
                    && stripos($msg, "Data truncated for column 'status'") === false) {
                    throw $e;
                }
            }
        }
        if ($lastEx) {
            throw $lastEx;
        }
        throw new RuntimeException('gps_motorista_insert_log: falha');
    }
}

if (!function_exists('gps_motorista_upsert_ultima')) {
    /**
     * @param array<string, mixed> $paramsUltima
     */
    function gps_motorista_upsert_ultima(PDO $conn, array $paramsUltima, ?int $bat): void
    {
        $fullBat = '
            INSERT INTO gps_ultima_posicao (
                veiculo_id, empresa_id, motorista_id, latitude, longitude, velocidade,
                status, parado_desde, ignicao, ultima_atualizacao, endereco, data_hora,
                bateria_pct, accuracy_metros, provider, location_mock
            )
            VALUES (:vid, :eid, :mid, :lat, :lng, :vel, :st, :pd, :ign, :ua, :end, :dh, :bat, :acc, :prov, :mock)
            ON DUPLICATE KEY UPDATE
                empresa_id = VALUES(empresa_id),
                motorista_id = VALUES(motorista_id),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                velocidade = VALUES(velocidade),
                status = VALUES(status),
                parado_desde = VALUES(parado_desde),
                ignicao = VALUES(ignicao),
                ultima_atualizacao = VALUES(ultima_atualizacao),
                endereco = VALUES(endereco),
                data_hora = VALUES(data_hora),
                bateria_pct = VALUES(bateria_pct),
                accuracy_metros = VALUES(accuracy_metros),
                provider = VALUES(provider),
                location_mock = VALUES(location_mock)
        ';

        $tries = [];
        if ($bat !== null) {
            $tries[] = function () use ($conn, $paramsUltima, $fullBat, $bat) {
                $conn->prepare($fullBat)->execute($paramsUltima + [':bat' => $bat]);
            };
        }
        $tries[] = function () use ($conn, $paramsUltima, $bat) {
            $sql = '
                INSERT INTO gps_ultima_posicao (
                    veiculo_id, empresa_id, motorista_id, latitude, longitude, velocidade,
                    status, parado_desde, ignicao, ultima_atualizacao, endereco, data_hora,
                    accuracy_metros, provider, location_mock
                )
                VALUES (:vid, :eid, :mid, :lat, :lng, :vel, :st, :pd, :ign, :ua, :end, :dh, :acc, :prov, :mock)
                ON DUPLICATE KEY UPDATE
                    empresa_id = VALUES(empresa_id),
                    motorista_id = VALUES(motorista_id),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    velocidade = VALUES(velocidade),
                    status = VALUES(status),
                    parado_desde = VALUES(parado_desde),
                    ignicao = VALUES(ignicao),
                    ultima_atualizacao = VALUES(ultima_atualizacao),
                    endereco = VALUES(endereco),
                    data_hora = VALUES(data_hora),
                    accuracy_metros = VALUES(accuracy_metros),
                    provider = VALUES(provider),
                    location_mock = VALUES(location_mock)
            ';
            $conn->prepare($sql)->execute($paramsUltima);
        };
        $tries[] = function () use ($conn, $paramsUltima) {
            $sql = '
                INSERT INTO gps_ultima_posicao (
                    veiculo_id, empresa_id, motorista_id, latitude, longitude, velocidade,
                    status, ignicao, ultima_atualizacao, endereco, data_hora
                )
                VALUES (:vid, :eid, :mid, :lat, :lng, :vel, :st, :ign, :ua, :end, :dh)
                ON DUPLICATE KEY UPDATE
                    empresa_id = VALUES(empresa_id),
                    motorista_id = VALUES(motorista_id),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    velocidade = VALUES(velocidade),
                    status = VALUES(status),
                    ignicao = VALUES(ignicao),
                    ultima_atualizacao = VALUES(ultima_atualizacao),
                    endereco = VALUES(endereco),
                    data_hora = VALUES(data_hora)
            ';
            $stVal = (string) $paramsUltima[':st'];
            if ($stVal === 'ocioso') {
                $stVal = 'parado';
            }
            $legacy = [
                ':vid' => $paramsUltima[':vid'],
                ':eid' => $paramsUltima[':eid'],
                ':mid' => $paramsUltima[':mid'],
                ':lat' => $paramsUltima[':lat'],
                ':lng' => $paramsUltima[':lng'],
                ':vel' => $paramsUltima[':vel'],
                ':st' => $stVal,
                ':ign' => $paramsUltima[':ign'],
                ':ua' => $paramsUltima[':ua'],
                ':end' => $paramsUltima[':end'],
                ':dh' => $paramsUltima[':dh'],
            ];
            $conn->prepare($sql)->execute($legacy);
        };
        $tries[] = function () use ($conn, $paramsUltima) {
            $sql = '
                INSERT INTO gps_ultima_posicao (veiculo_id, empresa_id, motorista_id, latitude, longitude, velocidade, data_hora)
                VALUES (:vid, :eid, :mid, :lat, :lng, :vel, :dh)
                ON DUPLICATE KEY UPDATE
                    empresa_id = VALUES(empresa_id),
                    motorista_id = VALUES(motorista_id),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    velocidade = VALUES(velocidade),
                    data_hora = VALUES(data_hora)
            ';
            $conn->prepare($sql)->execute([
                ':vid' => $paramsUltima[':vid'],
                ':eid' => $paramsUltima[':eid'],
                ':mid' => $paramsUltima[':mid'],
                ':lat' => $paramsUltima[':lat'],
                ':lng' => $paramsUltima[':lng'],
                ':vel' => $paramsUltima[':vel'],
                ':dh' => $paramsUltima[':dh'],
            ]);
        };

        $lastEx = null;
        foreach ($tries as $fn) {
            try {
                $fn();

                return;
            } catch (PDOException $e) {
                $lastEx = $e;
                $msg = $e->getMessage();
                $retry = strpos($msg, 'Unknown column') !== false
                    || stripos($msg, "Data truncated for column 'status'") !== false;
                if (!$retry) {
                    throw $e;
                }
            }
        }
        if ($lastEx) {
            throw $lastEx;
        }
    }
}
