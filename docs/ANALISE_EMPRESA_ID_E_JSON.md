# Análise: isolamento por empresa_id e padrão de respostas JSON

Documento gerado para correções dos itens **2.2** e **2.3** do `MELHORIAS_SISTEMA_VALIDACAO.md`.

---

## Parte 1 – Isolamento por `empresa_id`

### Objetivo
Garantir que **todas** as APIs que leem, alteram ou excluem dados **sempre** usem `empresa_id` da **sessão** e nunca confiem em `empresa_id` (ou IDs de recursos) vindos de parâmetros sem validar que o recurso pertence à empresa do usuário.

### Resumo do que foi analisado
- A maioria das APIs já usa `$empresa_id = $_SESSION['empresa_id']` e filtra com `WHERE empresa_id = :empresa_id`.
- Os pontos a corrigir são: **validar rota_id/rota antes de operar em despesas_viagem**, **usar prepared statements em lucratividade_analytics** (hoje concatena `empresa_id` na SQL) e **reforçar trocar_empresa** (opcional).

---

### Arquivos que DEVEM ser corrigidos

#### 1. `api/route_actions.php` – ação `save_expenses`
- **Problema:**  
  - O código usa `rota_id` do body (JSON) sem verificar se a rota pertence à empresa do usuário.  
  - `SELECT id FROM despesas_viagem WHERE rota_id = :rota_id` não filtra por `empresa_id`.  
  - `UPDATE despesas_viagem SET ... WHERE rota_id = :rota_id` também não usa `empresa_id`.
- **Risco:** Usuário da empresa A pode enviar um `rota_id` da empresa B e alterar ou criar despesas ligadas à rota da empresa B (inconsistência ou vazamento de dados).
- **Correção:**
  1. Antes de qualquer operação em `despesas_viagem`, validar:  
     `SELECT id FROM rotas WHERE id = :rota_id AND empresa_id = :empresa_id`.  
     Se não existir linha, retornar 404 / “Rota não encontrada”.
  2. No `SELECT` de despesas:  
     `WHERE rota_id = :rota_id AND empresa_id = :empresa_id`.
  3. No `UPDATE` de despesas:  
     `WHERE rota_id = :rota_id AND empresa_id = :empresa_id`.

#### 2. `api/despesas_viagem/update.php`
- **Problema:**  
  - Recebe `rota_id` do POST e não verifica se a rota pertence à empresa do usuário.  
  - `SELECT id FROM despesas_viagem WHERE rota_id = ?` não usa `empresa_id`.  
  - `UPDATE despesas_viagem SET ... WHERE rota_id=?` não usa `empresa_id`.
- **Risco:** Usuário pode alterar ou criar despesas para rota de outra empresa.
- **Correção:**
  1. Antes de qualquer operação:  
     `SELECT id FROM rotas WHERE id = :rota_id AND empresa_id = :empresa_id`.  
     Se não existir, retornar 403/404.
  2. No `SELECT` de despesas:  
     `WHERE rota_id = ? AND empresa_id = ?` (usar `$empresa_id` da sessão).
  3. No `UPDATE`:  
     `WHERE rota_id = ? AND empresa_id = ?`.

#### 3. `api/lucratividade_analytics.php`
- **Problema:**  
  Várias queries montam SQL concatenando `$_SESSION['empresa_id']` na string, por exemplo:  
  `WHERE empresa_id = " . $_SESSION['empresa_id'] . "`  
  em vez de usar prepared statements com `:empresa_id`.
- **Risco:**  
  Má prática e fragilidade (ex.: tipo da sessão, futuras mudanças). Risco de SQL injection se em algum momento o valor vier de entrada não validada.
- **Correção:**  
  Substituir **todas** as ocorrências por prepared statements com parâmetro `:empresa_id` (ou equivalente) e `bindParam` usando `$_SESSION['empresa_id']`. Não concatenar `empresa_id` na SQL.

#### 4. `api/rotas/view.php`
- **Problema:**  
  A rota é buscada com `r.empresa_id = ?` (correto). Porém, as despesas são buscadas só por `rota_id`:  
  `SELECT * FROM despesas_viagem WHERE rota_id = ?` (sem `empresa_id`).
- **Risco:** Baixo (a rota já foi validada), mas para defesa em profundidade e consistência o ideal é filtrar despesas por empresa.
- **Correção:**  
  Incluir `AND empresa_id = ?` na query de despesas_viagem e passar `$empresa_id` da sessão.

---

### Arquivo com comportamento especial (manter + documentar)

#### 5. `api/trocar_empresa.php`
- **Situação:**  
  É o único lugar onde `empresa_id` vem do request (`$_POST['empresa_id']`) de forma intencional, para “trocar” de empresa.  
  Só é acessível para usuários com `acesso_todas_empresas`; a empresa é validada como existente e ativa (`WHERE id = ? AND status = 'ativo'`).
- **Melhoria opcional (futuro):**  
  Quando existir tabela de “empresas permitidas por usuário”, validar se o `empresa_id` solicitado está nessa lista antes de atualizar a sessão.
- **Ação agora:**  
  Nenhuma alteração obrigatória; apenas documentar que `empresa_id` aqui vem do POST por design e que no futuro deve-se validar permissão por empresa.

---

### Arquivos revisados e OK (uso correto de `empresa_id` da sessão)

