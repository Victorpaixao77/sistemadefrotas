-- GPS: qualidade (accuracy, provider, mock), status ocioso, parado_desde, alertas, geocode cache.
-- Execute após create_gps_tracking.sql e alter_gps_bateria_pct.sql (se já rodou).

-- Histórico: qualidade + status por ponto
ALTER TABLE gps_logs
    ADD COLUMN accuracy_metros FLOAT NULL DEFAULT NULL COMMENT 'Raio de incerteza (m), menor = melhor' AFTER bateria_pct,
    ADD COLUMN provider VARCHAR(32) NULL DEFAULT NULL COMMENT 'fused, gps, network' AFTER accuracy_metros,
    ADD COLUMN location_mock TINYINT(1) NULL DEFAULT NULL COMMENT '1 = app reportou posição fictícia' AFTER provider,
    ADD COLUMN status VARCHAR(16) NULL DEFAULT NULL COMMENT 'parado, movimento, ocioso' AFTER location_mock;

ALTER TABLE gps_logs ADD KEY idx_motorista_data (motorista_id, data_hora);

-- Última posição
ALTER TABLE gps_ultima_posicao
    MODIFY COLUMN status ENUM('parado','movimento','ocioso') NULL DEFAULT NULL;

ALTER TABLE gps_ultima_posicao
    ADD COLUMN parado_desde DATETIME NULL DEFAULT NULL COMMENT 'Início parada no mesmo trecho (ocioso)' AFTER status;

ALTER TABLE gps_ultima_posicao
    ADD COLUMN accuracy_metros FLOAT NULL DEFAULT NULL AFTER bateria_pct,
    ADD COLUMN provider VARCHAR(32) NULL DEFAULT NULL AFTER accuracy_metros,
    ADD COLUMN location_mock TINYINT(1) NULL DEFAULT NULL AFTER provider;

-- Cache reverse geocoding (Nominatim / similar)
CREATE TABLE IF NOT EXISTS gps_geocode_cache (
    lat_key DECIMAL(8,4) NOT NULL,
    lng_key DECIMAL(8,4) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lat_key, lng_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alertas operacionais (bateria baixa, velocidade, mock)
CREATE TABLE IF NOT EXISTS gps_alertas_operacionais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    motorista_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(32) NOT NULL,
    mensagem VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    data_hora DATETIME NOT NULL,
    extra_json LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_empresa_data (empresa_id, data_hora),
    KEY idx_veiculo_tipo_data (veiculo_id, tipo, data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
