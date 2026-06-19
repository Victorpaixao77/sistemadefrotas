<?php
/**
 * Eventos CT-e: CC-e, desacordo tomador, comprovante entrega, persistência.
 */
declare(strict_types=1);

require_once __DIR__ . '/cte_wizard_helpers.php';
require_once __DIR__ . '/CTeService.php';

function cte_ensure_eventos_schema(?PDO $conn = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn = $conn ?? getConnection();
    $sqlFile = dirname(__DIR__, 2) . '/sql/cte_fase2_eventos.sql';
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
            error_log('cte_ensure_eventos_schema: ' . $e->getMessage());
        }
    }
}

/**
 * Monta array infCorrecao para sefazCCe (sped-cte).
 *
 * @return array<int,array<string,string>>
 */
function cte_build_inf_correcao(string $grupo, string $campo, string $valor, ?string $nroItem = null): array
{
    $item = [
        'grupoAlterado' => trim($grupo) !== '' ? trim($grupo) : 'compl',
        'campoAlterado' => trim($campo) !== '' ? trim($campo) : 'xObs',
        'valorAlterado' => trim($valor),
    ];
    if ($nroItem !== null && $nroItem !== '') {
        $item['nroItemAlterado'] = $nroItem;
    }
    return [$item];
}

/** Texto livre → correção em infAdic/infCpl */
function cte_build_inf_correcao_texto(string $texto): array
{
    return cte_build_inf_correcao('infAdic', 'infCpl', $texto);
}

function cte_proximo_seq_cce(PDO $conn, int $cteId, int $empresaId): int
{
    cte_ensure_eventos_schema($conn);
    $st = $conn->prepare('SELECT cce_seq FROM fiscal_cte WHERE id = ? AND empresa_id = ? LIMIT 1');
    $st->execute([$cteId, $empresaId]);
    $seq = (int) ($st->fetchColumn() ?: 0);
    return max(1, $seq + 1);
}

function cte_registrar_evento(
    PDO $conn,
    int $empresaId,
    int $cteId,
    string $tpEvento,
    int $nSeq,
    array $payload = []
): int {
    cte_ensure_eventos_schema($conn);
    $conn->prepare('
        INSERT INTO fiscal_cte_eventos (empresa_id, cte_id, tp_evento, n_seq_evento, status, payload_json, criado_em)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ')->execute([
        $empresaId,
        $cteId,
        $tpEvento,
        $nSeq,
        'pendente',
        $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
    ]);
    return (int) $conn->lastInsertId();
}

