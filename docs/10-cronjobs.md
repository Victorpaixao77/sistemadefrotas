[← Índice](README.md)

# 10 · Cron Jobs

6 rotinas em `cron/`. Em Windows use o **Agendador de Tarefas**; em Linux, `crontab`.
Documento de produção relacionado: [CRON_PRODUCAO.md](CRON_PRODUCAO.md). O painel admin
tem `adm_cron_jobs` / `adm_cron_runs` para registrar e auditar execuções.

| Job | Arquivo | Frequência sugerida | Objetivo |
|-----|---------|--------------------|----------|
| **Manutenção Control Center** | `cron/adm_cc_maintenance.php` | `*/15 * * * *` | Storage/cache, purge de logs, marcar cron jobs vencidos |
| **Arquivar notificações** | `cron/arquivar_notificacoes.php` | diário | Move `notificacoes` antigas → `notificacoes_arquivo` (a tabela viva cresce sem limite) |
| **Atualizar KM da frota** | `cron/atualizar_km_veiculos.php` | diário | `veiculos.km_atual` = maior KM real (`rotas.km_chegada`, `abastecimentos.km_atual`); só aumenta (fecha o ciclo da manutenção preditiva) |
| **Relatórios agendados** | `cron/enviar_relatorios_agendados.php` | de hora em hora | Envia por e-mail os relatórios automáticos vencidos |
| **Agregar GPS diário** | `cron/gps_agregar_diario.php` | diário | Agrega `gps_logs` do dia anterior em `gps_logs_resumido` e apaga o bruto (aceita data opcional) |
| **Worker Redis GPS** | `cron/gps_redis_worker.php` | serviço contínuo | Consome fila Redis (`BRPOP`) de posições GPS; exige `SF_REDIS_*` |

## Como registrar (Windows)

```
schtasks /Create /SC MINUTE /MO 15 /TN "Frotec-CC" ^
  /TR "C:\xampp\php\php.exe C:\xampp\htdocs\sistema-frotas\cron\adm_cc_maintenance.php"
```

## Como testar manualmente

```bash
C:/xampp/php/php.exe cron/atualizar_km_veiculos.php
C:/xampp/php/php.exe cron/gps_agregar_diario.php 2026-04-04   # data específica
```

- **Entradas:** leem do banco (por empresa quando aplicável) e/ou Redis.
- **Saídas:** gravam no banco e imprimem resumo no stdout; erros vão para `logs/`.
- **Falhas:** rodar manualmente com `SF_DEBUG=1` e checar `logs/`. O worker Redis só roda
  com `SF_REDIS_HOST` configurado; sem Redis, o GPS grava direto no banco.

> **Função reutilizável:** `atualizar_km_veiculos.php` expõe
> `sf_atualizar_km_veiculos($conn, $empresa_id)` (UPDATE não destrutivo com `GREATEST`),
> também chamada pelo botão "Atualizar KM da frota" na Manutenção Preditiva.
