[← Índice](README.md) · [← Banco](05-banco-de-dados.md) · [← Dicionário](05a-dicionario-dados.md)

# 05b · Tabelas por Módulo (descrição de negócio)

Finalidade de cada uma das **230 tabelas**, agrupadas por módulo. A estrutura (colunas,
tipos, chaves, FKs) está em [05a-dicionario-dados.md](05a-dicionario-dados.md). Números =
linhas aproximadas (`information_schema`, no momento da geração).

> **Fonte:** onde a tabela tem `COMMENT` no banco (41 tabelas), usamos o texto **oficial**.
> Nas demais, a finalidade é **inferida pelo nome/colunas** e marcada com _(inferido)_.

## 🚚 Operação / TMS
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `clientes` | 0 | Clientes/embarcadores (tomadores de frete) _(inferido)_ |
| `ordens_transporte` | 0 | Ordens de transporte (pedido de frete) _(inferido)_ |
| `aprovacoes` | 0 | Trilha de aprovação/rejeição de OTs _(inferido)_ |
| `rotas` | 357 | Rotas/viagens executadas _(inferido)_ — ver [ROTAS.md](ROTAS.md) |
| `status_rotas` | 4 | Domínio de status de rota _(inferido)_ |
| `disponibilidades` | 10 | Disponibilidade de veículos _(inferido)_ |
| `disponibilidades_motoristas` | 9 | Disponibilidade de motoristas _(inferido)_ |
| `implementos` | 1 | Carretas/reboques/dollys (ativos próprios) _(inferido)_ |
| `antt_frete_coeficientes` | 428 | Coeficientes do piso ANTT (Lei 13.703/2018) _(inferido)_ |

## 🚛 Frota / Veículos
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `veiculos` | 14 | Cadastro de veículos (cavalos) _(inferido)_ |
| `cavalos_carretas` | 17 | Vínculo cavalo↔carreta _(inferido)_ |
| `carrocerias` | 8 | Tipos de carroceria _(inferido)_ |
| `eixos` | 0 | Cadastro de eixos _(inferido)_ |
| `eixos_veiculos` | 13 | **Eixos de veículos criados na gestão interativa** |
| `gestao_montagem` | 0 | **Montagem custom (posições de eixos/tanque) por veículo** |
| `categorias` | 3 | Categorias de veículo _(inferido)_ |
| `tipos_carretas` | 8 | Domínio de tipos de carreta _(inferido)_ |
| `tipos_cavalos` | 4 | Domínio de tipos de cavalo _(inferido)_ |
| `status_veiculos` | 3 | Domínio de status do veículo _(inferido)_ |
| `filiais` | 0 | Filiais da empresa _(inferido)_ |

## 👤 Motoristas
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `motoristas` | 15 | Cadastro de motoristas _(inferido)_ |
| `motoristas_log` | 7 | Log de alterações de motorista _(inferido)_ |
| `documentos_motoristas` | 0 | Documentos do motorista (CNH etc.) _(inferido)_ |
| `motorista_dependentes` | 0 | Dependentes _(inferido)_ |
| `motorista_periodos` | 0 | Períodos/vínculos _(inferido)_ |
| `motorista_treinamentos` | 0 | Treinamentos _(inferido)_ |
| `motorista_favoritos` | 2 | Favoritos do motorista (app) _(inferido)_ |
| `jornada_motorista` | 8 | Jornada (Lei do Motorista) _(inferido)_ |
| `categorias_cnh` | 9 | Domínio de categorias de CNH _(inferido)_ |
| `api_tokens_motorista` | 10 | Tokens de autenticação do app _(inferido)_ |
| `usuarios_motoristas` | 0 | Vínculo usuário↔motorista _(inferido)_ |

