// Sistema de Eixos de Veículos - Gestão Interativa

function escapeHtmlPneus(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function sfPneusFetchHeaders(extra) {
    const h = Object.assign({ 'Content-Type': 'application/json' }, extra || {});
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        h['X-CSRF-Token'] = window.__SF_CSRF__;
    }
    return h;
}

// Estado dos eixos e pneus alocados no modo flexível
let eixosCaminhao = [];
let eixosCarreta = [];
let idEixo = 1;
let pneusFlexAlocados = {}; // { slotId: pneuObj }

// Ao selecionar veículo, carregar layout e pneus alocados do banco
function onSelecionarVeiculoFlexivel(veiculoId) {
    fetch('../gestao_interativa/api/eixos_veiculos.php?action=layout_completo&veiculo_id=' + veiculoId, {
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.layout) {
                eixosCaminhao = data.layout.eixosCaminhao || [];
                eixosCarreta = data.layout.eixosCarreta || [];
                idEixo = data.layout.idEixo || 1;
                
                // Reconectar pneus alocados aos dados atuais
                const salvos = data.layout.pneusFlexAlocados || {};
                pneusFlexAlocados = {};
                for (const slotId in salvos) {
                    const pneuSalvo = salvos[slotId];
                    // Tenta encontrar o pneu pelo ID na lista global (pode estar em uso, então busca em todas as listas)
                    let pneuAtual = null;
                    if (window.pneusDisponiveis) {
                        pneuAtual = window.pneusDisponiveis.find(p => p.id == pneuSalvo.id);
                    }
                    
                    // SEMPRE preservar as informações de posição do banco de dados
                    if (pneuAtual) {
                        // Mesclar dados do pneu atual com informações de posição salvas do banco
                        pneusFlexAlocados[slotId] = {
                            ...pneuAtual,
                            posicao_id: pneuSalvo.posicao_id,        // SEMPRE usar do banco
                            posicao_nome: pneuSalvo.posicao_nome     // SEMPRE usar do banco
                        };
                    } else {
                        // Usar o snapshot salvo completo do banco (inclui posição)
                        pneusFlexAlocados[slotId] = pneuSalvo;
                    }
                }
            } else {
                eixosCaminhao = [];
                eixosCarreta = [];
                idEixo = 1;
                pneusFlexAlocados = {};
            }
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso(); // Atualizar dashboard
            
            // Carregar histórico e estatísticas
            setTimeout(() => {
                carregarHistoricoFlexivel(veiculoId);
                carregarEstatisticasFlexivel(veiculoId);
            }, 100);
        })
        .catch(error => {
            console.error('Erro ao carregar layout:', error);
            // Fallback para sistema antigo se necessário
            carregarLayoutFallback(veiculoId);
        });
}

// Função de fallback para sistema antigo (JSON)
function carregarLayoutFallback(veiculoId) {
    fetch('../gestao_interativa/api/layout_flexivel.php?veiculo_id=' + veiculoId, {
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.layout) {
                eixosCaminhao = data.layout.eixosCaminhao || [];
                eixosCarreta = data.layout.eixosCarreta || [];
                idEixo = data.layout.idEixo || 1;
                pneusFlexAlocados = data.layout.pneusFlexAlocados || {};
            } else {
                eixosCaminhao = [];
                eixosCarreta = [];
                idEixo = 1;
                pneusFlexAlocados = {};
            }
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso();
        });
}

// Função para adicionar eixo usando API do banco
function adicionarEixo(tipo) {
    const veiculoId = veiculoSelecionado;
    if (!veiculoId) {
        giToast('Selecione um veículo primeiro!');
        return;
    }

    giChoose('Tipo de eixo a adicionar', [
        { label: 'Rodado simples', desc: '2 pneus (um de cada lado)', value: 2 },
        { label: 'Rodado duplo', desc: '4 pneus (dois de cada lado)', value: 4 }
    ]).then(function (quantidade_pneus) {
        if (!quantidade_pneus) return;

        fetch('../gestao_interativa/api/eixos_veiculos.php?action=adicionar_eixo', {
            method: 'POST',
            headers: sfPneusFetchHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                veiculo_id: veiculoId,
                tipo_veiculo: tipo,
                quantidade_pneus: quantidade_pneus
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                giToast('Eixo adicionado com sucesso!', 'success');
                // Recarregar layout do banco
                onSelecionarVeiculoFlexivel(veiculoId);
            } else {
                giToast('Erro ao adicionar eixo: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Erro ao adicionar eixo:', error);
            giToast('Erro ao adicionar eixo. Tente novamente.', 'error');
        });
    });
}

