-- Cadastro de fornecedores / parceiros comerciais por empresa (uso em NF-e, financeiro, etc.)
-- FK em empresa_clientes (padrão deste projeto; não usar empresas se essa tabela não existir).

CREATE TABLE IF NOT EXISTS fornecedores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL COMMENT 'empresa_clientes.id',

    tipo ENUM('F','J') NOT NULL COMMENT 'F=Pessoa física, J=Jurídica',
    nome VARCHAR(200) NOT NULL COMMENT 'Nome completo (PF) ou razão social (PJ)',

    cpf VARCHAR(11) DEFAULT NULL COMMENT 'Somente dígitos',
    cnpj VARCHAR(14) DEFAULT NULL COMMENT 'Somente dígitos',

    inscricao_estadual VARCHAR(20) DEFAULT NULL,
    inscricao_municipal VARCHAR(20) DEFAULT NULL,
    regime_tributario VARCHAR(50) DEFAULT NULL COMMENT 'Ex: Simples, Lucro Presumido',

    telefone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(200) DEFAULT NULL,
    site VARCHAR(200) DEFAULT NULL,

    endereco VARCHAR(200) DEFAULT NULL,
    numero VARCHAR(15) DEFAULT NULL,
    complemento VARCHAR(80) DEFAULT NULL,
    bairro VARCHAR(120) DEFAULT NULL,
    cidade VARCHAR(120) DEFAULT NULL,
    uf CHAR(2) DEFAULT NULL,
    cep VARCHAR(9) DEFAULT NULL,
    codigo_municipio_ibge VARCHAR(7) DEFAULT NULL COMMENT 'Obrigatório para NF-e (cMun 7 dígitos)',
    pais VARCHAR(60) DEFAULT 'Brasil',

    tipo_fornecedor VARCHAR(50) DEFAULT NULL COMMENT 'Ex: Peças, Combustível, Serviços',

    limite_credito DECIMAL(15,2) DEFAULT 0.00,
    prazo_pagamento INT DEFAULT 0 COMMENT 'Dias',

    taxa_multa DECIMAL(5,2) DEFAULT 0.00,
    taxa_juros DECIMAL(5,2) DEFAULT 0.00,

    situacao ENUM('A','I') NOT NULL DEFAULT 'A' COMMENT 'A=Ativo, I=Inativo',

    observacoes TEXT DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_fornecedores_empresa (empresa_id),
    KEY idx_fornecedores_situacao (empresa_id, situacao),
    KEY idx_fornecedores_doc (empresa_id, cnpj),
    KEY idx_fornecedores_cpf (empresa_id, cpf),

    CONSTRAINT fk_fornecedores_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fornecedores/parceiros por empresa';

-- Recomendação: unicidade de CNPJ/CPF por empresa (MySQL permite vários NULL em UNIQUE)
CREATE UNIQUE INDEX uk_fornecedores_empresa_cnpj ON fornecedores (empresa_id, cnpj);
CREATE UNIQUE INDEX uk_fornecedores_empresa_cpf ON fornecedores (empresa_id, cpf);
