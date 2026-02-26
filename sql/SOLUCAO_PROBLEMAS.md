# 🔧 Solução de Problemas - Scripts de Dados de Exemplo

## ❌ Erro: Tabela 'empresas' não existe

### Problema
Você está recebendo o erro:
```
#1146 - Tabela 'sistema_frotas.empresas' não existe
```

### Causa
O banco de dados ainda não tem as tabelas criadas. O script `dados_exemplo_empresa_1.sql` **NÃO cria tabelas**, ele apenas **insere dados** em tabelas que já devem existir.

### Solução

#### Passo 1: Verificar quais tabelas existem
Execute o script: `sql/verificar_tabelas.sql`

Este script mostrará:
- ✅ Quais tabelas existem
- ❌ Quais tabelas estão faltando

#### Passo 2: Criar as tabelas do sistema
Você precisa executar o script de criação do banco de dados do sistema primeiro. Procure por arquivos como:
- `database.sql`
- `schema.sql`
- `create_tables.sql`
- Ou qualquer script de instalação do sistema

#### Passo 3: Verificar novamente
Execute novamente: `sql/verificar_tabelas.sql`

Agora todas as tabelas devem aparecer como ✅ EXISTE

#### Passo 4: Executar o script de dados de exemplo
Agora sim, execute: `sql/dados_exemplo_empresa_1.sql`

## 📋 Tabelas Necessárias

O script `dados_exemplo_empresa_1.sql` precisa das seguintes tabelas:

1. ✅ `empresas` - Tabela de empresas
2. ✅ `veiculos` - Tabela de veículos
3. ✅ `motoristas` - Tabela de motoristas
4. ✅ `rotas` - Tabela de rotas
5. ✅ `abastecimentos` - Tabela de abastecimentos
6. ✅ `despesas_viagem` - Tabela de despesas de viagem
7. ✅ `despesas_fixas` - Tabela de despesas fixas

## 🔍 Scripts de Diagnóstico

### 1. Verificar Tabelas
```sql
-- Execute: sql/verificar_tabelas.sql
```
Mostra quais tabelas existem e quais estão faltando.

### 2. Diagnóstico Rápido
```sql
-- Execute: sql/diagnostico_rapido.sql
```
Verifica se tudo está pronto para inserir dados.

### 3. Diagnóstico Completo
```sql
-- Execute: sql/diagnostico_rotas.sql
```
Diagnóstico detalhado de todos os componentes.

### 4. Diagnóstico Visual (PHP)
```
Acesse: http://localhost/sistema-frotas/sql/diagnostico_rotas.php
```
Interface visual no navegador.

## 📝 Ordem de Execução Correta

1. **Criar banco de dados** (se não existir)
2. **Criar tabelas** (executar script de criação do sistema)
3. **Verificar tabelas** (`verificar_tabelas.sql`)
4. **Diagnóstico** (`diagnostico_rapido.sql`)
5. **Inserir dados de exemplo** (`dados_exemplo_empresa_1.sql`)

## ⚠️ Importante

- O script `dados_exemplo_empresa_1.sql` **NÃO cria tabelas**
- Ele apenas **insere dados** em tabelas existentes
- Certifique-se de que o sistema está instalado e as tabelas foram criadas antes de executar

## 🆘 Ainda com Problemas?

1. Execute `verificar_tabelas.sql` e veja quais tabelas estão faltando
2. Procure no projeto por scripts de criação de banco de dados
3. Verifique se o sistema foi instalado corretamente
4. Consulte a documentação de instalação do sistema
