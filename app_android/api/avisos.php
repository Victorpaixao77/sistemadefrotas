<?php
/**
 * Avisos do motorista - App Android (alternativa a Push/FCM via polling).
 * GET: lista de avisos relevantes calculados a partir dos dados existentes:
 *      - rotas de hoje e próximas (aprovadas);
 *      - checklist pendente (rota de hoje sem checklist);
 *      - CNH vencendo / vencida.
 * Cada aviso tem uma "chave" estável para o app não notificar duas vezes.
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

try {
    $conn = getConnection();
    $avisos = [];

    // Avisos enviados pelo gestor/Copiloto (IA).
    $idsMarcarLidos = [];
    try {
        $stIa = $conn->prepare("SELECT id, titulo, mensagem, created_at FROM motorista_avisos_ia
            WHERE motorista_id = :m AND empresa_id = :e AND lido_em IS NULL
            ORDER BY id DESC LIMIT 10");
        $stIa->execute([':m' => $motorista_id, ':e' => $empresa_id]);
        foreach ($stIa->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $idsMarcarLidos[] = (int) $row['id'];
            $avisos[] = [
                'chave' => 'ia:' . (int) $row['id'],
                'tipo' => 'gestor',
                'titulo' => (string) $row['titulo'],
                'descricao' => (string) $row['mensagem'],
                'data' => (string) $row['created_at'],
            ];
        }
        if ($idsMarcarLidos !== []) {
            $in = implode(',', array_map('intval', $idsMarcarLidos));
            $conn->exec("UPDATE motorista_avisos_ia SET lido_em = NOW() WHERE id IN ($in) AND motorista_id = " . (int) $motorista_id);
        }
    } catch (Throwable $e) {
        // tabela pode não existir ainda
    }

    // Rotas de hoje e próximas (até +2 dias), aprovadas.
    $r = $conn->prepare("
        SELECT r.id, r.data_rota,
               c1.nome AS origem, c2.nome AS destino,
               DATEDIFF(DATE(r.data_rota), CURDATE()) AS dias
        FROM rotas r
        LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
        LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
        WHERE r.empresa_id = :e AND r.motorista_id = :m
          AND r.status = 'aprovado'
          AND DATE(r.data_rota) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
        ORDER BY r.data_rota ASC
        LIMIT 20
    ");
    $r->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $rotasHoje = [];
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dias = (int) $row['dias'];
        $trajeto = trim(($row['origem'] ?? '') . ' → ' . ($row['destino'] ?? ''), ' →');
        if ($dias === 0) {
            $rotasHoje[] = (int) $row['id'];
            $titulo = 'Rota de hoje';
            $quando = 'hoje';
        } elseif ($dias === 1) {
            $titulo = 'Rota amanhã';
            $quando = 'amanhã';
        } else {
            $titulo = 'Rota agendada';
            $quando = 'em ' . $dias . ' dias';
        }
        $avisos[] = [
            'chave' => 'rota:' . (int) $row['id'],
            'tipo' => 'rota',
            'titulo' => $titulo,
            'descricao' => $trajeto . ' · ' . $quando,
            'data' => (string) $row['data_rota'],
        ];
    }

    // Checklist pendente: rota de hoje aprovada sem checklist_viagem.
    if ($rotasHoje) {
        $in = implode(',', array_fill(0, count($rotasHoje), '?'));
        $cl = $conn->prepare("
            SELECT r.id, c2.nome AS destino
            FROM rotas r
            LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
            WHERE r.id IN ($in)
              AND NOT EXISTS (SELECT 1 FROM checklist_viagem cv WHERE cv.rota_id = r.id)
        ");
        $cl->execute($rotasHoje);
        foreach ($cl->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $avisos[] = [
                'chave' => 'checklist:' . (int) $row['id'],
                'tipo' => 'checklist',
                'titulo' => 'Checklist pendente',
                'descricao' => 'Faça o checklist da rota de hoje' . ($row['destino'] ? ' para ' . $row['destino'] : '') . '.',
                'data' => date('Y-m-d'),
            ];
        }
    }

    // CNH vencendo (≤30 dias) ou vencida.
    $cnh = $conn->prepare("
        SELECT data_validade_cnh, DATEDIFF(data_validade_cnh, CURDATE()) AS dias
        FROM motoristas
        WHERE id = :m AND empresa_id = :e AND data_validade_cnh IS NOT NULL
        LIMIT 1
    ");
    $cnh->execute([':m' => $motorista_id, ':e' => $empresa_id]);
    if ($c = $cnh->fetch(PDO::FETCH_ASSOC)) {
        $dias = (int) $c['dias'];
        if ($dias <= 30) {
            $avisos[] = [
                'chave' => 'cnh:' . (string) $c['data_validade_cnh'],
                'tipo' => 'documento',
                'titulo' => $dias < 0 ? 'CNH vencida' : 'CNH vencendo',
                'descricao' => $dias < 0
                    ? 'Sua CNH venceu em ' . date('d/m/Y', strtotime((string) $c['data_validade_cnh'])) . '.'
                    : ($dias === 0 ? 'Sua CNH vence hoje.' : 'Sua CNH vence em ' . $dias . ' dia(s).'),
                'data' => (string) $c['data_validade_cnh'],
            ];
        }
    }

    api_success(['avisos' => $avisos, 'total' => count($avisos)]);
} catch (PDOException $e) {
    error_log('avisos.php: ' . $e->getMessage());
    api_error('Erro ao carregar avisos.', 500);
}
