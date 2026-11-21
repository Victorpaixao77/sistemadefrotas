[← Índice](README.md)

# A3 · Permissões e Perfis

Regras em `includes/permissions.php` (funções `can_*`) + colunas granulares em `usuarios`.
Menu em `includes/sidebar_pages.php`. Base: [PERMISSOES.md](PERMISSOES.md).

## Perfis base (real: `usuarios.tipo_usuario`)

`enum('admin','gestor','motorista')` + flag `acesso_todas_empresas` (tinyint) + permissões
granulares por função `can_*`. Suporte pode impersonar (auditado em `adm_auditoria_suporte`;
`sair_suporte.php` encerra). O **cliente/embarcador** não tem login — acessa só o
rastreio público por token.

| Perfil | Escopo |
|--------|--------|
| **admin** | Gestão completa da empresa; usuários; configurações sensíveis |
| **gestor** | Operação/frota/financeiro/fiscal conforme permissões `can_*` |
| **motorista** | App Android + área do motorista (não o painel de gestão) |
| **acesso_todas_empresas** | Escolhe empresa em `selecionar_empresa.php` (multi-tenant) |
| **suporte (impersonação)** | Acesso temporário auditado |
| **cliente** | Só `rastreio.php` por token (sem login) |

## Matriz de permissões (funções `can_*` reais)

| Função | Libera |
|--------|--------|
| `can_edit_system_users()` / `can_create_system_users()` | Editar / criar usuários |
| `can_edit_motoristas()` / `can_create_motoristas()` | Cadastro de motoristas |
| `can_access_lucratividade()` | Tela Lucratividade |
| `can_access_advanced_reports()` | Relatórios avançados / BI |
| `can_manage_system_settings()` | Configurações sensíveis, debug de APIs |
| `can_approve_refuels()` | Aprovar abastecimentos |
| `can_view_financial_data()` | Dados financeiros (valores R$) |
| `can_access_fiscal_system()` | Módulo fiscal (NF-e/CT-e/MDF-e/CIOT) |
| `can_access_tire_management()` | Gestão de pneus |
| `can_view_fleet_map()` | Mapa da frota (GPS) |

> **Nota (não inventar):** a associação exata **perfil → cada `can_*`** é resolvida em
> `includes/permissions.php` por colunas granulares no usuário; este documento lista as
> funções reais. Para o mapa perfil-a-função definitivo, ler `includes/permissions.php`.

## Como aplicar numa tela nova

1. `require_authentication()` (+ `require_permission(...)` quando aplicável).
2. Exibir no menu só com o `if (can_...())` correspondente em `sidebar_pages.php`.
3. Mesma chave em `require_permission` e na UI.
4. Na API, checar a mesma permissão antes de retornar dados sensíveis.

## App / APIs

Permissões do app em [`../app_android/docs/ENDPOINTS.md`](../app_android/docs/ENDPOINTS.md).
O painel web compartilha sessão + `empresa_id`; as APIs em `api/` aplicam regras equivalentes.

## Auditoria de acesso

`log_acessos`/`logs_acesso` (login, troca de empresa, ações), `adm_auditoria_suporte`
(impersonação), `motoristas_log`, `fiscal_logs`. Ver [11-seguranca.md](11-seguranca.md).
