<?php
/**
 * Importação em massa de abastecimentos (gestor).
 *
 * GET  action=veiculos                        -> lista veículos da empresa
 * GET  action=motoristas                      -> lista motoristas da empresa
 * GET  action=rotas&veiculo_id&motorista_id   -> rotas do par (FK obrigatória)
 * GET  action=combustiveis                    -> tipos de combustível
 * POST action=import (JSON)                    -> insere as linhas lidas do arquivo
 *
 * O arquivo (foto/CSV/JSON) só traz data, km, posto, litros e valor; os campos
 * Veículo, Motorista, Rota, Combustível e Forma de pagamento são escolhidos uma
 * vez no modal e aplicados a todas as linhas.
 */
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once __DIR__ . '/../includes/bi_cache_invalidate.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

$conn = getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/** Verifica se um id pertence à empresa na tabela informada. */
function sf_pertence(PDO $conn, string $tabela, int $id, int $empresa): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM {$tabela} WHERE id = :id AND empresa_id = :e LIMIT 1");
    $stmt->execute([':id' => $id, ':e' => $empresa]);
    return (bool) $stmt->fetchColumn();
}

try {
    switch ($action) {
        case 'veiculos':
            $stmt = $conn->prepare('SELECT id, placa, modelo, marca FROM veiculos WHERE empresa_id = :e ORDER BY placa');
            $stmt->execute([':e' => $empresa_id]);
            api_json_send(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'motoristas':
            $stmt = $conn->prepare('SELECT id, nome FROM motoristas WHERE empresa_id = :e ORDER BY nome');
            $stmt->execute([':e' => $empresa_id]);
            api_json_send(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'rotas':
            $veiculo_id = (int) ($_GET['veiculo_id'] ?? 0);
            $motorista_id = (int) ($_GET['motorista_id'] ?? 0);
            $stmt = $conn->prepare(
                "SELECT r.id, r.data_saida,
                        co.nome AS origem, cd.nome AS destino
                 FROM rotas r
                 LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                 LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                 WHERE r.empresa_id = :e AND r.veiculo_id = :v AND r.motorista_id = :m
                 ORDER BY r.data_saida DESC
                 LIMIT 300"
            );
            $stmt->execute([':e' => $empresa_id, ':v' => $veiculo_id, ':m' => $motorista_id]);
            api_json_send(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combustiveis':
            $rows = $conn->query('SELECT nome FROM tipos_combustivel ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);
            api_json_send(['success' => true, 'data' => $rows]);
            break;

        case 'import':
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                api_json_error('Corpo inválido.', 422);
            }

            $veiculo_id = (int) ($body['veiculo_id'] ?? 0);
            $motorista_id = (int) ($body['motorista_id'] ?? 0);
            $rota_id = (int) ($body['rota_id'] ?? 0);
            $tipo_combustivel = trim((string) ($body['tipo_combustivel'] ?? ''));
            $forma_pagamento = trim((string) ($body['forma_pagamento'] ?? ''));
            $rows = $body['rows'] ?? [];

            // Validações dos campos compartilhados
            $faltando = [];
            if (!$veiculo_id) $faltando[] = 'Veículo';
            if (!$motorista_id) $faltando[] = 'Motorista';
            if (!$rota_id) $faltando[] = 'Rota';
            if ($tipo_combustivel === '') $faltando[] = 'Combustível';
            if ($forma_pagamento === '') $faltando[] = 'Forma de pagamento';
            if ($faltando) {
                api_json_error('Selecione: ' . implode(', ', $faltando) . '.', 422);
            }
            if (!is_array($rows) || !count($rows)) {
                api_json_error('Nenhuma linha para importar.', 422);
            }

            // Ownership / FK (evita inserir referências de outra empresa)
            if (!sf_pertence($conn, 'veiculos', $veiculo_id, $empresa_id)) {
                api_json_error('Veículo inválido.', 422);
            }
            if (!sf_pertence($conn, 'motoristas', $motorista_id, $empresa_id)) {
                api_json_error('Motorista inválido.', 422);
            }
            if (!sf_pertence($conn, 'rotas', $rota_id, $empresa_id)) {
                api_json_error('Rota inválida.', 422);
            }

            // Gestor/admin importam como aprovado; demais como pendente.
            $tipo_usuario = strtolower((string) ($_SESSION['tipo_usuario'] ?? ''));
            $status = in_array($tipo_usuario, ['gestor', 'admin'], true) ? 'aprovado' : 'pendente';
            $fonte = 'gestor';

            $sql = "INSERT INTO abastecimentos (
                        empresa_id, veiculo_id, motorista_id, posto, data_abastecimento,
                        litros, valor_litro, valor_total, km_atual, tipo_combustivel,
                        forma_pagamento, observacoes, rota_id, data_cadastro, status, fonte
                    ) VALUES (
                        :empresa_id, :veiculo_id, :motorista_id, :posto, :data_abastecimento,
                        :litros, :valor_litro, :valor_total, :km_atual, :tipo_combustivel,
                        :forma_pagamento, :observacoes, :rota_id, NOW(), :status, :fonte
                    )";
            $stmt = $conn->prepare($sql);

            $inseridos = 0;
            $erros = [];
            $conn->beginTransaction();
            try {
                foreach ($rows as $i => $r) {
                    $linha = $i + 1;
                    $litros = (float) ($r['litros'] ?? 0);
                    $valorTotal = (float) ($r['valorTotal'] ?? $r['valor_total'] ?? 0);
                    $km = (int) ($r['km'] ?? 0);
                    $posto = trim((string) ($r['posto'] ?? ''));

                    // data: aceita datetime (YYYY-MM-DDTHH:MM), ymd (YYYY-MM-DD) ou data_abastecimento
                    $dt = (string) ($r['datetimeLocal'] ?? $r['datetime'] ?? '');
                    if ($dt === '') {
                        $ymd = (string) ($r['ymd'] ?? $r['data'] ?? '');
                        $dt = $ymd !== '' ? ($ymd . ' 08:00:00') : '';
                    }
                    $dt = str_replace('T', ' ', $dt);
                    if (strlen($dt) === 16) {
                        $dt .= ':00';
                    }

                    if ($litros <= 0 || $valorTotal < 0 || $km <= 0 || $posto === '' || $dt === '') {
                        $erros[] = "Linha {$linha}: dados incompletos (km, litros, valor, posto, data).";
                        continue;
                    }
                    $ts = strtotime($dt);
                    if ($ts === false) {
                        $erros[] = "Linha {$linha}: data inválida.";
                        continue;
                    }

                    $valorLitro = $litros > 0 ? round($valorTotal / $litros, 2) : 0;

                    $stmt->execute([
                        ':empresa_id' => $empresa_id,
                        ':veiculo_id' => $veiculo_id,
                        ':motorista_id' => $motorista_id,
                        ':posto' => mb_substr($posto, 0, 100),
                        ':data_abastecimento' => date('Y-m-d H:i:s', $ts),
                        ':litros' => $litros,
                        ':valor_litro' => $valorLitro,
                        ':valor_total' => $valorTotal,
                        ':km_atual' => $km,
                        ':tipo_combustivel' => mb_substr($tipo_combustivel, 0, 50),
                        ':forma_pagamento' => mb_substr($forma_pagamento, 0, 50),
                        ':observacoes' => 'Importado em lote',
                        ':rota_id' => $rota_id,
                        ':status' => $status,
                        ':fonte' => $fonte,
                    ]);
                    $inseridos++;
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollBack();
                error_log('refuel_import_bulk import: ' . $e->getMessage());
                api_json_error('Falha ao importar. Nenhum registro foi salvo.', 500);
            }

            if ($inseridos > 0) {
                bi_cache_invalidate_empresa($conn, $empresa_id);
            }

            api_json_send([
                'success' => true,
                'inserted' => $inseridos,
                'skipped' => count($erros),
                'errors' => $erros,
                'status' => $status,
            ]);
            break;

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('api/refuel_import_bulk.php: ' . $e->getMessage());
    api_json_error('Erro ao processar a solicitação.', 500);
}
