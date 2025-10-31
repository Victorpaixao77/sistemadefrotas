/**
 * Cálculo e exibição de Lucratividade de Rotas
 */

// Função para calcular e exibir a lucratividade da rota
function calcularLucratividade(rotaId) {
    console.log('🔍 Calculando lucratividade para rota ID:', rotaId);
    
    // Buscar dados da rota via API (mesma estrutura que routes.js usa)
    fetch(`../api/route_data.php?action=view&id=${rotaId}`)
        .then(response => response.json())
        .then(data => {
            console.log('📊 Dados da rota recebidos:', data);
            
            if (data.success && data.data) {
                const route = data.data;
                
                // Buscar despesas de viagem e abastecimentos
                Promise.all([
                    fetch(`../api/despesas_viagem/view.php?rota_id=${rotaId}`)
                        .then(r => r.json())
                        .catch(e => ({ data: null })),
                    fetch(`../api/refuel_data.php?action=list&rota_id=${rotaId}`)
                        .then(r => r.json())
                        .catch(e => ({ data: [] }))
                ])
                .then(([despesasData, abastecimentosData]) => {
                    console.log('💰 Despesas de viagem:', despesasData);
                    console.log('⛽ Abastecimentos:', abastecimentosData);
                    
                    const despesas = despesasData.data || despesasData;
                    const abastecimentos = abastecimentosData.data || abastecimentosData.refuels || [];
                    
                    exibirAnaliseCompl(route, despesas, abastecimentos);
                })
                .catch(error => {
                    console.error('⚠️ Erro ao buscar dados financeiros:', error);
                    exibirAnaliseCompl(route, null, []);
                });
            } else {
                console.error('❌ Resposta da API inválida:', data);
            }
        })
        .catch(error => {
            console.error('❌ Erro ao buscar dados da rota:', error);
        });
}

// Função para exibir análise completa
function exibirAnaliseCompl(route, despesasViagem, abastecimentosArray) {
    console.log('📈 Exibindo análise de lucratividade...');
    console.log('   Rota:', route);
    console.log('   Despesas:', despesasViagem);
    console.log('   Abastecimentos:', abastecimentosArray);
    
    // Valores
    const frete = parseFloat(route.frete) || 0;
    const comissao = parseFloat(route.comissao) || 0;
    
    console.log('   Frete:', frete, 'Comissão:', comissao);
    
    // Calcular total de despesas de viagem
    let totalDespesas = 0;
    if (despesasViagem) {
        if (Array.isArray(despesasViagem)) {
            despesasViagem.forEach(desp => {
                totalDespesas += parseFloat(desp.total_despviagem || desp.total || 0);
            });
        } else if (despesasViagem.total_despviagem) {
            totalDespesas = parseFloat(despesasViagem.total_despviagem);
        }
    }
    
    // Calcular total de abastecimentos
    let totalAbastecimentos = 0;
    if (Array.isArray(abastecimentosArray)) {
        abastecimentosArray.forEach(abast => {
            totalAbastecimentos += parseFloat(abast.valor_total || 0);
        });
    }
    
    console.log('   Total Despesas:', totalDespesas);
    console.log('   Total Abastecimentos:', totalAbastecimentos);
    
    // Cálculos de lucratividade
    const receitaBruta = frete;
    const lucroBruto = frete - comissao;
    const lucroLiquido = frete - comissao - totalDespesas - totalAbastecimentos;
    const margemLiquida = receitaBruta > 0 ? (lucroLiquido / receitaBruta) * 100 : 0;
    
    console.log('💵 Valores calculados:');
    console.log('   Receita Bruta:', receitaBruta);
    console.log('   Lucro Bruto:', lucroBruto);
    console.log('   Total Despesas:', totalDespesas);
    console.log('   Total Abastecimentos:', totalAbastecimentos);
    console.log('   Lucro Líquido:', lucroLiquido);
    console.log('   Margem Líquida:', margemLiquida.toFixed(1) + '%');
    
    // Adicionar log detalhado
    console.table({
        'Frete': frete,
        'Comissão': comissao,
        'Despesas Viagem': totalDespesas,
        'Abastecimentos': totalAbastecimentos,
        'Lucro Líquido': lucroLiquido,
        'Margem': margemLiquida.toFixed(1) + '%'
    });
    
    // Calcular despesas totais para o card
    const despesasTotais = comissao + totalDespesas + totalAbastecimentos;
    
    // Atualizar cards
    const elemReceitaBruta = document.getElementById('profitReceitaBruta');
    const elemDespesasTotais = document.getElementById('profitDespesasTotais');
    const elemLucroLiquido = document.getElementById('profitLucroLiquido');
    const elemMargem = document.getElementById('profitMargem');
    
    if (elemReceitaBruta) elemReceitaBruta.textContent = formatarMoeda(receitaBruta);
    if (elemDespesasTotais) elemDespesasTotais.textContent = formatarMoeda(despesasTotais);
    if (elemLucroLiquido) {
        elemLucroLiquido.textContent = formatarMoeda(lucroLiquido);
        // Colorir baseado no lucro
        if (lucroLiquido < 0) {
            elemLucroLiquido.style.color = '#dc3545'; // Vermelho
        } else if (lucroLiquido > 0) {
            elemLucroLiquido.style.color = '#2e7d32'; // Verde
        }
    }
    if (elemMargem) {
        elemMargem.textContent = margemLiquida.toFixed(1) + '%';
        // Colorir baseado na margem
        if (margemLiquida < 0) {
            elemMargem.style.color = '#dc3545'; // Vermelho
        } else if (margemLiquida < 10) {
            elemMargem.style.color = '#ffc107'; // Amarelo
        } else if (margemLiquida < 20) {
            elemMargem.style.color = '#17a2b8'; // Azul
        } else {
            elemMargem.style.color = '#28a745'; // Verde
        }
    }
    
    // Preencher tabela detalhada
    const tbody = document.getElementById('profitabilityTableBody');
    
    if (!tbody) {
        console.error('❌ Elemento profitabilityTableBody não encontrado!');
        return;
    }
    
    let html = '';
    console.log('📋 Preenchendo tabela detalhada...');
    
    // Receita
    html += gerarLinhaTabela('💰 Receita Bruta (Frete)', receitaBruta, receitaBruta, 'success');
    html += gerarLinhaDivisoria();
    
    // Deduções
    html += gerarLinhaTabela('➖ Comissão Motorista', comissao, receitaBruta, 'danger');
    html += gerarLinhaTabela('➖ Despesas de Viagem', totalDespesas, receitaBruta, 'danger');
    html += gerarLinhaTabela('➖ Abastecimentos (Combustível)', totalAbastecimentos, receitaBruta, 'danger');
    html += gerarLinhaDivisoria();
    
    // Lucro Bruto
    html += gerarLinhaTabela('📊 Lucro Bruto', lucroBruto, receitaBruta, 'warning', 'bold');
    html += gerarLinhaDivisoria();
    
    // Lucro Líquido
    const tipoLucro = lucroLiquido >= 0 ? 'success' : 'danger';
    html += gerarLinhaTabela('✅ Lucro Líquido', lucroLiquido, receitaBruta, tipoLucro, 'bold large');
    
    tbody.innerHTML = html;
    console.log('✅ Tabela preenchida com sucesso!');
    
    // Atualizar indicador visual
    console.log('🎯 Atualizando indicador de rentabilidade...');
    atualizarIndicadorRentabilidade(margemLiquida);
    
    console.log('✅ Análise de lucratividade concluída!');
}

