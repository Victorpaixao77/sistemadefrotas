-- Rastreamento GPS (app motorista → painel web)
-- Execute uma vez no banco da empresa.

CREATE TABLE IF NOT EXISTS gps_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    motorista_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    velocidade DECIMAL(6,2) NULL COMMENT 'km/h',
    bateria_pct TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '0-100 bateria celular motorista',
    accuracy_metros FLOAT NULL DEFAULT NULL COMMENT 'incerteza em metros',
    provider VARCHAR(32) NULL DEFAULT NULL,
    location_mock TINYINT(1) NULL DEFAULT NULL COMMENT '1 = mock',
    status VARCHAR(16) NULL DEFAULT NULL COMMENT 'parado, movimento, ocioso',
    data_hora DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_empresa_veiculo_data (empresa_id, veiculo_id, data_hora),
    KEY idx_empresa_data (empresa_id, data_hora),
    KEY idx_motorista_data (motorista_id, data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Última posição conhecida por veículo
CREATE TABLE IF NOT EXISTS gps_ultima_posicao (
    veiculo_id INT UNSIGNED NOT NULL,
    empresa_id INT UNSIGNED NOT NULL,
    motorista_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    velocidade DECIMAL(6,2) NULL,
    status ENUM('parado','movimento','ocioso') NULL DEFAULT NULL,
    parado_desde DATETIME NULL DEFAULT NULL,
    ignicao TINYINT(1) NULL DEFAULT NULL,
    ultima_atualizacao DATETIME NULL DEFAULT NULL,
    endereco VARCHAR(255) NULL DEFAULT NULL,
    bateria_pct TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '0-100 bateria celular motorista',
    accuracy_metros FLOAT NULL DEFAULT NULL,
    provider VARCHAR(32) NULL DEFAULT NULL,
    location_mock TINYINT(1) NULL DEFAULT NULL,
    data_hora DATETIME NOT NULL,
    PRIMARY KEY (veiculo_id),
    KEY idx_empresa_data (empresa_id, data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
