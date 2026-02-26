# Módulo Rotas – O que já tem e o que dá para melhorar

Documento de referência do módulo de **Rotas/Viagens**: o que já existe e o que ainda dá para melhorar.

---

## 1. O que já tem

### 1.1 Páginas e APIs

| Recurso | Arquivo | Descrição |
|--------|---------|-----------|
| Página principal | `pages/routes.php` | Listagem de rotas, KPIs, filtros, modais (novo/editar, detalhes, exclusão, filtro, ajuda, despesas, simulação), mapa |
| Dados (list/view/summary) | `api/route_data.php` | list (paginado, filtros), view (detalhes por ID), summary (últimos 30 dias) |
| Criar/atualizar/excluir | `api/route_actions.php` | add, update, delete, get_motoristas, get_veiculos, get_clientes, get_estados, get_cidades, etc. |
| Outros | `api/rotas/view.php`, `api/rotas/update.php`, `api/rotas/status.php`, `api/rotas_google_maps.php`, `api/rotas_mapa.php` | Visualização, atualização, status, integração mapa |

### 1.2 Funcionalidades na tela

- **Listagem:** tabela paginada (5, 10, 25, 50, 100 por página) com rotas aprovadas.
- **Filtros (API):** busca por texto (origem, destino, motorista, placa, modelo, ID, CPF, data), status (no prazo/atrasado), motorista, data.
- **Filtro por mês/ano:** modal com `input type="month"` para filtrar por período.
- **KPIs / Dashboard:** totais (rotas, no prazo, atrasadas, distância, frete, eficiência, % vazio) – dados dos últimos 30 dias.
- **Modais:**
  - **Novo/Editar rota** – formulário completo (veículo, motorista, origem/destino, datas, km, frete, despesas, comissão, etc.).
  - **Detalhes da rota** – visualização com abas (dados, despesas, lucratividade).
  - **Excluir** – confirmação antes de excluir.
  - **Filtro** – mês/ano.
  - **Ajuda** – texto de uso da tela.
  - **Despesas** – despesas da viagem.
  - **Simulação de rota** – simulação com mapa.
- **Mapa de rotas:** modal com mapa (canvas/Google) e filtros.
- **Botões:** Novo, Filtros, Exportar, Ajuda; na tabela: editar, ver detalhes, excluir, despesas, etc.
- **Fechar modal ao clicar fora:** em `js/routes.js`, `setupModals()` já fecha qualquer `.modal` ao clicar no overlay (`e.target === modal` → `closeAllModals()`). Modais são controlados com `style.display = 'block'/'none'`.

### 1.3 Regras de negócio (resumo)

- Apenas rotas com `status = 'aprovado'` na listagem e na API.
- Summary considera `data_saida` nos últimos 30 dias.
- Busca ampla: cidades origem/destino, motorista, placa, modelo, ID, CPF, data em vários formatos.

### 1.4 Infra

- Autenticação e `empresa_id` em sessão.
- PDO com prepared statements em `route_data.php` e `route_actions.php`.
- Paginação e filtros aplicados na API.

---

## 2. O que dá para melhorar

### 2.1 Funcionalidades

| Melhoria | Descrição |
|----------|-----------|
| **Exportar rotas em CSV** | Hoje existe botão "Exportar" na página, mas não há `api/route_export.php` (ou equivalente). Criar exportação CSV com os mesmos filtros da listagem (busca, motorista, data, status, mês/ano), no estilo de `refuel_export.php` e `vehicle_export.php`. |
| **Filtro por intervalo de datas** | Além do mês/ano, permitir "Data início" e "Data fim" no modal de filtros e na API (ex.: `date_from`, `date_to`). |
| **Persistir filtros na URL** | Colocar filtros atuais na query string (page, per_page, search, driver, date, status, month) para compartilhar link e manter estado ao atualizar, como em abastecimentos. |
| **Toasts em vez de alert()** | Usar o sistema global de toasts (`js/toast.js`) para sucesso/erro ao salvar, excluir ou exportar, em vez de `alert()`. |

### 2.2 UX e acessibilidade

| Melhoria | Descrição |
|----------|-----------|
| **Aria-labels em botões só-ícone** | Botões "Filtros", "Exportar", "Ajuda" e ações da tabela (editar, ver detalhes, excluir, despesas) com `aria-label` para leitores de tela. |
| **Loading da tabela** | Spinner ou skeleton enquanto a lista de rotas carrega (hoje a troca pode ser "seca"). |
| **Loading nos KPIs** | Estado de carregamento nos cards do dashboard enquanto o summary é buscado. |
| **Confirmar exclusão em modal** | Já existe modal de exclusão; garantir que o texto mostre rota (ex.: origem → destino + data) e que feche ao clicar fora (já coberto pelo `closeAllModals` no overlay). |

### 2.3 Código e desempenho

| Melhoria | Descrição |
|----------|-----------|
| **DEBUG_MODE nos logs** | Envolver `error_log` em `route_data.php` e `route_actions.php` em `if (defined('DEBUG_MODE') && DEBUG_MODE)`. |
| **Cache do summary** | Cache (arquivo ou outro) para `route_data.php?action=summary` por empresa + período, TTL curto (ex.: 2 min), como no summary de abastecimentos. |
| **Índices no banco** | Usar/confirmar índices em `rotas` (empresa_id, status, data_saida, veiculo_id, motorista_id) – já sugeridos em `sql/indices_desempenho.sql`. |

### 2.4 Documentação

| Melhoria | Descrição |
|----------|-----------|
| **README do módulo** | Detalhar em `docs/ROTAS.md` os endpoints, parâmetros de filtro e regras (este documento já é um começo). |
| **Changelog** | Registrar em `CHANGELOG.md` as mudanças do módulo rotas quando fizer melhorias. |

---

## 3. Checklist rápido (melhorias)

- [x] Exportação CSV de rotas (`api/route_export.php`) com filtros
- [x] Filtro por data início / data fim na tela e na API
- [x] Filtros na URL (leitura + replaceState)
- [x] Toasts em rotas (salvar, excluir, exportar, despesas, dashboard)
- [x] Aria-labels nos botões só-ícone e na tabela
- [x] Loading na tabela e nos KPIs (showLoading/hideLoading nos cards)
- [x] Fechar modal ao clicar fora (já implementado em `routes.js`)
- [x] Logs condicionados a DEBUG_MODE (`route_data.php`, `route_actions.php`)
- [x] Cache do summary (opcional, TTL 2 min em `route_data.php`)
- [x] Atualizar este documento conforme implementar

---

*Documento do projeto Sistema de Gestão de Frotas – Módulo Rotas.*
