// Configurações
const API_URL = '/sistema-frotas/IA/api.php';

// Função para fazer requisições à API
async function fetchAPI(route, method = 'GET', data = null) {
    try {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(`${API_URL}?route=${route}`, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Erro na requisição');
        }

        return result;
    } catch (error) {
        console.error('Erro na API:', error);
        throw error;
    }
}

// Função para atualizar estatísticas
async function atualizarEstatisticas() {
    try {
        const stats = await fetchAPI('notificacoes/estatisticas');
        
        document.getElementById('totalNotificacoes').textContent = stats.pendentes;
        document.getElementById('altaPrioridade').textContent = stats.alta_prioridade;
        document.getElementById('totalRecomendacoes').textContent = stats.recomendacoes;
        document.getElementById('totalInsights').textContent = stats.insights;
    } catch (error) {
        console.error('Erro ao atualizar estatísticas:', error);
    }
}

// Função para atualizar gráficos
async function atualizarGraficos() {
    try {
        // Gráfico de Consumo
        const dadosConsumo = await fetchAPI('analise/consumo');
        const consumoChart = new Chart(document.getElementById('consumoChart'), {
            type: 'line',
            data: {
                labels: dadosConsumo.map(d => d.mes),
                datasets: [{
                    label: 'Consumo de Combustível',
                    data: dadosConsumo.map(d => d.total_litros),
                    borderColor: '#28a745',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Gráfico de Manutenção
        const dadosManutencao = await fetchAPI('analise/manutencao');
        const manutencaoChart = new Chart(document.getElementById('manutencaoChart'), {
            type: 'bar',
            data: {
                labels: dadosManutencao.map(d => d.placa),
                datasets: [{
                    label: 'Gastos com Manutenção',
                    data: dadosManutencao.map(d => d.total_gasto),
                    backgroundColor: '#dc3545'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    } catch (error) {
        console.error('Erro ao atualizar gráficos:', error);
    }
}

// Função para atualizar alertas
async function atualizarAlertas() {
    try {
        const alertas = await fetchAPI('alertas');
        const container = document.getElementById('alertasContainer');
        
        container.innerHTML = alertas.map(alerta => `
            <div class="alert-item ${alerta.prioridade}">
                <h5>${alerta.titulo}</h5>
                <p>${alerta.mensagem}</p>
                <small>${alerta.data}</small>
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao atualizar alertas:', error);
    }
}

// Função para atualizar recomendações
async function atualizarRecomendacoes() {
    try {
        const recomendacoes = await fetchAPI('recomendacoes');
        const container = document.getElementById('recomendacoesContainer');
        
        container.innerHTML = recomendacoes.map(rec => `
            <div class="insight-card">
                <div class="insight-header">
                    <div class="insight-icon ${rec.tipo}">
                        <i class="fas fa-${getIconForType(rec.tipo)}"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">${rec.mensagem}</h5>
                        <small class="text-muted">${rec.veiculo || rec.rota}</small>
                    </div>
                </div>
                ${rec.acoes ? `
                    <ul class="mb-0">
                        ${rec.acoes.map(acao => `<li>${acao}</li>`).join('')}
                    </ul>
                ` : ''}
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao atualizar recomendações:', error);
    }
}

// Função para atualizar insights
async function atualizarInsights() {
    try {
        const insights = await fetchAPI('insights');
        const container = document.getElementById('insightsContainer');
        
        container.innerHTML = insights.map(insight => `
            <div class="insight-card">
                <div class="insight-header">
                    <div class="insight-icon ${insight.tipo}">
                        <i class="fas fa-${getIconForType(insight.tipo)}"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">${insight.mensagem}</h5>
                        <small class="text-muted">
                            ${insight.veiculo ? `${insight.veiculo} - ${insight.modelo}` : insight.rota}
                        </small>
                    </div>
                </div>
                ${insight.dados ? `
                    <div class="mt-3">
                        <pre class="mb-0"><code>${JSON.stringify(insight.dados, null, 2)}</code></pre>
                    </div>
                ` : ''}
            </div>
        `).join('');
    } catch (error) {
        console.error('Erro ao atualizar insights:', error);
    }
}

// Função para marcar notificação como lida
async function marcarNotificacaoLida(id) {
    try {
        await fetchAPI('notificacoes/marcar-lida', 'POST', { id });
        await atualizarEstatisticas();
    } catch (error) {
        console.error('Erro ao marcar notificação como lida:', error);
    }
}

// Função para marcar todas as notificações como lidas
async function marcarTodasNotificacoesLidas() {
    try {
        await fetchAPI('notificacoes/marcar-todas-lidas', 'POST');
        await atualizarEstatisticas();
    } catch (error) {
        console.error('Erro ao marcar todas as notificações como lidas:', error);
    }
}

// Função para obter ícone baseado no tipo
function getIconForType(type) {
    const icons = {
        'consumo': 'gas-pump',
        'manutencao': 'tools',
        'rota': 'route',
        'custo': 'dollar-sign',
        'documento': 'file-alt',
        'seguranca': 'shield-alt'
    };
    return icons[type] || 'info-circle';
}

// Função para atualizar todo o painel
async function atualizarPainel() {
    try {
        await Promise.all([
            atualizarEstatisticas(),
            atualizarGraficos(),
            atualizarAlertas(),
            atualizarRecomendacoes(),
            atualizarInsights()
        ]);
    } catch (error) {
        console.error('Erro ao atualizar painel:', error);
    }
}

// Atualiza o painel a cada 5 minutos
setInterval(atualizarPainel, 5 * 60 * 1000);

// Atualiza o painel quando a página carrega
document.addEventListener('DOMContentLoaded', atualizarPainel);

// Event Listeners
document.getElementById('marcarTodasLidas')?.addEventListener('click', marcarTodasNotificacoesLidas);

// Exporta funções para uso em outros arquivos
window.painelIA = {
    atualizarPainel,
    marcarNotificacaoLida,
    marcarTodasNotificacoesLidas
}; 