# Sistema de Frotas (Frotec)

Sistema web de gestão de frotas (multi-empresa) em **PHP + MySQL**, com app Android
(Kotlin/Compose) para motoristas. Rodando sobre XAMPP (Apache + MySQL na porta **3307**).

## Arquitetura

- **Entrada web:** `index.php` (dashboard), `login.php`, `logout.php`, `selecionar_empresa.php`.
- **`pages/`** — telas do gestor (veículos, rotas, abastecimentos, manutenções, BI, etc.).
- **`pages_motorista/`** — área do motorista. **`pages_adm/`** — administração interna.
- **`api/`** — endpoints JSON consumidos pelo front e pelo app Android.
- **`includes/`** — núcleo compartilhado (config, conexão, auth, csrf, helpers de domínio).
- **`js/`, `css/`** — assets do front (sem build step; versionados via `sf_asset_v()`).
- **`fiscal/`** — emissão fiscal (NFe/CTe/MDFe) via libs `nfephp-org/*` (Composer).
- **`app_android/`** — app do motorista (Kotlin) + sua própria API em `app_android/api/`.
- **`cron/`, `relatorios_automaticos/`** — jobs agendados (GPS, relatórios).

## Convenções importantes

- **Banco:** sempre via `getConnection()` (`includes/db_connect.php`) → PDO com
  `ERRMODE_EXCEPTION` e `EMULATE_PREPARES=false`. **Use sempre prepared statements
  com placeholders nomeados** — nunca concatene entrada do usuário no SQL.
- **Multi-empresa:** toda query de dados deve filtrar por `empresa_id`
  (`$_SESSION['empresa_id']`). É o limite de isolamento entre clientes — não esquecer
  em nenhum `SELECT/UPDATE/DELETE`.
- **Autenticação:** `require_authentication()` (`includes/config.php`) no topo de páginas
  e APIs. Sessão configurada por `configure_session()`. "Lembrar-me" usa token seguro
  selector:validator (`includes/remember_token.php`, tabela `auth_remember_tokens`) —
  **não** voltar ao cookie base64(id:email).
- **CSRF:** formulários usam `csrf_token_get()`/`csrf_token_validate()`; APIs que mudam
  estado chamam `api_require_csrf_json()`.
- **Erros:** nunca expor `$e->getMessage()` ao usuário. Registrar via `error_log()` e
  retornar mensagem genérica. Em APIs, devolver `success:false` + HTTP apropriado —
  **não fabricar dados de exemplo** em blocos `catch`.
- **Config/segredos:** credenciais de banco e chaves (OpenAI/DeepSeek) vêm de variáveis
  de ambiente (`SF_DB_*`, `SF_OPENAI_API_KEY`, etc.), com fallback local. Não commitar segredos.
- **Resposta JSON:** `json_response($data, $status)` ou `echo json_encode(...)` com
  `Content-Type: application/json`.

## Segurança (includes/security.php)

Biblioteca central `includes/security.php` reúne: política por empresa
(`configuracoes_seguranca`), 2FA TOTP (`sf_totp_*`), senha forte
(`sf_password_validate`), bloqueio de tentativas (`login_attempts`), geo-bloqueio,
whitelist de IP admin (`admin_ip_whitelist`), novo acesso (`acessos_conhecidos`) e
SMTP nativo (`sf_smtp_send`). Schema em `sql/security_features.sql`.

- **Política por empresa:** `sf_security_config($empresaId)` (cria a linha se faltar).
- **2FA por usuário:** colunas `two_factor_*` em `usuarios`; setup/confirm só do próprio
  usuário (página Usuários). Login exige código quando ativo e confirmado.
- **Logout por inatividade:** `sf_enforce_idle_timeout()` em `require_authentication()`;
  tempo cacheado em `$_SESSION['idle_timeout_min']`.
- **Segredos** (reCAPTCHA secret, SMTP pass): env primeiro (`SF_RECAPTCHA_*`,
  `SF_SMTP_PASS`) via `sf_security_secret()`, fallback ao banco. reCAPTCHA no login
  compartilhado usa env. Nunca devolver segredos por rede (mascarar com `********`).
- **UIs:** Configurações → card "Segurança" (`api/seguranca.php` + `js/seguranca.js`);
  Usuários → coluna/modal 2FA (`api/usuarios_2fa.php`).

## Módulo TMS — fluxo de frete (telas novas)

