# BI Frota – O que tem hoje e o que dá para melhorar

## O que tem no BI hoje

### Acesso e layout
- **URL:** `pages/bi.php` (antigo `indicadores_desempenho.php` redireciona para aqui)
- **Permissão:** Relatórios avançados (`access_advanced_reports`)
- **Ícone no header:** Link "BI Frota" ao lado do ícone IA (só para quem tem permissão)
- **Layout:** Menu lateral (sidebar_pages), header, tema claro/escuro, filtros no topo (Visão, Ano, Mês, Aplicar) ao lado do título

### Filtros (barra no topo)
| Filtro | Opções | Comportamento |
|--------|--------|----------------|
| **Visão** | Geral, Indicadores de Desempenho, Rotas, Abastecimento, Manutenção, Despesas de viagem, Despesas fixas | Altera conteúdo; Indicadores de Desempenho = tela só com tabela de indicadores |
| **Ano** | Ano atual até 4 anos atrás | Filtra dados pelo ano (no front, sobre os últimos 12 meses da API) |
| **Mês** | Todos ou jan–dez | Filtra por mês quando escolhido |
| **Botão Aplicar** | — | Recarrega dados com os filtros; trocar Visão/Ano/Mês também recarrega |

### Visão **Geral**
- **Indicadores de Saúde da Frota (4 cards):** Custo por KM, Lucro por KM, Margem operacional (%), Custo por Rota. **Semáforo:** margem &lt;10% vermelho, 10–20% amarelo, &gt;20% verde.
- **KPIs (11 cards):** Total Rotas, Km rodados, Faturamento (Frete), Comissão, Abastecimentos, Gasto Abast., Desp. viagem, Manutenção, Desp. fixas, Ticket médio, Lucro oper.
- **Gráficos:** Rotas e Faturamento no tempo (barras), Faturamento por mês (linha), Km por mês (barras), Abastecimentos e Gasto no tempo (barras), Top veículos (rotas e km)
- **Tabela:** Panorama mensal – colunas: Mês, Rotas, Km, Frete, Comissão, Abast., Gasto abast., Desp. viagem, Manut., Desp. fixas, Lucro oper.

### Visão **Rotas**
- **KPIs:** Total Rotas, Km rodados, Faturamento, Comissão, Ticket médio, Lucro oper.
- **Gráficos:** Rotas e Faturamento, Frete por mês, Km por mês, Top veículos (rotas e km)
- **Tabela:** Mês, Rotas, Km, Frete, Comissão, Lucro oper.

### Visão **Abastecimento**
- **KPIs:** Total Abastecimentos, Gasto Total, Média por abast., Veículos
- **Gráficos:** Abastecimentos e Gasto no tempo, Gasto por mês, Quantidade por mês, Top veículos (abastecimento)
- **Tabela:** Mês, Qtde Abast., Gasto (R$)

### Visão **Manutenção**
- **KPIs:** Total Manutenções, Custo Total, Média por manut., Veículos
- **Gráficos:** Custo por mês (barras + qtde), Custo por mês (linha), Quantidade por mês, Top veículos (custo manutenção)
- **Tabela:** Mês, Qtde, Custo (R$)

### Visão **Despesas de viagem**
- **KPIs:** Total Desp. Viagem, Rotas (período), Média por rota
- **Gráficos:** Despesas por mês (barras), Evolução (linha), Por mês (barras)
- **Tabela:** Mês, Total (R$)

### Visão **Despesas fixas**
- **KPIs:** Qtde Desp. Fixas, Total Pago, Média por despesa
- **Gráficos:** Valor e quantidade por mês, Total pago por mês (linha), Quantidade por mês
- **Tabela:** Mês, Qtde, Total pago (R$)

### API
- **Arquivo:** `api/performance_indicators.php`
- **Parâmetros:** `visao` (geral | rotas | abastecimento | manutencao | despesas_viagem | despesas_fixas)
- **Retorno:** Últimos 12 meses; `historico_mensal`, `mes_atual`, `veiculos_top`, `veiculos_top_abastecimento`, `veiculos_top_manutencao`, `labels`
- **Filtro Ano/Mês:** Aplicado no front (JavaScript) sobre os 12 meses; a API não recebe ano/mês

### Tecnologias
- Chart.js para gráficos
- Fetch à API com `credentials: same-origin`
- CSS com variáveis do sistema (theme.css, responsive.css)
- theme.js e sidebar.js para tema e menu

---

## O que dá para melhorar

### Dados e API
1. **Filtro Ano/Mês na API** – Enviar ano (e opcionalmente mês) para a API e buscar só aquele período no banco, em vez de sempre “últimos 12 meses” e filtrar no front. Assim dá para ver 2022, 2021 etc. com dados reais.
2. **Intervalo personalizado** – Opção “Últimos 6 meses”, “Últimos 12 meses”, “Ano X” (e talvez “Semestre”) para padronizar comparações.
3. **Comparação com período anterior** – Ex.: “Fev/2025 vs Fev/2024” ou “Mês atual vs mês anterior” em KPIs (variação % ou valor).
4. **Lucro no BI** – Incluir manutenção e despesas fixas no cálculo do lucro (hoje o lucro operacional só considera frete, comissão, abast. e desp. viagem). Alinhar fórmula com a tela de Lucratividade.
5. **Cache / performance** – Para muitos acessos, considerar cache (arquivo ou Redis) dos agregados por mês por empresa, com TTL (ex.: 5–15 min).

