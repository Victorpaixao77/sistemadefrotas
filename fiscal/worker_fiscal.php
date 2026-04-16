<?php
/**
 * Worker fiscal (CLI): fila + retry + monitor/auditoria.
 *
 * Exemplo cron (Linux):
 * * * * * /usr/bin/php /caminho/projeto/fiscal/worker_fiscal.php >> /tmp/worker_fiscal.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Use apenas via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/MdfeService.php';
require_once __DIR__ . '/includes/FiscalQueueService.php';

$conn = getConnection();
FiscalQueueService::ensureSchema($conn);

$limit = 20;
$jobs = FiscalQueueService::nextBatch($conn, $limit);
$processados = 0;
$sucessos = 0;
$falhas = 0;

foreach ($jobs as $job) {
    $processados++;
    $jobId = (int) ($job['id'] ?? 0);
    $empresaId = (int) ($job['empresa_id'] ?? 0);
    $tipo = (string) ($job['tipo_documento'] ?? '');
    $acao = (string) ($job['acao'] ?? '');
    $tentativas = (int) ($job['tentativas'] ?? 0) + 1;
    $maxTentativas = (int) ($job['max_tentativas'] ?? 5);
    $payload = json_decode((string) ($job['payload_json'] ?? '{}'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    FiscalQueueService::markProcessing($conn, $jobId);

    try {
        if ($tipo !== 'mdfe' || $acao !== 'emitir') {
            throw new RuntimeException("Job não suportado: {$tipo}/{$acao}");
        }

        $docId = (int) ($payload['id'] ?? 0);
        if ($docId <= 0) {
            throw new RuntimeException('Payload sem ID do MDF-e.');
        }

        $stmt = $conn->prepare("
            SELECT *
            FROM fiscal_mdfe
            WHERE id = ? AND empresa_id = ?
            LIMIT 1
        ");
        $stmt->execute([$docId, $empresaId]);
        $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mdfe) {
            throw new RuntimeException('MDF-e não encontrado para processamento.');
        }

        $st = strtolower(trim((string) ($mdfe['status'] ?? '')));
        if ($st === 'autorizado' || $st === 'cancelado' || $st === 'encerrado') {
            FiscalQueueService::markSuccess($conn, $jobId);
            $sucessos++;
            continue;
        }

        $service = new MdfeService($empresaId);
        $res = $service->emitir($mdfe);
        if (!empty($res['sucesso'])) {
            $stmtUp = $conn->prepare("
                UPDATE fiscal_mdfe
                SET status = ?,
                    protocolo_autorizacao = ?,
                    data_autorizacao = ?,
                    xml_mdfe = COALESCE(?, xml_mdfe),
                    chave_acesso = COALESCE(NULLIF(?, ''), chave_acesso),
                    updated_at = NOW()
                WHERE id = ? AND empresa_id = ?
            ");
            $stmtUp->execute([
                $res['status'] ?? 'pendente',
                $res['protocolo'] ?? null,
                date('Y-m-d H:i:s'),
                $res['xml_assinado'] ?? null,
                $res['chave_acesso'] ?? '',
                $docId,
                $empresaId,
            ]);

            FiscalQueueService::markSuccess($conn, $jobId);
            $sucessos++;
            continue;
        }

        $erro = (string) ($res['erro'] ?? 'Falha desconhecida no envio MDF-e.');
        FiscalQueueService::markRetry($conn, $jobId, $erro, $tentativas, $maxTentativas);
        $falhas++;
    } catch (Throwable $e) {
        FiscalQueueService::markRetry($conn, $jobId, $e->getMessage(), $tentativas, $maxTentativas);
        $falhas++;
    }
}

/**
 * Auditoria automática (alertas):
 * 1) MDF-e autorizado sem encerramento há mais de 24h.
 * 2) CT-e autorizado sem vínculo em MDF-e há mais de 24h.
 */
try {
    $sqlMdfeAberto = "
        SELECT m.id, m.empresa_id, m.numero_mdfe, m.data_autorizacao, m.data_emissao
        FROM fiscal_mdfe m
        WHERE m.status = 'autorizado'
          AND (m.data_encerramento IS NULL OR m.data_encerramento = '0000-00-00 00:00:00')
          AND COALESCE(m.data_autorizacao, CONCAT(m.data_emissao, ' 00:00:00')) <= (NOW() - INTERVAL 24 HOUR)
        LIMIT 200
    ";
    foreach ($conn->query($sqlMdfeAberto)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $empresa = (int) $row['empresa_id'];
        $numero = (string) ($row['numero_mdfe'] ?? $row['id']);
        $titulo = 'MDF-e não encerrado';
        $mensagem = "MDF-e #{$numero} autorizado há mais de 24h sem encerramento.";

        $stmtCheck = $conn->prepare("
            SELECT id FROM fiscal_alertas
            WHERE empresa_id = ? AND tipo_alerta = 'mdfe_nao_encerrado' AND status = 'ativo' AND mensagem = ?
            LIMIT 1
        ");
        $stmtCheck->execute([$empresa, $mensagem]);
        if (!$stmtCheck->fetch()) {
            $stmtIns = $conn->prepare("
                INSERT INTO fiscal_alertas (empresa_id, tipo_alerta, titulo, mensagem, nivel, status, acao_requerida)
                VALUES (?, 'mdfe_nao_encerrado', ?, ?, 'alto', 'ativo', 'Encerrar MDF-e na SEFAZ e no sistema.')
            ");
            $stmtIns->execute([$empresa, $titulo, $mensagem]);
        }
    }

    $sqlCteSemMdfe = "
        SELECT c.id, c.empresa_id, c.numero_cte, c.data_emissao
        FROM fiscal_cte c
        LEFT JOIN fiscal_mdfe_cte mc ON mc.cte_id = c.id
        WHERE c.status = 'autorizado'
          AND mc.id IS NULL
          AND CONCAT(c.data_emissao, ' 00:00:00') <= (NOW() - INTERVAL 24 HOUR)
        LIMIT 200
    ";
    foreach ($conn->query($sqlCteSemMdfe)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $empresa = (int) $row['empresa_id'];
        $numero = (string) ($row['numero_cte'] ?? $row['id']);
        $titulo = 'CT-e sem MDF-e';
        $mensagem = "CT-e #{$numero} autorizado sem MDF-e vinculado há mais de 24h.";

        $stmtCheck = $conn->prepare("
            SELECT id FROM fiscal_alertas
            WHERE empresa_id = ? AND tipo_alerta = 'documento_pendente' AND status = 'ativo' AND mensagem = ?
            LIMIT 1
        ");
        $stmtCheck->execute([$empresa, $mensagem]);
        if (!$stmtCheck->fetch()) {
            $stmtIns = $conn->prepare("
                INSERT INTO fiscal_alertas (empresa_id, tipo_alerta, titulo, mensagem, nivel, status, acao_requerida)
                VALUES (?, 'documento_pendente', ?, ?, 'medio', 'ativo', 'Vincular CT-e a um MDF-e autorizado.')
            ");
            $stmtIns->execute([$empresa, $titulo, $mensagem]);
        }
    }
} catch (Throwable $e) {
    // não interromper o worker por falha de auditoria
}

echo json_encode([
    'ok' => true,
    'processados' => $processados,
    'sucessos' => $sucessos,
    'falhas' => $falhas,
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;

