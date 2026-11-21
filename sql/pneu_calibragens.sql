-- =============================================================================
-- pneu_calibragens — registro de calibragem (pressão) dos pneus
-- Única tabela nova da Gestão Interativa Pro. As demais (pneus, pneu_movimentacoes,
-- pneu_medicoes_sulco, pneu_manutencao, eixos_veiculos, alocacao_pneus_flex) já existem.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/pneu_calibragens.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS pneu_calibragens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,
    veiculo_id INT NULL,
    pressao_recomendada DECIMAL(6,2) NULL COMMENT 'PSI recomendado',
    pressao_aferida DECIMAL(6,2) NOT NULL COMMENT 'PSI aferido',
    diferenca DECIMAL(6,2) NULL COMMENT 'aferida - recomendada (gerado na API)',
    km_veiculo INT NULL,
    responsavel VARCHAR(120) NULL,
    observacoes VARCHAR(500) NULL,
    usuario_id INT NULL,
    data_calibragem DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pneu_data (pneu_id, data_calibragem),
    INDEX idx_empresa (empresa_id),
    INDEX idx_veiculo (veiculo_id),
    CONSTRAINT fk_calibragem_pneu FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de calibragem (pressão) dos pneus';
