-- CRT do emitente na NF-e (tag emit/CRT): 1=Simples Nacional, 2=Simples excesso sublimite, 3=Regime normal (Lucro presumido/real)
-- Necessário para o sistema gerar ICMS00 + PIS/COFINS alíquota (CRT 3) ou ICMSSN (CRT 1/2).

ALTER TABLE fiscal_config_empresa
    ADD COLUMN crt TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=SN, 2=SN excesso, 3=regime normal' AFTER inscricao_estadual;
