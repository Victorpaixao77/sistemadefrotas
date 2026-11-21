# App do Motorista — Sistema de Frotas (Frotec)

Documentação funcional e técnica completa do aplicativo Android para motoristas.

> **Pacote:** `com.sistemafrotas.motorista` · **Versão:** 2.5 (versionCode 16)
> **Plataforma:** Android 8.0+ (minSdk 26) · alvo Android 14 (targetSdk 34)

---

## 1. Visão geral

O app é o **portal do motorista** do Sistema de Frotas. Ele permite que o motorista, do próprio celular:

- veja seu **painel** (rotas do dia, indicadores do mês, último GPS) e **avisos** (nova rota, checklist pendente, CNH vencendo);
- gerencie suas **rotas** (listar, criar, editar, excluir, ver detalhes);
- registre **abastecimentos** (com foto do comprovante e GPS) — com **OCR por IA** que lê o cupom e o painel (KM) e preenche o formulário;
- preencha **checklists inteligentes** (itens por tipo de veículo, incluindo carreta);
- lance **despesas de viagem** e **pedágios** (com OCR do recibo);
- faça **inspeção de pneus** lendo o QR Code do pneu;
- registre **ocorrências de viagem**, **jornada** (Lei do Motorista), **comprovante de entrega (POD)** com assinatura, **pânico** e **conferência de carga** (OCR de NF + leitura de código de barras/QR do lacre);
- acompanhe seu **consumo** (km/l + alertas antifraude), **controle de velocidade** e **avaliação/ranking**;
- envie a **localização GPS em segundo plano**, com **detecção automática de paradas/pernoite** e sugestão de **encerrar a rota**;
- use o **Modo Viagem** (copiloto digital): inicia a viagem num toque, acompanha com timeline, assistente IA e prompts GPS até o relatório final.

Tudo funciona **offline-first**: ações feitas sem internet ficam numa fila (outbox) e sincronizam sozinhas quando a conexão volta. A leitura por **IA de visão** (OCR) reaproveita o provedor já configurado por empresa (Gemini/OpenAI/OpenRouter/DeepSeek).

### 1.1. Recursos implementados (resumo) — v2.5

| Recurso | Onde no app | Endpoint / arquivos principais |
|---|---|---|
| **Modo Viagem v2.5** (POI posto/pedágio, cercas, sync timeline, PDF relatório, IA async) | Início / Mais | `feature/viagem/*`, `trip_timeline.php`, `viagem_context.php` |
| **Login 2FA** (TOTP) | Login | `auth.php`, `sql/motorista_app_2fa.sql` |
| **Mapa da viagem** (trajeto GPS 24h) | Mais | `gps_trajeto.php`, `MapaViagemScreen` |
| **Meus documentos** (CNH + alertas) | Mais | `motorista_documentos.php`, `DocumentosScreen` |
| **Assistente IA** (regras + OpenAI) | Modo Viagem FAB | `motorista_assistente.php` |
| **Ocorrência** com vídeo + assinatura | Mais | `ocorrencias.php`, `SignaturePad` |
| **Checklist** com assinatura do motorista | Checklists | `checklists.php`, `sql/checklist_assinatura.sql` |
| Painel (rotas do dia, indicadores, GPS) | Início | `dashboard.php`, `DashboardScreen` |
| Rotas (CRUD + detalhe) | Rotas | `rotas.php`, `RotasScreen` |
| Abastecimentos + **OCR cupom/painel** | Abastecimentos | `abastecimentos.php`, `ocr_documento.php`, `OcrLeitorButton` |
| Despesas de viagem | Despesas | `despesas.php` |
| Checklist inteligente (por tipo de veículo) | Checklists | `checklists.php`, `sql/checklist_itens_carreta.sql` |
| Inspeção de pneus (QR) | Pneus | `PneusQrScannerDialog` |
| Ocorrências de viagem | Mais | `ocorrencias.php` |
| Jornada do motorista (Lei do Motorista) | Mais | `jornada.php` |
| Comprovante de entrega (POD) + assinatura | Mais | `comprovantes_entrega.php`, `PodScreen` |
| Botão de pânico | Mais | `panico.php` |
| Diário de bordo | Mais | `diario.php` |
| **Controle de pedágios** + OCR recibo | Mais | `pedagios.php`, `sql/pedagios_viagem.sql` |
| **Controle de velocidade** (pontuação) | Mais | `velocidade.php` |
| **Meu consumo** (IA + antifraude) | Mais | `abastecimento_insights.php` |
| **Minha avaliação** (score + ranking) | Mais | `desempenho.php` |
| **Conferência de carga** (OCR NF/lacre + barcode/QR) | Mais | `conferencia_carga.php`, `sql/conferencia_carga.sql`, `BarcodeScannerDialog` |
| **Avisos** (nova rota / checklist / CNH) por polling | Mais | `avisos.php`, `AvisosPollingWorker` |
| GPS em segundo plano | Automático | `GpsForegroundService`, `salvar_gps_lote.php` |
| **Detecção automática de parada/pernoite/encerrar** | Automático | `evento_auto.php` |
| OCR de visão reutilizável (abastecimento/painel/pedágio/nota/lacre) | — | `ocr_documento.php`, `includes/sf_vision_ocr.php` |

