# Módulo de Manutenções – Estado atual (atualizado)

Documento com o estado atual do módulo após as melhorias de checklist, sugestões automáticas, previsão de custo, período nos gráficos, custo por veículo, relatório por veículo e anexos.

---

## 1. O que já existe

### 1.1 Telas e acesso
| Item | Onde |
|------|------|
| Página principal Manutenções | `pages/manutencoes.php` – listagem, KPIs, alertas, gráficos |
| Menu no sidebar | "Manutenções" (sidebar / sidebar_pages) |
| Planos de Manutenção | `pages/planos_manutencao.php` – CRUD de planos (veículo, componente, tipo, intervalo km/dias) |
| Link Planos na página | Botão/ícone "Planos" no header de Manutenções → `planos_manutencao.php` |
| Manutenção de Pneus | `pages/manutencao_pneus.php` (módulo separado) |
| Calendário de manutenção | `calendario/` + API `calendario_manutencao.php` (eventos por planos) |

### 1.2 API
| Item | Onde |
|------|------|
| CRUD manutenções | `api/manutencoes.php` – GET (lista/por id), POST, PUT, DELETE |
| Dados para gráficos/dashboard | `includes/get_maintenance_data.php` – custos, tipos, status, evolução, top veículos, componentes (aceita `?period=3|6|12|ano_atual`) |
| API listagem (AJAX) | `api/manutencoes.php?list=1` com paginação e filtros; retorna `custo_veiculo_12m` por linha |
| Relatório por veículo | `api/manutencoes.php?veiculo_id=X` – retorna lista + `total_custo_veiculo`, `custo_12m`, `total_preventivas`, `total_corretivas` |
| CRUD planos | `api/planos_manutencao.php` |
| Atualização de plano ao concluir preventiva | Em `api/manutencoes.php` – atualiza `ultimo_km` e `ultima_data` em `planos_manutencao` |
| Anexos de manutenção | `api/manutencao_anexos.php` – GET (listar por manutencao_id), POST (upload), POST action=delete; `api/download_anexo.php?id=` |

### 1.3 Dashboard e KPIs (página Manutenções)
| Item | Descrição |
|------|-----------|
| Total de manutenções no mês | Contagem no mês atual |
| Total preventivas / corretivas no mês | Regra por tipo (ex.: LIKE '%preventiva%') |
| Total custos no mês | Soma de `valor` no mês |
| MTBF | Tempo médio entre falhas (período conforme selector dos gráficos) |
| MTTR | Tempo médio para reparo (usa `data_conclusao`) |
| Custo por KM | Últimos 12 meses (manutenção vs. km) |
| Impacto no lucro | % manutenção sobre lucro bruto (12 meses) – card na página |
| **Estimativa próximo mês** | Card com média dos últimos 6 meses de custo (ex.: "R$ 8.400") – `maintenance_alertas_score.php` → `$previsao_proximo_mes` |

### 1.4 Alertas e score
| Item | Onde / como |
|------|-------------|
| Alertas e Próximas Manutenções | `includes/maintenance_alertas_score.php` – card na página (vencidas por km/data, próximas) |
| Alertas inteligentes | Mesmo include – 3+ corretivas no mesmo componente; corretiva em &lt;5.000 km após preventiva; **custo manutenção/km acima da média da frota**; **Avaliar troca do veículo** (score crítico + custo acima da média) |
| Score técnico do veículo (0–100) | Saudável / Atenção / Crítico – card na página (com tooltip custo/km 12m) |
| Sincronização com notificações | `includes/sync_manutencao_notificacoes.php` – alertas no sino do header |

### 1.5 Listagem (tabela)
| Item | Situação |
|------|----------|
| Colunas | Data, Veículo, Tipo, Descrição, Fornecedor, Status, Valor, **Custo 12m** (custo total do veículo nos últimos 12 meses), Ações |
| Paginação | Sim, com opções 5, 10, 25, 50, 100 por página |
| Filtro por período (De/Até) | No **modal de filtro** (ícone de filtro no topo), igual Abastecimentos |
| Ordenação por coluna | Sim – links no cabeçalho (Data, Veículo, Tipo, etc.) com direção ASC/DESC |
| Filtros na URL | Parâmetros `page`, `per_page`, `data_inicio`, `data_fim`, `order`, `dir` – estado mantido ao recarregar |
| Busca + filtros na tabela | Busca por texto; filtros por Veículo, Tipo, Status, Fornecedor (client-side na tabela) |
| Exportar CSV | Link/query `?format=csv` com os mesmos filtros da listagem |

### 1.6 Formulário (Nova / Editar / Ver / Excluir)
| Item | Situação |
|------|----------|
| Modal Nova/Editar | Campos: data, veículo, tipo, componente, status, km, valor, fornecedor, descrição, nota fiscal, observações, responsável, etc. |
| Data de conclusão | Opcional; **obrigatória** quando status é "Concluída" (validação na API e no front) |
| **Checklist ao concluir** | Quando status = Concluída: 3 checkboxes (Óleo trocado/verificado?, Filtro trocado?, Teste realizado?). Valores gravados em `observacoes` (linha "Checklist: ..."); ao editar, checkboxes preenchidos a partir de `observacoes` |
| Coluna `data_conclusao` no banco | Permite NULL (`sql/fix_manutencoes_data_conclusao_null.sql`) |
| **Relatório por veículo** | Botão "Histórico" na linha abre modal **Relatório**: resumo (Total gasto, Custo 12m, Preventivas, Corretivas) + tabela de manutenções + botão "Imprimir relatório" |
| **Anexos (NF/foto)** | Seção "Anexos (NF / foto)" no modal ao abrir manutenção existente (editar/ver): lista com link para abrir/baixar, excluir; upload opcional (PDF, imagens, doc). Tabela `manutencao_anexos`; arquivos em `uploads/manutencao_anexos/{empresa_id}/{manutencao_id}/` |

