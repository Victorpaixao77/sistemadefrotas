-- =====================================================
-- 🔢 TABELA DE SEQUÊNCIAS DE DOCUMENTOS FISCAIS
-- 📋 Compatível com sistema existente (fiscal_*)
-- =====================================================

CREATE TABLE IF NOT EXISTS sequencias_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_documento ENUM('NFE', 'CTE', 'MDFE') NOT NULL,
    serie VARCHAR(10) NOT NULL,
    ultimo_numero INT DEFAULT 0,
    proximo_numero INT DEFAULT 1,
    ano_exercicio INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_empresa_tipo_serie_ano (empresa_id, tipo_documento, serie, ano_exercicio),
    INDEX idx_empresa_tipo (empresa_id, tipo_documento),
    INDEX idx_ano_exercicio (ano_exercicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sequências de numeração para documentos fiscais';

-- Inserir dados iniciais para sequências
INSERT INTO sequencias_documentos (empresa_id, tipo_documento, serie, ultimo_numero, proximo_numero, ano_exercicio) VALUES
(1, 'NFE', '1', 0, 1, YEAR(CURRENT_DATE)),
(1, 'CTE', '1', 0, 1, YEAR(CURRENT_DATE)),
(1, 'MDFE', '1', 0, 1, YEAR(CURRENT_DATE))
ON DUPLICATE KEY UPDATE 
    ultimo_numero = VALUES(ultimo_numero),
    proximo_numero = VALUES(proximo_numero),
    updated_at = CURRENT_TIMESTAMP;