// Função para excluir eixo usando API do banco
function excluirEixo(tipo) {
    const veiculoId = veiculoSelecionado;
    if (!veiculoId) {
        giToast('Selecione um veículo primeiro!');
        return;
    }

    const eixos = tipo === 'caminhao' ? eixosCaminhao : eixosCarreta;
    
    if (eixos.length === 0) {
        giToast(`Não há eixos para excluir no ${tipo === 'caminhao' ? 'caminhão' : 'carreta'}.`);
        return;
    }

    // Verificar quais eixos têm pneus alocados
    const eixosComPneus = [];
    const eixosSemPneus = [];
    
    eixos.forEach((eixo, idx) => {
        const qtdPneus = eixo.pneus;
        let temPneus = false;
        
        // Verificar se algum pneu está alocado neste eixo
        for (let i = 0; i < qtdPneus; i++) {
            const slotId = tipo === 'caminhao' ? `cavalo-${idx}-${i}` : `carreta-${idx}-${i}`;
            if (pneusFlexAlocados[slotId]) {
                temPneus = true;
                break;
            }
        }
        
        if (temPneus) {
            eixosComPneus.push(idx + 1);
        } else {
            eixosSemPneus.push(idx + 1);
        }
    });

    if (eixosSemPneus.length === 0) {
        giToast(`Todos os eixos do ${tipo === 'caminhao' ? 'caminhão' : 'carreta'} têm pneus alocados. Remova os pneus primeiro.`);
        return;
    }

    // Lista de eixos que podem ser excluídos (sem pneus)
    const opcoesEixos = eixosSemPneus.map(function (numero) {
        return { label: `Eixo ${numero}`, value: numero - 1 };
    });
    const aviso = eixosComPneus.length > 0
        ? 'Eixos com pneus alocados não aparecem (remova os pneus antes).'
        : '';

    giChoose('Selecione o eixo para excluir' + (aviso ? ' — ' + aviso : ''), opcoesEixos).then(function (index) {
        if (index === null || index === undefined) return;
        giConfirm(`Tem certeza que deseja excluir o Eixo ${index + 1}?`, { okLabel: 'Excluir' }).then(function (ok) {
            if (!ok) return;
            const eixoParaExcluir = eixos[index];

        fetch('../gestao_interativa/api/eixos_veiculos.php?action=excluir_eixo', {
            method: 'POST',
            headers: sfPneusFetchHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                eixo_id: eixoParaExcluir.id
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                giToast(`Eixo ${index + 1} excluído com sucesso!`);
                // Recarregar layout do banco
                onSelecionarVeiculoFlexivel(veiculoId);
            } else {
                giToast('Erro ao excluir eixo: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir eixo:', error);
            giToast('Erro ao excluir eixo. Tente novamente.');
        });
        });
    });
}

