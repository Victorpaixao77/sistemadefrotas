# Changelog - Correção para empresa_clientes

## 🔧 Correções Realizadas

### Problema Identificado
O sistema usa a tabela `empresa_clientes` (não `empresas`) para armazenar as empresas clientes.

### Arquivos Corrigidos

#### 1. `dados_exemplo_empresa_1.sql`
- ✅ Adicionada seção para criar empresa de exemplo em `empresa_clientes` se não existir
- ✅ Todas as validações agora verificam `empresa_clientes` ao invés de `empresas`
- ✅ Campo `fonte` corrigido de `'sistema'` para `'gestor'` (conforme ENUM da tabela)

#### 2. `verificar_tabelas.sql`
- ✅ Verifica `empresa_clientes` e `empresa_adm` ao invés de `empresas`

#### 3. `diagnostico_rapido.sql`
- ✅ Atualizado para verificar `empresa_clientes`

#### 4. `diagnostico_rotas.sql`
- ✅ Atualizado para verificar `empresa_clientes`
- ✅ Mostra `razao_social` e `nome_fantasia` ao invés de apenas `nome`

#### 5. `diagnostico_rotas.php`
- ✅ Atualizado para consultar `empresa_clientes`
- ✅ Tratamento de erros melhorado

## 📋 Estrutura da Tabela empresa_clientes

A tabela `empresa_clientes` tem os seguintes campos principais:
- `id` - ID da empresa (usado como empresa_id nas outras tabelas)
- `empresa_adm_id` - ID da empresa administradora
- `razao_social` - Razão social (obrigatório)
- `nome_fantasia` - Nome fantasia (opcional)
- `cnpj` - CNPJ (obrigatório)
- `status` - ENUM('ativo', 'inativo')

## ✅ Próximos Passos

1. Execute `sql/verificar_tabelas.sql` para confirmar que `empresa_clientes` existe
2. Execute `sql/diagnostico_rapido.sql` para verificar se empresa ID 1 existe
3. Se não existir, o script `dados_exemplo_empresa_1.sql` criará automaticamente
4. Execute `sql/dados_exemplo_empresa_1.sql` para inserir os dados de exemplo

## 🔍 Nota Importante

O campo `empresa_id` nas tabelas `veiculos`, `motoristas`, `rotas`, etc. referencia `empresa_clientes.id`, não `empresa_adm.id`.
