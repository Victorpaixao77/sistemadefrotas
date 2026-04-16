-- Avaliação por viagem (nota por rota concluída)
-- Execute se a coluna ainda não existir.

ALTER TABLE rotas ADD COLUMN avaliacao_viagem DECIMAL(3,1) NULL COMMENT 'Nota 0-10 da viagem (avaliação do motorista)' AFTER comissao;