O sistema tem um fluxo comercial/operacional completo de transporte (TMS). A cadeia
principal, com a tela responsável por cada etapa:

**Cliente → Cotação/OT → Aprovação → Planejamento → Rota → GPS → Entrega/POD/Rastreio
→ CT-e → Financeiro → BI/IA/Preditiva**, com **Centro de Operações** e **Central de
Alertas** por cima como visão consolidada.

1. **Clientes** (`pages/clientes.php` + `api/clientes.php`, `sql/clientes.sql`) —
   cadastro de embarcadores/tomadores de frete (PJ/PF, contato, status
   ativo/prospect/inativo). A coluna "OTs" conta quantas ordens cada cliente teve.
2. **Ordens de Transporte / OT** (`pages/ordens_transporte.php` + `api/ordens_transporte.php`,
   `sql/ordens_transporte.sql`) — o pedido de frete. Status:
   `cotacao → aprovada → em_viagem → concluida` (ou `cancelada`). Usa o **Simulador de
   Rota** para custo/piso ANTT. Ações: `gerar_rota` (cria uma rota real em `rotas`,
   sem retrabalho), `atribuir` (grava só veiculo_id/motorista_id/data_coleta — UPDATE
   isolado para não sobrescrever campos), emitir CT-e pré-preenchido, e `token_rastreio`
   para o link público do cliente.
3. **Aprovações** (`pages/aprovacoes.php` + `api/aprovacoes.php`, `sql/aprovacoes.sql`) —
   fila das OTs em `cotacao`. Aprovar/rejeitar grava trilha de auditoria (autor, data,
   comentário). Rejeitar cancela a OT; aprovar libera para o Planejamento.
4. **Planejamento** (`pages/planejamento.php` + `api/planejamento.php`) — quadro das OTs
   `aprovada` sem rota. Atribui veículo/motorista (via ação `atribuir` da API de OT) e
   **gera a rota** direto daqui. KPIs de OTs a planejar, km, peso, frete e veículos livres.
5. **Rota** (`pages/routes.php`) — a rota gerada segue o fluxo de rotas existente.
6. **GPS / entrega** — posições do app do motorista (`api/get_posicoes.php`,
   `pages/mapa_frota.php`); rastreio público do cliente por token em `rastreio.php`
   (escopo só pela OT do token, sem dados comerciais).
7. **CT-e** — emitido a partir da OT já pré-preenchido (`fiscal/pages/partials/cte_wizard_modal.php`
   + `fiscal/pages/js/cte-wizard.js`), com `ordem_transporte_id` vinculado após a criação.
   O **ambiente SEFAZ é POR EMPRESA** (`fiscal_config_empresa.ambiente_sefaz` =
   `producao`/`homologacao`) — é a **fonte da verdade** usada por `CTeService`/emissão; a
   constante `FISCAL_AMBIENTE_SEFAZ` é só fallback legado (não confie nela). As APIs em
   `fiscal/api/` exigem **sessão da própria empresa** (isolamento — sempre iniciar a sessão
   antes de ler `$_SESSION['empresa_id']`) e usam **CORS de mesma origem**
   (`fiscal/api/_cors_same_origin.php`, não mais `Access-Control-Allow-Origin: *`).

### Telas de apoio / visão consolidada

- **Centro de Operações** (`pages/centro_operacoes.php` + `api/centro_operacoes.php`) —
  torre de controle **em tempo real** (mês corrente + situação agora, refresh 60s).
  Barra de status (Sistema/GPS/SEFAZ/CT-e/MDF-e), ações rápidas, resumo IA em linguagem
  natural, KPIs executivos, mapa da frota (Leaflet), situação operacional, financeiro do
  mês, gráficos 30d (Chart.js), rankings e pendências. **Só dado real** — onde não há
  registro mostra zero/estado vazio, nunca valor fictício. Difere do **Dashboard**
  (`index.php`), que é histórico com filtro de período.
- **Central de Alertas** (`pages/central_alertas.php` + `api/central_alertas.php`) —
  todos os alertas num lugar (CNH, documentos, multas, pneus, GPS), filtráveis por
  categoria/nível, clicáveis para a tela de origem.
