<?php
/**
 * API - Indicadores de Desempenho
 * Retorna dados por período (ano/mês) ou últimos 12 meses.
 * Parâmetros: visao, ano (opcional), mes (opcional, 1-12).
 * Comparação: mes_anterior e mesmo_mes_ano_anterior quando ano informado.
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Valida CSRF apenas para mutações (POST/PUT/PATCH/DELETE).
api_require_csrf_json();
$visao = isset($_GET['visao']) ? trim(strtolower($_GET['visao'])) : 'geral';
if (!in_array($visao, ['geral', 'rotas', 'abastecimento', 'manutencao', 'despesas_viagem', 'despesas_fixas'], true)) {
    $visao = 'geral';
}

$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : 0;
$mes_param = isset($_GET['mes']) ? trim($_GET['mes']) : '';
$usar_periodo = ($ano >= 2000 && $ano <= 2100);
$meses_pt = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
$nocache = isset($_GET['nocache']) && ($_GET['nocache'] === '1' || strtolower($_GET['nocache']) === 'true');

try {
    $conn = getConnection();
    
    // Cache: chave por empresa + visão + período (evita recálculo a cada request)
    $cache_key = $empresa_id . '_' . $visao . '_' . $ano . '_' . $mes_param;
    if (!$nocache) {
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS bi_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                cache_key VARCHAR(120) NOT NULL,
                payload LONGTEXT NOT NULL,
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_bi_cache_key (empresa_id, cache_key),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $conn->prepare("SELECT payload FROM bi_cache WHERE empresa_id = :eid AND cache_key = :ck AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute(['eid' => $empresa_id, 'ck' => $cache_key]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cached && !empty($cached['payload'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo $cached['payload'];
                exit;
            }
        } catch (Exception $e) {
            // cache falhou; segue cálculo normal
        }
    }

    if ($usar_periodo) {
        $mes_int = ($mes_param !== '' && preg_match('/^([1-9]|1[0-2])$/', $mes_param)) ? (int)$mes_param : 0;
        $data_inicio = ($ano - 1) . '-01-01';
        $data_fim = $ano . '-12-31';
        $meses = [];
        $data_formatada = [];
        for ($y = $ano - 1; $y <= $ano; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $data = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                $meses[] = $data;
                $data_formatada[$data] = [
                    'mes_ano' => $data,
                    'mes_nome' => ($meses_pt[$m] ?? '') . '/' . $y,
                    'total_abastecimentos' => 0,
                    'total_gasto_abastecimentos' => 0,
                    'total_rotas' => 0,
                    'total_km_rodados' => 0,
                    'total_frete' => 0,
                    'total_comissao' => 0,
                    'lucro_operacional' => 0,
                    'total_despesas_viagem' => 0,
                    'total_manutencoes' => 0,
                    'total_despesas_fixas' => 0,
                    'quantidade_manutencoes' => 0,
                    'quantidade_despesas_fixas' => 0,
                    'quantidade_veiculos_ativos' => 0,
                    'total_litros' => 0
                ];
            }
        }
    } else {
        $data_inicio = date('Y-m-d', strtotime('-2 months'));
        $data_fim = date('Y-m-d');
    $meses = [];
    $data_formatada = [];
    for ($i = 2; $i >= 0; $i--) {
        $data = date('Y-m', strtotime("-$i months"));
        $meses[] = $data;
            $m = (int)substr($data, 5, 2);
            $y = (int)substr($data, 0, 4);
        $data_formatada[$data] = [
            'mes_ano' => $data,
                'mes_nome' => ($meses_pt[$m] ?? date('M', mktime(0,0,0,$m,1))) . '/' . $y,
            'total_abastecimentos' => 0,
            'total_gasto_abastecimentos' => 0,
            'total_rotas' => 0,
            'total_km_rodados' => 0,
            'total_frete' => 0,
            'total_comissao' => 0,
            'lucro_operacional' => 0,
            'total_despesas_viagem' => 0,
                'total_manutencoes' => 0,
                'total_despesas_fixas' => 0,
                'quantidade_manutencoes' => 0,
                'quantidade_despesas_fixas' => 0,
                'quantidade_veiculos_ativos' => 0,
                'total_litros' => 0
            ];
        }
    }

    $cond_abast = $usar_periodo ? " AND data_abastecimento >= :data_inicio AND data_abastecimento <= :data_fim " : " AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
    $cond_rotas = $usar_periodo ? " AND data_saida >= :data_inicio AND data_saida <= :data_fim " : " AND data_saida >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
    $cond_rotas_r = $usar_periodo ? " AND r.data_saida >= :data_inicio AND r.data_saida <= :data_fim " : " AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
    $cond_manut = $usar_periodo ? " AND data_manutencao >= :data_inicio AND data_manutencao <= :data_fim " : " AND data_manutencao >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
    $cond_manut_m = $usar_periodo ? " AND m.data_manutencao >= :data_inicio AND m.data_manutencao <= :data_fim " : " AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";
    $cond_fixas = $usar_periodo ? " AND COALESCE(data_pagamento, vencimento) >= :data_inicio AND COALESCE(data_pagamento, vencimento) <= :data_fim " : " AND COALESCE(data_pagamento, vencimento) >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ";

    // 1. Total de Abastecimentos por mês (incluindo ARLA e litros para consumo KM/L)
    $sql_abastecimentos = "
        SELECT 
            DATE_FORMAT(data_abastecimento, '%Y-%m') as mes_ano,
            COUNT(*) as total_abastecimentos,
            COALESCE(SUM(valor_total + COALESCE(
                CASE WHEN inclui_arla = 1 THEN valor_total_arla ELSE 0 END, 0
            )), 0) as total_gasto,
            COALESCE(SUM(COALESCE(litros, 0)), 0) as total_litros
        FROM abastecimentos
        WHERE empresa_id = :empresa_id
        AND status = 'aprovado'
        " . $cond_abast . "
        GROUP BY DATE_FORMAT(data_abastecimento, '%Y-%m')
    ";
    
    $stmt = $conn->prepare($sql_abastecimentos);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($abastecimentos as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_abastecimentos'] = (int)$row['total_abastecimentos'];
            $data_formatada[$row['mes_ano']]['total_gasto_abastecimentos'] = (float)$row['total_gasto'];
            $data_formatada[$row['mes_ano']]['total_litros'] = (float)($row['total_litros'] ?? 0);
        }
    }
    
    // 2. Total de Rotas por mês
    $sql_rotas = "
        SELECT 
            DATE_FORMAT(data_saida, '%Y-%m') as mes_ano,
            COUNT(*) as total_rotas,
            COALESCE(SUM(distancia_km), 0) as total_km,
            COALESCE(SUM(frete), 0) as total_frete,
            COALESCE(SUM(comissao), 0) as total_comissao
        FROM rotas
        WHERE empresa_id = :empresa_id
        AND data_saida IS NOT NULL
        " . $cond_rotas . "
        GROUP BY DATE_FORMAT(data_saida, '%Y-%m')
    ";
    $stmt = $conn->prepare($sql_rotas);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rotas as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_rotas'] = (int)$row['total_rotas'];
            $data_formatada[$row['mes_ano']]['total_km_rodados'] = (float)$row['total_km'];
            $data_formatada[$row['mes_ano']]['total_frete'] = (float)$row['total_frete'];
            $data_formatada[$row['mes_ano']]['total_comissao'] = (float)$row['total_comissao'];
        }
    }
    
    // 3. Despesas de Viagem por mês (total_despviagem; se zero, soma das rubricas — evita gráficos vazios quando só o detalhe foi lançado)
    $dvLinha = "CASE WHEN COALESCE(dv.total_despviagem, 0) > 0 THEN dv.total_despviagem ELSE (COALESCE(dv.descarga, 0) + COALESCE(dv.pedagios, 0) + COALESCE(dv.caixinha, 0) + COALESCE(dv.estacionamento, 0) + COALESCE(dv.lavagem, 0) + COALESCE(dv.borracharia, 0) + COALESCE(dv.eletrica_mecanica, 0) + COALESCE(dv.adiantamento, 0)) END";
    $sql_despesas = "
        SELECT 
            DATE_FORMAT(r.data_saida, '%Y-%m') as mes_ano,
            COALESCE(SUM($dvLinha), 0) as total_despesas
        FROM despesas_viagem dv
        INNER JOIN rotas r ON r.id = dv.rota_id
        WHERE r.empresa_id = :empresa_id
        AND r.data_saida IS NOT NULL
        " . $cond_rotas_r . "
        GROUP BY DATE_FORMAT(r.data_saida, '%Y-%m')
    ";
    $stmt = $conn->prepare($sql_despesas);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($despesas as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_despesas_viagem'] = (float)$row['total_despesas'];
        }
    }
    
    // 3b. Manutenções por mês (valor total)
    $sql_manut = "
        SELECT 
            DATE_FORMAT(data_manutencao, '%Y-%m') as mes_ano,
            COUNT(*) as quantidade_manutencoes,
            COALESCE(SUM(valor), 0) as total_manutencoes
        FROM manutencoes
        WHERE empresa_id = :empresa_id
        " . $cond_manut . "
        GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
    ";
    $stmt = $conn->prepare($sql_manut);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $manut = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($manut as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_manutencoes'] = (float)$row['total_manutencoes'];
            $data_formatada[$row['mes_ano']]['quantidade_manutencoes'] = (int)$row['quantidade_manutencoes'];
        }
    }
    
    // 3c. Despesas fixas por mês (pagas: status_pagamento_id = 2, por data_pagamento)
    $sql_fixas = "
        SELECT 
            DATE_FORMAT(COALESCE(data_pagamento, vencimento), '%Y-%m') as mes_ano,
            COUNT(*) as quantidade_despesas_fixas,
            COALESCE(SUM(valor), 0) as total_despesas_fixas
        FROM despesas_fixas
        WHERE empresa_id = :empresa_id
        AND status_pagamento_id = 2
        AND (data_pagamento IS NOT NULL OR vencimento IS NOT NULL)
        " . $cond_fixas . "
        GROUP BY DATE_FORMAT(COALESCE(data_pagamento, vencimento), '%Y-%m')
    ";
    $stmt = $conn->prepare($sql_fixas);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $fixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fixas as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_despesas_fixas'] = (float)$row['total_despesas_fixas'];
            $data_formatada[$row['mes_ano']]['quantidade_despesas_fixas'] = (int)$row['quantidade_despesas_fixas'];
        }
    }
    
    // Calcular lucro operacional por mês e normalizar valores (evitar floats longos)
    $roundMon = function ($v) { return $v !== null ? round((float)$v, 2) : 0; };
    $roundKm = function ($v) { return $v !== null ? round((float)$v, 2) : 0; };
    foreach ($data_formatada as &$mes) {
        $mes['lucro_operacional'] = $mes['total_frete'] - $mes['total_comissao'] - $mes['total_gasto_abastecimentos'] - $mes['total_despesas_viagem'];
        $mes['total_gasto_abastecimentos'] = $roundMon($mes['total_gasto_abastecimentos']);
        $mes['total_km_rodados'] = $roundKm($mes['total_km_rodados']);
        $mes['total_frete'] = $roundMon($mes['total_frete']);
        $mes['total_comissao'] = $roundMon($mes['total_comissao']);
        $mes['total_despesas_viagem'] = $roundMon($mes['total_despesas_viagem']);
        $mes['total_manutencoes'] = $roundMon($mes['total_manutencoes']);
        $mes['total_despesas_fixas'] = $roundMon($mes['total_despesas_fixas']);
        $mes['lucro_operacional'] = $roundMon($mes['lucro_operacional']);
        $mes['total_litros'] = $roundKm($mes['total_litros']);
    }
    unset($mes);
    
    // 5. Quantidade de veículos ativos por mês (uma query agregada — evita N round-trips ao banco)
    $sql_veiculos_por_mes = "
        SELECT DATE_FORMAT(r.data_saida, '%Y-%m') AS mes_ano,
               COUNT(DISTINCT v.id) AS quantidade
        FROM veiculos v
        INNER JOIN rotas r ON r.veiculo_id = v.id
        WHERE v.empresa_id = :empresa_id
        AND r.data_saida >= :data_inicio
        AND r.data_saida <= :data_fim
        GROUP BY DATE_FORMAT(r.data_saida, '%Y-%m')
    ";
    $stmt = $conn->prepare($sql_veiculos_por_mes);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindValue(':data_inicio', $data_inicio);
    $stmt->bindValue(':data_fim', $data_fim);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ma = $row['mes_ano'] ?? '';
        if ($ma !== '' && isset($data_formatada[$ma])) {
            $data_formatada[$ma]['quantidade_veiculos_ativos'] = (int)($row['quantidade'] ?? 0);
        }
    }
    
    // 4. Veículos mais utilizados (período)
    $sql_veiculos = "
        SELECT 
            v.id,
            v.placa,
            v.modelo,
            COUNT(DISTINCT r.id) as total_rotas,
            COALESCE(SUM(r.distancia_km), 0) as total_km
        FROM veiculos v
        INNER JOIN rotas r ON r.veiculo_id = v.id
        WHERE v.empresa_id = :empresa_id
        " . $cond_rotas_r . "
        GROUP BY v.id, v.placa, v.modelo
        ORDER BY total_rotas DESC, total_km DESC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql_veiculos);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $veiculos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($veiculos_top as &$v) {
        $v['total_km'] = isset($v['total_km']) ? round((float)$v['total_km'], 2) : 0;
    }
    unset($v);

    // Top veículos por abastecimento (quantidade e valor)
    $sql_veiculos_abast = "
        SELECT 
            v.id,
            v.placa,
            v.modelo,
            COUNT(a.id) as total_abastecimentos,
            COALESCE(SUM(a.valor_total + COALESCE(
                CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
            )), 0) as total_gasto
        FROM veiculos v
        INNER JOIN abastecimentos a ON a.veiculo_id = v.id
        WHERE v.empresa_id = :empresa_id
        AND a.status = 'aprovado'
        " . $cond_abast . "
        GROUP BY v.id, v.placa, v.modelo
        ORDER BY total_abastecimentos DESC, total_gasto DESC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql_veiculos_abast);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $veiculos_top_abastecimento = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($veiculos_top_abastecimento as &$v) {
        $v['total_gasto'] = isset($v['total_gasto']) ? round((float)$v['total_gasto'], 2) : 0;
    }
    unset($v);

    // Top veículos por custo de manutenção (período)
    $sql_veiculos_manut = "
        SELECT 
            v.id,
            v.placa,
            v.modelo,
            COUNT(m.id) as total_manutencoes,
            COALESCE(SUM(m.valor), 0) as total_gasto
        FROM veiculos v
        INNER JOIN manutencoes m ON m.veiculo_id = v.id
        WHERE v.empresa_id = :empresa_id
        " . $cond_manut_m . "
        GROUP BY v.id, v.placa, v.modelo
        ORDER BY total_gasto DESC, total_manutencoes DESC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql_veiculos_manut);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
    $stmt->execute();
    $veiculos_top_manutencao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ranking de rotas (top 5 mais e top 5 menos lucrativas) - últimos 12 meses
    $ranking_rotas = ['top5' => [], 'bottom5' => []];
    try {
        $sql_ranking = "
            SELECT 
                r.id, r.distancia_km as km, r.frete, r.comissao,
                COALESCE(dv.total_desp, 0) as desp_viagem,
                (r.frete - r.comissao - COALESCE(dv.total_desp, 0)) as lucro,
                CASE WHEN r.frete > 0 THEN ((r.frete - r.comissao - COALESCE(dv.total_desp, 0)) / r.frete * 100) ELSE 0 END as margem_pct,
                COALESCE(co.nome, '') as origem_nome,
                COALESCE(cd.nome, '') as destino_nome
            FROM rotas r
            LEFT JOIN (
                SELECT rota_id, SUM(COALESCE(total_despviagem, 0)) as total_desp
                FROM despesas_viagem
                GROUP BY rota_id
            ) dv ON dv.rota_id = r.id
            LEFT JOIN cidades co ON co.id = r.cidade_origem_id
            LEFT JOIN cidades cd ON cd.id = r.cidade_destino_id
            WHERE r.empresa_id = :empresa_id
            AND r.data_saida IS NOT NULL
            " . $cond_rotas_r . "
            AND r.frete IS NOT NULL
        ";
        $stmt = $conn->prepare($sql_ranking);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
        $stmt->execute();
        $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($todas as &$r) {
            $r['origem_destino'] = trim(($r['origem_nome'] ?? '') . ' → ' . ($r['destino_nome'] ?? ''));
            if ($r['origem_destino'] === ' → ') $r['origem_destino'] = 'Rota #' . $r['id'];
            $r['custo_total'] = round((float)($r['comissao'] ?? 0) + (float)($r['desp_viagem'] ?? 0), 2);
            $r['lucro'] = round((float)$r['lucro'], 2);
            $r['margem_pct'] = round((float)($r['margem_pct'] ?? 0), 2);
            $r['km'] = round((float)($r['km'] ?? 0), 2);
            $r['frete'] = round((float)($r['frete'] ?? 0), 2);
            $r['comissao'] = round((float)($r['comissao'] ?? 0), 2);
            $r['desp_viagem'] = round((float)($r['desp_viagem'] ?? 0), 2);
        }
        unset($r);
        usort($todas, function ($a, $b) { return $b['lucro'] <=> $a['lucro']; });
        $ranking_rotas['top5'] = array_slice($todas, 0, 5);
        $ranking_rotas['bottom5'] = array_slice(array_reverse($todas), 0, 5);
    } catch (Exception $e) {
        // ignora se tabela cidades não existir ou join falhar
    }
    
    // Despesas de viagem por tipo (totais no período) para gráfico pizza
    $despesas_viagem_tipos = [
        'descarga' => 0, 'pedagios' => 0, 'caixinha' => 0, 'estacionamento' => 0,
        'lavagem' => 0, 'borracharia' => 0, 'eletrica_mecanica' => 0, 'adiantamento' => 0
    ];
    try {
        $cols = ['descarga', 'pedagios', 'caixinha', 'estacionamento', 'lavagem', 'borracharia', 'eletrica_mecanica', 'adiantamento'];
        $sel = implode(', ', array_map(function ($c) { return 'COALESCE(SUM(' . $c . '), 0) as ' . $c; }, $cols));
        $sql_dv_tipos = "SELECT $sel FROM despesas_viagem dv INNER JOIN rotas r ON r.id = dv.rota_id WHERE r.empresa_id = :empresa_id " . $cond_rotas_r;
        $stmt = $conn->prepare($sql_dv_tipos);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($cols as $c) { $despesas_viagem_tipos[$c] = round((float)($row[$c] ?? 0), 2); }
        }
    } catch (Exception $e) { }
    
    // Manutenção preventiva x corretiva (via tipos_manutencao.nome: Preventiva / Corretiva)
    $manut_preventiva_corretiva = ['preventiva' => ['qtd' => 0, 'valor' => 0], 'corretiva' => ['qtd' => 0, 'valor' => 0]];
    try {
        $sql_manut_tipo = "
            SELECT 
                COALESCE(SUM(CASE WHEN LOWER(TRIM(tm.nome)) LIKE '%preventiva%' THEN 1 ELSE 0 END), 0) as qtd_preventiva,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(tm.nome)) LIKE '%preventiva%' THEN m.valor ELSE 0 END), 0) as valor_preventiva,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(tm.nome)) NOT LIKE '%preventiva%' OR tm.nome IS NULL THEN 1 ELSE 0 END), 0) as qtd_corretiva,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(tm.nome)) NOT LIKE '%preventiva%' OR tm.nome IS NULL THEN m.valor ELSE 0 END), 0) as valor_corretiva
            FROM manutencoes m
            LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
            WHERE m.empresa_id = :empresa_id
            " . $cond_manut_m . "
        ";
        $stmt = $conn->prepare($sql_manut_tipo);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) { $stmt->bindValue(':data_inicio', $data_inicio); $stmt->bindValue(':data_fim', $data_fim); }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $manut_preventiva_corretiva['preventiva'] = ['qtd' => (int)($row['qtd_preventiva'] ?? 0), 'valor' => round((float)($row['valor_preventiva'] ?? 0), 2)];
            $manut_preventiva_corretiva['corretiva'] = ['qtd' => (int)($row['qtd_corretiva'] ?? 0), 'valor' => round((float)($row['valor_corretiva'] ?? 0), 2)];
        }
    } catch (Exception $e) {
        // se tabela tipos_manutencao não existir, deixa zerado
    }
    
    // Veículos críticos (custo acima da média da frota)
    $veiculos_criticos = [];
    if (count($veiculos_top_manutencao) > 0) {
        $soma = 0;
        foreach ($veiculos_top_manutencao as $v) { $soma += (float)($v['total_gasto'] ?? 0); }
        $media_frota = $soma / count($veiculos_top_manutencao);
        foreach ($veiculos_top_manutencao as $v) {
            if ((float)($v['total_gasto'] ?? 0) >= $media_frota) {
                $veiculos_criticos[] = ['placa' => $v['placa'] ?? '', 'modelo' => $v['modelo'] ?? '', 'total_gasto' => round((float)($v['total_gasto'] ?? 0), 2), 'total_manutencoes' => (int)($v['total_manutencoes'] ?? 0), 'acima_media' => true];
            }
        }
    }
    foreach ($veiculos_top_manutencao as &$v) {
        $v['total_gasto'] = isset($v['total_gasto']) ? round((float)$v['total_gasto'], 2) : 0;
    }
    unset($v);
    
    // Consumo por veículo (KM/L): litros agregados em subquery derivada (evita subquery correlacionada por linha)
    $veiculos_consumo = [];
    try {
        if ($usar_periodo) {
            $litros_sub = "
                SELECT veiculo_id, COALESCE(SUM(litros), 0) AS litros_sum
                FROM abastecimentos
                WHERE empresa_id = :eid_litros
                AND status = 'aprovado'
                AND data_abastecimento >= :d_ini_ab AND data_abastecimento <= :d_fim_ab
                GROUP BY veiculo_id
            ";
            $cond_r_litros = " AND r.data_saida >= :d_ini_r AND r.data_saida <= :d_fim_r ";
        } else {
            $litros_sub = "
                SELECT veiculo_id, COALESCE(SUM(litros), 0) AS litros_sum
                FROM abastecimentos
                WHERE empresa_id = :eid_litros
                AND status = 'aprovado'
                AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY veiculo_id
            ";
            $cond_r_litros = " AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH) ";
        }
        $sql_consumo = "
            SELECT v.id, v.placa, v.modelo,
                COALESCE(SUM(r.distancia_km), 0) AS total_km,
                COALESCE(MAX(al.litros_sum), 0) AS total_litros
            FROM veiculos v
            LEFT JOIN rotas r ON r.veiculo_id = v.id {$cond_r_litros}
            LEFT JOIN ({$litros_sub}) al ON al.veiculo_id = v.id
            WHERE v.empresa_id = :eid_v
            GROUP BY v.id, v.placa, v.modelo
            ORDER BY total_km DESC
            LIMIT 20
        ";
        $stmt = $conn->prepare($sql_consumo);
        $stmt->bindValue(':eid_v', $empresa_id, PDO::PARAM_INT);
        $stmt->bindValue(':eid_litros', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) {
            $stmt->bindValue(':d_ini_ab', $data_inicio);
            $stmt->bindValue(':d_fim_ab', $data_fim);
            $stmt->bindValue(':d_ini_r', $data_inicio);
            $stmt->bindValue(':d_fim_r', $data_fim);
        }
        $stmt->execute();
        $consumo_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $frota_litros = 0;
        $frota_km = 0;
        foreach ($consumo_rows as $c) {
            $km = (float)($c['total_km'] ?? 0);
            $lit = (float)($c['total_litros'] ?? 0);
            $frota_km += $km;
            $frota_litros += $lit;
            $consumo_medio = $lit > 0 ? ($km / $lit) : 0;
            $veiculos_consumo[] = ['placa' => $c['placa'] ?? '', 'modelo' => $c['modelo'] ?? '', 'total_km' => $km, 'total_litros' => $lit, 'consumo_medio' => round($consumo_medio, 2)];
        }
        $consumo_medio_frota = $frota_litros > 0 ? ($frota_km / $frota_litros) : 0;
        foreach ($veiculos_consumo as &$vc) {
            $vc['consumo_medio_frota'] = round($consumo_medio_frota, 2);
            $vc['fora_media'] = $consumo_medio_frota > 0 && $vc['consumo_medio'] > 0 && $vc['consumo_medio'] < $consumo_medio_frota * 0.85;
        }
        unset($vc);
    } catch (Exception $e) { }
    
    // Filtrar período de retorno e montar comparação (vs mês anterior, vs mesmo mês ano anterior)
    if ($usar_periodo) {
        $mes_ref = $mes_int ? sprintf('%04d-%02d', $ano, $mes_int) : $ano . '-12';
        $resultado = [];
        foreach ($meses as $ma) {
            $y = (int)substr($ma, 0, 4);
            $m = (int)substr($ma, 5, 2);
            if ($mes_int) {
                if ($ma === $mes_ref) $resultado[] = $data_formatada[$ma];
            } else {
                if ($y === $ano) $resultado[] = $data_formatada[$ma];
            }
        }
        $resultado = array_values($resultado);
        $mes_anterior_key = null;
        $mesmo_mes_ano_anterior_key = null;
        if ($mes_int) {
            if ($mes_int > 1) {
                $mes_anterior_key = $ano . '-' . str_pad($mes_int - 1, 2, '0', STR_PAD_LEFT);
            } else {
                $mes_anterior_key = ($ano - 1) . '-12';
            }
            $mesmo_mes_ano_anterior_key = ($ano - 1) . '-' . str_pad($mes_int, 2, '0', STR_PAD_LEFT);
        } else {
            $mes_anterior_key = $ano . '-11';
            $mesmo_mes_ano_anterior_key = ($ano - 1) . '-12';
        }
        $comparacao = [
            'mes_anterior' => isset($data_formatada[$mes_anterior_key]) ? $data_formatada[$mes_anterior_key] : null,
            'mesmo_mes_ano_anterior' => isset($data_formatada[$mesmo_mes_ano_anterior_key]) ? $data_formatada[$mesmo_mes_ano_anterior_key] : null
        ];
    } else {
        $resultado = array_values($data_formatada);
        $comparacao = null;
    }

    // Dados incompletos no período (podem impactar resultados)
    $dados_incompletos = ['rotas_sem_despesas' => 0, 'abastecimentos_sem_litros' => 0, 'manutencoes_sem_tipo' => 0];
    try {
        $cond_incompl = $usar_periodo ? " AND r.data_saida >= :di_ini AND r.data_saida <= :di_fim " : " AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH) ";
        $sql_rotas_sem_desp = "SELECT COUNT(*) as c FROM rotas r WHERE r.empresa_id = :empresa_id AND r.data_saida IS NOT NULL " . $cond_incompl . " AND NOT EXISTS (SELECT 1 FROM despesas_viagem dv WHERE dv.rota_id = r.id)";
        $stmt = $conn->prepare($sql_rotas_sem_desp);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) { $stmt->bindValue(':di_ini', $data_inicio); $stmt->bindValue(':di_fim', $data_fim); }
        $stmt->execute();
        $dados_incompletos['rotas_sem_despesas'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { }
    try {
        $cond_ab = $usar_periodo ? " AND data_abastecimento >= :di_ini AND data_abastecimento <= :di_fim " : " AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH) ";
        $sql_abast_sem_litros = "SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = :empresa_id AND status = 'aprovado' " . $cond_ab . " AND (litros IS NULL OR COALESCE(litros, 0) = 0)";
        $stmt = $conn->prepare($sql_abast_sem_litros);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) { $stmt->bindValue(':di_ini', $data_inicio); $stmt->bindValue(':di_fim', $data_fim); }
        $stmt->execute();
        $dados_incompletos['abastecimentos_sem_litros'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { }
    try {
        $cond_m = $usar_periodo ? " AND data_manutencao >= :di_ini AND data_manutencao <= :di_fim " : " AND data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH) ";
        $sql_manut_sem_tipo = "SELECT COUNT(*) FROM manutencoes WHERE empresa_id = :empresa_id " . $cond_m . " AND tipo_manutencao_id IS NULL";
        $stmt = $conn->prepare($sql_manut_sem_tipo);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($usar_periodo) { $stmt->bindValue(':di_ini', $data_inicio); $stmt->bindValue(':di_fim', $data_fim); }
        $stmt->execute();
        $dados_incompletos['manutencoes_sem_tipo'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { }
    $dados_incompletos['tem_incompletos'] = ($dados_incompletos['rotas_sem_despesas'] > 0 || $dados_incompletos['abastecimentos_sem_litros'] > 0 || $dados_incompletos['manutencoes_sem_tipo'] > 0);

    // Ponto de equilíbrio (break-even) com base no último mês do período
    $ponto_equilibrio = null;
    if (count($resultado) > 0) {
        $ref_pe = $resultado[count($resultado) - 1];
        $custo_total_pe = (float)($ref_pe['total_gasto_abastecimentos'] ?? 0) + (float)($ref_pe['total_manutencoes'] ?? 0) + (float)($ref_pe['total_despesas_viagem'] ?? 0) + (float)($ref_pe['total_despesas_fixas'] ?? 0);
        $total_rotas_pe = (int)($ref_pe['total_rotas'] ?? 0);
        $total_frete_pe = (float)($ref_pe['total_frete'] ?? 0);
        $ticket_medio_pe = $total_rotas_pe > 0 ? ($total_frete_pe / $total_rotas_pe) : 0;
        $rotas_empatar = ($ticket_medio_pe > 0 && $custo_total_pe > 0) ? (int)ceil($custo_total_pe / $ticket_medio_pe) : 0;
        $ponto_equilibrio = [
            'custo_total_ref' => round($custo_total_pe, 2),
            'faturamento_minimo' => round($custo_total_pe, 2),
            'rotas_para_empatar' => $rotas_empatar,
            'ticket_medio_ref' => round($ticket_medio_pe, 2),
            'mes_ref' => $ref_pe['mes_nome'] ?? $ref_pe['mes_ano'] ?? ''
        ];
    }

    $mes_atual = date('Y-m');
    $dados_mes_atual = isset($data_formatada[$mes_atual]) ? $data_formatada[$mes_atual] : [
        'mes_ano' => $mes_atual,
        'mes_nome' => ($meses_pt[(int)date('n')] ?? '') . '/' . date('Y'),
        'total_abastecimentos' => 0,
        'total_gasto_abastecimentos' => 0,
        'total_rotas' => 0,
        'total_km_rodados' => 0,
        'total_frete' => 0,
        'total_comissao' => 0,
        'lucro_operacional' => 0,
        'total_despesas_viagem' => 0,
        'total_manutencoes' => 0,
        'total_despesas_fixas' => 0,
        'quantidade_veiculos_ativos' => 0,
        'total_litros' => 0
    ];
    
    // Estimativa próximo mês (média dos últimos 6 meses de manutenção) – útil na visão Manutenção
    $previsao_proximo_mes = null;
    if (count($resultado) > 0) {
        $ultimos6 = array_slice($resultado, -6);
        $soma = 0;
        foreach ($ultimos6 as $r) { $soma += (float)($r['total_manutencoes'] ?? 0); }
        $previsao_proximo_mes = round($soma / count($ultimos6), 2);
    }

    $output = [
        'success' => true,
        'visao' => $visao,
        'data' => [
            'historico_mensal' => $resultado,
            'comparacao' => $comparacao,
            'mes_atual' => $dados_mes_atual,
            'veiculos_top' => $veiculos_top,
            'veiculos_top_abastecimento' => $veiculos_top_abastecimento,
            'veiculos_top_manutencao' => $veiculos_top_manutencao,
            'ranking_rotas' => $ranking_rotas,
            'despesas_viagem_tipos' => $despesas_viagem_tipos,
            'manut_preventiva_corretiva' => $manut_preventiva_corretiva,
            'veiculos_criticos' => $veiculos_criticos,
            'veiculos_consumo' => $veiculos_consumo,
            'labels' => array_column($resultado, 'mes_nome'),
            'dados_incompletos' => $dados_incompletos,
            'ponto_equilibrio' => $ponto_equilibrio,
            'previsao_proximo_mes' => $previsao_proximo_mes
        ]
    ];
    $payloadStr = json_encode($output);
    if (!$nocache && $payloadStr !== false) {
        try {
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $conn->prepare("INSERT INTO bi_cache (empresa_id, cache_key, payload, expires_at) VALUES (:eid, :ck, :pl, :ex) ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)");
            $stmt->execute(['eid' => $empresa_id, 'ck' => $cache_key, 'pl' => $payloadStr, 'ex' => $expires]);
        } catch (Exception $e) {
            // ignora falha ao gravar cache
        }
    }
    echo $payloadStr;
    
} catch (Exception $e) {
    error_log("Erro ao buscar indicadores de desempenho: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}