> **Não implementado ainda:** Push real via FCM (precisa do `google-services.json`); Chat com a central; integrações fiscais (CT-e/MDF-e/CIOT/Vale-Pedágio); scanner CNH/CRLV → PDF; IA visual (danos/carga/pneu); Geofencing nativo Android (`GeofencingClient`).

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
  feature/viagem/ → Modo Viagem (domain, data, detectors, viewmodel, ui)
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

A navegação principal é uma **barra inferior com 5 abas** (padrão Material 3, rótulos sempre visíveis):

| Aba | Ícone | Abre |
|---|---|---|
| **Início** | Dashboard | Home (portal do motorista) |
| **Fretes** | LocalShipping | Rotas |
| **Financeiro** | Payments | Despesas de viagem |
| **Documentos** | Description | Comprovante de entrega (POD) |
| **Mais** | Apps | Hub de recursos secundários |

O hub **"Mais"** reúne: **Abastecimentos, Checklists, Pneus**, Avisos, Pânico, Ocorrências, Jornada, Pedágios, Velocidade, Meu consumo, Conferência de carga, Minha avaliação e Diário de bordo. **Configurações** e **Sair** ficam no menu do avatar no cabeçalho do Início.

**Voltar inteligente:** ao abrir um recurso do "Mais" por um **atalho do Início**, o voltar retorna ao **Início**; ao abrir navegando pela lista do "Mais", o voltar retorna à lista (um único "voltar", na barra superior).

### 4.1. Login (`LoginScreen`)
- Entrada por **nome de usuário + senha**.
- Suporte a **2FA TOTP**: se a conta exige código, o campo de 6 dígitos aparece após a primeira tentativa.
- Autentica em `auth.php`, recebe **token** + **refresh token** e guarda no DataStore.
- Trata erros de rede e credenciais com mensagem amigável.

### 4.2. Início / Dashboard (`DashboardScreen`) — redesign premium
Home reprojetada com foco em uso diário do motorista (Material 3, tema claro/escuro):
- **Header azul** com **saudação por horário** ("Bom dia/tarde/noite"), primeiro nome, **sino** (Avisos) e **avatar** com menu (Configurações / Sair).
- **Card Modo Viagem** (hero navy): copiloto digital com botão **INICIAR VIAGEM** — abre painel full-screen com timeline, assistente IA e ações contextuais.
- **Card de Emergência** (vermelho) sempre visível.
- **Ações rápidas** em lista agrupada (ícone + título + descrição + seta): Abastecer, Jornada, Entrega (POD), Ocorrência, Checklist.
- **Status da jornada** em destaque: estado atual (verde/vermelho) e botão grande **Iniciar Jornada**.
- **Resumo do mês** em 4 cards uniformes (valores **reais** em R$): Fretes, Comissão, Despesas e **Lucro líquido** (valor em verde/vermelho).
- **Atividades recentes** (timeline) e **Avisos** (pendências reais: rotas/abastecimentos/checklists; "Tudo em dia" quando não há).
- **Status de GPS** e **pull-to-refresh**; detecta **sessão expirada** e redireciona ao login.
- *Observação (dados reais):* não há sparklines nem variações "%" porque a API não expõe série histórica — só valores reais são exibidos.

### 4.2.1. Modo Viagem (`TripModeScreen`) — copiloto digital

Overlay full-screen acessível pelo **Início** (card hero navy) ou **Mais → Modo Viagem**.

