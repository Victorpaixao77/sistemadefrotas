# App do Motorista — Sistema de Frotas (Frotec)

Documentação funcional e técnica completa do aplicativo Android para motoristas.

> **Pacote:** `com.sistemafrotas.motorista` · **Versão:** 1.1 (versionCode 2)
> **Plataforma:** Android 8.0+ (minSdk 26) · alvo Android 14 (targetSdk 34)

---

## 1. Visão geral

O app é o **portal do motorista** do Sistema de Frotas. Ele permite que o motorista, do próprio celular:

- veja seu **painel** (rotas do dia, indicadores do mês, último GPS);
- gerencie suas **rotas** (listar, criar, editar, excluir, ver detalhes);
- registre **abastecimentos** (com foto do comprovante e GPS);
- preencha **checklists** do veículo;
- lance **despesas de viagem** por rota;
- faça **inspeção de pneus** lendo o QR Code do pneu;
- envie a **localização GPS em segundo plano** para o gestor acompanhar a frota.

Tudo funciona **offline-first**: ações feitas sem internet ficam numa fila e sincronizam sozinhas quando a conexão volta.

---

## 2. Stack e tecnologias

| Camada | Tecnologia |
|--------|-----------|
| UI | **Jetpack Compose** + Material 3 |
| Navegação | Navigation Compose + abas (NavigationBar) |
| Estado | **ViewModel + StateFlow** (telas de lista) |
| Rede | **Retrofit 2** + Gson + OkHttp (logging condicional) |
| Persistência local | **Room** (cache + fila offline) e **DataStore** (token/config) |
| Imagens | **Coil** |
| Câmera / QR | **CameraX** + **ML Kit Barcode Scanning** |
| Localização | **Google Play Services Location** (FusedLocationProvider) |
| Tarefas em background | **WorkManager** (sincronização) + **Foreground Service** (GPS) |

---

## 3. Arquitetura

```
ui/
  screens/        → telas Compose (Login, Dashboard, Rotas, Abastecimentos, ...)
  viewmodel/      → ViewModels + StateFlow (estado que sobrevive à rotação)
  components/     → componentes reutilizáveis (DatePicker, máscaras, QR, skeletons)
  theme/          → tema Material 3
data/
  api/            → Retrofit (ApiService), modelos (ApiModels), tratamento de erro
  local/          → Room: entidades, DAOs, AppDatabase, LocalCache
  AuthRepository  → ponto único de acesso a dados (rede + cache + fila)
  AuthDataStore   → token de sessão e URL da API (DataStore)
  Gps*            → utilidades de GPS (fila, notificação, preferências, diagnóstico)
  Outbox*         → fila de sincronização offline (processador, worker, scheduler)
GpsForegroundService → serviço de rastreamento em segundo plano
MainActivity / SistemaFrotasApp → entrada do app
```

O **`AuthRepository`** é a fonte única de verdade: toda tela conversa com ele, nunca direto com a rede. Ele decide quando usar cache, quando enfileirar para sincronizar depois e como tratar erros.

---

## 4. Telas e funcionalidades

A navegação principal é uma **barra inferior com 6 abas operacionais** (Início, Rotas, Abastecimentos, Checklists, Despesas, Pneus) + uma aba **"Mais"** (hub de recursos secundários: Ocorrências, Jornada…), mais **Configurações** e **Sair** no menu (⋮) do topo.

### 4.1. Login (`LoginScreen`)
- Entrada por **nome de usuário + senha**.
- Autentica em `auth.php`, recebe **token** + **refresh token** e guarda no DataStore.
- Trata erros de rede e credenciais com mensagem amigável.

### 4.2. Início / Dashboard (`DashboardScreen`)
Painel de resumo do motorista, com:
- **Saudação** com o nome do motorista.
- **Contadores**: rotas pendentes, abastecimentos pendentes, checklists pendentes.
- **Resumo do mês**: total de frete, comissão, despesas e **lucro do mês**.
- **Rotas de hoje** e **últimas rotas / abastecimentos / checklists**.
- **Resumo de GPS**: último registro de localização enviado.
- Puxar para atualizar (pull-to-refresh); detecta **sessão expirada** e redireciona ao login.

