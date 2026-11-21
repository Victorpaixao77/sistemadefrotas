[← Telas](README.md) · [← Índice](../README.md)

# Ordens de Transporte (OT)

## Descrição
Lista e gestão das ordens de transporte — o pedido comercial de frete. Resumo (KPIs por
status), busca, filtro por status, paginação e modal de cadastro.

## Objetivo
Conduzir o frete de **cotação → aprovada → em viagem → concluída** (ou cancelada), gerar a
rota e faturar (CT-e), sem retrabalho.

## Quem utiliza
Gestor / comercial / operação.

## Permissões
Sessão + `empresa_id`; escrita com CSRF.

## Fluxo completo
1. **Nova OT** (cotação) — cliente, trecho, carga, distância, frete, custo (usa Simulador/ANTT).
2. **Aprovar** (ou pela tela Aprovações) → status `aprovada`.
3. **Atribuir** veículo/motorista e **Gerar rota** (aqui ou no Planejamento).
4. Em viagem → **Concluir**. **Emitir CT-e** (aprovada/em viagem). **Link de rastreio** p/ cliente.
Diagrama: [15-fluxos](../15-fluxos/README.md).

## Campos (modal, principais)
| Campo | Tipo | Obrig. | Coluna | Obs |
|-------|------|:------:|--------|-----|
| Cliente (cadastro) | select | não | `ordens_transporte.cliente_id` | preenche nome/doc |
| Nome do cliente / CNPJ | text | não | `cliente_nome`/`cliente_doc` | avulso |
| Origem/UF · Destino/UF | text | sim | `origem/estado_origem/destino/estado_destino` | — |
| Descrição da carga | text | não | `descricao_carga` | — |
| Distância (km) · Peso (kg) | number | não | `distancia_km`/`peso_carga` | — |
| Tipo de carga · Eixos | select/number | sim | `tipo_carga`/`eixos` | base do piso ANTT |
| Valor do frete · Custo estimado | number | sim/não | `valor_frete`/`valor_custo_estimado` | — |
| Datas coleta/entrega | datetime | não | `data_coleta`/`data_entrega` | — |

## Botões (ações por linha = `.btn-icon`)
| Botão | O que faz | Ação |
|-------|-----------|------|
| **Nova OT** | Modal em branco | `abrirOT()` |
| Editar | Carrega no modal | `GET action=get` |
| Rastreio | Gera/mostra link por token | `token_rastreio` |
| Emitir CT-e | Wizard CT-e pré-preenchido | `emitirCte()` (fiscal) |
| Aprovar | cotação→aprovada | `action=mudar` |
| Gerar rota | Cria rota real | `action=gerar_rota` |
| Concluir | em_viagem→concluida | `action=mudar` |
| Cancelar / Excluir | Cancela / remove | `action=mudar` / `delete` |

## Filtros
Busca (nº/cliente/origem/destino), Status, **Por página**.

## Regras de negócio
- OT aprovada **gera rota** em `rotas` (não recria dados).
- `atribuir` grava só veiculo/motorista/data_coleta (UPDATE isolado).
- `gerar_rota`: `rotas.data_chegada` é `NOT NULL` → fallback `data_entrega ?: data_saida`.
- Rastreio escopado só pela OT do token. Ver [06-regras-de-negocio.md](../06-regras-de-negocio.md).

## Consultas SQL
`SELECT` de OTs por `empresa_id` (+ status) com placa/motorista; `INSERT`/`UPDATE` de OT;
`INSERT` em `rotas` ao gerar; `UPDATE` isolado de `ordem_transporte_id` no CT-e.

## Arquivos envolvidos
- Página: `pages/ordens_transporte.php` · API: `api/ordens_transporte.php` · SQL: `sql/ordens_transporte.sql`
- Fiscal (CT-e): `fiscal/pages/partials/cte_wizard_modal.php`, `fiscal/pages/js/cte-wizard.js`
- Ajuda: `includes/page_help/09_tms_novos.php` (chave `ordens_transporte`)
- Tabelas: `ordens_transporte`, `rotas`, `clientes`, `veiculos`, `motoristas`, `fiscal_cte`

## APIs / retorno
| Endpoint | Método | Retorno |
|----------|--------|---------|
| `api/ordens_transporte.php?action=list` | GET | `{"success":true,"ordens":[…]}` |
| `api/ordens_transporte.php` (save/mudar/gerar_rota/atribuir/delete) | POST | `{"success":true,…}` |

## Possíveis erros
Validação de campos; falha ao gerar rota (checar `rotas` NOT NULL); 401. Ver [16](../16-troubleshooting.md).

## Relacionamentos
Origem do fluxo TMS: alimenta Aprovações, Planejamento, Rotas, CT-e e Financeiro.
