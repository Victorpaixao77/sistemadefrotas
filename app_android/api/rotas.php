<?php
/**
 * API Rotas - App Android Motoristas
 * GET: lista rotas do motorista (filtros: status, data_inicio, data_fim)
 *       GET ?id=X: retorna uma rota com despesas e abastecimentos vinculados
 * POST: cria rota (pendente, fonte motorista)
 * PUT: atualiza rota (id obrigatório)
 * DELETE: exclui rota (id obrigatório)
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

// Suporte a PUT/DELETE via POST com _method
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (is_array($body) && !empty($body['_method'])) {
    $method = strtoupper($body['_method']);
}

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            // Detalhe de uma rota: rota + despesas + abastecimentos
            $stmt = $conn->prepare('
                SELECT r.*, c1.nome AS cidade_origem_nome, c2.nome AS cidade_destino_nome, v.placa, v.modelo
                FROM rotas r
                LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
                LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                WHERE r.id = :id AND r.empresa_id = :e AND r.motorista_id = :m
            ');
            $stmt->execute([':id' => $id, ':e' => $empresa_id, ':m' => $motorista_id]);
            $rota = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rota) {
                api_error('Rota não encontrada.', 404);
            }
            $stmt = $conn->prepare('SELECT * FROM despesas_viagem WHERE rota_id = :rota_id AND empresa_id = :e');
            $stmt->execute([':rota_id' => $id, ':e' => $empresa_id]);
            $rota['despesas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $conn->prepare('
                SELECT a.*, v.placa, v.modelo
                FROM abastecimentos a
                JOIN veiculos v ON a.veiculo_id = v.id
                WHERE a.rota_id = :rota_id AND a.empresa_id = :e AND a.motorista_id = :m
                ORDER BY a.data_abastecimento DESC
            ');
            $stmt->execute([':rota_id' => $id, ':e' => $empresa_id, ':m' => $motorista_id]);
            $rota['abastecimentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            api_success(['rota' => $rota]);
        }

        $status = $_GET['status'] ?? null;
        $data_inicio = $_GET['data_inicio'] ?? null;
        $data_fim = $_GET['data_fim'] ?? null;
        $limite = min(100, max(1, (int)($_GET['limite'] ?? 50)));

        $sql = '
            SELECT r.*, c1.nome AS cidade_origem_nome, c2.nome AS cidade_destino_nome, v.placa
            FROM rotas r
            LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
            LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            WHERE r.empresa_id = :e AND r.motorista_id = :m
        ';
        $params = [':e' => $empresa_id, ':m' => $motorista_id];

        if ($status !== null && $status !== '') {
            $sql .= ' AND r.status = :status';
            $params[':status'] = $status;
        }
        if (!empty($data_inicio)) {
            $sql .= ' AND DATE(r.data_rota) >= :data_inicio';
            $params[':data_inicio'] = $data_inicio;
        }
        if (!empty($data_fim)) {
            $sql .= ' AND DATE(r.data_rota) <= :data_fim';
            $params[':data_fim'] = $data_fim;
        }

        $sql .= ' ORDER BY r.data_rota DESC LIMIT ' . $limite;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        api_success(['rotas' => $rotas]);
    }

    if ($method === 'POST') {
        $d = is_array($body) ? $body : (json_decode($raw, true) ?: $_POST);

        $veiculo_id = (int)($d['veiculo_id'] ?? 0);
        $cidade_origem_id = (int)($d['cidade_origem_id'] ?? 0);
        $cidade_destino_id = (int)($d['cidade_destino_id'] ?? 0);
        $estado_origem = strtoupper(substr(trim($d['estado_origem'] ?? ''), 0, 2));
        $estado_destino = strtoupper(substr(trim($d['estado_destino'] ?? ''), 0, 2));
        $data_saida = $d['data_saida'] ?? null;
        $data_chegada = $d['data_chegada'] ?? null;
        $data_rota = $d['data_rota'] ?? $data_saida;
        $km_saida = isset($d['km_saida']) ? (float)$d['km_saida'] : null;
        $km_chegada = isset($d['km_chegada']) ? (float)$d['km_chegada'] : null;
        $distancia_km = isset($d['distancia_km']) ? (float)$d['distancia_km'] : null;
        if ($distancia_km === null && $km_saida !== null && $km_chegada !== null) {
            $distancia_km = $km_chegada - $km_saida;
        }
        $km_vazio = isset($d['km_vazio']) ? (float)$d['km_vazio'] : null;
        $total_km = isset($d['total_km']) ? (float)$d['total_km'] : null;
        $percentual_vazio = isset($d['percentual_vazio']) ? (float)$d['percentual_vazio'] : null;
        $eficiencia_viagem = isset($d['eficiencia_viagem']) ? (float)$d['eficiencia_viagem'] : null;
        $frete = isset($d['frete']) ? (float)$d['frete'] : null;
        $comissao = isset($d['comissao']) ? (float)$d['comissao'] : null;
        $no_prazo = isset($d['no_prazo']) ? (int)(bool)$d['no_prazo'] : 0;
        $observacoes = $d['observacoes'] ?? null;
        $peso_carga = isset($d['peso_carga']) ? (float)$d['peso_carga'] : null;
        $descricao_carga = $d['descricao_carga'] ?? null;

        if (!$veiculo_id || !$cidade_origem_id || !$cidade_destino_id || !$data_saida) {
            api_error('Informe veiculo_id, cidade_origem_id, cidade_destino_id e data_saida.');
        }

        // Valida veículo da empresa
        $stmt = $conn->prepare('SELECT id FROM veiculos WHERE id = :id AND empresa_id = :e');
        $stmt->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
        if (!$stmt->fetch()) {
            api_error('Veículo inválido.');
        }

        $stmt = $conn->prepare('
            INSERT INTO rotas (
                empresa_id, veiculo_id, motorista_id,
                estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
                data_saida, data_chegada, km_saida, km_chegada, distancia_km,
                km_vazio, total_km, percentual_vazio, eficiencia_viagem,
                frete, comissao, no_prazo,
                observacoes, data_rota, peso_carga, descricao_carga, status, fonte
            ) VALUES (
                :e, :veiculo_id, :m,
                :estado_origem, :cidade_origem_id, :estado_destino, :cidade_destino_id,
                :data_saida, :data_chegada, :km_saida, :km_chegada, :distancia_km,
                :km_vazio, :total_km, :percentual_vazio, :eficiencia_viagem,
                :frete, :comissao, :no_prazo,
                :observacoes, :data_rota, :peso_carga, :descricao_carga, "pendente", "motorista"
            )
        ');
        $stmt->execute([
            ':e' => $empresa_id,
            ':veiculo_id' => $veiculo_id,
            ':m' => $motorista_id,
            ':estado_origem' => $estado_origem,
            ':cidade_origem_id' => $cidade_origem_id,
            ':estado_destino' => $estado_destino,
            ':cidade_destino_id' => $cidade_destino_id,
            ':data_saida' => $data_saida,
            ':data_chegada' => $data_chegada,
            ':km_saida' => $km_saida,
            ':km_chegada' => $km_chegada,
            ':distancia_km' => $distancia_km,
            ':km_vazio' => $km_vazio,
            ':total_km' => $total_km,
            ':percentual_vazio' => $percentual_vazio,
            ':eficiencia_viagem' => $eficiencia_viagem,
            ':frete' => $frete,
            ':comissao' => $comissao,
            ':no_prazo' => $no_prazo,
            ':observacoes' => $observacoes,
            ':data_rota' => $data_rota ?: $data_saida,
            ':peso_carga' => $peso_carga,
            ':descricao_carga' => $descricao_carga,
        ]);
        $id = $conn->lastInsertId();
        api_success(['id' => (int)$id], 'Rota registrada com sucesso.');
    }

    if ($method === 'PUT') {
        $d = is_array($body) ? $body : (json_decode($raw, true) ?: $_POST);
        $id = (int)($d['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            api_error('Informe o id da rota para atualizar.');
        }
        $stmt = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
        $stmt->execute([':id' => $id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$stmt->fetch()) {
            api_error('Rota não encontrada.', 404);
        }
        $veiculo_id = (int)($d['veiculo_id'] ?? 0);
        $cidade_origem_id = (int)($d['cidade_origem_id'] ?? 0);
        $cidade_destino_id = (int)($d['cidade_destino_id'] ?? 0);
        $estado_origem = strtoupper(substr(trim($d['estado_origem'] ?? ''), 0, 2));
        $estado_destino = strtoupper(substr(trim($d['estado_destino'] ?? ''), 0, 2));
        $data_saida = $d['data_saida'] ?? null;
        $data_chegada = $d['data_chegada'] ?? null;
        $data_rota = $d['data_rota'] ?? $data_saida;
        $km_saida = isset($d['km_saida']) ? (float)$d['km_saida'] : null;
        $km_chegada = isset($d['km_chegada']) ? (float)$d['km_chegada'] : null;
        $distancia_km = isset($d['distancia_km']) ? (float)$d['distancia_km'] : null;
        if ($distancia_km === null && $km_saida !== null && $km_chegada !== null) {
            $distancia_km = $km_chegada - $km_saida;
        }
        $km_vazio = isset($d['km_vazio']) ? (float)$d['km_vazio'] : null;
        $total_km = isset($d['total_km']) ? (float)$d['total_km'] : null;
        $percentual_vazio = isset($d['percentual_vazio']) ? (float)$d['percentual_vazio'] : null;
        $eficiencia_viagem = isset($d['eficiencia_viagem']) ? (float)$d['eficiencia_viagem'] : null;
        $frete = isset($d['frete']) ? (float)$d['frete'] : null;
        $comissao = isset($d['comissao']) ? (float)$d['comissao'] : null;
        $no_prazo = isset($d['no_prazo']) ? (int)(bool)$d['no_prazo'] : 0;
        $observacoes = $d['observacoes'] ?? null;
        $peso_carga = isset($d['peso_carga']) ? (float)$d['peso_carga'] : null;
        $descricao_carga = $d['descricao_carga'] ?? null;

        if (!$veiculo_id || !$cidade_origem_id || !$cidade_destino_id || !$data_saida) {
            api_error('Informe veiculo_id, cidade_origem_id, cidade_destino_id e data_saida.');
        }
        $stmt = $conn->prepare('SELECT id FROM veiculos WHERE id = :id AND empresa_id = :e');
        $stmt->execute([':id' => $veiculo_id, ':e' => $empresa_id]);
        if (!$stmt->fetch()) {
            api_error('Veículo inválido.');
        }
        $stmt = $conn->prepare('
            UPDATE rotas SET
                veiculo_id = :veiculo_id,
                estado_origem = :estado_origem, cidade_origem_id = :cidade_origem_id,
                estado_destino = :estado_destino, cidade_destino_id = :cidade_destino_id,
                data_saida = :data_saida, data_chegada = :data_chegada,
                data_rota = :data_rota,
                km_saida = :km_saida, km_chegada = :km_chegada, distancia_km = :distancia_km,
                km_vazio = :km_vazio, total_km = :total_km,
                percentual_vazio = :percentual_vazio, eficiencia_viagem = :eficiencia_viagem,
                frete = :frete, comissao = :comissao, no_prazo = :no_prazo,
                observacoes = :observacoes, peso_carga = :peso_carga, descricao_carga = :descricao_carga
            WHERE id = :id AND empresa_id = :e AND motorista_id = :m
        ');
        $stmt->execute([
            ':veiculo_id' => $veiculo_id,
            ':estado_origem' => $estado_origem, ':cidade_origem_id' => $cidade_origem_id,
            ':estado_destino' => $estado_destino, ':cidade_destino_id' => $cidade_destino_id,
            ':data_saida' => $data_saida, ':data_chegada' => $data_chegada,
            ':data_rota' => $data_rota ?: $data_saida,
            ':km_saida' => $km_saida, ':km_chegada' => $km_chegada, ':distancia_km' => $distancia_km,
            ':km_vazio' => $km_vazio, ':total_km' => $total_km,
            ':percentual_vazio' => $percentual_vazio, ':eficiencia_viagem' => $eficiencia_viagem,
            ':frete' => $frete, ':comissao' => $comissao, ':no_prazo' => $no_prazo,
            ':observacoes' => $observacoes, ':peso_carga' => $peso_carga, ':descricao_carga' => $descricao_carga,
            ':id' => $id, ':e' => $empresa_id, ':m' => $motorista_id,
        ]);
        api_success(['id' => $id], 'Rota atualizada com sucesso.');
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? (is_array($body) ? ($body['id'] ?? 0) : 0));
        if ($id <= 0) {
            api_error('Informe o id da rota para excluir.');
        }
        $stmt = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
        $stmt->execute([':id' => $id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$stmt->fetch()) {
            api_error('Rota não encontrada.', 404);
        }
        $conn->prepare('DELETE FROM despesas_viagem WHERE rota_id = :rota_id AND empresa_id = :e')->execute([':rota_id' => $id, ':e' => $empresa_id]);
        $stmt = $conn->prepare('DELETE FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
        $stmt->execute([':id' => $id, ':e' => $empresa_id, ':m' => $motorista_id]);
        api_success(null, 'Rota excluída com sucesso.');
    }

    api_error('Método não permitido.', 405);
} catch (PDOException $e) {
    error_log('API rotas: ' . $e->getMessage());
    api_error('Erro ao processar rotas.', 500);
}
