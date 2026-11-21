<?php
declare(strict_types=1);

/**
 * Registro de uso de IA no Control Center (adm_ia_usage).
 */
function adm_ia_usage_log(
    PDO $pdo,
    string $modelo,
    int $tokensIn = 0,
    int $tokensOut = 0,
    ?int $duracaoMs = null,
    ?float $custoEstimado = null,
    ?int $empresaClienteId = null,
    bool $sucesso = true
): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO adm_ia_usage
            (empresa_cliente_id, modelo, tokens_in, tokens_out, duracao_ms, custo_estimado, sucesso)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $empresaClienteId,
            substr($modelo, 0, 64),
            max(0, $tokensIn),
            max(0, $tokensOut),
            $duracaoMs,
            $custoEstimado,
            $sucesso ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        error_log('adm_ia_usage_log: ' . $e->getMessage());
    }
}

/**
 * Executa callable medindo tempo e registra uso (motor de regras ou API).
 */
function adm_ia_usage_timed(PDO $pdo, string $modelo, callable $fn, ?int $empresaClienteId = null)
{
    $t0 = hrtime(true);
    $ok = true;
    try {
        return $fn();
    } catch (Throwable $e) {
        $ok = false;
        throw $e;
    } finally {
        $ms = (int) round((hrtime(true) - $t0) / 1e6);
        adm_ia_usage_log($pdo, $modelo, 0, 0, $ms, null, $empresaClienteId, $ok);
    }
}
