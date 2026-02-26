# Manutenção – O que tem e o que dá para melhorar

## Visão geral – Lista completa (tudo em um lugar)

| # | Item | Status | Observação |
|---|------|--------|------------|
| **Acesso e telas** |
| 1 | Página principal Manutenções | ✅ Existe | `pages/manutencoes.php` – listagem, dashboard, análise |
| 2 | Menu Manutenções no sidebar | ✅ Existe | sidebar_pages |
| 3 | Manutenção de pneus | ✅ Existe | `manutencao_pneus.php` |
| 4 | Calendário de manutenção | ✅ Existe | `calendario/api/calendario_manutencao.php` |
| 5 | API CRUD manutenções | ✅ Existe | `api/manutencoes.php` – GET, POST, PUT, DELETE |
| 6 | API dados para gráficos | ✅ Existe | `get_maintenance_data.php`, `api/maintenance_data.php` |
| **Dashboard e KPIs** |
| 7 | Total de manutenções no mês | ✅ Existe | Primeiro/último dia do mês |
| 8 | Total preventivas no mês | ✅ Existe | Contagem por tipo |
| 9 | Total corretivas no mês | ✅ Existe | Contagem por tipo |
| 10 | Total custos no mês | ✅ Existe | Soma de valor |
| 11 | MTBF (tempo médio entre falhas) | ✅ Existe | Últimos 6 meses |
| 12 | MTTR (tempo médio para reparos) | ✅ Existe | Usa data_conclusao |
| 13 | Custo/KM | ✅ Existe | Pode ser 12 meses ou histórico; período selecionável falta |
| 14 | Top veículos (manutenções/custo) | ✅ Existe | Top 5 |
| 15 | Componentes com mais falhas | ✅ Existe | Até 10 |
| 16 | Status das manutenções (gráfico) | ✅ Existe | Por status_manutencao |
| 17 | Evolução mensal (gráfico) | ✅ Existe | Últimos 12 meses |
| **Listagem** |
| 18 | Tabela Data, Veículo, Tipo, Descrição, Fornecedor, Status, Valor, Ações | ✅ Existe | |
| 19 | Paginação | ✅ Existe | Fixa 5 por página |
| 20 | Filtro por período (data início/fim) | ❌ Falta | Só “mês atual” no dashboard |
| 21 | Paginação configurável (10, 25, 50) | ❌ Falta | |
| 22 | Filtros na URL (permalink) | ❌ Falta | Perde estado ao recarregar |
| 23 | Ordenação por coluna (cabeçalho clicável) | ❌ Falta | |
| 24 | Filtros: busca, veículo, tipo, status, fornecedor | ✅ Existe | Client-side na tabela |
| 25 | Botão Exportar (CSV/Excel) | ⚠ Parcial | Botão existe; garantir export com filtros |
| **CRUD e formulário** |
| 26 | Nova / Editar / Ver / Excluir manutenção | ✅ Existe | Modal + API |
| 27 | Campos: data, veículo, tipo, componente, status, km, valor, descrição, etc. | ✅ Existe | |
| 28 | data_conclusao obrigatória quando status Concluída | ❌ Falta | MTTR fica incorreto se NULL |
| 29 | Tipo obrigatório (evitar dados incompletos) | ⚠ Parcial | Pode haver NULL |
| **Dados e consistência** |
| 30 | Regra Preventiva/Corretiva unificada (LIKE '%preventiva%') | ⚠ Parcial | BI usa; dashboard em alguns pontos pode usar = 'Preventiva' |
| 31 | Custo/KM com período selecionável (alinhado BI) | ❌ Falta | 12 meses fixo ou sempre |
| **Planos e gestão preventiva** |
| 32 | Tabela planos_manutencao | ✅ Implementado | `sql/create_planos_manutencao.sql` |
| 33 | API planos (GET, POST, PUT, DELETE) | ✅ Implementado | `api/planos_manutencao.php` |
| 34 | Atualizar ultimo_km/ultima_data ao concluir preventiva | ✅ Implementado | Em `api/manutencoes.php` |
| 35 | Tela para cadastrar/editar planos | ❌ Falta | Só API existe |
| 36 | Próximas manutenções (vence por km ou data) | ✅ Implementado | `maintenance_alertas_score.php` + card na página |
| 37 | Alertas inteligentes (3+ corretivas mesmo componente, etc.) | ✅ Implementado | Mesmo include + card |
| **Score e impacto** |
| 38 | Score técnico do veículo (0–100) | ✅ Implementado | Saudável / Atenção / Crítico – card na página |
| 39 | Impacto da manutenção no lucro (12 meses) | ✅ Implementado | Card “Impacto no Lucro” |
| 40 | Impacto no lucro com período selecionável | ❌ Falta | Hoje 12 meses fixo |
| 41 | “Lucro sem corretivas” (simulação) | ❌ Falta | |
| **Relatórios e integração** |
| 42 | BI – Visão Manutenção | ✅ Existe | KPIs, gráficos, custo/km, preventiva x corretiva |
| 43 | Histórico + custo acumulado por veículo na tela Manutenções | ❌ Falta | Só no BI |
| 44 | Custo/km por veículo na listagem/card | ❌ Falta | |
| 45 | Relatório por veículo (página ou modal) | ❌ Falta | |
| 46 | Integração listagem ↔ calendário (links) | ❌ Falta | |
| 47 | Anexos (NF, foto do serviço) | ❌ Falta | |
| **Checklist e diferenciais** |
| 48 | Checklist antes de concluir (óleo, filtro, testes) | ❌ Falta | |
| 49 | Sugestão de troca de veículo (custo x margem) | ❌ Falta | |
| 50 | Ranking veículos problemáticos no BI (consolidado) | ⚠ Parcial | Pode existir; consolidar |
| 51 | Previsão de custo de manutenção (próximo mês) | ❌ Falta | |
| **Tabelas** |
| 52 | manutencoes, tipos_manutencao, status_manutencao, componentes_manutencao | ✅ Existe | |
| 53 | planos_manutencao | ✅ Implementado | Criar com script SQL |

