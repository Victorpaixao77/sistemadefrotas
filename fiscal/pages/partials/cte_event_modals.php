<?php
/** Modais de eventos CT-e (CC-e, desacordo, comprovante, inutilização) */
?>
<div class="modal fade fornc-modal" id="modalCteCce" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Carta de Correção (CC-e)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cceCteId" value="">
                <p class="text-muted small">Correção em campo permitido pelo MOC (ex.: informações complementares). Mínimo 15 caracteres recomendado.</p>
                <div class="mb-3">
                    <label class="form-label">Grupo alterado</label>
                    <select id="cceGrupo" class="form-select">
                        <option value="infAdic">infAdic (complementar)</option>
                        <option value="compl">compl</option>
                        <option value="ide">ide</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Campo alterado</label>
                    <input type="text" class="form-control" id="cceCampo" value="infCpl">
                </div>
                <div class="mb-3">
                    <label class="form-label">Texto da correção</label>
                    <textarea class="form-control" id="cceTexto" rows="4" placeholder="Descreva a correção..."></textarea>
                </div>
                <div id="cceStatus" class="alert d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btnEnviarCce"><i class="fas fa-paper-plane"></i> Enviar CC-e</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade fornc-modal" id="modalCteDesacordo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Desacordo do tomador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="desacordoCteId" value="">
                <p class="text-muted small">Evento 610110 — prestação de serviço em desacordo (tomador). Observação com no mínimo 15 caracteres.</p>
                <div class="mb-3">
                    <label class="form-label">UF do evento</label>
                    <input type="text" class="form-control" id="desacordoUf" maxlength="2" value="RS" placeholder="UF">
                </div>
                <div class="mb-3">
                    <label class="form-label">Observação</label>
                    <textarea class="form-control" id="desacordoObs" rows="4"></textarea>
                </div>
                <div id="desacordoStatus" class="alert d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-warning" id="btnEnviarDesacordo">Registrar desacordo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade fornc-modal" id="modalCteComprovante" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-check"></i> Comprovante de entrega</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ceCteId" value="">
                <div class="mb-3">
                    <label class="form-label">Nome do recebedor</label>
                    <input type="text" class="form-control" id="ceNome">
                </div>
                <div class="mb-3">
                    <label class="form-label">CPF/CNPJ do recebedor</label>
                    <input type="text" class="form-control" id="ceDoc">
                </div>
                <div class="mb-3">
                    <label class="form-label">Data/hora entrega (opcional)</label>
                    <input type="datetime-local" class="form-control" id="ceDh">
                </div>
                <div id="ceStatus" class="alert d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success" id="btnEnviarComprovante">Registrar entrega</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade fornc-modal" id="modalCteInutilizar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ban"></i> Inutilizar numeração CT-e</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label">Série</label>
                        <input type="number" class="form-control" id="inutSerie" value="1" min="1">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Nº inicial</label>
                        <input type="number" class="form-control" id="inutIni" min="1">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Nº final</label>
                        <input type="number" class="form-control" id="inutFim" min="1">
                    </div>
                </div>
                <div class="mb-3 mt-2">
                    <label class="form-label">Justificativa (mín. 15 caracteres)</label>
                    <textarea class="form-control" id="inutJust" rows="3"></textarea>
                </div>
                <div id="inutStatus" class="alert d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-danger" id="btnInutilizarCte">Inutilizar na SEFAZ</button>
            </div>
        </div>
    </div>
</div>
