<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Copiloto — operações IA';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Copiloto IA';
$cc_subtitle = 'Perguntas não entendidas, intenções custom e saúde global';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-panel">
    <div class="adm-panel__body">
        <h3>Saúde global da IA</h3>
        <div id="adm-ia-saude"><p>Carregando…</p></div>
        <div id="adm-ia-custos" style="margin-top:12px"><p>Carregando custos…</p></div>
        <hr>
        <h3>Perguntas pendentes (mapear → intenção)</h3>
        <p class="adm-cc-empty">Gestores perguntaram algo que o copiloto não entendeu. Crie um padrão de palavra-chave para a intenção correta.</p>
        <div id="adm-ia-pendentes"><p>Carregando…</p></div>
        <hr>
        <h3>Playground (benchmark classificador)</h3>
        <form id="adm-ia-play" class="adm-form-inline">
            <label>Empresa ID <input type="number" name="empresa_id" id="admPlayEmp" value="1" style="width:80px"></label>
            <label>Modelos <input type="text" name="modelos" value="gpt-4o-mini" style="min-width:160px"></label>
            <button type="submit" class="adm-cc-btn adm-cc-btn--primary">Benchmark suite</button>
        </form>
        <p class="adm-cc-empty">Usa a chave IA da empresa em modo suporte ou a primeira empresa com chave configurada via API gestor.</p>
        <div id="adm-ia-bench"></div>
        <hr>
        <h3>Traces suporte L2 (modo dev)</h3>
        <div id="adm-ia-traces"><p>Carregando…</p></div>
    </div>
