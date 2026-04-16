<?php
/**
 * Posição financeira e fiscal do fornecedor (somente leitura — totais calculados em tempo real).
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

configure_session();
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa não definida']);
    exit;
}

$action = $_GET['action'] ?? '';
$fornecedor_id = (int)($_GET['fornecedor_id'] ?? $_GET['id'] ?? 0);
if ($fornecedor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'fornecedor_id inválido']);
    exit;
}

function db_name(PDO $conn): string {
    return (string) $conn->query('SELECT DATABASE()')->fetchColumn();
}

function table_has_column(PDO $conn, string $table, string $column): bool {
    $db = db_name($conn);
    $st = $conn->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$db, $table, $column]);
    return (int) $st->fetchColumn() > 0;
}

function table_exists(PDO $conn, string $table): bool {
    $db = db_name($conn);
    $st = $conn->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $st->execute([$db, $table]);
    return (int) $st->fetchColumn() > 0;
}

function only_digits(?string $s): string {
    return preg_replace('/\D/', '', (string) $s);
}

try {
    $conn = getConnection();

    $st = $conn->prepare('SELECT * FROM fornecedores WHERE id = ? AND empresa_id = ? LIMIT 1');
    $st->execute([$fornecedor_id, $empresa_id]);
    $forn = $st->fetch(PDO::FETCH_ASSOC);
    if (!$forn) {
        echo json_encode(['success' => false, 'message' => 'Fornecedor não encontrado']);
        exit;
    }

    if ($action === 'financeiro') {
        $has_fid = table_has_column($conn, 'contas_pagar', 'fornecedor_id');

        $sql = 'SELECT cp.id, cp.descricao, cp.valor, cp.data_vencimento, cp.data_pagamento,
                cp.fornecedor, cp.observacoes, s.nome AS status_nome
                FROM contas_pagar cp
                LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
                WHERE cp.empresa_id = :eid AND (';

        if ($has_fid) {
            $sql .= 'cp.fornecedor_id = :fid OR ((cp.fornecedor_id IS NULL OR cp.fornecedor_id = 0) AND LOWER(TRIM(cp.fornecedor)) = LOWER(TRIM(:fnome)))';
        } else {
            $sql .= 'LOWER(TRIM(cp.fornecedor)) = LOWER(TRIM(:fnome))';
        }
        $sql .= ') ORDER BY cp.data_vencimento DESC, cp.id DESC LIMIT 300';

        $params = [
            ':eid' => $empresa_id,
            ':fnome' => $forn['nome'] ?? '',
        ];
        if ($has_fid) {
            $params[':fid'] = $fornecedor_id;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resumo = [
            'qtd_contas' => count($contas),
            'total_em_aberto' => 0.0,
            'total_pago' => 0.0,
            'total_outros' => 0.0,
        ];

        foreach ($contas as $c) {
            $v = (float) ($c['valor'] ?? 0);
            $sn = strtoupper((string) ($c['status_nome'] ?? ''));
            if (strpos($sn, 'CANCEL') !== false) {
                $resumo['total_outros'] += $v;
                continue;
            }
            $dp = $c['data_pagamento'] ?? null;
            if ($dp !== null && $dp !== '' && $dp !== '0000-00-00') {
                $resumo['total_pago'] += $v;
            } else {
                $resumo['total_em_aberto'] += $v;
            }
        }

        echo json_encode([
            'success' => true,
            'fornecedor' => [
                'id' => (int) $forn['id'],
                'nome' => $forn['nome'],
                'tipo' => $forn['tipo'],
            ],
            'resumo' => $resumo,
            'contas' => $contas,
            'match' => [
                'por_fornecedor_id' => $has_fid,
                'legado_por_nome' => true,
            ],
        ]);
        exit;
    }

    if ($action === 'fiscal') {
        $cnpj = only_digits($forn['cnpj'] ?? '');
        $cpf = only_digits($forn['cpf'] ?? '');
        $doc = ($forn['tipo'] ?? '') === 'J' ? $cnpj : $cpf;

        $nfe_emitidas = [];
        $nfe_recebidas = [];

        if (table_exists($conn, 'fiscal_nfe_emitidas')) {
            if (strlen($cnpj) === 14) {
                $st = $conn->prepare(
                    "SELECT id, serie, numero_nfe, chave_acesso, valor_total, status, data_emissao,
                            destinatario_nome, destinatario_cnpj, destinatario_cpf
                     FROM fiscal_nfe_emitidas
                     WHERE empresa_id = ?
                       AND REPLACE(REPLACE(REPLACE(IFNULL(destinatario_cnpj,''),'.',''),'/',''),'-','') = ?
                     ORDER BY COALESCE(data_emissao, created_at) DESC, id DESC
                     LIMIT 150"
                );
                $st->execute([$empresa_id, $cnpj]);
                $nfe_emitidas = $st->fetchAll(PDO::FETCH_ASSOC);
            } elseif (strlen($cpf) === 11) {
                $st = $conn->prepare(
                    "SELECT id, serie, numero_nfe, chave_acesso, valor_total, status, data_emissao,
                            destinatario_nome, destinatario_cnpj, destinatario_cpf
                     FROM fiscal_nfe_emitidas
                     WHERE empresa_id = ?
                       AND REPLACE(REPLACE(REPLACE(IFNULL(destinatario_cpf,''),'.',''),'/',''),'-','') = ?
                     ORDER BY COALESCE(data_emissao, created_at) DESC, id DESC
                     LIMIT 150"
                );
                $st->execute([$empresa_id, $cpf]);
                $nfe_emitidas = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        if (table_exists($conn, 'fiscal_nfe_clientes') && strlen($doc) >= 11) {
            $st = $conn->prepare(
                "SELECT id, numero_nfe, serie_nfe, chave_acesso, data_emissao, data_entrada,
                        cliente_razao_social, cliente_cnpj, valor_total, status
                 FROM fiscal_nfe_clientes
                 WHERE empresa_id = ?
                   AND REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(cliente_cnpj,''),'.',''),'/',''),'-',''),' ','') = ?
                 ORDER BY data_emissao DESC, id DESC
                 LIMIT 150"
            );
            $st->execute([$empresa_id, $doc]);
            $nfe_recebidas = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        $sum_emit = array_sum(array_map(static function ($r) {
            return (float) ($r['valor_total'] ?? 0);
        }, $nfe_emitidas));
        $sum_rec = array_sum(array_map(static function ($r) {
            return (float) ($r['valor_total'] ?? 0);
        }, $nfe_recebidas));

        $qtd_cte_tomador = 0;
        $valor_cte_tomador = 0.0;
        if (strlen($cnpj) === 14 && table_exists($conn, 'fiscal_cte_itens') && table_exists($conn, 'fiscal_cte')) {
            $stCte = $conn->prepare(
                'SELECT COUNT(*) AS c, COALESCE(SUM(fc.valor_total), 0) AS v
                 FROM fiscal_cte_itens fi
                 INNER JOIN fiscal_cte fc ON fc.id = fi.cte_id AND fc.empresa_id = ?
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(fi.tomador_cnpj, \'\'), \'.\', \'\'), \'/\', \'\'), \'-\', \'\'), \' \', \'\') = ?'
            );
            $stCte->execute([$empresa_id, $cnpj]);
            $rcte = $stCte->fetch(PDO::FETCH_ASSOC);
            if ($rcte) {
                $qtd_cte_tomador = (int) ($rcte['c'] ?? 0);
                $valor_cte_tomador = (float) ($rcte['v'] ?? 0);
            }
        }

        echo json_encode([
            'success' => true,
            'fornecedor' => [
                'id' => (int) $forn['id'],
                'nome' => $forn['nome'],
                'tipo' => $forn['tipo'],
                'documento_match' => $doc,
            ],
            'resumo' => [
                'qtd_nfe_emitidas' => count($nfe_emitidas),
                'valor_total_emitidas' => round($sum_emit, 2),
                'qtd_nfe_recebidas' => count($nfe_recebidas),
                'valor_total_recebidas' => round($sum_rec, 2),
                'qtd_cte_tomador' => $qtd_cte_tomador,
                'valor_total_cte_tomador' => round($valor_cte_tomador, 2),
            ],
            'nfe_emitidas' => $nfe_emitidas,
            'nfe_recebidas' => $nfe_recebidas,
            'nota' => 'Emitidas = NF-e de venda para o CPF/CNPJ do cadastro. Recebidas = entrada (XML de terceiros) com mesmo documento. CT-e = registros em que o fornecedor é tomador do serviço (tabela fiscal_cte_itens). MDF-e não possui vínculo direto por CNPJ de terceiro neste schema.',
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
} catch (Throwable $e) {
    error_log('fornecedor_posicao.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao consultar posição']);
}
