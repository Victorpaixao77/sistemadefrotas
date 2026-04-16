# Gestão de Pneus – O que tem e o que dá para melhorar

Este documento descreve o que já existe no módulo de gestão de pneus e sugere melhorias.

---

## 1. O que já existe

### 1.1 Páginas no menu (sidebar)

| Página | Rota | Descrição |
|--------|------|------------|
| **Pneus** | `pages/pneus.php` | Cadastro e listagem de pneus |
| **Estoque de Pneus** | `pages/estoque_pneus.php` | Visão de pneus em estoque |
| **Manutenção de Pneus** | `pages/manutencao_pneus.php` | Registro e listagem de manutenções de pneus |
| **Gestão Interativa de Pneus** | `pages/gestao_interativa.php` | Dashboard por veículo, alocação e posições |

Permissão no sistema: `pode_acessar_gestao_pneus` (em `includes/permissions.php`).

---

### 1.2 Página Pneus (`pages/pneus.php`)

**Interface:**
- Cabeçalho com título e botões: **Novo Pneu**, Filtros, Exportar, Ajuda.
- **4 KPIs:** Total de Pneus, Pneus em Uso, Vida Útil (%), Pneus em Alerta (valores carregados via JS de `get_tire_metrics.php`).
- **Filtros:** busca (texto), status (select), veículo (select). Botões Aplicar e Limpar.
- **Tabela:** Número de Série, Marca/Modelo, DOT, Veículo, Posição, Data Instalação, KM Instalação, Status, Ações (Ver, Editar, Excluir).
- **Paginação:** Anterior / Próximo, texto “Página X de Y”.
- **Modal único** para Adicionar / Editar / Visualizar: formulário com número de série, marca, modelo, DOT, KM instalação, data instalação, vida útil (km), status, medida, sulco inicial, número recapagens, data última recapagem, lote, data entrada, observações.

**Backend / JS:**
- Listagem: `includes/get_tires.php?page=&status=&veiculo=&search=` (parâmetros enviados pelo JS).
- Métricas: `includes/get_tire_metrics.php`.
- Filtros (opções): `includes/get_tire_data.php?type=status` e `?type=veiculos`.
- Salvar: `includes/save_tire.php` (POST JSON).
- Excluir: `includes/delete_tire.php`.
- Busca com debounce; paginação via `loadTires(page)`.

**Problemas atuais:**
- `get_tires.php` **não usa** os parâmetros `status`, `veiculo` e `search` na query: a lista vem sempre só de `pneus` com paginação, sem filtros.
- A lista **não traz** veículo nem posição (não há JOIN com tabelas de alocação/instalação), então as colunas “Veículo” e “Posição” tendem a ficar vazias ou inconsistentes.
- KPIs “Vida Útil” e “Pneus em Alerta” estão fixos em `get_tire_metrics.php` (ex.: vida_media = 100, pneus_alerta = 0).

---

### 1.3 Estoque de Pneus (`pages/estoque_pneus.php`)

**Interface:**
- Listagem em PHP com `getEstoquePneus($page)` (10 por página).
- Tabela: número série, marca, modelo, medida, sulco inicial, recapagens, data última recapagem, lote, status, disponível, created_at/updated_at.
- Paginação por links (GET `?page=`).

**Backend:**
- Dados no próprio PHP: JOIN `pneus` + `status_pneus` + `estoque_pneus`.
- Sem API própria para a listagem da página; `api/estoque_pneus.php` existe para outras ações (`get_estoque`, `update_status`).

**Observações:**
- Uso de `error_log` em excesso na função de busca.
- Sem filtros (busca, status) na listagem.
- “Por página” fixo em 10.

---

### 1.4 Manutenção de Pneus (`pages/manutencao_pneus.php`)

**Interface:**
- **KPIs:** total de manutenções, custo total, manutenções no mês (com filtro opcional mês/ano).
- **Filtros:** mês e ano (aplicados aos KPIs).
- **Tabela de manutenções:** dados de `pneu_manutencao` + número do pneu, placa do veículo, tipo de manutenção.
- **Formulário** para nova manutenção: pneu, veículo, tipo, data, km, custo, descrição, etc.
- Paginação (ex.: 5 por página) e estilos em `maintenance.css`.

**Backend:**
- KPIs e lista no próprio PHP (`getConnection()`, queries em `pneu_manutencao`, `pneus`, `veiculos`, `tipo_manutencao_pneus`).
- **APIs:**  
  - `api/pneu_manutencao_data.php`: `action=view` (por id), `action=list` (todas).  
  - `api/pneu_manutencao_actions.php`: create/update/delete (conforme uso na página).

---

### 1.5 Gestão Interativa (`pages/gestao_interativa.php` + `gestao_interativa/`)

