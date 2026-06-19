<?php
/**
 * Gera ou atualiza conta a pagar vinculada a uma manutenção concluída.
 */

if (!function_exists('sf_mnt_col_exists')) {
    function sf_mnt_col_exists(PDO $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $db = (string) $conn->query('SELECT DATABASE()')->fetchColumn();
        $st = $conn->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $st->execute([$db, $table, $column]);
        $cache[$key] = (int) $st->fetchColumn() > 0;

        return $cache[$key];
    }
}

if (!function_exists('sf_mnt_status_concluida')) {
    function sf_mnt_status_concluida(PDO $conn, int $status_id): bool
    {
        if ($status_id <= 0) {
            return false;
        }
        $st = $conn->prepare('SELECT nome FROM status_manutencao WHERE id = :id LIMIT 1');
        $st->execute(['id' => $status_id]);
        $nome = (string) $st->fetchColumn();

        return $nome !== '' && stripos($nome, 'conclu') !== false;
    }
}

if (!function_exists('sf_mnt_status_conta_pendente')) {
    function sf_mnt_status_conta_pendente(PDO $conn): ?int
    {
        $st = $conn->query(
            "SELECT id FROM status_contas_pagar WHERE UPPER(TRIM(nome)) LIKE '%PENDENTE%' ORDER BY id LIMIT 1"
        );
        $id = $st ? $st->fetchColumn() : false;

        return $id ? (int) $id : null;
    }
}

if (!function_exists('sf_mnt_resolve_fornecedor_id')) {
    function sf_mnt_resolve_fornecedor_id(PDO $conn, int $empresa_id, string $nome): ?int
    {
        $nome = trim($nome);
        if ($nome === '' || strcasecmp($nome, 'Sem fornecedor') === 0) {
            return null;
        }
        $st = $conn->prepare(
            'SELECT id FROM fornecedores WHERE empresa_id = :e AND situacao = :s AND (nome = :n OR razao_social = :n OR nome_fantasia = :n) LIMIT 1'
        );
        $st->execute(['e' => $empresa_id, 's' => 'A', 'n' => $nome]);
        $id = $st->fetchColumn();

        return $id ? (int) $id : null;
    }
}

