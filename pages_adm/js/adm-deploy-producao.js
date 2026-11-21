/**
 * Central de Deploy Inteligente — frontend
 */
(function () {
    'use strict';

    var API = 'api/deploy_producao.php';
    var csrf = window.__ADM_CSRF__ || '';
    var state = { servers: [], steps: [], runId: 0, ctx: {}, serverId: 0 };

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function apiGet(action, params) {
        var q = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch(API + '?' + q, { credentials: 'include' }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok && j && j.error) {
                    j.success = false;
                }
                return j;
            }).catch(function () {
                return { success: false, error: 'Resposta inválida (HTTP ' + r.status + ')' };
            });
        });
    }

    function apiPost(action, body) {
        return fetch(API + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body || {}),
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok && j && !j.error && j.message) {
                    j.error = j.message;
                }
                if (!r.ok) {
                    j.success = false;
                }
                return j;
            }).catch(function () {
                return { success: false, error: 'Resposta inválida (HTTP ' + r.status + ')' };
            });
        });
    }

    function serverId() {
        return parseInt(document.getElementById('dpServerSelect').value, 10) || 0;
    }

    function fillServerForm(s) {
        if (!s) return;
        document.getElementById('dpSrvId').value = s.id || 0;
        document.getElementById('dpSrvNome').value = s.nome || '';
        document.getElementById('dpSrvAmbiente').value = s.ambiente || 'producao';
        document.getElementById('dpSrvHost').value = s.host || '';
        document.getElementById('dpSrvPort').value = s.porta_ssh || 22;
        document.getElementById('dpSrvUser').value = s.usuario || '';
        document.getElementById('dpSrvPath').value = s.project_path || '';
        document.getElementById('dpSrvSite').value = s.site_url || '';
        document.getElementById('dpSrvExclude').value = s.sync_exclude || '';
        document.getElementById('dpSrvGitPath').value = s.git_path || '';
        document.getElementById('dpSrvBranch').value = s.git_branch || 'main';
        document.getElementById('dpSrvHomolog').value = s.homolog_path || '';
        document.getElementById('dpSrvAuth').value = s.auth_method || 'ssh_key';
        document.getElementById('dpSrvKey').value = s.has_ssh_key ? '********' : '';
        document.getElementById('dpSrvPass').value = s.has_password ? '********' : '';
        document.getElementById('dpDbHost').value = s.db_host || '';
        document.getElementById('dpDbPort').value = s.db_port || 3307;
        document.getElementById('dpDbName').value = s.db_name || '';
        document.getElementById('dpDbUser').value = s.db_user || 'root';
        document.getElementById('dpDbPass').value = s.has_db_pass ? '********' : '';
        document.getElementById('dpSrvAtivo').checked = s.ativo !== 0;
        document.getElementById('dp2faWrap').hidden = s.ambiente !== 'producao';
    }

    function renderServers(servers) {
        var sel = document.getElementById('dpServerSelect');
        sel.innerHTML = '<option value="">— Novo servidor —</option>';
        servers.forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.nome + ' (' + s.ambiente + ')';
            sel.appendChild(o);
        });
        if (state.serverId && servers.some(function (x) { return x.id === state.serverId; })) {
            sel.value = state.serverId;
            var cur = servers.find(function (x) { return x.id === state.serverId; });
            fillServerForm(cur);
        }
    }

    function renderStatus(data) {
        var el = document.getElementById('dpStatusBody');
        if (!data || data.error) {
            el.innerHTML = '<p class="adm-dp-meta">' + esc(data && data.error ? data.error : 'Sem dados') + '</p>';
            return;
        }
        var m = data.metrics || {};
        var v = data.versions || {};
        var errHtml = '';
        if (!data.online && data.connection_error) {
            errHtml = '<p class="adm-dp-meta adm-dp-error" style="color:#c0392b;margin:6px 0 10px">' + esc(data.connection_error) + '</p>';
        }
        el.innerHTML =
            '<p><strong>' + esc(data.status_label) + '</strong></p>' +
            errHtml +
            '<div class="adm-dp-kpi-row">' +
            '<div class="adm-dp-kpi"><span>CPU</span><strong>' + esc(m.cpu) + '</strong></div>' +
            '<div class="adm-dp-kpi"><span>RAM</span><strong>' + esc(m.ram) + '</strong></div>' +
            '<div class="adm-dp-kpi"><span>Disco</span><strong>' + esc(m.disk) + '</strong></div>' +
            '<div class="adm-dp-kpi"><span>Carga</span><strong>' + esc(m.load) + '</strong></div>' +
            '<div class="adm-dp-kpi"><span>Uptime</span><strong>' + esc(m.uptime) + '</strong></div>' +
            '<div class="adm-dp-kpi"><span>Escrita</span><strong>' + (data.writable ? '✅' : '❌') + '</strong></div>' +
            '</div>' +
            '<p class="adm-dp-meta" style="margin-top:10px">PHP: ' + esc(v.php) + '</p>' +
            '<p class="adm-dp-meta">MySQL: ' + esc(v.mysql) + ' · Git: ' + esc(v.git) + ' · Composer: ' + esc(v.composer) + '</p>' +
            '<p class="adm-dp-meta">Último deploy: ' + esc(data.ultimo_deploy && data.ultimo_deploy.created_at ? data.ultimo_deploy.created_at : '—') + '</p>';
    }

    function renderFileCmp(data) {
        var el = document.getElementById('dpFilesBody');
        if (!data || !data.summary) {
            el.innerHTML = '<p class="adm-dp-meta">' + esc(data && data.error ? data.error : 'Erro') + '</p>';
            return;
        }
        var s = data.summary;
        el.innerHTML =
            '<div class="adm-dp-diff-badges">' +
            '<span class="adm-dp-badge adm-dp-badge--new">Novos: ' + s.novos + '</span>' +
            '<span class="adm-dp-badge adm-dp-badge--chg">Alterados: ' + s.alterados + '</span>' +
            '<span class="adm-dp-badge adm-dp-badge--rm">Removidos: ' + s.removidos + '</span>' +
            '<span class="adm-dp-badge adm-dp-badge--eq">Iguais: ' + s.iguais + '</span>' +
            '</div><p><strong>' + s.total_alteracoes + '</strong> alteração(ões) no total</p>';
        if (data.detail && data.detail.alterados && data.detail.alterados.length) {
            el.innerHTML += '<ul style="font-size:0.8rem;max-height:120px;overflow:auto">' +
                data.detail.alterados.slice(0, 15).map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul>';
        }
    }

    function renderDbConn(conn) {
        function row(c) {
            if (!c) {
                return '<span class="adm-dp-conn adm-dp-conn--wait"><span class="adm-dp-spinner"></span> verificando…</span>';
            }
            if (c.connected) {
                var via = c.via ? ' <em>(' + esc(c.via) + ')</em>' : '';
                return '<span class="adm-dp-conn adm-dp-conn--ok">🟢 conectado</span> <span class="adm-dp-conn-info">' + esc(c.info || '') + via + '</span>';
            }
            return '<span class="adm-dp-conn adm-dp-conn--err">🔴 sem conexão</span> <span class="adm-dp-conn-info">' + esc(c.error || '') + '</span>';
        }
        var h = conn && conn.homolog ? conn.homolog : null;
        var p = conn && conn.producao ? conn.producao : null;
        return '<div class="adm-dp-conn-grid">' +
            '<div class="adm-dp-conn-cell"><strong>Homologação</strong><br>' + row(h) + '</div>' +
            '<div class="adm-dp-conn-cell"><strong>Produção</strong><br>' + row(p) + '</div>' +
            '</div>';
    }

    function renderDbCmp(data) {
        var el = document.getElementById('dpDbBody');
        var sqlEl = document.getElementById('dpSqlPreview');
        if (!data || !data.summary) {
            el.innerHTML = renderDbConn(data && data.connection) +
                '<p class="adm-dp-meta" style="color:#c0392b">' + esc(data && data.error ? data.error : 'Erro') + '</p>';
            return;
        }
        var s = data.summary;
        el.innerHTML =
            renderDbConn(data.connection) +
            '<div class="adm-dp-diff-badges">' +
            '<span class="adm-dp-badge adm-dp-badge--new">Tabelas novas: ' + s.tabelas_novas + '</span>' +
            '<span class="adm-dp-badge adm-dp-badge--chg">Colunas +/-: ' + (s.colunas_novas + s.colunas_alteradas) + '</span>' +
            '</div><p>' + s.total_diffs + ' diferença(s) de schema</p>';
        var sql = (data.detail && data.detail.migration_sql) ? data.detail.migration_sql : [];
        sqlEl.textContent = sql.length ? sql.join('\n\n') : '-- Nenhum SQL gerado';
        state.ctx.db_cmp = data;
    }

    function backupDownloadUrl(backupId, file) {
        return API + '?action=backup_download&backup_id=' + encodeURIComponent(backupId) + '&file=' + encodeURIComponent(file || 'database');
    }

    function renderBackups(list) {
        var el = document.getElementById('dpBackupBody');
        if (!list || !list.length) {
            el.innerHTML = '<p class="adm-dp-meta">Nenhum backup deste servidor ainda. O backup é gerado <strong>no servidor de produção</strong> (banco + arquivos).</p>';
            return;
        }
        el.innerHTML = '<ul class="adm-dp-backup-list">' + list.slice(0, 8).map(function (b) {
            var mb = b.size_bytes ? (b.size_bytes / 1024 / 1024).toFixed(2) + ' MB' : '—';
            var meta = {};
            try { meta = b.meta ? JSON.parse(b.meta) : {}; } catch (e) { meta = {}; }
            var where = meta.remote ? 'VPS' : 'local';
            return '<li class="adm-dp-backup-item">' +
                '<span>#' + b.id + ' ' + esc(b.created_at) + ' — ' + esc(b.status) + ' — ' + mb + ' (' + where + ')</span>' +
                '<span class="adm-dp-backup-dl">' +
                '<a class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" href="' + esc(backupDownloadUrl(b.id, 'database')) + '" download>SQL</a> ' +
                '<a class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" href="' + esc(backupDownloadUrl(b.id, 'files')) + '" download>Arquivos</a>' +
                '</span></li>';
        }).join('') + '</ul>' +
        '<p class="adm-dp-meta" style="margin-top:8px">Download traz cópia do VPS para seu PC (pode levar alguns segundos).</p>';
    }

    function loadBackups() {
        var sid = serverId();
        if (!sid) return;
        apiGet('backups', { server_id: sid }).then(function (r) {
            if (r.success) renderBackups(r.data);
        });
    }

    function renderGit(data) {
        var el = document.getElementById('dpGitBody');
        if (!data) {
            el.innerHTML = '<p class="adm-dp-meta">Sem Git</p>';
            return;
        }
        el.innerHTML =
            '<p><strong>Branch:</strong> ' + esc(data.branch) + ' · <code>' + esc(data.hash) + '</code></p>' +
            '<p class="adm-dp-meta">' + esc(data.mensagem) + ' — ' + esc(data.autor) + '</p>' +
            (data.dirty ? '<p style="color:#f59e0b">⚠ Working tree sujo</p>' : '<p style="color:#22c55e">✓ Clean</p>');
    }

    function renderHistory(rows) {
        var tb = document.querySelector('#dpHistoryTable tbody');
        if (!rows || !rows.length) {
            tb.innerHTML = '<tr><td colspan="8">Nenhum deploy registrado.</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(function (r) {
            var st = r.status;
            var badge = st === 'success' ? '🟢' : (st === 'failed' ? '🔴' : '🟡');
            return '<tr>' +
                '<td>' + esc(r.created_at) + '</td>' +
                '<td>' + esc(r.server_nome) + '</td>' +
                '<td>' + esc(r.versao) + '</td>' +
                '<td><code>' + esc(r.git_hash) + '</code></td>' +
                '<td>' + esc(r.arquivos_enviados) + '</td>' +
                '<td>' + esc(r.duracao_seg != null ? r.duracao_seg + 's' : '—') + '</td>' +
                '<td>' + badge + ' ' + esc(st) + '</td>' +
                '<td>' + (r.backup_id ? '<button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm dp-rollback" data-id="' + r.id + '">Rollback</button>' : '—') + '</td>' +
                '</tr>';
        }).join('');
        document.querySelectorAll('.dp-rollback').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Restaurar backup deste deploy?')) return;
                apiPost('rollback', { run_id: parseInt(btn.getAttribute('data-id'), 10) }).then(loadOverview);
            });
        });
    }

    function stepIcon(st) {
        return { ok: '✅', error: '❌', warn: '⚠️', running: '⏳', pending: '○', skipped: '⊘' }[st] || '○';
    }

    var FINISHED = ['success', 'failed', 'cancelled', 'rolled_back'];

    function fmtDur(seg) {
        seg = Math.max(0, Math.round(seg || 0));
        if (seg < 60) return seg + 's';
        return Math.floor(seg / 60) + 'm ' + (seg % 60) + 's';
    }

    function startTimer() {
        state.deployStart = Date.now();
        stopTimer();
        state.timer = setInterval(function () {
            var el = document.getElementById('dpProgressTime');
            if (el) el.textContent = fmtDur((Date.now() - state.deployStart) / 1000);
        }, 1000);
    }
    function stopTimer() {
        if (state.timer) { clearInterval(state.timer); state.timer = null; }
    }

    function renderProgress(run) {
        var wrap = document.getElementById('dpProgressWrap');
        if (!run) {
            wrap.hidden = true;
            return;
        }
        state.lastRun = run;
        wrap.hidden = false;
        var finished = FINISHED.indexOf(run.status) >= 0;
        document.getElementById('dpProgressPct').textContent = (run.progress_pct || 0) + '%';
        document.getElementById('dpProgressFill').style.width = (run.progress_pct || 0) + '%';
        document.getElementById('dpProgressLabel').textContent =
            run.status === 'success' ? 'Deploy concluído ✅' :
            run.status === 'rolled_back' ? 'Deploy revertido (rollback)' :
            run.status === 'failed' ? 'Deploy falhou' :
            'Etapa: ' + (run.step_atual || '…');
        // Cronômetro: ao vivo enquanto roda; duração final quando termina
        var spin = document.getElementById('dpProgressSpin');
        if (finished) {
            stopTimer();
            if (spin) spin.style.display = 'none';
            if (run.duracao_seg != null) document.getElementById('dpProgressTime').textContent = fmtDur(run.duracao_seg);
        } else if (spin) {
            spin.style.display = '';
        }
        // Box de erro (inclui erro de banco na migration)
        var errBox = document.getElementById('dpProgressError');
        if (errBox && (run.status === 'failed' || run.status === 'rolled_back') && run.erro_msg) {
            errBox.hidden = false;
            errBox.innerHTML = '<strong><i class="fas fa-triangle-exclamation"></i> ' +
                (run.status === 'rolled_back' ? 'Falhou e foi revertido' : 'Deploy falhou') + '</strong><br>' + esc(run.erro_msg);
        }
        var ul = document.getElementById('dpStepsList');
        ul.innerHTML = (run.steps || []).map(function (s) {
            var st = s.status === 'running' ? 'adm-dp-step--run' : '';
            return '<li class="' + st + '"><span class="adm-dp-step-icon">' + stepIcon(s.status) + '</span><span><strong>' + esc(s.step_label) + '</strong>' +
                (s.mensagem ? '<br><small>' + esc(s.mensagem) + '</small>' : '') +
                (s.duracao_ms ? ' <small class="adm-dp-step-ms">· ' + s.duracao_ms + 'ms</small>' : '') + '</span></li>';
        }).join('');
    }

    // Marca o passo atual como "executando" ANTES da chamada — feedback imediato em passos lentos.
    function markRunning(stepKey) {
        var run = state.lastRun;
        if (!run || !run.steps) return;
        run.steps.forEach(function (s) {
            if (s.step_key === stepKey && (s.status === 'pending' || !s.status)) s.status = 'running';
        });
        run.step_atual = stepKey;
        renderProgress(run);
        var cur = run.steps.filter(function (s) { return s.step_key === stepKey; })[0];
        if (cur) document.getElementById('dpProgressLabel').textContent = 'Executando: ' + cur.step_label + '…';
    }

    function showDeployError(msg, stepKey) {
        var box = document.getElementById('dpProgressError');
        if (box) {
            box.hidden = false;
            box.innerHTML = '<strong><i class="fas fa-triangle-exclamation"></i> Deploy interrompido</strong><br>' + esc(msg);
        }
        var spin = document.getElementById('dpProgressSpin');
        if (spin) spin.style.display = 'none';
        var lbl = document.getElementById('dpProgressLabel');
        if (lbl) lbl.textContent = 'Deploy interrompido';
        if (state.lastRun && state.lastRun.steps) {
            state.lastRun.steps.forEach(function (s) { if (s.step_key === stepKey && s.status === 'running') s.status = 'error'; });
            var ul = document.getElementById('dpStepsList');
            ul.innerHTML = (state.lastRun.steps || []).map(function (s) {
                return '<li><span class="adm-dp-step-icon">' + stepIcon(s.status) + '</span><span><strong>' + esc(s.step_label) + '</strong>' + (s.mensagem ? '<br><small>' + esc(s.mensagem) + '</small>' : '') + '</span></li>';
            }).join('');
        }
    }

    function loadOverview() {
        return apiGet('overview').then(function (r) {
            if (!r || !r.success) {
                var sel = document.getElementById('dpServerSelect');
                if (sel) {
                    sel.innerHTML = '<option value="">Erro ao carregar — F5</option>';
                }
                console.error('overview', r && r.error);
                return;
            }
            var d = r.data;
            state.servers = d.servers || [];
            state.steps = d.steps || [];
            renderServers(state.servers);
            renderHistory(d.history);
            loadBackups();
            document.getElementById('dpHomologPath').textContent = 'Origem: ' + (d.homolog_root || '—');
            if (serverId()) {
                refreshStatus();
                loadGit();
            }
        });
    }

    function refreshStatus() {
        var sid = serverId();
        if (!sid) return;
        var el = document.getElementById('dpStatusBody');
        if (el) {
            el.innerHTML = '<p class="adm-dp-meta adm-dp-loading"><span class="adm-dp-spinner"></span> Consultando servidor…</p>';
        }
        apiGet('server_status', { server_id: sid }).then(function (r) {
            if (r.success) {
                renderStatus(r.data);
            } else if (el) {
                el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">' + esc(r.error || 'Erro ao consultar status') + '</p>';
            }
        }).catch(function () {
            if (el) {
                el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">Falha de rede ao consultar status. Tente novamente.</p>';
            }
        });
    }

    function loadGit() {
        var sid = serverId();
        if (!sid) return;
        apiGet('git', { server_id: sid, sub: 'overview' }).then(function (r) {
            if (r.success) renderGit(r.data);
        });
    }

    function saveServer() {
        var form = document.getElementById('dpServerForm');
        var data = {};
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name) return;
            if (el.type === 'checkbox') data[el.name] = el.checked ? 1 : 0;
            else data[el.name] = el.value;
        });
        apiPost('server_save', data).then(function (r) {
            if (r.success) {
                state.serverId = r.data.id;
                loadOverview();
                alert('Configuração salva.');
            } else {
                alert(r.error || (r.data && r.data.error) || 'Erro ao salvar');
            }
        }).catch(function () {
            alert('Falha de rede ao salvar. Recarregue a página e tente novamente.');
        });
    }

    function renderPreflight(d) {
        var box = document.getElementById('dpPreflightBox');
        box.hidden = false;
        if (!d || !d.items) {
            box.innerHTML = '<p class="adm-dp-meta">' + esc((d && d.error) || 'Não foi possível verificar.') + '</p>';
            return;
        }
        var icon = { ok: '✅', warn: '⚠️', bad: '⛔' };
        var rows = d.items.map(function (it) {
            return '<li class="adm-dp-pf-item adm-dp-pf--' + it.status + '">' +
                '<span class="adm-dp-pf-ic">' + (icon[it.status] || '•') + '</span>' +
                '<span class="adm-dp-pf-txt"><strong>' + esc(it.label) + '</strong>' +
                '<span class="adm-dp-pf-detail">' + esc(it.detail || '') + '</span></span></li>';
        }).join('');
        var verdict = d.ready
            ? '<div class="adm-dp-pf-verdict ok"><i class="fas fa-circle-check"></i> Pronto para subir — nenhum bloqueio.</div>'
            : '<div class="adm-dp-pf-verdict bad"><i class="fas fa-triangle-exclamation"></i> Ainda não — resolva os itens em vermelho antes de publicar.</div>';
        box.innerHTML = verdict + '<ul class="adm-dp-pf-list">' + rows + '</ul>';
    }

    function runPreflight() {
        var sid = state.serverId || parseInt(document.getElementById('dpServerSelect').value, 10) || 0;
        if (!sid) { alert('Selecione um servidor primeiro.'); return; }
        var btn = document.getElementById('dpPreflightBtn');
        var old = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando…';
        var box = document.getElementById('dpPreflightBox');
        box.hidden = false;
        box.innerHTML = '<p class="adm-dp-meta">Rodando verificações de leitura…</p>';
        apiGet('preflight', { server_id: sid }).then(function (r) {
            renderPreflight(r.data || { error: r.error });
        }).catch(function () {
            box.innerHTML = '<p class="adm-dp-meta">Falha de rede na verificação.</p>';
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = old;
        });
    }

    var STEP_KEYS = [
        'validate_server', 'validate_permissions', 'git_check', 'git_pull',
        'compare_files', 'compare_db', 'backup', 'validate_sql', 'migration',
        'sync_files', 'clear_cache', 'composer', 'validate_app', 'auto_tests',
        'git_tag', 'finalize'
    ];

    function finishDeploy() {
        stopTimer();
        loadOverview();
        document.getElementById('dpDeployBtn').disabled = false;
        document.getElementById('dpCancelBtn').hidden = true;
    }

    function showCancelledUI() {
        stopTimer();
        var spin = document.getElementById('dpProgressSpin');
        if (spin) spin.style.display = 'none';
        var lbl = document.getElementById('dpProgressLabel');
        if (lbl) lbl.textContent = 'Deploy cancelado';
        var box = document.getElementById('dpProgressError');
        if (box) {
            box.hidden = false;
            box.innerHTML = '<strong><i class="fas fa-ban"></i> Deploy cancelado</strong><br>Interrompido a seu pedido. O que já foi enviado permanece no servidor; use Backup/Rollback se precisar reverter.';
        }
    }

    function cancelDeploy() {
        if (!state.runId || state.cancelled) return;
        if (!confirm('Cancelar o deploy? O passo atual encerra em instantes; o que já subiu permanece no servidor.')) return;
        state.cancelled = true;
        var btn = document.getElementById('dpCancelBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelando…';
        // Feedback imediato — o passo em andamento (envio) vai encerrar assim que checar o cancelamento.
        var box = document.getElementById('dpProgressError');
        if (box) {
            box.hidden = false;
            box.innerHTML = '<strong><i class="fas fa-ban"></i> Cancelando…</strong><br>Aguardando o passo atual encerrar. O que já subiu permanece no servidor.';
        }
        apiPost('run_cancel', { run_id: state.runId }).catch(function () {});
    }

    function runDeployPipeline(runId, stepIndex) {
        if (stepIndex >= STEP_KEYS.length) {
            apiGet('run_poll', { run_id: runId }).then(function (r) {
                renderProgress(r.data);
                finishDeploy();
            });
            return;
        }
        if (state.cancelled) { showCancelledUI(); finishDeploy(); return; }
        var step = STEP_KEYS[stepIndex];
        markRunning(step); // mostra "⏳ Executando: <passo>…" antes da chamada (feedback em passos lentos)
        apiPost('run_step', {
            run_id: runId,
            step: step,
            ctx: state.ctx,
            approve_sql: document.getElementById('dpApproveSql').checked,
            auto_rollback: document.getElementById('dpAutoRollback').checked,
            allow_dirty: false,
        }).then(function (r) {
            var d = r.data || {};
            if (d.ctx) state.ctx = d.ctx;
            if (d.run) renderProgress(d.run);
            if (!r.success || d.error) {
                if (d.cancelled || state.cancelled) {
                    showCancelledUI();
                } else {
                    showDeployError(d.error || r.error || 'erro no passo "' + step + '"', step);
                }
                finishDeploy();
                return;
            }
            if (d.done) {
                finishDeploy();
                alert('Deploy publicado com sucesso!');
                return;
            }
            setTimeout(function () { runDeployPipeline(runId, stepIndex + 1); }, 400);
        }).catch(function () {
            // Rede caiu ou o passo estourou o tempo → não deixa preso em "running".
            showDeployError('Falha de rede ou tempo excedido no passo "' + step + '". O deploy foi interrompido — confira o Histórico e os logs abaixo.', step);
            finishDeploy();
        });
    }

    function startDeploy() {
        var sid = serverId();
        if (!sid) {
            alert('Salve e selecione um servidor primeiro.');
            return;
        }
        if (!document.getElementById('dpConfirmDeploy').checked) {
            alert('Marque a confirmação de publicação.');
            return;
        }
        var amb = document.getElementById('dpSrvAmbiente').value;
        var body = {
            server_id: sid,
            confirm: true,
            versao: document.getElementById('dpVersao').value || ('v' + new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')),
            totp_code: document.getElementById('dpTotp').value || '',
        };
        document.getElementById('dpDeployBtn').disabled = true;
        apiPost('run_start', body).then(function (r) {
            if (!r.success) {
                document.getElementById('dpDeployBtn').disabled = false;
                if (r.require_2fa) document.getElementById('dp2faWrap').hidden = false;
                alert(r.error || 'Não foi possível iniciar');
                return;
            }
            state.runId = r.data.run_id;
            state.ctx = {};
            state.lastRun = null;
            state.cancelled = false;
            var pw = document.getElementById('dpProgressWrap');
            pw.hidden = false;
            try { pw.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
            document.getElementById('dpProgressError').hidden = true;
            document.getElementById('dpProgressTime').textContent = '0s';
            var cbtn = document.getElementById('dpCancelBtn');
            cbtn.hidden = false; cbtn.disabled = false; cbtn.innerHTML = '<i class="fas fa-stop"></i> Cancelar';
            startTimer();
            // Mostra logo os 16 passos (pendentes) antes de começar a executar.
            apiGet('run_poll', { run_id: state.runId }).then(function (p) {
                if (p && p.data) renderProgress(p.data);
                runDeployPipeline(state.runId, 0);
            }).catch(function () {
                runDeployPipeline(state.runId, 0);
            });
        }).catch(function () {
            document.getElementById('dpDeployBtn').disabled = false;
            alert('Falha de rede ao iniciar o deploy.');
        });
    }

    function bindEvents() {
        document.getElementById('dpRefresh').addEventListener('click', loadOverview);
        document.getElementById('dpSaveServer').addEventListener('click', saveServer);
        document.getElementById('dpRefreshStatus').addEventListener('click', refreshStatus);
        document.getElementById('dpCompareFiles').addEventListener('click', function () {
            var sid = serverId();
            if (!sid) { alert('Salve e selecione um servidor primeiro.'); return; }
            var btn = this;
            var el = document.getElementById('dpFilesBody');
            btn.disabled = true;
            el.innerHTML = '<p class="adm-dp-meta adm-dp-loading"><span class="adm-dp-spinner"></span> Comparando arquivos (homologação × produção)… pode levar até 1–2 min</p>';
            apiGet('compare_files', { server_id: sid }).then(function (r) {
                btn.disabled = false;
                if (r && r.success) {
                    renderFileCmp(r.data);
                    state.ctx.file_cmp = r.data;
                } else {
                    el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">' +
                        esc((r && (r.data && r.data.error || r.error)) || 'Erro na comparação de arquivos') + '</p>';
                }
            }).catch(function () {
                btn.disabled = false;
                el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">Falha de rede na comparação de arquivos. Tente novamente.</p>';
            });
        });
        document.getElementById('dpCompareDb').addEventListener('click', function () {
            var sid = serverId();
            if (!sid) { alert('Salve e selecione um servidor primeiro.'); return; }
            var btn = this;
            var el = document.getElementById('dpDbBody');
            btn.disabled = true;
            el.innerHTML = renderDbConn(null) +
                '<p class="adm-dp-meta adm-dp-loading"><span class="adm-dp-spinner"></span> Conectando e comparando o schema das duas bases…</p>';
            apiGet('compare_db', { server_id: sid }).then(function (r) {
                btn.disabled = false;
                if (r && r.success) {
                    renderDbCmp(r.data);
                } else {
                    var conn = r && r.data && r.data.connection ? r.data.connection : null;
                    el.innerHTML = renderDbConn(conn) +
                        '<p class="adm-dp-meta" style="color:#c0392b">' +
                        esc((r && (r.data && r.data.error || r.error)) || 'Erro na comparação') + '</p>';
                }
            }).catch(function () {
                btn.disabled = false;
                el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">Falha de rede na comparação. Tente novamente.</p>';
            });
        });
        document.getElementById('dpBackupNow').addEventListener('click', function () {
            var sid = serverId();
            if (!sid) { alert('Salve e selecione o servidor de produção primeiro.'); return; }
            var btn = this;
            var el = document.getElementById('dpBackupBody');
            btn.disabled = true;
            el.innerHTML = '<p class="adm-dp-meta adm-dp-loading"><span class="adm-dp-spinner"></span> Gerando backup no servidor de produção (banco + arquivos)… pode levar 1–3 min</p>';
            apiPost('backup', { server_id: sid }).then(function (r) {
                btn.disabled = false;
                if (r && r.success && r.data && r.data.backup_id) {
                    alert('Backup #' + r.data.backup_id + ' criado no servidor de produção.');
                    loadBackups();
                } else {
                    el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">' +
                        esc((r && (r.data && r.data.error || r.error)) || 'Erro ao gerar backup') + '</p>';
                }
            }).catch(function () {
                btn.disabled = false;
                el.innerHTML = '<p class="adm-dp-meta" style="color:#c0392b">Falha de rede ao gerar backup.</p>';
            });
        });
        document.getElementById('dpPreflightBtn').addEventListener('click', runPreflight);
        document.getElementById('dpCancelBtn').addEventListener('click', cancelDeploy);
        document.getElementById('dpDeployBtn').addEventListener('click', startDeploy);
        document.getElementById('dpServerSelect').addEventListener('change', function () {
            state.serverId = serverId();
            if (!state.serverId) {
                document.getElementById('dpSrvId').value = '0';
                return;
            }
            var s = state.servers.find(function (x) { return x.id === state.serverId; });
            fillServerForm(s);
            refreshStatus();
            loadGit();
            loadBackups();
        });
        document.getElementById('dpSrvAmbiente').addEventListener('change', function () {
            document.getElementById('dp2faWrap').hidden = this.value !== 'producao';
        });
        document.querySelectorAll('[data-git]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                apiPost('git', { server_id: serverId(), sub: btn.getAttribute('data-git') }).then(function (r) {
                    if (r.success && r.data.output) alert(r.data.output);
                    loadGit();
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bindEvents(); loadOverview(); });
    } else {
        bindEvents();
        loadOverview();
    }
})();
