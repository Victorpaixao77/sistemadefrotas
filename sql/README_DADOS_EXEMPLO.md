# Script de Dados de Exemplo - Empresa ID 1

## ⚠️ IMPORTANTE - LEIA ANTES DE EXECUTAR

**Este script NÃO cria tabelas!** Ele apenas insere dados em tabelas que **já devem existir**.

### Pré-requisitos

Antes de executar este script, você precisa:

1. ✅ Ter o banco de dados criado
2. ✅ Ter todas as tabelas do sistema criadas
3. ✅ Ter pelo menos a empresa ID 1 cadastrada (ou o script criará)

### Como verificar se está tudo pronto

Execute primeiro: `sql/verificar_tabelas.sql` para verificar se todas as tabelas existem.

Se alguma tabela estiver faltando, você verá o erro:
```
#1146 - Tabela 'sistema_frotas.empresas' não existe
```

Nesse caso, execute primeiro o script de criação do banco de dados do sistema.

## Descrição

Este script SQL (`dados_exemplo_empresa_1.sql`) insere dados de exemplo para a empresa_id = 1, cobrindo os últimos 6 meses (do mês atual até 6 meses atrás).

## O que é inserido:

1. **Veículos** (se não existirem):
   - ABC-1234 - Mercedes-Benz Actros 2020
   - XYZ-5678 - Volvo FH 2021
   - DEF-9012 - Scania R450 2019

2. **Motoristas** (se não existirem):
   - João Silva
   - Maria Santos
   - Pedro Oliveira

3. **Rotas**: Distribuídas pelos últimos 6 meses
   - Mês atual: 3 rotas
   - Mês -1: 3 rotas
   - Mês -2: 2 rotas
   - Mês -3: 3 rotas (configurado para ficar NEGATIVO)
   - Mês -4: 2 rotas
   - Mês -5: 2 rotas
   - Mês -6: 2 rotas

4. **Abastecimentos**: Vinculados às rotas, com valores proporcionais à distância

5. **Despesas de Viagem**: Vinculadas às rotas, incluindo:
   - Descarga
   - Pedágios
   - Caixinha
   - Estacionamento
   - Lavagem
   - Borracharia (apenas no mês -3)
   - Elétrica/Mecânica (apenas no mês -3)
   - Adiantamento

6. **Despesas Fixas**: Mensais para cada veículo, incluindo:
   - Seguro mensal
   - IPVA (no mês -3, valores altos para tornar negativo)
   - Licenciamento
   - Manutenção preventiva/corretiva

## Mês Negativo

O **mês -3 (3 meses atrás)** foi configurado para ficar **NEGATIVO**, onde:
- Abastecimentos têm preço mais alto (R$ 6,20/litro vs R$ 5,25-5,55/litro)
- Despesas de viagem são muito mais altas (pedágios 4.5x, descarga 1.2x, etc.)
- Despesas fixas incluem IPVA de todos os veículos (R$ 3.500, R$ 3.800, R$ 3.200)
- Manutenção corretiva no veículo 2 (R$ 2.500)

## Como usar:

1. **Verifique se as tabelas existem:**
   - Execute: `sql/verificar_tabelas.sql`
   - Todas as tabelas devem aparecer como ✅ EXISTE

2. **Faça backup do banco de dados** antes de executar

3. **Execute o diagnóstico:**
   - Execute: `sql/diagnostico_rapido.sql`
   - Verifique se tudo está OK

4. **Execute o script de dados:**
   - Execute: `sql/dados_exemplo_empresa_1.sql` no seu banco de dados MySQL/MariaDB
   - O script verifica se veículos e motoristas já existem antes de criar
   - Todos os dados são inseridos com status 'aprovado' e fonte 'sistema'

## 🔍 Scripts de Diagnóstico

- `verificar_tabelas.sql` - Verifica quais tabelas existem
- `diagnostico_rapido.sql` - Diagnóstico rápido do sistema
- `diagnostico_rotas.sql` - Diagnóstico completo
- `diagnostico_rotas.php` - Interface visual no navegador
- `SOLUCAO_PROBLEMAS.md` - Guia de solução de problemas

## Observações:

- Os dados são inseridos com datas relativas (baseadas em CURDATE())
- As rotas usam NULL para cidade_origem_id e cidade_destino_id (apenas estados são preenchidos)
- Todos os valores são em reais (R$)
- As distâncias são em quilômetros
- Os valores de frete e comissão são configurados para gerar lucro positivo na maioria dos meses

## Estrutura de Lucro por Mês:

- **Mês atual**: Positivo (receitas > custos)
- **Mês -1**: Positivo
- **Mês -2**: Positivo
- **Mês -3**: **NEGATIVO** (custos > receitas)
- **Mês -4**: Positivo
- **Mês -5**: Positivo
- **Mês -6**: Positivo
