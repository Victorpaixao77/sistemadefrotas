[← Índice](README.md)

# 06 · Regras de Negócio

## Multi-empresa (isolamento)

- Toda operação de dados **filtra por `$_SESSION['empresa_id']`**. É o limite entre
  clientes (tenants). Esquecer o filtro = vazamento de dados entre empresas → **bug grave**.
- A empresa é definida no login; usuários com `acesso_todas_empresas` escolhem em
  `selecionar_empresa.php`. Suporte pode impersonar (`sair_suporte.php` encerra).

## Autenticação e sessão

- `require_authentication()` no topo de páginas/APIs. Login sempre cai em **`index.php`**.
- "Lembrar-me" usa token **selector:validator** (`auth_remember_tokens`), nunca cookie
  `base64(id:email)`.
- Logout por inatividade (`sf_enforce_idle_timeout()`); tempo em `$_SESSION['idle_timeout_min']`.
- 2FA TOTP por usuário (colunas `two_factor_*` em `usuarios`). Ver [11-seguranca.md](11-seguranca.md).

## TMS — ciclo do frete

Fluxo: **Cliente → Cotação/OT → Aprovação → Planejamento → Rota → GPS → Entrega/POD →
CT-e → Financeiro**.

- **Ordem de Transporte (OT):** status `cotacao → aprovada → em_viagem → concluida`
  (ou `cancelada`). A OT aprovada **gera uma rota real** (não recria dados). A ação
  `atribuir` grava só `veiculo_id`/`motorista_id`/`data_coleta` (UPDATE isolado — não
  sobrescreve os outros campos).
- **Aprovação:** só OTs em `cotacao`. Aprovar libera para planejamento; rejeitar cancela.
  Toda decisão grava trilha (autor, data, comentário) em `aprovacoes`.
- **Rastreio público:** `rastreio.php` é escopado **apenas pela OT do token** — sem dados
  comerciais de outras OTs/empresas.

## Frete e piso ANTT

- Piso mínimo de frete (**Lei 13.703/2018**) em `includes/sf_antt_frete.php` →
  `sf_antt_calc_piso($conn, $tipo_carga, $eixos, $distancia, $contratacao, $alto_desempenho)`.
- Fórmula: **piso = CCD × km + CC** (coeficientes por tipo de carga/eixos na tabela
  `antt_frete_coeficientes`). O **Simulador de Rota** usa isso para custo, margem-alvo e
  **frete de equilíbrio (lucro 0)**.

## Financeiro

- **Lucratividade:** receita (fretes) − custos variáveis (combustível, pedágio, comissão)
  − fixos rateados. Drill-down por rota/veículo/motorista/período.
- **Comissão:** % sobre frete de rotas concluídas; adiantamentos e descontos (multas/danos)
  no fechamento. Rota sem frete → comissão zerada (validar cadastro).
- **Contas a pagar / financiamentos / multas / despesas fixas:** cadastros com vencimentos
  que alimentam a Central de Alertas e o Calendário.

## Fiscal

- **NF-e, CT-e, MDF-e, CIOT/PEF, vale-pedágio** via libs `nfephp-org/*`. Certificado A1
  por empresa. CT-e pode ser **pré-preenchido a partir da OT** (vincula `ordem_transporte_id`).
- Regras completas nos manuais: [MANUAL_CTE.md](MANUAL_CTE.md), [MANUAL_MDFE.md](MANUAL_MDFE.md),
  [MANUAL_NFE.md](MANUAL_NFE.md), [MANUAL_CIOT_ANTT.md](MANUAL_CIOT_ANTT.md).

## <a name="gps"></a>GPS / telemetria

- App envia posições (`gps_salvar*.php`); validação por dezenas de `SF_GPS_*`
  (velocidade plausível, precisão mínima, bloqueio de mock, cercas, ocioso/pernoite).
- **Detecção automática** de parada/pernoite/encerrar rota (`evento_auto.php`).
- Alertas operacionais (perda de sinal, velocidade, bateria) com **dedup** por tempo.
- Cercas (geofences): entrada/saída/permanência com debounce.

## Manutenção preditiva

- Prevê a próxima revisão: **km desde a última revisão + km/dia médio (90 dias)**.
  Intervalo vem do **histórico do veículo**; sem histórico, usa intervalo padrão (indicado).
- "Atualizar KM da frota": `sf_atualizar_km_veiculos()` — UPDATE **não destrutivo** (GREATEST).

## IA / OCR

- Resumos em linguagem natural e insights a partir de **dados reais** — nunca inventar.
- OCR de visão (`sf_vision_ocr.php`) lê cupom/painel/pedágio/NF/lacre; provedor por empresa.

## Erros e dados

- Nunca expor `$e->getMessage()`. `catch` → `error_log()` + mensagem genérica;
  em API `success:false` + HTTP apropriado. **Nunca fabricar dados de exemplo** — estado
  vazio honesto (zeros/"nenhum registro").
