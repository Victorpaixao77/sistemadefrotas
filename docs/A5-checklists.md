[← Índice](README.md)

# A5 · Checklists (Release, QA, Segurança, Performance, Banco)

Listas de verificação para padronizar entregas. Complementam [17-manutencao.md](17-manutencao.md).

## Checklist de Release

- [ ] Changelog atualizado ([A4](A4-changelog-roadmap.md))
- [ ] `php -l` em todos os arquivos alterados; `node --check` no JS
- [ ] Migrações `sql/` novas listadas e aplicadas em homologação
- [ ] Assets versionados (`?v=`) subidos quando CSS/JS mudou
- [ ] Backup de banco + `uploads/` antes do deploy
- [ ] Smoke test: login → `index.php`; 1 emissão fiscal; 1 relatório
- [ ] `SF_DEBUG=0` em produção; segredos por env
- [ ] Cron jobs registrados

## Checklist de Homologação (QA)

- [ ] Cada tela alterada abre sem erro (console limpo, sem 500)
- [ ] Isolamento: usuário de empresa A **não** vê dados da empresa B
- [ ] Permissões: perfil sem `can_*` não acessa a tela/dado
- [ ] CRUD completo (criar/editar/excluir/listar/paginar/filtrar)
- [ ] Validações de campo (obrigatórios, máscara, limites)
- [ ] Estados vazios corretos (sem dado fabricado)
- [ ] CSRF: ação sem token é rejeitada
- [ ] Responsivo (desktop/tablet/mobile) e dark/light
- [ ] Fluxo TMS ponta a ponta (OT → rota → GPS → CT-e → financeiro)

## Checklist de Segurança

- [ ] `require_authentication()` em toda página/API nova
- [ ] `empresa_id` em **todas** as queries (SELECT/UPDATE/DELETE)
- [ ] Prepared statements (sem concatenação de entrada)
- [ ] CSRF em toda escrita
- [ ] Saída escapada (`htmlspecialchars`/`textContent`)
- [ ] Sem `$e->getMessage()` exposto; `error_log` + genérico
- [ ] Uploads validados e servidos por `download_anexo.php` (checa `empresa_id`)
- [ ] Segredos por env; nenhum segredo devolvido por rede
- [ ] Endpoints `*_debug` fora de produção
- [ ] 2FA/anti-brute-force no login ativos

## Checklist de Performance

- [ ] Índice cobrindo `empresa_id` + coluna de filtro/data na query nova
- [ ] Sem N+1 (usar JOIN/subquery única)
- [ ] `EXPLAIN` nas consultas de analytics/listas grandes
- [ ] Paginação em listas potencialmente grandes
- [ ] GPS: usar `gps_logs_resumido`/`gps_ultima_posicao` para leitura
- [ ] Cache onde aplicável (`bi_cache`, geocode, Redis)
- [ ] Assets com `max-width:100%`; polling em vez de reload total

## Checklist de Banco

- [ ] `empresa_id` presente (isolamento) + índice
- [ ] PK e índices adequados; `created_at/updated_at`
- [ ] FKs coerentes (o banco já tem 149 FKs — ver [05a](05a-dicionario-dados.md))
- [ ] Triggers/procedures que dependem da tabela revisados
- [ ] Migração idempotente quando possível; backup antes de aplicar
- [ ] Sem `SELECT *` em telas críticas (colunas explícitas)
- [ ] Charset `utf8mb4`