// Função para alocar pneu usando API do banco
async function alocarPneuFlexivel(slotId, pneuId) {
    const veiculoId = veiculoSelecionado;
    if (!veiculoId) {
        giToast('Selecione um veículo primeiro!');
        return;
    }

    // Carregar posições disponíveis
    let posicoes = [];
    try {
        const response = await fetch('../gestao_interativa/api/posicoes_pneus.php', {
            credentials: 'same-origin'
        });
        
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
        }
        
        
        if (data.success && data.posicoes) {
            posicoes = data.posicoes;
        } else {
            console.error('API retornou erro:', data);
            throw new Error(data.error || 'Erro desconhecido ao carregar posições');
        }
    } catch (error) {
        console.error('Erro ao carregar posições:', error);
        giToast('ERRO: Não foi possível carregar as posições de pneus.\n\n' + 
              'Detalhes: ' + error.message + '\n\n' +
              'Verifique o console do navegador (F12) para mais informações.');
        return;
    }

    // Se não há posições, mostrar erro
    if (!posicoes || posicoes.length === 0) {
        giToast('ATENÇÃO: Não há posições de pneus cadastradas no sistema!\n\n' +
              'Para usar este sistema, você precisa cadastrar as posições de pneus primeiro.\n' +
              'Por favor, cadastre as posições antes de alocar pneus.');
        return;
    }
    
    // DEBUG: Remover este bloco que aloca sem posição
    /*
    if (posicoes.length === 0) {
        // Alocar sem posição específica
        const [tipo, idxEixo, posicao] = slotId.split('-');
        const eixos = tipo === 'cavalo' ? eixosCaminhao : eixosCarreta;
        const eixo = eixos[parseInt(idxEixo)];
        
        if (!eixo) {
            giToast('Eixo não encontrado!');
            return;
        }

        try {
            const response = await fetch('../gestao_interativa/api/eixos_veiculos.php?action=alocar_pneu', {
                method: 'POST',
                headers: sfPneusFetchHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    eixo_id: eixo.id,
                    pneu_id: pneuId,
                    slot_id: slotId,
                    posicao_slot: parseInt(posicao),
                    posicao_id: null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Atualizar estado local
                const pneu = window.pneusDisponiveis.find(p => p.id == pneuId);
                if (pneu) {
                    pneusFlexAlocados[slotId] = pneu;
                }
                renderizarEixosFlexivel();
                atualizarDashboardPneusEmUso();
            } else {
                giToast('Erro ao alocar pneu: ' + data.error);
            }
        } catch (error) {
            console.error('Erro ao alocar pneu:', error);
            giToast('Erro ao alocar pneu. Tente novamente.');
        }
        return;
    }
    */

    // Seleção da posição do pneu via modal
    const posOpcoes = posicoes.map(function (pos) { return { label: pos.nome, value: pos.id }; });
    const posicaoIdEscolhida = await giChoose('Selecione a posição do pneu', posOpcoes);

    // Se o usuário cancelar, não continuar
    if (posicaoIdEscolhida === null || posicaoIdEscolhida === undefined) {
        return;
    }

    const posicaoSelecionada = posicoes.find(function (p) { return p.id === posicaoIdEscolhida; });
    if (!posicaoSelecionada) {
        giToast('Seleção inválida!');
        return;
    }
    
    // Encontrar o eixo correspondente ao slot
    const [tipoEixo, idxEixo, posicaoEixo] = slotId.split('-');
    const eixos = tipoEixo === 'cavalo' ? eixosCaminhao : eixosCarreta;
    const eixo = eixos[parseInt(idxEixo)];
    
    if (!eixo) {
        giToast('Eixo não encontrado!');
        return;
    }

    try {
        const response = await fetch('../gestao_interativa/api/eixos_veiculos.php?action=alocar_pneu', {
            method: 'POST',
            headers: sfPneusFetchHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                eixo_id: eixo.id,
                pneu_id: pneuId,
                slot_id: slotId,
                posicao_slot: parseInt(posicaoEixo),
                posicao_id: posicaoSelecionada.id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Atualizar estado local
            const pneu = window.pneusDisponiveis.find(p => p.id == pneuId);
            if (pneu) {
                pneusFlexAlocados[slotId] = {
                    ...pneu,
                    posicao_id: posicaoSelecionada.id,
                    posicao_nome: posicaoSelecionada.nome
                };
            }
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso();
        } else {
            giToast('Erro ao alocar pneu: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao alocar pneu:', error);
        giToast('Erro ao alocar pneu. Tente novamente.');
    }
}

// Função para remover pneu usando API do banco
function removerPneuFlexivel(slotId, motivoRemocaoId) {
    const body = { slot_id: slotId };
    if (motivoRemocaoId) body.motivo_remocao_id = motivoRemocaoId;
    return fetch('../gestao_interativa/api/eixos_veiculos.php?action=remover_pneu', {
        method: 'POST',
        headers: sfPneusFetchHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            delete pneusFlexAlocados[slotId];
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso();
        } else {
            giToast('Erro ao remover pneu: ' + data.error);
        }
        return data;
    })
    .catch(error => {
        console.error('Erro ao remover pneu:', error);
        giToast('Erro ao remover pneu. Tente novamente.');
        return { success: false };
    });
}

