# 📅 Sistema de Calendário - Sistema de Frotas

## 🎯 **Visão Geral**

Sistema de calendário completo e automático que integra com o sistema de gestão de frotas, mostrando automaticamente todas as datas importantes e permitindo a criação de eventos personalizados.

## ✨ **Funcionalidades Principais**

### 🔄 **Eventos Automáticos**
- **CNH**: Vencimento de carteira de motoristas (alertas em 30, 60 e 90 dias)
- **Multas**: Vencimento de multas de veículos (alertas em 7, 15 e 30 dias)
- **Contas a Pagar**: Vencimento de contas (alertas em 7, 15 e 30 dias)
- **Financiamento**: Vencimento de parcelas (alertas em 7, 15 e 30 dias)
- **Manutenção**: Manutenção preventiva de veículos (baseado em dias e km)

### 🎨 **Eventos Personalizados**
- Criação de eventos customizados
- Categorização por tipo
- Cores personalizáveis
- Lembretes configuráveis
- Edição e exclusão

### 🔍 **Filtros e Visualização**
- Filtros por categoria de evento
- Visualização mensal, semanal, diária e em lista
- Tooltips informativos
- Drag & drop para alterar datas
- Responsivo para mobile
- **Eventos do Mês Atual**: Mostra apenas eventos futuros do mês atual ao acessar o calendário

## 🏗️ **Estrutura de Arquivos**

```
calendario/
├── index.php                 # Página principal do calendário
├── setup_database.sql        # Script de configuração do banco de dados
├── README.md                 # Esta documentação
├── assets/
│   ├── css/
│   │   └── calendario.css   # Estilos do calendário
│   └── js/
│       └── calendario.js    # JavaScript principal
└── api/
    ├── calendario_sync.php           # API para sincronização automática
    ├── calendario_cnh.php            # API para eventos de CNH
    ├── calendario_multas.php         # API para eventos de multas
    ├── calendario_contas.php         # API para eventos de contas
    ├── calendario_financiamento.php  # API para eventos de financiamento
    ├── calendario_manutencao.php     # API para eventos de manutenção
    ├── calendario_personal.php       # API para eventos personalizados
    ├── calendario_create_simples.php # API para criar eventos
    ├── calendario_update.php         # API para atualizar eventos
    └── calendario_delete.php         # API para excluir eventos
```

## 🚀 **Como Usar**

### 1. **Acesso ao Calendário**
- Clique no ícone do calendário no header do sistema
- Ou acesse diretamente: `/sistema-frotas/calendario/`

### 2. **Visualização de Eventos**
- Os eventos automáticos são carregados automaticamente
- Use os filtros para mostrar/ocultar categorias específicas
- Navegue entre meses, semanas e dias usando os botões do calendário
- **Eventos do Mês Atual**: Ao acessar o calendário, são mostrados apenas os eventos futuros do mês atual

### 3. **Sincronização Automática**
- **Multas**: Aparecem automaticamente ao cadastrar com data de vencimento
- **CNH**: Aparecem automaticamente ao cadastrar/alterar motoristas
- **Triggers**: Funcionam em background para sincronização em tempo real
- **Status**: Use o botão "Status" para verificar sincronização
- **Sincronizar**: Use o botão "Sincronizar" para forçar atualização

### 4. **Criar Evento Personalizado**
- Clique em "Novo Evento"
- Preencha os campos obrigatórios (título, categoria, data)
- Configure cor, descrição e lembrete
- Clique em "Salvar"

### 5. **Editar Evento**
- Clique em qualquer evento no calendário
- Modifique os campos desejados
- Clique em "Salvar"

### 6. **Excluir Evento**
- Abra o evento para edição
- Clique em "Excluir"
- Confirme a exclusão

## 🎨 **Códigos de Cores**

### **Eventos Automáticos**
- 🔴 **Vermelho**: Eventos críticos (vencimento hoje ou muito próximo)
- 🟡 **Amarelo**: Eventos de média prioridade
- 🔵 **Azul**: Eventos de baixa prioridade

### **Eventos Personalizados**
- 🎨 **Personalizável**: Escolha qualquer cor através do seletor

## 📱 **Responsividade**

- **Desktop**: Visualização completa com todos os controles
- **Tablet**: Layout adaptado para telas médias
- **Mobile**: Interface otimizada para dispositivos móveis

## 🔧 **Configurações Técnicas**

### **Dependências**
- FullCalendar 6.1.8
- SweetAlert2 para notificações
- Font Awesome para ícones
- Sistema de temas existente

### **Banco de Dados**
- Tabela `calendario_eventos` criada automaticamente
- Índices para performance
- Relacionamentos com empresa e usuário

### **APIs**
- RESTful com autenticação por sessão
- Validação de dados
- Tratamento de erros
- CORS configurado

## 🚨 **Alertas e Notificações**

