<?php
/**
 * API CIOT / PEF ANTT v1.1
 *
 * GET  action=config|list|detail|validar_contratacao
 * POST action=declarar|cancelar|encerrar|consultar_ciot|consultar_situacao|consultar_frota|consultar_excecao
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/pef_ciot_helpers.php';

configure_session();
session_start();

header('Content-Type: application/json; charset=utf-8');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

if (!can_access_fiscal_system()) {
    api_json_error('Sem permissão para o módulo fiscal.', 403);
}

$empresaId = (int) $_SESSION['empresa_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    api_require_csrf_json();
}

try {
    switch ($action) {
        case 'config':
            $cfg = sf_pef_config($empresaId);
            $cfg['cert_key_pass'] = !empty($cfg['cert_key_pass']) ? '********' : '';
            $cfg['empresa_cnpj'] = sf_pef_empresa_cnpj($empresaId);
            $cfg['tipos_contratacao'] = sf_pef_tipos_contratacao();
            echo json_encode(['success' => true, 'data' => $cfg]);
            break;

        case 'validar_contratacao':
            $tipo = $_GET['tipo_contratacao'] ?? $_POST['tipo_contratacao'] ?? '';
            $val = sf_pef_validar_tipo_contratacao(is_string($tipo) ? $tipo : '');
            echo json_encode(['success' => true, 'data' => $val]);
            break;

        case 'list':
            $status = trim((string) ($_GET['status'] ?? ''));
            $sql = 'SELECT id, id_operacao_transporte, codigo_identificacao, codigo_verificador, tipo_operacao,
                           status, valor_frete, data_declaracao, data_inicio_viagem, data_fim_viagem,
                           protocolo_antt, codigo_antt, mensagem_antt, criado_em
                    FROM pef_ciot_operacoes WHERE empresa_id = :e';
            $params = [':e' => $empresaId];
            if ($status !== '' && in_array($status, ['rascunho', 'declarado', 'cancelado', 'encerrado', 'retificado', 'erro'], true)) {
                $sql .= ' AND status = :st';
                $params[':st'] = $status;
            }
            $sql .= ' ORDER BY id DESC LIMIT 200';
            $stmt = getConnection()->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'disponiveis_mdfe':
            $rotaId = isset($_GET['rota_id']) ? (int) $_GET['rota_id'] : null;
            if ($rotaId !== null && $rotaId <= 0) {
                $rotaId = null;
            }
            $rows = sf_pef_ciot_disponiveis_mdfe($empresaId, $rotaId);
            $items = array_map('sf_pef_ciot_para_mdfe_item', $rows);
            echo json_encode(['success' => true, 'data' => $items, 'operacoes' => $rows]);
            break;

        case 'detail':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = getConnection()->prepare(
                'SELECT * FROM pef_ciot_operacoes WHERE id = :id AND empresa_id = :e LIMIT 1'
            );
            $stmt->execute([':id' => $id, ':e' => $empresaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                api_json_error('Operação não encontrada.', 404);
            }
            echo json_encode(['success' => true, 'data' => $row]);
            break;

        case 'declarar':
            $in = json_decode(file_get_contents('php://input') ?: '{}', true);
            if (!is_array($in)) {
                $in = $_POST;
            }
            $valContrat = sf_pef_validar_tipo_contratacao($in['tipo_contratacao'] ?? '');
            if (!$valContrat['permitido']) {
                api_json_error($valContrat['mensagem'], 422, 'ciot_nao_aplicavel');
            }
            if (empty($in['id_operacao_transporte'])) {
                $in['id_operacao_transporte'] = sf_pef_gerar_id_operacao($empresaId);
            }
            $payload = sf_pef_montar_declaracao($in);
            $meta = ['tipo_contratacao' => (string) ($in['tipo_contratacao'] ?? '')];
            $payloadAntt = $payload;
            $client = sf_pef_client_or_fail($empresaId);

            $conn = getConnection();
            $payLog = $payload;
            $payLog['_meta'] = $meta;
            $conn->prepare(
                'INSERT INTO pef_ciot_operacoes
                 (empresa_id, rota_id, id_operacao_transporte, tipo_operacao, status,
                  cpf_cnpj_contratado, rntrc_contratado, cpf_cnpj_contratante, cpf_cnpj_destinatario,
                  valor_frete, data_inicio_viagem, data_fim_viagem, ind_contingencia, payload_envio)
                 VALUES (:e, :rota, :idop, :tipo, \'rascunho\', :contr, :rntrc, :contrat, :dest,
                         :vf, :di, :df, :cont, :pay)'
            )->execute([
                ':e' => $empresaId,
                ':rota' => !empty($in['rota_id']) ? (int) $in['rota_id'] : null,
                ':idop' => $payload['IdOperacaoTransporte'],
                ':tipo' => (int) $payload['TipoOperacao'],
                ':contr' => $payload['CpfCnpjContratado'] ?? null,
                ':rntrc' => $payload['RNTRCContratado'] ?? null,
                ':contrat' => $payload['CpfCnpjContratante'] ?? null,
                ':dest' => $payload['CpfCnpjDestinatario'] ?? null,
                ':vf' => $payload['ValorFrete'] ?? null,
                ':di' => $payload['DataInicioViagem'] ?? null,
                ':df' => $payload['DataFimViagem'] ?? null,
                ':cont' => !empty($payloadAntt['IndContingencia']) ? 1 : 0,
                ':pay' => json_encode($payLog, JSON_UNESCAPED_UNICODE),
            ]);
            $operacaoId = (int) $conn->lastInsertId();

            $res = $client->declaracaoOperacaoTransporte($payloadAntt);
            sf_pef_log($empresaId, $operacaoId, 'DeclaracaoOperacaoTransporte', $payloadAntt, $res['data'], $res['http_code'], $res['error']);

            $data = $res['data'] ?? [];
            $codigo = (string) ($data['Codigo'] ?? '');
            $sucesso = in_array($codigo, ['110', '111'], true);
            $status = $sucesso ? 'declarado' : 'erro';

            $ciot = $data['CodigoIdentificacaoOperacao'] ?? $data['CIOT'] ?? null;
            $verif = $data['CodigoVerificador'] ?? null;
            $protocolo = $data['Protocolo'] ?? null;

            $conn->prepare(
                'UPDATE pef_ciot_operacoes SET status = :st, codigo_identificacao = :ciot, codigo_verificador = :ver,
                 data_declaracao = NOW(), payload_resposta = :resp, protocolo_antt = :prot, codigo_antt = :cod,
                 mensagem_antt = :msg, aviso_transportador = :aviso WHERE id = :id AND empresa_id = :e'
            )->execute([
                ':st' => $status,
                ':ciot' => $ciot,
                ':ver' => $verif,
                ':resp' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ':prot' => $protocolo,
                ':cod' => $codigo,
                ':msg' => $data['Mensagem'] ?? $res['error'],
                ':aviso' => $data['AvisoTransportador'] ?? null,
                ':id' => $operacaoId,
                ':e' => $empresaId,
            ]);

            if (!$sucesso) {
                api_json_error(
                    $data['Mensagem'] ?? $res['error'] ?? 'Falha na declaração à ANTT.',
                    422,
                    null,
                    ['operacao_id' => $operacaoId, 'antt' => $data]
                );
            }

            echo json_encode([
                'success' => true,
                'message' => $data['Mensagem'] ?? 'CIOT declarado com sucesso.',
                'data' => [
                    'operacao_id' => $operacaoId,
                    'id_operacao_transporte' => $payloadAntt['IdOperacaoTransporte'],
                    'ciot' => $ciot,
                    'codigo_verificador' => $verif,
                    'protocolo' => $protocolo,
                    'antt' => $data,
                ],
            ]);
            break;

        case 'cancelar':
            $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $id = (int) ($in['id'] ?? 0);
            $motivo = trim((string) ($in['motivo'] ?? ''));
            if ($motivo === '') {
                api_json_error('Informe o motivo do cancelamento.', 422);
            }
            $stmt = getConnection()->prepare(
                'SELECT * FROM pef_ciot_operacoes WHERE id = :id AND empresa_id = :e LIMIT 1'
            );
            $stmt->execute([':id' => $id, ':e' => $empresaId]);
            $op = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$op) {
                api_json_error('Operação não encontrada.', 404);
            }
            $body = [
                'IdOperacaoTransporte' => $op['id_operacao_transporte'],
                'CodigoIdentificacaoOperacao' => $op['codigo_identificacao'],
                'MotivoCancelamento' => $motivo,
            ];
            $client = sf_pef_client_or_fail($empresaId);
            $res = $client->cancelamentoOperacaoTransporte($body);
            sf_pef_log($empresaId, $id, 'CancelamentoOperacaoTransporte', $body, $res['data'], $res['http_code'], $res['error']);
            $data = $res['data'] ?? [];
            $ok = in_array((string) ($data['Codigo'] ?? ''), ['110', '111'], true);
            if ($ok) {
                getConnection()->prepare(
                    'UPDATE pef_ciot_operacoes SET status = \'cancelado\', motivo_cancelamento = :m,
                     payload_resposta = :r, codigo_antt = :c, mensagem_antt = :msg WHERE id = :id'
                )->execute([
                    ':m' => $motivo,
                    ':r' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ':c' => $data['Codigo'] ?? null,
                    ':msg' => $data['Mensagem'] ?? null,
                    ':id' => $id,
                ]);
            }
            if (!$ok) {
                api_json_error($data['Mensagem'] ?? $res['error'] ?? 'Cancelamento rejeitado.', 422, null, ['antt' => $data]);
            }
            echo json_encode(['success' => true, 'message' => $data['Mensagem'] ?? 'Cancelado.', 'data' => $data]);
            break;

        case 'encerrar':
            $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $id = (int) ($in['id'] ?? 0);
            $stmt = getConnection()->prepare(
                'SELECT * FROM pef_ciot_operacoes WHERE id = :id AND empresa_id = :e LIMIT 1'
            );
            $stmt->execute([':id' => $id, ':e' => $empresaId]);
            $op = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$op) {
                api_json_error('Operação não encontrada.', 404);
            }
            $body = [
                'IdOperacaoTransporte' => $op['id_operacao_transporte'],
                'CodigoIdentificacaoOperacao' => $op['codigo_identificacao'],
            ];
            $client = sf_pef_client_or_fail($empresaId);
            $res = $client->encerramentoOperacaoTransporte($body);
            sf_pef_log($empresaId, $id, 'EncerramentoOperacaoTransporte', $body, $res['data'], $res['http_code'], $res['error']);
            $data = $res['data'] ?? [];
            $ok = in_array((string) ($data['Codigo'] ?? ''), ['110', '111'], true);
            if ($ok) {
                getConnection()->prepare(
                    'UPDATE pef_ciot_operacoes SET status = \'encerrado\', payload_resposta = :r,
                     codigo_antt = :c, mensagem_antt = :msg WHERE id = :id'
                )->execute([
                    ':r' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ':c' => $data['Codigo'] ?? null,
                    ':msg' => $data['Mensagem'] ?? null,
                    ':id' => $id,
                ]);
            }
            if (!$ok) {
                api_json_error($data['Mensagem'] ?? $res['error'] ?? 'Encerramento rejeitado.', 422, null, ['antt' => $data]);
            }
            echo json_encode(['success' => true, 'message' => $data['Mensagem'] ?? 'Encerrado.', 'data' => $data]);
            break;

        case 'consultar_ciot':
            $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $body = array_filter([
                'CodigoIdentificacaoOperacao' => preg_replace('/\D/', '', (string) ($in['ciot'] ?? '')),
                'CpfCnpjContratado' => sf_pef_digits($in['cpf_cnpj_contratado'] ?? ''),
            ]);
            if (empty($body['CodigoIdentificacaoOperacao'])) {
                api_json_error('Informe o número do CIOT.', 422);
            }
            $client = sf_pef_client_or_fail($empresaId);
            $res = $client->consultarCIOTGerado($body);
            sf_pef_log($empresaId, null, 'ConsultarCIOTGerado', $body, $res['data'], $res['http_code'], $res['error']);
            echo json_encode(['success' => $res['ok'], 'data' => $res['data'], 'error' => $res['error']]);
            break;

        case 'consultar_situacao':
            $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $body = [
                'CpfCnpjTransportador' => sf_pef_digits($in['cpf_cnpj'] ?? sf_pef_empresa_cnpj($empresaId)),
                'RNTRCTransportador' => sf_pef_normalizar_rntrc($in['rntrc'] ?? ''),
            ];
            $client = sf_pef_client_or_fail($empresaId);
            $res = $client->consultarSituacaoTransportador($body);
            sf_pef_log($empresaId, null, 'ConsultarSituacaoTransportador', $body, $res['data'], $res['http_code'], $res['error']);
            echo json_encode(['success' => $res['ok'], 'data' => $res['data'], 'error' => $res['error']]);
            break;

        case 'consultar_frota':
            $in = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $body = [
                'CpfCnpjTransportador' => sf_pef_digits($in['cpf_cnpj'] ?? sf_pef_empresa_cnpj($empresaId)),
                'RNTRCTransportador' => sf_pef_normalizar_rntrc($in['rntrc'] ?? ''),
            ];
            $client = sf_pef_client_or_fail($empresaId);
            $res = $client->consultarFrotaTransportador($body);
            sf_pef_log($empresaId, null, 'ConsultarFrotaTransportador', $body, $res['data'], $res['http_code'], $res['error']);
            echo json_encode(['success' => $res['ok'], 'data' => $res['data'], 'error' => $res['error']]);
            break;

        case 'consultar_excecao':
            $cnpj = sf_pef_digits($_GET['cpf_cnpj'] ?? $_POST['cpf_cnpj'] ?? sf_pef_empresa_cnpj($empresaId));
            $client = sf_pef_client_or_fail($empresaId);
            $res = $client->consultarExcecao($cnpj);
            sf_pef_log($empresaId, null, 'ConsultarExcecao', ['CPFCNPJTransportador' => $cnpj], $res['data'], $res['http_code'], $res['error']);
            echo json_encode(['success' => $res['ok'], 'data' => $res['data'], 'error' => $res['error']]);
            break;

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (RuntimeException $e) {
    api_json_error($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('pef_ciot: ' . $e->getMessage());
    api_json_error('Erro interno ao processar CIOT.', 500);
}