async function solicitarRemocaoPneuFlexivel(slotId) {
    let motivoId = null;
    let motivos = [];
    try {
        const res = await fetch('../api/pneus_mercado.php?action=catalogos', { credentials: 'same-origin' });
        const data = await res.json();
        motivos = (data && data.motivos_remocao) ? data.motivos_remocao : [];
    } catch (e) {
        console.warn('Motivos remoção indisponíveis', e);
    }

    if (motivos.length) {
        // Escolher o motivo já serve como confirmação da remoção.
        const motOpcoes = motivos.map(function (m) { return { label: m.nome, value: m.id }; });
        const esc = await giChoose('Motivo da remoção do pneu', motOpcoes);
        if (esc === null || esc === undefined) return { success: false, cancelled: true };
        motivoId = esc || null;
    } else {
        const ok = await giConfirm('Confirmar remoção do pneu deste slot?', { okLabel: 'Remover' });
        if (!ok) return { success: false, cancelled: true };
    }
    return removerPneuFlexivel(slotId, motivoId);
}

// Mover pneu entre slots (persiste no banco)
function moverPneuFlexivel(fromSlotId, toSlotId) {
    return fetch('../gestao_interativa/api/eixos_veiculos.php?action=mover_pneu', {
        method: 'POST',
        headers: sfPneusFetchHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            from_slot_id: fromSlotId,
            to_slot_id: toSlotId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const pneu = pneusFlexAlocados[fromSlotId];
            if (pneu) {
                delete pneusFlexAlocados[fromSlotId];
                pneusFlexAlocados[toSlotId] = pneu;
            }
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso();
        } else {
            giToast('Erro ao mover pneu: ' + (data.error || 'falha desconhecida'));
        }
        return data;
    })
    .catch(error => {
        console.error('Erro ao mover pneu:', error);
        giToast('Erro ao mover pneu. Tente novamente.');
        return { success: false };
    });
}

// Enviar pneu alocado para manutenção (remove do veículo)
function enviarManutencaoFlexivel(slotId, observacoes) {
    return fetch('../gestao_interativa/api/eixos_veiculos.php?action=enviar_manutencao', {
        method: 'POST',
        headers: sfPneusFetchHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
            slot_id: slotId,
            observacoes: observacoes || 'Enviado para manutenção'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            delete pneusFlexAlocados[slotId];
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso();
            if (typeof carregarPneusDisponiveis === 'function') {
                carregarPneusDisponiveis().catch(function () {});
            }
            const veiculoId = typeof veiculoSelecionado !== 'undefined' ? veiculoSelecionado : null;
            if (veiculoId) {
                carregarHistoricoFlexivel(veiculoId);
                carregarEstatisticasFlexivel(veiculoId);
            }
            giToast('Pneu enviado para manutenção com sucesso.');
        } else {
            giToast('Erro: ' + (data.error || 'não foi possível enviar para manutenção'));
        }
        return data;
    })
    .catch(error => {
        console.error('Erro ao enviar para manutenção:', error);
        giToast('Erro ao enviar pneu para manutenção.');
        return { success: false };
    });
}

// Função para salvar layout (mantida para compatibilidade)
function salvarLayoutFlexivel(auto = false) {
    // Com o novo sistema, não precisamos mais salvar manualmente
    // Os dados são salvos automaticamente no banco
    if (!auto) {
        giToast('Layout salvo automaticamente no banco de dados!');
    }
}