#### Entrada e fluxo
- **Tela inicial:** próxima rota do dia (origem, destino, veículo, motorista) + botão **INICIAR VIAGEM**.
- **Painel ativo:** cronômetro, stats (dirigindo / parado / abastecimentos), ações rápidas e **linha do tempo** automática.
- **Encerrar viagem:** abre **relatório** (origem, destino, placa, eventos) → **Concluir** volta ao Início.
- **Voltar** (seta ou botão do sistema): fecha o overlay **sem encerrar** a viagem; ao reabrir, a sessão continua de onde parou.

#### Ações rápidas (abrem a feature na aba Mais)
Foto/OCR · Voz/Ocorrência · Abastecimento · Pedágio · Entrega (POD) · Ocorrência · Checklist · Conferir carga.

#### Assistente IA (FAB)
Bottom sheet com perguntas sugeridas; respostas com **dados reais** da rota/viagem (km previsto, despesas, jornada, pendências de abastecimento).

#### Automação e GPS
- **Início otimista:** salva sessão localmente e abre o painel na hora; detalhe da rota, jornada e GPS rodam em **background** (não trava a UI).
- **GPS:** liga `GpsForegroundService` se houver permissão de localização; `GpsForegroundService.start()` com `try/catch` (evita crash no Android 12+).
- **Parada detectada:** `GpsForegroundService` → `TripModeSignals` → prompt “Deseja registrar parada?” (confirmação antes de gravar).
- **Detectores desacoplados** (`TripDetectorCoordinator`): parada, posto, pedágio, chegada — stubs prontos para geodata/OCR/voz.

#### Persistência (DataStore)
| Store | Conteúdo |
|-------|----------|
| `TripSessionStore` | Sessão ativa (rota, fase, horários, origem/destino) |
| `TripTimelineStore` | Eventos da linha do tempo (JSON via Gson) |

#### Arquitetura (`feature/viagem/`)
```
domain/     TripPhase, TripSession, TripEvent, TripPrompt
data/       TripSessionStore, TripTimelineStore
detectors/  TripDetector, TripDetectorCoordinator
viewmodel/  TripModeViewModel
ui/         TripModeScreen
            TripModeSignals.kt  (ponte GPS → Modo Viagem)
```

#### Integração no app
- `MainScaffold`: overlay `showTripMode` sobre o conteúdo; deep-link para features via `maisInicial`.
- ViewModel **escopado ao overlay** (`ViewModelStore` próprio) — jobs (relógio, detectores) param ao fechar a tela.

#### Estabilidade (correções v2.3)
- Início/encerramento **não dependem da rede** para mudar de tela.
- Relatório abre **por cima** do painel (sem `return` que desmontava `LazyColumn`/`Scaffold`).
- Jobs em background **cancelados** ao encerrar; impede regravar viagem como “ativa” após o fim.
- Sessão `CONCLUIDA` órfã reabre o relatório em vez de spinner infinito.
- `BackHandler` trata voltar com relatório aberto vs. viagem em andamento.

#### Roadmap (próximas ondas)
OCR auto-tipo de documento · ocorrência por voz (STT) · geofence de chegada ao cliente · postos/pedágios conhecidos · relatório PDF/export · sync offline dedicado da timeline · IA visual (pneu, carga, danos).

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
3. Opcional: abre **Modo Viagem** (card no Início) → **INICIAR VIAGEM** → copiloto acompanha a operação (timeline, IA, prompts GPS).
4. Liga o **GPS** (Configurações) — o gestor passa a ver o veículo no mapa da frota.
5. Durante a viagem: cria/atualiza **rotas**, registra **abastecimentos** (com foto), preenche **checklists**, lança **despesas** e faz **inspeção de pneus** por QR.
6. **Encerra** pelo Modo Viagem (relatório) ou segue usando as abas normalmente.
7. Sem internet, tudo é **salvo localmente** e **sincroniza sozinho** quando a conexão volta.

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

**6. Notificações ao motorista** — ✅ **IMPLEMENTADO via polling** (aba Mais → "Avisos") · Esforço 🟡
Avisa: **Nova rota / rota de hoje · Checklist pendente · CNH vencendo/vencida**. Sem depender de Firebase: o app consulta `app_android/api/avisos.php` periodicamente (WorkManager a cada 2 h + no início do app) e dispara **notificação local** para cada aviso novo, com **dedup por chave** (SharedPreferences). Tela in-app `AvisosScreen` lista os avisos. Arquivos: `AvisosPollingWorker`, `AvisosScheduler`, `AvisosNotifier`, `AvisosViewModel`.
> **Push real (FCM)** continua como evolução: basta adicionar o `google-services.json` do projeto Firebase e o plugin; a lógica de avisos já existe e pode ser reaproveitada para o envio em tempo real.

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

