-- Estado por veículo+cerca: permanência mínima e debounce (geofence)
CREATE TABLE IF NOT EXISTS gps_cerca_estado (
    veiculo_id INT UNSIGNED NOT NULL,
    cerca_id INT UNSIGNED NOT NULL,
    empresa_id INT UNSIGNED NOT NULL,
    dentro TINYINT(1) NOT NULL DEFAULT 0,
    primeiro_dentro_em DATETIME NULL DEFAULT NULL,
    permanencia_alertada TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (veiculo_id, cerca_id),
    KEY idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
