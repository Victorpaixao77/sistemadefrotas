[← Telas](README.md) · [← Índice](../README.md)

# Nome da tela

> Copie este arquivo para `docs/14-telas/<pagina>.md` e preencha. Mantenha as seções.

## Descrição
Breve descrição do que a tela mostra.

## Objetivo
Para que serve (resultado de negócio).

## Quem utiliza
Perfis (gestor, motorista, adm…).

## Permissões
Quem pode ver/editar; checagens de perfil e `empresa_id`.

## Fluxo completo
Passo a passo do uso (com link para [15-fluxos](../15-fluxos/README.md) se houver diagrama).

## Campos
| Campo | Tipo | Obrigatório | Máscara | Validação | Padrão | Coluna no banco | Obs. |
|-------|------|-------------|---------|-----------|--------|-----------------|------|
| … | text | sim | — | não vazio | — | `tabela.coluna` | — |

## Botões
| Botão | O que faz ao clicar | API/ação |
|-------|---------------------|----------|
| Novo | Abre modal de criação | — |
| Salvar | `action=save` + CSRF | `api/….php` |

## Filtros
Lista de filtros (busca, status, período, por página…).

## Validações
Regras de validação (front e back).

## Regras de negócio
Regras específicas desta tela (link para [06-regras-de-negocio.md](../06-regras-de-negocio.md)).

## Consultas SQL utilizadas
Principais SELECT/INSERT/UPDATE (resumo).

## Arquivos envolvidos
- Página: `pages/….php`
- API: `api/….php`
- JS: `js/….js` · CSS: `css/….css`
- Includes/helpers: …
- Tabelas: …

## APIs chamadas / retorno esperado
| Endpoint | Método | Retorno |
|----------|--------|---------|
| `api/….php?action=list` | GET | `{"success":true,…}` |

## Possíveis erros e como corrigir
Ver [16-troubleshooting.md](../16-troubleshooting.md).

## Melhorias futuras
…

## Relacionamentos / dependências
Telas e tabelas relacionadas.
