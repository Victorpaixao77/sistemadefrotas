[← Índice](README.md)

# 12 · Performance

## Banco

- **Índices:** tabelas quentes têm índice por `empresa_id` (+ colunas de filtro/data).
  Ao criar consulta nova, garantir índice cobrindo `empresa_id` + coluna filtrada.
- **N+1:** evitar consulta dentro de laço. Preferir `JOIN` ou subquery correlata única
  (padrão usado no `api/centro_operacoes.php`).
- **GPS:** `gps_logs` cresce muito rápido → agregação diária em `gps_logs_resumido`
  (`cron/gps_agregar_diario.php`) e `gps_ultima_posicao` para leitura barata do "agora".
- **Cache de BI:** `bi_cache`; geocode em `gps_geocode_cache`; configs de cache em
  `configuracoes_cache`.

## Front-end

- **Sem build step:** assets versionados por `sf_asset_v()` (cache-busting) — subir a versão
  (`?v=…`) ao alterar CSS/JS para o navegador pegar a mudança.
- **Chart.js/Leaflet** carregados por CDN; imagens com `max-width:100%`.
- **Lazy/polling:** dashboards atualizam por *polling* (ex.: Centro de Operações a cada 60s),
  não recarregam a página inteira.

## Redis (opcional)

- Fila/cache de GPS (`SF_GPS_REDIS_*`, `cron/gps_redis_worker.php`) para picos de telemetria.

## Pontos de atenção (achados)

| Item | Observação |
|------|-----------|
| Consultas pesadas de analytics | Vários `*_analytics.php` fazem agregações amplas — validar índices e período |
| Arquivos de demo em produção | `*_demo.php`, `examples/` não deveriam ir para produção |
| Duplicidade `veiculos.php`/`vehicles.php`, `rotas.php`/`routes.php` | Pares PT/EN — ver [99-validacao-final.md](99-validacao-final.md) |
| `notificacoes` sem arquivamento | Mitigado por `cron/arquivar_notificacoes.php` (precisa estar agendado) |

Detalhes e priorização em [99-validacao-final.md](99-validacao-final.md).
