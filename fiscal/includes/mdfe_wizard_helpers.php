<?php
/**
 * Helpers do wizard MDF-e: normalização POST, vínculos CT-e/NF-e, persistência estendida.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/pef_ciot_helpers.php';

function mdfe_ensure_extended_schema(?PDO $conn = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn = $conn ?? getConnection();
    sf_pef_ensure_mdfe_link_schema($conn);
    $sqlFile = dirname(__DIR__, 2) . '/sql/mdfe_fase3_completo.sql';
    if (!is_readable($sqlFile)) {
        return;
    }
    foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($sqlFile)))) as $stmt) {
        if ($stmt === '' || stripos($stmt, '--') === 0) {
            continue;
        }
        if (stripos($stmt, 'ALTER') === false && stripos($stmt, 'CREATE') === false) {
            continue;
        }
        try {
            $conn->exec($stmt);
        } catch (Throwable $e) {
            error_log('mdfe_ensure_extended_schema: ' . $e->getMessage());
        }
    }
}

/** Normaliza nomes de campos do wizard para o fluxo criar_mdfe. */
function mdfe_normalize_wizard_post(array &$post): void
{
    $map = [
        'uf_carga' => 'uf_inicio',
        'municipio_carga' => 'municipio_carregamento',
        'uf_descarga' => 'uf_fim',
        'municipio_descarga' => 'municipio_descarregamento',
        'data_emissao' => 'data_viagem',
    ];
    foreach ($map as $from => $to) {
        if (empty($post[$to]) && !empty($post[$from])) {
            $post[$to] = $post[$from];
        }
    }
    $infoFisco = trim((string) ($post['info_fisco'] ?? ''));
    $infoCont = trim((string) ($post['info_contribuinte'] ?? ''));
    if ($infoFisco !== '' || $infoCont !== '') {
        $obs = trim((string) ($post['observacoes'] ?? ''));
        $parts = array_filter([$obs, $infoFisco !== '' ? 'Fisco: ' . $infoFisco : '', $infoCont !== '' ? 'Contribuinte: ' . $infoCont : '']);
        $post['observacoes'] = implode("\n", $parts);
    }
}

/**
 * @param array<int,int> $cteIds
 * @param array<int,int> $nfeIds
 * @param array<int,array<string,mixed>> $documentos
 * @return array{0:array<int,int>,1:array<int,int>}
 */
function mdfe_resolver_ids_documentos(PDO $conn, int $empresaId, array $cteIds, array $nfeIds, array $documentos): array
{
    $cteSet = [];
    foreach ($cteIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $cteSet[$id] = $id;
        }
    }
    $nfeSet = [];
    foreach ($nfeIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $nfeSet[$id] = $id;
        }
    }

    $stCte = $conn->prepare('SELECT id FROM fiscal_cte WHERE empresa_id = ? AND chave_acesso = ? LIMIT 1');
    $stNfe = $conn->prepare('SELECT id FROM fiscal_nfe_clientes WHERE empresa_id = ? AND chave_acesso = ? LIMIT 1');

    foreach ($documentos as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $cteId = (int) ($doc['cteId'] ?? $doc['cte_id'] ?? 0);
        if ($cteId > 0) {
            $cteSet[$cteId] = $cteId;
        }
        $nfeId = (int) ($doc['nfeId'] ?? $doc['nfe_id'] ?? 0);
        if ($nfeId > 0) {
            $nfeSet[$nfeId] = $nfeId;
        }
        $chCte = preg_replace('/\D/', '', (string) ($doc['chaveCte'] ?? $doc['chave_cte'] ?? ''));
        if (strlen($chCte) === 44) {
            $stCte->execute([$empresaId, $chCte]);
            $found = (int) $stCte->fetchColumn();
            if ($found > 0) {
                $cteSet[$found] = $found;
            }
        }
        $chNfe = preg_replace('/\D/', '', (string) ($doc['chaveNfe'] ?? $doc['chave_nfe'] ?? ''));
        if (strlen($chNfe) === 44) {
            $stNfe->execute([$empresaId, $chNfe]);
            $found = (int) $stNfe->fetchColumn();
            if ($found > 0) {
                $nfeSet[$found] = $found;
            }
        }
    }

    return [array_values($cteSet), array_values($nfeSet)];
}