// Função para limpar layout
function limparLayoutFlexivel() {
    eixosCaminhao = [];
    eixosCarreta = [];
    idEixo = 1;
    pneusFlexAlocados = {};
    renderizarEixosFlexivel();
}

// Função para carregar histórico no modo flexível
function carregarHistoricoFlexivel(veiculoId) {
    fetch(`../gestao_interativa/api/historico_alocacoes.php?veiculo_id=${veiculoId}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            const historyDiv = document.getElementById('allocationHistory');
            
            if (data.success && data.historico && data.historico.length > 0) {
                let html = '<div style="max-height: 200px; overflow-y: auto;">';
                data.historico.forEach(item => {
                    const acao = item.data_remocao ? 'Remoção' : 'Alocação';
                    const data = item.data_remocao || item.data_instalacao;
                    html += `
                        <div style="padding: 8px; border-bottom: 1px solid #ddd; font-size: 0.9em;">
                            <strong>${escapeHtmlPneus(data)}</strong><br>
                            ${acao}: ${escapeHtmlPneus(item.numero_serie)} (Pos. ${escapeHtmlPneus(item.posicao)})<br>
                            <span class="status-badge status-${escapeHtmlPneus(item.status_nome ? item.status_nome.toLowerCase().replace(' ', '-') : 'bom')}">${escapeHtmlPneus(item.status_nome || 'Bom')}</span>
                        </div>
                    `;
                });
                html += '</div>';
                historyDiv.innerHTML = html;
            } else {
                historyDiv.innerHTML = '<p>Nenhum histórico encontrado para este veículo.</p>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar histórico flexível:', error);
            const historyDiv = document.getElementById('allocationHistory');
            if (historyDiv) {
                historyDiv.innerHTML = '<p>Erro ao carregar histórico.</p>';
            }
        });
}

// Função para carregar estatísticas no modo flexível
function carregarEstatisticasFlexivel(veiculoId) {
    fetch(`../gestao_interativa/api/estatisticas_veiculo.php?veiculo_id=${veiculoId}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            const statsDiv = document.getElementById('vehicleStats');
            
            if (data.success && data.estatisticas) {
                const stats = data.estatisticas;
                statsDiv.innerHTML = `
                    <div style="font-size: 0.9em;">
                        <p><strong>Pneus Ativos:</strong> ${stats.pneusAtivos}</p>
                        <p><strong>Em Manutenção:</strong> ${stats.pneusManutencao}</p>
                        <p><strong>Descartados:</strong> ${stats.pneusDescartados}</p>
                        <p><strong>KM Médio:</strong> ${stats.quilometragemMedia.toLocaleString()} km</p>
                        <p><strong>Total de Alocações:</strong> ${stats.totalAlocacoes}</p>
                    </div>
                `;
            } else {
                statsDiv.innerHTML = '<p>Erro ao carregar estatísticas.</p>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar estatísticas flexível:', error);
            const statsDiv = document.getElementById('vehicleStats');
            if (statsDiv) {
                statsDiv.innerHTML = '<p>Erro ao carregar estatísticas.</p>';
            }
        });
}

// Exportar funções para uso global
window.adicionarEixo = adicionarEixo;
window.excluirEixo = excluirEixo;
window.alocarPneuFlexivel = alocarPneuFlexivel;
window.removerPneuFlexivel = removerPneuFlexivel;
window.solicitarRemocaoPneuFlexivel = solicitarRemocaoPneuFlexivel;
window.moverPneuFlexivel = moverPneuFlexivel;
window.enviarManutencaoFlexivel = enviarManutencaoFlexivel;
window.salvarLayoutFlexivel = salvarLayoutFlexivel;
window.limparLayoutFlexivel = limparLayoutFlexivel;
window.onSelecionarVeiculoFlexivel = onSelecionarVeiculoFlexivel;
window.carregarHistoricoFlexivel = carregarHistoricoFlexivel;
window.carregarEstatisticasFlexivel = carregarEstatisticasFlexivel;