if (!function_exists('sf_mnt_sync_conta_pagar')) {
    /**
     * @param array<string, mixed> $data
     * @return array{conta_pagar_id: ?int, conta_message: ?string}
     */
    function sf_mnt_sync_conta_pagar(PDO $conn, int $empresa_id, int $manutencao_id, array $data, bool $gerar): array
    {
        $empty = ['conta_pagar_id' => null, 'conta_message' => null];
        if (!$gerar || $manutencao_id <= 0) {
            return $empty;
        }
        if (!sf_mnt_status_concluida($conn, (int) ($data['status_manutencao_id'] ?? 0))) {
            return $empty;
        }

        $valor = (float) ($data['valor'] ?? 0);
        if ($valor <= 0) {
            return ['conta_pagar_id' => null, 'conta_message' => 'Valor zero — conta a pagar não gerada.'];
        }

        $status_pendente = sf_mnt_status_conta_pendente($conn);
        if (!$status_pendente) {
            return ['conta_pagar_id' => null, 'conta_message' => 'Status "Pendente" não encontrado em contas a pagar.'];
        }

        $fornecedor = trim((string) ($data['fornecedor'] ?? ''));
        if ($fornecedor === '') {
            $fornecedor = 'Sem fornecedor';
        }

        $data_conclusao = !empty($data['data_conclusao']) ? (string) $data['data_conclusao'] : (string) ($data['data_manutencao'] ?? date('Y-m-d'));
        $vencimento = !empty($data['conta_vencimento'])
            ? (string) $data['conta_vencimento']
            : date('Y-m-d', strtotime($data_conclusao . ' +30 days'));

        $placa = '';
        $stPlaca = $conn->prepare('SELECT v.placa FROM manutencoes m JOIN veiculos v ON v.id = m.veiculo_id WHERE m.id = :id AND m.empresa_id = :e LIMIT 1');
        $stPlaca->execute(['id' => $manutencao_id, 'e' => $empresa_id]);
        $placa = (string) ($stPlaca->fetchColumn() ?: '');

        $descricao = trim((string) ($data['descricao'] ?? 'Manutenção'));
        $desc_conta = sprintf(
            'Manutenção #%d%s — %s',
            $manutencao_id,
            $placa !== '' ? ' (' . $placa . ')' : '',
            mb_substr($descricao, 0, 120, 'UTF-8')
        );

        $obs = 'Gerado automaticamente da manutenção #' . $manutencao_id;
        if (!empty($data['nota_fiscal'])) {
            $obs .= ' | NF: ' . trim((string) $data['nota_fiscal']);
        }

        $fornecedor_id = sf_mnt_resolve_fornecedor_id($conn, $empresa_id, $fornecedor);
        $has_fid = sf_mnt_col_exists($conn, 'contas_pagar', 'fornecedor_id');
        $has_link = sf_mnt_col_exists($conn, 'manutencoes', 'conta_pagar_id');

        $conta_existente = null;
        if ($has_link) {
            $st = $conn->prepare('SELECT conta_pagar_id FROM manutencoes WHERE id = :id AND empresa_id = :e LIMIT 1');
            $st->execute(['id' => $manutencao_id, 'e' => $empresa_id]);
            $cid = $st->fetchColumn();
            if ($cid) {
                $conta_existente = (int) $cid;
            }
        }

        if ($conta_existente) {
            $chk = $conn->prepare('SELECT id FROM contas_pagar WHERE id = :id AND empresa_id = :e LIMIT 1');
            $chk->execute(['id' => $conta_existente, 'e' => $empresa_id]);
            if (!$chk->fetch()) {
                $conta_existente = null;
            }
        }

        if ($conta_existente) {
            $set = 'fornecedor = :forn, descricao = :desc, valor = :val, data_vencimento = :venc, observacoes = :obs';
            $params = [
                'forn' => $fornecedor,
                'desc' => $desc_conta,
                'val' => $valor,
                'venc' => $vencimento,
                'obs' => $obs,
                'id' => $conta_existente,
                'e' => $empresa_id,
            ];
            if ($has_fid) {
                $set .= ', fornecedor_id = :fid';
                $params['fid'] = $fornecedor_id;
            }
            $conn->prepare("UPDATE contas_pagar SET {$set} WHERE id = :id AND empresa_id = :e")->execute($params);

            return ['conta_pagar_id' => $conta_existente, 'conta_message' => 'Conta a pagar atualizada.'];
        }

        $cols = 'empresa_id, fornecedor, descricao, valor, data_vencimento, data_pagamento, status_id, forma_pagamento_id, banco_id, observacoes';
        $ph = ':e, :forn, :desc, :val, :venc, NULL, :st, NULL, NULL, :obs';
        $params = [
            'e' => $empresa_id,
            'forn' => $fornecedor,
            'desc' => $desc_conta,
            'val' => $valor,
            'venc' => $vencimento,
            'st' => $status_pendente,
            'obs' => $obs,
        ];
        if ($has_fid) {
            $cols .= ', fornecedor_id';
            $ph .= ', :fid';
            $params['fid'] = $fornecedor_id;
        }

        $conn->prepare("INSERT INTO contas_pagar ({$cols}) VALUES ({$ph})")->execute($params);
        $nova_id = (int) $conn->lastInsertId();

        if ($has_link && $nova_id > 0) {
            $conn->prepare('UPDATE manutencoes SET conta_pagar_id = :cid WHERE id = :id AND empresa_id = :e')
                ->execute(['cid' => $nova_id, 'id' => $manutencao_id, 'e' => $empresa_id]);
        }

        return ['conta_pagar_id' => $nova_id ?: null, 'conta_message' => 'Conta a pagar gerada com sucesso.'];
    }
}
