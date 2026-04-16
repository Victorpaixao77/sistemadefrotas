# Gestão de Pneus – Onde estamos e roadmap

Documento de referência: **o que já temos**, **o que falta** e **ordem sugerida** para evoluir o módulo de pneus ao nível de sistemas grandes.

---

## 1. Onde você já está FORTE

| Item | Situação atual |
|------|----------------|
| **Multiempresa** | Isolamento por `empresa_id` em pneus, manutenção, alocação e APIs. |
| **Gestão de estoque** | Página Estoque de Pneus, integração com `estoque_pneus` e status. |
| **Manutenção vinculada** | `pneu_manutencao` com pneu, veículo, tipo, custo, km; lista e formulário em Manutenção de Pneus. |
| **Alocação por eixo/posição** | Gestão Interativa com eixos, slots, `instalacoes_pneus` e `alocacoes_pneus_flexiveis`; status “em uso” / “usado” ao alocar/remover. |
| **Dashboard interativo** | Gestão Interativa por veículo, histórico de alocações, estatísticas. |
| **IA integrada** | Recomendações e notificações em `IA/recomendacao_pneus.php`, `IA/notificacao_pneu.php`. |
| **Custo na lucratividade** | Custo de pneu/manutenção entra em `lucratividade_funcoes.php` e relatórios. |

**Resumo:** Nível acima da média de sistemas pequenos; base sólida para subir de nível.

---

## 2. O que empresas grandes fazem (e ainda não temos completo)

### 2.1 Ciclo de vida completo do pneu

Grandes sistemas rastreiam: **Compra → Estoque → 1ª instalação → Deslocamento entre eixos → Recapagem 1 → Recapagem 2 → Sucateamento → Descarte**.

**O que já temos:** Cadastro de pneu, estoque, manutenção (incluindo recapagem), alocação por eixo/posição.

**O que falta:**

- **Indicador de custo por km real**
  - Ex.: Custo total R$ 5.200, KM rodado 128.000 km → **R$ 0,0406/km**.
  - Hoje: manutenção é guardada, mas não há:
    - KM acumulado por ciclo
    - KM por recapagem
    - Custo acumulado por fase

### 2.2 Vida por sulco (mm) – vida técnica vs vida econômica

Grandes sistemas separam:

- **Vida técnica:** sulco (mm), DOT/idade, limite legal. O que já temos: `sulco_inicial` em `pneus`; KPIs de “vida útil” e “em alerta” em `get_tire_metrics.php`.
- **Vida econômica:** custo/km, ROI do pneu, vale a pena recapagem ou descarte? Isso vem do histórico (custo acumulado, km rodado) e alimenta dashboards e IA depois.

**O que falta:** Sulco atual (atualização ao longo do tempo, idealmente na movimentação), sulco mínimo configurável, indicador de vida restante e previsão de troca.

### 2.3 Ranking de fornecedores de recapagem

Comparar fornecedores: **Qtde recapagens**, **vida média**, **custo médio**, **custo/km**.

**O que já temos:** Registro de manutenção/recapagem com custo e tipo.

**O que falta:** Cadastro de fornecedor vinculado à recapagem e relatório/ranking por fornecedor.

### 2.4 Previsão inteligente (IA)

Ex.: “Eixo 2 do veículo ABC-1234 consome pneus 18% acima da média”; pneu com desgaste irregular; veículo/eixo que desgasta mais.

**O que já temos:** IA básica (recomendações e notificações).

**O que falta:** Cálculos sobre histórico (desgaste por eixo/veículo, comparação com média, alertas preditivos). **Importante:** IA deve ser **camada que consome dados consolidados do histórico** e **não grava dados primários** — evita virar gambiarra de regra solta.

### 2.5 Integração com telemetria

Uso de peso por eixo, pressão (TPMS), velocidade média, frenagem.

**O que já temos:** KM por viagem/rota; tipo de rota e dados de viagem quando existirem.

**O que falta:** Integração com telemetria real; simulação com KM/rota/carga e correlação com desgaste.

---

## 3. Melhorias estruturais necessárias

Antes de crescer em funcionalidade, vale consolidar a base.

### 3.1 API única para pneus

**Hoje:** `includes/get_tires.php`, `get_tire_metrics.php`, `get_tire_data.php`, `save_tire.php`, `delete_tire.php`; `api/pneus_data.php`, `api/estoque_pneus.php`, `api/pneu_manutencao_*.php`; `gestao_interativa/api/` com várias APIs.

**Alvo:** Uma API principal, por exemplo:
- `api/pneus.php?action=list` (listagem + filtros + paginação)
- `api/pneus.php?action=summary` (KPIs)
- `api/pneus.php?action=history` (histórico por pneu)
- E ações de create/update/delete quando fizer sentido.

Fonte única para a página Pneus e, onde possível, para Estoque e Gestão Interativa.

### 3.2 Fonte da verdade da alocação

**Hoje:** Mais de uma “verdade”: `instalacoes_pneus`, `eixo_pneus`, `alocacoes_pneus_flexiveis` (e `eixos_veiculos`). O `get_tires.php` já consulta as três para listagem e detalhes.

**Alvo:** Definir qual modelo é o oficial (ou um histórico único que alimente as telas). Documentar no README da Gestão Interativa e no doc de modelo de dados.

### 3.3 Histórico cronológico completo (núcleo absoluto)

**Alvo:** Tabela de movimentação única: **pneu_movimentacoes**.

