[← Índice](README.md)

# 17 · Manutenção, Backup e Deploy

## Backup

| O quê | Como |
|-------|------|
| **Banco** | `mysqldump -h 127.0.0.1 -P 3307 -u root sistema_frotas > backup_YYYYMMDD.sql` |
| **Uploads** | copiar `uploads/` (anexos, comprovantes, certificados de empresa) |
| **Config** | variáveis de ambiente / `.env` do servidor (fora do git) |
| **Certificados fiscais** | A1 por empresa (em `uploads/` e/ou `fiscal_certificados_digitais`) |

> Fazer backup **antes** de aplicar migração ou deploy. Guardar dumps versionados.

## Restore

```bash
mysql -h 127.0.0.1 -P 3307 -u root -e "CREATE DATABASE sistema_frotas CHARACTER SET utf8mb4;"
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < backup_YYYYMMDD.sql
# restaurar uploads/ e variáveis de ambiente
composer install   # regenerar vendor/
```

## Deploy (atualização)

1. **Backup** do banco e `uploads/`.
2. `git pull` (ou enviar arquivos). **Não** subir `vendor/`, `logs/`, `cache/`, `tmp/`,
   `sessions/`, `uploads/` (ver `.gitignore`).
3. `composer install --no-dev` (produção; libs fiscais são obrigatórias).
4. Aplicar migrações `sql/` **novas** (as que ainda não estão no banco).
5. Garantir `logs/ cache/ tmp/ sessions/ uploads/` graváveis pelo Apache.
6. Conferir variáveis de ambiente (`SF_DB_*`, IA, fiscal). `SF_DEBUG` **desligado**.
7. Registrar cron jobs (ver [10-cronjobs.md](10-cronjobs.md)).
8. Smoke test: `login.php` → `index.php`; emitir um documento fiscal de teste.

## Rollback

- **Código:** `git checkout <tag_anterior>` / restaurar pacote anterior.
- **Banco:** restaurar o dump pré-deploy. Migrações **não têm `down`** — por isso o backup
  antes de migrar é obrigatório.

## Migração para servidor novo

Seguir [02-instalacao.md](02-instalacao.md) + restaurar dump + `uploads/` + env + `composer install`
+ cron. Ajustar `php.ini` (upload, execution time, timezone BR) e `.htaccess`/`mod_rewrite`.

## Checklist de deploy

- [ ] Backup banco + uploads
- [ ] `composer install`
- [ ] Migrações novas aplicadas
- [ ] Pastas graváveis
- [ ] Env conferido, `SF_DEBUG=0`
- [ ] Cron registrado
- [ ] Smoke test (login, uma emissão fiscal, um envio de relatório)
