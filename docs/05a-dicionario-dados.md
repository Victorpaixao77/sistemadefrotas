[← Índice](README.md) · [← Banco de Dados](05-banco-de-dados.md)

# 05a · Dicionário de Dados (gerado do banco)

> **Gerado automaticamente** a partir do `INFORMATION_SCHEMA` do banco `sistema_frotas`
> (2026-07-07). Fonte 100% real — reflete o schema atual. Regerar com o script no fim.

- **230 tabelas**, **149 chaves estrangeiras (FK)** definidas.
- Legenda de Chave: `PRI` (primária), `MUL` (índice/FK), `UNI` (única).

## Chaves estrangeiras (relacionamentos reais)

| Tabela (filha) | Coluna | → Tabela (pai) | Coluna |
|---|---|---|---|
| `abastecimentos` | `empresa_id` | `empresa_clientes` | `id` |
| `abastecimentos` | `empresa_id` | `empresa_clientes` | `id` |
| `abastecimentos` | `motorista_id` | `motoristas` | `id` |
| `abastecimentos` | `rota_id` | `rotas` | `id` |
| `abastecimentos` | `veiculo_id` | `veiculos` | `id` |
| `adm_config_boletos` | `empresa_adm_id` | `empresa_adm` | `id` |
| `adm_cron_runs` | `job_id` | `adm_cron_jobs` | `id` |
| `adm_empresa_modulos` | `modulo_id` | `adm_modulos` | `id` |
| `alocacoes_pneus_flexiveis` | `eixo_veiculo_id` | `eixos_veiculos` | `id` |
| `alocacoes_pneus_flexiveis` | `empresa_id` | `empresa_clientes` | `id` |
| `alocacoes_pneus_flexiveis` | `pneu_id` | `pneus` | `id` |
| `alocacoes_pneus_flexiveis` | `posicao_id` | `posicoes_pneus` | `id` |
| `calendario_eventos` | `categoria_id` | `categorias_calendario` | `id` |
| `calendario_eventos` | `empresa_id` | `empresa_clientes` | `id` |
| `cavalos_carretas` | `id_carreta` | `tipos_carretas` | `id` |
| `cavalos_carretas` | `id_cavalo` | `tipos_cavalos` | `id` |
| `cidades` | `uf` | `estados` | `uf` |
| `comissoes_pagamentos` | `rota_id` | `rotas` | `id` |
| `configuracoes` | `certificado_a1_id` | `fiscal_certificados_digitais` | `id` |
| `configuracoes` | `empresa_id` | `empresa_clientes` | `id` |
| `config_ranking` | `empresa_id` | `empresa_clientes` | `id` |
| `conquistas_motoristas` | `desafio_id` | `desafios_motoristas` | `id` |
| `conquistas_motoristas` | `empresa_id` | `empresa_clientes` | `id` |
| `conquistas_motoristas` | `motorista_id` | `motoristas` | `id` |
| `contas_pagar` | `banco_id` | `bancos` | `id` |
| `contas_pagar` | `empresa_id` | `empresa_clientes` | `id` |
| `contas_pagar` | `forma_pagamento_id` | `formas_pagamento` | `id` |
| `contas_pagar` | `status_id` | `status_contas_pagar` | `id` |
| `desafios_motoristas` | `empresa_id` | `empresa_clientes` | `id` |
| `despesas_fixas` | `empresa_id` | `empresa_clientes` | `id` |
| `despesas_fixas` | `forma_pagamento_id` | `formas_pagamento` | `id` |
| `despesas_fixas` | `status_pagamento_id` | `status_pagamentos` | `id` |
| `despesas_fixas` | `tipo_despesa_id` | `tipos_despesa_fixa` | `id` |
| `despesas_fixas` | `veiculo_id` | `veiculos` | `id` |
| `despesas_viagem` | `empresa_id` | `empresa_clientes` | `id` |
| `despesas_viagem` | `empresa_id` | `empresa_clientes` | `id` |
| `despesas_viagem` | `rota_id` | `rotas` | `id` |
| `documentos_motoristas` | `motorista_id` | `motoristas` | `id` |
| `eixos` | `posicao_id` | `posicoes_pneus` | `id` |
| `eixos` | `veiculo_id` | `veiculos` | `id` |
| `eixos_veiculos` | `empresa_id` | `empresa_clientes` | `id` |
| `eixos_veiculos` | `veiculo_id` | `veiculos` | `id` |
| `eixo_pneus` | `eixo_id` | `eixos` | `id` |
| `eixo_pneus` | `pneu_id` | `pneus` | `id` |
| `eixo_pneus` | `posicao_id` | `posicoes_pneus` | `id` |
| `empresa_clientes` | `empresa_adm_id` | `empresa_adm` | `id` |
| `estoque_pneus` | `pneu_id` | `pneus` | `id` |
| `estoque_pneus` | `status_id` | `status_pneus` | `id` |
| `financiamentos` | `banco_id` | `bancos` | `id` |
| `financiamentos` | `empresa_id` | `empresa_clientes` | `id` |
| `financiamentos` | `status_pagamento_id` | `status_pagamento` | `id` |
| `financiamentos` | `veiculo_id` | `veiculos` | `id` |
| `fiscal_alertas` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_certificados_digitais` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_config_empresa` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_config_envio_automatico` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_config_seguranca` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_cte` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_cte_itens` | `cte_id` | `fiscal_cte` | `id` |
| `fiscal_eventos_fiscais` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_logs` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_mdfe` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_mdfe` | `motorista_id` | `motoristas` | `id` |
| `fiscal_mdfe` | `veiculo_id` | `veiculos` | `id` |
| `fiscal_mdfe_cte` | `cte_id` | `fiscal_cte` | `id` |
| `fiscal_mdfe_cte` | `mdfe_id` | `fiscal_mdfe` | `id` |
| `fiscal_mdfe_nfe` | `mdfe_id` | `fiscal_mdfe` | `id` |
| `fiscal_mdfe_nfe` | `nfe_id` | `fiscal_nfe_clientes` | `id` |
| `fiscal_nfe_clientes` | `empresa_id` | `empresa_clientes` | `id` |
| `fiscal_nfe_itens` | `nfe_id` | `fiscal_nfe_clientes` | `id` |
| `fiscal_status_historico` | `empresa_id` | `empresa_clientes` | `id` |
| `fornecedores` | `empresa_id` | `empresa_clientes` | `id` |
| `fornecedor_clientes` | `empresa_id` | `empresa_clientes` | `id` |
| `gamificacao_motoristas` | `empresa_id` | `empresa_clientes` | `id` |
| `gamificacao_motoristas` | `motorista_id` | `motoristas` | `id` |
| `gastos_pneus` | `pneu_id` | `pneus` | `id` |
| `instalacoes_pneus` | `pneu_id` | `pneus` | `id` |
| `instalacoes_pneus` | `posicao_id` | `posicoes_pneus` | `id` |
| `instalacoes_pneus` | `veiculo_id` | `veiculos` | `id` |
| `manutencoes` | `componente_id` | `componentes_manutencao` | `id` |
| `manutencoes` | `empresa_id` | `empresa_clientes` | `id` |
| `manutencoes` | `empresa_id` | `empresa_clientes` | `id` |
| `manutencoes` | `status_manutencao_id` | `status_manutencao` | `id` |
| `manutencoes` | `tipo_manutencao_id` | `tipos_manutencao` | `id` |
| `manutencoes` | `veiculo_id` | `veiculos` | `id` |
| `motoristas` | `categoria_cnh_id` | `categorias_cnh` | `id` |
| `motoristas` | `categoria_cnh_id` | `categorias_cnh` | `id` |
| `motoristas` | `disponibilidade_id` | `disponibilidades_motoristas` | `id` |
| `motoristas` | `disponibilidade_id` | `disponibilidades` | `id` |
| `motoristas` | `empresa_id` | `empresa_clientes` | `id` |
| `motoristas` | `tipo_contrato_id` | `tipos_contrato` | `id` |
| `motoristas` | `tipo_contrato_id` | `tipos_contrato` | `id` |
| `motoristas_log` | `motorista_id` | `motoristas` | `id` |
| `motorista_dependentes` | `motorista_id` | `motoristas` | `id` |
| `motorista_periodos` | `motorista_id` | `motoristas` | `id` |
| `motorista_treinamentos` | `motorista_id` | `motoristas` | `id` |
| `multas` | `empresa_id` | `empresa_clientes` | `id` |
| `multas` | `motorista_id` | `motoristas` | `id` |
| `multas` | `rota_id` | `rotas` | `id` |
| `multas` | `veiculo_id` | `veiculos` | `id` |
| `notificacoes_feedback` | `notificacao_id` | `notificacoes` | `id` |
| `notifications` | `user_id` | `usuarios` | `id` |
| `parcelas_financiamento` | `empresa_id` | `empresa_clientes` | `id` |
| `parcelas_financiamento` | `financiamento_id` | `financiamentos` | `id` |
| `parcelas_financiamento` | `forma_pagamento_id` | `formas_pagamento` | `id` |
| `parcelas_financiamento` | `status_id` | `status_parcelas` | `id` |
| `planos_manutencao` | `componente_id` | `componentes_manutencao` | `id` |
| `planos_manutencao` | `empresa_id` | `empresa_clientes` | `id` |
| `planos_manutencao` | `tipo_manutencao_id` | `tipos_manutencao` | `id` |
| `planos_manutencao` | `veiculo_id` | `veiculos` | `id` |
| `pneus` | `empresa_id` | `empresa_clientes` | `id` |
| `pneus` | `status_id` | `status_pneus` | `id` |
| `pneus_alocacao` | `pneu_id` | `pneus` | `id` |
| `pneus_alocacao` | `posicao_id` | `posicoes_pneus` | `id` |
| `pneus_alocacao` | `veiculo_id` | `veiculos` | `id` |
| `pneu_calibragens` | `pneu_id` | `pneus` | `id` |
| `pneu_manutencao` | `pneu_id` | `pneus` | `id` |
| `pneu_manutencao` | `tipo_manutencao_id` | `tipo_manutencao_pneus` | `id` |
| `pneu_manutencao` | `veiculo_id` | `veiculos` | `id` |
| `pneu_medicoes_sulco` | `pneu_id` | `pneus` | `id` |
| `pneu_movimentacoes` | `empresa_id` | `empresa_clientes` | `id` |
| `pneu_movimentacoes` | `pneu_id` | `pneus` | `id` |
| `ranking_motoristas` | `empresa_id` | `empresa_clientes` | `id` |
| `ranking_motoristas` | `motorista_id` | `motoristas` | `id` |
| `recapagens` | `pneu_id` | `pneus` | `id` |
| `recibos` | `empresa_id` | `empresa_clientes` | `id` |
| `recibos` | `usuario_id` | `usuarios` | `id` |
| `rotas` | `cidade_destino_id` | `cidades` | `id` |
| `rotas` | `cidade_origem_id` | `cidades` | `id` |
| `rotas` | `empresa_id` | `empresa_clientes` | `id` |
| `rotas` | `estado_destino` | `estados` | `uf` |
| `rotas` | `estado_origem` | `estados` | `uf` |
| `rotas` | `motorista_id` | `motoristas` | `id` |
| `rotas` | `veiculo_id` | `veiculos` | `id` |
| `seguro_atendimentos` | `seguro_cliente_id` | `seguro_clientes` | `id` |
| `seguro_atendimentos` | `seguro_empresa_id` | `seguro_empresa_clientes` | `id` |
| `seguro_clientes` | `seguro_empresa_id` | `seguro_empresa_clientes` | `id` |
| `seguro_configuracoes` | `seguro_empresa_id` | `seguro_empresa_clientes` | `id` |
| `seguro_contratos_clientes` | `cliente_id` | `seguro_clientes` | `id` |
| `seguro_usuarios` | `seguro_empresa_id` | `seguro_empresa_clientes` | `id` |
| `usuarios` | `empresa_id` | `empresa_clientes` | `id` |
| `usuarios_motoristas` | `empresa_id` | `empresa_clientes` | `id` |
| `usuarios_motoristas` | `motorista_id` | `motoristas` | `id` |
| `veiculos` | `carroceria_id` | `carrocerias` | `id` |
| `veiculos` | `empresa_id` | `empresa_clientes` | `id` |
| `veiculos` | `id_carreta` | `tipos_carretas` | `id` |
| `veiculos` | `id_cavalo` | `tipos_cavalos` | `id` |
| `veiculos` | `status_id` | `status_veiculos` | `id` |
| `veiculos` | `tipo_combustivel_id` | `tipos_combustivel` | `id` |

---

## Tabelas (colunas)

### `abastecimentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `posto` | varchar(100) | NO |  |  |
| `data_abastecimento` | datetime | NO |  |  |
| `litros` | decimal(10,2) | NO |  |  |
| `valor_litro` | decimal(10,2) | NO |  |  |
| `valor_total` | decimal(10,2) | NO |  |  |
| `km_atual` | int(11) | NO |  |  |
| `tipo_combustivel` | varchar(50) | NO |  |  |
| `forma_pagamento` | varchar(50) | NO |  |  |
| `observacoes` | text | YES |  | NULL |
| `comprovante` | varchar(255) | YES |  | NULL |
| `data_cadastro` | timestamp | NO |  | current_timestamp() |
| `rota_id` | int(11) | NO | MUL |  |
| `status` | enum('pendente','aprovado','rejeitado') | YES |  | 'pendente' |
| `fonte` | enum('motorista','gestor') | YES |  | 'motorista' |
| `inclui_arla` | tinyint(1) | YES |  | 0 |
| `litros_arla` | decimal(10,2) | YES |  | NULL |
| `valor_litro_arla` | decimal(10,2) | YES |  | NULL |
| `valor_total_arla` | decimal(10,2) | YES |  | NULL |

### `acessos_conhecidos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `usuario_id` | int(11) | NO | MUL |  |
| `ip` | varchar(45) | NO |  |  |
| `user_agent_hash` | char(40) | NO |  |  |
| `cidade` | varchar(120) | YES |  | NULL |
| `pais` | varchar(8) | YES |  | NULL |
| `primeiro_acesso` | timestamp | NO |  | current_timestamp() |
| `ultimo_acesso` | timestamp | NO |  | current_timestamp() |

### `admin_ip_whitelist`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `ip` | varchar(45) | NO |  |  |
| `descricao` | varchar(160) | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `adm_alertas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `regra` | varchar(64) | NO |  |  |
| `prioridade` | enum('info','aviso','critico','emergencia') | NO | MUL | 'info' |
| `titulo` | varchar(200) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `empresa_adm_id` | int(11) | YES |  | NULL |
| `status` | enum('aberto','reconhecido','resolvido') | NO | MUL | 'aberto' |
| `meta` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |
| `resolvido_em` | timestamp | YES |  | NULL |

### `adm_auditoria_suporte`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `admin_id` | int(11) | NO | MUL |  |
| `usuario_id` | int(11) | NO |  |  |
| `empresa_cliente_id` | int(11) | NO | MUL |  |
| `acao` | varchar(64) | NO |  |  |
| `ip_address` | varchar(45) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `encerrado_em` | timestamp | YES |  | NULL |

### `adm_boletos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `empresa_cliente_id` | int(11) | NO | MUL |  |
| `numero_boleto` | varchar(50) | NO | UNI |  |
| `codigo_barras` | varchar(100) | YES |  | NULL |
| `linha_digitavel` | varchar(100) | YES |  | NULL |
| `descricao` | text | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `data_vencimento` | date | NO | MUL |  |
| `data_emissao` | date | NO |  |  |
| `status` | enum('pendente','pago','vencido','cancelado') | YES | MUL | 'pendente' |
| `data_pagamento` | date | YES |  | NULL |
| `forma_pagamento` | varchar(50) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `mes_referencia` | varchar(7) | YES |  | NULL |
| `tipo_boleto` | enum('mensalidade','adicional','multa','outros') | YES |  | 'mensalidade' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `adm_comunicacao_modelos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `slug` | varchar(64) | NO | UNI |  |
| `nome` | varchar(120) | NO |  |  |
| `canal` | enum('email','whatsapp','push','banner','aviso') | NO |  | 'aviso' |
| `titulo` | varchar(200) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `icone` | varchar(64) | YES |  | 'fa-file-lines' |
| `is_padrao` | tinyint(1) | NO |  | 0 |
| `created_by` | int(11) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `adm_comunicacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `canal` | enum('email','whatsapp','push','banner','aviso') | NO |  | 'aviso' |
| `titulo` | varchar(200) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `empresa_adm_id` | int(11) | YES |  | NULL |
| `agendado_para` | datetime | YES | MUL | NULL |
| `enviado_em` | datetime | YES |  | NULL |
| `status` | enum('rascunho','agendado','enviado','cancelado') | NO | MUL | 'rascunho' |
| `created_by` | int(11) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `adm_config_boletos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | UNI |  |
| `dia_vencimento` | int(11) | NO |  | 10 |
| `gerar_automatico` | tinyint(1) | YES |  | 1 |
| `valor_base` | decimal(10,2) | YES |  | 0.00 |
| `aplicar_por_veiculo` | tinyint(1) | YES |  | 1 |
| `valor_por_veiculo` | decimal(10,2) | YES |  | 0.00 |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `adm_cron_jobs`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `slug` | varchar(64) | NO | UNI |  |
| `nome` | varchar(120) | NO |  |  |
| `script_path` | varchar(255) | NO |  |  |
| `schedule_expr` | varchar(64) | YES |  | NULL |
| `ativo` | tinyint(1) | NO |  | 1 |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `adm_cron_runs`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `job_id` | int(11) | NO | MUL |  |
| `status` | enum('executando','pendente','concluido','falhou') | NO | MUL | 'pendente' |
| `duracao_ms` | int(11) | YES |  | NULL |
| `mensagem` | text | YES |  | NULL |
| `iniciado_em` | timestamp | YES |  | NULL |
| `concluido_em` | timestamp | YES |  | NULL |
| `proxima_execucao` | datetime | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_custos_mensais`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `categoria` | varchar(64) | NO | MUL |  |
| `valor` | decimal(12,2) | NO |  | 0.00 |
| `referencia_mes` | char(7) | NO |  |  |
| `observacao` | varchar(255) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `adm_deploys`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `ambiente` | enum('producao','homologacao','desenvolvimento') | NO | MUL | 'producao' |
| `versao` | varchar(32) | NO |  |  |
| `git_hash` | varchar(64) | YES | MUL | NULL |
| `git_autor` | varchar(120) | YES |  | NULL |
| `git_mensagem` | varchar(500) | YES |  | NULL |
| `admin_id` | int(11) | YES |  | NULL |
| `admin_nome` | varchar(120) | YES |  | NULL |
| `duracao_seg` | int(11) | YES |  | NULL |
| `arquivos_alterados` | int(11) | YES |  | 0 |
| `sql_aplicado` | tinyint(1) | NO |  | 0 |
| `rollback_disponivel` | tinyint(1) | NO |  | 0 |
| `meta` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_empresa_modulos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `modulo_id` | int(11) | NO | MUL |  |
| `ativo` | tinyint(1) | NO |  | 1 |
| `ativado_em` | timestamp | NO |  | current_timestamp() |

