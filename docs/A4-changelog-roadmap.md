[← Índice](README.md)

# A4 · Changelog e Roadmap

## Changelog (histórico real — git)

> Extraído de `git log`. Mensagens de commit resumidas; datas no repositório.

| Commit | Descrição |
|--------|-----------|
| `ca1c066` | Melhorias no app do motorista e geral |
| `b486342` | Atualização do sistema geral |
| `9654da1` | feat: melhorias fiscal, BI, multas e manutenção |
| `5b02a56` | Planos e logs |
| `a22ceaf` | Lucratividade |
| `cdc0334` | Correção de valores |
| `c48373b` | Sistema de seguro e correção de lucratividade |
| `5aee1f6` | Primeiro commit do sistema de seguro |
| `bfd7bb8` | Atualização produção |
| `b125e7b` | Correção validação de empresas |
| `3a8bbbb` | Melhoria geral |
| `32296cb` | Ajuste fiscal e agenda |
| `d7d659b` | Sistema de notificações IA (abastecimento/manutenção) |

### Alterações desta base de documentação (não versionadas ainda)
- **TMS:** Centro de Operações, Ordens de Transporte, Clientes, Implementos, Aprovações,
  Planejamento, Central de Alertas, Manutenção Preditiva, Simulador de Rota consolidado.
- **UI:** padronização `fornc-*` (toolbar/tabela/paginação/ações), estados vazios, ajuda por página.
- **Acesso:** login sempre em `index.php`; submenus recolhidos ao acessar.
- **Docs:** manual `docs/` (01–20 + A1–A6) + dicionário de dados gerado do banco.

### Auditoria de segurança/qualidade + ajuda (2026-07, não versionada ainda)
- **Segurança fiscal:**
  - `validar_sefaz.php` deixou de cair sempre na **empresa 1** — agora inicia sessão, exige
    login e valida o certificado da **própria empresa** (isolamento restaurado).
  - Guards de autenticação adicionados em `fiscal_cte`/`fiscal_nfe`/`fiscal_mdfe` (respondiam
    200 sem login).
  - CORS das APIs fiscais restrito à **mesma origem** (`fiscal/api/_cors_same_origin.php`),
    substituindo `Access-Control-Allow-Origin: *` em 13 endpoints.
  - `FISCAL_AMBIENTE_SEFAZ` marcada como fallback; fonte da verdade é
    `fiscal_config_empresa.ambiente_sefaz` (por empresa).
- **Robustez de APIs:** padronização de erro (validação → **400**, erro real → **500 + log +
  mensagem genérica**) em `relatorios_fiscais`, `formas_pagamento`, `multas_analytics`; fim da
  exposição de `$e->getMessage()` ao cliente (gestao_interativa APIs, `validar_sefaz`);
  `DEBUG_MODE=false` confirmado.
- **Correções funcionais:** `ia_dashboard.php` (funções inexistentes `hasPermission`/
  `getEmpresaData`) e `api/ia_custos_fraudes.php` (placeholders repetidos com
  `EMULATE_PREPARES=false`, colunas inexistentes, `ORDER BY` sobre agregado).
- **Performance:** `notificacoes/sync_all.php` com **throttle** (varredura pesada 1×/5min por
  sessão, não a cada página); logs de fetch de fundo suavizados (console limpo).
- **Ajuda:** nova seção **"Fluxo do Processo"** no renderizador + **"Passo a Passo"**;
  botão/conteúdo adicionados onde faltava (Dashboard, Simulador, Gestão Interativa de pneus,
  Usuários, Planos de Manutenção, Centro de Inteligência, Painel Inteligente); ajuda do admin
  reescrita com passo a passo + fluxo; correção do modal de ajuda no admin (`layout_end.php`).
- **Limpeza:** removido `gestao_interativa/views/dashboard.php` (código morto).

### Plataforma de logística inteligente — telas novas (2026-07, não versionada ainda)
Lote de **21 telas novas** (17 APIs + 4 migrações) — detalhes e auditoria em
[A9-novas-funcionalidades.md](A9-novas-funcionalidades.md):
- **Analytics/estratégico:** Painel Executivo, IA de Decisão, Scores (motorista/veículo/cliente),
  Previsão Financeira, Simulação de Crescimento (ROI/payback), Otimização Inteligente.
- **Operação:** Digital Twin (frota ao vivo), Replay de Viagem (GPS), Gestão de Pátio,
  Agenda Inteligente, SLA Operacional, Planejamento de Carga, Metas, Workflow configurável.
- **Frota/apoio:** Gestão de Diesel/Ranking de Postos, Timeline do Veículo, QR Codes, Contratos.
- **Migrações:** `sql/metas.sql`, `sql/contratos.sql`, `sql/patio_vagas.sql`, `sql/workflow.sql`.
- **Adiadas (sem dado/integração):** Garantias, Biblioteca Digital, Geofencing Inteligente.
- **Auditoria:** 401/403 sem login em todas as APIs; CSRF nas gravações; sem vazamento de
  exceção; prepared statements; 0 erro de console; 0 regressão.

> **Recomendação:** manter este changelog atualizado a cada release (ver [A5-checklists.md](A5-checklists.md#checklist-de-release)).

## Roadmap

Consolidado dos roadmaps existentes por módulo + pendências de auditoria.

### ✅ Implementado
Fluxo TMS ponta a ponta; fiscal NF-e/CT-e/MDF-e/CIOT; GPS + cercas + agregação; BI/IA/OCR;
gamificação; app do motorista (v2.5, offline-first); plataforma adm (planos/cobrança/saúde).

### 🔧 Em desenvolvimento / parcial
- Fiscal: fases MDF-e ([MDFE_FASE2](MDFE_FASE2.md), [MDFE_FASE3](MDFE_FASE3.md)), CT-e MOC v4
  ([CTE_MOC_V4_STATUS](CTE_MOC_V4_STATUS.md)).
- BI: itens em [BI_ROADMAP_O_QUE_FALTA](BI_ROADMAP_O_QUE_FALTA.md).
- Pneus: [GESTAO_PNEUS_ROADMAP](GESTAO_PNEUS_ROADMAP.md).
- Documentação exaustiva por tela/tabela/API (ver [99-validacao-final.md](99-validacao-final.md)).

### 📋 Planejado (sugestões da auditoria)
- `sql/schema_full.sql` consolidado; dicionário de dados versionado (já iniciado em [05a](05a-dicionario-dados.md)).
- Rate-limit nas APIs JSON; registro de webhooks por empresa; Push FCM no app.
- Padronizar nomenclatura PT/EN de APIs.

### 🗑️ Descontinuado / a remover
Páginas `*_demo`/`*_exemplo`/`template.php`; endpoints `*_debug`; handlers legados
`actions/`/`ajax/` (após auditoria). Ver [99-validacao-final.md](99-validacao-final.md).

> Roadmaps por módulo detalhados: [BI_ROADMAP_O_QUE_FALTA](BI_ROADMAP_O_QUE_FALTA.md),
> [GESTAO_PNEUS_ROADMAP](GESTAO_PNEUS_ROADMAP.md), [adm](AUDITORIA_CONTROL_CENTER_ADM.md).
