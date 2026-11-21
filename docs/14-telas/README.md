[← Índice](../README.md)

# 14 · Telas (índice)

As **57 páginas do gestor** (`pages/`). Status: ✅ documentada · 🟡 parcial (no CLAUDE.md/doc
de módulo) · ⬜ pendente (usar [`_TEMPLATE.md`](_TEMPLATE.md)). Páginas `*_demo`/exemplo são
não-produtivas (ver [04](../04-estrutura-do-projeto.md) e [99](../99-validacao-final.md)).

## TMS / Operação
| Tela | Arquivo | Status | Doc |
|------|---------|:------:|-----|
| Dashboard | `index.php` | 🟡 | — |
| Centro de Operações | `centro_operacoes.php` | ✅ | [centro_operacoes.md](centro_operacoes.md) |
| Central de Alertas | `central_alertas.php` | 🟡 | CLAUDE.md |
| Centro de Inteligência | `assistente_ia.php` | ✅ | [assistente_ia.md](assistente_ia.md) |
| Ordens de Transporte | `ordens_transporte.php` | ✅ | [ordens_transporte.md](ordens_transporte.md) |
| Aprovações | `aprovacoes.php` | 🟡 | CLAUDE.md |
| Planejamento | `planejamento.php` | 🟡 | CLAUDE.md |
| Rotas | `routes.php` | 🟡 | [ROTAS.md](../ROTAS.md) |
| Simulador de Rota | `simulador_rota.php` | 🟡 | CLAUDE.md |
| Clientes | `clientes.php` | ✅ | [clientes.md](clientes.md) |
| Importar rotas (Excel) | `import_rotas_excel.php` | ⬜ | [IMPORTACAO_XML_PDF_ROTAS](../IMPORTACAO_XML_PDF_ROTAS.md) |

## Frota / Manutenção / Pneus
| Tela | Arquivo | Status | Doc |
|------|---------|:------:|-----|
| Veículos | `vehicles.php` | ⬜ | — |
| Implementos | `implementos.php` | 🟡 | CLAUDE.md |
| Abastecimentos | `abastecimentos.php` | ⬜ | — |
| Abastec. motoristas | `abastecimentos_motoristas.php` | ⬜ | — |
| Manutenções | `manutencoes.php` | ⬜ | [MANUTENCAO_ESTADO_ATUAL](../MANUTENCAO_ESTADO_ATUAL.md) |
| Planos de manutenção | `planos_manutencao.php` | ⬜ | [PLANOS_MANUTENCAO](../PLANOS_MANUTENCAO.md) |
| Manutenção preditiva | `manutencao_preditiva.php` | 🟡 | CLAUDE.md |
| Pneus | `pneus.php` | ⬜ | [GESTAO_PNEUS_ANALISE](../GESTAO_PNEUS_ANALISE.md) |
| Pneus (dashboard) | `pneus_dashboard.php` | ⬜ | idem |
| Pneus (inventário) | `pneus_inventario.php` | ⬜ | idem |
| Estoque de pneus | `estoque_pneus.php` | ⬜ | idem |
| Manutenção de pneus | `manutencao_pneus.php` | ⬜ | idem |
| Gestão interativa | `gestao_interativa.php` | ⬜ | — |
| Gestão interativa PRO | `gestao_interativa_pro.php` | 🟡 | [gestao_interativa_pro](../gestao_interativa_pro.md) |
| Ficha do pneu | `pneu_ficha.php` | ⬜ | — |

## Financeiro
| Tela | Arquivo | Status | Doc |
|------|---------|:------:|-----|
| Lucratividade | `lucratividade.php` | ✅ | [lucratividade.md](lucratividade.md) |
| Contas a pagar | `contas_pagar.php` | ✅ | [contas_pagar.md](contas_pagar.md) |
| Despesas fixas | `despesas_fixas.php` | ✅ | [despesas_fixas.md](despesas_fixas.md) |
| Financiamento | `financiamento.php` | ✅ | [financiamento.md](financiamento.md) |
| Multas | `multas.php` | ✅ | [multas.md](multas.md) |
| Comissões | `comissoes.php` | ✅ | [comissoes.md](comissoes.md) |
| Comissões (export) | `comissoes_export.php` | 🟡 | ver [comissoes.md](comissoes.md) |

## Motoristas / GPS / BI / IA
| Tela | Arquivo | Status |
|------|---------|:------:|
| Motoristas | `motorists.php` | ⬜ |
| Gestão de motoristas | `gestao_motoristas.php` | ⬜ |
| Indicadores de desempenho | `indicadores_desempenho.php` | ⬜ |
| Rotas dos motoristas | `rotas_motoristas.php` | ⬜ |
| Mapa da frota (GPS) | `mapa_frota.php` | ⬜ |
| Cercas GPS | `gps_cercas.php` | ⬜ |
| BI | `bi.php` | 🟡 [BI_LISTA_COMPLETA](../BI_LISTA_COMPLETA.md) |
| IA — Painel | `ia_painel.php` | ⬜ |
| IA — Dashboard | `ia_dashboard.php` | ⬜ |

## Cadastros / Sistema
| Tela | Arquivo | Status |
|------|---------|:------:|
| Fornecedores | `fornecedores.php` / `fornecedores_moderno.php` | ⬜ |
| Configurações | `configuracoes.php` | ⬜ |
| Empresa | `empresa.php` | ⬜ |
| Usuários | `usuarios.php` | 🟡 [PERMISSOES](../PERMISSOES.md) |
| Perfil | `perfil.php` | ⬜ |
| Notificações | `notificacoes.php` | ⬜ |
| Relatórios | `relatorios.php` | 🟡 CLAUDE.md |
| Log de acessos | `log_acessos.php` | ⬜ |
| Fiscal (hub) | `fiscal.php` | 🟡 [MANUAL_FISCAL_INDICE](../MANUAL_FISCAL_INDICE.md) |

## Não-produtivas (demo/template)
As páginas de demonstração/exemplo (`bi_premium_demo.php`, `dashboard_premium_demo.php`,
`rotas_premium_demo.php`, `lucratividade_exemplo.php`, `dashboard_exemplos.php`) foram
**removidas** na limpeza — ver [A7-relatorio-limpeza.md](../A7-relatorio-limpeza.md).
`pages/template.php` foi **mantido** como scaffold de referência.
