-- Cercas eletrônicas (geofence) + alertas de entrada/saída
-- Execute após create_gps_tracking.sql

CREATE TABLE IF NOT EXISTS gps_cercas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    raio_metros INT UNSIGNED NOT NULL DEFAULT 500,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_empresa_ativo (empresa_id, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gps_cerca_alertas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL,
    cerca_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    motorista_id INT UNSIGNED NOT NULL,
    tipo ENUM('entrou','saiu','permanencia') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    data_hora DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_empresa_data (empresa_id, data_hora),
    KEY idx_veiculo_data (veiculo_id, data_hora),
    KEY idx_cerca (cerca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
