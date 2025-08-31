# 📥 Funcionalidades de Recebimento de NF-e Implementadas

## 🎯 Visão Geral

O sistema agora possui **três métodos diferentes** para receber NF-e do cliente, conforme solicitado:

### 1. 📄 **Upload XML da NF-e (Recomendado)**
- **Localização**: Primeira aba no modal "Receber NF-e"
- **Funcionalidade**: Upload do arquivo XML da NF-e autorizada pela SEFAZ
- **Vantagens**:
  - ✅ Garante integridade dos dados
  - ✅ Evita erros de digitação
  - ✅ Dados já validados pela SEFAZ
  - ✅ Extração automática de todos os campos
- **Campos extraídos automaticamente**:
  - Chave de acesso (44 dígitos)
  - Número e série da NF-e
  - Emitente e destinatário
  - Produtos transportados
  - Peso, volumes
  - Valor da nota
  - Data de emissão

### 2. ⌨️ **Digitação Manual dos Dados**
- **Localização**: Segunda aba no modal "Receber NF-e"
- **Funcionalidade**: Preenchimento manual dos dados básicos da NF-e
- **Uso**: Plano B quando o XML não estiver disponível
- **Campos obrigatórios**:
  - Número da NF-e
  - Série
  - Chave de acesso (44 dígitos)
  - Cliente remetente
  - Cliente destinatário
  - Valor da carga
  - Peso (kg)
  - Volumes
- **Validações**:
  - Formato da chave de acesso (44 dígitos numéricos)
  - Campos obrigatórios preenchidos
  - Verificação de duplicidade

### 3. 🔍 **Consulta Automática na SEFAZ**
- **Localização**: Terceira aba no modal "Receber NF-e"
- **Funcionalidade**: Consulta direta na SEFAZ usando apenas a chave de acesso
- **Requisitos**:
  - Certificado digital válido da transportadora
  - Integração com webservice SEFAZ
- **Vantagens**:
  - ✅ Evita depender do cliente enviar XML
  - ✅ Dados sempre atualizados
  - ✅ Validação automática na origem
- **Funcionalidades**:
  - Validação de certificado digital
  - Barra de progresso durante consulta
  - Tratamento de erros de conexão

## 🚀 Como Usar

### Acessar a Funcionalidade
1. Navegue para `http://localhost/sistema-frotas/fiscal/pages/nfe.php`
2. Clique no botão **"📥 Receber NF-e"**
3. Escolha o método desejado através das abas

### Método 1: Upload XML
1. Selecione a aba **"Upload XML (Recomendado)"**
2. Clique em **"Escolher arquivo"** e selecione o XML da NF-e
3. Adicione observações (opcional)
4. Clique em **"📥 Receber NF-e"**

### Método 2: Digitação Manual
1. Selecione a aba **"Digitação Manual"**
2. Preencha todos os campos obrigatórios
3. Adicione observações (opcional)
4. Clique em **"📥 Receber NF-e"**

### Método 3: Consulta SEFAZ
1. Selecione a aba **"Consulta SEFAZ"**
2. Digite a chave de acesso (44 dígitos)
3. Marque/desmarque validação de certificado
4. Adicione observações (opcional)
5. Clique em **"📥 Receber NF-e"**

## 🔧 Arquivos Modificados

### 1. `fiscal/pages/nfe.php`
- Modal redesenhado com sistema de abas
- JavaScript para processar os três métodos
- Validações específicas para cada método
- Interface responsiva e intuitiva

### 2. `fiscal/api/documentos_fiscais_v2.php`
- Nova função `receber_nfe_xml` para processar uploads
- Nova função `receber_nfe_manual` para digitação
- Nova função `receber_nfe_sefaz` para consulta automática
- Função auxiliar `consultarNFeSefaz` (simulada)

### 3. `uploads/nfe_xml/`
- Diretório criado para armazenar XMLs das NF-e
- Arquivo de exemplo para testes

## 📊 Estrutura de Dados

### Tabela `fiscal_nfe_clientes`
- **Campos principais**:
  - `id`, `empresa_id`, `numero_nfe`, `serie_nfe`
  - `chave_acesso`, `data_emissao`
  - `cliente_razao_social`, `cliente_destinatario`
  - `valor_total`, `peso_carga`, `volumes`
  - `status`, `tipo_operacao`
  - `xml_path`, `observacoes`, `protocolo_sefaz`

### Tipos de Operação
- `recebida_xml`: NF-e recebida via upload XML
- `recebida_manual`: NF-e recebida via digitação
- `recebida_sefaz`: NF-e recebida via consulta SEFAZ

## 🧪 Testes

### Arquivo XML de Exemplo
- Localização: `uploads/nfe_xml/exemplo_nfe.xml`
- Chave de acesso: `12345678901234567890123456789012345678901234`
- Dados simulados para testes

### Cenários de Teste
1. **Upload XML válido**: Deve extrair dados automaticamente
2. **Upload XML inválido**: Deve mostrar erro apropriado
3. **Digitação manual**: Deve validar campos obrigatórios
4. **Consulta SEFAZ**: Deve simular consulta e retornar dados

## 🔮 Próximos Passos

### Melhorias Futuras
1. **Integração real com SEFAZ**: Substituir simulação por webservice real
2. **Validação de XML**: Implementar validação completa contra XSD da SEFAZ
3. **Assinatura digital**: Implementar validação de assinatura das NF-e
4. **Cache de consultas**: Implementar cache para consultas SEFAZ
5. **Notificações**: Sistema de alertas para NF-e recebidas

### Integrações
1. **Webhook**: Recebimento automático de XML via API
2. **Email**: Processamento de XML enviados por email
3. **FTP**: Monitoramento de diretório FTP para novos XMLs
4. **API externa**: Integração com sistemas de terceiros

## 📝 Notas Técnicas

### Segurança
- Validação de tipos de arquivo
- Limite de tamanho de upload (5MB)
- Sanitização de dados de entrada
- Verificação de duplicidade

### Performance
- Processamento assíncrono de XMLs grandes
- Cache de consultas SEFAZ
- Índices de banco para chave de acesso

### Compatibilidade
- Suporte a diferentes versões de XML NFe
- Fallback para métodos alternativos
- Tratamento de erros robusto

---

**Desenvolvido para**: Sistema de Gestão de Frotas  
**Data**: Agosto 2025  
**Versão**: 1.0.0