### Novas visões / blocos
6. **Visão Motoristas** – KPIs e gráficos por motorista (rotas, km, frete, comissão, talvez “top motoristas”).
7. **Visão Contas a pagar / Financeiro** – Contas pagas por mês, a vencer, inadimplência (se houver tabela de contas).
8. **Visão Multas** – Se existir módulo de multas, resumo por mês e por veículo/motorista.
9. **Visão Pneus** – Custos de pneu/manutenção de pneu (se houver `pneu_manutencao` ou equivalente).
10. **Geral: mais um bloco** – Ex.: “Resumo financeiro” (receita vs despesas totais) ou mini gráfico de evolução do lucro.

### Gráficos e tabelas
11. **Exportar** – Botão “Exportar tabela” (CSV/Excel) e “Exportar gráfico” (PNG ou PDF) em cada visão.
12. **Legendas e tooltips** – Tooltips nos gráficos sempre em pt-BR (valor formatado R$, datas por extenso) e legendas claras.
13. **Tabela com totais** – Linha “Total” no pé da tabela em cada visão (soma das colunas numéricas do período filtrado).
14. **Ordenação na tabela** – Ordenar por coluna (ex.: clicando no cabeçalho) e indicador visual (seta).
15. **Gráficos vazios** – Quando não houver dados, mostrar mensagem “Sem dados no período” em vez de gráfico vazio ou quebrado.
16. **Cores por tema** – Garantir que as cores dos gráficos respeitem tema claro/escuro (Chart.js já tem suporte no theme.js).

### UX e acessibilidade
17. **Estado de carregamento** – Manter “Carregando…” bem visível e desabilitar filtros durante o request.
18. **Mensagem “Sem dados”** – Se a API retornar vazio ou só zeros, exibir texto único “Nenhum dado no período selecionado” em vez de vários zeros.
19. **Permalink / compartilhar** – Salvar filtros na URL (ex.: `?visao=rotas&ano=2024&mes=3`) para poder compartilhar ou favoritar uma visão específica.
20. **Atalho “Hoje / Mês atual”** – Botão rápido que aplica ano e mês atuais.
21. **Labels de mês em português** – Garantir que todos os labels de mês (Jan/2025 etc.) usem locale pt-BR na API e no front.

### Código e manutenção
22. **Comentário no topo de `bi.php`** – Atualizar para “BI Frota – Dashboard de indicadores” (e remover “Indicadores de Desempenho - teste”).
23. **Tratamento de erro da API** – Exibir mensagem amigável quando a API retornar 500 ou JSON inválido (além do `.catch` atual).
24. **Evitar session_start duplo** – Incluir `session_start()` só uma vez (ex.: em config ou no redirect de `indicadores_desempenho.php` não inicia sessão), para evitar notice de “session already active” nos logs.

### Segurança e permissões
25. **Auditoria** – Registrar acesso ao BI (quem, quando, empresa) no log de acessos, se o sistema já tiver essa tabela.
26. **Permissão específica (opcional)** – Se no futuro quiser separar “Relatórios avançados” de “Acesso ao BI”, criar permissão `access_bi` e usar só no BI.

---

## Resumo rápido

| Onde | O que tem | Melhoria principal |
|------|-----------|---------------------|
| Filtros | Visão, Ano, Mês, Aplicar | Enviar ano/mês para a API; permalink na URL |
| Geral | 11 KPIs, 5 gráficos, tabela completa | Comparação período anterior; exportar |
| Rotas / Abast. / Manut. / Desp. viagem / Desp. fixas | KPIs + gráficos + tabela por visão | Totais na tabela; exportar CSV/PNG |
| API | Últimos 12 meses, 6 visões | Parâmetros ano/mês; cache; lucro completo |
| UX | Loading, tema, responsivo | “Sem dados”; botão “Mês atual”; labels pt-BR |

Se quiser, podemos priorizar 3–5 itens e implementar em sequência (por exemplo: filtro ano/mês na API, totais na tabela, exportar CSV, mensagem “Sem dados” e permalink).

---

## Roadmap BI – Leitura inteligente (o que falta de verdade)

**Ideia central:** O BI mostra *o que aconteceu*; falta mostrar *por que*, *se está bom ou ruim* e *o que fazer agora*.

### A) BI Geral (Visão Geral) – implementado parcialmente
- **Já feito:** 4 cards **Indicadores de Saúde da Frota:** Custo por KM, Lucro por KM, Margem Operacional (%), Custo por Rota.
- **Já feito:** **Semáforo de desempenho** (margem &lt;10% vermelho, 10–20% amarelo, &gt;20% verde).
- Manter: totais, gráficos históricos, panorama mensal.

