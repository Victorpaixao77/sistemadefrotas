/* Configuração do provedor de IA de visão (leitura de fichas de abastecimento). */
(function () {
    'use strict';
    var API = '../api/vision_ai_config.php';
    var form = document.getElementById('visionAiForm');
    if (!form) return;
    var providers = {};

    function el(id) { return document.getElementById(id); }

    function msg(t, ok) {
        var m = el('visionAiMsg');
        if (!m) return;
        m.textContent = t;
        m.style.color = ok ? '#0a7d2c' : '#c0392b';
    }

    function currentPurpose() {
        var pe = el('vai_purpose');
        var v = pe ? pe.value : 'vision';
        if (v === 'text' || v === 'classify' || v === 'narrate') return v;
        return 'vision';
    }

    function updateProviderUI() {
        var p = el('vai_provider').value;
        var def = providers[p] || {};
        el('vai_base_wrap').style.display = (p === 'custom') ? '' : 'none';
        var hint = el('vai_model_hint');
        if (hint) hint.textContent = def.model ? ('— padrão: ' + def.model) : '';
        // Aviso quando um provedor sem visão é escolhido para a IA de Visão.
        var warn = el('vai_provider_warn');
        if (warn) {
            var noVision = def && def.vision === false;
            warn.style.display = (noVision && currentPurpose() === 'vision') ? '' : 'none';
        }
    }

    function load() {
        var purpose = currentPurpose();
        fetch(API + '?action=get&purpose=' + purpose, { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                providers = res.providers || {};
                var d = res.data || {};
                el('vai_provider').value = d.provider || (purpose === 'text' ? 'deepseek' : (purpose === 'classify' ? 'openai' : (purpose === 'narrate' ? 'gemini' : 'gemini')));
                el('vai_model').value = d.model || '';
                el('vai_base_url').value = d.base_url || '';
                el('vai_api_key').value = '';
                el('vai_api_key').placeholder = d.has_key
                    ? ('Chave salva (' + (d.key_masked || '…') + ') — em branco mantém')
                    : 'Cole a chave (em branco mantém a salva)';
                if (el('vai_clear_key')) el('vai_clear_key').checked = false;
                updateProviderUI();
                var s = el('visionAiStatusLine');
                var rotulo = purpose === 'text' ? 'Resumos/textos' : (purpose === 'classify' ? 'Classificação Copiloto' : (purpose === 'narrate' ? 'Narrativa Copiloto' : 'IA de Visão'));
                if (s) s.textContent = d.has_key ? (rotulo + ' configurado: ' + (d.provider || '')) : (rotulo + ': nenhuma chave salva ainda.');
            })
            .catch(function () {});
    }

    el('vai_provider').addEventListener('change', updateProviderUI);
    if (el('vai_purpose')) el('vai_purpose').addEventListener('change', load);

    function setBtnLoading(btn, loading, loadingText) {
        if (!btn) return;
        if (loading) {
            btn.dataset.html = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (loadingText || 'Aguarde…');
        } else {
            btn.disabled = false;
            if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button[type="submit"]');
        setBtnLoading(btn, true, 'Salvando…');
        var fd = new FormData(form);
        fd.append('action', 'save');
        if (!el('vai_clear_key').checked) fd.delete('clear_key');
        fetch(API, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) { msg('Configuração salva.', true); load(); }
                else { msg(res.message || res.error || 'Erro ao salvar.', false); }
            })
            .catch(function () { msg('Erro de comunicação.', false); })
            .then(function () { setBtnLoading(btn, false); });
    });

    el('visionAiTestBtn').addEventListener('click', function () {
        var btn = el('visionAiTestBtn');
        setBtnLoading(btn, true, 'Testando…');
        var fd = new FormData(form);
        fd.append('action', 'test');
        msg('Testando conexão…', true);
        fetch(API, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                msg(res.success ? (res.message || 'Conexão OK.') : (res.message || res.error || 'Falha no teste.'), !!res.success);
            })
            .catch(function () { msg('Erro de comunicação.', false); })
            .then(function () { setBtnLoading(btn, false); });
    });

    load();
})();

/* Painel de uso/custos IA (aba IA) */
(function () {
    'use strict';
    var box = document.getElementById('iaUsoStatusLine');
    var grid = document.getElementById('iaUsoGrid');
    if (!box || !grid) return;
    function esc(s) { return String(s == null ? '' : s); }
    function kpi(label, value) {
        return '<div class="dashboard-card" style="padding:12px;margin:0"><div class="form-text" style="margin:0">' + esc(label) + '</div><strong style="font-size:1.25rem">' + esc(value) + '</strong></div>';
    }
    fetch('../api/ia_saude.php', { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success || !res.data) {
                box.textContent = 'Não foi possível carregar o uso da IA.';
                return;
            }
            var m = res.data;
            var uso = m.uso_mes_empresa || {};
            box.textContent = 'Atualizado' + (uso.ultimo_uso ? ' em ' + uso.ultimo_uso : '') + '.';
            grid.innerHTML =
                kpi('Consultas no mês', uso.consultas || 0) +
                kpi('Tokens estimados', uso.tokens || 0) +
                kpi('Custo estimado', '~$' + (uso.custo_usd != null ? uso.custo_usd : '0')) +
                kpi('Consultas hoje', m.consultas_hoje || 0) +
                kpi('Cache hit hoje', (m.cache_hit_pct || 0) + '%') +
                kpi('Falhas hoje', m.falhas_hoje || 0);
        })
        .catch(function () { box.textContent = 'Erro ao carregar uso da IA.'; });
})();
