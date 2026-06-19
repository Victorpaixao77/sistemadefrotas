-- Itens específicos de carreta/conjunto no checklist de viagem (Checklist Inteligente).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/checklist_itens_carreta.sql

ALTER TABLE `checklist_viagem`
    ADD COLUMN IF NOT EXISTS `quinta_roda` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `mangueiras` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `travas` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `lona_carga` TINYINT(1) NOT NULL DEFAULT 0;
