<?php
/**
 * Helpers do wizard CT-e: schema, NF-e disponíveis, IBGE, partes rem/dest.
 */
declare(strict_types=1);

function cte_ensure_extended_schema(?PDO $conn = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $conn = $conn ?? getConnection();
    $sqlFile = dirname(__DIR__, 2) . '/sql/cte_layout_v4.sql';
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
            error_log('cte_ensure_extended_schema: ' . $e->getMessage());
        }
    }
}

/**
 * @return array{nfes:array<int,array<string,mixed>>,motoristas:array,veiculos:array}
 */
function cte_listar_dados_wizard(PDO $conn, int $empresaId, int $limit = 100): array
{
    cte_ensure_extended_schema($conn);
    $lim = max(1, min(200, $limit));

    $nfes = [];
    try {
        $st = $conn->prepare("
            SELECT n.id, n.numero_nfe, n.serie_nfe, n.chave_acesso, n.valor_total,
                   n.peso_carga, n.volumes, n.data_emissao, n.status,
                   n.cliente_cnpj, n.cliente_razao_social,
                   n.cliente_remetente, n.cnpj_remetente,
                   n.cliente_destinatario, n.cnpj_destinatario
            FROM fiscal_nfe_clientes n
            WHERE n.empresa_id = ?
              AND n.status = 'recebida'
              AND (n.cte_id IS NULL OR n.cte_id = 0)
            ORDER BY n.id DESC
            LIMIT {$lim}
        ");
        $st->execute([$empresaId]);
        $nfes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('cte_listar_dados_wizard nfes: ' . $e->getMessage());
    }

    $motoristas = [];
    $veiculos = [];
    try {
        $stM = $conn->prepare('SELECT id, nome, cpf FROM motoristas WHERE empresa_id = ? ORDER BY nome');
        $stM->execute([$empresaId]);
        $motoristas = $stM->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }
    try {
        $stV = $conn->prepare('SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ? ORDER BY placa');
        $stV->execute([$empresaId]);
        $veiculos = $stV->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }

    return ['nfes' => $nfes, 'motoristas' => $motoristas, 'veiculos' => $veiculos];
}

function cte_resolver_ibge(PDO $conn, string $cidade, string $uf): string
{
    $cidade = trim($cidade);
    $uf = strtoupper(trim(substr($uf, 0, 2)));
    if ($cidade === '') {
        return '';
    }
    try {
        if ($uf !== '') {
            $st = $conn->prepare('SELECT codigo_ibge FROM cidades WHERE uf = ? AND nome LIKE ? LIMIT 1');
            $st->execute([$uf, $cidade]);
            $ibge = (string) ($st->fetchColumn() ?: '');
            if ($ibge !== '') {
                return $ibge;
            }
        }
        $st = $conn->prepare('SELECT codigo_ibge FROM cidades WHERE nome LIKE ? LIMIT 1');
        $st->execute([$cidade]);
        return (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return '';
    }
}

/** @return array{cidade:string,uf:string} */
function cte_parse_origem_destino_texto(string $texto): array
{
    $texto = trim($texto);
    if ($texto === '') {
        return ['cidade' => '', 'uf' => ''];
    }
    if (preg_match('/^(.+?)\s*\/\s*([A-Za-z]{2})$/', $texto, $m)) {
        return ['cidade' => trim($m[1]), 'uf' => strtoupper($m[2])];
    }
    if (preg_match('/\b([A-Za-z]{2})\b$/', $texto, $m)) {
        return ['cidade' => trim(preg_replace('/\s*\/\s*[A-Za-z]{2}$/', '', $texto)), 'uf' => strtoupper($m[1])];
    }
    return ['cidade' => $texto, 'uf' => ''];
}

/**
 * Extrai remetente/destinatário do XML da NF-e (emit/dest).
 *
 * @return array{rem:array<string,string>,dest:array<string,string>}
 */
function cte_parse_parties_from_nfe_xml(?string $xml): array
{
    $empty = [
        'rem' => ['doc' => '', 'nome' => '', 'ie' => '', 'logradouro' => '', 'mun' => '', 'uf' => '', 'cmun' => ''],
        'dest' => ['doc' => '', 'nome' => '', 'ie' => '', 'logradouro' => '', 'mun' => '', 'uf' => '', 'cmun' => ''],
    ];
    if ($xml === null || trim($xml) === '') {
        return $empty;
    }
    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($xml);
    if ($sx === false) {
        return $empty;
    }
    $ns = 'http://www.portalfiscal.inf.br/nfe';
    $sx->registerXPathNamespace('nfe', $ns);
    $infNfe = $sx->xpath('//nfe:NFe/nfe:infNFe')[0]
        ?? $sx->xpath('//infNFe')[0]
        ?? null;
    if (!$infNfe) {
        return $empty;
    }
    $infNfe->registerXPathNamespace('nfe', $ns);

    $party = function ($node, string $enderTag) use ($ns): array {
        if (!$node) {
            return ['doc' => '', 'nome' => '', 'ie' => '', 'logradouro' => '', 'mun' => '', 'uf' => '', 'cmun' => ''];
        }
        $doc = '';
        foreach (['CNPJ', 'CPF'] as $tag) {
            $v = trim((string) ($node->{$tag} ?? $node->children($ns)->{$tag} ?? ''));
            if ($v !== '') {
                $doc = preg_replace('/\D/', '', $v);
                break;
            }
        }
        $nome = trim((string) ($node->xNome ?? $node->children($ns)->xNome ?? ''));
        $ie = trim((string) ($node->IE ?? $node->children($ns)->IE ?? ''));
        $ender = $node->{$enderTag} ?? $node->children($ns)->{$enderTag} ?? null;
        $log = '';
        $mun = '';
        $uf = '';
        $cmun = '';
        if ($ender) {
            $log = trim((string) ($ender->xLgr ?? $ender->children($ns)->xLgr ?? ''));
            $nro = trim((string) ($ender->nro ?? $ender->children($ns)->nro ?? ''));
            if ($nro !== '' && $nro !== 'S/N') {
                $log .= ($log !== '' ? ', ' : '') . $nro;
            }
            $mun = trim((string) ($ender->xMun ?? $ender->children($ns)->xMun ?? ''));
            $uf = strtoupper(substr(trim((string) ($ender->UF ?? $ender->children($ns)->UF ?? '')), 0, 2));
            $cmun = trim((string) ($ender->cMun ?? $ender->children($ns)->cMun ?? ''));
        }
        return [
            'doc' => $doc,
            'nome' => $nome,
            'ie' => $ie,
            'logradouro' => $log,
            'mun' => $mun,
            'uf' => $uf,
            'cmun' => $cmun,
        ];
    };

    $emit = $infNfe->emit ?? $infNfe->children($ns)->emit ?? null;
    $dest = $infNfe->dest ?? $infNfe->children($ns)->dest ?? null;

    return [
        'rem' => $party($emit, 'enderEmit'),
        'dest' => $party($dest, 'enderDest'),
    ];
}

/** @param array<string,mixed> $nfe */
function cte_parties_from_nfe_row(PDO $conn, array $nfe): array
{
    $fromXml = cte_parse_parties_from_nfe_xml((string) ($nfe['xml_nfe'] ?? ''));
    $rem = $fromXml['rem'];
    $dest = $fromXml['dest'];

    if ($rem['doc'] === '') {
        $rem['doc'] = preg_replace('/\D/', '', (string) ($nfe['cnpj_remetente'] ?? $nfe['cliente_cnpj'] ?? ''));
    }
    if ($rem['nome'] === '') {
        $rem['nome'] = trim((string) ($nfe['cliente_remetente'] ?? $nfe['cliente_razao_social'] ?? ''));
    }
    if ($dest['doc'] === '') {
        $dest['doc'] = preg_replace('/\D/', '', (string) ($nfe['cnpj_destinatario'] ?? ''));
    }
    if ($dest['nome'] === '') {
        $dest['nome'] = trim((string) ($nfe['cliente_destinatario'] ?? ''));
    }
    if ($rem['cmun'] === '' && $rem['mun'] !== '') {
        $rem['cmun'] = cte_resolver_ibge($conn, $rem['mun'], $rem['uf']);
    }
    if ($dest['cmun'] === '' && $dest['mun'] !== '') {
        $dest['cmun'] = cte_resolver_ibge($conn, $dest['mun'], $dest['uf']);
    }

    return ['rem' => $rem, 'dest' => $dest];
}

/** Persiste rem/dest em fiscal_cte_itens a partir das NF-es vinculadas. */
function cte_persistir_parties_itens(PDO $conn, int $cteId, array $nfes): void
{
    if (empty($nfes)) {
        return;
    }
    $first = $nfes[0];
    if (!is_array($first)) {
        return;
    }
    $parties = cte_parties_from_nfe_row($conn, $first);
    $rem = $parties['rem'];
    $dest = $parties['dest'];

    $stmt = $conn->prepare('SELECT id FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1');
    $stmt->execute([$cteId]);
    $ex = $stmt->fetch(PDO::FETCH_ASSOC);

    $fields = [
        'rem_cnpj' => $rem['doc'] !== '' ? $rem['doc'] : null,
        'rem_nome' => $rem['nome'] !== '' ? $rem['nome'] : null,
        'rem_ie' => $rem['ie'] !== '' ? $rem['ie'] : null,
        'rem_logradouro' => $rem['logradouro'] !== '' ? $rem['logradouro'] : null,
        'rem_mun' => $rem['mun'] !== '' ? $rem['mun'] : null,
        'rem_uf' => $rem['uf'] !== '' ? $rem['uf'] : null,
        'rem_cmun' => $rem['cmun'] !== '' ? $rem['cmun'] : null,
        'dest_cnpj' => $dest['doc'] !== '' ? $dest['doc'] : null,
        'dest_nome' => $dest['nome'] !== '' ? $dest['nome'] : null,
        'dest_ie' => $dest['ie'] !== '' ? $dest['ie'] : null,
        'dest_logradouro' => $dest['logradouro'] !== '' ? $dest['logradouro'] : null,
        'dest_mun' => $dest['mun'] !== '' ? $dest['mun'] : null,
        'dest_uf' => $dest['uf'] !== '' ? $dest['uf'] : null,
        'dest_cmun' => $dest['cmun'] !== '' ? $dest['cmun'] : null,
    ];

    if ($ex) {
        $sets = [];
        $vals = [];
        foreach ($fields as $col => $val) {
            $sets[] = "{$col} = ?";
            $vals[] = $val;
        }
        $vals[] = $cteId;
        $conn->prepare('UPDATE fiscal_cte_itens SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE cte_id = ?')
            ->execute($vals);
    }
}
