-- Clientes / Embarcadores (CRM) — tomadores de frete da transportadora.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/clientes.sql
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_pessoa ENUM('PJ','PF') NOT NULL DEFAULT 'PJ',
    razao_social VARCHAR(160) NOT NULL,
    nome_fantasia VARCHAR(160) NULL,
    cnpj_cpf VARCHAR(20) NULL,
    inscricao_estadual VARCHAR(30) NULL,
    contato_nome VARCHAR(120) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(120) NULL,
    endereco VARCHAR(200) NULL,
    cidade VARCHAR(120) NULL,
    uf VARCHAR(2) NULL,
    status ENUM('ativo','prospect','inativo') NOT NULL DEFAULT 'ativo',
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_emp_status (empresa_id, status),
    KEY idx_doc (empresa_id, cnpj_cpf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
