<!-- Modal Importar NF-e -->
<div class="modal fade" id="importNFEModal" tabindex="-1" aria-labelledby="importNFEModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importNFEModalLabel">
                    <i class="fas fa-upload text-primary"></i> Importar NF-e via XML
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importNFEForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="xmlFile" class="form-label">Arquivo XML da NF-e</label>
                        <input type="file" class="form-control" id="xmlFile" name="xml_file" accept=".xml" required>
                        <div class="form-text">Selecione o arquivo XML da NF-e emitida pela SEFAZ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Área de Upload</label>
                        <div id="uploadArea" class="upload-area">
                            <div class="upload-placeholder">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Arraste e solte o arquivo XML aqui ou clique para selecionar</p>
                                <small class="text-muted">Formatos aceitos: XML</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Observações sobre a NF-e..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="importarNFEXML()">
                    <i class="fas fa-upload"></i> Importar NF-e
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Emitir CT-e -->
<div class="modal fade" id="emitirCTeModal" tabindex="-1" aria-labelledby="emitirCTeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emitirCTeModalLabel">
                    <i class="fas fa-truck text-success"></i> Emitir Conhecimento de Transporte (CT-e)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="cteForm">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Dados do CT-e</h6>
                            
                            <div class="mb-3">
                                <label for="numero_cte" class="form-label">Número do CT-e</label>
                                <input type="text" class="form-control" id="numero_cte" name="numero_cte" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="serie_cte" class="form-label">Série</label>
                                <input type="text" class="form-control" id="serie_cte" name="serie_cte" value="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="data_emissao" class="form-label">Data de Emissão</label>
                                <input type="date" class="form-control" id="data_emissao" name="data_emissao" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tipo_servico" class="form-label">Tipo de Serviço</label>
                                <select class="form-select" id="tipo_servico" name="tipo_servico" required>
                                    <option value="normal">Normal</option>
                                    <option value="complemento">Complemento</option>
                                    <option value="anulacao">Anulação</option>
                                    <option value="substituicao">Substituição</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="natureza_operacao" class="form-label">Natureza da Operação</label>
                                <input type="text" class="form-control" id="natureza_operacao" name="natureza_operacao" placeholder="Ex: Transporte de mercadorias" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Origem e Destino</h6>
                            
                            <div class="mb-3">
                                <label for="origem_estado" class="form-label">Estado de Origem</label>
                                <select class="form-select" id="origem_estado" name="origem_estado" required>
                                    <option value="">Selecione o estado</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="PR">Paraná</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="BA">Bahia</option>
                                    <option value="GO">Goiás</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="ES">Espírito Santo</option>
                                    <option value="CE">Ceará</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PA">Pará</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="TO">Tocantins</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="PB">Paraíba</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="AC">Acre</option>
                                    <option value="AP">Amapá</option>
                                    <option value="RR">Roraima</option>
                                    <option value="DF">Distrito Federal</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="origem_cidade" class="form-label">Cidade de Origem</label>
                                <input type="text" class="form-control" id="origem_cidade" name="origem_cidade" placeholder="Nome da cidade" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="destino_estado" class="form-label">Estado de Destino</label>
                                <select class="form-select" id="destino_estado" name="destino_estado" required>
                                    <option value="">Selecione o estado</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="PR">Paraná</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="BA">Bahia</option>
                                    <option value="GO">Goiás</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="ES">Espírito Santo</option>
                                    <option value="CE">Ceará</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PA">Pará</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="TO">Tocantins</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="PB">Paraíba</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="AC">Acre</option>
                                    <option value="AP">Amapá</option>
                                    <option value="RR">Roraima</option>
                                    <option value="DF">Distrito Federal</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="destino_cidade" class="form-label">Cidade de Destino</label>
                                <input type="text" class="form-control" id="destino_cidade" name="destino_cidade" placeholder="Nome da cidade" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-info mb-3">Dados da Carga</h6>
                            
                            <div class="mb-3">
                                <label for="valor_total" class="form-label">Valor Total</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valor_total" name="valor_total" step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="peso_total" class="form-label">Peso Total (kg)</label>
                                <input type="number" class="form-control" id="peso_total" name="peso_total" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-warning mb-3">Observações</h6>
                            
                            <div class="mb-3">
                                <label for="observacoes_cte" class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes_cte" name="observacoes" rows="4" placeholder="Observações sobre o transporte..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="emitirCTe()">
                    <i class="fas fa-paper-plane"></i> Emitir CT-e
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Emitir MDF-e -->
<div class="modal fade" id="emitirMDFeModal" tabindex="-1" aria-labelledby="emitirMDFeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emitirMDFeModalLabel">
                    <i class="fas fa-file-alt text-info"></i> Emitir Manifesto de Documentos Fiscais (MDF-e)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="mdfeForm">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Dados do MDF-e</h6>
                            
                            <div class="mb-3">
                                <label for="numero_mdfe" class="form-label">Número do MDF-e</label>
                                <input type="text" class="form-control" id="numero_mdfe" name="numero_mdfe" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="serie_mdfe" class="form-label">Série</label>
                                <input type="text" class="form-control" id="serie_mdfe" name="serie_mdfe" value="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="data_emissao_mdfe" class="form-label">Data de Emissão</label>
                                <input type="date" class="form-control" id="data_emissao_mdfe" name="data_emissao" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tipo_transporte" class="form-label">Tipo de Transporte</label>
                                <select class="form-select" id="tipo_transporte" name="tipo_transporte" required>
                                    <option value="rodoviario">Rodoviário</option>
                                    <option value="aereo">Aéreo</option>
                                    <option value="aquaviario">Aquaviário</option>
                                    <option value="ferroviario">Ferroviário</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Motorista e Veículo</h6>
                            
                            <div class="mb-3">
                                <label for="motorista_id" class="form-label">Motorista</label>
                                <select class="form-select" id="motorista_id" name="motorista_id" required>
                                    <option value="">Selecione o motorista</option>
                                    <!-- Será preenchido via JavaScript -->
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="veiculo_id" class="form-label">Veículo de Tração</label>
                                <select class="form-select" id="veiculo_id" name="veiculo_id" required>
                                    <option value="">Selecione o veículo</option>
                                    <!-- Será preenchido via JavaScript -->
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="peso_total_carga" class="form-label">Peso Total da Carga (kg)</label>
                                <input type="number" class="form-control" id="peso_total_carga" name="peso_total_carga" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="qtd_total_volumes" class="form-label">Quantidade Total de Volumes</label>
                                <input type="number" class="form-control" id="qtd_total_volumes" name="qtd_total_volumes" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-warning mb-3">CT-e Vinculados</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Selecionar CT-e para vincular</label>
                                <div id="cteDisponiveis" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <p>Nenhum CT-e disponível para vinculação</p>
                                        <small>Os CT-e devem estar com status 'autorizado' para serem vinculados</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="observacoes_mdfe" class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes_mdfe" name="observacoes" rows="3" placeholder="Observações sobre o manifesto..."></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-info" onclick="emitirMDFe()">
                    <i class="fas fa-paper-plane"></i> Emitir MDF-e
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirmação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Tem certeza que deseja realizar esta ação?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Carregamento -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="mt-2 mb-0">Processando...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Erro -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="errorModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Erro
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="errorMessage">Ocorreu um erro inesperado.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Sucesso -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel">
                    <i class="fas fa-check-circle"></i> Sucesso
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="successMessage">Operação realizada com sucesso!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
// Configurar data atual nos campos de data
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('data_emissao').value = today;
    document.getElementById('data_emissao_mdfe').value = today;
});

// Funções para mostrar/ocultar modais
function showLoading() {
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();
}

function hideLoading() {
    const loadingModal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
    if (loadingModal) {
        loadingModal.hide();
    }
}

function showError(message) {
    document.getElementById('errorMessage').textContent = message;
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    errorModal.show();
}

function showSuccess(message) {
    document.getElementById('successMessage').textContent = message;
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
}

function showConfirm(message, callback) {
    document.getElementById('confirmMessage').textContent = message;
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    
    document.getElementById('confirmAction').onclick = function() {
        confirmModal.hide();
        if (callback) callback();
    };
    
    confirmModal.show();
}
</script>
