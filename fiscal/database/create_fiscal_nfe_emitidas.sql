-- NF-e emitida pela própria empresa (não confundir com fiscal_nfe_clientes = notas recebidas).
-- Execute uma vez no banco antes de usar a emissão.
--
-- Se você tentou criar antes e surgiu uma tabela estranha chamada `if`, remova:
--   DROP TABLE IF EXISTS `if`;
--
-- Este projeto usa empresa_clientes como cadastro de empresas (não `empresas`).
-- Sem FK obrigatório para evitar erro quando o nome da tabela de empresas for outro no seu ambiente.

CREATE TABLE IF NOT EXISTS `fiscal_nfe_emitidas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL,
    `serie` INT NOT NULL DEFAULT 1,
    `numero_nfe` INT NOT NULL,
    `chave_acesso` VARCHAR(44) NOT NULL,
    `protocolo_autorizacao` VARCHAR(50) DEFAULT NULL,
    `valor_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('rascunho','pendente','autorizada','rejeitada','denegada') NOT NULL DEFAULT 'pendente',
    `destinatario_cnpj` VARCHAR(14) DEFAULT NULL,
    `destinatario_cpf` VARCHAR(11) DEFAULT NULL,
    `destinatario_nome` VARCHAR(255) DEFAULT NULL,
    `xml_nfe` LONGTEXT,
    `xml_retorno_sefaz` LONGTEXT,
    `motivo_rejeicao` TEXT,
    `data_emissao` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_nfe_emitida_chave` (`chave_acesso`),
    KEY `idx_nfe_emitida_empresa` (`empresa_id`),
    KEY `idx_nfe_emitida_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='NF-e modelo 55 emitida pelo emitente';

-- Opcional: integridade referencial com o cadastro de empresas do sistema (descomente se a tabela existir):
-- ALTER TABLE `fiscal_nfe_emitidas`
--   ADD CONSTRAINT `fk_nfe_emitida_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresa_clientes` (`id`) ON DELETE CASCADE;
