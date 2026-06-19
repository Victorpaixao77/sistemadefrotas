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

    function updateProviderUI() {
        var p = el('vai_provider').value;
        var def = providers[p] || {};
        el('vai_base_wrap').style.display = (p === 'custom') ? '' : 'none';
        var hint = el('vai_model_hint');
        if (hint) hint.textContent = def.model ? ('— padrão: ' + def.model) : '';
    }

    function load() {
        fetch(API + '?action=get', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                providers = res.providers || {};
                var d = res.data || {};
                el('vai_provider').value = d.provider || 'gemini';
                el('vai_model').value = d.model || '';
                el('vai_base_url').value = d.base_url || '';
                if (d.has_key) {
                    el('vai_api_key').placeholder = 'Chave salva (' + (d.key_masked || '…') + ') — em branco mantém';
                }
                updateProviderUI();
                var s = el('visionAiStatusLine');
                if (s) s.textContent = d.has_key ? ('Configurado: ' + (d.provider || '')) : 'Nenhuma chave salva ainda.';
            })
            .catch(function () {});
    }

    el('vai_provider').addEventListener('change', updateProviderUI);

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