/** @return array{0:?int,1:?int} veiculo_id, motorista_id */
function mdfe_resolver_veiculo_motorista(PDO $conn, int $empresaId, array $post, $veiculoId, $motoristaId): array
{
    $veiculoId = (int) ($veiculoId ?? 0);
    $motoristaId = (int) ($motoristaId ?? 0);

    if ($veiculoId <= 0) {
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($post['rod_placa'] ?? '')));
        if ($placa !== '') {
            $st = $conn->prepare('SELECT id FROM veiculos WHERE empresa_id = ? AND UPPER(REPLACE(placa, \'-\', \'\')) = ? LIMIT 1');
            $st->execute([$empresaId, $placa]);
            $veiculoId = (int) $st->fetchColumn();
        }
    }

    if ($motoristaId <= 0) {
        $condutores = mdfe_parse_json_any($post['rod_condutores_json'] ?? '', []);
        $cpf = '';
        if (is_array($condutores) && isset($condutores[0])) {
            $cpf = preg_replace('/\D/', '', (string) ($condutores[0]['cpf'] ?? $condutores[0]['CPF'] ?? ''));
        }
        if (strlen($cpf) === 11) {
            $st = $conn->prepare('SELECT id FROM motoristas WHERE empresa_id = ? AND REPLACE(REPLACE(REPLACE(cpf, \'.\', \'\'), \'-\', \'\'), \'/\', \'\') = ? LIMIT 1');
            $st->execute([$empresaId, $cpf]);
            $motoristaId = (int) $st->fetchColumn();
        }
    }

    return [$veiculoId > 0 ? $veiculoId : null, $motoristaId > 0 ? $motoristaId : null];
}

/** @return array<int,array<string,mixed>> */
function mdfe_documentos_tem_chaves(array $documentos): array
{
    $chaves = ['cte' => 0, 'nfe' => 0];
    foreach ($documentos as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        if (strlen(preg_replace('/\D/', '', (string) ($doc['chaveCte'] ?? $doc['chave_cte'] ?? ''))) === 44) {
            $chaves['cte']++;
        }
        if (strlen(preg_replace('/\D/', '', (string) ($doc['chaveNfe'] ?? $doc['chave_nfe'] ?? ''))) === 44) {
            $chaves['nfe']++;
        }
    }
    return $chaves;
}

function mdfe_parse_json_any($raw, $default = [])
{
    if (is_array($raw)) {
        return $raw;
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return $default;
    }
    $j = json_decode($s, true);
    return is_array($j) ? $j : $default;
}

function mdfe_persist_percurso(PDO $conn, int $empresaId, int $mdfeId, $ufsPercurso): void
{
    if ($mdfeId <= 0) {
        return;
    }
    mdfe_ensure_extended_schema($conn);
    $lista = [];
    if (is_string($ufsPercurso)) {
        $s = trim($ufsPercurso);
        if ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
            $lista = mdfe_parse_json_any($s, []);
        } elseif ($s !== '') {
            $lista = array_map('trim', explode(',', $s));
        }
    } elseif (is_array($ufsPercurso)) {
        $lista = $ufsPercurso;
    }
    $ufs = [];
    foreach ($lista as $uf) {
        $u = strtoupper(trim((string) $uf));
        if (strlen($u) === 2 && !in_array($u, $ufs, true)) {
            $ufs[] = $u;
        }
    }
    $conn->prepare('UPDATE fiscal_mdfe SET ufs_percurso = ?, updated_at = ? WHERE id = ? AND empresa_id = ?')
        ->execute([$ufs ? json_encode($ufs, JSON_UNESCAPED_UNICODE) : null, date('Y-m-d H:i:s'), $mdfeId, $empresaId]);
}

