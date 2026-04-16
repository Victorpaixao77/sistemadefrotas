# Implementação – Motoristas (funcionalidades completas)

## Scripts SQL (executar na ordem)

1. **create_motoristas_log.sql** – Histórico de alterações (se ainda não rodou)
2. **alter_motoristas_log_mais_campos.sql** – Colunas “quem alterou” e IP no log
3. **alter_motoristas_dados_adicionais.sql** – Data nascimento, RG, PIS/PASEP, banco/agência/conta, contato emergência (nome), restrições médicas, tamanho uniforme, observações RH
4. **create_motorista_favoritos.sql** – Favoritos por usuário
5. **create_motorista_periodos.sql** – Calendário de disponibilidade (férias/licenças)
6. **create_motorista_treinamentos.sql** – Treinamentos/capacitações
7. **create_motorista_dependentes.sql** – Dependentes/beneficiários
8. **alter_rotas_avaliacao_viagem.sql** – Nota por viagem (avaliação 0–10)

---

## Já implementado no código

### Alertas
- **CNH no sino:** ao abrir a página de Motoristas ou ao buscar notificações (get_unread/get_count), é criada no máximo 1 notificação por dia com “CNH vencendo” (30/60/90 dias). Arquivo: `includes/sync_cnh_notificacoes.php`.

### Funcionalidades
- **Vínculo com manutenções:** no modal de visualização, card “Manutenções (veículos que dirigiu)” com total e link para Manutenções. API: `total_manutencoes` em `getMotoristById`.
- **Favoritos:** estrela na lista; toggle via `favorito_toggle`; filtro “Só favoritos” pode ser adicionado no front usando `favoritos_ids`.
- **Compartilhar:** botão “Compartilhar” no modal copia o link `motorists.php?view_id=ID`. Ao abrir a página com `?view_id=123` ou `?id=123`, o modal de visualização abre automaticamente.

### UX
- **Validação de CNH:** no salvar: CNH com 11 dígitos; data de validade não pode ser vencida.

### Relatórios/BI
- Relatório de comissões, mapa de calor e dashboard BI de motoristas ainda precisam de telas/relatórios específicos (veja “Pendente” abaixo).

---

## Pendente (para completar no front/back)

### Dados adicionais no cadastro
- Incluir no formulário de motorista (add/edit) e no modal de visualização os campos: data_nascimento, rg, pis_pasep, banco, agencia, conta, contato_emergencia_nome, restricoes_medicas, tamanho_uniforme, observacoes_rh.
- Na API: em `addMotorist` e `updateMotorist` (e no SELECT de `getMotoristById`) usar essas colunas (já existem no banco após rodar o ALTER).

### Modo cards
- Alternar lista tabela ↔ cards (ex.: botão “Visualização: Tabela | Cards”), guardar preferência em `localStorage` e renderizar cards com foto, nome, status e ações.

### Calendário de disponibilidade
- CRUD de `motorista_periodos` (tipo, data_inicio, data_fim, observação).
- Feed para o calendário (ex.: `calendario/api/calendario_disponibilidade.php`) e exibição no calendário existente.

### Avaliação por viagem
- Na tela de rotas (ou ao aprovar/concluir rota), permitir informar `avaliacao_viagem` (0–10). Na API de rotas: aceitar e gravar o campo. No modal do motorista: exibir média das avaliações das rotas.

### Treinamentos
- CRUD de `motorista_treinamentos` (nome_curso, instituicao, data_conclusao, carga_horaria, etc.). Aba ou seção no cadastro e no modal de visualização do motorista.

### Dependentes
- CRUD de `motorista_dependentes` (nome, parentesco, data_nascimento, cpf). Aba ou seção no cadastro e no modal do motorista.

### Relatório de comissões a pagar
- Em `relatorios.php`, novo tipo (ex.: `comissoes_a_pagar`): período, lista de motoristas com soma de `comissao` das rotas aprovadas no período, total a pagar.

### Mapa de calor (rotas mais frequentes)
- Query: agrupar por origem/destino (ou trecho), contar viagens. Nova página ou seção com tabela/gráfico (heatmap ou barras).

### Dashboard BI motoristas
- Nova página (ex.: `pages/bi_motoristas.php`) com KPIs, gráficos de produtividade, comissões, multas, uso dos relatórios existentes (produtividade_motoristas, etc.).

---

## Resumo

- **SQL:** 8 scripts listados no topo.
- **Código:** alertas CNH no sino, vínculo manutenções, favoritos, compartilhar, validação CNH e link por `view_id` já implementados.
- **Restante:** dados adicionais no form/view, modo cards, calendário disponibilidade, avaliação por viagem, treinamentos, dependentes, relatório comissões, mapa de calor, dashboard BI motoristas – podem ser implementados seguindo os pontos acima.
