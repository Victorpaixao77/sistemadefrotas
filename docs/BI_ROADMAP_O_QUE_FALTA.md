# BI Frota – Roadmap: o que ainda dá para colocar

Documento de referência com tudo que falta tecnicamente, em decisão de negócio e para vender.

---

## 1. O que FALTA tecnicamente (backend / dados)

### 1.1 Normalização financeira final ✅ (implementado)
- ~~Valores monetários com floats longos (ex.: 3267.35000000004)~~
- **Feito:** `round(..., 2)` em valores monetários antes do JSON na API.
- **Recomendado em produção:** colunas monetárias como `DECIMAL(12,2)` no SQL.

### 1.2 Cache de indicadores (performance)
- Hoje tudo é cálculo “on demand”.
- **Sugestão:** cache por empresa + período (Redis ou tabela `bi_cache`).
- Expiração mensal ou invalidação sob demanda.
- Diferencial quando o cliente e o volume de dados crescem.

### 1.3 Indicador de dados incompletos ✅ (implementado)
- **Feito:** flags no período:
  - rotas sem despesas de viagem;
  - abastecimentos sem litros (dificulta consumo km/L);
  - manutenções sem tipo.
- BI exibe aviso: *"Existem dados incompletos que podem impactar os resultados."*

---

## 2. O que FALTA no BI (decisão de negócio)

### 2.1 Ponto de equilíbrio (Break-even) ✅ (implementado)
- **Feito:** na Visão Geral:
  - Faturamento mínimo mensal (para lucro zero);
  - Quantas rotas precisam rodar para empatar (custo total ÷ ticket médio).
- Contador e gestor usam direto para planejamento.

### 2.2 Custo por KM (real e histórico) ✅ (implementado)
- **Feito:** na Visão Geral:
  - **Gráfico** “Evolução do custo/km (mês a mês)” – linha com custo/km por mês (abast + manut + desp. viagem + desp. fixas) ÷ km.
  - **Tabela** “Por veículo (período)” – Veículo, KM, Gasto abast.+manut., Custo/km (R$) – dados do merge de veículos_top + abastecimento + manutenção.

### 2.3 Tendência (setas inteligentes) ✅ (implementado)
- **Feito:** além de ▲▼:
  - “Tendência de alta nos custos de combustível” (3 meses consecutivos);
  - “3 meses consecutivos de queda de margem”.
- Regras estatísticas simples, sem IA pesada.

### 2.4 Simulador “E se…” ✅ (implementado)
- **Feito:** bloco na Visão Geral com campos Diesel (%) e Comissão (%); exibe lucro atual, lucro simulado e impacto em R$ e %.
- Ex.: “Se o diesel subir 10%, impacto estimado no lucro: -R$ X”.
- “Se reduzir comissão em 1%, lucro sobe X%.”
- Funcionalidade premium; requer tela e fórmulas dedicadas.

---

## 3. Alertas (UX)

### 3.1 Central de alertas clicável ✅ (implementado)
- **Feito:** cada alerta pode levar à tela filtrada:
  - Consumo abaixo da média → `?visao=abastecimento`;
  - Custo manutenção acima da média → `?visao=manutencao`;
  - Margem negativa / rotas → `?visao=rotas` ou Geral.
- Dados já vinham no JSON; falta só UX (links) – feito.

---

## 4. IA (fase 2 – quando quiser escalar)

### 4.1 IA nível 1 (regras + heurística, sem LLM)
- Detectar prejuízo recorrente.
- Sugerir redução de custos (ex.: “foco em pedágio”).
- Priorizar manutenção preventiva.
- Já há insights automáticos; dá para aprofundar regras.

### 4.2 IA nível 2 (LLM opcional)
- Texto automático de relatório mensal.
- Explicação em linguagem natural: *“Seu principal problema este mês foi custo excessivo de pedágio.”*

---

## 5. O que falta para VENDER o sistema

### 5.1 Auditoria / Log
- “Quem alterou esse valor?”
- Log de edição (antes/depois), usuário, data e IP.
- Diferencial para empresas que exigem rastreabilidade.

### 5.2 Perfis de acesso mais granulares
- Além de admin/usuário: Financeiro, Operacional, “Só visualização”.
- Permissões por módulo (BI, Rotas, Manutenção, etc.).

### 5.3 Dados de demonstração
- Botão “Carregar dados de exemplo”.
- Aumenta conversão em demonstrações e trials.

### 5.4 Relatório PDF executivo
- Resumo mensal automático: Score, Margem, Alertas, Top 3 problemas.
- Diretor/gestor usa para reunião rápida.

---

## 6. Resumo de status

| Item                         | Status      | Prioridade |
|-----------------------------|------------|------------|
| Normalização (round)        | ✅ Feito   | Alta       |
| Dados incompletos (aviso)   | ✅ Feito   | Alta       |
| Ponto de equilíbrio        | ✅ Feito   | Alta       |
| Tendências (3 meses)        | ✅ Feito   | Média      |
| Alertas clicáveis           | ✅ Feito   | Média      |
| Cache (tabela bi_cache)     | ✅ Feito   | Escala     |
| Custo/km histórico/veículo | ✅ Feito   | Média      |
| Simulador “E se…”           | Pendente   | Premium    |
| Auditoria / Log             | Pendente   | Venda      |
| Perfis granulares           | Pendente   | Venda      |
| Dados demonstração          | Pendente   | Venda      |
| PDF executivo               | Pendente   | Venda      |

---

## 7. Próximos passos sugeridos

1. **Produção:** padronizar colunas monetárias para `DECIMAL(12,2)` (migração SQL).
2. **Escala:** implementar cache de indicadores quando houver muitos acessos ou muitos anos.
3. **Venda:** relatório PDF executivo + botão “Dados de exemplo” para trials.
4. **Premium:** simulador “E se…” (combustível, comissão) como diferencial pago.

Se quiser, dá para detalhar: roadmap MVP → Premium, checklist técnico para produção ou modelo de pitch para transportadoras.
