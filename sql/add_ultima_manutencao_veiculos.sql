-- Adiciona colunas de última manutenção na tabela veiculos
-- (opcional: permite que a API atualize o veículo ao salvar uma manutenção)

ALTER TABLE veiculos
  ADD COLUMN ultima_manutencao DATE NULL COMMENT 'Data da última manutenção' AFTER status_id,
  ADD COLUMN km_ultima_manutencao DECIMAL(10,2) NULL COMMENT 'Quilometragem na última manutenção' AFTER ultima_manutencao;
