<?php
/**
 * Insights de fornecedores:
 *  - qualidade: documento (CNPJ/CPF) inválido; documento duplicado; sem e-mail;
 *               sem município (IBGE) — itens acionáveis do cadastro.
 *  - ranking:   top fornecedores por gasto (contas a pagar).
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/api_json.php';
require_once '../includes/doc_validators.php';
require_once '../includes/sf_insights_cache.php';

configure_session();
session_start();

require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

try {
    $conn = getConnection();

    sf_insights_json_cached($empresa_id, 'fornecedor', 300, function () use ($conn, $empresa_id) {
    // ---------- QUALIDADE DO CADASTRO ----------
    $stmt = $conn->prepare("SELECT id, tipo, nome, cpf, cnpj, email, cidade, uf, codigo_municipio_ibge, situacao
                            FROM fornecedores WHERE empresa_id = :e ORDER BY nome LIMIT 10000");
    $stmt->execute([':e' => $empresa_id]);
    $forns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapa de documentos para detectar duplicados.
    $docCount = [];
    foreach ($forns as $f) {
        $doc = doc_only_digits($f['tipo'] === 'J' ? ($f['cnpj'] ?? '') : ($f['cpf'] ?? ''));
        if ($doc !== '') {
            $docCount[$doc] = ($docCount[$doc] ?? 0) + 1;
        }
    }

    $itens = [];
    $qErros = 0;
    $qAvisos = 0;
    foreach ($forns as $f) {
        $motivos = [];
        $temErro = false;
        $ativo = ($f['situacao'] ?? 'A') === 'A';
        $isPJ = $f['tipo'] === 'J';
        $doc = doc_only_digits($isPJ ? ($f['cnpj'] ?? '') : ($f['cpf'] ?? ''));

        // Documento inválido
        if ($doc !== '') {
            $valido = $isPJ ? doc_validar_cnpj($doc) : doc_validar_cpf($doc);
            if (!$valido) {
                $motivos[] = ['sev' => 'erro', 'msg' => ($isPJ ? 'CNPJ' : 'CPF') . ' inválido (dígitos verificadores).'];
                $temErro = true;
            }
        }
        // Documento duplicado
        if ($doc !== '' && ($docCount[$doc] ?? 0) > 1) {
            $motivos[] = ['sev' => 'erro', 'msg' => ($isPJ ? 'CNPJ' : 'CPF') . ' duplicado em mais de um cadastro.'];
            $temErro = true;
        }
        // Sem e-mail (só para ativos)
        if ($ativo && trim((string) ($f['email'] ?? '')) === '') {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem e-mail cadastrado (atrapalha contato e NF-e).'];
        }
        // Sem município IBGE (só para ativos)
        if ($ativo && trim((string) ($f['codigo_municipio_ibge'] ?? '')) === '') {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Sem código de município (IBGE) — necessário para emissão fiscal.'];
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
            'id' => (int) $f['id'],
            'nome' => $f['nome'] ?: '—',
            'doc' => $doc,
            'tipo' => $isPJ ? 'PJ' : 'PF',
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

    // ---------- RANKING POR GASTO (contas a pagar) ----------
    $ranking = [];
    try {
        // Rankeia pelo nome do fornecedor na conta (funciona com ou sem vínculo por FK).
        $stmt = $conn->prepare("SELECT cp.fornecedor AS nome,
                                       SUM(cp.valor) AS total,
                                       COUNT(*) AS n
                                FROM contas_pagar cp
                                WHERE cp.empresa_id = :e AND cp.fornecedor <> ''
                                GROUP BY cp.fornecedor
                                HAVING total > 0
                                ORDER BY total DESC
                                LIMIT 8");
        $stmt->execute([':e' => $empresa_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ranking[] = [
                'nome' => $r['nome'] ?: '—',
                'total' => round((float) $r['total'], 2),
                'n' => (int) $r['n'],
            ];
        }
    } catch (Throwable $e) {
        // contas_pagar pode não existir / sem vínculo — ranking fica vazio.
        error_log('fornecedor_insights ranking: ' . $e->getMessage());
    }

    return [
        'success' => true,
        'total_fornecedores' => count($forns),
        'qualidade' => $itens,
        'q_erros' => $qErros,
        'q_avisos' => $qAvisos,
        'ranking' => $ranking,
    ];
    });
} catch (Throwable $e) {
    error_log('api/fornecedor_insights.php: ' . $e->getMessage());
    api_json_error('Erro ao gerar insights de fornecedores.', 500);
}
