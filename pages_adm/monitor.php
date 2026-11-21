<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Monitor ao vivo';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Monitor em tempo real';
$cc_subtitle = 'Usuários online no frotas e administradores no painel ADM';
$cc_actions = '<span class="adm-cc-live"><span class="adm-cc-live__dot"></span> Atualização a cada 10s</span>';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-cc-kpi-grid adm-cc-kpi-grid--3" style="margin-bottom:1rem">
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Online agora</div><div class="adm-cc-kpi__value" id="mon-online">—</div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Logins recentes (2h)</div><div class="adm-cc-kpi__value" id="mon-recent">—</div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Última atualização</div><div class="adm-cc-kpi__value" id="mon-updated" style="font-size:0.95rem">—</div></div>
</div>

<div class="adm-panel" style="margin-bottom:1rem">
    <div class="adm-panel__head"><h2><i class="fas fa-circle text-success"></i> Conectados agora</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table" id="tbl-monitor-live">
            <thead><tr><th>Tipo</th><th>Empresa</th><th>Usuário</th><th>Página</th><th>Online</th><th>IP</th><th>Ping</th></tr></thead>
            <tbody><tr><td colspan="7" style="text-align:center">Carregando…</td></tr></tbody>
        </table>
    </div>
</div>

<div class="adm-panel">
    <div class="adm-panel__head"><h2><i class="fas fa-clock-rotate-left"></i> Atividade recente</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table" id="tbl-monitor-recent">
            <thead><tr><th>Empresa</th><th>Usuário</th><th>Evento</th><th>Há</th><th>IP</th></tr></thead>
            <tbody><tr><td colspan="5" style="text-align:center">Carregando…</td></tr></tbody>
        </table>
    </div>
</div>

<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    function fmtSec(s) {
        s = parseInt(s, 10) || 0;
        if (s < 60) return s + 's';
        if (s < 3600) return Math.floor(s / 60) + 'm';
        return Math.floor(s / 3600) + 'h';
    }
    function fmtTime(iso) {
        if (!iso) return '—';
        try { return new Date(iso.replace(' ', 'T')).toLocaleTimeString('pt-BR'); } catch (e) { return iso; }
    }
    function renderErr(msg) {
        document.querySelector('#tbl-monitor-live tbody').innerHTML =
            '<tr><td colspan="7" class="adm-cc-empty">' + AdmCC.escapeHtml(msg) + '</td></tr>';
    }
    function render(d) {
        AdmCC.setText('mon-online', AdmCC.formatNum(d.total_online || 0));
        AdmCC.setText('mon-recent', AdmCC.formatNum(d.total_recent || 0));
        AdmCC.setText('mon-updated', new Date().toLocaleTimeString('pt-BR'));

        var live = document.querySelector('#tbl-monitor-live tbody');
        if (!d.sessions || !d.sessions.length) {
            live.innerHTML = '<tr><td colspan="7" class="adm-cc-empty">Ninguém online. Abra o sistema de frotas em outra aba ou aguarde usuários.</td></tr>';
        } else {
            live.innerHTML = d.sessions.map(function (s) {
                var tipo = s.tipo === 'admin' ? '<span class="adm-cc-score adm-cc-score--mid">ADM</span>' : 'Frotas';
                return '<tr><td>' + tipo + '</td><td>' + AdmCC.escapeHtml(s.empresa_label) + '</td><td>' + AdmCC.escapeHtml(s.usuario_label || '—') + '</td><td><code>' + AdmCC.escapeHtml(s.pagina || '—') + '</code></td><td>' + fmtSec(s.tempo_online_seg) + '</td><td>' + AdmCC.escapeHtml(s.ip_address || '—') + '</td><td>' + fmtTime(s.ultimo_ping) + '</td></tr>';
            }).join('');
        }

        var rec = document.querySelector('#tbl-monitor-recent tbody');
        if (!d.recent_logins || !d.recent_logins.length) {
            rec.innerHTML = '<tr><td colspan="5" class="adm-cc-empty">Sem logins nas últimas 2 horas.</td></tr>';
        } else {
            rec.innerHTML = d.recent_logins.map(function (s) {
                return '<tr><td>' + AdmCC.escapeHtml(s.empresa_label) + '</td><td>' + AdmCC.escapeHtml(s.usuario_label) + '</td><td>' + AdmCC.escapeHtml(s.pagina || '') + '</td><td>' + fmtSec(s.tempo_online_seg) + ' atrás</td><td>' + AdmCC.escapeHtml(s.ip_address || '—') + '</td></tr>';
            }).join('');
        }
    }
    AdmCC.poll('monitor_sessions.php', render, 10000, function (err) {
        renderErr('Erro ao carregar: ' + (err.message || 'verifique se está logado no ADM'));
    });
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
