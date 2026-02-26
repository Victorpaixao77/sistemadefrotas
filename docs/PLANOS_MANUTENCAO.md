# Planos de Manutenção – O que faz e como configurar

## O que é

**Planos de Manutenção** definem **regras de manutenção preventiva** por combinação de:

- **Veículo** (ex.: ABC-1234)
- **Componente** (ex.: Óleo do motor, Filtro de óleo, Freios)
- **Tipo** (ex.: Preventiva)

Para cada plano você informa **quando** a próxima manutenção deve acontecer:

- **Por quilometragem:** a cada X km (ex.: 10.000 km)
- **Por tempo:** a cada X dias (ex.: 180 dias / 6 meses)
- Ou **os dois:** vence quando atingir o km **ou** a data (o que vier primeiro)

O sistema usa isso para:

1. **Alertas na tela Manutenções** – card “Alertas e Próximas Manutenções” mostra o que está **próximo** ou **vencido** (por km ou por data).
2. **Atualização automática** – quando você **conclui** uma manutenção preventiva (na lista de manutenções), o sistema atualiza sozinho o “último km” e a “última data” daquele plano, e recalcula a próxima previsão.

Ou seja: você **configura a regra** (intervalo km/dias); o sistema **avisa** quando está perto ou vencido e **atualiza** o histórico ao concluir a preventiva.

---

## Onde acessar

- Na página **Manutenções** (`manutencoes.php`), no topo à direita: botão com ícone de **clipboard/lista** (“Planos de manutenção”).
- Ou direto: **http://localhost/sistema-frotas/pages/planos_manutencao.php**

---

## Como configurar (passo a passo)

### 1. Garantir que a tabela existe

Se a tela de planos redirecionar para Manutenções, execute no banco:

```sql
-- Arquivo: sql/create_planos_manutencao.sql
```

Isso cria a tabela `planos_manutencao`.

### 2. Cadastrar um plano

1. Clique em **“Novo plano”**.
2. Preencha:
   - **Veículo *** – placa do veículo.
   - **Componente *** – ex.: Óleo do motor, Filtro de ar, Freios.
   - **Tipo *** – em geral **Preventiva**.
   - **Intervalo (km)** – opcional. Ex.: `10000` = a cada 10.000 km.
   - **Intervalo (dias)** – opcional. Ex.: `180` = a cada 6 meses.
   - **Último km** – quilometragem na última vez que fez essa manutenção (opcional; pode ser preenchido depois ou atualizado ao concluir uma preventiva).
   - **Última data** – data da última execução (opcional; idem).
3. É obrigatório informar **pelo menos um** dos intervalos (km **ou** dias).
4. Clique em **Salvar**.

### 3. Exemplos de uso

| Objetivo | Intervalo KM | Intervalo dias | Último km / Última data |
|----------|--------------|----------------|--------------------------|
| Troca de óleo a cada 10.000 km | 10000 | (vazio) | Km atual do veículo na última troca |
| Revisão a cada 6 meses | (vazio) | 180 | Data da última revisão |
| Filtro de óleo: 10.000 km ou 6 meses | 10000 | 180 | Último km e última data |

### 4. O que aparece na tela Manutenções

- **Card “Alertas e Próximas Manutenções”:**
  - Itens **vencidos** (km já passou ou data já passou) em destaque.
  - Itens **próximos** (ainda não vencidos), com “Próximo em X km” ou “Vence em DD/MM/AAAA”.
- Se não houver planos cadastrados, a mensagem orienta: *“Cadastre planos de manutenção (por veículo/componente/tipo e intervalo km ou dias) para ver próximas e vencidas.”*

### 5. Atualização automática ao concluir preventiva

Quando você **conclui** uma manutenção (status “Concluída”) na listagem de Manutenções, a API de manutenções:

- Localiza o plano correspondente (mesmo veículo + componente + tipo).
- Atualiza **último_km** e **ultima_data** desse plano com os dados da manutenção concluída.

Assim, a “próxima” manutenção passa a ser calculada a partir da última execução real.

---

## Resumo

| O quê | Para quê |
|-------|----------|
| **Planos de Manutenção** | Definir **quando** fazer cada preventiva (por km e/ou por dias), por veículo e componente. |
| **Configurar** | Em **Planos de Manutenção**: novo plano → veículo, componente, tipo, intervalo km/dias, último km/data (opcional). |
| **Na prática** | Sistema avisa “próximo” ou “vencido” no card de alertas e atualiza o plano ao concluir a preventiva. |

Se quiser, na próxima podemos fazer um exemplo concreto (ex.: um plano “Troca de óleo a cada 10.000 km” para um veículo) passo a passo na tela.
