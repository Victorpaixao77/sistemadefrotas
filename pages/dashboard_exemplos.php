<?php
/**
 * Galeria de padrões visuais para dashboards (referência para novas telas).
 * URL: /sistema-frotas/pages/dashboard_exemplos.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Exemplos de dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <style>
        body.dashboard-exemplos-modern .dashboard-content.fornc-page {
            padding-top: 8px;
            max-width: 1280px;
            margin: 0 auto;
        }
        .dex-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 1.25rem 0 0.5rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid var(--border-color);
        }
        .dex-section-hint {
            font-size: 0.78rem;
            color: var(--text-secondary);
            margin: 0 0 0.75rem;
            line-height: 1.45;
        }
        .dex-code {
            font-size: 0.72rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.5rem 0.65rem;
            margin-top: 0.5rem;
            overflow-x: auto;
            color: var(--text-secondary);
            font-family: ui-monospace, monospace;
        }
        /* Faixa KPI com mais colunas (home / densidade) */
        .fornc-kpi-strip--dense {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important;
        }
        .dex-demo-table.fornc-table td { font-size: 0.8125rem; }
    </style>
</head>
<body class="dashboard-exemplos-modern">
    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="dashboard-content fornc-page">

                <div class="dashboard-header">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                </div>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex: 1 1 280px;">
                        <label>Referência rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-palette" aria-hidden="true"></i>
                            <input type="text" readonly value="Use estes blocos como base nas telas em pages/ e no index.php"
                                   style="cursor: default; opacity: 0.95;">
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <a href="../index.php" class="fornc-btn fornc-btn--primary"><i class="fas fa-home"></i> Dashboard principal</a>
                    </div>
                </div>

                <h2 class="dex-section-title">1) Faixa de KPIs (4 colunas padrão)</h2>
                <p class="dex-section-hint">Classe: <code>fornc-kpi-strip</code> + <code>fornc-kpi-cell</code> — igual às listagens modernas.</p>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><span class="lbl">Exemplo A</span><span class="val">128</span></div>
                    <div class="fornc-kpi-cell is-ok"><span class="lbl">Status OK</span><span class="val">99%</span></div>
                    <div class="fornc-kpi-cell is-warn"><span class="lbl">Atenção</span><span class="val">3</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Moeda</span><span class="val">R$ 12.450</span></div>
                </div>
                <div class="dex-code">.fornc-kpi-strip &gt; .fornc-kpi-cell &gt; .lbl / .val</div>

                <h2 class="dex-section-title">2) Faixa de KPIs densa (auto-fit)</h2>
                <p class="dex-section-hint">Útil no painel inicial com muitos indicadores. Classe extra: <code>fornc-kpi-strip--dense</code>.</p>
                <div class="fornc-kpi-strip fornc-kpi-strip--dense">
                    <?php foreach (['Vei.', 'Mot.', 'Rotas', 'Abast.', 'Desp.', 'Manut.', 'Pneus', 'Lucro'] as $i => $lbl): ?>
                    <div class="fornc-kpi-cell"><span class="lbl"><?php echo htmlspecialchars($lbl); ?></span><span class="val"><?php echo 10 + $i * 7; ?></span></div>
                    <?php endforeach; ?>
                </div>

                <h2 class="dex-section-title">3) Barra de ferramentas</h2>
                <p class="dex-section-hint">Classe: <code>fornc-toolbar</code> — busca, filtros e botões na mesma linha.</p>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="dexSearch">Busca</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="dexSearch" placeholder="Termo..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="dexSel">Filtro</label>
                            <select id="dexSel">
                                <option>Todos</option>
                                <option>Ativos</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--accent"><i class="fas fa-search"></i> Pesquisar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost"><i class="fas fa-sliders-h"></i> Opções</button>
                        <button type="button" class="fornc-btn fornc-btn--muted"><i class="fas fa-file-export"></i> Exportar</button>
                    </div>
                </div>

                <h2 class="dex-section-title">4) Tabela densa</h2>
                <p class="dex-section-hint"><code>fornc-table-wrap</code> + <code>fornc-table</code> — rolagem horizontal em telas estreitas.</p>
                <div class="fornc-table-wrap">
                    <table class="fornc-table dex-demo-table">
                        <thead>
                            <tr>
                                <th>Coluna A</th>
                                <th>Coluna B</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Item 1</td><td>Detalhe</td><td>R$ 100,00</td></tr>
                            <tr><td>Item 2</td><td>Detalhe</td><td>R$ 250,00</td></tr>
                        </tbody>
                    </table>
                </div>

                <h2 class="dex-section-title">5) Alertas (tema claro/escuro)</h2>
                <p class="dex-section-hint">Evite fundo 100% colorido; use borda esquerda com variável CSS.</p>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
                    <div class="dex-alert" style="--a:#16a34a;"><i class="fas fa-check-circle"></i> Sucesso</div>
                    <div class="dex-alert" style="--a:#ca8a04;"><i class="fas fa-exclamation-triangle"></i> Aviso</div>
                    <div class="dex-alert" style="--a:#dc2626;"><i class="fas fa-times-circle"></i> Crítico</div>
                </div>
                <style>
                    .dex-alert {
                        flex: 1 1 200px;
                        min-width: 160px;
                        padding: 0.65rem 0.85rem;
                        border-radius: 8px;
                        border: 1px solid var(--border-color);
                        border-left: 4px solid var(--a);
                        background: var(--card-bg, var(--bg-secondary));
                        color: var(--text-primary);
                        font-size: 0.8125rem;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                    .dex-alert i { color: var(--a); }
                </style>

                <h2 class="dex-section-title">6) Cards em grade (resumo)</h2>
                <p class="dex-section-hint">Combine com <code>dashboard-card</code> do tema ou estilize como nos KPIs acima.</p>
                <div class="dashboard-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:0.75rem;">
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Card A</h3></div>
                        <div class="card-body"><p style="margin:0;font-size:0.85rem;color:var(--text-secondary);">Texto ou gráfico.</p></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Card B</h3></div>
                        <div class="card-body"><p style="margin:0;font-size:0.85rem;color:var(--text-secondary);">Mesma linguagem visual.</p></div>
                    </div>
                </div>

                <h2 class="dex-section-title">7) Paginação</h2>
                <div class="fornc-pagination-bar">
                    <div class="pagination fornc-modern-pagination">
                        <span class="pagination-btn disabled" style="pointer-events:none;opacity:0.45;"><i class="fas fa-chevron-left"></i></span>
                        <span class="pagination-info">Mostrando 1–10 de 42</span>
                        <a href="#" class="pagination-btn" onclick="return false;"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>

                <p class="dex-section-hint" style="margin-top:1.5rem;">
                    Estilos centralizados em <code>css/fornc-modern-page.css</code>. Algumas telas antigas ainda aceitam <code>?classic=1</code> quando existir essa opção.
                </p>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
</body>
</html>