### `adm_empresa_scores`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `nota_geral` | tinyint(3) unsigned | NO | MUL | 0 |
| `seguranca` | tinyint(3) unsigned | NO |  | 0 |
| `uso` | tinyint(3) unsigned | NO |  | 0 |
| `financeiro` | tinyint(3) unsigned | NO |  | 0 |
| `performance` | tinyint(3) unsigned | NO |  | 0 |
| `treinamento` | tinyint(3) unsigned | NO |  | 0 |
| `explicacao` | text | YES |  | NULL |
| `calculado_em` | timestamp | NO |  | current_timestamp() |

### `adm_eventos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `empresa_cliente_id` | int(11) | YES | MUL | NULL |
| `empresa_adm_id` | int(11) | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `admin_id` | int(11) | YES |  | NULL |
| `modulo` | varchar(64) | NO | MUL | 'sistema' |
| `tipo` | varchar(64) | NO | MUL | 'info' |
| `severidade` | enum('info','aviso','erro','critico') | NO | MUL | 'info' |
| `mensagem` | varchar(500) | NO |  |  |
| `meta` | longtext | YES |  | NULL |
| `ip_address` | varchar(45) | YES |  | NULL |
| `user_agent` | varchar(500) | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_health_checks`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `servico` | varchar(64) | NO | MUL |  |
| `status` | enum('ok','degradado','falha','desconhecido') | NO |  | 'desconhecido' |
| `latencia_ms` | int(11) | YES |  | NULL |
| `detalhe` | varchar(500) | YES |  | NULL |
| `checked_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_ia_usage`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `empresa_cliente_id` | int(11) | YES | MUL | NULL |
| `modelo` | varchar(64) | YES |  | NULL |
| `tokens_in` | int(11) | YES |  | NULL |
| `tokens_out` | int(11) | YES |  | NULL |
| `duracao_ms` | int(11) | YES |  | NULL |
| `custo_estimado` | decimal(10,4) | YES |  | NULL |
| `sucesso` | tinyint(1) | NO |  | 1 |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_integration_checks`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `integracao` | varchar(64) | NO | MUL |  |
| `status` | enum('ok','degradado','falha','desconhecido') | NO |  | 'desconhecido' |
| `latencia_ms` | int(11) | YES |  | NULL |
| `erro` | varchar(500) | YES |  | NULL |
| `limite_diario` | int(11) | YES |  | NULL |
| `uso_hoje` | int(11) | YES |  | 0 |
| `checked_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_licenca_limites`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `recurso` | enum('veiculos','motoristas','usuarios','ia','storage_mb','api_calls') | NO |  |  |
| `limite_valor` | int(11) | YES |  | NULL |
| `uso_valor` | int(11) | NO |  | 0 |
| `overage_valor` | decimal(10,2) | YES |  | NULL |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `adm_modulos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `slug` | varchar(64) | NO | UNI |  |
| `nome` | varchar(120) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `icone` | varchar(64) | YES |  | 'fa-puzzle-piece' |
| `ativo_global` | tinyint(1) | NO |  | 1 |
| `ordem` | int(11) | NO |  | 0 |

### `adm_pagamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `boleto_id` | int(11) | NO | MUL |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `empresa_cliente_id` | int(11) | NO |  |  |
| `valor_pago` | decimal(10,2) | NO |  |  |
| `data_pagamento` | date | NO |  |  |
| `forma_pagamento` | varchar(50) | NO |  |  |
| `comprovante_path` | varchar(255) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `registrado_por` | int(11) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `adm_planos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `tipo` | enum('avulso','pacote') | NO | MUL | 'pacote' |
| `limite_veiculos` | int(11) | YES |  | NULL |
| `valor_por_veiculo` | decimal(10,2) | NO |  |  |
| `valor_maximo` | decimal(10,2) | YES |  | NULL |
| `ordem` | int(11) | YES | MUL | 0 |
| `status` | enum('ativo','inativo') | YES | MUL | 'ativo' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `adm_platform_config`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `chave` | varchar(64) | NO | UNI |  |
| `valor` | text | YES |  | NULL |
| `categoria` | varchar(32) | NO |  | 'sistema' |
| `sensivel` | tinyint(1) | NO |  | 0 |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `adm_request_metrics`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `rota` | varchar(255) | NO | MUL |  |
| `modulo` | varchar(64) | YES | MUL | NULL |
| `duracao_ms` | int(11) | NO |  |  |
| `memoria_kb` | int(11) | YES |  | NULL |
| `empresa_cliente_id` | int(11) | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_roadmap`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `versao` | varchar(32) | NO |  |  |
| `titulo` | varchar(200) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `status` | enum('concluido','desenvolvimento','planejado','beta') | NO | MUL | 'planejado' |
| `progresso` | tinyint(3) unsigned | NO |  | 0 |
| `ordem` | int(11) | NO | MUL | 0 |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `adm_sessoes_ativas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `session_id` | varchar(128) | NO | UNI |  |
| `usuario_id` | int(11) | YES |  | NULL |
| `admin_id` | int(11) | YES | MUL | NULL |
| `empresa_cliente_id` | int(11) | YES | MUL | NULL |
| `empresa_adm_id` | int(11) | YES |  | NULL |
| `pagina` | varchar(255) | YES |  | NULL |
| `user_agent` | varchar(500) | YES |  | NULL |
| `ip_address` | varchar(45) | YES |  | NULL |
| `cidade` | varchar(120) | YES |  | NULL |
| `ultimo_ping` | timestamp | NO | MUL | current_timestamp() |
| `iniciado_em` | timestamp | NO |  | current_timestamp() |

### `adm_sql_log`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `tipo` | enum('SELECT','INSERT','UPDATE','DELETE','OTHER') | NO |  | 'OTHER' |
| `rota` | varchar(255) | YES |  | NULL |
| `modulo` | varchar(64) | YES | MUL | NULL |
| `duracao_ms` | int(11) | NO | MUL |  |
| `empresa_cliente_id` | int(11) | YES |  | NULL |
| `detalhe` | varchar(500) | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `adm_storage_usage`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | UNI |  |
| `total_mb` | int(11) | NO |  | 0 |
| `fotos_mb` | int(11) | NO |  | 0 |
| `documentos_mb` | int(11) | NO |  | 0 |
| `outros_mb` | int(11) | NO |  | 0 |
| `calculado_em` | timestamp | NO | MUL | current_timestamp() |

### `alertas_inteligentes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(50) | NO | MUL |  |
| `titulo` | varchar(255) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `prioridade` | enum('baixa','media','alta') | YES | MUL | 'media' |
| `dados` | longtext | YES |  | NULL |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_resolucao` | timestamp | YES |  | NULL |
| `status` | enum('ativo','resolvido','ignorado') | YES |  | 'ativo' |

### `alertas_sistema`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(50) | NO | MUL |  |
| `prioridade` | enum('baixa','media','alta','critica') | YES |  | 'media' |
| `titulo` | varchar(255) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `dados` | longtext | YES |  | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `motorista_id` | int(11) | YES |  | NULL |
| `rota_id` | int(11) | YES |  | NULL |
| `status` | enum('ativo','tratado','resolvido','ignorado') | YES |  | 'ativo' |
| `data_criacao` | timestamp | NO | MUL | current_timestamp() |
| `data_tratamento` | timestamp | YES |  | NULL |
| `data_resolucao` | timestamp | YES |  | NULL |
| `usuario_tratamento` | int(11) | YES |  | NULL |
| `usuario_resolucao` | int(11) | YES |  | NULL |
| `observacoes_tratamento` | text | YES |  | NULL |
| `observacoes_resolucao` | text | YES |  | NULL |

### `alocacoes_pneus_flexiveis`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `eixo_veiculo_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `posicao_slot` | int(11) | NO |  |  |
| `posicao_id` | int(11) | YES | MUL | NULL |
| `slot_id` | varchar(50) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `data_alocacao` | timestamp | NO |  | current_timestamp() |
| `data_remocao` | timestamp | YES |  | NULL |
| `ativo` | tinyint(1) | NO | MUL | 1 |
| `data_criacao` | datetime | YES |  | current_timestamp() |

### `antt_frete_coeficientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `vigencia` | date | NO | MUL |  |
| `tipo_carga` | varchar(40) | NO |  |  |
| `eixos` | tinyint(3) unsigned | NO |  |  |
| `ccd` | decimal(12,4) | NO |  |  |
| `cc` | decimal(12,2) | NO |  |  |
| `contratacao` | enum('COMPOSICAO','TRACAO') | NO |  | 'COMPOSICAO' |
| `alto_desempenho` | char(1) | NO |  | 'N' |
| `ativo` | char(1) | NO |  | 'S' |

### `api_tokens_motorista`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO |  |  |
| `token` | varchar(64) | NO | UNI |  |
| `refresh_token` | varchar(64) | YES | UNI | NULL |
| `criado_em` | datetime | YES |  | current_timestamp() |
| `expira_em` | datetime | NO | MUL |  |
| `refresh_expira_em` | datetime | YES |  | NULL |

### `aprovacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `entity_type` | varchar(40) | NO | MUL |  |
| `entity_id` | int(11) | NO |  |  |
| `entity_ref` | varchar(40) | YES |  | NULL |
| `descricao` | varchar(255) | YES |  | NULL |
| `decisao` | enum('aprovado','rejeitado') | NO |  |  |
| `aprovador_id` | int(11) | YES |  | NULL |
| `aprovador_nome` | varchar(120) | YES |  | NULL |
| `comentario` | text | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |

### `auth_remember_tokens`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `usuario_id` | int(11) | NO | MUL |  |
| `selector` | char(24) | NO | UNI |  |
| `validator_hash` | char(64) | NO |  |  |
| `expira_em` | datetime | NO | MUL |  |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `bancos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |

### `bi_cache`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `cache_key` | varchar(120) | NO |  |  |
| `payload` | longtext | NO |  |  |
| `expires_at` | datetime | YES | MUL | NULL |
| `created_at` | datetime | YES |  | current_timestamp() |

### `bi_metas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(11) | NO | PRI |  |
| `meta_receita` | decimal(15,2) | YES |  | NULL |
| `meta_lucro` | decimal(15,2) | YES |  | NULL |
| `meta_km` | decimal(15,2) | YES |  | NULL |
| `meta_margem_pct` | decimal(5,2) | YES |  | 20.00 |
| `updated_at` | datetime | YES |  | current_timestamp() |

### `calendario_eventos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `titulo` | varchar(255) | NO |  |  |
| `categoria_id` | int(11) | NO | MUL |  |
| `data_inicio` | datetime | NO | MUL |  |
| `data_fim` | datetime | YES |  | NULL |
| `descricao` | text | YES |  | NULL |
| `cor` | varchar(7) | YES |  | NULL |
| `empresa_id` | int(11) | NO | MUL |  |
| `usuario_id` | int(11) | YES |  | NULL |
| `pago` | tinyint(1) | YES | MUL | 0 |
| `data_pagamento` | datetime | YES |  | NULL |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |
| `origem_tipo` | enum('multa','cnh','personalizado') | YES | MUL | 'personalizado' |
| `origem_id` | int(11) | YES |  | NULL |
| `sincronizado` | tinyint(1) | YES |  | 0 |

### `calendario_templates`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `name` | varchar(255) | NO | MUL |  |
| `category` | varchar(100) | NO | MUL |  |
| `description` | text | YES |  | NULL |
| `color` | varchar(7) | YES |  | '#3788d8' |
| `duration` | int(11) | YES |  | 1 |
| `reminder` | int(11) | YES |  | 0 |
| `tags` | text | YES |  | NULL |
| `icon` | varchar(100) | YES |  | 'fas fa-calendar' |
| `favorite` | tinyint(1) | YES | MUL | 0 |
| `usage_count` | int(11) | YES |  | 0 |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `carrocerias`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |

### `categorias`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `empresa_id` | int(11) | NO |  |  |
| `status` | enum('ativo','inativo') | YES |  | 'ativo' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `categorias_calendario`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `cor_padrao` | varchar(7) | YES |  | '#3788d8' |
| `icone` | varchar(50) | YES |  | 'fas fa-calendar' |
| `ativo` | tinyint(1) | YES |  | 1 |
| `empresa_id` | int(11) | NO | MUL |  |
| `data_criacao` | timestamp | NO |  | current_timestamp() |

### `categorias_cnh`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(5) | NO |  |  |

### `cavalos_carretas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `id_cavalo` | int(11) | NO | MUL |  |
| `id_carreta` | int(11) | NO | MUL |  |

### `certificados_denatran`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome_certificado` | varchar(255) | NO |  |  |
| `arquivo_certificado` | varchar(255) | NO |  |  |
| `arquivo_chave` | varchar(255) | YES |  | NULL |
| `senha_criptografada` | varchar(255) | YES |  | NULL |
| `data_validade` | date | YES |  | NULL |
| `data_upload` | datetime | YES |  | current_timestamp() |
| `ativo` | tinyint(1) | NO |  | 1 |

### `checklist_viagem`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `rota_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `oleo_motor` | tinyint(1) | YES |  | NULL |
| `agua_radiador` | tinyint(1) | YES |  | NULL |
| `fluido_freio` | tinyint(1) | YES |  | NULL |
| `fluido_direcao` | tinyint(1) | YES |  | NULL |
| `combustivel` | tinyint(1) | YES |  | NULL |
| `pneus` | tinyint(1) | YES |  | NULL |
| `estepe` | tinyint(1) | YES |  | NULL |
| `luzes` | tinyint(1) | YES |  | NULL |
| `buzina` | tinyint(1) | YES |  | NULL |
| `limpador_para_brisa` | tinyint(1) | YES |  | NULL |
| `agua_limpador` | tinyint(1) | YES |  | NULL |
| `freios` | tinyint(1) | YES |  | NULL |
| `vazamentos` | tinyint(1) | YES |  | NULL |
| `rastreador` | tinyint(1) | YES |  | NULL |
| `triangulo` | tinyint(1) | YES |  | NULL |
| `extintor` | tinyint(1) | YES |  | NULL |
| `chave_macaco` | tinyint(1) | YES |  | NULL |
| `cintas` | tinyint(1) | YES |  | NULL |
| `primeiros_socorros` | tinyint(1) | YES |  | NULL |
| `doc_veiculo` | tinyint(1) | YES |  | NULL |
| `cnh` | tinyint(1) | YES |  | NULL |
| `licenciamento` | tinyint(1) | YES |  | NULL |
| `seguro` | tinyint(1) | YES |  | NULL |
| `manifesto_carga` | tinyint(1) | YES |  | NULL |
| `doc_empresa` | tinyint(1) | YES |  | NULL |
| `carga_amarrada` | tinyint(1) | YES |  | NULL |
| `peso_correto` | tinyint(1) | YES |  | NULL |
| `motorista_descansado` | tinyint(1) | YES |  | NULL |
| `motorista_sobrio` | tinyint(1) | YES |  | NULL |
| `celular_carregado` | tinyint(1) | YES |  | NULL |
| `epi` | tinyint(1) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `assinatura_path` | varchar(255) | YES |  | NULL |
| `fonte` | enum('motorista','gestor') | YES |  | 'motorista' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `data_checklist` | datetime | YES |  | current_timestamp() |
| `quinta_roda` | tinyint(1) | NO |  | 0 |
| `mangueiras` | tinyint(1) | NO |  | 0 |
| `travas` | tinyint(1) | NO |  | 0 |
| `lona_carga` | tinyint(1) | NO |  | 0 |

