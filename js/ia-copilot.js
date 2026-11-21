/**
 * Centro de Inteligência — Copiloto de Operações (hub completo)
 */
(function () {
    'use strict';

    var chat = document.getElementById('aiChat');
    var input = document.getElementById('aiInput');
    var send = document.getElementById('aiSend');
    var micBtn = document.getElementById('aiMic');
    var chipsEl = document.getElementById('aiChips');
    var insightsEl = document.getElementById('ciInsights');
    var insightsInline = document.getElementById('ciInsightsInline');
    var execBtn = document.getElementById('ciExecutivo');
    var streamChk = document.getElementById('ciUseStream');
    var sugerirBtn = document.getElementById('ciSugerirAcoes');
    var csrf = (window.SF_IA && window.SF_IA.csrf) || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    if (!chat || !input || !send) return;

    var lastIntent = '';
    var modoAtivo = 'conversar';
    var recognition = null;

    var CHIPS = {
        conversar: [
            'Quantas rotas ativas tenho agora?',
            'Qual motorista mais faturou este mês?',
            'Quanto gastei de manutenção?',
            'Previsões para os próximos 30 dias',
            'Alguma CNH vencendo?'
        ],
        analisar: [
            'Analise completa da operação',
            'Mapa da frota agora',
            'Analise minha empresa',
            'Quem mais atrasou?',
            'Comparar com mês passado',
            'Como cadastrar motorista?'
        ],
        automatizar: [
            'Resumo executivo de hoje',
            'Quantas multas este mês?',
            'Quantas rotas no mês?',
            'Quantas manutenções abertas?'
        ],
        explicar: [
            'O que significa custo/km?',
            'Como foi o mês passado?',
            'Quantos pneus tenho?',
            'Explique previsão de manutenção'
        ],
        recomendar: [
            'Analise minha empresa',
            'Qual veículo ficou mais parado?',
            'Alguma CNH vencendo?',
            'Previsões para os próximos 30 dias'
        ]
    };

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function stars(n) {
        n = Math.max(0, Math.min(5, parseInt(n, 10) || 0));
        var h = '';
        for (var i = 0; i < 5; i++) h += i < n ? '★' : '☆';
        return h;
    }

    function renderChips(modo) {
        if (!chipsEl) return;
        var list = CHIPS[modo] || CHIPS.conversar;
        chipsEl.innerHTML = list.map(function (t) {
            return '<span class="ai-chip" role="button" tabindex="0">' + esc(t) + '</span>';
        }).join('');
    }

    function tabela(rows) {
        if (!rows || !rows.length) return '';
        var cols = Object.keys(rows[0]);
        var h = '<table class="ai-table"><thead><tr>' + cols.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>';
        h += rows.map(function (r) {
            return '<tr>' + cols.map(function (c) { return '<td>' + esc(r[c]) + '</td>'; }).join('') + '</tr>';
        }).join('');
        return h + '</tbody></table>';
    }

    function renderCards(cards) {
        if (!cards || !cards.length) return '';
        return '<div class="ai-cards">' + cards.map(function (c) {
            var formula = '';
            if (c.formula) {
                formula = '<button type="button" class="ai-formula-btn" title="Como calculou?" data-formula="' + esc(c.formula) + '">ℹ️</button>';
            }
            var inputsHtml = '';
            if (c.inputs && typeof c.inputs === 'object') {
                inputsHtml = '<ul class="ai-formula-inputs">' + Object.keys(c.inputs).map(function (k) {
                    return '<li><strong>' + esc(k) + ':</strong> ' + esc(c.inputs[k]) + '</li>';
                }).join('') + '</ul>';
            }
            return '<div class="ai-card"><div class="k">' + esc(c.title) + formula + '</div><div class="v">' + esc(c.value) + '</div>' +
                (c.formula ? '<div class="ai-formula-detail" hidden>' + esc(c.formula) + inputsHtml + '</div>' : '') +
                '</div>';
        }).join('') + '</div>';
    }

    function renderFontesClicaveis(fontes) {
        if (!fontes || !fontes.length) return '';
        var html = '<div class="ai-block ai-fontes-clic"><h4>Fontes</h4><div class="ai-fontes-links">';
        fontes.forEach(function (f) {
            if (f.url) {
                html += '<a href="' + esc(f.url) + '" class="ai-fonte-link" target="_blank" rel="noopener">' + esc(f.nome);
                if (f.count != null) html += ' (' + f.count + ')';
                html += '</a> ';
            } else {
                html += '<span class="ai-fonte-plain">' + esc(f.nome) + '</span> ';
            }
        });
        return html + '</div></div>';
    }

    function renderRaciocinio(list) {
        if (!list || !list.length) return '';
        return '<details class="ai-raciocinio"><summary>Raciocínio (' + list.length + ')</summary><ol>' +
            list.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') +
            '</ol></details>';
    }

    function renderTools(tools) {
        if (!tools || !tools.length) return '';
        return '<details class="ai-tools"><summary>Ferramentas (' + tools.length + ')</summary><ul class="ai-tools-list">' +
            tools.map(function (t) {
                return '<li><strong>' + esc(t.label || t.id) + '</strong> · ' + esc(t.tempo_ms) + ' ms' +
                    (t.linhas ? ' · ' + t.linhas + ' reg.' : '') +
                    (t.detalhe ? ' — ' + esc(t.detalhe) : '') + '</li>';
            }).join('') + '</ul></details>';
    }

    function renderMapInBubble(bubble, mapData) {
        if (!mapData || !mapData.markers || !mapData.markers.length || typeof L === 'undefined') return;
        var box = document.createElement('div');
        box.className = 'ai-map-wrap';
        box.style.height = '220px';
        bubble.appendChild(box);
        var m = L.map(box).setView([mapData.markers[0].lat, mapData.markers[0].lng], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OSM' }).addTo(m);
        var bounds = [];
        mapData.markers.forEach(function (mk) {
            if (!mk.lat || !mk.lng) return;
            L.marker([mk.lat, mk.lng]).addTo(m).bindPopup(esc(mk.placa || '') + '<br>' + esc(mk.hora || ''));
            bounds.push([mk.lat, mk.lng]);
        });
        if (bounds.length > 1) m.fitBounds(bounds, { padding: [20, 20] });
    }

    function renderBotPayload(d, answerOverride) {
        var answerText = answerOverride != null ? answerOverride : d.answer;
        var html = '<div class="ai-answer-text">' + esc(answerText) + '</div>';
        if (d.agente_label) {
            html += '<div class="ai-agente"><i class="fas fa-user-astronaut"></i> ' + esc(d.agente_label);
            if (d.skill_label) html += ' · <span class="ai-skill">' + esc(d.skill_label) + '</span>';
            html += '</div>';
        }
        if (d.from_cache) {
            html += '<div class="ai-meta">⚡ Resposta em cache (15 min)</div>';
        }
        if (d.cards && d.cards.length) html += renderCards(d.cards);
        else if (d.rows && d.rows.length && !(d.rows.length === 1 && Object.keys(d.rows[0]).length <= 1)) {
            html += tabela(d.rows);
        }
        if (d.comparacao && d.comparacao.texto) {
            html += '<div class="ai-block"><h4>Comparado</h4><p>' + esc(d.comparacao.texto) + '</p>';
            if (d.comparacao.motivos && d.comparacao.motivos.length) {
                html += '<ul>' + d.comparacao.motivos.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>';
            }
            html += '</div>';
        }
        if (d.analise && d.analise.length) {
            html += '<div class="ai-block"><h4>Análise</h4><ul>' + d.analise.map(function (a) { return '<li>' + esc(a) + '</li>'; }).join('') + '</ul></div>';
        }
        if (d.recomendacoes && d.recomendacoes.length) {
            html += '<div class="ai-block"><h4>Recomendação</h4><ul>' + d.recomendacoes.map(function (r) { return '<li>' + esc(r) + '</li>'; }).join('') + '</ul></div>';
        }
        if (d.confianca) {
            html += '<div class="ai-conf"><span class="ai-stars" title="Confiança">' + stars(d.confianca.stars) + '</span>';
            html += '<span>' + esc(d.confianca.score) + '% · ' + esc(d.confianca.label) + '</span>';
            if (d.confianca.motivos) {
                html += '<span>' + d.confianca.motivos.map(function (m) { return esc(m); }).join(' · ') + '</span>';
            }
            html += '</div>';
        }
        if (d.fontes_clicaveis && d.fontes_clicaveis.length) {
            html += renderFontesClicaveis(d.fontes_clicaveis);
        } else if (d.fontes && d.fontes.length) {
            html += '<div class="ai-block ai-fontes"><h4>Fontes utilizadas</h4><p>' +
                d.fontes.map(function (f) { return '<span>' + esc(f) + '</span>'; }).join(' ') + '</p></div>';
        }
        if (d.acao_pendente && d.acao_pendente.id) {
            html += '<div class="ai-acao-confirm"><button type="button" class="ci-btn-ok ci-btn-inline-confirm" data-acao-id="' + esc(d.acao_pendente.id) + '">' +
                '<i class="fas fa-check"></i> ' + esc(d.acao_pendente.label || 'Confirmar') + '</button>' +
                '<button type="button" class="ci-btn-cancel ci-btn-inline-cancel" data-acao-id="' + esc(d.acao_pendente.id) + '">Cancelar</button></div>';
        }
        if (d.acao_tela && d.acao_tela.url) {
            html += '<div class="ai-acao-tela"><a href="' + esc(d.acao_tela.url) + '" class="ci-btn-exec ci-btn-link" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> ' +
                esc(d.acao_tela.titulo || 'Abrir tela') + '</a></div>';
        }
        html += renderRaciocinio(d.raciocinio);
        html += renderTools(d.tools);
        if (d.dev && d.timeline && d.timeline.length) {
            html += '<details class="ai-tools" open><summary>Timeline (dev)</summary><ol class="ai-tools-list">';
            d.timeline.forEach(function (t) {
                html += '<li><code>' + esc(t.fase) + '</code></li>';
            });
            html += '</ol></details>';
        }
        if (d.periodo) {
            var viaTxt = d.via === 'ia' ? ' · 🧠 IA' : (d.via === 'contexto' ? ' · ↩️ contexto' : '');
            html += '<div class="ai-meta">Período: ' + esc(d.periodo) + ' · ' + esc(d.intent) + viaTxt + '</div>';
        }
        if (d.sugestoes && d.sugestoes.length) {
            html += '<div class="ai-block"><h4>Você pode perguntar</h4><div class="ai-sugestoes">' +
                d.sugestoes.map(function (s) {
                    return '<button type="button" class="ai-sug" data-sug="' + esc(s) + '">→ ' + esc(s) + '</button>';
                }).join('') + '</div></div>';
        }
        html += '<div class="ai-feedback" data-intent="' + esc(d.intent || '') + '">' +
            '<button type="button" class="ai-fb" data-util="1" title="Útil">👍</button>' +
            '<button type="button" class="ai-fb" data-util="0" title="Não útil">👎</button></div>';
        return html;
    }

    function renderChart(bubble, ch) {
        if (!ch || !ch.labels || ch.labels.length < 2 || typeof Chart === 'undefined') return;
        var box = document.createElement('div');
        box.style.cssText = 'height:190px;margin-top:10px';
        bubble.appendChild(box);
        var cv = document.createElement('canvas');
        box.appendChild(cv);
        new Chart(cv.getContext('2d'), {
            type: ch.type === 'line' ? 'line' : 'bar',
            data: {
                labels: ch.labels,
                datasets: [{
                    label: ch.label,
                    data: ch.values,
                    borderColor: '#3b82f6',
                    backgroundColor: ch.type === 'line' ? 'rgba(59,130,246,.25)' : 'rgba(59,130,246,.6)',
                    tension: .3,
                    borderWidth: 2,
                    pointRadius: 2,
                    fill: ch.type === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: ch.label, color: '#94a3b8', font: { size: 11 } }
                },
                scales: {
                    x: { ticks: { color: '#94a3b8', font: { size: 9 }, maxTicksLimit: 8 }, grid: { display: false } },
                    y: { ticks: { color: '#94a3b8', font: { size: 9 } }, grid: { color: 'rgba(148,163,184,.12)' } }
                }
            }
        });
    }

    function addMsg(role, html) {
        var m = document.createElement('div');
        m.className = 'ai-msg ' + role;
        var icon = role === 'user' ? 'fa-user' : 'fa-robot';
        m.innerHTML = '<div class="av"><i class="fas ' + icon + '"></i></div><div class="ai-bubble">' + html + '</div>';
        chat.appendChild(m);
        chat.scrollTop = chat.scrollHeight;
        return m;
    }

    function bindBotInteractions(msgEl, d) {
        if (!msgEl) return;
        msgEl.querySelectorAll('.ai-sug').forEach(function (btn) {
            btn.addEventListener('click', function () {
                perguntar(btn.getAttribute('data-sug') || btn.textContent.replace(/^\s*→\s*/, ''));
            });
        });
        msgEl.querySelectorAll('.ai-fb').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('action', 'feedback');
                fd.append('util', btn.getAttribute('data-util') || '0');
                fd.append('intent', d.intent || '');
                fetch('../api/ia_assistente.php', { method: 'POST', body: fd, credentials: 'include' });
                btn.disabled = true;
            });
        });
        msgEl.querySelectorAll('.ai-formula-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.ai-card');
                var det = card ? card.querySelector('.ai-formula-detail') : null;
                if (det) {
                    det.hidden = !det.hidden;
                }
            });
        });
        msgEl.querySelectorAll('.ci-btn-inline-confirm').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executando…';
                confirmarAcao(btn.getAttribute('data-acao-id'), btn);
            });
        });
        msgEl.querySelectorAll('.ci-btn-inline-cancel').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var wrap = btn.closest('.ai-acao-confirm');
                cancelarAcao(btn.getAttribute('data-acao-id'));
                if (wrap) wrap.innerHTML = '<span class="ai-meta">Ação cancelada.</span>';
            });
        });
        if (d.chart) renderChart(msgEl.querySelector('.ai-bubble'), d.chart);
        if (d.map) renderMapInBubble(msgEl.querySelector('.ai-bubble'), d.map);
    }

    function perguntarStream(texto) {
        var botMsg = addMsg('bot', '<div class="ai-phase"><i class="fas fa-spinner fa-spin"></i> consultando…</div><div class="ai-answer-text"></div>');
        var bubble = botMsg.querySelector('.ai-bubble');
        var answerEl = bubble.querySelector('.ai-answer-text');
        var phaseEl = bubble.querySelector('.ai-phase');
        var answerAcc = '';
        var url = '../api/ia_assistente_stream.php?pergunta=' + encodeURIComponent(texto) +
            '&contexto=' + encodeURIComponent(lastIntent) + '&modo=' + encodeURIComponent(modoAtivo) +
            (window.SF_IA_DEV ? '&dev=1' : '');
        var es = new EventSource(url);
        var gotDone = false;

        function finish() {
            send.disabled = false;
            input.focus();
        }

        es.addEventListener('phase', function (ev) {
            try {
                var p = JSON.parse(ev.data);
                if (p.msg && phaseEl) {
                    phaseEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + esc(p.msg);
                }
            } catch (e) { /* ignore */ }
        });

        es.addEventListener('token', function (ev) {
            try {
                var t = JSON.parse(ev.data);
                if (phaseEl) { phaseEl.remove(); phaseEl = null; }
                answerAcc += t.t || '';
                answerEl.textContent = answerAcc;
                chat.scrollTop = chat.scrollHeight;
            } catch (e) { /* ignore */ }
        });

        es.addEventListener('done', function (ev) {
            gotDone = true;
            es.close();
            try {
                var d = JSON.parse(ev.data);
                if (!d || !d.success) {
                    bubble.innerHTML = esc((d && d.error) ? d.error : 'Não consegui responder.');
                    finish();
                    return;
                }
                if (d.intent && d.intent !== 'ajuda') lastIntent = d.intent;
                bubble.innerHTML = renderBotPayload(d, answerAcc || d.answer);
                bindBotInteractions(botMsg, d);
            } catch (e) {
                bubble.innerHTML = 'Erro ao processar resposta.';
            }
            finish();
        });

        es.addEventListener('error', function () {
            if (gotDone) return;
            es.close();
            bubble.innerHTML = 'Erro no streaming — desative Streaming ou tente de novo.';
            finish();
        });
    }

    function perguntarFetch(texto, loading) {
        var devQ = window.SF_IA_DEV ? '&dev=1' : '';
        fetch('../api/ia_assistente.php?pergunta=' + encodeURIComponent(texto) + '&contexto=' + encodeURIComponent(lastIntent) + '&modo=' + encodeURIComponent(modoAtivo) + devQ, { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                loading.remove();
                if (!d || !d.success) {
                    addMsg('bot', esc((d && d.error) ? d.error : 'Não consegui responder agora.'));
                    return;
                }
                if (d.intent && d.intent !== 'ajuda') lastIntent = d.intent;
                var botMsg = addMsg('bot', renderBotPayload(d));
                bindBotInteractions(botMsg, d);
            })
            .catch(function () {
                loading.remove();
                addMsg('bot', 'Erro de conexão.');
            })
            .finally(function () {
                send.disabled = false;
                input.focus();
            });
    }

    function perguntar(texto) {
        texto = (texto || '').trim();
        if (!texto) return;
        addMsg('user', esc(texto));
        input.value = '';
        send.disabled = true;
        var loading = addMsg('bot', '<i class="fas fa-spinner fa-spin"></i> consultando…');

        if (streamChk && streamChk.checked && window.EventSource) {
            loading.remove();
            perguntarStream(texto);
        } else {
            perguntarFetch(texto, loading);
        }
    }

    function renderInsightsHtml(data, target) {
        if (!target) return;
        if (!data || !data.itens || !data.itens.length) {
            target.innerHTML = '<h3>' + esc(data.saudacao || 'Olá!') + '</h3><p class="ai-meta">Nenhum alerta automático no momento. ✔</p>';
            return;
        }
        var html = '<h3>' + esc(data.saudacao || 'Olá!') + ' — ' + data.total + ' ponto(s) importantes</h3><ul>';
        data.itens.forEach(function (it) {
            html += '<li>' + esc(it.texto);
            if (it.acao) html += ' — <button type="button" data-acao="' + esc(it.acao) + '">' + esc(it.acao) + '</button>';
            html += '</li>';
        });
        html += '</ul>';
        target.innerHTML = html;
        target.querySelectorAll('button[data-acao]').forEach(function (b) {
            b.addEventListener('click', function () {
                showPanel('chat');
                perguntar(b.getAttribute('data-acao'));
            });
        });
    }

    function carregarInsights() {
        fetch('../api/ia_assistente.php?action=insights', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) return;
                renderInsightsHtml(d.data, insightsEl);
                renderInsightsHtml(d.data, insightsInline);
            })
            .catch(function () {});
    }

    function resumoExecutivo() {
        showPanel('chat');
        addMsg('user', esc('Resumo executivo de hoje'));
        var loading = addMsg('bot', '<i class="fas fa-spinner fa-spin"></i> montando resumo…');
        fetch('../api/ia_assistente.php?action=executivo', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                loading.remove();
                if (!d || !d.success) {
                    addMsg('bot', 'Não foi possível gerar o resumo executivo.');
                    return;
                }
                lastIntent = 'executivo';
                var botMsg = addMsg('bot', renderBotPayload(d));
                bindBotInteractions(botMsg, d);
            })
            .catch(function () {
                loading.remove();
                addMsg('bot', 'Erro de conexão.');
            });
    }

    function showPanel(name) {
        document.querySelectorAll('.ci-section').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-panel') === name);
        });
        document.querySelectorAll('.ci-panel').forEach(function (p) {
            p.classList.toggle('ci-panel-hidden', p.id !== 'panel-' + name);
        });
        if (name === 'previsoes') carregarPrevisoes();
        if (name === 'documentos') carregarDocumentos();
        if (name === 'automacoes') carregarAcoes();
        if (name === 'auditoria') carregarAuditoria();
        if (name === 'saude') carregarSaude();
        if (name === 'skills') carregarSkills();
        if (name === 'aprendizado') carregarAprendizado();
        if (name === 'memoria') carregarMemoria();
    }

    function carregarAprendizado() {
        var pend = document.getElementById('ciAprendPend');
        var cus = document.getElementById('ciAprendCustom');
        if (!pend) return;
        fetch('../api/ia_aprendizado.php?action=listar', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) { pend.innerHTML = '<p class="ai-meta">Sem permissão ou erro ao carregar.</p>'; return; }
                var opts = (d.intents || []).map(function (i) { return '<option value="' + esc(i) + '">'; }).join('');
                var listaId = 'ciIntentsDL';
                var dl = '<datalist id="' + listaId + '">' + opts + '</datalist>';
                if (!(d.pendentes || []).length) {
                    pend.innerHTML = dl + '<p class="ai-meta">✅ Nenhuma pergunta pendente. A IA está entendendo tudo!</p>';
                } else {
                    pend.innerHTML = dl + (d.pendentes).map(function (p) {
                        return '<div class="ci-acao-card" data-pid="' + esc(p.id) + '">' +
                            '<h4>“' + esc(p.pergunta) + '”</h4>' +
                            '<p class="ai-meta">' + esc(p.ocorrencias) + ' ocorrência(s)</p>' +
                            '<div class="ci-acao-btns" style="flex-wrap:wrap">' +
                            '<input list="' + listaId + '" class="ap-intent" placeholder="intenção (ex.: manutencao)" ' +
                            'style="padding:7px 10px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);font-size:12px;min-width:180px">' +
                            '<button type="button" class="ci-btn-ok ap-map"><i class="fas fa-check"></i> Mapear</button>' +
                            '<button type="button" class="ci-btn-cancel ap-ign">Ignorar</button>' +
                            '</div></div>';
                    }).join('');
                }
                if (cus) {
                    cus.innerHTML = (d.customs || []).length
                        ? '<table class="ai-table"><thead><tr><th>Padrão</th><th>Intenção</th><th>Ativo</th></tr></thead><tbody>' +
                          d.customs.map(function (c) {
                              return '<tr><td>' + esc(c.padrao) + '</td><td>' + esc(c.intent) + '</td>' +
                                  '<td><button type="button" class="ai-fb ap-toggle" data-cid="' + esc(c.id) + '" data-ativo="' + esc(c.ativo) + '">' +
                                  (Number(c.ativo) ? '🟢' : '⚪') + '</button></td></tr>';
                          }).join('') + '</tbody></table>'
                        : '<p class="ai-meta">Nenhum sinônimo ainda.</p>';
                }
                bindAprendizado();
            })
            .catch(function () { pend.innerHTML = '<p class="ai-meta">Erro de conexão.</p>'; });
    }

    function apPost(params) {
        params.csrf_token = (window.SF_IA && window.SF_IA.csrf) || '';
        return fetch('../api/ia_aprendizado.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()
        }).then(function (r) { return r.json(); });
    }

    function bindAprendizado() {
        document.querySelectorAll('#ciAprendPend .ap-map').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.ci-acao-card');
                var intent = (card.querySelector('.ap-intent').value || '').trim();
                if (!intent) { card.querySelector('.ap-intent').focus(); return; }
                btn.disabled = true;
                apPost({ action: 'mapear', id: card.getAttribute('data-pid'), intent: intent }).then(function (d) {
                    if (d && d.success) { carregarAprendizado(); } else { btn.disabled = false; alert((d && d.error) || 'Erro ao mapear.'); }
                });
            });
        });
        document.querySelectorAll('#ciAprendPend .ap-ign').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.ci-acao-card');
                apPost({ action: 'ignorar', id: card.getAttribute('data-pid') }).then(function () { carregarAprendizado(); });
            });
        });
        document.querySelectorAll('#ciAprendCustom .ap-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var novo = Number(btn.getAttribute('data-ativo')) ? 0 : 1;
                apPost({ action: 'toggle_custom', id: btn.getAttribute('data-cid'), ativo: novo }).then(function () { carregarAprendizado(); });
            });
        });
    }

    function carregarSkills() {
        var el = document.getElementById('ciSkills');
        if (!el) return;
        fetch('../api/ia_assistente.php?action=skills', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) {
                    el.innerHTML = '<p class="ai-meta">Erro ao carregar skills.</p>';
                    return;
                }
                var html = '<div class="ai-skills-grid">';
                (d.data || []).forEach(function (s) {
                    html += '<div class="ai-skill-card' + (s.ativo ? '' : ' ai-skill-card--off') + '">';
                    html += '<i class="fas ' + esc(s.icon) + '"></i><strong>' + esc(s.label) + '</strong>';
                    html += '<span>' + esc(s.intents) + ' intenções · ' + esc(s.tools) + ' tools</span>';
                    if (!s.ativo) html += '<em>Permissão necessária</em>';
                    html += '</div>';
                });
                el.innerHTML = html + '</div>';
            });
    }

    function carregarSaude() {
        var el = document.getElementById('ciSaude');
        if (!el) return;
        el.innerHTML = '<p class="ai-meta"><i class="fas fa-spinner fa-spin"></i> Carregando…</p>';
        fetch('../api/ia_saude.php', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.data) {
                    el.innerHTML = '<p class="ai-meta">Sem métricas ainda.</p>';
                    return;
                }
                var m = d.data;
                var uso = m.uso_mes_empresa || {};
                el.innerHTML = '<div class="ai-saude-grid">' +
                    '<div class="ai-saude-kpi"><span>Consultas hoje</span><strong>' + esc(m.consultas_hoje) + '</strong></div>' +
                    '<div class="ai-saude-kpi"><span>Tempo médio</span><strong>' + esc(m.tempo_medio_ms) + ' ms</strong></div>' +
                    '<div class="ai-saude-kpi"><span>Cache hit</span><strong>' + esc(m.cache_hit_pct) + '%</strong></div>' +
                    '<div class="ai-saude-kpi"><span>Chamadas LLM</span><strong>' + esc(m.llm_pct) + '%</strong></div>' +
                    '<div class="ai-saude-kpi"><span>Falhas hoje</span><strong>' + esc(m.falhas_hoje) + '</strong></div>' +
                    '<div class="ai-saude-kpi"><span>Tokens hoje</span><strong>' + esc(m.tokens_hoje) + '</strong></div>' +
                    '</div>' +
                    '<p class="ai-meta">Mês: ' + esc(uso.consultas) + ' consultas · ' + esc(uso.tokens) + ' tokens · ~$' + esc(uso.custo_usd) + ' USD estimado</p>';
                if (m.intent_erros && m.intent_erros.length) {
                    el.innerHTML += '<div class="ai-block"><h4>Intents com falha (7 dias)</h4><ul>' +
                        m.intent_erros.map(function (r) {
                            return '<li><code>' + esc(r.intent) + '</code> — ' + esc(r.f) + ' falha(s) / ' + esc(r.c) + ' consulta(s)</li>';
                        }).join('') + '</ul></div>';
                }
                if (m.intent_top && m.intent_top.length) {
                    el.innerHTML += '<div class="ai-block"><h4>Top intents (30 dias)</h4><ul>' +
                        m.intent_top.map(function (r) {
                            return '<li><code>' + esc(r.intent) + '</code> — ' + esc(r.c) + '</li>';
                        }).join('') + '</ul></div>';
                }
                if (m.modelos_top && m.modelos_top.length) {
                    el.innerHTML += '<div class="ai-block"><h4>Modelos (30 dias)</h4><ul>' +
                        m.modelos_top.map(function (r) {
                            return '<li>' + esc(r.modelo) + ' — ' + esc(r.c) + ' consulta(s)</li>';
                        }).join('') + '</ul></div>';
                }
                if (m.top_usuarios && m.top_usuarios.length) {
                    el.innerHTML += '<div class="ai-block"><h4>Top usuários (30 dias)</h4><ul>' +
                        m.top_usuarios.map(function (r) {
                            return '<li>' + esc(r.nome || ('#' + r.usuario_id)) + ' — ' + esc(r.c) + ' consulta(s)</li>';
                        }).join('') + '</ul></div>';
                }
            })
            .catch(function () { el.innerHTML = '<p class="ai-meta">Erro ao carregar saúde.</p>'; });
    }

    function carregarPrevisoes() {
        var el = document.getElementById('ciPrevisoes');
        if (!el) return;
        el.innerHTML = '<p class="ai-meta"><i class="fas fa-spinner fa-spin"></i> Calculando…</p>';
        fetch('../api/ia_assistente.php?action=previsoes', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.data) {
                    el.innerHTML = '<p class="ai-meta">Sem previsões.</p>';
                    return;
                }
                var data = d.data;
                var html = '<h3>' + esc(data.resumo) + '</h3><ul class="ci-list">';
                (data.itens || []).forEach(function (it) {
                    html += '<li><strong>' + esc(it.titulo) + '</strong><br><span class="ai-meta">' + esc(it.detalhe) + '</span></li>';
                });
                html += '</ul>';
                el.innerHTML = html;
            })
            .catch(function () { el.innerHTML = '<p class="ai-meta">Erro ao carregar.</p>'; });
    }

    function carregarDocumentos() {
        var el = document.getElementById('ciDocList');
        if (!el) return;
        fetch('../api/ia_documentos.php?action=listar', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.data || !d.data.length) {
                    el.innerHTML = '<p class="ai-meta">Nenhum documento enviado. Faça upload para perguntar sobre NF/contratos.</p>';
                    return;
                }
                var html = '<table class="ai-table"><thead><tr><th>Arquivo</th><th>Status</th><th>Chars</th><th>Data</th></tr></thead><tbody>';
                d.data.forEach(function (row) {
                    html += '<tr><td>' + esc(row.nome_arquivo) + '</td><td>' + esc(row.status) + '</td><td>' + esc(row.chars) + '</td><td>' + esc(row.created_at) + '</td></tr>';
                });
                html += '</tbody></table>';
                el.innerHTML = html;
            });
    }

    function carregarAcoes() {
        var el = document.getElementById('ciAcoes');
        if (!el) return;
        fetch('../api/ia_acoes.php?action=listar&status=pendente', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.data || !d.data.length) {
                    el.innerHTML = '<p class="ai-meta">Nenhuma ação pendente.</p>';
                    return;
                }
                var html = '';
                d.data.forEach(function (a) {
                    html += '<div class="ci-acao-card"><h4>' + esc(a.titulo) + '</h4><p>' + esc(a.resumo) + '</p>';
                    html += '<div class="ci-acao-btns">';
                    html += '<button type="button" class="ci-btn-ok" data-id="' + a.id + '">Confirmar</button>';
                    html += '<button type="button" class="ci-btn-cancel" data-id="' + a.id + '">Cancelar</button>';
                    html += '</div></div>';
                });
                el.innerHTML = html;
                el.querySelectorAll('.ci-btn-ok').forEach(function (btn) {
                    btn.addEventListener('click', function () { confirmarAcao(btn.getAttribute('data-id')); });
                });
                el.querySelectorAll('.ci-btn-cancel').forEach(function (btn) {
                    btn.addEventListener('click', function () { cancelarAcao(btn.getAttribute('data-id')); });
                });
            });
    }

    function confirmarAcao(id, inlineBtn) {
        var fd = new FormData();
        fd.append('action', 'confirmar');
        fd.append('id', id);
        fd.append('csrf_token', csrf);
        return fetch('../api/ia_acoes.php', { method: 'POST', body: fd, credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.redirect) {
                    window.open(d.redirect, '_blank');
                }
                var texto = d.message || d.error || (d.success ? 'Executado.' : 'Falhou.');
                if (inlineBtn) {
                    var wrap = inlineBtn.closest('.ai-acao-confirm');
                    if (wrap) {
                        wrap.innerHTML = '<span class="ai-meta">' + (d.success ? '✔ ' : '⚠ ') + esc(texto) + '</span>';
                    }
                } else {
                    alert(texto);
                }
                carregarAcoes();
            })
            .catch(function () {
                if (inlineBtn) {
                    inlineBtn.disabled = false;
                    inlineBtn.innerHTML = '<i class="fas fa-check"></i> Confirmar';
                }
            });
    }

    function cancelarAcao(id) {
        var fd = new FormData();
        fd.append('action', 'cancelar');
        fd.append('id', id);
        fd.append('csrf_token', csrf);
        fetch('../api/ia_acoes.php', { method: 'POST', body: fd, credentials: 'include' })
            .then(function () { carregarAcoes(); });
    }

    function carregarAuditoria() {
        var el = document.getElementById('ciAuditoria');
        if (!el) return;
        fetch('../api/ia_assistente.php?action=auditoria&limit=80', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.data || !d.data.length) {
                    el.innerHTML = '<p class="ai-meta">Nenhuma consulta registrada ainda.</p>';
                    return;
                }
                var html = '<table class="ai-table"><thead><tr><th>Data</th><th>Agente</th><th>Intent</th><th>Pergunta</th><th>Conf.</th><th>ms</th></tr></thead><tbody>';
                d.data.forEach(function (row) {
                    html += '<tr><td>' + esc(row.created_at) + '</td><td>' + esc(row.agente) + '</td><td>' + esc(row.intent) + '</td><td>' + esc(row.pergunta) + '</td><td>' + esc(row.confianca_pct) + '</td><td>' + esc(row.tempo_ms) + '</td></tr>';
                });
                html += '</tbody></table>';
                el.innerHTML = html;
            });
    }

    function carregarMemoria() {
        fetch('../api/ia_assistente.php?action=memoria', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) return;
                var m = d.data || {};
                var f = document.getElementById('memFoco');
                var p = document.getElementById('memPeriodo');
                var n = document.getElementById('memNotas');
                if (f) f.value = m.foco || '';
                if (p) p.value = m.periodo_padrao || '';
                if (n) n.value = m.notas || '';
            });
    }

    document.querySelectorAll('.ci-section').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showPanel(btn.getAttribute('data-panel') || 'chat');
        });
    });

    document.querySelectorAll('.ci-mode').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.ci-mode').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            modoAtivo = btn.getAttribute('data-modo') || 'conversar';
            renderChips(modoAtivo);
        });
    });

    if (chipsEl) {
        chipsEl.addEventListener('click', function (e) {
            if (e.target.classList.contains('ai-chip')) perguntar(e.target.textContent);
        });
    }

    send.addEventListener('click', function () { perguntar(input.value); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') perguntar(input.value); });

    if (execBtn) execBtn.addEventListener('click', resumoExecutivo);

    if (sugerirBtn) {
        sugerirBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'sugerir_acoes');
            fd.append('csrf_token', csrf);
            fetch('../api/ia_assistente.php', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.success || !d.acoes || !d.acoes.length) {
                        alert((d && d.total) ? 'Nenhuma revisão urgente encontrada.' : 'Não foi possível sugerir ações.');
                        return;
                    }
                    var html = '<p>Encontrei <strong>' + d.total + '</strong> manutenção(ões) sugerida(s). Confirme abaixo:</p>';
                    d.acoes.forEach(function (a) {
                        html += '<div class="ai-acao-confirm" style="margin:8px 0">' +
                            '<span>' + esc(a.titulo) + '</span> ' +
                            '<button type="button" class="ci-btn-ok ci-btn-inline-confirm" data-acao-id="' + esc(a.id) + '">' + esc(a.label || 'Confirmar') + '</button>' +
                            '</div>';
                    });
                    var botMsg = addMsg('bot', html);
                    botMsg.querySelectorAll('.ci-btn-inline-confirm').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            btn.disabled = true;
                            confirmarAcao(btn.getAttribute('data-acao-id'), btn);
                        });
                    });
                    carregarAcoes();
                });
        });
    }

    var reindexBtn = document.getElementById('ciReindexEmb');
    if (reindexBtn) {
        reindexBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'reindex_embeddings');
            fd.append('csrf_token', csrf);
            reindexBtn.disabled = true;
            fetch('../api/ia_assistente.php', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    alert((d.data && d.data.msg) || d.error || 'Reindexação concluída.');
                    carregarDocumentos();
                })
                .finally(function () { reindexBtn.disabled = false; });
        });
    }

    var docForm = document.getElementById('ciDocForm');
    if (docForm) {
        docForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(docForm);
            fd.append('action', 'upload');
            fetch('../api/ia_documentos.php', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    alert(d.error || (d.success ? 'Documento enviado (' + (d.chars || 0) + ' caracteres indexados).' : 'Falhou.'));
                    docForm.reset();
                    carregarDocumentos();
                });
        });
    }

    var memForm = document.getElementById('ciMemoriaForm');
    if (memForm) {
        memForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(memForm);
            fd.append('action', 'memoria');
            fetch('../api/ia_assistente.php?action=memoria', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) { alert(d.success ? 'Memória salva.' : (d.error || 'Erro.')); });
        });
    }

    var playForm = document.getElementById('ciPlayForm');
    if (playForm) {
        playForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var pergunta = (document.getElementById('ciPlayPerg') || {}).value || '';
            var modelos = (document.getElementById('ciPlayModelos') || {}).value || 'gpt-4o-mini';
            var suite = document.getElementById('ciPlaySuite') && document.getElementById('ciPlaySuite').checked;
            var out = document.getElementById('ciPlayResult');
            if (out) out.innerHTML = '<p class="ai-meta"><i class="fas fa-spinner fa-spin"></i> Rodando…</p>';
            var url = '../api/ia_playground.php?modelos=' + encodeURIComponent(modelos);
            url += suite ? '&suite=1' : ('&pergunta=' + encodeURIComponent(pergunta));
            fetch(url, { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!out) return;
                    if (!d || !d.success) {
                        out.innerHTML = '<p class="ai-meta">' + esc((d && d.error) || 'Falhou.') + '</p>';
                        return;
                    }
                    var html = '<table class="ai-table"><thead><tr>';
                    if (suite) {
                        html += '<th>Pergunta</th><th>Esperado</th><th>Modelo</th><th>Intent</th><th>OK</th><th>ms</th></tr></thead><tbody>';
                        (d.data || []).forEach(function (row) {
                            html += '<tr><td>' + esc(row.pergunta) + '</td><td>' + esc(row.esperado) + '</td><td>' + esc(row.modelo) + '</td><td>' + esc(row.intent) + '</td><td>' + (row.acertou ? '✓' : '✗') + '</td><td>' + esc(row.tempo_ms) + '</td></tr>';
                        });
                    } else {
                        html += '<th>Modelo</th><th>Intent</th><th>ms</th><th>Tokens</th><th>USD</th></tr></thead><tbody>';
                        (d.data || []).forEach(function (row) {
                            html += '<tr><td>' + esc(row.modelo) + '</td><td>' + esc(row.intent) + '</td><td>' + esc(row.tempo_ms) + '</td><td>' + esc(row.tokens) + '</td><td>' + esc(row.custo_usd) + '</td></tr>';
                        });
                    }
                    out.innerHTML = html + '</tbody></table>';
                });
        });
    }

    var attachInp = document.getElementById('aiAttach');
    var imageInp = document.getElementById('aiImage');
    if (attachInp) {
        attachInp.addEventListener('change', function () {
            if (!attachInp.files || !attachInp.files[0]) return;
            var fd = new FormData();
            fd.append('action', 'upload');
            fd.append('csrf_token', csrf);
            fd.append('arquivo', attachInp.files[0]);
            addMsg('user', esc('📎 Enviando documento…'));
            fetch('../api/ia_documentos.php', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    attachInp.value = '';
                    if (d.success) {
                        perguntar('O que diz o documento que acabei de enviar?');
                    } else {
                        addMsg('bot', esc(d.error || 'Falha no upload.'));
                    }
                })
                .catch(function () { addMsg('bot', 'Erro no upload.'); });
        });
    }
    if (imageInp) {
        imageInp.addEventListener('change', function () {
            if (!imageInp.files || !imageInp.files[0]) return;
            var fd = new FormData();
            fd.append('imagem', imageInp.files[0]);
            fd.append('pergunta', input.value || 'O que aparece nesta imagem?');
            addMsg('user', esc('🖼️ Analisando imagem…'));
            send.disabled = true;
            fetch('../api/ia_assistente.php', { method: 'POST', body: fd, credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    imageInp.value = '';
                    send.disabled = false;
                    if (!d || !d.success) {
                        addMsg('bot', esc((d && d.error) || 'Falha na análise.'));
                        return;
                    }
                    if (d.intent && d.intent !== 'ajuda') lastIntent = d.intent;
                    var botMsg = addMsg('bot', renderBotPayload(d));
                    bindBotInteractions(botMsg, d);
                })
                .catch(function () {
                    imageInp.value = '';
                    send.disabled = false;
                    addMsg('bot', 'Erro ao enviar imagem.');
                });
        });
    }

    var devChk = document.getElementById('ciDevMode');
    if (devChk) {
        devChk.addEventListener('change', function () {
            window.SF_IA_DEV = devChk.checked;
        });
    }

    if (micBtn && (window.SpeechRecognition || window.webkitSpeechRecognition)) {
        recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
        recognition.lang = 'pt-BR';
        recognition.interimResults = false;
        micBtn.addEventListener('click', function () {
            if (micBtn.classList.contains('listening')) {
                recognition.stop();
                return;
            }
            micBtn.classList.add('listening');
            recognition.start();
        });
        recognition.onresult = function (ev) {
            var t = ev.results[0][0].transcript;
            input.value = t;
            perguntar(t);
        };
        recognition.onend = function () { micBtn.classList.remove('listening'); };
        recognition.onerror = function () { micBtn.classList.remove('listening'); };
    } else if (micBtn) {
        micBtn.style.display = 'none';
    }

    renderChips('conversar');
    carregarInsights();
    input.focus();
})();
