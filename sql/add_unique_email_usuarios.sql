-- Adicionar constraint UNIQUE no campo email da tabela usuarios
-- Este script previne a criação de emails duplicados no banco de dados

-- Verificar se a constraint já existe antes de adicionar
-- Se já existir, o comando será ignorado

-- Para MySQL/MariaDB
ALTER TABLE usuarios 
ADD UNIQUE INDEX idx_email_unique (email);

-- Se o comando acima der erro porque já existe, use este para verificar:
-- SHOW INDEX FROM usuarios WHERE Key_name = 'idx_email_unique';