// Gerar linha da tabela
function gerarLinhaTabela(item, valor, receitaBase, tipo = '', estilo = '') {
    const percentual = receitaBase > 0 ? (valor / receitaBase) * 100 : 0;
    const corTexto = tipo === 'danger' ? 'color: #dc3545;' : tipo === 'success' ? 'color: #28a745;' : tipo === 'warning' ? 'color: #ffc107;' : '';
    const negrito = estilo.includes('bold') ? 'font-weight: 700;' : '';
    const tamanho = estilo.includes('large') ? 'font-size: 1.1rem;' : '';
    const bg = estilo.includes('bold') ? 'background: #f8f9fa;' : '';
    
    return `
        <tr style="${bg}">
            <td style="padding: 10px; ${corTexto} ${negrito} ${tamanho}">${item}</td>
            <td style="padding: 10px; text-align: right; ${corTexto} ${negrito} ${tamanho}">${formatarMoeda(valor)}</td>
            <td style="padding: 10px; text-align: right; ${corTexto} ${negrito}">${percentual.toFixed(1)}%</td>
        </tr>
    `;
}

// Gerar linha divisória
function gerarLinhaDivisoria() {
    return `<tr><td colspan="3" style="padding: 0;"><div style="border-top: 2px solid #dee2e6; margin: 5px 0;"></div></td></tr>`;
}

// Formatar moeda
function formatarMoeda(valor) {
    return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Atualizar indicador de rentabilidade
function atualizarIndicadorRentabilidade(margem) {
    const indicador = document.getElementById('profitabilityIndicator');
    
    if (!indicador) {
        console.error('❌ Elemento profitabilityIndicator não encontrado!');
        return;
    }
    
    // Calcular largura (0% = 0, 100% = 100%)
    // Margem negativa = 0%, 0-10% = 25%, 10-20% = 50%, 20-30% = 75%, 30%+ = 100%
    let largura = 0;
    if (margem < 0) {
        largura = 10; // Prejuízo = barra vermelha no início
    } else if (margem <= 10) {
        largura = 25 + (margem * 2.5);
    } else if (margem <= 20) {
        largura = 50 + ((margem - 10) * 2.5);
    } else if (margem <= 30) {
        largura = 75 + ((margem - 20) * 2.5);
    } else {
        largura = 100;
    }
    
    const larguraFinal = Math.min(largura, 100);
    indicador.style.width = larguraFinal + '%';
    console.log('📊 Indicador atualizado: Margem', margem.toFixed(1) + '%', '→ Largura', larguraFinal + '%');
}

// Expor função globalmente
window.calcularLucratividade = calcularLucratividade;

