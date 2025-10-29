<?php
/**
 * API - Salvar Cliente
 * Endpoint para criar/atualizar clientes comissionados
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
if (!isset($_SESSION['seguro_logado']) || $_SESSION['seguro_logado'] !== true) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido'
    ]);
    exit;
}

// Obter dados
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos'
    ]);
    exit;
}

// Validar campos obrigatórios
$camposObrigatorios = ['tipoPessoa', 'cpfCnpj', 'nomeRazao', 'situacao'];
foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => "Campo obrigatório não informado: $campo"
        ]);
        exit;
    }
}

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    $usuario_id = obterUsuarioId();
    
    // Verificar se CPF/CNPJ já existe
    $stmt = $db->prepare("
        SELECT id FROM seguro_clientes 
        WHERE seguro_empresa_id = ? AND cpf_cnpj = ?
    ");
    $stmt->execute([$empresa_id, $dados['cpfCnpj']]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'CPF/CNPJ já cadastrado'
        ]);
        exit;
    }
    
    // Gerar código automático se não informado
    $codigo = null;
    if (empty($dados['codigo'])) {
        // Buscar último código
        $stmt = $db->prepare("
            SELECT MAX(CAST(codigo AS UNSIGNED)) as ultimo_codigo 
            FROM seguro_clientes 
            WHERE seguro_empresa_id = ?
        ");
        $stmt->execute([$empresa_id]);
        $resultado = $stmt->fetch();
        $codigo = ($resultado['ultimo_codigo'] ?? 30000) + 1;
    } else {
        $codigo = $dados['codigo'];
    }
    
    // Inserir cliente
    $stmt = $db->prepare("
        INSERT INTO seguro_clientes (
            seguro_empresa_id,
            codigo,
            tipo_pessoa,
            cpf_cnpj,
            nome_razao_social,
            sigla_fantasia,
            cep,
            logradouro,
            numero,
            complemento,
            bairro,
            cidade,
            uf,
            identificador,
            placa,
            conjunto,
            matricula,
            telefone,
            celular,
            email,
            unidade,
            porcentagem_recorrencia,
            observacoes,
            situacao
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");
    
    $stmt->execute([
        $empresa_id,
        $codigo,
        $dados['tipoPessoa'],
        $dados['cpfCnpj'],
        $dados['nomeRazao'],
        $dados['sigla'] ?? null,
        $dados['cep'] ?? null,
        $dados['logradouro'] ?? null,
        $dados['numero'] ?? null,
        $dados['complemento'] ?? null,
        $dados['bairro'] ?? null,
        $dados['cidade'] ?? null,
        $dados['uf'] ?? null,
        $dados['identificador'] ?? null,
        $dados['placa'] ?? null,
        $dados['conjunto'] ?? null,
        $dados['matricula'] ?? null,
        $dados['telefone'] ?? null,
        $dados['celular'] ?? null,
        $dados['email'] ?? null,
        $dados['unidade'] ?? null,
        $dados['porcentagemRecorrencia'] ?? 0.00,
        $dados['observacoes'] ?? null,
        $dados['situacao']
    ]);
    
    $cliente_id = $db->lastInsertId();
    
    // Registrar log
    registrarLog(
        $empresa_id,
        $usuario_id,
        'criar',
        'clientes',
        "Cliente {$dados['nomeRazao']} cadastrado (Código: $codigo)"
    );
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Cliente cadastrado com sucesso!',
        'cliente_id' => $cliente_id,
        'codigo' => $codigo
    ]);
    
} catch(PDOException $e) {
    error_log("Erro ao salvar cliente: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao salvar cliente. Tente novamente.'
    ]);
}
?>

