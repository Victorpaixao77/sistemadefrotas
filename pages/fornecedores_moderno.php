<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_api_base.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Fornecedores';
$csrf_token = csrf_token_get();

// Canônico: se alguém acessar /fornecedores_moderno.php diretamente,
// redirecionar para /fornecedores.php (a URL principal).
if (!defined('FORNECEDORES_CANONICAL_RENDER')) {
    $qs = http_build_query($_GET);
    header('Location: fornecedores.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/doc_validators.js"></script>
    <script src="../js/fornecedores.js?v=1.2" defer></script>
    <style>
        /* Ajuste de espaçamento igual ao padrão de rotas (dashboard mais "colada" no topo) */
        body.fornecedores-modern-page .dashboard-content.fornc-page {
            padding-top: 8px;
        }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="fornecedores-modern-page">
    <div id="fornGlobalLoading" aria-hidden="true"><div class="forn-spinner-box"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin:0.75rem 0 0;font-size:0.9rem;">Carregando...</p></div></div>
    <div id="fornToast" role="status" aria-live="polite"></div>

    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="dashboard-content fornc-page">

                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-building fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Total</span></div><span class="val" id="fornKpiTotal">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-circle-check fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Ativos</span></div><span class="val" id="fornKpiAtivos">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-ban fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Inativos</span></div><span class="val" id="fornKpiInativos">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-building fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">PJ</span></div><span class="val" id="fornKpiPJ">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-user fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">PF</span></div><span class="val" id="fornKpiPF">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-map fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">IBGE OK</span></div><span class="val" id="fornKpiIbgeOk">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-map-location-dot fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Sem IBGE</span></div><span class="val" id="fornKpiIbgeFalta">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-envelope fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Com e-mail</span></div><span class="val" id="fornKpiEmail">0</span></div>
                </div>
                <!-- Resumo de KPI removido: o texto "Base: ... | Ativos: ..." não deve aparecer na tela. -->

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchFornecedor">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchFornecedor" placeholder="Nome, CPF, CNPJ, cidade..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="filtroSituacaoFornecedor">Situação</label>
                            <select id="filtroSituacaoFornecedor" title="Filtrar por situação no servidor">
                                <option value="A" selected>Ativos</option>
                                <option value="I">Inativos</option>
                                <option value="all">Todos</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="filterTipoFornecedor">Tipo</label>
                            <select id="filterTipoFornecedor" title="Filtrar PJ/PF">
                                <option value="">Todos</option>
                                <option value="J">PJ</option>
                                <option value="F">PF</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="perPageFornecedores">Por página</label>
                            <select id="perPageFornecedores" class="filter-per-page" title="Registros por página">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" id="btnNovoFornecedor" class="fornc-btn fornc-btn--primary">
                            <i class="fas fa-plus"></i> Novo
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyFornecedorFilters" title="Aplicar filtros">
                            <i class="fas fa-search"></i> Pesquisar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn" title="Filtros (modal)">
                            <i class="fas fa-sliders-h"></i> Opções
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearFornecedorFilters" title="Limpar busca e tipo">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="importBtn" title="Importar fornecedores de CSV">
                            <i class="fas fa-file-import"></i> Importar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar CSV">
                            <i class="fas fa-file-export"></i> Exportar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>

                <!-- Insights de fornecedores: qualidade do cadastro + ranking -->
                <div class="fn-insights" id="fnInsights" style="display:none;">
                    <div class="fn-head">
                        <button type="button" class="fn-toggle" id="fnToggle" aria-expanded="false" aria-controls="fnBody">
                            <span class="fn-summary" id="fnSummary"></span>
                            <i class="fas fa-chevron-down fn-caret" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="fnRefresh" title="Reanalisar" aria-label="Reanalisar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="fn-body" id="fnBody" style="display:none;">
                        <div class="fn-section">
                            <h4><i class="fas fa-clipboard-check"></i> Qualidade do cadastro</h4>
                            <div id="fnQualList"></div>
                        </div>
                        <div class="fn-section">
                            <h4><i class="fas fa-ranking-star"></i> Maior gasto (contas a pagar)</h4>
                            <div id="fnRankList"></div>
                        </div>
                    </div>
                </div>

                <!-- Resumo executivo por IA -->
                <div class="fn-resumo-ia" id="fnResumoIa" style="display:none;">
                    <div class="fn-resumo-head">
                        <h4><i class="fas fa-wand-magic-sparkles"></i> Resumo executivo (IA)</h4>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="fnResumoBtn"><i class="fas fa-bolt"></i> Gerar resumo</button>
                    </div>
                    <div id="fnResumoTexto" class="fn-resumo-texto text-muted">Clique em “Gerar resumo” para uma análise dos fornecedores pela IA.</div>
                </div>

                <div class="fornc-empty-state" id="fornEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhum fornecedor encontrado</p>
                    <p class="fornc-empty-state-hint">Ajuste a busca ou a situação nos filtros, ou cadastre um novo fornecedor.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="fornEmptyCreateBtn"><i class="fas fa-plus"></i> Cadastrar primeiro fornecedor</button>
                </div>

                <div class="fornc-table-wrap" id="fornTableWrap">
                    <table class="fornc-table">
                        <thead>
                            <tr>
                                <th class="sortable sorted" data-sort="nome">Nome / razão social <span class="sort-ind">▲</span></th>
                                <th class="sortable" data-sort="tipo">Tipo <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="documento">CPF / CNPJ <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="cidade_uf">Cidade / UF <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="situacao">Situação <span class="sort-ind">⇅</span></th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="fornecedoresTableBody">
                            <tr><td colspan="6" style="text-align:center;padding:1rem;">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="fornc-pagination-bar">
                    <div class="pagination fornc-modern-pagination" id="fornecedoresPagination">
                        <a href="#" class="pagination-btn disabled" id="fornPrevPage" title="Página anterior" aria-label="Página anterior">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="pagination-info" id="fornPaginationInfo">—</span>
                        <a href="#" class="pagination-btn disabled" id="fornNextPage" title="Próxima página" aria-label="Próxima página">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Posição financeira -->
    <div class="modal fornc-modal" id="modalPosicaoFinanceira">
        <div class="modal-content modal-lg fornc-modal--wide">
            <div class="modal-header">
                <h2 id="posFinTitle">Posição financeira</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="posFinBody">
                <p style="color:var(--text-secondary);">Carregando...</p>
            </div>
            <div class="modal-footer">
                <a href="<?php echo htmlspecialchars(sf_app_url('pages/contas_pagar.php')); ?>" class="btn-secondary" style="text-decoration:none;display:inline-block;">Ir para contas a pagar</a>
                <button type="button" class="btn-primary close-modal">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Posição fiscal -->
    <div class="modal fornc-modal" id="modalPosicaoFiscal">
        <div class="modal-content modal-lg fornc-modal--wide">
            <div class="modal-header">
                <h2 id="posFiscTitle">Posição fiscal</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="posFiscBody">
                <p style="color:var(--text-secondary);">Carregando...</p>
            </div>
            <div class="modal-footer">
                <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/nfe.php')); ?>" class="btn-secondary" style="text-decoration:none;display:inline-block;">Ir para NF-e</a>
                <button type="button" class="btn-primary close-modal">Fechar</button>
            </div>
        </div>
    </div>

    <div class="modal fornc-modal" id="filterFornecedorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtrar fornecedores</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="modalFilterSit">Situação</label>
                    <select id="modalFilterSit">
                        <option value="A">Ativos</option>
                        <option value="I">Inativos</option>
                        <option value="all">Todos</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalFilterTipo">Tipo</label>
                    <select id="modalFilterTipo">
                        <option value="">Todos</option>
                        <option value="J">Pessoa jurídica</option>
                        <option value="F">Pessoa física</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalFilterSearch">Busca</label>
                    <input type="text" id="modalFilterSearch" placeholder="Nome, CPF, CNPJ, cidade...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="modalFornecedorClearFilter">Limpar</button>
                <button type="button" class="btn-primary" id="modalFornecedorApplyFilter">Aplicar</button>
            </div>
        </div>
    </div>

    <div class="modal fornc-modal" id="modalFornecedor">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 id="modalFornecedorTitle">Novo fornecedor</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formFornecedor" onsubmit="return false;">
                    <input type="hidden" id="fornecedor_id" value="">
                    <input type="hidden" id="forn_csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="forn_tipo">Tipo</label>
                            <select id="forn_tipo">
                                <option value="J">Pessoa jurídica (CNPJ)</option>
                                <option value="F">Pessoa física (CPF)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="forn_nome">Nome / razão social *</label>
                            <input type="text" id="forn_nome" required maxlength="200">
                        </div>
                        <div class="form-group" id="forn_cnpj_wrap">
                            <label for="forn_cnpj">CNPJ (com ou sem formatação)</label>
                            <div class="forn-cnpj-row">
                                <input type="text" id="forn_cnpj" maxlength="18" autocomplete="off" placeholder="Ex.: 11.167.599/0001-79 ou só números">
                                <button type="button" class="btn-secondary" id="btnConsultarCnpj" title="Consulta gratuita BrasilAPI">
                                    <i class="fas fa-cloud-download-alt"></i> Buscar CNPJ
                                </button>
                            </div>
                            <small id="fornBrasilapiHint" class="forn-brasilapi-hint"></small>
                        </div>
                        <div class="form-group" id="forn_cpf_wrap" style="display:none;">
                            <label for="forn_cpf">CPF (somente números)</label>
                            <input type="text" id="forn_cpf" maxlength="11" inputmode="numeric" placeholder="11 dígitos">
                        </div>
                        <div class="form-group">
                            <label for="forn_ie">Inscrição estadual</label>
                            <input type="text" id="forn_ie" maxlength="20">
                        </div>
                        <div class="form-group">
                            <label for="forn_im">Inscrição municipal</label>
                            <input type="text" id="forn_im" maxlength="20">
                        </div>
                        <div class="form-group">
                            <label for="forn_regime">Regime tributário</label>
                            <input type="text" id="forn_regime" maxlength="50" placeholder="Ex.: Simples Nacional">
                        </div>
                        <div class="form-group">
                            <label for="forn_telefone">Telefone</label>
                            <input type="text" id="forn_telefone" maxlength="20">
                        </div>
                        <div class="form-group">
                            <label for="forn_email">E-mail</label>
                            <input type="email" id="forn_email" maxlength="200">
                        </div>
                        <div class="form-group">
                            <label for="forn_site">Site</label>
                            <input type="text" id="forn_site" maxlength="200">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Endereço</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="forn_endereco">Logradouro</label>
                                <input type="text" id="forn_endereco" maxlength="200">
                            </div>
                            <div class="form-group">
                                <label for="forn_numero">Nº</label>
                                <input type="text" id="forn_numero" maxlength="15">
                            </div>
                            <div class="form-group">
                                <label for="forn_complemento">Complemento</label>
                                <input type="text" id="forn_complemento" maxlength="80">
                            </div>
                            <div class="form-group">
                                <label for="forn_bairro">Bairro</label>
                                <input type="text" id="forn_bairro" maxlength="120">
                            </div>
                            <div class="form-group">
                                <label for="forn_cep">CEP</label>
                                <input type="text" id="forn_cep" maxlength="9">
                            </div>
                            <div class="form-group">
                                <label for="forn_uf">UF</label>
                                <input type="text" id="forn_uf" maxlength="2" style="text-transform:uppercase;">
                            </div>
                            <div class="form-group">
                                <label for="forn_cidade">Município</label>
                                <input type="text" id="forn_cidade" maxlength="120">
                            </div>
                            <div class="form-group">
                                <label for="forn_cMun">cMun IBGE (7)</label>
                                <input type="text" id="forn_cMun" maxlength="7" inputmode="numeric" placeholder="Ex.: 3550308">
                            </div>
                            <div class="form-group">
                                <label for="forn_pais">País</label>
                                <input type="text" id="forn_pais" value="Brasil" maxlength="60">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Comercial / financeiro</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="forn_tipo_forn">Tipo (categoria)</label>
                                <input type="text" id="forn_tipo_forn" maxlength="50" placeholder="Ex.: Peças, combustível">
                            </div>
                            <div class="form-group">
                                <label for="forn_limite">Limite de crédito (R$)</label>
                                <input type="number" step="0.01" id="forn_limite" value="0">
                            </div>
                            <div class="form-group">
                                <label for="forn_prazo">Prazo pagamento (dias)</label>
                                <input type="number" id="forn_prazo" value="0">
                            </div>
                            <div class="form-group">
                                <label for="forn_multa">Taxa multa (%)</label>
                                <input type="number" step="0.01" id="forn_multa" value="0">
                            </div>
                            <div class="form-group">
                                <label for="forn_juros">Taxa juros (%)</label>
                                <input type="number" step="0.01" id="forn_juros" value="0">
                            </div>
                            <div class="form-group">
                                <label for="forn_situacao">Situação</label>
                                <select id="forn_situacao">
                                    <option value="A">Ativo</option>
                                    <option value="I">Inativo</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="forn_obs">Observações</label>
                                <textarea id="forn_obs" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary close-modal">Cancelar</button>
                        <button type="button" class="btn-primary" id="btnSalvarFornecedor">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de importação CSV -->
    <div class="modal fornc-modal" id="modalFornImport">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Importar fornecedores (CSV)</h2>
                <span class="close-modal" data-close-import>&times;</span>
            </div>
            <div class="modal-body">
                <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:0;">
                    Colunas: <strong>nome</strong>, <strong>tipo</strong> (F/J), <strong>documento</strong> (CPF/CNPJ), <strong>email</strong>, <strong>cidade</strong>, <strong>uf</strong>.
                    Separador <code>;</code> ou <code>,</code>. CNPJ/CPF inválidos e duplicados são ignorados.
                </p>
                <div class="form-group">
                    <label for="fornImportFile">Arquivo CSV</label>
                    <input type="file" id="fornImportFile" accept=".csv,text/csv">
                </div>
                <p id="fornImportInfo" style="font-size:0.85rem;margin:0.4rem 0;"></p>
                <div class="table-container" style="max-height:240px;overflow:auto;">
                    <table class="data-table"><thead><tr><th>Nome</th><th>Tipo</th><th>Documento</th><th>E-mail</th><th>Cidade/UF</th></tr></thead>
                    <tbody id="fornImportPreview"></tbody></table>
                </div>
            </div>
            <div class="modal-footer">
                <span id="fornImportResult" style="margin-right:auto;font-size:0.85rem;font-weight:600;"></span>
                <button type="button" class="btn-secondary" data-close-import>Fechar</button>
                <button type="button" class="btn-primary" id="fornImportSaveBtn" disabled><i class="fas fa-file-import"></i> Importar</button>
            </div>
        </div>
    </div>

    <style>
        /* Painel de insights de fornecedores */
        .fn-insights { border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 1rem; overflow: hidden; background: var(--card-bg, var(--bg-secondary)); }
        .fn-insights.has-erros { border-color: #dc2626; }
        .fn-insights.has-avisos { border-color: #f59e0b; }
        .fn-head { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.6rem 0.5rem 0.85rem; }
        .fn-toggle { flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-primary); font-weight: 600; font-size: 0.9rem; text-align: left; padding: 0.2rem 0; }
        .fn-caret { transition: transform 0.2s ease; color: var(--text-muted); }
        .fn-toggle[aria-expanded="true"] .fn-caret { transform: rotate(180deg); }
        .fn-summary .fn-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; margin-left: 6px; }
        .fn-pill.erro { background: rgba(220,38,38,0.12); color: #dc2626; }
        .fn-pill.aviso { background: rgba(245,158,11,0.14); color: #b45309; }
        .fn-pill.ok { background: rgba(22,163,74,0.12); color: #16a34a; }
        .fn-body { padding: 0.25rem 0.85rem 0.7rem; max-height: 400px; overflow: auto; }
        .fn-section { padding: 0.5rem 0; border-top: 1px solid var(--border-color); }
        .fn-section:first-child { border-top: none; }
        .fn-section h4 { margin: 0 0 0.4rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; color: var(--text-primary); }
        .fn-section h4 i { color: var(--accent-primary, #3b82f6); }
        .fn-item { display: flex; gap: 0.55rem; padding: 0.4rem 0; border-top: 1px dashed var(--border-color); font-size: 0.85rem; }
        .fn-item:first-child { border-top: none; }
        .fn-item .fn-sev { flex: 0 0 auto; }
        .fn-item .fn-sev.erro { color: #dc2626; }
        .fn-item .fn-sev.aviso { color: #f59e0b; }
        .fn-item .fn-txt { flex: 1; min-width: 0; }
        .fn-item .fn-txt strong { color: var(--text-primary); }
        .fn-item .fn-edit { flex: 0 0 auto; }
        .fn-empty { font-size: 0.83rem; color: var(--text-muted); padding: 0.2rem 0; }
        .fn-rank-row { display: flex; justify-content: space-between; gap: 0.5rem; padding: 0.3rem 0; border-top: 1px dashed var(--border-color); font-size: 0.85rem; }
        .fn-rank-row:first-child { border-top: none; }
        .fn-resumo-ia { border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 1rem; padding: 0.75rem 0.85rem; background: var(--card-bg, var(--bg-secondary)); }
        .fn-resumo-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.4rem; }
        .fn-resumo-head h4 { margin: 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem; }
        .fn-resumo-head h4 i { color: var(--accent-primary, #3b82f6); }
        .fn-resumo-texto { font-size: 0.9rem; line-height: 1.5; color: var(--text-primary); }
        .fn-resumo-texto.text-muted { color: var(--text-muted); }
    </style>
    <?php $sf_help_key = 'fornecedores'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
