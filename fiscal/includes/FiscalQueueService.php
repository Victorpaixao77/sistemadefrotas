<?php

/**
 * Fila fiscal simples (em banco) para processamento assíncrono e retry.
 */
class FiscalQueueService
{
    public static function ensureSchema(PDO $conn): void
    {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS fiscal_fila_processamento (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                tipo_documento VARCHAR(10) NOT NULL,
                acao VARCHAR(30) NOT NULL,
                payload_json LONGTEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pendente',
                tentativas INT NOT NULL DEFAULT 0,
                max_tentativas INT NOT NULL DEFAULT 5,
                erro_ultimo TEXT NULL,
                proxima_tentativa_em DATETIME NULL,
                processado_em DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_empresa_status (empresa_id, status),
                INDEX idx_next_try (status, proxima_tentativa_em),
                INDEX idx_tipo_acao (tipo_documento, acao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function enqueue(
        PDO $conn,
        int $empresaId,
        string $tipoDocumento,
        string $acao,
        array $payload,
        int $maxTentativas = 5
    ): int {
        self::ensureSchema($conn);
        $stmt = $conn->prepare("
            INSERT INTO fiscal_fila_processamento
                (empresa_id, tipo_documento, acao, payload_json, status, tentativas, max_tentativas, proxima_tentativa_em)
            VALUES
                (:empresa_id, :tipo, :acao, :payload, 'pendente', 0, :max_tentativas, NOW())
        ");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $tipoDocumento,
            ':acao' => $acao,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':max_tentativas' => max(1, $maxTentativas),
        ]);
        return (int) $conn->lastInsertId();
    }

    public static function nextBatch(PDO $conn, int $limit = 20): array
    {
        self::ensureSchema($conn);
        $stmt = $conn->prepare("
            SELECT *
            FROM fiscal_fila_processamento
            WHERE status IN ('pendente', 'erro')
              AND COALESCE(proxima_tentativa_em, NOW()) <= NOW()
              AND tentativas < max_tentativas
            ORDER BY COALESCE(proxima_tentativa_em, created_at) ASC, id ASC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function markProcessing(PDO $conn, int $id): void
    {
        $stmt = $conn->prepare("
            UPDATE fiscal_fila_processamento
            SET status = 'processando', tentativas = tentativas + 1, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
    }

    public static function markSuccess(PDO $conn, int $id): void
    {
        $stmt = $conn->prepare("
            UPDATE fiscal_fila_processamento
            SET status = 'sucesso', processado_em = NOW(), erro_ultimo = NULL, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
    }

    public static function markRetry(
        PDO $conn,
        int $id,
        string $erro,
        int $tentativasJaFeitas,
        int $maxTentativas
    ): void {
        $esgotou = $tentativasJaFeitas >= $maxTentativas;
        $delayMin = min(60, max(1, (int) pow(2, max(0, $tentativasJaFeitas - 1))));
        $stmt = $conn->prepare("
            UPDATE fiscal_fila_processamento
            SET status = :status,
                erro_ultimo = :erro,
                proxima_tentativa_em = :proxima,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $esgotou ? 'falha' : 'erro',
            ':erro' => mb_substr($erro, 0, 2000),
            ':proxima' => date('Y-m-d H:i:s', time() + ($delayMin * 60)),
            ':id' => $id,
        ]);
    }
}

