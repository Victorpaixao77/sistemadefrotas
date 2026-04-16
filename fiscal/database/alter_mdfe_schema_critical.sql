-- Ajustes críticos de schema para fluxo MDF-e atual
-- 1) status precisa aceitar rascunho e em_envio
-- 2) data_autorizacao precisa existir para persistir retorno SEFAZ

ALTER TABLE fiscal_mdfe
    MODIFY COLUMN status ENUM('rascunho', 'pendente', 'em_envio', 'emitido', 'em_viagem', 'autorizado', 'cancelado', 'encerrado', 'denegado')
    NOT NULL DEFAULT 'rascunho';

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS data_autorizacao DATETIME NULL AFTER protocolo_autorizacao;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS data_encerramento DATETIME NULL AFTER status;

