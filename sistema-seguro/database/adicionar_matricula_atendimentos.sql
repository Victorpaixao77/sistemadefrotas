-- ============================================
-- Adicionar campo de matrícula/conjunto aos atendimentos
-- Permite vincular atendimentos a um CONJUNTO específico
-- ============================================

-- Adicionar coluna matricula_conjunto
ALTER TABLE seguro_atendimentos 
ADD COLUMN IF NOT EXISTS matricula_conjunto VARCHAR(100) NULL 
COMMENT 'Matrícula/CONJUNTO vinculado ao atendimento'
AFTER seguro_cliente_id;

-- Criar índice para buscar atendimentos por matrícula
ALTER TABLE seguro_atendimentos 
ADD INDEX IF NOT EXISTS idx_matricula_conjunto (matricula_conjunto);

-- Comentário para documentação
ALTER TABLE seguro_atendimentos 
COMMENT = 'Atendimentos do Sistema Seguro - Agora com vínculo a CONJUNTO/Matrícula';