/** @param array<int,array<string,mixed>> $condutores */
function mdfe_persist_condutores(PDO $conn, int $empresaId, int $mdfeId, array $condutores): void
{
    if ($mdfeId <= 0) {
        return;
    }
    mdfe_ensure_extended_schema($conn);
    $conn->prepare('DELETE FROM fiscal_mdfe_condutores WHERE mdfe_id = ? AND empresa_id = ?')->execute([$mdfeId, $empresaId]);
    if (!$condutores) {
        return;
    }
    $st = $conn->prepare('INSERT INTO fiscal_mdfe_condutores (mdfe_id, empresa_id, nome, cpf, ordem, payload_json) VALUES (?,?,?,?,?,?)');
    $ordem = 1;
    foreach ($condutores as $c) {
        if (!is_array($c)) {
            continue;
        }
        $nome = trim((string) ($c['nome'] ?? $c['xNome'] ?? ''));
        $cpf = preg_replace('/\D/', '', (string) ($c['cpf'] ?? $c['CPF'] ?? ''));
        if ($nome === '' || strlen($cpf) !== 11) {
            continue;
        }
        $st->execute([$mdfeId, $empresaId, $nome, $cpf, $ordem++, json_encode($c, JSON_UNESCAPED_UNICODE)]);
    }
}

/** @param array<int,array<string,mixed>> $reboques */
function mdfe_persist_reboques(PDO $conn, int $empresaId, int $mdfeId, array $reboques): void
{
    if ($mdfeId <= 0) {
        return;
    }
    mdfe_ensure_extended_schema($conn);
    $conn->prepare('DELETE FROM fiscal_mdfe_reboques WHERE mdfe_id = ? AND empresa_id = ?')->execute([$mdfeId, $empresaId]);
    if (!$reboques) {
        return;
    }
    $st = $conn->prepare('
        INSERT INTO fiscal_mdfe_reboques (mdfe_id, empresa_id, placa, renavam, tara, cap_kg, tp_rod, tp_car, uf, cod_interno, payload_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ');
    foreach ($reboques as $r) {
        if (!is_array($r)) {
            continue;
        }
        $placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($r['placa'] ?? '')));
        if ($placa === '') {
            continue;
        }
        $st->execute([
            $mdfeId,
            $empresaId,
            $placa,
            preg_replace('/\D/', '', (string) ($r['renavam'] ?? '')) ?: null,
            (int) ($r['tara'] ?? $r['taraKg'] ?? 0) ?: null,
            (int) ($r['capKg'] ?? $r['capacidadeKg'] ?? 0) ?: null,
            str_pad(substr((string) ($r['tpRod'] ?? $r['tipoRodado'] ?? '01'), 0, 2), 2, '0', STR_PAD_LEFT),
            str_pad(substr((string) ($r['tpCar'] ?? $r['tipoCarroceria'] ?? '00'), 0, 2), 2, '0', STR_PAD_LEFT),
            strtoupper(substr((string) ($r['uf'] ?? ''), 0, 2)) ?: null,
            trim((string) ($r['codInterno'] ?? $r['cod_interno'] ?? '')) ?: null,
            json_encode($r, JSON_UNESCAPED_UNICODE),
        ]);
    }
}

