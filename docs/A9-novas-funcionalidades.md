[← Índice](README.md)

# A9 · Novas Funcionalidades (Plataforma de Logística Inteligente)

> Lote de telas novas construídas a partir da lista de evolução do produto. Todas seguem
> as regras do projeto: **dado real** (nunca fictício), padrão visual `fornc-*`/`sf-premium`,
> **isolamento por empresa** (`empresa_id`), **prepared statements**, **CSRF** nas gravações,
> ajuda por página (Passo a Passo + Fluxo) e sem quebra de compatibilidade.

## Resumo

- **21 telas novas** no total (incluindo as 5 da rodada anterior).
- **17 APIs novas** + **4 migrações** de banco.
- Auditadas: 0 erro de console, 0 vazamento de exceção, 0 injeção SQL, auth e CSRF OK.

## ⚠️ Migrações a aplicar (antes de usar em produção)

```bash
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/metas.sql
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/contratos.sql
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/patio_vagas.sql
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/workflow.sql
```
Todas usam `CREATE TABLE IF NOT EXISTS` (idempotentes).

## Telas entregues

| # | Funcionalidade | Página | API | O que faz |
|---|---|---|---|---|
| 3 | **Digital Twin** | `pages/digital_twin.php` | `api/digital_twin.php` | Frota "ao vivo": cada veículo com estado (movimento/parado/sem sinal), mapa e auto-refresh 30s. Reusa `gps_ultima_posicao`. |
| 4 | **Replay de Viagem** | `pages/replay_viagem.php` | `api/gps_replay.php` | Player do trajeto GPS gravado (`gps_logs`) com play/pause, timeline e velocidade 1x–25x (Leaflet). |
| 5 | **Simulação de Crescimento** | `pages/simulacao_crescimento.php` | `api/simulacao_crescimento.php` | "E se eu expandir?": lucro, ROI e payback ao comprar caminhões/contratar, sobre a base real. |
| 6 | **Contratos** | `pages/contratos.php` | `api/contratos.php` | CRUD de contratos (cliente/seguro/locação/leasing…) com alerta de vencimento. Tabela `contratos`. |
| 8 | **Workflow Configurável** | `pages/workflow.php` | `api/workflow.php` | Níveis de aprovação parametrizáveis; solicitações sobem nível a nível com trilha. Tabelas `workflow_*`. |
| 9 | **Scores** | `pages/scores.php` | `api/scores_consolidado.php` | Nota 0–100 de motoristas, veículos e clientes. |
| 10 | **Timeline do Veículo** | `pages/veiculo_timeline.php` | `api/veiculo_timeline.php` | Histórico cronológico unificado (rotas, abastecimentos, manutenções, multas). |
| 13 | **Gestão de Pátio** | `pages/patio.php` | `api/patio.php` | Mapa de vagas por setor; alocar/liberar veículo. Tabela `patio_vagas`. |
| 15 | **Agenda Inteligente** | `pages/agenda_inteligente.php` | *(reusa `central_alertas.php`)* | Vencimentos (CNH, doc, pneu, seguro) por urgência. |
| 16 | **Planejamento de Carga** | `pages/planejamento_carga.php` | `api/planejamento_carga.php` | Peso × capacidade/eixos do veículo, com alerta de excesso. |
| 17 | **Otimização Inteligente** | `pages/otimizacao.php` | `api/otimizacao.php` | Melhor veículo (custo/km) + motorista (score) disponíveis, com simulador de custo/lucro. |
| 19 | **Gestão de Diesel / Postos** | `pages/gestao_diesel.php` | `api/combustivel_postos.php` | Ranking de postos (preço médio, gasto, mais barato/usado) + evolução do preço. |
| 20 | **Painel Executivo** | `pages/painel_executivo.php` | `api/painel_executivo.php` | KPIs estratégicos + tendência faturamento×lucro + rankings. |
| 22 | **QR Codes** | `pages/qr_codes.php` | `api/qr_entidades.php` | Gera/imprime QR de veículos, pneus, implementos e motoristas. |
| 23 | **Metas** | `pages/metas.php` | `api/metas.php` | Metas (faturamento/rotas/km/SLA) com realizado calculado do dado real. Tabela `metas`. |
| 24 | **SLA Operacional** | `pages/sla_operacional.php` | `api/sla_operacional.php` | % de entregas no prazo, tempo médio de viagem, evolução e ranking. |
| 28 | **IA de Decisão** | `pages/ia_decisao.php` | `api/ia_decisao.php` | Consultor: qual veículo trocar, cliente no prejuízo, quem promover/treinar, se vale expandir. |
| 29 | **Previsão Financeira** | `pages/previsao_financeira.php` | `api/previsao_financeira.php` | Projeta faturamento/custo/lucro dos próximos 3 meses por regressão linear. |

## Auditoria (resultado)

- **Autenticação:** as 17 APIs respondem **401/403 sem sessão** (nenhuma vaza dado).
- **CSRF:** as gravações (`metas`, `contratos`, `patio`, `workflow`) exigem token (403 sem ele).
- **Exceções:** `$e->getMessage()` só no ramo `InvalidArgumentException` (mensagem de validação
  própria); erros reais vão para `error_log` + mensagem genérica.
- **SQL:** 100% prepared statements; interpolação só de `empresa_id`/`limit` com cast `(int)`.
- **Front:** 18/18 telas com **0 erro de console** e **ajuda funcional**; 0 regressão nas telas existentes.

## Não entregues (honestamente) — dependem de dado/integração

- **#18 Gestão de Garantias** — `pneus.garantia_ate` está vazio e não há outra fonte de garantia.
  Construir quando houver garantias cadastradas.
- **#12 Biblioteca Digital** — tabelas de anexos/documentos vazias (`manutencao_anexos`,
  `documentos_motoristas`). Idem.
- **#14 Geofencing Inteligente** — 0 cercas cadastradas e é um motor de orquestração que dispara
  ações (POD/checklist) via app do motorista; precisa de cercas + integração no app.

## Ajuda

Todo o conteúdo de ajuda das telas acima está em `includes/page_help/07_outros.php`
(exceto Diesel em `02_operacional.php`, Simulador/Rotas em `01_dashboard_frota.php` e
Gestão de pneus em `04_pneus.php`). Cada entrada tem Visão Geral, Funcionalidades,
**Passo a Passo** e **Fluxo do Processo**.
