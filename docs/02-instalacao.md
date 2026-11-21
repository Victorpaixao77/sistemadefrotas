[← Índice](README.md)

# 02 · Instalação

## Pré-requisitos

| Item | Versão / observação |
|------|---------------------|
| PHP | 8.2 (extensões: `pdo_mysql`, `openssl`, `curl`, `gd`, `mbstring`, `soap` p/ fiscal, `zip`) |
| MySQL/MariaDB | 10.4+ (no XAMPP local: porta **3307**) |
| Apache | 2.4 (mod_rewrite habilitado — o projeto usa `.htaccess`) |
| Composer | 2.x (para `vendor/` — libs fiscais e PDF/Excel) |
| Node | opcional (só se for mexer no build do app ou tooling) |
| Certificado A1 | `.pfx` por empresa, para emissão fiscal (NFe/CTe/MDFe) |

## Passo a passo (ambiente local XAMPP)

```bash
# 1. Clonar dentro de C:\xampp\htdocs\sistema-frotas
# 2. Instalar dependências PHP (gera vendor/ — não versionado)
composer install

# 3. Criar o banco e aplicar as migrações (sql/ é aplicado manualmente)
mysql -h 127.0.0.1 -P 3307 -u root -e "CREATE DATABASE sistema_frotas CHARACTER SET utf8mb4;"
#   Aplicar as migrações necessárias, ex.:
mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/arquivo.sql

# 4. Criar as pastas graváveis (não versionadas)
#   logs/ cache/ tmp/ sessions/ uploads/   (ver .gitignore)

# 5. Configurar variáveis de ambiente (ver 03-configuracao.md) ou usar fallback local

# 6. Subir Apache + MySQL pelo painel do XAMPP e abrir:
#   http://localhost/sistema-frotas/login.php
```

## Ordem das migrações

Não há um migrador automático — os arquivos em `sql/` são aplicados **manualmente**. Não
existe um `schema.sql` único consolidado; o banco atual tem **230 tabelas** montadas a
partir do histórico de migrações. Para um ambiente novo, o caminho prático é **restaurar
um dump** de um ambiente existente (ver [17-manutencao.md](17-manutencao.md)) e depois
aplicar apenas as migrações novas ainda não presentes.

> ⚠️ **Pendência conhecida:** falta um `sql/schema_full.sql` consolidado. Está listado em
> [99-validacao-final.md](99-validacao-final.md) como melhoria de implantação.

## Validação pós-instalação

```bash
php -l index.php                       # lint de um arquivo
C:/xampp/php/php.exe -v                 # versão do PHP
# App deve redirecionar para login:
curl -s -o /dev/null -w "%{http_code}" http://localhost/sistema-frotas/index.php  # espera 302
```

Login → cai em `index.php` (Dashboard). Se aparecer erro de conexão, revisar
[03-configuracao.md](03-configuracao.md) (credenciais `SF_DB_*` / porta 3307).
