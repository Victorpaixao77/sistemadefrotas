<?php
/**
 * Backend da "Gestão Interativa Pro" de pneus.
 *
 * Reaproveita as tabelas existentes (pneus, pneu_movimentacoes, pneu_medicoes_sulco,
 * eixos_veiculos, alocacoes_pneus_flexiveis, status_pneus, posicoes_pneus) e a nova
 * pneu_calibragens. O layout do veículo e mover/remover continuam no endpoint
 * existente gestao_interativa/api/eixos_veiculos.php.
 *
 * GET  action=vehicles                      -> veículos da empresa
 * GET  action=fleet_kpis                    -> indicadores agregados (frota)
 * GET  action=tire_detail&pneu_id=          -> dados completos do pneu (painel)
 * GET  action=history&pneu_id=              -> timeline (movimentações + sulco + calibragem)
 * GET  action=alertas&veiculo_id=           -> alertas inteligentes do veículo
 * POST action=registrar_sulco (JSON)        -> grava medição de sulco
 * POST action=registrar_calibragem (JSON)   -> grava calibragem
 */
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
if (file_exists(__DIR__ . '/../includes/pneu_movimentacoes_helper.php')) {
    require_once __DIR__ . '/../includes/pneu_movimentacoes_helper.php';
}

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
$usuario_id = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null;

// Libera o lock de sessão imediatamente: só LEMOS a sessão (nunca escrevemos), e o
// $_SESSION continua disponível para leitura (CSRF). Sem isso, as várias chamadas da
// página serializam (uma espera a outra), deixando o carregamento lento.
if (function_exists('session_write_close')) {
    session_write_close();
}

$conn = getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/** Limite mínimo de sulco (mm) e dias para "sem medição recente". */
const GP_SULCO_CRITICO = 3.0;
const GP_SULCO_ATENCAO = 5.0;
const GP_DIAS_SEM_MEDICAO = 60;
const GP_RODIZIO_DIFF = 2.0;   // diferença de sulco (mm) que justifica sugerir rodízio
const GP_DOT_ANOS_ALERTA = 6;  // idade (anos) do pneu (via DOT) que dispara alerta

/** Idade do pneu em anos a partir do DOT (formato WWYY, ex.: 3621 = semana 36 de 2021). null se não der pra ler. */
function gp_dot_idade_anos(?string $dot): ?float
{
    if ($dot === null) return null;
    $d = preg_replace('/\D/', '', $dot);
    if ($d === null || strlen($d) < 4) return null;
    $wwyy = substr($d, -4);
    $semana = (int) substr($wwyy, 0, 2);
    $ano2 = (int) substr($wwyy, 2, 2);
    if ($semana < 1 || $semana > 53) return null;
    $ano = ($ano2 <= ((int) date('y'))) ? 2000 + $ano2 : 1900 + $ano2;
    try {
        $fab = new DateTime();
        $fab->setISODate($ano, max(1, $semana));
        $diff = (new DateTime())->getTimestamp() - $fab->getTimestamp();
        if ($diff < 0) return null;
        return round($diff / (365.25 * 86400), 1);
    } catch (Throwable $e) { return null; }
}

