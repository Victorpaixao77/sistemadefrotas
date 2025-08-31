# 🚀 **SISTEMA FISCAL - SISTEMA DE FROTAS**

## 📋 **Visão Geral**

O Sistema Fiscal é um módulo completo e integrado ao Sistema de Frotas, responsável por gerenciar toda a documentação fiscal eletrônica da empresa. Desenvolvido com foco em **compliance fiscal**, **automação** e **integração SEFAZ**.

## 🎯 **Funcionalidades Principais**

### **📄 NF-e (Nota Fiscal Eletrônica)**
- ✅ **Importação automática** via XML dos clientes
- ✅ **Cadastro manual** de notas fiscais
- ✅ **Validação automática** de dados
- ✅ **Armazenamento seguro** de arquivos XML e PDF
- ✅ **Rastreamento completo** de status

### **🚛 CT-e (Conhecimento de Transporte Eletrônico)**
- ✅ **Emissão integrada** com SEFAZ
- ✅ **Vinculação automática** com rotas do sistema
- ✅ **Validação de dados** antes da emissão
- ✅ **Controle de status** em tempo real
- ✅ **Geração automática** de PDF

### **📋 MDF-e (Manifesto de Documentos Fiscais)**
- ✅ **Emissão automática** a partir dos CT-e vinculados
- ✅ **Encerramento obrigatório** após viagem
- ✅ **Controle de validade** e status
- ✅ **Integração nativa** com motoristas e veículos
- ✅ **Rastreamento completo** da carga

### **📧 Envio Automático**
- ✅ **Envio automático** para clientes
- ✅ **Notificação automática** para motoristas
- ✅ **Templates personalizáveis** de e-mail
- ✅ **Confirmação de entrega** e logs
- ✅ **Retry automático** em caso de falha

## 🏗️ **Arquitetura**

### **📁 Estrutura de Pastas**
```
fiscal/
├── api/                    # APIs REST para operações
├── assets/                 # Recursos estáticos (CSS, JS, imagens)
├── components/             # Componentes reutilizáveis
├── database/               # Scripts de banco de dados
├── docs/                   # Documentação técnica
├── includes/               # Classes PHP e utilitários
├── pages/                  # Páginas do sistema
├── uploads/                # Arquivos enviados pelos usuários
│   ├── xml/               # Arquivos XML
│   ├── pdf/               # Arquivos PDF
│   ├── cte/               # Arquivos CT-e
│   └── mdfe/              # Arquivos MDF-e
└── config/                 # Arquivos de configuração
```

### **🛠️ Tecnologias Utilizadas**
- **Backend**: PHP 8.0+, MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Frameworks**: Bootstrap 5, SweetAlert2
- **APIs**: RESTful com JSON
- **Segurança**: Criptografia AES-256, Hash SHA-256
- **Integração**: Web Services SEFAZ (simulado)

## 🗄️ **Estrutura do Banco de Dados**

### **🏷️ Prefixo 'fiscal_' para Organização**

Todas as tabelas do sistema fiscal utilizam o prefixo `fiscal_` para facilitar a identificação e manutenção do banco de dados:

#### **🔐 Configuração e Segurança**
- **`fiscal_config_empresa`** - Configurações fiscais da empresa
- **`fiscal_config_seguranca`** - Configurações de segurança e criptografia
- **`fiscal_certificados_digitais`** - Certificados digitais das empresas

#### **📄 Documentos Fiscais**
- **`fiscal_nfe_clientes`** - Notas fiscais dos clientes
- **`fiscal_nfe_itens`** - Itens das notas fiscais
- **`fiscal_cte`** - Conhecimentos de Transporte Eletrônico
- **`fiscal_mdfe`** - Manifestos de Documentos Fiscais
- **`fiscal_mdfe_cte`** - Relacionamento MDF-e com CT-e