**10b. Detecção automática de paradas / pernoite / encerrar rota** — ✅ **IMPLEMENTADO**
O `GpsForegroundService` analisa o GPS em tempo real (velocidade + distância da âncora): parado **≥5 min** → registra **parada** automática; **≥30 min** → notifica sugerindo **encerrar a rota**; **≥3 h** → registra **descanso/pernoite** (ajuda na Lei do Motorista). Os eventos vão para `jornada_motorista` com `fonte='auto'` via `app_android/api/evento_auto.php` (com dedup de 10 min) e aparecem na **Jornada** e no **Diário** com a observação ("Parada automática (X min)"). Notificações por `GpsNotifyHelper`. Sem custo extra de bateria — reaproveita os pings de GPS que o app já envia.

**11. Controle de Pedágios** — ✅ **IMPLEMENTADO** (aba Mais) · Esforço 🟢
Motorista registra cada praça: Valor · Praça/local · Foto do comprovante · GPS. Mostra o total dos últimos 30 dias e entra na linha do tempo do Diário. Offline-first (outbox com foto). Servidor: `app_android/api/pedagios.php` (GET lista+total, POST JSON/multipart); tabela `pedagios_viagem` (`sql/pedagios_viagem.sql`). App: `PedagiosScreen` + `PedagiosViewModel`. *(Integração Sem Parar fica na fase de terceiros.)*

**12. Checklist Inteligente** — Esforço 🟡 — ✅ IMPLEMENTADO
Itens agrupados (Veículo · Segurança · Documentação · Carga e carreta · Motorista) com seletor de "Configuração do veículo" (conjunto/caminhão/ônibus/outro). O grupo "Carga e carreta" — com os itens novos Quinta-roda · Mangueiras · Travas · Lona da carga — só aparece quando a configuração tem carreta. Servidor: `app_android/api/checklists.php` (INSERT dinâmico a partir de `$bools`); migração `sql/checklist_itens_carreta.sql` adiciona as colunas `quinta_roda`/`mangueiras`/`travas`/`lona_carga` a `checklist_viagem`.

### 🔵 Diferenciais Premium

**12b. Modo Viagem (copiloto digital)** — ✅ **IMPLEMENTADO** (Início + Mais) · Esforço 🟡 · Base ✅ (GPS, OCR, jornada, rotas)
> App: `feature/viagem/*`, `TripModeScreen`, integração `MainScaffold` / `DashboardScreen` / `MaisScreen`. Overlay full-screen: início otimista da viagem, painel com cronômetro e timeline, FAB assistente IA, ações rápidas (deep-link), prompts de parada via `TripModeSignals` + `GpsForegroundService`, relatório ao encerrar. Persistência: `TripSessionStore` + `TripTimelineStore` (DataStore). Detectores desacoplados (`TripDetectorCoordinator`) para posto/pedágio/chegada (stubs). Roadmap: OCR auto-tipo, voz, geofence, PDF.

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
Conferência na **coleta/entrega** com **OCR de NF** (preenche número, valor e volumes), **OCR de lacre** (lê o código) e **leitura de código de barras / QR** (ML Kit) para a **chave da NF** (extrai o número da nota dos 44 dígitos) e para o **lacre**, além de volumes, marcação "carga conforme/divergência", observações, foto e GPS. Offline-first. Servidor: `app_android/api/conferencia_carga.php`; tabela `conferencia_carga` (`sql/conferencia_carga.sql`); OCR via `ocr_documento.php` (tipos `nota`/`lacre`). App: `ConferenciaCargaScreen` + `ConferenciaViewModel` + componente reutilizável `BarcodeScannerDialog`.

### 14.1. Ordem de execução sugerida (para vender a transportadoras)
1. Comprovante de Entrega (POD) · 2. Ocorrências de Viagem · 3. Jornada do Motorista · 4. Push Notifications · 5. Chat com a Central · 6. Geofencing · 7. Botão de Pânico · 8. Controle de Velocidade · 9. Integração CT-e/MDF-e · 10. IA do Motorista.

> Esses 10 transformam o app de uma ferramenta operacional simples em uma **plataforma profissional de gestão de motoristas e viagens**.