- **Regra rígida:** Toda mudança de estado do pneu gera **UMA** movimentação:
  - entrada em estoque, instalação, deslocamento (troca de eixo/posição), remoção, recapagem, manutenção, descarte.
- **Status atual do pneu** = sempre o último registro do histórico (não campos soltos na tabela `pneus`).
- Evita inconsistência eterna e permite refazer qualquer KPI no futuro.

Estrutura de referência: `pneu_id`, `tipo` (instalação, deslocamento, recapagem, estoque, descarte, etc.), `veiculo_id`, `eixo_id`, `posicao_id`, `km_odometro`, `km_rodado`, `sulco_mm`, `custo`, `data_movimentacao`. DDL e regras: `sql/create_pneu_movimentacoes.sql` e `docs/GESTAO_PNEUS_MOVIMENTACOES.md`.

---

## 4. O que faria competir com sistema grande

| Funcionalidade | Descrição |
|----------------|-----------|
| **Dashboard executivo de pneus** | **Não é a tela operacional `pneus.php`.** É outra visão: menos operacional, mais gerencial — comparativo, tendência, custo por mês/veículo/eixo, vida média por marca, % recapados vs novos, previsão de compras. Isso ajuda quando for vender o sistema. |
| **Planejamento de compras prático** | Ex.: “Se nada mudar, em 90 dias faltarão 12 pneus medida 295/80 R22.5.” Transportador valoriza isso mais que só gráfico bonito. |
| **Indicador operacional (IEP)** | Índice de Eficiência de Pneus = km rodado / custo total; ranking da frota. |
| **Desempenho por motorista** | Se houver vínculo motorista/veículo/rota: “Motorista X consome Y% mais pneus que a média.” |

### 4.1 O “pulo do gato” (onde se diferenciar de sistemas grandes)

Sistemas grandes costumam ser pesados, pouco flexíveis e difíceis de adaptar à frota média. Você pode ganhar em:

- **Custo/km simples e transparente:** Mostrar a fórmula: *Custo/km = (Compra + Recapagens + Manutenções) / KM rodado*, e de onde vem cada número. Grandes sistemas muitas vezes escondem o cálculo.
- **Previsão explicável:** Em vez de só “Pneu em risco”, mostrar: *“Baseado nos últimos 3 ciclos, este pneu perde em média 1,2 mm a cada 18.000 km.”* Gera confiança.
- **Planejamento de compra prático:** Como acima — “em 90 dias faltarão X pneus medida Y”.

---

## 5. Onde você está no mercado

- **Hoje:** Sistema médio bem estruturado, acima da maioria dos ERPs pequenos.
- **Com as melhorias de estrutura + custo/km + histórico + dashboard:** Nível SaaS profissional para transportadora média.
- **Com histórico unificado + custo/km + previsão + ranking:** Começa a competir no módulo de pneus com players como Sascar/Autotrac (sem telemetria).

---

## 6. Ordem recomendada de implementação

Ordem sugerida para evoluir de forma consistente:

| # | Etapa | Objetivo |
|---|--------|----------|
| **1** | **Unificar API de pneus** | Uma API principal (list, summary, history, crud) para Pneus e consumo único na interface. |
| **2** | **Histórico único de movimentação** | Tabela `pneu_movimentacoes` e **regras para toda** instalação/remoção/recapagem/descarte **gerar registro**. O sistema deve passar a usar essa tabela como fonte da verdade. |
| **3** | **Custo por km real** | KM acumulado por pneu/ciclo; custo acumulado por fase; indicador custo/km por pneu (e agregado), **fórmula transparente**. |
| **4** | **Vida útil por sulco** | Sulco atual/mínimo; vida restante (% e km); previsão de troca. |
| **5** | **Dashboard executivo** | Visão gerencial (não a tela operacional): gráficos e KPIs, previsão de compras. |
| **6** | **Previsão inteligente (IA)** | Análise de desgaste por eixo/veículo, comparação com média, alertas explicáveis. IA como camada que só consome histórico. |

Depois disso: ranking de fornecedores de recapagem, planejamento de compras detalhado, IEP e desempenho por motorista (quando houver vínculo motorista–veículo–rota).

### Recomendação final (prática)

- **Não criar nenhuma feature nova de pneus antes do histórico único.**
- Implementar `pneu_movimentacoes` e fazer o sistema passar a usá-la em toda instalação/remoção/recapagem/descarte.
- Só depois calcular custo/km.
- Só depois IA e dashboards.

O roadmap está correto; o diferencial é execução disciplinada.

---

## 7. Referência com os demais docs

- **Estado atual detalhado e melhorias pontuais:** `docs/GESTAO_PNEUS_ANALISE.md` (páginas, APIs, filtros, KPIs, estoque, manutenção, gestão interativa).
- **Visão e ordem de evolução:** este arquivo (`docs/GESTAO_PNEUS_ROADMAP.md`).
- **DDL, regras de negócio, queries e algoritmo de previsão:** `sql/create_pneu_movimentacoes.sql`, `docs/GESTAO_PNEUS_MOVIMENTACOES.md`, `sql/pneu_queries_custo_vida.sql`.
- **Mapeamento de tabelas (quais existem, onde são usadas):** `docs/GESTAO_PNEUS_TABELAS.md`.

Para implementar, seguir a ordem da seção 6 e ir marcando no roadmap o que já foi feito.
