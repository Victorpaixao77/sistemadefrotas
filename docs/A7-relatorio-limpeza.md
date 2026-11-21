[← Índice](README.md)

# A7 · Relatório de Organização e Limpeza

Análise completa do projeto + limpeza **segura e verificada**, mantendo o sistema **100%
funcional**. Data: 2026-07. Escopo analisado: todo o repositório exceto `app_android/`
(projeto Kotlin próprio), `vendor/`, `.git/`.

---

## ⚠️ Decisão de arquitetura (importante)

O pedido incluía **reorganizar as pastas** no padrão MVC (`/assets`, `/controllers`,
`/models`, `/services`, `/repositories`, `/middleware`…). **Isso NÃO foi executado** — de
forma consciente — porque:

- O projeto é **PHP flat, sem framework e sem autoload de aplicação**. Os arquivos se
  referenciam por `require`/`include` com **caminho relativo**, e o front por
  `<script src>`/`<link href>`/`fetch('api/x.php')` com **caminho literal**.
- Mover `css/`→`assets/css/` ou criar `/controllers`/`/models` exigiria **reescrever
  centenas de caminhos** em ~300 arquivos PHP + JS. Qualquer caminho esquecido quebra a tela.
- A regra **"nunca quebrar funcionalidades"** tem prioridade sobre a estética de pastas.

**Recomendação:** se quiser o restructure, fazer **incremental e opt-in**, uma pasta por
vez, com reescrita de caminhos e teste tela a tela — como um projeto à parte. A estrutura
atual está mapeada e padronizada em [04-estrutura-do-projeto.md](04-estrutura-do-projeto.md).

---

## 1. Arquivos removidos (11 · 1.917 linhas · ~109 KB)

Todos com **0 referências** (verificado por `grep` de `include`/`require`/`src`/`fetch`/
`href`/`action` em todo o código, exceto `app_android`). Histórico preservado no git
(`git checkout <commit> -- <arquivo>` restaura).

| Arquivo | Motivo | Refs |
|---------|--------|:----:|
| `fiscal/teste_nfephp.php` | Script de teste manual (nfephp) | 0 |
| `gestao_interativa/api/teste_alocacao.php` | API de teste | 0 |
| `gestao_interativa/gestao_interativa/scripts/test_session_fix.php` | Script de teste (vazio, 0 bytes) | 0 |
| `gestao_interativa/views/test.php` | View de teste | 0 |
| `sql/teste_conexao.php` | Script de teste de conexão | 0 |
| `js/adicionar_alerta_final.js` | JS morto (não carregado em nenhuma página) | 0 |
| `pages/dashboard_exemplos.php` | Página de exemplo | 0 |
| `pages/lucratividade_exemplo.php` | Página de exemplo | 0 |
| `pages/dashboard_premium_demo.php` | Página de demonstração | 0 |
| `pages/rotas_premium_demo.php` | Página de demonstração | 0 |
| `pages/bi_premium_demo.php` | Página de demonstração | 0 |

## 2. Arquivos reorganizados (movidos)

**Nenhum** — ver "Decisão de arquitetura" acima (mover quebraria caminhos relativos).

## 3. Arquivos renomeados

**Nenhum** — renomear páginas/APIs quebraria `include`/`fetch`/links. Casos com nome
"ruim" (ex.: `documentos_fiscais_v2.php`) estão **em uso** e renomear exigiria atualizar
todos os consumidores (marcados para revisão, item 4).

## 4. Arquivos suspeitos MANTIDOS (marcados para revisão)

Aparentam ser candidatos, mas foram **preservados** por terem uso ou risco:

