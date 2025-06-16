-- Adicionar campo status à tabela empresa_clientes se não existir
ALTER TABLE empresa_clientes
ADD COLUMN IF NOT EXISTS status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo';

-- Atualizar registros existentes para status ativo
UPDATE empresa_clientes SET status = 'ativo' WHERE status IS NULL; 