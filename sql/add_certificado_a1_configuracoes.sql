-- Adicionar campo de certificado A1 à tabela configuracoes
-- Este script adiciona um campo para referenciar o certificado digital da empresa

-- Verificar se a tabela existe
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'configuracoes'
);

-- Se a tabela não existir, criar
SET @sql = IF(@table_exists = 0,
    'CREATE TABLE configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nome_personalizado VARCHAR(255),
        logo_empresa VARCHAR(255),
        certificado_a1_id INT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
        FOREIGN KEY (certificado_a1_id) REFERENCES fiscal_certificados_digitais(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
    'SELECT "Tabela configuracoes já existe" as status;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Se a tabela já existir, adicionar o campo certificado_a1_id
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'configuracoes' 
    AND column_name = 'certificado_a1_id'
);

SET @add_column_sql = IF(@column_exists = 0,
    'ALTER TABLE configuracoes ADD COLUMN certificado_a1_id INT NULL AFTER logo_empresa;',
    'SELECT "Campo certificado_a1_id já existe" as status;'
);

PREPARE stmt FROM @add_column_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar foreign key se não existir
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.key_column_usage 
    WHERE table_schema = DATABASE() 
    AND table_name = 'configuracoes' 
    AND column_name = 'certificado_a1_id'
    AND referenced_table_name = 'fiscal_certificados_digitais'
);

SET @add_fk_sql = IF(@fk_exists = 0,
    'ALTER TABLE configuracoes ADD CONSTRAINT fk_configuracoes_certificado 
     FOREIGN KEY (certificado_a1_id) REFERENCES fiscal_certificados_digitais(id) ON DELETE SET NULL;',
    'SELECT "Foreign key já existe" as status;'
);

PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a tabela fiscal_certificados_digitais existe, se não, criar
SET @fiscal_table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'fiscal_certificados_digitais'
);

SET @create_fiscal_sql = IF(@fiscal_table_exists = 0,
    'CREATE TABLE fiscal_certificados_digitais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        nome_certificado VARCHAR(255) NOT NULL,
        arquivo_certificado VARCHAR(255),
        senha_certificado_criptografada VARCHAR(255),
        tipo_certificado ENUM("A1", "A3") DEFAULT "A1",
        data_validade DATE NOT NULL,
        data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM("ativo", "inativo", "expirado") DEFAULT "ativo",
        observacoes TEXT,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Certificados digitais das empresas";',
    'SELECT "Tabela fiscal_certificados_digitais já existe" as status;'
);

PREPARE stmt FROM @create_fiscal_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mostrar estrutura final da tabela
DESCRIBE configuracoes;
DESCRIBE fiscal_certificados_digitais;

-- Mostrar status
SELECT "Script executado com sucesso!" as status;
