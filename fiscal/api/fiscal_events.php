<?php
/**
 * 📋 API de Eventos Fiscais
 * 🔄 Gerencia eventos como cancelamento, encerramento e CCE
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

// Incluir configurações
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/../includes/CTeService.php';
require_once __DIR__ . '/../includes/MdfeService.php';
require_once __DIR__ . '/../includes/NFeService.php';

try {
    // Configurar sessão
    configure_session();
    session_start();
    
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $empresa_id = (int)($_SESSION['empresa_id'] ?? ($input['empresa_id'] ?? 0));
    if ($empresa_id < 1) {
        throw new Exception('Sessão inválida ou empresa não informada.');
    }
    $action = $input['action'] ?? 'list';
    
    $conn = getConnection();
    $usuario_id = $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

    switch ($action) {
        case 'list': {
            $limit = isset($input['limit']) ? max(1, (int)$input['limit']) : 50;
            $stmt = $conn->prepare("
                SELECT
                    id, tipo_evento, documento_tipo, documento_id,
                    protocolo_evento, justificativa, status, data_evento
                FROM fiscal_eventos_fiscais
                WHERE empresa_id = ?
                ORDER BY data_evento DESC
                LIMIT ?
            ");
            $stmt->execute([$empresa_id, $limit]);
            $events_list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'message' => 'Lista de eventos fiscais carregada',
                'data' => $events_list,
                'total' => count($events_list)
            ]);
            break;
        }

        case 'cancelar': {
            $documento_tipo = $input['documento_tipo'] ?? null; // nfe | cte
            $documento_id = isset($input['documento_id']) ? (int)$input['documento_id'] : 0;
            $justificativa = $input['justificativa'] ?? '';

            if (!$documento_tipo || !$documento_id) {
                throw new Exception('documento_tipo e documento_id são obrigatórios');
            }

            $conn->beginTransaction();

            $cStat = '';
            $xMotivo = '';
            $protocoloEvento = null;
            $xmlRet = '';
            $eventoAceito = false;

            if ($documento_tipo === 'nfe') {
                $stmtNfe = $conn->prepare("
                    SELECT id, chave_acesso, protocolo_autorizacao, status
                    FROM fiscal_nfe_clientes
                    WHERE id = ? AND empresa_id = ?
                    LIMIT 1
                ");
                $stmtNfe->execute([$documento_id, $empresa_id]);
                $nfe = $stmtNfe->fetch(PDO::FETCH_ASSOC);
                if (!$nfe) {
                    throw new Exception('NF-e não encontrada.');
                }
                $chave = preg_replace('/\D/', '', (string)($nfe['chave_acesso'] ?? ''));
                $nProt = (string)($nfe['protocolo_autorizacao'] ?? '');
                if (empty($chave) || strlen($chave) !== 44) {
                    throw new Exception('NF-e sem chave_acesso válida para cancelamento na SEFAZ.');
                }
                if ($nProt === '' || $nProt === '0') {
                    throw new Exception('NF-e sem protocolo_autorizacao (nProt) para cancelamento.');
                }

                $nfeService = new NFeService((int)$empresa_id);
                $resp = $nfeService->enviarCancelamentoNFe($chave, (string)$justificativa, $nProt);
                $xmlRet = (string)($resp['response_xml'] ?? '');
                $eventoAceito = !empty($resp['success']);
                if (!$eventoAceito) {
                    throw new Exception('Falha ao cancelar NF-e na SEFAZ: ' . ($resp['message'] ?? 'erro desconhecido'));
                }

                try {
                    $sx = @simplexml_load_string($xmlRet);
                    if ($sx !== false) {
                        $nodesCStat = @$sx->xpath('//*[local-name()="cStat"]');
                        if ($nodesCStat && isset($nodesCStat[0])) {
                            $cStat = (string)$nodesCStat[0];
                        }
                        $nodesMotivo = @$sx->xpath('//*[local-name()="xMotivo"]');
                        if ($nodesMotivo && isset($nodesMotivo[0])) {
                            $xMotivo = (string)$nodesMotivo[0];
                        }
                        $nodesProt = @$sx->xpath('//*[local-name()="infEvento"]/*[local-name()="nProt"]');
                        if ($nodesProt && isset($nodesProt[0])) {
                            $protocoloEvento = (string)$nodesProt[0];
                        }
                        if (empty($protocoloEvento)) {
                            $nodesProt2 = @$sx->xpath('//*[local-name()="nProt"]');
                            if ($nodesProt2 && isset($nodesProt2[0])) {
                                $protocoloEvento = (string)$nodesProt2[0];
                            }
                        }
                    }
                } catch (Throwable $e) {}

                $novoStatus = $eventoAceito ? 'cancelada' : ($nfe['status'] ?? 'pendente');
                $stmt = $conn->prepare("
                    UPDATE fiscal_nfe_clientes
                    SET status = ?, updated_at = NOW()
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$novoStatus, $documento_id, $empresa_id]);
            } elseif ($documento_tipo === 'cte') {
                // Integração SEFAZ (cancelamento) para CT-e.
                // Por segurança, primeiro buscamos a chave e o protocolo original.
                $stmtCte = $conn->prepare("
                    SELECT id, chave_acesso, protocolo_autorizacao, status
                    FROM fiscal_cte
                    WHERE id = ? AND empresa_id = ?
                    LIMIT 1
                ");
                $stmtCte->execute([$documento_id, $empresa_id]);
                $cte = $stmtCte->fetch(PDO::FETCH_ASSOC);
                if (!$cte) {
                    throw new Exception('CT-e não encontrado.');
                }
                $chave = preg_replace('/\D/', '', (string)($cte['chave_acesso'] ?? ''));
                $nProt = (string)($cte['protocolo_autorizacao'] ?? '');
                if (empty($chave) || strlen($chave) !== 44) {
                    throw new Exception('CT-e sem chave_acesso válida para cancelamento.');
                }
                if (empty($nProt)) {
                    throw new Exception('CT-e sem protocolo_autorizacao (nProt) para cancelamento.');
                }

                $cteService = new CTeService((int)$empresa_id);
                $resp = $cteService->enviarCancelamentoCTe($chave, (string)$justificativa, $nProt);
                if (empty($resp['success'])) {
                    throw new Exception('Falha ao enviar cancelamento CT-e: ' . ($resp['message'] ?? 'erro desconhecido'));
                }

                $xmlRet = (string)($resp['response_xml'] ?? '');

                // Parse simples: localizar cStat e protocolo do evento
                try {
                    $sx = @simplexml_load_string($xmlRet);
                    if ($sx !== false) {
                        $nodesCStat = @$sx->xpath('//*[local-name()="cStat"]');
                        if ($nodesCStat && isset($nodesCStat[0])) $cStat = (string)$nodesCStat[0];
                        $nodesMotivo = @$sx->xpath('//*[local-name()="xMotivo"]');
                        if ($nodesMotivo && isset($nodesMotivo[0])) $xMotivo = (string)$nodesMotivo[0];

                        // Em eventos, o protocolo do evento costuma vir como nProt
                        $nodesProt = @$sx->xpath('//*[local-name()="nProt"]');
                        if ($nodesProt && isset($nodesProt[0])) $protocoloEvento = (string)$nodesProt[0];
                        // Alternativa: elemento <protocolo>
                        if (empty($protocoloEvento)) {
                            $nodesProt2 = @$sx->xpath('//*[local-name()="protocolo"]');
                            if ($nodesProt2 && isset($nodesProt2[0])) $protocoloEvento = (string)$nodesProt2[0];
                        }
                    }
                } catch (Throwable $e) {}

                // Atualizar status conforme retorno
                $eventoAceito = in_array($cStat, ['135', '136'], true) || strpos($cStat, '1') === 0;
                $novoStatus = $eventoAceito ? 'cancelado' : 'pendente';

                $stmt = $conn->prepare("
                    UPDATE fiscal_cte
                    SET status = ?, updated_at = NOW()
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$novoStatus, $documento_id, $empresa_id]);
            } else {
                throw new Exception('documento_tipo inválido para cancelamento');
            }

            $stmtEv = $conn->prepare("
                INSERT INTO fiscal_eventos_fiscais (
                    empresa_id, tipo_evento, documento_tipo, documento_id,
                    justificativa, protocolo_evento, xml_evento, xml_retorno,
                    status, data_evento, usuario_id
                ) VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, 'pendente', NOW(), ?)
            ");
            $stmtEv->execute([
                $empresa_id,
                'cancelamento',
                $documento_tipo,
                $documento_id,
                $justificativa,
                $usuario_id
            ]);

            $evento_id = (int)$conn->lastInsertId();

            // Quando for CT-e, tenta atualizar o evento com status e protocolo do retorno.
            if ($documento_tipo === 'nfe' || $documento_tipo === 'cte') {
                // Buscar cStat/protocolo já processados acima é mais confiável guardando os dados,
                // mas como mantivemos parse genérico no bloco, reparamos aqui buscando o último XML.
                // Se parse falhou, mantém pendente.
                try {
                    // Guardamos xml_retorno somente se estiver preenchido no registro.
                    // Como o bloco acima não persistiu xml_evento/xml_retorno, vamos reexecutar o necessário aqui:
                    // -> buscar a resposta xml do envio é opcional; caso não exista, só deixamos pendente/aceito.
                    $stmtLast = $conn->prepare("SELECT protocolo_evento, status FROM fiscal_eventos_fiscais WHERE id = ? AND empresa_id = ? LIMIT 1");
                    $stmtLast->execute([$evento_id, $empresa_id]);
                    $ev = $stmtLast->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {}

                // Observação: o status final do evento depende do parse anterior; se foi aceito, marcamos aceito.
                // (Mantém pendente quando cStat não foi extraído.)
                // Exemplo aceito: cStat 135/136.
                if (!empty($cStat) || $documento_tipo === 'nfe') {
                    $stmtUpEv = $conn->prepare("
                        UPDATE fiscal_eventos_fiscais
                        SET status = ?, protocolo_evento = ?, xml_retorno = ?, data_evento = NOW()
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmtUpEv->execute([
                        $eventoAceito ? 'aceito' : 'rejeitado',
                        $protocoloEvento ?: null,
                        $xmlRet ?: null,
                        $evento_id,
                        $empresa_id
                    ]);
                }
            }

            $conn->commit();

            // Retornar status real do evento (aceito/rejeitado/pendente)
            $stmtEvStatus = $conn->prepare("SELECT status, protocolo_evento FROM fiscal_eventos_fiscais WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtEvStatus->execute([$evento_id, $empresa_id]);
            $evStatus = $stmtEvStatus->fetch(PDO::FETCH_ASSOC) ?: [];
            $statusEvento = $evStatus['status'] ?? 'pendente';
            $protocoloEvento = $evStatus['protocolo_evento'] ?? null;

            echo json_encode([
                'success' => true,
                'message' => 'Solicitação de cancelamento processada.',
                'data' => [
                    'evento_id' => $evento_id,
                    'status' => $statusEvento,
                    'protocolo_evento' => $protocoloEvento
                ]
            ]);
            break;
        }

        case 'encerrar': {
            $documento_tipo = $input['documento_tipo'] ?? null; // mdfe
            $documento_id = isset($input['documento_id']) ? (int)$input['documento_id'] : 0;

            if ($documento_tipo !== 'mdfe' || !$documento_id) {
                throw new Exception('encerrar exige documento_tipo=mdfe e documento_id');
            }

            $conn->beginTransaction();

            // Regra crítica: validar com SEFAZ antes de encerrar (evita duplicidade e garante consistência)
            $stmtMdfe = $conn->prepare("
                SELECT id, status, chave_acesso, data_encerramento
                FROM fiscal_mdfe
                WHERE id = ? AND empresa_id = ?
                LIMIT 1
            ");
            $stmtMdfe->execute([$documento_id, $empresa_id]);
            $mdfe = $stmtMdfe->fetch(PDO::FETCH_ASSOC);
            if (!$mdfe) {
                throw new Exception('MDF-e não encontrado');
            }
            if (($mdfe['status'] ?? '') !== 'autorizado') {
                throw new Exception('Só é possível encerrar MDF-e com status AUTORIZADO');
            }
            if (!empty($mdfe['data_encerramento'])) {
                throw new Exception('MDF-e já encerrado.');
            }

            $chaveAc = preg_replace('/\D/', '', (string)($mdfe['chave_acesso'] ?? ''));
            if (empty($chaveAc) || strlen($chaveAc) < 44) {
                throw new Exception('MDF-e sem chave_acesso válida. Faça emissão/autorização antes de encerrar.');
            }

            $mdfeService = new MdfeService((int)$empresa_id);
            $consulta = $mdfeService->consultarEventosPorChave($chaveAc);
            $tpEventos = $consulta['tpEventos'] ?? [];
            $cStat = (string)($consulta['cStat'] ?? '');

            if (in_array('110112', $tpEventos, true) || $cStat === '132') {
                throw new Exception('MDF-e já encerrado na SEFAZ.');
            }
            if (in_array('110111', $tpEventos, true) || $cStat === '101') {
                throw new Exception('MDF-e já cancelado na SEFAZ. Encerramento não permitido.');
            }

            $stmt = $conn->prepare("
                UPDATE fiscal_mdfe
                SET status = 'encerrado', updated_at = NOW()
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$documento_id, $empresa_id]);

            $stmtEv = $conn->prepare("
                INSERT INTO fiscal_eventos_fiscais (
                    empresa_id, tipo_evento, documento_tipo, documento_id,
                    justificativa, protocolo_evento, xml_evento, xml_retorno,
                    status, data_evento, usuario_id
                ) VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL, 'pendente', NOW(), ?)
            ");
            $stmtEv->execute([
                $empresa_id,
                'encerramento',
                'mdfe',
                $documento_id,
                $usuario_id
            ]);

            $evento_id = (int)$conn->lastInsertId();
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Solicitação de encerramento registrada (pendente de integração SEFAZ).',
                'data' => [
                    'evento_id' => $evento_id,
                    'status' => 'pendente'
                ]
            ]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
