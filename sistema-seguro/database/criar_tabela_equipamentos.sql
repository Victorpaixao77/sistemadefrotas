-- ============================================
-- TABELA DE EQUIPAMENTOS DOS CLIENTES
-- Sistema Seguro - Gestão de Equipamentos
-- ============================================

CREATE TABLE IF NOT EXISTS `seguro_equipamentos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` INT(11) NOT NULL,
    `seguro_cliente_id` INT(11) NOT NULL,
    `tipo` VARCHAR(100) NOT NULL COMMENT 'Câmera, DVR/NVR, Alarme, Sensor, Central, Controle, Outros',
    `descricao` VARCHAR(255) NOT NULL,
    `marca` VARCHAR(100) DEFAULT NULL,
    `modelo` VARCHAR(100) DEFAULT NULL,
    `numero_serie` VARCHAR(100) DEFAULT NULL,
    `data_instalacao` DATE DEFAULT NULL,
    `localizacao` VARCHAR(255) DEFAULT NULL COMMENT 'Localização física do equipamento',
    `situacao` ENUM('ativo', 'inativo', 'manutencao', 'substituido') DEFAULT 'ativo',
    `observacoes` TEXT DEFAULT NULL,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `seguro_cliente_id` (`seguro_cliente_id`),
    KEY `situacao` (`situacao`),
    KEY `tipo` (`tipo`),
    CONSTRAINT `fk_equipamentos_empresa` FOREIGN KEY (`seguro_empresa_id`) 
        REFERENCES `seguro_empresas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_equipamentos_cliente` FOREIGN KEY (`seguro_cliente_id`) 
        REFERENCES `seguro_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices adicionais para performance
CREATE INDEX idx_tipo_situacao ON seguro_equipamentos(tipo, situacao);
CREATE INDEX idx_data_cadastro ON seguro_equipamentos(data_cadastro);

-- Inserir alguns exemplos (opcional - comentar se não quiser)
/*
INSERT INTO seguro_equipamentos 
(seguro_empresa_id, seguro_cliente_id, tipo, descricao, marca, modelo, numero_serie, situacao, localizacao, data_instalacao, observacoes) 
VALUES 
(1, 1, 'Câmera', 'Câmera IP HD Externa', 'Intelbras', 'VHD 1220 B', 'CAM001', 'ativo', 'Entrada Principal', '2024-01-15', 'Câmera com visão noturna'),
(1, 1, 'DVR/NVR', 'DVR 8 Canais', 'Intelbras', 'MHDX 1108', 'DVR001', 'ativo', 'Sala de Monitoramento', '2024-01-15', 'DVR principal do sistema'),
(1, 1, 'Sensor', 'Sensor de Presença', 'Honeywell', 'IS312', 'SENS001', 'ativo', 'Corredor Central', '2024-01-20', NULL);
*/

-- Verificar se a tabela foi criada
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    CREATE_TIME 
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'seguro_equipamentos';