**Legenda:** ✅ Existe / Implementado | ⚠ Parcial | ❌ Falta

---

## 1. O que existe hoje

### 1.1 Acesso e telas
- **Página principal:** `pages/manutencoes.php` – listagem, dashboard e análise.
- **Menu:** item "Manutenções" no sidebar (sidebar_pages).
- **Outras telas relacionadas:** `manutencao_pneus.php` (pneus), calendário de manutenção (`calendario/api/calendario_manutencao.php`).
- **APIs:** `api/manutencoes.php` (CRUD), `api/maintenance_data.php` (listagem com filtros), `api/maintenance_data.php` usa `get_maintenance_data.php`.

### 1.2 Dashboard (métricas do mês)
- **Total de manutenções** no mês (entre primeiro e último dia).
- **Preventivas** – contagem com `tipos_manutencao.nome = 'Preventiva'`.
- **Corretivas** – contagem com `tipos_manutencao.nome = 'Corretiva'`.
- **Custos** – soma de `valor` no mês.

### 1.3 KPIs técnicos (getKPIMetrics)
- **MTBF** (Tempo Médio entre Falhas) – total_km / total_falhas (últimos 6 meses).
- **MTTR** (Tempo Médio para Reparos) – total_horas_manutencao / total_falhas (usa `data_manutencao` e `data_conclusao`).
- **Custo/KM** – custo_total / km_total (manutenções × veículos, sem filtrar por período consistente).
- **Top 5 veículos** – mais manutenções e custo total.
- **Componentes com mais falhas** – agrupado por `componentes_manutencao` (até 10).
- **Status das manutenções** – contagem por `status_manutencao`.
- **Evolução mensal** – quantidade por mês (últimos 12 meses).

