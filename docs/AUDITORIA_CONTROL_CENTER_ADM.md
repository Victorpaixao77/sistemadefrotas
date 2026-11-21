# Auditoria — Control Center ADM (Frotec)

**Data:** 2026-06-16 (revisão pós-implementação Fases 2–5)  
**Escopo:** `pages_adm/`, APIs v1, integração com produto (`includes/sf_saas.php`), SQL `adm_control_center_*`  
**Status:** Control Center **operacional** — ~78% do roadmap 2.5.0; integração SaaS com produto **iniciada na Fase 5**.

---

## 1. Resumo executivo

O painel `pages_adm` evoluiu de CRUD operacional para um **Centro de Controle da Plataforma** com NOC, métricas SaaS, observabilidade, IA administrativa (rules engine), marketplace de módulos e modo suporte auditado.

| Camada | Antes (jun/2026) | Agora |
|--------|------------------|-------|
| Dashboard / NOC | 4 KPIs estáticos | Dashboard auto-refresh, monitor sessões, health, SLA, heatmap, timeline, mapa |
| APIs | 5 endpoints legados | **37** em `api/v1/` + 6 legados |
| Páginas | ~15 telas | **49** PHP (45 no menu + auth/bridge) |
| Segurança ADM | Só sessão `admin_id` | CSRF em forms críticos, 2FA TOTP, reCAPTCHA global |
| Integração produto | Nenhuma | Gates SaaS: status empresa, manutenção, módulos, limite veículos |
| Telemetria | Inexistente | `adm-telemetry.js` + `adm_request_metrics` |
| Financeiro SaaS | Boletos manuais | MRR/ARR, custos plataforma, posição financeira + iframe |

**Conclusão:** O Control Center está **pronto para operação interna** da plataforma. Itens restantes são refinamento (refatoração god files, SLA externo, geocoding real, rollback deploy automático em prod).

---

## 2. Inventário quantitativo

| Categoria | Quantidade |
|-----------|------------|
| Telas PHP (`pages_adm/*.php`) | 49 |
| Itens no menu (`sidebar.php`) | 45 |
| APIs v1 (`pages_adm/api/v1/`) | 37 |
| APIs legadas (`pages_adm/api/`) | 6 |
| Bibliotecas (`pages_adm/lib/`) | 14 |
| Arquivos PHP totais em `pages_adm/` | ~117 |

### Módulos do menu (7 seções)

| Seção | Telas | Principais entregas |
|-------|-------|---------------------|
| **Visão** | 7 | Dashboard, monitor, NOC, SLA, heatmap, timeline, mapa clientes |
| **Operações** | 7 | Empresas, planos, usuários, licenciamento, marketplace, health score, suporte |
| **Financeiro** | 4 | MRR/ARR, comercial, custos plataforma, posição financeira |
| **Observabilidade** | 8 | Performance, DB, SQL live, integrações, logs, alertas, erros, benchmark |
| **Inteligência** | 6 | IA admin, IA dev, stats IA, auditoria, IA comercial, analytics |
| **Plataforma** | 13 | Deploy, jobs, storage, deps, config CC, comunicação, backup, sistema, roadmap, segurança NOC, 2FA ADM, logs, reCAPTCHA |
| **Bridge** | 1 | `acessar_frotas.php` → seleção de empresa |

---

## 3. APIs v1 por domínio

| Domínio | Endpoints |
|---------|-----------|
| Dashboard / financeiro | `dashboard_metrics`, `finance_metrics`, `commercial_metrics`, `sla_metrics`, `platform_costs` |
| Monitoramento | `monitor_sessions`, `telemetry_ping`, `health_status`, `performance`, `usage_heatmap`, `timeline` |
| Banco | `db_overview`, `db_index_suggestions`, `sql_stream` |
| Logs / alertas | `logs_stream`, `alerts`, `errors` |
| IA | `ia_stats`, `ia_admin_analyze`, `ia_code_audit`, `intel` |
| Ops | `deploy`, `deploy_history`, `deploy_rollback`, `jobs`, `dependencies`, `storage_metrics`, `integrations_health` |
| Clientes | `company_health_score`, `health_score_explain`, `clients_geo`, `licensing`, `marketplace` |
| Segurança / sistema | `security_overview`, `platform_config`, `benchmark`, `system_info` |

Boot padrão: `lib/adm_api.php` → `adm_api_boot()` (auth `admin_id` + CSRF em mutações).

---

## 4. Schema e migrações

| Arquivo | Conteúdo |
|---------|----------|
| `sql/adm_control_center_foundation.sql` | Eventos, roadmap, métricas, telemetria base |
| `sql/adm_control_center_phase2.sql` | Jobs, integrações, licenças, marketplace, health score, custos |
| `sql/adm_control_center_phase3.sql` | Custos mensais CRUD, config plataforma, heatmap_days |
| `sql/adm_control_center_phase5.sql` | Cache `adm_storage_usage`, progresso roadmap 2.5–2.7 |

Runtime: `adm_schema_ensure()` aplica migrações quando a assinatura muda (`pages_adm/lib/adm_schema.php`).

---

## 5. Integração produto (Fase 5) — `includes/sf_saas.php`

