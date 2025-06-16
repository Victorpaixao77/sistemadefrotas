-- Criar tabela de status
CREATE TABLE IF NOT EXISTS `status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nome` varchar(50) NOT NULL,
    `descricao` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir status básicos
INSERT INTO `status` (`nome`, `descricao`) VALUES
('Novo', 'Pneu novo, nunca utilizado'),
('Usado', 'Pneu usado, em condições de uso'),
('Recapado', 'Pneu que passou por processo de recapagem'),
('Inservível', 'Pneu que não pode mais ser utilizado'); 