-- Verifica se a tabela tipos_contrato existe
CREATE TABLE IF NOT EXISTS tipos_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insere valores padrão se a tabela estiver vazia
INSERT IGNORE INTO tipos_contrato (id, nome, descricao) VALUES
(1, 'CLT', 'Contrato CLT padrão'),
(2, 'PJ', 'Pessoa Jurídica'),
(3, 'Autônomo', 'Prestador de serviços autônomo'),
(4, 'Temporário', 'Contrato por tempo determinado'),
(5, 'Terceirizado', 'Funcionário terceirizado');

-- Atualiza a foreign key na tabela motoristas se necessário
ALTER TABLE motoristas
DROP FOREIGN KEY IF EXISTS fk_motoristas_tipo_contrato;

ALTER TABLE motoristas
ADD CONSTRAINT fk_motoristas_tipo_contrato
FOREIGN KEY (tipo_contrato_id)
REFERENCES tipos_contrato(id)
ON DELETE SET NULL
ON UPDATE CASCADE; 