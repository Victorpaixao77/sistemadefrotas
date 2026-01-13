-- =====================================================
-- 📋 TABELAS DE POSIÇÃO FINANCEIRA
-- Sistema de Gestão de Frotas - Painel Administrativo
-- =====================================================

-- Tabela de boletos
CREATE TABLE IF NOT EXISTS adm_boletos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_adm_id INT NOT NULL,
    empresa_cliente_id INT NOT NULL,
    numero_boleto VARCHAR(50) NOT NULL UNIQUE,
    codigo_barras VARCHAR(100),
    linha_digitavel VARCHAR(100),
    descricao TEXT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_emissao DATE NOT NULL,
    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
    data_pagamento DATE NULL,
    forma_pagamento VARCHAR(50) NULL,
    observacoes TEXT NULL,
    mes_referencia VARCHAR(7) NULL COMMENT 'Formato: YYYY-MM',
    tipo_boleto ENUM('mensalidade', 'adicional', 'multa', 'outros') DEFAULT 'mensalidade',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresa_adm (empresa_adm_id),
    INDEX idx_empresa_cliente (empresa_cliente_id),
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_numero_boleto (numero_boleto),
    INDEX idx_mes_referencia (mes_referencia),
    FOREIGN KEY (empresa_adm_id) REFERENCES empresa_adm(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_cliente_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Boletos gerados para empresas clientes';

-- Tabela de histórico de pagamentos
CREATE TABLE IF NOT EXISTS adm_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boleto_id INT NOT NULL,
    empresa_adm_id INT NOT NULL,
    empresa_cliente_id INT NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    comprovante_path VARCHAR(255) NULL,
    observacoes TEXT NULL,
    registrado_por INT NULL COMMENT 'ID do admin que registrou',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_boleto (boleto_id),
    INDEX idx_empresa_adm (empresa_adm_id),
    INDEX idx_empresa_cliente (empresa_cliente_id),
    INDEX idx_data_pagamento (data_pagamento),
    FOREIGN KEY (boleto_id) REFERENCES adm_boletos(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_adm_id) REFERENCES empresa_adm(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_cliente_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Histórico de pagamentos registrados';

-- Tabela de configurações de geração de boletos
CREATE TABLE IF NOT EXISTS adm_config_boletos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_adm_id INT NOT NULL,
    dia_vencimento INT NOT NULL DEFAULT 10 COMMENT 'Dia do mês para vencimento',
    gerar_automatico TINYINT(1) DEFAULT 1 COMMENT 'Gerar boletos automaticamente',
    valor_base DECIMAL(10,2) DEFAULT 0.00,
    aplicar_por_veiculo TINYINT(1) DEFAULT 1 COMMENT 'Aplicar valor por veículo',
    valor_por_veiculo DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_empresa (empresa_adm_id),
    FOREIGN KEY (empresa_adm_id) REFERENCES empresa_adm(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Configurações de geração de boletos por empresa';

-- =====================================================
-- 📊 ÍNDICES ADICIONAIS PARA PERFORMANCE
-- =====================================================

-- Índice composto para consultas por empresa e status
CREATE INDEX IF NOT EXISTS idx_empresa_status ON adm_boletos(empresa_adm_id, status);

-- Índice composto para consultas por empresa e data
CREATE INDEX IF NOT EXISTS idx_empresa_data ON adm_boletos(empresa_adm_id, data_vencimento);

-- =====================================================
-- 📝 NOTAS
-- =====================================================
-- Este script cria as tabelas necessárias para o sistema
-- de posição financeira do painel administrativo.
-- Os boletos são gerados de forma fictícia para controle
-- de pagamentos das empresas clientes.