### `cidades`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `uf` | varchar(2) | YES | MUL | NULL |
| `nome` | varchar(100) | NO |  |  |
| `codigo_ibge` | varchar(7) | YES |  | NULL |
| `latitude` | decimal(10,8) | YES |  | NULL |
| `longitude` | decimal(11,8) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_pessoa` | enum('PJ','PF') | NO |  | 'PJ' |
| `razao_social` | varchar(160) | NO |  |  |
| `nome_fantasia` | varchar(160) | YES |  | NULL |
| `cnpj_cpf` | varchar(20) | YES |  | NULL |
| `inscricao_estadual` | varchar(30) | YES |  | NULL |
| `contato_nome` | varchar(120) | YES |  | NULL |
| `telefone` | varchar(30) | YES |  | NULL |
| `email` | varchar(120) | YES |  | NULL |
| `endereco` | varchar(200) | YES |  | NULL |
| `cidade` | varchar(120) | YES |  | NULL |
| `uf` | varchar(2) | YES |  | NULL |
| `status` | enum('ativo','prospect','inativo') | NO |  | 'ativo' |
| `observacoes` | text | YES |  | NULL |
| `criado_por` | int(11) | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `comissoes_pagamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `rota_id` | int(11) | NO | UNI |  |
| `status` | enum('pago','pendente') | NO |  | 'pendente' |
| `valor` | decimal(10,2) | YES |  | NULL |
| `data_pagamento` | date | YES |  | NULL |
| `observacao` | text | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `competicoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(255) | NO |  |  |
| `tipo` | enum('semanal','mensal','especial') | YES |  | 'mensal' |
| `periodo_inicio` | date | NO | MUL |  |
| `periodo_fim` | date | NO |  |  |
| `premio` | text | NO |  |  |
| `criterios` | longtext | NO |  |  |
| `status` | enum('ativa','finalizada','cancelada') | YES | MUL | 'ativa' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `componentes_manutencao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |

### `comprovantes_entrega`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `recebedor_nome` | varchar(120) | YES |  | NULL |
| `recebedor_doc` | varchar(40) | YES |  | NULL |
| `foto_nf_path` | varchar(255) | YES |  | NULL |
| `foto_carga_path` | varchar(255) | YES |  | NULL |
| `assinatura_path` | varchar(255) | YES |  | NULL |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `data_hora` | datetime | NO |  |  |
| `observacoes` | varchar(255) | YES |  | NULL |
| `status` | varchar(20) | NO |  | 'entregue' |
| `fonte` | varchar(20) | NO |  | 'motorista' |
| `created_at` | datetime | NO |  | current_timestamp() |

### `conferencia_carga`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `tipo` | varchar(12) | NO |  | 'coleta' |
| `nf_numero` | varchar(40) | YES |  | NULL |
| `nf_valor` | decimal(12,2) | YES |  | NULL |
| `volumes` | int(11) | YES |  | NULL |
| `lacre` | varchar(60) | YES |  | NULL |
| `conforme` | tinyint(1) | NO |  | 1 |
| `observacoes` | text | YES |  | NULL |
| `foto_path` | varchar(255) | YES |  | NULL |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `data_hora` | datetime | NO | MUL |  |
| `fonte` | varchar(20) | NO |  | 'motorista' |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `configuracoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `cor_menu` | varchar(7) | NO |  | '#343a40' |
| `nome_personalizado` | varchar(255) | NO |  |  |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |
| `logo_empresa` | varchar(255) | YES |  | NULL |
| `certificado_a1_id` | int(11) | YES | MUL | NULL |
| `email_notificacoes` | varchar(255) | YES |  | NULL |
| `notificar_abastecimentos` | tinyint(1) | YES |  | 0 |
| `notificar_manutencoes` | tinyint(1) | YES |  | 0 |
| `notificar_viagens` | tinyint(1) | YES |  | 0 |
| `limite_km_manutencao` | int(11) | YES |  | 0 |
| `limite_dias_manutencao` | int(11) | YES |  | 0 |
| `notificar_pneus_vida_util` | tinyint(1) | YES |  | 0 |
| `notificar_pneus_recapagem` | tinyint(1) | YES |  | 0 |
| `notificar_pneus_troca_frequente` | tinyint(1) | YES |  | 0 |
| `calcular_todas_despesas` | tinyint(1) | NO |  | 1 |
| `calcular_despesas_fixas` | tinyint(1) | NO |  | 0 |
| `calcular_despesas_viagem` | tinyint(1) | NO |  | 0 |
| `calcular_abastecimentos` | tinyint(1) | NO |  | 0 |
| `calcular_manutencao` | tinyint(1) | NO |  | 0 |
| `calcular_manutencao_pneus` | tinyint(1) | NO |  | 0 |
| `calcular_comissoes` | tinyint(1) | NO |  | 0 |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `google_maps_api_key` | varchar(500) | YES |  | NULL |
| `openai_api_key` | varchar(512) | YES |  | NULL |
| `openai_vision_model` | varchar(80) | YES |  | NULL |
| `openai_monthly_budget_usd` | decimal(10,2) | YES |  | NULL |
| `azure_docintel_endpoint` | varchar(255) | YES |  | NULL |
| `azure_docintel_key` | text | YES |  | NULL |
| `azure_docintel_model` | varchar(80) | YES |  | NULL |
| `azure_docintel_api_version` | varchar(30) | YES |  | NULL |
| `google_vision_api_key` | text | YES |  | NULL |

### `configuracoes_badges`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `badge_motorista_economico` | int(11) | YES |  | 3 |
| `badge_sem_multas` | int(11) | YES |  | 12 |
| `badge_checklists_perfeitos` | int(11) | YES |  | 50 |
| `pontos_por_badge` | int(11) | YES |  | 50 |
| `sistema_badges_ativo` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_cache`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `cache_ttl` | int(11) | YES |  | 5 |
| `cache_max_size` | int(11) | YES |  | 100 |
| `background_intervalo` | int(11) | YES |  | 30 |
| `background_timeout` | int(11) | YES |  | 10 |
| `cache_ativo` | tinyint(1) | YES |  | 1 |
| `background_ativo` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_denatran`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `habilitado` | tinyint(1) | NO |  | 0 |
| `base_url` | varchar(500) | NO |  | 'https://wsdenatrandes-des07116.apps.dev.serpro' |
| `cpf_usuario` | varchar(20) | YES |  | NULL |
| `cert_path` | varchar(500) | YES |  | NULL |
| `key_path` | varchar(500) | YES |  | NULL |
| `key_pass` | varchar(255) | YES |  | NULL |
| `data_criacao` | datetime | YES |  | current_timestamp() |
| `data_atualizacao` | datetime | YES |  | NULL |
| `certificado_denatran_id` | int(11) | YES |  | NULL |

### `configuracoes_desafios`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `desafio_km_sem_infracoes` | int(11) | YES |  | 5000 |
| `desafio_rotas_sem_atrasos` | int(11) | YES |  | 10 |
| `desafio_economia_combustivel` | decimal(5,2) | YES |  | 15.00 |
| `pontos_desafio_completo` | int(11) | YES |  | 100 |
| `desafios_ativos` | tinyint(1) | YES |  | 1 |
| `feedback_imediato` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_filtros`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `periodo_padrao` | varchar(20) | YES |  | 'mensal' |
| `filtro_por_veiculo` | varchar(20) | YES |  | 'todos' |
| `filtro_por_rota` | varchar(20) | YES |  | 'todas' |
| `comparacao_filiais` | varchar(20) | YES |  | 'desabilitada' |
| `filtros_ativos` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_ia`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(50) | NO |  |  |
| `valor` | text | NO |  |  |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_metricas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `peso_ocorrencias_sinistros` | int(11) | YES |  | 15 |
| `peso_custos_km` | int(11) | YES |  | 10 |
| `peso_feedback_cliente` | int(11) | YES |  | 10 |
| `peso_manutencao_preventiva` | int(11) | YES |  | 5 |
| `metricas_avancadas_ativas` | tinyint(1) | YES |  | 0 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_niveis`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `nivel_bronze_min` | int(11) | YES |  | 0 |
| `nivel_bronze_max` | int(11) | YES |  | 99 |
| `nivel_prata_min` | int(11) | YES |  | 100 |
| `nivel_prata_max` | int(11) | YES |  | 299 |
| `nivel_ouro_min` | int(11) | YES |  | 300 |
| `nivel_ouro_max` | int(11) | YES |  | 599 |
| `nivel_platina_min` | int(11) | YES |  | 600 |
| `nivel_platina_max` | int(11) | YES |  | 899 |
| `nivel_diamante_min` | int(11) | YES |  | 900 |
| `nivel_diamante_max` | int(11) | YES |  | 999 |
| `nivel_lenda_min` | int(11) | YES |  | 1000 |
| `nivel_lenda_max` | int(11) | YES |  | 9999 |
| `niveis_avancados_ativos` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_notificacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `notificacoes_badges` | tinyint(1) | YES |  | 1 |
| `notificacoes_niveis` | tinyint(1) | YES |  | 1 |
| `notificacoes_ranking` | tinyint(1) | YES |  | 1 |
| `notificacoes_desafios` | tinyint(1) | YES |  | 1 |
| `sistema_notificacoes_ativo` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_performance`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `peso_pontualidade` | int(11) | YES |  | 25 |
| `peso_consumo` | int(11) | YES |  | 30 |
| `peso_multas` | int(11) | YES |  | 20 |
| `peso_checklist` | int(11) | YES |  | 15 |
| `peso_ocorrencias` | int(11) | YES |  | 10 |
| `pontos_maximos` | int(11) | YES |  | 1000 |
| `gamificacao_ativa` | tinyint(1) | YES |  | 1 |
| `ranking_automatico` | tinyint(1) | YES |  | 1 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `configuracoes_seguranca`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(11) | NO | PRI |  |
| `idle_timeout_min` | int(11) | NO |  | 0 |
| `login_max_tentativas` | int(11) | NO |  | 5 |
| `login_bloqueio_min` | int(11) | NO |  | 15 |
| `senha_forte` | tinyint(1) | NO |  | 1 |
| `two_factor_obrigatorio` | tinyint(1) | NO |  | 0 |
| `recaptcha_enabled` | tinyint(1) | NO |  | 0 |
| `recaptcha_site_key` | varchar(120) | YES |  | NULL |
| `recaptcha_secret_key` | varchar(120) | YES |  | NULL |
| `recaptcha_apos_tentativas` | int(11) | NO |  | 3 |
| `novo_acesso_email` | tinyint(1) | NO |  | 0 |
| `smtp_host` | varchar(160) | YES |  | NULL |
| `smtp_port` | int(11) | YES |  | NULL |
| `smtp_user` | varchar(160) | YES |  | NULL |
| `smtp_pass` | varchar(255) | YES |  | NULL |
| `smtp_secure` | varchar(10) | YES |  | NULL |
| `smtp_from` | varchar(160) | YES |  | NULL |
| `ip_whitelist_enabled` | tinyint(1) | NO |  | 0 |
| `geo_block_enabled` | tinyint(1) | NO |  | 0 |
| `geo_paises_permitidos` | varchar(255) | NO |  | 'BR' |
| `atualizado_em` | timestamp | NO |  | current_timestamp() |

### `config_ranking`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `peso_consumo` | decimal(5,2) | YES |  | 30.00 |
| `peso_pontualidade` | decimal(5,2) | YES |  | 25.00 |
| `peso_multas` | decimal(5,2) | YES |  | 20.00 |
| `peso_ocorrencias` | decimal(5,2) | YES |  | 15.00 |
| `peso_checklist` | decimal(5,2) | YES |  | 10.00 |
| `periodo_calculo` | enum('semanal','mensal','trimestral') | YES |  | 'mensal' |
| `dias_historico` | int(11) | YES |  | 30 |
| `minimo_rotas_avaliacao` | int(11) | YES |  | 5 |
| `pontos_por_pontualidade` | int(11) | YES |  | 10 |
| `pontos_por_consumo_eficiente` | int(11) | YES |  | 15 |
| `pontos_por_checklist` | int(11) | YES |  | 5 |
| `pontos_sem_multas_mes` | int(11) | YES |  | 20 |
| `pontos_nivel_bronze` | int(11) | YES |  | 0 |
| `pontos_nivel_prata` | int(11) | YES |  | 100 |
| `pontos_nivel_ouro` | int(11) | YES |  | 300 |
| `pontos_nivel_diamante` | int(11) | YES |  | 600 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `conquistas_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `desafio_id` | int(11) | YES | MUL | NULL |
| `tipo_conquista` | enum('badge','nivel','desafio','bonus') | NO |  |  |
| `nome_conquista` | varchar(100) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `pontos_recebidos` | int(11) | YES |  | 0 |
| `badge_recebida` | varchar(50) | YES |  | NULL |
| `bonus_recebido` | decimal(10,2) | YES |  | 0.00 |
| `data_conquista` | timestamp | NO |  | current_timestamp() |
| `periodo_referencia` | date | NO |  |  |

### `contas_pagar`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `fornecedor_id` | bigint(20) unsigned | YES |  | NULL |
| `fornecedor` | varchar(255) | NO |  |  |
| `descricao` | varchar(255) | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `data_vencimento` | date | NO |  |  |
| `data_pagamento` | date | YES |  | NULL |
| `status_id` | int(11) | NO | MUL | 1 |
| `forma_pagamento_id` | int(11) | YES | MUL | NULL |
| `banco_id` | int(11) | YES | MUL | NULL |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `recibo_arquivo` | varchar(255) | YES |  | NULL |

### `desafios_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(100) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `tipo` | enum('diario','semanal','mensal','trimestral') | YES | MUL | 'semanal' |
| `criterio_tipo` | enum('pontualidade','consumo','multas','checklist','eficiencia','combinado') | NO |  |  |
| `criterio_valor` | decimal(10,2) | NO |  |  |
| `criterio_periodo` | int(11) | YES |  | 7 |
| `pontos_recompensa` | int(11) | YES |  | 0 |
| `badge_recompensa` | varchar(50) | YES |  | NULL |
| `bonus_recompensa` | decimal(10,2) | YES |  | 0.00 |
| `ativo` | tinyint(1) | YES |  | 1 |
| `data_inicio` | date | NO |  |  |
| `data_fim` | date | YES |  | NULL |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `despesas_fixas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `valor` | decimal(10,2) | NO |  |  |
| `vencimento` | date | YES |  | NULL |
| `repetir_automaticamente` | tinyint(1) | YES |  | 0 |
| `ano_referencia` | year(4) | NO |  |  |
| `mes_pago` | tinyint(4) | YES |  | NULL |
| `status_pagamento_id` | int(11) | YES | MUL | 1 |
| `data_pagamento` | date | YES |  | NULL |
| `comprovante` | varchar(255) | YES |  | NULL |
| `descricao` | text | YES |  | NULL |
| `data_aprovacao` | datetime | YES |  | NULL |
| `notificar_vencimento` | tinyint(1) | YES |  | 0 |
| `notificado_em` | datetime | YES |  | NULL |
| `created_at` | datetime | YES |  | current_timestamp() |
| `tipo_despesa_id` | int(11) | YES | MUL | NULL |
| `forma_pagamento_id` | int(11) | NO | MUL |  |

### `despesas_viagem`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `rota_id` | int(11) | NO | MUL |  |
| `descarga` | decimal(10,2) | YES |  | NULL |
| `pedagios` | decimal(10,2) | YES |  | NULL |
| `caixinha` | decimal(10,2) | YES |  | NULL |
| `estacionamento` | decimal(10,2) | YES |  | NULL |
| `lavagem` | decimal(10,2) | YES |  | NULL |
| `borracharia` | decimal(10,2) | YES |  | NULL |
| `eletrica_mecanica` | decimal(10,2) | YES |  | NULL |
| `adiantamento` | decimal(10,2) | YES |  | NULL |
| `outros` | decimal(10,2) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `total_despviagem` | decimal(10,2) | YES |  | NULL |
| `status` | enum('pendente','aprovado','rejeitado') | YES |  | 'pendente' |
| `fonte` | enum('motorista','gestor') | YES |  | 'motorista' |

### `disponibilidades`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `disponibilidades_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |

### `documentos_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(50) | NO |  |  |
| `arquivo` | varchar(255) | NO |  |  |
| `data_validade` | date | YES |  | NULL |
| `status` | varchar(50) | YES |  | 'V?lido' |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `eixos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `posicao_id` | int(11) | NO | MUL |  |
| `quantidade_pneus` | int(11) | YES |  | 2 |

### `eixos_veiculos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `tipo_veiculo` | enum('caminhao','carreta') | NO |  |  |
| `numero_eixo` | int(11) | NO |  |  |
| `quantidade_pneus` | int(11) | NO |  | 2 |
| `empresa_id` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `eixo_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `eixo_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | YES | MUL | NULL |
| `posicao_id` | int(11) | YES | MUL | NULL |
| `data_alocacao` | date | NO |  |  |
| `km_alocacao` | decimal(10,2) | NO |  |  |
| `data_desalocacao` | date | YES |  | NULL |
| `km_desalocacao` | decimal(10,2) | YES |  | NULL |
| `status` | enum('alocado','desalocado') | YES |  | 'alocado' |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `empresa_adm`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `razao_social` | varchar(255) | NO |  |  |
| `cnpj` | varchar(18) | NO | UNI |  |
| `telefone` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | NO | UNI |  |
| `status_saas` | enum('trial','ativa','bloqueada','cancelada','suspensa') | NO |  | 'ativa' |
| `trial_ate` | date | YES |  | NULL |
| `bloqueado_motivo` | text | YES |  | NULL |
| `cancelada_em` | date | YES |  | NULL |
| `valor_por_veiculo` | decimal(10,2) | YES |  | 0.00 |
| `data_ultima_cobranca` | date | YES |  | NULL |
| `plano` | varchar(50) | YES |  | NULL |
| `plano_id` | int(11) | YES | MUL | NULL |
| `tem_acesso_seguro` | enum('sim','nao') | YES |  | 'nao' |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `cidade` | varchar(120) | YES |  | NULL |
| `estado` | char(2) | YES |  | NULL |
| `created_at` | timestamp | YES |  | current_timestamp() |

