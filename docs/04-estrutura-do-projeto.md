[← Índice](README.md)

# 04 · Estrutura do Projeto

## Mapa de pastas (raiz)

| Pasta / arquivo | Responsabilidade |
|-----------------|------------------|
| `index.php` | Dashboard (tela inicial após login) |
| `login.php` · `logout.php` · `selecionar_empresa.php` | Autenticação e escolha de empresa |
| `recuperar_senha.php` · `reset_senha.php` | Recuperação de senha |
| `rastreio.php` | Rastreio **público** da carga por token (cliente) |
| `sair_suporte.php` | Encerrar sessão de suporte (impersonação) |
| **`pages/`** | Telas do **gestor** (57 páginas: rotas, veículos, financeiro, fiscal, BI…) |
| **`pages_motorista/`** | Área do motorista (web) |
| **`pages_adm/`** | **Administração da plataforma** (empresas, planos, cobrança, saúde) |
| **`api/`** | 136 endpoints JSON do front web e do app |
| **`includes/`** | Núcleo compartilhado (config, conexão, auth, csrf, helpers de domínio) |
| **`fiscal/`** | Emissão fiscal NFe/CTe/MDFe/CIOT (libs `nfephp-org/*`), tem `api/` e `pages/` próprios |
| **`app_android/`** | App Kotlin do motorista + `app_android/api/` (backend próprio do app) |
| **`bi/`** | Componentes/renderers de Business Intelligence |
| **`IA/`** | Painéis e APIs de IA (`IA/api/`, `IA/painel.php`) |
| **`google-maps/`** | Stack de mapas (maps.js, route-manager, geolocation, `api.php`) |
| **`integracoes/`** | Integrações externas |
| **`notificacoes/`** | Motor de notificações |
| **`calendario/`** | Calendário (vencimentos CNH/multas via triggers) |
| **`gestao_interativa/`** | Gestão interativa de pneus/frota (visual) |
| **`cron/`** | 6 jobs agendados (ver [10-cronjobs.md](10-cronjobs.md)) |
| **`relatorios_automaticos/`** | Geração de relatórios agendados |
| **`sql/`** | 109 migrações/seeds `.sql` (aplicadas manualmente) |
| **`js/` · `css/`** | Assets do front (sem build; versionados por `sf_asset_v()`) |
| **`design-system/`** | Tokens/base de design |
| **`site/`** | Site institucional / marketing |
| **`tools/`** | Scripts utilitários de manutenção/dev |
| **`actions/`** | Handlers de ações de formulário legadas |
| **`ajax/`** | Endpoints AJAX legados (ver nota abaixo) |
| **`examples/` · `*_demo.php`** | Páginas de exemplo/demo (ver "código não-produtivo") |
| **`vendor/`** | Dependências Composer (**não versionado** — rodar `composer install`) |
| `logs/` `cache/` `tmp/` `sessions/` `uploads/` | **Não versionados** (runtime; ver `.gitignore`) |

## Núcleo compartilhado (`includes/`) — os mais usados

| Arquivo | Papel |
|---------|-------|
| `config.php` | Bootstrap: erros, `require_authentication()`, `configure_session()` |
| `db_connect.php` | `getConnection()` → PDO |
| `functions.php` | Helpers gerais (`sf_app_url`, `sf_asset_v`, `json_response`…) |
| `csrf.php` | `csrf_token_get()` / `csrf_token_validate()` / `api_require_csrf_json()` |
| `security.php` | 2FA, senha forte, login_attempts, geo/IP, SMTP, política por empresa |
| `remember_token.php` | "Lembrar-me" seguro (selector:validator) |
| `sf_api_base.php` | Base JS de API (`sfApiUrl`, `window.__SF_*__`, `sf_render_api_scripts`) |
| `sf_antt_frete.php` | Piso ANTT (`sf_antt_calc_piso`, `sf_antt_tipos_carga`) |
| `sf_page_help.php` · `sf_page_help_bundle.php` | Sistema de ajuda por página |
| `sf_vision_ocr.php` | OCR de visão (IA) reutilizável |
| `sidebar_pages.php` · `header.php` · `footer.php` | Shell visual das páginas |
| `relatorios_helpers.php` | Allow-list e período de relatórios |

## Padrão de uma página do gestor

```
require_once '../includes/config.php';        // + functions, csrf, sf_page_help…
configure_session(); session_start(); require_authentication();
$page_title = '...';
// HTML: <body class="sf-premium"> → .app-container → sidebar_pages.php +
//       .main-content → header.php + .dashboard-content + footer.php
// scripts: jquery + theme.js + sidebar.js + (sf_page_help_js)
```

Padrões visuais (toolbar, tabela, paginação, ajuda) em
[13-modulos/padroes-ui.md](13-modulos/padroes-ui.md) e no [`../CLAUDE.md`](../CLAUDE.md).

## Observações de organização

- **`ajax/`, `actions/`** contêm handlers **legados**; parte da lógica migrou para `api/`.
  Candidatos a auditoria de código morto — ver [99-validacao-final.md](99-validacao-final.md).
- **Páginas `*_demo.php` / `examples/`** são demonstrações, **não** telas de produção
  (`bi_premium_demo.php`, `dashboard_premium_demo.php`, `rotas_premium_demo.php`,
  `lucratividade_exemplo.php`, `dashboard_exemplos.php`, `template.php`).
