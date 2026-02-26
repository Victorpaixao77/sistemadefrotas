# BI Frota – Lista completa: o que foi colocado e o que ainda dá para colocar

---

## Parte 1 – Tudo que JÁ FOI COLOCADO no BI

### Acesso e estrutura
- URL única: `pages/bi.php` (redirecionamento de `indicadores_desempenho.php`).
- Permissão: Relatórios avançados (`access_advanced_reports`).
- Link "BI Frota" no header (para quem tem permissão).
- Layout: sidebar, header, tema claro/escuro, conteúdo que respeita a largura da tela (sem overflow ao abrir/fechar menu).

### Filtros
- **Visão:** Geral, Indicadores de Desempenho, Rotas, Abastecimento, Manutenção, Despesas de viagem, Despesas fixas.
- **Ano:** ano atual até 4 anos atrás.
- **Mês:** Todos ou jan–dez.
- **Aplicar** – recarrega dados com os filtros.
- **Mês atual** – atalho para ano e mês atuais.
- **Permalink** – filtros salvos na URL (`?visao=...&ano=...&mes=...`) para compartilhar e ao abrir a página.

### API e dados
- **API:** `api/performance_indicators.php` com parâmetros `visao`, `ano`, `mes`.
- **Período real:** quando ano (e opcionalmente mês) são enviados, a API busca dados daquele período no banco (não só “últimos 12 meses”).
- **Comparação:** resposta inclui `comparacao.mes_anterior` e `comparacao.mesmo_mes_ano_anterior` para cálculo de variações.
- **Normalização:** valores monetários com `round(..., 2)` antes do JSON (sem floats longos).
- **Cache:** tabela `bi_cache` com TTL 15 min; parâmetro `?nocache=1` para forçar recálculo.
- **Dados incompletos:** API retorna `dados_incompletos` (rotas sem despesas, abastecimentos sem litros, manutenções sem tipo); BI exibe aviso quando há incompletos.
- **Labels de mês:** em português (Jan, Fev, Mar, …) na API.

### Visão Geral
- **Score da frota (0–100):** índice único com classificação Bom/Médio/Atenção (margem, semáforo, crescimento).
- **Indicadores de saúde (4 cards):** Custo por KM, Lucro por KM, Margem operacional (%), Custo por Rota.
- **Semáforo:** margem &lt;10% vermelho, 10–20% amarelo, &gt;20% verde.
- **KPIs (11 cards)** com comparação: vs mês anterior e vs mesmo mês do ano anterior (▲/▼ e %).
- **Gráficos:** Rotas e Faturamento no tempo, Faturamento por mês, Km por mês, Abastecimentos e Gasto no tempo, Top veículos.
- **Tabela panorama mensal** com linha de totais.
- **Ponto de equilíbrio (break-even):** faturamento mínimo para empatar e número de rotas para empatar (referência no último mês do período).
- **Tendências:** “3 meses consecutivos de queda de margem” e “Tendência de alta nos custos de combustível”.
- **Simulador “E se…”:** campos Diesel (%) e Comissão (%); exibe lucro atual, lucro simulado e impacto em R$ e %.
- **Custo/km – histórico:** gráfico de evolução do custo/km mês a mês.
- **Custo/km por veículo:** tabela (Veículo, KM, Gasto abast.+manut., Custo/km).
- **Alertas ativos no período:** lista com links para a visão filtrada (abastecimento, manutenção, rotas).
- **Insights automáticos:** textos com causa principal (ex.: “Lucro caiu X% principalmente por aumento de combustível (+Y%)”).
- **Exportar:** botão “Exportar CSV” na tabela panorama e “Exportar PNG” no primeiro gráfico.

### Visão Indicadores de Desempenho
- Tela dedicada com tabela de indicadores em 4 blocos: Financeiro, Operacional, Custos, Alertas.
- Botões Atualizar e Exportar (Excel/CSV).
- Carregamento com ano/mês dos filtros.

### Visões Rotas, Abastecimento, Manutenção, Despesas de viagem, Despesas fixas
- KPIs com comparação (vs mês anterior e vs mesmo mês ano anterior) quando a API envia `comparacao`.
- Gráficos e tabela por visão, com totais no pé da tabela.
- **Rotas:** ranking Top 5 mais e Top 5 menos lucrativas; gráfico Lucro/KM no tempo.
- **Abastecimento:** consumo médio (KM/L), custo combustível por KM, alertas (consumo abaixo da média, aumento brusco), tabela consumo por veículo.
- **Manutenção:** custo por KM, preventiva x corretiva (qtd e valor), veículos críticos (custo acima da média).
- **Despesas de viagem:** despesa por KM, gráfico pizza por tipo (Pedágio, Alimentação, Hospedagem, Outros).
- **Despesas fixas:** fixas/KM e impacto no faturamento (%).

### UX
- Estado de carregamento (“Carregando…”).
- Mensagem “Nenhum dado no período selecionado” quando a tabela está vazia.
- Tratamento de erro da API (mensagem exibida ao usuário).
- Alertas clicáveis que levam à visão e período corretos.