### 4.3. Rotas (`RotasScreen` + `RotaDetalheScreen`)
- **Lista** de rotas com filtros por **status** (Todas / Pendente / Aprovado / Rejeitado) e por **intervalo de datas**.
- **Criar** nova rota (veículo, origem/destino por estado+cidade, datas, KM saída/chegada, frete, etc.), com validações (ex.: KM chegada ≥ KM saída; data/hora de chegada ≥ saída).
- **Editar** e **excluir** rotas.
- **Detalhe da rota**: resumo financeiro (frete, comissão, total de despesas, **lucro da rota**) e listas de **despesas** e **abastecimentos** vinculados.
- Funciona offline: rota criada/editada/excluída sem internet entra na **fila de sincronização**.

### 4.4. Abastecimentos (`AbastecimentosScreen`)
- **Lista** dos abastecimentos do motorista.
- **Novo abastecimento**: veículo, rota do dia, posto, litros, valor total, **valor/litro** (calculado se não informado), KM atual, tipo de combustível, forma de pagamento, observações.
- **Foto do comprovante** (galeria ou câmera) enviada via multipart.
- **Captura de GPS** no momento do registro (latitude/longitude).
- Validações de número (bloqueia litros = 0, evita divisão por zero).
- Offline: salva localmente e sincroniza depois (inclusive a foto).

### 4.5. Checklists (`ChecklistsScreen`)
- **Lista** de checklists.
- **Novo checklist** do veículo: seleção de veículo/rota e itens de verificação.
- Offline-first (fila de sincronização).

### 4.6. Despesas de viagem (`DespesasScreen`)
- Seleciona uma **rota** e lança as **despesas da viagem**: descarga, pedágios, caixinha, estacionamento, lavagem, borracharia, elétrica/mecânica, adiantamento, outros.
- Salva o conjunto de despesas vinculado à rota.
- Offline-first.

### 4.7. Pneus (`PneusScreen`)
- **Leitura de QR Code** do pneu pela câmera (ML Kit) — ou digitação manual da referência.
- Busca os dados do pneu e mostra a ficha.
- **Registro de inspeção**: pressão (PSI), sulco (mm), tem corte, tem bolha, observações, veículo.
- **Foto da inspeção** (multipart).

### 4.8. Configurações (`ConfigScreen`)
- **URL da API** (endpoint do servidor) — permite apontar para produção/teste.
- **Veículo padrão** do motorista.
- **Ativar/desativar o rastreamento GPS** (liga/desliga o serviço em segundo plano).
- **Diagnóstico de GPS** (status de permissões, provedores, conectividade) para suporte.

---

## 5. Rastreamento GPS (`GpsForegroundService`)

Serviço **foreground** (com notificação persistente, exigido pelo Android) que envia a posição do veículo para o gestor.

- **Tipo de serviço:** `location` (compatível com as regras estritas do Android 14+).
- **Economia de bateria/dados:** só envia quando o veículo **deslocou ≥ 30 m** ou **passaram ≥ 30 s**; parado, espaça os envios (~2 min). Velocidade baixa aumenta o intervalo mínimo.
- **Dados enviados:** latitude, longitude, velocidade (km/h), precisão, provider, bateria do aparelho, flag de localização fictícia (mock).
- **Proteção de bateria:** ao chegar em **≤ 15%**, reduz/ajusta o envio.
- **Fila offline de GPS:** se o servidor responde **5xx/429** ou não há rede, os pontos vão para a tabela `gps_pending` e são reenviados depois (worker dedicado), inclusive **em lote** (`gps_salvar_lote.php`).
- Reage a mudanças de configuração de localização do sistema (`GpsLocationSettingsReceiver`).

---

## 6. Funcionamento offline (offline-first)

O app foi feito para o motorista, que muitas vezes está em área sem sinal.

### 6.1. Cache de leitura (Room)
Respostas de **rotas**, **abastecimentos** e **checklists** são salvas em tabelas de cache (`rotas_cache`, `abastecimentos_cache`, `checklists_cache`). Se a rede falhar, a tela mostra o que está em cache (mesclado com pendências locais).

