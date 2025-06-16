-- Adicionar novos campos à tabela pneus
ALTER TABLE pneus
ADD COLUMN medida VARCHAR(20) AFTER posicao_id,
ADD COLUMN sulco_inicial DECIMAL(4,1) AFTER medida,
ADD COLUMN numero_recapagens INT DEFAULT 0 AFTER sulco_inicial,
ADD COLUMN data_ultima_recapagem DATE AFTER numero_recapagens,
ADD COLUMN lote VARCHAR(50) AFTER data_ultima_recapagem,
ADD COLUMN data_entrada DATE AFTER lote; 