## ⛽ Abastecimento / Despesas
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `abastecimentos` | 542 | Registros de abastecimento _(inferido)_ |
| `despesas_viagem` | 483 | Despesas de viagem _(inferido)_ |
| `despesas_fixas` | 96 | Despesas fixas (rateadas) _(inferido)_ |
| `tipos_despesa_fixa` | 17 | Domínio de despesa fixa _(inferido)_ |
| `tipos_combustivel` | 5 | Domínio de combustível _(inferido)_ |
| `pedagios` / `pedagios_viagem` | 0 | Pedágios (gestão / por viagem, app) _(inferido)_ |

## 🔧 Manutenção
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `manutencoes` | 11 | Ordens/registros de manutenção _(inferido)_ |
| `manutencao_anexos` | 0 | Anexos de manutenção _(inferido)_ |
| `componentes_manutencao` | 25 | Componentes/itens de manutenção _(inferido)_ |
| `planos_manutencao` | 8 | Planos preventivos _(inferido)_ — ver [PLANOS_MANUTENCAO.md](PLANOS_MANUTENCAO.md) |
| `tipos_manutencao` | 10 | Domínio de tipo de manutenção _(inferido)_ |
| `status_manutencao` | 4 | Domínio de status _(inferido)_ |

## 🛞 Pneus (ver GESTAO_PNEUS_TABELAS.md)
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `pneus` | 12 | Cadastro de pneus _(inferido)_ |
| `estoque_pneus` | 12 | Estoque de pneus _(inferido)_ |
| `pneu_movimentacoes` | 22 | **Histórico único de movimentações — fonte da verdade p/ custo/km e vida útil** |
| `pneu_calibragens` | 0 | **Histórico de calibragem (pressão)** |
| `pneu_medicoes_sulco` | 0 | **Histórico de medições de sulco (TWI)** |
| `alocacoes_pneus_flexiveis` | 37 | **Alocações de pneus nos eixos flexíveis** |
| `pneus_alocacao` / `posicoes_pneus` / `eixo_pneus` | 1/29/0 | Alocação/posições nos eixos _(inferido)_ |
| `instalacoes_pneus` | 26 | Instalações _(inferido)_ |
| `pneu_manutencao` / `tipo_manutencao_pneus` | 7/6 | Manutenção de pneu + domínio _(inferido)_ |
| `pneu_inspecoes` / `pneu_auditoria` / `pneu_anexos` | 0/5/0 | Inspeção/auditoria/anexos _(inferido)_ |
| `pneu_garantias` / `pneu_sucata` / `recapagens` | 0/0/0 | Garantia/sucata/recapagem _(inferido)_ |
| `pneu_inventario_sessoes` / `pneu_inventario_itens` | 0/0 | Inventário de pneus _(inferido)_ |
| `pneu_motivos_remocao` | 9 | Domínio de motivo de remoção _(inferido)_ |
| `gastos_pneus` | 0 | Gastos com pneus _(inferido)_ |
| `status_pneus` | 6 | Domínio de status _(inferido)_ |

## 💰 Financeiro
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `contas_pagar` | 13 | Contas a pagar _(inferido)_ |
| `status_contas_pagar` | 7 | Domínio de status _(inferido)_ |
| `financiamentos` | 12 | Financiamentos _(inferido)_ |
| `parcelas_financiamento` | 73 | Parcelas de financiamento _(inferido)_ |
| `status_parcelas` | 5 | Domínio de status de parcela _(inferido)_ |
| `multas` | 12 | Multas de trânsito _(inferido)_ |
| `comissoes_pagamentos` | 11 | Pagamentos de comissão _(inferido)_ |
| `formas_pagamento` | 11 | Domínio de forma de pagamento _(inferido)_ |
| `status_pagamento`/`status_pagamentos` | 5/4 | Domínios de status de pagamento _(inferido)_ |
| `bancos` | 13 | Domínio de bancos _(inferido)_ |
| `recibos` | 0 | Recibos _(inferido)_ |
| `tipos_contrato` | 6 | Domínio de tipo de contrato _(inferido)_ |

