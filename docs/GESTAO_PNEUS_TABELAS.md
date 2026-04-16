# Tabelas de gestão de pneus – mapeamento e uso

Visão técnica de **todas as tabelas** usadas no módulo de pneus: quais existem, onde são usadas e para que servem.

---

## 1. Tabelas principais (cadastro e estado)

| Tabela | Em uso | Finalidade |
|--------|--------|------------|
| **pneus** | ✅ Sim | Cadastro do pneu: numero_serie, marca, modelo, medida, sulco_inicial, dot, status_id, km_instalacao, data_entrada, numero_recapagens, etc. Fonte principal de dados do pneu. |
| **status_pneus** | ✅ Sim | Catálogo de status (ex.: "disponível", "em uso", "usado", "manutenção"). Referenciado por `pneus.status_id`. |
| **tipo_manutencao_pneus** | ✅ Sim | Tipos de manutenção (Recapagem, Calibragem, Troca, Balanceamento). Usado em `pneu_manutencao.tipo_manutencao_id`. |

**Onde são usadas:** `includes/save_tire.php`, `get_tires.php`, `get_tire_data.php`, `delete_tire.php`, `get_tire_metrics.php`, páginas Pneus/Estoque, APIs de listagem, Gestão Interativa, relatórios, IA.

---

## 2. Manutenção e custos

| Tabela | Em uso | Finalidade |
|--------|--------|------------|
| **pneu_manutencao** | ✅ Sim | Registro de cada manutenção/recapagem: pneu_id, veiculo_id, data_manutencao, km_veiculo, custo, tipo_manutencao_id, observacoes. Usado para custo na lucratividade, relatórios e analytics. |

**Onde são usadas:** `api/pneu_manutencao_data.php`, `api/pneu_manutencao_actions.php` (add/update/delete), `api/pneu_manutencao_analytics.php`, `pages/manutencao_pneus.php`, `lucratividade_funcoes.php`, `lucratividade.php`, `profit_per_km_analytics.php`, `net_revenue_analytics.php`, `financial_analytics.php`, `cost_per_km_analytics.php`, `expenses_distribution.php`, `index.php`, `gestao_interativa/api/pneu_detalhes.php`, `pages/relatorios.php`.

---

## 3. Alocação / localização do pneu no veículo

Existem **três** modelos de “onde está o pneu” no sistema; o código trata os três para listagem e detalhes.

| Tabela | Em uso | Finalidade |
|--------|--------|------------|
| **instalacoes_pneus** | ✅ Sim | Modelo “simples”: pneu instalado em veículo/posição. Campos típicos: pneu_id, veiculo_id, posicao_id, data_instalacao, data_remocao (NULL = ainda instalado). Usado por `salvar_alocacao_pneu.php`, histórico, estatísticas e IA. |
| **alocacoes_pneus_flexiveis** | ✅ Sim | Modelo da **Gestão Interativa**: pneu alocado a um **slot** de um **eixo** do veículo. Campos: eixo_veiculo_id, pneu_id, posicao_slot, posicao_id (opcional), slot_id, empresa_id, ativo, data_remocao. `get_tires.php` e várias APIs usam para mostrar veículo/posição atual. |
| **eixos_veiculos** | ✅ Sim | Eixos do veículo (caminhão/carreta) com quantidade de pneus e slots. Referenciado por `alocacoes_pneus_flexiveis.eixo_veiculo_id`. |
| **eixo_pneus** | ✅ Sim | Modelo alternativo de alocação: pneu no eixo/posição com status 'alocado'/'desalocado', km_alocacao, km_desalocacao. Usado por `api/deslocar_pneu.php`, `api/pneus_data.php`, `get_tires.php` (fallback). |
| **posicoes_pneus** | ✅ Sim | Catálogo de posições (ex.: DD, DE, TD). Referenciado por instalacoes_pneus, eixo_pneus e, se existir coluna, alocacoes_pneus_flexiveis.posicao_id. |

**Onde são usadas:**  
- **instalacoes_pneus:** `gestao_interativa/api/salvar_alocacao_pneu.php`, `historico_alocacoes.php`, `estatisticas_veiculo.php`, `pneus_veiculo.php`, `pneus_disponiveis.php`, `get_tires.php`, `gestao_interativa.php`, `IA/recomendacao_pneus.php`, `ia_regras.php`, `pneu_detalhes.php`.  
- **alocacoes_pneus_flexiveis + eixos_veiculos:** `gestao_interativa/api/eixos_veiculos.php` (alocar/remover, layout), `get_tires.php`, `estatisticas_veiculo.php`, `pneu_detalhes.php`, `gestao_interativa.php`.  
- **eixo_pneus:** `api/deslocar_pneu.php`, `api/pneus_data.php`, `get_tires.php`.

