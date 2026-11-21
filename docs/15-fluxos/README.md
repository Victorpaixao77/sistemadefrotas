[← Índice](../README.md)

# 15 · Fluxos (Fluxogramas Mermaid)

## Login e acesso

```mermaid
flowchart TD
    A[login.php] -->|credenciais| B{Válido?}
    B -- não --> A
    B -- sim --> C{2FA ativo?}
    C -- sim --> D[Pede código TOTP]
    D --> E{Código ok?}
    E -- não --> D
    E -- sim --> F{acesso_todas_empresas?}
    C -- não --> F
    F -- sim --> G[selecionar_empresa.php]
    F -- não --> H[define empresa_id]
    G --> H
    H --> I[sf_sidebar_reset=1]
    I --> J[index.php - Dashboard]
```

## Ciclo TMS do frete

```mermaid
flowchart LR
    CLI[Cliente] --> OT[Ordem de Transporte cotacao]
    OT --> AP{Aprovação}
    AP -- rejeita --> CAN[OT cancelada]
    AP -- aprova --> PL[Planejamento: veiculo + motorista]
    PL --> ROTA[Gera Rota]
    ROTA --> GPS[App: GPS + eventos]
    GPS --> POD[Entrega / POD]
    POD --> CTE[Emite CT-e prefill]
    CTE --> FIN[Financeiro / Comissão]
    ROTA -.rastreio por token.-> CLIENTE_EXT[Cliente acompanha]
```

## Abastecimento (app, com OCR)

```mermaid
flowchart TD
    A[Motorista tira foto do cupom/painel] --> B[OCR IA le KM e litros]
    B --> C[Preenche formulario]
    C --> D{Online?}
    D -- sim --> E[POST api abastecimento]
    D -- nao --> F[Outbox offline]
    F -->|conexao volta| E
    E --> G[Anti-fraude km/l valida]
    G --> H[Gestor ve em abastecimentos.php + insights]
```

## Emissão fiscal (CT-e a partir da OT)

```mermaid
flowchart TD
    A[OT aprovada/em viagem] --> B[Botao Emitir CT-e]
    B --> C[Wizard CT-e pre-preenchido]
    C --> D[Assina com certificado A1]
    D --> E[SEFAZ autoriza]
    E -- ok --> F[fiscal_cte status autorizado + vincula ordem_transporte_id]
    E -- rejeita --> G[Corrige e reenvia; fiscal_logs]
```

## Manutenção preditiva

```mermaid
flowchart LR
    A[cron atualizar_km_veiculos] --> B[veiculos.km_atual = maior KM real]
    B --> C[km desde ultima revisao]
    D[rotas 90 dias] --> E[km/dia medio]
    C --> F{Previsao}
    E --> F
    F --> G[Atrasada / Proxima / Em dia / Sem dados]
    G --> H[Central de Alertas + tela Preditiva]
```

> Fluxos fiscais detalhados (NF-e/MDF-e/CIOT) nos manuais em `docs/` (fiscais).