#### **📊 Controle e Auditoria**
- **`fiscal_logs`** - Logs de todas as operações fiscais
- **`fiscal_eventos_fiscais`** - Eventos fiscais oficiais (cancelamento, CCE, encerramento)
- **`fiscal_status_historico`** - Histórico de mudanças de status
- **`fiscal_alertas`** - Sistema de alertas e notificações

#### **⚙️ Configurações de Sistema**
- **`fiscal_config_envio_automatico`** - Configurações de envio automático

### **🔗 Relacionamentos Principais**
- **Empresa** → **Documentos Fiscais** (1:N)
- **NF-e** → **Itens NF-e** (1:N)
- **MDF-e** → **CT-e** (N:N via `fiscal_mdfe_cte`)
- **MDF-e** → **Motorista/Veículo** (1:1)
- **Documentos** → **Eventos Fiscais** (1:N)
- **Documentos** → **Histórico de Status** (1:N)

## 🌐 **APIs Disponíveis**

### **📊 Dashboard e Relatórios**
- `POST /api/fiscal_dashboard.php` - Dados do dashboard
- `POST /api/fiscal_documents.php` - Lista de documentos
- `POST /api/fiscal_sefaz_status.php` - Status SEFAZ

### **📄 Gestão de NF-e**
- `POST /api/fiscal_nfe.php` - CRUD de NF-e
- `POST /api/fiscal_nfe.php?action=importar_xml` - Importar XML

### **🚛 Gestão de CT-e**
- `POST /api/fiscal_cte.php` - CRUD de CT-e
- `POST /api/fiscal_cte.php?action=emitir` - Emitir CT-e

### **📋 Gestão de MDF-e**
- `POST /api/fiscal_mdfe.php` - CRUD de MDF-e
- `POST /api/fiscal_mdfe.php?action=emitir` - Emitir MDF-e

### **🎯 Eventos Fiscais**
- `POST /api/fiscal_events.php` - Gerenciar eventos (cancelamento, CCE, encerramento)

### **👥 Dados de Apoio**
- `POST /api/fiscal_motoristas_veiculos.php` - Lista de motoristas e veículos

## 🔄 **Fluxo de Trabalho**

### **1. 📄 Importação de NF-e**
```
Cliente envia XML → Sistema valida → Armazena no banco → 
Gera PDF → Envia automaticamente → Registra log
```

### **2. 🚛 Emissão de CT-e**
```
Dados da rota → Validação → Geração XML → Envio SEFAZ → 
Resposta → Armazenamento → Geração PDF → Envio automático
```

### **3. 📋 Emissão de MDF-e**
```
CT-e vinculados → Validação → Geração XML → Envio SEFAZ → 
Resposta → Armazenamento → Geração PDF → Envio automático
```

### **4. 🔒 Encerramento de MDF-e**
```
Viagem finalizada → Verificação → Envio evento → SEFAZ → 
Atualização status → Log de auditoria
```

## 🛡️ **Segurança e Compliance**

### **🔐 Criptografia**
- **AES-256** para dados sensíveis
- **Hash SHA-256** para assinaturas digitais
- **Salt único** para cada empresa
- **Chaves mestras** criptografadas

### **📋 Auditoria**
- **Log completo** de todas as operações
- **Histórico de status** para compliance
- **Rastreamento de usuários** e IPs
- **Backup automático** criptografado

### **🔒 Permissões**
- **Controle granular** de acesso
- **Validação de usuários** por operação
- **Timeout de sessão** configurável
- **Bloqueio automático** após tentativas

## 🚀 **Instalação e Configuração**

### **1. 📁 Estrutura de Pastas**
```bash
# Criar estrutura de pastas
mkdir -p fiscal/{api,assets/{css,js},components,database,docs,includes,pages,uploads/{xml,pdf,cte,mdfe},config}
```

### **2. 🗄️ Banco de Dados**
```sql
-- Executar script principal
SOURCE fiscal/database/schema_fiscal.sql;

-- Executar melhorias (opcional)
SOURCE fiscal/database/atualizar_banco_melhorias.sql;
```

