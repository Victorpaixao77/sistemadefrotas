-- Criar tabela estoque_pneus
CREATE TABLE IF NOT EXISTS estoque_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    pneu_id INT(11) NOT NULL,
    status_id INT(11) NULL,
    disponivel TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (pneu_id) REFERENCES pneus(id),
    FOREIGN KEY (status_id) REFERENCES status(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger para adicionar pneus ao estoque automaticamente
DELIMITER //
CREATE TRIGGER after_pneu_insert
AFTER INSERT ON pneus
FOR EACH ROW
BEGIN
    INSERT INTO estoque_pneus (pneu_id, status_id, disponivel)
    VALUES (NEW.id, NEW.status_id, 1);
END//
DELIMITER ; 