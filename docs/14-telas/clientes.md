[← Telas](README.md) · [← Índice](../README.md)

# Clientes

## Descrição
Cadastro de **clientes / embarcadores** (tomadores de frete), com lista, busca, filtro por
status, resumo (KPIs) e paginação. Integra com as Ordens de Transporte.

## Objetivo
Manter a base de quem contrata frete, reutilizável ao abrir uma OT. A coluna **OTs** mostra
quantas ordens cada cliente já teve.

## Quem utiliza
Gestor / operação comercial.

## Permissões
Requer sessão autenticada; todos os dados filtrados por `empresa_id`. Escrita exige CSRF.

## Fluxo completo
1. Abrir → carrega a lista (`api/clientes.php?action=list`).
2. Buscar/filtrar por status ou "Novo cliente".
3. Salvar (modal) → `action=save` → recarrega. Editar/Excluir por linha.

## Campos (modal)
| Campo | Tipo | Obrigatório | Máscara | Validação | Padrão | Coluna | Obs |
|-------|------|:-----------:|---------|-----------|--------|--------|-----|
| Tipo pessoa | select PJ/PF | sim | — | PJ ou PF | PJ | `clientes.tipo_pessoa` | — |
| Razão social/Nome | text | **sim** | — | não vazio | — | `clientes.razao_social` | valida no back |
| Nome fantasia | text | não | — | — | — | `clientes.nome_fantasia` | — |
| CNPJ/CPF | text | não | CNPJ/CPF | — | — | `clientes.cnpj_cpf` | autofill BrasilAPI possível |
| Inscrição estadual | text | não | — | — | — | `clientes.inscricao_estadual` | — |
| Contato / Telefone / E-mail | text/email | não | — | e-mail válido | — | `contato_nome`/`telefone`/`email` | — |
| Endereço / Cidade / UF | text | não | UF 2 letras | — | — | `endereco`/`cidade`/`uf` | — |
| Status | select | sim | — | ativo/prospect/inativo | ativo | `clientes.status` | — |
| Observações | textarea | não | — | — | — | `clientes.observacoes` | — |

## Botões
| Botão | O que faz | Ação |
|-------|-----------|------|
| **Novo cliente** | Abre modal em branco | `abrirCl()` |
| **Salvar** | Cria/atualiza | `POST action=save` + CSRF |
| **Editar** (linha) | Carrega dados no modal | `GET action=get&id=` |
| **Excluir** (linha) | Confirma e remove | `POST action=delete` + CSRF |
| **Ajuda** (`#helpBtn`) | Abre modal de ajuda | `sf_page_help` (chave `clientes`) |

## Filtros
Busca (nome/CNPJ), Status (todos/ativo/prospect/inativo), **Por página** (10/25/50/100).

## Validações
- Back: `razao_social` não pode ser vazio (`InvalidArgumentException` → mensagem ao usuário).
- Front: busca com debounce; paginação client-side sobre a lista carregada.

## Regras de negócio
Prospects viram ativos ao fechar a primeira OT (manter status atualizado). Ver
[06-regras-de-negocio.md](../06-regras-de-negocio.md).

## Consultas SQL
`SELECT … FROM clientes WHERE empresa_id=:e [AND status=…] [AND (razao_social/cnpj LIKE …)]`
com subquery de contagem de OTs; `INSERT`/`UPDATE`/`DELETE` por `id` + `empresa_id`.

## Arquivos envolvidos
- Página: `pages/clientes.php` · API: `api/clientes.php` · SQL: `sql/clientes.sql`
- CSS: `fornc-modern-page.css`, `sf-premium.css` · JS: inline + `theme.js`/`sidebar.js`
- Ajuda: `includes/page_help/09_tms_novos.php` (chave `clientes`)
- Tabela: `clientes` (ligada a `ordens_transporte`)

## APIs chamadas / retorno
| Endpoint | Método | Retorno |
|----------|--------|---------|
| `api/clientes.php?action=list` | GET | `{"success":true,"clientes":[…]}` |
| `api/clientes.php?action=get&id=` | GET | `{"success":true,"cliente":{…}}` |
| `api/clientes.php` (save/delete) | POST | `{"success":true}` ou `{"success":false,"error":"…"}` |

## Possíveis erros
"Informe a razão social" (validação); 401 (sessão). Ver [16-troubleshooting.md](../16-troubleshooting.md).

## Melhorias futuras
Máscara/validação de CNPJ no front; importação em lote; anexos por cliente.

## Relacionamentos
`clientes` → `ordens_transporte` (uma OT referencia um cliente). Consumida em
[ordens_transporte.md](ordens_transporte.md) e no Planejamento.
