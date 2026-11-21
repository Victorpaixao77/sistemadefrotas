-- Assinatura do motorista no checklist de viagem (app Android)
ALTER TABLE `checklist_viagem`
    ADD COLUMN `assinatura_path` VARCHAR(255) NULL DEFAULT NULL AFTER `observacoes`;
