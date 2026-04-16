<?php
/**
 * MDF-e: toasts, confirmação genérica, busca cliente/fornecedor (sem lógica PHP).
 */
?>
<div id="mdfeToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 11000" aria-live="polite"></div>

<div class="modal fade fornc-modal" id="modalMdfeConfirm" tabindex="-1" aria-modal="true" role="dialog" aria-labelledby="modalMdfeConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title modal-mdfe-confirm-title" id="modalMdfeConfirmTitle">Confirmar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body modal-mdfe-confirm-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
                <button type="button" class="btn btn-primary" id="modalMdfeConfirmSim">Sim</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade fornc-modal" id="modalMdfeBuscaFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMdfeBuscaFornecedorTitle">Pesquisar cliente / fornecedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="search" class="form-control" id="modalMdfeBuscaFornecedorTermo" placeholder="Nome, CNPJ ou CPF" autocomplete="off">
                    <button type="button" class="btn btn-primary" id="modalMdfeBuscaFornecedorBtnPesquisar" onclick="executarBuscaFornecedorMdfe()">Pesquisar</button>
                </div>
                <div id="modalMdfeBuscaFornecedorLista" class="list-group"></div>
            </div>
        </div>
    </div>
</div>
