# DM — Composer fiscal em produção

**Manuais por módulo:** [`MANUAL_FISCAL_INDICE.md`](MANUAL_FISCAL_INDICE.md) (NF-e, CT-e, MDF-e, CIOT)

Documento de memória para executar **depois**, no servidor de produção, a instalação das dependências PHP necessárias ao MDF-e, CT-e, DAMDFE/DACTE e demais módulos fiscais.

## Objetivo

Habilitar os pacotes oficiais NFePHP no ambiente real:

| Pacote | Uso |
|--------|-----|
| `nfephp-org/sped-mdfe` | Emissão MDF-e, eventos SEFAZ (assinatura XML-DSig completa) |
| `nfephp-org/sped-nfe` | NF-e |
| `nfephp-org/sped-cte` | CT-e — emissão, consulta, eventos (CC-e, cancelar, comprovante…) |
| `nfephp-org/sped-da` | DAMDFE / DANFE / **DACTE** em layout oficial |

Sem `vendor/`, o sistema usa **fallback manual** (OpenSSL + cURL). Funciona para testes, mas em produção o recomendado é `composer install`.

## Pré-requisitos no servidor

- PHP **8.1+** (compatível com `composer.json` atual)
- Extensões: `openssl`, `curl`, `mbstring`, `gd`, `dom`, `json`, `zip`
- Composer instalado globalmente **ou** `composer.phar` na raiz do projeto
- Acesso SSH ao servidor da aplicação
- Janela de manutenção curta (recomendado) — parar filas/cron fiscal se houver

## Passo a passo

### 1. Backup

```bash
# Na pasta do projeto (ex.: /var/www/sistema-frotas)
cp -a vendor vendor.bak.$(date +%Y%m%d) 2>/dev/null || true
mysqldump -h HOST -P PORTA -u USER sistema_frotas > backup_pre_composer_$(date +%Y%m%d).sql
```

### 2. Atualizar código

```bash
cd /caminho/para/sistema-frotas
git pull   # ou deploy do artefato aprovado
```

Confirmar que existem `composer.json` e `composer.lock` na raiz.

### 3. Instalar dependências (produção)

```bash
cd /caminho/para/sistema-frotas
composer install --no-dev --optimize-autoloader --no-interaction
```

**Windows / XAMPP local (referência):**

```powershell
cd C:\xampp\htdocs\sistema-frotas
php composer.phar install --no-dev --optimize-autoloader
```

### 4. Permissões

```bash
chown -R www-data:www-data vendor
chmod -R a+rX vendor
```

Ajuste `www-data` conforme o usuário do Apache/nginx/PHP-FPM.

### 5. Verificação rápida

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('NFePHP\\MDFe\\Tools') ? 'sped-mdfe OK' : 'FALHA'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('NFePHP\\CTe\\Tools') ? 'sped-cte OK' : 'FALHA'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('NFePHP\\DA\\MDFe\\Damdfe') ? 'sped-da MDFe OK' : 'FALHA'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('NFePHP\\DA\\CTe\\Dacte') ? 'sped-da DACTe OK' : 'FALHA'; echo PHP_EOL;"
php -l fiscal/includes/MdfeService.php
php -l fiscal/includes/CTeService.php
```

### 6. Testes funcionais (homologação SEFAZ)

**MDF-e**

1. **Configurações → Fiscal** — certificado A1 e ambiente **homologação**.
2. Emitir **1 MDF-e** de teste → status autorizado.
3. **Download DAMDFE** na listagem MDF-e.
4. **Encerrar** o manifesto de teste.
5. (Opcional) Cancelar outro MDF-e dentro de 24h com justificativa.

**CT-e** (ver também [`docs/CTE_MOC_V4_STATUS.md`](CTE_MOC_V4_STATUS.md))

1. Aplicar SQL: `sql/cte_layout_v4.sql`, `sql/cte_fase2_eventos.sql`
2. NF-e status `recebida` → **Novo CT-e** (wizard)
3. **Enviar SEFAZ** → autorizado
4. Download **DACTE PDF** (verificar `X-Fiscal-Dacte-Engine: sped-da`)
5. (Opcional) CC-e, comprovante de entrega, cancelamento
6. Incluir CT-e em **MDF-e** de teste

Só após OK em homologação, alterar ambiente para **produção**.

## Variáveis de ambiente (não commitar)

Manter no `.env` ou config do servidor:

- `SF_DB_*` — banco
- `SF_OPENAI_API_KEY` — se usar IA
- `SF_PEF_CERT_PASS` — senha chave PEM do PEF ANTT
- `SF_SMTP_PASS`, `SF_RECAPTCHA_*` — segurança

## Rollback

```bash
cd /caminho/para/sistema-frotas
rm -rf vendor
mv vendor.bak.YYYYMMDD vendor
# Reiniciar PHP-FPM / Apache se necessário
```

O fallback manual continua ativo se `vendor` for removido; a API fiscal retorna HTTP 503 se `vendor/autoload.php` estiver ausente em endpoints que exigem NFeService no bootstrap.

## Checklist pós-deploy

- [ ] `vendor/autoload.php` existe e é legível pelo usuário do web server
- [ ] `composer install` sem erros
- [ ] Emissão MDF-e homologação OK
- [ ] DAMDFE PDF abre sem erro
- [ ] Eventos MDF-e cancelar/encerrar/condutor OK
- [ ] Emissão CT-e homologação OK (`sped-cte` + `sped-da`)
- [ ] DACTE PDF abre sem erro
- [ ] CT-e vinculado ao MDF-e OK
- [ ] CIOT PEF → MDF-e (importação) OK
- [ ] Logs sem `pacote ausente` / `missing_vendor`
- [ ] Backup do banco antes da virada para produção SEFAZ

## Referências no projeto

- `composer.json` — versões fixadas
- `fiscal/includes/MdfeService.php` — emissão e eventos
- `fiscal/includes/MdfeDamdfeSped.php` — DAMDFE via sped-da
- `fiscal/includes/CteDacteSped.php` — DACTE via sped-da
- `fiscal/includes/CTeService.php` — CT-e SEFAZ
- `docs/CTE_MOC_V4_STATUS.md` — deploy CT-e + checklist
- `docs/MDFE_FASE2.md` — itens da fase 2 (PEF + PDF)
- `CLAUDE.md` — convenções gerais

## Responsável / data

Preencher ao executar em produção:

| Campo | Valor |
|-------|-------|
| Data | |
| Executor | |
| Servidor | |
| Versão git / tag | |
| Resultado homologação | |
| Data virada produção SEFAZ | |
