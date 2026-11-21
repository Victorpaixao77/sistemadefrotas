[← Telas](README.md) · [← Índice](../README.md)

# Multas

## Descrição / Objetivo
Gestão de **multas de trânsito** por veículo/motorista: cadastro, valor, vencimento,
infração e responsável. Multas pendentes viram alerta e evento no calendário (via triggers).

## Quem utiliza / Permissões
Gestor. Sessão + `empresa_id`; escrita com CSRF.

## Fluxo
Lista multas (por período/veículo/motorista) → novo/editar/excluir → status/pagamento.
Trigger sincroniza a multa no calendário (`trigger_multa_*_calendario`).

## Campos (tabela `multas` — ver [05a](../05a-dicionario-dados.md#multas))
Principais: `veiculo_id`, `motorista_id`, valor, data da infração, vencimento, descrição/AIT,
status/pagamento. Pode importar do DENATRAN (`api/denatran_infracoes.php`).

## Botões
| Botão | Ação |
|-------|------|
| Nova multa | Modal |
| Salvar / Editar / Excluir | `api/multas.php` (+ CSRF) |
| Analytics | `api/multas_analytics.php` |

## Filtros
Período, veículo, motorista, status.

## Regras de negócio
Multa pode ser descontada na **comissão** do motorista responsável. Vencimento alimenta
calendário/alertas (trigger). Ver [06](../06-regras-de-negocio.md#financeiro).

## Arquivos envolvidos
- Página: `pages/multas.php` (~871 linhas)
- APIs: `api/multas.php`, `api/multas_analytics.php`, `api/denatran_infracoes.php` (import)
- JS: `js/multas.js`, `sf-charts-premium.js`
- Tabelas: `multas`, `veiculos`, `motoristas`; trigger → `calendario_eventos`

## Possíveis erros
Validação de valor/data; 401. Ver [16](../16-troubleshooting.md).

## Relacionamentos
Motoristas (responsável/comissão), Veículos, Calendário, DENATRAN.
