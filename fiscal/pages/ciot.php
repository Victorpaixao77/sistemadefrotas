<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions.php';
configure_session();
session_start();
require_authentication();

if (!can_access_fiscal_system()) {
    header('Location: ' . sf_app_url('index.php'));
    exit;
}

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sf_api_base.php';
require_once __DIR__ . '/../../includes/sf_page_help.php';
require_once __DIR__ . '/../../includes/pef_ciot_helpers.php';

$page_title = 'CIOT — PEF ANTT';
$is_modern = true;
$pef_tipos_contratacao = sf_pef_tipos_contratacao();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <link rel="stylesheet" href="../../css/responsive.css">
    <link rel="stylesheet" href="../../css/fornc-modern-page.css">
    <link rel="icon" type="image/png" href="../../logo.png">
    <?php sf_page_help_css('../..'); ?>
    <style>
        .ciot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
        .ciot-grid .fg label { display: block; font-size: .85rem; margin-bottom: .35rem; color: var(--text-muted, #64748b); }
        .ciot-grid input, .ciot-grid select { width: 100%; padding: .5rem .65rem; border-radius: 8px; border: 1px solid var(--border-color, #cbd5e1); }
        .ciot-badge { display: inline-block; padding: .2rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .ciot-badge--declarado { background: #dcfce7; color: #166534; }
        .ciot-badge--erro { background: #fee2e2; color: #991b1b; }
        .ciot-badge--cancelado { background: #f1f5f9; color: #475569; }
        .ciot-badge--encerrado { background: #dbeafe; color: #1e40af; }
        .ciot-badge--rascunho { background: #fef9c3; color: #854d0e; }
        .ciot-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .ciot-table th, .ciot-table td { padding: .65rem .75rem; border-bottom: 1px solid var(--border-color, #e2e8f0); text-align: left; }
        .ciot-table th { font-size: .8rem; text-transform: uppercase; color: var(--text-muted, #64748b); }
        .ciot-modal { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15,23,42,.45); align-items: center; justify-content: center; padding: 1rem; }
        .ciot-modal.is-open { display: flex; }
        .ciot-modal__box { background: var(--card-bg, #fff); border-radius: 12px; max-width: 720px; width: 100%; max-height: 90vh; overflow: auto; padding: 1.25rem; }
        .ciot-modal__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .ciot-hint { font-size: .85rem; color: var(--text-muted, #64748b); margin: 0 0 1rem; }
        .ciot-alert { display: none; grid-column: 1 / -1; padding: .75rem 1rem; border-radius: 8px; font-size: .85rem; margin-bottom: .25rem; }
        .ciot-alert--bloqueio { display: block; background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .ciot-alert--aviso { display: block; background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; }
        .ciot-alert--ok { display: block; background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
    </style>
</head>
<body class="routes-modern">
    <div class="app-container">
        <?php include '../../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            <div class="dashboard-content fornc-page routes-modern-page">
                <div class="dashboard-header" style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:1rem;">
                    <div>
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <p class="ciot-hint">
                        Declaração e gestão de CIOT junto à ANTT (PEF v1.1).
                        Configure certificado e ambiente em
                        <a href="<?php echo htmlspecialchars(sf_app_url('pages/configuracoes.php?aba=fiscal')); ?>">Configurações → Fiscal &amp; Multas</a>.
                    </p>
                    </div>
                    <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost" title="Ajuda">
                        <i class="fas fa-question-circle"></i> Ajuda
                    </button>
                </div>

                <div id="ciotInactiveBanner" class="ciot-hint" style="display:none;padding:.75rem 1rem;background:#fff3cd;border-radius:8px;border:1px solid #ffc107;margin-bottom:1rem;"></div>

                <div class="fornc-toolbar">
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="ciotStatusFilter">Status</label>
                            <select id="ciotStatusFilter">
                                <option value="">Todos</option>
                                <option value="declarado">Declarado</option>
                                <option value="encerrado">Encerrado</option>
                                <option value="cancelado">Cancelado</option>
                                <option value="erro">Erro</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="btnNovaDeclaracao"><i class="fas fa-plus"></i> Declarar CIOT</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="btnConsultarSituacao"><i class="fas fa-user-check"></i> Situação transportador</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="btnConsultarFrota"><i class="fas fa-truck"></i> Frota ANTT</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="btnAtualizarLista"><i class="fas fa-sync-alt"></i> Atualizar</button>
                    </div>
                </div>
                <div id="pefConsultaResult" class="ciot-hint" style="margin-top:.5rem;display:none;white-space:pre-wrap;font-family:monospace;font-size:.8rem;"></div>

                <div class="table-container fornc-table-wrap" style="margin-top:8px;">
                    <div id="ciotListWrap"><p>Carregando...</p></div>
                </div>
            </div>
        </div>
    </div>

    <div class="ciot-modal" id="ciotDeclararModal" role="dialog" aria-modal="true">
        <div class="ciot-modal__box">
            <div class="ciot-modal__head">
                <h2 style="margin:0;font-size:1.1rem;">Declarar operação de transporte</h2>
                <button type="button" class="fornc-btn fornc-btn--ghost" id="ciotModalClose" aria-label="Fechar">&times;</button>
            </div>
            <form id="ciotDeclararForm" class="ciot-grid">
                <div class="fg" style="grid-column:1/-1;">
                    <label for="ciot_tipo_contratacao">Tipo de contratação do transporte <span style="color:#dc2626">*</span></label>
                    <select name="tipo_contratacao" id="ciot_tipo_contratacao" required>
                        <option value="">— Selecione —</option>
                        <?php foreach ($pef_tipos_contratacao as $k => $label): ?>
                        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="ciot-hint" style="margin-top:.35rem;">CIOT aplica-se a TAC, agregado ou ETC terceirizada — não a frota própria.</small>
                </div>
                <div id="ciotContratacaoAlert" class="ciot-alert" role="alert"></div>
                <div class="fg">
                    <label>Tipo operação</label>
                    <select name="tipo_operacao" id="ciot_tipo_operacao">
                        <option value="1">1 — Lotação</option>
                        <option value="2">2 — Fracionada</option>
                        <option value="3">3 — TAC-Agregado</option>
                    </select>
                </div>
                <div class="fg">
                    <label>CPF/CNPJ contratado</label>
                    <input name="cpf_cnpj_contratado" required>
                </div>
                <div class="fg">
                    <label>RNTRC contratado</label>
                    <input name="rntrc_contratado" required>
                </div>
                <div class="fg">
                    <label>CPF/CNPJ contratante</label>
                    <input name="cpf_cnpj_contratante" required>
                </div>
                <div class="fg">
                    <label>CPF/CNPJ destinatário</label>
                    <input name="cpf_cnpj_destinatario">
                </div>
                <div class="fg">
                    <label>Valor frete (R$)</label>
                    <input name="valor_frete" type="number" step="0.01" min="0" required>
                </div>
                <div class="fg">
                    <label>Início viagem</label>
                    <input name="data_inicio_viagem" type="date" required>
                </div>
                <div class="fg">
                    <label>Fim viagem</label>
                    <input name="data_fim_viagem" type="date" required>
                </div>
                <div class="fg">
                    <label>Placa veículo</label>
                    <input name="veiculo_placa" placeholder="ABC1D23" required>
                </div>
                <div class="fg">
                    <label>RNTRC veículo</label>
                    <input name="veiculo_rntrc">
                </div>
                <div class="fg">
                    <label>Cód. município origem (IBGE)</label>
                    <input name="codigo_municipio_origem" placeholder="3550308">
                </div>
                <div class="fg">
                    <label>Cód. município destino (IBGE)</label>
                    <input name="codigo_municipio_destino">
                </div>
                <div class="fg">
                    <label>Distância (km)</label>
                    <input name="distancia_km" type="number" min="0">
                </div>
                <div class="fg">
                    <label>Peso carga (kg)</label>
                    <input name="peso_carga" type="number" step="0.01" min="0">
                </div>
            </form>
            <div class="fornc-btn-row" style="margin-top:1rem;">
                <button type="button" class="fornc-btn fornc-btn--primary" id="btnEnviarDeclaracao"><i class="fas fa-paper-plane"></i> Enviar à ANTT</button>
                <button type="button" class="fornc-btn fornc-btn--ghost" id="btnCancelarModal">Cancelar</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/sidebar.js"></script>
    <script src="../../js/theme.js"></script>
    <?php sf_render_api_scripts(); ?>
    <script src="js/ciot.js?v=<?php echo file_exists(__DIR__ . '/js/ciot.js') ? (int) filemtime(__DIR__ . '/js/ciot.js') : 1; ?>"></script>
    <?php $sf_help_key = 'ciot_pef'; include __DIR__ . '/../../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('../..'); ?>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
