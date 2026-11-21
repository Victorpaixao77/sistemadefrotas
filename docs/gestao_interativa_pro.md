# Gestão Interativa Pro — Documentação técnica completa

Página premium de **gestão visual de pneus por veículo** do Sistema de Frotas (Frotec).
Mostra o veículo (cavalo + carreta) em vista superior, com cada pneu clicável e colorido
por saúde (sulco/vida), concentrando numa única tela: montagem de eixos, alocação de pneus,
medição de sulco, calibragem, rodízio, descarte, KPIs, filtros, histórico, alertas,
exportação (CSV/PDF), odômetro e resumo por IA.

> **Não confundir** com a versão clássica `pages/gestao_interativa.php` (que continua
> existindo). Esta é uma página **nova e independente**, que reusa o mesmo banco de pneus.

**Sumário**
1. [Arquivos](#1-arquivos)
2. [Bootstrap e estrutura da página (PHP)](#2-bootstrap-e-estrutura-da-página-php)
3. [Controlador JS](#3-controlador-js-gestao-interativa-projs)
4. [Motor SVG](#4-motor-svg-gip-stage-premiumjs)
5. [Backend — gestao_pneu_pro.php](#5-backend--apigestao_pneu_prophp)
6. [Backend — resumo IA](#6-backend--apigestao_pneu_resumo_iaphp)
7. [Endpoints reusados](#7-endpoints-reusados-gestao_interativaapi)
8. [Banco de dados](#8-banco-de-dados)
9. [Regras de saúde, alertas e custo](#9-regras-de-saúde-alertas-e-custo)
10. [Catálogo de CSS](#10-catálogo-de-css)
11. [Segurança](#11-segurança)
12. [Performance](#12-performance)
13. [Como estender](#13-como-estender)
14. [Troubleshooting](#14-troubleshooting)
15. [Changelog](#15-changelog-desta-evolução)

---

## 1. Arquivos

| Camada | Arquivo | Papel |
|---|---|---|
| Página (view) | `pages/gestao_interativa_pro.php` | HTML, filtros, KPIs, estágio, painel, modais estáticos |
| Controlador JS | `js/gestao-interativa-pro.js` | Toda a lógica de UI, fetch e interação (IIFE) |
| Motor SVG | `js/gip-stage-premium.js` | Desenho técnico do veículo em SVG (`window.GipStagePremium`) |
| Estilos | `css/gestao-interativa-pro.css` | Estilo próprio da página |
| Estilos (reuso) | `css/bi-premium.css` | KPIs no padrão `biq-kpi` (igual ao BI/Routes) |
| API principal | `api/gestao_pneu_pro.php` | 15 ações: KPIs, saúde, alertas, sulco, calibragem, montagem, rodízio, odômetro, descarte |
| API de IA | `api/gestao_pneu_resumo_ia.php` | Resumo executivo do veículo via IA |
| API de eixos/alocação | `gestao_interativa/api/eixos_veiculos.php` | layout, alocar, mover, remover, manutenção, add/excluir eixo |
| API auxiliares | `gestao_interativa/api/pneus_disponiveis.php`, `.../posicoes_pneus.php` | estoque de pneus e catálogo de posições |
| Ajuda | `includes/page_help/04_pneus.php` (chave `gestao_interativa_pro`) | tour/ajuda "?" |
| Migrações | `sql/gestao_montagem.sql`, `sql/pneu_calibragens.sql` | tabelas novas |

---

## 2. Bootstrap e estrutura da página (PHP)

### Topo do arquivo
```php
require_once '../includes/config.php';
require_once '../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/sf_api_base.php';
configure_session(); session_start();
require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');
```

- `sf_render_api_scripts()` injeta no `<head>`: `window.__SF_CSRF__`, helpers `sfApiUrl()`,
  `sfMutationHeaders()` e `sfDeferTableFlash()`.
- CSS na ordem: FontAwesome (CDN, com `preconnect`/`dns-prefetch`), `styles.css`, `theme.css`,
  `responsive.css`, `fornc-modern-page.css`, `bi-premium.css`, `gestao-interativa-pro.css`.
- Scripts no fim do `<body>`: `theme.js`, `sidebar.js`, `gip-stage-premium.js`, `gestao-interativa-pro.js`.
- Ajuda: `<?php $sf_help_key = 'gestao_interativa_pro'; include sf_page_help_bundle.php; ?>`
  + `sf_page_help_js('..')`.

### Mapa do DOM
```
.gip-wrap
├─ .gip-head                      → título + subtítulo
├─ .gip-filters                   → #gipVeiculo, #gipStatus, #gipVida, #gipExtra, #gipSoAlerta
│                                    + #gipResumoIA, #gipKm, #gipExport, #gipRefresh
├─ #gipKpiScope                   → chip "frota toda" × "veículo X"
├─ #gipKpis (.biq-kpis)           → 8 cards biq-kpi[data-k] (clicáveis)
│     total | uso | alerta | critico | vida | custo | custokm | semmed
└─ .gip-main (grid 1fr / 360px)
   ├─ .gip-stage-wrap
   │   ├─ .gip-stage-head         → #gipStageTitle, #gipStageMeta,
   │   │                            #gipViewToggle (Tudo/Cavalo/Carreta),
   │   │                            menu #gipEixosBtn/#gipEixosMenu, #gipRodizio,
   │   │                            #gipMontar, #gipAutoMontagem
   │   ├─ #gipZoom                 → − / ajustar / +
   │   ├─ .gip-legend (#gipLegend) → swatches data-leg (clicáveis)
   │   └─ #gipStage               → SVG injetado aqui
   └─ .gip-panel
       ├─ #gipPanel               → ficha + ações do pneu
       └─ #gipTimeline            → histórico do pneu
.gip-alerts-card → #gipAlerts (#gipAlertCount)
Modais estáticos: #gipSulcoModal, #gipCalibModal
Tooltip: #gipTooltip   ·   Toast: #gipToast
```

### Modais
| Modal | Tipo | Campos / conteúdo |
|---|---|---|
| `#gipSulcoModal` | estático | `sulcoExt`, `sulcoCen`, `sulcoInt`, `sulcoKm`, `sulcoResp`, `sulcoObs`, `sulcoSalvar`, `sulcoMsg` |
| `#gipCalibModal` | estático | `calibReco`, `calibAfer`, `calibKm`, `calibResp`, `calibObs`, `calibSalvar`, `calibMsg` |
| `#gipAlocarModal` | dinâmico | passo 1 (estoque) + passo 2 (posição) |
| `#gipConfirmModal` | dinâmico | confirmação estilizada (`gipConfirm`) |
| `#gipChooseModal` | dinâmico | lista de escolha (`gipChoose`) |
| `#gipEixoAddModal` | dinâmico | cartões Simples (2) / Duplo (4) |
| `#gipRodizioModal` | dinâmico | sugestões + ranking |
| `#gipResumoModal` | dinâmico | chips + texto da IA |
| `#gipKmModal` | dinâmico | input de odômetro |
| `#gipCompareModal` | dinâmico | duas colunas de métricas |

---

## 3. Controlador JS (`gestao-interativa-pro.js`)

IIFE única (`(function(){ ... })();`). Inicializa via `wire()` no `DOMContentLoaded`.

### 3.1 Helpers de base
| Função | Papel |
|---|---|
| `proApi(action)` | URL de `gestao_pneu_pro.php?action=...` (via `sfApiUrl`) |
| `el(id)`, `esc(s)`, `escAttr(s)` | atalhos DOM e escaping |
| `money(n)`, `num(n,suf)`, `dateBr(s)` | formatação pt-BR |
| `mutHeaders()` | headers de mutação + `X-CSRF-Token` |
| `gipResolveUrl(rel)` | resolve URL relativa à página |
| `getJson(url)` | GET → JSON (trata erro/HTTP) |
| `postJson(url, body)` | POST JSON com CSRF → `{ ok, d }` |
| `toast(msg, type)` | notificação efêmera |
| `lsGet(k)`, `lsSet(k,v)` | localStorage seguro |
| `gipConfirm(opts)` | modal de confirmação → `Promise<bool>` |
| `gipChoose(opts)` | modal de lista → `Promise<value\|null>` |

### 3.2 Estado
| Variável | Significado |
|---|---|
| `vehId` | veículo selecionado |
| `layout` | `{ eixosCaminhao[], eixosCarreta[] }` (cada eixo `{ id, pneus }`) |
| `allocated` | `slotId → pneu { id, numero_serie, marca, modelo, medida, status_id, status_nome, posicao_nome }` |
| `healthMap` | `pneuId → { sulco_atual, sulco_inicial, pct, vida, sem_medicao, sem_calibragem, health{cor,nivel,rotulo} }` |
| `curSlot`, `curPneuId` | pneu selecionado |
| `viewMode` | `all`/`cavalo`/`carreta` (localStorage `gip_view`) |
| `assemblyMode` | modo "Montar eixos" (drag) |
| `customAxleXs`, `customTankX` | posições custom (px) de eixos/tanque |
| `pendingMontagem` | montagem salva a aplicar no 1º render (evita flash) |
| `axleOrder[]`, `axleIds[]` | alinham slot→eixo (ver 3.3) |
| `lastBuilt` | `{ width, height, pad, tankX }` do último build |
| `stageZoom` | zoom do estágio |
| `compareMode`, `compareA` | comparação de 2 pneus |

### 3.3 Convenção de `slot_id` (crucial)
```
slot_id = `${prefix}-${eixoIdx}-${posIdx}`
  prefix  = 'cavalo' | 'carreta'
  eixoIdx = índice do eixo dentro da unidade (0-based)
  posIdx  = índice do pneu dentro do eixo (top primeiro, depois bottom)
```
Mapeamento para a API de alocação (que usa `eixo_veiculo.id`):
- Em `renderStage()`: `axleOrder[i] = `${prefix}-${eixoIdx}``, `axleIds[i] = eixo.id`.
- `slotEixoId(slot)` → `axleIds[ axleOrder.indexOf(prefix-eixoIdx) ]` = **eixo_id**.
- `posicao_slot = parseInt(slot.split('-')[2])`.
- `slotSideInfo(slot)` deduz lado (topo=Esquerdo / base=Direito) e Interno/Externo (duais),
  usado por `suggestPosicaoId()` para sugerir a posição nomeada na alocação.

### 3.4 Fluxo de carregamento (otimizado, sem flash)
```
wire()
└─ loadVehicles()                         // ÚNICA chamada no load
   └─ popula #gipVeiculo
      ├─ se localStorage 'gip_veh' existe → selectVehicle(saved)
      └─ senão                            → loadFleetKpis()  (frota)

selectVehicle(id)
├─ reset estado + setStageTitle() + loadFleetKpis()   // KPIs no escopo do veículo
└─ Promise.all([ loadVehicleLayout(id), get_montagem ])
   ├─ seta layout/allocated, pendingMontagem
   ├─ renderStage()                       // 1 desenho só (montagem já aplicada)
   ├─ loadHealthAndColor()                // tires_health → applyTireColors + applyFilters
   └─ loadAlertas()
```
`loadVehicleLayout()` tenta `eixos_veiculos.php?action=layout_completo`; em falha, cai para
o JSON legado (`layout_flexivel`). **Trocar Cavalo/Carreta** chama só `renderStage()` —
reaproveita `healthMap` (sem novo request).

### 3.5 `renderStage()` (resumo do pipeline)
1. Filtra `cav`/`car` conforme `viewMode`; trata estados vazios (sem eixos / visão vazia).
2. Monta `axles[]` e preenche `axleOrder`/`axleIds`.
3. Aplica `pendingMontagem` (se houver) **antes** de construir → 1º desenho já correto.
4. `GipStagePremium.build(buildOpts)` → injeta `markup` no `#gipStage`.
5. Atualiza `#gipStageMeta` (eixos · cavalo/carreta · pneus · **vagas**).
6. `applyTireColors()` → `applyFilters()` → `applyZoom()`.

### 3.6 Pintura do pneu — `tireSvg()` + `applyTireColors()`
- Cada pneu = `<g class="gip-tire" data-slot data-pneu>` com borracha (`drawTireRubber`),
  **anel de cor** (`gip-tire-ring`), área de clique (`gip-tire-hit`, focável) e um **chip**
  (`PNEU NNN` + barra de % + texto de %).
- Slot vazio = borracha com borda tracejada + `+` central.
- `applyTireColors()` define cor pelo `healthMap` (ou `statusFallbackColor`), preenche %,
  marca `data-nivel/-vida/-semcalib/-semmed`, e deixa o **anel tracejado** quando
  `sem_calibragem`.

### 3.7 Filtros — `applyFilters()`
Lê `#gipStatus`, `#gipVida`, `#gipExtra`, `#gipSoAlerta`. Esmaece os pneus que não batem
(`opacity .16`) e, com **qualquer filtro ativo**, esmaece também os slots vazios.
KPIs (`wireKpis`) e legenda (`#gipLegend`) chamam `setFiltersTo({...})` e ficam
**sincronizados** (um desativa o outro via `clearKpiActive`).

### 3.8 Modo montagem (drag)
`toggleAssembly()` liga/desliga (só na visão **Tudo**). Em `onStageMouseDown` há dois modos:
- **Normal:** arrastar um pneu (`gip-tire[data-pneu]`) e soltar numa **vaga vazia** →
  `mover_pneu` (`onTireSwapMove/End`, com limiar de 6px para distinguir clique de drag).
- **Montagem:** arrastar **eixos** (`[data-axle]`) e o **tanque** (`[data-part="tank"]`);
  ao soltar, grava `customAxleXs`/`customTankX` e re-renderiza. `saveMontagem()` persiste
  (`salvar_montagem`); `resetAssembly()` volta ao automático; `applyMontagem(cfg)` restaura.

### 3.9 Alocação em 2 passos
`openAlocarPicker(slot)` → `alocarStepTires()` (lista `pneus_disponiveis.php`) →
`alocarStepPosicao()` (lista `posicoes_pneus.php`, com **posição sugerida** por lado) →
`doAlocar()` → `eixos_veiculos.php?action=alocar_pneu` com
`{ eixo_id, pneu_id, slot_id, posicao_slot, posicao_id? }`.

### 3.10 Referência de funções (todas)
`proApi, el, esc, money, num, dateBr, mutHeaders, gipResolveUrl, getJson, postJson, toast,
lsGet, lsSet, loadVehicles, setKpiScope, loadFleetKpis, parseLayoutFromApi,
fetchLayoutJsonFallback, fetchLayoutFromDb, loadVehicleLayout, selectVehicle, setStageTitle,
loadHealthAndColor, gipEllipsePath, gipRoundRectPath, renderStage, applyZoom, setZoom,
tireSvg, statusFallbackColor, applyTireColors, applyFilters, clearKpiActive, onManualFilter,
setFiltersTo, wireKpis, showTooltip, moveTooltip, hideTooltip, renderPanelEmpty,
renderTimelineEmpty, onTireClick, slotEixoId, slotSideInfo, suggestPosicaoId, openAlocarPicker,
alocarStepTires, alocarStepPosicao, doAlocar, renderPanel, startCompare, cancelCompare,
doCompare, renderCompare, descartarPneu, hexA, loadHistory, loadAlertas, renderAlerts,
openModal, closeModal, submitSulco, submitCalib, removerPneu, recapagem, moverPneu,
reloadVehicle, refreshAfterAction, toggleAssembly, resetAssembly, saveMontagem, applyMontagem,
onStageMouseDown, onAxleDragMove, onAxleDragEnd, gipConfirm, gipChoose, openRodizio,
highlightSlots, vehName, currentTireRows, openExport, printReport, csvReport, openResumoIA,
openKm, adicionarEixo, doAddEixo, excluirEixo, onTireSwapMove, onTireSwapEnd, wire`.

### 3.11 Interações e persistência
- Clique → painel; **duplo-clique** → medição de sulco.
- KPI/legenda → filtra; arrastar pneu → troca; **Enter/Espaço** no pneu focado → painel.
- `localStorage`: `gip_veh` (último veículo), `gip_view` (visão).

---

## 4. Motor SVG (`gip-stage-premium.js`)

IIFE que expõe `window.GipStagePremium`. **API pública:** `build`, `tireSlots`,
`drawTireRubber`, `drawTireRubberLegacy`, `pathRoundRect`, `drawDefs`, `drawCab`, `drawFrame`,
`drawDifferential`, `drawFifthWheel`, `drawTire`.

```js
GipStagePremium.build({
  axles,            // [{ prefix:'cavalo'|'carreta', unit, eixoIdx, pneus, id }]
  cavCount, carCount,
  darkTheme,        // bool (tema)
  renderTire,       // callback (tireSvg) que desenha cada pneu
  axleXs, tankX,    // posições custom (modo montagem) — opcionais
}) // → { width, height, markup, axleXs, tankX, pad }
```

**Camadas internas** (`L`): `under` (sombra, chassi, cardan, frame), `power` (tanque,
bateria, motor, câmbio), `hitch` (quinta-roda, landing gear), `mech` (eixos, cubos,
para-lamas), `tires`, `steerTires`, `cab`, `labels`. `placeAxleXs()` calcula o espaçamento
automático (reserva o vão da cabine só quando há cavalo). Rótulos **dinâmicos**:
`CAVALO MECÂNICO · N eixos` e `CARRETA · M eixos`. A cabine translúcida (`drawCab`) é
desenhada **apenas** quando há eixos de cavalo na visão (visão "Carreta" mostra só a carreta).

---

## 5. Backend — `api/gestao_pneu_pro.php`

Roteador por `?action=`. Sequência: includes → `configure_session()`/`session_start()` →
`require_authentication()` → lê `empresa_id`/`usuario_id` → **`session_write_close()`** →
`getConnection()` → `switch($action)`. Mutações chamam `api_require_csrf_json()`.
Helpers: `gp_col_exists()` (cacheado), `gp_health()`, `gp_dot_idade_anos()`.

### Constantes
`GP_SULCO_CRITICO=3.0`, `GP_SULCO_ATENCAO=5.0`, `GP_DIAS_SEM_MEDICAO=60`,
`GP_RODIZIO_DIFF=2.0` (mm), `GP_DOT_ANOS_ALERTA=6` (anos).

### Ações (request → response)

**`vehicles`** (GET) → `{ success, data:[{ id, placa, modelo, marca, tem_montagem }] }`

**`fleet_kpis`** (GET) / **`vehicle_kpis`** (GET, `?veiculo_id`) →
```json
{ "success": true, "data": {
  "total":12, "em_uso":4, "em_alerta":0, "criticos":1, "vida_media":1.6,
  "custo_total":0, "custo_km":0, "sem_medicao":11, "escopo":"frota|veiculo" } }
```

**`tire_detail`** (GET, `?pneu_id`) → `data` com: `id, numero_serie, marca, modelo, medida,
dot, dot_idade_anos, qr_token, status_id, status_nome, vida, numero_recapagens,
sulco_inicial, sulco_atual, sulco_data, pressao_recomendada, pressao_aferida,
pressao_diferenca, calibragem_data, km_acumulado, km_instalacao, data_instalacao,
valor_compra, custo_total, custo_km, vida_util_km, veiculo_id, posicao_nome,
health{cor,nivel,rotulo}, proxima_inspecao`.

**`history`** (GET, `?pneu_id`) → `{ success, data:[ movimentações ] }` (timeline).

**`tires_health`** (GET, `?veiculo_id`) → `{ success, data: { pneuId → {sulco_atual,
sulco_inicial, pct, vida, sem_medicao, sem_calibragem, health} } }` (1 chamada pinta tudo).

**`alertas`** (GET, `?veiculo_id` opcional) → `{ success, data:[{ nivel:'critico|atencao|info',
pneu_id, titulo, desc }] }`. Gera: sulco crítico/atenção, sem medição (60d), calibragem
atrasada (90d) e **idade DOT** (≥6 anos).

**`rodizio`** (GET, `?veiculo_id`) →
```json
{ "success": true, "data": {
  "suggestions": [{ "de_slot","de_serie","de_sulco","de_pos",
                    "para_slot","para_serie","para_sulco","para_pos","diff" }],
  "ranking": [{ "pneu_id","serie","slot","pos","sulco" }],
  "media": 7.2, "message": "" } }
```
Heurística: ordena por sulco asc e emparelha extremos enquanto `diff ≥ GP_RODIZIO_DIFF`.

**`aplicar_rodizio`** (POST, CSRF) `{ veiculo_id, slot_a, slot_b }` → **transação**:
desativa as 2 alocações e reinsere trocando os pneus (cada um assume a posição física do
outro, incl. `posicao_id`), grava 2 movimentações `deslocamento`. → `{ success, message }`.

**`set_odometro`** (POST, CSRF) `{ veiculo_id, km }` → atualiza `veiculos.km_atual`
(rejeita `km` menor que o atual). → `{ success, km_atual }`.

**`registrar_sulco`** (POST, CSRF) `{ pneu_id, veiculo_id?, sulco_externo?, sulco_central?,
sulco_interno?, sulco_mm?, km_veiculo?, responsavel?, observacoes? }` → grava a **média**
das leituras em `pneu_medicoes_sulco`. → `{ success, sulco_media, health, message }`.

**`registrar_calibragem`** (POST, CSRF) `{ pneu_id, veiculo_id?, pressao_recomendada?,
pressao_aferida (obrig.), km_veiculo?, responsavel?, observacoes? }` → grava em
`pneu_calibragens` com `diferenca`. → `{ success, ... }`.

**`descartar_pneu`** (POST, CSRF) `{ pneu_id }` → transação: desativa alocação, `status_id=3`,
movimentação `descarte`. → `{ success }`.

**`get_montagem`** (GET, `?veiculo_id`) → `{ success, data: { eixos:{<eixo_id>:x}, tankX } | null }`.
**`salvar_montagem`** (POST, CSRF) `{ veiculo_id, eixos:{<eixo_id>:x}, tank_x }` → grava JSON
em `gestao_montagem` (ou apaga a linha se vazio = volta ao automático).

> **EMULATE_PREPARES=false:** nunca reutilizar o mesmo placeholder nomeado 2× na mesma query
> (use `:e` e `:e2`).

### Formato da montagem (`gestao_montagem.config`)
```json
{ "eixos": { "37": 130.0, "41": 234.0, "55": 470.0 }, "tankX": 360.0 }
```
Chave = `eixo_veiculo.id`; valor = X em coordenadas internas do SVG.

---

## 6. Backend — `api/gestao_pneu_resumo_ia.php`
POST `{ veiculo_id }` (CSRF). Faz `session_write_close()` (a IA pode levar dezenas de
segundos). Monta no servidor `{ veiculo, resumo{instalados,criticos,em_atencao,sem_medicao,
sulco_medio_mm}, pneus[] }`, resolve a IA via `sf_vision_ai_resolve(..., 'text')` e chama
`chat/completions`. → `{ success, resumo, meta }`. Erros típicos: `ia_nao_configurada` (422),
HTTP 429 → 402, falha de rede → 502.

---

## 7. Endpoints reusados (`gestao_interativa/api/`)
- **`eixos_veiculos.php?action=`**
  - `layout_completo` (GET, `?veiculo_id`) → `{ layout:{eixosCaminhao,eixosCarreta,pneusFlexAlocados} }`.
  - `alocar_pneu` (POST) `{ eixo_id, pneu_id, slot_id, posicao_slot, posicao_id? }`.
  - `mover_pneu` (POST) `{ from_slot_id, to_slot_id }`.
  - `remover_pneu` (POST) `{ slot_id }`.
  - `enviar_manutencao` (POST) `{ slot_id, observacoes }` (usado por "Recapagem").
  - `adicionar_eixo` (POST) `{ veiculo_id, tipo_veiculo:'caminhao'|'carreta', quantidade_pneus:2|4 }`.
  - `excluir_eixo` (POST) `{ eixo_id }` (bloqueia se houver pneus alocados).
  - Também faz `session_write_close()` após a checagem de permissão.
- **`pneus_disponiveis.php`** (GET) → `{ pneus:[{ id, numero_serie, marca, modelo, medida, dot, status_nome }] }`.
- **`posicoes_pneus.php`** (GET) → `{ posicoes:[{ id, nome }] }` (catálogo global de posições).

---

## 8. Banco de dados

| Tabela | Uso |
|---|---|
| `veiculos` | dados do veículo, `km_atual` |
| `eixos_veiculos` | eixos (`tipo_veiculo`, `numero_eixo`, `quantidade_pneus`) |
| `pneus` | ficha mestre (sulco_inicial, dot, valor_compra, km_rodado_total, status_id, qr_token) |
| `status_pneus` | 1 em uso · 2 reserva · 3 descartado · 4 recapagem · 5 novo · 6 usado |
| `alocacoes_pneus_flexiveis` | alocação por **slot** (`eixo_veiculo_id, pneu_id, posicao_slot, posicao_id, slot_id, ativo`) |
| `pneu_medicoes_sulco` | medições de sulco |
| `pneu_calibragens` *(nova)* | calibragens (`pressao_recomendada/aferida/diferenca`) |
| `pneu_movimentacoes` | histórico (`tipo` ENUM: entrada_estoque, instalacao, remocao, deslocamento, recapagem, manutencao, descarte) |
| `posicoes_pneus` | catálogo de posições nomeadas |
| `gestao_montagem` *(nova)* | posições custom de eixos/tanque (JSON) por veículo (PK `veiculo_id`) |

**Vidas** = `numero_recapagens + 1`.

Migrações:
```
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/gestao_montagem.sql
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/pneu_calibragens.sql
```

---

## 9. Regras de saúde, alertas e custo

**Saúde** (`gp_health(sulco, statusId, recapagens)`):
| Condição | nível | cor |
|---|---|---|
| status 3 | descartado | `#64748b` |
| status 4 | recapagem | `#3b82f6` |
| sulco ≤ 3.0 | crítico | `#ef4444` |
| sulco ≤ 5.0 | atenção | `#f59e0b` |
| caso geral | bom | `#22c55e` |

**Alertas** (action `alertas`): crítico/atenção por sulco; "sem medição" (>60 dias);
"calibragem atrasada" (>90 dias); "pneu antigo (DOT)" (≥6 anos via `gp_dot_idade_anos`,
formato WWYY).

**Custo:** `custo_total = Σ valor_compra + Σ custo(movimentações 'recapagem'/'manutencao')`;
`custo_km = custo_total / Σ km_rodado_total`. Mostra **"—"** quando 0 (sem `valor_compra`).

---

## 10. Catálogo de CSS (`gestao-interativa-pro.css`)

| Grupo | Classes principais |
|---|---|
| Layout | `.gip-wrap`, `.gip-head`, `.gip-filters`, `.gip-field`, `.gip-main`, `.gip-card` |
| Botões | `.gip-btn`, `.gip-btn--primary/--ghost/--danger/--sm/--wide` |
| KPIs | `.gip-kpis.biq-kpis` (mapeia `--biq-*`), `.gip-kpi-scope`, `.biq-kpi.kpi-active` |
| Estágio | `.gip-stage`, `.gip-stage-head`, `.gip-stage-tools`, `.gip-view-toggle`, `.gip-zoom` |
| Pneu | `.gip-tire`, `.gip-tire-ring`, `.gip-chip`, `.gip-chip-bar`, `.gip-slot-empty`, `.gip-slot-plus` |
| Drag | `.gsp-assembly [data-axle]`, `.gip-drop-target`, `.gip-tire.gip-rod-flash` |
| Legenda | `.gip-legend span[data-leg]`, `.leg-active`, `.gip-leg-dash` |
| Menu Eixos | `.gip-eixos-wrap`, `.gip-eixos-menu` |
| Painel | `.gip-pnl-status/-title/-sub/-grid/-item/-actions`, `.gip-meter` |
| Alertas | `.gip-alert(.critico/.atencao/.info)` |
| Modais | `.gip-modal(.open)`, `.gip-modal-box(.gip-modal-sm)`, `.gip-modal-head/-body/-foot` |
| Alocar | `.gip-alocar-item`, `.gip-aloc-hint/-sug/-badge/-skip` |
| Rodízio | `.gip-rod-sug/-leg/-btns/-rank/-row` |
| Comparar | `.gip-cmp-heads/-head/-rows/-row` |
| Resumo IA | `.gip-resumo-chips`, `.gip-resumo-text` |
| Eixo (add) | `.gip-eixo-opts`, `.gip-eixo-opt` |

---

## 11. Segurança
- `require_authentication()` + `require_permission('access_tire_management')` na página
  e checagens equivalentes nas APIs.
- Isolamento multi-empresa: **todo** SELECT/UPDATE/DELETE filtra `empresa_id`.
- CSRF (`api_require_csrf_json`) em **todas** as mutações.
- Nunca expor `$e->getMessage()`; erros via `error_log` + mensagem genérica.
- Prepared statements com placeholders nomeados; sem concatenar entrada do usuário.

---

## 12. Performance
- **`session_write_close()`** cedo em `gestao_pneu_pro.php`, `eixos_veiculos.php` e
  `gestao_pneu_resumo_ia.php` → as várias chamadas da página rodam **em paralelo**
  (sem serializar no lock de sessão de arquivo).
- **OPcache** ligado no `php.ini` (sem ele, ~74 ms de bootstrap por request, pois o PHP
  recompila os includes). Requer reiniciar o Apache. `validate_timestamps=1` + `revalidate_freq=2`
  mantêm o dev pegando mudanças de código.
- Visão Cavalo/Carreta reusa `healthMap` (sem refetch). `gp_col_exists` cacheado por request.
- Carregamento prioriza o dropdown e carrega os KPIs **uma vez** no escopo certo.

---

## 13. Como estender
- **Nova ação de backend:** adicionar `case` em `gestao_pneu_pro.php` (GET lê; POST com
  `api_require_csrf_json()`), filtrando `empresa_id`; chamar via `proApi('nome')`.
- **Novo dado no painel:** incluir no retorno de `tire_detail` e renderizar em `renderPanel()`.
- **Novo KPI:** card `biq-kpi[data-k=...]` no PHP + preencher em `loadFleetKpis()` + (se filtra)
  mapear em `wireKpis()` + somar no `vehicle_kpis`/`fleet_kpis`.
- **Novo alerta:** acrescentar regra no `case 'alertas'`.
- **Mudar o desenho:** editar `gip-stage-premium.js` (`build`/`drawCab`/`drawAxle`/`placeAxleXs`…).

---

## 14. Troubleshooting
| Sintoma | Causa provável | Ação |
|---|---|---|
| Página/lista lenta | OPcache desligado | Habilitar OPcache + reiniciar Apache |
| Custo total/Custo-km = "—" | Pneus sem `valor_compra`/sem custo de movimentação | Cadastrar custo |
| Alerta de DOT estranho | `pneus.dot` com dado de teste | Cadastrar DOT real (WWYY) |
| "Sem eixos configurados" | Veículo sem `eixos_veiculos` | Menu **Eixos → Adicionar eixo** ou clássica |
| Resumo IA falha (422) | IA não configurada | Configurações → IA de Visão (chave) |
| 500 numa ação | Placeholder nomeado reutilizado | Usar placeholders distintos (`:e`/`:e2`) |
| Rodízio "não está mais ocupada" (409) | Layout mudou entre carregar e aplicar | Recarregar e tentar de novo |
| Montagem não salva | Fora da visão "Tudo" | Montagem só na visão Tudo |

---

## 15. Changelog (desta evolução)
- KPIs por **veículo** (`vehicle_kpis`) + chip de escopo; KPIs e **legenda clicáveis** (filtram).
- **Rodízio**: sugestões/ranking + **aplicar troca** real (`aplicar_rodizio`).
- **Resumo IA**, **Exportar** (CSV/PDF), **Atualizar odômetro** (`set_odometro`).
- **Alerta de idade DOT**; **calibragem vencida** = anel tracejado.
- **Montar eixos** (drag de eixos/tanque) + **salvar layout** (`gestao_montagem`).
- **Alocar em 2 passos** com posição sugerida; **arrastar pneu** para trocar; **descartar**.
- Visão **Tudo/Cavalo/Carreta** (rótulos dinâmicos); **zoom**; **comparar pneus**;
  duplo-clique → sulco; teclado (Enter/Espaço); persistência de veículo/visão.
- Modais estilizados (`gipConfirm`/`gipChoose`) no lugar de `confirm/prompt` nativos.
- Performance: `session_write_close()` + OPcache; carregamento sem flash de KPIs.

---

_Última atualização: 2026-06-30._