### B) BI Rotas
- Ranking: Top 5 rotas mais lucrativas e Top 5 menos lucrativas (KM, Frete, Custo total, Lucro, Margem %).
- Gráfico: Lucro por KM (rota) ao longo do tempo.

### C) BI Abastecimento
- Consumo médio (KM/L) geral e por veículo.
- Custo de combustível por KM (gasto total ÷ KM).
- Alertas: veículo com consumo fora da média; aumento brusco de gasto mês a mês.

### D) BI Manutenção
- Custo de manutenção por KM (total manut ÷ KM).
- Manutenção corretiva x preventiva (quantidade e valor).
- Veículos críticos: maior custo acumulado e custo acima da média da frota.

### E) BI Despesas de Viagem
- Despesa média por KM (desp viagem ÷ KM).
- Despesa por tipo: gráfico pizza (Pedágio, Alimentação, Hospedagem, Outros).

### F) BI Despesas Fixas
- Impacto fixas/KM (fixas ÷ KM).
- Impacto no lucro (%) (fixas ÷ Faturamento).

### G) Indicadores de Desempenho (tela dedicada)
- Reorganizar em 4 blocos: **Financeiro** (margem, lucro/KM, ticket, crescimento), **Operacional** (KM/veículo, rotas/veículo, utilização frota), **Custos** (custo/KM, combustível/KM, manut/KM), **Alertas** (margem negativa, custo fora do padrão, queda de faturamento).

### H) BI + IA (diferencial)
- Textos automáticos em linguagem natural, ex.:  
  “⚠️ O lucro operacional caiu 35% em relação ao mês anterior.”  
  “⛽ O consumo médio piorou em 18% no veículo X.”  
  “📉 A rota Y apresenta margem negativa recorrente.”

---

## Status do roadmap (o que já foi feito)

| Tela | Item | Status |
|------|------|--------|
| **A) Geral** | 4 cards (Custo/KM, Lucro/KM, Margem %, Custo/Rota) | ✅ Feito |
| **A) Geral** | Semáforo (margem &lt;10% / 10–20% / &gt;20%) | ✅ Feito |
| **B) Rotas** | Ranking Top 5 mais e Top 5 menos lucrativas | ✅ Feito |
| **B) Rotas** | Gráfico Lucro/KM ao longo do tempo | ✅ Feito |
| **C) Abastecimento** | Consumo médio (KM/L) geral | ✅ Feito |
| **C) Abastecimento** | Custo combustível por KM | ✅ Feito |
| **C) Abastecimento** | Alertas (consumo fora da média; aumento brusco) | ✅ Feito |
| **D) Manutenção** | Custo manutenção por KM | ✅ Feito |
| **D) Manutenção** | Preventiva x Corretiva (qtd + valor) | ✅ Feito (via `tipos_manutencao.nome` com "Preventiva") |
| **D) Manutenção** | Veículos críticos (acima da média) | ✅ Feito |
| **E) Desp. Viagem** | Despesa média por KM | ✅ Feito |
| **E) Desp. Viagem** | Gráfico pizza por tipo | ✅ Feito (descarga, pedágios, caixinha, etc.) |
| **F) Desp. Fixas** | Fixas/KM e impacto no faturamento (%) | ✅ Feito |
| **G) Indic. Desempenho** | 4 blocos (Financeiro, Operacional, Custos, Alertas) | ✅ Feito (blocos + seção Alertas) |
| **H) BI + IA** | Insights automáticos (textos) | ✅ Feito (na visão Geral) |

---

## O que ainda falta (opcional / refinamento)

1. ~~**Indicadores de Desempenho em 4 blocos**~~ **Feito:** tabela com blocos 🔹 Financeiro, 🔹 Operacional, 🔹 Custos e seção 🔹 Alertas (margem negativa, queda faturamento, custo/KM acima da média).

2. ~~**Consumo por veículo (lista)**~~ **Feito:** na visão Abastecimento há tabela “Consumo por veículo” (Veículo, KM, Litros, KM/L).

3. ~~**Despesas de viagem – nomes do doc**~~ **Feito:** gráfico pizza em 4 grupos: Pedágio, Alimentação, Hospedagem, Outros (mapeados a partir dos campos do banco).

4. **Tipos de manutenção (preventiva/corretiva)**  
   A API usa `tipos_manutencao`: se o **nome** do tipo contiver “Preventiva”, entra como preventiva; o restante como corretiva. Convém ter em `tipos_manutencao` pelo menos um tipo com “Preventiva” no nome.

5. ~~**Melhorias de UX**~~ **Feito:** permalink (filtros na URL: `?visao=...&ano=...&mes=...`), botão “Mês atual”, totais no pé da tabela, mensagem “Nenhum dado no período selecionado” quando a tabela está vazia.
