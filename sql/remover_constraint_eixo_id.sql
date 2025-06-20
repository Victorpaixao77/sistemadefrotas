-- Script para remover a constraint de eixo_id da tabela instalacoes_pneus
-- Execute este script no phpMyAdmin ou MySQL CLI

-- 1. Remover a constraint de chave estrangeira
ALTER TABLE instalacoes_pneus DROP FOREIGN KEY instalacoes_pneus_ibfk_3;

-- 2. Remover o índice da coluna eixo_id (se existir)
ALTER TABLE instalacoes_pneus DROP INDEX idx_eixo_id;

-- 3. Alterar a coluna eixo_id para permitir NULL
ALTER TABLE instalacoes_pneus MODIFY COLUMN eixo_id INT(11) NULL;

-- 4. Definir eixo_id como NULL para registros existentes (opcional)
UPDATE instalacoes_pneus SET eixo_id = NULL WHERE eixo_id IS NOT NULL;

-- 5. Verificar se a alteração foi aplicada
DESCRIBE instalacoes_pneus; 