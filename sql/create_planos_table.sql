-- =====================================================
-- 📋 TABELA DE PLANOS DE COBRANÇA
-- Sistema de Gestão de Frotas - Planos por Quantidade de Veículos
-- =====================================================

CREATE TABLE IF NOT EXISTS adm_planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL COMMENT 'Nome do plano (ex: Básico, Profissional, etc)',
    descricao TEXT NULL COMMENT 'Descrição do plano',
    tipo ENUM('avulso', 'pacote') NOT NULL DEFAULT 'pacote' COMMENT 'Tipo: avulso ou pacote',
    limite_veiculos INT NULL COMMENT 'Limite máximo de veículos (NULL = ilimitado)',
    valor_por_veiculo DECIMAL(10,2) NOT NULL COMMENT 'Valor cobrado por veículo',
    valor_maximo DECIMAL(10,2) NULL COMMENT 'Valor máximo do plano (NULL = sem limite)',
    ordem INT DEFAULT 0 COMMENT 'Ordem de exibição',
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_status (status),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Planos de cobrança por quantidade de veículos';

-- Inserir planos padrão
INSERT INTO adm_planos (nome, descricao, tipo, limite_veiculos, valor_por_veiculo, valor_maximo, ordem) VALUES
('Avulso - 1 veículo', 'Plano avulso para 1 veículo', 'avulso', 1, 120.00, 120.00, 1),
('Avulso - 2 veículos', 'Plano avulso para 2 veículos', 'avulso', 2, 110.00, 220.00, 2),
('Avulso - 3 veículos', 'Plano avulso para 3 veículos', 'avulso', 3, 100.00, 300.00, 3),
('Avulso - 4 veículos', 'Plano avulso para 4 veículos', 'avulso', 4, 95.00, 380.00, 4),
('Básico', 'Plano básico até 5 veículos', 'pacote', 5, 85.00, 425.00, 5),
('Profissional', 'Plano profissional até 8 veículos', 'pacote', 8, 75.00, 600.00, 6),
('Empresarial', 'Plano empresarial até 10 veículos', 'pacote', 10, 70.00, 700.00, 7),
('Frota Avançada', 'Plano para frotas médias até 15 veículos', 'pacote', 15, 65.00, 975.00, 8),
('Frota Corporativa', 'Plano para grandes frotas (20+ veículos)', 'pacote', NULL, 60.00, NULL, 9);

-- Adicionar coluna plano_id na tabela empresa_adm (se não existir)
ALTER TABLE empresa_adm 
ADD COLUMN plano_id INT NULL COMMENT 'ID do plano de cobrança' AFTER plano,
ADD INDEX idx_plano_id (plano_id);

-- =====================================================

