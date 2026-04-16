# App Android – Portal do Motorista

## O que tem no app hoje

### Autenticação
- **Login** com Nome e Senha (API `auth.php`)
- **Logout** (menu ⋮ → Sair ou botão Sair no Início); mensagem "Você foi desconectado com sucesso" na volta ao login
- Token salvo localmente (DataStore); renovação apenas com novo login
- Tratamento de sessão expirada (retorno ao login)

### Navegação
- **5 abas** na parte inferior: Início, Rotas, Abastecimentos, Checklists, Despesas
- **Barra superior** com título "Portal do Motorista" e menu (⋮) com opção Sair
- **Início (Dashboard)**: cards clicáveis (Rotas, Abastecimentos, Checklists, Despesas) com contadores de pendentes; ao tocar, abre a aba correspondente; botão **Sair** visível no canto

### Rotas
- **Lista** de rotas (origem → destino, data, placa, status)
- **Nova Rota**: formulário com Data da Rota (seletor de data), Veículo, Origem (Estado + Cidade), Destino (Estado + Cidade), Data/Hora Saída e Chegada (data por seletor + hora), KM Saída/Chegada, KM Vazio, Valor Frete, Comissão (calculada por %), Entrega no Prazo, Peso/Descrição da Carga, Observações
- **FAB (+)** para abrir o formulário de nova rota
- Sem edição/exclusão de rota; sem tela de detalhe

### Abastecimentos
- **Lista** de abastecimentos (data, litros, valor, placa, status)
- **Novo Abastecimento**: Data da Rota e Data do Abastecimento (seletor), Veículo (por data), Rota, Tipo Combustível, Posto, Litros, Preço/Litro, Valor Total (cálculo automático), KM Atual, Forma de Pagamento, Observações
- **FAB (+)** para novo abastecimento
- Sem edição/exclusão; sem anexo de comprovante

### Checklists
- **Lista** de checklists (data, rota, placa)
- **Novo Checklist**: seleção de Rota, Veículo, checkbox "Todos os itens OK"
- **FAB (+)** para novo checklist
- Sem edição/exclusão; sem checklist item a item (só “todos OK”)

### Despesas
- **Lista** de rotas; ao tocar em uma rota, abre o **formulário de despesas** daquela rota
- Campos: Descarga, Pedágios, Caixinha, Estacionamento, Lavagem, Borracharia, Elétrica/Mecânica, Adiantamento; botão **Salvar despesas**
- Sem listagem de despesas já salvas por rota; sem edição posterior

### Tema e layout
- **Cores** alinhadas ao sistema web (azul #3b82f6, fundos escuros/claros)
- **Tela de login** com logo Frotec (mesma do web), cores Frotec, frases de fundo espalhadas, card escuro
- **Seletor de data** nos formulários (toque para abrir calendário; data atual como padrão)
- **Padding inferior** nos formulários para o botão Salvar não ficar atrás da barra de navegação

### API
- Base URL configurável em `Api.kt` (ex.: emulador `10.0.2.2`, celular IP do PC)
- Endpoints: auth, dashboard, rotas, abastecimentos, checklists, despesas, veículos, estados, cidades
- Autenticação por Bearer token no header e na query

---

## O que dá para melhorar

### Funcionalidades
| Melhoria | Descrição |
|---------|-----------|
| **Editar/Excluir rota** | Permitir editar ou excluir rota pela lista (se a API suportar). |
| **Detalhe da rota** | Tela ao tocar em um item da lista: ver todos os campos e opção de editar. |
| **Pull-to-refresh** | Arrastar para baixo para atualizar lista em Rotas, Abastecimentos, Checklists. |
| **Filtros nas listas** | Filtrar rotas por data, status; abastecimentos por data; etc. |
| **Checklist completo** | Tela com cada item do checklist (óleo, pneus, documentação, etc.) em vez de só "todos OK". |
| **Anexo de comprovante** | No abastecimento, permitir foto ou galeria para comprovante (depende da API). |
| **Despesas: ver já salvas** | Ao abrir uma rota, mostrar despesas já cadastradas e permitir editar. |
| **Total de despesas** | Mostrar total calculado no formulário de despesas antes de salvar. |
| **Sair em todas as abas** | Botão Sair visível também nas outras abas ou no menu da barra (já existe no ⋮). |

### UX e interface
| Melhoria | Descrição |
|---------|-----------|
| **Estado vazio** | Ilustração ou texto mais amigável quando não há rotas/abastecimentos/checklists. |
| **Loading nas listas** | Skeleton ou indicador claro ao carregar listas. |
| **Confirmação antes de salvar** | Resumo antes de enviar rota/abastecimento/checklist (opcional). |
| **Validação em tempo real** | Mensagens de erro por campo (ex.: KM inválido, data de chegada antes da saída). |
| **Acessibilidade** | contentDescription em ícones, tamanhos de toque adequados, contraste. |

### Técnico e segurança
| Melhoria | Descrição |
|---------|-----------|
| **HTTPS em produção** | Usar sempre HTTPS para a base URL em produção. |
| **URL configurável na tela** | Tela de configuração ou primeiro acesso para informar IP/URL da API (evitar recompilar). |
| **Tratamento de erro de rede** | Mensagem clara quando não há internet ou servidor indisponível. |
| **Refresh do token** | Se a API tiver refresh token, renovar sem pedir login de novo. |
| **Logs** | Remover ou reduzir nível de log em produção (ex.: HttpLoggingInterceptor). |

### Dados e offline
| Melhoria | Descrição |
|---------|-----------|
| **Cache local** | Guardar última lista de rotas/abastecimentos para exibir offline e atualizar quando online. |
| **Envio em fila** | Se implementar rascunhos, enviar quando tiver conexão. |

---

## Resumo

- **Já existe**: login/logout, dashboard com contadores e atalhos, CRUD básico de rotas (criar), abastecimentos (criar), checklists (criar), despesas por rota, tema Frotec, seletor de data, logo e frases no login.
- **Próximos passos sugeridos**: pull-to-refresh, editar/excluir e detalhe de rota (e equivalentes onde fizer sentido), checklist item a item, total de despesas no formulário, melhor tratamento de erros e estado vazio, e HTTPS + URL configurável em produção.
