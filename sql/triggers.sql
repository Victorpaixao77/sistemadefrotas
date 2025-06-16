DELIMITER //

-- Trigger para inserir automaticamente na tabela estoque_pneus quando um novo pneu é cadastrado
CREATE TRIGGER after_pneu_insert
AFTER INSERT ON pneus
FOR EACH ROW
BEGIN
    INSERT INTO estoque_pneus (pneu_id, status_id, disponivel, created_at, updated_at)
    VALUES (NEW.id, NEW.status_id, 1, NOW(), NOW());
END//

DELIMITER ; 