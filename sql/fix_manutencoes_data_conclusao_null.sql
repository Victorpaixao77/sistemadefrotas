-- Permite NULL em data_conclusao (manutenções não concluídas não têm data de conclusão)
-- Erro evitado: SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'data_conclusao' cannot be null

ALTER TABLE manutencoes
MODIFY COLUMN data_conclusao DATE NULL;
