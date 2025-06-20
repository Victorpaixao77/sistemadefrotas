// ===== ATUALIZAR LEGENDAS COM ESTADO DE ALERTA =====

// Função para atualizar todas as legendas
function atualizarLegendas() {
    try {
        // Atualizar primeira legenda (modo padrão)
        const legenda1 = document.querySelector('.legend');
        if (legenda1) {
            legenda1.innerHTML = `
                <div class="legend-item">
                    <div class="legend-color available"></div>
                    <span>Disponível</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color occupied"></div>
                    <span>Em Uso</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color alert"></div>
                    <span>Alerta</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color maintenance"></div>
                    <span>Manutenção</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color critical"></div>
                    <span>Crítico</span>
                </div>
            `;
        }

        // Atualizar segunda legenda (modo flexível)
        const legenda2 = document.querySelector('#area-modo-flexivel .legend');
        if (legenda2) {
            legenda2.innerHTML = `
                <div class="legend-item">
                    <div class="legend-color available"></div>
                    <span>Disponível</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color occupied"></div>
                    <span>Em Uso</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color alert"></div>
                    <span>Alerta</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color maintenance"></div>
                    <span>Manutenção</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color critical"></div>
                    <span>Crítico</span>
                </div>
            `;
        }

        console.log('Legendas atualizadas com sucesso!');
    } catch (error) {
        console.error('Erro ao atualizar legendas:', error);
    }
}

// Executar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Aguardar um pouco para garantir que todos os elementos estejam carregados
    setTimeout(atualizarLegendas, 100);
});

// Função para atualizar legendas quando mudar de modo
function atualizarLegendasModoFlexivel() {
    setTimeout(atualizarLegendas, 200);
} 