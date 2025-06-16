<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../db.php';

// Verifica se o motorista está logado
validar_sessao_motorista();

// Obtém dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['empresa_id'];

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $conn = getConnection();
    
    // Obter dados do formulário
    $rota_id = $_POST['rota_id'];
    $veiculo_id = $_POST['veiculo_id'];
    $data_checklist = $_POST['data_checklist'];
    
    // Verificações do veículo
    $oleo_motor = isset($_POST['oleo_motor']) ? 1 : 0;
    $agua_radiador = isset($_POST['agua_radiador']) ? 1 : 0;
    $fluido_freio = isset($_POST['fluido_freio']) ? 1 : 0;
    $fluido_direcao = isset($_POST['fluido_direcao']) ? 1 : 0;
    $combustivel = isset($_POST['combustivel']) ? 1 : 0;
    $pneus = isset($_POST['pneus']) ? 1 : 0;
    $estepe = isset($_POST['estepe']) ? 1 : 0;
    $luzes = isset($_POST['luzes']) ? 1 : 0;
    $buzina = isset($_POST['buzina']) ? 1 : 0;
    $limpador_para_brisa = isset($_POST['limpador_para_brisa']) ? 1 : 0;
    $agua_limpador = isset($_POST['agua_limpador']) ? 1 : 0;
    $freios = isset($_POST['freios']) ? 1 : 0;
    $vazamentos = isset($_POST['vazamentos']) ? 1 : 0;
    $rastreador = isset($_POST['rastreador']) ? 1 : 0;
    
    // Equipamentos de segurança
    $triangulo = isset($_POST['triangulo']) ? 1 : 0;
    $extintor = isset($_POST['extintor']) ? 1 : 0;
    $chave_macaco = isset($_POST['chave_macaco']) ? 1 : 0;
    $cintas = isset($_POST['cintas']) ? 1 : 0;
    $primeiros_socorros = isset($_POST['primeiros_socorros']) ? 1 : 0;
    
    // Documentação
    $doc_veiculo = isset($_POST['doc_veiculo']) ? 1 : 0;
    $cnh = isset($_POST['cnh']) ? 1 : 0;
    $licenciamento = isset($_POST['licenciamento']) ? 1 : 0;
    $seguro = isset($_POST['seguro']) ? 1 : 0;
    $manifesto_carga = isset($_POST['manifesto_carga']) ? 1 : 0;
    $doc_empresa = isset($_POST['doc_empresa']) ? 1 : 0;
    
    // Carga e motorista
    $carga_amarrada = isset($_POST['carga_amarrada']) ? 1 : 0;
    $peso_correto = isset($_POST['peso_correto']) ? 1 : 0;
    $motorista_descansado = isset($_POST['motorista_descansado']) ? 1 : 0;
    $motorista_sobrio = isset($_POST['motorista_sobrio']) ? 1 : 0;
    $celular_carregado = isset($_POST['celular_carregado']) ? 1 : 0;
    $epi = isset($_POST['epi']) ? 1 : 0;
    
    $observacoes = $_POST['observacoes'] ?? null;
    
    // Inserir checklist
    $sql = "INSERT INTO checklist_viagem (
                empresa_id, veiculo_id, motorista_id, rota_id,
                oleo_motor, agua_radiador, fluido_freio, fluido_direcao,
                combustivel, pneus, estepe, luzes, buzina,
                limpador_para_brisa, agua_limpador, freios, vazamentos,
                rastreador, triangulo, extintor, chave_macaco, cintas,
                primeiros_socorros, doc_veiculo, cnh, licenciamento,
                seguro, manifesto_carga, doc_empresa, carga_amarrada,
                peso_correto, motorista_descansado, motorista_sobrio,
                celular_carregado, epi, observacoes, fonte, data_checklist
            ) VALUES (
                :empresa_id, :veiculo_id, :motorista_id, :rota_id,
                :oleo_motor, :agua_radiador, :fluido_freio, :fluido_direcao,
                :combustivel, :pneus, :estepe, :luzes, :buzina,
                :limpador_para_brisa, :agua_limpador, :freios, :vazamentos,
                :rastreador, :triangulo, :extintor, :chave_macaco, :cintas,
                :primeiros_socorros, :doc_veiculo, :cnh, :licenciamento,
                :seguro, :manifesto_carga, :doc_empresa, :carga_amarrada,
                :peso_correto, :motorista_descansado, :motorista_sobrio,
                :celular_carregado, :epi, :observacoes, 'motorista', :data_checklist
            )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'empresa_id' => $empresa_id,
        'veiculo_id' => $veiculo_id,
        'motorista_id' => $motorista_id,
        'rota_id' => $rota_id,
        'oleo_motor' => $oleo_motor,
        'agua_radiador' => $agua_radiador,
        'fluido_freio' => $fluido_freio,
        'fluido_direcao' => $fluido_direcao,
        'combustivel' => $combustivel,
        'pneus' => $pneus,
        'estepe' => $estepe,
        'luzes' => $luzes,
        'buzina' => $buzina,
        'limpador_para_brisa' => $limpador_para_brisa,
        'agua_limpador' => $agua_limpador,
        'freios' => $freios,
        'vazamentos' => $vazamentos,
        'rastreador' => $rastreador,
        'triangulo' => $triangulo,
        'extintor' => $extintor,
        'chave_macaco' => $chave_macaco,
        'cintas' => $cintas,
        'primeiros_socorros' => $primeiros_socorros,
        'doc_veiculo' => $doc_veiculo,
        'cnh' => $cnh,
        'licenciamento' => $licenciamento,
        'seguro' => $seguro,
        'manifesto_carga' => $manifesto_carga,
        'doc_empresa' => $doc_empresa,
        'carga_amarrada' => $carga_amarrada,
        'peso_correto' => $peso_correto,
        'motorista_descansado' => $motorista_descansado,
        'motorista_sobrio' => $motorista_sobrio,
        'celular_carregado' => $celular_carregado,
        'epi' => $epi,
        'observacoes' => $observacoes,
        'data_checklist' => $data_checklist
    ]);
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Checklist registrado com sucesso!'
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao registrar checklist: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao registrar checklist: ' . $e->getMessage()
    ]);
} 