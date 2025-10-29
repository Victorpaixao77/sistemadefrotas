-- ============================================
-- ADICIONAR CAMPO tem_acesso_seguro
-- ============================================
-- Este script adiciona o campo necessário na
-- tabela empresa_adm para controlar o acesso
-- ao Sistema Seguro (Plano Premium)
-- ============================================

-- Adicionar coluna se não existir
ALTER TABLE `empresa_adm` 
ADD COLUMN IF NOT EXISTS `tem_acesso_seguro` enum('sim','nao') NOT NULL DEFAULT 'nao' 
COMMENT 'Empresa tem acesso ao Sistema Seguro (Plano Premium)';

-- Verificar se foi criado com sucesso
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'empresa_adm' 
AND COLUMN_NAME = 'tem_acesso_seguro';

