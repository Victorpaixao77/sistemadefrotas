<?php
/**
 * Carrega contexto completo do CT-e para montagem do XML layout 4.00.
 */
declare(strict_types=1);

require_once __DIR__ . '/cte_wizard_helpers.php';

class CteContextLoader
{
    private int $empresaId;
    private PDO $conn;

    public function __construct(int $empresaId, ?PDO $conn = null)
    {
        $this->empresaId = $empresaId;
        $this->conn = $conn ?? getConnection();
        cte_ensure_extended_schema($this->conn);
    }

    /** @return array<string,mixed> */
    public function loadById(int $cteId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM fiscal_cte WHERE id = :id AND empresa_id = :e LIMIT 1');
        $stmt->execute([':id' => $cteId, ':e' => $this->empresaId]);
        $cte = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cte) {
            throw new RuntimeException('CT-e não encontrado.');
        }

        $cfg = $this->conn->prepare('SELECT * FROM fiscal_config_empresa WHERE empresa_id = :e LIMIT 1');
        $cfg->execute([':e' => $this->empresaId]);
        $config = $cfg->fetch(PDO::FETCH_ASSOC) ?: [];

        $emp = $this->conn->prepare('SELECT * FROM empresa_clientes WHERE id = :id LIMIT 1');
        $emp->execute([':id' => $this->empresaId]);
        $empresa = $emp->fetch(PDO::FETCH_ASSOC) ?: [];

        $itens = [];
        $stIt = $this->conn->prepare('SELECT * FROM fiscal_cte_itens WHERE cte_id = ? LIMIT 1');
        $stIt->execute([$cteId]);
        $itens = $stIt->fetch(PDO::FETCH_ASSOC) ?: [];

        $nfes = $this->loadNfesVinculadas($cteId);

        if (empty($itens['rem_cnpj']) && !empty($nfes)) {
            cte_persistir_parties_itens($this->conn, $cteId, $nfes);
            $stIt->execute([$cteId]);
            $itens = $stIt->fetch(PDO::FETCH_ASSOC) ?: $itens;
        }

