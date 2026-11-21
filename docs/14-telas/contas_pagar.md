[← Telas](README.md) · [← Índice](../README.md)

# Contas a Pagar

## Descrição / Objetivo
Gestão de **contas a pagar** da empresa: cadastro, vencimentos, status de pagamento,
banco/forma de pagamento e insights. Alimenta alertas e o calendário de vencimentos.

## Quem utiliza / Permissões
Gestor financeiro. Sessão + `empresa_id`; escrita com CSRF.

## Fluxo
Lista contas (por período/status) → novo/editar/baixar (marcar paga) → gráficos + resumo IA.

## Campos (tabela `contas_pagar` — ver [05a](../05a-dicionario-dados.md#contas_pagar))
Principais: descrição, valor, vencimento, `status_contas_pagar_id`, `forma_pagamento_id`,
`banco_id`, fornecedor/observações. Domínios: `status_contas_pagar`, `formas_pagamento`, `bancos`.

## Botões
| Botão | Ação |
|-------|------|
| Nova conta | Abre modal |
| Salvar | `api/contas_pagar_actions.php` (save) + CSRF |
| Editar / Excluir | actions correspondentes |
| Baixar/Pagar | Atualiza status |

## Filtros
Período (`sf-periodo.js`), status, fornecedor.

## Regras de negócio
Vencimento vencido/próximo aparece na Central de Alertas/Calendário. Ver [06](../06-regras-de-negocio.md#financeiro).

## Arquivos envolvidos
- Página: `pages/contas_pagar.php` (~1.839 linhas)
- APIs: `api/contas_pagar_actions.php` (escrita), `api/contas_pagar_analytics.php`, `api/contas_pagar_insights.php`
- JS: `sf-charts-premium.js`, `sf-periodo.js`, `sf_insights.js`
- Tabelas: `contas_pagar`, `status_contas_pagar`, `formas_pagamento`, `bancos`

## Possíveis erros
Validação de valor/vencimento; 401. Ver [16](../16-troubleshooting.md).

## Relacionamentos
Financeiro/lucratividade (custos), calendário (vencimentos), fornecedores.