</div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').content;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    // GET direto no endpoint desta pasta (api/). Não usar AdmCC.fetch aqui: ele prefixa
    // "api/v1/" (padrão das telas phase2) e geraria api/v1/api/... → 404.
    function apiGet(url) {
        return fetch(url, { credentials: 'include' }).then(function (x) { return x.json(); });
    }

    apiGet('api/ia_copilot_ops.php?action=saude_global').then(function (r) {
        var d = r.data || {};
        var el = document.getElementById('adm-ia-saude');
        el.innerHTML = '<ul>' +
            '<li>Consultas hoje: ' + (d.consultas_hoje || 0) + '</li>' +
            '<li>Tempo médio: ' + (d.tempo_medio_ms || 0) + ' ms</li>' +
            '<li>Cache hit: ' + (d.cache_hit_pct || 0) + '%</li>' +
            '<li>LLM: ' + (d.llm_pct || 0) + '%</li>' +
            '<li>Falhas hoje: ' + (d.falhas_hoje || 0) + '</li>' +
            '</ul>';
        var cg = d.custos_global || {};
        var cel = document.getElementById('adm-ia-custos');
        if (cel) {
            cel.innerHTML = '<h4>Custos IA (30 dias)</h4><ul>' +
                '<li>Consultas: ' + (cg.consultas || 0) + '</li>' +
                '<li>Tokens: ' + (cg.tokens || 0) + '</li>' +
                '<li>Custo estimado: ~$' + (cg.custo_usd || 0) + ' USD</li>' +
                '<li>Empresas com uso: ' + (cg.empresas || 0) + '</li></ul>';
        }
    });

    function carregarPendentes() {
        apiGet('api/ia_copilot_ops.php?action=listar_pendentes').then(function (r) {
            var rows = r.data || [];
            var el = document.getElementById('adm-ia-pendentes');
            if (!rows.length) {
                el.innerHTML = '<p class="adm-cc-empty">Nenhuma pergunta pendente.</p>';
                return;
            }
            var html = '<table class="adm-table"><thead><tr><th>Empresa</th><th>Pergunta</th><th>Ocorr.</th><th>Intent</th><th>Ações</th></tr></thead><tbody>';
            rows.forEach(function (row) {
                html += '<tr><td>' + esc(row.empresa_id) + '</td><td>' + esc(row.pergunta) + '</td><td>' + esc(row.ocorrencias) + '</td>';
                html += '<td><input type="text" class="adm-intent" data-id="' + row.id + '" placeholder="faturamento" style="width:120px"></td>';
                html += '<td><button type="button" class="adm-cc-btn adm-map" data-id="' + row.id + '" data-pergunta="' + esc(row.pergunta) + '">Mapear</button> ';
                html += '<button type="button" class="adm-cc-btn adm-ignore" data-id="' + row.id + '">Ignorar</button></td></tr>';
            });
            html += '</tbody></table>';
            el.innerHTML = html;
            el.querySelectorAll('.adm-map').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-id');
                    var intentInp = el.querySelector('.adm-intent[data-id="' + id + '"]');
                    var intent = intentInp ? intentInp.value : '';
                    var fd = new FormData();
                    fd.append('action', 'mapear');
                    fd.append('csrf_token', csrf || '');
                    fd.append('id', id);
                    fd.append('intent', intent);
                    fd.append('padrao', (btn.getAttribute('data-pergunta') || '').toLowerCase().slice(0, 80));
                    fetch('api/ia_copilot_ops.php', { method: 'POST', body: fd, credentials: 'include' })
                        .then(function (x) { return x.json(); })
                        .then(function () { carregarPendentes(); });
                });
            });
            el.querySelectorAll('.adm-ignore').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var fd = new FormData();
                    fd.append('action', 'ignorar');
                    fd.append('csrf_token', csrf || '');
                    fd.append('id', btn.getAttribute('data-id'));
                    fetch('api/ia_copilot_ops.php', { method: 'POST', body: fd, credentials: 'include' })
                        .then(function () { carregarPendentes(); });
                });
            });
        });
    }
    carregarPendentes();

    document.getElementById('adm-ia-play').addEventListener('submit', function (e) {
        e.preventDefault();
        var emp = document.getElementById('admPlayEmp').value || '1';
        var modelos = e.target.querySelector('[name=modelos]').value || 'gpt-4o-mini';
        var el = document.getElementById('adm-ia-bench');
        el.innerHTML = '<p>Rodando suite…</p>';
        apiGet('api/ia_copilot_ops.php?action=benchmark_admin&empresa_id=' + encodeURIComponent(emp) + '&modelos=' + encodeURIComponent(modelos)).then(function (r) {
            var rows = r.data || [];
            if (!rows.length) { el.innerHTML = '<p class="adm-cc-empty">Sem resultados (empresa sem chave IA?).</p>'; return; }
            var html = '<table class="adm-table"><thead><tr><th>Pergunta</th><th>Esperado</th><th>Modelo</th><th>Intent</th><th>OK</th></tr></thead><tbody>';
            rows.forEach(function (row) {
                html += '<tr><td>' + esc(row.pergunta) + '</td><td>' + esc(row.esperado) + '</td><td>' + esc(row.modelo) + '</td><td>' + esc(row.intent) + '</td><td>' + (row.acertou ? '✓' : '✗') + '</td></tr>';
            });
            el.innerHTML = html + '</tbody></table>';
        });
    });

    apiGet('api/ia_copilot_ops.php?action=listar_traces').then(function (r) {
        var rows = r.data || [];
        var el = document.getElementById('adm-ia-traces');
        if (!rows.length) { el.innerHTML = '<p class="adm-cc-empty">Nenhum trace (ative Modo dev no Centro de Inteligência).</p>'; return; }
        var html = '<table class="adm-table"><thead><tr><th>Data</th><th>Empresa</th><th>Intent</th><th>Pergunta</th></tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr><td>' + esc(row.created_at) + '</td><td>' + esc(row.empresa_id) + '</td><td>' + esc(row.intent) + '</td><td>' + esc(row.pergunta) + '</td></tr>';
        });
        el.innerHTML = html + '</tbody></table>';
    });
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