- `api/denatran_infracoes.php` – usa sessão e filtra por `empresa_id` nas configs/certificado.
- `api/configuracoes.php` – todas as operações usam `$empresa_id` da sessão.
- `api/refuel_data.php` – `empresa_id` da sessão; queries com `:empresa_id`; quando usa `id`, faz `AND a.empresa_id = :empresa_id`.
- `api/route_data.php` – quando usa `id`, faz `WHERE r.id = :id AND r.empresa_id = :empresa_id`.
- `api/motorist_data.php` – usa sessão e checagens `motoristas WHERE id = :id AND empresa_id = :empresa_id`.
- `api/vehicle_data.php` – `WHERE v.id = :id AND v.empresa_id = :empresa_id` e semelhantes.
- `api/contas_pagar_actions.php` – `WHERE id = ? AND empresa_id = ?` com `$_SESSION['empresa_id']`.
- `api/multas.php` – update/delete/get verificam `WHERE id = :id AND empresa_id = :empresa_id`.
- `api/rotas/update.php` – verifica rota com `id = ? AND empresa_id = ?` antes de atualizar.
- `api/financiamentos.php` – operações com `empresa_id` da sessão e `WHERE ... AND empresa_id = :empresa_id`.
- Demais APIs listadas na varredura usam `empresa_id` da sessão nas queries.

---

## Parte 2 – Padrão de respostas JSON (2.3)

### Objetivo
Padronizar respostas em todas as APIs no formato:

- **Sucesso:**  
  `{ "success": true, "message": "..." }`  
  e, quando fizer sentido, outros campos (ex.: `data`, `id`).
- **Erro:**  
  `{ "success": false, "message": "..." }`  
  e, opcionalmente, `"error"` (texto técnico) ou `"code"` (código de erro).

Assim o front pode sempre checar `success` e exibir `message` para o usuário.

---

### Arquivos que DEVEM ser ajustados (resposta de erro inconsistente)

| Arquivo | O que hoje usa em erro | Ajuste sugerido |
|--------|------------------------|------------------|
| `api/configuracoes.php` | `['error' => 'Não autorizado']` (linha ~13) | Usar `['success' => false, 'message' => 'Não autorizado']`. Manter `error` só se quiser detalhe técnico. |
| `api/configuracoes.php` | Várias respostas só com `'error' => ...` | Em respostas de falha: incluir `'success' => false` e `'message' => ...` (texto para usuário). Pode manter `error` com o mesmo valor ou para detalhe. |
| `api/trocar_empresa.php` | `['success' => false, 'error' => ...]` | Padronizar mensagem de erro em `message` (e opcionalmente manter `error`). |
| `api/log_acessos.php` | `['error' => 'Não autorizado']` | Trocar para `['success' => false, 'message' => 'Não autorizado']`. |
| `api/profit_per_km_analytics.php` | `['error' => 'Não autorizado']` | Trocar para `['success' => false, 'message' => 'Não autorizado']`. |
| `api/performance_indicators.php` | Já usa `success` + `error` em alguns pontos | Garantir que todo retorno de erro tenha `success: false` e `message` (e opcionalmente `error`). |
| `api/rotas/view.php` | `['error' => '...']` (401, 400, 404, 500) | Usar `['success' => false, 'message' => '...']` (e opcionalmente `error`). |
| `api/rotas/update.php` | `['error' => '...']` | Idem: `success: false` + `message`. |
| `api/despesas_viagem/update.php` | `['error' => '...']` | Idem: `success: false` + `message`. |
| `api/motorist_data.php` | `['error' => 'ID do motorista é obrigatório']` (e possíveis outros) | Incluir `'success' => false` e colocar texto em `message`. |
| `api/route_data.php` | Respostas com `'error'` | Garantir `success: false` e `message` em erros. |
| `api/refuel_data.php` | Algumas com `'error'` sem `success` | Padronizar erros com `success: false` e `message`. |

Regra prática para correção em qualquer API:

- Em **erro:** sempre devolver `success: false` e `message` (frase para o usuário).
- Opcional: manter um campo `error` com a mesma string ou com detalhe técnico (ex.: exception message em desenvolvimento).

---

### APIs já alinhadas ao padrão (exemplos)

- `api/denatran_infracoes.php` – usa `success` + `message` em sucesso e erro.
- `api/multas.php` – usa `success` e `message` nas respostas.
- Maior parte de `api/configuracoes.php` (sucesso) – já usa `success` e `message`.

---

## Ordem sugerida de correção

1. **Segurança (empresa_id)**  
   - `api/route_actions.php` (save_expenses)  
   - `api/despesas_viagem/update.php`  
   - `api/lucratividade_analytics.php` (prepared statements)  
   - `api/rotas/view.php` (empresa_id na query de despesas)

2. **Padrão JSON**  
   - Ajustar os arquivos da tabela acima para sempre retornar `success` e `message` em erros (e opcionalmente `error`).

3. **Documentação**  
   - Em `trocar_empresa.php` (comentário ou doc) explicando que `empresa_id` vem do POST por design e que no futuro se deve validar permissão por empresa.

Com isso, o isolamento por empresa fica consistente e as respostas JSON facilitam o tratamento único no front (evitando que um usuário veja dados de outra empresa e padronizando mensagens de erro).
