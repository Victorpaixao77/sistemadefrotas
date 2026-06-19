-- Campo "outros" em despesas de viagem e total gerado incluindo essa rubrica.
-- Execute no banco da aplicação (ex.: phpMyAdmin ou mysql CLI).

ALTER TABLE despesas_viagem
  ADD COLUMN outros DECIMAL(10,2) NULL DEFAULT NULL AFTER adiantamento;

-- Recria a coluna gerada somando todas as rubricas (ajuste se sua expressão atual for diferente).
ALTER TABLE despesas_viagem
  MODIFY COLUMN total_despviagem DECIMAL(10,2) AS (
    COALESCE(descarga, 0) + COALESCE(pedagios, 0) + COALESCE(caixinha, 0) +
    COALESCE(estacionamento, 0) + COALESCE(lavagem, 0) + COALESCE(borracharia, 0) +
    COALESCE(eletrica_mecanica, 0) + COALESCE(adiantamento, 0) + COALESCE(outros, 0)
  ) STORED;