## 🧾 Fiscal (ver MANUAL_FISCAL_INDICE.md) — comentários oficiais
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `fiscal_cte` | 2 | **Conhecimentos de Transporte Eletrônico** |
| `fiscal_cte_itens` | 0 | **Dados complementares do CT-e (tomador, valores, carga, protocolo)** |
| `fiscal_cte_eventos` | 0 | Eventos do CT-e _(inferido)_ |
| `fiscal_mdfe` | 0 | **Manifestos de Documentos Fiscais Eletrônicos** |
| `fiscal_mdfe_cte` | 1 | **Relacionamento entre MDF-e e CT-e** |
| `fiscal_mdfe_nfe` | 0 | **Relacionamento entre MDF-e e NF-e de origem** |
| `fiscal_mdfe_*` (condutores, contratantes, lacres, produtos, reboques, seguros, pagamentos, vale_pedagio…) | 0 | Componentes do MDF-e _(inferido)_ |
| `fiscal_nfe_emitidas` | 0 | **NF-e modelo 55 emitida pelo emitente** |
| `fiscal_nfe_clientes` | 1 | **Notas fiscais recebidas dos clientes** |
| `fiscal_nfe_itens` | 1 | **Itens das notas fiscais dos clientes** |
| `fiscal_distribuicao_nsu` | 0 | **Último NSU da Distribuição DFe (sincroniza NF-e por CNPJ)** |
| `fiscal_eventos_fiscais` | 0 | **Eventos oficiais (cancelamento, CCE, encerramento)** |
| `fiscal_status_historico` | 0 | **Histórico de mudanças de status dos documentos** |
| `fiscal_logs` | 1752 | **Logs de todas as operações fiscais** |
| `fiscal_alertas` | 0 | **Alertas e notificações fiscais** |
| `fiscal_certificados_digitais` | 3 | **Certificados digitais das empresas** |
| `fiscal_config_empresa` | 3 | **Configurações fiscais por empresa** |
| `fiscal_config_envio_automatico` | 3 | **Envio automático de documentos** |
| `fiscal_config_seguranca` | 3 | **Segurança e criptografia (fiscal)** |
| `fiscal_fila_processamento` | 0 | Fila de processamento fiscal _(inferido)_ |
| `sequencias_documentos` | 3 | **Sequências de numeração para documentos fiscais** |
| `certificados_denatran` / `configuracoes_denatran` | 0/1 | DENATRAN _(inferido)_ — ver [DENATRAN_INTEGRACAO.md](DENATRAN_INTEGRACAO.md) |
| `pef_ciot_operacoes` / `pef_ciot_log` / `pef_config_empresa` | 0/0/0 | CIOT/PEF _(inferido)_ — ver [PEF_CIOT_INTEGRACAO.md](PEF_CIOT_INTEGRACAO.md) |
| `mdfe_envios` / `mdfe_validacao_log` | 0/0 | Envio/validação MDF-e _(inferido)_ |

## 📍 GPS / Telemetria
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `gps_logs` | 566 | Posições GPS brutas _(inferido)_ |
| `gps_logs_resumido` | 0 | Agregação diária de GPS _(inferido)_ |
| `gps_ultima_posicao` | 1 | Última posição por veículo _(inferido)_ |
| `gps_cercas` / `gps_cerca_alertas` / `gps_cerca_estado` | 0 | Geofences: def./alertas/estado _(inferido)_ |
| `gps_alertas_operacionais` | 0 | Alertas operacionais de GPS _(inferido)_ |
| `gps_geocode_cache` | 0 | Cache de geocodificação _(inferido)_ |

## 📦 App do motorista (viagem/eventos)
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `checklist_viagem` | 4 | Checklists de viagem _(inferido)_ |
| `comprovantes_entrega` | 0 | POD (comprovante de entrega) _(inferido)_ |
| `conferencia_carga` | 0 | Conferência de carga _(inferido)_ |
| `ocorrencias_viagem` | 0 | Ocorrências de viagem _(inferido)_ |
| `panico_alertas` | 0 | Botão de pânico _(inferido)_ |
| `trip_timeline_events` | 0 | Timeline do Modo Viagem _(inferido)_ |
| `openai_ocr_usage` / `insights_ia` | 11/0 | Uso de OCR / insights _(inferido)_ |

