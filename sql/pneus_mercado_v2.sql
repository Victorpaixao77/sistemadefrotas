-- Módulo de pneus nível mercado v2
-- Execute: Get-Content sql/pneus_mercado_v2.sql -Raw | C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root sistema_frotas

-- Filiais / unidades da empresa
CREATE TABLE IF NOT EXISTS filiais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    codigo VARCHAR(30) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_filial_empresa (empresa_id),
    UNIQUE KEY uk_filial_empresa_codigo (empresa_id, codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Motivos de remoção / sucata
CREATE TABLE IF NOT EXISTS pneu_motivos_remocao (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL COMMENT 'NULL = catálogo global da instalação',
    codigo VARCHAR(40) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_motivo_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pneu_motivos_remocao (id, empresa_id, codigo, nome) VALUES
(1, NULL, 'desgaste_normal', 'Desgaste normal'),
(2, NULL, 'corte_lateral', 'Corte lateral'),
(3, NULL, 'furo', 'Furo'),
(4, NULL, 'bolha', 'Bolha'),
(5, NULL, 'desalinhamento', 'Desalinhamento'),
(6, NULL, 'fim_vida', 'Fim da vida útil'),
(7, NULL, 'rodizio', 'Rodízio'),
(8, NULL, 'recapagem', 'Enviado para recapagem'),
(9, NULL, 'outro', 'Outro');

-- Garantias detalhadas (fabricante, revenda, recapadora)
CREATE TABLE IF NOT EXISTS pneu_garantias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,
    tipo ENUM('fabricante','revenda','recapadora') NOT NULL,
    fornecedor_nome VARCHAR(150) NULL,
    fornecedor_id INT NULL,
    validade_ate DATE NULL,
    observacoes VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_garantia_pneu (pneu_id),
    INDEX idx_garantia_validade (validade_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos e laudos
CREATE TABLE IF NOT EXISTS pneu_anexos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,
    movimentacao_id BIGINT UNSIGNED NULL,
    tipo ENUM('compra','instalacao','remocao','manutencao','sucata','inspecao','outro') NOT NULL DEFAULT 'outro',
    arquivo VARCHAR(255) NOT NULL,
    legenda VARCHAR(255) NULL,
    usuario_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_anexo_pneu (pneu_id),
    INDEX idx_anexo_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de sucata
CREATE TABLE IF NOT EXISTS pneu_sucata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,
    motivo_remocao_id INT UNSIGNED NULL,
    motivo_texto VARCHAR(255) NULL,
    valor_recuperado DECIMAL(12,2) NULL,
    destino VARCHAR(150) NULL,
    observacoes TEXT NULL,
    usuario_id INT NULL,
    data_sucata DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sucata_pneu (pneu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inspeção / checklist do motorista
CREATE TABLE IF NOT EXISTS pneu_inspecoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NULL,
    veiculo_id INT NULL,
    motorista_id INT NULL,
    usuario_id INT NULL,
    pressao_psi DECIMAL(5,1) NULL,
    sulco_mm DECIMAL(5,2) NULL,
    tem_corte TINYINT(1) DEFAULT 0,
    tem_bolha TINYINT(1) DEFAULT 0,
    observacoes TEXT NULL,
    origem ENUM('motorista','gestor','inventario') NOT NULL DEFAULT 'gestor',
    data_inspecao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inspecao_pneu (pneu_id),
    INDEX idx_inspecao_veiculo (veiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventário por QR
CREATE TABLE IF NOT EXISTS pneu_inventario_sessoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT UNSIGNED NULL,
    usuario_id INT NULL,
    titulo VARCHAR(120) NULL,
    status ENUM('aberta','fechada') NOT NULL DEFAULT 'aberta',
    iniciada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechada_em DATETIME NULL,
    INDEX idx_inv_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pneu_inventario_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sessao_id BIGINT UNSIGNED NOT NULL,
    pneu_id INT NOT NULL,
    qr_token VARCHAR(64) NULL,
    encontrado TINYINT(1) NOT NULL DEFAULT 1,
    observacoes VARCHAR(255) NULL,
    lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_inv_sessao_pneu (sessao_id, pneu_id),
    INDEX idx_inv_sessao (sessao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria detalhada (complementa pneu_movimentacoes)
CREATE TABLE IF NOT EXISTS pneu_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,
    acao VARCHAR(60) NOT NULL,
    usuario_id INT NULL,
    ip_origem VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    dados_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_pneu (pneu_id),
    INDEX idx_audit_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colunas extras em pneus
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'filial_id');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN filial_id INT UNSIGNED NULL AFTER empresa_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'carcaca_codigo');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN carcaca_codigo VARCHAR(60) NULL COMMENT ''Código da carcaça (pode = numero_serie)'' AFTER numero_serie', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'tipo_rodado_ideal');
SET @sql := IF(@c = 0, "ALTER TABLE pneus ADD COLUMN tipo_rodado_ideal ENUM('direcional','tracao','livre','implemento','qualquer') NULL DEFAULT 'qualquer' AFTER medida", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Tipo de rodado na posição (direcional, tração, etc.)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posicoes_pneus' AND COLUMN_NAME = 'tipo_rodado');
SET @sql := IF(@c = 0, "ALTER TABLE posicoes_pneus ADD COLUMN tipo_rodado ENUM('direcional','tracao','livre','implemento','qualquer') NOT NULL DEFAULT 'qualquer' AFTER nome", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Auditoria em movimentações
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_movimentacoes' AND COLUMN_NAME = 'usuario_id');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_movimentacoes ADD COLUMN usuario_id INT NULL AFTER observacoes', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_movimentacoes' AND COLUMN_NAME = 'ip_origem');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_movimentacoes ADD COLUMN ip_origem VARCHAR(45) NULL AFTER usuario_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_movimentacoes' AND COLUMN_NAME = 'motivo_remocao_id');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_movimentacoes ADD COLUMN motivo_remocao_id INT UNSIGNED NULL AFTER ip_origem', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_movimentacoes' AND COLUMN_NAME = 'filial_id');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_movimentacoes ADD COLUMN filial_id INT UNSIGNED NULL AFTER motivo_remocao_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
