// Sistema de Eixos de Veículos - Gestão Interativa
// Usando API do banco de dados ao invés de arquivo JSON

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
        alert('Selecione um veículo primeiro!');
        return;
    }

    let qtd = prompt('Quantos pneus por eixo? (1 = rodado simples/2 pneus, 2 = rodado duplo/4 pneus)', '1');
    if (!qtd || isNaN(qtd)) return;
    
    qtd = parseInt(qtd);
    if (qtd < 1 || qtd > 2) {
        alert('Quantidade deve ser 1 ou 2!');
        return;
    }

    const quantidade_pneus = qtd === 1 ? 2 : 4; // Converter para quantidade real de pneus

    fetch('../gestao_interativa/api/eixos_veiculos.php?action=adicionar_eixo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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
            alert('Eixo adicionado com sucesso!');
            // Recarregar layout do banco
            onSelecionarVeiculoFlexivel(veiculoId);
        } else {
            alert('Erro ao adicionar eixo: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro ao adicionar eixo:', error);
        alert('Erro ao adicionar eixo. Tente novamente.');
    });
}

// Função para excluir eixo usando API do banco
function excluirEixo(tipo) {
    const veiculoId = veiculoSelecionado;
    if (!veiculoId) {
        alert('Selecione um veículo primeiro!');
        return;
    }

    const eixos = tipo === 'caminhao' ? eixosCaminhao : eixosCarreta;
    
    if (eixos.length === 0) {
        alert(`Não há eixos para excluir no ${tipo === 'caminhao' ? 'caminhão' : 'carreta'}.`);
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
        alert(`Todos os eixos do ${tipo === 'caminhao' ? 'caminhão' : 'carreta'} têm pneus alocados. Remova os pneus primeiro.`);
        return;
    }

    // Mostrar opções de eixos que podem ser excluídos
    let opcoes = `Eixos disponíveis para exclusão:\n\n`;
    eixosSemPneus.forEach(numero => {
        opcoes += `${numero}. Eixo ${numero}\n`;
    });
    
    if (eixosComPneus.length > 0) {
        opcoes += `\nEixos com pneus (não podem ser excluídos): ${eixosComPneus.join(', ')}`;
    }
    
    const escolha = prompt(opcoes + '\n\nDigite o número do eixo que deseja excluir:');
    const index = parseInt(escolha) - 1;
    
    if (isNaN(index) || index < 0 || !eixosSemPneus.includes(index + 1)) {
        alert('Seleção inválida!');
        return;
    }

    if (confirm(`Tem certeza que deseja excluir o Eixo ${index + 1}?`)) {
        const eixoParaExcluir = eixos[index];
        
        fetch('../gestao_interativa/api/eixos_veiculos.php?action=excluir_eixo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                eixo_id: eixoParaExcluir.id
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Eixo ${index + 1} excluído com sucesso!`);
                // Recarregar layout do banco
                onSelecionarVeiculoFlexivel(veiculoId);
            } else {
                alert('Erro ao excluir eixo: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir eixo:', error);
            alert('Erro ao excluir eixo. Tente novamente.');
        });
    }
}

// Função para alocar pneu usando API do banco
async function alocarPneuFlexivel(slotId, pneuId) {
    const veiculoId = veiculoSelecionado;
    if (!veiculoId) {
        alert('Selecione um veículo primeiro!');
        return;
    }

    // Carregar posições disponíveis
    let posicoes = [];
    try {
        console.log('Carregando posições disponíveis...');
        const response = await fetch('../gestao_interativa/api/posicoes_pneus.php', {
            credentials: 'same-origin'
        });
        
        console.log('Response status posições:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const text = await response.text();
        console.log('Response text:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
        }
        
        console.log('Dados de posições recebidos:', data);
        
        if (data.success && data.posicoes) {
            posicoes = data.posicoes;
            console.log('Posições carregadas:', posicoes.length);
        } else {
            console.error('API retornou erro:', data);
            throw new Error(data.error || 'Erro desconhecido ao carregar posições');
        }
    } catch (error) {
        console.error('Erro ao carregar posições:', error);
        alert('ERRO: Não foi possível carregar as posições de pneus.\n\n' + 
              'Detalhes: ' + error.message + '\n\n' +
              'Verifique o console do navegador (F12) para mais informações.');
        return;
    }

    // Se não há posições, mostrar erro
    if (!posicoes || posicoes.length === 0) {
        alert('ATENÇÃO: Não há posições de pneus cadastradas no sistema!\n\n' +
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
            alert('Eixo não encontrado!');
            return;
        }

        try {
            const response = await fetch('../gestao_interativa/api/eixos_veiculos.php?action=alocar_pneu', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                alert('Erro ao alocar pneu: ' + data.error);
            }
        } catch (error) {
            console.error('Erro ao alocar pneu:', error);
            alert('Erro ao alocar pneu. Tente novamente.');
        }
        return;
    }
    */

    // Criar prompt simples para seleção de posição
    let opcoes = 'Selecione a posição do pneu:\n\n';
    posicoes.forEach((pos, index) => {
        opcoes += `${index + 1}. ${pos.nome}\n`;
    });
    
    console.log('Exibindo prompt de seleção de posição...');
    const escolha = prompt(opcoes + '\nDigite o número da posição:');
    
    // Se o usuário cancelar o prompt, não continuar
    if (escolha === null || escolha === '') {
        console.log('Usuário cancelou a seleção da posição');
        return;
    }
    
    const index = parseInt(escolha) - 1;
    
    if (isNaN(index) || index < 0 || index >= posicoes.length) {
        alert('Seleção inválida!');
        return;
    }
    
    const posicaoSelecionada = posicoes[index];
    
    // Encontrar o eixo correspondente ao slot
    const [tipoEixo, idxEixo, posicaoEixo] = slotId.split('-');
    const eixos = tipoEixo === 'cavalo' ? eixosCaminhao : eixosCarreta;
    const eixo = eixos[parseInt(idxEixo)];
    
    if (!eixo) {
        alert('Eixo não encontrado!');
        return;
    }

    try {
        const response = await fetch('../gestao_interativa/api/eixos_veiculos.php?action=alocar_pneu', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
            alert('Erro ao alocar pneu: ' + data.error);
        }
    } catch (error) {
        console.error('Erro ao alocar pneu:', error);
        alert('Erro ao alocar pneu. Tente novamente.');
    }
}

// Função para remover pneu usando API do banco
function removerPneuFlexivel(slotId) {
    fetch('../gestao_interativa/api/eixos_veiculos.php?action=remover_pneu', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            slot_id: slotId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Atualizar estado local
            delete pneusFlexAlocados[slotId];
            renderizarEixosFlexivel();
            atualizarDashboardPneusEmUso();
        } else {
            alert('Erro ao remover pneu: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro ao remover pneu:', error);
        alert('Erro ao remover pneu. Tente novamente.');
    });
}

// Função para salvar layout (mantida para compatibilidade)
function salvarLayoutFlexivel(auto = false) {
    // Com o novo sistema, não precisamos mais salvar manualmente
    // Os dados são salvos automaticamente no banco
    if (!auto) {
        alert('Layout salvo automaticamente no banco de dados!');
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
    console.log('Carregando histórico flexível para veículo:', veiculoId);
    fetch(`../gestao_interativa/api/historico_alocacoes.php?veiculo_id=${veiculoId}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            console.log('Dados do histórico flexível:', data);
            const historyDiv = document.getElementById('allocationHistory');
            
            if (data.success && data.historico && data.historico.length > 0) {
                let html = '<div style="max-height: 200px; overflow-y: auto;">';
                data.historico.forEach(item => {
                    const acao = item.data_remocao ? 'Remoção' : 'Alocação';
                    const data = item.data_remocao || item.data_instalacao;
                    html += `
                        <div style="padding: 8px; border-bottom: 1px solid #ddd; font-size: 0.9em;">
                            <strong>${data}</strong><br>
                            ${acao}: ${item.numero_serie} (Pos. ${item.posicao})<br>
                            <span class="status-badge status-${item.status_nome ? item.status_nome.toLowerCase().replace(' ', '-') : 'bom'}">${item.status_nome || 'Bom'}</span>
                        </div>
                    `;
                });
                html += '</div>';
                historyDiv.innerHTML = html;
                console.log('Histórico flexível carregado com sucesso');
            } else {
                historyDiv.innerHTML = '<p>Nenhum histórico encontrado para este veículo.</p>';
                console.log('Nenhum histórico flexível encontrado');
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
    console.log('Carregando estatísticas flexível para veículo:', veiculoId);
    fetch(`../gestao_interativa/api/estatisticas_veiculo.php?veiculo_id=${veiculoId}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            console.log('Dados das estatísticas flexível:', data);
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
                console.log('Estatísticas flexível carregadas com sucesso');
            } else {
                statsDiv.innerHTML = '<p>Erro ao carregar estatísticas.</p>';
                console.log('Erro ao carregar estatísticas flexível:', data.error);
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
window.salvarLayoutFlexivel = salvarLayoutFlexivel;
window.limparLayoutFlexivel = limparLayoutFlexivel;
window.onSelecionarVeiculoFlexivel = onSelecionarVeiculoFlexivel;
window.carregarHistoricoFlexivel = carregarHistoricoFlexivel;
window.carregarEstatisticasFlexivel = carregarEstatisticasFlexivel;