function cte_atualizar_evento(
    PDO $conn,
    int $eventoId,
    int $empresaId,
    string $status,
    ?string $protocolo,
    ?string $xmlRetorno,
    ?string $motivo = null
): void {
    $conn->prepare('
        UPDATE fiscal_cte_eventos
        SET status = ?, protocolo_evento = ?, xml_retorno = ?, motivo_rejeicao = ?
        WHERE id = ? AND empresa_id = ?
    ')->execute([$status, $protocolo, $xmlRetorno, $motivo, $eventoId, $empresaId]);
}

/** @return array<string,mixed>|null */
function cte_carregar_para_evento(PDO $conn, int $cteId, int $empresaId): ?array
{
    $st = $conn->prepare('SELECT * FROM fiscal_cte WHERE id = ? AND empresa_id = ? LIMIT 1');
    $st->execute([$cteId, $empresaId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function cte_hash_comprovante_entrega(string $chave, string $dhEntrega, ?string $assinaturaBase64 = null): string
{
    $chave = preg_replace('/\D/', '', $chave);
    $bin = $chave;
    if ($assinaturaBase64 !== null && $assinaturaBase64 !== '') {
        $decoded = base64_decode($assinaturaBase64, true);
        if ($decoded !== false) {
            $bin .= $decoded;
        }
    } else {
        $bin .= $dhEntrega;
    }
    return base64_encode(hash('sha1', $bin, true));
}

/** @return array<int,string> */
function cte_chaves_nfe_vinculadas(PDO $conn, int $cteId, int $empresaId): array
{
    $out = [];
    try {
        $st = $conn->prepare('SELECT chave_acesso FROM fiscal_nfe_clientes WHERE cte_id = ? AND empresa_id = ?');
        $st->execute([$cteId, $empresaId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ch) {
            $ch = preg_replace('/\D/', '', (string) $ch);
            if (strlen($ch) === 44) {
                $out[] = $ch;
            }
        }
    } catch (Throwable $e) {
    }
    return $out;
}

function cte_processar_cce(PDO $conn, int $empresaId, int $cteId, array $infCorrecao, ?int $nSeq = null): array
{
    $cte = cte_carregar_para_evento($conn, $cteId, $empresaId);
    if (!$cte) {
        return ['success' => false, 'message' => 'CT-e não encontrado.'];
    }
    if (strtolower((string) ($cte['status'] ?? '')) !== 'autorizado') {
        return ['success' => false, 'message' => 'CC-e só para CT-e autorizado.'];
    }
    $chave = preg_replace('/\D/', '', (string) ($cte['chave_acesso'] ?? ''));
    if (strlen($chave) !== 44) {
        return ['success' => false, 'message' => 'Chave inválida.'];
    }
    if (empty($infCorrecao)) {
        return ['success' => false, 'message' => 'Informe os dados da correção.'];
    }

    $seq = $nSeq ?? cte_proximo_seq_cce($conn, $cteId, $empresaId);
    $evId = cte_registrar_evento($conn, $empresaId, $cteId, '110110', $seq, ['infCorrecao' => $infCorrecao]);

    try {
        $svc = new CTeService($empresaId);
        $resp = $svc->enviarCartaCorrecaoCTe($chave, $infCorrecao, $seq);
    } catch (Throwable $e) {
        cte_atualizar_evento($conn, $evId, $empresaId, 'rejeitado', null, null, $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage(), 'evento_id' => $evId];
    }

    $xmlRet = (string) ($resp['response_xml'] ?? '');
    if (!empty($resp['success'])) {
        cte_atualizar_evento($conn, $evId, $empresaId, 'aceito', $resp['protocolo_evento'] ?? null, $xmlRet ?: null, null);
        $conn->prepare('UPDATE fiscal_cte SET cce_seq = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?')
            ->execute([$seq, $cteId, $empresaId]);
        return array_merge($resp, ['success' => true, 'evento_id' => $evId, 'n_seq_evento' => $seq]);
    }

    cte_atualizar_evento($conn, $evId, $empresaId, 'rejeitado', null, $xmlRet ?: null, $resp['message'] ?? 'Rejeitado');
    return array_merge($resp, ['success' => false, 'evento_id' => $evId]);
}

function cte_processar_desacordo(PDO $conn, int $empresaId, int $cteId, string $xObs, string $ufEvento = 'RS'): array
{
    $cte = cte_carregar_para_evento($conn, $cteId, $empresaId);
    if (!$cte) {
        return ['success' => false, 'message' => 'CT-e não encontrado.'];
    }
    $xObs = trim($xObs);
    if (strlen($xObs) < 15) {
        return ['success' => false, 'message' => 'Observação mínima de 15 caracteres (MOC 610110).'];
    }
    $chave = preg_replace('/\D/', '', (string) ($cte['chave_acesso'] ?? ''));
    if (strlen($chave) !== 44) {
        return ['success' => false, 'message' => 'Chave inválida.'];
    }

    $evId = cte_registrar_evento($conn, $empresaId, $cteId, '610110', 1, ['xObs' => $xObs, 'uf' => $ufEvento]);

    try {
        $svc = new CTeService($empresaId);
        $resp = $svc->enviarDesacordoTomador($chave, $xObs, $ufEvento);
    } catch (Throwable $e) {
        cte_atualizar_evento($conn, $evId, $empresaId, 'rejeitado', null, null, $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage(), 'evento_id' => $evId];
    }

    $xmlRet = (string) ($resp['response_xml'] ?? '');
    if (!empty($resp['success'])) {
        cte_atualizar_evento($conn, $evId, $empresaId, 'aceito', $resp['protocolo_evento'] ?? null, $xmlRet ?: null, null);
        try {
            $conn->prepare('UPDATE fiscal_cte_itens SET ult_protocolo_desacordo = ?, updated_at = NOW() WHERE cte_id = ?')
                ->execute([$resp['protocolo_evento'] ?? null, $cteId]);
        } catch (Throwable $e) {
        }
        return array_merge($resp, ['success' => true, 'evento_id' => $evId]);
    }

    cte_atualizar_evento($conn, $evId, $empresaId, 'rejeitado', null, $xmlRet ?: null, $resp['message'] ?? 'Rejeitado');
    return array_merge($resp, ['success' => false, 'evento_id' => $evId]);
}

/** @param array<string,mixed> $dados */
function cte_processar_comprovante_entrega(PDO $conn, int $empresaId, int $cteId, array $dados): array
{
    $cte = cte_carregar_para_evento($conn, $cteId, $empresaId);
    if (!$cte) {
        return ['success' => false, 'message' => 'CT-e não encontrado.'];
    }
    if (strtolower((string) ($cte['status'] ?? '')) !== 'autorizado') {
        return ['success' => false, 'message' => 'Comprovante só para CT-e autorizado.'];
    }
    $chave = preg_replace('/\D/', '', (string) ($cte['chave_acesso'] ?? ''));
    $nProt = trim((string) ($cte['protocolo_autorizacao'] ?? ''));
    if (strlen($chave) !== 44 || $nProt === '') {
        return ['success' => false, 'message' => 'CT-e sem chave/protocolo de autorização.'];
    }

    $xNome = trim((string) ($dados['x_nome'] ?? $dados['nome_recebedor'] ?? ''));
    $nDoc = preg_replace('/\D/', '', (string) ($dados['n_doc'] ?? $dados['doc_recebedor'] ?? ''));
    if ($xNome === '' || $nDoc === '') {
        return ['success' => false, 'message' => 'Nome e documento do recebedor são obrigatórios.'];
    }

    $dhEntrega = trim((string) ($dados['dh_entrega'] ?? ''));
    if ($dhEntrega === '') {
        $dhEntrega = date('Y-m-d\TH:i:sP');
    }
    $lat = trim((string) ($dados['latitude'] ?? ''));
    $lon = trim((string) ($dados['longitude'] ?? ''));
    $assinatura = trim((string) ($dados['assinatura_base64'] ?? ''));
    $hash = cte_hash_comprovante_entrega($chave, $dhEntrega, $assinatura !== '' ? $assinatura : null);
    $nSeq = max(1, (int) ($dados['n_seq_evento'] ?? 1));

    $nfes = $dados['chaves_nfe'] ?? null;
    if (!is_array($nfes) || empty($nfes)) {
        $nfes = cte_chaves_nfe_vinculadas($conn, $cteId, $empresaId);
    }

    $evId = cte_registrar_evento($conn, $empresaId, $cteId, '110180', $nSeq, $dados);

    try {
        $svc = new CTeService($empresaId);
        $resp = $svc->enviarComprovanteEntrega(
            $chave,
            $nProt,
            $xNome,
            $nDoc,
            $hash,
            $lat !== '' ? $lat : null,
            $lon !== '' ? $lon : null,
            $nSeq,
            $dhEntrega,
            $nfes
        );
    } catch (Throwable $e) {
        cte_atualizar_evento($conn, $evId, $empresaId, 'rejeitado', null, null, $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage(), 'evento_id' => $evId];
    }

    $xmlRet = (string) ($resp['response_xml'] ?? '');
    if (!empty($resp['success'])) {
        cte_atualizar_evento($conn, $evId, $empresaId, 'aceito', $resp['protocolo_evento'] ?? null, $xmlRet ?: null, null);
        try {
            $conn->prepare('UPDATE fiscal_cte_itens SET ult_protocolo_ce = ?, updated_at = NOW() WHERE cte_id = ?')
                ->execute([$resp['protocolo_evento'] ?? null, $cteId]);
        } catch (Throwable $e) {
        }
        return array_merge($resp, ['success' => true, 'evento_id' => $evId]);
    }

    cte_atualizar_evento($conn, $evId, $empresaId, 'rejeitado', null, $xmlRet ?: null, $resp['message'] ?? 'Rejeitado');
    return array_merge($resp, ['success' => false, 'evento_id' => $evId]);
}

function cte_processar_inutilizacao(
    PDO $conn,
    int $empresaId,
    int $serie,
    int $nIni,
    int $nFin,
    string $xJust
): array {
    $xJust = trim($xJust);
    if (strlen($xJust) < 15) {
        return ['success' => false, 'message' => 'Justificativa mínima de 15 caracteres.'];
    }
    if ($nIni < 1 || $nFin < $nIni) {
        return ['success' => false, 'message' => 'Faixa de numeração inválida.'];
    }

    try {
        $svc = new CTeService($empresaId);
        return $svc->inutilizarNumeracao($serie, $nIni, $nFin, $xJust);
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