### 6.2. Fila de escrita / Outbox (`pending_sync`)
Ações que mudam dados sem internet são **enfileiradas** e processadas depois pelo `OutboxSyncProcessor` (disparado por **WorkManager** quando a conectividade volta). Operações suportadas:

| Operação | Descrição |
|----------|-----------|
| `CREATE_ROTA` / `UPDATE_ROTA` / `DELETE_ROTA` | Criar, editar, excluir rota |
| `CREATE_ABASTECIMENTO_JSON` | Abastecimento sem foto |
| `CREATE_ABASTECIMENTO_MULTIPART` | Abastecimento com foto do comprovante |
| `CREATE_CHECKLIST` | Checklist do veículo |
| `SAVE_DESPESAS` | Despesas de viagem da rota |

IDs temporários (offline) são **reconciliados** com os IDs reais do servidor após a sincronização (ex.: o `rota_id` de um abastecimento criado offline é trocado pelo id real da rota).

---

## 7. Autenticação e sessão

- Login retorna **token** + **refresh token**, guardados com **DataStore**.
- O token é injetado automaticamente nas chamadas (interceptor OkHttp).
- **Renovação automática:** ao receber 401, um `Authenticator` usa o refresh token para obter um novo token sem o usuário perceber (cliente separado para evitar deadlock).
- **Sessão expirada** (refresh inválido) → limpa o token e volta ao login.

---

## 8. Permissões usadas

| Permissão | Para quê |
|-----------|----------|
| `INTERNET` | Comunicação com a API |
| `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION` | Posição GPS |
| `ACCESS_BACKGROUND_LOCATION` | Rastreamento com o app em segundo plano |
| `FOREGROUND_SERVICE` / `FOREGROUND_SERVICE_LOCATION` | Serviço de GPS persistente |
| `POST_NOTIFICATIONS` | Notificação do serviço de GPS (Android 13+) |
| `CAMERA` | Leitura do QR do pneu e foto de comprovante/inspeção |

---

## 9. API consumida (`app_android/api/`)

Base configurável em Configurações (padrão de desenvolvimento: `http://10.0.2.2/sistema-frotas/app_android/api/`).

| Endpoint | Método | Função |
|----------|--------|--------|
| `auth.php` | POST/GET | Login, dados do usuário (`me`), logout, refresh token |
| `dashboard.php` | GET | Dados do painel |
| `rotas.php` | GET/POST/PUT/DELETE | Listar, detalhe, criar, atualizar, excluir rotas |
| `abastecimentos.php` | GET/POST/Multipart | Listar e criar abastecimentos (com/sem foto) |
| `checklists.php` | GET/POST | Listar e criar checklists |
| `despesas.php` | GET/POST | Despesas por rota |
| `veiculos.php` | GET | Veículos do motorista |
| `estados.php` / `cidades.php` | GET | Combos de origem/destino |
| `pneus.php` | GET/POST/Multipart | Buscar pneu por QR, listar veículos, registrar inspeção, foto |
| `gps_salvar.php` | POST | Enviar 1 ponto GPS |
| `gps_salvar_lote.php` | POST | Enviar pontos GPS em lote (fila offline) |

> Todas as respostas seguem o envelope padrão `{ success, message, data }`.

---

## 10. Banco local (Room)

| Tabela | Uso |
|--------|-----|
| `rotas_cache` | Cache de rotas (leitura offline) |
| `abastecimentos_cache` | Cache de abastecimentos |
| `checklists_cache` | Cache de checklists |
| `pending_sync` | Fila de operações a sincronizar (outbox) |
| `gps_pending` | Pontos GPS aguardando reenvio |

---

## 11. Build e requisitos

- **Android Studio** (Gradle KTS).
- `compileSdk 34`, `minSdk 26`, `targetSdk 34`.
- **Release com R8 ligado** (`isMinifyEnabled = true` + `shrinkResources`) e regras `proguard-rules.pro` que preservam os modelos Gson/Retrofit, Room e ML Kit — APK menor.
- **Logging de rede** só quando `BuildConfig.LOG_API_BODY` está ativo (debug), nunca em release.
- Compilar:
  ```bash
  ./gradlew assembleDebug      # APK de desenvolvimento
  ./gradlew assembleRelease    # APK otimizado (R8)
  ```

---