**Funcionalidades:**
- Dashboard com **veículos** e visualização de **eixos / posições de pneus**.
- **Alocação de pneus** a veículos/posições (tabelas como `instalacoes_pneus`, `alocacoes_pneus_flexiveis`, `eixo_pneus`).
- Uso de APIs em `gestao_interativa/api/`: `posicoes_pneus.php`, `eixos_veiculos.php`, `pneus_veiculo.php`, `pneus_disponiveis.php`, `salvar_alocacao_pneu.php`, `pneu_detalhes.php`, `historico_alocacoes.php`, `estatisticas_veiculo.php`, etc.
- Estrutura MVC em `gestao_interativa/src/` (Controllers, Models, Repositories) e views em `gestao_interativa/views/pneus/`.
- README do módulo com plano de refatoração (estruturação, backend, frontend, BD, testes).

**Observação:** Há referências a várias tabelas (ex.: `instalacoes_pneus`, `alocacoes_pneus_flexiveis`, `eixos`, `eixo_pneus`). A página principal de Pneus (`pneus.php`) não usa essa estrutura para preencher “Veículo” e “Posição”.

---

### 1.6 Outras APIs e includes

| Arquivo | Função |
|---------|--------|
| `api/pneus_data.php` | `get_pneus` por `veiculo_id`: pneus alocados ao veículo (eixo_pneus, eixos, veiculos, posicoes_pneus). |
| `api/deslocar_pneu.php` | Deslocamento de pneu (conforme uso no sistema). |
| `api/pneus_disponiveis.php` | Lista de pneus disponíveis. |
| `api/estoque_pneus.php` | `get_estoque` (com filtro status), `update_status`. |
| `includes/get_tire_data.php` | Retorna listas para selects: status, posições (e tipo “veiculos” para filtro). |
| `includes/get_tire_metrics.php` | Total de pneus, total em uso, vida média (fixa), alertas (fixo 0), custo total estimado. |
| `includes/get_tires.php` | Lista paginada de pneus (sem filtros) ou um pneu por `id`. |
| `includes/save_tire.php` | Create/update de pneu (JSON). |
| `includes/delete_tire.php` | Exclusão por id (com verificação empresa_id). |

---

### 1.7 IA e notificações

- **IA:** `IA/recomendacao_pneus.php`, `IA/notificacao_pneu.php` (integração com regras e notificações).
- **Pneus no relatório:** `api/pneu_manutencao_analytics.php` para analytics de manutenção de pneus.
- **Lucratividade:** custos de manutenção de pneus considerados em `includes/lucratividade_funcoes.php` (ex.: `total_pneu_manutencao`).

---

### 1.8 Front-end (JS/CSS) específicos

- **Pneus:** lógica na própria `pneus.php` (inline): `loadTires`, `loadFilters`, `loadDashboardMetrics`, `saveTire`, `deleteTire`, modais, paginação.
- **Gestão interativa:** `gestao_interativa/assets/js/gestao-pneus.js`, `flex-dashboard.js`, `flex-filters.js`; CSS em `gestao_interativa/assets/css/`.
- **IA pneus:** `js/ia_pneus.js`, `js/ia_pneus_avancado.js`; `css/pneus_ia.css`, `css/ia_pneus_avancado.css`.
- **Legenda:** `css/legenda_pneus.css`.

---

## 2. O que dá para melhorar

### 2.1 Página Pneus (`pneus.php`)

- **Filtros no backend:** Em `includes/get_tires.php` (ou em uma API única tipo `api/pneus_data.php`), aplicar `status`, `veiculo` e `search` na query (ex.: WHERE por status_id, JOIN com alocação/veículo, LIKE em número_serie/marca/modelo).
- **Veículo e posição na lista:** Fazer JOIN com a estrutura de alocação/instalação usada no sistema (ex.: `eixo_pneus` + `eixos` + `veiculos` e `posicoes_pneus`, ou `instalacoes_pneus`) e retornar `veiculo_placa` e `posicao_nome` na listagem.
- **Unificar fonte de dados:** Ter uma única API (ex.: `api/pneus_data.php`) para listagem + summary (totais para os KPIs), e a página só consumir essa API (como em veículos/motoristas).
- **KPIs reais:** Em `get_tire_metrics.php` (ou na API unificada): calcular vida útil média a partir de sulco/vida_util_km e definir regra de “em alerta” (ex.: sulco &lt; X mm ou vencimento DOT).
- **Validação:** Validar DOT (ex.: formato semanal/ano), número de série (obrigatório e único por empresa), sulco inicial (faixa razoável) no front e no backend.
- **Exportar:** Implementar o botão “Exportar” (CSV/Excel) usando a mesma listagem (com filtros) da API.
- **Feedback:** Trocar `alert()` por toasts ou mensagens na página; loading nos botões Salvar/Excluir.

---

### 2.2 Estoque de Pneus (`estoque_pneus.php`)

- **Busca e filtros no backend:** Adicionar parâmetros de busca (número série, marca, modelo) e filtro por status na query e, se possível, em uma API dedicada.
- **Paginação configurável:** “Por página” (5, 10, 25, 50) e persistir na URL (ex.: `?per_page=25&page=1`).
- **Reduzir logs:** Remover ou condicionar `error_log` da função de listagem para produção.
- **Consistência de tabelas:** Revisar se a lista deve usar `estoque_pneus` + `pneus_alocacao` de forma alinhada com `api/estoque_pneus.php` e com a gestão interativa.

