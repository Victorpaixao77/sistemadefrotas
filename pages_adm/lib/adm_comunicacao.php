<?php
declare(strict_types=1);

/**
 * Envio de comunicações do Control Center → notificações do frotas.
 */
require_once dirname(__DIR__, 2) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/sf_platform_banner.php';

/** @return int[] empresa_cliente_id */
function adm_comunicacao_empresas_clientes(PDO $pdo, ?int $empresaAdmId): array
{
    try {
        if ($empresaAdmId) {
            $st = $pdo->prepare("SELECT ec.id FROM empresa_clientes ec
                JOIN empresa_adm ea ON ea.id = ec.empresa_adm_id
                WHERE ea.id = ? AND ec.status = 'ativo' AND ea.status_saas IN ('ativa','trial')");
            $st->execute([$empresaAdmId]);
        } else {
            $st = $pdo->query("SELECT ec.id FROM empresa_clientes ec
                JOIN empresa_adm ea ON ea.id = ec.empresa_adm_id
                WHERE ec.status = 'ativo' AND ea.status_saas IN ('ativa','trial')");
        }
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        error_log('adm_comunicacao_empresas_clientes: ' . $e->getMessage());
        return [];
    }
}

function adm_comunicacao_recipients(PDO $pdo, ?int $empresaAdmId): array
{
    if ($empresaAdmId) {
        $stmt = $pdo->prepare('SELECT email FROM empresa_adm WHERE id = ? AND email IS NOT NULL AND email != \'\' LIMIT 1');
        $stmt->execute([$empresaAdmId]);
        $email = $stmt->fetchColumn();
        return $email ? [(string) $email] : [];
    }
    $rows = $pdo->query("SELECT DISTINCT email FROM empresa_adm WHERE email IS NOT NULL AND email != '' AND status_saas IN ('ativa','trial')")->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(array_map('strval', $rows)));
}

function adm_comunicacao_smtp_cfg(PDO $pdo): array
{
    if (!function_exists('sf_security_config')) {
        require_once dirname(__DIR__, 2) . '/includes/security.php';
    }
    $empresaId = (int) $pdo->query('SELECT id FROM empresa_clientes ORDER BY id ASC LIMIT 1')->fetchColumn();
    return $empresaId > 0 ? sf_security_config($empresaId) : [];
}

function adm_comunicacao_enviar_email(PDO $pdo, array $recipients, string $titulo, string $mensagem): bool
{
    if (!$recipients) {
        return false;
    }
    $cfg = adm_comunicacao_smtp_cfg($pdo);
    if (empty($cfg['smtp_host'])) {
        return false;
    }
    $body = '<div style="font-family:sans-serif"><h2>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<div>' . nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '<p style="color:#888;font-size:12px">Frotec Control Center</p></div>';
    $ok = true;
    foreach ($recipients as $to) {
        if (!sf_smtp_send($cfg, $to, $titulo, $body)) {
            $ok = false;
        }
    }
    return $ok;
}

/**
 * Insere no sininho (tabela notificacoes) para cada empresa cliente alvo.
 */
function adm_comunicacao_inserir_notificacoes(
    PDO $pdo,
    string $titulo,
    string $mensagem,
    ?int $empresaAdmId,
    int $comunicacaoId,
    string $canal
): int {
    sf_notif_ensure_schema($pdo);
    $empresas = adm_comunicacao_empresas_clientes($pdo, $empresaAdmId);
    if (!$empresas) {
        return 0;
    }
    $inseridas = 0;
    $grupo = 'adm_com_' . $comunicacaoId;
    $metaStr = json_encode([
        'origem' => 'control_center',
        'canal' => $canal,
        'comunicacao_id' => $comunicacaoId,
        'empresa_adm_id' => $empresaAdmId,
    ], JSON_UNESCAPED_UNICODE);
    $ins = $pdo->prepare(
        'INSERT INTO notificacoes (empresa_id, tipo, severidade, grupo_chave, titulo, mensagem, referencia_id, meta_json, data_criacao, lida)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)'
    );
    foreach ($empresas as $ecId) {
        try {
            $chk = $pdo->prepare('SELECT id FROM notificacoes WHERE empresa_id = ? AND grupo_chave = ? LIMIT 1');
            $chk->execute([$ecId, $grupo]);
            if ($chk->fetch()) {
                $inseridas++;
                continue;
            }
            $ins->execute([$ecId, 'plataforma', 'aviso', $grupo, $titulo, $mensagem, $comunicacaoId, $metaStr]);
            $inseridas++;
        } catch (Throwable $e) {
            error_log('adm_comunicacao_inserir_notificacoes ec=' . $ecId . ': ' . $e->getMessage());
        }
    }
    return $inseridas;
}

/**
 * @return array{status:string, detalhe:string, notificacoes?:int}
 */
function adm_comunicacao_dispatch(
    PDO $pdo,
    string $canal,
    string $titulo,
    string $mensagem,
    ?int $empresaAdmId,
    int $comunicacaoId
): array {
    $canal = in_array($canal, ['email', 'whatsapp', 'push', 'banner', 'aviso'], true) ? $canal : 'aviso';

    if ($canal === 'email') {
        $recipients = adm_comunicacao_recipients($pdo, $empresaAdmId);
        if (!$recipients) {
            return ['status' => 'rascunho', 'detalhe' => 'Nenhum destinatário com e-mail válido.'];
        }
        if (adm_comunicacao_enviar_email($pdo, $recipients, $titulo, $mensagem)) {
            return ['status' => 'enviado', 'detalhe' => 'E-mail enviado para ' . count($recipients) . ' destinatário(s).'];
        }
        return ['status' => 'rascunho', 'detalhe' => 'SMTP não configurado ou falha no envio. Registro salvo como rascunho — configure SMTP em Configurações do frotas.'];
    }

    if ($canal === 'whatsapp') {
        return ['status' => 'rascunho', 'detalhe' => 'Canal WhatsApp registrado; integração de envio em desenvolvimento.'];
    }

    if ($canal === 'banner') {
        sf_platform_banner_salvar($pdo, $comunicacaoId, $titulo, $mensagem, $empresaAdmId);
        $n = adm_comunicacao_inserir_notificacoes($pdo, $titulo, $mensagem, $empresaAdmId, $comunicacaoId, $canal);
        $alvo = $empresaAdmId ? 'empresa selecionada' : 'todas as empresas';
        return [
            'status' => 'enviado',
            'detalhe' => 'Banner ativo no topo do sistema para ' . $alvo . ($n > 0 ? " · {$n} notificação(ões) no sininho." : ''),
            'notificacoes' => $n,
        ];
    }

    if (in_array($canal, ['push', 'aviso'], true)) {
        $n = adm_comunicacao_inserir_notificacoes($pdo, $titulo, $mensagem, $empresaAdmId, $comunicacaoId, $canal);
        if ($n === 0) {
            $empresas = adm_comunicacao_empresas_clientes($pdo, $empresaAdmId);
            if (!$empresas) {
                return ['status' => 'rascunho', 'detalhe' => 'Nenhuma empresa cliente ativa encontrada para receber o aviso.'];
            }
            return ['status' => 'rascunho', 'detalhe' => 'Aviso já enviado recentemente (mesmo título) ou notificações desativadas para as empresas.'];
        }
        $alvo = $empresaAdmId ? '1 empresa' : $n . ' empresa(s)';
        return [
            'status' => 'enviado',
            'detalhe' => 'Aviso entregue no sininho de ' . $alvo . '.',
            'notificacoes' => $n,
        ];
    }

    return ['status' => 'rascunho', 'detalhe' => 'Canal não suportado.'];
}
