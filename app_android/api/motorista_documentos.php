<?php
/**
 * Documentos do motorista logado (CNH e alertas de vencimento).
 * GET
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_error('Método não permitido.', 405);
}

try {
    $conn = getConnection();
    $stmt = $conn->prepare('
        SELECT m.nome, m.cpf, m.cnh, m.data_validade_cnh, c.nome AS categoria_cnh
        FROM motoristas m
        LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
        WHERE m.id = :id AND m.empresa_id = :e
        LIMIT 1
    ');
    $stmt->execute([':id' => $motorista_id, ':e' => $empresa_id]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        api_error('Motorista não encontrado.', 404);
    }

    $validade = $m['data_validade_cnh'] ?? null;
    $dias = null;
    $status = 'ok';
    if ($validade) {
        try {
            $hoje = new DateTime('today');
            $v = new DateTime($validade);
            $dias = (int) $hoje->diff($v)->format('%r%a');
            if ($dias < 0) {
                $status = 'vencida';
            } elseif ($dias <= 30) {
                $status = 'vencendo';
            }
        } catch (Exception $e) {
            // Data de validade inválida no cadastro — não quebra a resposta.
            $dias = null;
            $status = 'ok';
        }
    }

    api_success([
        'nome' => $m['nome'],
        'cpf' => $m['cpf'],
        'cnh' => $m['cnh'],
        'categoria_cnh' => $m['categoria_cnh'],
        'data_validade_cnh' => $validade,
        'dias_para_vencer' => $dias,
        'status_cnh' => $status,
    ]);
} catch (PDOException $e) {
    error_log('motorista_documentos.php: ' . $e->getMessage());
    api_error('Erro ao carregar documentos.', 500);
}
