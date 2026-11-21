[← Telas](README.md) · [← Índice](../README.md)

# Despesas Fixas

## Descrição / Objetivo
Cadastro de **despesas fixas** (mensais/recorrentes) que são **rateadas** na lucratividade.
Ex.: aluguel, salários, seguros, softwares.

## Quem utiliza / Permissões
Gestor financeiro. Sessão + `empresa_id`; escrita com CSRF.

## Fluxo
Lista despesas → novo/editar/excluir → o valor entra no rateio de custos fixos da margem.

## Campos (tabela `despesas_fixas` — ver [05a](../05a-dicionario-dados.md#despesas_fixas))
Principais: descrição, valor, `tipo_despesa_fixa_id`, periodicidade/competência, status.
Domínio: `tipos_despesa_fixa` (17 tipos).

## Botões
| Botão | Ação |
|-------|------|
| Nova despesa | Modal |
| Salvar / Editar / Excluir | `api/despesas_fixas.php` (+ CSRF) |

## Filtros
Período (`sf-periodo.js`), tipo.

## Regras de negócio
Despesa fixa é **custo fixo rateado** na Lucratividade. Ver [06](../06-regras-de-negocio.md#financeiro).

## Arquivos envolvidos
- Página: `pages/despesas_fixas.php` (~611 linhas)
- API: `api/despesas_fixas.php`
- JS: `js/despesas_fixas.js`, `sf-charts-premium.js`, `sf-periodo.js`
- Tabelas: `despesas_fixas`, `tipos_despesa_fixa`

## Possíveis erros
Validação de valor; 401. Ver [16](../16-troubleshooting.md).

## Relacionamentos
Lucratividade (rateio de fixos).