### 1.4 Listagem
- Tabela com: Data, Veículo, Tipo, Descrição, Fornecedor, Status, Valor, Ações (ver, editar, excluir).
- **Paginação** – 5 registros por página (fixo).
- **Filtros:** busca por texto, veículo, tipo (Preventiva/Corretiva), status (Agendada, Em andamento, Concluída, Cancelada), fornecedor.
- Botões: Nova Manutenção, Filtros, Exportar, Ajuda.

### 1.5 Formulário (CRUD)
- Campos obrigatórios na API: `data_manutencao`, `veiculo_id`, `tipo_manutencao_id`, `componente_id`, `status_manutencao_id`, `km_atual`, `valor`, `descricao`, `descricao_servico`, `responsavel_aprovacao`.
- Outros: fornecedor, custo_total, nota_fiscal, observacoes.
- API REST: GET (lista ou por id), POST (criar), PUT (atualizar), DELETE (excluir).

### 1.6 Gráficos (analytics)
- Custos de manutenção (últimos 6 meses).
- Tipos de manutenção (pizza ou barras).
- Status das manutenções.
- Evolução mensal de manutenções.

### 1.7 Integração com o BI
- **Visão Manutenção** no BI: KPIs (total, custo total, média por manut., veículos), gráficos (custo por mês, quantidade), tabela mensal, custo por KM, preventiva x corretiva, veículos críticos (custo acima da média).
- API `performance_indicators.php` agrega por mês e por tipo (preventiva = nome com "Preventiva").

### 1.8 Tabelas envolvidas (inferido)
- `manutencoes` – registro principal (empresa_id, veiculo_id, tipo_manutencao_id, componente_id, status_manutencao_id, data_manutencao, data_conclusao?, km_atual, valor, fornecedor, descricao, etc.).
- `tipos_manutencao` – ex.: Preventiva, Corretiva.
- `status_manutencao` – ex.: Agendada, Em andamento, Concluída, Cancelada.
- `componentes_manutencao` – componentes (ex.: Motor, Freios).

---

## 2. Pontos fracos e riscos

### 2.1 Dados e consistência
- **Preventiva/Corretiva:** dependem de `tipos_manutencao.nome` exatamente "Preventiva" e "Corretiva". Se houver "Preventiva programada" ou outro nome, o dashboard e o BI podem divergir. O BI usa `LIKE '%preventiva%'`; a página usa `= 'Preventiva'`.
- **MTBF/MTTR:** usam `data_conclusao` e `km_atual` do veículo; se `data_conclusao` não for preenchida, MTTR fica incorreto. KM pode ser o atual do veículo e não o da falha.
- **Custo/KM (página):** calculado sobre todo o histórico (sem período), diferente do BI que é por período.
- **Manutenções sem tipo:** se `tipo_manutencao_id` for NULL, não entram em Preventiva nem Corretiva (e o BI marca em "dados incompletos").

### 2.2 UX e performance
- Paginação fixa em 5 itens por página – pouco prática para muitas manutenções.
- Filtros não estão na URL – ao recarregar ou compartilhar, perde-se o estado.
- Exportar: botão existe mas precisa conferir se gera CSV/Excel de fato.
- Sem filtro por período (data início/fim) na listagem principal (só no dashboard “mês atual”).

### 2.3 Funcionalidades ausentes
- Sem **lembretes/alertas** de próxima preventiva por veículo ou por componente.
- Sem **histórico de custos por veículo** na própria tela de manutenção (só no BI).
- Sem **anexos** (foto da NF, do serviço).
- Sem **ordenação** na tabela (clicar no cabeçalho para ordenar por data, valor, etc.).
- **Calendário** existe em outro módulo – integração com a listagem não está documentada aqui.

---

## 3. O que dá para melhorar (priorizado)

