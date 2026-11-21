[← Telas](README.md) · [← Índice](../README.md)

# Centro de Operações

## Descrição
Torre de controle **em tempo real** da operação (mês corrente + situação agora), com
atualização automática a cada 60s. Consome um único endpoint e mostra tudo com **dado real**.

## Objetivo
O gestor entender a operação inteira em <10s: frota, financeiro do mês, alertas, eventos,
vencimentos, rankings e pendências — sem trocar de tela.

## Quem utiliza
Gestor / operação. Valores em R$ só para quem tem permissão financeira; CT-e/MDF-e só com
acesso fiscal.

## Permissões
Sessão + `empresa_id`. Dados financeiros/fiscais condicionados à permissão do usuário.

## Fluxo
Carrega `api/centro_operacoes.php` → renderiza seções → *polling* 60s. Cards/tiles/alertas
são clicáveis e levam à tela de origem. Difere do **Dashboard** (`index.php`, histórico com
filtro de período). Diagrama: [15-fluxos](../15-fluxos/README.md).

## Seções (todas com dado real; vazio = zero honesto)
| Seção | Fonte (`data.*`) |
|-------|------------------|
| Barra de status (Sistema/GPS/SEFAZ/CT-e/MDF-e) | `status.gps` (verde só se GPS < 2h) |
| Ações rápidas | links: rotas, ordens, CT-e, MDF-e, abastecimento, mapa, alertas |
| Resumo Inteligente (IA) | `ia.frases` (linguagem natural, honesto) |
| KPIs executivos | `kpis.*` (receita, lucro, lucro/km, km, consumo, disponibilidade, ocupação, em andamento) |
| Mapa da frota (Leaflet) | posições GPS recentes |
| Situação operacional | `situacao.*` (disponíveis/rodando/programadas/manutenção/atrasadas/inativos) |
| Financeiro do mês | `financeiro.*` (receita/lucro/a receber/a pagar/combustível/pedágio/manutenção/margem) |
| Eventos ao vivo | `timeline` (rotas/abastecimentos/CT-e) |
| Alertas crít./atenção | `alertas.*` |
| Próximos vencimentos | `vencimentos` |
| Gráficos 30d | `charts.faturamento` / `charts.consumo` (Chart.js) |
| Rankings | `rankings.motoristas` / `rankings.veiculos` |
| Pendências | `pendencias.*` (CT-e, MDF-e, ordens, aprovações, abastecimentos, manutenções) |

## Botões / interações
| Elemento | O que faz |
|----------|-----------|
| Ações rápidas | Navegam para a tela correspondente |
| Atualizar (↻) | Recarrega os dados na hora |
| Tiles/alertas/pendências | Clicáveis → tela de origem |
| Ajuda (`#helpBtn`) | Modal de ajuda (chave `centro_operacoes`) |

## Regras de negócio
- **Nunca fabricar dado**: sem registro → 0/estado vazio.
- GPS "online" só com posição nas últimas 2h; senão amarelo (não finge online).
- Mês corrente para KPIs/financeiro; 30 dias para gráficos.

## Consultas SQL
Várias agregações por `empresa_id` e período (rotas aprovadas do mês, abastecimentos,
`fiscal_cte`, `fiscal_mdfe`, manutenções, CNH/multas/pneus para alertas). Subqueries
correlatas para rankings; `UNION` para a timeline. Sem N+1.

## Arquivos envolvidos
- Página: `pages/centro_operacoes.php` · API: `api/centro_operacoes.php`
- CSS: inline + `styles/theme/responsive` + Leaflet · JS: inline + Chart.js + Leaflet
- Ajuda: `includes/page_help/09_tms_novos.php` (chave `centro_operacoes`)
- Tabelas: `rotas`, `abastecimentos`, `veiculos`, `motoristas`, `fiscal_cte`, `fiscal_mdfe`,
  `manutencoes`, `gps_logs`, `multas`, `estoque_pneus`, `clientes`, `ordens_transporte`

## APIs chamadas / retorno
| Endpoint | Método | Retorno |
|----------|--------|---------|
| `api/centro_operacoes.php` | GET | `{"success":true,"kpis":{…},"financeiro":{…},"alertas":{…},"timeline":[…],"charts":{…},"rankings":{…},"pendencias":{…},"ia":{…},"status":{…}}` |

## Possíveis erros
`{"success":false}` → mostra estados vazios; 401 → sessão. Ver [16-troubleshooting.md](../16-troubleshooting.md).

## Melhorias futuras
Mapa GPS embutido ao vivo (hoje painel + Leaflet); WebSocket em vez de polling.

## Relacionamentos
Agrega quase todos os módulos; ponto de partida para navegar ao restante do sistema.