### `empresa_clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `razao_social` | varchar(255) | NO |  |  |
| `nome_fantasia` | varchar(255) | YES |  | NULL |
| `cnpj` | varchar(18) | NO |  |  |
| `inscricao_estadual` | varchar(50) | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | YES |  | NULL |
| `endereco` | varchar(255) | YES |  | NULL |
| `cidade` | varchar(100) | YES |  | NULL |
| `estado` | varchar(2) | YES |  | NULL |
| `cep` | varchar(10) | YES |  | NULL |
| `responsavel` | varchar(100) | YES |  | NULL |
| `logo` | varchar(255) | YES |  | NULL |
| `data_atualizacao` | datetime | YES |  | current_timestamp() |
| `status` | enum('ativo','inativo') | NO |  | 'ativo' |

### `empresa_openai_credentials`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(10) unsigned | NO | PRI |  |
| `api_key` | mediumtext | NO |  |  |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `empresa_vision_ai`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(10) unsigned | NO | PRI |  |
| `purpose` | varchar(20) | NO | PRI | 'vision' |
| `provider` | varchar(20) | NO |  | 'gemini' |
| `api_key` | mediumtext | YES |  | NULL |
| `model` | varchar(80) | YES |  | NULL |
| `base_url` | varchar(255) | YES |  | NULL |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `estados`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `uf` | char(2) | NO | UNI |  |
| `nome` | varchar(50) | NO |  |  |
| `regiao` | varchar(20) | NO |  |  |
| `map_x` | int(11) | YES |  | NULL |
| `map_y` | int(11) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `estoque_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `status_id` | int(11) | YES | MUL | NULL |
| `disponivel` | tinyint(1) | YES |  | 1 |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | datetime | YES |  | current_timestamp() |

### `filiais`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(120) | NO |  |  |
| `codigo` | varchar(30) | YES |  | NULL |
| `ativo` | tinyint(1) | NO |  | 1 |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `financiamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `banco_id` | int(11) | NO | MUL |  |
| `valor_total` | decimal(10,2) | NO |  |  |
| `numero_parcelas` | int(11) | NO |  |  |
| `valor_parcela` | decimal(10,2) | NO |  |  |
| `data_inicio` | date | NO |  |  |
| `taxa_juros` | decimal(5,2) | YES |  | NULL |
| `status_pagamento_id` | int(11) | NO | MUL | 1 |
| `observacoes` | text | YES |  | NULL |
| `contrato` | varchar(255) | YES |  | NULL |
| `data_proxima_parcela` | date | YES |  | NULL |
| `empresa_id` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_alertas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_alerta` | enum('certificado_vencendo','mdfe_nao_encerrado','erro_sefaz','documento_pendente','backup_necessario') | NO | MUL |  |
| `titulo` | varchar(255) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `nivel` | enum('baixo','medio','alto','critico') | YES | MUL | 'medio' |
| `status` | enum('ativo','resolvido','ignorado') | YES | MUL | 'ativo' |
| `data_criacao` | timestamp | NO | MUL | current_timestamp() |
| `data_resolucao` | timestamp | YES |  | NULL |
| `usuario_resolucao` | int(11) | YES |  | NULL |
| `acao_requerida` | text | YES |  | NULL |

### `fiscal_certificados_digitais`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome_certificado` | varchar(255) | NO |  |  |
| `arquivo_certificado` | varchar(255) | YES |  | NULL |
| `senha_criptografada` | varchar(255) | YES |  | NULL |
| `tipo_certificado` | enum('A1','A3') | YES |  | 'A1' |
| `data_emissao` | date | YES |  | NULL |
| `data_vencimento` | date | NO | MUL |  |
| `cnpj_proprietario` | varchar(18) | YES |  | NULL |
| `razao_social_proprietario` | varchar(255) | YES |  | NULL |
| `emissor` | varchar(255) | YES |  | NULL |
| `serial_number` | varchar(100) | YES |  | NULL |
| `ativo` | tinyint(1) | YES | MUL | 1 |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_config_empresa`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `cnpj` | varchar(18) | NO | MUL |  |
| `razao_social` | varchar(255) | NO |  |  |
| `nome_fantasia` | varchar(255) | YES |  | NULL |
| `inscricao_estadual` | varchar(20) | YES |  | NULL |
| `crt` | tinyint(3) unsigned | NO |  | 1 |
| `rntrc` | varchar(20) | YES |  | NULL |
| `codigo_municipio` | varchar(7) | YES |  | NULL |
| `cep` | varchar(9) | YES |  | NULL |
| `endereco` | text | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | YES |  | NULL |
| `ambiente_sefaz` | enum('producao','homologacao') | YES | MUL | 'homologacao' |
| `certificado_digital` | varchar(255) | YES |  | NULL |
| `senha_certificado_criptografada` | varchar(255) | YES |  | NULL |
| `chave_criptografia` | varchar(255) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_config_envio_automatico`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_documento` | enum('nfe','cte','mdfe') | NO | MUL |  |
| `enviar_automatico` | tinyint(1) | YES |  | 0 |
| `enviar_cliente` | tinyint(1) | YES |  | 1 |
| `enviar_motorista` | tinyint(1) | YES |  | 0 |
| `template_email_cliente` | text | YES |  | NULL |
| `template_email_motorista` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_config_seguranca`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | UNI |  |
| `algoritmo_criptografia` | enum('AES-256','AES-128','3DES') | YES |  | 'AES-256' |
| `chave_mestre` | varchar(255) | YES |  | NULL |
| `salt_criptografia` | varchar(64) | YES |  | NULL |
| `tempo_expiracao_sessao` | int(11) | YES |  | 3600 |
| `max_tentativas_login` | int(11) | YES |  | 5 |
| `bloqueio_temporario` | int(11) | YES |  | 900 |
| `log_tentativas_acesso` | tinyint(1) | YES |  | 1 |
| `criptografar_arquivos` | tinyint(1) | YES |  | 1 |
| `backup_criptografado` | tinyint(1) | YES |  | 1 |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_cte`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero_cte` | varchar(20) | NO | MUL |  |
| `serie_cte` | varchar(10) | YES |  | NULL |
| `chave_acesso` | varchar(44) | YES | UNI | NULL |
| `data_emissao` | date | NO | MUL |  |
| `tipo_servico` | enum('normal','complemento','anulacao','substituicao') | YES |  | 'normal' |
| `natureza_operacao` | varchar(100) | YES |  | NULL |
| `cfop` | varchar(4) | YES |  | '5353' |
| `tp_cte` | tinyint(4) | YES |  | 0 |
| `modal` | varchar(2) | YES |  | '01' |
| `cod_mun_env` | varchar(7) | YES |  | NULL |
| `cod_mun_ini` | varchar(7) | YES |  | NULL |
| `cod_mun_fim` | varchar(7) | YES |  | NULL |
| `rntrc` | varchar(20) | YES |  | NULL |
| `cce_seq` | int(11) | NO |  | 0 |
| `nfe_ids` | longtext | YES |  | NULL |
| `protocolo_autorizacao` | varchar(50) | YES |  | NULL |
| `status` | enum('rascunho','pendente','autorizado','cancelado','denegado','inutilizado') | YES | MUL | 'pendente' |
| `valor_total` | decimal(15,2) | NO |  |  |
| `peso_carga` | decimal(12,3) | YES |  | NULL |
| `volumes_carga` | int(11) | YES |  | NULL |
| `peso_total` | decimal(10,3) | YES |  | NULL |
| `origem_estado` | varchar(2) | YES |  | NULL |
| `origem_cidade` | varchar(100) | YES |  | NULL |
| `destino_estado` | varchar(2) | YES |  | NULL |
| `destino_cidade` | varchar(100) | YES |  | NULL |
| `veiculo_id` | int(11) | YES | MUL | NULL |
| `motorista_id` | int(11) | YES | MUL | NULL |
| `origem` | varchar(255) | YES |  | NULL |
| `destino` | varchar(255) | YES |  | NULL |
| `xml_cte` | longtext | YES |  | NULL |
| `pdf_cte` | varchar(255) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `hash_assinatura` | varchar(64) | YES |  | NULL |
| `status_assinatura` | enum('valida','invalida','pendente') | YES |  | 'pendente' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `mdfe_id` | int(11) | YES | MUL | NULL |
| `ordem_transporte_id` | int(11) | YES | MUL | NULL |

### `fiscal_cte_eventos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `cte_id` | int(11) | NO | MUL |  |
| `tp_evento` | varchar(10) | NO | MUL |  |
| `n_seq_evento` | int(11) | NO |  | 1 |
| `protocolo_evento` | varchar(30) | YES |  | NULL |
| `status` | enum('pendente','aceito','rejeitado') | NO |  | 'pendente' |
| `payload_json` | longtext | YES |  | NULL |
| `xml_retorno` | longtext | YES |  | NULL |
| `motivo_rejeicao` | varchar(500) | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `fiscal_cte_itens`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `cte_id` | int(11) | NO | UNI |  |
| `tomador_cnpj` | varchar(18) | YES | MUL | NULL |
| `tomador_nome` | varchar(255) | YES |  | NULL |
| `tp_toma` | tinyint(4) | YES |  | 0 |
| `rem_cnpj` | varchar(18) | YES |  | NULL |
| `rem_nome` | varchar(255) | YES |  | NULL |
| `rem_ie` | varchar(20) | YES |  | NULL |
| `rem_logradouro` | varchar(255) | YES |  | NULL |
| `rem_mun` | varchar(100) | YES |  | NULL |
| `rem_uf` | char(2) | YES |  | NULL |
| `rem_cmun` | varchar(7) | YES |  | NULL |
| `dest_cnpj` | varchar(18) | YES |  | NULL |
| `dest_nome` | varchar(255) | YES |  | NULL |
| `dest_ie` | varchar(20) | YES |  | NULL |
| `dest_logradouro` | varchar(255) | YES |  | NULL |
| `dest_mun` | varchar(100) | YES |  | NULL |
| `dest_uf` | char(2) | YES |  | NULL |
| `dest_cmun` | varchar(7) | YES |  | NULL |
| `rntrc` | varchar(20) | YES |  | NULL |
| `ult_protocolo_ce` | varchar(30) | YES |  | NULL |
| `ult_protocolo_desacordo` | varchar(30) | YES |  | NULL |
| `valor_prestacao` | decimal(15,2) | YES |  | NULL |
| `valor_receber` | decimal(15,2) | YES |  | NULL |
| `comp_nome` | varchar(100) | YES |  | NULL |
| `comp_valor` | decimal(15,2) | YES |  | NULL |
| `icms_cst` | varchar(3) | YES |  | NULL |
| `icms_vbc` | decimal(15,2) | YES |  | NULL |
| `icms_picms` | decimal(5,2) | YES |  | NULL |
| `icms_vicms` | decimal(15,2) | YES |  | NULL |
| `valor_carga` | decimal(15,2) | YES |  | NULL |
| `produto_predominante` | varchar(100) | YES |  | NULL |
| `inf_complementar` | text | YES |  | NULL |
| `data_protocolo` | datetime | YES |  | NULL |
| `numero_protocolo` | varchar(30) | YES |  | NULL |
| `status_protocolo` | varchar(5) | YES |  | NULL |
| `motivo_protocolo` | varchar(255) | YES |  | NULL |
| `versao_aplicativo` | varchar(20) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_distribuicao_nsu`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(10) unsigned | NO | PRI |  |
| `ult_nsu` | varchar(15) | NO |  | '0' |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `fiscal_eventos_fiscais`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_evento` | enum('cancelamento','encerramento','cce','inutilizacao','carta_correcao','manifestacao') | NO | MUL |  |
| `documento_tipo` | enum('nfe','cte','mdfe') | NO | MUL |  |
| `documento_id` | int(11) | NO |  |  |
| `protocolo_evento` | varchar(50) | YES |  | NULL |
| `justificativa` | text | YES |  | NULL |
| `xml_evento` | longtext | YES |  | NULL |
| `xml_retorno` | longtext | YES |  | NULL |
| `status` | enum('pendente','aceito','rejeitado') | YES | MUL | 'pendente' |
| `data_evento` | timestamp | NO | MUL | current_timestamp() |
| `data_processamento` | timestamp | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |

