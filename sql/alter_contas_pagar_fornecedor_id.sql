-- Vincula contas a pagar ao cadastro de fornecedores (sem saldo denormalizado — só FK).
-- Execute após existir a tabela `fornecedores`.

-- Se a coluna já existir, ignore o erro ou comente a linha abaixo.
ALTER TABLE contas_pagar
    ADD COLUMN fornecedor_id BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'fornecedores.id — opcional; legado usa campo texto fornecedor'
        AFTER empresa_id;

CREATE INDEX idx_contas_pagar_fornecedor ON contas_pagar (empresa_id, fornecedor_id);

-- FK opcional (descomente se as tabelas existirem com InnoDB)
-- ALTER TABLE contas_pagar
--   ADD CONSTRAINT fk_contas_pagar_fornecedor
--   FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL;
