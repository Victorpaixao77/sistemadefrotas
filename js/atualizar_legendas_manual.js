// ===== ATUALIZAR LEGENDAS MANUALMENTE =====
// Execute este código no console do navegador (F12)

console.log('Atualizando legendas...');

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
            console.log('✅ Primeira legenda atualizada');
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
            console.log('✅ Segunda legenda atualizada');
        }

        // Adicionar CSS para a cor de alerta se não existir
        if (!document.querySelector('#alert-css')) {
            const style = document.createElement('style');
            style.id = 'alert-css';
            style.textContent = `
                .legend-color.alert {
                    background: linear-gradient(45deg, #ffd700, #ffed4e);
                    box-shadow: 0 0 10px rgba(255, 215, 0, 0.6);
                    animation: alertPulse 2s infinite;
                }
                
                @keyframes alertPulse {
                    0%, 100% { box-shadow: 0 0 10px rgba(255, 215, 0, 0.6); }
                    50% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.8); }
                }
                
                .legend-item:has(.legend-color.alert) span {
                    color: #b8860b;
                    font-weight: 600;
                }
            `;
            document.head.appendChild(style);
            console.log('✅ CSS de alerta adicionado');
        }

        console.log('🎉 Todas as legendas foram atualizadas com sucesso!');
        return true;
    } catch (error) {
        console.error('❌ Erro ao atualizar legendas:', error);
        return false;
    }
}

// Executar imediatamente
atualizarLegendas();

// Também executar quando mudar para modo flexível
const modoFlexivelBtn = document.querySelector('button[onclick*="modoFlexivel"]');
if (modoFlexivelBtn) {
    modoFlexivelBtn.addEventListener('click', function() {
        setTimeout(atualizarLegendas, 500);
    });
}

console.log('📋 Para executar novamente, digite: atualizarLegendas()'); 