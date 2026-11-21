[← Telas](README.md) · [← Índice](../README.md)

# Financiamentos

## Descrição / Objetivo
Controle de **financiamentos** (de veículos/implementos): contrato, parcelas, banco e
status de pagamento. Cada financiamento gera N parcelas com vencimento.

## Quem utiliza / Permissões
Gestor financeiro. Sessão + `empresa_id`; escrita com CSRF.

## Fluxo
Cadastra financiamento (vincula veículo, banco, nº parcelas) → sistema gera `parcelas_financiamento`
→ baixa parcela a parcela (status). Parcelas a vencer alimentam alertas/calendário.

## Campos
- `financiamentos` (ver [05a](../05a-dicionario-dados.md#financiamentos)): `veiculo_id`,
  `banco_id`, valor total, nº parcelas, taxa, datas.
- `parcelas_financiamento` (73 linhas): número, valor, vencimento, `status_pagamento_id`.
Domínios: `bancos`, `status_pagamento`.

## Botões
| Botão | Ação |
|-------|------|
| Novo financiamento | Modal (gera parcelas) |
| Salvar / Editar / Excluir | `api/financiamentos.php` (+ CSRF) |
| Baixar parcela | Atualiza `status_pagamento_id` |

## Filtros
Período, banco, veículo, status.

## Regras de negócio
Parcela é conta a pagar com vencimento; entra no custo/financeiro. Ver [06](../06-regras-de-negocio.md#financeiro).

## Arquivos envolvidos
- Página: `pages/financiamento.php` (~2.245 linhas)
- API: `api/financiamentos.php`
- JS: `sf-charts-premium.js`, `sf-periodo.js`
- Tabelas: `financiamentos`, `parcelas_financiamento`, `bancos`, `status_pagamento`, `veiculos`

## Possíveis erros
Nº de parcelas inválido; datas; 401. Ver [16](../16-troubleshooting.md).

## Relacionamentos
Veículos (ativo financiado), Financeiro/Lucratividade, Calendário (vencimentos).