### `fiscal_fila_processamento`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_documento` | varchar(10) | NO | MUL |  |
| `acao` | varchar(30) | NO |  |  |
| `payload_json` | longtext | YES |  | NULL |
| `status` | varchar(20) | NO | MUL | 'pendente' |
| `tentativas` | int(11) | NO |  | 0 |
| `max_tentativas` | int(11) | NO |  | 5 |
| `erro_ultimo` | text | YES |  | NULL |
| `proxima_tentativa_em` | datetime | YES |  | NULL |
| `processado_em` | datetime | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_logs`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `documento_tipo` | enum('nfe','cte','mdfe') | NO | MUL |  |
| `documento_id` | int(11) | NO |  |  |
| `acao` | enum('criacao','edicao','exclusao','cancelamento','encerramento','cce','inutilizacao','assinatura','verificacao','envio','recebimento','erro','sincronizacao') | NO | MUL |  |
| `status` | enum('sucesso','erro','pendente') | NO | MUL |  |
| `mensagem` | text | YES |  | NULL |
| `detalhes` | longtext | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `ip_usuario` | varchar(45) | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `fiscal_mdfe`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero_mdfe` | varchar(20) | NO | MUL |  |
| `serie_mdfe` | varchar(10) | YES |  | NULL |
| `chave_acesso` | varchar(44) | YES | UNI | NULL |
| `data_emissao` | date | NO | MUL |  |
| `tipo_transporte` | enum('rodoviario','aereo','aquaviario','ferroviario') | YES |  | 'rodoviario' |
| `protocolo_autorizacao` | varchar(50) | YES |  | NULL |
| `data_autorizacao` | datetime | YES |  | NULL |
| `status` | enum('rascunho','pendente','em_envio','emitido','em_viagem','autorizado','cancelado','encerrado','denegado') | NO | MUL | 'rascunho' |
| `data_encerramento` | datetime | YES |  | NULL |
| `valor_total_carga` | decimal(15,2) | YES |  | NULL |
| `peso_total_carga` | decimal(10,3) | YES |  | NULL |
| `qtd_total_volumes` | int(11) | YES |  | NULL |
| `qtd_total_peso` | decimal(10,3) | YES |  | NULL |
| `xml_mdfe` | longtext | YES |  | NULL |
| `pdf_mdfe` | varchar(255) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `motorista_id` | int(11) | YES | MUL | NULL |
| `veiculo_id` | int(11) | YES | MUL | NULL |
| `uf_inicio` | varchar(2) | YES |  | NULL |
| `uf_fim` | varchar(2) | YES |  | NULL |
| `municipio_carregamento` | varchar(100) | YES |  | NULL |
| `municipio_descarregamento` | varchar(100) | YES |  | NULL |
| `cod_mun_carreg_ibge` | varchar(7) | YES |  | NULL |
| `cod_mun_desc_ibge` | varchar(7) | YES |  | NULL |
| `ufs_percurso` | longtext | YES |  | NULL |
| `tipo_viagem` | varchar(2) | YES |  | '1' |
| `tipo_emitente` | char(1) | YES |  | NULL |
| `tipo_transportador` | char(1) | YES |  | NULL |
| `rod_rntrc` | varchar(20) | YES |  | NULL |
| `rod_placa` | varchar(10) | YES |  | NULL |
| `rod_tipo_rodado` | varchar(2) | YES |  | NULL |
| `rod_tipo_carroceria` | varchar(2) | YES |  | NULL |
| `total_cte` | int(11) | YES |  | 0 |
| `hash_assinatura` | varchar(64) | YES |  | NULL |
| `status_assinatura` | enum('valida','invalida','pendente') | YES |  | 'pendente' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_autorizados_download`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `documento` | varchar(20) | NO |  |  |
| `motorista` | varchar(255) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_ciot`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero_ciot` | varchar(60) | YES |  | NULL |
| `valor_frete` | decimal(15,2) | YES |  | NULL |
| `cpf_cnpj_tac` | varchar(20) | YES |  | NULL |
| `ipef` | varchar(120) | YES |  | NULL |
| `pef_operacao_id` | int(10) unsigned | YES | MUL | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_condutores`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `mdfe_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(120) | NO |  |  |
| `cpf` | char(11) | NO |  |  |
| `ordem` | smallint(6) | NO |  | 1 |
| `payload_json` | longtext | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_contratantes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_pessoa` | varchar(20) | YES |  | NULL |
| `documento` | varchar(30) | YES |  | NULL |
| `razao_social` | varchar(255) | YES |  | NULL |
| `numero_contrato` | varchar(80) | YES |  | NULL |
| `valor` | decimal(15,2) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_cte`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `mdfe_id` | int(11) | NO | MUL |  |
| `cte_id` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_documentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `mdfe_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `municipio_descarga` | varchar(120) | YES |  | NULL |
| `cod_mun_descarga` | varchar(7) | YES |  | NULL |
| `chave_nfe` | char(44) | YES |  | NULL |
| `nfe_id` | int(11) | YES |  | NULL |
| `chave_cte` | char(44) | YES |  | NULL |
| `cte_id` | int(11) | YES |  | NULL |
| `numero_nfe` | varchar(30) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_lacres`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero_lacre` | varchar(80) | NO |  |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_nfe`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `mdfe_id` | int(11) | NO | MUL |  |
| `nfe_id` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_pagamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_pessoa` | varchar(20) | YES |  | NULL |
| `documento` | varchar(30) | YES |  | NULL |
| `razao_social` | varchar(255) | YES |  | NULL |
| `considerar_componentes` | tinyint(1) | NO |  | 0 |
| `valor_total_contrato` | decimal(15,2) | YES |  | NULL |
| `indicador_forma_pagamento` | varchar(30) | YES |  | NULL |
| `forma_financiamento` | varchar(100) | YES |  | NULL |
| `alto_desempenho` | varchar(30) | YES |  | NULL |
| `tipo_pagamento` | varchar(60) | YES |  | NULL |
| `indicador_status_pagamento` | varchar(30) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_pagamento_componentes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `pagamento_id` | bigint(20) | YES | MUL | NULL |
| `empresa_id` | int(11) | NO | MUL |  |
| `codigo` | varchar(10) | YES |  | NULL |
| `tipo` | varchar(120) | YES |  | NULL |
| `valor` | decimal(15,2) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_produtos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_carga` | varchar(120) | YES |  | NULL |
| `descricao_produto` | varchar(255) | YES |  | NULL |
| `gtin` | varchar(20) | YES |  | NULL |
| `ncm` | varchar(12) | YES |  | NULL |
| `carga_lotacao` | varchar(5) | YES |  | NULL |
| `local_carregamento_cep` | varchar(12) | YES |  | NULL |
| `local_descarregamento_cep` | varchar(12) | YES |  | NULL |
| `cep_descarregamento` | varchar(12) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_reboques`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `mdfe_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `placa` | varchar(10) | NO |  |  |
| `renavam` | varchar(20) | YES |  | NULL |
| `tara` | int(11) | YES |  | NULL |
| `cap_kg` | int(11) | YES |  | NULL |
| `tp_rod` | varchar(2) | YES |  | NULL |
| `tp_car` | varchar(2) | YES |  | NULL |
| `uf` | char(2) | YES |  | NULL |
| `cod_interno` | varchar(30) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_seguros`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `responsavel` | varchar(255) | YES |  | NULL |
| `cpf_cnpj_responsavel` | varchar(20) | YES |  | NULL |
| `emitente` | varchar(255) | YES |  | NULL |
| `cnpj_seguradora` | varchar(20) | YES |  | NULL |
| `nome_seguradora` | varchar(255) | YES |  | NULL |
| `tomador_contratante` | varchar(255) | YES |  | NULL |
| `numero_apolice` | varchar(80) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_seguros_averbacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `seguro_id` | bigint(20) | YES | MUL | NULL |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero_averbacao` | varchar(80) | NO |  |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_mdfe_vale_pedagio`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `eixos` | int(11) | YES |  | NULL |
| `valor` | decimal(15,2) | YES |  | NULL |
| `tipo` | varchar(80) | YES |  | NULL |
| `cnpj_fornecedor` | varchar(20) | YES |  | NULL |
| `numero_comprovante` | varchar(100) | YES |  | NULL |
| `responsavel_pagamento` | varchar(20) | YES |  | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_nfe_clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero_nfe` | varchar(20) | NO | MUL |  |
| `serie_nfe` | varchar(10) | YES |  | NULL |
| `chave_acesso` | varchar(44) | YES | UNI | NULL |
| `data_emissao` | date | NO | MUL |  |
| `data_entrada` | date | YES |  | NULL |
| `cliente_cnpj` | varchar(18) | YES |  | NULL |
| `cliente_razao_social` | varchar(255) | YES |  | NULL |
| `cliente_nome_fantasia` | varchar(255) | YES |  | NULL |
| `cliente_remetente` | varchar(255) | YES |  | NULL |
| `cnpj_remetente` | varchar(18) | YES |  | NULL |
| `cliente_destinatario` | varchar(255) | YES |  | NULL |
| `cnpj_destinatario` | varchar(18) | YES |  | NULL |
| `valor_total` | decimal(15,2) | NO |  |  |
| `peso_carga` | decimal(12,3) | YES |  | NULL |
| `volumes` | int(11) | YES |  | NULL |
| `status` | enum('pendente','autorizada','cancelada','denegada','rascunho','inutilizada','recebida','consultada_sefaz','validada','em_transporte') | YES | MUL | 'pendente' |
| `protocolo_autorizacao` | varchar(50) | YES |  | NULL |
| `xml_nfe` | longtext | YES |  | NULL |
| `pdf_nfe` | varchar(255) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `cte_id` | int(11) | YES | MUL | NULL |
| `hash_assinatura` | varchar(64) | YES |  | NULL |
| `status_assinatura` | enum('valida','invalida','pendente') | YES |  | 'pendente' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_nfe_emitidas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `serie` | int(11) | NO |  | 1 |
| `numero_nfe` | int(11) | NO |  |  |
| `chave_acesso` | varchar(44) | NO | UNI |  |
| `protocolo_autorizacao` | varchar(50) | YES |  | NULL |
| `valor_total` | decimal(15,2) | NO |  | 0.00 |
| `status` | enum('rascunho','pendente','autorizada','rejeitada','denegada') | NO | MUL | 'pendente' |
| `destinatario_cnpj` | varchar(14) | YES |  | NULL |
| `destinatario_cpf` | varchar(11) | YES |  | NULL |
| `destinatario_nome` | varchar(255) | YES |  | NULL |
| `xml_nfe` | longtext | YES |  | NULL |
| `xml_retorno_sefaz` | longtext | YES |  | NULL |
| `motivo_rejeicao` | text | YES |  | NULL |
| `data_emissao` | date | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_nfe_itens`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nfe_id` | int(11) | NO | MUL |  |
| `numero_item_nfe` | int(11) | YES |  | NULL |
| `gtin` | varchar(20) | YES |  | NULL |
| `codigo_produto` | varchar(50) | YES | MUL | NULL |
| `descricao_produto` | text | NO |  |  |
| `ncm` | varchar(10) | YES | MUL | NULL |
| `cest` | varchar(10) | YES |  | NULL |
| `cfop` | varchar(4) | YES |  | NULL |
| `unidade_comercial` | varchar(10) | YES |  | NULL |
| `quantidade_comercial` | decimal(15,4) | NO |  |  |
| `valor_unitario` | decimal(15,4) | NO |  |  |
| `valor_total_item` | decimal(15,2) | NO |  |  |
| `peso_bruto` | decimal(10,3) | YES |  | NULL |
| `peso_liquido` | decimal(10,3) | YES |  | NULL |
| `cst_icms` | varchar(3) | YES |  | NULL |
| `cst_pis` | varchar(3) | YES |  | NULL |
| `cst_cofins` | varchar(3) | YES |  | NULL |
| `cst_ipi` | varchar(3) | YES |  | NULL |
| `valor_icms` | decimal(12,2) | YES |  | NULL |
| `valor_icms_st` | decimal(12,2) | YES |  | NULL |
| `valor_ipi` | decimal(12,2) | YES |  | NULL |
| `valor_pis` | decimal(12,2) | YES |  | NULL |
| `valor_cofins` | decimal(12,2) | YES |  | NULL |
| `valor_total_tributos` | decimal(12,2) | YES |  | NULL |
| `valor_desconto` | decimal(12,2) | YES |  | NULL |
| `valor_frete` | decimal(12,2) | YES |  | NULL |
| `valor_seguro` | decimal(12,2) | YES |  | NULL |
| `valor_outros` | decimal(12,2) | YES |  | NULL |
| `informacao_adicional_item` | text | YES |  | NULL |
| `anp_codigo` | varchar(15) | YES |  | NULL |
| `anp_descricao` | varchar(100) | YES |  | NULL |
| `percentual_biodiesel` | decimal(5,2) | YES |  | NULL |
| `uf_consumo` | char(2) | YES |  | NULL |
| `icms_monofasico_valor` | decimal(12,2) | YES |  | NULL |
| `icms_monofasico_aliquota_adrem` | decimal(10,4) | YES |  | NULL |
| `placa` | varchar(10) | YES |  | NULL |
| `motorista_nome` | varchar(100) | YES |  | NULL |
| `motorista_cpf` | varchar(14) | YES |  | NULL |
| `km_veiculo` | int(11) | YES |  | NULL |
| `veiculo_id` | int(10) unsigned | YES |  | NULL |
| `rota_id` | int(10) unsigned | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `fiscal_status_historico`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `documento_tipo` | enum('nfe','cte','mdfe') | NO | MUL |  |
| `documento_id` | int(11) | NO |  |  |
| `status_anterior` | varchar(50) | YES | MUL | NULL |
| `status_novo` | varchar(50) | YES | MUL | NULL |
| `motivo_mudanca` | text | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `data_alteracao` | timestamp | NO | MUL | current_timestamp() |
| `ip_usuario` | varchar(45) | YES |  | NULL |
| `user_agent` | text | YES |  | NULL |

### `formas_pagamento`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |
| `descricao` | text | YES |  | NULL |

### `fornecedores`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | enum('F','J') | NO |  |  |
| `nome` | varchar(200) | NO |  |  |
| `cpf` | varchar(11) | YES |  | NULL |
| `cnpj` | varchar(14) | YES |  | NULL |
| `inscricao_estadual` | varchar(20) | YES |  | NULL |
| `inscricao_municipal` | varchar(20) | YES |  | NULL |
| `regime_tributario` | varchar(50) | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `email` | varchar(200) | YES |  | NULL |
| `site` | varchar(200) | YES |  | NULL |
| `endereco` | varchar(200) | YES |  | NULL |
| `numero` | varchar(15) | YES |  | NULL |
| `complemento` | varchar(80) | YES |  | NULL |
| `bairro` | varchar(120) | YES |  | NULL |
| `cidade` | varchar(120) | YES |  | NULL |
| `uf` | char(2) | YES |  | NULL |
| `cep` | varchar(9) | YES |  | NULL |
| `codigo_municipio_ibge` | varchar(7) | YES |  | NULL |
| `pais` | varchar(60) | YES |  | 'Brasil' |
| `tipo_fornecedor` | varchar(50) | YES |  | NULL |
| `limite_credito` | decimal(15,2) | YES |  | 0.00 |
| `prazo_pagamento` | int(11) | YES |  | 0 |
| `taxa_multa` | decimal(5,2) | YES |  | 0.00 |
| `taxa_juros` | decimal(5,2) | YES |  | 0.00 |
| `situacao` | enum('A','I') | NO |  | 'A' |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | YES |  | NULL |

### `fornecedor_clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(255) | NO |  |  |
| `cnpj` | varchar(18) | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | YES |  | NULL |
| `endereco` | text | YES |  | NULL |
| `status` | enum('ativo','inativo') | YES |  | 'ativo' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `gamificacao_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `periodo` | date | NO |  |  |
| `pontos_totais` | int(11) | YES | MUL | 0 |
| `pontos_pontualidade` | int(11) | YES |  | 0 |
| `pontos_consumo` | int(11) | YES |  | 0 |
| `pontos_checklist` | int(11) | YES |  | 0 |
| `pontos_multas` | int(11) | YES |  | 0 |
| `pontos_eficiencia` | int(11) | YES |  | 0 |
| `pontos_bonus` | int(11) | YES |  | 0 |
| `nivel_atual` | varchar(20) | YES | MUL | 'Bronze' |
| `nivel_anterior` | varchar(20) | YES |  | 'Bronze' |
| `pontos_para_proximo_nivel` | int(11) | YES |  | 100 |
| `progresso_nivel` | decimal(5,2) | YES |  | 0.00 |
| `badge_atual` | varchar(50) | YES |  | NULL |
| `total_badges` | int(11) | YES |  | 0 |
| `badges_conquistadas` | longtext | YES |  | NULL |
| `dias_ativo` | int(11) | YES |  | 0 |
| `rotas_concluidas` | int(11) | YES |  | 0 |
| `desafios_completos` | int(11) | YES |  | 0 |
| `streak_atual` | int(11) | YES |  | 0 |
| `streak_maximo` | int(11) | YES |  | 0 |
| `data_calculo` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `gastos_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `tipo` | enum('compra','recapagem','reparo','instalacao','rodizio','descartado') | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `data` | date | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `fornecedor` | varchar(100) | YES |  | NULL |

### `gestao_montagem`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `veiculo_id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `config` | longtext | YES |  | NULL |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `gps_alertas_operacionais`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `veiculo_id` | int(10) unsigned | NO | MUL |  |
| `motorista_id` | int(10) unsigned | NO |  |  |
| `tipo` | varchar(32) | NO |  |  |
| `mensagem` | varchar(255) | NO |  |  |
| `latitude` | decimal(10,8) | YES |  | NULL |
| `longitude` | decimal(11,8) | YES |  | NULL |
| `data_hora` | datetime | NO |  |  |
| `extra_json` | longtext | YES |  | NULL |

### `gps_cercas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `nome` | varchar(120) | NO |  |  |
| `latitude` | decimal(10,8) | NO |  |  |
| `longitude` | decimal(11,8) | NO |  |  |
| `raio_metros` | int(10) unsigned | NO |  | 500 |
| `ativo` | tinyint(1) | NO |  | 1 |
| `created_at` | datetime | NO |  | current_timestamp() |

### `gps_cerca_alertas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `cerca_id` | int(10) unsigned | NO | MUL |  |
| `veiculo_id` | int(10) unsigned | NO | MUL |  |
| `motorista_id` | int(10) unsigned | NO |  |  |
| `tipo` | enum('entrou','saiu','permanencia') | NO |  |  |
| `latitude` | decimal(10,8) | NO |  |  |
| `longitude` | decimal(11,8) | NO |  |  |
| `data_hora` | datetime | NO |  |  |

### `gps_cerca_estado`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `veiculo_id` | int(10) unsigned | NO | PRI |  |
| `cerca_id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `dentro` | tinyint(1) | NO |  | 0 |
| `primeiro_dentro_em` | datetime | YES |  | NULL |
| `permanencia_alertada` | tinyint(1) | NO |  | 0 |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `gps_geocode_cache`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `lat_key` | decimal(8,4) | NO | PRI |  |
| `lng_key` | decimal(8,4) | NO | PRI |  |
| `endereco` | varchar(255) | NO |  |  |
| `atualizado_em` | datetime | NO |  | current_timestamp() |

### `gps_logs`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `veiculo_id` | int(10) unsigned | NO |  |  |
| `motorista_id` | int(10) unsigned | NO | MUL |  |
| `latitude` | decimal(10,8) | NO |  |  |
| `longitude` | decimal(11,8) | NO |  |  |
| `velocidade` | decimal(6,2) | YES |  | NULL |
| `bateria_pct` | tinyint(3) unsigned | YES |  | NULL |
| `accuracy_metros` | float | YES |  | NULL |
| `provider` | varchar(32) | YES |  | NULL |
| `location_mock` | tinyint(1) | YES |  | NULL |
| `status` | varchar(16) | YES |  | NULL |
| `data_hora` | datetime | NO | MUL |  |

### `gps_logs_resumido`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `veiculo_id` | int(10) unsigned | NO |  |  |
| `data` | date | NO |  |  |
| `total_pontos` | int(10) unsigned | NO |  | 0 |
| `distancia_km` | decimal(10,2) | NO |  | 0.00 |
| `tempo_movimento_seg` | int(10) unsigned | NO |  | 0 |
| `tempo_parado_seg` | int(10) unsigned | NO |  | 0 |
| `created_at` | datetime | NO |  | current_timestamp() |

### `gps_ultima_posicao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `veiculo_id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `motorista_id` | int(10) unsigned | NO |  |  |
| `latitude` | decimal(10,8) | NO |  |  |
| `longitude` | decimal(11,8) | NO |  |  |
| `velocidade` | decimal(6,2) | YES |  | NULL |
| `status` | enum('parado','movimento','ocioso') | YES |  | NULL |
| `parado_desde` | datetime | YES |  | NULL |
| `ignicao` | tinyint(1) | YES |  | NULL |
| `ultima_atualizacao` | datetime | YES |  | NULL |
| `endereco` | varchar(255) | YES |  | NULL |
| `bateria_pct` | tinyint(3) unsigned | YES |  | NULL |
| `accuracy_metros` | float | YES |  | NULL |
| `provider` | varchar(32) | YES |  | NULL |
| `location_mock` | tinyint(1) | YES |  | NULL |
| `data_hora` | datetime | NO |  |  |

### `historico_analises`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_analise` | varchar(50) | NO |  |  |
| `dados_entrada` | longtext | YES |  | NULL |
| `dados_saida` | longtext | YES |  | NULL |
| `tempo_execucao` | decimal(10,4) | YES |  | NULL |
| `data_execucao` | timestamp | NO | MUL | current_timestamp() |
| `status` | enum('sucesso','erro','parcial') | YES | MUL | 'sucesso' |
| `mensagem_erro` | text | YES |  | NULL |

