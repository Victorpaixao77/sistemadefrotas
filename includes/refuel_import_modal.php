<?php
/**
 * Modal "Importar abastecimentos / ficha de frete" (reutilizável).
 *
 * Lê foto/PDF/CSV via IA de visão: extrai a viagem inteira (rota + despesas +
 * abastecimentos), deixa revisar e cria a rota com os abastecimentos vinculados.
 * Também importa só abastecimentos (CSV/JSON/foto) para uma rota já existente.
 *
 * Depende de: includes/sf_api_base.php (sf_render_api_scripts) + js/refuel-import.js
 * + css/abastecimentos.css. Use em páginas dentro de /pages.
 *
 * Variável opcional no escopo de inclusão:
 *   $is_modern (bool) — aplica as classes "fornc-modal" do tema moderno.
 */
$rim_modern = !empty($is_modern);
$rim_title = isset($rim_title) && $rim_title !== '' ? $rim_title : 'Importar abastecimentos';
?>
    <!-- Importar abastecimentos / ficha de frete -->
    <div class="modal<?php echo $rim_modern ? ' fornc-modal' : ''; ?>" id="refuelImportModal">
        <div class="modal-content<?php echo $rim_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
            <div class="modal-header">
                <h2><?php echo htmlspecialchars($rim_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div id="refuelImportIntro" class="refuel-import-intro-wrap">
                <p class="refuel-import-intro text-muted">
                    <strong>Ficha de frete (foto/PDF):</strong> a <strong>IA de Visão</strong> lê a viagem inteira —
                    motorista, origem/destino, KM, peso, frete, comissão, despesas (pedágio, adiantamento, etc.) e os abastecimentos.
                    Você revisa tudo e o sistema <strong>cria a rota já com os abastecimentos vinculados</strong>.
                </p>
                <p class="refuel-import-intro text-muted" id="refuelImportIntroAvulso">
                    <strong>Só abastecimentos:</strong> envie um <strong>CSV/JSON</strong> com as colunas
                    <strong>data</strong>, <strong>km</strong>, <strong>posto</strong>, <strong>litros</strong>, <strong>valor_total</strong>
                    (<code>;</code> ou <code>,</code>; datas <code>19/03/2025</code> ou <code>2025-03-19</code>),
                    ou uma foto/PDF do comprovante — selecione veículo, motorista e rota antes de importar.
                </p>
                <p class="refuel-import-intro text-muted">
                    Configure a chave em <a href="configuracoes.php#config-vision-ai">Configurações → IA &amp; OCR</a>
                    (recomendado: <strong>Google Gemini — gratuito</strong>, chave no
                    <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>),
                    ou <strong>OpenRouter</strong> com modelo free de visão.
                    Sem chave, usa <strong>OCR no navegador</strong> (Tesseract — gratuito, menos preciso). <strong>Revise sempre antes de salvar.</strong>
                </p>
                <p class="refuel-import-intro text-muted">
                    <a href="../examples/abastecimento_import_modelo.csv" download class="refuel-import-model-link"><i class="fas fa-download" aria-hidden="true"></i> Modelo CSV</a>
                    ·
                    <a href="../examples/abastecimento_import_modelo.json" download class="refuel-import-model-link"><i class="fas fa-download" aria-hidden="true"></i> Modelo JSON</a>
                </p>
                </div>
                <div class="form-group">
                    <label for="refuelImportFile">Arquivo</label>
                    <input type="file" id="refuelImportFile" accept=".csv,.json,.pdf,image/*,text/csv,application/json,application/pdf">
                </div>
                <p id="refuelImportProgress" class="refuel-import-progress" role="status" aria-live="polite"></p>
                <p id="refuelImportError" class="refuel-import-error" role="alert"></p>
                <details class="refuel-import-ocr-details" id="refuelImportOcrWrap" hidden>
                    <summary>Texto extraído (OCR / PDF)</summary>
                    <pre id="refuelImportOcrRaw" class="refuel-import-ocr-pre"></pre>
                </details>
                <div class="refuel-import-shared" id="refuelImportShared" hidden>
                    <p class="refuel-import-intro text-muted" style="margin-bottom:.5rem">
                        <strong>Aplicar a todas as linhas:</strong> o comprovante não traz estes campos — selecione uma vez.
                    </p>
                    <div class="refuel-import-shared-grid">
                        <div class="form-group">
                            <label for="impVeiculo">Veículo</label>
                            <select id="impVeiculo"><option value="">Selecione…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impMotorista">Motorista</label>
                            <select id="impMotorista"><option value="">Selecione…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impRota">Rota</label>
                            <select id="impRota"><option value="">Selecione veículo e motorista…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impCombustivel">Combustível</label>
                            <select id="impCombustivel"><option value="">Selecione…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impFormaPgto">Forma de pagamento</label>
                            <select id="impFormaPgto">
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="PIX">PIX</option>
                                <option value="Cartão">Cartão</option>
                                <option value="Boleto">Boleto</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="refuel-import-preview-wrap" id="refuelImportPreviewWrap" hidden>
                    <table class="data-table refuel-import-preview" id="refuelImportPreviewTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>KM</th>
                                <th>Posto</th>
                                <th>Litros</th>
                                <th>Valor R$</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="refuelImportPreviewBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span id="refuelImportResult" class="refuel-import-result" aria-live="polite" style="margin-right:auto"></span>
                <span id="irrStatus" class="irr-status" aria-live="polite"></span>
                <button type="button" class="btn-secondary close-modal">Fechar</button>
                <button type="button" class="btn-primary" id="irrCreateBtn" style="display:none"><i class="fas fa-route"></i> Criar rota e importar abastecimentos</button>
                <button type="button" class="btn-primary" id="refuelImportSaveBtn" disabled><i class="fas fa-file-import"></i> Importar</button>
            </div>
        </div>
    </div>