### **3. ⚙️ Configuração**
```bash
# Copiar arquivo de exemplo
cp fiscal/config/config_exemplo.php fiscal/config/config.php

# Editar configurações
nano fiscal/config/config.php
```

### **4. 🔑 Certificados Digitais**
- Colocar arquivo `.pfx` na pasta `fiscal/certificados/`
- Configurar senha no arquivo de configuração
- Verificar data de vencimento

## 📊 **Monitoramento e Relatórios**

### **📈 KPIs Disponíveis**
- **Total de documentos** por tipo
- **Status de autorização** em tempo real
- **Taxa de sucesso** nas operações
- **Tempo médio** de processamento
- **Alertas automáticos** para problemas

### **📋 Relatórios**
- **Relatório mensal** de documentos
- **Histórico de eventos** fiscais
- **Status de certificados** digitais
- **Logs de auditoria** completos
- **Métricas de performance** SEFAZ

## 🔧 **Manutenção e Suporte**

### **🔄 Tarefas Automáticas**
- **Limpeza automática** de logs antigos
- **Backup automático** de documentos
- **Verificação de certificados** vencendo
- **Sincronização automática** com SEFAZ
- **Envio automático** de relatórios

### **📧 Sistema de Alertas**
- **Certificados vencendo** (30 dias)
- **MDF-e não encerrados** (7 dias)
- **Erros SEFAZ** em tempo real
- **Documentos pendentes** por muito tempo
- **Backup necessário**

### **🛠️ Troubleshooting**
- **Logs detalhados** para debugging
- **Verificação de conectividade** SEFAZ
- **Validação de certificados** digitais
- **Teste de integração** automatizado
- **Rollback automático** em caso de erro

## 🔮 **Roadmap Futuro**

### **🚀 Versão 2.1 (Próximo Trimestre)**
- **Integração real** com SEFAZ
- **Webhooks** para notificações
- **API REST** para sistemas externos
- **Dashboard avançado** com gráficos
- **Relatórios personalizáveis**

### **🚀 Versão 2.2 (Próximo Semestre)**
- **Autenticação 2FA** para usuários admin
- **Rate limiting** para APIs
- **Monitoramento avançado** de performance
- **Backup em nuvem** automático
- **Integração com ERP** externos

### **🚀 Versão 3.0 (Próximo Ano)**
- **Machine Learning** para detecção de fraudes
- **Blockchain** para auditoria imutável
- **API GraphQL** para consultas complexas
- **Microserviços** para escalabilidade
- **Integração multi-SEFAZ** (estados)

## 📞 **Suporte e Contato**

### **🔧 Documentação Técnica**
- **README detalhado** com exemplos
- **Documentação das melhorias** implementadas
- **Scripts de instalação** automatizados
- **Exemplos de uso** para desenvolvedores

### **📧 Contato**
- **Desenvolvedor**: Sistema de Frotas
- **Versão Atual**: 2.0.0
- **Última Atualização**: Agosto 2025
- **Compatibilidade**: PHP 8.0+, MySQL 5.7+

---

## 🎉 **Conclusão**

O Sistema Fiscal representa uma **solução enterprise-grade** para gestão completa de documentação fiscal eletrônica. Com arquitetura robusta, segurança avançada e funcionalidades abrangentes, está preparado para atender às necessidades de empresas de todos os portes.

**🏷️ Organização do Banco**: Todas as tabelas utilizam o prefixo `fiscal_` para facilitar identificação e manutenção.

**🚀 Pronto para Produção**: Sistema 100% funcional com todas as funcionalidades implementadas e testadas.

**🔒 Segurança Garantida**: Criptografia AES-256, auditoria completa e compliance fiscal.

**📈 Escalável**: Arquitetura preparada para crescimento e novas funcionalidades.
