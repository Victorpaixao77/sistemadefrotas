<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/lib/adm_events.php';
require_once __DIR__ . '/lib/adm_comunicacao.php';
require_once __DIR__ . '/lib/adm_comunicacao_templates.php';

$page_title = 'Central de comunicação';
$mensagem = '';
$tipo_msg = 'info';
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')));

function com_responder_ajax(bool $ok, string $message, string $tipo, array $extra = []): void
{
    adm_comunicacao_ajax_exit(array_merge([
        'success' => $ok,
        'message' => $message,
        'tipo' => $tipo,
    ], $extra));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $csrfOk = csrf_token_validate($_POST['csrf_token'] ?? null);

    if (!$csrfOk) {
        if ($isAjax) {
            com_responder_ajax(false, 'Token CSRF inválido.', 'error');
        }
        $mensagem = 'Token CSRF inválido.';
        $tipo_msg = 'error';
    } elseif ($action === 'excluir_modelo') {
        $res = adm_comunicacao_modelo_excluir($pdo, (string) ($_POST['slug'] ?? ''));
        if ($isAjax) {
            com_responder_ajax(!empty($res['success']), $res['message'], !empty($res['success']) ? 'success' : 'error', [
                'modelos' => adm_comunicacao_modelos_list($pdo),
            ]);
        }
        $mensagem = $res['message'];
        $tipo_msg = !empty($res['success']) ? 'success' : 'error';
    } elseif ($action === 'editar_modelo') {
        $res = adm_comunicacao_modelo_salvar(
            $pdo,
            (string) ($_POST['modelo_nome'] ?? ''),
            (string) ($_POST['canal'] ?? 'aviso'),
            (string) ($_POST['titulo'] ?? ''),
            (string) ($_POST['mensagem'] ?? ''),
            (int) ($_SESSION['admin_id'] ?? 0),
            (string) ($_POST['slug'] ?? '')
        );
        if ($isAjax) {
            com_responder_ajax(!empty($res['success']), $res['message'], !empty($res['success']) ? 'success' : 'error', [
                'modelos' => adm_comunicacao_modelos_list($pdo),
            ]);
        }
        $mensagem = $res['message'];
        $tipo_msg = !empty($res['success']) ? 'success' : 'error';
    } elseif ($action === 'salvar_modelo') {
        $res = adm_comunicacao_modelo_salvar(
            $pdo,
            (string) ($_POST['modelo_nome'] ?? ''),
            (string) ($_POST['canal'] ?? 'aviso'),
            (string) ($_POST['titulo'] ?? ''),
            (string) ($_POST['mensagem'] ?? ''),
            (int) ($_SESSION['admin_id'] ?? 0)
        );
        if ($isAjax) {
            com_responder_ajax(!empty($res['success']), $res['message'], !empty($res['success']) ? 'success' : 'error', [
                'modelos' => adm_comunicacao_modelos_list($pdo),
            ]);
        }
        $mensagem = $res['message'];
        $tipo_msg = !empty($res['success']) ? 'success' : 'error';
    } elseif ($action === 'reenviar') {
        $comId = (int) ($_POST['comunicacao_id'] ?? 0);
        try {
            $st = $pdo->prepare('SELECT * FROM adm_comunicacoes WHERE id = ?');
            $st->execute([$comId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Comunicação não encontrada.');
            }
            $canal = (string) $row['canal'];
            if (!in_array($canal, ['aviso', 'push', 'banner'], true)) {
                throw new RuntimeException('Reenvio disponível apenas para Aviso, Push e Banner.');
            }
            $empId = !empty($row['empresa_adm_id']) ? (int) $row['empresa_adm_id'] : null;
            $result = adm_comunicacao_dispatch($pdo, $canal, (string) $row['titulo'], (string) $row['mensagem'], $empId, $comId);
            $status = $result['status'];
            $enviadoEm = $status === 'enviado' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare('UPDATE adm_comunicacoes SET status = ?, enviado_em = ? WHERE id = ?')
                ->execute([$status, $enviadoEm, $comId]);
            $row['status'] = $status;
            $row['enviado_em'] = $enviadoEm;
            $mensagem = $result['detalhe'];
            $tipo_msg = $status === 'enviado' ? 'success' : 'warn';
            if ($isAjax) {
                com_responder_ajax($status === 'enviado', $mensagem, $tipo_msg, ['historico_item' => adm_comunicacao_historico_json($row)]);
            }
        } catch (Throwable $e) {
            error_log('comunicacao reenviar: ' . $e->getMessage());
            if ($isAjax) {
                com_responder_ajax(false, $e->getMessage(), 'error');
            }
            $mensagem = $e->getMessage();
            $tipo_msg = 'error';
        }
    } elseif ($action === 'enviar') {
        try {
            $canal = (string) ($_POST['canal'] ?? 'aviso');
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $texto = trim((string) ($_POST['mensagem'] ?? ''));
            $empId = !empty($_POST['empresa_adm_id']) ? (int) $_POST['empresa_adm_id'] : null;

            $pdo->prepare('INSERT INTO adm_comunicacoes (canal, titulo, mensagem, empresa_adm_id, status, enviado_em, created_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$canal, $titulo, $texto, $empId, 'rascunho', null, (int) $_SESSION['admin_id']]);
            $comId = (int) $pdo->lastInsertId();

            $result = adm_comunicacao_dispatch($pdo, $canal, $titulo, $texto, $empId, $comId);
            $status = $result['status'];
            $enviadoEm = $status === 'enviado' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare('UPDATE adm_comunicacoes SET status = ?, enviado_em = ? WHERE id = ?')
                ->execute([$status, $enviadoEm, $comId]);

            adm_event_log($pdo, 'comunicacao', 'envio', $canal . ': ' . $titulo, $status === 'enviado' ? 'info' : 'aviso', null, $empId);

            $st = $pdo->prepare('SELECT * FROM adm_comunicacoes WHERE id = ?');
            $st->execute([$comId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $mensagem = $result['detalhe'];
            $tipo_msg = $status === 'enviado' ? 'success' : 'warn';
            if ($isAjax) {
                com_responder_ajax($status === 'enviado' || $status === 'rascunho', $mensagem, $tipo_msg, [
                    'historico_item' => adm_comunicacao_historico_json($row),
                ]);
            }
        } catch (Throwable $e) {
            error_log('comunicacao: ' . $e->getMessage());
            if ($isAjax) {
                com_responder_ajax(false, 'Erro ao salvar comunicação.', 'error');
            }
            $mensagem = 'Erro ao salvar comunicação.';
            $tipo_msg = 'error';
        }
    }
}

$empresas = $pdo->query('SELECT id, razao_social FROM empresa_adm ORDER BY razao_social')->fetchAll(PDO::FETCH_ASSOC);
$historico = $pdo->query('SELECT * FROM adm_comunicacoes ORDER BY created_at DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
$modelos = adm_comunicacao_modelos_list($pdo);
$csrfToken = csrf_token_get();

require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Central de comunicação';
$cc_subtitle = 'Aviso/push → sininho · Banner → modal central (clique Entendi) · E-mail via SMTP';
include __DIR__ . '/components/page_header.php';
?>
<?php if ($mensagem && !$isAjax): ?>
<p class="adm-cc-support-banner adm-cc-support-banner--<?php echo htmlspecialchars($tipo_msg, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<div id="comFlash" class="adm-cc-support-banner" style="display:none" role="status"></div>

<div class="adm-cc-page-stack">
<div class="adm-panel">
    <div class="adm-panel__head"><h2><i class="fas fa-layer-group"></i> Modelos prontos</h2></div>
    <div class="adm-panel__body">
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 0.75rem">
            Clique para usar no formulário. Use <strong>Editar</strong> ou <strong>Excluir</strong> em cada modelo.
        </p>
        <div class="adm-com-modelos-grid" id="admComModelosGrid">
            <?php foreach ($modelos as $m): ?>
            <div class="adm-com-modelo-card" data-slug="<?php echo htmlspecialchars($m['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" class="adm-com-modelo-card__use" data-modelo="<?php echo htmlspecialchars(json_encode([
                    'canal' => $m['canal'], 'titulo' => $m['titulo'], 'mensagem' => $m['mensagem'],
                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="adm-com-modelo-card__icon"><i class="fas <?php echo htmlspecialchars($m['icone'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                    <span class="adm-com-modelo-card__nome"><?php echo htmlspecialchars($m['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="adm-com-modelo-card__canal"><?php echo htmlspecialchars($m['canal'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($m['is_padrao'])): ?><span class="adm-com-modelo-card__tag adm-com-modelo-card__tag--padrao">Padrão</span><?php endif; ?>
                </button>
                <div class="adm-com-modelo-card__actions">
                    <button type="button" class="adm-com-modelo-edit" title="Editar" data-modelo-full="<?php echo htmlspecialchars(json_encode($m, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-pen"></i></button>
                    <button type="button" class="adm-com-modelo-del" title="Excluir" data-slug="<?php echo htmlspecialchars($m['slug'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form method="post" class="adm-panel" id="formComunicacao">
    <div class="adm-panel__head"><h2>Nova comunicação</h2></div>
    <div class="adm-panel__body">
        <input type="hidden" name="action" value="enviar" id="comAction">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" id="comCsrf">
        <div class="adm-cc-filters adm-cc-filters--flat">
            <select name="canal" id="comCanal" aria-label="Canal">
                <option value="email">E-mail (SMTP)</option>
                <option value="whatsapp">WhatsApp (em breve)</option>
                <option value="push">Push / sininho</option>
                <option value="banner">Banner + sininho</option>
                <option value="aviso" selected>Aviso no sininho</option>
            </select>
            <select name="empresa_adm_id" aria-label="Empresa">
                <option value="">Todas empresas (global)</option>
                <?php foreach ($empresas as $e): ?>
                <option value="<?php echo (int) $e['id']; ?>"><?php echo htmlspecialchars($e['razao_social'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 0.75rem">
            <strong>Aviso</strong> e <strong>Push</strong> aparecem no sininho (notificações) de cada empresa.
            <strong>Banner</strong> abre um aviso no centro da tela (botão Entendi para fechar) + sininho.
            Verifique em Configurações → Notificações se o sistema de notificações está ativo na empresa.
        </p>
        <p><input type="text" name="titulo" id="comTitulo" placeholder="Título" class="adm-cc-input" style="width:100%" required></p>
        <p><textarea name="mensagem" id="comMensagem" rows="6" placeholder="Mensagem" class="adm-cc-input" style="width:100%" required></textarea></p>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
            <button type="submit" class="adm-cc-btn adm-cc-btn--primary" id="comBtnEnviar">Enviar</button>
            <button type="button" class="adm-cc-btn adm-cc-btn--secondary" id="comBtnSalvarModelo">
                <i class="fas fa-bookmark"></i> Salvar como modelo
            </button>
        </div>
    </div>
</form>
<div class="adm-panel">
    <div class="adm-panel__head"><h2>Histórico</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table">
            <thead><tr><th>Canal</th><th>Título</th><th>Status</th><th>Enviado</th><th>Criado</th><th></th></tr></thead>
            <tbody id="comHistoricoBody">
            <?php foreach ($historico as $h): ?>
            <tr data-id="<?php echo (int) $h['id']; ?>">
                <td><?php echo htmlspecialchars($h['canal'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($h['titulo'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($h['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($h['enviado_em'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($h['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if (in_array($h['canal'], ['aviso', 'push', 'banner'], true)): ?>
                    <button type="button" class="adm-cc-btn adm-cc-btn--sm com-btn-reenviar" data-id="<?php echo (int) $h['id']; ?>">Reenviar</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div id="modalEditModelo" class="adm-com-modal" hidden>
    <div class="adm-com-modal__backdrop" data-close-modal></div>
    <div class="adm-com-modal__card" role="dialog" aria-labelledby="modalEditModeloTitle">
        <h3 id="modalEditModeloTitle">Editar modelo</h3>
        <form id="formEditModelo">
            <input type="hidden" name="action" value="editar_modelo">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="slug" id="editModeloSlug">
            <p><label>Nome do modelo</label><input type="text" name="modelo_nome" id="editModeloNome" class="adm-cc-input" style="width:100%" required></p>
            <p><label>Canal sugerido</label>
                <select name="canal" id="editModeloCanal" class="adm-cc-input" style="width:100%">
                    <option value="aviso">Aviso</option><option value="banner">Banner</option><option value="push">Push</option>
                    <option value="email">E-mail</option><option value="whatsapp">WhatsApp</option>
                </select>
            </p>
            <p><label>Título</label><input type="text" name="titulo" id="editModeloTitulo" class="adm-cc-input" style="width:100%" required></p>
            <p><label>Mensagem</label><textarea name="mensagem" id="editModeloMensagem" rows="6" class="adm-cc-input" style="width:100%" required></textarea></p>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.75rem">
                <button type="button" class="adm-cc-btn adm-cc-btn--secondary" data-close-modal>Cancelar</button>
                <button type="submit" class="adm-cc-btn adm-cc-btn--primary">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php
$adm_extra_scripts = <<<'JS'
<style>
.adm-com-modelos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0.65rem}
.adm-com-modelo-card{position:relative;border:1px solid var(--border-color,#e2e8f0);border-radius:10px;background:var(--bg-secondary,#fff);overflow:hidden}
.adm-com-modelo-card.is-active{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.15)}
.adm-com-modelo-card__use{display:flex;flex-direction:column;align-items:flex-start;gap:0.25rem;padding:0.75rem;padding-right:3rem;width:100%;border:0;background:transparent;cursor:pointer;text-align:left}
.adm-com-modelo-card__icon{width:36px;height:36px;border-radius:8px;background:rgba(37,99,235,.1);color:#2563eb;display:flex;align-items:center;justify-content:center}
.adm-com-modelo-card__nome{font-weight:600;font-size:0.88rem;color:var(--text-primary)}
.adm-com-modelo-card__canal{font-size:0.72rem;text-transform:uppercase;color:var(--text-secondary)}
.adm-com-modelo-card__tag{font-size:0.68rem;padding:2px 6px;border-radius:4px;margin-top:2px}
.adm-com-modelo-card__tag--padrao{background:#eff6ff;color:#1d4ed8}
.adm-com-modelo-card__actions{position:absolute;top:6px;right:6px;display:flex;gap:2px}
.adm-com-modelo-card__actions button{width:28px;height:28px;border:0;border-radius:6px;background:var(--bg-tertiary,#f1f5f9);cursor:pointer;color:var(--text-secondary)}
.adm-com-modelo-card__actions button:hover{background:#e2e8f0;color:#2563eb}
.adm-com-modelo-card__actions .adm-com-modelo-del:hover{color:#dc2626}
.adm-com-modal{position:fixed;inset:0;z-index:10060;display:flex;align-items:center;justify-content:center;padding:1rem}
.adm-com-modal[hidden]{display:none!important}
.adm-com-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5)}
.adm-com-modal__card{position:relative;z-index:1;width:min(520px,100%);background:var(--bg-secondary,#fff);border-radius:12px;padding:1.25rem;max-height:90vh;overflow:auto}
.adm-com-modal__card label{display:block;font-size:0.82rem;font-weight:600;margin-bottom:0.25rem}
.adm-com-modal__card h3{margin:0 0 1rem}
</style>
<script>
(function () {
    var CSRF = document.getElementById('comCsrf') ? document.getElementById('comCsrf').value : '';
    var flash = document.getElementById('comFlash');
    var grid = document.getElementById('admComModelosGrid');
    var tbody = document.getElementById('comHistoricoBody');
    var form = document.getElementById('formComunicacao');
    var canal = document.getElementById('comCanal');
    var titulo = document.getElementById('comTitulo');
    var msg = document.getElementById('comMensagem');
    var modal = document.getElementById('modalEditModelo');
    var formEdit = document.getElementById('formEditModelo');

    function esc(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function showFlash(text, tipo) {
        if (!flash) return;
        flash.textContent = text;
        flash.className = 'adm-cc-support-banner adm-cc-support-banner--' + (tipo || 'info');
        flash.style.display = 'block';
        flash.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function postFormData(fd) {
        return fetch(window.location.pathname, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); });
    }

    function historicoRow(h) {
        var reenv = h.pode_reenviar
            ? '<button type="button" class="adm-cc-btn adm-cc-btn--sm com-btn-reenviar" data-id="' + h.id + '">Reenviar</button>' : '';
        return '<tr data-id="' + h.id + '"><td>' + esc(h.canal) + '</td><td>' + esc(h.titulo) + '</td><td>' + esc(h.status)
            + '</td><td>' + esc(h.enviado_em || '—') + '</td><td>' + esc(h.created_at) + '</td><td>' + reenv + '</td></tr>';
    }

    function prependHistorico(h) {
        if (!tbody || !h) return;
        var old = tbody.querySelector('tr[data-id="' + h.id + '"]');
        if (old) old.remove();
        tbody.insertAdjacentHTML('afterbegin', historicoRow(h));
        while (tbody.rows.length > 30) tbody.deleteRow(-1);
    }

    function renderModelos(modelos) {
        if (!grid || !modelos) return;
        grid.innerHTML = modelos.map(function (m) {
            var data = JSON.stringify({ canal: m.canal, titulo: m.titulo, mensagem: m.mensagem });
            var full = JSON.stringify(m);
            var tag = m.is_padrao ? '<span class="adm-com-modelo-card__tag adm-com-modelo-card__tag--padrao">Padrão</span>' : '';
            return '<div class="adm-com-modelo-card" data-slug="' + esc(m.slug) + '">'
                + '<button type="button" class="adm-com-modelo-card__use" data-modelo="' + esc(data) + '">'
                + '<span class="adm-com-modelo-card__icon"><i class="fas ' + esc(m.icone || 'fa-file-lines') + '"></i></span>'
                + '<span class="adm-com-modelo-card__nome">' + esc(m.nome) + '</span>'
                + '<span class="adm-com-modelo-card__canal">' + esc(m.canal) + '</span>' + tag + '</button>'
                + '<div class="adm-com-modelo-card__actions">'
                + '<button type="button" class="adm-com-modelo-edit" title="Editar" data-modelo-full="' + esc(full) + '"><i class="fas fa-pen"></i></button>'
                + '<button type="button" class="adm-com-modelo-del" title="Excluir" data-slug="' + esc(m.slug) + '"><i class="fas fa-trash"></i></button>'
                + '</div></div>';
        }).join('');
    }

    if (grid) {
        grid.addEventListener('click', function (e) {
            var del = e.target.closest('.adm-com-modelo-del');
            if (del) {
                e.stopPropagation();
                if (!confirm('Excluir este modelo?')) return;
                var fd = new FormData();
                fd.append('action', 'excluir_modelo');
                fd.append('ajax', '1');
                fd.append('csrf_token', CSRF);
                fd.append('slug', del.getAttribute('data-slug'));
                postFormData(fd).then(function (j) {
                    showFlash(j.message, j.tipo || (j.success ? 'success' : 'error'));
                    if (j.success && j.modelos) renderModelos(j.modelos);
                });
                return;
            }
            var edit = e.target.closest('.adm-com-modelo-edit');
            if (edit) {
                e.stopPropagation();
                try {
                    var m = JSON.parse(edit.getAttribute('data-modelo-full'));
                    document.getElementById('editModeloSlug').value = m.slug;
                    document.getElementById('editModeloNome').value = m.nome;
                    document.getElementById('editModeloCanal').value = m.canal;
                    document.getElementById('editModeloTitulo').value = m.titulo;
                    document.getElementById('editModeloMensagem').value = m.mensagem;
                    modal.hidden = false;
                } catch (err) { console.warn(err); }
                return;
            }
            var use = e.target.closest('.adm-com-modelo-card__use');
            if (!use) return;
            try {
                var m2 = JSON.parse(use.getAttribute('data-modelo'));
                if (m2.canal) canal.value = m2.canal;
                if (m2.titulo) titulo.value = m2.titulo;
                if (m2.mensagem) msg.value = m2.mensagem;
                grid.querySelectorAll('.adm-com-modelo-card').forEach(function (c) { c.classList.remove('is-active'); });
                use.closest('.adm-com-modelo-card').classList.add('is-active');
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (err2) { console.warn(err2); }
        });
    }

    if (modal) {
        modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
            el.addEventListener('click', function () { modal.hidden = true; });
        });
    }

    if (formEdit) {
        formEdit.addEventListener('submit', function (e) {
            e.preventDefault();
            postFormData(new FormData(formEdit)).then(function (j) {
                showFlash(j.message, j.tipo || (j.success ? 'success' : 'error'));
                if (j.success) {
                    modal.hidden = true;
                    if (j.modelos) renderModelos(j.modelos);
                }
            });
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            document.getElementById('comAction').value = 'enviar';
            var btn = document.getElementById('comBtnEnviar');
            if (btn) btn.disabled = true;
            postFormData(new FormData(form)).then(function (j) {
                showFlash(j.message, j.tipo || (j.success ? 'success' : 'warn'));
                if (j.historico_item) prependHistorico(j.historico_item);
                if (j.success) {
                    titulo.value = '';
                    msg.value = '';
                }
            }).finally(function () { if (btn) btn.disabled = false; });
        });

        document.getElementById('comBtnSalvarModelo').addEventListener('click', function () {
            var nome = prompt('Nome do modelo:');
            if (!nome || !nome.trim()) return;
            var fd = new FormData(form);
            fd.set('action', 'salvar_modelo');
            fd.set('modelo_nome', nome.trim());
            postFormData(fd).then(function (j) {
                showFlash(j.message, j.tipo || (j.success ? 'success' : 'error'));
                if (j.success && j.modelos) renderModelos(j.modelos);
            });
        });
    }

    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('.com-btn-reenviar');
            if (!btn) return;
            var fd = new FormData();
            fd.append('action', 'reenviar');
            fd.append('ajax', '1');
            fd.append('csrf_token', CSRF);
            fd.append('comunicacao_id', btn.getAttribute('data-id'));
            btn.disabled = true;
            postFormData(fd).then(function (j) {
                showFlash(j.message, j.tipo || (j.success ? 'success' : 'error'));
                if (j.historico_item) prependHistorico(j.historico_item);
            }).finally(function () { btn.disabled = false; });
        });
    }
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
