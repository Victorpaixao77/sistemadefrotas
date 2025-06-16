-- Remover a chave estrangeira existente
ALTER TABLE pneu_manutencao DROP FOREIGN KEY fk_pneu_manutencao_empresa;

-- Adicionar a nova chave estrangeira correta
ALTER TABLE pneu_manutencao 
ADD CONSTRAINT fk_pneu_manutencao_empresa 
FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id); 