| Gate | Onde aplicado |
|------|---------------|
| `status_saas` (trial/ativa/bloqueada/cancelada/suspensa) | `login.php`, `selecionar_empresa.php`, `require_authentication()` |
| Trial expirado | Mesmos pontos |
| `maintenance_mode` (plataforma) | `login.php` bloqueia clientes |
| Marketplace módulos | `sidebar_pages.php`, `header.php` (CT-e, MDF-e, financeiro, GPS, IA) |
| Limite veículos | `api/vehicle_data.php` (INSERT), `adm_licenca_limites` |
| Modo suporte | `suporte.php` → impersonation; `sair_suporte.php` restaura sessão ADM |

**Impersonation:** backup `_adm_session_backup`, banner no `header.php`, auditoria `adm_auditoria_suporte` + `adm_eventos`.

---

## 6. Matriz de maturidade (atualizada)

| Área | Status | Notas |
|------|--------|-------|
| Login ADM + 2FA | ✅ | `usuarios_adm`, TOTP |
| CRUD empresas/planos/usuários | ✅ | Health score, sync limites, log upgrade/downgrade |
| Posição financeira | ✅ | Boletos, CSRF em POSTs |
| Dashboard executivo | ✅ | Poll + Chart.js com `destroyChart()` |
| Monitor / NOC / SLA | 🟡 | SLA sem monitor externo real |
| Métricas SaaS (MRR/ARR) | ✅ | `finance_metrics`, painel financeiro |
| IA plataforma | 🟡 | Rules engine + stats; não é LLM cross-tenant |
| Modo suporte | 🟡 | Fluxo completo; falta relatório exportável |
| Deploy / rollback | 🟡 | Rollback manual; auto só com `SF_ADM_AUTO_ROLLBACK=1` |
| Storage por tenant | ✅ | Cache DB + cron `cron/adm_cc_maintenance.php` |
| Geocoding mapa | 🟡 | Fallback cidade/UF |
| CSRF ADM | 🟡 | Forms principais OK; revisar APIs legadas |
| God files | 🔴 | `empresas.php`, `posicao_financeira.php`, `adm_phase2.php` |

---

## 7. Roadmap `adm_roadmap` (progresso)

| Versão | Título | Progresso | Status |
|--------|--------|-----------|--------|
| 2.5.0 | Control Center ADM | **78%** | desenvolvimento |
| 2.6.0 | IA administrativa | **55%** | desenvolvimento |
| 2.7.0 | Modo suporte auditado | **40%** | desenvolvimento |
| 2.4.0 | Segurança enterprise | 100% | concluído |

---

## 8. O que ainda falta (backlog priorizado)

### Alta prioridade
- Refatorar god files (`empresas.php`, `posicao_financeira.php`, `adm_phase2.php`) em controllers + partials

### Concluído nesta fase (pós-SQL phase5)
- Enforcement limites `ia`, `storage_mb`, `api_calls`, `motoristas` nas APIs
- Geocoding Nominatim + cache (`lib/adm_geocode.php`)
- Healthcheck público `api/healthcheck.php`
- SLA com probe externo (`sla_probe_url` em Config CC)
- Rollback deploy via `deploy_auto_rollback=1` em Config CC
- Export CSV auditoria suporte
- Cron Windows `tools/adm_cc_maintenance.bat`
- `licencas.php` redireciona para `licenciamento.php`

### Média prioridade
- Deprecar APIs legadas `pages_adm/api/` em favor de v1

### Baixa prioridade / futuro
- WebSocket/SSE para logs (hoje polling)
- IA administrativa com LLM cross-tenant (política de dados)
- Multi-região / sharding (fora do escopo atual)

---

## 9. Validação recomendada

```bash
# Sintaxe PHP
php -l pages_adm/seguranca_login.php
php -l api/vehicle_data.php

# Migração Fase 5
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_control_center_phase5.sql
```

**Testes manuais:**
1. Login com empresa `cancelada` → mensagem e bloqueio
2. `maintenance_mode=1` em `adm_platform_config` → login cliente bloqueado
3. Marketplace: desativar módulo `ia` → menu IA/BI oculto
4. Limite veículos no plano → API `vehicle_data?action=add` retorna 403
5. Modo suporte → banner → Encerrar suporte → volta ao `pages_adm/suporte.php`

---

## 10. Arquitetura (referência rápida)

```
pages_adm/
├── login.php, includes/adm_auth.php
├── api/v1/*.php          # JSON APIs
├── lib/
│   ├── adm_schema.php    # Migrações idempotentes
│   ├── adm_phase2.php    # Domínio Fase 2 (licensing, marketplace, storage…)
│   └── adm_api.php       # Boot API
├── js/adm-telemetry.js
└── components/           # page_header, cards NOC

includes/sf_saas.php       # Gates produto (sessão + módulos + limites)
cron/adm_cc_maintenance.php
sql/adm_control_center_*.sql
```

**Autenticação:** `usuarios_adm` + `$_SESSION['admin_id']` (separado do frotas).  
**Tenant:** `empresa_adm` (contrato SaaS) ↔ `empresa_clientes` (tenant operacional).

---

*Documento substitui a auditoria pré-implementação de jun/2026. Atualizar após cada fase major ou mudança de schema.*
