<?php
/** Wizard CT-e — criar rascunho e opcionalmente enviar à SEFAZ */
?>
<div class="modal fade fornc-modal" id="modalCteWizard" tabindex="-1" aria-labelledby="modalCteWizardLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCteWizardLabel"><i class="fas fa-truck-loading"></i> Novo CT-e</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Selecione as NF-e recebidas, veículo, motorista e dados do frete. O sistema monta o XML CT-e layout 4.00 (modal rodoviário, rem/dest, chaves NF-e).</p>

                <div class="row g-3">
                    <div class="col-12">
                        <label for="cteWizardOtSel" class="form-label fw-semibold"><i class="fas fa-file-signature"></i> Puxar de uma Ordem de Transporte <span class="text-muted small">(opcional — pré-preenche veículo, motorista, origem/destino, frete e peso)</span></label>
                        <select id="cteWizardOtSel" class="form-select">
                            <option value="">— não usar OT —</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">NF-e vinculadas <span class="text-danger">*</span></label>
                        <div id="cteWizardNfeList" class="border rounded p-2" style="max-height:220px;overflow-y:auto;">
                            <p class="text-muted small mb-0">Carregando NF-e...</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="cteWizardVeiculo" class="form-label">Veículo <span class="text-danger">*</span></label>
                        <select id="cteWizardVeiculo" class="form-select">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="cteWizardMotorista" class="form-label">Motorista <span class="text-danger">*</span></label>
                        <select id="cteWizardMotorista" class="form-select">
                            <option value="">Selecione...</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="cteWizardOrigem" class="form-label">Origem</label>
                        <input type="text" class="form-control" id="cteWizardOrigem" placeholder="Cidade/UF (ex: Maringá/PR)">
                    </div>
                    <div class="col-md-6">
                        <label for="cteWizardDestino" class="form-label">Destino</label>
                        <input type="text" class="form-control" id="cteWizardDestino" placeholder="Cidade/UF">
                    </div>

                    <div class="col-md-4">
                        <label for="cteWizardValorFrete" class="form-label">Valor do frete (R$)</label>
                        <input type="number" class="form-control" id="cteWizardValorFrete" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-4">
                        <label for="cteWizardPeso" class="form-label">Peso total (kg)</label>
                        <input type="number" class="form-control" id="cteWizardPeso" min="0" step="0.001" value="0">
                    </div>
                    <div class="col-md-4">
                        <label for="cteWizardVolumes" class="form-label">Volumes</label>
                        <input type="number" class="form-control" id="cteWizardVolumes" min="0" step="1" value="0">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="cteWizardEmitirApos" checked>
                            <label class="form-check-label" for="cteWizardEmitirApos">Enviar à SEFAZ após salvar (se dados válidos)</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div id="cteWizardStatus" class="alert d-none mb-0" role="alert"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnCteWizardSalvar">
                    <i class="fas fa-save"></i> Salvar CT-e
                </button>
            </div>
        </div>
    </div>
</div>
