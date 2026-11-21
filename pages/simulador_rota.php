<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_api_base.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Simulador de Rota';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/routes.css?v=1.0.20">
    <link rel="icon" type="image/png" href="../logo.png">
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        /* Exibe o simulador (antes um modal) como conteúdo de página */
        #routeSimulationModal { display: block !important; position: static; inset: auto; background: none; padding: 0; }
        #routeSimulationModal .route-sim-modal-content, #routeSimulationModal .modal-content {
            max-width: 100%; width: 100%; margin: 0; box-shadow: none; border: none; background: none; max-height: none;
        }
        #routeSimulationModal .modal-header { border: none; padding: 0 0 12px; }
        #routeSimulationModal .modal-body { padding: 0; }

        /* ===== Simulação rápida (what-if) ===== */
        .wf-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 18px 20px; margin-bottom: 20px; }
        .wf-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
        .wf-head h2 { margin: 0; font-size: 1.15rem; color: var(--text-primary); }
        .wf-head .wf-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #fff; background: var(--accent-primary); padding: 3px 10px; border-radius: 999px; }
        .wf-head .wf-reset { margin-left: auto; }
        .wf-sub { color: var(--text-muted); font-size: 13px; margin: 0 0 16px; }
        .wf-grid { display: grid; grid-template-columns: 1.1fr 1fr; gap: 22px; align-items: start; }
        @media (max-width: 900px) { .wf-grid { grid-template-columns: 1fr; } }
        .wf-controls { display: flex; flex-direction: column; gap: 14px; }
        .wf-ctrl label { display: flex; justify-content: space-between; align-items: baseline; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; }
        .wf-ctrl label b { color: var(--text-primary); font-weight: 800; font-size: 14px; }
        .wf-ctrl label b small { color: var(--text-muted); font-weight: 600; font-size: 11px; }
        .wf-row { display: flex; align-items: center; gap: 10px; }
        .wf-row input[type="range"] { flex: 1; accent-color: var(--accent-primary); height: 4px; }
        .wf-row input[type="number"] { width: 96px; padding: 7px 9px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); font-size: 13px; font-weight: 700; text-align: right; }
        .wf-toggle { display: inline-flex; gap: 6px; align-items: center; font-size: 13px; color: var(--text-secondary); cursor: pointer; }
        .wf-out { display: flex; flex-direction: column; gap: 12px; }
        .wf-verdict { border-radius: 14px; padding: 16px 18px; text-align: center; border: 1px solid var(--border-color); }
        .wf-verdict.lucro { background: rgba(22,163,74,.12); border-color: rgba(22,163,74,.4); }
        .wf-verdict.prej { background: rgba(220,38,38,.12); border-color: rgba(220,38,38,.4); }
        .wf-verdict .lbl { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); }
        .wf-verdict .val { font-size: 2rem; font-weight: 800; margin: 4px 0; }
        .wf-verdict.lucro .val { color: #16a34a; } .wf-verdict.prej .val { color: #dc2626; }
        .wf-verdict .hint { font-size: 12px; color: var(--text-secondary); }
        .wf-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .wf-kpi { background: var(--bg-tertiary); border-radius: 10px; padding: 11px 12px; text-align: center; }
        .wf-kpi .k { font-size: 10px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
        .wf-kpi .v { font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin-top: 2px; }
        .wf-break { background: var(--bg-tertiary); border-radius: 10px; padding: 12px 14px; }
        .wf-break h4 { margin: 0 0 8px; font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .03em; }
        .wf-break .b-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; border-bottom: 1px dashed var(--border-color); }
        .wf-break .b-row:last-child { border-bottom: none; }
        .wf-break .b-row .bk { color: var(--text-secondary); } .wf-break .b-row .bv { color: var(--text-primary); font-weight: 700; }
        .wf-eq { font-size: 13px; color: var(--text-secondary); margin-top: 10px; }
        .wf-eq b { color: var(--accent-primary); }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">

    <!-- ===== Simulação rápida (what-if) — 100% instantânea, sem rota real ===== -->
    <div class="wf-card" id="whatIfCard">
        <div class="wf-head">
            <span class="wf-badge">Novo</span>
            <h2>⚡ Simulação rápida (what-if)</h2>
            <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost" title="Ajuda desta tela" aria-label="Ajuda desta tela"><i class="fas fa-question-circle"></i> Ajuda</button>
            <button type="button" id="wfReset" class="fornc-btn fornc-btn--ghost wf-reset"><i class="fas fa-undo"></i> Restaurar</button>
        </div>
        <p class="wf-sub">Arraste os controles e veja <strong>lucro, margem e ponto de equilíbrio</strong> mudarem na hora — sem precisar traçar a rota. Ideal para responder “e se o diesel subir?”, “e se a distância for maior?”.</p>

        <div class="wf-grid">
            <div class="wf-controls">
                <div class="wf-ctrl">
                    <label for="wfFrete">Valor do frete <b>R$ <span id="wfFreteV">3.500</span></b></label>
                    <div class="wf-row"><input type="range" id="wfFrete" min="0" max="30000" step="50" value="3500"><input type="number" id="wfFreteN" step="50" value="3500"></div>
                </div>
                <div class="wf-ctrl">
                    <label for="wfDist">Distância <b><span id="wfDistV">800</span> <small>km</small></b></label>
                    <div class="wf-row"><input type="range" id="wfDist" min="10" max="3000" step="10" value="800"><input type="number" id="wfDistN" step="10" value="800"></div>
                    <label class="wf-toggle" style="margin-top:6px"><input type="checkbox" id="wfRoundTrip"> Ida e volta (dobra a distância)</label>
                </div>
                <div class="wf-ctrl">
                    <label for="wfDiesel">Preço do diesel <b>R$ <span id="wfDieselV">5,50</span> <small>/L</small></b></label>
                    <div class="wf-row"><input type="range" id="wfDiesel" min="4" max="9" step="0.05" value="5.50"><input type="number" id="wfDieselN" step="0.05" value="5.50"></div>
                </div>
                <div class="wf-ctrl">
                    <label for="wfConsumo">Consumo <b><span id="wfConsumoV">2,5</span> <small>km/L</small></b></label>
                    <div class="wf-row"><input type="range" id="wfConsumo" min="1" max="5" step="0.1" value="2.5"><input type="number" id="wfConsumoN" step="0.1" value="2.5"></div>
                </div>
                <div class="wf-ctrl">
                    <label for="wfPedagio">Pedágio (total) <b>R$ <span id="wfPedagioV">120</span></b></label>
                    <div class="wf-row"><input type="range" id="wfPedagio" min="0" max="3000" step="10" value="120"><input type="number" id="wfPedagioN" step="10" value="120"></div>
                </div>
                <div class="wf-ctrl">
                    <label for="wfComissao">Comissão do motorista <b><span id="wfComissaoV">10</span> <small>%</small></b></label>
                    <div class="wf-row"><input type="range" id="wfComissao" min="0" max="30" step="0.5" value="10"><input type="number" id="wfComissaoN" step="0.5" value="10"></div>
                </div>
                <div class="wf-ctrl">
                    <label for="wfOpKm">Custo operacional <b>R$ <span id="wfOpKmV">0,80</span> <small>/km (manut./pneus/depr.)</small></b></label>
                    <div class="wf-row"><input type="range" id="wfOpKm" min="0" max="4" step="0.05" value="0.80"><input type="number" id="wfOpKmN" step="0.05" value="0.80"></div>
                </div>
                <div class="wf-ctrl">
                    <label for="wfFixo">Outros custos fixos (diária, ARLA…) <b>R$ <span id="wfFixoV">0</span></b></label>
                    <div class="wf-row"><input type="range" id="wfFixo" min="0" max="5000" step="50" value="0"><input type="number" id="wfFixoN" step="50" value="0"></div>
                </div>
            </div>

            <div class="wf-out">
                <div class="wf-verdict lucro" id="wfVerdict">
                    <div class="lbl" id="wfVerdictLbl">Lucro estimado</div>
                    <div class="val" id="wfLucro">R$ 0,00</div>
                    <div class="hint" id="wfLucroHint">margem 0% · R$ 0,00/km</div>
                </div>
                <div class="wf-kpis">
                    <div class="wf-kpi"><div class="k">Receita</div><div class="v" id="wfReceita">R$ 0</div></div>
                    <div class="wf-kpi"><div class="k">Custo total</div><div class="v" id="wfCusto">R$ 0</div></div>
                    <div class="wf-kpi"><div class="k">Frete / km</div><div class="v" id="wfFreteKm">R$ 0</div></div>
                </div>
                <div class="wf-break">
                    <h4>Composição do custo</h4>
                    <div class="b-row"><span class="bk">⛽ Combustível (<span id="wfLitros">0</span> L)</span><span class="bv" id="wfCombustivel">R$ 0</span></div>
                    <div class="b-row"><span class="bk">🛣️ Pedágio</span><span class="bv" id="wfBkPedagio">R$ 0</span></div>
                    <div class="b-row"><span class="bk">👤 Comissão</span><span class="bv" id="wfBkComissao">R$ 0</span></div>
                    <div class="b-row"><span class="bk">🔧 Operacional</span><span class="bv" id="wfBkOper">R$ 0</span></div>
                    <div class="b-row"><span class="bk">📌 Fixos</span><span class="bv" id="wfBkFixo">R$ 0</span></div>
                </div>
                <p class="wf-eq">⚖️ <strong>Frete de equilíbrio</strong> (lucro zero): <b id="wfEquilibrio">R$ 0,00</b> — abaixo disso você opera no prejuízo.</p>
            </div>
        </div>
    </div>

    <div class="modal" id="routeSimulationModal">
        <div class="modal-content route-sim-modal-content">
            <div class="modal-header">
                <h2>🚛 Simulador de Rota</h2>
            </div>
            <div class="modal-body">
                <div class="route-sim-grid-main">
                    <div class="simulation-form">
                        <h3 class="route-sim-section-title">📍 Configuração da Rota</h3>
                        
                        <div class="form-section">
                            <h4 class="route-sim-subsection-title">Origem</h4>
                            <div class="form-grid route-sim-form-grid">
                                <div class="form-group">
                                    <label for="sim_estado_origem">Estado</label>
                                    <select id="sim_estado_origem" class="route-sim-input">
                                        <option value="">Selecione o estado</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="sim_cidade_origem_id">Cidade</label>
                                    <select id="sim_cidade_origem_id" class="route-sim-input" disabled>
                                        <option value="">Selecione primeiro o estado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h4 class="route-sim-subsection-title">Destino</h4>
                            <div class="form-grid route-sim-form-grid">
                                <div class="form-group">
                                    <label for="sim_estado_destino">Estado</label>
                                    <select id="sim_estado_destino" class="route-sim-input">
                                        <option value="">Selecione o estado</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="sim_cidade_destino_id">Cidade</label>
                                    <select id="sim_cidade_destino_id" class="route-sim-input" disabled>
                                        <option value="">Selecione primeiro o estado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-grid route-sim-form-grid">
                            <div class="form-group">
                                <label for="simVehicle">Veículo (cadastro)</label>
                                <select id="simVehicle" class="route-sim-input">
                                    <option value="">Selecione um veículo</option>
                                </select>
                                <p id="simVehicleCarretaLine" class="route-sim-vehicle-meta" aria-live="polite"></p>
                            </div>
                            <div class="form-group">
                                <label for="simDriver">Motorista</label>
                                <select id="simDriver" class="route-sim-input">
                                    <option value="">Selecione (preenche a comissão)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="simCargoWeight">Peso da carga (kg)</label>
                                <input type="number" id="simCargoWeight" class="route-sim-input" step="1" min="0" placeholder="Ex: 25000">
                                <p id="simCargoWarn" class="route-sim-vehicle-meta" aria-live="polite"></p>
                            </div>
                            <div class="form-group">
                                <label class="route-sim-trip-label">Tipo de viagem</label>
                                <div class="route-sim-trip-toggle" role="radiogroup" aria-label="Tipo de viagem">
                                    <label class="route-sim-trip-opt"><input type="radio" name="simTripType" value="one_way" checked> Somente ida</label>
                                    <label class="route-sim-trip-opt"><input type="radio" name="simTripType" value="round_trip"> Ida e volta</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="route-sim-subsection-title">Consumo e combustível</h4>
                            <div class="form-grid route-sim-form-grid">
                                <div class="form-group">
                                    <label for="simFuelType">Combustível</label>
                                    <select id="simFuelType" class="route-sim-input">
                                        <option value="DIESEL" selected>Diesel</option>
                                        <option value="GASOLINE">Gasolina</option>
                                        <option value="HYBRID">Híbrido</option>
                                        <option value="ELECTRIC">Elétrico</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="simConsumption">Consumo (km/L)</label>
                                    <input type="number" id="simConsumption" class="route-sim-input" step="0.1" min="0.1" placeholder="Auto pelo histórico">
                                    <p id="simConsumptionMeta" class="route-sim-vehicle-meta" aria-live="polite"></p>
                                </div>
                                <div class="form-group">
                                    <label for="simFuelPrice">Preço (R$/L)</label>
                                    <input type="number" id="simFuelPrice" class="route-sim-input" step="0.01" value="5.50">
                                    <p id="simFuelPriceMeta" class="route-sim-vehicle-meta" aria-live="polite"></p>
                                </div>
                                <div class="form-group">
                                    <label for="simTankCapacity">Tanque (L)</label>
                                    <input type="number" id="simTankCapacity" class="route-sim-input" step="1" min="1" value="400">
                                </div>
                                <div class="form-group">
                                    <label for="simArlaPrice">ARLA 32 (R$/L)</label>
                                    <input type="number" id="simArlaPrice" class="route-sim-input" step="0.01" min="0" placeholder="0 = ignorar">
                                    <p class="route-sim-vehicle-meta">Diesel consome ~5% de ARLA. Em branco/0 não entra no custo.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="route-sim-subsection-title">Avaliação do frete</h4>
                            <div class="form-grid route-sim-form-grid">
                                <div class="form-group">
                                    <label for="simFreteValue">Valor do frete (R$)</label>
                                    <input type="number" id="simFreteValue" class="route-sim-input" step="0.01" min="0" placeholder="Ex: 3500">
                                </div>
                                <div class="form-group">
                                    <label for="simCommissionPct">Comissão motorista (%)</label>
                                    <input type="number" id="simCommissionPct" class="route-sim-input" step="0.1" min="0" max="100" placeholder="Ex: 10">
                                </div>
                                <div class="form-group">
                                    <label for="simOpCostPerKm">Custo operacional (R$/km)</label>
                                    <input type="number" id="simOpCostPerKm" class="route-sim-input" step="0.01" min="0" placeholder="Manut./pneus/depr.">
                                </div>
                                <div class="form-group">
                                    <label for="simTargetMargin">Margem-alvo (%)</label>
                                    <input type="number" id="simTargetMargin" class="route-sim-input" step="1" min="0" max="99" placeholder="Ex: 20">
                                    <p id="simSuggestedFreight" class="route-sim-vehicle-meta" aria-live="polite"></p>
                                </div>
                                <div class="form-group">
                                    <label for="simCargoType">Tipo de carga (piso ANTT)</label>
                                    <select id="simCargoType" class="route-sim-input">
                                        <option value="geral" selected>Carga Geral</option>
                                        <option value="granel_solido">Granel sólido</option>
                                        <option value="granel_liquido">Granel líquido</option>
                                        <option value="frigorificada">Frigorificada</option>
                                        <option value="conteinerizada">Conteinerizada</option>
                                        <option value="neogranel">Neogranel</option>
                                        <option value="granel_pressurizada">Granel pressurizada</option>
                                        <option value="perigosa_geral">Perigosa (geral)</option>
                                        <option value="perigosa_granel_solido">Perigosa (granel sólido)</option>
                                        <option value="perigosa_granel_liquido">Perigosa (granel líquido)</option>
                                        <option value="perigosa_frigorificada">Perigosa (frigorificada)</option>
                                        <option value="perigosa_conteinerizada">Perigosa (conteinerizada)</option>
                                    </select>
                                    <label class="route-sim-trip-opt" style="margin-top:6px;">
                                        <input type="checkbox" id="simAltoDesempenho"> Operação de alto desempenho (Tabela C)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="route-sim-subsection-title">Pedágio</h4>
                            <div class="form-grid route-sim-form-grid">
                                <div class="form-group">
                                    <label for="simTollPass">TAG / pedágio (Google Routes)</label>
                                    <select id="simTollPass" class="route-sim-input">
                                        <option value="">Não informar (preço padrão da API)</option>
                                        <option value="BR_SEM_PARAR">Sem Parar</option>
                                        <option value="BR_CONECTCAR">ConectCar</option>
                                        <option value="BR_VELOE">Veloe</option>
                                        <option value="BR_MOVE_MAIS">Move Mais</option>
                                        <option value="BR_TAGGY">Taggy</option>
                                        <option value="BR_AUTO_EXPRESO">Auto Expresso</option>
                                        <option value="BR_PASSA_RAPIDO">Passa Rápido</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="simTollManual">Pedágio real (R$) — opcional</label>
                                    <input type="number" id="simTollManual" class="route-sim-input" step="0.01" min="0" placeholder="Ex: 122.40 (Qualp/Sem Parar)">
                                </div>
                            </div>
                            <p class="route-sim-vehicle-meta">A estimativa do Google é imprecisa para caminhão no Brasil. Sabendo o pedágio real (Qualp, Sem Parar…), informe-o que o sistema usa esse valor no custo.</p>
                        </div>

                        <button type="button" id="simulateRouteBtnModal" class="btn-route-sim-calc">
                            <i class="fas fa-calculator"></i> Simular Rota
                        </button>
                    </div>

                    <div class="simulation-map">
                        <h3 class="route-sim-section-title">🗺️ Mapa da Rota</h3>
                        <p class="route-sim-vehicle-meta">Mapa base <strong>OpenStreetMap</strong> — funciona sem cadastro de API. A rota traçada usa a mesma base; opcionalmente o Google Maps se houver chave.</p>
                        <label class="route-sim-trip-opt" style="display:block;margin-bottom:10px;">
                            <input type="checkbox" id="simUseGoogleMap" value="1">
                            Traçar no <strong>Google Maps</strong> (opcional — requer chave em Configurações e Maps JavaScript API ativa)
                        </label>
                        <div id="simulationMap" class="route-sim-map-el"></div>
                        <div id="simulationInfo" class="route-sim-alert--info" role="status"></div>
                        <div id="simulationError" class="route-sim-alert--err" role="alert"></div>

                        <div class="route-sim-external-box">
                            <h4 class="route-sim-external-title">
                                <i class="fas fa-external-link-alt"></i> Simuladores de Rotas Online
                            </h4>
                            <div class="route-sim-external-grid">
                                <a href="https://rotasbrasil.com.br/" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-route"></i>
                                    <span>Rotas Brasil</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="https://www.webrouter.com.br/way/#/calcularRota" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <span>WebRouter</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="https://qualp.com.br/#/" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-route"></i>
                                    <span>Qualp</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="https://www.semparar.com.br/trace-sua-rota" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-tag"></i>
                                    <span>Sem Parar</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="simulationResults" class="route-sim-results">
                    <h3 class="route-sim-results-title">📊 Resultados da Simulação</h3>
                    
                    <div class="route-sim-result-cards">
                        <div class="result-card result-card--dist">
                            <h4 class="rc-title rc-title--green">📏 Distância</h4>
                            <p class="rc-val rc-val--green" id="simDistance">-</p>
                            <p class="rc-sub rc-sub--green" id="simDuration">-</p>
                        </div>
                        <div class="result-card result-card--fuel">
                            <h4 class="rc-title rc-title--orange">⛽ Combustível</h4>
                            <p class="rc-val rc-val--orange" id="simFuelCost">-</p>
                            <p class="rc-sub rc-sub--orange" id="simFuelLiters">-</p>
                        </div>
                        <div class="result-card result-card--toll">
                            <h4 class="rc-title rc-title--orange">🛣️ Pedágios</h4>
                            <p class="rc-val rc-val--orange" id="simTolls">-</p>
                            <p class="rc-sub rc-sub--orange" id="simTollCount">-</p>
                        </div>
                        <div class="result-card result-card--total">
                            <h4 class="rc-title rc-title--white">💰 Custo Total</h4>
                            <p class="rc-val rc-val--total" id="simTotalCost">-</p>
                            <p class="rc-sub rc-sub--total" id="simCostPerKm">-</p>
                        </div>
                    </div>

                    <div class="route-sim-result-cards route-sim-profit-cards" id="simProfitCards" style="display:none;">
                        <div class="result-card result-card--profit">
                            <h4 class="rc-title">📈 Lucro estimado</h4>
                            <p class="rc-val" id="simProfit">-</p>
                            <p class="rc-sub" id="simProfitPerKm">-</p>
                        </div>
                        <div class="result-card result-card--margin">
                            <h4 class="rc-title">🧮 Margem</h4>
                            <p class="rc-val" id="simMargin">-</p>
                            <p class="rc-sub" id="simMarginHint">sobre o frete</p>
                        </div>
                        <div class="result-card result-card--commission">
                            <h4 class="rc-title">👤 Comissão</h4>
                            <p class="rc-val" id="simCommission">-</p>
                            <p class="rc-sub" id="simCommissionHint">do motorista</p>
                        </div>
                        <div class="result-card result-card--freight">
                            <h4 class="rc-title">🚚 Frete / km</h4>
                            <p class="rc-val" id="simFreightPerKm">-</p>
                            <p class="rc-sub" id="simFreightHint">receita por km</p>
                        </div>
                        <div class="result-card result-card--perday">
                            <h4 class="rc-title">📅 Lucro / dia</h4>
                            <p class="rc-val" id="simProfitPerDay">-</p>
                            <p class="rc-sub" id="simProfitPerDayHint">pela duração da viagem</p>
                        </div>
                    </div>

                    <div class="route-sim-antt" id="simAnttPanel" style="display:none;">
                        <h4>⚖️ Piso mínimo de frete (ANTT)</h4>
                        <div class="route-sim-antt-grid">
                            <div><span class="ra-kv-label">Piso ANTT</span><strong id="simAnttPiso">—</strong></div>
                            <div><span class="ra-kv-label">Frete informado</span><strong id="simAnttFrete">—</strong></div>
                            <div><span class="ra-kv-label">Diferença</span><strong id="simAnttDiff">—</strong></div>
                        </div>
                        <div id="simAnttAlert" class="route-sim-antt-alert" role="status"></div>
                        <p class="route-sim-vehicle-meta" id="simAnttMeta"></p>
                    </div>

                    <div class="route-sim-details-panel">
                        <h4>🛣️ Detalhes da Rota</h4>
                        <div id="routeDetails" class="route-sim-details-scroll"></div>
                    </div>

                    <div class="route-sim-actions">
                        <button type="button" id="simSaveBtn" class="btn-secondary"><i class="fas fa-save"></i> Salvar simulação</button>
                        <button type="button" id="simCreateRouteBtn" class="btn-route-sim-calc">
                            <i class="fas fa-plus"></i> Criar rota com esta simulação
                        </button>
                    </div>

                    <div class="route-sim-saved" id="simSavedPanel" style="display:none;">
                        <div class="route-sim-saved-head">
                            <h4>📚 Simulações salvas (comparação)</h4>
                            <button type="button" id="simClearSavedBtn" class="btn-link-sm">Limpar todas</button>
                        </div>
                        <div class="route-sim-saved-table-wrap">
                            <table class="route-sim-saved-table">
                                <thead>
                                    <tr><th>Rota</th><th>Veículo</th><th class="rd-num">Dist.</th><th class="rd-num">Custo</th><th class="rd-num">Frete</th><th class="rd-num">Lucro</th><th class="rd-num">Margem</th><th></th></tr>
                                </thead>
                                <tbody id="simSavedBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../google-maps/maps.js"></script>
    <script src="../google-maps/route-manager.js"></script>
    <script src="../google-maps/geolocation.js"></script>
    <script src="../js/routes-simulation.js?v=1.0.16"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof initRouteSimulator === 'function') { try { initRouteSimulator(); } catch (e) { console.error(e); } }
        });
    </script>
    <script>
    (function(){
        var $ = function(id){ return document.getElementById(id); };
        var moeda = function(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); };
        var moedaK = function(v){ return 'R$ ' + Math.round(Number(v||0)).toLocaleString('pt-BR'); };
        var br1 = function(v){ return Number(v).toFixed(1).replace('.',','); };
        var br2 = function(v){ return Number(v).toFixed(2).replace('.',','); };
        var brInt = function(v){ return Math.round(Number(v)).toLocaleString('pt-BR'); };

        // base -> formatador do rótulo (span wf<base>V)
        var CTRL = {
            Frete:   { fmt: brInt },
            Dist:    { fmt: brInt },
            Diesel:  { fmt: br2 },
            Consumo: { fmt: br1 },
            Pedagio: { fmt: brInt },
            Comissao:{ fmt: function(v){ return (Number(v)%1===0)? String(Number(v)) : br1(v); } },
            OpKm:    { fmt: br2 },
            Fixo:    { fmt: brInt }
        };
        var DEFAULTS = { Frete:3500, Dist:800, Diesel:5.5, Consumo:2.5, Pedagio:120, Comissao:10, OpKm:0.8, Fixo:0 };

        function val(base){ var r = $('wf'+base); return r ? parseFloat(r.value)||0 : 0; }

        function sync(base, from){
            var r = $('wf'+base), n = $('wf'+base+'N'), s = $('wf'+base+'V');
            if(!r||!n) return;
            if(from==='range'){ n.value = r.value; }
            else { // veio do number: respeita limites do range
                var v = parseFloat(n.value); if(isNaN(v)) v = 0;
                var min=parseFloat(r.min), max=parseFloat(r.max);
                if(v<min) v=min; if(v>max) v=max;
                r.value = v; n.value = v;
            }
            if(s) s.textContent = CTRL[base].fmt(parseFloat(r.value)||0);
        }

        function calc(){
            var frete=val('Frete'), distBase=val('Dist'), diesel=val('Diesel'), consumo=val('Consumo'),
                pedagio=val('Pedagio'), comPct=val('Comissao'), opKm=val('OpKm'), fixo=val('Fixo');
            var dist = distBase * ($('wfRoundTrip').checked ? 2 : 1);
            var litros = consumo>0 ? dist/consumo : 0;
            var comb = litros*diesel;
            var comissao = frete*comPct/100;
            var oper = dist*opKm;
            var custo = comb + pedagio + comissao + oper + fixo;
            var lucro = frete - custo;
            var margem = frete>0 ? (lucro/frete*100) : 0;
            var lucroKm = dist>0 ? (lucro/dist) : 0;
            var freteKm = dist>0 ? (frete/dist) : 0;
            var equil = comPct<100 ? (comb+pedagio+oper+fixo)/(1-comPct/100) : 0;

            var vc = $('wfVerdict');
            vc.className = 'wf-verdict ' + (lucro>=0 ? 'lucro' : 'prej');
            $('wfVerdictLbl').textContent = lucro>=0 ? 'Lucro estimado' : 'Prejuízo estimado';
            $('wfLucro').textContent = moeda(lucro);
            $('wfLucroHint').textContent = 'margem ' + br1(margem) + '% · ' + moeda(lucroKm) + '/km';
            $('wfReceita').textContent = moedaK(frete);
            $('wfCusto').textContent = moedaK(custo);
            $('wfFreteKm').textContent = moeda(freteKm);
            $('wfLitros').textContent = brInt(litros);
            $('wfCombustivel').textContent = moedaK(comb);
            $('wfBkPedagio').textContent = moedaK(pedagio);
            $('wfBkComissao').textContent = moedaK(comissao);
            $('wfBkOper').textContent = moedaK(oper);
            $('wfBkFixo').textContent = moedaK(fixo);
            $('wfEquilibrio').textContent = moeda(equil);
        }

        function wire(base){
            var r=$('wf'+base), n=$('wf'+base+'N');
            if(!r||!n) return;
            r.addEventListener('input', function(){ sync(base,'range'); calc(); });
            n.addEventListener('input', function(){ sync(base,'number'); calc(); });
        }

        function init(){
            if(!$('whatIfCard')) return;
            Object.keys(CTRL).forEach(function(b){ wire(b); sync(b,'range'); });
            $('wfRoundTrip').addEventListener('change', calc);
            $('wfReset').addEventListener('click', function(){
                Object.keys(DEFAULTS).forEach(function(b){ $('wf'+b).value = DEFAULTS[b]; sync(b,'range'); });
                $('wfRoundTrip').checked = false; calc();
            });
            calc();
        }
        if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'simulador_rota'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
