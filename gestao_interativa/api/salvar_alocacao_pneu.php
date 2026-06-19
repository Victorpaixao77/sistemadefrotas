<?php
/**
 * Remoção / envio para manutenção (modelo instalacoes_pneus legado).
 * Preferir gestao_interativa/api/eixos_veiculos.php para alocações novas.
 */
require_once __DIR__ . '/../../includes/pneus_api_base.php';
require_once __DIR__ . '/../../includes/pneu_movimentacoes_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_error('Método não permitido.', 405);
}

api_require_csrf_json();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    api_json_error('Dados inválidos.', 400);
}

$required = ['veiculo_id', 'pneu_id', 'acao'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        api_json_error('Campo obrigatório: ' . $field, 400);
    }
}

$veiculo_id = (int) $data['veiculo_id'];
$pneu_id = (int) $data['pneu_id'];
$acao = (string) $data['acao'];
$empresa_id = pneus_empresa_id();

try {
    $conn = getConnection();
    $conn->beginTransaction();

    if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
        $conn->rollBack();
        api_json_error('Pneu não encontrado.', 404);
    }
    if (!pneus_validar_veiculo_empresa($conn, $veiculo_id, $empresa_id)) {
        $conn->rollBack();
        api_json_error('Veículo não encontrado.', 404);
    }

    switch ($acao) {
        case 'alocar':
            $posicao_id = isset($data['posicao_id']) ? (int) $data['posicao_id'] : null;

            // Encerra instalação ativa do mesmo pneu (se houver) antes de realocar.
            $st = $conn->prepare('UPDATE instalacoes_pneus SET data_remocao = NOW() WHERE pneu_id = ? AND veiculo_id = ? AND data_remocao IS NULL');
            $st->execute([$pneu_id, $veiculo_id]);

            $st = $conn->prepare('INSERT INTO instalacoes_pneus (pneu_id, veiculo_id, posicao_id, data_instalacao, status) VALUES (?, ?, ?, CURDATE(), ?)');
            $st->execute([$pneu_id, $veiculo_id, $posicao_id, 'bom']);

            $statusEmUso = pneus_status_id_por_nome($conn, 'em uso') ?? 1;
            $st = $conn->prepare('UPDATE pneus SET status_id = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
            $st->execute([$statusEmUso, $pneu_id, $empresa_id]);
            pneus_sync_status_estoque($conn, $pneu_id, $empresa_id, $statusEmUso, 1);

            pneu_movimentacao_inserir($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id' => $pneu_id,
                'tipo' => 'instalacao',
                'veiculo_id' => $veiculo_id,
            ]);

            $mensagem = 'Pneu alocado com sucesso';
            break;

        case 'manutencao':
            $st = $conn->prepare('UPDATE instalacoes_pneus SET data_remocao = NOW() WHERE pneu_id = ? AND veiculo_id = ? AND data_remocao IS NULL');
            $st->execute([$pneu_id, $veiculo_id]);

            $statusManut = pneus_status_id_manutencao($conn) ?? pneus_status_id_por_nome($conn, 'furado') ?? 1;
            $st = $conn->prepare('UPDATE pneus SET status_id = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
            $st->execute([$statusManut, $pneu_id, $empresa_id]);
            pneus_sync_status_estoque($conn, $pneu_id, $empresa_id, $statusManut, 0);

            pneu_movimentacao_inserir($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id' => $pneu_id,
                'tipo' => 'manutencao',
                'veiculo_id' => $veiculo_id,
                'observacoes' => 'Enviado para manutenção (legado)',
            ]);

            $mensagem = 'Pneu enviado para manutenção com sucesso';
            break;

        case 'remover':
            $st = $conn->prepare('UPDATE instalacoes_pneus SET data_remocao = NOW() WHERE pneu_id = ? AND veiculo_id = ? AND data_remocao IS NULL');
            $st->execute([$pneu_id, $veiculo_id]);

            $statusDisp = pneus_status_id_por_nome($conn, 'reserva') ?? 2;
            $st = $conn->prepare('UPDATE pneus SET status_id = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
            $st->execute([$statusDisp, $pneu_id, $empresa_id]);
            pneus_sync_status_estoque($conn, $pneu_id, $empresa_id, $statusDisp, 1);

            pneu_movimentacao_inserir($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id' => $pneu_id,
                'tipo' => 'remocao',
                'veiculo_id' => $veiculo_id,
            ]);

            $mensagem = 'Pneu removido com sucesso';
            break;

        default:
            $conn->rollBack();
            api_json_error('Ação não reconhecida.', 400);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $mensagem], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('salvar_alocacao_pneu: ' . $e->getMessage());
    api_json_error('Erro ao processar alocação.', 500);
}