/** Verifica se uma coluna existe (defensivo contra variações de schema). Cacheado por requisição. */
function gp_col_exists(PDO $conn, string $table, string $col): bool
{
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $st = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $st->execute([$table, $col]);
        return $cache[$key] = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

/** Classifica a saúde do pneu a partir do sulco atual e status. */
function gp_health(?float $sulco, ?int $statusId, ?int $recapagens): array
{
    // Posição sem pneu é tratada no front (cinza). Aqui sempre há pneu.
    if ($statusId === 3) return ['nivel' => 'descartado', 'cor' => '#64748b', 'rotulo' => 'Descartado'];
    if ($statusId === 4) return ['nivel' => 'recapagem', 'cor' => '#3b82f6', 'rotulo' => 'Recapagem'];
    if ($sulco !== null) {
        if ($sulco <= GP_SULCO_CRITICO) return ['nivel' => 'critico', 'cor' => '#ef4444', 'rotulo' => 'Crítico'];
        if ($sulco <= GP_SULCO_ATENCAO) return ['nivel' => 'atencao', 'cor' => '#f59e0b', 'rotulo' => 'Atenção'];
    }
    if ((int) $statusId === 5) return ['nivel' => 'novo', 'cor' => '#22c55e', 'rotulo' => 'Novo'];
    return ['nivel' => 'bom', 'cor' => '#22c55e', 'rotulo' => 'Bom'];
}

try {
    switch ($action) {

        case 'vehicles': {
            // tem_montagem: selo de montagem personalizada salva.
            $hasMont = false;
            try {
                $hasMont = (bool) $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gestao_montagem' LIMIT 1")->fetchColumn();
            } catch (Throwable $e) { $hasMont = false; }
            if ($hasMont) {
                $sql = "SELECT v.id, v.placa, v.modelo, v.marca,
                            (SELECT 1 FROM gestao_montagem gm WHERE gm.veiculo_id = v.id AND gm.empresa_id = :e2 LIMIT 1) AS tem_montagem
                        FROM veiculos v WHERE v.empresa_id = :e ORDER BY v.placa";
                $st = $conn->prepare($sql);
                $st->execute([':e' => $empresa_id, ':e2' => $empresa_id]);
            } else {
                $st = $conn->prepare("SELECT id, placa, modelo, marca, NULL AS tem_montagem FROM veiculos WHERE empresa_id = :e ORDER BY placa");
                $st->execute([':e' => $empresa_id]);
            }
            api_json_send(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'fleet_kpis': {
            // Último sulco por pneu (subconsulta) para classificar alerta/crítico/sem-medição.
            $sql = "
                SELECT
                    p.id, p.status_id, p.numero_recapagens, p.valor_compra, p.km_rodado_total,
                    (SELECT s.sulco_mm FROM pneu_medicoes_sulco s
                       WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco_atual,
                    (SELECT s.data_medicao FROM pneu_medicoes_sulco s
                       WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS ult_medicao
                FROM pneus p
                WHERE p.empresa_id = :e";
            $st = $conn->prepare($sql);
            $st->execute([':e' => $empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $total = count($rows);
            $emUso = $alerta = $critico = $semMedicao = 0;
            $somaVida = 0; $somaCustoCompra = 0.0; $somaKm = 0;
            $limite = (new DateTime())->modify('-' . GP_DIAS_SEM_MEDICAO . ' days');
            foreach ($rows as $r) {
                $sid = (int) $r['status_id'];
                if ($sid === 1) $emUso++;
                $somaVida += ((int) $r['numero_recapagens']) + 1;
                $somaCustoCompra += (float) ($r['valor_compra'] ?? 0);
                $somaKm += (int) ($r['km_rodado_total'] ?? 0);
                $sulco = $r['sulco_atual'] !== null ? (float) $r['sulco_atual'] : null;
                if ($sid !== 3) { // descartado não conta como alerta operacional
                    if ($sulco !== null && $sulco <= GP_SULCO_CRITICO) $critico++;
                    elseif ($sulco !== null && $sulco <= GP_SULCO_ATENCAO) $alerta++;
                }
                $um = $r['ult_medicao'] ? new DateTime($r['ult_medicao']) : null;
                if ($um === null || $um < $limite) $semMedicao++;
            }

            // Custo de recapagem/manutenção (soma das movimentações).
            $stc = $conn->prepare("SELECT COALESCE(SUM(custo),0) FROM pneu_movimentacoes WHERE empresa_id = :e AND tipo IN ('recapagem','manutencao')");
            $stc->execute([':e' => $empresa_id]);
            $custoMov = (float) $stc->fetchColumn();
            $custoTotal = $somaCustoCompra + $custoMov;
            $custoKm = $somaKm > 0 ? $custoTotal / $somaKm : 0.0;

            api_json_send(['success' => true, 'data' => [
                'total' => $total,
                'em_uso' => $emUso,
                'em_alerta' => $alerta,
                'criticos' => $critico,
                'vida_media' => $total > 0 ? round($somaVida / $total, 1) : 0,
                'custo_total' => round($custoTotal, 2),
                'custo_km' => round($custoKm, 4),
                'sem_medicao' => $semMedicao,
                'escopo' => 'frota',
            ]]);
            break;
        }

        case 'vehicle_kpis': {
            // KPIs apenas dos pneus instalados no veículo selecionado.
            $veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
            if (!$veiculoId) api_json_error('veiculo_id obrigatório.', 422);
            $sql = "SELECT p.id, p.status_id, p.numero_recapagens, p.valor_compra, p.km_rodado_total,
                        (SELECT s.sulco_mm FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco_atual,
                        (SELECT s.data_medicao FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS ult_medicao
                    FROM pneus p
                    JOIN alocacoes_pneus_flexiveis apf ON apf.pneu_id = p.id AND apf.ativo = 1 AND apf.empresa_id = :e2
                    JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id AND ev.veiculo_id = :v
                    WHERE p.empresa_id = :e";
            $st = $conn->prepare($sql);
            $st->execute([':e' => $empresa_id, ':e2' => $empresa_id, ':v' => $veiculoId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $total = count($rows);
            $emUso = $alerta = $critico = $semMedicao = 0;
            $somaVida = 0; $somaCustoCompra = 0.0; $somaKm = 0; $ids = [];
            $limite = (new DateTime())->modify('-' . GP_DIAS_SEM_MEDICAO . ' days');
            foreach ($rows as $r) {
                $ids[] = (int) $r['id'];
                $sid = (int) $r['status_id'];
                if ($sid === 1) $emUso++;
                $somaVida += ((int) $r['numero_recapagens']) + 1;
                $somaCustoCompra += (float) ($r['valor_compra'] ?? 0);
                $somaKm += (int) ($r['km_rodado_total'] ?? 0);
                $sulco = $r['sulco_atual'] !== null ? (float) $r['sulco_atual'] : null;
                if ($sid !== 3) {
                    if ($sulco !== null && $sulco <= GP_SULCO_CRITICO) $critico++;
                    elseif ($sulco !== null && $sulco <= GP_SULCO_ATENCAO) $alerta++;
                }
                $um = $r['ult_medicao'] ? new DateTime($r['ult_medicao']) : null;
                if ($um === null || $um < $limite) $semMedicao++;
            }
            $custoMov = 0.0;
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $stc = $conn->prepare("SELECT COALESCE(SUM(custo),0) FROM pneu_movimentacoes WHERE empresa_id = ? AND tipo IN ('recapagem','manutencao') AND pneu_id IN ($in)");
                $stc->execute(array_merge([$empresa_id], $ids));
                $custoMov = (float) $stc->fetchColumn();
            }
            $custoTotal = $somaCustoCompra + $custoMov;
            $custoKm = $somaKm > 0 ? $custoTotal / $somaKm : 0.0;

            api_json_send(['success' => true, 'data' => [
                'total' => $total,
                'em_uso' => $emUso,
                'em_alerta' => $alerta,
                'criticos' => $critico,
                'vida_media' => $total > 0 ? round($somaVida / $total, 1) : 0,
                'custo_total' => round($custoTotal, 2),
                'custo_km' => round($custoKm, 4),
                'sem_medicao' => $semMedicao,
                'escopo' => 'veiculo',
            ]]);
            break;
        }

        case 'tire_detail': {
            $pneuId = (int) ($_GET['pneu_id'] ?? 0);
            if (!$pneuId) api_json_error('pneu_id obrigatório.', 422);

            $st = $conn->prepare("
                SELECT p.*, sp.nome AS status_nome
                FROM pneus p
                LEFT JOIN status_pneus sp ON p.status_id = sp.id
                WHERE p.id = :id AND p.empresa_id = :e LIMIT 1");
            $st->execute([':id' => $pneuId, ':e' => $empresa_id]);
            $p = $st->fetch(PDO::FETCH_ASSOC);
            if (!$p) api_json_error('Pneu não encontrado.', 404);

            // Último sulco e última calibragem
            $ss = $conn->prepare("SELECT sulco_mm, data_medicao FROM pneu_medicoes_sulco WHERE pneu_id = :id ORDER BY data_medicao DESC LIMIT 1");
            $ss->execute([':id' => $pneuId]);
            $ultSulco = $ss->fetch(PDO::FETCH_ASSOC) ?: null;

            $sc = $conn->prepare("SELECT pressao_recomendada, pressao_aferida, diferenca, data_calibragem FROM pneu_calibragens WHERE pneu_id = :id ORDER BY data_calibragem DESC LIMIT 1");
            $sc->execute([':id' => $pneuId]);
            $ultCalib = $sc->fetch(PDO::FETCH_ASSOC) ?: null;

            // Custo total (compra + recapagem/manutenção) e custo/km
            $cm = $conn->prepare("SELECT COALESCE(SUM(custo),0) FROM pneu_movimentacoes WHERE pneu_id = :id AND tipo IN ('recapagem','manutencao')");
            $cm->execute([':id' => $pneuId]);
            $custoMov = (float) $cm->fetchColumn();
            $custoTotal = (float) ($p['valor_compra'] ?? 0) + $custoMov;
            $km = (int) ($p['km_rodado_total'] ?? 0);
            $custoKm = $km > 0 ? $custoTotal / $km : 0.0;

            // Posição/veículo atual (defensivo: posicao_id pode não existir)
            $veiculoId = null; $posicaoNome = null;
            try {
                $hasPos = gp_col_exists($conn, 'alocacoes_pneus_flexiveis', 'posicao_id');
                $sqlPos = "SELECT ev.veiculo_id" . ($hasPos ? ", pp.nome AS posicao_nome" : "") . "
                           FROM alocacoes_pneus_flexiveis apf
                           JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id" .
                           ($hasPos ? " LEFT JOIN posicoes_pneus pp ON apf.posicao_id = pp.id" : "") . "
                           WHERE apf.pneu_id = :id AND apf.empresa_id = :e AND apf.ativo = 1 LIMIT 1";
                $sp = $conn->prepare($sqlPos);
                $sp->execute([':id' => $pneuId, ':e' => $empresa_id]);
                if ($rowp = $sp->fetch(PDO::FETCH_ASSOC)) {
                    $veiculoId = $rowp['veiculo_id'] ?? null;
                    $posicaoNome = $rowp['posicao_nome'] ?? null;
                }
            } catch (Throwable $e) { /* sem alocação ativa */ }

            $sulcoAtual = $ultSulco ? (float) $ultSulco['sulco_mm'] : null;
            $health = gp_health($sulcoAtual, (int) $p['status_id'], (int) $p['numero_recapagens']);
            $proxInspecao = null;
            if ($ultSulco) {
                $proxInspecao = (new DateTime($ultSulco['data_medicao']))->modify('+30 days')->format('Y-m-d');
            }

            api_json_send(['success' => true, 'data' => [
                'id' => (int) $p['id'],
                'numero_serie' => $p['numero_serie'],
                'marca' => $p['marca'],
                'modelo' => $p['modelo'],
                'medida' => $p['medida'],
                'dot' => $p['dot'],
                'dot_idade_anos' => gp_dot_idade_anos($p['dot'] ?? null),
                'qr_token' => $p['qr_token'] ?? null,
                'status_id' => (int) $p['status_id'],
                'status_nome' => $p['status_nome'],
                'vida' => ((int) $p['numero_recapagens']) + 1,
                'numero_recapagens' => (int) $p['numero_recapagens'],
                'sulco_inicial' => $p['sulco_inicial'] !== null ? (float) $p['sulco_inicial'] : null,
                'sulco_atual' => $sulcoAtual,
                'sulco_data' => $ultSulco['data_medicao'] ?? null,
                'pressao_recomendada' => $ultCalib['pressao_recomendada'] ?? null,
                'pressao_aferida' => $ultCalib['pressao_aferida'] ?? null,
                'pressao_diferenca' => $ultCalib['diferenca'] ?? null,
                'calibragem_data' => $ultCalib['data_calibragem'] ?? null,
                'km_acumulado' => $km,
                'km_instalacao' => $p['km_instalacao'] !== null ? (int) $p['km_instalacao'] : null,
                'data_instalacao' => $p['data_instalacao'] ?? null,
                'valor_compra' => $p['valor_compra'] !== null ? (float) $p['valor_compra'] : null,
                'custo_total' => round($custoTotal, 2),
                'custo_km' => round($custoKm, 4),
                'vida_util_km' => $p['vida_util_km'] !== null ? (int) $p['vida_util_km'] : null,
                'veiculo_id' => $veiculoId !== null ? (int) $veiculoId : null,
                'posicao_nome' => $posicaoNome,
                'health' => $health,
                'proxima_inspecao' => $proxInspecao,
            ]]);
            break;
        }

        case 'history': {
            $pneuId = (int) ($_GET['pneu_id'] ?? 0);
            if (!$pneuId) api_json_error('pneu_id obrigatório.', 422);
            $eventos = [];

            $sm = $conn->prepare("SELECT tipo, km_odometro, sulco_mm, custo, observacoes, data_movimentacao FROM pneu_movimentacoes WHERE pneu_id = :id AND empresa_id = :e ORDER BY data_movimentacao DESC LIMIT 100");
            $sm->execute([':id' => $pneuId, ':e' => $empresa_id]);
            foreach ($sm->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $eventos[] = [
                    'grupo' => 'movimentacao', 'tipo' => $m['tipo'], 'data' => $m['data_movimentacao'],
                    'km' => $m['km_odometro'], 'sulco' => $m['sulco_mm'], 'custo' => $m['custo'],
                    'obs' => $m['observacoes'],
                ];
            }
            $ss = $conn->prepare("SELECT sulco_mm, km_veiculo, observacoes, data_medicao FROM pneu_medicoes_sulco WHERE pneu_id = :id ORDER BY data_medicao DESC LIMIT 100");
            $ss->execute([':id' => $pneuId]);
            foreach ($ss->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $eventos[] = ['grupo' => 'sulco', 'tipo' => 'medicao_sulco', 'data' => $s['data_medicao'], 'km' => $s['km_veiculo'], 'sulco' => $s['sulco_mm'], 'custo' => null, 'obs' => $s['observacoes']];
            }
            $sc = $conn->prepare("SELECT pressao_aferida, pressao_recomendada, km_veiculo, observacoes, data_calibragem FROM pneu_calibragens WHERE pneu_id = :id AND empresa_id = :e ORDER BY data_calibragem DESC LIMIT 100");
            $sc->execute([':id' => $pneuId, ':e' => $empresa_id]);
            foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $eventos[] = ['grupo' => 'calibragem', 'tipo' => 'calibragem', 'data' => $c['data_calibragem'], 'km' => $c['km_veiculo'], 'sulco' => null, 'custo' => null, 'obs' => 'Aferida ' . $c['pressao_aferida'] . ' psi / Recomendada ' . ($c['pressao_recomendada'] ?? '—') . ' psi. ' . ($c['observacoes'] ?? '')];
            }
            usort($eventos, function ($a, $b) { return strcmp($b['data'] ?? '', $a['data'] ?? ''); });
            api_json_send(['success' => true, 'data' => array_slice($eventos, 0, 60)]);
            break;
        }

        case 'tires_health': {
            // Saúde de todos os pneus alocados do veículo (para colorir o estágio numa só chamada).
            $veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
            if (!$veiculoId) api_json_error('veiculo_id obrigatório.', 422);
            $sql = "SELECT p.id, p.status_id, p.numero_recapagens, p.sulco_inicial,
                        (SELECT s.sulco_mm FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco_atual,
                        (SELECT s.data_medicao FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS ult_medicao,
                        (SELECT c.data_calibragem FROM pneu_calibragens c WHERE c.pneu_id = p.id ORDER BY c.data_calibragem DESC LIMIT 1) AS ult_calib
                    FROM pneus p
                    JOIN alocacoes_pneus_flexiveis apf ON apf.pneu_id = p.id AND apf.ativo = 1 AND apf.empresa_id = :e2
                    JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id AND ev.veiculo_id = :v
                    WHERE p.empresa_id = :e";
            $st = $conn->prepare($sql);
            $st->execute([':e' => $empresa_id, ':e2' => $empresa_id, ':v' => $veiculoId]);
            $map = [];
            $limMed = (new DateTime())->modify('-' . GP_DIAS_SEM_MEDICAO . ' days');
            $limCal = (new DateTime())->modify('-90 days');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sulco = $r['sulco_atual'] !== null ? (float) $r['sulco_atual'] : null;
                $ini = ($r['sulco_inicial'] !== null && (float) $r['sulco_inicial'] > 0) ? (float) $r['sulco_inicial'] : null;
                $pct = ($sulco !== null && $ini !== null) ? max(0, min(100, round(($sulco / $ini) * 100))) : null;
                $um = $r['ult_medicao'] ? new DateTime($r['ult_medicao']) : null;
                $uc = $r['ult_calib'] ? new DateTime($r['ult_calib']) : null;
                $map[(int) $r['id']] = [
                    'sulco_atual' => $sulco,
                    'sulco_inicial' => $ini,
                    'pct' => $pct,
                    'vida' => ((int) $r['numero_recapagens']) + 1,
                    'sem_medicao' => ($um === null || $um < $limMed),
                    'sem_calibragem' => ($uc === null || $uc < $limCal),
                    'health' => gp_health($sulco, (int) $r['status_id'], (int) $r['numero_recapagens']),
                ];
            }
            api_json_send(['success' => true, 'data' => $map]);
            break;
        }

        case 'alertas': {
            $veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
            // Pneus do veículo (via alocação ativa). Se não vier veiculo_id, alerta da frota.
            $hasPos = gp_col_exists($conn, 'alocacoes_pneus_flexiveis', 'posicao_id');
            $params = [':e' => $empresa_id];
            $where = "p.empresa_id = :e";
            $join = "";
            if ($veiculoId) {
                $join = "JOIN alocacoes_pneus_flexiveis apf ON apf.pneu_id = p.id AND apf.ativo = 1 AND apf.empresa_id = :e2
                         JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id AND ev.veiculo_id = :v";
                $params[':v'] = $veiculoId;
                $params[':e2'] = $empresa_id;
            }
            $sql = "SELECT p.id, p.numero_serie, p.status_id, p.numero_recapagens, p.km_rodado_total, p.valor_compra, p.dot,
                        (SELECT s.sulco_mm FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco_atual,
                        (SELECT s.data_medicao FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS ult_medicao,
                        (SELECT c.data_calibragem FROM pneu_calibragens c WHERE c.pneu_id = p.id ORDER BY c.data_calibragem DESC LIMIT 1) AS ult_calib
                    FROM pneus p $join WHERE $where";
            $st = $conn->prepare($sql);
            $st->execute($params);
            $alertas = [];
            $limiteMed = (new DateTime())->modify('-' . GP_DIAS_SEM_MEDICAO . ' days');
            $limiteCal = (new DateTime())->modify('-90 days');
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ((int) $r['status_id'] === 3) continue;
                $sn = $r['numero_serie'] ?: ('#' . $r['id']);
                $sulco = $r['sulco_atual'] !== null ? (float) $r['sulco_atual'] : null;
                if ($sulco !== null && $sulco <= GP_SULCO_CRITICO) {
                    $alertas[] = ['nivel' => 'critico', 'pneu_id' => (int) $r['id'], 'titulo' => "Sulco crítico ($sulco mm)", 'desc' => "Pneu $sn abaixo do limite mínimo. Programar troca/recapagem."];
                } elseif ($sulco !== null && $sulco <= GP_SULCO_ATENCAO) {
                    $alertas[] = ['nivel' => 'atencao', 'pneu_id' => (int) $r['id'], 'titulo' => "Sulco em atenção ($sulco mm)", 'desc' => "Pneu $sn aproximando do limite. Acompanhar."];
                }
                $um = $r['ult_medicao'] ? new DateTime($r['ult_medicao']) : null;
                if ($um === null || $um < $limiteMed) {
                    $alertas[] = ['nivel' => 'atencao', 'pneu_id' => (int) $r['id'], 'titulo' => 'Sem medição recente', 'desc' => "Pneu $sn sem medição de sulco há mais de " . GP_DIAS_SEM_MEDICAO . " dias."];
                }
                $uc = $r['ult_calib'] ? new DateTime($r['ult_calib']) : null;
                if ($uc === null || $uc < $limiteCal) {
                    $alertas[] = ['nivel' => 'info', 'pneu_id' => (int) $r['id'], 'titulo' => 'Calibragem atrasada', 'desc' => "Pneu $sn sem calibragem registrada nos últimos 90 dias."];
                }
                $idadeDot = gp_dot_idade_anos($r['dot'] ?? null);
                if ($idadeDot !== null && $idadeDot >= GP_DOT_ANOS_ALERTA) {
                    $alertas[] = ['nivel' => 'atencao', 'pneu_id' => (int) $r['id'], 'titulo' => 'Pneu antigo (DOT)', 'desc' => "Pneu $sn com cerca de {$idadeDot} anos de fabricação (DOT {$r['dot']}). Avaliar troca por idade."];
                }
            }
            // Ordena por severidade
            $ordem = ['critico' => 0, 'atencao' => 1, 'info' => 2];
            usort($alertas, function ($a, $b) use ($ordem) { return ($ordem[$a['nivel']] ?? 9) <=> ($ordem[$b['nivel']] ?? 9); });
            api_json_send(['success' => true, 'data' => $alertas]);
            break;
        }

        case 'rodizio': {
            // Sugestão de rodízio: equilibra o desgaste trocando pneus mais gastos pelos mais novos.
            $veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
            if (!$veiculoId) api_json_error('veiculo_id obrigatório.', 422);
            $sql = "SELECT p.id, p.numero_serie, apf.slot_id, ev.tipo_veiculo, ev.numero_eixo,
                        (SELECT s.sulco_mm FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco_atual
                    FROM pneus p
                    JOIN alocacoes_pneus_flexiveis apf ON apf.pneu_id = p.id AND apf.ativo = 1 AND apf.empresa_id = :e2
                    JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id AND ev.veiculo_id = :v
                    WHERE p.empresa_id = :e AND p.status_id <> 3";
            $st = $conn->prepare($sql);
            $st->execute([':e' => $empresa_id, ':e2' => $empresa_id, ':v' => $veiculoId]);
            $tires = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['sulco_atual'] === null) continue; // sem medição não entra no rodízio
                $tires[] = [
                    'pneu_id' => (int) $r['id'],
                    'serie'   => $r['numero_serie'] ?: ('#' . $r['id']),
                    'slot'    => $r['slot_id'],
                    'pos'     => (($r['tipo_veiculo'] === 'caminhao') ? 'Cavalo' : 'Carreta') . ' · eixo ' . (int) $r['numero_eixo'],
                    'sulco'   => round((float) $r['sulco_atual'], 1),
                ];
            }
            $n = count($tires);
            if ($n < 2) {
                api_json_send(['success' => true, 'data' => [
                    'suggestions' => [], 'ranking' => $tires, 'media' => $n ? $tires[0]['sulco'] : null,
                    'message' => $n ? 'Poucos pneus com sulco medido para sugerir rodízio.' : 'Nenhum pneu com sulco medido neste veículo.',
                ]]);
                break;
            }
            $media = round(array_sum(array_column($tires, 'sulco')) / $n, 1);
            usort($tires, fn($a, $b) => $a['sulco'] <=> $b['sulco']); // mais gasto primeiro
            $suggestions = [];
            $i = 0; $j = $n - 1;
            while ($i < $j && ($tires[$j]['sulco'] - $tires[$i]['sulco']) >= GP_RODIZIO_DIFF) {
                $a = $tires[$i]; $b = $tires[$j];
                if ($a['slot'] !== $b['slot']) {
                    $suggestions[] = [
                        'de_slot' => $a['slot'], 'de_serie' => $a['serie'], 'de_sulco' => $a['sulco'], 'de_pos' => $a['pos'],
                        'para_slot' => $b['slot'], 'para_serie' => $b['serie'], 'para_sulco' => $b['sulco'], 'para_pos' => $b['pos'],
                        'diff' => round($b['sulco'] - $a['sulco'], 1),
                    ];
                }
                $i++; $j--;
            }
            api_json_send(['success' => true, 'data' => [
                'suggestions' => $suggestions,
                'ranking' => $tires,
                'media' => $media,
                'message' => $suggestions ? '' : 'Desgaste equilibrado — rodízio não é necessário agora.',
            ]]);
            break;
        }

        case 'aplicar_rodizio': {
            // Troca real de dois pneus entre posições ocupadas (rodízio).
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) api_json_error('Corpo inválido.', 422);
            $slotA = trim((string) ($body['slot_a'] ?? ''));
            $slotB = trim((string) ($body['slot_b'] ?? ''));
            if ($slotA === '' || $slotB === '' || $slotA === $slotB) api_json_error('Slots inválidos.', 422);

            $hasPos = gp_col_exists($conn, 'alocacoes_pneus_flexiveis', 'posicao_id');
            $cols = "id, eixo_veiculo_id, pneu_id, posicao_slot, slot_id" . ($hasPos ? ", posicao_id" : "");
            $get = $conn->prepare("SELECT $cols FROM alocacoes_pneus_flexiveis WHERE slot_id = :s AND empresa_id = :e AND ativo = 1 LIMIT 1");
            $get->execute([':s' => $slotA, ':e' => $empresa_id]);
            $a = $get->fetch(PDO::FETCH_ASSOC);
            $get->execute([':s' => $slotB, ':e' => $empresa_id]);
            $b = $get->fetch(PDO::FETCH_ASSOC);
            if (!$a || !$b) api_json_error('Uma das posições não está mais ocupada. Recarregue e tente de novo.', 409);

            $conn->beginTransaction();
            // Desativa as duas alocações atuais.
            $off = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE id = :id");
            $off->execute([':id' => $a['id']]);
            $off->execute([':id' => $b['id']]);
            // Reinsere trocando os pneus (cada pneu assume a posição física do outro).
            if ($hasPos) {
                $ins = $conn->prepare("INSERT INTO alocacoes_pneus_flexiveis (eixo_veiculo_id, pneu_id, posicao_slot, posicao_id, slot_id, empresa_id, ativo) VALUES (:ev, :pn, :ps, :pi, :sl, :e, 1)");
                $ins->execute([':ev' => $b['eixo_veiculo_id'], ':pn' => $a['pneu_id'], ':ps' => $b['posicao_slot'], ':pi' => $b['posicao_id'], ':sl' => $slotB, ':e' => $empresa_id]);
                $ins->execute([':ev' => $a['eixo_veiculo_id'], ':pn' => $b['pneu_id'], ':ps' => $a['posicao_slot'], ':pi' => $a['posicao_id'], ':sl' => $slotA, ':e' => $empresa_id]);
            } else {
                $ins = $conn->prepare("INSERT INTO alocacoes_pneus_flexiveis (eixo_veiculo_id, pneu_id, posicao_slot, slot_id, empresa_id, ativo) VALUES (:ev, :pn, :ps, :sl, :e, 1)");
                $ins->execute([':ev' => $b['eixo_veiculo_id'], ':pn' => $a['pneu_id'], ':ps' => $b['posicao_slot'], ':sl' => $slotB, ':e' => $empresa_id]);
                $ins->execute([':ev' => $a['eixo_veiculo_id'], ':pn' => $b['pneu_id'], ':ps' => $a['posicao_slot'], ':sl' => $slotA, ':e' => $empresa_id]);
            }
            // Movimentações de rodízio.
            foreach ([[$a['pneu_id'], (int) $b['eixo_veiculo_id']], [$b['pneu_id'], (int) $a['eixo_veiculo_id']]] as $mv) {
                if (function_exists('pneu_movimentacao_inserir')) {
                    pneu_movimentacao_inserir($conn, [
                        'empresa_id' => $empresa_id, 'pneu_id' => (int) $mv[0], 'tipo' => 'deslocamento',
                        'eixo_id' => $mv[1], 'observacoes' => 'Rodízio aplicado na Gestão Interativa Pro.',
                    ]);
                }
            }
            $conn->commit();
            api_json_send(['success' => true, 'message' => 'Rodízio aplicado.']);
            break;
        }

        case 'set_odometro': {
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) api_json_error('Corpo inválido.', 422);
            $veiculoId = (int) ($body['veiculo_id'] ?? 0);
            $km = (int) ($body['km'] ?? -1);
            if (!$veiculoId || $km < 0) api_json_error('Dados inválidos.', 422);
            $chk = $conn->prepare("SELECT km_atual FROM veiculos WHERE id = :v AND empresa_id = :e");
            $chk->execute([':v' => $veiculoId, ':e' => $empresa_id]);
            $atual = $chk->fetchColumn();
            if ($atual === false) api_json_error('Veículo não encontrado.', 404);
            if ($km < (int) $atual) api_json_error('O KM informado é menor que o atual (' . (int) $atual . ' km).', 422);
            $up = $conn->prepare("UPDATE veiculos SET km_atual = :km WHERE id = :v AND empresa_id = :e");
            $up->execute([':km' => $km, ':v' => $veiculoId, ':e' => $empresa_id]);
            api_json_send(['success' => true, 'message' => 'Odômetro atualizado.', 'km_atual' => $km]);
            break;
        }

        case 'registrar_sulco': {
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) api_json_error('Corpo inválido.', 422);
            $pneuId = (int) ($body['pneu_id'] ?? 0);
            if (!$pneuId) api_json_error('Pneu obrigatório.', 422);
            // Confirma pertencimento à empresa
            $chk = $conn->prepare("SELECT 1 FROM pneus WHERE id = :id AND empresa_id = :e");
            $chk->execute([':id' => $pneuId, ':e' => $empresa_id]);
            if (!$chk->fetchColumn()) api_json_error('Pneu não pertence à empresa.', 403);

            $leituras = [];
            foreach (['sulco_externo', 'sulco_central', 'sulco_interno'] as $k) {
                if (isset($body[$k]) && $body[$k] !== '' && is_numeric($body[$k])) $leituras[] = (float) $body[$k];
            }
            if (!$leituras && isset($body['sulco_mm']) && is_numeric($body['sulco_mm'])) $leituras[] = (float) $body['sulco_mm'];
            if (!$leituras) api_json_error('Informe ao menos uma medição de sulco.', 422);
            $media = round(array_sum($leituras) / count($leituras), 2);

            $obsPartes = [];
            if (count($leituras) > 1) $obsPartes[] = 'Leituras: ' . implode(' / ', $leituras) . ' mm';
            if (!empty($body['responsavel'])) $obsPartes[] = 'Resp.: ' . trim((string) $body['responsavel']);
            if (!empty($body['observacoes'])) $obsPartes[] = trim((string) $body['observacoes']);
            $obs = mb_substr(implode(' | ', $obsPartes), 0, 500);

            $ins = $conn->prepare("INSERT INTO pneu_medicoes_sulco (empresa_id, pneu_id, sulco_mm, km_veiculo, veiculo_id, observacoes, usuario_id, data_medicao)
                                   VALUES (:e, :p, :s, :km, :v, :obs, :u, NOW())");
            $ins->execute([
                ':e' => $empresa_id, ':p' => $pneuId, ':s' => $media,
                ':km' => isset($body['km_veiculo']) && $body['km_veiculo'] !== '' ? (int) $body['km_veiculo'] : null,
                ':v' => isset($body['veiculo_id']) && $body['veiculo_id'] !== '' ? (int) $body['veiculo_id'] : null,
                ':obs' => $obs ?: null, ':u' => $usuario_id,
            ]);

            $health = gp_health($media, null, null);
            api_json_send(['success' => true, 'sulco_media' => $media, 'health' => $health, 'message' => 'Medição registrada.']);
            break;
        }

        case 'registrar_calibragem': {
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) api_json_error('Corpo inválido.', 422);
            $pneuId = (int) ($body['pneu_id'] ?? 0);
            if (!$pneuId) api_json_error('Pneu obrigatório.', 422);
            if (!isset($body['pressao_aferida']) || !is_numeric($body['pressao_aferida'])) api_json_error('Pressão aferida obrigatória.', 422);
            $chk = $conn->prepare("SELECT 1 FROM pneus WHERE id = :id AND empresa_id = :e");
            $chk->execute([':id' => $pneuId, ':e' => $empresa_id]);
            if (!$chk->fetchColumn()) api_json_error('Pneu não pertence à empresa.', 403);

            $afer = (float) $body['pressao_aferida'];
            $reco = (isset($body['pressao_recomendada']) && is_numeric($body['pressao_recomendada'])) ? (float) $body['pressao_recomendada'] : null;
            $dif = $reco !== null ? round($afer - $reco, 2) : null;

            $ins = $conn->prepare("INSERT INTO pneu_calibragens (empresa_id, pneu_id, veiculo_id, pressao_recomendada, pressao_aferida, diferenca, km_veiculo, responsavel, observacoes, usuario_id, data_calibragem)
                                   VALUES (:e, :p, :v, :reco, :afer, :dif, :km, :resp, :obs, :u, NOW())");
            $ins->execute([
                ':e' => $empresa_id, ':p' => $pneuId,
                ':v' => isset($body['veiculo_id']) && $body['veiculo_id'] !== '' ? (int) $body['veiculo_id'] : null,
                ':reco' => $reco, ':afer' => $afer, ':dif' => $dif,
                ':km' => isset($body['km_veiculo']) && $body['km_veiculo'] !== '' ? (int) $body['km_veiculo'] : null,
                ':resp' => !empty($body['responsavel']) ? mb_substr((string) $body['responsavel'], 0, 120) : null,
                ':obs' => !empty($body['observacoes']) ? mb_substr((string) $body['observacoes'], 0, 500) : null,
                ':u' => $usuario_id,
            ]);

            $aviso = null;
            if ($reco !== null) {
                if ($afer < $reco - 2) $aviso = 'Pressão abaixo do recomendado.';
                elseif ($afer > $reco + 2) $aviso = 'Pressão acima do recomendado.';
            }
            api_json_send(['success' => true, 'diferenca' => $dif, 'aviso' => $aviso, 'message' => 'Calibragem registrada.']);
            break;
        }

        case 'descartar_pneu': {
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) api_json_error('Corpo inválido.', 422);
            $pneuId = (int) ($body['pneu_id'] ?? 0);
            if (!$pneuId) api_json_error('Pneu obrigatório.', 422);
            $chk = $conn->prepare("SELECT 1 FROM pneus WHERE id = :id AND empresa_id = :e");
            $chk->execute([':id' => $pneuId, ':e' => $empresa_id]);
            if (!$chk->fetchColumn()) api_json_error('Pneu não pertence à empresa.', 403);

            $conn->beginTransaction();
            try {
                // Veículo/eixo da alocação ativa (para registrar a movimentação)
                $sa = $conn->prepare("SELECT apf.eixo_veiculo_id, ev.veiculo_id, apf.km_odometro
                                      FROM alocacoes_pneus_flexiveis apf
                                      LEFT JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                                      WHERE apf.pneu_id = :id AND apf.empresa_id = :e AND apf.ativo = 1 LIMIT 1");
                $sa->execute([':id' => $pneuId, ':e' => $empresa_id]);
                $aloc = $sa->fetch(PDO::FETCH_ASSOC) ?: [];

                // Desativa alocação
                $up = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE pneu_id = :id AND empresa_id = :e AND ativo = 1");
                $up->execute([':id' => $pneuId, ':e' => $empresa_id]);

                // Status descartado (3)
                $us = $conn->prepare("UPDATE pneus SET status_id = 3 WHERE id = :id AND empresa_id = :e");
                $us->execute([':id' => $pneuId, ':e' => $empresa_id]);

                // Movimentação de descarte
                $obs = !empty($body['observacoes']) ? mb_substr((string) $body['observacoes'], 0, 500) : 'Descartado (Gestão Interativa Pro).';
                $im = $conn->prepare("INSERT INTO pneu_movimentacoes (empresa_id, pneu_id, tipo, veiculo_id, eixo_id, observacoes, data_movimentacao)
                                      VALUES (:e, :p, 'descarte', :v, :eixo, :obs, NOW())");
                $im->execute([
                    ':e' => $empresa_id, ':p' => $pneuId,
                    ':v' => isset($aloc['veiculo_id']) ? $aloc['veiculo_id'] : null,
                    ':eixo' => isset($aloc['eixo_veiculo_id']) ? $aloc['eixo_veiculo_id'] : null,
                    ':obs' => $obs,
                ]);
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollBack();
                throw $e;
            }
            api_json_send(['success' => true, 'message' => 'Pneu descartado.']);
            break;
        }

        case 'get_montagem': {
            $veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
            if (!$veiculoId) api_json_error('veiculo_id obrigatório.', 422);
            $st = $conn->prepare("SELECT config FROM gestao_montagem WHERE veiculo_id = :v AND empresa_id = :e LIMIT 1");
            $st->execute([':v' => $veiculoId, ':e' => $empresa_id]);
            $cfg = $st->fetchColumn();
            $data = $cfg ? json_decode($cfg, true) : null;
            api_json_send(['success' => true, 'data' => is_array($data) ? $data : null]);
            break;
        }

        case 'salvar_montagem': {
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) api_json_error('Corpo inválido.', 422);
            $veiculoId = (int) ($body['veiculo_id'] ?? 0);
            if (!$veiculoId) api_json_error('veiculo_id obrigatório.', 422);
            // Confirma que o veículo é da empresa
            $chk = $conn->prepare("SELECT 1 FROM veiculos WHERE id = :v AND empresa_id = :e");
            $chk->execute([':v' => $veiculoId, ':e' => $empresa_id]);
            if (!$chk->fetchColumn()) api_json_error('Veículo não pertence à empresa.', 403);

            $eixos = (isset($body['eixos']) && is_array($body['eixos'])) ? $body['eixos'] : [];
            $temEixos = false;
            $eixosLimpo = [];
            foreach ($eixos as $k => $v) {
                if (is_numeric($v)) { $eixosLimpo[(string) (int) $k] = round((float) $v, 1); $temEixos = true; }
            }
            $tankX = (isset($body['tank_x']) && is_numeric($body['tank_x'])) ? round((float) $body['tank_x'], 1) : null;

            // Sem nenhuma posição custom: remove a montagem (volta ao automático).
            if (!$temEixos && $tankX === null) {
                $del = $conn->prepare("DELETE FROM gestao_montagem WHERE veiculo_id = :v AND empresa_id = :e");
                $del->execute([':v' => $veiculoId, ':e' => $empresa_id]);
                api_json_send(['success' => true, 'cleared' => true, 'message' => 'Montagem automática.']);
                break;
            }

            $cfg = json_encode(['eixos' => (object) $eixosLimpo, 'tankX' => $tankX], JSON_UNESCAPED_UNICODE);
            $up = $conn->prepare("INSERT INTO gestao_montagem (veiculo_id, empresa_id, config) VALUES (:v, :e, :c)
                                  ON DUPLICATE KEY UPDATE config = VALUES(config)");
            $up->execute([':v' => $veiculoId, ':e' => $empresa_id, ':c' => $cfg]);
            api_json_send(['success' => true, 'message' => 'Montagem salva.']);
            break;
        }

        case 'vehicle_timeline': {
            // Linha do tempo do veículo: movimentações de pneus + calibragens.
            $veiculoId = (int) ($_GET['veiculo_id'] ?? 0);
            if (!$veiculoId) api_json_error('veiculo_id obrigatório.', 422);
            $itens = [];

            $sqlM = "SELECT m.tipo, m.data_movimentacao AS data, m.observacoes, m.custo, m.eixo_id,
                        p.numero_serie, ev.numero_eixo, ev.tipo_veiculo
                     FROM pneu_movimentacoes m
                     LEFT JOIN pneus p ON p.id = m.pneu_id
                     LEFT JOIN eixos_veiculos ev ON ev.id = m.eixo_id
                     WHERE m.veiculo_id = :v AND m.empresa_id = :e
                     ORDER BY m.data_movimentacao DESC LIMIT 120";
            $stm = $conn->prepare($sqlM);
            $stm->execute([':v' => $veiculoId, ':e' => $empresa_id]);
            $labels = [
                'instalacao' => ['Instalação de pneu', 'fa-down-long', '#22c55e'],
                'remocao' => ['Remoção de pneu', 'fa-up-long', '#f59e0b'],
                'deslocamento' => ['Rodízio / movimentação', 'fa-rotate', '#3b82f6'],
                'recapagem' => ['Recapagem', 'fa-recycle', '#8b5cf6'],
                'manutencao' => ['Manutenção', 'fa-screwdriver-wrench', '#06b6d4'],
                'descarte' => ['Descarte', 'fa-trash', '#ef4444'],
                'entrada_estoque' => ['Entrada em estoque', 'fa-box', '#94a3b8'],
            ];
            foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $lbl = $labels[$r['tipo']] ?? [ucfirst((string) $r['tipo']), 'fa-circle', '#94a3b8'];
                $pos = $r['numero_eixo'] ? ((($r['tipo_veiculo'] === 'caminhao') ? 'cavalo' : 'carreta') . ' eixo ' . (int) $r['numero_eixo']) : '';
                $sn = $r['numero_serie'] ? ('pneu ' . $r['numero_serie']) : '';
                $desc = trim(implode(' · ', array_filter([$sn, $pos])));
                if (!empty($r['observacoes'])) $desc = $desc ? ($desc . ' — ' . $r['observacoes']) : $r['observacoes'];
                $itens[] = ['data' => $r['data'], 'titulo' => $lbl[0], 'icon' => $lbl[1], 'cor' => $lbl[2], 'desc' => $desc];
            }

            $stc = $conn->prepare("SELECT c.data_calibragem AS data, c.pressao_aferida, c.diferenca, p.numero_serie
                                   FROM pneu_calibragens c LEFT JOIN pneus p ON p.id = c.pneu_id
                                   WHERE c.veiculo_id = :v AND c.empresa_id = :e
                                   ORDER BY c.data_calibragem DESC LIMIT 60");
            $stc->execute([':v' => $veiculoId, ':e' => $empresa_id]);
            foreach ($stc->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = ($r['pressao_aferida'] !== null ? $r['pressao_aferida'] . ' psi' : '') .
                     ($r['diferenca'] !== null ? ' (' . ($r['diferenca'] >= 0 ? '+' : '') . $r['diferenca'] . ')' : '');
                $itens[] = ['data' => $r['data'], 'titulo' => 'Calibragem', 'icon' => 'fa-gauge', 'cor' => '#14b8a6',
                            'desc' => trim(($r['numero_serie'] ? 'pneu ' . $r['numero_serie'] . ' · ' : '') . $d)];
            }

            usort($itens, function ($a, $b) { return strcmp((string) $b['data'], (string) $a['data']); });
            $itens = array_slice($itens, 0, 120);
            api_json_send(['success' => true, 'data' => $itens]);
            break;
        }

        case 'fleet_ranking': {
            // KPIs por veículo para ranking/comparação da frota.
            $sql = "SELECT v.id, v.placa, v.modelo,
                        p.status_id, p.numero_recapagens, p.valor_compra, p.km_rodado_total,
                        (SELECT s.sulco_mm FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco
                    FROM veiculos v
                    JOIN eixos_veiculos ev ON ev.veiculo_id = v.id AND ev.empresa_id = :e2
                    JOIN alocacoes_pneus_flexiveis apf ON apf.eixo_veiculo_id = ev.id AND apf.ativo = 1 AND apf.empresa_id = :e3
                    JOIN pneus p ON p.id = apf.pneu_id AND p.empresa_id = :e4
                    WHERE v.empresa_id = :e";
            $st = $conn->prepare($sql);
            $st->execute([':e' => $empresa_id, ':e2' => $empresa_id, ':e3' => $empresa_id, ':e4' => $empresa_id]);
            $vmap = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $id = (int) $r['id'];
                if (!isset($vmap[$id])) $vmap[$id] = ['id' => $id, 'placa' => $r['placa'], 'modelo' => $r['modelo'],
                    'total' => 0, 'score' => 0.0, 'recapagens' => 0, 'custo' => 0.0, 'km' => 0];
                $vmap[$id]['total']++;
                $vmap[$id]['recapagens'] += (int) $r['numero_recapagens'];
                $vmap[$id]['custo'] += (float) ($r['valor_compra'] ?? 0);
                $vmap[$id]['km'] += (int) ($r['km_rodado_total'] ?? 0);
                $sid = (int) $r['status_id'];
                $sulco = $r['sulco'] !== null ? (float) $r['sulco'] : null;
                $sc = 70; // sem medição = neutro
                if ($sid === 4) $sc = 55;
                elseif ($sulco !== null) $sc = ($sulco <= GP_SULCO_CRITICO) ? 25 : (($sulco <= GP_SULCO_ATENCAO) ? 60 : 100);
                $vmap[$id]['score'] += $sc;
            }
            $rows = [];
            foreach ($vmap as $v) {
                $saude = $v['total'] > 0 ? round($v['score'] / $v['total']) : 0;
                $custoKm = $v['km'] > 0 ? round($v['custo'] / $v['km'], 4) : 0.0;
                $rows[] = ['id' => $v['id'], 'placa' => $v['placa'], 'modelo' => $v['modelo'],
                    'total' => $v['total'], 'saude' => $saude, 'recapagens' => $v['recapagens'],
                    'custo_total' => round($v['custo'], 2), 'custo_km' => $custoKm];
            }
            usort($rows, function ($a, $b) { return $b['saude'] <=> $a['saude']; });
            api_json_send(['success' => true, 'data' => $rows]);
            break;
        }

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('gestao_pneu_pro: ' . $e->getMessage());
    api_json_error('Erro interno ao processar a solicitação.', 500);
}
