<?php
/**
 * OCR de documento único por IA de visão - App Android Motoristas.
 *
 * POST multipart: campo "image" (JPEG/PNG/WebP) + "tipo".
 *   tipo = abastecimento | painel | pedagio
 * Retorna { success, data:{ campos lidos } } para o app pré-preencher o formulário.
 * O motorista SEMPRE revisa/edita antes de salvar — isto é só um preenchimento automático.
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$root = dirname(__DIR__, 2);
require_once $root . '/includes/sf_vision_ocr.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Use POST.', 405);
}

$tipo = strtolower(trim((string) ($_POST['tipo'] ?? 'abastecimento')));
$tiposValidos = ['abastecimento', 'painel', 'pedagio', 'nota', 'lacre', 'auto'];
if (!in_array($tipo, $tiposValidos, true)) {
    api_error('Tipo de documento inválido.');
}

// ---- imagem ----
$binary = '';
$mime = 'image/jpeg';
if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $binary = (string) file_get_contents($_FILES['image']['tmp_name']);
    $mime = strtolower((string) ($_FILES['image']['type'] ?? ''));
    if ($mime === '' || $mime === 'application/octet-stream') {
        $ext = strtolower(pathinfo((string) ($_FILES['image']['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
    }
} else {
    $raw = (string) file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (is_array($in) && !empty($in['image_base64'])) {
        $b64 = preg_replace('#^data:image/[^;]+;base64,#', '', (string) $in['image_base64']);
        $dec = base64_decode((string) $b64, true);
        if ($dec !== false && strlen($dec) > 50) {
            $binary = $dec;
            $mime = isset($in['mime_type']) && is_string($in['mime_type']) && $in['mime_type'] !== '' ? strtolower($in['mime_type']) : 'image/jpeg';
        }
        if (!empty($in['tipo']) && in_array(strtolower((string) $in['tipo']), $tiposValidos, true)) {
            $tipo = strtolower((string) $in['tipo']);
        }
    }
}

if ($binary === '') {
    api_error('Envie a foto no campo "image".');
}
if (strlen($binary) > 18 * 1024 * 1024) {
    api_error('Imagem muito grande (máx. 18 MB).');
}
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
    api_error('Use JPEG, PNG ou WebP.');
}

try {
    $conn = getConnection();
    $cfg = sf_vision_ocr_resolve($conn, (int) $empresa_id);
    if ($cfg === null) {
        api_error('Leitura por IA não configurada para esta empresa. Peça ao gestor para configurar a IA de visão em Configurações.', 503);
    }

    [$binary, $mime] = sf_vision_ocr_prepare_image($binary, $mime);
    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);

    $system = 'Você lê documentos de transporte no Brasil (comprovantes de posto, painel de veículo, recibos de pedágio, notas fiscais, lacres). Responda SOMENTE JSON válido, sem markdown e sem texto fora do JSON. Nunca invente: se não ler com clareza um campo, use null.';

    if ($tipo === 'auto') {
        $classifyText = <<<'TXT'
Identifique o tipo deste documento/foto de transporte no Brasil.

Responda com este JSON exato:
{"tipo_documento":"abastecimento|pedagio|painel|nota|lacre|desconhecido","confianca":0}

Regras:
- abastecimento: cupom/nota de posto de combustível
- pedagio: recibo de praça de pedágio
- painel: foto do hodômetro/painel do veículo
- nota: DANFE/NF-e/nota fiscal de carga
- lacre: foto de lacre de container/carga
- desconhecido: foto de veículo, carga, estrada ou outro sem texto fiscal claro
- confianca: 0 a 100
TXT;
        $classRes = sf_vision_ocr_call($cfg, $dataUrl, $system, $classifyText);
        if (!$classRes['ok']) {
            if ($classRes['http'] === 429) {
                api_error('Serviço de IA ocupado no momento. Tente novamente em instantes.', 429);
            }
            error_log('ocr_documento.php classificação: ' . $classRes['error']);
            api_error('Não foi possível identificar o documento. Tente novamente.', 502);
        }
        $classParsed = sf_vision_ocr_decode_json($classRes['content']);
        $detected = strtolower(trim((string) ($classParsed['tipo_documento'] ?? 'desconhecido')));
        if (!in_array($detected, ['abastecimento', 'painel', 'pedagio', 'nota', 'lacre'], true)) {
            api_success(['tipo' => 'desconhecido', 'campos' => new stdClass()], 'Tipo não identificado. Abra o formulário manualmente.');
            exit;
        }
        $tipo = $detected;
    }

    if ($tipo === 'abastecimento') {
        $userText = <<<'TXT'
Esta é a foto de UM comprovante/cupom de abastecimento de combustível. Extraia os campos do cupom.

Responda com este JSON exato:
{"posto":"nome do posto ou null","data":"dd/mm/aaaa ou null","hora":"HH:MM ou null","litros":0,"valor_total":0,"valor_litro":0,"combustivel":"diesel s10|diesel s500|gasolina|etanol|arla|gnv ou null","numero_nota":"número do cupom/nota ou null"}

Regras:
- "litros", "valor_total", "valor_litro": números com ponto decimal (ex.: 128.5 e 950.30). Se só houver dois dos três, deixe o terceiro como 0.
- "combustivel": normalize para uma das opções listadas; se não identificar, null.
- Não preencha KM (não aparece no cupom).
- Campos ilegíveis: null (ou 0 para números).
TXT;
    } elseif ($tipo === 'painel') {
        $userText = <<<'TXT'
Esta é a foto do PAINEL/odômetro de um veículo. Leia o hodômetro (quilometragem total) e, se visível, o nível de combustível.

Responda com este JSON exato:
{"km":0,"combustivel_pct":null}

Regras:
- "km": apenas o hodômetro TOTAL (odômetro), inteiro, sem o trip parcial. Remova separador de milhar (84.971 -> 84971). Se houver dúvida entre total e trip, escolha o maior número.
- "combustivel_pct": estimativa 0-100 do ponteiro de combustível, ou null se não der para estimar.
- Se não ler o hodômetro com clareza, "km": 0.
TXT;
    } elseif ($tipo === 'pedagio') {
        $userText = <<<'TXT'
Esta é a foto de UM recibo/comprovante de PEDÁGIO no Brasil.

Responda com este JSON exato:
{"praca":"nome da praça/concessionária ou null","valor":0,"data":"dd/mm/aaaa ou null"}

Regras:
- "valor": número com ponto decimal (ex.: 12.40).
- "praca": nome da praça de pedágio ou da concessionária, como escrito.
- Campos ilegíveis: null (ou 0 para o valor).
TXT;
    } elseif ($tipo === 'nota') {
        $userText = <<<'TXT'
Esta é a foto de uma NOTA FISCAL (DANFE/NF-e) brasileira. Leia os campos principais.

Responda com este JSON exato:
{"nf_numero":"número da NF ou null","nf_serie":"série ou null","nf_valor":0,"volumes":0,"peso":0,"emitente":"nome do emitente ou null"}

Regras:
- "nf_numero": número da nota (campo "Nº" / "Número").
- "nf_valor": valor total da nota com ponto decimal.
- "volumes": quantidade de volumes (campo "Qtd. Volumes"), inteiro; 0 se não houver.
- "peso": peso bruto em kg com ponto decimal; 0 se não houver.
- Campos ilegíveis: null (ou 0 para números).
TXT;
    } else { // lacre
        $userText = <<<'TXT'
Esta é a foto de um LACRE de carga/container. Leia o código/número do lacre.

Responda com este JSON exato:
{"lacre":"código do lacre ou null"}

Regras:
- "lacre": apenas o código alfanumérico do lacre, sem espaços extras.
- Ilegível: null.
TXT;
    }

    $res = sf_vision_ocr_call($cfg, $dataUrl, $system, $userText);
    if (!$res['ok']) {
        if ($res['http'] === 429) {
            api_error('Serviço de IA ocupado no momento. Tente novamente em instantes.', 429);
        }
        error_log('ocr_documento.php IA: ' . $res['error']);
        api_error('Não foi possível ler o documento agora. Preencha manualmente.', 502);
    }

    // Log de uso (não-fatal).
    try {
        if (function_exists('sf_openai_log_usage')) {
            sf_openai_log_usage($conn, (int) $empresa_id, $cfg['model'], $res['usage'][0], $res['usage'][1]);
        }
    } catch (Throwable $e) {
        // silencioso
    }

    $parsed = sf_vision_ocr_decode_json($res['content']);
    if ($parsed === null) {
        api_error('A IA não retornou dados legíveis. Preencha manualmente.', 502);
    }

    if ($tipo === 'abastecimento') {
        $litros = sf_vision_ocr_to_float($parsed['litros'] ?? null);
        $valor = sf_vision_ocr_to_float($parsed['valor_total'] ?? ($parsed['valor'] ?? null));
        $vl = sf_vision_ocr_to_float($parsed['valor_litro'] ?? null);
        // Completa o valor/litro que faltar quando há dois dos três.
        if (($vl === null || $vl <= 0) && $litros && $litros > 0 && $valor && $valor > 0) {
            $vl = round($valor / $litros, 3);
        }
        if (($valor === null || $valor <= 0) && $litros && $vl && $litros > 0 && $vl > 0) {
            $valor = round($litros * $vl, 2);
        }
        $combustivel = isset($parsed['combustivel']) && is_string($parsed['combustivel']) ? trim($parsed['combustivel']) : '';
        $data = [
            'posto' => isset($parsed['posto']) && is_string($parsed['posto']) ? trim($parsed['posto']) : null,
            'data' => sf_vision_ocr_to_ymd($parsed['data'] ?? null),
            'hora' => isset($parsed['hora']) && preg_match('/^\d{1,2}:\d{2}/', (string) $parsed['hora']) ? substr((string) $parsed['hora'], 0, 5) : null,
            'litros' => $litros,
            'valor_total' => $valor,
            'valor_litro' => $vl,
            'combustivel' => $combustivel !== '' ? $combustivel : null,
            'numero_nota' => isset($parsed['numero_nota']) && $parsed['numero_nota'] !== '' ? (string) $parsed['numero_nota'] : null,
        ];
    } elseif ($tipo === 'painel') {
        $km = sf_vision_ocr_to_int($parsed['km'] ?? null);
        if ($km !== null && ($km < 1 || $km > 99999999)) {
            $km = null;
        }
        $pct = sf_vision_ocr_to_int($parsed['combustivel_pct'] ?? null);
        if ($pct !== null && ($pct < 0 || $pct > 100)) {
            $pct = null;
        }
        $data = ['km' => $km, 'combustivel_pct' => $pct];
    } elseif ($tipo === 'pedagio') {
        $valor = sf_vision_ocr_to_float($parsed['valor'] ?? ($parsed['valor_total'] ?? null));
        $data = [
            'praca' => isset($parsed['praca']) && is_string($parsed['praca']) ? trim($parsed['praca']) : null,
            'valor' => $valor,
            'data' => sf_vision_ocr_to_ymd($parsed['data'] ?? null),
        ];
    } elseif ($tipo === 'nota') {
        $vol = sf_vision_ocr_to_int($parsed['volumes'] ?? null);
        $peso = sf_vision_ocr_to_float($parsed['peso'] ?? null);
        $data = [
            'nf_numero' => isset($parsed['nf_numero']) && $parsed['nf_numero'] !== '' ? (string) $parsed['nf_numero'] : null,
            'nf_serie' => isset($parsed['nf_serie']) && $parsed['nf_serie'] !== '' ? (string) $parsed['nf_serie'] : null,
            'nf_valor' => sf_vision_ocr_to_float($parsed['nf_valor'] ?? null),
            'volumes' => ($vol !== null && $vol > 0) ? $vol : null,
            'peso' => ($peso !== null && $peso > 0) ? $peso : null,
            'emitente' => isset($parsed['emitente']) && is_string($parsed['emitente']) ? trim($parsed['emitente']) : null,
        ];
    } else { // lacre
        $lacre = isset($parsed['lacre']) && $parsed['lacre'] !== '' ? preg_replace('/\s+/', '', (string) $parsed['lacre']) : null;
        $data = ['lacre' => $lacre];
    }

    api_success(['tipo' => $tipo, 'campos' => $data], 'Documento lido. Revise antes de salvar.');
} catch (PDOException $e) {
    error_log('ocr_documento.php: ' . $e->getMessage());
    api_error('Erro ao processar o documento.', 500);
}