---

## 15. Auditoria técnica — desempenho, bugs e segurança

Auditoria do código Android (hoje **113 arquivos Kotlin, ~15,6k linhas** + endpoints `app_android/api/`). Achados priorizados: 🔴 alto · 🟠 médio · 🟢 baixo. As rodadas 1–3 cobriram o núcleo; 4–6, o Modo Viagem; a 7ª, o código novo + endpoints (ver 15.5).

### 15.1. Desempenho / bateria
| # | Achado | Sev. | Recomendação |
|---|---|---|---|
| P1 | **`collectAsState()` em 52 pontos, 0 `collectAsStateWithLifecycle`** — os flows continuam sendo coletados com o app em segundo plano (gasto de CPU/bateria num app que roda o dia todo com GPS). A dependência `lifecycle-runtime-compose` **já está** no projeto. | 🔴 | Trocar `collectAsState()` → `collectAsStateWithLifecycle()` nas telas. Ganho direto de bateria, mudança mecânica. |
| P2 | **Listas `LazyColumn` sem `key`** (12 telas: Abastecimentos, Avisos, Checklists, Despesas, Diário, Jornada, Ocorrências, Pedágios, Velocidade, Consumo, Conferência, Mais). Sem identidade estável, o Compose recompõe itens desnecessariamente e a rolagem trava em atualizações. | 🟠 | Adicionar `key = { it.id }` em `items(list) { }`. |
| P3 | **`GpsDeviceStatusCard` faz polling a cada 2,5 s** (`while(true){ … delay(2500) }`). Para um card de status é agressivo. | 🟢 | Elevar para 6–10 s. |
| P4 | **Sem cache de resposta HTTP** (OkHttp sem `Cache`). GETs como `dashboard.php`/`rotas.php` recarregam sempre da rede no cold start. | 🟢 | Habilitar um `Cache` de disco pequeno (5–10 MB) para GETs; complementa o cache Room. |
| P5 | **`compose-bom:2023.10.01` e `lifecycle:2.6.2` antigos.** Versões novas do Compose trazem *strong skipping* e correções de performance. | 🟠 | Atualizar BOM/lifecycle (requer teste de regressão). |

### 15.2. Bugs / robustez
| # | Achado | Sev. | Recomendação |
|---|---|---|---|
| B1 | **`code.rawValue!!`** nos leitores de código de barras/QR (`BarcodeScannerDialog`, `PneusQrScannerDialog`). `rawValue` pode ser `null` → **crash**. | 🟠 | Trocar por `code.rawValue?.let { onBarcode(it) }`. |
| B2 | **`runBlocking` em `GpsLocationSettingsReceiver.onReceive`** — `BroadcastReceiver` tem janela curta (~10 s); flush de banco lento pode gerar **ANR**. | 🟠 | Usar `goAsync()` + coroutine, ou agendar via `WorkManager`. |
| B3 | **`catch (_: Exception) { }` vazio** em `AuthRepository:46` — engole erro silenciosamente. | 🟢 | Ao menos `Log`/`error_log` para diagnóstico. |
| B4 | **`!!` diversos** (`a.descricao!!`, `erro!!`, `body.data!!`). A maioria está protegida por `if (… != null)` na mesma branch (ok), mas são frágeis a refatorações. | 🟢 | Preferir `?.let`/smart-cast; evitar `!!` fora de guardas óbvias. |

### 15.3. Segurança
| # | Achado | Sev. | Recomendação |
|---|---|---|---|
| S1 | **HTTP cleartext** — `baseUrl` padrão `http://10.0.2.2/...` e `usesCleartextTraffic="true"` no manifesto. Token e credenciais trafegam **sem TLS**. Aceitável em LAN/servidor próprio, mas arriscado em redes públicas. | 🟠 | Suportar **HTTPS**; usar `network_security_config` para permitir cleartext só em hosts específicos (dev). |
| S2 | Logging de corpo HTTP já é **gated** por `BuildConfig.LOG_API_BODY` (desligado em release) ✅; timeouts (30 s) configurados ✅. | — | Manter. |

### 15.4. Pontos positivos observados
- **Offline-first** sólido: cache Room + outbox de escrita + reconciliação de IDs.
- **Retrofit suspend** (dispatch de rede automático fora da main thread) + timeouts + refresh token via Authenticator.
- **Imagens via Coil** (cache automático).
- **Foreground service de GPS** com permissões corretas (`FOREGROUND_SERVICE_LOCATION`, `ACCESS_BACKGROUND_LOCATION`).
- **Logging de corpo desligado em release**.

