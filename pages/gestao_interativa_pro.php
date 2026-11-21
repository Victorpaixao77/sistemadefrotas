<?php
// Gestão Interativa — visualização interativa de pneus por veículo.
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/sf_api_base.php';

configure_session();
session_start();

require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');

$page_title = 'Gestão Interativa';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <?php sf_render_api_scripts(); ?>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/bi-premium.css?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../css/gestao-interativa-pro.css?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php sf_page_help_css('..'); ?>
</head>
<body class="gestao-interativa-pro-page">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="gip-wrap">

                    <div class="gip-head">
                        <div>
                            <h1><span class="gip-eyebrow-dot"></span> Gestão Interativa</h1>
                            <p class="gip-sub">Visualize o veículo, clique nos pneus e gerencie sulco, calibragem, posição e status.</p>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="gip-filters">
                        <div class="gip-field gip-grow">
                            <label for="gipVeiculo">Veículo</label>
                            <select id="gipVeiculo"><option value="">Selecione um veículo…</option></select>
                        </div>
                        <div class="gip-field">
                            <label for="gipStatus">Status do pneu</label>
                            <select id="gipStatus">
                                <option value="">Todos</option>
                                <option value="bom">Bom</option>
                                <option value="atencao">Atenção</option>
                                <option value="critico">Crítico</option>
                                <option value="recapagem">Recapagem</option>
                                <option value="novo">Novo</option>
                            </select>
                        </div>
                        <div class="gip-field">
                            <label for="gipVida">Vida</label>
                            <select id="gipVida">
                                <option value="">Todas</option>
                                <option value="1">1ª vida</option>
                                <option value="2">2ª vida</option>
                                <option value="3">3ª vida</option>
                                <option value="4">4ª vida+</option>
                            </select>
                        </div>
                        <div class="gip-field">
                            <label for="gipExtra">Mais filtros</label>
                            <select id="gipExtra">
                                <option value="">—</option>
                                <option value="proximo_descarte">Próximo do descarte</option>
                                <option value="sem_calibragem">Sem calibragem recente</option>
                                <option value="sem_medicao">Sem medição recente</option>
                            </select>
                        </div>
                        <div class="gip-field" style="justify-content:flex-end">
                            <label>&nbsp;</label>
                            <label style="display:flex;align-items:center;gap:7px;text-transform:none;font-weight:600;color:var(--text-secondary);font-size:.82rem;cursor:pointer">
                                <input type="checkbox" id="gipSoAlerta" style="width:auto"> Só em alerta
                            </label>
                        </div>
                        <div class="gip-spacer"></div>
                        <button class="gip-btn gip-btn--sm" id="gipResumoIA" title="Resumo e recomendações por IA"><i class="fas fa-wand-magic-sparkles"></i> Resumo IA</button>
                        <button class="gip-btn gip-btn--sm" id="gipFrota" title="Ranking e comparação de veículos"><i class="fas fa-ranking-star"></i> Frota</button>
                        <button class="gip-btn gip-btn--sm" id="gipKm" title="Atualizar o odômetro do veículo"><i class="fas fa-gauge-high"></i> KM</button>
                        <button class="gip-btn gip-btn--sm" id="gipExport" title="Exportar / imprimir os pneus do veículo"><i class="fas fa-file-export"></i> Exportar</button>
                        <button class="gip-btn gip-btn--sm" id="helpBtn" title="Ajuda desta tela" aria-label="Ajuda desta tela"><i class="fas fa-question-circle"></i> Ajuda</button>
                        <button class="gip-btn" id="gipRefresh"><i class="fas fa-arrows-rotate"></i> Atualizar</button>
                    </div>

                    <!-- Indicadores -->
                    <div class="gip-kpi-scope" id="gipKpiScope"><i class="fas fa-layer-group"></i> Indicadores da <b>frota toda</b></div>
                    <div class="dashboard-grid biq-kpis gip-kpis" id="gipKpis" role="region" aria-label="Indicadores de pneus">
                        <div class="dashboard-card biq-card biq-kpi" data-k="total" data-kpi="total">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Total de pneus</span><span class="biq-kpi__icon ic-blue"><i class="fas fa-circle-nodes" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiTotal">—</div><div class="biq-kpi__hint" id="kpiTotalHint">Na frota</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="uso" data-kpi="uso">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Em uso</span><span class="biq-kpi__icon ic-green"><i class="fas fa-truck" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiUso">—</div><div class="biq-kpi__hint">Pneus instalados</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="alerta" data-kpi="alerta">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Em alerta</span><span class="biq-kpi__icon ic-amber"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiAlerta">—</div><div class="biq-kpi__hint">Em atenção</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="critico" data-kpi="critico">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Críticos</span><span class="biq-kpi__icon ic-red"><i class="fas fa-circle-exclamation" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiCritico">—</div><div class="biq-kpi__hint">Precisam de troca</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="vida" data-kpi="vida">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Vida média</span><span class="biq-kpi__icon ic-violet"><i class="fas fa-recycle" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiVida">—</div><div class="biq-kpi__hint">Recapagens + 1</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="custo" data-kpi="custo">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Custo total</span><span class="biq-kpi__icon ic-cyan"><i class="fas fa-sack-dollar" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiCusto">—</div><div class="biq-kpi__hint">Investido em pneus</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="custokm" data-kpi="custokm">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Custo / km</span><span class="biq-kpi__icon ic-cyan"><i class="fas fa-route" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiCustoKm">—</div><div class="biq-kpi__hint">Por km rodado</div>
                        </div>
                        <div class="dashboard-card biq-card biq-kpi" data-k="semmed" data-kpi="semmed">
                            <div class="biq-kpi__top"><span class="biq-kpi__label">Sem medição</span><span class="biq-kpi__icon ic-amber"><i class="fas fa-clock" aria-hidden="true"></i></span></div>
                            <div class="biq-kpi__value" id="kpiSemMed">—</div><div class="biq-kpi__hint">Sem sulco recente</div>
                        </div>
                    </div>

                    <!-- Estágio + painel -->
                    <div class="gip-main">
                        <div class="gip-card gip-stage-wrap">
                            <div class="gip-card-head gip-stage-head">
                                <div class="gip-stage-headline">
                                    <h3><i class="fas fa-truck-moving"></i> <span id="gipStageTitle">Estágio do veículo</span></h3>
                                    <span id="gipStageMeta" style="font-size:.78rem;color:var(--text-muted,var(--text-secondary))"></span>
                                </div>
                                <div class="gip-stage-tools">
                                    <div class="gip-view-toggle" id="gipViewToggle" role="tablist" title="Ver o conjunto inteiro, só o cavalo ou só a carreta">
                                        <button type="button" class="active" data-view="all"><i class="fas fa-truck-moving"></i> Tudo</button>
                                        <button type="button" data-view="cavalo"><i class="fas fa-truck-front"></i> Cavalo</button>
                                        <button type="button" data-view="carreta"><i class="fas fa-trailer"></i> Carreta</button>
                                    </div>
                                    <div class="gip-eixos-wrap">
                                        <button type="button" class="gip-btn gip-btn--sm" id="gipEixosBtn" title="Adicionar / excluir eixos e salvar layout"><i class="fas fa-layer-group"></i> Eixos <i class="fas fa-caret-down" style="margin-left:2px"></i></button>
                                        <div class="gip-eixos-menu" id="gipEixosMenu" hidden>
                                            <button type="button" data-act="add-cav"><i class="fas fa-plus"></i> Adicionar eixo ao Caminhão</button>
                                            <button type="button" data-act="add-car"><i class="fas fa-plus"></i> Adicionar eixo à Carreta</button>
                                            <div class="gip-eixos-sep"></div>
                                            <button type="button" data-act="del-cav"><i class="fas fa-minus"></i> Excluir eixo do Caminhão</button>
                                            <button type="button" data-act="del-car"><i class="fas fa-minus"></i> Excluir eixo da Carreta</button>
                                            <div class="gip-eixos-sep"></div>
                                            <button type="button" data-act="save"><i class="fas fa-floppy-disk"></i> Salvar layout</button>
                                        </div>
                                    </div>
                                    <button type="button" class="gip-btn gip-btn--sm" id="gipSaude" title="Saúde geral do veículo + heatmap de desgaste"><i class="fas fa-heart-pulse"></i> Saúde</button>
                                    <button type="button" class="gip-btn gip-btn--sm" id="gipTimelineBtn" title="Linha do tempo do veículo"><i class="fas fa-timeline"></i> Timeline</button>
                                    <button type="button" class="gip-btn gip-btn--sm" id="gipRodizio" title="Sugerir rodízio de pneus pelo desgaste"><i class="fas fa-rotate"></i> Rodízio</button>
                                    <button type="button" class="gip-btn gip-btn--sm" id="gipMontar" title="Arrastar os eixos para posicionar"><i class="fas fa-up-down-left-right"></i> Montar eixos</button>
                                    <button type="button" class="gip-btn gip-btn--sm gip-btn--ghost" id="gipAutoMontagem" style="display:none" title="Voltar ao espaçamento automático"><i class="fas fa-wand-magic-sparkles"></i> Auto</button>
                                </div>
                            </div>
                            <div class="gip-legend" id="gipLegend" title="Clique para filtrar por este estado">
                                <span data-leg="reset"><i style="background:#94a3b8"></i> Sem pneu</span>
                                <span data-leg="bom"><i style="background:#22c55e"></i> Bom / Novo</span>
                                <span data-leg="atencao"><i style="background:#f59e0b"></i> Atenção</span>
                                <span data-leg="critico"><i style="background:#ef4444"></i> Crítico</span>
                                <span data-leg="recapagem"><i style="background:#3b82f6"></i> Recapagem</span>
                                <span data-leg="sem_calibragem"><i class="gip-leg-dash"></i> Calibragem vencida</span>
                            </div>
                            <div class="gip-card-body" style="position:relative">
                                <div class="gip-zoom" id="gipZoom">
                                    <button type="button" data-z="out" title="Diminuir"><i class="fas fa-minus"></i></button>
                                    <button type="button" data-z="reset" title="Ajustar à largura"><i class="fas fa-expand"></i></button>
                                    <button type="button" data-z="in" title="Aumentar"><i class="fas fa-plus"></i></button>
                                </div>
                                <div class="gip-stage" id="gipStage"></div>
                            </div>
                        </div>

                        <div class="gip-card gip-panel">
                            <div class="gip-card-head"><h3><i class="fas fa-gauge-high"></i> Pneu selecionado</h3></div>
                            <div class="gip-card-body" id="gipPanel">
                                <div class="gip-empty"><i class="fas fa-hand-pointer"></i>Clique em um pneu no estágio para ver os detalhes e ações.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Histórico + alertas -->
                    <div class="gip-bottom">
                        <div class="gip-card">
                            <div class="gip-card-head"><h3><i class="fas fa-timeline"></i> Histórico do pneu</h3></div>
                            <div class="gip-card-body">
                                <ul class="gip-timeline" id="gipTimeline"><li class="gip-tl-empty">Selecione um pneu para ver o histórico.</li></ul>
                            </div>
                        </div>
                        <div class="gip-card">
                            <div class="gip-card-head"><h3><i class="fas fa-bell"></i> Alertas <span id="gipAlertCount" style="font-weight:600;color:var(--text-muted,var(--text-secondary))"></span></h3></div>
                            <div class="gip-card-body">
                                <ul class="gip-alerts" id="gipAlerts"><li class="gip-alerts-empty">Selecione um veículo para ver os alertas.</li></ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal: medição de sulco -->
    <div class="gip-modal" id="gipSulcoModal">
        <div class="gip-modal-box">
            <div class="gip-modal-head"><h4>Nova medição de sulco</h4><button type="button" class="x" data-close>&times;</button></div>
            <div class="gip-modal-body">
                <div><label for="sulcoExt">Sulco externo (mm)</label><input type="number" step="0.1" id="sulcoExt" placeholder="0,0"></div>
                <div><label for="sulcoCen">Sulco central (mm)</label><input type="number" step="0.1" id="sulcoCen" placeholder="0,0"></div>
                <div><label for="sulcoInt">Sulco interno (mm)</label><input type="number" step="0.1" id="sulcoInt" placeholder="0,0"></div>
                <div><label for="sulcoKm">KM do veículo</label><input type="number" id="sulcoKm" placeholder="opcional"></div>
                <div class="full"><label for="sulcoResp">Responsável</label><input type="text" id="sulcoResp" placeholder="quem mediu"></div>
                <div class="full"><label for="sulcoObs">Observação</label><textarea id="sulcoObs" rows="2" placeholder="opcional"></textarea></div>
            </div>
            <div class="gip-modal-foot"><span class="msg" id="sulcoMsg"></span><button class="gip-btn" data-close>Cancelar</button><button class="gip-btn gip-btn--primary" id="sulcoSalvar"><i class="fas fa-ruler-vertical"></i> Salvar medição</button></div>
        </div>
    </div>

    <!-- Modal: calibragem -->
    <div class="gip-modal" id="gipCalibModal">
        <div class="gip-modal-box">
            <div class="gip-modal-head"><h4>Registrar calibragem</h4><button type="button" class="x" data-close>&times;</button></div>
            <div class="gip-modal-body">
                <div><label for="calibReco">Pressão recomendada (psi)</label><input type="number" step="0.5" id="calibReco" placeholder="ex: 120"></div>
                <div><label for="calibAfer">Pressão aferida (psi)</label><input type="number" step="0.5" id="calibAfer" placeholder="ex: 115"></div>
                <div><label for="calibKm">KM do veículo</label><input type="number" id="calibKm" placeholder="opcional"></div>
                <div><label for="calibResp">Responsável</label><input type="text" id="calibResp" placeholder="quem calibrou"></div>
                <div class="full"><label for="calibObs">Observação</label><textarea id="calibObs" rows="2" placeholder="opcional"></textarea></div>
            </div>
            <div class="gip-modal-foot"><span class="msg" id="calibMsg"></span><button class="gip-btn" data-close>Cancelar</button><button class="gip-btn gip-btn--primary" id="calibSalvar"><i class="fas fa-gauge"></i> Salvar calibragem</button></div>
        </div>
    </div>

    <div class="gip-tooltip" id="gipTooltip"></div>
    <div class="gip-toast" id="gipToast"></div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/gip-stage-premium.js?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="../js/gestao-interativa-pro.js?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'gestao_interativa_pro'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
