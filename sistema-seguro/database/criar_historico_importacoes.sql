-- ============================================
-- TABELA DE HISTÓRICO DE IMPORTAÇÕES
-- Registra todas as importações de CSV realizadas
-- ============================================

CREATE TABLE IF NOT EXISTS `seguro_historico_importacoes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `empresa_id` INT(11) NOT NULL,
    `usuario_id` INT(11) NOT NULL,
    `nome_arquivo` VARCHAR(255) NOT NULL,
    `data_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `total_registros` INT(11) DEFAULT 0,
    `processados` INT(11) DEFAULT 0,
    `total_erros` INT(11) DEFAULT 0,
    `detalhes` TEXT DEFAULT NULL COMMENT 'JSON com detalhes dos erros',
    PRIMARY KEY (`id`),
    KEY `idx_empresa` (`empresa_id`),
    KEY `idx_usuario` (`usuario_id`),
    KEY `idx_data` (`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EXEMPLO DE INSERÇÃO
-- ============================================

/*
INSERT INTO seguro_historico_importacoes 
(empresa_id, usuario_id, nome_arquivo, total_registros, processados, total_erros, detalhes)
VALUES 
(1, 1, 'retorno_2025_10.csv', 100, 98, 2, 
'{"erros": [{"linha": 15, "erro": "CPF inválido"}, {"linha": 42, "erro": "Data inválida"}]}');
*/

-- ============================================
-- CONSULTAS ÚTEIS
-- ============================================

-- Ver últimas 10 importações
/*
SELECT 
    nome_arquivo,
    DATE_FORMAT(data_hora, '%d/%m/%Y %H:%i') as data,
    total_registros,
    processados,
    total_erros
FROM seguro_historico_importacoes
WHERE empresa_id = 1
ORDER BY data_hora DESC
LIMIT 10;
*/

-- Total de importações por mês
/*
SELECT 
    DATE_FORMAT(data_hora, '%Y-%m') as mes,
    COUNT(*) as total_importacoes,
    SUM(total_registros) as total_documentos,
    SUM(total_erros) as total_erros
FROM seguro_historico_importacoes
WHERE empresa_id = 1
GROUP BY DATE_FORMAT(data_hora, '%Y-%m')
ORDER BY mes DESC;
*/

