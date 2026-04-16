<?php
/**
 * Leituras GPS para API com token de motorista (app /api/gps/*).
 */

if (!function_exists('gps_motorista_api_veiculo_empresa')) {
    /**
     * @return array{id:int,placa:string}|null
     */
    function gps_motorista_api_veiculo_empresa(PDO $conn, int $veiculo_id, int $empresa_id): ?array
    {
        $st = $conn->prepare('SELECT id, placa FROM veiculos WHERE id = :id AND empresa_id = :e LIMIT 1');
        $st->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ['id' => (int) $row['id'], 'placa' => (string) $row['placa']];
    }

    /**
     * @return list<array<string,mixed>>
     */
    function gps_motorista_api_historico_pontos(
        PDO $conn,
        int $empresa_id,
        int $veiculo_id,
        DateTimeImmutable $dtIni,
        DateTimeImmutable $dtFim,
        int $limite
    ): array {
        $limite = max(1, min(5000, $limite));
        $paramsH = [
            ':eid' => $empresa_id,
            ':vid' => $veiculo_id,
            ':ini' => $dtIni->format('Y-m-d H:i:s'),
            ':fim' => $dtFim->format('Y-m-d H:i:s'),
        ];
        $sqlExt = '
            SELECT latitude, longitude, velocidade, data_hora, accuracy_metros, status, provider, location_mock
            FROM gps_logs
            WHERE empresa_id = :eid
              AND veiculo_id = :vid
              AND data_hora >= :ini
              AND data_hora <= :fim
            ORDER BY data_hora ASC
            LIMIT ' . (int) $limite;
        $sqlBase = '
            SELECT latitude, longitude, velocidade, data_hora
            FROM gps_logs
            WHERE empresa_id = :eid
              AND veiculo_id = :vid
              AND data_hora >= :ini
              AND data_hora <= :fim
            ORDER BY data_hora ASC
            LIMIT ' . (int) $limite;
        try {
            $stmt = $conn->prepare($sqlExt);
            $stmt->execute($paramsH);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }
            $stmt = $conn->prepare($sqlBase);
            $stmt->execute($paramsH);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($rows as &$r) {
            $r['latitude'] = $r['latitude'] !== null ? (float) $r['latitude'] : null;
            $r['longitude'] = $r['longitude'] !== null ? (float) $r['longitude'] : null;
            $r['velocidade'] = $r['velocidade'] !== null ? (float) $r['velocidade'] : null;
            if (array_key_exists('accuracy_metros', $r) && $r['accuracy_metros'] !== null && $r['accuracy_metros'] !== '') {
                $r['accuracy_metros'] = (float) $r['accuracy_metros'];
            }
            if (array_key_exists('location_mock', $r) && $r['location_mock'] !== null && $r['location_mock'] !== '') {
                $r['location_mock'] = (int) $r['location_mock'];
            }
        }
        unset($r);

        return $rows;
    }
}
