-- Anexos de manutenção (NF, foto) – upload opcional, link na listagem/detalhes

CREATE TABLE IF NOT EXISTS manutencao_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manutencao_id INT NOT NULL,
    empresa_id INT NOT NULL,
    nome_original VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo enviado',
    caminho VARCHAR(512) NOT NULL COMMENT 'Caminho relativo no servidor',
    tipo VARCHAR(100) NULL COMMENT 'MIME ou extensão',
    tamanho INT UNSIGNED DEFAULT 0 COMMENT 'Tamanho em bytes',
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_manutencao (manutencao_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
