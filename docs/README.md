# 📚 Manual Oficial — Sistema de Frotas (Frotec)

Documentação de referência do sistema. Serve desenvolvedores, analistas, suporte,
implantação, QA e (quando necessário) clientes.

> **Como este manual está organizado:** os arquivos numerados abaixo são os documentos
> **transversais** (arquitetura, instalação, banco, segurança, etc.). As pastas
> `13-modulos/`, `14-telas/`, `15-fluxos/` e `16-troubleshooting/` detalham cada parte.
> Muitos módulos **já tinham documentação própria** em `docs/` (fiscal, BI, pneus, CIOT…)
> — este índice as incorpora em vez de duplicar. Veja a seção "Documentos legados".

---

## 🔢 Documentos transversais

| # | Documento | Conteúdo |
|---|-----------|----------|
| 01 | [Visão geral](01-visao-geral.md) | O que é o sistema, público, capacidades, stack |
| 02 | [Instalação](02-instalacao.md) | Ambiente, dependências, primeiro boot |
| 03 | [Configuração](03-configuracao.md) | Variáveis de ambiente, sessão, SMTP, segredos |
| 04 | [Estrutura do projeto](04-estrutura-do-projeto.md) | Mapa de pastas e responsabilidades |
| 05 | [Banco de dados](05-banco-de-dados.md) | 230 tabelas, views, triggers, procedures, ER |
| 06 | [Regras de negócio](06-regras-de-negocio.md) | Multi-empresa, frete, comissão, fiscal, GPS |
| 07 | [APIs](07-apis.md) | Convenções, autenticação, índice de endpoints |
| 08 | [Webhooks e integrações](08-webhooks.md) | SEFAZ, DENATRAN, provedores de IA, Maps |
| 10 | [Cron jobs](10-cronjobs.md) | As 6 rotinas agendadas, como testar |
| 11 | [Segurança](11-seguranca.md) | Auth, CSRF, 2FA, geo/IP, rate limit, auditoria |
| 12 | [Performance](12-performance.md) | Cache, N+1, índices, assets |
| 17 | [Manutenção / backup / deploy](17-manutencao.md) | Backup, restore, deploy, rollback |
| 20 | [Glossário](20-glossario.md) | Termos do domínio (frete, CT-e, CIOT, POD…) |
| 99 | [**Validação final**](99-validacao-final.md) | Cobertura, órfãos, código morto, bugs, score |

## 🏢 Documentos enterprise (A1–A6) + dicionário

| # | Documento | Conteúdo |
|---|-----------|----------|
| 05a | [Dicionário de dados](05a-dicionario-dados.md) | **230 tabelas + 149 FKs geradas do banco** (real) |
| 05b | [Tabelas por módulo](05b-tabelas-por-modulo.md) | **Finalidade de cada uma das 230 tabelas** (comentário oficial ou inferido) |
| A1 | [Arquitetura, dependências e eventos](A1-arquitetura.md) | Camadas, dependência entre módulos, eventos automáticos (Mermaid) |
| A2 | [Manual do desenvolvedor](A2-manual-desenvolvedor.md) | Criar página/API/CRUD/cron/integração |
| A3 | [Permissões e perfis](A3-permissoes.md) | admin/gestor/motorista + funções `can_*` |
| A4 | [Changelog e roadmap](A4-changelog-roadmap.md) | Histórico (git) + roadmap por módulo |
| A5 | [Checklists](A5-checklists.md) | Release, QA, segurança, performance, banco |
| A6 | [Auditoria da documentação](A6-auditoria-documentacao.md) | Antes/depois, nível, backlog |
| A7 | [Relatório de organização e limpeza](A7-relatorio-limpeza.md) | Arquivos removidos/mantidos, estatísticas, validação |
| 📕 | [**MANUAL_COMPLETO.md**](MANUAL_COMPLETO.md) | **Tudo consolidado em um único arquivo** |

## 📁 Pastas detalhadas

- **[13-modulos/](13-modulos/)** — um documento por módulo (TMS, App, Financeiro, Pneus, Fiscal…).
- **[14-telas/](14-telas/)** — um documento por tela (campos, botões, validações, SQL, APIs).
  Comece pelo [`_TEMPLATE.md`](14-telas/_TEMPLATE.md) e pelo [índice de telas](14-telas/README.md).
- **[15-fluxos/](15-fluxos/)** — fluxogramas Mermaid (login, viagem, fiscal, financeiro…).
- **[16-troubleshooting/](16-troubleshooting.md)** — erros comuns, causa e solução.

