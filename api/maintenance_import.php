<?php
/**
 * Importação em lote de manutenções (CSV → linhas JSON do front).
 * POST JSON: { rows: [{ data, placa, tipo, componente, fornecedor, valor, km, descricao, nota_fiscal, status }] }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';
require_once __DIR__ . '/../includes/bi_cache_invalidate.php';

configure_session();
session_start();

header('Content-Type: application/json; charset=utf-8');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }
api_require_csrf_json();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_error('Não autorizado.', 401);
}

$body = json_decode((string) file_get_contents('php://input'), true);
$rows = (is_array($body) && isset($body['rows']) && is_array($body['rows'])) ? $body['rows'] : [];
if (!count($rows)) {
    api_json_error('Nenhuma linha para importar.', 422);
}
if (count($rows) > 500) {
    api_json_error('Limite de 500 linhas por importação.', 422);
}

function mnt_import_parse_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $raw, $m)) {
        return '20' . $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    return null;
}

function mnt_import_parse_decimal($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (float) $value;
    }
    $s = preg_replace('/[^\d,.\-]/', '', (string) $value);
    if (!is_string($s) || $s === '') {
        return null;
    }
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = strrpos($s, ',') > strrpos($s, '.')
            ? str_replace(['.', ','], ['', '.'], $s)
            : str_replace(',', '', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }

    return is_numeric($s) ? (float) $s : null;
}

function mnt_import_lookup_id(PDO $conn, string $table, string $nome): ?int
{
    $nome = trim($nome);
    if ($nome === '') {
        return null;
    }
    $st = $conn->prepare("SELECT id FROM {$table} WHERE LOWER(TRIM(nome)) = LOWER(TRIM(:n)) LIMIT 1");
    $st->execute(['n' => $nome]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $st = $conn->prepare("SELECT id FROM {$table} WHERE LOWER(nome) LIKE LOWER(:n) LIMIT 1");
    $st->execute(['n' => '%' . $nome . '%']);

    return ($id = $st->fetchColumn()) ? (int) $id : null;
}

try {
    $conn = getConnection();

    $stVeic = $conn->prepare('SELECT id FROM veiculos WHERE empresa_id = :e AND UPPER(REPLACE(placa, "-", "")) = UPPER(REPLACE(:p, "-", "")) LIMIT 1');

    $stDefaultStatus = $conn->query("SELECT id FROM status_manutencao ORDER BY id LIMIT 1");
    $default_status = $stDefaultStatus ? (int) $stDefaultStatus->fetchColumn() : 0;

    $stTipoPrev = $conn->query("SELECT id FROM tipos_manutencao WHERE LOWER(nome) LIKE '%preventiva%' ORDER BY id LIMIT 1");
    $default_tipo = $stTipoPrev ? (int) $stTipoPrev->fetchColumn() : 0;
    if (!$default_tipo) {
        $default_tipo = (int) $conn->query('SELECT id FROM tipos_manutencao ORDER BY id LIMIT 1')->fetchColumn();
    }

    $stComp = $conn->query('SELECT id FROM componentes_manutencao ORDER BY id LIMIT 1');
    $default_comp = $stComp ? (int) $stComp->fetchColumn() : 0;

    $responsavel = trim((string) ($_SESSION['nome'] ?? ''));
    if ($responsavel === '') {
        $responsavel = 'Importação CSV';
    }

    $ins = $conn->prepare(
        'INSERT INTO manutencoes (
            empresa_id, data_manutencao, data_conclusao, veiculo_id, tipo_manutencao_id,
            componente_id, status_manutencao_id, km_atual, fornecedor,
            valor, custo_total, nota_fiscal, descricao, descricao_servico,
            observacoes, responsavel_aprovacao
        ) VALUES (
            :e, :data_m, :data_c, :vid, :tid,
            :cid, :sid, :km, :forn,
            :val, :custo, :nf, :desc, :desc_svc,
            :obs, :resp
        )'
    );

    $inseridos = 0;
    $erros = [];

    $conn->beginTransaction();
    foreach ($rows as $i => $row) {
        $linha = $i + 1;
        $placa = trim((string) ($row['placa'] ?? ''));
        if ($placa === '') {
            $erros[] = "Linha {$linha}: placa vazia.";
            continue;
        }

        $stVeic->execute(['e' => $empresa_id, 'p' => $placa]);
        $veiculo_id = (int) $stVeic->fetchColumn();
        if ($veiculo_id <= 0) {
            $erros[] = "Linha {$linha}: veículo \"{$placa}\" não encontrado.";
            continue;
        }

        $data_raw = (string) ($row['data'] ?? $row['data_manutencao'] ?? '');
        $data_m = mnt_import_parse_date($data_raw);
        if (!$data_m) {
            $erros[] = "Linha {$linha}: data inválida ({$data_raw}).";
            continue;
        }

        $valor = mnt_import_parse_decimal($row['valor'] ?? null);
        if ($valor === null || $valor < 0) {
            $erros[] = "Linha {$linha}: valor inválido.";
            continue;
        }

        $km = isset($row['km']) ? (int) preg_replace('/\D/', '', (string) $row['km']) : 0;

        $tipo_nome = trim((string) ($row['tipo'] ?? ''));
        $tipo_id = $tipo_nome !== '' ? mnt_import_lookup_id($conn, 'tipos_manutencao', $tipo_nome) : null;
        if (!$tipo_id) {
            $tipo_id = $default_tipo;
        }

        $comp_nome = trim((string) ($row['componente'] ?? ''));
        $comp_id = $comp_nome !== '' ? mnt_import_lookup_id($conn, 'componentes_manutencao', $comp_nome) : null;
        if (!$comp_id) {
            $comp_id = $default_comp;
        }

        $status_nome = trim((string) ($row['status'] ?? ''));
        $status_id = $status_nome !== '' ? mnt_import_lookup_id($conn, 'status_manutencao', $status_nome) : null;
        if (!$status_id) {
            $status_id = $default_status;
        }

        $stNome = $conn->prepare('SELECT nome FROM status_manutencao WHERE id = :id LIMIT 1');
        $stNome->execute(['id' => $status_id]);
        $status_label = (string) $stNome->fetchColumn();
        $is_concluida = stripos($status_label, 'conclu') !== false;
        $data_c = $is_concluida ? $data_m : null;

        $desc = trim((string) ($row['descricao'] ?? ''));
        if ($desc === '') {
            $desc = 'Importado — ' . $placa;
        }
        $desc_svc = trim((string) ($row['descricao_servico'] ?? ''));
        if ($desc_svc === '') {
            $desc_svc = $desc;
        }

        $ins->execute([
            'e' => $empresa_id,
            'data_m' => $data_m,
            'data_c' => $data_c,
            'vid' => $veiculo_id,
            'tid' => $tipo_id,
            'cid' => $comp_id,
            'sid' => $status_id,
            'km' => $km,
            'forn' => trim((string) ($row['fornecedor'] ?? '')),
            'val' => $valor,
            'custo' => $valor,
            'nf' => trim((string) ($row['nota_fiscal'] ?? '')),
            'desc' => $desc,
            'desc_svc' => $desc_svc,
            'obs' => 'Importação CSV linha ' . $linha,
            'resp' => $responsavel,
        ]);
        $inseridos++;
    }

    if ($inseridos === 0) {
        $conn->rollBack();
        api_json_error('Nenhuma linha importada.' . (count($erros) ? ' ' . implode(' ', array_slice($erros, 0, 5)) : ''), 422);
    }

    $conn->commit();
    bi_cache_invalidate_empresa($conn, $empresa_id);

    echo json_encode([
        'success' => true,
        'inserted' => $inseridos,
        'skipped' => count($rows) - $inseridos,
        'errors' => array_slice($erros, 0, 20),
        'message' => $inseridos . ' manutenção(ões) importada(s).',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('maintenance_import: ' . $e->getMessage());
    api_json_error('Erro ao importar manutenções.', 500);
}
