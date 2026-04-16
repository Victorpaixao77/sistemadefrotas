<?php
/**
 * API Despesas de viagem - App Android Motoristas
 * GET: lista despesas por rota (rota_id obrigatório)
 * POST: cria ou atualiza despesa da rota (action: create | update)
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $rota_id = (int)($_GET['rota_id'] ?? 0);
        if (!$rota_id) {
            api_error('Informe rota_id.');
        }
        $stmt = $conn->prepare('
            SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m
        ');
        $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$stmt->fetch()) {
            api_error('Rota não encontrada.');
        }
        $stmt = $conn->prepare('
            SELECT * FROM despesas_viagem WHERE rota_id = :rota_id AND empresa_id = :e
        ');
        $stmt->execute([':rota_id' => $rota_id, ':e' => $empresa_id]);
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
        api_success(['despesas' => $lista]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true);
        if (!is_array($d)) {
            $d = $_POST;
        }

        $rota_id = (int)($d['rota_id'] ?? 0);
        $action = $d['action'] ?? 'create';

        if (!$rota_id) {
            api_error('Informe rota_id.');
        }

        $stmt = $conn->prepare('
            SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m
        ');
        $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$stmt->fetch()) {
            api_error('Rota não encontrada ou não pertence ao motorista.');
        }

        $descarga = (float)($d['descarga'] ?? 0);
        $pedagios = (float)($d['pedagios'] ?? 0);
        $caixinha = (float)($d['caixinha'] ?? 0);
        $estacionamento = (float)($d['estacionamento'] ?? 0);
        $lavagem = (float)($d['lavagem'] ?? 0);
        $borracharia = (float)($d['borracharia'] ?? 0);
        $eletrica_mecanica = (float)($d['eletrica_mecanica'] ?? 0);
        $adiantamento = (float)($d['adiantamento'] ?? 0);
        $total_despviagem = (float)($d['total'] ?? $d['total_despviagem'] ?? 0);
        $observacoes = $d['observacoes'] ?? null;

        if ($action === 'update') {
            $id = (int)($d['id'] ?? 0);
            if (!$id) {
                api_error('Para atualizar informe id da despesa.');
            }
            $stmt = $conn->prepare('
                UPDATE despesas_viagem SET
                    descarga = :descarga, pedagios = :pedagios, caixinha = :caixinha,
                    estacionamento = :estacionamento, lavagem = :lavagem, borracharia = :borracharia,
                    eletrica_mecanica = :eletrica_mecanica, adiantamento = :adiantamento,
                    total_despviagem = :total_despviagem, status = :status, fonte = :fonte
                WHERE id = :id AND empresa_id = :e AND rota_id = :rota_id
            ');
            $stmt->execute([
                ':descarga' => $descarga, ':pedagios' => $pedagios, ':caixinha' => $caixinha,
                ':estacionamento' => $estacionamento, ':lavagem' => $lavagem, ':borracharia' => $borracharia,
                ':eletrica_mecanica' => $eletrica_mecanica, ':adiantamento' => $adiantamento,
                ':total_despviagem' => $total_despviagem, ':status' => 'pendente', ':fonte' => 'motorista',
                ':id' => $id, ':e' => $empresa_id, ':rota_id' => $rota_id,
            ]);
            api_success(null, 'Despesas atualizadas com sucesso.');
        }

        $stmt = $conn->prepare('
            INSERT INTO despesas_viagem (
                empresa_id, rota_id, descarga, pedagios, caixinha,
                estacionamento, lavagem, borracharia, eletrica_mecanica,
                adiantamento, total_despviagem, status, fonte
            ) VALUES (
                :e, :rota_id, :descarga, :pedagios, :caixinha,
                :estacionamento, :lavagem, :borracharia, :eletrica_mecanica,
                :adiantamento, :total_despviagem, "pendente", "motorista"
            )
        ');
        $stmt->execute([
            ':e' => $empresa_id, ':rota_id' => $rota_id,
            ':descarga' => $descarga, ':pedagios' => $pedagios, ':caixinha' => $caixinha,
            ':estacionamento' => $estacionamento, ':lavagem' => $lavagem, ':borracharia' => $borracharia,
            ':eletrica_mecanica' => $eletrica_mecanica, ':adiantamento' => $adiantamento,
            ':total_despviagem' => $total_despviagem,
        ]);
        $id = $conn->lastInsertId();
        api_success(['id' => (int)$id], 'Despesas registradas com sucesso.');
    }

    api_error('Método não permitido.', 405);
} catch (PDOException $e) {
    error_log('API despesas: ' . $e->getMessage());
    api_error('Erro ao processar despesas.', 500);
}