### 15.5. Status de aplicação

**✅ Aplicado nesta rodada:**
- **P1** — `collectAsState()` → `collectAsStateWithLifecycle()` em **17 arquivos / 55 pontos** (+ import). Para de coletar flows com o app em segundo plano (bateria).
- **P2** — `key` em **9 listas** (`items(list, key = { it.id })`; Avisos por `chave`, Mais por `key`). Diário/Consumo/Velocidade ficaram sem key porque o modelo não tem id estável.
- **P3** — polling do `GpsDeviceStatusCard` de 2,5 s → **6 s**.
- **B1** — removido `!!` dos leitores de código (`code.rawValue?.let(onBarcode)`).
- **B3** — logout remoto agora **loga** o erro em vez de engolir.
- **B2** — reavaliado: **já estava correto** (`goAsync()` + `Thread` + `runBlocking(IO)`), sem risco de ANR. Nada a mudar.

**✅ Aplicado — 2ª rodada:**
- **P5 (Kotlin 2.0 + Compose 1.7)** — migração de toolchain: Kotlin `1.9.22 → 2.0.21`, KSP `→ 2.0.21-1.0.25`, novo plugin `org.jetbrains.kotlin.plugin.compose` (substitui `kotlinCompilerExtensionVersion`), Compose BOM `2023.10.01 → 2024.09.03`, lifecycle `2.6.2 → 2.8.6`, activity-compose `1.9.2`, navigation-compose `2.8.2`. Habilita **strong skipping** (menos recomposições). *Requer build de validação (migração maior).* Se o KSP/Room reclamar, o próximo passo é subir Room para `2.7.x`.
- **S1 (rede)** — adicionado `res/xml/network_security_config.xml` (+ referência no manifesto): mantém **HTTP na LAN** (não quebra) **e** passa a confiar em **certificados internos (user CA)** → habilita **HTTPS corporativo** sem trocar código. Migrar para `https://…` público passa a ser só trocar a URL.

**✅ Aplicado — 3ª rodada:**
- **P4 (offline do Início)** — o **painel/Dashboard** agora funciona **sem internet**: `loadDashboardResult()` salva o último painel em arquivo JSON (`dashboard_cache.json`, via Gson, sem tocar no schema Room) e, se a rede falhar, mostra o último salvo — mesmo padrão já usado em Rotas. Sem mexer no núcleo de rede/auth. O banner de conectividade já sinaliza o modo offline.

**✅ Aplicado — 4ª rodada (Modo Viagem v2.3):**
- **Modo Viagem** — novo copiloto digital do motorista (`feature/viagem/`): overlay full-screen no **Início** (card hero) e **Mais → Modo Viagem**.
- **Início otimista** da viagem: sessão salva no DataStore e painel abre na hora; API (detalhe da rota, `registrarJornada`) e GPS em background.
- **Painel ativo:** cronômetro, stats, ações rápidas (deep-link para abastecimento, POD, pedágio, etc.), linha do tempo automática, FAB **Assistente IA** (dados reais da rota).
- **GPS integrado:** `TripModeSignals` conecta paradas do `GpsForegroundService` a prompts contextuais; `start()` do serviço com `try/catch` e checagem de permissão.
- **Encerrar viagem:** relatório modal + placeholder estável (sem desmontar `LazyColumn`); persistência e jornada em background; retoma sessão ao reabrir o overlay.
- **Estabilidade:** ViewModel escopado ao overlay; cancelamento de jobs ao encerrar; tratamento de sessão `CONCLUIDA` órfã; `BackHandler` para voltar sem derrubar o app.
- Arquivos principais: `TripModeScreen.kt`, `TripModeViewModel.kt`, `TripSessionStore.kt`, `TripTimelineStore.kt`, `TripDetectorCoordinator.kt`, `TripModeSignals.kt`, integração em `MainScaffold.kt` / `DashboardScreen.kt` / `MaisScreen.kt`.

