-- Planos de Manutenção Preventiva (regra + frequência + previsão)
-- Permite alertas automáticos e sugestão de manutenção pré-agendada

CREATE TABLE IF NOT EXISTS planos_manutencao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    componente_id INT NOT NULL,
    tipo_manutencao_id INT NOT NULL,
    intervalo_km INT NULL COMMENT 'Intervalo em km (ex: 10000). NULL = só por dias',
    intervalo_dias INT NULL COMMENT 'Intervalo em dias (ex: 180). NULL = só por km',
    ultimo_km INT NULL COMMENT 'Último km na última manutenção deste plano',
    ultima_data DATE NULL COMMENT 'Data da última manutenção deste plano',
    ativo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_plano (empresa_id, veiculo_id, componente_id, tipo_manutencao_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_veiculo (veiculo_id),
    INDEX idx_ultima_data (ultima_data),
    FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (componente_id) REFERENCES componentes_manutencao(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_manutencao_id) REFERENCES tipos_manutencao(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
