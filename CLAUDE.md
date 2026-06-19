# Sistema de Frotas (Frotec)

Sistema web de gestão de frotas (multi-empresa) em **PHP + MySQL**, com app Android
(Kotlin/Compose) para motoristas. Rodando sobre XAMPP (Apache + MySQL na porta **3307**).

## Arquitetura

- **Entrada web:** `index.php` (dashboard), `login.php`, `logout.php`, `selecionar_empresa.php`.
- **`pages/`** — telas do gestor (veículos, rotas, abastecimentos, manutenções, BI, etc.).
- **`pages_motorista/`** — área do motorista. **`pages_adm/`** — administração interna.
- **`api/`** — endpoints JSON consumidos pelo front e pelo app Android.
- **`includes/`** — núcleo compartilhado (config, conexão, auth, csrf, helpers de domínio).
- **`js/`, `css/`** — assets do front (sem build step; versionados via `sf_asset_v()`).
- **`fiscal/`** — emissão fiscal (NFe/CTe/MDFe) via libs `nfephp-org/*` (Composer).
- **`app_android/`** — app do motorista (Kotlin) + sua própria API em `app_android/api/`.
- **`cron/`, `relatorios_automaticos/`** — jobs agendados (GPS, relatórios).

## Convenções importantes

- **Banco:** sempre via `getConnection()` (`includes/db_connect.php`) → PDO com
  `ERRMODE_EXCEPTION` e `EMULATE_PREPARES=false`. **Use sempre prepared statements
  com placeholders nomeados** — nunca concatene entrada do usuário no SQL.
- **Multi-empresa:** toda query de dados deve filtrar por `empresa_id`
  (`$_SESSION['empresa_id']`). É o limite de isolamento entre clientes — não esquecer
  em nenhum `SELECT/UPDATE/DELETE`.
- **Autenticação:** `require_authentication()` (`includes/config.php`) no topo de páginas
  e APIs. Sessão configurada por `configure_session()`. "Lembrar-me" usa token seguro
  selector:validator (`includes/remember_token.php`, tabela `auth_remember_tokens`) —
  **não** voltar ao cookie base64(id:email).
- **CSRF:** formulários usam `csrf_token_get()`/`csrf_token_validate()`; APIs que mudam
  estado chamam `api_require_csrf_json()`.
- **Erros:** nunca expor `$e->getMessage()` ao usuário. Registrar via `error_log()` e
  retornar mensagem genérica. Em APIs, devolver `success:false` + HTTP apropriado —
  **não fabricar dados de exemplo** em blocos `catch`.
- **Config/segredos:** credenciais de banco e chaves (OpenAI/DeepSeek) vêm de variáveis
  de ambiente (`SF_DB_*`, `SF_OPENAI_API_KEY`, etc.), com fallback local. Não commitar segredos.
- **Resposta JSON:** `json_response($data, $status)` ou `echo json_encode(...)` com
  `Content-Type: application/json`.

## Segurança (includes/security.php)

Biblioteca central `includes/security.php` reúne: política por empresa
(`configuracoes_seguranca`), 2FA TOTP (`sf_totp_*`), senha forte
(`sf_password_validate`), bloqueio de tentativas (`login_attempts`), geo-bloqueio,
whitelist de IP admin (`admin_ip_whitelist`), novo acesso (`acessos_conhecidos`) e
SMTP nativo (`sf_smtp_send`). Schema em `sql/security_features.sql`.

- **Política por empresa:** `sf_security_config($empresaId)` (cria a linha se faltar).
- **2FA por usuário:** colunas `two_factor_*` em `usuarios`; setup/confirm só do próprio
  usuário (página Usuários). Login exige código quando ativo e confirmado.
- **Logout por inatividade:** `sf_enforce_idle_timeout()` em `require_authentication()`;
  tempo cacheado em `$_SESSION['idle_timeout_min']`.
- **Segredos** (reCAPTCHA secret, SMTP pass): env primeiro (`SF_RECAPTCHA_*`,
  `SF_SMTP_PASS`) via `sf_security_secret()`, fallback ao banco. reCAPTCHA no login
  compartilhado usa env. Nunca devolver segredos por rede (mascarar com `********`).
- **UIs:** Configurações → card "Segurança" (`api/seguranca.php` + `js/seguranca.js`);
  Usuários → coluna/modal 2FA (`api/usuarios_2fa.php`).

## Banco de dados

- Migrações/seeds em **`sql/`** (arquivos `.sql` aplicados manualmente).
- Aplicar uma migração:
  `mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/arquivo.sql`

## Validação

- Lint PHP: `php -l caminho/arquivo.php` (`C:\xampp\php\php.exe`).
- Não há suíte de testes automatizada; validar mudanças manualmente no fluxo afetado.

## Não versionar (ver `.gitignore`)

`vendor/` (rodar `composer install`), `logs/`, `cache/`, `tmp/`, `sessions/`, `uploads/`.