---

### 2.3 Manutenção de Pneus (`manutencao_pneus.php`)

- **Paginação na lista:** Aplicar LIMIT/OFFSET na listagem de manutenções (no PHP ou via API) em vez de carregar todas.
- **Filtros na listagem:** Além de mês/ano nos KPIs, permitir filtrar a tabela por período, tipo, veículo ou pneu (enviar para backend).
- **API única:** Ter um endpoint único para listagem (com paginação e filtros) e outro para KPIs, para poder reutilizar no front (ex.: recarregar só a tabela sem recarregar a página).

---

### 2.4 Gestão Interativa

- **Documentar fluxo:** Explicar no README ou em outro doc como funciona a alocação (instalação vs flexível) e quais tabelas são a “fonte da verdade” para “pneu no veículo X na posição Y”.
- **Refatoração:** Seguir o plano do `gestao_interativa/README.md` (estrutura MVC, serviços, validações, front modular, migrações, testes).
- **Integração com Pneus:** Garantir que a listagem de `pneus.php` use a mesma base (ex.: mesma visão “pneu alocado em qual veículo/posição”) que a gestão interativa, para evitar duas fontes de verdade.

---

### 2.5 APIs e includes

- **get_tires.php:** Implementar filtros (search, status, veiculo) e JOINs para veículo/posição; ou deprecar em favor de `api/pneus_data.php` com ação `list` + `summary`.
- **get_tire_metrics.php:** Remover `display_errors` e reduzir `error_log` em produção; retornar métricas calculadas (vida útil média, quantidade em alerta).
- **save_tire.php:** Validar DOT e sulco; retornar JSON padronizado (ex.: `{ success, message, id }`).
- **Padronização:** Respostas de erro em formato único (ex.: `{ success: false, error: "mensagem" }`) e uso de `require_authentication()` e `empresa_id` em todos os endpoints de pneus.

---

### 2.6 Banco de dados

- **Documentar modelo:** Criar um doc (ou diagrama) com tabelas principais: `pneus`, `status_pneus`, `posicoes_pneus`, `estoque_pneus`, `eixos`, `eixo_pneus`, `instalacoes_pneus`, `alocacoes_pneus_flexiveis`, `pneu_manutencao`, `tipo_manutencao_pneus`, e relacionamentos.
- **Índices:** Verificar índices em `empresa_id`, `status_id`, `pneu_id`, `veiculo_id`, `data_manutencao`, etc., para listagens e filtros.
- **Migrações:** Se o projeto adotar migrações, ter scripts para criar/alterar tabelas de pneus versionados.

---

### 2.7 UX e consistência

- **Modais:** Padronizar abertura/fechamento (ex.: classe `active`, `closeModal` único) para evitar problemas como em motoristas/veículos (overlay bloqueando cliques).
- **Permissões:** Garantir que todas as páginas e APIs de pneus checam `pode_acessar_gestao_pneus` antes de exibir dados ou executar ações.
- **Acessibilidade:** Labels, contraste e foco em formulários e tabelas; mensagens de erro associadas aos campos quando possível.

---

### 2.8 Testes e documentação

- **Checklist manual:** Fluxos principais (cadastrar pneu, editar, excluir, filtrar, estoque, manutenção, alocação na gestão interativa).
- **README de pneus:** Resumir em um único README (ou seção em doc geral) as telas, APIs principais e onde estão filtros, KPIs e exportação.

---

## 3. Resumo rápido

| Área | O que tem | Melhoria principal |
|------|-----------|---------------------|
| **pneus.php** | Listagem, KPIs, filtros (front), modal add/edit/view, save/delete | Aplicar filtros no backend e retornar veículo/posição; KPIs reais; API unificada |
| **estoque_pneus.php** | Listagem PHP, paginação 10 | Filtros e busca no backend; per_page configurável |
| **manutencao_pneus.php** | KPIs, lista, formulário, filtro mês/ano | Paginação na lista; filtros na API; um endpoint para list + um para KPIs |
| **gestao_interativa** | Dashboard, alocação, várias APIs | Documentar fluxo; alinhar com pneus.php; refatoração do README |
| **APIs/includes** | get_tires, get_tire_metrics, save_tire, delete_tire, estoque, pneu_manutencao | Filtros em get_tires; métricas reais; validações; menos logs em produção |

Se quiser, posso detalhar a implementação de alguma dessas melhorias (ex.: filtros e veículo/posição em `get_tires` ou API unificada de pneus).

---

## 4. Visão e ordem de evolução

Para uma visão estratégica (onde estamos fortes, o que empresas grandes fazem, melhorias estruturais e ordem recomendada de implementação), ver **`docs/GESTAO_PNEUS_ROADMAP.md`**.
