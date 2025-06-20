// Classe para gerenciar o componente de veículo
class VeiculoComponente {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.pneus = [];
        this.eixos = [];
        this.rodizioSugerido = [];
    }
    
    async carregarPneus(veiculoId) {
        try {
            const response = await fetch(`/sistema-frotas/api/pneus_data.php?action=get_pneus&veiculo_id=${veiculoId}`);
            const data = await response.json();
            
            if (data.success) {
                this.pneus = data.pneus || [];
                this.eixos = data.eixos || [];
                this.rodizioSugerido = this.pneus
                    .filter(p => p.rodizio === 1)
                    .map(p => p.posicao_id);

                this.render();
                document.getElementById('legendaPneus').classList.add('ativa');
                const painelEixos = document.getElementById('painelEixos');
                if (painelEixos) {
                    painelEixos.classList.add('ativa');
                }
                this.renderEixosMontados();
                this.setupBtnSalvarEixos();
                this.setupBtnResetEixos();
                
                // Carregar histórico
                this.carregarHistorico(veiculoId);
            } else {
                throw new Error(data.error || 'Erro ao carregar pneus');
            }
        } catch (error) {
            console.error('Erro ao carregar pneus:', error);
            alert('Erro ao carregar pneus. Verifique o console para mais detalhes.');
            document.getElementById('legendaPneus').classList.remove('ativa');
            const painelEixos = document.getElementById('painelEixos');
            if (painelEixos) {
                painelEixos.classList.remove('ativa');
            }
        }
    }
    
    render() {
        this.container.innerHTML = `
            <div class="composicao-veiculo">
                <div class="cavalo" style="position:relative;">
                    <div class="cabine"></div>
                    <div class="veiculo veiculo-centralizado" id="veiculoCavalo">
                        <div class="linha-central"></div>
                        <div class="eixos-wrapper drop-area" id="dropCavalo"></div>
                    </div>
                    <div class="label-cavalo">Cavalo</div>
                </div>
                <div class="carreta" style="position:relative;">
                    <div class="carreta-wire">
                        <div class="eixo"></div>
                        <div class="eixo"></div>
                    </div>
                    <div class="veiculo veiculo-centralizado" id="veiculoCarreta">
                        <div class="linha-central"></div>
                        <div class="eixos-wrapper drop-area" id="dropCarreta"></div>
                    </div>
                    <div class="label-carreta">Carreta</div>
                </div>
            </div>
            <div style="display:flex;justify-content:center;gap:10px;margin-top:20px;">
                <button class="btn-salvar-eixos" id="btnSalvarEixos">Salvar</button>
                <button class="btn-undo-eixos" id="btnUndoEixos">Desfazer</button>
                <button class="btn-reset-eixos" id="btnResetEixos">Resetar</button>
            </div>
        `;
        
        this.renderEixosMontados();
        this.setupDragDrop();
        this.setupBtnSalvarEixos();
        this.setupBtnResetEixos();
    }
    
    renderEixo(qtdPneus, posicao) {
        let pneusHTML = '';
        const posicoesDoEixo = this.mapaEixoParaPosicoes[posicao] || [];
        
        for (let i = 0; i < qtdPneus; i++) {
            const posicaoId = posicoesDoEixo[i];
            const pneu = this.pneus.find(p => p.posicao_id == posicaoId) || null;
            pneusHTML += `<div style='display:flex;flex-direction:column;align-items:center;'>`;
            pneusHTML += this.renderPneu(pneu, posicaoId, i < qtdPneus/2 ? 'esquerda' : 'direita');
            pneusHTML += `</div>`;
        }
        
        pneusHTML += '<div class="espaco-central"></div>';
        
        return `
            <div class="eixo" data-pneus="${qtdPneus}" data-posicao="${posicao || ''}" draggable="true">
                <div class="linha-eixo"></div>
                ${pneusHTML}
            </div>`;
    }
    
    renderPneu(pneuData, posicao, lado) {
        if (!pneuData) {
            return `<div class="pneu vazio" data-posicao_id="${posicao}" data-lado="${lado}"></div>`;
        }
        
        const alerta = pneuData.alerta === 1;
        const rodizio = this.rodizioSugerido.includes(pneuData.posicao_id);
        const status = pneuData.status || 'bom';
        
        return `
            <div class="pneu ${status} ${alerta ? 'alerta' : ''} ${rodizio ? 'rodizio' : ''}" 
                 data-posicao_id="${pneuData.posicao_id}" data-lado="${lado}">
                <div class="tooltip">
                    <strong>Pneu ${pneuData.posicao_nome}</strong><br>
                    Status: ${pneuData.status_nome}<br>
                    Marca: ${pneuData.marca}<br>
                    Modelo: ${pneuData.modelo}<br>
                    Sulco: ${pneuData.sulco_inicial} mm<br>
                    DOT: ${pneuData.dot}<br>
                    Última Recapagem: ${pneuData.data_ultima_recapagem || 'N/A'}
                </div>
            </div>`;
    }
    
    async carregarHistorico(veiculoId) {
        try {
            const response = await fetch(`/sistema-frotas/api/pneus_data.php?action=get_historico&veiculo_id=${veiculoId}`);
            const data = await response.json();
            
            if (data.success) {
                const tbody = document.getElementById('historicoPneus');
                if (tbody) {
                    tbody.innerHTML = data.historico.map(registro => `
                        <tr>
                            <td>${registro.numero_serie}</td>
                            <td>${registro.eixo_posicao}</td>
                            <td>${registro.pneu_posicao}</td>
                            <td>${new Date(registro.data_alocacao).toLocaleDateString()}</td>
                            <td>${registro.km_alocacao.toLocaleString()}</td>
                            <td>${registro.data_desalocacao ? new Date(registro.data_desalocacao).toLocaleDateString() : '-'}</td>
                            <td>${registro.km_desalocacao ? registro.km_desalocacao.toLocaleString() : '-'}</td>
                            <td>${registro.alocacao_status}</td>
                            <td>${registro.observacoes || '-'}</td>
                        </tr>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
        }
    }
    
    setupDragDrop() {
        // Painel lateral: só adiciona ao arrastar
        document.querySelectorAll('.eixo-drag').forEach(el => {
            el.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('qtd', this.getAttribute('data-qtd'));
                e.dataTransfer.setData('source', 'painel');
            });
        });
        
        // Delegation para drop, dragover e dragleave nas áreas de drop
        const container = document.getElementById('componente-veiculo');
        if (!container._delegationSetup) {
            container.addEventListener('dragover', function(e) {
                const dropArea = e.target.closest('.drop-area');
                if (!dropArea) return;
                e.preventDefault();
                dropArea.classList.add('over');
            });
            
            container.addEventListener('dragleave', function(e) {
                const dropArea = e.target.closest('.drop-area');
                if (!dropArea) return;
                dropArea.classList.remove('over');
            });
            
            container.addEventListener('drop', function(e) {
                let dropArea = e.target;
                while (dropArea && !dropArea.classList.contains('drop-area')) {
                    dropArea = dropArea.parentElement;
                }
                if (!dropArea) return;
                
                e.preventDefault();
                dropArea.classList.remove('over');
                
                const qtd = parseInt(e.dataTransfer.getData('qtd'));
                const source = e.dataTransfer.getData('source');
                let arr, dropAreaId;
                
                if (dropArea.id === 'dropCavalo') {
                    arr = this.eixosCavaloMontados;
                    dropAreaId = 'dropCavalo';
                } else {
                    arr = this.eixosCarretaMontados;
                    dropAreaId = 'dropCarreta';
                }
                
                if (source === 'painel') {
                    arr.push(qtd);
                    this.historicoAcoes.push({ tipo: dropAreaId, idx: arr.length - 1 });
                } else if (source === dropAreaId) {
                    const fromIdx = parseInt(e.dataTransfer.getData('fromIdx'));
                    const toIdx = this.getDropIndex(e, dropArea);
                    if (fromIdx !== toIdx) {
                        const [moved] = arr.splice(fromIdx, 1);
                        arr.splice(toIdx, 0, moved);
                    }
                }
                
                this.renderEixosMontados();
            });
            
            container._delegationSetup = true;
        }
        
        // Eixos já montados: permitir drag para reordenar
        document.querySelectorAll('.drop-area .eixo').forEach(el => {
            el.setAttribute('draggable', 'true');
            el.addEventListener('dragstart', function(e) {
                const parentId = this.parentElement.classList.contains('drop-area') ? 
                    this.parentElement.id : this.closest('.drop-area').id;
                e.dataTransfer.setData('qtd', this.getAttribute('data-pneus'));
                e.dataTransfer.setData('fromIdx', this.getAttribute('data-idx'));
                e.dataTransfer.setData('source', parentId);
            });
        });
    }
    
    getDropIndex(e, dropArea) {
        const rect = dropArea.getBoundingClientRect();
        const y = e.clientY - rect.top;
        const items = Array.from(dropArea.children);
        let idx = 0;
        let acc = 0;
        
        for (let i = 0; i < items.length; i++) {
            acc += items[i].offsetHeight;
            if (y < acc) {
                idx = i;
                break;
            }
            idx = i + 1;
        }
        
        return idx;
    }
    
    setupBtnSalvarEixos() {
        const btnSalvar = document.getElementById('btnSalvarEixos');
        if (!btnSalvar) return;
        
        btnSalvar.onclick = async () => {
            const selectVeiculo = document.getElementById('veiculoSelect');
            const veiculoId = selectVeiculo ? selectVeiculo.value : null;
            
            if (!veiculoId) {
                alert('Selecione um veículo primeiro.');
                return;
            }
            
            try {
                const resp = await fetch('/sistema-frotas/api/salvar_eixos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        veiculo_id: veiculoId,
                        eixos_cavalo: this.eixosCavaloMontados,
                        eixos_carreta: this.eixosCarretaMontados
                    })
                });
                
                const data = await resp.json();
                if (data.success) {
                    alert('Configuração de eixos salva com sucesso!');
                    await this.carregarPneus(veiculoId);
                } else {
                    throw new Error(data.error || 'Erro ao salvar eixos');
                }
            } catch (error) {
                console.error('Erro ao salvar eixos:', error);
                alert('Erro ao salvar configuração de eixos.');
            }
        };
    }
    
    setupBtnResetEixos() {
        const btnReset = document.getElementById('btnResetEixos');
        if (!btnReset || btnReset._listenerSetup) return;
        
        btnReset.addEventListener('click', async () => {
            const select = document.getElementById('veiculoSelect');
            const veiculoId = select ? select.value : null;
            if (!veiculoId) return;
            
            try {
                const resp = await fetch(`/sistema-frotas/api/pneus_data.php?action=get_pneus&veiculo_id=${veiculoId}`);
                const data = await resp.json();
                
                if (data.pneus && data.pneus.some(p => p.veiculo_id == veiculoId)) {
                    alert('Não é possível resetar enquanto houver pneus alocados. Desloque todos os pneus antes de resetar.');
                    return;
                }
                
                this.eixosCavaloMontados = [];
                this.eixosCarretaMontados = [];
                this.renderEixosMontados();
                this.setupBtnSalvarEixos();
                this.setupBtnResetEixos();
            } catch (error) {
                console.error('Erro ao verificar pneus alocados:', error);
                alert('Erro ao verificar pneus alocados.');
            }
        });
        
        btnReset._listenerSetup = true;
    }
    
    setupBtnUndoEixos() {
        const btnUndo = document.getElementById('btnUndoEixos');
        if (!btnUndo || btnUndo._listenerSetup) return;
        
        btnUndo.addEventListener('click', () => {
            if (this.historicoAcoes.length === 0) return;
            
            const last = this.historicoAcoes.pop();
            if (last.tipo === 'dropCavalo') {
                this.eixosCavaloMontados.splice(last.idx, 1);
            } else if (last.tipo === 'dropCarreta') {
                this.eixosCarretaMontados.splice(last.idx, 1);
            }
            
            this.renderEixosMontados();
            this.setupBtnSalvarEixos();
            this.setupBtnResetEixos();
        });
        
        btnUndo._listenerSetup = true;
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    const veiculo = new VeiculoComponente('componente-veiculo');
    
    // Event listener para o select de veículos
    const selectVeiculo = document.getElementById('veiculoSelect');
    if (selectVeiculo) {
        selectVeiculo.addEventListener('change', function() {
            const veiculoId = this.value;
            if (veiculoId) {
                veiculo.carregarPneus(veiculoId);
            }
        });
    }
}); 