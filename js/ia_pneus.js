// ===== IA - ANÁLISE INTELIGENTE DE PNEUS =====

// Analisar estado do pneu e gerar recomendações
function analisarPneuIA(pneu) {
    try {
        const analise = {
            status: 'bom',
            alertas: [],
            recomendacoes: [],
            prioridade: 'baixa'
        };
        
        // Análise de quilometragem (km na instalação / vida útil)
        const kmAtual = parseFloat(pneu.km_instalacao || pneu.quilometragem || 0);
        const vidaUtil = parseFloat(pneu.vida_util_km || 80000);
        const kmLimite = vidaUtil > 0 ? vidaUtil * 0.9 : 80000;
        const kmCritico = vidaUtil > 0 ? vidaUtil : 100000;
        const kmAlerta = vidaUtil > 0 ? vidaUtil * 0.75 : 60000;
        
        if (kmAtual > kmCritico) {
            analise.status = 'critico';
            analise.alertas.push('🚨 Pneu com quilometragem CRÍTICA!');
            analise.recomendacoes.push('Troca URGENTE recomendada');
            analise.prioridade = 'alta';
        } else if (kmAtual > kmLimite) {
            analise.status = 'critico';
            analise.alertas.push('🚨 Pneu próximo do limite de quilometragem');
            analise.recomendacoes.push('Troca IMEDIATA necessária');
            analise.prioridade = 'alta';
        } else if (kmAtual > kmAlerta) {
            analise.status = 'alerta';
            analise.alertas.push('⚠️ Pneu necessita verificação de sulco');
            analise.recomendacoes.push('Verificar profundidade dos sulcos e calibração');
            analise.prioridade = 'media';
        }
        
        // Análise de idade (baseada no DOT)
        if (pneu.dot) {
            const anoDOT = parseInt(pneu.dot.substring(2, 4)) + 2000;
            const idade = new Date().getFullYear() - anoDOT;
            
            if (idade > 6) {
                analise.alertas.push('📅 Pneu com mais de 6 anos - verificar integridade');
                analise.recomendacoes.push('Considerar troca por idade');
                if (analise.prioridade === 'baixa') analise.prioridade = 'media';
            } else if (idade > 4) {
                analise.alertas.push('📅 Pneu com mais de 4 anos - verificar sulcos');
                analise.recomendacoes.push('Verificar profundidade dos sulcos');
                if (analise.status === 'bom') {
                    analise.status = 'alerta';
                    analise.prioridade = 'media';
                }
            }
        }
        
        // Análise de status
        const status = pneu.status_nome || '';
        if (status.includes('critico') || status.includes('furado')) {
            analise.status = 'critico';
            analise.alertas.push('🔴 Pneu em estado CRÍTICO!');
            analise.recomendacoes.push('Troca IMEDIATA necessária');
            analise.prioridade = 'alta';
        } else if (status.includes('gasto')) {
            analise.status = 'alerta';
            analise.alertas.push('🟡 Pneu gasto - verificar sulcos');
            analise.recomendacoes.push('Verificar profundidade dos sulcos e planejar troca');
            analise.prioridade = 'media';
        } else if (status.includes('desgaste')) {
            analise.status = 'alerta';
            analise.alertas.push('🟡 Pneu com desgaste - verificar calibração');
            analise.recomendacoes.push('Verificar pressão e alinhamento');
            analise.prioridade = 'media';
        }
        
        // Pressão: só analisa se o campo existir no payload
        if (pneu.pressao != null && pneu.pressao !== '') {
        const pressaoAtual = parseFloat(pneu.pressao);
        const pressaoIdeal = 35;
        const pressaoMinima = 30;
        
        if (pressaoAtual < pressaoMinima) {
            analise.alertas.push('💨 Pressão baixa - calibrar imediatamente');
            analise.recomendacoes.push('Calibrar pneu para pressão ideal');
            if (analise.status === 'bom') {
                analise.status = 'alerta';
                analise.prioridade = 'media';
            }
        } else if (pressaoAtual < pressaoIdeal) {
            analise.alertas.push('💨 Pressão abaixo do ideal - verificar calibração');
            analise.recomendacoes.push('Calibrar pneu para pressão ideal');
            if (analise.status === 'bom') {
                analise.status = 'alerta';
                analise.prioridade = 'media';
            }
        }
        } else if (pneu.sulco_inicial != null && parseFloat(pneu.sulco_inicial) <= 2) {
            analise.alertas.push('📏 Sulco crítico (≤ 2 mm)');
            analise.recomendacoes.push('Substituir ou recapar o pneu');
            analise.status = 'critico';
            analise.prioridade = 'alta';
        }
        
        // Análise de custo-benefício
        const custoAcumulado = calcularCustoAcumulado(pneu);
        if (custoAcumulado > 2000) {
            analise.recomendacoes.push('💰 Alto custo acumulado - avaliar recapagem');
        }
        
        return analise;
    } catch (error) {
        console.error('Erro na análise IA do pneu:', error);
        return { status: 'bom', alertas: [], recomendacoes: [], prioridade: 'baixa' };
    }
}