### `implementos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `placa` | varchar(10) | NO |  |  |
| `tipo` | varchar(40) | NO |  | 'Carreta' |
| `tipo_carreta_id` | int(11) | YES |  | NULL |
| `marca` | varchar(60) | YES |  | NULL |
| `ano` | smallint(6) | YES |  | NULL |
| `renavam` | varchar(20) | YES |  | NULL |
| `chassi` | varchar(30) | YES |  | NULL |
| `rntrc` | varchar(20) | YES |  | NULL |
| `capacidade_kg` | decimal(10,2) | YES |  | NULL |
| `eixos` | tinyint(3) unsigned | YES |  | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `status` | enum('ativo','manutencao','inativo') | NO |  | 'ativo' |
| `vencimento_licenciamento` | date | YES |  | NULL |
| `vencimento_seguro` | date | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `criado_por` | int(11) | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `insights_ia`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(50) | NO |  |  |
| `titulo` | varchar(255) | NO |  |  |
| `descricao` | text | NO |  |  |
| `dados` | longtext | YES |  | NULL |
| `confianca` | decimal(3,2) | YES | MUL | 0.80 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |
| `status` | enum('ativo','inativo') | YES | MUL | 'ativo' |

### `instalacoes_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `eixo_id` | int(11) | YES | MUL | NULL |
| `posicao` | int(11) | NO |  |  |
| `data_instalacao` | date | NO |  |  |
| `data_remocao` | date | YES |  | NULL |
| `status` | enum('bom','gasto','furado') | YES |  | 'bom' |
| `posicao_id` | int(11) | YES | MUL | NULL |

### `jornada_motorista`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `tipo` | varchar(20) | NO |  |  |
| `data_hora` | datetime | NO |  |  |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `observacoes` | varchar(255) | YES |  | NULL |
| `fonte` | varchar(20) | NO |  | 'motorista' |
| `created_at` | datetime | NO |  | current_timestamp() |

### `login_attempts`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `email` | varchar(255) | NO | MUL |  |
| `ip` | varchar(45) | NO |  |  |
| `tentativas` | int(11) | NO |  | 0 |
| `bloqueado_ate` | datetime | YES | MUL | NULL |
| `atualizado_em` | timestamp | NO |  | current_timestamp() |

### `logs_acesso`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `usuario_id` | int(11) | NO |  |  |
| `empresa_id` | int(11) | YES |  | NULL |
| `tipo_usuario` | enum('admin','usuario') | NO |  |  |
| `ip` | varchar(45) | NO |  |  |
| `data_acesso` | datetime | NO |  |  |
| `status` | enum('sucesso','falha') | NO |  |  |

### `log_acessos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `usuario_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_acesso` | enum('login','logout','tentativa_login_falha','sessao_expirada') | YES | MUL | 'login' |
| `status` | enum('sucesso','falha') | YES | MUL | 'sucesso' |
| `ip_address` | varchar(45) | YES |  | NULL |
| `user_agent` | text | YES |  | NULL |
| `descricao` | text | YES |  | NULL |
| `data_acesso` | timestamp | NO | MUL | current_timestamp() |

### `manutencao_anexos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `manutencao_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome_original` | varchar(255) | NO |  |  |
| `caminho` | varchar(512) | NO |  |  |
| `tipo` | varchar(100) | YES |  | NULL |
| `tamanho` | int(10) unsigned | YES |  | 0 |
| `data_upload` | datetime | YES |  | current_timestamp() |

### `manutencoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `data_manutencao` | date | NO |  |  |
| `data_conclusao` | date | YES |  | NULL |
| `descricao` | text | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `valor_pecas` | decimal(10,2) | NO |  | 0.00 |
| `valor_mao_obra` | decimal(10,2) | NO |  | 0.00 |
| `valor_outros` | decimal(10,2) | NO |  | 0.00 |
| `km_atual` | decimal(10,2) | NO |  |  |
| `fornecedor` | varchar(255) | YES |  | NULL |
| `nota_fiscal` | varchar(255) | YES |  | NULL |
| `conta_pagar_id` | int(10) unsigned | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `custo_total` | decimal(10,2) | YES |  | 0.00 |
| `descricao_servico` | text | NO |  |  |
| `responsavel_aprovacao` | varchar(255) | NO |  |  |
| `tipo_manutencao_id` | int(11) | NO | MUL |  |
| `componente_id` | int(11) | NO | MUL |  |
| `status_manutencao_id` | int(11) | NO | MUL |  |

### `mdfe_envios`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `mdfe_id` | bigint(20) | YES | MUL | NULL |
| `xml_hash` | char(64) | NO |  |  |
| `status_envio` | varchar(30) | NO | MUL |  |
| `protocolo` | varchar(60) | YES |  | NULL |
| `metodo_envio` | varchar(40) | YES |  | NULL |
| `resposta_sefaz` | longtext | YES |  | NULL |
| `erro` | text | YES |  | NULL |
| `criado_em` | datetime | NO | MUL | current_timestamp() |
| `atualizado_em` | datetime | NO |  | current_timestamp() |

### `mdfe_validacao_log`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) | NO | PRI |  |
| `mdfe_id` | bigint(20) | YES | MUL | NULL |
| `empresa_id` | int(11) | NO | MUL |  |
| `versao_regra` | varchar(30) | YES |  | NULL |
| `contexto` | varchar(30) | NO |  |  |
| `payload_hash` | char(64) | YES | MUL | NULL |
| `payload_json` | longtext | YES |  | NULL |
| `erros_json` | longtext | YES |  | NULL |
| `warnings_json` | longtext | YES |  | NULL |
| `criado_em` | datetime | NO | MUL | current_timestamp() |

### `motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(255) | NO |  |  |
| `cpf` | varchar(14) | NO |  |  |
| `cnh` | varchar(20) | YES |  | NULL |
| `data_validade_cnh` | date | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `telefone_emergencia` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | YES |  | NULL |
| `endereco` | text | YES |  | NULL |
| `data_contratacao` | date | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `tipo_contrato_id` | int(11) | YES | MUL | NULL |
| `disponibilidade_id` | int(11) | YES | MUL | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `porcentagem_comissao` | decimal(5,2) | YES |  | 10.00 |
| `categoria_cnh_id` | int(11) | YES | MUL | NULL |
| `cnh_arquivo` | varchar(255) | YES |  | NULL |
| `contrato_arquivo` | varchar(255) | YES |  | NULL |
| `foto_motorista` | varchar(255) | YES |  | NULL |
| `data_nascimento` | date | YES |  | NULL |
| `rg` | varchar(20) | YES |  | NULL |
| `pis_pasep` | varchar(20) | YES |  | NULL |
| `banco` | varchar(100) | YES |  | NULL |
| `agencia` | varchar(20) | YES |  | NULL |
| `conta` | varchar(30) | YES |  | NULL |
| `contato_emergencia_nome` | varchar(150) | YES |  | NULL |
| `restricoes_medicas` | text | YES |  | NULL |
| `tamanho_uniforme` | varchar(20) | YES |  | NULL |
| `observacoes_rh` | text | YES |  | NULL |

### `motoristas_log`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `acao` | enum('create','update','delete') | NO |  | 'update' |
| `descricao` | text | YES |  | NULL |
| `dados_anteriores` | longtext | YES |  | NULL |
| `dados_novos` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |
| `usuario_id` | int(11) | YES |  | NULL |
| `nome_usuario` | varchar(150) | YES |  | NULL |
| `ip_origem` | varchar(45) | YES |  | NULL |

### `motorista_dependentes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(150) | NO |  |  |
| `parentesco` | varchar(50) | YES |  | NULL |
| `data_nascimento` | date | YES |  | NULL |
| `cpf` | varchar(14) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `motorista_favoritos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `usuario_id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `motorista_periodos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | enum('ferias','licenca','afastamento','outro') | NO |  | 'outro' |
| `data_inicio` | date | NO | MUL |  |
| `data_fim` | date | NO |  |  |
| `observacao` | varchar(255) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `motorista_treinamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome_curso` | varchar(200) | NO |  |  |
| `instituicao` | varchar(150) | YES |  | NULL |
| `data_conclusao` | date | YES |  | NULL |
| `carga_horaria` | int(11) | YES |  | NULL |
| `certificado_arquivo` | varchar(255) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `multas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `data_infracao` | date | NO |  |  |
| `tipo_infracao` | varchar(255) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `pontos` | int(11) | YES |  | 0 |
| `valor` | decimal(10,2) | NO |  |  |
| `status_pagamento` | enum('pendente','pago','recurso') | YES |  | 'pendente' |
| `vencimento` | date | YES |  | NULL |
| `data_pagamento` | date | YES |  | NULL |
| `comprovante` | varchar(255) | YES |  | NULL |
| `created_at` | datetime | YES |  | current_timestamp() |

### `notificacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(64) | NO |  | 'alerta' |
| `severidade` | varchar(20) | NO |  | 'info' |
| `grupo_chave` | varchar(120) | YES |  | NULL |
| `titulo` | varchar(255) | NO |  |  |
| `mensagem` | text | NO |  |  |
| `ia_mensagem` | text | YES |  | NULL |
| `meta_json` | text | YES |  | NULL |
| `lida` | tinyint(1) | YES |  | 0 |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `referencia_id` | int(11) | YES |  | NULL |

### `notificacoes_digest_envio`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `usuario_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO |  |  |
| `data_ref` | date | NO |  |  |
| `enviado_em` | datetime | NO |  | current_timestamp() |

### `notificacoes_feedback`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `notificacao_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO |  |  |
| `usuario_id` | int(11) | YES |  | NULL |
| `feedback` | tinyint(1) | YES |  | NULL |
| `comentario` | text | YES |  | NULL |
| `data` | datetime | YES |  | current_timestamp() |

### `notificacoes_ia_config`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO |  |  |
| `usuario_id` | int(11) | NO |  |  |
| `tipos_notificacao` | text | YES |  | NULL |
| `limites` | longtext | YES |  | NULL |
| `canais` | text | YES |  | NULL |
| `data_atualizacao` | datetime | YES |  | current_timestamp() |

### `notificacoes_preferencias`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `usuario_id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | PRI |  |
| `tipos_desativados` | longtext | YES |  | NULL |
| `email_digest` | tinyint(1) | NO |  | 0 |
| `email_digest_hora` | time | NO |  | '08:00:00' |

### `notificacoes_sync_controle`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(11) | NO | PRI |  |
| `chave` | varchar(64) | NO | PRI |  |
| `ultima_execucao` | datetime | NO |  |  |

### `notifications`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `user_id` | int(11) | NO | MUL |  |
| `title` | varchar(255) | NO |  |  |
| `message` | text | NO |  |  |
| `type` | enum('info','success','warning','error') | YES |  | 'info' |
| `data` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |
| `read_at` | timestamp | YES | MUL | NULL |

### `ocorrencias_viagem`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `tipo` | varchar(40) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `data_hora` | datetime | NO |  |  |
| `foto_path` | varchar(255) | YES |  | NULL |
| `video_path` | varchar(255) | YES |  | NULL |
| `status` | varchar(20) | NO |  | 'aberta' |
| `fonte` | varchar(20) | NO |  | 'motorista' |
| `created_at` | datetime | NO |  | current_timestamp() |

### `openai_ocr_usage`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(10) unsigned | NO | MUL |  |
| `prompt_tokens` | int(10) unsigned | NO |  | 0 |
| `completion_tokens` | int(10) unsigned | NO |  | 0 |
| `estimated_cost_usd` | decimal(14,8) | NO |  | 0.00000000 |
| `model` | varchar(80) | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |

### `ordens_transporte`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `numero` | varchar(20) | NO |  |  |
| `cliente_nome` | varchar(160) | YES |  | NULL |
| `cliente_doc` | varchar(40) | YES |  | NULL |
| `origem` | varchar(160) | YES |  | NULL |
| `destino` | varchar(160) | YES |  | NULL |
| `estado_origem` | varchar(2) | YES |  | NULL |
| `estado_destino` | varchar(2) | YES |  | NULL |
| `distancia_km` | decimal(10,2) | NO |  | 0.00 |
| `tipo_carga` | varchar(40) | NO |  | 'geral' |
| `eixos` | tinyint(3) unsigned | NO |  | 5 |
| `peso_carga` | decimal(10,2) | YES |  | NULL |
| `descricao_carga` | varchar(255) | YES |  | NULL |
| `valor_frete` | decimal(12,2) | NO |  | 0.00 |
| `valor_custo_estimado` | decimal(12,2) | YES |  | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `motorista_id` | int(11) | YES |  | NULL |
| `rota_id` | int(11) | YES | MUL | NULL |
| `data_coleta` | datetime | YES |  | NULL |
| `data_entrega` | datetime | YES |  | NULL |
| `status` | enum('cotacao','aprovada','em_viagem','concluida','cancelada') | NO |  | 'cotacao' |
| `observacoes` | text | YES |  | NULL |
| `criado_por` | int(11) | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |
| `updated_at` | datetime | NO |  | current_timestamp() |
| `token_rastreio` | varchar(40) | YES | MUL | NULL |

### `panico_alertas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `veiculo_id` | int(11) | YES |  | NULL |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `data_hora` | datetime | NO |  |  |
| `status` | varchar(20) | NO |  | 'aberto' |
| `atendido_por` | int(11) | YES |  | NULL |
| `atendido_em` | datetime | YES |  | NULL |
| `observacoes` | varchar(255) | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |

### `parcelas_financiamento`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `financiamento_id` | int(11) | NO | MUL |  |
| `numero_parcela` | int(11) | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `data_vencimento` | date | NO |  |  |
| `data_pagamento` | date | YES |  | NULL |
| `forma_pagamento_id` | int(11) | YES | MUL | NULL |
| `status_id` | int(11) | NO | MUL | 1 |
| `comprovante_pagamento` | varchar(255) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `empresa_id` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `pedagios`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `concessionaria` | varchar(100) | YES |  | NULL |
| `praca_de_pedagio` | varchar(150) | YES |  | NULL |
| `ano_do_pnv_snv` | year(4) | YES |  | NULL |
| `rodovia` | varchar(20) | YES |  | NULL |
| `uf` | char(2) | YES |  | NULL |
| `km_m` | decimal(10,3) | YES |  | NULL |
| `municipio` | varchar(100) | YES |  | NULL |
| `tipo_pista` | varchar(50) | YES |  | NULL |
| `sentido` | varchar(50) | YES |  | NULL |
| `situacao` | varchar(50) | YES |  | NULL |
| `data_da_inativacao` | date | YES |  | NULL |
| `latitude` | decimal(10,6) | YES |  | NULL |
| `longitude` | decimal(10,6) | YES |  | NULL |

### `pedagios_viagem`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `praca` | varchar(150) | YES |  | NULL |
| `valor` | decimal(10,2) | NO |  | 0.00 |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `data_hora` | datetime | NO | MUL |  |
| `foto_path` | varchar(255) | YES |  | NULL |
| `fonte` | varchar(20) | NO |  | 'motorista' |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `pef_ciot_log`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `operacao_id` | int(10) unsigned | YES | MUL | NULL |
| `servico` | varchar(64) | NO |  |  |
| `request_json` | longtext | YES |  | NULL |
| `response_json` | longtext | YES |  | NULL |
| `http_code` | smallint(6) | YES |  | NULL |
| `erro` | text | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |

### `pef_ciot_operacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `rota_id` | int(11) | YES | MUL | NULL |
| `mdfe_id` | int(11) | YES |  | NULL |
| `id_operacao_transporte` | varchar(12) | NO |  |  |
| `codigo_identificacao` | varchar(16) | YES |  | NULL |
| `codigo_verificador` | char(4) | YES |  | NULL |
| `tipo_operacao` | tinyint(4) | NO |  | 1 |
| `status` | enum('rascunho','declarado','cancelado','encerrado','retificado','erro') | NO |  | 'rascunho' |
| `cpf_cnpj_contratado` | varchar(14) | YES |  | NULL |
| `rntrc_contratado` | varchar(9) | YES |  | NULL |
| `cpf_cnpj_contratante` | varchar(14) | YES |  | NULL |
| `cpf_cnpj_destinatario` | varchar(14) | YES |  | NULL |
| `valor_frete` | decimal(12,2) | YES |  | NULL |
| `data_declaracao` | datetime | YES |  | NULL |
| `data_inicio_viagem` | date | YES |  | NULL |
| `data_fim_viagem` | date | YES |  | NULL |
| `ind_contingencia` | tinyint(1) | NO |  | 0 |
| `payload_envio` | longtext | YES |  | NULL |
| `payload_resposta` | longtext | YES |  | NULL |
| `protocolo_antt` | varchar(16) | YES |  | NULL |
| `codigo_antt` | varchar(20) | YES |  | NULL |
| `mensagem_antt` | varchar(500) | YES |  | NULL |
| `aviso_transportador` | varchar(300) | YES |  | NULL |
| `motivo_cancelamento` | varchar(500) | YES |  | NULL |
| `criado_em` | timestamp | NO |  | current_timestamp() |
| `atualizado_em` | timestamp | NO |  | current_timestamp() |

### `pef_config_empresa`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `empresa_id` | int(11) | NO | PRI |  |
| `ambiente` | enum('homologacao','producao') | NO |  | 'homologacao' |
| `cpf_cnpj_interessado` | varchar(14) | YES |  | NULL |
| `cert_pem_path` | varchar(500) | YES |  | NULL |
| `cert_key_path` | varchar(500) | YES |  | NULL |
| `cert_key_pass` | varchar(255) | YES |  | NULL |
| `ativo` | tinyint(1) | NO |  | 0 |
| `atualizado_em` | timestamp | NO |  | current_timestamp() |

