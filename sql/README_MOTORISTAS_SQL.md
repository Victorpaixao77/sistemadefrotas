# Scripts SQL – Motoristas (funcionalidades completas)

Execute no banco na ordem abaixo. Use o cliente MySQL (phpMyAdmin, MySQL Workbench ou linha de comando).

| Arquivo | Uso |
|---------|-----|
| **alter_motoristas_dados_adicionais.sql** | Data nascimento, RG, PIS/PASEP, banco/agência/conta, contato emergência (nome), restrições médicas, tamanho uniforme, observações RH |
| **create_motorista_favoritos.sql** | Favoritos por usuário |
| **create_motorista_periodos.sql** | Calendário de disponibilidade (férias/licenças) |
| **create_motorista_treinamentos.sql** | Treinamentos/capacitações |
| **create_motorista_dependentes.sql** | Dependentes/beneficiários |
| **alter_rotas_avaliacao_viagem.sql** | Coluna avaliacao_viagem (nota 0–10) em rotas |

Se ainda não tiver o histórico de alterações:

- **create_motoristas_log.sql** – Tabela do histórico
- **alter_motoristas_log_mais_campos.sql** – Colunas “quem alterou” e IP

Exemplo (linha de comando, dentro da pasta do projeto):

```bash
cd c:\xampp\htdocs\sistema-frotas
mysql -u root -p nome_do_banco < sql/alter_motoristas_dados_adicionais.sql
mysql -u root -p nome_do_banco < sql/create_motorista_favoritos.sql
# ... e assim por diante
```

Ou no phpMyAdmin: escolha o banco, aba “SQL”, cole o conteúdo de cada arquivo e execute.
