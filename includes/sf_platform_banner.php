<?php
declare(strict_types=1);

/**
 * Banner ativo de comunicação da plataforma (Control Center → header do frotas).
 */

function sf_platform_banner_cache_key(): string
{
    return 'platform_banner_ativo';
}

/** @return array{id:int,titulo:string,mensagem:string,canal:string}|null */
function sf_platform_banner_atual(PDO $pdo, int $empresaClienteId): ?array
{
    try {
        $st = $pdo->prepare('SELECT valor FROM adm_platform_config WHERE chave = ? LIMIT 1');
        $st->execute([sf_platform_banner_cache_key()]);
        $raw = $st->fetchColumn();
        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data) || empty($data['titulo'])) {
            return null;
        }
        if (!empty($data['ate']) && strtotime((string) $data['ate']) < time()) {
            return null;
        }
        $eaAlvo = isset($data['empresa_adm_id']) ? (int) $data['empresa_adm_id'] : 0;
        if ($eaAlvo > 0) {
            $stEc = $pdo->prepare('SELECT empresa_adm_id FROM empresa_clientes WHERE id = ? LIMIT 1');
            $stEc->execute([$empresaClienteId]);
            $eaUser = (int) ($stEc->fetchColumn() ?: 0);
            if ($eaUser !== $eaAlvo) {
                return null;
            }
        }
        return [
            'id' => (int) ($data['comunicacao_id'] ?? 0),
            'rev' => (string) ($data['rev'] ?? ''),
            'titulo' => (string) $data['titulo'],
            'mensagem' => (string) ($data['mensagem'] ?? ''),
            'canal' => (string) ($data['canal'] ?? 'banner'),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function sf_platform_banner_salvar(PDO $pdo, int $comunicacaoId, string $titulo, string $mensagem, ?int $empresaAdmId, int $dias = 14): void
{
    $payload = json_encode([
        'comunicacao_id' => $comunicacaoId,
        'rev' => (string) time(),
        'titulo' => $titulo,
        'mensagem' => $mensagem,
        'empresa_adm_id' => $empresaAdmId,
        'canal' => 'banner',
        'ate' => date('Y-m-d H:i:s', strtotime('+' . max(1, $dias) . ' days')),
    ], JSON_UNESCAPED_UNICODE);
    try {
        $pdo->prepare('INSERT INTO adm_platform_config (chave, valor, categoria) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), categoria = VALUES(categoria)')
            ->execute([sf_platform_banner_cache_key(), $payload, 'comunicacao']);
    } catch (Throwable $e) {
        error_log('sf_platform_banner_salvar: ' . $e->getMessage());
    }
}
