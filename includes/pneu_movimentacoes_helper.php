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
    $usuario_id = isset($data['usuario_id']) ? (int) $data['usuario_id'] : null;
    $ip_origem = isset($data['ip_origem']) ? trim((string) $data['ip_origem']) : null;
    $motivo_remocao_id = isset($data['motivo_remocao_id']) ? (int) $data['motivo_remocao_id'] : null;
    $filial_id = isset($data['filial_id']) ? (int) $data['filial_id'] : null;
    if ($usuario_id === null && function_exists('pneus_audit_context')) {
        if (!function_exists('pneus_column_exists')) {
            require_once __DIR__ . '/pneus_helpers.php';
        }
        $ctx = pneus_audit_context();
        $usuario_id = $ctx['usuario_id'];
        if ($ip_origem === null) {
            $ip_origem = $ctx['ip_origem'];
        }
    }
    $cols = ['empresa_id', 'pneu_id', 'tipo', 'veiculo_id', 'eixo_id', 'posicao_id',
        'km_odometro', 'km_rodado', 'sulco_mm', 'custo', 'fornecedor_id', 'observacoes'];
    $vals = [':empresa_id', ':pneu_id', ':tipo', ':veiculo_id', ':eixo_id', ':posicao_id',
        ':km_odometro', ':km_rodado', ':sulco_mm', ':custo', ':fornecedor_id', ':observacoes'];
    $params = [
        ':empresa_id' => $empresa_id,
        ':pneu_id' => $pneu_id,
        ':tipo' => $tipo,
        ':veiculo_id' => $veiculo_id,
        ':eixo_id' => $eixo_id,
        ':posicao_id' => $posicao_id,
        ':km_odometro' => $km_odometro,
        ':km_rodado' => $km_rodado,
        ':sulco_mm' => $sulco_mm,
        ':custo' => $custo,
        ':fornecedor_id' => $fornecedor_id,
        ':observacoes' => $observacoes,
    ];
    if (!function_exists('pneus_column_exists')) {
        require_once __DIR__ . '/pneus_helpers.php';
    }
    if (pneus_column_exists($pdo, 'pneu_movimentacoes', 'usuario_id')) {
        $cols[] = 'usuario_id';
        $vals[] = ':usuario_id';
        $params[':usuario_id'] = $usuario_id;
    }
    if (pneus_column_exists($pdo, 'pneu_movimentacoes', 'ip_origem')) {
        $cols[] = 'ip_origem';
        $vals[] = ':ip_origem';
        $params[':ip_origem'] = $ip_origem;
    }
    if (pneus_column_exists($pdo, 'pneu_movimentacoes', 'motivo_remocao_id') && $motivo_remocao_id > 0) {
        $cols[] = 'motivo_remocao_id';
        $vals[] = ':motivo_remocao_id';
        $params[':motivo_remocao_id'] = $motivo_remocao_id;
    }
    if (pneus_column_exists($pdo, 'pneu_movimentacoes', 'filial_id') && $filial_id > 0) {
        $cols[] = 'filial_id';
        $vals[] = ':filial_id';
        $params[':filial_id'] = $filial_id;
    }
    $sql = 'INSERT INTO pneu_movimentacoes (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === ':sulco_mm') {
                $stmt->bindValue($k, $v, $v !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            } elseif (in_array($k, [':empresa_id', ':pneu_id', ':veiculo_id', ':eixo_id', ':posicao_id', ':km_odometro', ':km_rodado', ':fornecedor_id', ':usuario_id', ':motivo_remocao_id', ':filial_id'], true)) {
                $stmt->bindValue($k, $v, $v !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($k, $v, $v !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            }
        }
        $stmt->execute();
        if (file_exists(__DIR__ . '/pneus_repository.php')) {
            require_once __DIR__ . '/pneus_repository.php';
            if ($sulco_mm !== null && function_exists('pneus_atualizar_sulco_registro')) {
                pneus_atualizar_sulco_registro($pdo, $pneu_id, $sulco_mm);
            }
        }
        return true;
    } catch (PDOException $e) {
        if (function_exists('error_log')) {
            error_log('pneu_movimentacao_inserir: ' . $e->getMessage());
        }
        return false;
    }
}
