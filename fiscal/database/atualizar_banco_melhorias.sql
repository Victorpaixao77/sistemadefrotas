-- =====================================================
-- 📋 SCRIPT DE ATUALIZAÇÃO DO BANCO DE DADOS
-- 🚀 MELHORIAS IMPLEMENTADAS NO SISTEMA FISCAL
-- 🏷️  PREFIXO: fiscal_ (para organização do banco)
-- =====================================================
-- 
-- Este script adiciona as melhorias implementadas ao
-- sistema fiscal existente.
--
-- ⚠️  IMPORTANTE: Execute este script APÓS ter criado
--     as tabelas básicas do sistema fiscal.
--
-- 📅 Data: Agosto 2025
-- 🔧 Versão: 2.0.0
-- =====================================================

-- =====================================================
-- 🔐 1. ATUALIZAR TABELAS EXISTENTES COM NOVOS CAMPOS
-- =====================================================

-- Adicionar campos de assinatura digital à tabela fiscal_nfe_clientes
ALTER TABLE fiscal_nfe_clientes 
ADD COLUMN IF NOT EXISTS hash_assinatura VARCHAR(64) NULL COMMENT 'Hash SHA-256 da assinatura digital',
ADD COLUMN IF NOT EXISTS status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente' COMMENT 'Status da assinatura digital';

-- Adicionar campos de assinatura digital à tabela fiscal_cte
ALTER TABLE fiscal_cte 
ADD COLUMN IF NOT EXISTS hash_assinatura VARCHAR(64) NULL COMMENT 'Hash SHA-256 da assinatura digital',
ADD COLUMN IF NOT EXISTS status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente' COMMENT 'Status da assinatura digital';

-- Adicionar campos de assinatura digital à tabela fiscal_mdfe
ALTER TABLE fiscal_mdfe 
ADD COLUMN IF NOT EXISTS hash_assinatura VARCHAR(64) NULL COMMENT 'Hash SHA-256 da assinatura digital',
ADD COLUMN IF NOT EXISTS status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente' COMMENT 'Status da assinatura digital';

-- Adicionar campos de motorista e veículo à tabela fiscal_mdfe
ALTER TABLE fiscal_mdfe 
ADD COLUMN IF NOT EXISTS motorista_id INT NULL COMMENT 'ID do motorista responsável pela viagem',
ADD COLUMN IF NOT EXISTS veiculo_id INT NULL COMMENT 'ID do veículo de tração principal';

-- Adicionar foreign keys para motorista e veículo
ALTER TABLE fiscal_mdfe 
ADD CONSTRAINT IF NOT EXISTS fk_fiscal_mdfe_motorista 
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_fiscal_mdfe_veiculo 
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL;

