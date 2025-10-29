-- ============================================
-- TABELA DE HISTÓRICO DE RELATÓRIOS
-- Armazena informações sobre relatórios gerados
-- ============================================

CREATE TABLE IF NOT EXISTS `seguro_historico_relatorios` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` INT(11) NOT NULL,
    `seguro_usuario_id` INT(11) NOT NULL,
    `tipo_relatorio` VARCHAR(50) NOT NULL COMMENT 'clientes, financeiro, atendimentos, equipamentos, comissoes',
    `data_geracao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `periodo_inicio` DATE NOT NULL,
    `periodo_fim` DATE NOT NULL,
    `total_registros` INT(11) DEFAULT 0,
    `formato` VARCHAR(10) DEFAULT 'csv' COMMENT 'csv, pdf, excel',
    `filtros_aplicados` TEXT DEFAULT NULL COMMENT 'JSON com filtros usados',
    `arquivo_path` VARCHAR(255) DEFAULT NULL COMMENT 'Caminho do arquivo gerado (se salvo)',
    PRIMARY KEY (`id`),
    KEY `idx_empresa` (`seguro_empresa_id`),
    KEY `idx_usuario` (`seguro_usuario_id`),
    KEY `idx_data` (`data_geracao`),
    KEY `idx_tipo` (`tipo_relatorio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EXEMPLO DE USO
-- ============================================

-- Inserir registro ao gerar relatório:
/*
INSERT INTO seguro_historico_relatorios 
(seguro_empresa_id, seguro_usuario_id, tipo_relatorio, periodo_inicio, periodo_fim, total_registros, formato)
VALUES 
(1, 1, 'clientes', '2025-01-01', '2025-10-27', 150, 'csv');
*/

-- Consultar últimos relatórios:
/*
SELECT 
    tipo_relatorio,
    DATE_FORMAT(data_geracao, '%d/%m/%Y %H:%i') as data,
    total_registros,
    formato
FROM seguro_historico_relatorios
WHERE seguro_empresa_id = 1
ORDER BY data_geracao DESC
LIMIT 10;
*/

