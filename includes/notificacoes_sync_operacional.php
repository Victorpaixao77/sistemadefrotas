<?php
/**
 * Sincronização de notificações operacionais (sininho).
 */
require_once __DIR__ . '/notificacoes_helpers.php';

function sf_notif_sync_operacional_completo(PDO $conn, int $empresa_id): int
{
    if (!sf_notificacoes_sistema_ativo($conn, $empresa_id)) {
        return 0;
    }
    $n = 0;
    $n += sf_notif_sync_rotas_pendentes($conn, $empresa_id);
    $n += sf_notif_sync_abastecimentos_pendentes($conn, $empresa_id);
    $n += sf_notif_sync_despesas_viagem($conn, $empresa_id);
    $n += sf_notif_sync_fornecedores($conn, $empresa_id);
    $n += sf_notif_sync_checklists_problema($conn, $empresa_id);
    $n += sf_notif_sync_multas($conn, $empresa_id);
    $n += sf_notif_sync_contas_pagar($conn, $empresa_id);
    $n += sf_notif_sync_comissoes($conn, $empresa_id);
    $n += sf_notif_sync_fiscal_pendentes($conn, $empresa_id);
    sf_notif_sync_cnh($conn, $empresa_id);
    $n += sf_notif_sync_cnh_vencidas($conn, $empresa_id);
    $n += sf_notif_sync_veiculos_documentos($conn, $empresa_id);
    $n += sf_notif_sync_pneus_estoque($conn, $empresa_id);
    $n += sf_notif_sync_pneus_dot($conn, $empresa_id);
    $n += sf_notif_sync_pneus_garantia($conn, $empresa_id);
    sf_notif_sync_resolver_pendencias_resolvidas($conn, $empresa_id);
    return $n;
}

