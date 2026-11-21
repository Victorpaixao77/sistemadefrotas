[← Telas](README.md) · [← Índice](../README.md)

# Comissões

## Descrição / Objetivo
Cálculo e **fechamento de comissões** dos motoristas sobre os fretes das rotas concluídas,
com adiantamentos e descontos (multas/danos), gráficos e resumo IA.

## Quem utiliza / Permissões
Gestor financeiro. Protegida por `can_view_financial_data()` (+ `empresa_id`).

## Fluxo
Seleciona período → sistema consolida rotas concluídas e calcula a comissão por motorista →
aplica adiantamentos/descontos → fecha e registra pagamento. Exportável.

## Campos (tabela `comissoes_pagamentos` — ver [05a](../05a-dicionario-dados.md#comissoes_pagamentos))
Principais: `motorista_id`, período, base (frete), % / valor, adiantamentos, descontos,
valor líquido, status/pagamento.

## Botões
| Botão | Ação |
|-------|------|
| Fechar período | `api/commissions_actions.php` |
| Registrar pagamento / editar | actions correspondentes |
| Exportar | `pages/comissoes_export.php` |
| Analytics / Insights | `api/commissions_analytics.php`, `api/comissoes_insights.php`, `api/comissoes_resumo_ia.php` |

## Filtros
Período (`sf-periodo.js`), motorista.

## Regras de negócio
Só rotas **concluídas** com frete geram comissão; rota sem frete → comissão zerada (validar
cadastro). Descontos de multa/dano documentados. Ver [06](../06-regras-de-negocio.md#financeiro).

## Arquivos envolvidos
- Página: `pages/comissoes.php` (~885 linhas) + `pages/comissoes_export.php`
- APIs: `api/commissions_actions.php`, `api/commissions_analytics.php`,
  `api/comissoes_insights.php`, `api/comissoes_resumo_ia.php`
- JS: `js/comissoes.js`, `sf-charts-premium.js`, `sf-periodo.js`, `sf_insights.js`
- Tabelas: `comissoes_pagamentos`, `rotas`, `motoristas`, `multas`

## Possíveis erros
Rota sem frete (comissão 0); período sem rotas concluídas; 401. Ver [16](../16-troubleshooting.md).

## Relacionamentos
Rotas (base), Motoristas, Multas (descontos), Lucratividade (custo de comissão).
