-- Verificar índices existentes nas tabelas do BI

-- ROTAS
SHOW INDEXES FROM rotas WHERE Key_name LIKE 'idx_%';
SELECT '=== ROTAS ===' AS tabela;

-- ABASTECIMENTOS
SELECT '=== ABASTECIMENTOS ===' AS tabela;
SHOW INDEXES FROM abastecimentos WHERE Key_name LIKE 'idx_%';

-- MANUTENCOES
SELECT '=== MANUTENCOES ===' AS tabela;
SHOW INDEXES FROM manutencoes WHERE Key_name LIKE 'idx_%';

-- DESPESAS_FIXAS
SELECT '=== DESPESAS_FIXAS ===' AS tabela;
SHOW INDEXES FROM despesas_fixas WHERE Key_name LIKE 'idx_%';

-- DESPESAS_VIAGEM
SELECT '=== DESPESAS_VIAGEM ===' AS tabela;
SHOW INDEXES FROM despesas_viagem WHERE Key_name LIKE 'idx_%';