### **Tipos de Alerta**
1. **Crítico** (Vermelho): Ação imediata necessária
2. **Alto** (Vermelho): Ação em até 7 dias
3. **Médio** (Amarelo): Ação em até 15-30 dias
4. **Baixo** (Azul): Ação em até 30-90 dias

### **Notificações**
- Alertas visuais no calendário
- Notificações popup para eventos próximos
- Cores diferenciadas por prioridade

## 🔄 **Sincronização Automática**

- **CNH**: Baseado na data de validade dos motoristas
- **Multas**: Baseado na data de vencimento das multas
- **Contas**: Baseado na data de vencimento das contas
- **Financiamento**: Baseado na data de vencimento das parcelas
- **Manutenção**: Baseado em dias desde última manutenção e km rodados

## 📊 **Performance**

- Carregamento assíncrono de eventos
- Filtros aplicados no cliente
- Índices de banco otimizados
- Cache de eventos por sessão

## 🛡️ **Segurança**

- Autenticação por sessão
- Validação de dados de entrada
- Sanitização de dados
- Controle de acesso por empresa

## 🚀 **Funcionalidades Implementadas**

### ✅ **Sincronização Automática**
- **Triggers de Banco**: Detectam automaticamente mudanças em multas e CNH
- **Sincronização em Tempo Real**: Eventos aparecem no calendário instantaneamente
- **APIs de Sincronização**: Controle manual e verificação de status
- **Procedimentos Armazenados**: Sincronização em lote quando necessário

### ✅ **Sistema de Triggers**
- **`trigger_multa_insert_calendario`**: Cria eventos ao cadastrar multas
- **`trigger_multa_update_calendario`**: Atualiza eventos ao modificar multas
- **`trigger_multa_delete_calendario`**: Remove eventos ao excluir multas
- **`trigger_motorista_cnh_calendario`**: Cria eventos ao cadastrar motoristas
- **`trigger_motorista_cnh_update_calendario`**: Atualiza eventos ao modificar CNH
- **`trigger_motorista_delete_calendario`**: Remove eventos ao excluir motoristas

### ✅ **Sistema de Eventos Inteligente**
- **Eventos do Mês Atual**: Filtro inteligente que mostra apenas eventos futuros do mês atual
- **Fallback para Eventos de Hoje**: Se não houver eventos futuros no mês, mostra eventos de hoje
- **Mensagens Contextuais**: Notificações personalizadas baseadas no contexto dos eventos
- **Remoção de Duplicatas**: Sistema automático para eliminar eventos duplicados baseado em título e data
- **Visualização Organizada**: Eventos agrupados por data para melhor legibilidade

### ✅ **APIs de Controle**
- **`calendario_sync.php`**: API completa para sincronização
- **Verificação de Status**: Monitora estado da sincronização
- **Sincronização Manual**: Força atualização quando necessário
- **Verificação de Triggers**: Confirma funcionamento automático

## 🚀 **Próximas Funcionalidades**

- [ ] Notificações push
- [ ] Integração com email
- [ ] Sincronização com Google Calendar
- [ ] Relatórios de eventos
- [ ] Backup automático
- [ ] API para integrações externas
- [ ] Dashboard de sincronização
- [ ] Logs de sincronização
- [ ] Configuração de alertas por email

## 📞 **Suporte**

Para dúvidas ou problemas:
1. **Verifique os logs** de erro do sistema
2. **Confirme se todas as dependências** estão instaladas
3. **Verifique as permissões** de banco de dados
4. **Teste as APIs** individualmente
5. **Use o botão Status** no calendário para verificar sincronização
6. **Execute o script de configuração**: `setup_database.sql` se necessário

## 📝 **Changelog**

### **v1.1.0** (2025-01-27)
- ✅ **Sincronização Automática** com triggers de banco
- ✅ **Triggers para Multas**: Criação, atualização e exclusão automática
- ✅ **Triggers para CNH**: Sincronização automática de motoristas
- ✅ **API de Sincronização**: Controle manual e verificação de status
- ✅ **Procedimentos Armazenados**: Sincronização em lote
- ✅ **Interface de Controle**: Botões de status e sincronização
- ✅ **Teste de Sincronização**: Ferramenta para verificar triggers
- ✅ **Sistema de Eventos Inteligente**: Filtro de eventos do mês atual com fallback para eventos de hoje
- ✅ **Correção de Duplicatas**: Sistema automático para remover eventos duplicados
- ✅ **Visualização Melhorada**: Eventos organizados por data com interface mais limpa

### **v1.0.0** (2025-08-23)
- ✅ Sistema de calendário completo
- ✅ Eventos automáticos integrados
- ✅ Eventos personalizados
- ✅ Interface responsiva
- ✅ Sistema de filtros
- ✅ Notificações inteligentes
- ✅ APIs RESTful
- ✅ Integração com sistema existente
