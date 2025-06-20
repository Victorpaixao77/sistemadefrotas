# Instruções para Adicionar Legenda de Alerta

## Problema
A legenda do estado "Alerta" não está aparecendo no modo flexível da gestão de pneus.

## Solução Manual (Imediata)

### Passo 1: Abrir o Console do Navegador
1. Abra a página de Gestão Interativa de Pneus
2. Pressione **F12** para abrir as ferramentas do desenvolvedor
3. Vá para a aba **Console**

### Passo 2: Executar o Código
Copie e cole o seguinte código no console:

```javascript
// Atualizar legendas com estado de alerta
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

        // Adicionar CSS para a cor de alerta
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
        }

        console.log('Legendas atualizadas com sucesso!');
    } catch (error) {
        console.error('Erro ao atualizar legendas:', error);
    }
}

// Executar
atualizarLegendas();
```

### Passo 3: Verificar Resultado
Após executar o código, você deve ver:
- ✅ Mensagem "Legendas atualizadas com sucesso!" no console
- ✅ Nova legenda "Alerta" com cor amarela/dourada e animação
- ✅ Legenda aparecendo tanto no modo padrão quanto no flexível

## Solução Permanente

### Opção 1: Usar Arquivo JavaScript Existente
O arquivo `js/atualizar_legendas_manual.js` já contém o código necessário. Você pode:

1. Abrir o arquivo no navegador: `http://localhost/sistema-frotas/js/atualizar_legendas_manual.js`
2. Copiar todo o conteúdo
3. Colar no console da página de gestão de pneus

### Opção 2: Editar o Arquivo Principal
Editar o arquivo `pages/gestao_interativa.php` e adicionar o código de atualização das legendas no evento `DOMContentLoaded`.

## Estados dos Pneus

Agora você terá **5 estados** na legenda:

1. **Disponível** (azul) - Pneu disponível no estoque
2. **Em Uso** (verde) - Pneu alocado e funcionando bem
3. **Alerta** (amarelo/dourado) - Pneu precisa de atenção (calibração, verificação de sulcos)
4. **Manutenção** (laranja) - Pneu em manutenção
5. **Crítico** (vermelho) - Pneu com problemas críticos

## Funcionalidades Adicionais

- **Análise IA**: Pneus em estado de alerta recebem análise inteligente
- **Notificações**: Alertas automáticos para pneus que precisam de atenção
- **Dicas Ações**: Sugestões de ações baseadas no estado do pneu
- **Animações**: Efeitos visuais para chamar atenção

## Teste

Para testar se está funcionando:
1. Execute o código no console
2. Mude para o modo flexível
3. Verifique se a legenda "Alerta" aparece
4. Aloque um pneu e verifique se os estados são aplicados corretamente 