# Módulo do Motorista - Sistema de Frotas

Este módulo permite que os motoristas registrem rotas, abastecimentos e checklists de veículos, aguardando aprovação do gestor.

## Funcionalidades

### 1. Autenticação
- Login com nome e senha
- Sessão segura
- Logout

### 2. Rotas
- Registro de rotas realizadas
- Origem e destino
- Quilometragem rodada
- Data e observações
- Status pendente até aprovação

### 3. Abastecimentos
- Registro de abastecimentos
- Tipo de combustível
- Quantidade e valor
- Posto e quilometragem
- Status pendente até aprovação

### 4. Checklists
- Registro de checklists diários/semanais/mensais
- Itens verificados (ok/nok/na)
- Quilometragem atual
- Observações
- Status pendente até aprovação

## Estrutura de Arquivos

```
pages_motorista/
├── api/
│   └── motorista_api.php
├── css/
│   └── motorista.css
├── js/
│   └── motorista.js
├── uploads/
│   └── fotos/
├── config.php
├── database.sql
├── functions.php
├── layout.php
├── login.php
├── index.php
├── rotas.php
├── abastecimento.php
├── checklist.php
└── logout.php
```

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Extensões PHP:
  - PDO
  - PDO_MySQL
  - GD (para manipulação de imagens)

## Instalação

1. Importe o arquivo `database.sql` no seu banco de dados:
   ```sql
   mysql -u usuario -p banco_de_dados < database.sql
   ```

2. Configure o arquivo `config.php` com as credenciais do banco de dados:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sistema_frotas');
   define('DB_USER', 'seu_usuario');
   define('DB_PASS', 'sua_senha');
   ```

3. Crie o diretório `uploads/fotos` e configure as permissões:
   ```bash
   mkdir -p uploads/fotos
   chmod 755 uploads/fotos
   ```

4. Configure o servidor web para apontar para o diretório do sistema.

## Uso

1. Acesse a página de login:
   ```
   http://seu-servidor/sistema-frotas/pages_motorista/login.php
   ```

2. Use as credenciais de teste:
   - Nome: Motorista Teste
   - Senha: password

3. Após o login, você terá acesso ao dashboard com as opções:
   - Rotas
   - Abastecimento
   - Checklist

## Segurança

- Senhas criptografadas com bcrypt
- Proteção contra SQL Injection
- Validação de dados
- Tokens CSRF
- Sessões seguras
- Logs de ações

## Personalização

### Cores
Edite as variáveis CSS em `css/motorista.css`:
```css
:root {
    --primary-color: #2c3e50;
    --secondary-color: #34495e;
    --accent-color: #3498db;
    /* ... */
}
```

### Layout
Modifique o arquivo `layout.php` para alterar a estrutura da página.

### Funcionalidades
Adicione novas funcionalidades editando os arquivos:
- `functions.php` para funções PHP
- `motorista.js` para interações JavaScript
- `motorista_api.php` para endpoints da API

## Suporte

Para suporte, entre em contato com a equipe de desenvolvimento.

## Licença

Este projeto está licenciado sob a licença MIT. 