### 3.1 Alinhamento e dados (rápido)
1. **Unificar regra Preventiva/Corretiva** – Usar em todo o sistema a mesma lógica (ex.: `LIKE '%preventiva%'` no dashboard também) e documentar em um único lugar.
2. **Período no Custo/KM da página** – Calcular custo/km dos últimos 12 meses (ou período selecionável), alinhado ao BI.
3. **Obrigar tipo e data_conclusão** – Para manutenções “Concluídas”, exigir tipo e data_conclusao (ou tratar NULL nos KPIs para não distorcer MTTR).

### 3.2 Listagem e filtros
4. **Filtro por período** – Data início e data fim na listagem (e opção “Este mês”, “Últimos 3 meses”, etc.).
5. **Paginação configurável** – Ex.: 10, 25, 50 por página.
6. **Permalink dos filtros** – Salvar filtros na URL para compartilhar e recarregar.
7. **Ordenação por coluna** – Clicar no cabeçalho para ordenar (data, valor, veículo, etc.).

### 3.3 Alertas e planejamento
8. **Alertas de preventiva** – Ex.: “Veículo X está há Y km da próxima revisão” (se houver km ou data prevista por veículo/componente).
9. **Resumo “Próximas manutenções”** – Lista ou card com agendadas + vencidas (se houver data prevista).

### 3.4 Relatórios e exportação
10. **Exportar lista** – Garantir exportação CSV/Excel da listagem (com filtros aplicados).
11. **Relatório por veículo** – Histórico de manutenções e custo acumulado por veículo (pode ser outra página ou modal).

### 3.5 Diferenciais (médio prazo)
12. **Anexos** – Campo para anexar nota fiscal ou foto (upload + armazenamento).
13. **Integração com calendário** – Na listagem, link “Ver no calendário” e no calendário “Abrir manutenção”.
14. **Custo/km por veículo na tela** – Card ou coluna com custo/km do veículo (período configurável), reutilizando lógica do BI.

---

## 4. Resumo

| Área            | O que tem                          | Melhoria principal                    |
|-----------------|------------------------------------|--------------------------------------|
| Dashboard       | 4 cards mês + MTBF, MTTR, Custo/km | Unificar regra preventiva/corretiva  |
| Listagem        | Tabela, filtros, 5 por página      | Período, paginação, ordenação, URL   |
| CRUD            | Completo com tipos/status/componente | Data conclusão obrigatória quando concluída |
| Analytics       | 4 gráficos                         | Período selecionável                 |
| BI              | Visão Manutenção completa          | Já alinhado; manter consistência     |

Se quiser, o próximo passo pode ser: (1) unificar Preventiva/Corretiva e período no Custo/KM, (2) filtro por período e permalink na listagem, ou (3) exportar lista em CSV.

---

## 5. Melhorias de gestão (implementado)

### 5.1 Plano de manutenção automática
- **Tabela** `planos_manutencao`: veiculo_id, componente_id, tipo_manutencao_id, intervalo_km, intervalo_dias, ultimo_km, ultima_data, ativo.
- **Script:** `sql/create_planos_manutencao.sql`.
- **API:** `api/planos_manutencao.php` (GET lista, POST criar, PUT atualizar, DELETE desativar).
- Ao **concluir uma manutenção preventiva** (API manutencoes.php), o sistema atualiza `ultimo_km` e `ultima_data` no plano correspondente (se existir).

### 5.2 Próximas manutenções e alertas
- **Include** `includes/maintenance_alertas_score.php` calcula:
  - **Próximas/vencidas:** com base em planos (vence por km ou por data).
  - **Alertas inteligentes:** 3+ corretivas no mesmo componente; preventiva vencida; corretiva em &lt;5.000 km após preventiva no mesmo componente.

### 5.3 Score técnico do veículo (0–100)
- Baseado em custo/km (12 meses), quantidade de corretivas (12 meses) e, se houver planos, preventivas atrasadas.
- **Status:** Saudável (≥70), Atenção (40–69), Crítico (&lt;40).
- Exibido na página Manutenções em “Score Técnico dos Veículos”.