### `planos_manutencao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `componente_id` | int(11) | NO | MUL |  |
| `tipo_manutencao_id` | int(11) | NO | MUL |  |
| `intervalo_km` | int(11) | YES |  | NULL |
| `intervalo_dias` | int(11) | YES |  | NULL |
| `ultimo_km` | int(11) | YES |  | NULL |
| `ultima_data` | date | YES | MUL | NULL |
| `ativo` | tinyint(1) | YES |  | 1 |
| `created_at` | datetime | YES |  | current_timestamp() |
| `updated_at` | datetime | YES |  | current_timestamp() |

### `pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `filial_id` | int(10) unsigned | YES |  | NULL |
| `numero_serie` | varchar(50) | NO |  |  |
| `carcaca_codigo` | varchar(60) | YES |  | NULL |
| `marca` | varchar(50) | NO |  |  |
| `modelo` | varchar(50) | NO |  |  |
| `medida` | varchar(20) | NO |  |  |
| `tipo_rodado_ideal` | enum('direcional','tracao','livre','implemento','qualquer') | YES |  | 'qualquer' |
| `sulco_inicial` | decimal(4,2) | NO |  |  |
| `valor_compra` | decimal(12,2) | YES |  | NULL |
| `fornecedor_id` | int(11) | YES |  | NULL |
| `nota_fiscal` | varchar(60) | YES |  | NULL |
| `garantia_ate` | date | YES |  | NULL |
| `qr_token` | varchar(64) | YES | UNI | NULL |
| `dot` | varchar(20) | NO |  |  |
| `km_instalacao` | decimal(10,2) | NO |  |  |
| `km_rodado_total` | int(10) unsigned | YES |  | 0 |
| `data_instalacao` | date | NO |  |  |
| `vida_util_km` | int(11) | NO |  |  |
| `numero_recapagens` | int(11) | YES |  | 0 |
| `data_ultima_recapagem` | date | YES |  | NULL |
| `lote` | varchar(50) | YES |  | NULL |
| `data_entrada` | date | YES |  | curdate() |
| `status_id` | int(11) | NO | MUL |  |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `pneus_alocacao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `posicao_id` | int(11) | NO | MUL |  |
| `data_alocacao` | date | NO |  |  |
| `km_alocacao` | decimal(10,2) | NO |  |  |
| `data_desalocacao` | date | YES |  | NULL |
| `km_desalocacao` | decimal(10,2) | YES |  | NULL |
| `status` | enum('alocado','desalocado') | NO |  | 'alocado' |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `pneu_anexos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO |  |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `movimentacao_id` | bigint(20) unsigned | YES |  | NULL |
| `tipo` | enum('compra','instalacao','remocao','manutencao','sucata','inspecao','outro') | NO | MUL | 'outro' |
| `arquivo` | varchar(255) | NO |  |  |
| `legenda` | varchar(255) | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `pneu_auditoria`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO |  |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `acao` | varchar(60) | NO |  |  |
| `usuario_id` | int(11) | YES |  | NULL |
| `ip_origem` | varchar(45) | YES |  | NULL |
| `user_agent` | varchar(255) | YES |  | NULL |
| `dados_json` | longtext | YES |  | NULL |
| `created_at` | timestamp | NO | MUL | current_timestamp() |

### `pneu_calibragens`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | YES | MUL | NULL |
| `pressao_recomendada` | decimal(6,2) | YES |  | NULL |
| `pressao_aferida` | decimal(6,2) | NO |  |  |
| `diferenca` | decimal(6,2) | YES |  | NULL |
| `km_veiculo` | int(11) | YES |  | NULL |
| `responsavel` | varchar(120) | YES |  | NULL |
| `observacoes` | varchar(500) | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `data_calibragem` | datetime | NO |  | current_timestamp() |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `pneu_garantias`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO |  |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `tipo` | enum('fabricante','revenda','recapadora') | NO |  |  |
| `fornecedor_nome` | varchar(150) | YES |  | NULL |
| `fornecedor_id` | int(11) | YES |  | NULL |
| `validade_ate` | date | YES | MUL | NULL |
| `observacoes` | varchar(500) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `pneu_inspecoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO |  |  |
| `pneu_id` | int(11) | YES | MUL | NULL |
| `veiculo_id` | int(11) | YES | MUL | NULL |
| `motorista_id` | int(11) | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `pressao_psi` | decimal(5,1) | YES |  | NULL |
| `sulco_mm` | decimal(5,2) | YES |  | NULL |
| `tem_corte` | tinyint(1) | YES |  | 0 |
| `tem_bolha` | tinyint(1) | YES |  | 0 |
| `observacoes` | text | YES |  | NULL |
| `origem` | enum('motorista','gestor','inventario') | NO |  | 'gestor' |
| `data_inspecao` | datetime | NO |  | current_timestamp() |

### `pneu_inventario_itens`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `sessao_id` | bigint(20) unsigned | NO | MUL |  |
| `pneu_id` | int(11) | NO |  |  |
| `qr_token` | varchar(64) | YES |  | NULL |
| `encontrado` | tinyint(1) | NO |  | 1 |
| `observacoes` | varchar(255) | YES |  | NULL |
| `lido_em` | datetime | NO |  | current_timestamp() |

### `pneu_inventario_sessoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `filial_id` | int(10) unsigned | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `titulo` | varchar(120) | YES |  | NULL |
| `status` | enum('aberta','fechada') | NO |  | 'aberta' |
| `iniciada_em` | datetime | NO |  | current_timestamp() |
| `fechada_em` | datetime | YES |  | NULL |

### `pneu_manutencao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | YES | MUL | NULL |
| `data_manutencao` | date | NO |  |  |
| `km_veiculo` | decimal(10,2) | NO |  |  |
| `custo` | decimal(10,2) | NO |  |  |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `tipo_manutencao_id` | int(11) | NO | MUL |  |
| `fornecedor_id` | int(11) | YES |  | NULL |
| `garantia_ate` | date | YES |  | NULL |
| `sulco_pos_recapagem` | decimal(5,2) | YES |  | NULL |

### `pneu_medicoes_sulco`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `sulco_mm` | decimal(5,2) | NO |  |  |
| `km_veiculo` | int(11) | YES |  | NULL |
| `veiculo_id` | int(11) | YES |  | NULL |
| `observacoes` | varchar(500) | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `data_medicao` | datetime | NO |  | current_timestamp() |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `pneu_motivos_remocao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(10) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | YES | MUL | NULL |
| `codigo` | varchar(40) | NO |  |  |
| `nome` | varchar(120) | NO |  |  |
| `ativo` | tinyint(1) | NO |  | 1 |

### `pneu_movimentacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `tipo` | enum('entrada_estoque','instalacao','remocao','deslocamento','recapagem','manutencao','descarte') | NO | MUL |  |
| `veiculo_id` | int(11) | YES | MUL | NULL |
| `eixo_id` | int(11) | YES |  | NULL |
| `posicao_id` | int(11) | YES |  | NULL |
| `km_odometro` | int(11) | YES |  | NULL |
| `km_rodado` | int(11) | YES |  | NULL |
| `sulco_mm` | decimal(5,2) | YES |  | NULL |
| `custo` | decimal(12,2) | YES |  | 0.00 |
| `fornecedor_id` | int(11) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `ip_origem` | varchar(45) | YES |  | NULL |
| `motivo_remocao_id` | int(10) unsigned | YES |  | NULL |
| `filial_id` | int(10) unsigned | YES |  | NULL |
| `data_movimentacao` | datetime | NO | MUL | current_timestamp() |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `pneu_sucata`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO |  |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `motivo_remocao_id` | int(10) unsigned | YES |  | NULL |
| `motivo_texto` | varchar(255) | YES |  | NULL |
| `valor_recuperado` | decimal(12,2) | YES |  | NULL |
| `destino` | varchar(150) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `usuario_id` | int(11) | YES |  | NULL |
| `data_sucata` | datetime | NO |  | current_timestamp() |

### `posicoes_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO | UNI |  |
| `tipo_rodado` | enum('direcional','tracao','livre','implemento','qualquer') | NO |  | 'qualquer' |

### `ranking_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `periodo_inicio` | date | NO |  |  |
| `periodo_fim` | date | NO |  |  |
| `nota_consumo` | decimal(5,2) | YES |  | 0.00 |
| `nota_pontualidade` | decimal(5,2) | YES |  | 0.00 |
| `nota_multas` | decimal(5,2) | YES |  | 0.00 |
| `nota_ocorrencias` | decimal(5,2) | YES |  | 0.00 |
| `nota_checklist` | decimal(5,2) | YES |  | 0.00 |
| `nota_eficiencia` | decimal(5,2) | YES |  | 0.00 |
| `nota_total` | decimal(5,2) | YES |  | 0.00 |
| `posicao_ranking` | int(11) | YES | MUL | 0 |
| `total_rotas` | int(11) | YES |  | 0 |
| `rotas_pontuais` | int(11) | YES |  | 0 |
| `consumo_medio` | decimal(8,3) | YES |  | 0.000 |
| `total_multas` | int(11) | YES |  | 0 |
| `total_ocorrencias` | int(11) | YES |  | 0 |
| `checklists_completos` | int(11) | YES |  | 0 |
| `total_checklists` | int(11) | YES |  | 0 |
| `data_calculo` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `recapagens`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `pneu_id` | int(11) | NO | MUL |  |
| `data_recapagem` | date | NO |  |  |
| `fornecedor` | varchar(100) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |

### `recibos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `usuario_id` | int(11) | YES | MUL | NULL |
| `numero` | varchar(20) | NO |  |  |
| `data_emissao` | datetime | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `pagador` | varchar(255) | NO |  |  |
| `cpf_cnpj` | varchar(20) | YES |  | NULL |
| `descricao` | text | NO |  |  |
| `forma_pagamento` | varchar(50) | NO |  |  |
| `status` | enum('Pendente','Emitido','Cancelado') | NO |  | 'Pendente' |
| `observacoes` | text | YES |  | NULL |
| `arquivo` | varchar(255) | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `recomendacoes_ia`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `categoria` | varchar(50) | NO | MUL |  |
| `titulo` | varchar(255) | NO |  |  |
| `descricao` | text | NO |  |  |
| `impacto` | enum('baixo','medio','alto') | YES | MUL | 'medio' |
| `dados` | longtext | YES |  | NULL |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_implementacao` | timestamp | YES |  | NULL |
| `status` | enum('pendente','implementada','rejeitada') | YES |  | 'pendente' |

### `relatorios_agendados`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `report_type` | varchar(60) | NO |  |  |
| `frequencia` | enum('diario','semanal','mensal') | NO |  | 'mensal' |
| `destinatarios` | text | NO |  |  |
| `formato` | enum('pdf','html') | NO |  | 'html' |
| `ativo` | tinyint(1) | NO |  | 1 |
| `ultimo_envio` | datetime | YES |  | NULL |
| `proximo_envio` | datetime | YES | MUL | NULL |
| `criado_por` | int(11) | YES |  | NULL |
| `created_at` | datetime | NO |  | current_timestamp() |
| `updated_at` | datetime | NO |  | current_timestamp() |

### `rotas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `veiculo_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `estado_origem` | varchar(2) | YES | MUL | NULL |
| `cidade_origem_id` | int(11) | YES | MUL | NULL |
| `estado_destino` | varchar(2) | YES | MUL | NULL |
| `cidade_destino_id` | int(11) | YES | MUL | NULL |
| `data_saida` | datetime | NO | MUL |  |
| `data_chegada` | datetime | NO |  | current_timestamp() |
| `km_saida` | decimal(10,2) | YES |  | NULL |
| `km_chegada` | decimal(10,2) | YES |  | NULL |
| `distancia_km` | decimal(10,2) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `data_rota` | date | YES | MUL | NULL |
| `no_prazo` | tinyint(1) | NO |  | 0 |
| `frete` | decimal(10,2) | YES |  | 0.00 |
| `comissao` | decimal(10,2) | YES |  | 0.00 |
| `avaliacao_viagem` | decimal(3,1) | YES |  | NULL |
| `km_vazio` | decimal(10,2) | YES |  | 0.00 |
| `total_km` | decimal(10,2) | YES |  | 0.00 |
| `percentual_vazio` | decimal(5,2) | YES |  | 0.00 |
| `eficiencia_viagem` | decimal(5,2) | YES |  | 0.00 |
| `peso_carga` | decimal(10,2) | YES |  | NULL |
| `descricao_carga` | text | YES |  | NULL |
| `status` | enum('pendente','aprovado','rejeitado') | YES |  | 'pendente' |
| `fonte` | enum('motorista','gestor') | YES |  | 'motorista' |

### `seguro_atendimentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `seguro_cliente_id` | int(11) | NO | MUL |  |
| `matricula_conjunto` | varchar(100) | YES | MUL | NULL |
| `usuario_id` | int(11) | YES | MUL | NULL |
| `tipo` | enum('suporte','reclamacao','duvida','venda','acompanhamento','outros') | YES |  | 'suporte' |
| `prioridade` | enum('baixa','media','alta','urgente') | YES |  | 'media' |
| `status` | enum('aberto','em_andamento','aguardando','resolvido','fechado','cancelado') | YES | MUL | 'aberto' |
| `titulo` | varchar(255) | NO |  |  |
| `descricao` | text | NO |  |  |
| `solucao` | text | YES |  | NULL |
| `protocolo` | varchar(50) | YES | MUL | NULL |
| `data_abertura` | datetime | YES | MUL | current_timestamp() |
| `data_fechamento` | datetime | YES |  | NULL |
| `tempo_resposta` | int(11) | YES |  | NULL |
| `avaliacao` | tinyint(1) | YES |  | NULL |

### `seguro_clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `codigo` | varchar(50) | YES |  | NULL |
| `razao_social` | varchar(255) | YES |  | NULL |
| `nome_fantasia` | varchar(255) | YES |  | NULL |
| `identificador` | varchar(100) | YES |  | NULL |
| `tipo_pessoa` | enum('fisica','juridica') | NO |  | 'fisica' |
| `cpf_cnpj` | varchar(18) | NO | MUL |  |
| `nome_razao_social` | varchar(255) | NO |  |  |
| `sigla_fantasia` | varchar(255) | YES |  | NULL |
| `cep` | varchar(10) | YES |  | NULL |
| `logradouro` | varchar(255) | YES |  | NULL |
| `numero` | varchar(20) | YES |  | NULL |
| `complemento` | varchar(100) | YES |  | NULL |
| `bairro` | varchar(100) | YES |  | NULL |
| `cidade` | varchar(100) | YES |  | NULL |
| `uf` | varchar(2) | YES |  | NULL |
| `matricula` | varchar(100) | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `celular` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | YES |  | NULL |
| `unidade` | varchar(100) | YES |  | NULL |
| `porcentagem_recorrencia` | decimal(5,2) | YES |  | 0.00 |
| `observacoes` | text | YES |  | NULL |
| `data_cadastro` | datetime | YES | MUL | current_timestamp() |
| `data_atualizacao` | datetime | YES |  | current_timestamp() |
| `situacao` | enum('ativo','inativo','aguardando_ativacao') | NO | MUL | 'aguardando_ativacao' |

### `seguro_configuracoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `chave` | varchar(100) | NO |  |  |
| `valor` | text | YES |  | NULL |
| `tipo` | enum('texto','numero','booleano','json') | YES |  | 'texto' |
| `descricao` | varchar(255) | YES |  | NULL |
| `data_atualizacao` | datetime | YES |  | current_timestamp() |

### `seguro_contratos_clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `cliente_id` | int(11) | NO | MUL |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `matricula` | varchar(100) | NO | MUL |  |
| `placa` | varchar(255) | YES | MUL | NULL |
| `porcentagem_recorrencia` | decimal(5,2) | NO |  | 0.00 |
| `data_inicio` | date | YES |  | NULL |
| `valor` | decimal(10,2) | YES |  | 0.00 |
| `situacao` | enum('aguardando_ativacao','ativo','aguardando_link','aguardando_vistoria','devolvido_para_unidade','aguardando_assinatura','desistencia','negociar_cliente') | NO |  | 'aguardando_ativacao' |
| `tipo_os` | varchar(100) | YES |  | NULL |
| `envio_whatsapp` | enum('sim','nao') | NO |  | 'nao' |
| `envio_email` | enum('sim','nao') | NO |  | 'nao' |
| `planilha` | enum('sim','nao') | NO |  | 'nao' |
| `ativo` | enum('sim','nao') | NO | MUL | 'sim' |
| `observacoes` | text | YES |  | NULL |
| `data_criacao` | timestamp | NO |  | current_timestamp() |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `seguro_empresa_clientes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_adm_id` | int(11) | NO | MUL |  |
| `razao_social` | varchar(255) | NO |  |  |
| `nome_fantasia` | varchar(255) | YES |  | NULL |
| `cnpj` | varchar(18) | NO | MUL |  |
| `inscricao_estadual` | varchar(50) | YES |  | NULL |
| `inscricao_municipal` | varchar(50) | YES |  | NULL |
| `telefone` | varchar(20) | YES |  | NULL |
| `celular` | varchar(20) | YES |  | NULL |
| `email` | varchar(255) | YES |  | NULL |
| `site` | varchar(255) | YES |  | NULL |
| `endereco` | varchar(255) | YES |  | NULL |
| `numero` | varchar(20) | YES |  | NULL |
| `complemento` | varchar(100) | YES |  | NULL |
| `bairro` | varchar(100) | YES |  | NULL |
| `cidade` | varchar(100) | YES |  | NULL |
| `estado` | varchar(2) | YES |  | NULL |
| `cep` | varchar(10) | YES |  | NULL |
| `responsavel` | varchar(100) | YES |  | NULL |
| `logo` | varchar(255) | YES |  | NULL |
| `porcentagem_fixa` | decimal(5,2) | YES |  | 0.00 |
| `dia_fechamento` | int(2) | YES |  | 25 |
| `unidade` | varchar(100) | YES |  | NULL |
| `tema` | enum('claro','escuro') | YES |  | 'claro' |
| `cor_primaria` | varchar(7) | YES |  | '#667eea' |
| `cor_secundaria` | varchar(7) | YES |  | '#764ba2' |
| `cor_destaque` | varchar(7) | YES |  | '#28a745' |
| `observacoes` | text | YES |  | NULL |
| `plano` | enum('premium','enterprise') | YES |  | 'premium' |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `data_atualizacao` | datetime | YES |  | current_timestamp() |
| `status` | enum('ativo','inativo') | NO | MUL | 'ativo' |

