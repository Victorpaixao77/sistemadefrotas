-- Corrigir estrutura das tabelas para certificado A1
-- Execute este script para resolver o erro "Column not found"

-- 1. Remover tabela se existir e recriar com estrutura correta
DROP TABLE IF EXISTS fiscal_certificados_digitais;

-- 2. Criar tabela fiscal_certificados_digitais com estrutura correta
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

-- 3. Adicionar campo certificado_a1_id na tabela configuracoes (se não existir)
ALTER TABLE configuracoes 
ADD COLUMN IF NOT EXISTS certificado_a1_id INT NULL 
AFTER logo_empresa;

-- 4. Adicionar foreign key (se não existir)
-- Primeiro remover se existir para evitar erro
ALTER TABLE configuracoes 
DROP FOREIGN KEY IF EXISTS fk_configuracoes_certificado;

-- Depois adicionar novamente
ALTER TABLE configuracoes 
ADD CONSTRAINT fk_configuracoes_certificado 
FOREIGN KEY (certificado_a1_id) REFERENCES fiscal_certificados_digitais(id) ON DELETE SET NULL;

-- 5. Mostrar estrutura das tabelas
DESCRIBE configuracoes;
DESCRIBE fiscal_certificados_digitais;

-- 6. Status de conclusão
SELECT 'Tabelas corrigidas com sucesso! Certificado A1 funcionando.' as status;
