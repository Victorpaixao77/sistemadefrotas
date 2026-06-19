<?php
/**
 * Helpers — módulo de pneus nível mercado (carcaça, DOT, fotos, filiais, inventário, IA).
 */
require_once __DIR__ . '/pneus_helpers.php';

if (!function_exists('pneus_table_exists')) {
    function pneus_table_exists(PDO $conn, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $st = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $st->execute([$table]);
            $cache[$table] = (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}

if (!function_exists('pneus_audit_context')) {
    function pneus_audit_context(): array
    {
        $uid = null;
        if (!empty($_SESSION['usuario_id'])) {
            $uid = (int) $_SESSION['usuario_id'];
        } elseif (!empty($_SESSION['motorista_id'])) {
            $uid = (int) $_SESSION['motorista_id'];
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return [
            'usuario_id' => $uid,
            'ip_origem' => is_string($ip) ? substr($ip, 0, 45) : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ];
    }
}

if (!function_exists('pneus_auditoria_registrar')) {
    function pneus_auditoria_registrar(PDO $conn, array $data): bool
    {
        if (!pneus_table_exists($conn, 'pneu_auditoria')) {
            return false;
        }
        $ctx = pneus_audit_context();
        $empresa_id = (int) ($data['empresa_id'] ?? 0);
        $pneu_id = (int) ($data['pneu_id'] ?? 0);
        $acao = trim((string) ($data['acao'] ?? ''));
        if ($empresa_id <= 0 || $pneu_id <= 0 || $acao === '') {
            return false;
        }
        $json = null;
        if (!empty($data['dados']) && is_array($data['dados'])) {
            $json = json_encode($data['dados'], JSON_UNESCAPED_UNICODE);
        }
        try {
            $st = $conn->prepare(
                'INSERT INTO pneu_auditoria (empresa_id, pneu_id, acao, usuario_id, ip_origem, user_agent, dados_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $empresa_id,
                $pneu_id,
                $acao,
                $data['usuario_id'] ?? $ctx['usuario_id'],
                $data['ip_origem'] ?? $ctx['ip_origem'],
                $data['user_agent'] ?? $ctx['user_agent'],
                $json,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('pneus_auditoria_registrar: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('pneus_dot_parse')) {
    /** Interpreta DOT (ex.: 3224 = semana 32 de 2024). */
    function pneus_dot_parse(?string $dot): ?array
    {
        if ($dot === null || trim($dot) === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $dot);
        if (strlen($digits) < 4) {
            return null;
        }
        $ww = (int) substr($digits, -4, 2);
        $yy = (int) substr($digits, -2);
        $year = $yy >= 0 && $yy <= 99 ? (2000 + $yy) : null;
        if ($year === null || $ww < 1 || $ww > 53) {
            return null;
        }
        $fabricado = new DateTimeImmutable(sprintf('%04d-W%02d-1', $year, $ww));
        $idade_anos = (new DateTimeImmutable('today'))->diff($fabricado)->y
            + ((new DateTimeImmutable('today'))->diff($fabricado)->m / 12);
        $alerta = 'ok';
        if ($idade_anos >= 7) {
            $alerta = 'vencido';
        } elseif ($idade_anos >= 5) {
            $alerta = 'atencao_5anos';
        }
        return [
            'dot' => $dot,
            'semana' => $ww,
            'ano' => $year,
            'fabricado_em' => $fabricado->format('Y-m-d'),
            'idade_anos' => round($idade_anos, 1),
            'alerta' => $alerta,
        ];
    }
}

if (!function_exists('pneus_dot_alertas_frota')) {
    function pneus_dot_alertas_frota(PDO $conn, int $empresa_id): array
    {
        $st = $conn->prepare('SELECT id, numero_serie, dot FROM pneus WHERE empresa_id = ? AND dot IS NOT NULL AND TRIM(dot) <> \'\'');
        $st->execute([$empresa_id]);
        $out = ['atencao_5anos' => [], 'vencido' => []];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $info = pneus_dot_parse($row['dot']);
            if (!$info || $info['alerta'] === 'ok') {
                continue;
            }
            $item = [
                'pneu_id' => (int) $row['id'],
                'numero_serie' => $row['numero_serie'],
                'dot' => $row['dot'],
                'idade_anos' => $info['idade_anos'],
            ];
            $out[$info['alerta']][] = $item;
        }
        return $out;
    }
}

if (!function_exists('pneus_carcaca_resumo')) {
    function pneus_carcaca_resumo(PDO $conn, int $pneu_id, int $empresa_id): ?array
    {
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return null;
        }
        $st = $conn->prepare(
            'SELECT p.*, sp.nome AS status_nome FROM pneus p
             LEFT JOIN status_pneus sp ON sp.id = p.status_id
             WHERE p.id = ? AND p.empresa_id = ? LIMIT 1'
        );
        $st->execute([$pneu_id, $empresa_id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            return null;
        }
        $codigo = $p['carcaca_codigo'] ?? $p['numero_serie'] ?? '';
        $recapagens = (int) ($p['numero_recapagens'] ?? 0);
        $km = (int) ($p['km_rodado_total'] ?? 0);
        if ($km <= 0 && pneus_column_exists($conn, 'pneus', 'km_instalacao')) {
            $veiculo_id = pneus_get_veiculo_alocado($conn, $pneu_id, $empresa_id);
            if ($veiculo_id) {
                $km_v = pneus_get_veiculo_km($conn, $veiculo_id, $empresa_id);
                $ki = (int) ($p['km_instalacao'] ?? 0);
                if ($km_v !== null && $ki > 0) {
                    $km = max(0, $km_v - $ki);
                }
            }
        }
        $investido = (float) ($p['valor_compra'] ?? 0);
        if (pneus_table_exists($conn, 'pneu_movimentacoes')) {
            $sm = $conn->prepare(
                "SELECT COALESCE(SUM(custo), 0) FROM pneu_movimentacoes
                 WHERE pneu_id = ? AND empresa_id = ? AND tipo IN ('recapagem','manutencao')"
            );
            $sm->execute([$pneu_id, $empresa_id]);
            $investido += (float) $sm->fetchColumn();
        }
        $status_carcaca = 'Em operação';
        $sulco = pneus_get_sulco_atual($conn, $pneu_id);
        if ($sulco !== null && $sulco <= 1.6) {
            $status_carcaca = 'Sulco no limite — avaliar troca';
        } elseif ($recapagens >= 3) {
            $status_carcaca = 'Múltiplas recapagens — inspeção reforçada';
        } elseif ($recapagens < 3 && $sulco !== null && $sulco > 3) {
            $status_carcaca = 'Apta para recapagem';
        }
        $dot_info = pneus_dot_parse($p['dot'] ?? null);
        return [
            'codigo' => $codigo,
            'comprada_em' => $p['data_entrada'] ?? $p['created_at'] ?? null,
            'recapagens' => $recapagens,
            'km_acumulados' => $km,
            'valor_investido' => round($investido, 2),
            'status_carcaca' => $status_carcaca,
            'dot' => $dot_info,
            'marca' => $p['marca'] ?? null,
            'modelo' => $p['modelo'] ?? null,
            'medida' => $p['medida'] ?? null,
        ];
    }
}

if (!function_exists('pneus_validar_posicao_instalacao')) {
    function pneus_validar_posicao_instalacao(PDO $conn, int $pneu_id, ?int $posicao_id, int $empresa_id): array
    {
        if ($posicao_id === null || $posicao_id <= 0) {
            return ['ok' => true];
        }
        if (!pneus_column_exists($conn, 'posicoes_pneus', 'tipo_rodado')) {
            return ['ok' => true];
        }
        $st = $conn->prepare('SELECT tipo_rodado_ideal FROM pneus WHERE id = ? AND empresa_id = ? LIMIT 1');
        $st->execute([$pneu_id, $empresa_id]);
        $ideal = $st->fetchColumn();
        if (!$ideal || $ideal === 'qualquer') {
            return ['ok' => true];
        }
        $sp = $conn->prepare('SELECT nome, tipo_rodado FROM posicoes_pneus WHERE id = ? LIMIT 1');
        $sp->execute([$posicao_id]);
        $pos = $sp->fetch(PDO::FETCH_ASSOC);
        if (!$pos) {
            return ['ok' => true];
        }
        $tipo_pos = $pos['tipo_rodado'] ?? 'qualquer';
        if ($tipo_pos === 'qualquer' || $tipo_pos === $ideal) {
            return ['ok' => true];
        }
        return [
            'ok' => false,
            'error' => sprintf(
                'Pneu configurado como %s não pode ser instalado na posição %s (%s).',
                $ideal,
                $pos['nome'] ?? (string) $posicao_id,
                $tipo_pos
            ),
        ];
    }
}

if (!function_exists('pneus_motivos_remocao_listar')) {
    function pneus_motivos_remocao_listar(PDO $conn, int $empresa_id): array
    {
        if (!pneus_table_exists($conn, 'pneu_motivos_remocao')) {
            return [];
        }
        $st = $conn->prepare(
            'SELECT id, codigo, nome FROM pneu_motivos_remocao
             WHERE ativo = 1 AND (empresa_id IS NULL OR empresa_id = ?)
             ORDER BY id'
        );
        $st->execute([$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_estatisticas_remocao')) {
    function pneus_estatisticas_remocao(PDO $conn, int $empresa_id): array
    {
        if (!pneus_table_exists($conn, 'pneu_movimentacoes') || !pneus_column_exists($conn, 'pneu_movimentacoes', 'motivo_remocao_id')) {
            return [];
        }
        $st = $conn->prepare(
            "SELECT m.nome, m.codigo, COUNT(*) AS total
             FROM pneu_movimentacoes pm
             INNER JOIN pneu_motivos_remocao m ON m.id = pm.motivo_remocao_id
             WHERE pm.empresa_id = ? AND pm.tipo = 'remocao'
             GROUP BY m.id, m.nome, m.codigo
             ORDER BY total DESC"
        );
        $st->execute([$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_anexos_listar')) {
    function pneus_anexos_listar(PDO $conn, int $pneu_id, int $empresa_id): array
    {
        if (!pneus_table_exists($conn, 'pneu_anexos') || !pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return [];
        }
        $st = $conn->prepare(
            'SELECT id, tipo, arquivo, legenda, created_at FROM pneu_anexos
             WHERE pneu_id = ? AND empresa_id = ? ORDER BY created_at DESC'
        );
        $st->execute([$pneu_id, $empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_anexo_salvar_registro')) {
    function pneus_anexo_salvar_registro(PDO $conn, int $empresa_id, array $data): array
    {
        if (!pneus_table_exists($conn, 'pneu_anexos')) {
            return ['success' => false, 'error' => 'Migração pneus_mercado_v2 não aplicada'];
        }
        $pneu_id = (int) ($data['pneu_id'] ?? 0);
        $tipo = $data['tipo'] ?? 'outro';
        $arquivo = trim((string) ($data['arquivo'] ?? ''));
        if ($pneu_id <= 0 || $arquivo === '' || !pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Dados inválidos'];
        }
        $tipos = ['compra', 'instalacao', 'remocao', 'manutencao', 'sucata', 'inspecao', 'outro'];
        if (!in_array($tipo, $tipos, true)) {
            $tipo = 'outro';
        }
        $ctx = pneus_audit_context();
        $st = $conn->prepare(
            'INSERT INTO pneu_anexos (empresa_id, pneu_id, movimentacao_id, tipo, arquivo, legenda, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $empresa_id,
            $pneu_id,
            isset($data['movimentacao_id']) ? (int) $data['movimentacao_id'] : null,
            $tipo,
            $arquivo,
            isset($data['legenda']) ? trim((string) $data['legenda']) : null,
            $ctx['usuario_id'],
        ]);
        pneus_auditoria_registrar($conn, [
            'empresa_id' => $empresa_id,
            'pneu_id' => $pneu_id,
            'acao' => 'anexo_' . $tipo,
            'dados' => ['arquivo' => $arquivo],
        ]);
        return ['success' => true, 'id' => (int) $conn->lastInsertId()];
    }
}

if (!function_exists('pneus_garantias_listar')) {
    function pneus_garantias_listar(PDO $conn, int $pneu_id, int $empresa_id): array
    {
        if (!pneus_table_exists($conn, 'pneu_garantias')) {
            return [];
        }
        $st = $conn->prepare(
            'SELECT * FROM pneu_garantias WHERE pneu_id = ? AND empresa_id = ? ORDER BY validade_ate ASC'
        );
        $st->execute([$pneu_id, $empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_garantia_salvar')) {
    function pneus_garantia_salvar(PDO $conn, int $empresa_id, array $data): array
    {
        if (!pneus_table_exists($conn, 'pneu_garantias')) {
            return ['success' => false, 'error' => 'Migração não aplicada'];
        }
        $pneu_id = (int) ($data['pneu_id'] ?? 0);
        if ($pneu_id <= 0 || !pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Pneu inválido'];
        }
        $tipo = $data['tipo'] ?? 'fabricante';
        if (!in_array($tipo, ['fabricante', 'revenda', 'recapadora'], true)) {
            $tipo = 'fabricante';
        }
        $st = $conn->prepare(
            'INSERT INTO pneu_garantias (empresa_id, pneu_id, tipo, fornecedor_nome, fornecedor_id, validade_ate, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $empresa_id,
            $pneu_id,
            $tipo,
            isset($data['fornecedor_nome']) ? trim((string) $data['fornecedor_nome']) : null,
            isset($data['fornecedor_id']) ? (int) $data['fornecedor_id'] : null,
            !empty($data['validade_ate']) ? $data['validade_ate'] : null,
            isset($data['observacoes']) ? trim((string) $data['observacoes']) : null,
        ]);
        return ['success' => true, 'id' => (int) $conn->lastInsertId()];
    }
}

if (!function_exists('pneus_garantias_vencendo')) {
    function pneus_garantias_vencendo(PDO $conn, int $empresa_id, int $dias = 30): array
    {
        if (!pneus_table_exists($conn, 'pneu_garantias')) {
            return [];
        }
        $st = $conn->prepare(
            'SELECT g.*, p.numero_serie FROM pneu_garantias g
             INNER JOIN pneus p ON p.id = g.pneu_id AND p.empresa_id = g.empresa_id
             WHERE g.empresa_id = ? AND g.validade_ate IS NOT NULL
               AND g.validade_ate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY g.validade_ate ASC LIMIT 50'
        );
        $st->execute([$empresa_id, $dias]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_registrar_sucata')) {
    function pneus_registrar_sucata(PDO $conn, int $empresa_id, array $data): array
    {
        if (!pneus_table_exists($conn, 'pneu_sucata')) {
            return ['success' => false, 'error' => 'Migração não aplicada'];
        }
        $pneu_id = (int) ($data['pneu_id'] ?? 0);
        if ($pneu_id <= 0 || !pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Pneu inválido'];
        }
        if (pneus_esta_alocado($conn, $pneu_id)) {
            return ['success' => false, 'error' => 'Remova o pneu do veículo antes de registrar sucata'];
        }
        $ctx = pneus_audit_context();
        $st = $conn->prepare(
            'INSERT INTO pneu_sucata (empresa_id, pneu_id, motivo_remocao_id, motivo_texto, valor_recuperado, destino, observacoes, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $empresa_id,
            $pneu_id,
            isset($data['motivo_remocao_id']) ? (int) $data['motivo_remocao_id'] : null,
            isset($data['motivo_texto']) ? trim((string) $data['motivo_texto']) : null,
            isset($data['valor_recuperado']) ? (float) $data['valor_recuperado'] : null,
            isset($data['destino']) ? trim((string) $data['destino']) : null,
            isset($data['observacoes']) ? trim((string) $data['observacoes']) : null,
            $ctx['usuario_id'],
        ]);
        $status_desc = pneus_status_id_por_nome($conn, 'descartado');
        if ($status_desc === null) {
            $status_desc = pneus_status_id_por_nome($conn, 'sucata');
        }
        if ($status_desc !== null) {
            pneus_sync_status_estoque($conn, $pneu_id, $empresa_id, $status_desc, 0);
        }
        require_once __DIR__ . '/pneu_movimentacoes_helper.php';
        pneu_movimentacao_inserir($conn, [
            'empresa_id' => $empresa_id,
            'pneu_id' => $pneu_id,
            'tipo' => 'descarte',
            'observacoes' => $data['motivo_texto'] ?? 'Sucata registrada',
            'motivo_remocao_id' => isset($data['motivo_remocao_id']) ? (int) $data['motivo_remocao_id'] : null,
        ]);
        pneus_auditoria_registrar($conn, [
            'empresa_id' => $empresa_id,
            'pneu_id' => $pneu_id,
            'acao' => 'sucata',
            'dados' => $data,
        ]);
        return ['success' => true, 'id' => (int) $conn->lastInsertId()];
    }
}

if (!function_exists('pneus_registrar_inspecao')) {
    function pneus_registrar_inspecao(PDO $conn, int $empresa_id, array $data): array
    {
        if (!pneus_table_exists($conn, 'pneu_inspecoes')) {
            return ['success' => false, 'error' => 'Migração não aplicada'];
        }
        $pneu_id = isset($data['pneu_id']) ? (int) $data['pneu_id'] : null;
        $veiculo_id = isset($data['veiculo_id']) ? (int) $data['veiculo_id'] : null;
        if ($pneu_id && !pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Pneu inválido'];
        }
        if ($veiculo_id && !pneus_validar_veiculo_empresa($conn, $veiculo_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Veículo inválido'];
        }
        $ctx = pneus_audit_context();
        $origem = $data['origem'] ?? 'gestor';
        if (!in_array($origem, ['motorista', 'gestor', 'inventario'], true)) {
            $origem = 'gestor';
        }
        $st = $conn->prepare(
            'INSERT INTO pneu_inspecoes (empresa_id, pneu_id, veiculo_id, motorista_id, usuario_id, pressao_psi, sulco_mm, tem_corte, tem_bolha, observacoes, origem)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $empresa_id,
            $pneu_id ?: null,
            $veiculo_id ?: null,
            isset($data['motorista_id']) ? (int) $data['motorista_id'] : null,
            $ctx['usuario_id'],
            isset($data['pressao_psi']) ? (float) $data['pressao_psi'] : null,
            isset($data['sulco_mm']) ? (float) $data['sulco_mm'] : null,
            !empty($data['tem_corte']) ? 1 : 0,
            !empty($data['tem_bolha']) ? 1 : 0,
            isset($data['observacoes']) ? trim((string) $data['observacoes']) : null,
            $origem,
        ]);
        $id = (int) $conn->lastInsertId();
        if ($pneu_id && isset($data['sulco_mm']) && function_exists('pneus_registrar_medicao_sulco')) {
            require_once __DIR__ . '/pneus_repository.php';
            pneus_registrar_medicao_sulco($conn, $empresa_id, [
                'pneu_id' => $pneu_id,
                'sulco_mm' => (float) $data['sulco_mm'],
                'veiculo_id' => $veiculo_id,
                'observacoes' => 'Inspeção #' . $id,
            ]);
        }
        if ($pneu_id) {
            pneus_auditoria_registrar($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id' => $pneu_id,
                'acao' => 'inspecao',
                'dados' => ['inspecao_id' => $id],
            ]);
        }
        return ['success' => true, 'id' => $id];
    }
}

if (!function_exists('pneus_filiais_listar')) {
    function pneus_filiais_listar(PDO $conn, int $empresa_id): array
    {
        if (!pneus_table_exists($conn, 'filiais')) {
            return [];
        }
        $st = $conn->prepare('SELECT id, nome, codigo, ativo FROM filiais WHERE empresa_id = ? AND ativo = 1 ORDER BY nome');
        $st->execute([$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_filial_garantir_matriz')) {
    function pneus_filial_garantir_matriz(PDO $conn, int $empresa_id): ?int
    {
        if (!pneus_table_exists($conn, 'filiais')) {
            return null;
        }
        $st = $conn->prepare('SELECT id FROM filiais WHERE empresa_id = ? ORDER BY id LIMIT 1');
        $st->execute([$empresa_id]);
        $id = $st->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $ins = $conn->prepare('INSERT INTO filiais (empresa_id, nome, codigo) VALUES (?, ?, ?)');
        $ins->execute([$empresa_id, 'Matriz', 'MATRIZ']);
        return (int) $conn->lastInsertId();
    }
}

if (!function_exists('pneus_transferir_filial')) {
    function pneus_transferir_filial(PDO $conn, int $empresa_id, int $pneu_id, int $filial_destino_id, ?string $obs = null): array
    {
        if (!pneus_column_exists($conn, 'pneus', 'filial_id')) {
            return ['success' => false, 'error' => 'Migração não aplicada'];
        }
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Pneu inválido'];
        }
        $st = $conn->prepare('SELECT id FROM filiais WHERE id = ? AND empresa_id = ? AND ativo = 1');
        $st->execute([$filial_destino_id, $empresa_id]);
        if (!$st->fetch()) {
            return ['success' => false, 'error' => 'Filial inválida'];
        }
        $up = $conn->prepare('UPDATE pneus SET filial_id = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
        $up->execute([$filial_destino_id, $pneu_id, $empresa_id]);
        require_once __DIR__ . '/pneu_movimentacoes_helper.php';
        pneu_movimentacao_inserir($conn, [
            'empresa_id' => $empresa_id,
            'pneu_id' => $pneu_id,
            'tipo' => 'deslocamento',
            'observacoes' => $obs ?? ('Transferência para filial #' . $filial_destino_id),
            'filial_id' => $filial_destino_id,
        ]);
        pneus_auditoria_registrar($conn, [
            'empresa_id' => $empresa_id,
            'pneu_id' => $pneu_id,
            'acao' => 'transferencia_filial',
            'dados' => ['filial_id' => $filial_destino_id],
        ]);
        return ['success' => true];
    }
}

if (!function_exists('pneus_inventario_iniciar')) {
    function pneus_inventario_iniciar(PDO $conn, int $empresa_id, ?int $filial_id, ?string $titulo): array
    {
        if (!pneus_table_exists($conn, 'pneu_inventario_sessoes')) {
            return ['success' => false, 'error' => 'Migração não aplicada'];
        }
        $ctx = pneus_audit_context();
        $st = $conn->prepare(
            'INSERT INTO pneu_inventario_sessoes (empresa_id, filial_id, usuario_id, titulo) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$empresa_id, $filial_id, $ctx['usuario_id'], $titulo ?: 'Inventário ' . date('d/m/Y H:i')]);
        return ['success' => true, 'sessao_id' => (int) $conn->lastInsertId()];
    }
}

if (!function_exists('pneus_inventario_scan')) {
    function pneus_inventario_scan(PDO $conn, int $empresa_id, int $sessao_id, string $token_ou_id): array
    {
        if (!pneus_table_exists($conn, 'pneu_inventario_itens')) {
            return ['success' => false, 'error' => 'Migração não aplicada'];
        }
        $st = $conn->prepare('SELECT id FROM pneu_inventario_sessoes WHERE id = ? AND empresa_id = ? AND status = \'aberta\' LIMIT 1');
        $st->execute([$sessao_id, $empresa_id]);
        if (!$st->fetch()) {
            return ['success' => false, 'error' => 'Sessão inválida ou fechada'];
        }
        $pneu_id = 0;
        if (ctype_digit($token_ou_id)) {
            $pneu_id = (int) $token_ou_id;
        } elseif (pneus_column_exists($conn, 'pneus', 'qr_token')) {
            $sq = $conn->prepare('SELECT id FROM pneus WHERE qr_token = ? AND empresa_id = ? LIMIT 1');
            $sq->execute([trim($token_ou_id), $empresa_id]);
            $pneu_id = (int) ($sq->fetchColumn() ?: 0);
        }
        if ($pneu_id <= 0 || !pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Pneu não encontrado'];
        }
        $token = pneus_ensure_qr_token($conn, $pneu_id, $empresa_id);
        $ins = $conn->prepare(
            'INSERT INTO pneu_inventario_itens (sessao_id, pneu_id, qr_token) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE lido_em = NOW(), encontrado = 1'
        );
        $ins->execute([$sessao_id, $pneu_id, $token]);
        $sp = $conn->prepare('SELECT numero_serie, marca, medida FROM pneus WHERE id = ?');
        $sp->execute([$pneu_id]);
        $p = $sp->fetch(PDO::FETCH_ASSOC);
        return ['success' => true, 'pneu_id' => $pneu_id, 'pneu' => $p];
    }
}

if (!function_exists('pneus_inventario_resumo')) {
    function pneus_inventario_resumo(PDO $conn, int $empresa_id, int $sessao_id): array
    {
        $st = $conn->prepare(
            'SELECT s.*, COUNT(i.id) AS lidos FROM pneu_inventario_sessoes s
             LEFT JOIN pneu_inventario_itens i ON i.sessao_id = s.id
             WHERE s.id = ? AND s.empresa_id = ? GROUP BY s.id LIMIT 1'
        );
        $st->execute([$sessao_id, $empresa_id]);
        $sessao = $st->fetch(PDO::FETCH_ASSOC);
        if (!$sessao) {
            return ['success' => false];
        }
        $itens = $conn->prepare(
            'SELECT i.*, p.numero_serie, p.marca, p.medida FROM pneu_inventario_itens i
             INNER JOIN pneus p ON p.id = i.pneu_id WHERE i.sessao_id = ? ORDER BY i.lido_em DESC'
        );
        $itens->execute([$sessao_id]);
        $total_estoque = 0;
        if (function_exists('pneus_get_summary')) {
            require_once __DIR__ . '/pneus_repository.php';
            $sum = pneus_get_summary($conn, $empresa_id);
            $total_estoque = (int) ($sum['total_cadastro'] ?? $sum['estoque_disponivel'] ?? 0);
        }
        return [
            'success' => true,
            'sessao' => $sessao,
            'itens' => $itens->fetchAll(PDO::FETCH_ASSOC),
            'total_lidos' => (int) $sessao['lidos'],
            'referencia_total' => $total_estoque,
        ];
    }
}

if (!function_exists('pneus_previsao_compra_ia')) {
    function pneus_previsao_compra_ia(PDO $conn, int $empresa_id, int $dias = 60): array
    {
        require_once __DIR__ . '/pneus_repository.php';
        $previsao = pneus_previsao_compras($conn, $empresa_id, $dias);
        $total = 0;
        $detalhes = [];
        foreach ($previsao as $p) {
            $q = (int) ($p['sugestao_compra'] ?? 0);
            if ($q <= 0) {
                continue;
            }
            $total += $q;
            $detalhes[] = $p['medida'] . ': ' . $q;
        }
        $criticos = 0;
        $st = $conn->prepare('SELECT id FROM pneus WHERE empresa_id = ? LIMIT 200');
        $st->execute([$empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $prev = pneus_previsao_troca_sulco($conn, (int) $pid, $empresa_id);
            if (($prev['dias_restantes'] ?? 999) <= $dias) {
                $criticos++;
            }
        }
        $msg = $total > 0
            ? sprintf('Será necessário comprar aproximadamente %d pneu(s) nos próximos %d dias.', $total, $dias)
            : sprintf('Nenhuma compra urgente prevista para os próximos %d dias (revise estoque por medida).', $dias);
        if ($criticos > 0) {
            $msg .= ' ' . $criticos . ' pneu(s) com troca prevista por sulco no período.';
        }
        return [
            'dias' => $dias,
            'quantidade_sugerida' => $total,
            'criticos_sulco' => $criticos,
            'detalhes_medida' => $detalhes,
            'mensagem_ia' => $msg,
            'previsao' => $previsao,
        ];
    }
}

if (!function_exists('pneus_abastecimento_insights')) {
    /** Cruza consumo de combustível com desgaste de pneus por veículo. */
    function pneus_abastecimento_insights(PDO $conn, int $empresa_id): array
    {
        $insights = [];
        try {
            $st = $conn->prepare(
                "SELECT v.id, v.placa,
                    (SELECT MAX(a.km_atual) - MIN(a.km_atual) FROM abastecimentos a
                     WHERE a.veiculo_id = v.id AND a.empresa_id = v.empresa_id
                       AND a.data_abastecimento >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                       AND a.km_atual IS NOT NULL) AS km_periodo,
                    (SELECT SUM(a.litros) FROM abastecimentos a
                     WHERE a.veiculo_id = v.id AND a.empresa_id = v.empresa_id
                       AND a.data_abastecimento >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) AS litros
                 FROM veiculos v WHERE v.empresa_id = ? AND v.status = 'ativo' LIMIT 40"
            );
            $st->execute([$empresa_id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $vid = (int) $v['id'];
                $km = (int) ($v['km_periodo'] ?? 0);
                $litros = (float) ($v['litros'] ?? 0);
                if ($km < 500 || $litros <= 0) {
                    continue;
                }
                $consumo = $litros / ($km / 100);
                $pneus_veic = pneus_pneus_do_veiculo($conn, $vid, $empresa_id);
                $sulco_medio = null;
                $n = 0;
                foreach ($pneus_veic as $p) {
                    $s = pneus_get_sulco_atual($conn, (int) $p['id']);
                    if ($s !== null) {
                        $sulco_medio = ($sulco_medio ?? 0) + $s;
                        $n++;
                    }
                }
                if ($n > 0) {
                    $sulco_medio /= $n;
                }
                $alertas = [];
                if ($consumo > 45 && $sulco_medio !== null && $sulco_medio < 4) {
                    $alertas[] = 'Consumo elevado com desgaste acelerado — verificar pressão e alinhamento';
                }
                if ($consumo > 50) {
                    $alertas[] = 'Consumo muito alto — possível excesso de carga ou pressão incorreta';
                }
                if ($alertas) {
                    $insights[] = [
                        'veiculo_id' => $vid,
                        'placa' => $v['placa'],
                        'consumo_km_l' => round($consumo, 2),
                        'sulco_medio_mm' => $sulco_medio !== null ? round($sulco_medio, 2) : null,
                        'alertas' => $alertas,
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('pneus_abastecimento_insights: ' . $e->getMessage());
        }
        return $insights;
    }
}

if (!function_exists('pneus_pneus_do_veiculo')) {
    function pneus_pneus_do_veiculo(PDO $conn, int $veiculo_id, int $empresa_id): array
    {
        $out = [];
        try {
            $st = $conn->prepare(
                'SELECT p.id, p.numero_serie, p.marca FROM pneus p
                 INNER JOIN alocacoes_pneus_flexiveis apf ON apf.pneu_id = p.id AND apf.ativo = 1
                 INNER JOIN eixos_veiculos ev ON ev.id = apf.eixo_veiculo_id
                 WHERE ev.veiculo_id = ? AND apf.empresa_id = ?'
            );
            $st->execute([$veiculo_id, $empresa_id]);
            $out = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
        }
        return $out;
    }
}

if (!function_exists('pneus_manutencao_veiculo_afetados')) {
    function pneus_manutencao_veiculo_afetados(PDO $conn, int $veiculo_id, int $empresa_id, string $tipo_manutencao): array
    {
        $tipos_pneu = ['alinhamento', 'balanceamento', 'suspensao', 'pneu', 'pneus'];
        $t = mb_strtolower($tipo_manutencao, 'UTF-8');
        $afeta = false;
        foreach ($tipos_pneu as $tp) {
            if (strpos($t, $tp) !== false) {
                $afeta = true;
                break;
            }
        }
        if (!$afeta) {
            return ['afeta_pneus' => false, 'pneus' => []];
        }
        $pneus = pneus_pneus_do_veiculo($conn, $veiculo_id, $empresa_id);
        return ['afeta_pneus' => true, 'pneus' => $pneus, 'mensagem' => count($pneus) . ' pneu(s) instalado(s) podem ser impactados'];
    }
}

if (!function_exists('pneus_por_qr_ou_id')) {
    function pneus_por_qr_ou_id(PDO $conn, int $empresa_id, string $ref): ?array
    {
        if (ctype_digit($ref)) {
            $id = (int) $ref;
            if (pneus_validar_pneu_empresa($conn, $id, $empresa_id)) {
                $st = $conn->prepare('SELECT * FROM pneus WHERE id = ? LIMIT 1');
                $st->execute([$id]);
                return $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }
        if (pneus_column_exists($conn, 'pneus', 'qr_token')) {
            $st = $conn->prepare('SELECT * FROM pneus WHERE qr_token = ? AND empresa_id = ? LIMIT 1');
            $st->execute([trim($ref), $empresa_id]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return null;
    }
}
