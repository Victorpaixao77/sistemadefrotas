<?php
/**
 * MDF-e: modais (origem, criar, selecionar CT-e/NF-e, wizard, edição, condutor).
 * Requer: $is_modern
 */
if (!isset($mdfe_ufs)) {
    $mdfe_ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
}
?>
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalEscolhaOrigemMdfe" tabindex="-1" aria-labelledby="modalEscolhaOrigemMdfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEscolhaOrigemMdfeLabel"><i class="fas fa-layer-group"></i> Como deseja iniciar o MDF-e?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mdfe-origem-grid">
                        <button type="button" class="mdfe-origem-card" onclick="iniciarNovoMDFEManual()">
                            <h6><i class="fas fa-file-circle-plus"></i> Novo MDF-e</h6>
                            <p>Cadastro manual completo pelo assistente (wizard).</p>
                        </button>
                        <button type="button" class="mdfe-origem-card" onclick="abrirEscolhaImportacaoMDFE('cte')">
                            <h6><i class="fas fa-receipt"></i> Partir de CT-e</h6>
                            <p>Escolha XML ou documentos já autorizados no sistema.</p>
                        </button>
                        <button type="button" class="mdfe-origem-card" onclick="abrirEscolhaImportacaoMDFE('nfe')">
                            <h6><i class="fas fa-file-invoice"></i> Partir de NF-e</h6>
                            <p>Escolha XML ou documentos já cadastrados no sistema.</p>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalImportacaoMdfe" tabindex="-1" aria-labelledby="modalImportacaoMdfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportacaoMdfeLabel"><i class="fas fa-download"></i> Origem dos dados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p id="modalImportacaoMdfeTexto" class="text-muted mb-3">Selecione como deseja iniciar.</p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="confirmarImportacaoMDFE('xml')">
                            <i class="fas fa-file-import"></i> Importar XML
                        </button>
                        <button type="button" class="btn btn-primary" onclick="confirmarImportacaoMDFE('sistema')">
                            <i class="fas fa-database"></i> Importar do sistema
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar MDF-e -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalVisualizarMdfe" tabindex="-1" aria-labelledby="modalVisualizarMdfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVisualizarMdfeLabel"><i class="fas fa-route"></i> MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalVisualizarMdfeBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalAuditoriaMdfe" tabindex="-1" aria-labelledby="modalAuditoriaMdfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAuditoriaMdfeLabel"><i class="fas fa-clipboard-check"></i> Auditoria MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalAuditoriaMdfeBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Criar MDF-e -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="criarMDFEModal" tabindex="-1" aria-labelledby="criarMDFEModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="criarMDFEModalLabel"><i class="fas fa-plus"></i> Criar MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Selecione os CT-e autorizados que farão parte desta viagem. O MDF-e será emitido no seu CNPJ (transportadora executora); basta informar a chave dos CT-e.</p>
                    <form id="criarMDFEForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="veiculoMDFE" class="form-label">Veículo</label>
                                <select class="form-select" id="veiculoMDFE" name="veiculo_id" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="motoristaMDFE" class="form-label">Motorista</label>
                                <select class="form-select" id="motoristaMDFE" name="motorista_id" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufInicio" class="form-label">UF Início</label>
                                <select class="form-select" id="ufInicio" name="uf_inicio" required>
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($mdfe_ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeCarregamento" class="form-label">Cidade carregamento</label>
                                <select class="form-select" id="cidadeCarregamento" name="municipio_carregamento" required disabled aria-describedby="hint_mdfe_legacy_mun_carga">
                                    <option value="">Selecione primeiro o estado</option>
                                </select>
                                <small id="hint_mdfe_legacy_mun_carga" class="form-text">Lista preenchida pela API após escolher a UF.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="tipoViagem" class="form-label">Tipo</label>
                                <select class="form-select" id="tipoViagem" name="tipo_viagem">
                                    <option value="1">Com CT-e</option>
                                    <option value="2">Sem CT-e</option>
                                    <option value="3">Misto</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufFim" class="form-label">UF Fim</label>
                                <select class="form-select" id="ufFim" name="uf_fim" required>
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($mdfe_ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeDescarregamento" class="form-label">Cidade descarregamento</label>
                                <select class="form-select" id="cidadeDescarregamento" name="municipio_descarregamento" required disabled aria-describedby="hint_mdfe_legacy_mun_desc">
                                    <option value="">Selecione primeiro o estado</option>
                                </select>
                                <small id="hint_mdfe_legacy_mun_desc" class="form-text">Lista preenchida pela API após escolher a UF.</small>
                            </div>
                            <div class="col-md-4"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CT-e autorizados</label>
                            <div id="cteSelector" class="cte-selector">Carregando CT-e...</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Peso total (kg)</label>
                                <input type="number" class="form-control" id="totalPesoMDFE" name="peso_total" step="0.01" min="0" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Volumes</label>
                                <input type="number" class="form-control" id="totalVolumesMDFE" name="volumes_total" min="0" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qtd CT-e</label>
                                <input type="number" class="form-control" id="totalCTe" name="total_cte" min="0" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor total</label>
                                <input type="number" class="form-control" id="valorTotalMDFE" name="valor_total" step="0.01" min="0" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="observacoesMDFE" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoesMDFE" name="observacoes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarMDFE" onclick="salvarMDFE()"><i class="fas fa-save"></i> Criar MDF-e</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="selecionarCTEWizardModal" tabindex="-1" aria-labelledby="selecionarCTEWizardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="selecionarCTEWizardModalLabel"><i class="fas fa-receipt"></i> Selecionar CT-e do sistema</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Selecione os CT-e para pré-preencher o <strong>Novo MDF-e</strong> (municípios, documentos e totalizadores).</p>
                    <div class="mb-3">
                        <label class="form-label">CT-e autorizados</label>
                        <div id="cteSelectorWizardMdfe" class="cte-selector">Carregando CT-e...</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Qtd CT-e selecionados</label>
                            <input type="number" class="form-control" id="totalCteWizardSelecionados" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Peso total (kg)</label>
                            <input type="number" class="form-control" id="totalPesoCteWizardSelecionados" step="0.001" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor total</label>
                            <input type="number" class="form-control" id="totalValorCteWizardSelecionados" step="0.01" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="importarCteSelecionadosParaWizardMDFE()">
                        <i class="fas fa-check"></i> Importar selecionados
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="selecionarNFEModal" tabindex="-1" aria-labelledby="selecionarNFEModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="selecionarNFEModalLabel"><i class="fas fa-file-invoice"></i> Selecionar NF-e do sistema</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Selecione as NF-e para pré-preencher a aba <strong>Documentos</strong> do Novo MDF-e.</p>
                    <div class="mb-3">
                        <label class="form-label">NF-e disponíveis</label>
                        <div id="nfeSelectorMdfe" class="cte-selector">Carregando NF-e...</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Qtd NF-e selecionadas</label>
                            <input type="number" class="form-control" id="totalNfeSelecionadasMdfe" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor total NF-e</label>
                            <input type="number" class="form-control" id="totalValorNfeSelecionadasMdfe" step="0.01" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="importarNfeSelecionadasParaMDFE()">
                        <i class="fas fa-check"></i> Importar selecionadas
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
    $ufs = $mdfe_ufs;
    include __DIR__ . '/mdfe_wizard_modal.php';
    ?>

    <!-- Modal Erro de Validação (com botão Editar) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalErroValidacaoMdfe" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Validação antes de emitir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="modalErroValidacaoMdfeTexto" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditarAposErro"><i class="fas fa-edit"></i> Editar MDF-e</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar MDF-e -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="editarMDFEModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editarMdfeId" value="">
                    <p class="text-muted small">Corrija os dados e salve. Depois tente enviar para a SEFAZ novamente.</p>
                    <form id="editarMDFEForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="veiculoMDFEEdit" class="form-label">Veículo</label>
                                <select class="form-select" id="veiculoMDFEEdit" name="veiculo_id" required></select>
                            </div>
                            <div class="col-md-6">
                                <label for="motoristaMDFEEdit" class="form-label">Motorista</label>
                                <select class="form-select" id="motoristaMDFEEdit" name="motorista_id" required></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufInicioEdit" class="form-label">UF Início</label>
                                <select class="form-select" id="ufInicioEdit" name="uf_inicio" required>
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($mdfe_ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeCarregamentoEdit" class="form-label">Cidade carregamento</label>
                                <select class="form-select" id="cidadeCarregamentoEdit" name="municipio_carregamento" required disabled></select>
                            </div>
                            <div class="col-md-4">
                                <label for="tipoViagemEdit" class="form-label">Tipo</label>
                                <select class="form-select" id="tipoViagemEdit" name="tipo_viagem">
                                    <option value="1">Com CT-e</option>
                                    <option value="2">Sem CT-e</option>
                                    <option value="3">Misto</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufFimEdit" class="form-label">UF Fim</label>
                                <select class="form-select" id="ufFimEdit" name="uf_fim" required>
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($mdfe_ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeDescarregamentoEdit" class="form-label">Cidade descarregamento</label>
                                <select class="form-select" id="cidadeDescarregamentoEdit" name="municipio_descarregamento" required disabled></select>
                            </div>
                            <div class="col-md-4"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Origem documental</label>
                            <div id="origemMdfeEditInfo" class="small text-muted">
                                <span class="mdfe-origem-badge origem-manual">Manual</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CT-e autorizados</label>
                            <div id="cteSelectorEdit" class="cte-selector">Carregando...</div>
                        </div>
                        <div class="mb-3">
                            <label for="observacoesMDFEEdit" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoesMDFEEdit" name="observacoes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarEditarMDFE"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Incluir Condutor (troca de motorista durante a viagem) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalIncluirCondutor" tabindex="-1" aria-labelledby="modalIncluirCondutorLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalIncluirCondutorLabel"><i class="fas fa-user-edit"></i> Incluir / Trocar condutor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Use quando houver troca de motorista durante a viagem (revezamento, etc.).</p>
                    <div class="mb-3">
                        <label for="condutorMotoristaSelect" class="form-label">Novo condutor</label>
                        <select class="form-select" id="condutorMotoristaSelect">
                            <option value="">Carregando...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarIncluirCondutor()"><i class="fas fa-check"></i> Incluir condutor</button>
                </div>
            </div>
        </div>
    </div>
