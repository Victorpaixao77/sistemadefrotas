<?php
/**
 * Importação em massa de fornecedores (CSV → linhas JSON enviadas pelo front).
 * Espera POST JSON: { rows: [{ nome, tipo, documento, email, cidade, uf }] }
 * Valida CNPJ/CPF, evita duplicados (mesmo documento já cadastrado) e insere.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/doc_validators.php';

configure_session();
session_start();

header('Content-Type: application/json');
require_authentication();
api_require_csrf_json();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

$body = json_decode(file_get_contents('php://input'), true);
$rows = (is_array($body) && isset($body['rows']) && is_array($body['rows'])) ? $body['rows'] : [];
if (!count($rows)) {
    api_json_error('Nenhuma linha para importar.', 422);
}
if (count($rows) > 2000) {
    api_json_error('Limite de 2000 linhas por importação.', 422);
}

try {
    $conn = getConnection();

    // Documentos já existentes (para evitar duplicar).
    $existentes = [];
    $stmt = $conn->prepare("SELECT tipo, cpf, cnpj FROM fornecedores WHERE empresa_id = :e");
    $stmt->execute([':e' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = doc_only_digits($r['tipo'] === 'J' ? ($r['cnpj'] ?? '') : ($r['cpf'] ?? ''));
        if ($d !== '') {
            $existentes[$d] = true;
        }
    }

    $ins = $conn->prepare("INSERT INTO fornecedores (empresa_id, tipo, nome, cpf, cnpj, email, cidade, uf, situacao)
                           VALUES (:e, :tipo, :nome, :cpf, :cnpj, :email, :cidade, :uf, 'A')");

    $inseridos = 0;
    $erros = [];
    $novosDocs = [];

    $conn->beginTransaction();
    try {
        foreach ($rows as $i => $row) {
            $linha = $i + 1;
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome === '') {
                $erros[] = "Linha {$linha}: nome vazio.";
                continue;
            }
            $tipo = strtoupper(trim((string) ($row['tipo'] ?? '')));
            $docRaw = (string) ($row['documento'] ?? ($row['cnpj'] ?? $row['cpf'] ?? ''));
            $doc = doc_only_digits($docRaw);

            // Detecta tipo pelo tamanho do documento quando não informado.
            if ($tipo !== 'F' && $tipo !== 'J') {
                $tipo = (strlen($doc) === 11) ? 'F' : 'J';
            }

            if ($doc === '') {
                $erros[] = "Linha {$linha} ({$nome}): documento (CPF/CNPJ) obrigatório.";
                continue;
            }

            $cpf = null;
            $cnpj = null;
            if ($tipo === 'F') {
                if (!doc_validar_cpf($doc)) {
                    $erros[] = "Linha {$linha} ({$nome}): CPF inválido.";
                    continue;
                }
                $cpf = $doc;
            } else {
                if (!doc_validar_cnpj($doc)) {
                    $erros[] = "Linha {$linha} ({$nome}): CNPJ inválido.";
                    continue;
                }
                $cnpj = $doc;
            }
            if (isset($existentes[$doc]) || isset($novosDocs[$doc])) {
                $erros[] = "Linha {$linha} ({$nome}): documento já cadastrado (ignorado).";
                continue;
            }

            $uf = strtoupper(trim((string) ($row['uf'] ?? '')));
            if (strlen($uf) > 2) {
                $uf = substr($uf, 0, 2);
            }

            $ins->execute([
                ':e' => $empresa_id,
                ':tipo' => $tipo,
                ':nome' => mb_substr($nome, 0, 200),
                ':cpf' => $cpf,
                ':cnpj' => $cnpj,
                ':email' => mb_substr(trim((string) ($row['email'] ?? '')), 0, 200) ?: null,
                ':cidade' => mb_substr(trim((string) ($row['cidade'] ?? '')), 0, 120) ?: null,
                ':uf' => $uf !== '' ? $uf : null,
            ]);
            $novosDocs[$doc] = true;
            $inseridos++;
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log('fornecedor_import: ' . $e->getMessage());
        api_json_error('Falha ao importar. Nenhum registro foi salvo.', 500);
    }

    echo json_encode([
        'success' => true,
        'inserted' => $inseridos,
        'skipped' => count($erros),
        'errors' => array_slice($erros, 0, 50),
    ]);
} catch (Throwable $e) {
    error_log('api/fornecedor_import.php: ' . $e->getMessage());
    api_json_error('Erro ao processar a importação.', 500);
}
