-- Verificar e corrigir a estrutura da tabela fiscal_certificados_digitais
-- Este script verifica se a tabela existe e tem a estrutura correta

-- 1. Verificar se a tabela existe
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Tabela fiscal_certificados_digitais EXISTE'
        ELSE 'Tabela fiscal_certificados_digitais NÃO EXISTE'
    END as status_tabela
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'fiscal_certificados_digitais';

-- 2. Se a tabela não existir, criar com a estrutura correta
DROP TABLE IF EXISTS fiscal_certificados_digitais;

CREATE TABLE fiscal_certificados_digitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome_certificado VARCHAR(255) NOT NULL,
    arquivo_certificado VARCHAR(255),
    senha_certificado_criptografada VARCHAR(255),
    tipo_certificado ENUM('A1', 'A3') DEFAULT 'A1',
    data_validade DATE NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo', 'expirado') DEFAULT 'ativo',
    observacoes TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificados digitais das empresas';

-- 3. Verificar se o campo certificado_a1_id existe na tabela configuracoes
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Campo certificado_a1_id EXISTE'
        ELSE 'Campo certificado_a1_id NÃO EXISTE'
    END as status_campo
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'configuracoes' 
AND column_name = 'certificado_a1_id';

-- 4. Se o campo não existir, adicionar
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

-- 5. Verificar se a foreign key existe
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Foreign key EXISTE'
        ELSE 'Foreign key NÃO EXISTE'
    END as status_fk
FROM information_schema.key_column_usage 
WHERE table_schema = DATABASE() 
AND table_name = 'configuracoes' 
AND column_name = 'certificado_a1_id'
AND referenced_table_name = 'fiscal_certificados_digitais';

-- 6. Se a foreign key não existir, adicionar
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.key_column_usage 
    WHERE table_schema = DATABASE() 
    AND table_name = 'configuracoes' 
    AND column_name = 'certificado_a1_id'
    AND referenced_table_name = 'fiscal_certificados_digitais'
);

SET @add_fk_sql = IF(@fk_exists = 0,
    'ALTER TABLE configuracoes ADD CONSTRAINT fk_configuracoes_certificado FOREIGN KEY (certificado_a1_id) REFERENCES fiscal_certificados_digitais(id) ON DELETE SET NULL;',
    'SELECT "Foreign key já existe" as status;'
);

PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Mostrar estrutura final das tabelas
echo "=== ESTRUTURA DA TABELA configuracoes ===";
DESCRIBE configuracoes;

echo "=== ESTRUTURA DA TABELA fiscal_certificados_digitais ===";
DESCRIBE fiscal_certificados_digitais;

-- 8. Mostrar status final
SELECT 'Script de verificação e correção executado com sucesso!' as status;