/** @param array<int,array<string,mixed>> $documentos */
function mdfe_persist_documentos_extended(PDO $conn, int $empresaId, int $mdfeId, array $documentos): void
{
    if ($mdfeId <= 0) {
        return;
    }
    mdfe_ensure_extended_schema($conn);
    $conn->prepare('DELETE FROM fiscal_mdfe_documentos WHERE mdfe_id = ? AND empresa_id = ?')->execute([$mdfeId, $empresaId]);
    if (!$documentos) {
        return;
    }
    $st = $conn->prepare('
        INSERT INTO fiscal_mdfe_documentos
            (mdfe_id, empresa_id, municipio_descarga, cod_mun_descarga, chave_nfe, chave_cte, cte_id, nfe_id, numero_nfe, payload_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($documentos as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $chCte = preg_replace('/\D/', '', (string) ($doc['chaveCte'] ?? $doc['chave_cte'] ?? ''));
        $chNfe = preg_replace('/\D/', '', (string) ($doc['chaveNfe'] ?? $doc['chave_nfe'] ?? ''));
        $mun = trim((string) ($doc['municipioDescarga'] ?? $doc['municipio_descarga'] ?? ''));
        $cMun = preg_replace('/\D/', '', (string) ($doc['codMunDescarga'] ?? $doc['cod_mun_descarga'] ?? ''));
        $cteId = (int) ($doc['cteId'] ?? $doc['cte_id'] ?? 0);
        $nfeId = (int) ($doc['nfeId'] ?? $doc['nfe_id'] ?? 0);
        if ($chCte === '' && $chNfe === '' && $mun === '' && $cteId <= 0 && $nfeId <= 0) {
            continue;
        }
        if ($cteId <= 0 && strlen($chCte) === 44) {
            $stL = $conn->prepare('SELECT id FROM fiscal_cte WHERE empresa_id = ? AND chave_acesso = ? LIMIT 1');
            $stL->execute([$empresaId, $chCte]);
            $cteId = (int) $stL->fetchColumn();
        }
        if ($nfeId <= 0 && strlen($chNfe) === 44) {
            $stL = $conn->prepare('SELECT id FROM fiscal_nfe_clientes WHERE empresa_id = ? AND chave_acesso = ? LIMIT 1');
            $stL->execute([$empresaId, $chNfe]);
            $nfeId = (int) $stL->fetchColumn();
        }
        $st->execute([
            $mdfeId,
            $empresaId,
            $mun !== '' ? $mun : null,
            strlen($cMun) === 7 ? $cMun : null,
            strlen($chNfe) === 44 ? $chNfe : null,
            strlen($chCte) === 44 ? $chCte : null,
            $cteId > 0 ? $cteId : null,
            $nfeId > 0 ? $nfeId : null,
            trim((string) ($doc['numeroNfe'] ?? $doc['numero_nfe'] ?? '')) ?: null,
            json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

/** @return array{ctes:array<int,array<string,mixed>>,nfes:array<int,array<string,mixed>>} */
function mdfe_listar_documentos_disponiveis(PDO $conn, int $empresaId, int $limit = 100): array
{
    $lim = max(1, min(200, $limit));
    $stCte = $conn->prepare("
        SELECT c.id, c.numero_cte, c.serie_cte, c.chave_acesso, c.valor_total, c.peso_total,
               c.origem_cidade, c.origem_estado, c.destino_cidade, c.destino_estado, c.data_emissao
        FROM fiscal_cte c
        WHERE c.empresa_id = ? AND c.status = 'autorizado'
          AND NOT EXISTS (
              SELECT 1 FROM fiscal_mdfe_cte mc
              INNER JOIN fiscal_mdfe m ON m.id = mc.mdfe_id AND m.empresa_id = c.empresa_id
              WHERE mc.cte_id = c.id AND m.status IN ('autorizado','pendente','rascunho')
          )
        ORDER BY c.id DESC LIMIT {$lim}
    ");
    $stCte->execute([$empresaId]);
    $ctes = $stCte->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stNfe = $conn->prepare("
        SELECT n.id, n.numero_nfe, n.serie_nfe, n.chave_acesso, n.valor_total, n.peso_carga,
               n.cliente_remetente, n.cliente_destinatario, n.data_emissao, n.status
        FROM fiscal_nfe_clientes n
        WHERE n.empresa_id = ? AND n.status IN ('recebida','em_transporte')
          AND NOT EXISTS (
              SELECT 1 FROM fiscal_mdfe_nfe mn
              INNER JOIN fiscal_mdfe m ON m.id = mn.mdfe_id AND m.empresa_id = n.empresa_id
              WHERE mn.nfe_id = n.id AND m.status IN ('autorizado','pendente','rascunho')
          )
        ORDER BY n.id DESC LIMIT {$lim}
    ");
    try {
        $stNfe->execute([$empresaId]);
        $nfes = $stNfe->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $nfes = [];
    }

    return ['ctes' => $ctes, 'nfes' => $nfes];
}

function sf_pef_desvincular_mdfe(int $empresaId, int $mdfeId): void
{
    if ($mdfeId <= 0) {
        return;
    }
    sf_pef_ensure_mdfe_link_schema();
    $conn = getConnection();
    $conn->prepare('UPDATE pef_ciot_operacoes SET mdfe_id = NULL, atualizado_em = NOW() WHERE empresa_id = ? AND mdfe_id = ?')
        ->execute([$empresaId, $mdfeId]);
}

/**
 * Encerra na ANTT operações PEF vinculadas ao MDF-e (não bloqueia se ANTT falhar).
 *
 * @return array<int,string> avisos
 */
function sf_pef_encerrar_vinculados_mdfe(int $empresaId, int $mdfeId): array
{
    $avisos = [];
    if ($mdfeId <= 0) {
        return $avisos;
    }
    sf_pef_ensure_mdfe_link_schema();
    $conn = getConnection();
    $st = $conn->prepare('SELECT id, id_operacao_transporte, codigo_identificacao FROM pef_ciot_operacoes WHERE empresa_id = ? AND mdfe_id = ? AND status = \'declarado\'');
    $st->execute([$empresaId, $mdfeId]);
    $ops = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$ops) {
        return $avisos;
    }
    try {
        $client = sf_pef_client_or_fail($empresaId);
    } catch (Throwable $e) {
        $avisos[] = 'PEF ANTT: ' . $e->getMessage();
        return $avisos;
    }
    foreach ($ops as $op) {
        $id = (int) ($op['id'] ?? 0);
        $body = [
            'IdOperacaoTransporte' => $op['id_operacao_transporte'],
            'CodigoIdentificacaoOperacao' => $op['codigo_identificacao'],
        ];
        try {
            $res = $client->encerramentoOperacaoTransporte($body);
            sf_pef_log($empresaId, $id, 'EncerramentoOperacaoTransporte', $body, $res['data'] ?? null, $res['http_code'] ?? null, $res['error'] ?? null);
            $data = $res['data'] ?? [];
            $ok = in_array((string) ($data['Codigo'] ?? ''), ['110', '111'], true);
            if ($ok) {
                $conn->prepare('UPDATE pef_ciot_operacoes SET status = \'encerrado\', payload_resposta = ?, codigo_antt = ?, mensagem_antt = ?, atualizado_em = NOW() WHERE id = ?')
                    ->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $data['Codigo'] ?? null, $data['Mensagem'] ?? null, $id]);
            } else {
                $avisos[] = 'CIOT #' . $id . ': ' . ($data['Mensagem'] ?? $res['error'] ?? 'encerramento ANTT não confirmado');
            }
        } catch (Throwable $e) {
            $avisos[] = 'CIOT #' . $id . ': ' . $e->getMessage();
        }
    }
    return $avisos;
}

function fiscal_mdfe_logo_path(int $empresaId): ?string
{
    $candidates = [];
    if (defined('FISCAL_REPORT_LOGO')) {
        $candidates[] = dirname(__DIR__) . '/' . ltrim((string) FISCAL_REPORT_LOGO, '/');
    }
    $candidates[] = dirname(__DIR__, 2) . '/logo.png';
    try {
        $conn = getConnection();
        $st = $conn->prepare('SELECT logo FROM empresa_clientes WHERE id = ? LIMIT 1');
        $st->execute([$empresaId]);
        $logo = trim((string) $st->fetchColumn());
        if ($logo !== '') {
            $candidates[] = dirname(__DIR__, 2) . '/' . ltrim($logo, '/');
        }
    } catch (Throwable $e) {
    }
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}
