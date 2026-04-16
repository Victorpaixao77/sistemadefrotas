# App Android - Motoristas (Caminhoneiros)

API REST para o aplicativo móvel dos motoristas, espelhando o módulo **Portal do Motorista** (`pages_motorista`). O app consome esta API para login, dashboard, rotas, abastecimentos, checklists e despesas de viagem.

## Requisitos

- PHP 7.4+ com PDO MySQL
- Banco `sistema_frotas` com as tabelas do sistema (motoristas, rotas, abastecimentos, checklist_viagem, despesas_viagem, veiculos, cidades, usuarios_motoristas)
- Tabela de tokens: execute o script `sql/create_api_tokens_motorista.sql` no banco (instalações já existentes: também `sql/alter_api_tokens_refresh.sql` para access token + refresh token separados).

## Instalação

1. Execute no MySQL:
   ```sql
   source sql/create_api_tokens_motorista.sql;
   -- Se a tabela já existia sem refresh_token:
   source sql/alter_api_tokens_refresh.sql;
   ```
2. Configure a base URL no app Android, por exemplo:
   - Desenvolvimento: `http://SEU_IP/sistema-frotas/app_android/api/`
   - Produção: `https://seudominio.com.br/sistema-frotas/app_android/api/`

## Autenticação

- **Login:** `POST api/auth.php` com `nome` e `senha` (ou JSON `{"nome":"...","senha":"..."}`).  
  Resposta inclui `token` (acesso), `refresh_token`, `motorista_id`, `empresa_id`, `nome`, `expira_em` (acesso), e opcionalmente `refresh_expira_em`.
- **Refresh:** `POST api/auth.php` com JSON `{"action":"refresh","refresh_token":"..."}` para renovar só o access token.
- **Requisições autenticadas:** envie o token no header:
  ```http
  Authorization: Bearer SEU_TOKEN
  ```
- **Logout:** `POST api/auth.php` com `action=logout` e o header `Authorization: Bearer TOKEN`.

## Endpoints

| Recurso       | Método | Arquivo          | Descrição                    |
|---------------|--------|------------------|-----------------------------|
| Login / Me    | POST/GET | auth.php       | Login, dados do motorista, logout |
| Dashboard     | GET    | dashboard.php    | Resumo (contadores, rotas do dia, últimas listas) |
| Rotas         | GET/POST | rotas.php      | Listar e criar rotas        |
| Abastecimentos | GET/POST | abastecimentos.php | Listar e registrar abastecimento |
| Checklists    | GET/POST | checklists.php  | Listar e registrar checklist de viagem |
| Despesas      | GET/POST | despesas.php   | Por rota: listar, criar, atualizar |
| Veículos      | GET    | veiculos.php    | Listar veículos da empresa  |
| Cidades       | GET    | cidades.php     | Listar cidades por UF (?uf=SP) |

Detalhes em [docs/ENDPOINTS.md](docs/ENDPOINTS.md).

### GPS (mesmo token, URL na raiz do projeto)

Útil para integrações ou painel chamando `https://.../sistema-frotas/api/gps/...` (Bearer igual ao app):

| Ficheiro | Método | Descrição |
|----------|--------|-----------|
| `api/gps/salvar.php` | POST | Um ponto (igual `gps_salvar.php`) |
| `api/gps/salvar_lote.php` | POST | Lote (igual `gps_salvar_lote.php`) |
| `api/gps/posicao_atual.php` | GET | `?veiculo_id=` opcional — última posição |
| `api/gps/historico.php` | GET | `veiculo_id`, `data_inicio`, `data_fim`, `limite` |
| `api/gps/track_playback.php` | GET | Igual histórico + `format=geojson` para LineString |

## Respostas

- Sucesso: `{"success": true, "message": "...", "data": {...}}`
- Erro: `{"success": false, "message": "..."}` com HTTP 4xx/5xx quando aplicável.

## Segurança

- Use **HTTPS** em produção.
- Access token: ~7 dias (env `SF_API_ACCESS_TTL`); refresh: ~90 dias (`SF_API_REFRESH_TTL`). O app renova com `action=refresh`.
- Não exponha o token em logs ou em URLs.