-- =====================================================
-- 🎯 2. CRIAR TABELA DE EVENTOS FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_eventos_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_evento ENUM('cancelamento', 'encerramento', 'cce', 'inutilizacao', 'carta_correcao') NOT NULL,
    documento_tipo ENUM('nfe', 'cte', 'mdfe') NOT NULL,
    documento_id INT NOT NULL,
    protocolo_evento VARCHAR(50),
    justificativa TEXT,
    xml_evento LONGTEXT COMMENT 'XML completo do evento',
    xml_retorno LONGTEXT COMMENT 'XML de retorno da SEFAZ',
    status ENUM('pendente', 'aceito', 'rejeitado') DEFAULT 'pendente',
    data_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_processamento TIMESTAMP NULL,
    usuario_id INT NULL COMMENT 'Usuário que solicitou o evento',
    observacoes TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (documento_tipo, documento_id),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_status (status),
    INDEX idx_data_evento (data_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eventos fiscais oficiais (cancelamento, CCE, encerramento)';

-- =====================================================
-- 📊 3. CRIAR TABELA DE HISTÓRICO DE STATUS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_status_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    documento_tipo ENUM('nfe', 'cte', 'mdfe') NOT NULL,
    documento_id INT NOT NULL,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50),
    motivo_mudanca TEXT,
    usuario_id INT NULL COMMENT 'Usuário que alterou o status',
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_usuario VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (documento_tipo, documento_id),
    INDEX idx_status_anterior (status_anterior),
    INDEX idx_status_novo (status_novo),
    INDEX idx_data_alteracao (data_alteracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de todas as mudanças de status dos documentos';

-- =====================================================
-- 🛡️ 4. CRIAR TABELA DE CONFIGURAÇÃO DE SEGURANÇA
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_config_seguranca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    algoritmo_criptografia ENUM('AES-256', 'AES-128', '3DES') DEFAULT 'AES-256',
    chave_mestre VARCHAR(255) COMMENT 'Chave mestra para criptografia',
    salt_criptografia VARCHAR(64) COMMENT 'Salt para hash das senhas',
    tempo_expiracao_sessao INT DEFAULT 3600 COMMENT 'Tempo em segundos',
    max_tentativas_login INT DEFAULT 5,
    bloqueio_temporario INT DEFAULT 900 COMMENT 'Tempo de bloqueio em segundos',
    log_tentativas_acesso BOOLEAN DEFAULT TRUE,
    criptografar_arquivos BOOLEAN DEFAULT TRUE,
    backup_criptografado BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações de segurança e criptografia';

-- =====================================================
-- 🔑 5. CRIAR TABELA DE CERTIFICADOS DIGITAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_certificados_digitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome_certificado VARCHAR(255) NOT NULL,
    arquivo_certificado VARCHAR(255),
    senha_criptografada VARCHAR(255),
    tipo_certificado ENUM('A1', 'A3') DEFAULT 'A1',
    data_emissao DATE,
    data_vencimento DATE NOT NULL,
    cnpj_proprietario VARCHAR(18),
    razao_social_proprietario VARCHAR(255),
    emissor VARCHAR(255),
    serial_number VARCHAR(100),
    ativo BOOLEAN DEFAULT TRUE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificados digitais das empresas';

-- =====================================================
-- 📧 6. CRIAR TABELA DE ALERTAS FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_alerta ENUM('certificado_vencendo', 'mdfe_nao_encerrado', 'erro_sefaz', 'documento_pendente', 'backup_necessario') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    nivel ENUM('baixo', 'medio', 'alto', 'critico') DEFAULT 'medio',
    status ENUM('ativo', 'resolvido', 'ignorado') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_resolucao TIMESTAMP NULL,
    usuario_resolucao INT NULL,
    acao_requerida TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_tipo_alerta (tipo_alerta),
    INDEX idx_nivel (nivel),
    INDEX idx_status (status),
    INDEX idx_data_criacao (data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de alertas e notificações fiscais';

-- =====================================================
-- 🔧 7. ATUALIZAR TABELA DE LOGS FISCAIS
-- =====================================================

-- Expandir enum de ações
ALTER TABLE fiscal_logs 
MODIFY COLUMN acao ENUM('criacao', 'edicao', 'exclusao', 'cancelamento', 'encerramento', 'cce', 'inutilizacao', 'assinatura', 'verificacao', 'envio', 'recebimento', 'erro', 'sincronizacao') NOT NULL;

-- Adicionar campos de usuário e IP
ALTER TABLE fiscal_logs 
ADD COLUMN IF NOT EXISTS usuario_id INT NULL COMMENT 'ID do usuário que executou a ação',
ADD COLUMN IF NOT EXISTS ip_usuario VARCHAR(45) COMMENT 'IP do usuário';

-- Adicionar índice para status
ALTER TABLE fiscal_logs 
ADD INDEX IF NOT EXISTS idx_status (status);

-- =====================================================
-- 📊 8. ADICIONAR ÍNDICES DE PERFORMANCE
-- =====================================================

-- Índices para fiscal_nfe_clientes
ALTER TABLE fiscal_nfe_clientes 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_data_emissao (data_emissao);

-- Índices para fiscal_cte
ALTER TABLE fiscal_cte 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_data_emissao (data_emissao);

-- Índices para fiscal_mdfe
ALTER TABLE fiscal_mdfe 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_data_emissao (data_emissao),
ADD INDEX IF NOT EXISTS idx_motorista (motorista_id),
ADD INDEX IF NOT EXISTS idx_veiculo (veiculo_id);

-- =====================================================
-- 🚀 9. INSERIR DADOS INICIAIS DE CONFIGURAÇÃO
-- =====================================================

-- Configuração de segurança para empresa ID 1
INSERT INTO fiscal_config_seguranca (empresa_id, algoritmo_criptografia, chave_mestre, salt_criptografia) 
VALUES (1, 'AES-256', 'chave_mestra_empresa_1_' || UUID(), 'salt_empresa_1_' || UUID())
ON DUPLICATE KEY UPDATE 
    algoritmo_criptografia = VALUES(algoritmo_criptografia),
    chave_mestre = VALUES(chave_mestre),
    salt_criptografia = VALUES(salt_criptografia);

-- Configuração de segurança para empresa ID 2
INSERT INTO fiscal_config_seguranca (empresa_id, algoritmo_criptografia, chave_mestre, salt_criptografia) 
VALUES (2, 'AES-256', 'chave_mestra_empresa_2_' || UUID(), 'salt_empresa_2_' || UUID())
ON DUPLICATE KEY UPDATE 
    algoritmo_criptografia = VALUES(algoritmo_criptografia),
    chave_mestre = VALUES(chave_mestre),
    salt_criptografia = VALUES(salt_criptografia);

-- Configuração de segurança para empresa ID 3
INSERT INTO fiscal_config_seguranca (empresa_id, algoritmo_criptografia, chave_mestre, salt_criptografia) 
VALUES (3, 'AES-256', 'chave_mestra_empresa_3_' || UUID(), 'salt_empresa_3_' || UUID())
ON DUPLICATE KEY UPDATE 
    algoritmo_criptografia = VALUES(algoritmo_criptografia),
    chave_mestre = VALUES(chave_mestre),
    salt_criptografia = VALUES(salt_criptografia);

-- =====================================================
-- ✅ 10. VERIFICAÇÃO FINAL
-- =====================================================

-- Verificar se todas as tabelas foram criadas
SELECT 
    TABLE_NAME as 'Tabela',
    TABLE_ROWS as 'Registros',
    TABLE_COMMENT as 'Descrição'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN (
    'fiscal_eventos_fiscais',
    'fiscal_status_historico', 
    'fiscal_config_seguranca',
    'fiscal_certificados_digitais',
    'fiscal_alertas'
)
ORDER BY TABLE_NAME;

-- Verificar campos adicionados nas tabelas existentes
SELECT 
    TABLE_NAME as 'Tabela',
    COLUMN_NAME as 'Campo',
    COLUMN_TYPE as 'Tipo',
    IS_NULLABLE as 'Pode Nulo',
    COLUMN_DEFAULT as 'Padrão',
    COLUMN_COMMENT as 'Comentário'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('fiscal_nfe_clientes', 'fiscal_cte', 'fiscal_mdfe')
AND COLUMN_NAME IN ('hash_assinatura', 'status_assinatura', 'motorista_id', 'veiculo_id')
ORDER BY TABLE_NAME, COLUMN_NAME;

-- =====================================================
-- 🎉 SCRIPT CONCLUÍDO COM SUCESSO!
-- =====================================================
-- 
-- ✅ Todas as melhorias foram implementadas
-- ✅ Banco de dados atualizado
-- ✅ Índices de performance criados
-- ✅ Configurações de segurança aplicadas
-- 🏷️  Todas as tabelas com prefixo 'fiscal_'
--
-- 🚀 Próximo passo: Testar as funcionalidades no sistema
-- =====================================================
