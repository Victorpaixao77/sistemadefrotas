<?php
/**
 * Avaliação / Ranking do Motorista - App Android.
 * GET: pontuação do motorista no período (padrão 30 dias) a partir de dados já existentes
 *      (multas, ocorrências, excessos de velocidade, consumo) + posição no ranking da empresa.
 * Params: dias (padrão 30, máx 180), limite (km/h p/ excesso, padrão 90).
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

/**
 * Pontuação 0-100 por penalidades (mesma fórmula usada no ranking).
 */
function desempenho_score(int $multas, int $pontos, int $ocorrencias, float $excessoRatio): int
{
    $s = 100.0;
    $s -= $multas * 8;
    $s -= $pontos * 2;
    $s -= $ocorrencias * 6;
    $s -= $excessoRatio * 30;
    return (int) max(0, min(100, round($s)));
}

try {
    $conn = getConnection();
    $dias = min(180, max(1, (int) ($_GET['dias'] ?? 30)));
    $limite = (float) ($_GET['limite'] ?? 90);
    if ($limite < 40 || $limite > 130) {
        $limite = 90;
    }

    // ---- métricas do motorista ----
    $m = $conn->prepare('SELECT COUNT(*) c, COALESCE(SUM(pontos),0) p FROM multas WHERE empresa_id=:e AND motorista_id=:m AND data_infracao >= DATE_SUB(CURDATE(), INTERVAL :d DAY)');
    $m->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $m->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $m->bindValue(':d', $dias, PDO::PARAM_INT);
    $m->execute();
    $mr = $m->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 'p' => 0];
    $multas = (int) $mr['c'];
    $pontos = (int) $mr['p'];

    $o = $conn->prepare('SELECT COUNT(*) c FROM ocorrencias_viagem WHERE empresa_id=:e AND motorista_id=:m AND data_hora >= DATE_SUB(NOW(), INTERVAL :d DAY)');
    $o->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $o->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $o->bindValue(':d', $dias, PDO::PARAM_INT);
    $o->execute();
    $ocorrencias = (int) ($o->fetchColumn() ?: 0);

    // Velocidade: proporção de pontos em excesso.
    $v = $conn->prepare('SELECT COUNT(*) total, SUM(CASE WHEN velocidade > :lim THEN 1 ELSE 0 END) exc FROM gps_logs WHERE empresa_id=:e AND motorista_id=:m AND velocidade IS NOT NULL AND data_hora >= DATE_SUB(NOW(), INTERVAL :d DAY)');
    $v->bindValue(':lim', $limite);
    $v->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $v->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $v->bindValue(':d', $dias, PDO::PARAM_INT);
    $v->execute();
    $vr = $v->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'exc' => 0];
    $vTotal = (int) $vr['total'];
    $vExc = (int) $vr['exc'];
    $excessoRatio = $vTotal > 0 ? $vExc / $vTotal : 0.0;

    // Consumo médio (km/l) no período: (km máx - km mín) / litros, a partir dos abastecimentos.
    $a = $conn->prepare('SELECT COUNT(*) n, COALESCE(SUM(litros),0) litros, COALESCE(MAX(km_atual),0) kmax, COALESCE(MIN(km_atual),0) kmin FROM abastecimentos WHERE empresa_id=:e AND motorista_id=:m AND km_atual > 0 AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL :d DAY)');
    $a->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $a->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $a->bindValue(':d', $dias, PDO::PARAM_INT);
    $a->execute();
    $ar = $a->fetch(PDO::FETCH_ASSOC) ?: [];
    $nAbast = (int) ($ar['n'] ?? 0);
    $litros = (float) ($ar['litros'] ?? 0);
    $kmRodados = max(0, (float) ($ar['kmax'] ?? 0) - (float) ($ar['kmin'] ?? 0));
    $consumo = ($nAbast >= 2 && $litros > 0 && $kmRodados > 0) ? round($kmRodados / $litros, 2) : null;

    $score = desempenho_score($multas, $pontos, $ocorrencias, $excessoRatio);
    if ($score >= 90) {
        $classificacao = 'Excelente';
    } elseif ($score >= 75) {
        $classificacao = 'Bom';
    } elseif ($score >= 50) {
        $classificacao = 'Regular';
    } else {
        $classificacao = 'Atenção';
    }

    // ---- ranking da empresa ----
    // Métricas por motorista (multas/pontos, ocorrências, excessos) em uma passada cada.
    $scoreByMot = [];
    $ensure = function (&$arr, $id) {
        if (!isset($arr[$id])) {
            $arr[$id] = ['multas' => 0, 'pontos' => 0, 'ocor' => 0, 'vt' => 0, 've' => 0];
        }
    };

    $qm = $conn->prepare('SELECT motorista_id, COUNT(*) c, COALESCE(SUM(pontos),0) p FROM multas WHERE empresa_id=:e AND data_infracao >= DATE_SUB(CURDATE(), INTERVAL :d DAY) GROUP BY motorista_id');
    $qm->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $qm->bindValue(':d', $dias, PDO::PARAM_INT);
    $qm->execute();
    foreach ($qm->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['motorista_id'];
        $ensure($scoreByMot, $id);
        $scoreByMot[$id]['multas'] = (int) $r['c'];
        $scoreByMot[$id]['pontos'] = (int) $r['p'];
    }

    $qo = $conn->prepare('SELECT motorista_id, COUNT(*) c FROM ocorrencias_viagem WHERE empresa_id=:e AND data_hora >= DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY motorista_id');
    $qo->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $qo->bindValue(':d', $dias, PDO::PARAM_INT);
    $qo->execute();
    foreach ($qo->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['motorista_id'];
        $ensure($scoreByMot, $id);
        $scoreByMot[$id]['ocor'] = (int) $r['c'];
    }

    $qv = $conn->prepare('SELECT motorista_id, COUNT(*) total, SUM(CASE WHEN velocidade > :lim THEN 1 ELSE 0 END) exc FROM gps_logs WHERE empresa_id=:e AND velocidade IS NOT NULL AND data_hora >= DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY motorista_id');
    $qv->bindValue(':lim', $limite);
    $qv->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $qv->bindValue(':d', $dias, PDO::PARAM_INT);
    $qv->execute();
    foreach ($qv->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['motorista_id'];
        $ensure($scoreByMot, $id);
        $scoreByMot[$id]['vt'] = (int) $r['total'];
        $scoreByMot[$id]['ve'] = (int) $r['exc'];
    }

    // Garante que o próprio motorista entra no ranking mesmo sem eventos.
    $ensure($scoreByMot, (int) $motorista_id);

    $scores = [];
    foreach ($scoreByMot as $id => $x) {
        $ratio = $x['vt'] > 0 ? $x['ve'] / $x['vt'] : 0.0;
        $scores[$id] = desempenho_score($x['multas'], $x['pontos'], $x['ocor'], $ratio);
    }
    arsort($scores);
    $posicao = 0;
    $i = 0;
    foreach (array_keys($scores) as $id) {
        $i++;
        if ($id === (int) $motorista_id) {
            $posicao = $i;
            break;
        }
    }
    $totalMot = count($scores);

    api_success([
        'dias' => $dias,
        'score' => $score,
        'classificacao' => $classificacao,
        'ranking_posicao' => $posicao,
        'ranking_total' => $totalMot,
        'metricas' => [
            'multas' => $multas,
            'pontos_cnh' => $pontos,
            'ocorrencias' => $ocorrencias,
            'excessos_velocidade' => $vExc,
            'pct_excesso' => $vTotal > 0 ? round($excessoRatio * 100, 1) : 0,
            'consumo_km_l' => $consumo,
            'km_rodados' => round($kmRodados, 0),
            'abastecimentos' => $nAbast,
        ],
    ]);
} catch (PDOException $e) {
    error_log('desempenho.php: ' . $e->getMessage());
    api_error('Erro ao calcular desempenho.', 500);
}
