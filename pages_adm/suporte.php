<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/lib/adm_events.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_suporte_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Data', 'Empresa', 'Usuário', 'Ação', 'IP', 'Encerrado em'], ';');
    $st = $pdo->query('
        SELECT a.created_at, ec.razao_social, u.nome AS usuario_nome, a.acao, a.ip_address, a.encerrado_em
        FROM adm_auditoria_suporte a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN empresa_clientes ec ON ec.id = a.empresa_cliente_id
        ORDER BY a.created_at DESC
        LIMIT 5000
    ');
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['created_at'],
            $row['razao_social'] ?? '',
            $row['usuario_nome'] ?? '',
            $row['acao'],
            $row['ip_address'] ?? '',
            $row['encerrado_em'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

$page_title = 'Modo suporte';
$mensagem = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_validate($_POST['csrf_token'] ?? null)) {
        $mensagem = 'Token CSRF inválido.';
        $tipo = 'erro';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'entrar') {
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
        $ecId = (int) ($_POST['empresa_cliente_id'] ?? 0);
        try {
            $st = $pdo->prepare('SELECT u.*, ec.empresa_adm_id FROM usuarios u JOIN empresa_clientes ec ON ec.id = u.empresa_id WHERE u.id = ? AND ec.id = ? LIMIT 1');
            $st->execute([$usuarioId, $ecId]);
            $user = $st->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new RuntimeException('Usuário não encontrado nesta empresa.');
            }

            $_SESSION['_adm_session_backup'] = [
                'admin_id' => $_SESSION['admin_id'] ?? null,
                'admin_nome' => $_SESSION['admin_nome'] ?? null,
                'admin_email' => $_SESSION['admin_email'] ?? null,
            ];

            $pdo->prepare('INSERT INTO adm_auditoria_suporte (admin_id, usuario_id, empresa_cliente_id, acao, ip_address) VALUES (?,?,?,?,?)')
                ->execute([
                    (int) $_SESSION['admin_id'],
                    $usuarioId,
                    $ecId,
                    'impersonate_start',
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

            $_SESSION['adm_impersonating'] = true;
            $_SESSION['adm_impersonate_admin_id'] = (int) $_SESSION['admin_id'];
            $_SESSION['loggedin'] = true;
            $_SESSION['usuario_id'] = $usuarioId;
            $_SESSION['empresa_id'] = $ecId;
            $_SESSION['nome'] = $user['nome'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['tipo_usuario'] = $user['tipo_usuario'] ?? 'admin';
            $_SESSION['is_admin'] = (int) ($user['is_admin'] ?? 1);

            adm_event_log($pdo, 'suporte', 'impersonate', 'Admin assumiu sessão do usuário #' . $usuarioId, 'aviso', $ecId, (int) $user['empresa_adm_id'], $usuarioId);

            sf_project_include('includes/sf_paths.php');
            header('Location: ' . sf_app_url('index.php'));
            exit;
        } catch (Throwable $e) {
            error_log('suporte: ' . $e->getMessage());
            $mensagem = 'Não foi possível iniciar o modo suporte.';
            $tipo = 'erro';
        }
    }
}

$empresas = $pdo->query('
    SELECT ec.id AS ec_id, ec.razao_social, ea.id AS ea_id
    FROM empresa_clientes ec
    JOIN empresa_adm ea ON ea.id = ec.empresa_adm_id
    ORDER BY ec.razao_social
')->fetchAll(PDO::FETCH_ASSOC);

$auditoria = $pdo->query('
    SELECT a.*, u.nome AS usuario_nome, ec.razao_social
    FROM adm_auditoria_suporte a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    LEFT JOIN empresa_clientes ec ON ec.id = a.empresa_cliente_id
    ORDER BY a.created_at DESC LIMIT 50
')->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Modo suporte';
$cc_subtitle = 'Acesso auditado à empresa sem senha do usuário';
include __DIR__ . '/components/page_header.php';
?>
<?php if ($mensagem): ?><p class="adm-cc-support-banner"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

<div class="adm-cc-page-stack">
<div class="adm-panel">
    <div class="adm-panel__head"><h2>Assumir sessão</h2></div>
    <div class="adm-panel__body">
        <form method="post" id="form-suporte">
            <input type="hidden" name="action" value="entrar">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="adm-cc-filters adm-cc-filters--flat">
                <select name="empresa_cliente_id" id="sup-empresa" required>
                    <option value="">Empresa…</option>
                    <?php foreach ($empresas as $e): ?>
                    <option value="<?php echo (int) $e['ec_id']; ?>" data-ea="<?php echo (int) $e['ea_id']; ?>"><?php echo htmlspecialchars($e['razao_social'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="usuario_id" id="sup-usuario" required disabled><option value="">Selecione a empresa primeiro</option></select>
                <button type="submit" class="adm-cc-btn adm-cc-btn--primary">Entrar como usuário</button>
            </div>
        </form>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin-top:0.75rem">Toda ação é registrada em <code>adm_auditoria_suporte</code> e <code>adm_eventos</code>. Um banner será exibido na sessão do frotas.</p>
    </div>
</div>

<div class="adm-panel">
    <div class="adm-panel__head" style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap">
        <h2>Auditoria recente</h2>
        <a href="suporte.php?export=csv" class="adm-cc-btn adm-cc-btn--sm"><i class="fas fa-file-csv"></i> Exportar CSV</a>
    </div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table">
            <thead><tr><th>Data</th><th>Empresa</th><th>Usuário</th><th>Ação</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($auditoria as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($a['razao_social'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($a['usuario_nome'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($a['acao'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($a['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?php
$adm_extra_scripts = <<<'JS'
<script>
document.getElementById('sup-empresa').addEventListener('change', function () {
    var sel = document.getElementById('sup-usuario');
    sel.innerHTML = '<option value="">Carregando…</option>';
    if (!this.value) { sel.disabled = true; return; }
    var eaId = this.options[this.selectedIndex].getAttribute('data-ea');
    fetch('api/get_usuarios_empresa.php?empresa_adm_id=' + eaId).then(function (r) { return r.json(); }).then(function (j) {
        sel.innerHTML = '<option value="">Usuário…</option>';
        (j.usuarios || []).forEach(function (u) {
            var o = document.createElement('option');
            o.value = u.id;
            o.textContent = u.nome + ' (' + u.email + ')';
            sel.appendChild(o);
        });
        sel.disabled = false;
    }).catch(function () {
        sel.innerHTML = '<option value="">Erro ao carregar</option>';
    });
});
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