function sf_notif_sync_rotas_pendentes(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    try {
        $sql = "SELECT r.id, v.placa, m.nome AS motorista,
                   CONCAT(COALESCE(co.nome, r.estado_origem, '—'), ' → ', COALESCE(cd.nome, r.estado_destino, '—')) AS rota_txt
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            JOIN motoristas m ON r.motorista_id = m.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :e AND r.status = 'pendente'
            AND r.data_rota >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND r.data_rota <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            LIMIT 50";
        $st = $conn->prepare($sql);
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $rota) {
            $meta = ['acoes' => [
                ['acao' => 'aprovar_rota', 'label' => 'Aprovar', 'ref' => (int) $rota['id']],
                ['acao' => 'rejeitar_rota', 'label' => 'Rejeitar', 'ref' => (int) $rota['id']],
            ]];
            if (sf_notif_inserir($conn, $empresa_id, 'rota', 'Rota pendente',
                "Rota {$rota['rota_txt']} · {$rota['placa']}",
                ['ia_mensagem' => "Motorista {$rota['motorista']} aguarda aprovação.", 'referencia_id' => (int) $rota['id'],
                 'severidade' => 'aviso', 'meta_json' => $meta, 'horas_janela' => 48])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_rotas_pendentes: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_abastecimentos_pendentes(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    $sql = "SELECT a.id, v.placa, m.nome AS motorista
            FROM abastecimentos a
            JOIN veiculos v ON a.veiculo_id = v.id
            JOIN motoristas m ON a.motorista_id = m.id
            WHERE a.empresa_id = :e AND a.status = 'pendente'
            AND a.data_abastecimento >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            LIMIT 50";
    $st = $conn->prepare($sql);
    $st->execute([':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $meta = ['acoes' => [
            ['acao' => 'aprovar_abastecimento', 'label' => 'Aprovar', 'ref' => (int) $row['id']],
            ['acao' => 'rejeitar_abastecimento', 'label' => 'Rejeitar', 'ref' => (int) $row['id']],
        ]];
        if (sf_notif_inserir($conn, $empresa_id, 'abastecimento', 'Abastecimento pendente',
            "Veículo {$row['placa']} — revisar lançamento do motorista {$row['motorista']}",
            ['referencia_id' => (int) $row['id'], 'severidade' => 'aviso', 'meta_json' => $meta, 'horas_janela' => 48])) {
            $criadas++;
        }
    }
    return $criadas;
}

function sf_notif_sync_despesas_viagem(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    try {
        $st = $conn->prepare("
            SELECT d.id, d.rota_id, d.total_despviagem,
                   CONCAT(COALESCE(co.nome, r.estado_origem, '—'), ' → ', COALESCE(cd.nome, r.estado_destino, '—')) AS rota_txt,
                   m.nome AS motorista, v.placa
            FROM despesas_viagem d
            INNER JOIN rotas r ON r.id = d.rota_id AND r.empresa_id = d.empresa_id
            INNER JOIN motoristas m ON m.id = r.motorista_id
            INNER JOIN veiculos v ON v.id = r.veiculo_id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE d.empresa_id = :e AND d.status = 'pendente'
            ORDER BY d.id DESC
            LIMIT 30
        ");
        $st->execute([':e' => $empresa_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $valor = number_format((float) ($row['total_despviagem'] ?? 0), 2, ',', '.');
            $meta = ['acoes' => [
                ['acao' => 'aprovar_despesa', 'label' => 'Aprovar', 'ref' => (int) $row['id']],
                ['acao' => 'rejeitar_despesa', 'label' => 'Rejeitar', 'ref' => (int) $row['id']],
            ]];
            if (sf_notif_inserir($conn, $empresa_id, 'despesa', 'Despesa de viagem pendente',
                "{$row['rota_txt']} · {$row['placa']} · R$ {$valor}",
                ['ia_mensagem' => "Motorista {$row['motorista']} aguarda aprovação das despesas.",
                 'referencia_id' => (int) $row['id'], 'severidade' => 'aviso', 'meta_json' => $meta, 'horas_janela' => 48])) {
                $criadas++;
            }
        }
        if (count($rows) > 5) {
            $total = count($rows);
            if (sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'despesas_pendentes', 'despesa',
                'Várias despesas pendentes',
                "{$total}+ lançamento(s) aguardando aprovação",
                'Use os cards acima para aprovar ou rejeitar cada viagem.',
                'aviso', ['total' => $total])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_despesas_viagem: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_fornecedores(PDO $conn, int $empresa_id): int
{
    if (!is_file(__DIR__ . '/doc_validators.php')) {
        return 0;
    }
    require_once __DIR__ . '/doc_validators.php';
    $criadas = 0;
    try {
        $st = $conn->prepare("SELECT id, tipo, nome, cpf, cnpj, email, situacao, codigo_municipio_ibge
                              FROM fornecedores WHERE empresa_id = :e ORDER BY nome LIMIT 5000");
        $st->execute([':e' => $empresa_id]);
        $forns = $st->fetchAll(PDO::FETCH_ASSOC);
        $docCount = [];
        foreach ($forns as $f) {
            $doc = doc_only_digits($f['tipo'] === 'J' ? ($f['cnpj'] ?? '') : ($f['cpf'] ?? ''));
            if ($doc !== '') {
                $docCount[$doc] = ($docCount[$doc] ?? 0) + 1;
            }
        }
        $problemas = [];
        foreach ($forns as $f) {
            $ativo = ($f['situacao'] ?? 'A') === 'A';
            $isPJ = ($f['tipo'] ?? '') === 'J';
            $doc = doc_only_digits($isPJ ? ($f['cnpj'] ?? '') : ($f['cpf'] ?? ''));
            $motivo = null;
            $sev = 'aviso';
            if ($doc !== '') {
                $valido = $isPJ ? doc_validar_cnpj($doc) : doc_validar_cpf($doc);
                if (!$valido) {
                    $motivo = ($isPJ ? 'CNPJ' : 'CPF') . ' inválido';
                    $sev = 'critico';
                } elseif (($docCount[$doc] ?? 0) > 1) {
                    $motivo = ($isPJ ? 'CNPJ' : 'CPF') . ' duplicado';
                    $sev = 'critico';
                }
            }
            if ($motivo === null && $ativo && trim((string) ($f['email'] ?? '')) === '') {
                $motivo = 'Sem e-mail cadastrado';
            }
            if ($motivo === null && $ativo && trim((string) ($f['codigo_municipio_ibge'] ?? '')) === '') {
                $motivo = 'Sem município IBGE (fiscal)';
            }
            if ($motivo === null) {
                continue;
            }
            $problemas[] = ['id' => (int) $f['id'], 'nome' => (string) ($f['nome'] ?? '—'), 'msg' => $motivo, 'sev' => $sev];
        }
        usort($problemas, static fn($a, $b) => ($a['sev'] === 'critico' ? 0 : 1) <=> ($b['sev'] === 'critico' ? 0 : 1));
        foreach (array_slice($problemas, 0, 3) as $item) {
            if (sf_notif_inserir($conn, $empresa_id, 'fornecedor', 'Fornecedor com pendência',
                "{$item['nome']}: {$item['msg']}",
                ['referencia_id' => $item['id'], 'severidade' => $item['sev'],
                 'ia_mensagem' => 'Corrija o cadastro para evitar falhas na emissão fiscal.',
                 'grupo_chave' => 'forn_' . $item['id'], 'horas_janela' => 72])) {
                $criadas++;
            }
        }
        if (count($problemas) > 3) {
            if (sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'fornecedores_qualidade', 'fornecedor',
                'Cadastro de fornecedores incompleto',
                count($problemas) . ' fornecedor(es) com pendências no cadastro',
                'Revise documentos, e-mail e município IBGE na tela de fornecedores.',
                'aviso', ['total' => count($problemas)])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_fornecedores: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_checklists_problema(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    $camposCriticos = [
        'freios' => 'Freios', 'pneus' => 'Pneus', 'extintor' => 'Extintor', 'cnh' => 'CNH',
        'luzes' => 'Luzes', 'doc_veiculo' => 'CRLV', 'motorista_sobrio' => 'Motorista apto',
        'vazamentos' => 'Vazamentos', 'triangulo' => 'Triângulo',
    ];
    try {
        $cols = implode(', ', array_map(static fn($c) => "c.{$c}", array_keys($camposCriticos)));
        $conds = implode(' OR ', array_map(static fn($c) => "c.{$c} = 0", array_keys($camposCriticos)));
        $st = $conn->prepare("
            SELECT c.id, c.rota_id, c.data_checklist, m.nome AS motorista, v.placa,
                   {$cols}
            FROM checklist_viagem c
            JOIN motoristas m ON m.id = c.motorista_id
            JOIN veiculos v ON v.id = c.veiculo_id
            WHERE c.empresa_id = :e
            AND c.data_checklist >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND ({$conds})
            ORDER BY c.data_checklist DESC
            LIMIT 20
        ");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $falhas = [];
            foreach ($camposCriticos as $col => $label) {
                if ((int) ($row[$col] ?? 1) === 0) {
                    $falhas[] = $label;
                }
            }
            if ($falhas === []) {
                continue;
            }
            $msg = "{$row['placa']} · " . implode(', ', array_slice($falhas, 0, 4));
            $sev = in_array('Freios', $falhas, true) || in_array('Pneus', $falhas, true)
                || in_array('Motorista apto', $falhas, true) ? 'critico' : 'aviso';
            if (sf_notif_inserir($conn, $empresa_id, 'checklist', 'Checklist com irregularidade',
                $msg,
                ['referencia_id' => (int) $row['id'], 'severidade' => $sev,
                 'ia_mensagem' => "Motorista {$row['motorista']} reportou itens não conformes antes da viagem.",
                 'grupo_chave' => 'checklist_' . $row['id'], 'horas_janela' => 72])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_checklists_problema: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_multas(PDO $conn, int $empresa_id): int
{
    try {
        $st = $conn->prepare("
            SELECT COUNT(*) AS total, COALESCE(SUM(valor), 0) AS valor
            FROM multas
            WHERE empresa_id = :e AND status_pagamento != 'pago'
            AND (vencimento IS NULL OR vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        ");
        $st->execute([':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return 0;
        }
        $st2 = $conn->prepare("SELECT COUNT(*) FROM multas WHERE empresa_id = :e AND status_pagamento != 'pago' AND vencimento < CURDATE()");
        $st2->execute([':e' => $empresa_id]);
        $vencidas = (int) $st2->fetchColumn();
        $valor = number_format((float) ($row['valor'] ?? 0), 2, ',', '.');
        $sev = $vencidas > 0 ? 'critico' : 'aviso';
        return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'multas_pendentes', 'multa',
            'Multas em aberto',
            "{$total} multa(s) · R$ {$valor}" . ($vencidas > 0 ? " · {$vencidas} vencida(s)" : ''),
            'Quite ou conteste multas vencidas para evitar juros e pontos na CNH da frota.',
            $sev, ['total' => $total, 'vencidas' => $vencidas]) ? 1 : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function sf_notif_sync_contas_pagar(PDO $conn, int $empresa_id): int
{
    try {
        $st = $conn->prepare("
            SELECT COUNT(*) AS total, COALESCE(SUM(cp.valor), 0) AS valor
            FROM contas_pagar cp
            LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
            WHERE cp.empresa_id = :e AND s.nome IN ('Pendente', 'Vencida')
        ");
        $st->execute([':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return 0;
        }
        $st2 = $conn->prepare("
            SELECT COUNT(*) FROM contas_pagar cp
            LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
            WHERE cp.empresa_id = :e AND s.nome = 'Vencida'
        ");
        $st2->execute([':e' => $empresa_id]);
        $vencidas = (int) $st2->fetchColumn();
        $valor = number_format((float) ($row['valor'] ?? 0), 2, ',', '.');
        return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'contas_pagar', 'financeiro',
            'Contas a pagar em aberto',
            "{$total} conta(s) · R$ {$valor}" . ($vencidas > 0 ? " · {$vencidas} vencida(s)" : ''),
            'Priorize contas vencidas para manter o fluxo de caixa saudável.',
            $vencidas > 0 ? 'critico' : 'aviso',
            ['total' => $total, 'vencidas' => $vencidas, 'requer_financeiro' => true]) ? 1 : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function sf_notif_sync_comissoes(PDO $conn, int $empresa_id): int
{
    try {
        $st = $conn->prepare("
            SELECT COUNT(*) AS total, COALESCE(SUM(r.comissao), 0) AS valor
            FROM rotas r
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE r.empresa_id = :e AND r.comissao > 0 AND r.status = 'aprovado'
            AND (cp.status IS NULL OR cp.status = 'pendente')
        ");
        $st->execute([':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return 0;
        }
        $valor = number_format((float) ($row['valor'] ?? 0), 2, ',', '.');
        return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'comissoes_pendentes', 'comissao',
            'Comissões pendentes',
            "{$total} rota(s) · R$ {$valor} em aberto",
            'Regularize pagamentos de comissão para evitar desmotivação da equipe.',
            $total >= 10 ? 'critico' : 'aviso',
            ['total' => $total, 'requer_financeiro' => true]) ? 1 : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function sf_notif_sync_fiscal_pendentes(PDO $conn, int $empresa_id): int
{
    $pendentes = 0;
    $partes = [];
    foreach ([
        ['fiscal_nfe_clientes', 'NF-e', "status IN ('pendente','recebida','consultada_sefaz','recebida_manual')"],
        ['fiscal_cte', 'CT-e', "status IN ('pendente','rascunho')"],
        ['fiscal_mdfe', 'MDF-e', "status IN ('pendente','rascunho')"],
    ] as $def) {
        try {
            $conn->query("SELECT 1 FROM {$def[0]} LIMIT 1");
            $st = $conn->prepare("SELECT COUNT(*) FROM {$def[0]} WHERE empresa_id = :e AND {$def[2]}");
            $st->execute([':e' => $empresa_id]);
            $c = (int) $st->fetchColumn();
            if ($c > 0) {
                $pendentes += $c;
                $partes[] = "{$c} {$def[1]}";
            }
        } catch (Throwable $e) {
            // tabela fiscal ausente
        }
    }
    if ($pendentes <= 0) {
        return 0;
    }
    return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'fiscal_pendentes', 'fiscal',
        'Documentos fiscais pendentes',
        implode(' · ', $partes),
        'Emita ou regularize documentos fiscais para não travar operações e entregas.',
        'aviso', ['total' => $pendentes, 'requer_financeiro' => true]) ? 1 : 0;
}

function sf_notif_sync_cnh_vencidas(PDO $conn, int $empresa_id): int
{
    $st = $conn->prepare("
        SELECT COUNT(*) AS total, GROUP_CONCAT(nome ORDER BY data_validade_cnh SEPARATOR ', ') AS nomes
        FROM motoristas
        WHERE empresa_id = :e AND data_validade_cnh IS NOT NULL AND data_validade_cnh < CURDATE()
    ");
    $st->execute([':e' => $empresa_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $total = (int) ($row['total'] ?? 0);
    if ($total <= 0) {
        return 0;
    }
    $nomes = implode(', ', array_slice(explode(', ', (string) ($row['nomes'] ?? '')), 0, 5));
    return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'cnh_vencidas', 'cnh',
        'CNH vencida',
        "{$total} motorista(s): {$nomes}",
        'Motoristas com CNH vencida não podem conduzir legalmente — bloqueie rotas até regularizar.',
        'critico', ['total' => $total]) ? 1 : 0;
}

function sf_notif_sync_veiculos_documentos(PDO $conn, int $empresa_id): int
{
    $st = $conn->prepare("
        SELECT COUNT(*) FROM veiculos
        WHERE empresa_id = :e AND (documento IS NULL OR TRIM(documento) = '')
    ");
    $st->execute([':e' => $empresa_id]);
    $semDoc = (int) $st->fetchColumn();
    if ($semDoc < 3) {
        return 0;
    }
    return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'veiculos_sem_doc', 'documento',
        'Veículos sem CRLV',
        "{$semDoc} veículo(s) sem documento anexado",
        'Complete o cadastro documental para auditorias e fiscalização.',
        'aviso', ['total' => $semDoc]) ? 1 : 0;
}

function sf_notif_sync_pneus_estoque(PDO $conn, int $empresa_id): int
{
    if (!is_file(__DIR__ . '/pneus_repository.php')) {
        return 0;
    }
    require_once __DIR__ . '/pneus_repository.php';
    try {
        $summary = pneus_get_summary($conn, $empresa_id);
        $estoque = (int) ($summary['estoque_disponivel'] ?? 0);
        $emUso = (int) ($summary['em_uso'] ?? 0);
        if ($estoque >= 2 || $emUso <= 5) {
            return 0;
        }
        return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'pneus_estoque_baixo', 'pneu',
            'Estoque de pneus baixo',
            "Apenas {$estoque} disponível(is) para {$emUso} em uso",
            'Planeje compra ou recapagem para evitar parada por falta de pneu.',
            'aviso', ['estoque' => $estoque, 'em_uso' => $emUso]) ? 1 : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function sf_notif_sync_pneus_dot(PDO $conn, int $empresa_id): int
{
    if (!is_file(__DIR__ . '/pneus_mercado_helpers.php')) {
        return 0;
    }
    require_once __DIR__ . '/pneus_mercado_helpers.php';
    $criadas = 0;
    try {
        $dot = pneus_dot_alertas_frota($conn, $empresa_id);
        $venc = count($dot['vencido'] ?? []);
        if ($venc > 0 && sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'pneus_dot_vencido', 'pneu',
            'Pneus com DOT vencido',
            "{$venc} pneu(s) com mais de 7 anos (DOT)",
            'Substitua ou descarte conforme política de segurança da frota.',
            'erro', ['total' => $venc])) {
            $criadas++;
        }
        $at = count($dot['atencao_5anos'] ?? []);
        if ($at > 0 && sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'pneus_dot_5anos', 'pneu',
            'Pneus DOT — 5+ anos',
            "{$at} pneu(s) acima de 5 anos",
            'Planeje troca preventiva.',
            'aviso', ['total' => $at])) {
            $criadas++;
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_pneus_dot: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_pneus_garantia(PDO $conn, int $empresa_id): int
{
    if (!is_file(__DIR__ . '/pneus_mercado_helpers.php')) {
        return 0;
    }
    require_once __DIR__ . '/pneus_mercado_helpers.php';
    try {
        $rows = pneus_garantias_vencendo($conn, $empresa_id, 30);
        if (!$rows) {
            return 0;
        }
        $n = count($rows);
        return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'pneus_garantia_vencendo', 'pneu',
            'Garantias de pneus vencendo',
            "{$n} garantia(s) vencem em até 30 dias",
            'Acione fabricante, revenda ou recapadora conforme o tipo.',
            'aviso', ['total' => $n]) ? 1 : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function sf_notif_sync_resolver_pendencias_resolvidas(PDO $conn, int $empresa_id): void
{
    $sql = "UPDATE notificacoes n
            LEFT JOIN rotas r ON n.referencia_id = r.id AND n.tipo = 'rota'
            LEFT JOIN abastecimentos a ON n.referencia_id = a.id AND n.tipo = 'abastecimento'
            LEFT JOIN despesas_viagem d ON n.referencia_id = d.id AND n.tipo = 'despesa'
            SET n.lida = 1
            WHERE n.empresa_id = :e AND n.lida = 0
            AND ((n.tipo = 'rota' AND (r.status IS NULL OR r.status != 'pendente'))
                 OR (n.tipo = 'abastecimento' AND (a.status IS NULL OR a.status != 'pendente'))
                 OR (n.tipo = 'despesa' AND n.referencia_id IS NOT NULL AND n.referencia_id > 0
                     AND (d.status IS NULL OR d.status != 'pendente')))";
    try {
        $st = $conn->prepare($sql);
        $st->execute([':e' => $empresa_id]);
    } catch (Throwable $e) {
        error_log('sf_notif_sync_resolver_pendencias: ' . $e->getMessage());
    }
}
