<?php
/**
 * Modal wizard "Novo MDF-e" — incluído por mdfe.php.
 * Variáveis: $is_modern (bool). $ufs definido abaixo se ausente.
 */
if (!isset($ufs) || !is_array($ufs)) {
    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
}
?>
    <!-- Wizard Novo MDF-e -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="novoMDFEWizardModal" tabindex="-1" aria-labelledby="novoMDFEWizardLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novoMDFEWizardLabel"><i class="fas fa-plus-circle"></i> Novo MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="route-modal-tab-bar" role="tablist" aria-label="Seções do formulário de MDF-e">
                        <button type="button" class="route-tab-btn is-active" data-route-tab="1">Dados do MDF-e</button>
                        <button type="button" class="route-tab-btn" data-route-tab="2">Rodoviário</button>
                        <button type="button" class="route-tab-btn" data-route-tab="3">Documentos</button>
                        <button type="button" class="route-tab-btn" data-route-tab="4">Seguros</button>
                        <button type="button" class="route-tab-btn" data-route-tab="5">Produto predominante</button>
                        <button type="button" class="route-tab-btn" data-route-tab="6">Totalizadores</button>
                    </div>

                    <form id="novoMDFEWizardForm">
                        <div>
                            <div class="route-modal-tab-pane is-active" data-route-tab="1">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_data_emissao" class="form-label">Data de emissão</label>
                                        <input type="date" class="form-control" id="novo_mdfe_data_emissao" name="data_emissao" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_tipo_emitente" class="form-label">Tipo de emitente</label>
                                        <select class="form-select" id="novo_mdfe_tipo_emitente" name="tipo_emitente" required>
                                            <option value="">Selecione</option>
                                            <option value="1">1 - Prestador de serviço de transporte</option>
                                            <option value="2">2 - Transportador de carga própria</option>
                                            <option value="3">3 - Prestador de serviço que emitirá CT-e globalizado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_tipo_transportador" class="form-label">Tipo de transportador</label>
                                        <select class="form-select" id="novo_mdfe_tipo_transportador" name="tipo_transportador" required>
                                            <option value="">Selecione</option>
                                            <option value="1">1 - ETC (Empresa de Transporte de Cargas)</option>
                                            <option value="2">2 - TAC (Transportador Autônomo de Cargas)</option>
                                            <option value="3">3 - CTC (Cooperativa de Transporte de Cargas)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="novo_mdfe_previsao_inicio" class="form-label">Previsão de início da viagem</label>
                                        <input type="datetime-local" class="form-control" id="novo_mdfe_previsao_inicio" name="previsao_inicio_viagem">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_uf_carga" class="form-label">UF de carga</label>
                                        <select class="form-select" id="novo_mdfe_uf_carga" name="uf_carga" required>
                                            <option value="">Selecione o estado</option>
                                            <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_municipio_carga" class="form-label">Município de carga</label>
                                        <select class="form-select" id="novo_mdfe_municipio_carga" name="municipio_carga" required disabled aria-describedby="hint_mdfe_mun_carga">
                                            <option value="">Selecione primeiro a UF</option>
                                        </select>
                                        <small id="hint_mdfe_mun_carga" class="form-text">Municípios carregados pela API ao selecionar a UF (mesma base das rotas).</small>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="novo_mdfe_uf_descarga" class="form-label">UF de descarga</label>
                                        <select class="form-select" id="novo_mdfe_uf_descarga" name="uf_descarga" required>
                                            <option value="">Selecione o estado</option>
                                            <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_municipio_descarga" class="form-label">Município de descarga</label>
                                        <select class="form-select" id="novo_mdfe_municipio_descarga" name="municipio_descarga" required disabled aria-describedby="hint_mdfe_mun_descarga">
                                            <option value="">Selecione primeiro a UF</option>
                                        </select>
                                        <small id="hint_mdfe_mun_descarga" class="form-text">Municípios carregados pela API ao selecionar a UF.</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="novo_mdfe_uf_percurso" class="form-label">UF de percurso</label>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" id="novo_mdfe_uf_percurso">
                                                <option value="">Adicionar UF de percurso</option>
                                                <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                            </select>
                                            <button type="button" class="btn btn-outline-primary" onclick="adicionarUfPercursoNovoMDFE()">Adicionar</button>
                                        </div>
                                        <small class="text-muted">Adicione todos os estados por onde o veículo passará.</small>
                                    </div>

                                    <div class="col-12">
                                        <div id="novo_mdfe_ufs_percurso_lista" class="d-flex flex-wrap gap-2"></div>
                                    </div>
                                    <input type="hidden" id="novo_mdfe_ufs_percurso_hidden" name="ufs_percurso" value="">

                                    <div class="col-12">
                                        <label for="novo_mdfe_info_fisco" class="form-label">Informações de interesse do Fisco</label>
                                        <textarea class="form-control" id="novo_mdfe_info_fisco" name="info_fisco" rows="2" placeholder="Informações adicionais para o Fisco"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label for="novo_mdfe_info_contribuinte" class="form-label">Informações de interesse do contribuinte</label>
                                        <textarea class="form-control" id="novo_mdfe_info_contribuinte" name="info_contribuinte" rows="2" placeholder="Informações adicionais para o contribuinte"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="route-modal-tab-pane" data-route-tab="2">
                                <div class="mdfe-subtab-bar" role="tablist" aria-label="Subseções da aba Rodoviário">
                                    <button type="button" class="mdfe-subtab-btn is-active" data-mdfe-rodo-tab="1">Veículo de tração</button>
                                    <button type="button" class="mdfe-subtab-btn" data-mdfe-rodo-tab="2">CIOT</button>
                                    <button type="button" class="mdfe-subtab-btn" data-mdfe-rodo-tab="3">Reboque</button>
                                    <button type="button" class="mdfe-subtab-btn" data-mdfe-rodo-tab="4">Vale pedágio</button>
                                    <button type="button" class="mdfe-subtab-btn" data-mdfe-rodo-tab="5">Contratantes</button>
                                    <button type="button" class="mdfe-subtab-btn" data-mdfe-rodo-tab="6">Pagamento de frete</button>
                                </div>

                                <div class="mdfe-subtab-pane is-active" data-mdfe-rodo-tab="1">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="rod_rntrc" class="form-label">RNTRC</label>
                                            <input type="text" class="form-control" id="rod_rntrc" name="rod_rntrc" maxlength="20">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="rod_cod_agendamento_porto" class="form-label">Código de agendamento no porto</label>
                                            <input type="text" class="form-control" id="rod_cod_agendamento_porto" name="rod_cod_agendamento_porto" maxlength="60">
                                        </div>
                                    </div>

                                    <hr>
                                    <h6 class="mb-2">Dados do veículo de tração</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="rod_cod_interno_veiculo" class="form-label">Código interno do veículo</label>
                                            <input type="text" class="form-control" id="rod_cod_interno_veiculo" name="rod_cod_interno_veiculo">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="rod_renavam" class="form-label">RENAVAM</label>
                                            <input type="text" class="form-control" id="rod_renavam" name="rod_renavam" maxlength="20">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="rod_placa" class="form-label">Placa do veículo *</label>
                                            <input type="text" class="form-control" id="rod_placa" name="rod_placa" required maxlength="10">
                                        </div>

                                        <div class="col-md-3">
                                            <label for="rod_tara_kg" class="form-label">Tara em KG *</label>
                                            <input type="number" class="form-control" id="rod_tara_kg" name="rod_tara_kg" min="1" step="0.001" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="rod_capacidade_kg" class="form-label">Capacidade em KG</label>
                                            <input type="number" class="form-control" id="rod_capacidade_kg" name="rod_capacidade_kg" min="0" step="0.001">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="rod_capacidade_m3" class="form-label">Capacidade em M³</label>
                                            <input type="number" class="form-control" id="rod_capacidade_m3" name="rod_capacidade_m3" min="0" step="0.001">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="rod_uf_licenciamento" class="form-label">UF licenciamento</label>
                                            <select class="form-select" id="rod_uf_licenciamento" name="rod_uf_licenciamento">
                                                <option value="">Selecione</option>
                                                <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="rod_tipo_rodado" class="form-label">Tipo de rodado *</label>
                                            <input type="text" class="form-control" id="rod_tipo_rodado" name="rod_tipo_rodado" placeholder="Ex: 4x2, 6x4" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="rod_tipo_carroceria" class="form-label">Tipo de carroceria *</label>
                                            <input type="text" class="form-control" id="rod_tipo_carroceria" name="rod_tipo_carroceria" placeholder="Ex: aberta, fechada, baú" required>
                                        </div>
                                    </div>

                                    <hr>
                                    <h6 class="mb-2">Veículo pertence à empresa emitente do MDF-e?</h6>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rod_veiculo_empresa_emitente" id="rod_veiculo_emitente_sim" value="sim" checked>
                                            <label class="form-check-label" for="rod_veiculo_emitente_sim">Sim</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rod_veiculo_empresa_emitente" id="rod_veiculo_emitente_nao" value="nao">
                                            <label class="form-check-label" for="rod_veiculo_emitente_nao">Não</label>
                                        </div>
                                    </div>

                                    <div id="mdfeProprietarioWrap" class="mdfe-proprietario-wrap mt-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Proprietário do veículo</h6>
                                            <button type="button" class="btn btn-outline-secondary btn-sm">Pesquisar proprietário</button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label for="rod_prop_tipo_pessoa" class="form-label">Tipo de pessoa</label>
                                                <select class="form-select" id="rod_prop_tipo_pessoa" name="rod_prop_tipo_pessoa">
                                                    <option value="">Selecione</option>
                                                    <option value="juridica">Pessoa jurídica</option>
                                                    <option value="fisica">Pessoa física</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_prop_pessoa_juridica" class="form-label">Pessoa jurídica</label>
                                                <select class="form-select" id="rod_prop_pessoa_juridica" name="rod_prop_pessoa_juridica">
                                                    <option value="">Selecione</option>
                                                    <option value="sim">Sim</option>
                                                    <option value="nao">Não</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_prop_pessoa_fisica" class="form-label">Pessoa física</label>
                                                <select class="form-select" id="rod_prop_pessoa_fisica" name="rod_prop_pessoa_fisica">
                                                    <option value="">Selecione</option>
                                                    <option value="sim">Sim</option>
                                                    <option value="nao">Não</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label for="rod_prop_cnpj" class="form-label">CNPJ</label>
                                                <input type="text" class="form-control" id="rod_prop_cnpj" name="rod_prop_cnpj" maxlength="18">
                                            </div>
                                            <div class="col-md-8">
                                                <label for="rod_prop_razao_social" class="form-label">Razão social</label>
                                                <input type="text" class="form-control" id="rod_prop_razao_social" name="rod_prop_razao_social" maxlength="255">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="rod_prop_rntrc" class="form-label">RNTRC</label>
                                                <input type="text" class="form-control" id="rod_prop_rntrc" name="rod_prop_rntrc" maxlength="20">
                                            </div>
                                            <div class="col-md-2">
                                                <label for="rod_prop_uf" class="form-label">UF</label>
                                                <select class="form-select" id="rod_prop_uf" name="rod_prop_uf">
                                                    <option value="">UF</option>
                                                    <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_prop_ie" class="form-label">Inscrição estadual</label>
                                                <input type="text" class="form-control" id="rod_prop_ie" name="rod_prop_ie" maxlength="30">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="rod_prop_contribuinte" class="form-label">Contribuinte</label>
                                                <select class="form-select" id="rod_prop_contribuinte" name="rod_prop_contribuinte">
                                                    <option value="">Selecione</option>
                                                    <option value="sim">Sim</option>
                                                    <option value="nao">Não</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_prop_tipo_proprietario" class="form-label">Tipo de proprietário</label>
                                                <select class="form-select" id="rod_prop_tipo_proprietario" name="rod_prop_tipo_proprietario">
                                                    <option value="">Selecione</option>
                                                    <option value="0">0 - TAC agregado</option>
                                                    <option value="1">1 - TAC independente</option>
                                                    <option value="2">2 - Outros</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Condutores A</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarCondutorNovoMDFE()">
                                            <i class="fas fa-plus"></i> Novo condutor
                                        </button>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-7">
                                            <label for="rod_condutor_nome" class="form-label">Nome</label>
                                            <input type="text" class="form-control" id="rod_condutor_nome" placeholder="Nome completo do condutor">
                                        </div>
                                        <div class="col-md-5">
                                            <label for="rod_condutor_cpf" class="form-label">CPF</label>
                                            <input type="text" class="form-control" id="rod_condutor_cpf" placeholder="000.000.000-00">
                                        </div>
                                    </div>
                                    <input type="hidden" id="rod_condutores_json" name="rod_condutores_json" value="[]">
                                    <div class="table-responsive">
                                        <table class="mdfe-condutores-table">
                                            <thead>
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>CPF</th>
                                                    <th style="width: 70px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rodCondutoresTabelaBody">
                                                <tr><td colspan="3" class="text-muted">Nenhum condutor adicionado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mdfe-subtab-pane" data-mdfe-rodo-tab="2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">CIOT</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormCiotNovoMDFE()">
                                            <i class="fas fa-plus"></i> Adicionar CIOT
                                        </button>
                                    </div>
                                    <div id="rodCiotFormWrap" class="mdfe-inline-form is-hidden">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="rod_ciot_numero" class="form-label">Número CIOT</label>
                                                <input type="text" class="form-control" id="rod_ciot_numero">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="rod_ciot_valor_frete" class="form-label">Valor frete</label>
                                                <input type="number" class="form-control" id="rod_ciot_valor_frete" min="0" step="0.01">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="rod_ciot_cpf_cnpj_tac" class="form-label">CPF/CNPJ TAC</label>
                                                <input type="text" class="form-control" id="rod_ciot_cpf_cnpj_tac">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="rod_ciot_ipef" class="form-label">IPEF (Pamcard, Repom...)</label>
                                                <input type="text" class="form-control" id="rod_ciot_ipef">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 mt-3">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarCiotNovoMDFE()">Cancelar</button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="gravarCiotNovoMDFE()">Gravar</button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="rod_ciot_json" name="rod_ciot_json" value="[]">
                                    <div class="table-responsive mt-3">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>Número CIOT</th>
                                                    <th>Valor frete</th>
                                                    <th>CPF/CNPJ TAC</th>
                                                    <th>IPEF</th>
                                                    <th style="width: 92px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rodCiotTabelaBody">
                                                <tr><td colspan="5" class="text-muted">Nenhum CIOT adicionado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="mdfe-subtab-pane" data-mdfe-rodo-tab="3">
                                    <div class="alert alert-info mb-0">Submenu "Reboque" será implementado na próxima fase.</div>
                                </div>
                                <div class="mdfe-subtab-pane" data-mdfe-rodo-tab="4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Vale Pedágio</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormValePedagioNovoMDFE()">
                                            <i class="fas fa-plus"></i> Adicionar vale pedágio
                                        </button>
                                    </div>
                                    <div id="rodValePedagioFormWrap" class="mdfe-inline-form is-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Incluir vale pedágio</h6>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pesquisarValePedagioNovoMDFE()">
                                                <i class="fas fa-search"></i> Pesquisar vale pedágio
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label for="rod_vp_eixos" class="form-label">Eixos do veículo</label>
                                                <select class="form-select" id="rod_vp_eixos">
                                                    <option value="">Selecione</option>
                                                    <?php for ($i = 1; $i <= 12; $i++) { echo '<option value="' . $i . '">' . $i . ' eixos</option>'; } ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_vp_valor" class="form-label">Valor *</label>
                                                <div class="d-flex gap-2">
                                                    <input type="number" class="form-control" id="rod_vp_valor" min="0" step="0.01" placeholder="0,00">
                                                    <button type="button" class="btn btn-outline-primary" onclick="focarValorValePedagioNovoMDFE()">Valor pedágio</button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_vp_tipo" class="form-label">Tipo do vale pedágio</label>
                                                <input type="text" class="form-control" id="rod_vp_tipo" placeholder="Tipo/classificação">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_vp_cnpj_fornecedor" class="form-label">CNPJ da empresa fornecedora *</label>
                                                <input type="text" class="form-control" id="rod_vp_cnpj_fornecedor" placeholder="00.000.000/0000-00">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_vp_num_comprovante" class="form-label">Número do comprovante</label>
                                                <input type="text" class="form-control" id="rod_vp_num_comprovante">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_vp_resp_pagamento" class="form-label">CPF/CNPJ do responsável pelo pagamento *</label>
                                                <input type="text" class="form-control" id="rod_vp_resp_pagamento" placeholder="CPF ou CNPJ">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 mt-3">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarValePedagioNovoMDFE()">Cancelar</button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="gravarValePedagioNovoMDFE()">Gravar</button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="rod_vales_pedagio_json" name="rod_vales_pedagio_json" value="[]">
                                    <div class="table-responsive mt-3">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>Eixos</th>
                                                    <th>Fornecedor</th>
                                                    <th>Comprovante</th>
                                                    <th>Tipo</th>
                                                    <th>Valor</th>
                                                    <th>Responsável pagamento</th>
                                                    <th style="width: 92px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rodValePedagioTabelaBody">
                                                <tr><td colspan="7" class="text-muted">Nenhum vale pedágio adicionado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="mdfe-subtab-pane" data-mdfe-rodo-tab="5">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Contratantes</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormContratanteNovoMDFE()">
                                            <i class="fas fa-plus"></i> Adicionar contratante
                                        </button>
                                    </div>
                                    <div id="rodContratanteFormWrap" class="mdfe-inline-form is-hidden">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Identificação do contratante</h6>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pesquisarClienteContratanteNovoMDFE()">
                                                <i class="fas fa-search"></i> Pesquisar cliente
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label d-block">Tipo de pessoa</label>
                                                <div class="d-flex gap-3 flex-wrap">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rod_contratante_tipo_pessoa" id="rod_contratante_tipo_juridica" value="juridica" checked>
                                                        <label class="form-check-label" for="rod_contratante_tipo_juridica">Pessoa jurídica</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rod_contratante_tipo_pessoa" id="rod_contratante_tipo_fisica" value="fisica">
                                                        <label class="form-check-label" for="rod_contratante_tipo_fisica">Pessoa física</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rod_contratante_tipo_pessoa" id="rod_contratante_tipo_estrangeiro" value="estrangeiro">
                                                        <label class="form-check-label" for="rod_contratante_tipo_estrangeiro">Estrangeiro</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_contratante_doc" class="form-label" id="rodContratanteDocLabel">CNPJ *</label>
                                                <input type="text" class="form-control" id="rod_contratante_doc" placeholder="Documento do contratante">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_contratante_razao_social" class="form-label">Razão social / nome</label>
                                                <input type="text" class="form-control" id="rod_contratante_razao_social">
                                            </div>
                                            <div class="col-md-2">
                                                <label for="rod_contratante_numero_contrato" class="form-label">Número do contrato</label>
                                                <input type="text" class="form-control" id="rod_contratante_numero_contrato">
                                            </div>
                                            <div class="col-md-2">
                                                <label for="rod_contratante_valor" class="form-label">Valor</label>
                                                <input type="number" class="form-control" id="rod_contratante_valor" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 mt-3">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarContratanteNovoMDFE()">Cancelar</button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="gravarContratanteNovoMDFE()">Gravar</button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="rod_contratantes_json" name="rod_contratantes_json" value="[]">
                                    <div class="table-responsive mt-3">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>Tipo pessoa</th>
                                                    <th>Documento</th>
                                                    <th>Razão social / nome</th>
                                                    <th>Nº contrato</th>
                                                    <th>Valor</th>
                                                    <th style="width: 92px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rodContratantesTabelaBody">
                                                <tr><td colspan="6" class="text-muted">Nenhum contratante adicionado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="mdfe-subtab-pane" data-mdfe-rodo-tab="6">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h6 class="mb-0">Pagamento de Frete <span class="text-danger">*</span></h6>
                                            <span class="mdfe-tooltip-note" title="Atenção: A partir de outubro de 2025, o MDF-e passou a seguir a Nota Técnica 2025.001, com novos campos obrigatórios. Se o transporte for de carga lotação (apenas um DF-e vinculado), preencha a aba Pagamento de Frete e informe o NCM do produto predominante. Confirme todas informações obrigatórias com seu contador.">
                                                <i class="fas fa-circle-info"></i> Passe o mouse para orientações
                                            </span>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormPagamentoFreteNovoMDFE()">
                                            <i class="fas fa-plus"></i> Adicionar pagamento de frete
                                        </button>
                                    </div>
                                    <div id="rodPagamentoFreteFormWrap" class="mdfe-inline-form is-hidden">
                                        <h6 class="mb-2">Incluir pagamento de frete</h6>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label mb-0">Identificação do contratante (pagador)</label>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pesquisarClientePagamentoNovoMDFE()">
                                                    <i class="fas fa-search"></i> Pesquisar cliente
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copiarDadosEmitentePagamentoNovoMDFE()">
                                                    <i class="fas fa-copy"></i> Copiar dados do emitente
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <div class="d-flex gap-3 flex-wrap">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rod_pag_tipo_pessoa" id="rod_pag_tipo_juridica" value="juridica" checked>
                                                        <label class="form-check-label" for="rod_pag_tipo_juridica">Pessoa jurídica</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rod_pag_tipo_pessoa" id="rod_pag_tipo_fisica" value="fisica">
                                                        <label class="form-check-label" for="rod_pag_tipo_fisica">Pessoa física</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rod_pag_tipo_pessoa" id="rod_pag_tipo_estrangeiro" value="estrangeiro">
                                                        <label class="form-check-label" for="rod_pag_tipo_estrangeiro">Estrangeiro</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_doc" class="form-label" id="rodPagDocLabel">CNPJ *</label>
                                                <input type="text" class="form-control" id="rod_pag_doc" placeholder="Documento do pagador">
                                            </div>
                                            <div class="col-md-8">
                                                <label for="rod_pag_razao_social" class="form-label">Razão social / nome</label>
                                                <input type="text" class="form-control" id="rod_pag_razao_social">
                                            </div>
                                        </div>

                                        <hr>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Componentes do pagamento</h6>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarComponentePagamentoNovoMDFE()">
                                                <i class="fas fa-plus"></i> Novo componente
                                            </button>
                                        </div>
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-8">
                                                <label for="rod_pag_comp_tipo" class="form-label">Tipo do componente</label>
                                                <select class="form-select" id="rod_pag_comp_tipo">
                                                    <option value="">Selecione</option>
                                                    <option value="01">01 - Vale Pedágio</option>
                                                    <option value="02">02 - Impostos, taxas e contribuições</option>
                                                    <option value="03">03 - Despesas (bancárias, meios de pagamento, outras)</option>
                                                    <option value="04">04 - Frete</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_comp_valor" class="form-label">Valor</label>
                                                <input type="number" class="form-control" id="rod_pag_comp_valor" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="mdfe-simple-table">
                                                <thead>
                                                    <tr>
                                                        <th>Tipo</th>
                                                        <th>Valor</th>
                                                        <th style="width: 70px;">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="rodPagamentoCompTabelaBody">
                                                    <tr><td colspan="3" class="text-muted">Nenhum componente adicionado.</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mdfe-page-hint">
                                            <span id="rodPagCompPageInfo">1 de 1</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="rod_pag_comp_per_page" class="mb-0">Itens por página</label>
                                                <select id="rod_pag_comp_per_page" class="form-select form-select-sm" style="width: 74px;" onchange="renderComponentesPagamentoNovoMDFE(1)">
                                                    <option value="5" selected>5</option>
                                                    <option value="10">10</option>
                                                    <option value="20">20</option>
                                                </select>
                                            </div>
                                        </div>

                                        <hr>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="rod_pag_considerar_componentes" checked>
                                                    <label class="form-check-label" for="rod_pag_considerar_componentes">Considerar componentes do valor do pagamento</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="rod_pag_valor_total_contrato" class="form-label">Valor total do contrato <span class="text-danger">(x obrigatório)</span></label>
                                                <input type="number" class="form-control" id="rod_pag_valor_total_contrato" min="0" step="0.01" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_ind_forma_pagamento" class="form-label">Indicador da forma de pagamento</label>
                                                <select class="form-select" id="rod_pag_ind_forma_pagamento">
                                                    <option value="">Selecione</option>
                                                    <option value="0">0 - Pagamento à vista</option>
                                                    <option value="1">1 - Pagamento a prazo</option>
                                                    <option value="2">2 - Outros</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_forma_financiamento" class="form-label">Forma de financiamento</label>
                                                <input type="text" class="form-control" id="rod_pag_forma_financiamento">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_alto_desempenho" class="form-label">Transporte de alto desempenho</label>
                                                <select class="form-select" id="rod_pag_alto_desempenho" title="Indique se se aplica transporte de alto desempenho.">
                                                    <option value="">Selecione</option>
                                                    <option value="sim">Sim</option>
                                                    <option value="nao">Não</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_tipo_pagamento" class="form-label">Tipo de pagamento</label>
                                                <select class="form-select" id="rod_pag_tipo_pagamento">
                                                    <option value="">Selecione</option>
                                                    <option value="avista">À vista</option>
                                                    <option value="antecipado">Antecipado</option>
                                                    <option value="cartao_frete">Cartão frete</option>
                                                    <option value="outros">Outros</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="rod_pag_indicador_status" class="form-label">Indicador do pagamento</label>
                                                <select class="form-select" id="rod_pag_indicador_status">
                                                    <option value="">Selecione</option>
                                                    <option value="pago">Pago</option>
                                                    <option value="a_pagar">A pagar</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2 mt-3">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarPagamentoFreteNovoMDFE()">Cancelar</button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="gravarPagamentoFreteNovoMDFE()">Gravar</button>
                                        </div>
                                    </div>
                                    <input type="hidden" id="rod_pagamentos_frete_json" name="rod_pagamentos_frete_json" value="[]">
                                    <div class="table-responsive mt-3">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>Pagador</th>
                                                    <th>Documento</th>
                                                    <th>Componentes</th>
                                                    <th>Valor total contrato</th>
                                                    <th style="width: 92px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rodPagamentosFreteTabelaBody">
                                                <tr><td colspan="5" class="text-muted">Nenhum pagamento de frete adicionado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="route-modal-tab-pane" data-route-tab="3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Documentos (NF-e / CT-e)</h6>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormDocumentoNovoMDFE('adicionar')">
                                            <i class="fas fa-plus"></i> Adicionar nova NF-e
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormDocumentoNovoMDFE('criar')">
                                            <i class="fas fa-file-circle-plus"></i> Criar NF-e
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="mostrarFormDocumentoNovoMDFE('buscar')">
                                            <i class="fas fa-search"></i> Buscar NF-e já emitida
                                        </button>
                                    </div>
                                </div>
                                <div id="docFormWrapNovoMDFE" class="mdfe-inline-form is-hidden">
                                    <h6 class="mb-2">Incluir documento fiscal</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="doc_municipio_descarregamento" class="form-label">Município de descarregamento</label>
                                            <select class="form-select" id="doc_municipio_descarregamento">
                                                <option value="">Selecione um município</option>
                                            </select>
                                            <small class="text-muted">Baseado nos municípios do frete final.</small>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="doc_tipo_acao" class="form-label">Ação</label>
                                            <select class="form-select" id="doc_tipo_acao">
                                                <option value="adicionar">Adicionar nova NF-e</option>
                                                <option value="criar">Criar NF-e</option>
                                                <option value="buscar">Buscar NF-e já emitida</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="doc_chave_nfe" class="form-label">Chave da NF-e</label>
                                            <input type="text" class="form-control" id="doc_chave_nfe" maxlength="44" placeholder="44 dígitos">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="doc_numero_nfe" class="form-label">Número NF-e</label>
                                            <input type="text" class="form-control" id="doc_numero_nfe">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="doc_serie_nfe" class="form-label">Série NF-e</label>
                                            <input type="text" class="form-control" id="doc_serie_nfe">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="doc_valor_nfe" class="form-label">Valor NF-e</label>
                                            <input type="number" class="form-control" id="doc_valor_nfe" min="0" step="0.01">
                                        </div>
                                    </div>
                                    <div id="docCteWrapNovoMDFE" class="row g-3 mt-1 mdfe-inline-form is-hidden">
                                        <div class="col-md-6">
                                            <label for="doc_chave_cte" class="form-label">CT-e (chave de acesso)</label>
                                            <input type="text" class="form-control" id="doc_chave_cte" maxlength="44" placeholder="Exibido quando tipo emitente = 1">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="doc_numero_cte" class="form-label">Número CT-e</label>
                                            <input type="text" class="form-control" id="doc_numero_cte">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarDocumentoNovoMDFE()">Cancelar</button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="gravarDocumentoNovoMDFE()">Gravar</button>
                                    </div>
                                </div>
                                <input type="hidden" id="doc_documentos_json" name="doc_documentos_json" value="[]">
                                <div class="table-responsive mt-3">
                                    <table class="mdfe-simple-table">
                                        <thead>
                                            <tr>
                                                <th>Município descarregamento</th>
                                                <th>Ação</th>
                                                <th>NF-e</th>
                                                <th>Valor</th>
                                                <th>CT-e</th>
                                                <th style="width: 92px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="docTabelaBodyNovoMDFE">
                                            <tr><td colspan="6" class="text-muted">Nenhum documento fiscal adicionado.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="route-modal-tab-pane" data-route-tab="4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Seguros</h6>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormSeguroNovoMDFE()">
                                        <i class="fas fa-plus"></i> Adicionar seguro
                                    </button>
                                </div>
                                <div id="seguroFormWrapNovoMDFE" class="mdfe-inline-form is-hidden">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Incluir seguro</h6>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pesquisarSeguroNovoMDFE()">
                                            <i class="fas fa-search"></i> Pesquisar seguro
                                        </button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="seg_responsavel" class="form-label">Responsável pelo seguro</label>
                                            <input type="text" class="form-control" id="seg_responsavel">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="seg_cpf_cnpj_responsavel" class="form-label">CPF/CNPJ</label>
                                            <input type="text" class="form-control" id="seg_cpf_cnpj_responsavel">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="seg_emitente" class="form-label">Emitente</label>
                                            <input type="text" class="form-control" id="seg_emitente">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="seg_cnpj_seguradora" class="form-label">CNPJ da seguradora</label>
                                            <input type="text" class="form-control" id="seg_cnpj_seguradora">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="seg_nome_seguradora" class="form-label">Nome da seguradora</label>
                                            <input type="text" class="form-control" id="seg_nome_seguradora">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="seg_tomador_contratante" class="form-label">Tomador do Serviço / Contratante</label>
                                            <input type="text" class="form-control" id="seg_tomador_contratante">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="seg_numero_apolice" class="form-label">Número da apólice</label>
                                            <input type="text" class="form-control" id="seg_numero_apolice">
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Averbações</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarAverbacaoSeguroNovoMDFE()">
                                            <i class="fas fa-plus"></i> Nova averbação
                                        </button>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label for="seg_numero_averbacao" class="form-label">Número da averbação</label>
                                            <input type="text" class="form-control" id="seg_numero_averbacao">
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>Número da averbação</th>
                                                    <th style="width: 70px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="segAverbacoesTabelaBodyNovoMDFE">
                                                <tr><td colspan="2" class="text-muted">Nenhuma averbação adicionada.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarSeguroNovoMDFE()">Cancelar</button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="gravarSeguroNovoMDFE()">Gravar</button>
                                    </div>
                                </div>
                                <input type="hidden" id="seg_seguros_json" name="seg_seguros_json" value="[]">
                                <div class="table-responsive mt-3">
                                    <table class="mdfe-simple-table">
                                        <thead>
                                            <tr>
                                                <th>Responsável</th>
                                                <th>Seguradora</th>
                                                <th>Nº apólice</th>
                                                <th>Averbações</th>
                                                <th style="width: 92px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="segTabelaBodyNovoMDFE">
                                            <tr><td colspan="5" class="text-muted">Nenhum seguro adicionado.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="route-modal-tab-pane" data-route-tab="5">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Produtos Predominantes</h6>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="mostrarFormProdutoPredNovoMDFE()">
                                        <i class="fas fa-plus"></i> Adicionar produto predominante
                                    </button>
                                </div>
                                <div id="prodFormWrapNovoMDFE" class="mdfe-inline-form is-hidden">
                                    <h6 class="mb-2">Produto predominante</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="prod_tipo_carga" class="form-label">Tipo de carga</label>
                                            <input type="text" class="form-control" id="prod_tipo_carga">
                                        </div>
                                        <div class="col-md-8">
                                            <label for="prod_descricao" class="form-label">Descrição do produto</label>
                                            <input type="text" class="form-control" id="prod_descricao">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="prod_gtin" class="form-label">GTIN</label>
                                            <input type="text" class="form-control" id="prod_gtin">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="prod_ncm" class="form-label">NCM</label>
                                            <input type="text" class="form-control" id="prod_ncm">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="prod_carga_lotacao" class="form-label">Carga lotação</label>
                                            <select class="form-select" id="prod_carga_lotacao">
                                                <option value="">Selecione</option>
                                                <option value="sim">Sim</option>
                                                <option value="nao">Não</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="prodLocalizacoesLotacaoWrap" class="is-hidden">
                                    <hr>
                                    <h6 class="mb-2">Localizações</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="prod_local_carregamento_cep" class="form-label">Localização de carregamento - CEP</label>
                                            <input type="text" class="form-control" id="prod_local_carregamento_cep" placeholder="00000-000">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="prod_local_descarregamento_cep" class="form-label">Localização de descarregamento - CEP</label>
                                            <input type="text" class="form-control" id="prod_local_descarregamento_cep" placeholder="00000-000">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="prod_cep_descarregamento" class="form-label">CEP de descarregamento</label>
                                            <input type="text" class="form-control" id="prod_cep_descarregamento" placeholder="00000-000">
                                        </div>
                                    </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelarProdutoPredNovoMDFE()">Cancelar</button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="gravarProdutoPredNovoMDFE()">Gravar</button>
                                    </div>
                                </div>
                                <input type="hidden" id="prod_predominantes_json" name="prod_predominantes_json" value="[]">
                                <div class="table-responsive mt-3">
                                    <table class="mdfe-simple-table">
                                        <thead>
                                            <tr>
                                                <th>Tipo de carga</th>
                                                <th>Descrição</th>
                                                <th>NCM</th>
                                                <th>Carga lotação</th>
                                                <th>CEP carregamento</th>
                                                <th>CEP descarregamento</th>
                                                <th>CEP de descarregamento</th>
                                                <th style="width: 92px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="prodTabelaBodyNovoMDFE">
                                            <tr><td colspan="8" class="text-muted">Nenhum produto predominante adicionado.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="route-modal-tab-pane" data-route-tab="6">
                                <div class="mdfe-subtab-bar" role="tablist" aria-label="Subseções da aba Totalizadores">
                                    <button type="button" class="mdfe-subtab-btn is-active" data-mdfe-total-tab="1">Totais dos Documentos</button>
                                    <button type="button" class="mdfe-subtab-btn" data-mdfe-total-tab="2">Autorização para Download</button>
                                </div>

                                <div class="mdfe-subtab-pane is-active" data-mdfe-total-tab="1">
                                    <h6 class="mb-2">Totais dos Documentos</h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label for="tot_total_nfe" class="form-label">Total de NF-e's informadas</label>
                                            <input type="number" class="form-control" id="tot_total_nfe" min="0">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="tot_valor_total_carga" class="form-label">Valor total da carga</label>
                                            <input type="number" class="form-control" id="tot_valor_total_carga" min="0" step="0.01">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="tot_unidade_medida_carga" class="form-label">Unidade de medida da carga</label>
                                            <select class="form-select" id="tot_unidade_medida_carga">
                                                <option value="">Selecione</option>
                                                <option value="01">01 - KG</option>
                                                <option value="02">02 - TON</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="tot_peso_total" class="form-label">Peso total</label>
                                            <input type="number" class="form-control" id="tot_peso_total" min="0" step="0.001">
                                        </div>
                                    </div>

                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Lacres</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarLacreTotalizadoresNovoMDFE()">
                                            <i class="fas fa-plus"></i> Novo lacre
                                        </button>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label for="tot_numero_lacre" class="form-label">Número do lacre</label>
                                            <input type="text" class="form-control" id="tot_numero_lacre">
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>Número do lacre</th>
                                                    <th style="width: 70px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="totLacresTabelaBodyNovoMDFE">
                                                <tr><td colspan="2" class="text-muted">Nenhum lacre adicionado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mdfe-subtab-pane" data-mdfe-total-tab="2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Autorização para Download</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarAutorizadoDownloadNovoMDFE()">
                                            <i class="fas fa-plus"></i> Novo
                                        </button>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-4">
                                            <label for="tot_autorizado_doc" class="form-label">CPF/CNPJ</label>
                                            <input type="text" class="form-control" id="tot_autorizado_doc" placeholder="CPF ou CNPJ">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="tot_autorizado_motorista" class="form-label">Motorista</label>
                                            <input type="text" class="form-control" id="tot_autorizado_motorista" placeholder="Nome do motorista">
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="mdfe-simple-table">
                                            <thead>
                                                <tr>
                                                    <th>CPF/CNPJ</th>
                                                    <th>Motorista</th>
                                                    <th style="width: 70px;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody id="totAutorizadosTabelaBodyNovoMDFE">
                                                <tr><td colspan="3" class="text-muted">Nenhum autorizado cadastrado.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <input type="hidden" id="tot_totalizadores_json" name="tot_totalizadores_json" value="{}">
                                <input type="hidden" id="origem_mdfe" name="origem_mdfe" value="">
                                <input type="hidden" id="origem_cte_ids" name="origem_cte_ids" value="[]">
                                <input type="hidden" id="origem_nfe_ids" name="origem_nfe_ids" value="[]">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="avancarNovoMdfeWizard()">
                        <i class="fas fa-arrow-right"></i> Próxima etapa
                    </button>
                </div>
            </div>
        </div>
    </div>
