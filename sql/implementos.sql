-- Implementos (ativos rebocados: carretas, reboques, dollys, pranchas…), separados do cavalo (veiculos).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/implementos.sql
CREATE TABLE IF NOT EXISTS implementos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    placa VARCHAR(10) NOT NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'Carreta',
    tipo_carreta_id INT NULL,
    marca VARCHAR(60) NULL,
    ano SMALLINT NULL,
    renavam VARCHAR(20) NULL,
    chassi VARCHAR(30) NULL,
    rntrc VARCHAR(20) NULL,
    capacidade_kg DECIMAL(10,2) NULL,
    eixos TINYINT UNSIGNED NULL,
    veiculo_id INT NULL,
    status ENUM('ativo','manutencao','inativo') NOT NULL DEFAULT 'ativo',
    vencimento_licenciamento DATE NULL,
    vencimento_seguro DATE NULL,
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_placa (empresa_id, placa),
    KEY idx_emp_status (empresa_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