### `seguro_equipamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `seguro_cliente_id` | int(11) | NO | MUL |  |
| `tipo` | varchar(100) | NO | MUL |  |
| `descricao` | varchar(255) | NO |  |  |
| `marca` | varchar(100) | YES |  | NULL |
| `modelo` | varchar(100) | YES |  | NULL |
| `numero_serie` | varchar(100) | YES |  | NULL |
| `data_instalacao` | date | YES |  | NULL |
| `localizacao` | varchar(255) | YES |  | NULL |
| `situacao` | enum('ativo','inativo','manutencao','substituido') | YES | MUL | 'ativo' |
| `observacoes` | text | YES |  | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `data_atualizacao` | datetime | YES |  | NULL |

### `seguro_financeiro`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `cliente_id` | int(11) | YES | MUL | NULL |
| `cliente_nao_encontrado` | enum('sim','nao') | YES |  | 'nao' |
| `unidade` | varchar(255) | YES |  | NULL |
| `ponteiro` | varchar(50) | YES |  | NULL |
| `seguro_cliente_id` | int(11) | NO | MUL |  |
| `tipo` | enum('receita','despesa','comissao','recorrencia') | NO | MUL |  |
| `categoria` | varchar(100) | YES |  | NULL |
| `data_emissao` | date | YES |  | NULL |
| `descricao` | varchar(255) | NO |  |  |
| `valor` | decimal(10,2) | NO |  |  |
| `placa` | varchar(100) | YES |  | NULL |
| `conjunto` | varchar(100) | YES |  | NULL |
| `matricula` | varchar(100) | YES |  | NULL |
| `data_vencimento` | date | NO | MUL |  |
| `data_pagamento` | date | YES |  | NULL |
| `status` | enum('pendente','pago','vencido','cancelado') | YES | MUL | 'pendente' |
| `forma_pagamento` | varchar(50) | YES |  | NULL |
| `observacoes` | text | YES |  | NULL |
| `numero_documento` | varchar(50) | YES |  | NULL |
| `associado` | varchar(255) | YES |  | NULL |
| `classe` | varchar(100) | YES |  | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `valor_pago` | decimal(10,2) | YES |  | 0.00 |
| `data_baixa` | date | YES | MUL | NULL |
| `proposals` | varchar(100) | YES |  | NULL |

### `seguro_historico_importacoes`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `usuario_id` | int(11) | NO | MUL |  |
| `nome_arquivo` | varchar(255) | NO |  |  |
| `data_hora` | datetime | NO | MUL | current_timestamp() |
| `total_registros` | int(11) | YES |  | 0 |
| `processados` | int(11) | YES |  | 0 |
| `total_erros` | int(11) | YES |  | 0 |
| `detalhes` | text | YES |  | NULL |

### `seguro_historico_relatorios`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `seguro_usuario_id` | int(11) | NO | MUL |  |
| `tipo_relatorio` | varchar(50) | NO | MUL |  |
| `data_geracao` | datetime | NO | MUL | current_timestamp() |
| `periodo_inicio` | date | NO |  |  |
| `periodo_fim` | date | NO |  |  |
| `total_registros` | int(11) | YES |  | 0 |
| `formato` | varchar(10) | YES |  | 'csv' |
| `filtros_aplicados` | text | YES |  | NULL |
| `arquivo_path` | varchar(255) | YES |  | NULL |

### `seguro_logs`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `usuario_id` | int(11) | YES | MUL | NULL |
| `acao` | varchar(100) | NO | MUL |  |
| `modulo` | varchar(100) | NO | MUL |  |
| `descricao` | text | YES |  | NULL |
| `ip` | varchar(50) | YES |  | NULL |
| `user_agent` | varchar(255) | YES |  | NULL |
| `data_hora` | datetime | YES | MUL | current_timestamp() |

### `seguro_usuarios`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `usuario_adm_id` | int(11) | YES | MUL | NULL |
| `seguro_empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(255) | NO |  |  |
| `email` | varchar(255) | NO | UNI |  |
| `senha` | varchar(255) | NO |  |  |
| `nivel_acesso` | enum('admin','gerente','operador','visualizador') | YES |  | 'operador' |
| `telefone` | varchar(20) | YES |  | NULL |
| `foto` | varchar(255) | YES |  | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `data_atualizacao` | datetime | YES |  | current_timestamp() |
| `ultimo_acesso` | datetime | YES |  | NULL |
| `tema` | enum('claro','escuro','auto') | YES |  | 'claro' |
| `status` | enum('ativo','inativo') | NO |  | 'ativo' |

### `sequencias_documentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `tipo_documento` | enum('NFE','CTE','MDFE') | NO |  |  |
| `serie` | varchar(10) | NO |  |  |
| `ultimo_numero` | int(11) | YES |  | 0 |
| `proximo_numero` | int(11) | YES |  | 1 |
| `ano_exercicio` | int(11) | NO | MUL |  |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `sistema_login_config`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI | 1 |
| `recaptcha_enabled` | tinyint(1) | NO |  | 0 |
| `recaptcha_site_key` | varchar(120) | YES |  | NULL |
| `recaptcha_secret_key` | varchar(255) | YES |  | NULL |
| `recaptcha_apos_tentativas` | int(11) | NO |  | 3 |
| `atualizado_em` | timestamp | NO |  | current_timestamp() |

### `status_contas_pagar`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO | UNI |  |

### `status_manutencao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |

### `status_pagamento`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO | UNI |  |
| `descricao` | text | YES |  | NULL |

### `status_pagamentos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |

### `status_parcelas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO | UNI |  |

### `status_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(30) | NO | UNI |  |
| `descricao` | text | YES |  | NULL |
| `cor` | varchar(20) | YES |  | '#666666' |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `status_rotas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |
| `created_at` | timestamp | NO |  | current_timestamp() |

### `status_veiculos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |

### `tipos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |

### `tipos_carretas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |
| `descricao` | text | YES |  | NULL |
| `uso_recomendado` | varchar(100) | YES |  | NULL |
| `capacidade_media` | decimal(5,2) | YES |  | NULL |
| `nro_eixos` | int(11) | NO |  |  |

### `tipos_cavalos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |
| `eixos` | int(11) | NO |  |  |
| `tracao` | int(11) | NO |  |  |
| `capacidade_maxima` | decimal(5,2) | YES |  | NULL |
| `descricao` | text | YES |  | NULL |
| `apoio` | tinyint(1) | YES |  | 0 |

### `tipos_combustivel`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |

### `tipos_contrato`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO |  |  |

### `tipos_despesa_fixa`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO |  |  |
| `descricao` | text | YES |  | NULL |

### `tipos_manutencao`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(100) | NO | UNI |  |
| `descricao` | text | YES |  | NULL |

### `tipo_manutencao_pneus`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(50) | NO | UNI |  |

### `trip_timeline_events`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO |  |  |
| `rota_id` | int(11) | NO |  |  |
| `event_uuid` | varchar(36) | NO | UNI |  |
| `tipo` | varchar(40) | NO |  |  |
| `titulo` | varchar(160) | NO |  |  |
| `detalhe` | text | YES |  | NULL |
| `latitude` | decimal(10,7) | YES |  | NULL |
| `longitude` | decimal(10,7) | YES |  | NULL |
| `evento_em` | datetime | NO |  |  |
| `created_at` | datetime | NO |  | current_timestamp() |

### `usuarios`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `nome` | varchar(255) | NO |  |  |
| `email` | varchar(255) | NO | UNI |  |
| `senha` | varchar(255) | NO |  |  |
| `tipo_usuario` | enum('admin','gestor','motorista') | YES |  | 'gestor' |
| `status` | enum('ativo','inativo') | YES |  | 'ativo' |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `is_admin` | tinyint(1) | NO |  | 0 |
| `foto_perfil` | varchar(255) | YES |  | NULL |
| `pode_editar_usuarios_sistema` | tinyint(1) | YES |  | 0 |
| `pode_criar_usuarios_sistema` | tinyint(1) | YES |  | 0 |
| `pode_acessar_lucratividade` | tinyint(1) | YES |  | 0 |
| `pode_acessar_relatorios_avancados` | tinyint(1) | YES |  | 0 |
| `pode_gerenciar_configuracoes` | tinyint(1) | YES |  | 0 |
| `pode_aprovar_abastecimentos` | tinyint(1) | YES |  | 1 |
| `pode_ver_dados_financeiros` | tinyint(1) | NO |  | 0 |
| `reset_token` | varchar(255) | YES | MUL | NULL |
| `reset_expires` | datetime | YES | MUL | NULL |
| `pode_acessar_sistema_fiscal` | tinyint(1) | YES |  | 0 |
| `pode_acessar_gestao_pneus` | tinyint(1) | YES |  | 0 |
| `is_oculto` | tinyint(1) | YES | MUL | 0 |
| `acesso_todas_empresas` | tinyint(1) | YES | MUL | 0 |
| `two_factor_enabled` | tinyint(1) | NO |  | 0 |
| `two_factor_secret` | varchar(64) | YES |  | NULL |
| `two_factor_confirmed_at` | datetime | YES |  | NULL |

### `usuarios_adm`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `nome` | varchar(255) | NO |  |  |
| `email` | varchar(255) | NO |  |  |
| `senha` | varchar(255) | NO |  |  |
| `created_at` | timestamp | YES |  | current_timestamp() |
| `two_factor_enabled` | tinyint(1) | NO |  | 0 |
| `two_factor_secret` | varchar(64) | YES |  | NULL |
| `two_factor_confirmed_at` | datetime | YES |  | NULL |

### `usuarios_motoristas`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `motorista_id` | int(11) | NO | MUL |  |
| `nome` | varchar(255) | NO |  |  |
| `senha` | varchar(255) | NO |  |  |
| `status` | enum('ativo','inativo') | YES |  | 'ativo' |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `foto_perfil` | varchar(255) | YES |  | NULL |
| `two_factor_enabled` | tinyint(1) | NO |  | 0 |
| `two_factor_secret` | varchar(64) | YES |  | NULL |
| `two_factor_confirmed_at` | datetime | YES |  | NULL |

### `veiculos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO | PRI |  |
| `empresa_id` | int(11) | NO | MUL |  |
| `placa` | varchar(10) | NO | MUL |  |
| `modelo` | varchar(255) | NO |  |  |
| `marca` | varchar(100) | YES |  | NULL |
| `ano` | int(11) | YES |  | NULL |
| `cor` | varchar(50) | YES |  | NULL |
| `chassi` | varchar(50) | YES |  | NULL |
| `renavam` | varchar(50) | YES |  | NULL |
| `km_atual` | decimal(10,2) | YES |  | 0.00 |
| `documento` | varchar(255) | YES |  | NULL |
| `data_cadastro` | datetime | YES |  | current_timestamp() |
| `observacoes` | text | YES |  | NULL |
| `created_at` | timestamp | NO |  | current_timestamp() |
| `updated_at` | timestamp | NO |  | current_timestamp() |
| `capacidade_carga` | decimal(10,2) | YES |  | NULL |
| `capacidade_passageiros` | int(11) | YES |  | NULL |
| `numero_motor` | varchar(100) | YES |  | NULL |
| `proprietario` | varchar(100) | YES |  | NULL |
| `foto_veiculo` | varchar(255) | YES |  | NULL |
| `tipo_combustivel_id` | int(11) | YES | MUL | NULL |
| `potencia_motor` | varchar(50) | YES |  | NULL |
| `numero_eixos` | int(11) | YES |  | NULL |
| `carroceria_id` | int(11) | YES | MUL | NULL |
| `status_id` | int(11) | YES | MUL | NULL |
| `ultima_manutencao` | date | YES |  | NULL |
| `km_ultima_manutencao` | decimal(10,2) | YES |  | NULL |
| `id_cavalo` | int(11) | YES | MUL | NULL |
| `id_carreta` | int(11) | YES | MUL | NULL |
| `capacidade_tanque` | decimal(7,2) | YES |  | NULL |
| `consumo_medio` | decimal(5,2) | YES |  | NULL |
| `custo_operacional_km` | decimal(8,2) | YES |  | NULL |

### `view_seguro_atendimentos_abertos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO |  | 0 |
| `protocolo` | varchar(50) | YES |  | NULL |
| `titulo` | varchar(255) | NO |  |  |
| `tipo` | enum('suporte','reclamacao','duvida','venda','acompanhamento','outros') | YES |  | 'suporte' |
| `prioridade` | enum('baixa','media','alta','urgente') | YES |  | 'media' |
| `status` | enum('aberto','em_andamento','aguardando','resolvido','fechado','cancelado') | YES |  | 'aberto' |
| `data_abertura` | datetime | YES |  | current_timestamp() |
| `cliente` | varchar(255) | NO |  |  |
| `empresa` | varchar(255) | NO |  |  |
| `horas_abertas` | bigint(21) | YES |  | NULL |

### `view_seguro_clientes_ativos`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `id` | int(11) | NO |  | 0 |
| `codigo` | varchar(50) | YES |  | NULL |
| `nome_razao_social` | varchar(255) | NO |  |  |
| `cpf_cnpj` | varchar(18) | NO |  |  |
| `cidade` | varchar(100) | YES |  | NULL |
| `uf` | varchar(2) | YES |  | NULL |
| `unidade` | varchar(100) | YES |  | NULL |
| `porcentagem_recorrencia` | decimal(5,2) | YES |  | 0.00 |
| `empresa` | varchar(255) | NO |  |  |
| `seguro_empresa_id` | int(11) | NO |  | 0 |

### `view_seguro_financeiro_resumo`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `seguro_empresa_id` | int(11) | NO |  |  |
| `empresa` | varchar(255) | NO |  |  |
| `total_movimentacoes` | bigint(21) | NO |  | 0 |
| `total_receitas` | decimal(32,2) | YES |  | NULL |
| `total_despesas` | decimal(32,2) | YES |  | NULL |
| `total_comissoes` | decimal(32,2) | YES |  | NULL |
| `total_recorrencias` | decimal(32,2) | YES |  | NULL |
| `total_pendente` | decimal(32,2) | YES |  | NULL |
| `total_pago` | decimal(32,2) | YES |  | NULL |

### `v_gamificacao_atual`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `motorista_id` | int(11) | NO |  |  |
| `motorista_nome` | varchar(255) | NO |  |  |
| `telefone` | varchar(20) | YES |  | NULL |
| `empresa_id` | int(11) | NO |  |  |
| `pontos_totais` | int(11) | YES |  | 0 |
| `nivel_atual` | varchar(20) | YES |  | 'Bronze' |
| `badge_atual` | varchar(50) | YES |  | NULL |
| `total_badges` | int(11) | YES |  | 0 |
| `rotas_concluidas` | int(11) | YES |  | 0 |
| `desafios_completos` | int(11) | YES |  | 0 |
| `streak_atual` | int(11) | YES |  | 0 |
| `streak_maximo` | int(11) | YES |  | 0 |
| `progresso_nivel` | decimal(5,2) | YES |  | 0.00 |
| `pontos_para_proximo_nivel` | int(11) | YES |  | 100 |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |

### `v_ranking_atual`

| Campo | Tipo | Nulo | Chave | Default |
|---|---|---|---|---|
| `motorista_id` | int(11) | NO |  |  |
| `motorista_nome` | varchar(255) | NO |  |  |
| `telefone` | varchar(20) | YES |  | NULL |
| `empresa_id` | int(11) | NO |  |  |
| `nota_total` | decimal(5,2) | YES |  | 0.00 |
| `posicao_ranking` | int(11) | YES |  | 0 |
| `nota_consumo` | decimal(5,2) | YES |  | 0.00 |
| `nota_pontualidade` | decimal(5,2) | YES |  | 0.00 |
| `nota_multas` | decimal(5,2) | YES |  | 0.00 |
| `nota_ocorrencias` | decimal(5,2) | YES |  | 0.00 |
| `nota_checklist` | decimal(5,2) | YES |  | 0.00 |
| `total_rotas` | int(11) | YES |  | 0 |
| `data_atualizacao` | timestamp | NO |  | current_timestamp() |
| `classificacao` | varchar(16) | YES |  | NULL |

---

## Como regerar este arquivo
```bash
# roda o gerador (query INFORMATION_SCHEMA) e sobrescreve docs/05a-dicionario-dados.md
# ver histórico do comando no repositório / CLAUDE.md
```
