# Sistema de Frotas (Frotec)

Plataforma web **multi-empresa (SaaS)** de gestão de transporte de cargas (TMS + frota),
em **PHP 8.2 + MySQL**, com **app Android** (Kotlin/Compose) para os motoristas.

## 🚀 Início rápido

```bash
composer install                      # dependências (vendor/ não versionado)
# criar banco + aplicar migrações de sql/ (ver docs/02-instalacao.md)
# subir Apache + MySQL (XAMPP, MySQL na porta 3307)
# abrir http://localhost/sistema-frotas/login.php
```

Detalhes: [docs/02-instalacao.md](docs/02-instalacao.md) · Configuração (env `SF_*`): [docs/03-configuracao.md](docs/03-configuracao.md).

## 📚 Documentação

O **manual oficial** está em [`docs/`](docs/README.md):

- [Índice do manual](docs/README.md) · [Manual único consolidado](docs/MANUAL_COMPLETO.md)
- [Visão geral](docs/01-visao-geral.md) · [Estrutura do projeto](docs/04-estrutura-do-projeto.md)
- [Banco de dados](docs/05-banco-de-dados.md) · [Dicionário (230 tabelas)](docs/05a-dicionario-dados.md) · [Tabelas por módulo](docs/05b-tabelas-por-modulo.md)
- [APIs](docs/07-apis.md) · [Segurança](docs/11-seguranca.md) · [Arquitetura](docs/A1-arquitetura.md)
- [Manual do desenvolvedor](docs/A2-manual-desenvolvedor.md) · [Convenções / padrões de UI](docs/13-modulos/padroes-ui.md)

Guia para IA/dev no editor: [`CLAUDE.md`](CLAUDE.md).

## 🗂️ Estrutura (resumo)

| Pasta | Conteúdo |
|-------|----------|
| `pages/` | Telas do gestor · `pages_motorista/` motorista · `pages_adm/` plataforma |
| `api/` | Endpoints JSON (web + app) |
| `includes/` | Núcleo compartilhado (config, auth, csrf, helpers) |
| `fiscal/` | NF-e/CT-e/MDF-e/CIOT (libs `nfephp-org/*`) |
| `app_android/` | App do motorista (Kotlin) + API própria |
| `js/` `css/` | Assets (sem build; versionados por `sf_asset_v()`) |
| `sql/` | Migrações/seeds (aplicadas manualmente) |
| `cron/` | Jobs agendados |
| `docs/` | Documentação |

Mapa completo: [docs/04-estrutura-do-projeto.md](docs/04-estrutura-do-projeto.md).

## 🧭 Convenções essenciais

- Toda query filtra `empresa_id` (isolamento multi-empresa).
- Prepared statements sempre; `require_authentication()` + CSRF nas escritas.
- Nunca expor `$e->getMessage()`; nunca fabricar dado em `catch`.
- Assets sem build: subir `?v=` ao alterar CSS/JS.

Detalhe: [CLAUDE.md](CLAUDE.md) e [docs/06-regras-de-negocio.md](docs/06-regras-de-negocio.md).

## ⚠️ Não versionar

`vendor/`, `logs/`, `cache/`, `tmp/`, `sessions/`, `uploads/` (ver `.gitignore`).