| Arquivo | Por que foi mantido |
|---------|---------------------|
| `api/pneu_movimentacoes_debug.php` | **Em uso** — `pages/pneus.php` faz `fetch` dele (painel de debug de movimentações) |
| `api/rotas_google_maps_debug.php` | Só referenciado em docs; é **API** (regra: não mexer em API sem necessidade) → revisar |
| `pages/template.php` | Scaffold útil para criar novas telas (0 refs, mas serve de modelo) |
| `examples/*_import_modelo.{csv,json}` | **Em uso** — modelos baixados por `includes/refuel_import_modal.php` e `pages/manutencoes.php` |
| `fiscal/config/config_exemplo.php` | Exemplo de configuração (como `.env.example`) — referência de deploy |
| `fiscal/api/documentos_fiscais_v2.php` | **Ativo** — backend atual do wizard de CT-e |
| `sql/dados_exemplo_*.sql`, `sql/dados_teste_*.sql`, `sql/*_v2.sql`, `sql/*_exemplo.sql` | Seeds/migrações históricas (pasta `sql/` é aplicada manualmente; preservar) |
| `uploads/**/*teste*.png`, `uploads/certificados/*teste*.pfx`, `uploads/nfe_xml/exemplo_nfe.xml` | **Uploads** — podem estar referenciados em linhas do banco (ex.: `empresa_clientes.logo`). Regra: não apagar uploads sem validar o banco |
| `gestao_interativa/gestao_interativa/` (pasta duplo-aninhada) | Estrutura estranha, mas com arquivos usados — revisar migração com cuidado |

## 5. Achados adicionais (recomendações, sem ação automática)

- **`uploads/` (119 arqs) e `cache/` (4 arqs) estão versionados** apesar de constarem no
  `.gitignore` — foram commitados antes da regra. Para destrackear (sem apagar do disco):
  `git rm -r --cached uploads cache` (requer autorização — pode afetar quem depende do repo).
- **Duplicação de nomenclatura PT/EN** em APIs (`veiculos.php`×`vehicles.php`,
  `rotas.php`×`routes_data.php`) — padronizar exige auditar consumidores (ver [99](99-validacao-final.md)).
- **`log_acessos` × `logs_acesso`** (duas tabelas de log de acesso) — consolidar (requer DB).
- Handlers legados em `actions/`/`ajax/` — auditar `empresa_id`/CSRF e possível remoção.

## 6. Melhorias realizadas

- Limpeza de **11 arquivos mortos** (test/demo/JS não usado), 100% verificados.
- Comentário obsoleto removido em `css/bi-premium.css` (citava demo apagado).
- **`README.md` na raiz** criado (guia de entrada + estrutura + convenções).
- Estrutura, convenções e dependências **documentadas** (não havia visão de conjunto):
  [04](04-estrutura-do-projeto.md), [A2](A2-manual-desenvolvedor.md), [padrões-ui](13-modulos/padroes-ui.md).

## 7. Estatísticas finais

| Métrica | Valor |
|---------|------:|
| Arquivos removidos | **11** |
| Linhas removidas | **1.917** |
| Espaço liberado | **~109 KB** |
| Arquivos reorganizados (movidos) | 0 (por segurança) |
| Arquivos renomeados | 0 (por segurança) |
| Arquivos mantidos para revisão | 10 grupos |
| Referências quebradas introduzidas | **0** |
| Redução estimada de complexidade | baixa (~1% dos arquivos) — o ganho maior foi de **clareza/documentação**, não de volume |

## 8. Validação de funcionamento (pós-limpeza)

- Smoke test HTTP: `index`, `login`, `bi`, `pneus`, `lucratividade`, `manutencoes`,
  `routes`, `centro_operacoes`, `clientes`, `comissoes` → **302/200** (sem 500).
- Demo removido → **404** (corretamente ausente).
- `php -l` nas páginas vizinhas → **OK**.
- Confirmação final: nenhuma referência restante aos arquivos removidos no código.

**Conclusão:** o sistema permanece **100% funcional**. A limpeza foi conservadora e
verificada; o restructure de pastas e a padronização de nomes de APIs ficam como
**passos opt-in** por serem de alto risco num projeto sem framework.
