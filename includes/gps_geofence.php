<?php
/**
 * Cercas eletrônicas: detecta entrada/saída ao gravar GPS (Haversine).
 */

if (!function_exists('gps_haversine_metros')) {
    function gps_haversine_metros(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000.0;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlambda = deg2rad($lon2 - $lon1);
        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
        return $R * $c;
    }
}

if (!function_exists('gps_ultima_ler_anterior')) {
    /**
     * Posição antes do próximo upsert (para comparar transição de cerca).
     *
     * @return array{latitude: float, longitude: float}|null
     */
    function gps_ultima_ler_anterior(PDO $conn, int $veiculo_id, int $empresa_id): ?array
    {
        try {
            $st = $conn->prepare('
                SELECT latitude, longitude FROM gps_ultima_posicao
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
            ];
        } catch (PDOException $e) {
            return null;
        }
    }
}

if (!function_exists('gps_cerca_alerta_recente')) {
    function gps_cerca_alerta_recente(
        PDO $conn,
        int $veiculo_id,
        int $cerca_id,
        string $tipo,
        string $data_hora_ref,
        int $segundos
    ): bool {
        if ($segundos <= 0) {
            return false;
        }
        $seg = max(0, (int) $segundos);
        try {
            $sql = '
                SELECT 1 FROM gps_cerca_alertas
                WHERE veiculo_id = :vid AND cerca_id = :cid AND tipo = :tipo
                  AND data_hora > DATE_SUB(:dh, INTERVAL ' . $seg . ' SECOND)
                LIMIT 1
            ';
            $st = $conn->prepare($sql);
            $st->execute([
                ':vid' => $veiculo_id,
                ':cid' => $cerca_id,
                ':tipo' => $tipo,
                ':dh' => $data_hora_ref,
            ]);
            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('gps_geofence_carregar_estado')) {
    /**
     * @return array<int, array{dentro: int, primeiro_dentro_em: ?string, permanencia_alertada: int}>
     */
    function gps_geofence_carregar_estado(PDO $conn, int $empresa_id, int $veiculo_id): array
    {
        try {
            $st = $conn->prepare('
                SELECT cerca_id, dentro, primeiro_dentro_em, permanencia_alertada
                FROM gps_cerca_estado
                WHERE empresa_id = :eid AND veiculo_id = :vid
            ');
            $st->execute([':eid' => $empresa_id, ':vid' => $veiculo_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $r) {
            $cid = (int) $r['cerca_id'];
            $map[$cid] = [
                'dentro' => (int) $r['dentro'],
                'primeiro_dentro_em' => $r['primeiro_dentro_em'] !== null ? (string) $r['primeiro_dentro_em'] : null,
                'permanencia_alertada' => (int) $r['permanencia_alertada'],
            ];
        }
        return $map;
    }
}

if (!function_exists('gps_geofence_registrar_transicoes')) {
    /**
     * @param array{latitude: float, longitude: float}|null $prevUltima
     */
    function gps_geofence_registrar_transicoes(
        PDO $conn,
        int $empresa_id,
        int $veiculo_id,
        int $motorista_id,
        float $latitude,
        float $longitude,
        ?array $prevUltima,
        string $data_hora
    ): void {
        try {
            $st = $conn->prepare('
                SELECT id, nome, latitude, longitude, raio_metros
                FROM gps_cercas
                WHERE empresa_id = :eid AND ativo = 1
            ');
            $st->execute([':eid' => $empresa_id]);
            $cercas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return;
        }
        if (!$cercas) {
            return;
        }

        $debounceSeg = (int) (getenv('SF_GPS_CERCA_DEBOUNCE_SEG') ?: 120);
        $permanenciaSeg = (int) (getenv('SF_GPS_CERCA_PERMANENCIA_SEG') ?: 120);

        $prevLat = $prevUltima['latitude'] ?? null;
        $prevLng = $prevUltima['longitude'] ?? null;

        $useEstadoTabela = true;
        try {
            $conn->query('SELECT 1 FROM gps_cerca_estado LIMIT 1');
        } catch (PDOException $e) {
            $useEstadoTabela = false;
        }

        $estadoMap = $useEstadoTabela ? gps_geofence_carregar_estado($conn, $empresa_id, $veiculo_id) : [];

        $ins = $conn->prepare('
            INSERT INTO gps_cerca_alertas
            (empresa_id, cerca_id, veiculo_id, motorista_id, tipo, latitude, longitude, data_hora)
            VALUES (:eid, :cid, :vid, :mid, :tipo, :lat, :lng, :dh)
        ');

        $upsertEstado = null;
        if ($useEstadoTabela) {
            try {
                $upsertEstado = $conn->prepare('
                    INSERT INTO gps_cerca_estado (veiculo_id, cerca_id, empresa_id, dentro, primeiro_dentro_em, permanencia_alertada)
                    VALUES (:vid, :cid, :eid, :dentro, :pde, :pal)
                    ON DUPLICATE KEY UPDATE
                        empresa_id = VALUES(empresa_id),
                        dentro = VALUES(dentro),
                        primeiro_dentro_em = VALUES(primeiro_dentro_em),
                        permanencia_alertada = VALUES(permanencia_alertada)
                ');
            } catch (PDOException $e) {
                $upsertEstado = null;
                $useEstadoTabela = false;
            }
        }

        $tsRef = strtotime($data_hora) ?: time();

        foreach ($cercas as $c) {
            $clat = (float) $c['latitude'];
            $clng = (float) $c['longitude'];
            $raio = (int) $c['raio_metros'];
            $cercaId = (int) $c['id'];

            $distNow = gps_haversine_metros($latitude, $longitude, $clat, $clng);
            $nowIn = $distNow <= $raio;

            $wasIn = false;
            if ($prevLat !== null && $prevLng !== null) {
                $distPrev = gps_haversine_metros($prevLat, $prevLng, $clat, $clng);
                $wasIn = $distPrev <= $raio;
            }

            $est = $estadoMap[$cercaId] ?? ['dentro' => 0, 'primeiro_dentro_em' => null, 'permanencia_alertada' => 0];

            try {
                if (!$nowIn) {
                    if ($wasIn && !gps_cerca_alerta_recente($conn, $veiculo_id, $cercaId, 'saiu', $data_hora, $debounceSeg)) {
                        $ins->execute([
                            ':eid' => $empresa_id,
                            ':cid' => $cercaId,
                            ':vid' => $veiculo_id,
                            ':mid' => $motorista_id,
                            ':tipo' => 'saiu',
                            ':lat' => round($latitude, 8),
                            ':lng' => round($longitude, 8),
                            ':dh' => $data_hora,
                        ]);
                    }
                    if ($est['dentro'] && $upsertEstado) {
                        $upsertEstado->execute([
                            ':vid' => $veiculo_id,
                            ':cid' => $cercaId,
                            ':eid' => $empresa_id,
                            ':dentro' => 0,
                            ':pde' => null,
                            ':pal' => 0,
                        ]);
                        $estadoMap[$cercaId] = ['dentro' => 0, 'primeiro_dentro_em' => null, 'permanencia_alertada' => 0];
                    }
                    continue;
                }

                if (!$wasIn && $nowIn) {
                    if (!gps_cerca_alerta_recente($conn, $veiculo_id, $cercaId, 'entrou', $data_hora, $debounceSeg)) {
                        $ins->execute([
                            ':eid' => $empresa_id,
                            ':cid' => $cercaId,
                            ':vid' => $veiculo_id,
                            ':mid' => $motorista_id,
                            ':tipo' => 'entrou',
                            ':lat' => round($latitude, 8),
                            ':lng' => round($longitude, 8),
                            ':dh' => $data_hora,
                        ]);
                    }
                    if ($upsertEstado) {
                        $upsertEstado->execute([
                            ':vid' => $veiculo_id,
                            ':cid' => $cercaId,
                            ':eid' => $empresa_id,
                            ':dentro' => 1,
                            ':pde' => $data_hora,
                            ':pal' => 0,
                        ]);
                    }
                    $estadoMap[$cercaId] = [
                        'dentro' => 1,
                        'primeiro_dentro_em' => $data_hora,
                        'permanencia_alertada' => 0,
                    ];
                    continue;
                }

                if ($wasIn && $nowIn && $useEstadoTabela && $upsertEstado) {
                    $primeiro = $est['primeiro_dentro_em'] ?? null;
                    if ($primeiro === null || $primeiro === '') {
                        $primeiro = $data_hora;
                        $upsertEstado->execute([
                            ':vid' => $veiculo_id,
                            ':cid' => $cercaId,
                            ':eid' => $empresa_id,
                            ':dentro' => 1,
                            ':pde' => $primeiro,
                            ':pal' => (int) $est['permanencia_alertada'],
                        ]);
                        $estadoMap[$cercaId] = [
                            'dentro' => 1,
                            'primeiro_dentro_em' => $primeiro,
                            'permanencia_alertada' => (int) $est['permanencia_alertada'],
                        ];
                    }
                    $t0 = strtotime((string) $primeiro) ?: $tsRef;
                    if (
                        !$est['permanencia_alertada']
                        && ($tsRef - $t0) >= $permanenciaSeg
                        && !gps_cerca_alerta_recente($conn, $veiculo_id, $cercaId, 'permanencia', $data_hora, $debounceSeg)
                    ) {
                        try {
                            $ins->execute([
                                ':eid' => $empresa_id,
                                ':cid' => $cercaId,
                                ':vid' => $veiculo_id,
                                ':mid' => $motorista_id,
                                ':tipo' => 'permanencia',
                                ':lat' => round($latitude, 8),
                                ':lng' => round($longitude, 8),
                                ':dh' => $data_hora,
                            ]);
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'permanencia') !== false || strpos($e->getMessage(), 'Data truncated') !== false) {
                                error_log('gps_geofence: execute sql/alter_gps_cerca_alertas_permanencia.sql para alertas de permanência.');
                            } else {
                                throw $e;
                            }
                        }
                        $upsertEstado->execute([
                            ':vid' => $veiculo_id,
                            ':cid' => $cercaId,
                            ':eid' => $empresa_id,
                            ':dentro' => 1,
                            ':pde' => $primeiro,
                            ':pal' => 1,
                        ]);
                        $estadoMap[$cercaId]['permanencia_alertada'] = 1;
                    }
                }
            } catch (PDOException $e) {
                error_log('gps_geofence alerta: ' . $e->getMessage());
            }
        }
    }
}
