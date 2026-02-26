-- Configurações da integração WSDenatran (consulta de multas no DETRAN)
-- Uma linha por empresa (empresa_id). Certificado é enviado pela tela e salvo em certificados_denatran.

CREATE TABLE IF NOT EXISTS configuracoes_denatran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    habilitado TINYINT(1) NOT NULL DEFAULT 0,
    base_url VARCHAR(500) NOT NULL DEFAULT 'https://wsdenatrandes-des07116.apps.dev.serpro',
    cpf_usuario VARCHAR(20) DEFAULT NULL COMMENT 'CPF (apenas números) do usuário autorizado',
    certificado_denatran_id INT DEFAULT NULL COMMENT 'FK para certificados_denatran',
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa (empresa_id),
    KEY idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
