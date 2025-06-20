// ===== ADICIONAR ALERTA AUTOMATICAMENTE =====
// Este arquivo adiciona o estado "Alerta" nas legendas automaticamente

(function() {
    'use strict';
    
    // Função para adicionar o estado "Alerta" nas legendas
    function adicionarAlertaLegendas() {
        try {
            console.log('🔄 Adicionando estado "Alerta" nas legendas...');
            
            // Encontrar todas as legendas
            const legendas = document.querySelectorAll('.legend');
            
            legendas.forEach((legenda, index) => {
                // Verificar se já tem "Alerta"
                if (!legenda.innerHTML.includes('Alerta')) {
                    console.log(`📋 Atualizando legenda ${index + 1}...`);
                    
                    // Adicionar "Alerta" após "Em Uso"
                    let html = legenda.innerHTML;
                    
                    // Substituir "Em Uso" por "Em Uso" + "Alerta"
                    const padraoEmUso = /<div class="legend-item">\s*<div class="legend-color occupied"><\/div>\s*<span>Em Uso<\/span>\s*<\/div>/g;
                    const novoEmUso = `<div class="legend-item">
                                        <div class="legend-color occupied"></div>
                                        <span>Em Uso</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color alert"></div>
                                        <span>Alerta</span>
                                    </div>`;
                    
                    html = html.replace(padraoEmUso, novoEmUso);
                    legenda.innerHTML = html;
                    
                    console.log(`✅ Legenda ${index + 1} atualizada`);
                } else {
                    console.log(`ℹ️ Legenda ${index + 1} já tem "Alerta"`);
                }
            });
            
            // Adicionar CSS para a cor amarela se não existir
            if (!document.querySelector('#alert-css')) {
                console.log('🎨 Adicionando estilos CSS para alerta...');
                const style = document.createElement('style');
                style.id = 'alert-css';
                style.textContent = `
                    .legend-color.alert {
                        background: #ffd700;
                        border: 1px solid #b8860b;
                    }
                `;
                document.head.appendChild(style);
                console.log('✅ Estilos CSS adicionados');
            }
            
            console.log('🎉 Estado "Alerta" adicionado com sucesso!');
            
        } catch (error) {
            console.error('❌ Erro ao adicionar estado "Alerta":', error);
        }
    }
    
    // Executar quando o DOM estiver carregado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', adicionarAlertaLegendas);
    } else {
        adicionarAlertaLegendas();
    }
    
    // Também executar quando mudar para modo flexível
    document.addEventListener('click', function(e) {
        if (e.target && e.target.textContent.includes('Modo Flexível')) {
            setTimeout(adicionarAlertaLegendas, 100);
        }
    });
    
})(); 