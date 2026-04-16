-- Fila fiscal para processamento assíncrono + retry
CREATE TABLE IF NOT EXISTS fiscal_fila_processamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_documento VARCHAR(10) NOT NULL,
    acao VARCHAR(30) NOT NULL,
    payload_json LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    tentativas INT NOT NULL DEFAULT 0,
    max_tentativas INT NOT NULL DEFAULT 5,
    erro_ultimo TEXT NULL,
    proxima_tentativa_em DATETIME NULL,
    processado_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresa_status (empresa_id, status),
    INDEX idx_next_try (status, proxima_tentativa_em),
    INDEX idx_tipo_acao (tipo_documento, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

