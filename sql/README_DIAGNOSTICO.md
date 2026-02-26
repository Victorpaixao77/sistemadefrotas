# Scripts de Diagnóstico para Inserção de Rotas

## 📋 Descrição

Estes scripts foram criados para diagnosticar problemas antes de inserir rotas no banco de dados. Eles verificam:

- ✅ Se a empresa ID 1 existe
- ✅ Se os veículos necessários existem
- ✅ Se os motoristas necessários existem
- ✅ A estrutura da tabela `rotas`
- ✅ Se os INSERTs funcionam corretamente

## 🛠️ Como Usar

### Opção 1: Script SQL (phpMyAdmin / MySQL Workbench)

1. Abra o arquivo `diagnostico_rotas.sql`
2. Execute no seu cliente MySQL (phpMyAdmin, MySQL Workbench, etc.)
3. Analise os resultados de cada seção

**Vantagens:**
- Funciona em qualquer cliente MySQL
- Mostra todos os detalhes do banco
- Testa o INSERT com ROLLBACK

### Opção 2: Script PHP (Navegador)

1. Acesse no navegador: `http://localhost/sistema-frotas/sql/diagnostico_rotas.php`
2. Veja o diagnóstico visual com cores e ícones
3. O script testa o INSERT automaticamente

**Vantagens:**
- Interface visual amigável
- Cores indicam status (verde = OK, vermelho = erro)
- Testa o INSERT automaticamente

## 📊 O que cada script verifica

### 1. Verificação da Empresa
- Verifica se a empresa ID 1 existe no banco

### 2. Verificação dos Veículos
Verifica se existem os seguintes veículos:
- `ABC-1234` - Mercedes-Benz Actros
- `XYZ-5678` - Volvo FH
- `DEF-9012` - Scania R450

### 3. Verificação dos Motoristas
Verifica se existem os seguintes motoristas:
- `12345678901` - João Silva
- `98765432109` - Maria Santos
- `11122233344` - Pedro Oliveira

### 4. Estrutura da Tabela Rotas
- Lista todos os campos da tabela `rotas`
- Mostra quais campos permitem NULL
- Mostra tipos de dados e defaults

### 5. Teste de INSERT
- Tenta inserir uma rota de teste
- Faz ROLLBACK automaticamente
- Mostra erros específicos se houver

## 🔍 Interpretando os Resultados

### ✅ Tudo OK
Se todos os itens aparecerem com ✅, você pode executar o script `dados_exemplo_empresa_1.sql` sem problemas.

### ❌ Erros Encontrados

**Se veículos ou motoristas não forem encontrados:**
1. Execute a seção 1 do script `dados_exemplo_empresa_1.sql` (criação de veículos e motoristas)
2. Execute o diagnóstico novamente
3. Se ainda houver erro, verifique se os INSERTs foram executados corretamente

**Se o INSERT de teste falhar:**
- Verifique a mensagem de erro específica
- Compare os campos do INSERT com a estrutura da tabela
- Verifique se há constraints ou foreign keys que estão impedindo a inserção

## 🐛 Erros Comuns

### "Column 'X' doesn't exist"
- **Causa:** O campo não existe na tabela ou tem nome diferente
- **Solução:** Verifique a estrutura da tabela e ajuste o script

### "Cannot add or update a child row: foreign key constraint fails"
- **Causa:** O ID de veículo ou motorista não existe
- **Solução:** Execute primeiro a criação de veículos e motoristas

### "Field 'X' doesn't have a default value"
- **Causa:** Campo obrigatório sem valor
- **Solução:** Adicione o campo no INSERT ou forneça um valor default

## 📝 Exemplo de Saída

### Script SQL
```
=== VERIFICAÇÃO DA EMPRESA ===
✅ Empresa ID 1 encontrada

=== VERIFICAÇÃO DOS VEÍCULOS ===
✅ Veículo 1 (ID: 1) encontrado
✅ Veículo 2 (ID: 2) encontrado
✅ Veículo 3 (ID: 3) encontrado

=== RESUMO FINAL ===
✅ TUDO OK - Pode executar o script de inserção de rotas
```

### Script PHP
Interface visual com:
- ✅ Indicadores verdes para itens OK
- ❌ Indicadores vermelhos para erros
- Tabela com estrutura da tabela rotas
- Resumo final colorido

## 🔄 Próximos Passos

1. Execute o diagnóstico
2. Se tudo estiver OK, execute `dados_exemplo_empresa_1.sql`
3. Se houver erros, corrija-os e execute o diagnóstico novamente

## ⚠️ Importante

- O script de teste faz ROLLBACK, então não insere dados reais
- Sempre execute o diagnóstico antes de inserir dados em produção
- Mantenha backup do banco antes de executar scripts de inserção
