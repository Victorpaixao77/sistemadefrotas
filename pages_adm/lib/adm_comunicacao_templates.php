<?php
declare(strict_types=1);

function adm_comunicacao_modelos_padrao_defs(): array
{
    return [
        ['slug' => 'manutencao_programada', 'nome' => 'Manutenção programada', 'canal' => 'banner', 'icone' => 'fa-screwdriver-wrench',
            'titulo' => 'Manutenção programada do sistema',
            'mensagem' => "Informamos que será realizada uma manutenção programada em nosso sistema para melhorias de desempenho, segurança e estabilidade.\n\nDurante esse período, o sistema poderá apresentar instabilidades ou ficar temporariamente indisponível.\n\nAgradecemos a compreensão e pedimos desculpas por qualquer inconveniente."],
        ['slug' => 'manutencao_em_andamento', 'nome' => 'Manutenção em andamento', 'canal' => 'banner', 'icone' => 'fa-triangle-exclamation',
            'titulo' => 'Manutenção em andamento',
            'mensagem' => "Estamos realizando uma manutenção no momento. O acesso pode estar lento ou intermitente.\n\nRecomendamos evitar operações críticas até a conclusão. Avisaremos quando tudo estiver normalizado."],
        ['slug' => 'manutencao_concluida', 'nome' => 'Manutenção concluída', 'canal' => 'aviso', 'icone' => 'fa-circle-check',
            'titulo' => 'Manutenção concluída',
            'mensagem' => "A manutenção programada foi concluída com sucesso. O sistema já está disponível em condições normais de uso.\n\nSe notar qualquer comportamento incomum, entre em contato com o suporte Frotec."],
        ['slug' => 'nova_funcionalidade', 'nome' => 'Nova funcionalidade', 'canal' => 'aviso', 'icone' => 'fa-star',
            'titulo' => 'Novidade na plataforma',
            'mensagem' => "Lançamos uma nova funcionalidade para facilitar o dia a dia da sua frota.\n\nAcesse o menu indicado ou consulte a central de ajuda para saber como utilizar."],
        ['slug' => 'atualizacao_seguranca', 'nome' => 'Atualização de segurança', 'canal' => 'push', 'icone' => 'fa-shield-halved',
            'titulo' => 'Atualização de segurança aplicada',
            'mensagem' => "Aplicamos melhorias de segurança na plataforma. Não é necessária nenhuma ação da sua parte.\n\nRecomendamos manter senhas fortes e o 2FA ativo quando disponível."],
        ['slug' => 'instabilidade_resolvida', 'nome' => 'Instabilidade resolvida', 'canal' => 'aviso', 'icone' => 'fa-server',
            'titulo' => 'Serviço normalizado',
            'mensagem' => "O incidente que causou instabilidade no sistema foi resolvido. Todos os serviços estão operando normalmente.\n\nAgradecemos a paciência."],
        ['slug' => 'comunicado_geral', 'nome' => 'Comunicado geral', 'canal' => 'aviso', 'icone' => 'fa-bullhorn',
            'titulo' => 'Comunicado Frotec',
            'mensagem' => "Prezado cliente,\n\n[Descreva aqui o comunicado.]\n\nEquipe Frotec"],
    ];
}

function adm_comunicacao_modelos_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS adm_comunicacao_modelos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(64) NOT NULL,
        nome VARCHAR(120) NOT NULL,
        canal ENUM('email','whatsapp','push','banner','aviso') NOT NULL DEFAULT 'aviso',
        titulo VARCHAR(200) NOT NULL,
        mensagem TEXT NOT NULL,
        icone VARCHAR(64) NULL DEFAULT 'fa-file-lines',
        is_padrao TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    try {
        $cols = $pdo->query('SHOW COLUMNS FROM adm_comunicacao_modelos')->fetchAll(PDO::FETCH_ASSOC);
        $colMap = [];
        foreach ($cols as $c) {
            $colMap[$c['Field']] = $c;
        }
        if (!isset($colMap['icone'])) {
            $pdo->exec("ALTER TABLE adm_comunicacao_modelos ADD COLUMN icone VARCHAR(64) NULL DEFAULT 'fa-file-lines' AFTER mensagem");
        }
        if (!isset($colMap['is_padrao'])) {
            $pdo->exec('ALTER TABLE adm_comunicacao_modelos ADD COLUMN is_padrao TINYINT(1) NOT NULL DEFAULT 0 AFTER icone');
        }
    } catch (Throwable $e) {
        error_log('adm_comunicacao_modelos_ensure migrate: ' . $e->getMessage());
    }

    $ins = $pdo->prepare('INSERT IGNORE INTO adm_comunicacao_modelos (slug, nome, canal, titulo, mensagem, icone, is_padrao)
        VALUES (?,?,?,?,?,?,1)');
    foreach (adm_comunicacao_modelos_padrao_defs() as $m) {
        $ins->execute([$m['slug'], $m['nome'], $m['canal'], $m['titulo'], $m['mensagem'], $m['icone']]);
    }
}

