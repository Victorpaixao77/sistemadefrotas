<?php
/**
 * Aprendizado da IA (admin) — fecha o loop: revisa perguntas não entendidas
 * (ia_perguntas_pendentes) e as mapeia para intenções (ia_intencoes_custom), que o
 * classificador já consulta (sf_ia_tools_match_custom). Só leitura/gravação nessas 2
 * tabelas, sempre escopado por empresa_id. Requer permissão de administração.
 *
 * GET  ?action=listar
 * POST action=mapear  { id, intent, padrao? , csrf_token }
 * POST action=ignorar { id, csrf_token }
 * POST action=toggle_custom { id, ativo, csrf_token }
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/permissions.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=utf-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$usuario_id = (int) ($_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 0);

// Gate de administração (só quem administra o sistema treina a IA)
$podeAdmin = (function_exists('can_manage_system_settings') && can_manage_system_settings())
    || (function_exists('is_admin') && is_admin());
if (!$podeAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para treinar a IA.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Normaliza igual ao classificador (minúsculo + sem acento) para o padrão casar com $qNorm. */
$norm = static function (string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
    return strtr($s, $map);
};

$action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

try {
    $conn = getConnection();

    if ($action === 'listar') {
        $pend = $conn->prepare("SELECT id, pergunta, ocorrencias, updated_at
            FROM ia_perguntas_pendentes WHERE empresa_id = :e AND status = 'pendente'
            ORDER BY ocorrencias DESC, updated_at DESC LIMIT 100");
        $pend->execute([':e' => $empresa_id]);

        $cus = $conn->prepare("SELECT id, padrao, intent, ativo FROM ia_intencoes_custom
            WHERE empresa_id = :e ORDER BY created_at DESC LIMIT 100");
        $cus->execute([':e' => $empresa_id]);

        // sugestões de intenção = intenções reais já produzidas pelo classificador
        $intents = [];
        try {
            $it = $conn->prepare("SELECT DISTINCT intent FROM ia_auditoria
                WHERE empresa_id = :e AND intent <> '' AND intent <> 'ajuda' ORDER BY intent");
            $it->execute([':e' => $empresa_id]);
            $intents = $it->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) { /* ia_auditoria pode não ter dados ainda */ }

        echo json_encode([
            'success' => true,
            'pendentes' => $pend->fetchAll(PDO::FETCH_ASSOC),
            'customs' => $cus->fetchAll(PDO::FETCH_ASSOC),
            'intents' => $intents,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- ações de escrita: exigem CSRF ---
    if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'mapear') {
        $id = (int) ($_POST['id'] ?? 0);
        $intent = preg_replace('/[^a-z_]/', '', mb_strtolower(trim((string) ($_POST['intent'] ?? '')), 'UTF-8'));
        if ($id < 1 || $intent === '') {
            throw new InvalidArgumentException('Informe a pergunta e a intenção.');
        }
        // busca a pergunta pendente (da própria empresa)
        $g = $conn->prepare("SELECT pergunta FROM ia_perguntas_pendentes WHERE id = :id AND empresa_id = :e");
        $g->execute([':id' => $id, ':e' => $empresa_id]);
        $perg = $g->fetchColumn();
        if ($perg === false) {
            throw new InvalidArgumentException('Pergunta não encontrada.');
        }
        // padrão = informado (encurtado) ou a própria pergunta, sempre normalizado
        $padrao = $norm((string) ($_POST['padrao'] ?? '')) ?: $norm((string) $perg);
        if ($padrao === '') {
            throw new InvalidArgumentException('Padrão vazio.');
        }
        $ins = $conn->prepare("INSERT INTO ia_intencoes_custom (empresa_id, padrao, intent, ativo, criado_por, created_at)
            VALUES (:e, :p, :i, 1, :u, NOW())");
        $ins->execute([':e' => $empresa_id, ':p' => $padrao, ':i' => $intent, ':u' => $usuario_id ?: null]);

        $upd = $conn->prepare("UPDATE ia_perguntas_pendentes SET status = 'mapeada', intent_mapeada = :i, updated_at = NOW()
            WHERE id = :id AND empresa_id = :e");
        $upd->execute([':i' => $intent, ':id' => $id, ':e' => $empresa_id]);

        echo json_encode(['success' => true, 'padrao' => $padrao, 'intent' => $intent], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'ignorar') {
        $id = (int) ($_POST['id'] ?? 0);
        $upd = $conn->prepare("UPDATE ia_perguntas_pendentes SET status = 'ignorada', updated_at = NOW()
            WHERE id = :id AND empresa_id = :e");
        $upd->execute([':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'toggle_custom') {
        $id = (int) ($_POST['id'] ?? 0);
        $ativo = (int) ($_POST['ativo'] ?? 0) ? 1 : 0;
        $upd = $conn->prepare("UPDATE ia_intencoes_custom SET ativo = :a WHERE id = :id AND empresa_id = :e");
        $upd->execute([':a' => $ativo, ':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true, 'ativo' => $ativo], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new InvalidArgumentException('Ação inválida.');
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ia_aprendizado.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}
