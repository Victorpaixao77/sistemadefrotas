-- Dados adicionais no cadastro de motoristas
-- Execute no banco (apenas uma vez). Se coluna já existir, ignore o erro.

ALTER TABLE motoristas
    ADD COLUMN data_nascimento DATE NULL COMMENT 'Data de nascimento',
    ADD COLUMN rg VARCHAR(20) NULL COMMENT 'RG',
    ADD COLUMN pis_pasep VARCHAR(20) NULL COMMENT 'PIS/PASEP',
    ADD COLUMN banco VARCHAR(100) NULL COMMENT 'Banco para pagamento',
    ADD COLUMN agencia VARCHAR(20) NULL COMMENT 'Agência',
    ADD COLUMN conta VARCHAR(30) NULL COMMENT 'Conta (conta corrente)',
    ADD COLUMN contato_emergencia_nome VARCHAR(150) NULL COMMENT 'Nome do contato de emergência',
    ADD COLUMN restricoes_medicas TEXT NULL COMMENT 'Restrições médicas, alergias, etc.',
    ADD COLUMN tamanho_uniforme VARCHAR(20) NULL COMMENT 'Tamanho de uniforme (EPI)',
    ADD COLUMN observacoes_rh TEXT NULL COMMENT 'Observações do RH (interno)';