- **Assistente IA / conversacional** (`pages/assistente_ia.php` + `api/ia_assistente.php`) —
  "ChatGPT da operação": pergunta em linguagem natural → resposta com **dado real**.
  **Segurança:** o LLM **nunca** roda SQL — a pergunta é classificada numa **intenção**
  pré-definida e o backend roda a **query parametrizada** filtrada por `empresa_id` (só
  leitura). Classificação em 2 camadas: **(1) palavra-chave/sinônimos** (rápido, sem IA);
  **(2) fallback por LLM** (`sf_vision_ai_resolve()` do provedor da empresa) que escolhe a
  intenção quando o texto não casa — entende qualquer frase. Intenções: faturamento,
  motorista/cliente/veículo/rota top, veículo parado, atrasos, CNH, consumo, km,
  **rotas ativas (em andamento)**, **rotas do mês**, no prazo, abastecimentos, pedágio,
  despesas, multas, frota, **manutenção** (gasto/abertas), **contas a pagar**, **pneus**,
  **implementos**. **Período livre** (`sf_ia_periodo()`, funciona com e sem IA): mês
  atual/passado, "primeiro trimestre de 2025", "últimos 3 meses/90 dias", "em 2024",
  mês por nome, "semana passada"; **sem período dito, usa os últimos 12 meses**. Quando há
  chave, a IA roda em **toda** pergunta, devolve `{intent, periodo}` em JSON e **prevalece**
  (o período livre que ela extrai passa pelo mesmo `sf_ia_periodo()`); sem chave, vale o
  matcher. A resposta indica quando foi "🧠 interpretado por IA".
  **Memória de contexto:** o front manda a última intenção (`contexto`); um follow-up só de
  período ("e no mês passado?", "e em 2024?") reaproveita a intenção anterior (`via=contexto`).
  **Gráficos inline:** intenções de série (faturamento, km, multas, manutenção) devolvem
  `chart{type,label,labels,values}` e a página renderiza um gráfico (Chart.js) por mês
  quando há ≥2 pontos. Helper `sf_ia_serie()`.
  **Aprender sempre:** perguntas não entendidas vão para `logs/ia_assistente_naoentendidas.log`
  **e** para a fila `ia_perguntas_pendentes` (`sf_ia_tools_queue_unanswered`). A aba
  **Aprendizado** (só admin — `can_manage_system_settings`, `api/ia_aprendizado.php`) lista
  as pendentes por frequência e permite **mapear cada uma para uma intenção** →
  `ia_intencoes_custom`, que o classificador já consulta (`sf_ia_tools_match_custom`).
  Fecha o loop: uma vez mapeada, perguntas parecidas passam a ser entendidas sem código.
  Linkado no menu (Operação) e nas ações rápidas do Centro de Operações ("Perguntar à IA").
- **Manutenção Preditiva** (`pages/manutencao_preditiva.php` + `api/manutencao_preditiva.php`) —
  prevê quando revisar cada veículo (km desde a última revisão + km/dia médio de 90 dias;
  intervalo do histórico do veículo, senão padrão). Botão "Atualizar KM da frota" chama
  `sf_atualizar_km_veiculos()` (`cron/atualizar_km_veiculos.php`, UPDATE não destrutivo
  com GREATEST).
- **Implementos** (`pages/implementos.php` + `api/implementos.php`, `sql/implementos.sql`) —
  carretas/reboques/dollys como ativos próprios (placa, documentos, vencimentos de
  licenciamento/seguro com alerta), separados do cavalo mecânico.
- **Simulador de Rota** (`pages/simulador_rota.php` + `js/routes-simulation.js`) — custo,
  margem-alvo e **frete de equilíbrio (lucro 0)**; usa o piso ANTT
  (`includes/sf_antt_frete.php` → `sf_antt_calc_piso()`, Lei 13.703/2018,
  tabela `antt_frete_coeficientes`). É a versão única do simulador (antes duplicado).
  Tem no topo um painel **"Simulação rápida (what-if)"** — sliders 100% client-side
  (frete, distância, diesel, consumo, pedágio, comissão, custo/km, fixos, ida-e-volta) que
  recalculam lucro/margem/lucro-km e **frete de equilíbrio** ao vivo, sem traçar a rota.

### Plataforma de logística inteligente (telas novas — ver `docs/A9-novas-funcionalidades.md`)

