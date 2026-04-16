-- Relacionamento MDF-e x NF-e (origem de mercadoria)
CREATE TABLE IF NOT EXISTS fiscal_mdfe_nfe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id INT NOT NULL,
    nfe_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mdfe_id) REFERENCES fiscal_mdfe(id) ON DELETE CASCADE,
    FOREIGN KEY (nfe_id) REFERENCES fiscal_nfe_clientes(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_mdfe_nfe (mdfe_id, nfe_id),
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_nfe (nfe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
