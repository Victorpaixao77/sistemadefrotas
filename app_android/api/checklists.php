<?php
/**
 * API Checklists de viagem - App Android Motoristas
 * GET: lista checklists do motorista (checklist_viagem)
 * POST: registra checklist de viagem
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(100, max(1, (int)($_GET['limite'] ?? 50)));
        $stmt = $conn->prepare('
            SELECT cv.*, v.placa, c1.nome AS cidade_origem_nome, c2.nome AS cidade_destino_nome
            FROM checklist_viagem cv
            JOIN veiculos v ON cv.veiculo_id = v.id
            JOIN rotas r ON cv.rota_id = r.id
            LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
            LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
            WHERE cv.empresa_id = :e AND cv.motorista_id = :m
            ORDER BY cv.data_checklist DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
        api_success(['checklists' => $lista]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true);
        if (!is_array($d)) {
            $d = $_POST;
        }

        $rota_id = (int)($d['rota_id'] ?? 0);
        $veiculo_id = (int)($d['veiculo_id'] ?? 0);
        $data_checklist = $d['data_checklist'] ?? date('Y-m-d H:i:s');

        if (!$rota_id || !$veiculo_id) {
            api_error('Informe rota_id e veiculo_id.');
        }

        $stmt = $conn->prepare('
            SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m
        ');
        $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$stmt->fetch()) {
            api_error('Rota não encontrada ou não pertence ao motorista.');
        }

        $stmt = $conn->prepare('SELECT id FROM veiculos WHERE id = :id AND empresa_id = :e');
        $stmt->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
        if (!$stmt->fetch()) {
            api_error('Veículo inválido.');
        }

        $bools = [
            'oleo_motor', 'agua_radiador', 'fluido_freio', 'fluido_direcao', 'combustivel',
            'pneus', 'estepe', 'luzes', 'buzina', 'limpador_para_brisa', 'agua_limpador',
            'freios', 'vazamentos', 'rastreador', 'triangulo', 'extintor', 'chave_macaco',
            'cintas', 'primeiros_socorros', 'doc_veiculo', 'cnh', 'licenciamento',
            'seguro', 'manifesto_carga', 'doc_empresa', 'carga_amarrada', 'peso_correto',
            'motorista_descansado', 'motorista_sobrio', 'celular_carregado', 'epi',
        ];
        $bind = [
            'empresa_id' => $empresa_id,
            'veiculo_id' => $veiculo_id,
            'motorista_id' => $motorista_id,
            'rota_id' => $rota_id,
            'data_checklist' => $data_checklist,
        ];
        foreach ($bools as $k) {
            $bind[$k] = !empty($d[$k]) ? 1 : 0;
        }
        $bind['observacoes'] = $d['observacoes'] ?? null;

        $cols = implode(', ', array_merge(
            ['empresa_id', 'veiculo_id', 'motorista_id', 'rota_id', 'data_checklist'],
            $bools,
            ['observacoes', 'fonte']
        ));
        $placeholders = ':' . implode(', :', array_merge(
            ['empresa_id', 'veiculo_id', 'motorista_id', 'rota_id', 'data_checklist'],
            $bools,
            ['observacoes']
        ));
        $placeholders .= ", 'motorista'";

        $sql = "INSERT INTO checklist_viagem ($cols) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        foreach ($bind as $key => $val) {
            $stmt->bindValue(":$key", $val);
        }
        $stmt->execute();
        $id = $conn->lastInsertId();
        api_success(['id' => (int)$id], 'Checklist registrado com sucesso.');
    }

    api_error('Método não permitido.', 405);
} catch (PDOException $e) {
    error_log('API checklists: ' . $e->getMessage());
    api_error('Erro ao processar checklists.', 500);
}
