<?php
declare(strict_types=1);

require_once __DIR__ . '/adm_schema.php';
require_once __DIR__ . '/adm_events.php';

function adm_auditoria_empresas(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT e.id, e.razao_social, e.email, e.status_saas,
               ec.id AS empresa_cliente_id
        FROM empresa_adm e
        LEFT JOIN empresa_clientes ec ON ec.empresa_adm_id = e.id
        ORDER BY e.razao_social
    ")->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $emp) {
        $issues = [];
        $score = 100;
        $ecId = (int) ($emp['empresa_cliente_id'] ?? 0);

        if ($ecId > 0) {
            try {
                $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE empresa_id = ? AND two_factor_enabled = 1 AND two_factor_confirmed_at IS NOT NULL');
                $st->execute([$ecId]);
                $com2fa = (int) $st->fetchColumn();
                $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = ? AND status = 'ativo'");
                $st->execute([$ecId]);
                $ativos = (int) $st->fetchColumn();
                if ($ativos > 0 && $com2fa === 0) {
                    $issues[] = ['tipo' => 'aviso', 'msg' => 'Nenhum usuário com 2FA ativo'];
                    $score -= 15;
                }
            } catch (Throwable $e) {
            }

            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = ? AND status = 'ativo' AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
                $st->execute([$ecId]);
                $inativos = (int) $st->fetchColumn();
                if ($inativos > 0) {
                    $issues[] = ['tipo' => 'info', 'msg' => "{$inativos} usuário(s) sem atividade há 90+ dias"];
                    $score -= 5;
                }
            } catch (Throwable $e) {
            }

            try {
                $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts la
                    INNER JOIN usuarios u ON u.email = la.email
                    WHERE u.empresa_id = ? AND la.tentativas >= 5');
                $st->execute([$ecId]);
                if ((int) $st->fetchColumn() > 0) {
                    $issues[] = ['tipo' => 'critico', 'msg' => 'Muitas tentativas de login bloqueadas'];
                    $score -= 20;
                }
            } catch (Throwable $e) {
            }
        }

        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM adm_boletos WHERE empresa_adm_id = ? AND status = 'vencido'");
            $st->execute([(int) $emp['id']]);
            if ((int) $st->fetchColumn() > 0) {
                $issues[] = ['tipo' => 'critico', 'msg' => 'Boletos vencidos'];
                $score -= 25;
            }
        } catch (Throwable $e) {
        }

        $out[] = [
            'id' => (int) $emp['id'],
            'razao_social' => $emp['razao_social'],
            'score' => max(0, $score),
            'issues' => $issues,
        ];
    }
    return $out;
}

function adm_ia_comercial_oportunidades(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
        SELECT e.id, e.razao_social, e.tem_acesso_seguro,
               p.nome AS plano,
               (SELECT COUNT(*) FROM veiculos v JOIN empresa_clientes ec ON ec.id = v.empresa_id
                WHERE ec.empresa_adm_id = e.id) AS veiculos,
               (SELECT COUNT(*) FROM rotas r JOIN empresa_clientes ec ON ec.id = r.empresa_id
                WHERE ec.empresa_adm_id = e.id AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS rotas_30d,
               (SELECT MAX(data_acesso) FROM log_acessos l
                JOIN empresa_clientes ec ON ec.id = l.empresa_id
                WHERE ec.empresa_adm_id = e.id) AS ultimo_login
        FROM empresa_adm e
        LEFT JOIN adm_planos p ON p.id = e.plano_id
        WHERE e.status_saas IN ('ativa','trial')
    ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('adm_ia_comercial_oportunidades: ' . $e->getMessage());
        return [];
    }
    $ops = [];
    foreach ($rows as $r) {
        $sugestoes = [];
        if (($r['tem_acesso_seguro'] ?? 'nao') === 'nao') {
            $sugestoes[] = 'Pode contratar Sistema Seguro';
        }
        if ((int) $r['rotas_30d'] === 0 && (int) $r['veiculos'] > 0) {
            $sugestoes[] = 'Baixo uso de Rotas — risco de churn';
        }
        if (empty($r['ultimo_login']) || strtotime((string) $r['ultimo_login']) < time() - 86400 * 30) {
            $sugestoes[] = 'Empresa inativa há 30+ dias';
        }
        if ((int) $r['veiculos'] >= 5 && stripos((string) ($r['plano'] ?? ''), 'básico') !== false) {
            $sugestoes[] = 'Chance de upgrade de plano';
        }
        if ($sugestoes) {
            $ops[] = ['empresa' => $r['razao_social'], 'id' => (int) $r['id'], 'sugestoes' => $sugestoes];
        }
    }
    return $ops;
}

function adm_ia_admin_analyze(PDO $pdo): array
{
    require_once __DIR__ . '/adm_metrics.php';
    $dash = adm_metrics_dashboard($pdo);
    $audit = adm_auditoria_empresas($pdo);
    $comercial = adm_ia_comercial_oportunidades($pdo);

    $risco_churn = array_filter($comercial, static fn ($o) => in_array('Baixo uso de Rotas — risco de churn', $o['sugestoes'], true) || in_array('Empresa inativa há 30+ dias', $o['sugestoes'], true));

    $baixa_seguranca = array_filter($audit, static fn ($a) => $a['score'] < 70);

    $alertas_abertos = 0;
    try {
        $alertas_abertos = (int) $pdo->query("SELECT COUNT(*) FROM adm_alertas WHERE status = 'aberto'")->fetchColumn();
    } catch (Throwable $e) {
    }

    return [
        'resumo' => [
            'empresas_ativas' => $dash['empresas_ativas'],
            'mrr' => $dash['mrr'],
            'inadimplentes' => $dash['clientes_inadimplentes'],
            'alertas_abertos' => $alertas_abertos,
        ],
        'risco_cancelamento' => array_values($risco_churn),
        'seguranca_baixa' => array_values($baixa_seguranca),
        'oportunidades' => $comercial,
        'sugestoes' => [
            $dash['clientes_inadimplentes'] > 0 ? 'Priorizar cobrança de inadimplentes' : null,
            $dash['empresas_trial'] > 0 ? 'Acompanhar conversão de trials' : null,
            $alertas_abertos > 0 ? 'Revisar alertas abertos no NOC' : null,
        ],
    ];
}