### 1.7 Gráficos (análise)
| Item | Fonte |
|------|--------|
| **Período selecionável** | Selector na seção "Análise de Manutenções": **Últimos 3 / 6 / 12 meses** ou **Ano atual**. Parâmetro `period` em `get_maintenance_data.php` aplicado em custos, evolução mensal, top veículos, MTBF/MTTR. |
| Custos por mês | Preventiva x Corretiva (conforme período) |
| Tipos de manutenção | Distribuição por tipo |
| Status das manutenções | Por status |
| Evolução mensal | Quantidade por mês (conforme período) |
| Top veículos (custos) | Top 5 (conforme período) |
| Componentes com mais falhas | Até 10 |

### 1.8 Tabelas no banco
- `manutencoes` – registro principal (inclui `data_conclusao` NULL quando não concluída)
- `tipos_manutencao`, `status_manutencao`, `componentes_manutencao`
- `planos_manutencao` – planos por veículo/componente/tipo com intervalo km/dias
- **`manutencao_anexos`** – anexos por manutenção (`sql/create_manutencao_anexos.sql`): id, manutencao_id, empresa_id, nome_original, caminho, tipo, tamanho, data_upload

### 1.9 Integração com BI e outros
- Visão Manutenção no BI (KPIs, gráficos, custo/km, preventiva x corretiva)
- Relatórios e lucratividade podem usar dados de manutenção

---

## 2. O que ainda dá para colocar (opcional)

### 2.1 Melhorias de dados e UX
| Item | Descrição |
|------|-----------|
| Impacto no lucro por período | Card usa 12 meses fixos; permitir escolher período (ex.: último trimestre). |
| Tipo obrigatório no formulário | Garantir que toda manutenção tenha tipo (evitar NULL em Preventiva/Corretiva). |
| Regra Preventiva/Corretiva unificada | Em todo o sistema usar a mesma regra (ex.: `LIKE '%preventiva%'`) para evitar divergência entre dashboard, BI e gráficos. |

### 2.2 Exportação e integração
| Item | Descrição |
|------|-----------|
| Exportar com filtros da tabela | Garantir que o CSV use os mesmos filtros (período, ordem) e inclua todas as colunas úteis (incl. Custo 12m). |
| Link listagem ↔ calendário | Na listagem: "Ver no calendário"; no calendário: "Abrir manutenção" (link para o registro). |

### 2.3 Diferenciais (opcional)
| Item | Descrição |
|------|-----------|
| Indicador de saúde evolutivo | Histórico do score do veículo mês a mês (hoje é estático). |
| Ranking veículos problemáticos | Consolidar no BI (ou na página) ranking por custo/corretivas. |

---

## 3. Resumo: o que tem x o que falta

| Área | O que tem | O que ainda dá (opcional) |
|------|-----------|----------------------------|
| **Telas** | Manutenções, Planos, Calendário, Pneus | – |
| **CRUD** | Completo; data_conclusao quando Concluída; **checklist ao concluir** (óleo, filtro, teste em observacoes) | Tipo sempre obrigatório |
| **Listagem** | Tabela, paginação, filtro período, ordenação, URL, CSV; **coluna Custo 12m** | CSV com todas as colunas; links ↔ calendário |
| **Dashboard** | KPIs mês, MTBF, MTTR, Custo/KM, Impacto lucro, alertas, score; **Estimativa próximo mês** (média 6m) | Impacto por período selecionável |
| **Alertas** | Próximas/vencidas; 3+ corretivas mesmo componente; **custo/km acima da média**; **Avaliar troca** (crítico + custo alto) | – |
| **Planos** | Tela + API; atualização ao concluir preventiva; alertas | – |
| **Gráficos** | 6 gráficos; **período selecionável** (3/6/12 meses, Ano atual) | – |
| **Relatório por veículo** | Modal Relatório (Total, Custo 12m, Preventivas, Corretivas + tabela + Imprimir) | – |
| **Anexos** | Tabela `manutencao_anexos`, API upload/list/delete, download; seção no modal (editar/ver) | – |
| **Integração** | BI, notificações, calendário (eventos) | Links listagem ↔ calendário |

---

## 4. O que foi implementado (última atualização)

- **Checklist ao concluir:** 3 checkboxes (óleo, filtro, teste) quando status = Concluída; gravado em `observacoes`; ao editar, checkboxes preenchidos a partir do texto.
- **Sugestões automáticas:** alerta "custo manutenção/km acima da média da frota"; alerta "Avaliar troca do veículo" (score crítico + custo ≥ média).
- **Previsão de custo:** card "Estimativa próximo mês" com média dos últimos 6 meses.
- **Período nos gráficos:** selector Últimos 3/6/12 meses e Ano atual; `get_maintenance_data.php?period=`.
- **Custo por veículo na listagem:** coluna "Custo 12m" (custo total do veículo nos últimos 12 meses).
- **Relatório por veículo:** modal Relatório com resumo (Total gasto, Custo 12m, Preventivas, Corretivas) + tabela + "Imprimir relatório".
- **Anexos:** tabela `manutencao_anexos`, `api/manutencao_anexos.php`, `api/download_anexo.php`, seção no modal de manutenção (listar, enviar, excluir). Rodar `sql/create_manutencao_anexos.sql` para criar a tabela.

O módulo está completo no essencial; o restante são refinamentos opcionais.