Lote de telas de analytics/operação construídas sobre dado real (mesmo padrão `fornc-*`,
isolamento por empresa, CSRF nas gravações, ajuda por página). **Fonte da verdade e
auditoria em `docs/A9`**; resumo:
- **Estratégico:** Painel Executivo (`pages/painel_executivo.php`), IA de Decisão
  (`ia_decisao.php` — consultor por regras), Scores 0–100 (`scores.php`), Previsão Financeira
  (`previsao_financeira.php` — regressão), Simulação de Crescimento/ROI (`simulacao_crescimento.php`),
  Otimização (`otimizacao.php` — melhor veículo/motorista).
- **Operação:** Digital Twin (`digital_twin.php` — frota ao vivo), Replay de Viagem
  (`replay_viagem.php` — trajeto `gps_logs`), Gestão de Pátio (`patio.php`), Agenda Inteligente
  (`agenda_inteligente.php` — reusa `central_alertas`), SLA Operacional (`sla_operacional.php`),
  Planejamento de Carga (`planejamento_carga.php`), Metas (`metas.php`), Workflow configurável
  (`workflow.php`).
- **Frota/apoio:** Gestão de Diesel/Postos (`gestao_diesel.php`), Timeline do Veículo
  (`veiculo_timeline.php`), QR Codes (`qr_codes.php`), Contratos (`contratos.php`).
- **Migrações novas** (aplicar em produção): `sql/metas.sql`, `sql/contratos.sql`,
  `sql/patio_vagas.sql`, `sql/workflow.sql`.
- **Convenção mantida:** APIs de gravação (`metas`/`contratos`/`patio`/`workflow`) usam
  `api_require_csrf_json()` + `InvalidArgumentException` (mensagem de validação) vs `Throwable`
  (log + genérica). Leitura filtra sempre por `empresa_id`.

### Relatórios

- **Relatórios** (`pages/relatorios.php` + `includes/relatorios_helpers.php`) —
  preview em modal, exportação CSV/Excel/PDF (com branding), gráficos, favoritos/recentes
  (localStorage), filtros por veículo/motorista, período flexível
  (`relatorio_apply_daterange()`), comparação de períodos e novos tipos de relatório. O
  menu lateral tem submenu por categoria (abre o relatório escolhido; "todos" lista tudo).
  Allow-list e validação de datas em `relatorios_helpers.php`.
- **Relatórios automáticos/agendados** (`api/auto_reports.php` gera PDF/Excel/CSV;
  `api/relatorios_agendar.php` + `sql/relatorios_agendados.sql`; envio por
  `cron/enviar_relatorios_agendados.php`). Não rotular período como "toda segunda/dia 1º"
  se não houver cron rodando — usar rótulo honesto ("semana/mês atual").

## App do motorista (Android)

App Kotlin/Jetpack Compose em `app_android/` (pacote `com.sistemafrotas.motorista`,
Retrofit + Room, min Android 8), com **API própria** em `app_android/api/`. É o portal
do motorista, **offline-first** (ações sem internet vão para uma fila/outbox e
sincronizam sozinhas) e com **OCR por IA de visão** que reusa o provedor configurado por
empresa (Gemini/OpenAI/OpenRouter/DeepSeek) via `includes/sf_vision_ocr.php`.

**O que faz:** painel (rotas do dia, indicadores, último GPS) e avisos (nova rota/checklist/
CNH, por polling); rotas (CRUD + detalhe); abastecimentos com **OCR do cupom/painel**;
checklists inteligentes por tipo de veículo (com assinatura); despesas e **pedágios** (OCR
do recibo); inspeção de pneus por **QR**; ocorrências (vídeo + assinatura); **jornada** (Lei
do Motorista); **comprovante de entrega (POD)** com assinatura; **pânico**; diário de bordo;
**conferência de carga** (OCR de NF + barcode/QR do lacre); consumo (km/L + antifraude);
controle de velocidade; avaliação/ranking; **GPS em segundo plano** com detecção automática
de parada/pernoite/encerrar; e **Modo Viagem** (copiloto: inicia num toque, timeline,
assistente IA, prompts GPS, PDF de relatório). Login com **2FA TOTP**.

**Fluxo app ↔ web:** o motorista executa a rota no celular; o app envia GPS
(`gps_salvar.php`/`gps_salvar_lote.php`/`gps_trajeto.php`), POD, checklists, abastecimentos,
ocorrências, jornada e despesas; o **gestor vê no web** (mapa da frota, rotas, comissões,
BI, Centro de Operações) e o **cliente** acompanha pelo rastreio público da OT. O
`evento_auto.php` sugere encerrar a rota ao detectar pernoite. Endpoints do app em
`app_android/api/*.php` (auth, dashboard, rotas, gps_*, checklists, comprovantes_entrega,
conferencia_carga, ocorrencias, jornada, panico, pedagios, despesas, ocr_documento, etc.).

