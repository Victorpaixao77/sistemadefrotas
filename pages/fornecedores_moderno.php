<?php
require_once '../includes/config.php';
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
    <script src="../js/fornecedores.js" defer></script>
    <style>
        /* Ajuste de espaçamento igual ao padrão de rotas (dashboard mais "colada" no topo) */
        body.fornecedores-modern-page .dashboard-content.fornc-page {
            padding-top: 8px;
        }
    </style>
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
                    <div class="fornc-kpi-cell"><span class="lbl">Total</span><span class="val" id="fornKpiTotal">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Ativos</span><span class="val" id="fornKpiAtivos">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Inativos</span><span class="val" id="fornKpiInativos">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">PJ</span><span class="val" id="fornKpiPJ">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">PF</span><span class="val" id="fornKpiPF">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">IBGE OK</span><span class="val" id="fornKpiIbgeOk">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Sem IBGE</span><span class="val" id="fornKpiIbgeFalta">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Com e-mail</span><span class="val" id="fornKpiEmail">0</span></div>
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
                        <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar CSV">
                            <i class="fas fa-file-export"></i> Exportar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>

                <div class="fornc-table-wrap">
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

    <div class="modal fornc-modal" id="helpFornecedorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Fornecedores</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão geral</h3>
                    <p>Cadastre fornecedores e clientes para reutilizar dados ao emitir <strong>NF-e</strong> (seleção no modal em Gestão de NF-e).</p>
                    <ul>
                        <li><strong>Pessoa jurídica:</strong> com CNPJ válido (14 dígitos, <strong>com ou sem</strong> pontos, barra e traço), os dados podem ser preenchidos pela <strong>BrasilAPI</strong> — botão &quot;Buscar CNPJ&quot; ou aguarde após digitar.</li>
                        <li><strong>cMun IBGE:</strong> código de 7 dígitos do município — necessário para endereço do destinatário na NF-e.</li>
                        <li><strong>Pessoa física:</strong> informe CPF de 11 dígitos.</li>
                    </ul>
                </div>
                <div class="help-section">
                    <h3>Filtros e lista</h3>
                    <p>Use <strong>Situação</strong> para buscar ativos, inativos ou todos no servidor. Use <strong>Tipo</strong> e a <strong>busca</strong> para refinar.</p>
                    <p>Clique nos títulos das colunas (exceto <strong>Ações</strong>) para ordenar: texto em ordem alfabética (A–Z ou Z–A); use novamente o mesmo título para inverter. A ordenação é feita no servidor.</p>
                </div>
                <div class="help-section">
                    <h3>Exportar</h3>
                    <p>O botão <strong>Exportar</strong> gera um CSV com os registros que batem com situação, tipo e busca (até 5.000 linhas).</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary close-modal">Fechar</button>
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
</body>
</html>
