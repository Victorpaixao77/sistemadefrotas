[← Índice](README.md)

# 07 · APIs

136 endpoints em `api/*.php` (web) + ~30 em `app_android/api/*.php` (app, autenticados por
token). Documento legado relacionado: [WEB_API.md](WEB_API.md). Endpoints do app:
[`../app_android/docs/ENDPOINTS.md`](../app_android/docs/ENDPOINTS.md).

## Convenções (todas as APIs web)

| Aspecto | Regra |
|---------|-------|
| **Sessão/auth** | `configure_session()` + `session_start()` + `require_authentication()` no topo |
| **Isolamento** | Toda query filtra `empresa_id` (`$_SESSION['empresa_id']`) |
| **Método** | Leitura por `GET` (com `?action=`), escrita por `POST` |
| **CSRF** | Ações que mudam estado: `csrf_token_validate($_POST['csrf_token'])` / `api_require_csrf_json()` |
| **Resposta** | JSON: `{"success":true, ...}` ou `{"success":false,"error":"..."}` + HTTP adequado |
| **Erros** | `catch (InvalidArgumentException)` → `echo $e->getMessage()` (mensagem própria, segura); `catch (Throwable)` → `error_log()` + mensagem genérica. **Nunca** vazar erro de PDO/sistema |
| **Prepared** | 100% prepared statements, placeholders nomeados |
| **Padrão de retorno vazio** | Sem dado → array vazio / zeros, **nunca** dado fabricado |

### Contrato típico

```
GET  api/clientes.php?action=list&status=ativo&busca=acme
POST api/clientes.php   (body x-www-form-urlencoded)  action=save&csrf_token=...&razao_social=...
→ 200 {"success":true,"clientes":[...]}     |  401 {"error":"Acesso não autorizado"}
```

Front usa `sfApiUrl()` / `window.__SF_API_BASE__` / `window.__SF_CSRF__` (de `sf_api_base.php`).

## Índice por domínio

### TMS / operação
`clientes.php`, `ordens_transporte.php`, `aprovacoes.php`, `planejamento.php`,
`central_alertas.php`, `centro_operacoes.php`, `rotas.php`, `route_data.php`,
`route_actions.php`, `route_anomalies.php`, `route_export.php`, `routes_data.php`,
`rotas_mapa.php`, `rotas_google_maps.php`, `import_rotas.php`, `implementos.php`.

### Frota / veículos
`vehicles.php`, `veiculos.php`, `vehicle_data.php`, `vehicle_export.php`,
`vehicle_insights.php`, `vehicle_resumo_ia.php`, `carrocerias.php`, `categorias.php`,
`tipos.php`, `rendimento_veiculo.php`, `validar_quilometragem.php`.

### Motoristas
`motoristas.php`, `motorist_data.php`, `motorist_actions.php`, `motorist_insights.php`,
`motorist_resumo_ia.php`, `motorist_efficiency_analytics.php`,
`motorist_performance_analytics.php`, `motorist_performance_chart.php`,
`ranking_motoristas.php`, `gamificacao_motoristas.php`, `gestao_motoristas*.php`,
`usuarios_motoristas.php`.

### Abastecimento
`refuel_data.php`, `refuel_actions.php`, `refuel_export.php`, `refuel_anomalies.php`,
`refuel_import_bulk.php`, `refuel_ocr_openai.php`, `import_abastecimentos_azure.php`,
`combustiveis.php`, `tipos_combustivel.php`.

### Manutenção / pneus
`manutencoes.php`, `maintenance_data.php`, `maintenance_import.php`,
`maintenance_ocr_nf.php`, `maintenance_insights.php`, `maintenance_resumo_ia.php`,
`manutencao_anexos.php`, `manutencao_preditiva.php`, `planos_manutencao.php`;
`pneus*.php`, `pneus_data.php`, `pneus_acoes.php`, `pneu_manutencao_*.php`,
`estoque_pneus.php`, `deslocar_pneu.php`, `gestao_pneu_pro.php`, `pneus_mercado.php`.

### Financeiro
`lucratividade_*.php`, `financial_analytics.php`, `net_revenue_analytics.php`,
`profit_forecast_analytics.php`, `profit_per_km_analytics.php`, `projecao_lucro.php`,
`cost_per_km_analytics.php`, `distribuicao_custos.php`, `expenses_distribution.php`,
`contas_pagar_*.php`, `despesas_fixas.php`, `financiamentos.php`, `multas*.php`,
`commissions_actions.php`, `commissions_analytics.php`, `comissoes_*.php`,
`payment_methods.php`, `formas_pagamento.php`, `margem_tipo_frete.php`.

### GPS
`get_posicoes.php`, `gps_posicoes.php`, `gps_historico.php`, `gps_cercas.php`,
`gps_cerca_alertas.php`, `gps_alertas_operacionais.php`.

### BI / IA
`bi_drill_down.php`, `bi_resumo_ia.php`, `dashboard_data.php`, `performance_indicators.php`,
`ia_custos_fraudes.php`, `*_insights.php`, `*_resumo_ia.php`, `vision_ai_config.php`.

### Fiscal / integrações
`pef_ciot.php`, `denatran_infracoes.php`, `cnpj_brasilapi.php`, `fornecedor_*.php`.

### Sistema / segurança / plataforma
`auth.php`, `usuarios.php`, `usuarios_2fa.php`, `seguranca.php`, `log_acessos.php`,
`notifications.php`, `alertas_sistema.php`, `trocar_empresa.php`, `configuracoes.php`,
`healthcheck.php`, `auto_reports.php`, `relatorios_agendar.php`, `save_layout.php`,
`download_anexo.php`.

## Exemplo documentado — `api/clientes.php`

| Item | Valor |
|------|-------|
| **Objetivo** | CRUD de clientes/embarcadores |
| **Auth** | Sessão + `empresa_id` |
| **Ações** | `?action=list` (GET, filtros `status`,`busca`), `?action=get&id=`, `save`/`delete` (POST + CSRF) |
| **Request (save)** | `action=save&csrf_token=…&razao_social=…&tipo_pessoa=PJ&status=ativo` |
| **Response** | `{"success":true,"clientes":[{"id":1,"razao_social":"…","ots":3,"status":"ativo"}]}` |
| **Erros** | `401` sem sessão; `{"success":false,"error":"Informe a razão social."}` (validação) |

> **Backlog:** documentar **cada** endpoint no padrão acima (objetivo, ações, request/response,
> erros) é incremental — ver [99-validacao-final.md](99-validacao-final.md).