**Docs detalhados (fonte da verdade do app):** `app_android/DOCUMENTACAO_APP.md`
(funcional + técnico, com tabela de recursos por versão), `app_android/docs/ENDPOINTS.md`
(contrato dos endpoints) e `app_android/README.md`. Ao mexer no app, **atualizar esses
arquivos** — aqui no CLAUDE.md fica só o resumo e o ponteiro.

## Padrões de UI compartilhados

- **Página moderna:** `body.sf-premium` + `css/fornc-modern-page.css` (design system
  `.fornc-*`, 100% escopado) + `css/sf-premium.css` (regras sob `body.sf-premium`).
- **Toolbar padrão** (igual `pages/routes.php`): `.fornc-toolbar` → `.fornc-search-block`
  ("Busca rápida" com ícone) + `.fornc-filters-inline` (selects rotulados em `.fg`,
  incluindo "Por página") + `.fornc-btn-row` (`.fornc-btn--primary/accent/ghost/icon`).
- **Tabela:** `.fornc-table` dentro de `.fornc-table-wrap`; última coluna `<th>Ações</th>`
  com `<td class="actions">` e botões `.btn-icon` (`edit-btn`/`delete-btn`/`view-btn` —
  cores por tipo no hover, definidas em `fornc-modern-page.css`).
- **Estado vazio:** `.fornc-empty-state` (título + dica contextual + botão de ação).
- **Paginação:** `.pagination` (styles.css) com `‹ | Página X de Y (N registros) | ›`;
  o seletor de itens por página fica **na toolbar do topo**, não na barra de baixo.
- **Ajuda por página:** `includes/sf_page_help.php` + arquivos em `includes/page_help/*.php`
  (chave por página). Na página: `sf_page_help_css('..')` no head, botão `#helpBtn`,
  `$sf_help_key='<chave>'; include '../includes/sf_page_help_bundle.php';` e
  `sf_page_help_js('..')` antes de `</body>`. O `js/sf_page_help.js` acha o botão e o
  modal (`[data-sf-help-key]`) e liga abrir/fechar sozinho. Chaves das telas novas em
  `includes/page_help/09_tms_novos.php`.
  Cada entrada renderiza, nesta ordem: Visão Geral, Funcionalidades, **Passo a Passo
  (Como Fazer)**, **Fluxo do Processo** (de onde vem o dado → o que fazer aqui → para onde
  vai / próxima tela), KPIs, Gráficos, Dicas e Legenda — só as chaves preenchidas aparecem
  (helpers `sf_hlp_ul`/`sf_hlp_ol`). **Toda tela relevante deve ter o `#helpBtn`** (sem ele
  o conteúdo existe mas fica inacessível).

## Comportamento de acesso

- **Login sempre cai em `index.php`** (Dashboard) — o redirect não usa mais a "última
  página tentada". Vale para senha, lembrar-me e seleção de empresa.
- **Submenus do menu lateral recolhidos ao acessar** — a barra abre normal (expandida),
  mas os grupos (dropdowns) começam **fechados**. No login grava `$_SESSION['sf_sidebar_reset']`
  e `includes/sidebar_pages.php` limpa `localStorage.sidebarDropdowns` na primeira página,
  então nenhum grupo fica aberto na tela inicial. Ao navegar para uma página interna, o
  grupo daquela página abre sozinho (mostra onde você está).

## Banco de dados

- Migrações/seeds em **`sql/`** (arquivos `.sql` aplicados manualmente).
- Aplicar uma migração:
  `mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/arquivo.sql`
- Migrações do módulo TMS: `sql/ordens_transporte.sql`, `sql/clientes.sql`,
  `sql/implementos.sql`, `sql/aprovacoes.sql`, `sql/relatorios_agendados.sql`.

## Validação

- Lint PHP: `php -l caminho/arquivo.php` (`C:\xampp\php\php.exe`).
- Não há suíte de testes automatizada; validar mudanças manualmente no fluxo afetado.

## Não versionar (ver `.gitignore`)

`vendor/` (rodar `composer install`), `logs/`, `cache/`, `tmp/`, `sessions/`, `uploads/`.
