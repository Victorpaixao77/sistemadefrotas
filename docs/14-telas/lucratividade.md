[← Telas](README.md) · [← Índice](../README.md)

# Lucratividade

## Descrição / Objetivo
Painel analítico de **margem da operação**: receita (fretes) − custos variáveis
(combustível, pedágio, comissão) − fixos rateados, por rota/veículo/motorista/período.
Tela pesada de **leitura** (dashboards, projeções, IA), sem CRUD.

## Quem utiliza / Permissões
Gestor com permissão. Protegida por `require_permission('access_lucratividade')` (+ isolamento `empresa_id`).

## Fluxo
Seleciona período (`js/sf-periodo.js`) → chama vários endpoints de analytics → renderiza
gráficos (`js/sf-charts-premium.js`) + resumo IA (`js/sf_insights.js`). Drill-down por KPI.

## Filtros
Período (mês/trimestre/ano), veículo, motorista, rota (conforme os endpoints).

## Regras de negócio
Ver [06-regras-de-negocio.md](../06-regras-de-negocio.md#financeiro). Rota sem frete não
gera receita; custos vêm de abastecimentos/pedágio/comissão + despesas fixas rateadas.

## Arquivos envolvidos
- Página: `pages/lucratividade.php` (~2.568 linhas)
- APIs: `api/evolucao_lucratividade.php`, `api/profit_per_km_analytics.php`,
  `api/profit_forecast_analytics.php`, `api/projecao_lucro.php`, `api/cost_per_km_analytics.php`,
  `api/distribuicao_custos.php`, `api/margem_tipo_frete.php`, `api/lucratividade_insights.php`,
  `api/lucratividade_resumo_ia.php`
- JS: `sf-charts-premium.js`, `sf-periodo.js`, `sf_insights.js`
- Fontes de dado: `rotas`, `abastecimentos`, `despesas_viagem`, `despesas_fixas`, `comissoes_pagamentos`

## APIs / retorno
Todas GET, retornam JSON agregado por `empresa_id`+período (`{"success":true,...}`) para os gráficos.

## Possíveis erros
Sem permissão → bloqueio; período sem dados → gráficos vazios (não fabrica). Ver [16](../16-troubleshooting.md).

## Relacionamentos
Consome o resultado de Rotas/Abastecimento/Comissões; espelhado no Centro de Operações.
