[← Índice](README.md)

# A1 · Arquitetura, Dependências e Eventos Automáticos

## 1. Arquitetura em camadas

O Frotec **não usa framework MVC**; a "camada" é lógica (scripts PHP + includes
compartilhados), não pastas rígidas. O fluxo real de uma requisição:

```mermaid
flowchart TD
    subgraph Cliente
      W[Navegador - pages/*.php + js/*.js]
      APP[App Android - Kotlin/Compose]
      EXT[Cliente externo - rastreio.php por token]
    end
    W -->|GET/POST JSON| API[api/*.php]
    APP -->|token| APIAPP[app_android/api/*.php]
    API --> AUTH[includes: config, auth, csrf, security]
    APIAPP --> AUTH
    AUTH --> HELP[includes de dominio: sf_antt_frete, sf_vision_ocr, functions, relatorios_helpers]
    HELP --> DB[(MySQL - 230 tabelas)]
    HELP --> INT[Integracoes: SEFAZ, PEF/CIOT, DENATRAN, Google Maps, BrasilAPI]
    HELP --> IA[Provedores IA/OCR por empresa]
    DB --> BI[BI/Relatorios/Dashboard]
    IA --> BI
    EXT --> API
```

> Camadas equivalentes: **Front** (`pages/`,`js/`,`css/`) → **API** (`api/`) →
> **"Services/Helpers"** (`includes/`) → **Banco** (MySQL) + **Integrações** + **IA** →
> **BI/Relatórios/Dashboard**. Não há Controllers/Services formais — a lógica vive nos
> endpoints e nos helpers de `includes/`.

## 2. Dependência entre módulos

```mermaid
flowchart LR
    OT[Ordens de Transporte] --> CLI[Clientes]
    OT --> VEI[Veiculos]
    OT --> IMP[Implementos]
    OT --> MOT[Motoristas]
    OT --> ROTA[Rotas]
    ROTA --> GPS[GPS]
    ROTA --> ABAST[Abastecimento]
    ROTA --> COM[Comissoes]
    ROTA --> FIN[Financeiro]
    OT --> CTE[CT-e]
    CTE --> MDFE[MDF-e]
    CTE --> CIOT[CIOT/PEF]
    FIN --> BI[BI]
    GPS --> BI
    ABAST --> BI
    BI --> IA[IA]
    VEI --> MAN[Manutencao]
    VEI --> PNEU[Pneus]
    MAN --> PRED[Manutencao Preditiva]
    MOT --> GAM[Gamificacao]
    ALL[Centro de Operacoes] -.agrega.-> OT & GPS & FIN & CTE & MDFE & MAN
```

**Regra transversal:** *todos* dependem de **Segurança/Auth** e do isolamento
**`empresa_id`** (tabela `empresa_clientes`).

## 3. Eventos automáticos (o que acontece sozinho)

### Em tempo de banco (triggers/procedures reais)
| Gatilho | Efeito |
|---------|--------|
| Concluir rota (`tr_atualizar_ranking_rota`) | Atualiza ranking do motorista |
| Concluir checklist (`tr_atualizar_gamificacao_checklist`) | Pontua gamificação |
| Inserir/editar CNH de motorista (`trigger_motorista_cnh_*`) | Sincroniza vencimento no calendário |
| Inserir/editar multa (`trigger_multa_*_calendario`) | Sincroniza multa no calendário |
| Inserir pneu (`after_pneu_insert`) | Efeito colateral de pneu |
| Procedures | `sp_calcular_ranking_motoristas`, `sincronizar_cnh_calendario`, `sincronizar_multas_calendario` |

### Em tempo de aplicação (fluxo)
```mermaid
flowchart LR
    R[Rota concluida] --> C[Comissao recalculada]
    R --> F[Financeiro/lucratividade]
    R --> K[KPIs do Centro de Operacoes]
    R --> RANK[Ranking/gamificacao - via trigger]
    A[Abastecimento salvo] --> AF[Anti-fraude km/L]
    A --> INS[Insights IA de consumo]
    OT[OT aprovada] --> ROTA[Gera rota real]
    CTE[CT-e autorizado] --> LINK[Vincula ordem_transporte_id]
    LOGIN[Login] --> RESET[Reseta submenus do sidebar]
```

### Em tempo agendado (cron)
| Horário sugerido | Job | Efeito |
|------------------|-----|--------|
| a cada 15 min | `adm_cc_maintenance.php` | Storage/cache/logs/cron vencidos |
| diário (madrugada) | `gps_agregar_diario.php` | Agrega GPS do dia anterior |
| diário | `atualizar_km_veiculos.php` | Atualiza KM da frota (preditiva) |
| diário | `arquivar_notificacoes.php` | Arquiva notificações antigas |
| de hora em hora | `enviar_relatorios_agendados.php` | Envia relatórios agendados |
| contínuo | `gps_redis_worker.php` | Consome fila Redis de GPS |

Detalhes em [10-cronjobs.md](10-cronjobs.md).

## 4. Linha do tempo diária (cron)

```mermaid
gantt
    dateFormat HH:mm
    axisFormat %H:%M
    title Rotinas automáticas no dia
    section Contínuo
    Control Center (15/15min) :00:00, 24h
    Worker Redis GPS          :00:00, 24h
    Relatórios agendados (1/1h):00:00, 24h
    section Madrugada
    Agregar GPS diário   :02:00, 20m
    Atualizar KM frota   :02:30, 15m
    Arquivar notificações:03:00, 15m
```