---

## 4. Estoque e alocação legada

| Tabela | Em uso | Finalidade |
|--------|--------|------------|
| **estoque_pneus** | ✅ Sim | Disponibilidade no estoque: pneu_id, disponivel (0/1), status_id, updated_at. Usado na página Estoque e em `api/estoque_pneus.php` (update_status). `deslocar_pneu.php` marca disponivel = 1 ao desalocar. |
| **pneus_alocacao** | ✅ Sim | Histórico de alocação com status 'alocado'/'desalocado', data_desalocacao. Usado por `api/deslocar_pneu.php`, `api/estoque_pneus.php` (get_estoque exclui pneus com status 'alocado'), `pages/relatorios.php`. |

**Onde são usadas:** `pages/estoque_pneus.php`, `api/estoque_pneus.php`, `api/deslocar_pneu.php`, `pages/relatorios.php`.

---

## 5. Histórico único (novo – fonte da verdade)

| Tabela | Em uso | Finalidade |
|--------|--------|------------|
| **pneu_movimentacoes** | ⚠️ Criada, em implementação | Histórico cronológico único: toda mudança de estado (entrada_estoque, instalacao, remocao, deslocamento, recapagem, manutencao, descarte) gera **uma** linha. Status atual do pneu = último registro. Base para custo/km, vida útil e previsão. |

**DDL:** `sql/create_pneu_movimentacoes.sql`.  
**Regras e queries:** `docs/GESTAO_PNEUS_MOVIMENTACOES.md`, `sql/pneu_queries_custo_vida.sql`.

O código que altera estado do pneu (cadastro, alocação, remoção, manutenção) deve **também** inserir em `pneu_movimentacoes` quando a tabela existir (helper em `includes/pneu_movimentacoes_helper.php`).

**Pontos onde o helper já é chamado:**
- **Cadastro de pneu novo:** `includes/save_tire.php` → `entrada_estoque`
- **Alocar pneu no veículo (Gestão Interativa):** `gestao_interativa/api/eixos_veiculos.php` (action `alocar_pneu`) → `instalacao`
- **Remover pneu do veículo (Gestão Interativa):** `gestao_interativa/api/eixos_veiculos.php` (action `remover_pneu`) → `remocao`
- **Remover / enviar para manutenção (instalações):** `gestao_interativa/api/salvar_alocacao_pneu.php` → `remocao`
- **Registrar manutenção/recapagem:** `api/pneu_manutencao_actions.php` (action `add`) → `recapagem` ou `manutencao` conforme tipo
- **Deslocar pneu (modelo eixo_pneus):** `api/deslocar_pneu.php` → `remocao`

---

## 6. Resumo por fluxo

- **Cadastro de pneu:** `pneus` (save_tire). Opcional: `entrada_estoque` em `pneu_movimentacoes`.
- **Alocar no veículo (Gestão Interativa):** `alocacoes_pneus_flexiveis` + `pneus.status_id` → “em uso”. Opcional: `instalacao` em `pneu_movimentacoes`.
- **Remover do veículo (Gestão Interativa):** `alocacoes_pneus_flexiveis.ativo = 0`, `pneus.status_id` → “usado”. Opcional: `remocao` em `pneu_movimentacoes`.
- **Remover / enviar manutenção (instalacoes_pneus):** `instalacoes_pneus.data_remocao`, `pneus.status_id`. Opcional: `remocao` ou contexto manutenção em `pneu_movimentacoes`.
- **Deslocar (eixo_pneus):** `eixo_pneus`, `pneus_alocacao`, `pneus.status_id`, `estoque_pneus`. Opcional: `remocao` em `pneu_movimentacoes`.
- **Registrar manutenção/recapagem:** `pneu_manutencao`. Opcional: `recapagem` ou `manutencao` em `pneu_movimentacoes`.
- **Consultas e relatórios:** `get_tires.php` considera as três fontes (instalacoes_pneus, alocacoes_pneus_flexiveis, eixo_pneus) para localização atual; custos e KPIs usam `pneu_manutencao` (e no futuro `pneu_movimentacoes`).

---

## 7. Referência

- **Roadmap:** `docs/GESTAO_PNEUS_ROADMAP.md`  
- **Regras e queries de movimentações:** `docs/GESTAO_PNEUS_MOVIMENTACOES.md`  
- **Análise de melhorias:** `docs/GESTAO_PNEUS_ANALISE.md`
