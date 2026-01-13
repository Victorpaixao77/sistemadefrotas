# 📋 Instruções: Usuário Oculto com Acesso a Todas as Empresas

## 🎯 Objetivo
Criar um usuário que tenha acesso a todas as empresas do sistema, mas que não apareça nas listagens de usuários para os outros usuários.

## 📝 Passos para Implementar

### 1. Executar o SQL
Execute o arquivo `sql/add_usuario_oculto_fields.sql` no banco de dados para adicionar os campos necessários:
- `is_oculto`: Marca usuários como ocultos (não aparecem nas listagens)
- `acesso_todas_empresas`: Permite acesso a todas as empresas

```sql
-- Execute este comando no MySQL
source sql/add_usuario_oculto_fields.sql;
```

### 2. Criar o Usuário Oculto
1. Acesse `pages_adm/usuarios.php`
2. Clique em "Novo Usuário"
3. Preencha os dados:
   - Nome, Email, Senha
   - Selecione uma empresa (será ignorada se marcar "Acesso a Todas as Empresas")
   - Tipo de Usuário
   - **Marque "Usuário Oculto"** ✅
   - **Marque "Acesso a Todas as Empresas"** ✅
4. Salve

### 3. Como Funciona

#### Usuário Oculto (`is_oculto = 1`)
- ✅ **NÃO aparece** nas listagens de usuários em:
  - `pages/usuarios.php` (usuários da empresa)
  - `pages_adm/api/get_usuarios_empresa.php` (modal de usuários da empresa)
- ✅ **APARECE** apenas em:
  - `pages_adm/usuarios.php` (painel administrativo)

#### Acesso a Todas as Empresas (`acesso_todas_empresas = 1`)
- ✅ Ao fazer login, o sistema:
  - **Redireciona para `selecionar_empresa.php`** para escolher qual empresa acessar
  - Marca `$_SESSION["acesso_todas_empresas"] = true`
  - **NÃO define empresa_id automaticamente** - o usuário escolhe
- ✅ O usuário pode:
  - **Escolher qual empresa acessar** na tela de seleção
  - **Trocar de empresa** a qualquer momento usando o botão no header (ícone de prédio)
  - Acessar dados de qualquer empresa do sistema
- ✅ **Não precisa criar um usuário oculto para cada empresa** - um único usuário acessa todas!

### 4. Como Usar

#### Ao Fazer Login:
1. **Usuário com acesso global** será redirecionado para `selecionar_empresa.php`
2. **Escolha a empresa** que deseja acessar
3. Clique em **"Acessar Empresa Selecionada"**
4. O sistema redireciona para o dashboard da empresa escolhida

#### Durante o Uso:
1. **Trocar de empresa** a qualquer momento:
   - Clique no **ícone de prédio** no header (ao lado das notificações)
   - Selecione a empresa desejada
   - A página será recarregada com os dados da nova empresa

### 5. Verificação

#### Testar se está funcionando:
1. **Criar usuário oculto** em `pages_adm/usuarios.php` com:
   - ✅ "Usuário Oculto" marcado
   - ✅ "Acesso a Todas as Empresas" marcado
2. **Fazer login** com esse usuário
3. **Verificar** que aparece a tela de seleção de empresa
4. **Escolher uma empresa** e acessar
5. **Verificar** que aparece o botão de trocar empresa no header
6. **Verificar** que ele NÃO aparece em:
   - Lista de usuários da empresa (`pages/usuarios.php`)
   - Modal de usuários da empresa (em `pages_adm/empresas.php`)

### 6. Observações Importantes

⚠️ **Segurança:**
- Usuários ocultos ainda aparecem no painel administrativo (`pages_adm/usuarios.php`)
- Apenas administradores do sistema podem criar/editar usuários ocultos
- O campo `is_oculto` e `acesso_todas_empresas` só podem ser alterados por admins

⚠️ **Login:**
- Se o usuário tiver `acesso_todas_empresas = 1`, o sistema busca automaticamente a primeira empresa ativa
- O `empresa_id` na sessão será definido automaticamente
- O usuário pode acessar dados de qualquer empresa através do código

⚠️ **Compatibilidade:**
- O código verifica se os campos existem antes de usá-los
- Se os campos não existirem, o sistema funciona normalmente (sem erro)
- Usuários antigos continuam funcionando normalmente

## 🔧 Arquivos Criados/Modificados

### Novos Arquivos:
1. `selecionar_empresa.php` - Tela para escolher qual empresa acessar
2. `api/trocar_empresa.php` - API para listar e trocar de empresa

### Arquivos Modificados:
1. `sql/add_usuario_oculto_fields.sql` - SQL para adicionar campos
2. `login.php` - Redireciona para seleção de empresa se tiver acesso global
3. `pages/usuarios.php` - Filtrar usuários ocultos
4. `pages_adm/usuarios.php` - Interface para criar/editar usuários ocultos
5. `pages_adm/api/get_usuarios_empresa.php` - Filtrar usuários ocultos
6. `pages_adm/api/salvar_usuario.php` - Salvar campos de oculto e acesso global
7. `includes/header.php` - Adiciona botão de trocar empresa no header
8. `js/header.js` - JavaScript para o seletor de empresa no header

## ✅ Checklist de Implementação

- [ ] Executar SQL `add_usuario_oculto_fields.sql`
- [ ] Criar usuário de teste com `is_oculto = 1` e `acesso_todas_empresas = 1`
- [ ] Testar login com usuário oculto
- [ ] Verificar que aparece a tela `selecionar_empresa.php` após login
- [ ] Selecionar uma empresa e verificar acesso
- [ ] Verificar que aparece o botão de trocar empresa no header
- [ ] Testar trocar de empresa usando o botão do header
- [ ] Verificar que usuário oculto NÃO aparece em `pages/usuarios.php`
- [ ] Verificar que usuário oculto NÃO aparece no modal de usuários da empresa
- [ ] Verificar que usuário oculto APARECE em `pages_adm/usuarios.php`
- [ ] Testar acesso a dados de diferentes empresas após trocar

