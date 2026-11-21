<?php
/**
 * Colunas operacionais do veículo usadas na Simulação de Rota:
 *  - capacidade_tanque      (litros) → autonomia / nº de reabastecimentos;
 *  - consumo_medio          (km/L)   → fallback quando não há histórico de abastecimento;
 *  - custo_operacional_km   (R$/km)  → manutenção/pneus/depreciação no custo da rota.
 *
 * Garante as colunas em runtime (idempotente). Migração também em
 * sql/veiculos_sim_columns.sql.
 */

if (!function_exists('sf_ensure_veiculos_sim_columns')) {
    function sf_ensure_veiculos_sim_columns(PDO $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [
            'capacidade_tanque' => 'ADD COLUMN capacidade_tanque DECIMAL(7,2) NULL DEFAULT NULL',
            'consumo_medio' => 'ADD COLUMN consumo_medio DECIMAL(5,2) NULL DEFAULT NULL',
            'custo_operacional_km' => 'ADD COLUMN custo_operacional_km DECIMAL(8,2) NULL DEFAULT NULL',
        ];
        foreach ($cols as $alter) {
            try {
                $conn->exec('ALTER TABLE veiculos ' . $alter);
            } catch (Throwable $e) {
                // coluna já existe
            }
        }
    }
}