---

## Parte 2 – O que AINDA DÁ PARA COLOCAR no BI

### Dados e API
- **Intervalo personalizado:** opção “Últimos 6 meses”, “Últimos 12 meses”, “Ano X” (e semestre) para padronizar comparações.
- **Lucro completo:** incluir manutenção e despesas fixas no lucro operacional (alinhar com tela de Lucratividade).
- **Cache:** já existe tabela; opcional Redis para escala maior; expiração/invalidação sob demanda (ex.: ao salvar nova rota).
- **DECIMAL(12,2):** migração das colunas monetárias no banco para evitar qualquer float.

### Novas visões
- **Visão Motoristas:** KPIs e gráficos por motorista (rotas, km, frete, comissão, top motoristas).
- **Visão Contas a pagar / Financeiro:** contas pagas por mês, a vencer, inadimplência (se houver módulo).
- **Visão Multas:** resumo por mês e por veículo/motorista (se existir módulo).
- **Visão Pneus:** custos de pneu e manutenção de pneu (se houver `pneu_manutencao` ou equivalente).

### Gráficos e tabelas
- **Exportar em todas as visões:** botão CSV/Excel em cada tabela e PNG em cada gráfico (hoje só panorama e primeiro gráfico).
- **Ordenação na tabela:** ordenar por coluna (clique no cabeçalho) com seta de indicação.
- **Gráficos vazios:** mensagem “Sem dados no período” em vez de gráfico vazio ou quebrado.
- **Cores por tema:** garantir que todos os gráficos respeitem tema claro/escuro (Chart.js + theme).

### Simulador “E se…”
- Mais cenários: despesas de viagem (+X%), manutenção (+X%), faturamento (-X%).
- Impacto por rota ou por veículo (opcional).

### Tendências e alertas
- Mais regras: “4 meses de alta no custo de manutenção”, “queda de rotas por 2 meses”.
- **Alertas persistentes** já existem; evoluir para central única (ex.: página ou painel só de alertas) com filtros.

### IA (fase 2)
- **Nível 1 (regras):** prejuízo recorrente, sugestão de redução de custos, priorizar manutenção preventiva.
- **Nível 2 (LLM opcional):** texto automático de relatório mensal; explicação em linguagem natural (“Principal problema este mês: custo de pedágio”).

### Para venda e produção
- **Auditoria / log:** quem acessou o BI, quando, empresa (se houver tabela de log).
- **Permissão específica:** permissão `access_bi` separada de “Relatórios avançados” (opcional).
- **Relatório PDF executivo:** resumo mensal (Score, Margem, Alertas, Top 3 problemas) para download.
- **Dados de demonstração:** botão “Carregar dados de exemplo” para trials e demonstrações.

---

## Parte 3 – Resumo em tabela

| Item | Status | Observação |
|------|--------|------------|
| Filtros (visão, ano, mês, aplicar, mês atual) | ✅ | Permalink na URL |
| API com período real (ano/mês) | ✅ | Comparação incluída |
| Comparação (vs mês ant., vs ano ant.) | ✅ | Em todos os KPIs |
| Normalização (round) e cache (bi_cache) | ✅ | TTL 15 min |
| Dados incompletos (aviso) | ✅ | Rotas, abast., manut. |
| Score da frota (0–100) | ✅ | Visão Geral |
| Ponto de equilíbrio (break-even) | ✅ | Visão Geral |
| Tendências (3 meses) | ✅ | Margem e combustível |
| Simulador “E se…” (diesel, comissão) | ✅ | Visão Geral |
| Custo/km histórico + por veículo | ✅ | Visão Geral |
| Alertas clicáveis | ✅ | Link para visão filtrada |
| Insights com causa (ex.: combustível) | ✅ | Visão Geral |
| Exportar CSV (panorama) e PNG (gráfico) | ✅ | Parcial |
| Indicadores de Desempenho (4 blocos) | ✅ | Tela dedicada |
| Visões Rotas, Abast., Manut., Desp. viagem, Desp. fixas | ✅ | Com comparação e extras |
| Exportar em todas as visões | Pendente | CSV + PNG em cada bloco |
| Ordenação na tabela | Pendente | Clique no cabeçalho |
| Visão Motoristas | Pendente | — |
| Relatório PDF executivo | Pendente | — |
| Dados de demonstração | Pendente | — |
| IA nível 1/2 (regras / LLM) | Pendente | Fase 2 |

---

## Parte 4 – Documentos relacionados

- **BI_FROTA_INVENTARIO_E_MELHORIAS.md** – visão geral do BI e melhorias (parte desatualizada; usar esta lista como referência atual).
- **BI_ROADMAP_O_QUE_FALTA.md** – roadmap técnico e de negócio (cache, simulador, custo/km, etc.).
- **ANALISE_MANUTENCAO.md** – análise do módulo de Manutenção e melhorias sugeridas.

Para priorizar: exportar em todas as visões, ordenação na tabela e relatório PDF têm alto impacto para o usuário final; Visão Motoristas e dados de demonstração ajudam na venda.