---

## 🧭 Números do sistema (levantados do projeto)

| Item | Quantidade |
|------|-----------:|
| Páginas do gestor (`pages/`) | 57 |
| Páginas admin interno (`pages_adm/`) | ~128 arquivos |
| Páginas do motorista web (`pages_motorista/`) | ~34 arquivos |
| Endpoints API web (`api/*.php`) | 136 |
| Endpoints API do app (`app_android/api/*.php`) | ~30 |
| Includes/núcleo (`includes/`) | 106 |
| Scripts JS (`js/`) | 54 · CSS (`css/`) | 31 |
| Migrações SQL (`sql/*.sql`) | 109 |
| Tabelas no banco | **230** |
| Views · Triggers · Procedures | 5 · 9 · 3 |
| Cron jobs (`cron/`) | 6 |
| App Android (`app_android/`) | ~6.667 arquivos (projeto Kotlin) |

## 🗂️ Documentos legados já existentes em `docs/` (fonte de verdade dos módulos)

Fiscal: [MANUAL_FISCAL_INDICE.md](MANUAL_FISCAL_INDICE.md), [MANUAL_NFE.md](MANUAL_NFE.md),
[MANUAL_CTE.md](MANUAL_CTE.md), [MANUAL_MDFE.md](MANUAL_MDFE.md),
[MANUAL_CIOT_ANTT.md](MANUAL_CIOT_ANTT.md), [PEF_CIOT_INTEGRACAO.md](PEF_CIOT_INTEGRACAO.md),
[DENATRAN_INTEGRACAO.md](DENATRAN_INTEGRACAO.md), [MDFE_FASE2.md](MDFE_FASE2.md),
[MDFE_FASE3.md](MDFE_FASE3.md), [CTE_MOC_V4_STATUS.md](CTE_MOC_V4_STATUS.md),
[PRODUCAO_COMPOSER_FISCAL.md](PRODUCAO_COMPOSER_FISCAL.md).

BI: [BI_LISTA_COMPLETA.md](BI_LISTA_COMPLETA.md),
[BI_FROTA_INVENTARIO_E_MELHORIAS.md](BI_FROTA_INVENTARIO_E_MELHORIAS.md),
[BI_ROADMAP_O_QUE_FALTA.md](BI_ROADMAP_O_QUE_FALTA.md).

Pneus: [GESTAO_PNEUS_ANALISE.md](GESTAO_PNEUS_ANALISE.md),
[GESTAO_PNEUS_TABELAS.md](GESTAO_PNEUS_TABELAS.md),
[GESTAO_PNEUS_MOVIMENTACOES.md](GESTAO_PNEUS_MOVIMENTACOES.md),
[GESTAO_PNEUS_ROADMAP.md](GESTAO_PNEUS_ROADMAP.md).

Outros: [ROTAS.md](ROTAS.md), [WEB_API.md](WEB_API.md), [PERMISSOES.md](PERMISSOES.md),
[PLANOS_MANUTENCAO.md](PLANOS_MANUTENCAO.md), [MANUTENCAO_ESTADO_ATUAL.md](MANUTENCAO_ESTADO_ATUAL.md),
[CRON_PRODUCAO.md](CRON_PRODUCAO.md), [IMPORTACAO_XML_PDF_ROTAS.md](IMPORTACAO_XML_PDF_ROTAS.md),
[gestao_interativa_pro.md](gestao_interativa_pro.md), [auditoria_enterprise.md](auditoria_enterprise.md),
[AUDITORIA_CONTROL_CENTER_ADM.md](AUDITORIA_CONTROL_CENTER_ADM.md).

App do motorista: [`app_android/DOCUMENTACAO_APP.md`](../app_android/DOCUMENTACAO_APP.md),
[`app_android/docs/ENDPOINTS.md`](../app_android/docs/ENDPOINTS.md).

Guia raiz para IA/dev: [`../CLAUDE.md`](../CLAUDE.md).

---

## ⚠️ Estado desta documentação

Este manual foi iniciado com **cobertura total dos documentos transversais** (01–20) e
**telas-exemplo completas**. A documentação exaustiva de **cada uma das ~220 telas** e
**cada uma das 230 tabelas** é um backlog grande e incremental — o que já está pronto e o
que falta está listado em [99-validacao-final.md](99-validacao-final.md). Use o
[`_TEMPLATE.md`](14-telas/_TEMPLATE.md) para preencher novas telas no mesmo padrão.
