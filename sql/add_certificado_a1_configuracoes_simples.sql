-- Adicionar campo de certificado A1 à tabela configuracoes existente
-- Este script adiciona apenas o campo necessário para referenciar o certificado digital

-- 1. Adicionar o campo certificado_a1_id após o campo logo_empresa
ALTER TABLE configuracoes 
ADD COLUMN certificado_a1_id INT NULL 
AFTER logo_empresa;

-- 2. Verificar se a tabela fiscal_certificados_digitais existe, se não, criar
CREATE TABLE IF NOT EXISTS fiscal_certificados_digitais (
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

-- 3. Adicionar foreign key para referenciar o certificado
ALTER TABLE configuracoes 
ADD CONSTRAINT fk_configuracoes_certificado 
FOREIGN KEY (certificado_a1_id) REFERENCES fiscal_certificados_digitais(id) ON DELETE SET NULL;

-- 4. Mostrar a estrutura final da tabela
DESCRIBE configuracoes;

-- 5. Mostrar status
SELECT 'Campo certificado_a1_id adicionado com sucesso!' as status;
