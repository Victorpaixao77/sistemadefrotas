[← Índice](README.md)

# 11 · Segurança

Biblioteca central: `includes/security.php`. Schema: `sql/security_features.sql`.
Ver também [PERMISSOES.md](PERMISSOES.md).

## Autenticação

- `require_authentication()` (`includes/config.php`) protege páginas e APIs.
- Senha com **hash** seguro (`password_hash`); política de senha forte `sf_password_validate`.
- **2FA TOTP** por usuário (colunas `two_factor_*` em `usuarios`; UI em Usuários,
  `api/usuarios_2fa.php`). Login exige código quando ativo e confirmado.
- **"Lembrar-me"** por token **selector:validator** (`auth_remember_tokens`,
  `includes/remember_token.php`) — nunca cookie reversível.
- **Bloqueio de tentativas** (`login_attempts`) + reCAPTCHA após N tentativas
  (`SF_RECAPTCHA_APOS_TENTATIVAS`).

## Autorização e multi-empresa

- `empresa_id` isola cada tenant em **todas** as queries — é o principal controle de acesso
  a dados. Perfis/permissões por usuário; suporte pode impersonar (auditado em `adm_auditoria_suporte`).
- **Novo acesso** (dispositivo/IP desconhecido) → e-mail/notificação (`acessos_conhecidos`).
- **Geo-bloqueio** e **whitelist de IP admin** (`admin_ip_whitelist`) por empresa
  (`configuracoes_seguranca`, `sf_security_config($empresaId)`).

## CSRF / XSS / SQL Injection

| Ameaça | Defesa |
|--------|--------|
| **CSRF** | `csrf_token_get()`/`csrf_token_validate()`; APIs `api_require_csrf_json()` em toda ação de escrita |
| **SQL Injection** | 100% prepared statements com placeholders nomeados; `EMULATE_PREPARES=false` |
| **XSS** | `htmlspecialchars()` na saída; no JS, escapar via `textContent` antes de injetar |
| **Vazamento de erro** | Nunca exibir `$e->getMessage()`; `error_log()` + mensagem genérica |

## Sessão

- `configure_session()`: cookie seguro, `SameSite`, path controlado (`SF_SESSION_PATH`).
- Logout por inatividade `sf_enforce_idle_timeout()`.

## Segredos

- **Env primeiro** (`SF_RECAPTCHA_*`, `SF_SMTP_PASS`, chaves de IA), fallback ao banco via
  `sf_security_secret()`. **Nunca devolver segredo por rede** (mascarar `********`).

## Uploads

- `uploads/` não é versionado. Validar tipo/tamanho; servir anexos por endpoint controlado
  (`api/download_anexo.php`) com checagem de `empresa_id`, não por link direto adivinhável.

## Auditoria / logs

- `log_acessos`/`logs_acesso` (login, troca de empresa, ações sensíveis), `fiscal_logs`,
  `adm_auditoria_suporte`, `motoristas_log`, `adm_sql_log`. Ver [16-troubleshooting.md](16-troubleshooting.md).

## Rate limit

- Login: bloqueio por tentativas. **Pendência:** não há rate-limit global nas APIs JSON —
  ver [99-validacao-final.md](99-validacao-final.md).
