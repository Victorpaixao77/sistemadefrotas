-- Status de pneus para Gestão Interativa:
-- "em uso" = quando o pneu está alocado no veículo
-- "usado"   = quando o pneu foi removido do veículo (fica disponível para realocar)
-- Executar uma vez se esses status não existirem na tabela status_pneus.

INSERT INTO status_pneus (nome)
SELECT 'em uso' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM status_pneus WHERE LOWER(TRIM(nome)) = 'em uso' LIMIT 1);

INSERT INTO status_pneus (nome)
SELECT 'usado' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM status_pneus WHERE LOWER(TRIM(nome)) = 'usado' LIMIT 1);
