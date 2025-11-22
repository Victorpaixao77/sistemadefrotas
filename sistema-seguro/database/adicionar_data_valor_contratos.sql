-- =====================================================
-- Adicionar campos 'data_inicio' e 'valor' na tabela seguro_contratos_clientes
-- =====================================================

USE sistema_frotas;

-- Adicionar campo 'data_inicio'
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS data_inicio DATE NULL 
COMMENT 'Data de início do contrato'
AFTER porcentagem_recorrencia;

-- Adicionar campo 'valor'
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS valor DECIMAL(10,2) NULL DEFAULT 0.00
COMMENT 'Valor mensal do contrato'
AFTER data_inicio;

-- Adicionar campo 'situacao'
ALTER TABLE seguro_contratos_clientes 
ADD COLUMN IF NOT EXISTS situacao ENUM(
    'aguardando_ativacao',
    'ativo',
    'aguardando_link',
    'aguardando_vistoria',
    'devolvido_para_unidade',
    'aguardando_assinatura',
    'desistencia',
    'negociar_cliente'
) NOT NULL DEFAULT 'aguardando_ativacao'
COMMENT 'Situação atual do contrato'
AFTER valor;

-- Exibir estrutura atualizada
SELECT 
    '✅ Campos adicionados com sucesso!' as status;

-- Listar todos os campos da tabela
SELECT 
    COLUMN_NAME as campo,
    COLUMN_TYPE as tipo,
    IS_NULLABLE as permite_nulo,
    COLUMN_DEFAULT as valor_padrao,
    COLUMN_COMMENT as comentario
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'seguro_contratos_clientes'
ORDER BY ORDINAL_POSITION;

