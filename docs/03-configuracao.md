[← Índice](README.md)

# 03 · Configuração

Segredos e parâmetros vêm de **variáveis de ambiente** (`getenv`), com **fallback local**
no código quando a variável não existe. **Nunca commitar segredos.** Segredos também podem
vir do banco (`configuracoes_seguranca`, `fiscal_config_*`), sempre com `env` tendo
prioridade e mascarados (`********`) quando devolvidos por rede.

## Banco de dados (`includes/db_connect.php`)

| Variável | Padrão local | Descrição |
|----------|--------------|-----------|
| `SF_DB_HOST` | `127.0.0.1` | Host do MySQL |
| `SF_DB_NAME` | `sistema_frotas` | Nome do banco |
| `SF_DB_USER` | `root` | Usuário |
| `SF_DB_PASS` | (vazio) | Senha |

> Porta local **3307** (XAMPP). Conexão via `getConnection()` → PDO com
> `ERRMODE_EXCEPTION` e `EMULATE_PREPARES=false` (placeholders nomeados não repetem).

## Sessão / debug

| Variável | Descrição |
|----------|-----------|
| `SF_DEBUG` | `1` liga exibição de erros (dev). Em produção deixar desligado. |
| `SF_SESSION_PATH` | Caminho de gravação das sessões (padrão `sessions/`) |

Sessão configurada por `configure_session()` (cookie seguro, SameSite). Logout por
inatividade via `sf_enforce_idle_timeout()`.

## IA / OCR (visão)

| Variável | Descrição |
|----------|-----------|
| `SF_OPENAI_API_KEY` / `SF_OPENAI_VISION_MODEL` / `SF_OPENAI_IMAGE_DETAIL` | OpenAI |
| `SF_DEEPSEEK_API_KEY` / `SF_DEEPSEEK_BASE_URL` / `SF_DEEPSEEK_VISION_MODEL` | DeepSeek |
| `SF_GOOGLE_VISION_API_KEY` | Google Vision |

O provedor efetivo é escolhido **por empresa** (`empresa_openai_credentials`,
`empresa_vision_ai`, `configuracoes_ia`). OCR central em `includes/sf_vision_ocr.php`.

## Segurança / login

| Variável | Descrição |
|----------|-----------|
| `SF_RECAPTCHA_SITE_KEY` / `SF_RECAPTCHA_SECRET_KEY` | reCAPTCHA do login |
| `SF_RECAPTCHA_APOS_TENTATIVAS` | Nº de tentativas antes de exigir captcha |
| `SF_SMTP_PASS` | Senha SMTP (envio nativo `sf_smtp_send`) |

## GPS (dezenas de variáveis `SF_GPS_*`)

Ajustam validação e alertas de telemetria: velocidade máxima plausível, dedup de alertas,
precisão mínima (`SF_GPS_MAX_ACCURACY_M`), bloqueio de mock (`SF_GPS_BLOQUEAR_MOCK`),
cercas (`SF_GPS_CERCA_*`), ocioso/pernoite, reverse geocode e cache Redis. Lista completa:
`grep -rhoE "SF_GPS_[A-Z_]+" includes/`. Ver [06-regras-de-negocio.md](06-regras-de-negocio.md#gps).

## Redis (opcional)

| Variável | Descrição |
|----------|-----------|
| `SF_REDIS_HOST` / `SF_REDIS_PORT` / `SF_REDIS_PASSWORD` / `SF_REDIS_KEY` | Cache/worker de GPS |
| `SF_GPS_REDIS_CACHE` / `SF_GPS_REDIS_READ` | Liga leitura/escrita de GPS via Redis |

Worker: `cron/gps_redis_worker.php` (ver [10-cronjobs.md](10-cronjobs.md)).

## Fiscal / integrações

| Variável | Descrição |
|----------|-----------|
| `SF_PEF_CERT_PEM` | Certificado PEM para PEF/CIOT |
| `SF_MARKETING_SITE_URL` / `SF_SUPPORT_WHATSAPP` | Links institucionais |

Certificados fiscais A1 por empresa em `fiscal_certificados_digitais` /
`fiscal_config_empresa`. Ver [MANUAL_FISCAL_INDICE.md](MANUAL_FISCAL_INDICE.md).

## Servidor

- **Apache** com `mod_rewrite` (o projeto tem `.htaccess` na raiz e em subpastas).
- **`php.ini`:** `upload_max_filesize`/`post_max_size` adequados a anexos e XML fiscal;
  `max_execution_time` maior para importações e emissão fiscal; `date.timezone` BR.
- **`composer install`** obrigatório em produção (libs fiscais). Ver [PRODUCAO_COMPOSER_FISCAL.md](PRODUCAO_COMPOSER_FISCAL.md).