**✅ Aplicado — 5ª rodada (Modo Viagem v2.4):**
- **Foto OCR inteligente** (`TripSmartPhoto.kt`): câmera → IA classifica tipo (`auto`) → abre tela correta com formulário pré-preenchido (`TripFeatureBridge` + `AbastecimentosScreen`).
- **Ocorrência por voz** (`TripVoice.kt` + `SpeechRecognizer`): texto transcrito → tipo inferido → abre `OcorrenciasScreen` com campos preenchidos.
- **Chegada ao destino:** GPS + `Geocoder` compara cidade com destino da rota → prompt POD.
- **Lembretes:** 4h dirigindo e abastecimento pendente após 2h de viagem.
- **Dashboard:** banner verde “Viagem em andamento” quando há sessão ativa.
- **Relatório:** compartilhar via `Intent.ACTION_SEND`; confirmação antes de encerrar.
- Roadmap restante: FCM push, chat central, integrações fiscais, IA visual (danos/carga).

**✅ Aplicado — 6ª rodada (v2.5):**
- **Sync timeline** com servidor (`trip_timeline.php` + tabela `trip_timeline_events`).
- **Contexto de viagem:** postos/pedágios históricos, cercas `gps_cercas`, destino (`viagem_context.php`).
- **Assistente IA async** via API (`motorista_assistente.php` + `sf_openai_chat_completion`).
- **Mapa da viagem** (Canvas, pontos GPS 24h) e **Meus documentos** (CNH).
- **Login 2FA** TOTP no app (`motorista_app_2fa.sql`).
- **Ocorrência:** vídeo + assinatura; **Checklist:** assinatura do motorista.
- **Relatório PDF** (`TripReportPdf.kt`) + compartilhar.
- **OCR prefill** em pedágio e conferência de carga via `TripFeatureBridge`.

**✅ Aplicado — 7ª rodada (auditoria Modo Viagem + endpoints):**
Escopo: código novo desta versão (Modo Viagem ~2,7k linhas + 6 endpoints PHP novos); base hoje **113 arquivos Kotlin / ~15,6k linhas**.
- **P2 (recomposição)** — `items(ui.events)` sem `key` em `TripModeScreen` → `key = { it.id }` (identidade estável na linha do tempo).
- **Perf (servidor)** — `trip_timeline.php` re-preparava o `SELECT rotas` **a cada evento** do lote → `prepare` movido para fora do loop (`$chkRota`, reexecutado).
- **Robustez/segurança (servidor)** — `motorista_documentos.php`: `new DateTime($validade)` lança `Exception` (não `PDOException`) → ficava fora do `catch` e podia vazar erro cru; agora em `try/catch (Exception)` retornando estado neutro.
- **Clareza (regra "nada fictício")** — comentário "Simula detecção…" em `IdleStopPromptDetector` estava **desatualizado**: a parada é alimentada por **GPS real** (`GpsForegroundService` → `TripModeSignals.onIdleDetected`); comentários corrigidos. Removido `Gson` sem uso em `TripSessionStore`.

**Verificado sem necessidade de correção:** os 6 endpoints novos (`viagem_context`, `trip_timeline`, `motorista_assistente`, `motorista_documentos`, `gps_trajeto`, `avisos`) usam **token + `empresa_id` + `motorista_id` + prepared statements + erro logado com mensagem genérica**; `api_success/api_error` fazem `exit` (sem dupla resposta no ramo `gps_logs`); `evento_em` já sai no formato `yyyy-MM-dd HH:mm:ss`; FileProvider `path="."` cobre `cacheDir/relatorios` e os `.jpg`; `RECORD_AUDIO` já declarado; **sem regressão** de `collectAsStateWithLifecycle`; 2FA (`auth.php`) com prepared/escopo/`sf_totp_verify` e `getMessage()` só em `strpos` interno.

**Recomendações em aberto (baixa severidade, sem ação nesta rodada):** (1) **rate-limit** nas tentativas de código TOTP no login do app (hoje só o password é limitado); (2) `viagem_context.php` não popula `destino_lat/lng` → a detecção de chegada cai no **geocoding por cidade** (funciona, mas menos preciso que por coordenada).

> As demais telas secundárias (consumo, velocidade, avaliação…) **não** receberam cache offline por baixo valor prático (uso raro) × custo de código; o padrão está pronto para replicar se necessário.

> **Resumo final:** P1–P5, S1, B1, B3 aplicados; **Modo Viagem v2.5** integrado com sync, mapa, documentos, 2FA e assinaturas. 7ª rodada auditou o código novo (Modo Viagem + endpoints) e aplicou P2/perf/robustez; nenhuma falha de segurança encontrada nos endpoints novos.
