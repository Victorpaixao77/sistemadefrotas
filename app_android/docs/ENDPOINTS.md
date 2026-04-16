# Endpoints - API App Android Motoristas

Base URL exemplo: `http://SEU_HOST/sistema-frotas/app_android/api/`

Todas as respostas são JSON. Endpoints que exigem autenticação devem enviar:

```http
Authorization: Bearer <token>
```

---

## 1. Autenticação

### POST auth.php — Login

- **Body (JSON ou form):** `nome`, `senha` (ou `usuario`/`password`)
- **Resposta 200:**  
  `{ "success": true, "message": "...", "data": { "token", "motorista_id", "empresa_id", "nome", "expira_em" } }`
- **Erro 401:** usuário/senha inválidos

### GET auth.php — Dados do motorista (Me)

- **Headers:** `Authorization: Bearer <token>`
- **Resposta 200:**  
  `{ "success": true, "data": { "motorista_id", "empresa_id", "nome" } }`

### POST auth.php — Logout

- **Body:** `action=logout`
- **Headers:** `Authorization: Bearer <token>`
- **Resposta 200:** `{ "success": true, "message": "Logout realizado." }`

---

## 2. Dashboard

### GET dashboard.php

- **Headers:** `Authorization: Bearer <token>`
- **Resposta 200:**  
  `data`: `contadores` (rotas_pendentes, abastecimentos_pendentes, checklists_pendentes),  
  `rotas_hoje`, `ultimas_rotas`, `ultimos_abastecimentos`, `ultimos_checklists`

---

## 3. Rotas

### GET rotas.php

- **Query (opcional):** `status`, `data_inicio`, `data_fim`, `limite` (default 50, máx 100)
- **Resposta 200:** `data.rotas`: array de rotas com cidade_origem_nome, cidade_destino_nome, placa

### POST rotas.php — Criar rota

- **Body (JSON):**  
  Obrigatórios: `veiculo_id`, `cidade_origem_id`, `cidade_destino_id`, `estado_origem`, `estado_destino`, `data_saida`  
  Opcionais: `data_chegada`, `data_rota`, `km_saida`, `km_chegada`, `observacoes`, `peso_carga`, `descricao_carga`
- **Resposta 200:** `data.id` (ID da rota criada)

---

## 4. Abastecimentos

### GET abastecimentos.php

- **Query (opcional):** `limite` (default 50, máx 100)
- **Resposta 200:** `data.abastecimentos`: array com placa, modelo

### POST abastecimentos.php — Registrar abastecimento

- **Body (JSON):**  
  Obrigatórios: `veiculo_id`, `litros` (ou `quantidade`), `valor_litro` (ou `preco_litro`), `valor_total`, `km_atual`, `tipo_combustivel`  
  Opcionais: `rota_id`, `data_abastecimento`, `posto`, `forma_pagamento`, `observacoes`
- **Resposta 200:** `data.id`

---

## 5. Checklists (checklist_viagem)

### GET checklists.php

- **Query (opcional):** `limite` (default 50, máx 100)
- **Resposta 200:** `data.checklists`: array com placa, cidade_origem_nome, cidade_destino_nome

### POST checklists.php — Registrar checklist

- **Body (JSON):**  
  Obrigatórios: `rota_id`, `veiculo_id`  
  Opcionais: `data_checklist`, `observacoes` e todos os itens booleanos (oleo_motor, agua_radiador, fluido_freio, pneus, luzes, freios, triangulo, extintor, cnh, etc.) — enviar 1 ou 0.
- **Resposta 200:** `data.id`

---

## 6. Despesas de viagem

### GET despesas.php

- **Query obrigatória:** `rota_id`
- **Resposta 200:** `data.despesas`: array de despesas da rota

### POST despesas.php — Criar despesa

- **Body (JSON):** `rota_id`, `action`: "create", valores numéricos: `descarga`, `pedagios`, `caixinha`, `estacionamento`, `lavagem`, `borracharia`, `eletrica_mecanica`, `adiantamento`, `total` (ou `total_despviagem`)

### POST despesas.php — Atualizar despesa

- **Body (JSON):** `rota_id`, `action`: "update", `id` (da despesa), mesmos campos numéricos acima

---

## 7. Veículos

### GET veiculos.php

- **Resposta 200:** `data.veiculos`: array de `{ id, placa, modelo }`

---

## 7.1 GPS (rastreamento)

### POST gps_salvar.php

- **Headers:** `Authorization: Bearer <token>`
- **Body (JSON):** obrigatórios `veiculo_id`, `latitude`, `longitude`; opcionais `velocidade` (km/h), `data_hora` (`YYYY-MM-DD HH:MM:SS`). O `motorista_id` efetivo é o do token (se enviar outro no corpo, deve coincidir).
- **Resposta 200:** `data.id` (id do registro em `gps_logs`)
- **Nota:** execute `sql/create_gps_tracking.sql` no MySQL antes de usar.

### POST /api/gps/salvar.php (painel, mesmo contrato)

- Mesma lógica que `app_android/api/gps_salvar.php`, útil se a base da API for `.../api/` em vez de `.../app_android/api/`.

### Fila offline (app)

- Com falha de rede, o app grava pontos em **Room** (`gps_pending`) e reenvia a cada localização e a cada **15 min** (WorkManager).

### Redis (servidor, opcional)

- Variáveis: `SF_REDIS_HOST`, opcional `SF_REDIS_PORT`, `SF_REDIS_KEY` (default `gps:stream`), `SF_REDIS_PASSWORD`.
- Cada ponto salvo no MySQL pode ser empilhado em Redis para integrações. Worker de exemplo: `php cron/gps_redis_worker.php`.

---

## 8. Cidades (público)

### GET cidades.php

- **Query:** `uf` (ex: SP, MG)
- **Resposta 200:** `data.cidades`: array de `{ id, nome, uf }`

---

## Códigos HTTP

- **200** — Sucesso
- **400** — Dados inválidos ou incompletos
- **401** — Não autorizado (token ausente/inválido/expirado)
- **404** — Recurso não encontrado
- **405** — Método não permitido
- **500** — Erro interno
