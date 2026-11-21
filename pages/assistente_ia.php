<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Centro de Inteligência';
$csrf_token = csrf_token_get();
$pode_financeiro = can_view_financial_data();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/ia-copilot.css?v=1.5.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="icon" type="image/png" href="../logo.png">
    <style>html, body { overflow-x: hidden; max-width: 100%; }</style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="ci-wrap ci-wrap--wide">
                    <div class="ci-hero">
                        <h1><i class="fas fa-brain"></i> Centro de Inteligência</h1>
                        <button type="button" id="helpBtn" class="ci-section" title="Ajuda desta tela" aria-label="Ajuda desta tela" style="margin-top:10px"><i class="fas fa-question-circle"></i> Ajuda</button>
                        <p>Copiloto enterprise — agentes especializados, previsões, documentos, ações com confirmação e auditoria. <strong>Somente leitura</strong> no banco; ações exigem sua aprovação.</p>
                    </div>

                    <nav class="ci-sections" aria-label="Seções do hub">
                        <button type="button" class="ci-section active" data-panel="chat"><i class="fas fa-comments"></i> Chat</button>
                        <button type="button" class="ci-section" data-panel="insights"><i class="fas fa-bell"></i> Insights</button>
                        <button type="button" class="ci-section" data-panel="previsoes"><i class="fas fa-chart-line"></i> Previsões</button>
                        <button type="button" class="ci-section" data-panel="documentos"><i class="fas fa-file-upload"></i> Documentos</button>
                        <button type="button" class="ci-section" data-panel="automacoes"><i class="fas fa-bolt"></i> Automações</button>
                        <button type="button" class="ci-section" data-panel="auditoria"><i class="fas fa-shield-halved"></i> Auditoria</button>
                        <?php if (can_manage_system_settings()): ?>
                        <button type="button" class="ci-section" data-panel="aprendizado"><i class="fas fa-graduation-cap"></i> Aprendizado</button>
                        <?php endif; ?>
                        <button type="button" class="ci-section" data-panel="saude"><i class="fas fa-heart-pulse"></i> Saúde</button>
                        <button type="button" class="ci-section" data-panel="skills"><i class="fas fa-puzzle-piece"></i> Skills</button>
                        <button type="button" class="ci-section" data-panel="memoria"><i class="fas fa-database"></i> Memória</button>
                    </nav>

                    <div class="ci-toolbar">
                        <button type="button" id="ciExecutivo" class="ci-btn-exec"><i class="fas fa-briefcase"></i> Resumo Executivo</button>
                        <?php if ($pode_financeiro): ?>
                        <a href="../api/ia_relatorio_pdf.php" class="ci-btn-exec ci-btn-link" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> PDF Diretoria</a>
                        <?php endif; ?>
                        <label class="ci-toggle"><input type="checkbox" id="ciUseStream" checked> Streaming</label>
                        <label class="ci-toggle" title="Timeline e trace para suporte"><input type="checkbox" id="ciDevMode"> Modo dev</label>
                        <button type="button" id="ciSugerirAcoes" class="ci-btn-exec"><i class="fas fa-wand-magic-sparkles"></i> Sugerir revisões</button>
                    </div>

                    <!-- Painel Chat -->
                    <div id="panel-chat" class="ci-panel">
                        <nav class="ci-modes" aria-label="Modos do copiloto">
                            <button type="button" class="ci-mode active" data-modo="conversar"><i class="fas fa-comments"></i> Conversar</button>
                            <button type="button" class="ci-mode" data-modo="analisar"><i class="fas fa-chart-line"></i> Analisar</button>
                            <button type="button" class="ci-mode" data-modo="automatizar"><i class="fas fa-bolt"></i> Automatizar</button>
                            <button type="button" class="ci-mode" data-modo="explicar"><i class="fas fa-circle-question"></i> Explicar</button>
                            <button type="button" class="ci-mode" data-modo="recomendar"><i class="fas fa-lightbulb"></i> Recomendar</button>
                        </nav>

                        <div id="ciInsightsInline" class="ci-insights ci-insights--compact" role="region" aria-live="polite">
                            <h3><i class="fas fa-spinner fa-spin"></i> Carregando insights…</h3>
                        </div>

                        <div class="ai-chips" id="aiChips"></div>

                        <div class="ai-chat" id="aiChat" role="log" aria-live="polite">
                            <div class="ai-msg bot">
                                <div class="av"><i class="fas fa-robot"></i></div>
                                <div class="ai-bubble">
                                    Olá! Sou seu <strong>copiloto da operação</strong> com agentes Financeiro, Frota, Oficina, Logística, RH e Comercial.
                                    Experimente: <em>«Analise minha empresa»</em>, <em>«Previsões para os próximos 30 dias»</em> ou envie documentos na aba Documentos.
                                </div>
                            </div>
                        </div>

                        <div class="ai-inputbar">
                            <label class="ai-attach" title="Enviar documento e perguntar">
                                <input type="file" id="aiAttach" accept=".txt,.pdf,.png,.jpg,.jpeg,.webp" hidden>
                                <i class="fas fa-paperclip"></i>
                            </label>
                            <label class="ai-attach" title="Foto — OCR + análise">
                                <input type="file" id="aiImage" accept="image/*" capture="environment" hidden>
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="text" id="aiInput" placeholder="Digite ou use o microfone…" autocomplete="off" aria-label="Pergunta ao copiloto">
                            <button type="button" id="aiMic" class="ai-btn-mic" title="Falar pergunta" aria-label="Entrada por voz"><i class="fas fa-microphone"></i></button>
                            <button type="button" id="aiSend" aria-label="Enviar"><i class="fas fa-paper-plane"></i></button>
                        </div>
                        <p class="ai-disclaimer">🔒 Escopo da sua empresa · auditoria completa · cache 15 min em consultas repetidas</p>
                    </div>

                    <!-- Painel Insights -->
                    <div id="panel-insights" class="ci-panel ci-panel-hidden">
                        <div id="ciInsights" class="ci-insights" role="region" aria-live="polite">
                            <h3><i class="fas fa-spinner fa-spin"></i> Carregando…</h3>
                        </div>
                    </div>

                    <!-- Painel Previsões -->
                    <div id="panel-previsoes" class="ci-panel ci-panel-hidden">
                        <div id="ciPrevisoes" class="ci-card-box"><p class="ai-meta">Carregando previsões…</p></div>
                    </div>

                    <!-- Painel Documentos -->
                    <div id="panel-documentos" class="ci-panel ci-panel-hidden">
                        <form id="ciDocForm" class="ci-upload-form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <label>Arquivo (TXT, PDF, imagem)
                                <input type="file" name="arquivo" accept=".txt,.pdf,.png,.jpg,.jpeg,.webp" required>
                            </label>
                            <label>Texto complementar (opcional)
                                <textarea name="texto" rows="2" placeholder="Cole trechos da NF/contrato se o PDF não for lido automaticamente"></textarea>
                            </label>
                            <button type="submit" class="ci-btn-exec"><i class="fas fa-upload"></i> Enviar documento</button>
                            <button type="button" id="ciReindexEmb" class="ci-btn-exec ci-btn-ghost" style="margin-left:8px"><i class="fas fa-sync"></i> Reindexar vetores</button>
                        </form>
                        <div id="ciDocList" class="ci-card-box"><p class="ai-meta">Nenhum documento ainda.</p></div>
                    </div>

                    <!-- Painel Automações -->
                    <div id="panel-automacoes" class="ci-panel ci-panel-hidden">
                        <p class="ai-meta">Ações propostas pelo copiloto. Nada é executado sem sua confirmação.</p>
                        <div id="ciAcoes" class="ci-card-box"></div>
                    </div>

                    <!-- Painel Auditoria -->
                    <div id="panel-auditoria" class="ci-panel ci-panel-hidden">
                        <div id="ciAuditoria" class="ci-card-box"><p class="ai-meta">Carregando auditoria…</p></div>
                    </div>

                    <?php if (can_manage_system_settings()): ?>
                    <!-- Painel Aprendizado (admin) -->
                    <div id="panel-aprendizado" class="ci-panel ci-panel-hidden">
                        <div class="ci-card-box">
                            <h3 style="margin:0 0 6px;font-size:14px"><i class="fas fa-graduation-cap"></i> Treinar a IA</h3>
                            <p class="ai-meta">Perguntas que a IA ainda não entendeu (mais frequentes primeiro). Mapeie cada uma para uma intenção — a partir daí, perguntas parecidas passam a ser entendidas automaticamente.</p>
                            <div id="ciAprendPend"><p class="ai-meta">Carregando…</p></div>
                        </div>
                        <div class="ci-card-box">
                            <h3 style="margin:0 0 6px;font-size:14px">Sinônimos ativos</h3>
                            <p class="ai-meta">Mapeamentos criados por você. Desative para o classificador ignorar.</p>
                            <div id="ciAprendCustom"><p class="ai-meta">—</p></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Painel Saúde IA -->
                    <div id="panel-saude" class="ci-panel ci-panel-hidden">
                        <div id="ciSaude" class="ci-card-box"><p class="ai-meta">Carregando métricas…</p></div>
                        <div class="ci-card-box" style="margin-top:12px">
                            <h3 style="margin:0 0 8px;font-size:14px">Playground (benchmark)</h3>
                            <p class="ai-meta">Compare classificação em modelos diferentes (consome quota IA).</p>
                            <form id="ciPlayForm" class="ci-memoria-form">
                                <label>Pergunta de teste
                                    <input type="text" id="ciPlayPerg" placeholder="Ex.: quanto gastei de manutenção?">
                                </label>
                                <label>Modelos (vírgula)
                                    <input type="text" id="ciPlayModelos" value="gpt-4o-mini">
                                </label>
                                <label class="ci-toggle"><input type="checkbox" id="ciPlaySuite"> Suite fixa (8 perguntas)</label>
                                <button type="submit" class="ci-btn-exec"><i class="fas fa-flask"></i> Rodar</button>
                            </form>
                            <div id="ciPlayResult"></div>
                        </div>
                    </div>

                    <!-- Painel Skills -->
                    <div id="panel-skills" class="ci-panel ci-panel-hidden">
                        <div id="ciSkills" class="ci-card-box"><p class="ai-meta">Carregando skills…</p></div>
                    </div>

                    <!-- Painel Memória -->
                    <div id="panel-memoria" class="ci-panel ci-panel-hidden">
                        <form id="ciMemoriaForm" class="ci-memoria-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                            <label>Foco da operação
                                <input type="text" name="foco" id="memFoco" placeholder="Ex.: reduzir custo/km, melhorar pontualidade">
                            </label>
                            <label>Período padrão nas perguntas
                                <select name="periodo_padrao" id="memPeriodo">
                                    <option value="">Automático</option>
                                    <option value="mes atual">Mês atual</option>
                                    <option value="mes passado">Mês passado</option>
                                    <option value="ultimos 12 meses">Últimos 12 meses</option>
                                </select>
                            </label>
                            <label>Notas para o copiloto
                                <textarea name="notas" id="memNotas" rows="3" placeholder="Metas, restrições, contexto da empresa"></textarea>
                            </label>
                            <button type="submit" class="ci-btn-exec"><i class="fas fa-save"></i> Salvar memória</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        window.SF_IA = {
            csrf: <?php echo json_encode($csrf_token, JSON_UNESCAPED_UNICODE); ?>,
            podeFinanceiro: <?php echo $pode_financeiro ? 'true' : 'false'; ?>
        };
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../js/ia-copilot.js?v=1.6.0"></script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'assistente_ia'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
