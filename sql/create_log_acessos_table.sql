-- =====================================================
-- 📋 TABELA DE LOG DE ACESSOS
-- Sistema de Gestão de Frotas
-- =====================================================

CREATE TABLE IF NOT EXISTS log_acessos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    tipo_acesso ENUM('login', 'logout', 'tentativa_login_falha', 'sessao_expirada') DEFAULT 'login',
    status ENUM('sucesso', 'falha') DEFAULT 'sucesso',
    ip_address VARCHAR(45),
    user_agent TEXT,
    descricao TEXT,
    data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_tipo (tipo_acesso),
    INDEX idx_status (status),
    INDEX idx_data (data_acesso),
    INDEX idx_empresa_data (empresa_id, data_acesso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Log de acessos ao sistema - registra logins, logouts e tentativas falhas';

-- =====================================================
-- 📊 ÍNDICES ADICIONAIS PARA PERFORMANCE
-- =====================================================

-- Índice composto para consultas por empresa e tipo
CREATE INDEX IF NOT EXISTS idx_empresa_tipo ON log_acessos(empresa_id, tipo_acesso);

-- Índice composto para consultas por empresa e status
CREATE INDEX IF NOT EXISTS idx_empresa_status ON log_acessos(empresa_id, status);

-- =====================================================
-- 📝 NOTAS
-- =====================================================
-- Esta tabela é criada automaticamente pela função registrarLogAcesso()
-- caso não exista. Este script SQL é fornecido apenas para referência
-- ou criação manual se necessário.
