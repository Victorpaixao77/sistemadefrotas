-- Vagas do pátio (mapa de vagas + veículo alocado em cada uma).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/patio_vagas.sql
CREATE TABLE IF NOT EXISTS patio_vagas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT NOT NULL,
    nome         VARCHAR(40) NOT NULL,             -- ex.: 'A1', 'Doca 3'
    setor        VARCHAR(60) NULL,                 -- ex.: 'Carregamento', 'Manutenção'
    veiculo_id   INT NULL,                         -- veículo alocado (quando ocupada)
    status       ENUM('livre','ocupada','manutencao','reservada') NOT NULL DEFAULT 'livre',
    observacoes  VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patio_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