// Gerar notificação inteligente
function gerarNotificacaoIA(pneu, slotId) {
    try {
        const analise = analisarPneuIA(pneu);
        const notificacao = {
            titulo: '',
            mensagem: '',
            tipo: 'info',
            acoes: []
        };
        
        if (analise.status === 'critico') {
            notificacao.titulo = '🚨 ALERTA CRÍTICO - Pneu ' + pneu.numero_serie;
            notificacao.tipo = 'critico';
            notificacao.mensagem = analise.alertas.join('\n') + '\n\n' + analise.recomendacoes.join('\n');
            notificacao.acoes = ['Trocar Imediatamente', 'Agendar Manutenção', 'Verificar Estoque'];
        } else if (analise.status === 'alerta') {
            notificacao.titulo = '⚠️ ALERTA - Pneu ' + pneu.numero_serie;
            notificacao.tipo = 'alerta';
            notificacao.mensagem = analise.alertas.join('\n') + '\n\n' + analise.recomendacoes.join('\n');
            notificacao.acoes = ['Verificar Sulcos', 'Calibrar Pneu', 'Verificar Alinhamento', 'Planejar Troca'];
        } else {
            notificacao.titulo = '✅ Pneu ' + pneu.numero_serie + ' - Estado Bom';
            notificacao.tipo = 'sucesso';
            notificacao.mensagem = 'Pneu em bom estado. Continue monitorando o desgaste regularmente.';
            notificacao.acoes = ['Ver Detalhes', 'Histórico de Manutenção'];
        }
        
        return notificacao;
    } catch (error) {
        console.error('Erro ao gerar notificação IA:', error);
        return { titulo: 'Erro', mensagem: 'Erro ao analisar pneu', tipo: 'erro', acoes: [] };
    }
}

