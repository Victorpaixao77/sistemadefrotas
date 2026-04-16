-- =====================================================
-- fiscal_nfe_clientes.status — alinhar ENUM ao PHP
-- Corrige: SQLSTATE 01000 / 1265 Data truncated for column 'status'
-- =====================================================
-- Bases comuns:
--   • schema antigo: pendente, autorizada, cancelada, denegada, inutilizada
--   • sua base:      pendente, autorizada, cancelada, denegada, rascunho
-- O sistema grava: recebida, consultada_sefaz, validada, em_transporte
-- Esta ALTER mantém TODOS os valores já usados + os novos.
-- Execute uma vez (phpMyAdmin → SQL ou mysql CLI).

ALTER TABLE fiscal_nfe_clientes
MODIFY COLUMN status ENUM(
    'pendente',
    'autorizada',
    'cancelada',
    'denegada',
    'rascunho',
    'inutilizada',
    'recebida',
    'consultada_sefaz',
    'validada',
    'em_transporte'
) NULL DEFAULT 'pendente';
