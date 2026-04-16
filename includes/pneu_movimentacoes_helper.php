<?php
/**
 * Helper para registrar movimentações na tabela pneu_movimentacoes.
 * Só grava se a tabela existir; não quebra o fluxo se não existir ou der erro.
 *
 * Uso: pneu_movimentacao_inserir($pdo, [
 *   'empresa_id' => 1,
 *   'pneu_id'    => 123,
 *   'tipo'       => 'entrada_estoque', // ou instalacao, remocao, deslocamento, recapagem, manutencao, descarte
 *   'veiculo_id' => null,
 *   'eixo_id'    => null,
 *   'posicao_id' => null,
 *   'km_odometro'=> null,
 *   'km_rodado'  => null,
 *   'sulco_mm'   => null,
 *   'custo'      => 0,
 *   'fornecedor_id' => null,
 *   'observacoes'=> null,
 * ]);
 *
 * @param PDO $pdo
 * @param array $data empresa_id, pneu_id, tipo obrigatórios; demais opcionais
 * @return bool true se inseriu, false se tabela não existe ou erro
 */
function pneu_movimentacao_inserir(PDO $pdo, array $data) {
    $required = ['empresa_id', 'pneu_id', 'tipo'];
    foreach ($required as $k) {
        if (!array_key_exists($k, $data)) {
            if (function_exists('error_log')) {
                error_log('pneu_movimentacao_inserir: falta ' . $k);
            }
            return false;
        }
    }
    $tipos_validos = ['entrada_estoque', 'instalacao', 'remocao', 'deslocamento', 'recapagem', 'manutencao', 'descarte'];
    if (!in_array($data['tipo'], $tipos_validos, true)) {
        if (function_exists('error_log')) {
            error_log('pneu_movimentacao_inserir: tipo inválido ' . $data['tipo']);
        }
        return false;
    }
    try {
        $r = $pdo->query("SHOW TABLES LIKE 'pneu_movimentacoes'");
        if (!$r || $r->rowCount() === 0) {
            return false;
        }
    } catch (PDOException $e) {
        return false;
    }
    $empresa_id    = (int) $data['empresa_id'];
    $pneu_id       = (int) $data['pneu_id'];
    $tipo          = $data['tipo'];
    $veiculo_id    = isset($data['veiculo_id']) ? (int) $data['veiculo_id'] : null;
    $eixo_id       = isset($data['eixo_id']) ? (int) $data['eixo_id'] : null;
    $posicao_id    = isset($data['posicao_id']) ? (int) $data['posicao_id'] : null;
    $km_odometro   = isset($data['km_odometro']) ? (int) $data['km_odometro'] : null;
    $km_rodado     = isset($data['km_rodado']) ? (int) $data['km_rodado'] : null;
    $sulco_mm      = isset($data['sulco_mm']) ? (float) $data['sulco_mm'] : null;
    $custo         = isset($data['custo']) ? (float) $data['custo'] : 0.0;
    $fornecedor_id = isset($data['fornecedor_id']) ? (int) $data['fornecedor_id'] : null;
    $observacoes   = isset($data['observacoes']) ? trim((string) $data['observacoes']) : null;
    if ($observacoes === '') {
        $observacoes = null;
    }
    $sql = "INSERT INTO pneu_movimentacoes (
                empresa_id, pneu_id, tipo, veiculo_id, eixo_id, posicao_id,
                km_odometro, km_rodado, sulco_mm, custo, fornecedor_id, observacoes
            ) VALUES (
                :empresa_id, :pneu_id, :tipo, :veiculo_id, :eixo_id, :posicao_id,
                :km_odometro, :km_rodado, :sulco_mm, :custo, :fornecedor_id, :observacoes
            )";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindValue(':pneu_id', $pneu_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $stmt->bindValue(':eixo_id', $eixo_id, PDO::PARAM_INT);
        $stmt->bindValue(':posicao_id', $posicao_id, PDO::PARAM_INT);
        $stmt->bindValue(':km_odometro', $km_odometro, PDO::PARAM_INT);
        $stmt->bindValue(':km_rodado', $km_rodado, PDO::PARAM_INT);
        $stmt->bindValue(':sulco_mm', $sulco_mm, $sulco_mm !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':custo', $custo, PDO::PARAM_STR);
        $stmt->bindValue(':fornecedor_id', $fornecedor_id, PDO::PARAM_INT);
        $stmt->bindValue(':observacoes', $observacoes, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        if (function_exists('error_log')) {
            error_log('pneu_movimentacao_inserir: ' . $e->getMessage());
        }
        return false;
    }
}