// Mostrar notificação IA
function mostrarNotificacaoIA(pneu, slotId) {
    try {
        const notificacao = gerarNotificacaoIA(pneu, slotId);
        
        // Criar modal de notificação
        const modal = document.createElement('div');
        modal.className = 'modal-notificacao-ia';
        modal.innerHTML = `
            <div class="modal-content-ia ${notificacao.tipo}">
                <div class="modal-header-ia">
                    <h3>${notificacao.titulo}</h3>
                    <span class="close-ia" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
                </div>
                <div class="modal-body-ia">
                    <p>${notificacao.mensagem}</p>
                    <div class="acoes-ia">
                        ${notificacao.acoes.map(acao => `<button onclick="executarAcaoIA('${acao}', '${slotId}')">${acao}</button>`).join('')}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Auto-remover após 10 segundos se não for crítica
        if (notificacao.tipo !== 'critico') {
            setTimeout(() => {
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 10000);
        }
    } catch (error) {
        console.error('Erro ao mostrar notificação IA:', error);
    }
}

// Executar ação da IA
function executarAcaoIA(acao, slotId) {
    try {
        const pneu = window.pneusFlexAlocados ? window.pneusFlexAlocados[slotId] : null;
        
        switch(acao) {
            case 'Trocar Imediatamente':
                alert('Ação: Trocar pneu imediatamente\n\nPróximos passos:\n1. Remover pneu do veículo\n2. Verificar estoque de reposição\n3. Alocar novo pneu\n4. Registrar troca no sistema');
                break;
            case 'Agendar Manutenção':
                alert('Ação: Agendar manutenção\n\nPróximos passos:\n1. Verificar disponibilidade da oficina\n2. Agendar data e horário\n3. Preparar pneu de reposição\n4. Notificar motorista');
                break;
            case 'Verificar Estoque':
                alert('Verificando estoque de pneus compatíveis...');
                // Aqui você pode integrar com a API de estoque
                break;
            case 'Verificar Sulcos':
                alert('Ação: Verificar sulcos do pneu\n\nChecklist:\n1. Medir profundidade dos sulcos (mínimo 1.6mm)\n2. Verificar desgaste irregular\n3. Observar sinais de alinhamento\n4. Documentar medições\n5. Registrar no sistema');
                break;
            case 'Calibrar Pneu':
                alert('Ação: Calibrar pneu\n\nPróximos passos:\n1. Verificar pressão atual\n2. Calibrar para pressão ideal (35 PSI)\n3. Verificar vazamentos\n4. Testar em movimento\n5. Registrar calibração');
                break;
            case 'Verificar Alinhamento':
                alert('Ação: Verificar alinhamento\n\nChecklist:\n1. Verificar desgaste irregular dos pneus\n2. Observar direção do veículo\n3. Testar em reta\n4. Agendar alinhamento se necessário\n5. Documentar verificação');
                break;
            case 'Planejar Troca':
                alert('Ação: Planejar troca\n\nRecomendações:\n1. Monitorar desgaste semanalmente\n2. Preparar pneu de reposição\n3. Agendar troca para próxima manutenção\n4. Documentar planejamento');
                break;
            case 'Monitorar Desgaste':
                alert('Ação: Monitorar desgaste\n\nChecklist:\n1. Verificar sulcos (mínimo 1.6mm)\n2. Observar desgaste irregular\n3. Medir pressão regularmente\n4. Registrar medições');
                break;
            case 'Verificar Histórico':
                alert('Histórico do pneu:\n\n' + buscarHistoricoPneu(pneu ? pneu.id : null));
                break;
            case 'Ver Detalhes':
                if (pneu) {
                    const detalhes = `
                        Pneu: ${pneu.numero_serie}
                        Marca: ${pneu.marca}
                        Modelo: ${pneu.modelo}
                        Medida: ${pneu.medida}
                        Status: ${pneu.status_nome || 'N/A'}
                        KM: ${pneu.km_instalacao || pneu.quilometragem || 'N/A'}
                        DOT: ${pneu.dot || 'N/A'}
                        Pressão: ${pneu.pressao || 'N/A'} PSI
                    `;
                    alert(detalhes);
                }
                break;
            case 'Histórico de Manutenção':
                alert('Histórico de manutenção:\n\n' + buscarHistoricoPneu(pneu ? pneu.id : null));
                break;
        }
        
        // Remover modal
        const modal = document.querySelector('.modal-notificacao-ia');
        if (modal) modal.remove();
    } catch (error) {
        console.error('Erro ao executar ação IA:', error);
    }
}

// Análise preditiva de desgaste
function analisePreditivaDesgaste(pneu) {
    try {
        const kmAtual = parseFloat(pneu.km_instalacao || pneu.quilometragem || 0);
        const kmPorMes = 5000; // Estimativa
        const kmRestante = 80000 - kmAtual;
        const mesesRestante = Math.floor(kmRestante / kmPorMes);
        
        return {
            kmRestante: kmRestante,
            mesesRestante: mesesRestante,
            recomendacao: mesesRestante <= 3 ? 'Troca Imediata' : 
                         mesesRestante <= 6 ? 'Planejar Troca' : 'Monitorar'
        };
    } catch (error) {
        console.error('Erro na análise preditiva:', error);
        return { kmRestante: 0, mesesRestante: 0, recomendacao: 'Erro' };
    }
}

// Calcular custo acumulado do pneu
function calcularCustoAcumulado(pneu) {
    try {
        // Simular cálculo de custo baseado em quilometragem e manutenções
        const kmAtual = parseFloat(pneu.km_instalacao || pneu.quilometragem || 0);
        const custoPorKm = 0.05; // R$ 0,05 por km
        const custoManutencao = 500; // R$ 500 por manutenção
        const manutencoesEstimadas = Math.floor(kmAtual / 40000); // Manutenção a cada 40k km
        
        return (kmAtual * custoPorKm) + (manutencoesEstimadas * custoManutencao);
    } catch (error) {
        console.error('Erro ao calcular custo acumulado:', error);
        return 0;
    }
}

// Buscar histórico do pneu
function buscarHistoricoPneu(pneuId) {
    try {
        // Simular histórico do pneu
        return `Histórico do Pneu ${pneuId}:
        
📅 Instalações:
- 15/01/2024: Instalado no veículo ABC-1234
- 10/03/2024: Manutenção preventiva
- 25/05/2024: Verificação de sulcos

🔧 Manutenções:
- 10/03/2024: Alinhamento e balanceamento
- 25/05/2024: Verificação de pressão

💰 Custos:
- Valor inicial: R$ 1.200,00
- Manutenções: R$ 300,00
- Total acumulado: R$ 1.500,00

📊 Estatísticas:
- KM total: 45.000 km
- Vida útil restante: ~35.000 km
- Desgaste médio: 0,8mm/10.000 km`;
    } catch (error) {
        console.error('Erro ao buscar histórico:', error);
        return 'Erro ao carregar histórico';
    }
} 