## 12. Resumo do fluxo do motorista

1. **Entra** com usuário e senha.
2. Vê o **painel** com rotas do dia e indicadores.
3. Liga o **GPS** (Configurações) — o gestor passa a ver o veículo no mapa da frota.
4. Durante a viagem: cria/atualiza **rotas**, registra **abastecimentos** (com foto), preenche **checklists**, lança **despesas** e faz **inspeção de pneus** por QR.
5. Sem internet, tudo é **salvo localmente** e **sincroniza sozinho** quando a conexão volta.

---

## 13. Evolução do produto (roadmap)

Plano de evolução do app de "ferramenta operacional" para **plataforma profissional de gestão de motoristas e viagens** (foco em transportadoras).

**Legendas de esforço:** 🟢 baixo · 🟡 médio · 🔴 alto.
**"Base":** ✅ = já existe infraestrutura no sistema que acelera muito a implementação.

### 13.1. 1ª onda recomendada (prioridade de venda)

Ordem sugerida para transformar o app num produto comercial forte. Sobe o que tem alto valor **e** já tem base pronta.

| # | Recurso | Valor | Esforço | Base existente |
|---|---------|-------|---------|----------------|
| 1 | **Push Notifications (FCM)** — nova rota, rota alterada, checklist pendente, documento/pneu vencendo | Alto | 🟡 | — (destrava os demais) |
| 2 | **Ocorrências de Viagem** — acidente, multa, roubo, problema mecânico/carga, bloqueio (foto/vídeo/GPS/data-hora) | Alto | 🟢 | ✅ GPS + upload de mídia |
| 3 | **POD — Comprovante de Entrega** — foto da NF, foto da carga, assinatura do cliente, GPS da entrega | Alto | 🟡 | ✅ GPS + multipart |
| 4 | **Assinatura Digital** — assinar checklist/entrega/coleta/ocorrência na tela | Alto | 🟢 | (acompanha o POD) |
| 5 | **Geofencing no app** — entrou/saiu do cliente, saiu da rota, área de risco | Alto | 🟢 | ✅ cercas + alertas já existem no web |
| 6 | **Botão de Pânico** — envia localização + alerta + aciona central | Alto | 🟢 | ✅ extensão do serviço de GPS |
| 7 | **Jornada do Motorista** — início/parada/descanso/refeição/fim + relatórios (Lei do Motorista) | Alto | 🟡 | — (diferencial de venda) |
| 8 | **OCR no abastecimento** — extrai valor/litros/data/nota da foto do comprovante | Médio | 🟢 | ✅ OCR já existe no servidor (`refuel_ocr_openai.php`) |
| 9 | **Diário de Bordo / Mapa da Viagem** — trajeto, paradas, abastecimentos, velocidade média | Médio | 🟡 | ✅ histórico GPS já gravado |
| 10 | **Chat com a Central** — texto (começar simples; áudio/imagem depois) | Médio | 🔴 | — |
| 11 | **IA Assistente do Motorista** — "qual meu último abastecimento?", "quanto gastei na viagem?" | Diferencial | 🟡 | ✅ IA já no sistema principal |

