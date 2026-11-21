<?php
/**
 * Helpers compartilhados do módulo de notificações (tabela notificacoes).
 */
require_once __DIR__ . '/sf_paths.php';

/** Janela padrão para resumo e badges (dias). */
const SF_NOTIF_DIAS_JANELA = 14;

/** Tipos operacionais (sininho regular): pendências e compliance acionável. */
function sf_notif_tipos_operacional(): array
{
    return ['rota', 'abastecimento', 'documento', 'cnh', 'financeiro', 'despesa', 'multa', 'comissao', 'fiscal', 'fornecedor', 'checklist', 'seguranca', 'plataforma'];
}

/** Tipos IA / insights analíticos. */
function sf_notif_tipos_ia(): array
{
    return ['manutencao', 'alerta', 'pneu', 'consumo', 'custo', 'insights', 'recomendacao', 'gps', 'fraudes'];
}

/** Tipos visíveis apenas com permissão financeira. */
function sf_notif_tipos_financeiros(): array
{
    return ['financeiro', 'comissao', 'fiscal'];
}

/** Rótulos amigáveis por tipo (UI de preferências). */
function sf_notif_tipo_labels(): array
{
    return [
        'rota' => 'Rotas pendentes de aprovação',
        'abastecimento' => 'Abastecimentos para validar',
        'documento' => 'Documentos e vencimentos',
        'cnh' => 'CNH de motoristas',
        'financeiro' => 'Alertas financeiros',
        'despesa' => 'Despesas de viagem',
        'multa' => 'Multas de trânsito',
        'comissao' => 'Comissões',
        'fiscal' => 'Pendências fiscais',
        'fornecedor' => 'Fornecedores',
        'checklist' => 'Checklists com problemas',
        'seguranca' => 'Segurança (login / IP novo)',
        'plataforma' => 'Comunicados da plataforma Frotec',
        'manutencao' => 'Manutenções preventivas',
        'alerta' => 'Alertas gerais de frota',
        'pneu' => 'Pneus e desgaste',
        'consumo' => 'Consumo de combustível',
        'custo' => 'Lucratividade e custos',
        'insights' => 'Resumo consolidado (insights)',
        'recomendacao' => 'Recomendações da IA',
        'gps' => 'Cercas GPS (entrou / saiu)',
        'fraudes' => 'Anomalias e possíveis fraudes',
    ];
}

