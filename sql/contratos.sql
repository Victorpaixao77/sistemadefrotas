-- Contratos (clientes, fornecedores, seguros, locações, financiamentos, leasing...).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/contratos.sql
CREATE TABLE IF NOT EXISTS contratos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT NOT NULL,
    titulo        VARCHAR(160) NOT NULL,
    tipo          ENUM('cliente','fornecedor','seguro','locacao','financiamento','leasing','prestador','outro') NOT NULL DEFAULT 'outro',
    parte         VARCHAR(160) NULL,             -- contraparte (nome da empresa/pessoa)
    valor         DECIMAL(14,2) NULL,
    data_inicio   DATE NULL,
    data_fim      DATE NULL,                     -- vencimento
    status        ENUM('ativo','encerrado','cancelado') NOT NULL DEFAULT 'ativo',
    observacoes   VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contratos_empresa (empresa_id),
    INDEX idx_contratos_venc (empresa_id, data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
