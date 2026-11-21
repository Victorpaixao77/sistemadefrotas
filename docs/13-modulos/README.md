[← Índice](../README.md)

# 13 · Módulos

Cada módulo agrupa telas + APIs + tabelas com um objetivo de negócio. Muitos já têm
documento dedicado em `docs/` (coluna "Doc detalhado").

| Módulo | Telas principais (`pages/`) | APIs | Doc detalhado |
|--------|----------------------------|------|---------------|
| **TMS / Operação** | clientes, ordens_transporte, aprovacoes, planejamento, routes, simulador_rota, centro_operacoes, central_alertas | clientes, ordens_transporte, aprovacoes, planejamento, rotas | [`../../CLAUDE.md`](../../CLAUDE.md) (seção TMS) |
| **Frota / Veículos** | vehicles, implementos | vehicles/veiculos, implementos | — |
| **Motoristas** | motorists, gestao_motoristas, indicadores_desempenho | motoristas, gestao_motoristas, ranking_motoristas | [IMPLEMENTACAO_MOTORISTAS_COMPLETO](../IMPLEMENTACAO_MOTORISTAS_COMPLETO.md) |
| **Abastecimento** | abastecimentos, abastecimentos_motoristas | refuel_*, combustiveis | — |
| **Manutenção** | manutencoes, planos_manutencao, manutencao_preditiva | manutencoes, maintenance_*, manutencao_preditiva | [MANUTENCAO_ESTADO_ATUAL](../MANUTENCAO_ESTADO_ATUAL.md), [PLANOS_MANUTENCAO](../PLANOS_MANUTENCAO.md) |
| **Pneus** | pneus, pneus_dashboard, pneus_inventario, estoque_pneus, manutencao_pneus, gestao_interativa(_pro), pneu_ficha | pneus_*, pneu_manutencao_*, estoque_pneus, deslocar_pneu | [GESTAO_PNEUS_ANALISE](../GESTAO_PNEUS_ANALISE.md), [GESTAO_PNEUS_TABELAS](../GESTAO_PNEUS_TABELAS.md) |
| **Financeiro** | lucratividade, contas_pagar, despesas_fixas, financiamento, multas, comissoes | lucratividade_*, contas_pagar_*, commissions_*, multas_* | — |
| **Fiscal** | fiscal (+ `fiscal/pages/`) | pef_ciot, denatran_infracoes (+ `fiscal/api/`) | [MANUAL_FISCAL_INDICE](../MANUAL_FISCAL_INDICE.md) |
| **Rotas** | routes, import_rotas_excel | rotas, route_*, routes_data, rotas_google_maps | [ROTAS](../ROTAS.md), [IMPORTACAO_XML_PDF_ROTAS](../IMPORTACAO_XML_PDF_ROTAS.md) |
| **GPS** | mapa_frota, gps_cercas | get_posicoes, gps_* | — |
| **BI** | bi | bi_drill_down, bi_resumo_ia, dashboard_data | [BI_LISTA_COMPLETA](../BI_LISTA_COMPLETA.md) |
| **IA** | ia_painel, ia_dashboard | *_insights, *_resumo_ia, ia_custos_fraudes, vision_ai_config | — |
| **Relatórios** | relatorios | auto_reports, relatorios_agendar | — |
| **Usuários / Config** | usuarios, configuracoes, empresa, perfil | usuarios, usuarios_2fa, seguranca, configuracoes | [PERMISSOES](../PERMISSOES.md) |
| **Notificações** | notificacoes | notifications, alertas_sistema | — |
| **App do motorista** | (app_android) | app_android/api | [DOCUMENTACAO_APP](../../app_android/DOCUMENTACAO_APP.md) |
| **Plataforma (adm)** | `pages_adm/` | — | [plataforma-adm.md](plataforma-adm.md) · [AUDITORIA_CONTROL_CENTER_ADM](../AUDITORIA_CONTROL_CENTER_ADM.md) |
| **Sistema Seguro** | (módulo `seguro_*`) | — | [05b · Sistema Seguro](../05b-tabelas-por-modulo.md#-sistema-seguro-módulo-seguro_--comentários-oficiais) |

Padrões de UI comuns a todas as telas web: [padroes-ui.md](padroes-ui.md).
