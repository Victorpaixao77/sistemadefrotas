-- Pagamentos de comissão por rota
CREATE TABLE IF NOT EXISTS comissoes_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL UNIQUE,
    empresa_id INT NOT NULL,
    status ENUM('pago', 'pendente') NOT NULL DEFAULT 'pendente',
    valor DECIMAL(10,2) DEFAULT NULL,
    data_pagamento DATE DEFAULT NULL,
    observacao TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_comissoes_pag_rotas FOREIGN KEY (rota_id) REFERENCES rotas(id) ON DELETE CASCADE,
    KEY idx_cp_empresa_status (empresa_id, status, data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
