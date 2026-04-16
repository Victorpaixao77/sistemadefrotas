# Posição financeira / fiscal do fornecedor

## Migração recomendada

1. `create_fornecedores.sql` — se ainda não criou a tabela.
2. `alter_contas_pagar_fornecedor_id.sql` — adiciona `fornecedor_id` em `contas_pagar` (sem saldo denormalizado).

## Como funciona

- **Financeiro** (`api/fornecedor_posicao.php?action=financeiro`): lista `contas_pagar` onde `fornecedor_id` = fornecedor **ou** (legado) nome em `fornecedor` igual ao cadastro.
- **Fiscal** (`action=fiscal`):
  - **NF-e emitidas** (`fiscal_nfe_emitidas`): notas em que seu emitente vendeu para este CPF/CNPJ (destinatário).
  - **NF-e recebidas** (`fiscal_nfe_clientes`): notas de entrada casadas pelo documento do emitente em `cliente_cnpj` (mesmo campo usado no import XML).

## View opcional

Ver `create_view_posicao_fornecedor_exemplo.sql` — apenas para linhas já com `fornecedor_id` preenchido.
