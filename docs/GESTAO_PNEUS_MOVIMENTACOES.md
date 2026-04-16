# Regras de negócio e queries – pneu_movimentacoes

Documento de referência: **quando gerar movimentação**, **queries de custo/km e vida útil** e **algoritmo de previsão (IA explicável)**. Tudo com multiempresa e histórico como fonte da verdade.

---

## 1. Regra de ouro

**Qualquer mudança de estado do pneu gera UMA movimentação.**

- **Status atual do pneu** = último registro do histórico (não campos soltos na tabela `pneus`).
- Nada é calculado direto no pneu; relatórios e KPIs derivam do histórico.

---

## 2. Quando gerar cada tipo de movimentação

| Tipo | Quando | Campos principais |
|------|--------|-------------------|
| **entrada_estoque** | Pneu cadastrado / comprado | `custo` = valor da compra; `veiculo_id` = NULL |
| **instalacao** | Pneu sai do estoque e vai para veículo/posição | `veiculo_id`, `eixo_id`, `posicao_id`, `km_odometro` |
| **deslocamento** | Troca de eixo ou posição no mesmo veículo | `veiculo_id`, novo `eixo_id`/`posicao_id`, `km_odometro` |
| **remocao** | Pneu sai do veículo (volta ao estoque ou para manutenção) | `veiculo_id`, `km_odometro`; preencher `km_rodado` = km_remocao - km_instalacao |
| **recapagem** | Recapagem realizada | `custo`, `fornecedor_id`, `sulco_mm` (novo sulco) |
| **manutencao** | Conserto, balanceamento relevante, etc. | `custo`, `fornecedor_id`, `sulco_mm` se houver medição |
| **descarte** | Pneu inutilizado | `km_odometro` = km final; **após descarte o pneu não pode mais ser instalado** |

---

## 3. Queries – KM total, custo total e custo/km

### 3.1 KM total rodado do pneu

```sql
SELECT
    pneu_id,
    SUM(km_rodado) AS km_total
FROM pneu_movimentacoes
WHERE tipo IN ('remocao', 'descarte')
  AND km_rodado IS NOT NULL
GROUP BY pneu_id;
```

*(`km_rodado` é preenchido na remoção/descarte: km_atual - km_da_instalação.)*

### 3.2 Custo total do pneu

```sql
SELECT
    pneu_id,
    SUM(custo) AS custo_total
FROM pneu_movimentacoes
WHERE custo > 0
GROUP BY pneu_id;
```

Inclui: compra (entrada_estoque), recapagens, manutenções.

### 3.3 Custo por km (individual por pneu)

```sql
SELECT
    p.id AS pneu_id,
    SUM(m.custo) / NULLIF(SUM(CASE WHEN m.km_rodado IS NOT NULL THEN m.km_rodado ELSE 0 END), 0) AS custo_por_km
FROM pneus p
JOIN pneu_movimentacoes m ON m.pneu_id = p.id AND m.empresa_id = p.empresa_id
WHERE m.km_rodado IS NOT NULL
GROUP BY p.id, p.empresa_id;
```

### 3.4 Vida útil (%) por sulco

Supondo `sulco_inicial` e sulco mínimo 3 mm em `pneus`:

```sql
SELECT
    p.id,
    p.empresa_id,
    ultimo.sulco_mm AS sulco_atual,
    ROUND(
        ((ultimo.sulco_mm - 3) / NULLIF(p.sulco_inicial - 3, 0)) * 100,
        2
    ) AS vida_percentual
FROM pneus p
JOIN (
    SELECT pneu_id, empresa_id, sulco_mm, data_movimentacao,
           ROW_NUMBER() OVER (PARTITION BY pneu_id ORDER BY data_movimentacao DESC) AS rn
    FROM pneu_movimentacoes
    WHERE sulco_mm IS NOT NULL
) ultimo ON ultimo.pneu_id = p.id AND ultimo.empresa_id = p.empresa_id AND ultimo.rn = 1;
```

*(Se o MySQL for antigo e não tiver ROW_NUMBER, usar subconsulta com MAX(data_movimentacao).)*

---

## 4. Algoritmo de previsão (IA explicável)

Nada de modelo pesado: regras sobre o histórico já resolvem a maior parte dos casos.

### 4.1 Taxa média de desgaste (por pneu, último ciclo)

- `desgaste_mm` = sulco_anterior - sulco_atual  
- `km_intervalo` = km_atual - km_anterior  
- **taxa_desgaste** = desgaste_mm / km_intervalo (ex.: 1,2 mm a cada 18.000 km → 0,0000667 mm/km)

### 4.2 Previsão de troca (km restante)

- **km_restante** = (sulco_atual - sulco_minimo) / taxa_desgaste  
- Ex.: (7 - 3) / 0,0000667 ≈ 60.000 km

### 4.3 Regra de alerta

- Se `km_restante` < 10.000 → **alerta vermelho**
- Se `km_restante` < 20.000 → **alerta amarelo**

### 4.4 Comparação com média do eixo (desgaste anormal)

- Calcular média de `km_rodado` por eixo (movimentações tipo `remocao`).
- Se o pneu rodou X% a menos que a média do eixo → desgaste acima do normal.
- Mensagem sugerida: *"Este pneu teve desgaste Y% acima da média do eixo Z."*

Exemplo de base para média por eixo:

```sql
SELECT
    eixo_id,
    AVG(km_rodado) AS media_km
FROM pneu_movimentacoes
WHERE tipo = 'remocao'
  AND eixo_id IS NOT NULL
  AND km_rodado IS NOT NULL
GROUP BY eixo_id;
```

---

## 5. Multiempresa e auditoria

- Todas as queries devem filtrar por `empresa_id` (sessão do usuário).
- Custo/km e vida útil são **derivados do histórico**; não gravar resultado como campo solto no pneu, para manter auditoria e consistência.

---

## 6. Referência

- **DDL:** `sql/create_pneu_movimentacoes.sql`
- **Roadmap e ajustes:** `docs/GESTAO_PNEUS_ROADMAP.md`
