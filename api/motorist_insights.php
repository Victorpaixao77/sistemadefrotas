<?php
/**
 * Insights de motoristas:
 *  - alertas de CNH: vencida, vencendo (30/60 dias), sem data de validade;
 *  - qualidade do cadastro: sem CNH, CPF inválido, sem e-mail/telefone.
 * Itens acionáveis com link direto para edição do motorista.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/api_json.php';
require_once '../includes/doc_validators.php';
require_once '../includes/sf_insights_cache.php';

configure_session();
session_start();

require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

try {
    $conn = getConnection();

    sf_insights_json_cached($empresa_id, 'motorist', 300, function () use ($conn, $empresa_id) {
    $stmt = $conn->prepare("SELECT id, nome, cpf, cnh, data_validade_cnh, email, telefone, foto_motorista
                            FROM motoristas WHERE empresa_id = :e ORDER BY nome LIMIT 10000");
    $stmt->execute([':e' => $empresa_id]);
    $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hoje = new DateTime('today');
    $itens = [];
    $qErros = 0;
    $qAvisos = 0;
    $cVencidas = 0;
    $c30 = 0;
    $c60 = 0;

    foreach ($motoristas as $m) {
        $motivos = [];
        $temErro = false;
        $nome = trim((string) ($m['nome'] ?? '')) ?: '—';

        // ----- CNH: validade -----
        $val = trim((string) ($m['data_validade_cnh'] ?? ''));
        $semValidade = ($val === '' || $val === '0000-00-00' || strpos($val, '0000') === 0);
        if ($semValidade) {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem data de validade da CNH cadastrada.'];
        } else {
            try {
                $dv = new DateTime($val);
                $diff = (int) $hoje->diff($dv)->format('%r%a'); // dias (negativo = vencida)
                $dataBr = $dv->format('d/m/Y');
                if ($diff < 0) {
                    $motivos[] = ['sev' => 'erro', 'msg' => "CNH vencida desde $dataBr (" . abs($diff) . " dias)."];
                    $temErro = true;
                    $cVencidas++;
                } elseif ($diff <= 30) {
                    $motivos[] = ['sev' => 'aviso', 'msg' => "CNH vence em $diff dia(s) — $dataBr."];
                    $c30++;
                } elseif ($diff <= 60) {
                    $motivos[] = ['sev' => 'aviso', 'msg' => "CNH vence em $diff dias — $dataBr."];
                    $c60++;
                }
            } catch (Throwable $e) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Data de validade da CNH em formato inválido.'];
            }
        }

        // ----- CNH: número -----
        if (trim((string) ($m['cnh'] ?? '')) === '') {
            $motivos[] = ['sev' => 'erro', 'msg' => 'Sem número de CNH cadastrado.'];
            $temErro = true;
        }

        // ----- CPF -----
        $cpf = doc_only_digits((string) ($m['cpf'] ?? ''));
        if ($cpf !== '' && !doc_validar_cpf($cpf)) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'CPF inválido (dígitos verificadores).'];
            $temErro = true;
        } elseif ($cpf === '') {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem CPF cadastrado.'];
        }

        // ----- Contato -----
        if (trim((string) ($m['email'] ?? '')) === '') {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem e-mail cadastrado.'];
        }
        if (trim((string) ($m['telefone'] ?? '')) === '') {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem telefone cadastrado.'];
        }

        if (!$motivos) {
            continue;
        }
        if ($temErro) {
            $qErros++;
        } else {
            $qAvisos++;
        }
        $itens[] = [
            'id' => (int) $m['id'],
            'nome' => $nome,
            'severidade' => $temErro ? 'erro' : 'aviso',
            'motivos' => $motivos,
        ];
    }

    usort($itens, function ($a, $b) {
        if ($a['severidade'] === $b['severidade']) {
            return strcmp($a['nome'], $b['nome']);
        }
        return $a['severidade'] === 'erro' ? -1 : 1;
    });

    return [
        'success' => true,
        'total_motoristas' => count($motoristas),
        'qualidade' => $itens,
        'q_erros' => $qErros,
        'q_avisos' => $qAvisos,
        'cnh' => [
            'vencidas' => $cVencidas,
            'vence_30' => $c30,
            'vence_60' => $c60,
        ],
    ];
    });
} catch (Throwable $e) {
    error_log('api/motorist_insights.php: ' . $e->getMessage());
    api_json_error('Erro ao gerar insights de motoristas.', 500);
}
