-- Drop the existing foreign key constraint
ALTER TABLE motoristas
DROP FOREIGN KEY fk_motoristas_disponibilidade;

-- Rename the table if it exists
RENAME TABLE disponibilidades TO disponibilidades_motoristas;

-- Add the foreign key constraint back
ALTER TABLE motoristas
ADD CONSTRAINT fk_motoristas_disponibilidade
FOREIGN KEY (disponibilidade_id)
REFERENCES disponibilidades_motoristas(id)
ON DELETE SET NULL
ON UPDATE CASCADE; 