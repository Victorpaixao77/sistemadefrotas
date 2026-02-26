-- Certificados para integração WSDenatran (consulta de multas no DETRAN).
-- Um certificado por vez por empresa; upload igual ao Certificado Digital A1.

CREATE TABLE IF NOT EXISTS certificados_denatran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome_certificado VARCHAR(255) NOT NULL,
    arquivo_certificado VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo .pem (certificado) em uploads/certificados_denatran/',
    arquivo_chave VARCHAR(255) DEFAULT NULL COMMENT 'Nome do arquivo .key (chave privada) em uploads/certificados_denatran/',
    senha_criptografada VARCHAR(255) DEFAULT NULL COMMENT 'Senha do certificado (criptografada), se .pfx/.p12',
    data_validade DATE DEFAULT NULL,
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar referência em configuracoes_denatran (se a tabela já existir, use ALTER)
-- Ao criar do zero, use a estrutura com certificado_denatran_id.
-- ALTER TABLE configuracoes_denatran ADD COLUMN certificado_denatran_id INT NULL AFTER cpf_usuario;
-- ALTER TABLE configuracoes_denatran DROP COLUMN cert_path, DROP COLUMN key_path, DROP COLUMN key_pass;
