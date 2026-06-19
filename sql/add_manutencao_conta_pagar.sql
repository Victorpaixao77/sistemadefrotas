-- Vincula manutenção concluída à conta a pagar gerada automaticamente.
ALTER TABLE manutencoes
    ADD COLUMN conta_pagar_id INT UNSIGNED NULL DEFAULT NULL AFTER nota_fiscal;

CREATE INDEX idx_manutencoes_conta_pagar ON manutencoes (empresa_id, conta_pagar_id);
