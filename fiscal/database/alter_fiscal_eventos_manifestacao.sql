-- Inclui 'manifestacao' no ENUM de tipo_evento (necessário para manifestação do destinatário NF-e).
-- Execute no MySQL/MariaDB se o INSERT de eventos com tipo manifestacao falhar.

ALTER TABLE fiscal_eventos_fiscais
MODIFY COLUMN tipo_evento ENUM(
  'cancelamento',
  'encerramento',
  'cce',
  'inutilizacao',
  'carta_correcao',
  'manifestacao'
) NOT NULL;
