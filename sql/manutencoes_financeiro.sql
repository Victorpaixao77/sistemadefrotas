-- Campos financeiros detalhados da manutenção (para o Resumo Financeiro da OS).
ALTER TABLE manutencoes
  ADD COLUMN IF NOT EXISTS valor_pecas    DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER valor,
  ADD COLUMN IF NOT EXISTS valor_mao_obra DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER valor_pecas,
  ADD COLUMN IF NOT EXISTS valor_outros   DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER valor_mao_obra;