## 🤖 IA / BI
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `insights_ia` / `recomendacoes_ia` | 0/0 | Insights/recomendações de IA _(inferido)_ |
| `configuracoes_ia` | 0 | Config de IA por empresa _(inferido)_ |
| `empresa_openai_credentials` / `empresa_vision_ai` | 0/0 | Credenciais IA por empresa _(inferido)_ |
| `bi_cache` / `bi_metas` | 1/0 | Cache/metas de BI _(inferido)_ |
| `historico_analises` | 0 | Histórico de análises _(inferido)_ |
| `alertas_inteligentes` | 0 | Alertas inteligentes _(inferido)_ |

## 🏆 Gamificação
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `gamificacao_motoristas` | 5 | Estado de gamificação por motorista _(inferido)_ |
| `conquistas_motoristas` / `desafios_motoristas` | 0/0 | Conquistas/desafios _(inferido)_ |
| `competicoes` | 3 | Competições _(inferido)_ |
| `ranking_motoristas` | 5 | Ranking calculado _(inferido)_ |
| `config_ranking` / `configuracoes_desafios` / `configuracoes_niveis` / `configuracoes_badges` / `configuracoes_metricas` | — | Configurações de gamificação _(inferido)_ |

## 🔔 Notificações
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `notificacoes` | 85 464 | Notificações (tabela viva; arquivar via cron) _(inferido)_ |
| `notifications` | 36 | Notificações (outra origem) _(inferido)_ |
| `notificacoes_preferencias` / `_ia_config` / `_feedback` / `_digest_envio` / `_sync_controle` | — | Config/feedback/digest/sync _(inferido)_ |
| `alertas_sistema` | 11 | Alertas do sistema _(inferido)_ |
| `configuracoes_notificacoes` | 2 | Config de notificações _(inferido)_ |

## 📅 Calendário
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `calendario_eventos` | 13 | Eventos (CNH/multas via triggers) _(inferido)_ |
| `calendario_templates` | 0 | Templates de evento _(inferido)_ |
| `categorias_calendario` | 12 | Domínio de categorias _(inferido)_ |

## 🔐 Segurança / Usuários
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `usuarios` | 9 | **Usuários do sistema com permissões granulares** |
| `usuarios_adm` | 0 | Usuários do painel adm _(inferido)_ |
| `configuracoes_seguranca` | 3 | Política de segurança por empresa _(inferido)_ |
| `login_attempts` | 2 | Tentativas de login (anti-brute-force) _(inferido)_ |
| `acessos_conhecidos` | 4 | Dispositivos/IP conhecidos _(inferido)_ |
| `admin_ip_whitelist` | 0 | Whitelist de IP admin _(inferido)_ |
| `auth_remember_tokens` | 0 | Tokens "lembrar-me" _(inferido)_ |
| `log_acessos` | 247 | **Log de acessos (logins, logouts, tentativas)** |
| `logs_acesso` | 158 | Log de acessos (outra origem) _(inferido)_ |
| `sistema_login_config` | 0 | Config de login _(inferido)_ |

## 🗺️ Cadastros base / domínios (lookups)
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `estados` | 27 | UFs do Brasil _(inferido)_ |
| `cidades` | 5 581 | Municípios _(inferido)_ |
| `fornecedores` | 3 | **Fornecedores/parceiros por empresa** |
| `fornecedor_clientes` | 0 | Vínculo fornecedor↔cliente _(inferido)_ |
| `configuracoes` | 5 | Configurações gerais _(inferido)_ |
| `configuracoes_cache` / `_filtros` / `_performance` | 2 | Configs diversas _(inferido)_ |
| `tipos` / `categorias` | 0/3 | Domínios genéricos _(inferido)_ |
| `empresa_clientes` | 5 | **Empresas-cliente (tenants)** _(inferido)_ |
| `empresa_adm` | 6 | Vínculo empresa↔adm _(inferido)_ |

