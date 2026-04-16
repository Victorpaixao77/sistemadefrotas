# Mapa de permissões (painel web)

As regras ficam em `includes/permissions.php` (funções `can_*` e colunas granulares em `usuarios`). O menu lateral usa `includes/sidebar_pages.php`.

## Funções principais

| Função | Uso típico |
|--------|------------|
| `can_edit_system_users()` | Editar usuários do sistema |
| `can_create_system_users()` | Criar usuários |
| `can_edit_motoristas()` / `can_create_motoristas()` | Cadastro de motoristas |
| `can_access_lucratividade()` | Tela Lucratividade |
| `can_access_advanced_reports()` | Relatórios avançados / BI (header) |
| `can_manage_system_settings()` | Configurações sensíveis, debug APIs |
| `can_approve_refuels()` | Aprovar abastecimentos |
| `can_view_financial_data()` | Dados financeiros |
| `can_access_fiscal_system()` | Módulo fiscal |
| `can_access_tire_management()` | Gestão de pneus (menu) |

## Ao criar uma tela nova

1. Proteger com `require_authentication()` (e `require_permission(...)` quando aplicável).
2. Expor no menu apenas com o `if (can_...())` correspondente em `sidebar_pages.php`.
3. Manter a mesma chave em `require_permission` e na UI.

## App motorista / APIs

Permissões do app Android estão documentadas em `app_android/docs/ENDPOINTS.md`; o painel web compartilha sessão e empresa, com regras equivalentes nas APIs em `api/`.
