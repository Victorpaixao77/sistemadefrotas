[← Índice](README.md)

# 99 · Validação Final

Relatório honesto do estado desta documentação e dos achados de auditoria. Sistema de porte
**grande**: 230 tabelas, ~220 telas (57 gestor + ~128 adm + ~34 motorista), 136 APIs web +
~30 do app. A documentação **exaustiva item-a-item é um backlog incremental** — abaixo o que
está pronto, o que falta e como continuar.

## 1. Cobertura da documentação

| Área | Estado |
|------|:------:|
| Documentos transversais (01–20) | ✅ completo |
| Fluxos Mermaid principais (login, TMS, abastecimento, fiscal, preditiva) | ✅ |
| Índice de módulos + padrões de UI | ✅ |
| Índice das 57 telas do gestor (com status) | ✅ |
| Telas totalmente detalhadas | 🟡 3 exemplos (centro_operacoes, clientes, ordens_transporte) + template |
| Módulos com doc própria legada (fiscal, BI, pneus, CIOT, rotas, permissões, app) | ✅ (reaproveitados) |
| Cada uma das ~220 telas, campo-a-campo/botão-a-botão | ⬜ pendente |
| Cada uma das 230 tabelas, campo-a-campo | ⬜ pendente (pneus/fiscal já têm) |
| Cada endpoint no padrão request/response | 🟡 convenções + índice + 1 exemplo |

**Estimativa de cobertura do escopo total pedido: ~45%** (fundação 100%; detalhamento
exaustivo ~15%).

## 2. O que ainda NÃO foi possível documentar (backlog)

1. ~54 telas do gestor restantes (usar [`14-telas/_TEMPLATE.md`](14-telas/_TEMPLATE.md)).
2. Todo o painel **administrativo** (`pages_adm/`, ~128 arquivos, tabelas `adm_*`).
3. Área do **motorista web** (`pages_motorista/`).
4. **Cada endpoint** no padrão completo (objetivo/headers/params/erros/limites).
5. **Cada tabela** (dicionário de dados campo-a-campo) — exceto pneus/fiscal.
6. `fiscal/` interno, `IA/`, `bi/`, `integracoes/`, `notificacoes/`, `calendario/`,
   `gestao_interativa/`, `google-maps/`, `tools/`, `actions/`, `ajax/` em profundidade.
7. `sql/schema_full.sql` consolidado (hoje só migrações avulsas).

## 3. Melhorias sugeridas

- Gerar **dicionário de dados automático** (`INFORMATION_SCHEMA`) para as 230 tabelas.
- Consolidar um **`schema_full.sql`** para instalar do zero (hoje depende de dump).
- Padronizar nomes **PT vs EN** de APIs (ver item 6).
- Rate-limit nas APIs JSON; registro de webhooks por empresa; Push FCM no app.
- Mover `*_demo`/`examples`/`*_debug` para fora de produção.

## 4. Arquivos órfãos / candidatos a revisão

| Arquivo | Situação |
|---------|----------|
| `pages/veiculos.php`? vs `pages/vehicles.php` | Sidebar linka **vehicles.php**; conferir uso de `veiculos.php` |
| `pages/*_demo.php`, `pages/lucratividade_exemplo.php`, `pages/dashboard_exemplos.php`, `pages/template.php` | Demonstração — **não** telas de produção |
| `api/pneu_movimentacoes_debug.php`, `api/rotas_google_maps_debug.php` | Debug — remover/proteger em produção |
| `actions/`, `ajax/` | Handlers legados; parte migrou para `api/` — auditar |

> ⚠️ "Órfão" aqui é **candidato**, não confirmação — exige checagem de referências antes de apagar.

## 5. Código morto / não-produtivo (confirmado)

- **Demos:** `bi_premium_demo.php`, `dashboard_premium_demo.php`, `rotas_premium_demo.php`,
  `dashboard_exemplos.php`, `lucratividade_exemplo.php`, `pages/template.php`.
- **Debug endpoints:** `api/pneu_movimentacoes_debug.php`, `api/rotas_google_maps_debug.php`.
- Baixa incidência de `TODO/FIXME` (1 arquivo) — código relativamente limpo nesse aspecto.

## 6. Duplicação (nomenclatura PT/EN)

Coexistem endpoints/telas em português e inglês para o mesmo domínio — padronizar o canônico:
`api/veiculos.php` × `api/vehicles.php`; `api/motoristas.php` × `api/motorist_*`/`motorists`;
`api/rotas.php` × `api/routes_data.php`/`route_data.php`. **Não** são necessariamente
funções idênticas — auditar antes de unificar.

## 7. Possíveis bugs (achados / corrigidos nesta base)

| Item | Estado |
|------|--------|
| `rotas.data_chegada` NOT NULL ao gerar rota da OT | ✅ corrigido (fallback) |
| Planejamento fazia UPDATE de todos os campos (risco de zerar) | ✅ corrigido (ação `atribuir` isolada) |
| Simulador de Rota duplicado | ✅ consolidado |
| Rótulos "toda segunda/dia 1º" em relatórios sem cron | ✅ trocados por honestos |
| **A revisar:** filtro `empresa_id` em endpoints antigos (`actions/`, `ajax/`) | ⬜ auditar |

## 8. Segurança (achados)

- ✅ Auth, CSRF, 2FA, prepared statements, isolamento `empresa_id`, "lembrar-me" seguro.
- ⚠️ **Sem rate-limit** global nas APIs JSON (só login tem bloqueio por tentativas).
- ⚠️ Endpoints `*_debug` não devem ir a produção.
- ⚠️ Uploads: garantir sempre servir via `api/download_anexo.php` com checagem de `empresa_id`.
- ⬜ Auditar `actions/`/`ajax/` legados quanto a CSRF e `empresa_id`.

## 9. Performance (achados)

- ✅ Agregação de GPS (`gps_logs_resumido`), `gps_ultima_posicao`, `bi_cache`, geocode cache.
- ⚠️ `*_analytics.php` fazem agregações amplas — validar índices + janela de período.
- ⚠️ `notificacoes` cresce sem limite — depende do cron de arquivamento estar **agendado**.
- ⚠️ Assets sem minificação/bundle (aceitável, mas há espaço para ganho).

## 10. Pontuação de qualidade da documentação

| Critério | Nota |
|----------|-----:|
| Fundação (arquitetura, instalação, config, segurança, banco visão geral) | 95% |
| Fluxos e diagramas | 85% |
| Detalhamento por tela | 20% |
| Detalhamento por API | 30% |
| Dicionário de dados (tabelas) | 15% |
| Reaproveitamento de docs existentes | 90% |
| **Pontuação global ponderada** | **≈ 55/100** |

> **Leitura:** o **manual está pronto para orientar qualquer dev** (instalar, configurar,
> entender arquitetura, regras, fluxos e onde fica cada coisa). Para virar "manual oficial
> 100%", falta o detalhamento **exaustivo** de cada tela/tabela/endpoint — um esforço grande
> e incremental, viável usando os templates e os scripts de `INFORMATION_SCHEMA`.