### 5.4 Impacto da manutenção no lucro
- Últimos 12 meses: total de manutenção vs. lucro bruto (frete − comissão − despesas de viagem).
- Percentual “manutenção representou X% do lucro bruto” e card na página Manutenções.

### 5.5 Roadmap sugerido (próximos passos)
- **Curto:** Tela para cadastrar/editar planos (usar API planos_manutencao); checklist antes de concluir manutenção (opcional).
- **Médio:** Histórico e custo/km por veículo na tela; ranking de veículos problemáticos no BI.
- **Longo:** Sugestão de troca de veículo (custo manutenção vs. margem); previsão simples de custo mensal.

---

## 6. O que falta na Manutenção

Lista objetiva do que ainda **não está feito** (lacunas reais).

### 6.1 Dados e consistência
| Item | Situação |
|------|----------|
| Regra Preventiva/Corretiva unificada | Dashboard ainda pode usar `= 'Preventiva'` em alguns pontos; ideal é `LIKE '%preventiva%'` em todo o sistema. |
| Custo/KM com período na página | Hoje pode ser “sempre” ou 12 meses fixo; falta período selecionável alinhado ao BI. |
| data_conclusao obrigatória | Para status “Concluída”, não há validação que exija `data_conclusao` (MTTR fica incorreto se vier NULL). |
| Tipo obrigatório | Manutenções sem tipo não entram em Preventiva/Corretiva; pode haver dados incompletos. |

### 6.2 Listagem e UX
| Item | Situação |
|------|----------|
| Filtro por período | Sem data início/fim na listagem (só “mês atual” no dashboard). |
| Paginação configurável | Fixa em 5 por página; falta opção 10, 25, 50. |
| Filtros na URL (permalink) | Ao recarregar ou compartilhar, perde-se filtros. |
| Ordenação por coluna | Não dá para ordenar a tabela clicando no cabeçalho (data, valor, veículo, etc.). |
| Exportar lista | Botão existe; garantir CSV/Excel com filtros aplicados. |

### 6.3 Funcionalidades de gestão
| Item | Situação |
|------|----------|
| Tela de planos | API `planos_manutencao` existe; **falta tela** para cadastrar/editar planos (veículo, componente, tipo, intervalo km/dias). |
| Checklist antes de concluir | Antes de marcar “Concluída”, não há checklist (óleo, filtro, testes, etc.) para reduzir retrabalho. |
| Histórico por veículo na tela | Histórico de manutenções e custo acumulado por veículo não está na própria tela de Manutenções (só no BI). |
| Custo/km por veículo na listagem | Não mostra custo/km do veículo (período configurável) na tabela ou em card. |

### 6.4 Relatórios e integração
| Item | Situação |
|------|----------|
| Relatório por veículo | Página ou modal com histórico + custo acumulado por veículo. |
| Integração com calendário | Sem link “Ver no calendário” na listagem e “Abrir manutenção” no calendário. |
| Anexos | Sem upload de nota fiscal ou foto do serviço. |

### 6.5 Diferenciais (médio/longo prazo)
| Item | Situação |
|------|----------|
| Sugestão de troca de veículo | Não há regra automática tipo “custo manutenção/km alto + margem negativa = sugerir troca”. |
| Impacto no lucro por período | Card de impacto existe (12 meses); falta período selecionável e “lucro sem corretivas”. |
| Ranking de veículos problemáticos no BI | Pode existir parcialmente; consolidar e expor no BI. |
| Previsão de custo mensal | Não há previsão simples de custo de manutenção para o próximo mês. |

### 6.6 Resumo “o que falta”
- **Curto prazo (alto impacto):** tela de planos, filtro por período na listagem, paginação configurável, exportar CSV, ordenação na tabela, data_conclusao obrigatória quando Concluída.
- **Médio prazo:** checklist ao concluir, histórico/custo por veículo na tela, integração calendário, anexos.
- **Longo prazo:** sugestão de troca de veículo, previsão de custo mensal.
