<?php
/**
 * Carrega contexto completo do MDF-e (wizard + vínculos) para montagem do XML 4.00.
 */
declare(strict_types=1);

class MdfeContextLoader
{
    private int $empresaId;
    private PDO $conn;

    public function __construct(int $empresaId, ?PDO $conn = null)
    {
        $this->empresaId = $empresaId;
        $this->conn = $conn ?? getConnection();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $sqlFile = dirname(__DIR__, 2) . '/sql/fiscal_mdfe_meta_v1.sql';
        if (!is_file($sqlFile)) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $sql = file_get_contents($sqlFile);
            if ($sql) {
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if ($stmt !== '' && (stripos($stmt, 'CREATE') !== false || stripos($stmt, 'ALTER') !== false)) {
                        try {
                            $this->conn->exec($stmt);
                        } catch (Throwable $e) {
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('MdfeContextLoader schema: ' . $e->getMessage());
        }
    }

    /** @return array<string,mixed> */
    public function loadById(int $mdfeId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM fiscal_mdfe WHERE id = :id AND empresa_id = :e LIMIT 1');
        $stmt->execute([':id' => $mdfeId, ':e' => $this->empresaId]);
        $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mdfe) {
            throw new RuntimeException('MDF-e não encontrado.');
        }

        $cfg = $this->conn->prepare('SELECT * FROM fiscal_config_empresa WHERE empresa_id = :e LIMIT 1');
        $cfg->execute([':e' => $this->empresaId]);
        $config = $cfg->fetch(PDO::FETCH_ASSOC) ?: [];

        $emp = $this->conn->prepare('SELECT * FROM empresa_clientes WHERE id = :id LIMIT 1');
        $emp->execute([':id' => $this->empresaId]);
        $empresa = $emp->fetch(PDO::FETCH_ASSOC) ?: [];

        $veiculo = [];
        if (!empty($mdfe['veiculo_id'])) {
            $st = $this->conn->prepare('SELECT * FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1');
            $st->execute([(int) $mdfe['veiculo_id'], $this->empresaId]);
            $veiculo = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $motorista = [];
        if (!empty($mdfe['motorista_id'])) {
            $st = $this->conn->prepare('SELECT * FROM motoristas WHERE id = ? AND empresa_id = ? LIMIT 1');
            $st->execute([(int) $mdfe['motorista_id'], $this->empresaId]);
            $motorista = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $ctes = [];
        $stCte = $this->conn->prepare('
            SELECT c.chave_acesso, c.id
            FROM fiscal_mdfe_cte mc
            INNER JOIN fiscal_cte c ON c.id = mc.cte_id
            WHERE mc.mdfe_id = ?
        ');
        $stCte->execute([$mdfeId]);
        foreach ($stCte->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ch = preg_replace('/\D/', '', (string) ($row['chave_acesso'] ?? ''));
            if (strlen($ch) === 44) {
                $ctes[] = ['chave' => $ch, 'id' => (int) $row['id']];
            }
        }

        $nfes = [];
        try {
            $stNfe = $this->conn->prepare('
                SELECT n.chave_acesso, n.id
                FROM fiscal_mdfe_nfe mn
                INNER JOIN fiscal_nfe_clientes n ON n.id = mn.nfe_id
                WHERE mn.mdfe_id = ?
            ');
            $stNfe->execute([$mdfeId]);
            foreach ($stNfe->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ch = preg_replace('/\D/', '', (string) ($row['chave_acesso'] ?? ''));
                if (strlen($ch) === 44) {
                    $nfes[] = ['chave' => $ch, 'id' => (int) $row['id']];
                }
            }
        } catch (Throwable $e) {
        }

        $documentos = $this->loadTable('fiscal_mdfe_documentos', $mdfeId);
        if (!$documentos) {
            $documentos = $this->buildDocumentosFromLinks($ctes, $nfes, $mdfe);
        }

        $tipoEmitente = trim((string) ($mdfe['tipo_emitente'] ?? ''));
        $tipoTransportador = trim((string) ($mdfe['tipo_transportador'] ?? ''));
        if ($tipoEmitente === '') {
            [$tipoEmitente, $tipoTransportador] = $this->inferTiposFromLog($mdfeId, $ctes, $nfes, $tipoEmitente, $tipoTransportador);
        }

        $ufIni = strtoupper((string) ($mdfe['uf_inicio'] ?? ''));
        $ufFim = strtoupper((string) ($mdfe['uf_fim'] ?? ''));
        $munCarreg = trim((string) ($mdfe['municipio_carregamento'] ?? ''));
        $munDesc = trim((string) ($mdfe['municipio_descarregamento'] ?? ''));
        $cMunCarreg = trim((string) ($mdfe['cod_mun_carreg_ibge'] ?? ''));
        $cMunDesc = trim((string) ($mdfe['cod_mun_desc_ibge'] ?? ''));
        if ($cMunCarreg === '' && $munCarreg !== '') {
            $cMunCarreg = $this->ibgeMunicipio($munCarreg, $ufIni);
        }
        if ($cMunDesc === '' && $munDesc !== '') {
            $cMunDesc = $this->ibgeMunicipio($munDesc, $ufFim);
        }

        $pagamentos = $this->loadPagamentos($mdfeId);

        $ufsPercurso = [];
        $rawPercurso = $mdfe['ufs_percurso'] ?? null;
        if (is_string($rawPercurso) && $rawPercurso !== '') {
            $decoded = json_decode($rawPercurso, true);
            if (is_array($decoded)) {
                $ufsPercurso = array_values(array_filter(array_map(function ($u) {
                    return strtoupper(trim((string) $u));
                }, $decoded)));
            }
        }

        return [
            'mdfe' => $mdfe,
            'config' => $config,
            'empresa' => $empresa,
            'veiculo' => $veiculo,
            'motorista' => $motorista,
            'tipo_emitente' => $tipoEmitente !== '' ? $tipoEmitente : '1',
            'tipo_transportador' => $tipoTransportador,
            'uf_inicio' => $ufIni,
            'uf_fim' => $ufFim,
            'municipio_carregamento' => $munCarreg,
            'municipio_descarregamento' => $munDesc,
            'cod_mun_carreg_ibge' => $cMunCarreg,
            'cod_mun_desc_ibge' => $cMunDesc,
            'ctes' => $ctes,
            'nfes' => $nfes,
            'documentos' => $documentos,
            'ciots' => $this->loadTable('fiscal_mdfe_ciot', $mdfeId),
            'vales_pedagio' => $this->loadTable('fiscal_mdfe_vale_pedagio', $mdfeId),
            'contratantes' => $this->loadTable('fiscal_mdfe_contratantes', $mdfeId),
            'pagamentos' => $pagamentos,
            'seguros' => $this->loadTable('fiscal_mdfe_seguros', $mdfeId),
            'produtos' => $this->loadTable('fiscal_mdfe_produtos', $mdfeId),
            'lacres' => $this->loadTable('fiscal_mdfe_lacres', $mdfeId, 'numero_lacre'),
            'autorizados' => $this->loadTable('fiscal_mdfe_autorizados_download', $mdfeId),
            'ufs_percurso' => $ufsPercurso,
            'condutores' => $this->loadTable('fiscal_mdfe_condutores', $mdfeId),
            'reboques' => $this->loadTable('fiscal_mdfe_reboques', $mdfeId),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function loadTable(string $table, int $mdfeId, ?string $simpleCol = null): array
    {
        try {
            $st = $this->conn->prepare("SELECT * FROM {$table} WHERE mdfe_id = ? ORDER BY id ASC");
            $st->execute([$mdfeId]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function loadPagamentos(int $mdfeId): array
    {
        $rows = $this->loadTable('fiscal_mdfe_pagamentos', $mdfeId);
        if (!$rows) {
            return [];
        }
        $stComp = $this->conn->prepare('SELECT * FROM fiscal_mdfe_pagamento_componentes WHERE mdfe_id = ? ORDER BY id ASC');
        $stComp->execute([$mdfeId]);
        $comps = $stComp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $byPag = [];
        foreach ($comps as $c) {
            $pid = (int) ($c['pagamento_id'] ?? 0);
            $byPag[$pid][] = $c;
        }
        foreach ($rows as &$r) {
            $r['componentes'] = $byPag[(int) ($r['id'] ?? 0)] ?? [];
        }
        return $rows;
    }

    /** @param array<int,array<string,mixed>> $ctes @param array<int,array<string,mixed>> $nfes */
    private function buildDocumentosFromLinks(array $ctes, array $nfes, array $mdfe): array
    {
        $docs = [];
        $munDesc = trim((string) ($mdfe['municipio_descarregamento'] ?? ''));
        $cMun = trim((string) ($mdfe['cod_mun_desc_ibge'] ?? ''));
        foreach ($ctes as $c) {
            $docs[] = [
                'municipio_descarga' => $munDesc,
                'cod_mun_descarga' => $cMun,
                'chave_cte' => $c['chave'],
                'chave_nfe' => null,
            ];
        }
        foreach ($nfes as $n) {
            $docs[] = [
                'municipio_descarga' => $munDesc,
                'cod_mun_descarga' => $cMun,
                'chave_cte' => null,
                'chave_nfe' => $n['chave'],
            ];
        }
        return $docs;
    }

    /** @return array{0:string,1:string} */
    private function inferTiposFromLog(int $mdfeId, array $ctes, array $nfes, string $te, string $tt): array
    {
        try {
            $st = $this->conn->prepare('
                SELECT payload_json FROM mdfe_validacao_log
                WHERE mdfe_id = ? AND empresa_id = ?
                ORDER BY id DESC LIMIT 1
            ');
            $st->execute([$mdfeId, $this->empresaId]);
            $raw = $st->fetchColumn();
            if ($raw) {
                $p = json_decode((string) $raw, true);
                if (is_array($p)) {
                    $te = trim((string) ($p['tipo_emitente'] ?? $te));
                    $tt = trim((string) ($p['tipo_transportador'] ?? $tt));
                }
            }
        } catch (Throwable $e) {
        }
        if ($te === '') {
            $te = !empty($ctes) ? '1' : (!empty($nfes) ? '2' : '1');
        }
        return [$te, $tt];
    }

    private function ibgeMunicipio(string $nome, string $uf): string
    {
        $nome = trim($nome);
        $uf = strtoupper(trim($uf));
        if ($nome === '' || $uf === '') {
            return '0000000';
        }
        try {
            $st = $this->conn->prepare('SELECT codigo_ibge FROM cidades WHERE UPPER(uf) = :uf AND UPPER(nome) = :nome LIMIT 1');
            $st->execute([':uf' => $uf, ':nome' => strtoupper($nome)]);
            $ibge = (string) $st->fetchColumn();
            return strlen($ibge) === 7 ? $ibge : '0000000';
        } catch (Throwable $e) {
            return '0000000';
        }
    }
}