/** @return list<array<string,mixed>> */
function adm_comunicacao_modelos_list(PDO $pdo): array
{
    adm_comunicacao_modelos_ensure($pdo);
    $rows = $pdo->query('SELECT id, slug, nome, canal, titulo, mensagem, icone, is_padrao FROM adm_comunicacao_modelos ORDER BY is_padrao DESC, nome')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'slug' => $r['slug'],
            'nome' => $r['nome'],
            'canal' => $r['canal'],
            'titulo' => $r['titulo'],
            'mensagem' => $r['mensagem'],
            'icone' => $r['icone'] ?: 'fa-file-lines',
            'is_padrao' => (int) $r['is_padrao'] === 1,
        ];
    }
    return $out;
}

function adm_comunicacao_modelo_salvar(PDO $pdo, string $nome, string $canal, string $titulo, string $mensagem, int $adminId, ?string $slugEdit = null): array
{
    $nome = trim($nome);
    $titulo = trim($titulo);
    $mensagem = trim($mensagem);
    if ($nome === '' || $titulo === '' || $mensagem === '') {
        return ['success' => false, 'message' => 'Nome, título e mensagem são obrigatórios.'];
    }
    if (!in_array($canal, ['email', 'whatsapp', 'push', 'banner', 'aviso'], true)) {
        $canal = 'aviso';
    }
    adm_comunicacao_modelos_ensure($pdo);
    try {
        if ($slugEdit) {
            $pdo->prepare('UPDATE adm_comunicacao_modelos SET nome=?, canal=?, titulo=?, mensagem=? WHERE slug=?')
                ->execute([$nome, $canal, $titulo, $mensagem, $slugEdit]);
            return ['success' => true, 'message' => 'Modelo atualizado: ' . $nome];
        }
        $slug = substr(trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($nome, 'UTF-8')), '_'), 0, 50);
        $slug = $slug !== '' ? $slug : 'modelo_' . time();
        $pdo->prepare('INSERT INTO adm_comunicacao_modelos (slug, nome, canal, titulo, mensagem, is_padrao, created_by) VALUES (?,?,?,?,?,0,?)
            ON DUPLICATE KEY UPDATE nome=VALUES(nome), canal=VALUES(canal), titulo=VALUES(titulo), mensagem=VALUES(mensagem)')
            ->execute([$slug, $nome, $canal, $titulo, $mensagem, $adminId > 0 ? $adminId : null]);
        return ['success' => true, 'message' => 'Modelo salvo: ' . $nome];
    } catch (Throwable $e) {
        error_log('adm_comunicacao_modelo_salvar: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Não foi possível salvar o modelo.'];
    }
}

function adm_comunicacao_modelo_excluir(PDO $pdo, string $slug): array
{
    adm_comunicacao_modelos_ensure($pdo);
    try {
        $st = $pdo->prepare('DELETE FROM adm_comunicacao_modelos WHERE slug = ?');
        $st->execute([$slug]);
        if ($st->rowCount() < 1) {
            return ['success' => false, 'message' => 'Modelo não encontrado.'];
        }
        return ['success' => true, 'message' => 'Modelo excluído.'];
    } catch (Throwable $e) {
        error_log('adm_comunicacao_modelo_excluir: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Não foi possível excluir o modelo.'];
    }
}

/** @param array<string,mixed> $row */
function adm_comunicacao_historico_json(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'canal' => (string) $row['canal'],
        'titulo' => (string) $row['titulo'],
        'status' => (string) $row['status'],
        'enviado_em' => $row['enviado_em'] ?? null,
        'created_at' => (string) $row['created_at'],
        'pode_reenviar' => in_array($row['canal'], ['aviso', 'push', 'banner'], true),
    ];
}

function adm_comunicacao_ajax_exit(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