        $veiculo = [];
        $vid = (int) ($cte['veiculo_id'] ?? 0);
        if ($vid > 0) {
            $st = $this->conn->prepare('SELECT * FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$vid, $this->empresaId]);
            $veiculo = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $motorista = [];
        $mid = (int) ($cte['motorista_id'] ?? 0);
        if ($mid > 0) {
            $st = $this->conn->prepare('SELECT * FROM motoristas WHERE id = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$mid, $this->empresaId]);
            $motorista = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $loc = $this->resolverLocalidades($cte, $itens, $nfes, $config, $empresa);

        $rntrc = trim((string) ($cte['rntrc'] ?? $itens['rntrc'] ?? $config['rntrc'] ?? ''));
        $rntrc = preg_replace('/\D/', '', $rntrc);

        $rem = $this->partyFromItens($itens, 'rem');
        $dest = $this->partyFromItens($itens, 'dest');
        if ($rem['doc'] === '' && !empty($nfes)) {
            $p = cte_parties_from_nfe_row($this->conn, $nfes[0]);
            $rem = $p['rem'];
            $dest = $p['dest'];
        }

        $tpAmb = (($config['ambiente_sefaz'] ?? 'homologacao') === 'producao') ? '1' : '2';
        if ($tpAmb === '2') {
            $hom = 'CT-E EMITIDO EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL';
            if ($rem['nome'] !== '') {
                $rem['nome'] = $hom;
            }
            if ($dest['nome'] !== '') {
                $dest['nome'] = $hom;
            }
        }

        $valorFrete = (float) ($cte['valor_total'] ?? 0);
        if ($valorFrete <= 0 && isset($itens['valor_prestacao'])) {
            $valorFrete = (float) $itens['valor_prestacao'];
        }

        $peso = (float) ($cte['peso_carga'] ?? $cte['peso_total'] ?? 0);
        if ($peso <= 0) {
            foreach ($nfes as $n) {
                $peso += (float) ($n['peso_carga'] ?? 0);
            }
        }

        $valorCarga = 0.0;
        foreach ($nfes as $n) {
            $valorCarga += (float) ($n['valor_total'] ?? 0);
        }
        if ($valorCarga <= 0 && isset($itens['valor_carga'])) {
            $valorCarga = (float) $itens['valor_carga'];
        }

        return [
            'cte' => $cte,
            'config' => $config,
            'empresa' => $empresa,
            'itens' => $itens,
            'nfes' => $nfes,
            'veiculo' => $veiculo,
            'motorista' => $motorista,
            'rem' => $rem,
            'dest' => $dest,
            'tp_toma' => (string) ($itens['tp_toma'] ?? '0'),
            'rntrc' => $rntrc,
            'tp_amb' => $tpAmb,
            'cod_mun_env' => $loc['cod_mun_env'],
            'mun_env' => $loc['mun_env'],
            'uf_env' => $loc['uf_env'],
            'cod_mun_ini' => $loc['cod_mun_ini'],
            'mun_ini' => $loc['mun_ini'],
            'uf_ini' => $loc['uf_ini'],
            'cod_mun_fim' => $loc['cod_mun_fim'],
            'mun_fim' => $loc['mun_fim'],
            'uf_fim' => $loc['uf_fim'],
            'valor_frete' => $valorFrete,
            'peso_carga' => $peso,
            'valor_carga' => $valorCarga,
            'cfop' => trim((string) ($cte['cfop'] ?? '5353')) ?: '5353',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function loadNfesVinculadas(int $cteId): array
    {
        try {
            $st = $this->conn->prepare('
                SELECT *
                FROM fiscal_nfe_clientes
                WHERE cte_id = ? AND empresa_id = ?
                ORDER BY id ASC
            ');
            $st->execute([$cteId, $this->empresaId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows) {
                return $rows;
            }
        } catch (Throwable $e) {
        }

        $cte = $this->conn->prepare('SELECT nfe_ids FROM fiscal_cte WHERE id = ? LIMIT 1');
        $cte->execute([$cteId]);
        $raw = $cte->fetchColumn();
        $ids = [];
        if (is_string($raw) && $raw !== '') {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $ids = array_filter(array_map('intval', $dec));
            }
        }
        if (!$ids) {
            return [];
        }
        $ph = str_repeat('?,', count($ids) - 1) . '?';
        $st = $this->conn->prepare("SELECT * FROM fiscal_nfe_clientes WHERE id IN ($ph) AND empresa_id = ?");
        $st->execute(array_merge($ids, [$this->empresaId]));
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed> $cte
     * @param array<string,mixed> $itens
     * @param array<int,array<string,mixed>> $nfes
     * @param array<string,mixed> $config
     * @param array<string,mixed> $empresa
     * @return array<string,string>
     */
    private function resolverLocalidades(array $cte, array $itens, array $nfes, array $config, array $empresa): array
    {
        $out = [
            'cod_mun_env' => '', 'mun_env' => '', 'uf_env' => '',
            'cod_mun_ini' => '', 'mun_ini' => '', 'uf_ini' => '',
            'cod_mun_fim' => '', 'mun_fim' => '', 'uf_fim' => '',
        ];

        $out['cod_mun_env'] = trim((string) ($cte['cod_mun_env'] ?? $config['codigo_municipio'] ?? ''));
        $out['mun_env'] = trim((string) ($config['municipio'] ?? $empresa['cidade'] ?? $empresa['municipio'] ?? ''));
        $out['uf_env'] = strtoupper(substr(trim((string) ($config['uf'] ?? $empresa['estado'] ?? $empresa['uf'] ?? 'PR')), 0, 2));

        if ($out['cod_mun_env'] === '' && $out['mun_env'] !== '') {
            $out['cod_mun_env'] = cte_resolver_ibge($this->conn, $out['mun_env'], $out['uf_env']);
        }

        $iniCidade = trim((string) ($cte['origem_cidade'] ?? ''));
        $iniUf = strtoupper(trim((string) ($cte['origem_estado'] ?? '')));
        if ($iniCidade === '' && trim((string) ($cte['origem'] ?? '')) !== '') {
            $p = cte_parse_origem_destino_texto((string) $cte['origem']);
            $iniCidade = $p['cidade'];
            $iniUf = $p['uf'];
        }
        if ($iniCidade === '' && !empty($itens['rem_mun'])) {
            $iniCidade = (string) $itens['rem_mun'];
            $iniUf = (string) ($itens['rem_uf'] ?? '');
        }
        if ($iniCidade === '' && !empty($nfes)) {
            $p = cte_parties_from_nfe_row($this->conn, $nfes[0]);
            $iniCidade = $p['rem']['mun'];
            $iniUf = $p['rem']['uf'];
        }

        $fimCidade = trim((string) ($cte['destino_cidade'] ?? ''));
        $fimUf = strtoupper(trim((string) ($cte['destino_estado'] ?? '')));
        if ($fimCidade === '' && trim((string) ($cte['destino'] ?? '')) !== '') {
            $p = cte_parse_origem_destino_texto((string) $cte['destino']);
            $fimCidade = $p['cidade'];
            $fimUf = $p['uf'];
        }
        if ($fimCidade === '' && !empty($itens['dest_mun'])) {
            $fimCidade = (string) $itens['dest_mun'];
            $fimUf = (string) ($itens['dest_uf'] ?? '');
        }
        if ($fimCidade === '' && !empty($nfes)) {
            $p = cte_parties_from_nfe_row($this->conn, $nfes[0]);
            $fimCidade = $p['dest']['mun'];
            $fimUf = $p['dest']['uf'];
        }

        $out['mun_ini'] = $iniCidade;
        $out['uf_ini'] = $iniUf;
        $out['cod_mun_ini'] = trim((string) ($cte['cod_mun_ini'] ?? $itens['rem_cmun'] ?? ''));
        if ($out['cod_mun_ini'] === '' && $iniCidade !== '') {
            $out['cod_mun_ini'] = cte_resolver_ibge($this->conn, $iniCidade, $iniUf);
        }

        $out['mun_fim'] = $fimCidade;
        $out['uf_fim'] = $fimUf;
        $out['cod_mun_fim'] = trim((string) ($cte['cod_mun_fim'] ?? $itens['dest_cmun'] ?? ''));
        if ($out['cod_mun_fim'] === '' && $fimCidade !== '') {
            $out['cod_mun_fim'] = cte_resolver_ibge($this->conn, $fimCidade, $fimUf);
        }

        if ($out['cod_mun_env'] === '') {
            $out['cod_mun_env'] = $out['cod_mun_ini'] ?: '4115200';
            $out['mun_env'] = $out['mun_env'] ?: $out['mun_ini'] ?: 'Maringa';
            $out['uf_env'] = $out['uf_env'] ?: $out['uf_ini'] ?: 'PR';
        }

        return $out;
    }

    /** @param array<string,mixed> $itens @return array<string,string> */
    private function partyFromItens(array $itens, string $prefix): array
    {
        return [
            'doc' => preg_replace('/\D/', '', (string) ($itens[$prefix . '_cnpj'] ?? '')),
            'nome' => trim((string) ($itens[$prefix . '_nome'] ?? '')),
            'ie' => trim((string) ($itens[$prefix . '_ie'] ?? '')),
            'logradouro' => trim((string) ($itens[$prefix . '_logradouro'] ?? '')),
            'mun' => trim((string) ($itens[$prefix . '_mun'] ?? '')),
            'uf' => strtoupper(substr(trim((string) ($itens[$prefix . '_uf'] ?? '')), 0, 2)),
            'cmun' => trim((string) ($itens[$prefix . '_cmun'] ?? '')),
        ];
    }
}
