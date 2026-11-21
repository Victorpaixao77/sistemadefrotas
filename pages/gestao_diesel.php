<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Gestão de Diesel · Ranking de Postos';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$per_ini = date('Y-m-d', strtotime('-12 months'));
$per_fim = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/theme.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/responsive.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/fornc-modern-page.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/sf-premium.css?v=<?php echo $v; ?>">
    <?php sf_page_help_css('..'); ?>
    <style>
        .gd-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin: 6px 0 18px; }
        .gd-kpi { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; }
        .gd-kpi__label { font-size: .78rem; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }
        .gd-kpi__value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin-top: 4px; }
        .gd-kpi__hint { font-size: .72rem; color: var(--text-muted, var(--text-secondary)); margin-top: 2px; }
        .gd-highlights { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .gd-hl { border-radius: 12px; padding: 14px 16px; border: 1px solid var(--border-color); background: var(--bg-secondary); }
        .gd-hl b { display: block; font-size: 1.05rem; color: var(--text-primary); margin-top: 3px; }
        .gd-hl small { color: var(--text-secondary); font-size: .76rem; }
        .gd-hl--green { border-left: 4px solid #16a34a; } .gd-hl--blue { border-left: 4px solid #3b82f6; } .gd-hl--amber { border-left: 4px solid #f59e0b; }
        .gd-chart-wrap { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; margin-bottom: 18px; }
        .gd-chart-wrap h3 { font-size: .95rem; margin: 0 0 10px; color: var(--text-primary); }
        .gd-chart-box { position: relative; height: 260px; width: 100%; }
        .gd-bar { height: 8px; border-radius: 6px; background: var(--bg-primary); overflow: hidden; }
        .gd-bar > i { display: block; height: 100%; background: linear-gradient(90deg, #3b82f6, #06b6d4); }
        .gd-badge { font-size: .68rem; padding: 2px 7px; border-radius: 999px; font-weight: 700; }
        .gd-badge--barato { background: rgba(22,163,74,.15); color: #16a34a; } .gd-badge--usado { background: rgba(59,130,246,.15); color: #3b82f6; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="gdBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="gdBusca" placeholder="Nome do posto...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg"><label for="gdTipo">Combustível</label>
                            <select id="gdTipo"><option value="">Todos</option></select>
                        </div>
                    </div>
                    <input type="hidden" data-sf-periodo-from id="gdInicio" value="<?php echo htmlspecialchars($per_ini); ?>">
                    <input type="hidden" data-sf-periodo-to id="gdFim" value="<?php echo htmlspecialchars($per_fim); ?>">
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo data-sf-periodo-onapply="gdPeriodo" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda desta tela" aria-label="Ajuda desta tela"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="gd-kpis" id="gdKpis">
                    <div class="gd-kpi"><div class="gd-kpi__label"><i class="fas fa-sack-dollar"></i> Gasto total</div><div class="gd-kpi__value" id="kGasto">—</div><div class="gd-kpi__hint">No período</div></div>
                    <div class="gd-kpi"><div class="gd-kpi__label"><i class="fas fa-droplet"></i> Litros</div><div class="gd-kpi__value" id="kLitros">—</div><div class="gd-kpi__hint">Abastecidos</div></div>
                    <div class="gd-kpi"><div class="gd-kpi__label"><i class="fas fa-tag"></i> Preço médio</div><div class="gd-kpi__value" id="kPreco">—</div><div class="gd-kpi__hint">Ponderado (R$/L)</div></div>
                    <div class="gd-kpi"><div class="gd-kpi__label"><i class="fas fa-gas-pump"></i> Postos</div><div class="gd-kpi__value" id="kPostos">—</div><div class="gd-kpi__hint">Distintos</div></div>
                    <div class="gd-kpi"><div class="gd-kpi__label"><i class="fas fa-receipt"></i> Abastecimentos</div><div class="gd-kpi__value" id="kAbast">—</div><div class="gd-kpi__hint">No período</div></div>
                </div>

                <div class="gd-highlights" id="gdHighlights"></div>

                <div class="gd-chart-wrap">
                    <h3><i class="fas fa-chart-line"></i> Evolução do preço médio (R$/L) por mês</h3>
                    <div class="gd-chart-box"><canvas id="gdChart"></canvas></div>
                </div>

                <div class="fornc-table-wrap">
                    <table class="fornc-table" id="gdTabela">
                        <thead><tr>
                            <th>Posto</th><th>Abast.</th><th>Litros</th>
                            <th>Preço médio (R$/L)</th><th>Total gasto</th><th>% do gasto</th>
                        </tr></thead>
                        <tbody id="gdTbody"><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-secondary)">Carregando…</td></tr></tbody>
                    </table>
                </div>

                <div class="fornc-empty-state" id="gdVazio" style="display:none">
                    <h3>Nenhum abastecimento no período</h3>
                    <p>Ajuste o período ou registre abastecimentos com o posto informado.</p>
                    <a href="abastecimentos.php" class="fornc-btn fornc-btn--primary"><i class="fas fa-gas-pump"></i> Ir para Abastecimentos</a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var brl = function (n) { return 'R$ ' + (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var num = function (n, d) { return (Number(n) || 0).toLocaleString('pt-BR', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); };
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
        var chart = null, dados = null;

        function carregar() {
            var qs = '?data_inicio=' + document.getElementById('gdInicio').value +
                     '&data_fim=' + document.getElementById('gdFim').value +
                     '&tipo_combustivel=' + encodeURIComponent(document.getElementById('gdTipo').value);
            fetch('../api/combustivel_postos.php' + qs, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (!d.success) throw new Error(d.message || 'erro'); dados = d; render(d); })
                .catch(function (e) { document.getElementById('gdTbody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#dc3545">Erro ao carregar.</td></tr>'; });
        }

        function preencherTipos(tipos) {
            var sel = document.getElementById('gdTipo'), atual = sel.value;
            sel.innerHTML = '<option value="">Todos</option>';
            (tipos || []).forEach(function (t) { var o = document.createElement('option'); o.value = t; o.textContent = t; sel.appendChild(o); });
            sel.value = atual;
        }

        function render(d) {
            var r = d.resumo || {};
            document.getElementById('kGasto').textContent = brl(r.gasto_total);
            document.getElementById('kLitros').textContent = num(r.litros_total) + ' L';
            document.getElementById('kPreco').textContent = brl(r.preco_medio);
            document.getElementById('kPostos').textContent = r.qtd_postos || 0;
            document.getElementById('kAbast').textContent = r.total_abast || 0;
            if (!d.tipos || !document.getElementById('gdTipo').dataset.done) { preencherTipos(d.tipos); document.getElementById('gdTipo').dataset.done = '1'; }

            // destaques
            var h = d.destaques || {}, html = '';
            if (h.mais_barato) html += '<div class="gd-hl gd-hl--green"><small><i class="fas fa-medal"></i> Melhor preço médio</small><b>' + esc(h.mais_barato.posto) + '</b><small>' + brl(h.mais_barato.preco_medio) + '/L · ' + num(h.mais_barato.litros) + ' L</small></div>';
            if (h.mais_usado) html += '<div class="gd-hl gd-hl--blue"><small><i class="fas fa-repeat"></i> Mais utilizado</small><b>' + esc(h.mais_usado.posto) + '</b><small>' + (h.mais_usado.n_abast) + ' abastecimentos</small></div>';
            if (h.maior_gasto) html += '<div class="gd-hl gd-hl--amber"><small><i class="fas fa-fire"></i> Maior gasto</small><b>' + esc(h.maior_gasto.posto) + '</b><small>' + brl(h.maior_gasto.total) + '</small></div>';
            document.getElementById('gdHighlights').innerHTML = html;

            desenharTabela(d.ranking || []);
            desenharGrafico(d.evolucao || []);
            var vazio = (d.ranking || []).length === 0;
            document.getElementById('gdVazio').style.display = vazio ? '' : 'none';
            document.querySelector('.fornc-table-wrap').style.display = vazio ? 'none' : '';
        }

        function desenharTabela(rows) {
            var termo = (document.getElementById('gdBusca').value || '').toLowerCase();
            var filtradas = rows.filter(function (x) { return !termo || (x.posto || '').toLowerCase().indexOf(termo) >= 0; });
            var barato = (dados.destaques || {}).mais_barato, usado = (dados.destaques || {}).mais_usado;
            if (!filtradas.length) { document.getElementById('gdTbody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-secondary)">Nenhum posto encontrado.</td></tr>'; return; }
            document.getElementById('gdTbody').innerHTML = filtradas.map(function (x) {
                var tags = '';
                if (barato && x.posto === barato.posto) tags += ' <span class="gd-badge gd-badge--barato">mais barato</span>';
                if (usado && x.posto === usado.posto) tags += ' <span class="gd-badge gd-badge--usado">mais usado</span>';
                return '<tr>' +
                    '<td><strong>' + esc(x.posto) + '</strong>' + tags + '</td>' +
                    '<td>' + x.n_abast + '</td>' +
                    '<td>' + num(x.litros) + ' L</td>' +
                    '<td>' + brl(x.preco_medio) + '</td>' +
                    '<td>' + brl(x.total) + '</td>' +
                    '<td><div class="gd-bar"><i style="width:' + Math.min(100, x.pct_gasto) + '%"></i></div><small>' + num(x.pct_gasto, 1) + '%</small></td>' +
                    '</tr>';
            }).join('');
        }

        function desenharGrafico(evol) {
            var ctx = document.getElementById('gdChart').getContext('2d');
            if (chart) chart.destroy();
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: evol.map(function (e) { return e.ym; }),
                    datasets: [{ label: 'Preço médio (R$/L)', data: evol.map(function (e) { return e.preco; }),
                        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.12)', fill: true, tension: .3, pointRadius: 3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                    scales: { y: { ticks: { callback: function (v) { return 'R$ ' + v; } } } } }
            });
        }

        window.gdPeriodo = carregar;               // chamado pelo botão Período (sf-periodo.js)
        document.getElementById('gdTipo').addEventListener('change', carregar);
        document.getElementById('gdBusca').addEventListener('input', function () { if (dados) desenharTabela(dados.ranking || []); });
        carregar();
    })();
    </script>
    <script src="../js/sf-periodo.js?v=<?php echo $v; ?>"></script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'gestao_diesel'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
