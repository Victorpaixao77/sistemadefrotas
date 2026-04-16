# API do painel web (REST interno)

## Base URL dinâmica

Não fixe `/sistema-frotas/...` no front-end. Use:

- **PHP:** `sf_app_url('api/nome.php')` e `require_once` de `includes/sf_paths.php` ou `includes/sf_api_base.php`.
- **JavaScript:** `sfApiUrl('nome.php')` e `sfAppUrl('caminho/relativo/ao/app')`, injetados por `sf_render_api_scripts()` em `includes/sf_api_base.php` (o `includes/header.php` já chama isso antes de `js/header.js`).

Variáveis globais: `window.__SF_API_BASE__`, `window.__SF_APP_BASE__`.

## Endpoints usados com frequência

| Área | Exemplo | Notas |
|------|---------|--------|
| Dashboard | `api/expenses_distribution.php`, `api/dashboard_data.php`, `api/financial_analytics.php` | Ver `index.php` / `js/dashboard.js` |
| Rotas | `api/*` via `js/routes.js` | Base com `sfApiUrl` |
| Fornecedores | `api/fornecedores.php` | Ver `js/fornecedores.js` |
| Fiscal | `fiscal/api/*.php` | Ver `fiscal/assets/js/fiscal.js` |
| Trocar empresa | `api/trocar_empresa.php` | Header |
| Notificações | `notificacoes/notificacoes.php` | Caminho de app com `sfAppUrl` |
| GPS (painel) | `api/gps_posicoes.php`, `api/gps_historico.php`, `api/gps_cercas.php` (GET/POST JSON + CSRF nas mutações), `api/gps_cerca_alertas.php` | Sessão web autenticada |

## App Android

Contrato e exemplos de URL: **`app_android/docs/ENDPOINTS.md`**. A base costuma ser `.../app_android/api/` (configurável no app).

## Respostas JSON

Preferir `{ "success": true|false, "message": "...", ... }` e códigos HTTP corretos (401 para não autenticado nas APIs).

Helpers em `includes/api_json.php`: `api_json_send()`, `api_json_unauthorized()`, `api_json_method_not_allowed()`, `api_json_error()`.

**Contas a pagar** (`api/contas_pagar_actions.php`): não autenticado → 401 + `{ success, message, error: "unauthorized" }`; exclusão → só **POST** com `id` + `csrf_token` (ou header `X-CSRF-Token`), `action=delete` na query.

## Produção / ambiente

- **`SF_DEBUG`**: `1` / `true` / `yes` ativa `DEBUG_MODE`, `error_reporting` completo e `sf_log_debug`.
- **`SF_SESSION_PATH`**: path do cookie de sessão (ex.: `/sistema-frotas`); se vazio, usa detecção por `DOCUMENT_ROOT` vs pasta do projeto (`includes/sf_paths.php` → `sf_session_cookie_path()`).
- **Assets**: `.htaccess` na raiz do app define `Cache-Control` para `css`/`js`/imagens. Subir `APP_VERSION` em `config.php` quando quiser política de versionamento em URLs.