## 🛡️ Sistema Seguro (módulo `seguro_*`) — comentários oficiais
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `seguro_clientes` | 15 | **Clientes comissionados do Sistema Seguro** |
| `seguro_contratos_clientes` | 5 | **Contratos com matrículas e placas vinculadas** |
| `seguro_atendimentos` | 3 | **Atendimentos (vínculo a conjunto/matrícula)** |
| `seguro_financeiro` | 302 | **Movimentações financeiras do Sistema Seguro** |
| `seguro_empresa_clientes` | 2 | **Empresas com acesso ao Sistema Seguro** |
| `seguro_usuarios` | 2 | **Usuários do Sistema Seguro** |
| `seguro_configuracoes` | 0 | **Configurações personalizadas por empresa** |
| `seguro_equipamentos` | 0 | Equipamentos segurados _(inferido)_ |
| `seguro_logs` | 313 | **Logs de atividades do sistema** |
| `seguro_historico_importacoes` / `seguro_historico_relatorios` | 4/0 | Histórico de import/relatórios _(inferido)_ |

> **Nota:** o "Sistema Seguro" é um módulo à parte (clientes comissionados/atendimentos/
> financeiro) com views próprias (`view_seguro_*`). Não confundir com "seguro do veículo".

## 🏢 Plataforma / Administração (`adm_*`)
| Tabela | Linhas | Finalidade |
|--------|-------:|-----------|
| `adm_planos` | 9 | Planos comerciais da plataforma _(inferido)_ |
| `adm_modulos` / `adm_empresa_modulos` | 7/1 | Módulos e liberação por empresa _(inferido)_ |
| `adm_pagamentos` / `adm_boletos` / `adm_config_boletos` | 0/0/0 | **Cobrança/boletos por empresa** |
| `adm_licenca_limites` / `licenciamento` | 0 | Limites de licença _(inferido)_ |
| `adm_health_checks` | 865 | Health-checks da plataforma _(inferido)_ |
| `adm_integration_checks` | 39 | Checagens de integração _(inferido)_ |
| `adm_request_metrics` | 10 806 | Métricas de requisição _(inferido)_ |
| `adm_storage_usage` / `adm_custos_mensais` | 0/5 | Uso de storage/custos _(inferido)_ |
| `adm_cron_jobs` / `adm_cron_runs` | 6/3 | Registro/execução de cron _(inferido)_ |
| `adm_deploys` / `adm_roadmap` | 0/4 | Deploys/roadmap _(inferido)_ |
| `adm_sessoes_ativas` / `adm_auditoria_suporte` / `adm_sql_log` | 1/0/20 | Sessões/auditoria de suporte/log SQL _(inferido)_ |
| `adm_comunicacoes` / `adm_comunicacao_modelos` | 9/7 | Comunicação com clientes _(inferido)_ |
| `adm_alertas` / `adm_eventos` | 4/11 | Alertas/eventos da plataforma _(inferido)_ |
| `adm_empresa_scores` / `health_score` | 18 | Score de saúde por empresa _(inferido)_ |
| `adm_ia_usage` | 10 | Uso de IA por empresa _(inferido)_ |
| `adm_platform_config` | 7 | Config global da plataforma _(inferido)_ |

Ver [AUDITORIA_CONTROL_CENTER_ADM.md](AUDITORIA_CONTROL_CENTER_ADM.md) para o painel adm.

---

> **Aviso de honestidade:** as finalidades marcadas _(inferido)_ derivam do nome/colunas,
> não de comentário oficial no banco. Para confirmar/ajustar, ler a estrutura em
> [05a](05a-dicionario-dados.md) e o código que usa a tabela. Correções são bem-vindas.