function sf_notif_tipo_label(string $tipo): string
{
    $labels = sf_notif_tipo_labels();
    return $labels[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
}

/** Tipos que o usuário logado pode configurar (respeita permissão financeira). */
function sf_notif_tipos_para_usuario(string $grupo = 'todos'): array
{
    $filtrar = static function (array $tipos): array {
        return array_values(array_filter($tipos, 'sf_notif_usuario_pode_ver_tipo'));
    };
    if ($grupo === 'operacional') {
        return $filtrar(sf_notif_tipos_operacional());
    }
    if ($grupo === 'ia') {
        return $filtrar(sf_notif_tipos_ia());
    }
    return array_merge($filtrar(sf_notif_tipos_operacional()), $filtrar(sf_notif_tipos_ia()));
}

function sf_notif_ensure_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = $conn->query('SHOW COLUMNS FROM notificacoes')->fetchAll(PDO::FETCH_ASSOC);
        $colMap = [];
        foreach ($cols as $c) {
            $colMap[$c['Field']] = $c;
        }
        if (!isset($colMap['severidade'])) {
            $conn->exec("ALTER TABLE notificacoes ADD COLUMN severidade VARCHAR(20) NOT NULL DEFAULT 'info' AFTER tipo");
        }
        if (!isset($colMap['grupo_chave'])) {
            $conn->exec('ALTER TABLE notificacoes ADD COLUMN grupo_chave VARCHAR(120) NULL AFTER severidade');
        }
        if (!isset($colMap['meta_json'])) {
            $conn->exec('ALTER TABLE notificacoes ADD COLUMN meta_json TEXT NULL AFTER ia_mensagem');
        }
        $tipoCol = $colMap['tipo'] ?? null;
        if ($tipoCol && stripos((string) ($tipoCol['Type'] ?? ''), 'enum') !== false) {
            $conn->exec("ALTER TABLE notificacoes MODIFY COLUMN tipo VARCHAR(64) NOT NULL DEFAULT 'alerta'");
        }
        $conn->exec("CREATE TABLE IF NOT EXISTS notificacoes_preferencias (
            usuario_id INT NOT NULL, empresa_id INT NOT NULL,
            tipos_desativados JSON NULL, email_digest TINYINT(1) NOT NULL DEFAULT 0,
            email_digest_hora TIME NOT NULL DEFAULT '08:00:00',
            PRIMARY KEY (usuario_id, empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->exec("CREATE TABLE IF NOT EXISTS notificacoes_sync_controle (
            empresa_id INT NOT NULL, chave VARCHAR(64) NOT NULL,
            ultima_execucao DATETIME NOT NULL, PRIMARY KEY (empresa_id, chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->exec("CREATE TABLE IF NOT EXISTS notificacoes_digest_envio (
            id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, empresa_id INT NOT NULL,
            data_ref DATE NOT NULL, enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_digest_dia (usuario_id, empresa_id, data_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('sf_notif_ensure_schema: ' . $e->getMessage());
    }
}

function sf_notif_sync_pode_executar(PDO $conn, int $empresa_id, string $chave, int $minutos): bool
{
    sf_notif_ensure_schema($conn);
    try {
        $st = $conn->prepare('SELECT ultima_execucao FROM notificacoes_sync_controle WHERE empresa_id = :e AND chave = :c');
        $st->execute([':e' => $empresa_id, ':c' => $chave]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && strtotime($row['ultima_execucao']) > time() - ($minutos * 60)) {
            return false;
        }
    } catch (Throwable $e) {
        return true;
    }
    return true;
}

function sf_notif_sync_registrar(PDO $conn, int $empresa_id, string $chave): void
{
    try {
        $st = $conn->prepare('INSERT INTO notificacoes_sync_controle (empresa_id, chave, ultima_execucao)
            VALUES (:e, :c, NOW()) ON DUPLICATE KEY UPDATE ultima_execucao = NOW()');
        $st->execute([':e' => $empresa_id, ':c' => $chave]);
    } catch (Throwable $e) {
        // ignora
    }
}

function sf_notif_usuario_id(): int
{
    return (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0);
}

function sf_notif_usuario_pode_ver_tipo(string $tipo): bool
{
    if (in_array($tipo, sf_notif_tipos_financeiros(), true)) {
        if (!function_exists('can_view_financial_data')) {
            require_once __DIR__ . '/permissions.php';
        }
        return can_view_financial_data();
    }
    return true;
}

function sf_notif_preferencias(PDO $conn, int $usuario_id, int $empresa_id): array
{
    sf_notif_ensure_schema($conn);
    $defaults = ['tipos_desativados' => [], 'email_digest' => 0, 'email_digest_hora' => '08:00:00'];
    if ($usuario_id <= 0) {
        return $defaults;
    }
    try {
        $st = $conn->prepare('SELECT tipos_desativados, email_digest, email_digest_hora FROM notificacoes_preferencias WHERE usuario_id = :u AND empresa_id = :e');
        $st->execute([':u' => $usuario_id, ':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $defaults;
        }
        $tipos = $row['tipos_desativados'] ?? '[]';
        if (is_string($tipos)) {
            $tipos = json_decode($tipos, true) ?: [];
        }
        return [
            'tipos_desativados' => is_array($tipos) ? $tipos : [],
            'email_digest' => (int) ($row['email_digest'] ?? 0),
            'email_digest_hora' => (string) ($row['email_digest_hora'] ?? '08:00:00'),
        ];
    } catch (Throwable $e) {
        return $defaults;
    }
}

function sf_notif_filtrar_para_usuario(array $notificacoes, PDO $conn, int $usuario_id, int $empresa_id): array
{
    $prefs = sf_notif_preferencias($conn, $usuario_id, $empresa_id);
    $off = array_flip($prefs['tipos_desativados']);
    $out = [];
    foreach ($notificacoes as $n) {
        $tipo = (string) ($n['tipo'] ?? '');
        if (isset($off[$tipo])) {
            continue;
        }
        if (!sf_notif_usuario_pode_ver_tipo($tipo)) {
            continue;
        }
        $out[] = $n;
    }
    return $out;
}

function sf_notif_escopo_valido(?string $escopo): string
{
    $e = strtolower(trim((string) $escopo));
    if (in_array($e, ['operacional', 'ia', 'todos'], true)) {
        return $e;
    }
    return 'todos';
}

/**
 * Placeholders IN (...) para tipos do escopo.
 * @return array{0: string, 1: array<int, string>} [sql_fragment, tipos]
 */
function sf_notif_escopo_tipos(string $escopo): array
{
    if ($escopo === 'operacional') {
        $tipos = sf_notif_tipos_operacional();
    } elseif ($escopo === 'ia') {
        $tipos = sf_notif_tipos_ia();
    } else {
        return ['', []];
    }
    $ph = [];
    foreach ($tipos as $i => $t) {
        $ph[] = ':tipo' . $i;
    }
    return [implode(', ', $ph), $tipos];
}

function sf_notificacoes_sistema_ativo(PDO $conn, int $empresa_id): bool
{
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_notificacoes (
            empresa_id INT NOT NULL PRIMARY KEY,
            notificacoes_badges TINYINT(1) DEFAULT 1,
            notificacoes_niveis TINYINT(1) DEFAULT 1,
            notificacoes_ranking TINYINT(1) DEFAULT 1,
            notificacoes_desafios TINYINT(1) DEFAULT 1,
            sistema_notificacoes_ativo TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $conn->prepare('SELECT sistema_notificacoes_ativo FROM configuracoes_notificacoes WHERE empresa_id = :e LIMIT 1');
        $stmt->execute([':e' => $empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return true;
        }
        return (int) ($row['sistema_notificacoes_ativo'] ?? 1) === 1;
    } catch (Throwable $e) {
        error_log('sf_notificacoes_sistema_ativo: ' . $e->getMessage());
        return true;
    }
}

function sf_notif_url(string $tipo, ?int $referencia_id = null): ?string
{
    $base = static function (string $page, array $query = []): string {
        $url = sf_app_url($page);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    };

    switch ($tipo) {
        case 'rota':
            return $base('pages/rotas_motoristas.php', $referencia_id ? ['id' => $referencia_id] : []);
        case 'abastecimento':
            return $base('pages/abastecimentos_motoristas.php', $referencia_id ? ['id' => $referencia_id] : []);
        case 'manutencao':
            return $base('pages/manutencoes.php');
        case 'pneu':
            return $base('pages/pneus.php');
        case 'cnh':
        case 'documento':
            return $base('pages/motorists.php');
        case 'financeiro':
            return $base('pages/contas_pagar.php');
        case 'despesa':
            return $base('pages/rotas_motoristas.php');
        case 'multa':
            return $base('pages/multas.php');
        case 'comissao':
            return $base('pages/comissoes.php');
        case 'fiscal':
            return $base('fiscal/pages/index.php');
        case 'seguranca':
            return $base('pages/configuracoes.php', ['tab' => 'seguranca']);
        case 'fornecedor':
            return $base('pages/fornecedores_moderno.php');
        case 'checklist':
            return $base('pages/checklists.php', $referencia_id ? ['id' => $referencia_id] : []);
        case 'consumo':
        case 'custo':
        case 'fraudes':
            return $base('pages/lucratividade.php');
        case 'insights':
        case 'recomendacao':
            return $base('pages/bi.php');
        case 'gps':
            return $base('pages/mapa_frota.php');
        case 'alerta':
            return $base('pages/gestao_interativa_pro.php');
        case 'plataforma':
            return $base('pages/notificacoes.php');
        default:
            return null;
    }
}

/**
 * Insere notificação (com severidade, grupo e meta opcionais).
 *
 * @param array{ia_mensagem?:?string,referencia_id?:?int,horas_janela?:int,severidade?:string,grupo_chave?:?string,meta_json?:?array} $opts
 */
function sf_notif_inserir(
    PDO $conn,
    int $empresa_id,
    string $tipo,
    string $titulo,
    string $mensagem,
    array $opts = []
): bool {
    sf_notif_ensure_schema($conn);
    try {
        $horas = max(1, (int) ($opts['horas_janela'] ?? 48));
        $grupo = $opts['grupo_chave'] ?? null;
        $sqlCheck = 'SELECT id FROM notificacoes WHERE empresa_id = :empresa_id AND tipo = :tipo AND titulo = :titulo
            AND data_criacao >= DATE_SUB(NOW(), INTERVAL ' . $horas . ' HOUR)';
        $params = [':empresa_id' => $empresa_id, ':tipo' => $tipo, ':titulo' => $titulo];
        $ref = isset($opts['referencia_id']) ? (int) $opts['referencia_id'] : null;
        if ($ref !== null && $ref > 0) {
            $sqlCheck .= ' AND referencia_id = :ref';
            $params[':ref'] = $ref;
        } elseif ($grupo) {
            $sqlCheck .= ' AND grupo_chave = :grupo';
            $params[':grupo'] = $grupo;
        }
        $sqlCheck .= ' LIMIT 1';
        $stmt = $conn->prepare($sqlCheck);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            return false;
        }

        $meta = $opts['meta_json'] ?? null;
        $metaStr = is_array($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $sev = in_array($opts['severidade'] ?? 'info', ['info', 'aviso', 'critico'], true)
            ? $opts['severidade'] : 'info';

        $stmt = $conn->prepare(
            'INSERT INTO notificacoes (empresa_id, tipo, severidade, grupo_chave, titulo, mensagem, referencia_id, ia_mensagem, meta_json, data_criacao, lida)
             VALUES (:empresa_id, :tipo, :severidade, :grupo_chave, :titulo, :mensagem, :referencia_id, :ia_mensagem, :meta_json, NOW(), 0)'
        );
        $stmt->execute([
            ':empresa_id' => $empresa_id,
            ':tipo' => $tipo,
            ':severidade' => $sev,
            ':grupo_chave' => $grupo,
            ':titulo' => $titulo,
            ':mensagem' => $mensagem,
            ':referencia_id' => ($ref !== null && $ref > 0) ? $ref : null,
            ':ia_mensagem' => $opts['ia_mensagem'] ?? null,
            ':meta_json' => $metaStr,
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('sf_notif_inserir: ' . $e->getMessage());
        return false;
    }
}

/** Atualiza ou cria notificação agrupada do dia (resumos). */
function sf_notif_upsert_grupo_hoje(
    PDO $conn,
    int $empresa_id,
    string $grupo_chave,
    string $tipo,
    string $titulo,
    string $mensagem,
    ?string $ia_mensagem = null,
    string $severidade = 'info',
    ?array $meta = null
): bool {
    sf_notif_ensure_schema($conn);
    $sev = in_array($severidade, ['info', 'aviso', 'critico'], true) ? $severidade : 'info';
    $metaStr = is_array($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
    try {
        $st = $conn->prepare("SELECT id FROM notificacoes
            WHERE empresa_id = :e AND grupo_chave = :g AND DATE(data_criacao) = CURDATE() AND lida = 0 LIMIT 1");
        $st->execute([':e' => $empresa_id, ':g' => $grupo_chave]);
        $id = $st->fetchColumn();
        if ($id) {
            $up = $conn->prepare('UPDATE notificacoes SET titulo = :t, mensagem = :m, ia_mensagem = :ia, severidade = :s, meta_json = :meta, data_criacao = NOW()
                WHERE id = :id AND empresa_id = :e');
            $up->execute([':t' => $titulo, ':m' => $mensagem, ':ia' => $ia_mensagem, ':s' => $sev, ':meta' => $metaStr, ':id' => $id, ':e' => $empresa_id]);
            return true;
        }
        return sf_notif_inserir($conn, $empresa_id, $tipo, $titulo, $mensagem, [
            'ia_mensagem' => $ia_mensagem, 'severidade' => $sev, 'grupo_chave' => $grupo_chave,
            'meta_json' => $meta, 'horas_janela' => 24,
        ]);
    } catch (Throwable $e) {
        error_log('sf_notif_upsert_grupo_hoje: ' . $e->getMessage());
        return false;
    }
}

/**
 * @deprecated Use sf_notif_inserir()
 */
function sf_notif_inserir_se_novo(
    PDO $conn,
    int $empresa_id,
    string $tipo,
    string $titulo,
    string $mensagem,
    ?string $ia_mensagem = null,
    ?int $referencia_id = null,
    int $horas_janela = 48
): bool {
    return sf_notif_inserir($conn, $empresa_id, $tipo, $titulo, $mensagem, [
        'ia_mensagem' => $ia_mensagem,
        'referencia_id' => $referencia_id,
        'horas_janela' => $horas_janela,
    ]);
}

/**
 * Remove duplicatas visuais (mesmo tipo + título + dia).
 */
function sf_notif_deduplicar_lista(array $notificacoes): array
{
    $out = [];
    $chaves = [];
    foreach ($notificacoes as $notif) {
        $data_dia = date('Y-m-d', strtotime($notif['data_criacao'] ?? 'now'));
        $chave = ($notif['tipo'] ?? '') . '_' . ($notif['titulo'] ?? '') . '_' . $data_dia;
        if (!in_array($chave, $chaves, true)) {
            $chaves[] = $chave;
            $out[] = $notif;
        }
    }
    return $out;
}

function sf_notif_contar_nao_lidas(PDO $conn, int $empresa_id, string $escopo = 'todos', int $dias = SF_NOTIF_DIAS_JANELA): int
{
    $dias = max(1, (int) $dias);
    $sql = 'SELECT COUNT(*) FROM notificacoes
            WHERE empresa_id = :empresa_id AND lida = 0
            AND data_criacao >= DATE_SUB(NOW(), INTERVAL ' . $dias . ' DAY)';
    $params = [':empresa_id' => $empresa_id];

    [$inSql, $tipos] = sf_notif_escopo_tipos($escopo);
    if ($inSql !== '') {
        $sql .= ' AND tipo IN (' . $inSql . ')';
        foreach ($tipos as $i => $t) {
            $params[':tipo' . $i] = $t;
        }
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function sf_notif_anexar_urls(array $notificacoes): array
{
    foreach ($notificacoes as &$n) {
        $ref = isset($n['referencia_id']) ? (int) $n['referencia_id'] : null;
        if ($ref !== null && $ref <= 0) {
            $ref = null;
        }
        $n['url'] = sf_notif_url((string) ($n['tipo'] ?? ''), $ref);
        if (!empty($n['meta_json']) && is_string($n['meta_json'])) {
            $decoded = json_decode($n['meta_json'], true);
            $n['meta'] = is_array($decoded) ? $decoded : [];
        } elseif (!empty($n['meta_json']) && is_array($n['meta_json'])) {
            $n['meta'] = $n['meta_json'];
        } else {
            $n['meta'] = [];
        }
        $n['severidade'] = $n['severidade'] ?? 'info';
    }
    unset($n);
    return $notificacoes;
}

function sf_notif_contar_nao_lidas_usuario(PDO $conn, int $empresa_id, int $usuario_id, string $escopo = 'todos', int $dias = SF_NOTIF_DIAS_JANELA): int
{
    $diasSql = max(1, (int) $dias);
    $sql = 'SELECT tipo FROM notificacoes
            WHERE empresa_id = :empresa_id AND lida = 0
            AND data_criacao >= DATE_SUB(NOW(), INTERVAL ' . $diasSql . ' DAY)';
    $params = [':empresa_id' => $empresa_id];
    [$inSql, $tipos] = sf_notif_escopo_tipos($escopo);
    if ($inSql !== '') {
        $sql .= ' AND tipo IN (' . $inSql . ')';
        foreach ($tipos as $i => $t) {
            $params[':tipo' . $i] = $t;
        }
    }
    $st = $conn->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return count(sf_notif_filtrar_para_usuario($rows, $conn, $usuario_id, $empresa_id));
}

/**
 * Sincroniza alerta de CNH vencendo para a tabela notificacoes (máx. 1 por dia).
 */
function sf_notif_sync_cnh(PDO $conn, int $empresa_id): void
{
    if (!sf_notificacoes_sistema_ativo($conn, $empresa_id)) {
        return;
    }

    $sql = "SELECT id, nome, data_validade_cnh,
            DATEDIFF(data_validade_cnh, CURRENT_DATE) AS dias
            FROM motoristas
            WHERE empresa_id = :eid AND data_validade_cnh IS NOT NULL
            AND data_validade_cnh >= CURRENT_DATE
            AND DATEDIFF(data_validade_cnh, CURRENT_DATE) <= 90
            ORDER BY data_validade_cnh ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':eid' => $empresa_id]);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($lista === []) {
        return;
    }

    $em30 = [];
    $em60 = [];
    $em90 = [];
    foreach ($lista as $r) {
        $d = (int) $r['dias'];
        $nome = $r['nome'] . ' (' . $d . ' dias)';
        if ($d <= 30) {
            $em30[] = $nome;
        } elseif ($d <= 60) {
            $em60[] = $nome;
        } else {
            $em90[] = $nome;
        }
    }

    $msg = [];
    if ($em30 !== []) {
        $msg[] = 'Até 30 dias: ' . implode(', ', array_slice($em30, 0, 5));
    }
    if ($em60 !== []) {
        $msg[] = '31–60 dias: ' . implode(', ', array_slice($em60, 0, 3));
    }
    if ($em90 !== []) {
        $msg[] = '61–90 dias: ' . implode(', ', array_slice($em90, 0, 3));
    }

    $titulo = 'CNH vencendo';
    $mensagem = implode("\n", $msg);
    $ia = count($em30) > 0
        ? 'Priorize renovação das CNHs com vencimento em até 30 dias para evitar bloqueio operacional.'
        : 'Acompanhe o calendário de validade das CNHs da frota.';

    sf_notif_inserir($conn, $empresa_id, 'cnh', 'CNH vencendo', $mensagem, [
        'ia_mensagem' => $ia, 'severidade' => count($em30) > 0 ? 'critico' : 'aviso',
        'grupo_chave' => 'cnh_vencendo', 'horas_janela' => 24,
    ]);
}

/**
 * Registra notificação de novo acesso (IP/dispositivo desconhecido) no sininho.
 */
function sf_notif_registrar_novo_acesso(
    PDO $conn,
    int $empresa_id,
    int $usuario_id,
    string $nome,
    string $ip,
    array $geo = []
): bool {
    if ($empresa_id <= 0 || !sf_notificacoes_sistema_ativo($conn, $empresa_id)) {
        return false;
    }
    $cidade = trim((string) ($geo['city'] ?? ''));
    if ($cidade !== '' && !empty($geo['country'])) {
        $cidade .= ' — ' . $geo['country'];
    }
    if ($cidade === '') {
        $cidade = 'Local não identificado';
    }
    $msg = "{$nome} acessou de IP {$ip} · {$cidade}";
    $hoje = date('Y-m-d');
    return sf_notif_inserir($conn, $empresa_id, 'seguranca', 'Novo acesso detectado', $msg, [
        'referencia_id' => $usuario_id,
        'severidade' => 'aviso',
        'ia_mensagem' => 'Se não foi você, altere a senha e revise acessos em Configurações → Segurança.',
        'grupo_chave' => 'novo_acesso_' . $usuario_id . '_' . $hoje,
        'horas_janela' => 24,
    ]);
}

/**
 * Cria notificação de novo acesso pendente na sessão (usuários multi-empresa).
 */
function sf_notif_guardar_novo_acesso_sessao(string $nome, string $ip, array $geo = []): void
{
    $_SESSION['_sf_notif_novo_acesso'] = [
        'nome' => $nome,
        'ip' => $ip,
        'geo' => $geo,
        'usuario_id' => (int) ($_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 0),
    ];
}

/**
 * Dispara notificação de novo acesso da sessão quando empresa_id estiver definido.
 */
function sf_notif_flush_novo_acesso_sessao(PDO $conn, int $empresa_id): void
{
    if ($empresa_id <= 0 || empty($_SESSION['_sf_notif_novo_acesso']) || !is_array($_SESSION['_sf_notif_novo_acesso'])) {
        return;
    }
    $p = $_SESSION['_sf_notif_novo_acesso'];
    unset($_SESSION['_sf_notif_novo_acesso']);
    sf_notif_registrar_novo_acesso(
        $conn,
        $empresa_id,
        (int) ($p['usuario_id'] ?? 0),
        (string) ($p['nome'] ?? 'Usuário'),
        (string) ($p['ip'] ?? ''),
        is_array($p['geo'] ?? null) ? $p['geo'] : []
    );
}
