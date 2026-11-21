[← Módulos](README.md) · [← Índice](../README.md)

# Módulo · Plataforma / Control Center (`pages_adm/`)

Painel de **administração da plataforma** (visão do dono do SaaS, não do cliente). ~128
arquivos em `pages_adm/`, tabelas `adm_*` e views `view_seguro_*`. Auditoria detalhada:
[AUDITORIA_CONTROL_CENTER_ADM.md](../AUDITORIA_CONTROL_CENTER_ADM.md).

> Acesso separado (`pages_adm/login.php`), fora do fluxo do gestor. Suporte pode impersonar
> uma empresa (auditado em `adm_auditoria_suporte`).

## Telas do painel (por área)

| Área | Telas (`pages_adm/*.php`) | Tabelas base |
|------|---------------------------|--------------|
| **Home / visão** | `index.php`, `monitor.php`, `saude.php`, `health_score.php` | `adm_health_checks`, `adm_empresa_scores` |
| **Empresas-cliente** | `empresas.php`, `acessar_frotas.php`, `mapa_clientes.php` | `empresa_clientes`, `adm_empresa_modulos` |
| **Comercial / planos** | `comercial.php`, `planos.php`, `licencas.php`, `licenciamento.php`, `marketplace.php` | `adm_planos`, `adm_modulos`, `adm_licenca_limites` |
| **Financeiro plataforma** | `financeiro_executivo.php`, `posicao_financeira.php`, `custos.php` | `adm_pagamentos`, `adm_boletos`, `adm_config_boletos`, `adm_custos_mensais` |
| **Operação técnica** | `apis.php`, `jobs.php`, `deploy.php`, `backup.php`, `banco.php`, `performance.php`, `benchmark.php` | `adm_cron_jobs`, `adm_cron_runs`, `adm_deploys`, `adm_request_metrics`, `adm_storage_usage` |
| **Observabilidade** | `logs.php`, `log_acessos.php`, `erros.php`, `auditoria.php`, `analytics.php`, `heatmap.php` | `adm_sql_log`, `log_acessos`, `adm_auditoria_suporte` |
| **IA (interno)** | `ia_admin.php`, `ia_comercial.php`, `ia_dev.php`, `ia_estatisticas.php` | `adm_ia_usage` |
| **Comunicação / roadmap** | `comunicacao.php`, `alertas.php`, `roadmap.php` | `adm_comunicacoes`, `adm_comunicacao_modelos`, `adm_alertas`, `adm_roadmap` |
| **Config** | `configuracoes_cc.php`, `conta_adm.php`, `dependencias.php` | `adm_platform_config` |

## Rotinas ligadas ao painel

- `cron/adm_cc_maintenance.php` (a cada 15 min): storage/cache, purge de logs, cron vencidos.
- Métricas de requisição (`adm_request_metrics`, ~10 mil linhas) e health-checks
  (`adm_health_checks`, ~865) alimentam `monitor.php`/`saude.php`.

## Observações

- **Backlog:** documentar tela a tela do painel adm (campos/botões/regras) está pendente —
  usar o [`_TEMPLATE.md`](../14-telas/_TEMPLATE.md). O grosso da lógica de negócio da
  plataforma está aqui e ainda **não** tem doc campo-a-campo.
- Isolamento: o painel adm **cruza empresas** por natureza (é o dono do SaaS) — as checagens
  de `empresa_id` valem para o gestor, não para o adm. Tratar com cuidado de segurança.