### 13.2. Prioridade Alta (núcleo do produto)
- **Ocorrências de Viagem** (#2)
- **Jornada do Motorista** (#7)
- **Assinatura Digital** (#4)
- **POD — Comprovante de Entrega** (#3)
- **Chat com a Central** (#10)
- **Notificações Push** (#1)

### 13.3. Prioridade Média
- **Scanner de Documentos** — fotografar CNH/CRLV/NF e salvar como PDF (recorte automático). 🟡
- **Leitura OCR** do comprovante (#8) — 🟢 ✅ já tem base.
- **Controle de Velocidade** — excesso/velocidade média + pontuação. 🟡 *(frenagem/curva brusca confiável só com telemetria do veículo; pelo celular é impreciso)*.
- **Diário de Bordo** (#9). 🟡 ✅
- **Controle de Pedágios** — pedágio pago, valor, foto do comprovante. 🟢 ✅ *(integração Sem Parar fica na fase de terceiros)*.
- **Checklist Inteligente** — itens obrigatórios por tipo de veículo (caminhão: freio/pneus/óleo; carreta: quinta-roda/mangueiras/travas). 🟡 ✅

### 13.4. Diferenciais Premium
- **IA Assistente do Motorista** (#11). 🟡 ✅
- **Avaliação/Ranking do Motorista** — pontuação por consumo/velocidade/multas/ocorrências/despesas. 🟡 *(o web já tem ranking/score; reaproveitar)*.
- **Mapa da Viagem** estilo rastreador profissional. 🟡 ✅
- **Botão de Pânico** (#6). 🟢 ✅
- **Geofencing** (#5). 🟢 ✅
- **Controle de Documentos do Motorista** — CNH/ASO/treinamentos/certificados com alerta de vencimento. 🟢 *(CNH já existe no cadastro web)*.

### 13.5. Fase posterior — Integrações fiscais e de terceiros

> **Decisão de produto:** deixar para uma fase posterior. Dependem de integração com SEFAZ/ERP/operação e contratos com terceiros — alto custo e baixo valor *imediato* para o motorista.

- **Integração CT-e / MDF-e** — motorista visualiza CT-es da viagem, MDF-e e NFs transportadas.
- **CIOT** — número, status, pagamento.
- **Vale-Pedágio** — saldo, utilização, trechos.
- **Sem Parar** (integração de pedágio automático).
- **Controle de Carga** — peso, cubagem, volumes, lacres.
- **Conferência de Carga** — leitura de código de barras / QR na coleta e entrega.

### 13.6. Observações de viabilidade
- **Itens "quase de graça"** (já há infraestrutura): Ocorrências, Geofencing, Botão de Pânico, Diário de Bordo, Mapa da Viagem, OCR de abastecimento, IA do Motorista.
- **Cuidado de escopo:** o **Chat completo** (texto/áudio/imagem em tempo real) é praticamente um mini-produto (mensageria + websockets + storage de mídia) — começar por mensagens simples + push.
- **Limites de hardware:** velocidade/frenagem/curva confiáveis exigem telemetria do veículo; pelo GPS do celular, ficar em velocidade média e excesso grosseiro.

---

## 14. Catálogo completo de recursos (backlog detalhado)

Especificação item a item de **todos** os recursos previstos, para servir de backlog de produto.
**Prioridade:** 🟢 Alta · 🟡 Média · 🔵 Premium · 🟣 Transportadora (fase posterior).
**Esforço:** 🟢 baixo · 🟡 médio · 🔴 alto. · **Base ✅** = infra já existente acelera.

### 🟢 Prioridade Alta

**1. Ocorrências de Viagem** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟢 · Base ✅
> Servidor: `sql/ocorrencias_viagem.sql`, `app_android/api/ocorrencias.php`. App: `OcorrenciasScreen` + `OcorrenciasViewModel` + outbox offline (foto/GPS).
O motorista registra problemas durante a viagem:
- Acidente · Multa · Roubo · Tentativa de roubo · Problema mecânico · Problema com carga · Bloqueio de estrada.
Cada ocorrência com: **Foto · Vídeo · GPS · Data/Hora**.

**2. Controle de Jornada do Motorista** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟡 (Lei do Motorista)
> Servidor: `sql/jornada_motorista.sql`, `app_android/api/jornada.php` (com resumo de horas do dia). App: `JornadaScreen` + `JornadaViewModel` + outbox offline.
Registrar: Início de jornada · Parada · Descanso · Refeição · Fim da jornada.
Relatórios: Horas dirigidas · Horas paradas · Descanso obrigatório.

**3. Assinatura Digital** — Esforço 🟢
Ao finalizar **Checklist / Entrega / Coleta / Ocorrência**, o motorista **assina na tela** (captura da assinatura).

**4. Comprovante de Entrega (POD)** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟡 · Base ✅ (GPS + multipart)
> Servidor: `sql/comprovantes_entrega.sql`, `app_android/api/comprovantes_entrega.php` (multipart 3 arquivos). App: `PodScreen` com **pad de assinatura na tela** (Canvas→PNG) + foto NF + foto carga + GPS + outbox offline (`CREATE_POD`).
Muito usado em transportadoras. Permite: **Foto da NF · Foto da carga entregue · Assinatura do cliente · GPS da entrega**.

**5. Chat com a Central** — Esforço 🔴
Comunicação entre Gestor · Operador · Motorista (estilo WhatsApp): **Texto · Áudio · Imagem · Localização**.
> *Recomendação: começar por texto + push; áudio/imagem em etapa seguinte.*

**6. Notificações Push (FCM)** — Esforço 🟡
Enviar ao motorista: Nova rota · Rota alterada · Checklist pendente · Documento vencendo · Pneu com problema.
Tecnologia: **Firebase Cloud Messaging**.

### 🟡 Prioridade Média

**7. Scanner de Documentos** — Esforço 🟡
Motorista fotografa CNH · CRLV · Nota Fiscal · MDF-e · CT-e. O sistema **recorta automaticamente e salva como PDF**.

**8. Leitura OCR (IA de visão)** — ✅ **IMPLEMENTADO** · Esforço 🟢 · Base ✅
Botão **"Preencher por foto (IA)"** nos formulários:
- **Abastecimento:** foto do cupom do posto → preenche posto, data/hora, litros, valor/litro, valor total, combustível e usa a própria foto como comprovante. Motorista só confere e informa o **KM** (não aparece no cupom).
- **Painel/KM:** foto do hodômetro → preenche a quilometragem automaticamente.
- **Pedágio:** foto do recibo → preenche valor e praça.
- **Nota fiscal:** foto da NF → preenche número, valor e volumes (usado na Conferência de Carga).
- **Lacre:** foto do lacre → lê o código.

Servidor: `app_android/api/ocr_documento.php` (tipo = abastecimento|painel|pedagio|nota|lacre) sobre o helper reutilizável `includes/sf_vision_ocr.php`, que resolve o provedor de visão por empresa (`empresa_vision_ai` → OpenAI → DeepSeek), pré-processa a imagem e devolve só os campos. App: componente `OcrLeitorButton` (câmera/galeria + estado de leitura) integrado em `AbastecimentosScreen`, `PedagiosScreen` e `ConferenciaCargaScreen`. O motorista **sempre revisa** antes de salvar (preenchimento assistido, não automático cego).

**8b. Abastecimento Inteligente (consumo + antifraude)** — ✅ **IMPLEMENTADO** (aba Mais → "Meu consumo")
Análise sobre os abastecimentos já lançados (sem configuração extra): **consumo médio km/l**, último consumo, tendência (% vs média) com insight textual, e **alertas antifraude** — KM menor que o anterior, volume muito acima da média do veículo, preço/litro fora da faixa, consumo impossível. Servidor: `app_android/api/abastecimento_insights.php`. App: `ConsumoScreen` + `AbastecimentoInsightsViewModel`.

**9. Controle de Velocidade** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟡
Pontuação de direção 0-100 a partir dos pings de GPS já coletados (`gps_logs.velocidade`): velocidade média/máxima, nº e % de excessos acima do limite (padrão 90 km/h), e lista dos maiores excessos. Servidor: `app_android/api/velocidade.php`. App: `VelocidadeScreen` + `VelocidadeViewModel`.
> *Frenagem/curva brusca confiáveis só com telemetria do veículo; por isso o foco é velocidade média e excesso, calculados no servidor sem custo de bateria no app.*

**10. Diário de Bordo** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟡 · Base ✅
> Servidor: `app_android/api/diario.php` (UNION de jornada/abastecimentos/ocorrências/POD/pânico/checklists/pedágios, ordenado por data). App: `DiarioScreen` (linha do tempo com ícone/cor por tipo) + `DiarioViewModel`.
Registro automático: Localizações · Eventos · Abastecimentos · Paradas → **histórico completo** da viagem. *(Mapa visual da viagem fica para quando integrar um SDK de mapa no app.)*

**11. Controle de Pedágios** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟢
Motorista registra cada praça: Valor · Praça/local · Foto do comprovante · GPS. Mostra o total dos últimos 30 dias e entra na linha do tempo do Diário. Offline-first (outbox com foto). Servidor: `app_android/api/pedagios.php` (GET lista+total, POST JSON/multipart); tabela `pedagios_viagem` (`sql/pedagios_viagem.sql`). App: `PedagiosScreen` + `PedagiosViewModel`. *(Integração Sem Parar fica na fase de terceiros.)*

**12. Checklist Inteligente** — Esforço 🟡 — ✅ IMPLEMENTADO
Itens agrupados (Veículo · Segurança · Documentação · Carga e carreta · Motorista) com seletor de "Configuração do veículo" (conjunto/caminhão/ônibus/outro). O grupo "Carga e carreta" — com os itens novos Quinta-roda · Mangueiras · Travas · Lona da carga — só aparece quando a configuração tem carreta. Servidor: `app_android/api/checklists.php` (INSERT dinâmico a partir de `$bools`); migração `sql/checklist_itens_carreta.sql` adiciona as colunas `quinta_roda`/`mangueiras`/`travas`/`lona_carga` a `checklist_viagem`.

### 🔵 Diferenciais Premium

**13. IA Assistente do Motorista** — Esforço 🟡 · Base ✅ (IA do sistema)
Chat dentro do app. Ex.: "Qual foi meu último abastecimento?" · "Quanto gastei nessa viagem?" · "Meu pneu precisa de recapagem?".

**14. Avaliação do Motorista** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟡 · Base ✅ (score/ranking no web)
Pontuação 0-100 por penalidades (multas, pontos na CNH, ocorrências, % de excesso de velocidade) + **posição no ranking** da empresa, indicadores (consumo km/l, km rodados, abastecimentos). Servidor: `app_android/api/desempenho.php` (calcula score do motorista e de todos da empresa para ordenar). App: `DesempenhoScreen` + `DesempenhoViewModel`.

**15. Mapa da Viagem** — Esforço 🟡 · Base ✅ (GPS)
Mostra: Trajeto realizado · Paradas · Abastecimentos · Velocidade média (estilo rastreador profissional).

**16. Botão de Pânico** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟢 · Base ✅ (serviço GPS)
> Servidor: `sql/panico_alertas.sql`, `app_android/api/panico.php`. App: `PanicoScreen` (botão grande + confirmação) + outbox offline (`CREATE_PANICO`).
Ao pressionar: envia localização · envia alerta · aciona central.

**17. Geofencing** — Esforço 🟢 · Base ✅ (cercas/alertas no web)
Alertas: entrou no cliente · saiu do cliente · entrou em área de risco · saiu da rota.

**18. Controle de Documentos** — Esforço 🟢 · Base ✅ (CNH já no cadastro)
Motorista visualiza CNH · ASO · Treinamentos · Certificados, com **alerta de vencimento**.

### 🟣 Recursos para Transportadoras — *fase posterior (integrações fiscais/terceiros)*

**19. Integração CT-e / MDF-e** — Esforço 🔴
Motorista visualiza CT-es da viagem · MDF-e · NFs transportadas — sem ligar para a operação.

**20. CIOT** — Esforço 🟡
Visualização: número CIOT · status · pagamento.

**21. Vale-Pedágio** — Esforço 🟡
Mostrar: saldo · utilização · trechos.

**22. Controle de Carga** — Esforço 🟡
Peso · Cubagem · Quantidade de volumes · Lacres.

**23. Conferência de Carga** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟡
Conferência na **coleta/entrega** com **OCR de NF** (preenche número, valor e volumes) e **OCR de lacre** (lê o código), volumes, marcação "carga conforme/divergência", observações, foto e GPS. Offline-first. Servidor: `app_android/api/conferencia_carga.php`; tabela `conferencia_carga` (`sql/conferencia_carga.sql`); OCR via `ocr_documento.php` (tipos `nota`/`lacre`). App: `ConferenciaCargaScreen` + `ConferenciaViewModel`. *(Leitura de código de barras/QR fica como complemento futuro ao OCR.)*

### 14.1. Ordem de execução sugerida (para vender a transportadoras)
1. Comprovante de Entrega (POD) · 2. Ocorrências de Viagem · 3. Jornada do Motorista · 4. Push Notifications · 5. Chat com a Central · 6. Geofencing · 7. Botão de Pânico · 8. Controle de Velocidade · 9. Integração CT-e/MDF-e · 10. IA do Motorista.

> Esses 10 transformam o app de uma ferramenta operacional simples em uma **plataforma profissional de gestão de motoristas e viagens**.
