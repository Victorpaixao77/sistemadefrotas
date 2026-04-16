-- =============================================================================
-- pneu_movimentacoes – Histórico cronológico único do pneu
-- Núcleo do módulo de pneus: toda mudança de estado gera UMA movimentação.
-- Status atual do pneu = último registro do histórico (não campos soltos).
-- =============================================================================

CREATE TABLE IF NOT EXISTS pneu_movimentacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,

    tipo ENUM(
        'entrada_estoque',
        'instalacao',
        'remocao',
        'deslocamento',
        'recapagem',
        'manutencao',
        'descarte'
    ) NOT NULL COMMENT 'Mudança de estado do pneu',

    veiculo_id INT NULL,
    eixo_id INT NULL COMMENT 'eixos.id ou eixos_veiculos.id conforme modelo',
    posicao_id INT NULL,

    km_odometro INT NULL COMMENT 'KM do veículo no momento da movimentação',
    km_rodado INT NULL COMMENT 'KM rodado neste ciclo (ex: preenchido na remocao = km_remocao - km_instalacao)',

    sulco_mm DECIMAL(5,2) NULL COMMENT 'Sulco em mm (ex: após recapagem ou medição)',

    custo DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Custo desta movimentação (compra, recapagem, manutenção)',

    fornecedor_id INT NULL COMMENT 'Opcional: fornecedor da recapagem/compra',

    observacoes TEXT NULL,

    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_empresa (empresa_id),
    INDEX idx_pneu (pneu_id),
    INDEX idx_tipo (tipo),
    INDEX idx_data (data_movimentacao),
    INDEX idx_veiculo (veiculo_id),
    INDEX idx_pneu_data (pneu_id, data_movimentacao),

    CONSTRAINT fk_mov_pneu FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_empresa FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico único de movimentações do pneu. Fonte da verdade para custo/km e vida útil.';

-- Se sua tabela de empresas for "empresas" em vez de "empresa_clientes", desfaça a FK acima e use:
-- ALTER TABLE pneu_movimentacoes ADD CONSTRAINT fk_mov_empresa
--   FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE;
