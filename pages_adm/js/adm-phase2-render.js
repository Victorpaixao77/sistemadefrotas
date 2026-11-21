/**
 * Renderizadores Phase 2 + loader com paginação e poll inteligente
 */
(function (global) {
    'use strict';
    var E = global.AdmCC ? global.AdmCC.escapeHtml : function (s) { return String(s == null ? '' : s); };
    var B = global.AdmCC ? global.AdmCC.formatBRL : function (n) { return 'R$ ' + n; };
    var N = global.AdmCC ? global.AdmCC.formatNum : function (n) { return String(n); };

    var state = { page: 1, api: '', pollTimer: null, empresaFilter: null };

    function panel(title, body) {
        return '<div class="adm-panel"><div class="adm-panel__head"><h2>' + E(title) + '</h2></div><div class="adm-panel__body">' + body + '</div></div>';
    }
    function kpis(items) {
        return '<div class="adm-cc-kpi-grid">' + items.map(function (x) {
            return '<div class="adm-cc-kpi"><div class="adm-cc-kpi__label">' + E(x[0]) + '</div><div class="adm-cc-kpi__value">' + E(x[1]) + '</div></div>';
        }).join('') + '</div>';
    }
    function kpiBand(items, title) {
        var head = title ? '<h2 class="adm-cc-section__title">' + E(title) + '</h2>' : '';
        return '<section class="adm-cc-kpi-band">' + head + kpis(items) + '</section>';
    }
    function table(headers, rows) {
        return '<div class="adm-table-wrap"><table class="adm-table"><thead><tr>' +
            headers.map(function (h) { return '<th>' + E(h) + '</th>'; }).join('') +
            '</tr></thead><tbody>' + (rows.length ? rows.join('') : '<tr><td colspan="' + headers.length + '">Nenhum dado</td></tr>') +
            '</tbody></table></div>';
    }
    function statusBadge(s) {
        var c = s === 'ok' || s === 'concluido' || s === 'ativo' ? 'adm-cc-score--high' : (s === 'falha' || s === 'falhou' ? 'adm-cc-score--low' : 'adm-cc-score--mid');
        return '<span class="adm-cc-score ' + c + '">' + E(s) + '</span>';
    }
    function estBanner(msg) {
        if (!msg) return '';
        return '<div class="adm-cc-support-banner adm-cc-support-banner--warn"><i class="fas fa-info-circle"></i> ' + E(msg) + '</div>';
    }
    function noteBanner(d) {
        return d.nota ? estBanner(d.nota) : '';
    }
    function paginationHtml(p, api) {
        if (!p || p.pages <= 1) return '';
        return '<div class="adm-cc-pagination">' +
            (p.has_prev ? '<button type="button" class="adm-cc-btn adm-cc-btn--sm adm-cc-page" data-page="' + (p.page - 1) + '" data-api="' + E(api) + '">← Anterior</button> ' : '') +
            '<span>Página ' + p.page + ' / ' + p.pages + ' (' + N(p.total) + ')</span> ' +
            (p.has_next ? '<button type="button" class="adm-cc-btn adm-cc-btn--sm adm-cc-page" data-page="' + (p.page + 1) + '" data-api="' + E(api) + '">Próxima →</button>' : '') +
            '</div>';
    }
    function bindPagination(el, api, renderFn) {
        el.querySelectorAll('.adm-cc-page').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.page = parseInt(btn.dataset.page, 10) || 1;
                fetchAndRender(api, renderFn, el, false);
            });
        });
    }

    function apiUrl(base) {
        var u = base;
        if (state.empresaFilter) {
            u += (u.indexOf('?') >= 0 ? '&' : '?') + 'empresa_adm_id=' + encodeURIComponent(state.empresaFilter);
        }
        u += (u.indexOf('?') >= 0 ? '&' : '?') + 'page=' + state.page;
        return u;
    }

    function fetchAndRender(api, renderFn, el, isPoll) {
        AdmCC.fetch(apiUrl(api)).then(function (r) {
            if (!isPoll) el.innerHTML = '';
            renderFn(r.data, el, api);
        }).catch(function (e) {
            el.innerHTML = '<div class="adm-cc-empty">' + E(e.message) + '</div>';
        });
    }

    function load(opts) {
        state.api = opts.api;
        state.page = 1;
        state.empresaFilter = null;
        state.render = opts.render;
        state.estimated = opts.estimated || '';
        var el = opts.root;
        var renderFn = renderers[opts.render];
        if (!renderFn) {
            el.innerHTML = '<div class="adm-cc-empty">Renderer não encontrado</div>';
            return;
        }
        if (state.pollTimer) clearInterval(state.pollTimer);
        fetchAndRender(opts.api, renderFn, el, false);
        if (opts.poll > 0) {
            state.pollTimer = setInterval(function () {
                fetchAndRender(opts.api, renderFn, el, true);
            }, opts.poll);
        }
    }

    function wrapEstimated(html, estimated) {
        return estBanner(estimated) + html;
    }

    function deploy(d, el, api) {
        var u = d.ultimo_deploy || {};
        var diff = d.git_diff || {};
        el.innerHTML = wrapEstimated(kpiBand([
            ['Versão', d.versao_atual || '—'], ['Git', d.git_hash || '—'],
            ['Arquivos alterados', diff.files != null ? diff.files : (u.arquivos_alterados || '—')],
            ['+/- linhas', (diff.insertions || 0) + ' / ' + (diff.deletions || 0)],
            ['Último deploy', u.created_at || '—'], ['Rollback', d.rollback_disponivel ? 'Disponível' : 'Indisponível']
        ], 'Deploy') + (d.rollback_disponivel ? '<p><button type="button" class="adm-cc-btn adm-cc-btn--primary" id="btn-rollback">Registrar rollback</button> <code id="rollback-hash">' + E(d.rollback_hash || '') + '</code></p><p id="rollback-msg" style="font-size:0.85rem;color:var(--text-secondary)">' + E(d.nota || '') + '</p>' : '') +
            panel('Commits', table(['Hash', 'Autor', 'Data', 'Descrição'], (d.commits || []).map(function (c) {
                return '<tr><td><code>' + E(c.hash) + '</code></td><td>' + E(c.autor || c.admin_nome) + '</td><td>' + E(c.data || c.created_at) + '</td><td>' + E(c.descricao || c.git_mensagem) + '</td></tr>';
            }))), d.nota);
        var rb = el.querySelector('#btn-rollback');
        if (rb) {
            rb.addEventListener('click', function () {
                AdmCC.fetch('deploy.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })
                    .then(function (r) { document.getElementById('rollback-msg').textContent = r.data.message || 'OK'; })
                    .catch(function (e) { alert(e.message); });
            });
        }
        bindPagination(el, api, deploy);
    }

    function banco(d, el, api) {
        var idxRows = (d.index_suggestions || []).map(function (s) {
            return '<tr><td>' + E(s.tabela) + '</td><td>' + E(s.coluna) + '</td><td><code style="font-size:0.72rem">' + E(s.sugestao) + '</code></td></tr>';
        });
        var lockKpi = d.locks_espera != null ? N(d.locks_espera) : '—';
        el.innerHTML = wrapEstimated(kpiBand([
            ['Banco', d.database], ['Versão', d.version || '—'], ['Tabelas', N(d.qtd_tabelas)], ['Tamanho MB', N(d.tamanho_mb)],
            ['Conexões', N(d.conexoes)], ['Executando', N(d.executando || 0)], ['Slow queries', N(d.slow_queries)],
            ['Locks espera', lockKpi], ['Cache hit', d.cache_hit_pct != null ? d.cache_hit_pct + '%' : '—']
        ], 'Banco de dados') + paginationHtml(d.pagination, api) +
            (d.maior_tabela ? '<p style="font-size:0.85rem;margin:0.5rem 0">Maior tabela: <strong>' + E(d.maior_tabela.nome) + '</strong> (' + d.maior_tabela.mb + ' MB)</p>' : '') +
            panel('Tabelas', table(['Nome', 'Linhas', 'MB', 'Frag. MB'], (d.tabelas || []).map(function (t) {
                return '<tr><td>' + E(t.nome) + '</td><td>' + N(t.linhas_est) + '</td><td>' + t.mb + '</td><td>' + E(t.fragmentacao_mb) + '</td></tr>';
            }))) +
            panel('Índices sugeridos', table(['Tabela', 'Coluna', 'SQL'], idxRows)) +
            panel('Slow log / performance_schema', table(['Tipo', 'ms', 'Query'], (d.slow_log || []).map(function (s) {
                return '<tr><td>' + E(s.tipo) + '</td><td>' + s.tempo_ms + '</td><td><code style="font-size:0.75rem">' + E(s.query) + '</code></td></tr>';
            }))), '');
        bindPagination(el, api, banco);
    }

    function sqlMonitor(d, el) {
        var stream = (d.stream || []).map(function (s) {
            return '<div class="adm-cc-terminal__line"><span class="adm-cc-terminal__time">' + E(s.created_at) + '</span><span class="adm-cc-terminal__msg"><strong>' + E(s.tipo) + '</strong> ' + s.duracao_ms + 'ms · ' + E(s.modulo) + ' · ' + E(s.detalhe || s.rota) + '</span></div>';
        }).join('');
        var live = (d.live || []).map(function (l) {
            return '<div class="adm-cc-terminal__line"><span class="adm-cc-terminal__msg adm-cc-terminal__msg--aviso"><strong>' + E(l.tipo) + '</strong> ' + l.tempo_ms + 'ms · ' + E(l.origem) + '</span></div>';
        }).join('');
        el.innerHTML = estBanner('Fonte: ' + (d.fonte_slow || 'métricas') + ' + PROCESSLIST ao vivo') +
            panel('Ao vivo', '<div class="adm-cc-terminal">' + (live || '<div class="adm-cc-empty">Nenhuma query ativa</div>') + '</div>') +
            panel('Histórico', '<div class="adm-cc-terminal" style="max-height:400px;overflow:auto">' + stream + '</div>');
    }

    function jobs(d, el, api) {
        el.innerHTML = panel('Cron / Jobs', table(['Job', 'Script', 'Status', 'Última', 'Ação'], (d.jobs || []).map(function (j) {
            return '<tr><td>' + E(j.nome) + '</td><td><code>' + E(j.script_path) + '</code></td><td>' + statusBadge(j.ultimo_status) + '</td><td>' + E(j.ultima_execucao) + '</td><td><button type="button" class="adm-cc-btn adm-cc-btn--sm adm-job-run" data-id="' + j.id + '">Executar</button></td></tr>';
        })));
        el.querySelectorAll('.adm-job-run').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                AdmCC.fetch('jobs.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ job_id: parseInt(btn.dataset.id, 10) }) })
                    .then(function () { fetchAndRender(api || 'jobs.php', jobs, el, false); })
                    .catch(function (e) { alert(e.message); btn.disabled = false; });
            });
        });
    }

    function apis(d, el) {
        el.innerHTML = panel('APIs externas', table(['API', 'Status', 'Tempo', 'Uso hoje'], (d.integracoes || []).map(function (i) {
            return '<tr><td>' + E(i.api) + '</td><td>' + statusBadge(i.status) + '</td><td>' + (i.tempo_ms != null ? i.tempo_ms + 'ms' : '—') + '</td><td>' + E(i.uso_hoje) + '</td></tr>';
        })));
    }

    function licensing(d, el, api) {
        el.innerHTML = paginationHtml(d.pagination, api) + panel('Licenciamento', table(['Empresa', 'Plano', 'Veíc.', 'IA/mês', 'Storage', 'API 30d', 'Extra R$'], (d.empresas || []).map(function (e) {
            return '<tr><td>' + E(e.razao_social) + '</td><td>' + E(e.plano_nome) + '</td><td>' + e.veiculos + '/' + (e.limite_veiculos_val != null ? e.limite_veiculos_val : '∞') + '</td><td>' + e.ia_mes + '</td><td>' + e.storage_mb + ' MB</td><td>' + e.api_calls + '</td><td>' + B(e.valor_extra) + '</td></tr>';
        })));
        bindPagination(el, api, licensing);
    }

    function storage(d, el) {
        el.innerHTML = noteBanner(d) + kpiBand([['Livre GB', d.espaco_livre_gb], ['Total GB', d.espaco_total_gb]], 'Armazenamento') +
            panel('Por empresa (extensão real)', table(['Empresa', 'Fotos', 'Docs', 'OS', 'Comprov.', 'Total'], (d.empresas || []).map(function (e) {
                return '<tr><td>' + E(e.empresa) + '</td><td>' + e.fotos_mb + '</td><td>' + e.documentos_mb + '</td><td>' + e.os_mb + '</td><td>' + e.comprovantes_mb + '</td><td><strong>' + e.total_mb + '</strong></td></tr>';
            })));
    }

    function dependencies(d, el) {
        el.innerHTML = panel('Dependências', table(['Pacote', 'Versão'], (d.dependencias || []).map(function (p) {
            return '<tr><td>' + E(p.nome) + '</td><td>' + E(p.versao) + '</td></tr>';
        })));
    }

    function security(d, el) {
        el.innerHTML = kpiBand([['Tentativas hoje', N(d.tentativas_hoje)], ['IPs bloqueados', N(d.ips_bloqueados)], ['Ataques hoje', N(d.ataques_hoje)], ['2FA admins', N(d.adm_2fa_ativos)]], 'Segurança');
    }

    function commercial(d, el) {
        el.innerHTML = estBanner('Churn e conversão trial são estimativas com base em status_saas e adm_eventos.') + kpiBand([
            ['Novos (mês)', N(d.clientes_novos_mes)], ['Perdidos', N(d.clientes_perdidos_mes)],
            ['Upgrades', N(d.upgrades_mes || 0)], ['Downgrades', N(d.downgrades_mes || 0)],
            ['MRR', B(d.mrr)], ['Churn', (d.churn_pct || 0) + '%']
        ], 'Comercial');
    }

    function healthScore(d, el, api) {
        el.innerHTML = paginationHtml(d.pagination, api) + panel('Score', table(['Empresa', 'Nota', 'Seg.', 'Uso', 'Fin.', 'IA'], (d.scores || []).map(function (s) {
            return '<tr><td>' + E(s.empresa) + '</td><td><strong>' + s.nota + '</strong></td><td>' + s.seguranca + '</td><td>' + s.uso + '</td><td>' + s.financeiro + '</td><td><button type="button" class="adm-cc-btn adm-cc-btn--sm adm-hs-explain" data-id="' + s.empresa_adm_id + '">Explicar</button><div class="adm-hs-exp" id="hs-exp-' + s.empresa_adm_id + '" style="font-size:0.8rem;margin-top:0.25rem"></div></td></tr>';
        })));
        el.querySelectorAll('.adm-hs-explain').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.dataset.id;
                var box = document.getElementById('hs-exp-' + id);
                box.textContent = 'Analisando…';
                AdmCC.fetch('health_score_explain.php?empresa_adm_id=' + id).then(function (r) {
                    box.textContent = r.data.explicacao_ia || '—';
                }).catch(function (e) { box.textContent = e.message; });
            });
        });
        bindPagination(el, api, healthScore);
    }

    function heatmap(d, el) {
        var max = d.max || 1;
        var html = noteBanner(d) + '<div class="adm-cc-heatmap">';
        (d.dias || []).forEach(function (dia, di) {
            html += '<div class="adm-cc-heatmap__row"><span class="adm-cc-heatmap__label">' + E(dia) + '</span><div class="adm-cc-heatmap__cells">';
            (d.grid[di] || []).forEach(function (v) {
                var op = Math.min(1, v / max);
                html += '<span class="adm-cc-heatmap__cell" style="opacity:' + (0.15 + op * 0.85) + '" title="' + v + '"></span>';
            });
            html += '</div></div>';
        });
        html += '</div>';
        el.innerHTML = panel('Uso por hora', html);
    }

    function timeline(d, el, api) {
        if (d.empresa_adm_id) {
            state.empresaFilter = String(d.empresa_adm_id);
        }
        var filter = '<div class="adm-cc-pagination" style="margin-bottom:1rem"><label>Empresa <select id="tl-empresa"><option value="">Todas</option>' +
            (d.empresas || []).map(function (e) {
                var sel = d.empresa_adm_id == e.id ? ' selected' : '';
                return '<option value="' + e.id + '"' + sel + '>' + E(e.razao_social) + '</option>';
            }).join('') + '</select></div>';
        var html = filter + '<div class="adm-cc-timeline">';
        (d.eventos || []).forEach(function (ev) {
            html += '<div class="adm-cc-timeline__item"><span class="adm-cc-timeline__time">' + E(ev.created_at) + '</span><div class="adm-cc-timeline__body"><strong>' + E(ev.modulo) + '</strong> · ' + E(ev.tipo || '') + ' · ' + E(ev.mensagem) + '</div></div>';
        });
        html += '</div>';
        el.innerHTML = paginationHtml(d.pagination, api) + panel('Timeline', html || '<div class="adm-cc-empty">Sem eventos</div>');
        var sel = el.querySelector('#tl-empresa');
        if (sel) {
            sel.addEventListener('change', function () {
                state.empresaFilter = sel.value || null;
                state.page = 1;
                state.api = 'timeline.php';
                fetchAndRender('timeline.php', timeline, el, false);
            });
        }
        bindPagination(el, api, timeline);
    }

    function configHub(d, el) {
        var cats = d.categorias || {};
        var html = '<p style="font-size:0.85rem;margin-bottom:1rem">Segredos (SMTP, API keys) vêm de variáveis de ambiente. Abaixo: parâmetros editáveis em adm_platform_config.</p>';
        Object.keys(cats).forEach(function (k) {
            html += '<div class="adm-cc-kpi"><div class="adm-cc-kpi__label">' + E(k.toUpperCase()) + '</div><div style="font-size:0.85rem">';
            Object.keys(cats[k]).forEach(function (kk) { html += E(kk) + ': ' + E(cats[k][kk]) + '<br>'; });
            html += '</div></div>';
        });
        html += '<div style="margin-top:1.5rem"><h3 style="font-size:0.95rem;margin-bottom:0.75rem">Editável</h3>';
        (d.editaveis || []).forEach(function (row) {
            html += '<div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.5rem;flex-wrap:wrap">' +
                '<label style="min-width:160px;font-size:0.85rem">' + E(row.label) + '</label>' +
                '<input type="text" class="adm-cc-input adm-cfg-val" data-chave="' + E(row.chave) + '" value="' + E(row.valor) + '" style="flex:1;min-width:120px">' +
                '<button type="button" class="adm-cc-btn adm-cc-btn--sm adm-cfg-save" data-chave="' + E(row.chave) + '">Salvar</button></div>';
        });
        html += '</div><div style="margin-top:1rem">' + (d.links || []).map(function (l) {
            return '<a class="adm-cc-btn adm-cc-btn--sm" href="' + E(l[1]) + '">' + E(l[0]) + '</a> ';
        }).join('') + '</div>';
        el.innerHTML = panel('Configurações', '<div class="adm-cc-kpi-grid">' + html + '</div>');
        el.querySelectorAll('.adm-cfg-save').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var chave = btn.dataset.chave;
                var inp = el.querySelector('.adm-cfg-val[data-chave="' + chave + '"]');
                AdmCC.fetch('platform_config.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ chave: chave, valor: inp ? inp.value : '' })
                }).then(function () { btn.textContent = 'OK'; setTimeout(function () { btn.textContent = 'Salvar'; }, 1500); })
                    .catch(function (e) { alert(e.message); });
            });
        });
    }

    function mapa(d, el) {
        el.innerHTML = kpiBand([['Online', N(d.sessoes_online)], ['No mapa', (d.pontos || []).length]], 'Mapa') +
            '<div id="adm-map" style="height:420px;border-radius:12px;border:1px solid var(--border-color)"></div>';
        setTimeout(function () {
            var mapEl = document.getElementById('adm-map');
            if (!mapEl) return;
            if (global.L && d.pontos && d.pontos.length) {
                var map = L.map('adm-map').setView([-15.78, -47.93], 4);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'OSM' }).addTo(map);
                d.pontos.forEach(function (p) {
                    L.circleMarker([p.lat, p.lng], { radius: 8, color: p.online ? '#22c55e' : '#64748b' })
                        .bindPopup(E(p.empresa) + '<br>' + E(p.cidade)).addTo(map);
                });
            } else {
                mapEl.innerHTML = '<div class="adm-cc-empty" style="padding:4rem">Nenhuma empresa cadastrada.</div>';
            }
        }, 50);
    }

    function custos(d, el) {
        var mes = d.mes || '';
        var editRows = (d.itens || []).filter(function (i) { return i.editavel; });
        var form = '<form id="custos-form"><p style="font-size:0.85rem;margin:0 0 0.75rem">Mês de referência: <strong>' + E(mes) + '</strong></p>';
        form += '<div class="adm-table-wrap"><table class="adm-table"><thead><tr><th>Provedor / custo</th><th>Valor (R$)</th><th></th></tr></thead><tbody>';
        editRows.forEach(function (i) {
            form += '<tr><td>' + E(i.nome) + (i.padrao ? ' <span style="opacity:0.55;font-size:0.75rem">padrão</span>' : '') + '</td>' +
                '<td><input type="number" step="0.01" min="0" class="adm-cc-input adm-custo-inp" name="' + E(i.categoria) + '" value="' + (parseFloat(i.valor) || 0) + '" style="width:110px"></td>' +
                '<td>' + (i.removivel ? '<button type="button" class="adm-cc-btn adm-cc-btn--sm adm-custo-del" data-cat="' + E(i.categoria) + '" title="Remover"><i class="fas fa-trash"></i></button>' : '') + '</td></tr>';
        });
        form += '</tbody></table></div>';
        form += '<button type="submit" class="adm-cc-btn adm-cc-btn--primary adm-cc-btn--sm" style="margin-top:0.75rem">Salvar valores</button></form>';

        var sugestoes = (d.sugestoes || []).map(function (s) {
            return '<button type="button" class="adm-cc-btn adm-cc-btn--sm adm-custo-sug" data-nome="' + E(s) + '">' + E(s) + '</button>';
        }).join(' ');
        var addForm = '<form id="custos-add-form" class="adm-cc-filters adm-cc-filters--flat" style="margin-top:0.5rem">' +
            '<input type="text" name="nome" id="custo-add-nome" class="adm-cc-input" placeholder="Provedor (ex. VPS, SSL, CDN)" required style="max-width:240px">' +
            '<input type="number" step="0.01" min="0" name="valor" class="adm-cc-input" placeholder="Valor R$" style="width:110px" value="0">' +
            '<button type="submit" class="adm-cc-btn adm-cc-btn--primary adm-cc-btn--sm"><i class="fas fa-plus"></i> Adicionar</button>' +
            '</form>' +
            (sugestoes ? '<p style="font-size:0.78rem;color:var(--text-secondary);margin:0.5rem 0 0">Sugestões: ' + sugestoes + '</p>' : '');

        var autoRows = (d.itens || []).filter(function (i) { return !i.editavel; });
        el.innerHTML = noteBanner(d) + kpiBand([
            ['Custo total', B(d.total_custo)], ['Receita (MRR)', B(d.receita)], ['Lucro estimado', B(d.lucro)]
        ], 'Resumo') + panel('Automático', table(['Item', 'Valor'], autoRows.map(function (i) {
            return '<tr><td>' + E(i.nome) + ' <span style="opacity:0.55;font-size:0.75rem">(adm_ia_usage)</span></td><td>' + B(i.valor) + '</td></tr>';
        }))) + panel('Provedores e custos fixos', form) + panel('Adicionar custo', addForm) +
            (d.historico && d.historico.length ? panel('Histórico', table(['Provedor', 'Mês', 'Valor'], d.historico.map(function (h) {
                return '<tr><td>' + E(h.label || h.categoria) + '</td><td>' + E(h.referencia_mes) + '</td><td>' + B(h.valor) + '</td></tr>';
            }))) : '');

        function refresh(data) {
            custos(data.custos || data, el);
        }

        var f = el.querySelector('#custos-form');
        if (f) {
            f.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var itens = {};
                f.querySelectorAll('.adm-custo-inp').forEach(function (inp) {
                    itens[inp.name] = parseFloat(inp.value) || 0;
                });
                AdmCC.fetch('platform_costs.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save', mes: mes, itens: itens })
                }).then(function (r) { refresh(r.data); })
                    .catch(function (e) { alert(e.message); });
            });
            f.querySelectorAll('.adm-custo-del').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm('Remover este custo do mês ' + mes + '?')) return;
                    AdmCC.fetch('platform_costs.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', mes: mes, categoria: btn.dataset.cat })
                    }).then(function (r) { refresh(r.data); })
                        .catch(function (e) { alert(e.message); });
                });
            });
        }

        var addF = el.querySelector('#custos-add-form');
        if (addF) {
            addF.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var nomeInp = addF.querySelector('[name="nome"]');
                var valInp = addF.querySelector('[name="valor"]');
                AdmCC.fetch('platform_costs.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add',
                        mes: mes,
                        nome: nomeInp ? nomeInp.value : '',
                        valor: valInp ? parseFloat(valInp.value) || 0 : 0
                    })
                }).then(function (r) {
                    refresh(r.data);
                    if (nomeInp) nomeInp.value = '';
                    if (valInp) valInp.value = '0';
                }).catch(function (e) { alert(e.message); });
            });
        }

        el.querySelectorAll('.adm-custo-sug').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var inp = document.getElementById('custo-add-nome');
                if (inp) {
                    inp.value = btn.dataset.nome || '';
                    inp.focus();
                }
            });
        });
    }

    function sla(d, el) {
        var meta = d.meta_sla_pct != null ? d.meta_sla_pct + '%' : '—';
        var probe = '';
        if (d.probe_externo && d.probe_externo.url) {
            probe = '<p style="font-size:0.82rem;color:var(--text-secondary);margin:0 0 0.75rem">Probe externo: ' + E(d.probe_externo.url) + ' — ' + (d.probe_externo.ok ? 'OK' : 'Falha') + ' (' + N(d.probe_externo.latencia_ms) + 'ms)</p>';
        }
        el.innerHTML = estBanner('SLA calculado a partir de health checks e métricas HTTP — meta configurável em Configurações CC.') + probe + kpiBand([
            ['Disponibilidade', d.disponibilidade_pct + '%'], ['Meta SLA', meta], ['Status', d.sla_ok ? 'OK' : 'Abaixo'],
            ['Resposta', d.tempo_resposta_ms + 'ms'], ['Pico', N(d.pico_usuarios)]
        ], 'SLA');
    }

    function benchmark(d, el) {
        el.innerHTML = panel('Rankings', '<div class="adm-cc-kpi-grid">' + (d.rankings || []).map(function (r) {
            return '<div class="adm-cc-kpi"><div class="adm-cc-kpi__label">' + E(r.titulo) + '</div><div class="adm-cc-kpi__value" style="font-size:1rem">' + E(r.empresa) + '</div></div>';
        }).join('') + '</div>') + panel('Todas as empresas', table(['Empresa', 'Veículos', 'IA', 'Faturamento'], (d.detalhe || []).map(function (r) {
            return '<tr><td>' + E(r.razao_social) + '</td><td>' + N(r.veiculos) + '</td><td>' + N(r.ia_uso) + '</td><td>' + B(r.faturamento) + '</td></tr>';
        })));
    }

    function marketplace(d, el, api) {
        var mods = d.modulos || [];
        var emps = d.empresas || [];
        var matrix = d.matrix || {};
        var filter = '<div class="adm-cc-pagination" style="margin-bottom:0.75rem"><input type="search" id="mp-search" class="adm-cc-input" placeholder="Filtrar empresa…" style="min-width:220px"></div>';
        var rows = emps.map(function (emp) {
            var cells = mods.map(function (m) {
                var on = matrix[emp.id + ':' + m.id];
                return '<td style="text-align:center"><input type="checkbox" class="adm-mp-toggle" data-e="' + emp.id + '" data-m="' + m.id + '" ' + (on ? 'checked' : '') + '></td>';
            }).join('');
            return '<tr class="adm-mp-row" data-name="' + String(emp.razao_social || '').toLowerCase().replace(/"/g, '') + '"><td>' + E(emp.razao_social) + '</td>' + cells + '</tr>';
        });
        var head = '<tr><th>Empresa</th>' + mods.map(function (m) { return '<th title="' + E(m.descricao) + '">' + E(m.nome) + '</th>'; }).join('') + '</tr>';
        el.innerHTML = paginationHtml(d.pagination, api) + panel('Ativar módulos por empresa', filter + '<div class="adm-table-wrap" style="overflow:auto"><table class="adm-table" id="mp-table"><thead>' + head + '</thead><tbody>' + rows.join('') + '</tbody></table></div>');
        bindPagination(el, api, marketplace);
        var search = el.querySelector('#mp-search');
        if (search) {
            search.addEventListener('input', function () {
                var q = search.value.toLowerCase().trim();
                el.querySelectorAll('.adm-mp-row').forEach(function (tr) {
                    var name = tr.getAttribute('data-name') || '';
                    tr.style.display = !q || name.indexOf(q) >= 0 ? '' : 'none';
                });
            });
        }
        el.querySelectorAll('.adm-mp-toggle').forEach(function (cb) {
            cb.addEventListener('change', function () {
                AdmCC.fetch('marketplace.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ empresa_adm_id: parseInt(cb.dataset.e, 10), modulo_id: parseInt(cb.dataset.m, 10), ativo: cb.checked })
                }).catch(function (e) { alert(e.message); cb.checked = !cb.checked; });
            });
        });
    }

    function iaDev(d, el) {
        el.innerHTML = kpiBand([
            ['Arquivos grandes', N(d.arquivos_grandes)], ['Includes repetidos', N(d.includes_repetidos)],
            ['CSS duplicados', N(d.css_repetidos)], ['JS duplicados', N(d.js_repetidos)],
            ['Consultas lentas (7d)', N(d.consultas_lentas)], ['Páginas fora do menu', N(d.arquivos_mortos)]
        ], 'Auditoria de código') +
            panel('> 80KB', table(['Arquivo', 'KB'], (d.lista_grandes || []).map(function (f) {
                return '<tr><td><code>' + E(f.arquivo) + '</code></td><td>' + f.kb + '</td></tr>';
            }))) +
            panel('CSS mesmo nome', table(['Arquivo', 'Cópias'], (d.lista_css_dupes || []).map(function (f) {
                return '<tr><td>' + E(f.arquivo) + '</td><td>' + f.caminhos + '</td></tr>';
            }))) +
            panel('JS mesmo nome', table(['Arquivo', 'Cópias'], (d.lista_js_dupes || []).map(function (f) {
                return '<tr><td>' + E(f.arquivo) + '</td><td>' + f.caminhos + '</td></tr>';
            }))) +
            panel('Páginas ADM fora do sidebar', '<ul>' + (d.lista_orfaos || []).map(function (s) { return '<li><code>' + E(s) + '</code></li>'; }).join('') + '</ul>') +
            panel('Sugestões', '<ul>' + (d.sugestoes || []).map(function (s) { return '<li>' + E(s) + '</li>'; }).join('') + '</ul>');
    }

    var renderers = {
        deploy: deploy, banco: banco, sqlMonitor: sqlMonitor, jobs: jobs, apis: apis,
        licensing: licensing, storage: storage, dependencies: dependencies, security: security,
        commercial: commercial, healthScore: healthScore, heatmap: heatmap, timeline: timeline,
        configHub: configHub, mapa: mapa, custos: custos, sla: sla, benchmark: benchmark,
        marketplace: marketplace, iaDev: iaDev
    };

    global.AdmPhase2 = {
        load: load,
        renderers: renderers,
        deploy: deploy, banco: banco, sqlMonitor: sqlMonitor, jobs: jobs, apis: apis,
        licensing: licensing, storage: storage, dependencies: dependencies, security: security,
        commercial: commercial, healthScore: healthScore, heatmap: heatmap, timeline: timeline,
        configHub: configHub, mapa: mapa, custos: custos, sla: sla, benchmark: benchmark,
        marketplace: marketplace, iaDev: iaDev
    };
})(typeof window !== 'undefined' ? window : this);
