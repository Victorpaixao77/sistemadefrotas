-- Inclui alerta de permanência mínima dentro da cerca
ALTER TABLE gps_cerca_alertas
    MODIFY COLUMN tipo ENUM('entrou','saiu','permanencia') NOT NULL;
