<?php
/**
 * API - Editar Cliente
 * Atualiza dados de um cliente existente
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

if (!$dados || !isset($dados['id'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos ou ID não informado'
    ]);
    exit;
}

// Validar campos obrigatórios
$camposObrigatorios = ['tipoPessoa', 'cpfCnpj', 'nomeRazao', 'situacao', 'matricula'];
$nomesAmigaveis = [
    'tipoPessoa' => 'Tipo de Pessoa',
    'cpfCnpj' => 'CPF/CNPJ',
    'nomeRazao' => 'Nome/Razão Social',
    'situacao' => 'Situação',
    'matricula' => 'MATRÍCULA (Código do Cliente)'
];

foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        $nomeAmigavel = $nomesAmigaveis[$campo] ?? $campo;
        echo json_encode([
            'sucesso' => false,
            'mensagem' => "Campo obrigatório não informado: $nomeAmigavel"
        ]);
        exit;
    }
}

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    $usuario_id = obterUsuarioId();
    $cliente_id = intval($dados['id']);
    
    // Verificar se o cliente pertence à empresa
    $stmt = $db->prepare("
        SELECT codigo FROM seguro_clientes 
        WHERE id = ? AND seguro_empresa_id = ?
    ");
    $stmt->execute([$cliente_id, $empresa_id]);
    $clienteExiste = $stmt->fetch();
    
    if (!$clienteExiste) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Cliente não encontrado ou não pertence à sua empresa'
        ]);
        exit;
    }
    
    // Atualizar cliente
    // Nota: campos removidos: identificador, placa, conjunto (gerenciados por contratos)
    // unidade e porcentagem_recorrencia são read-only ou gerenciados por contratos
    $stmt = $db->prepare("
        UPDATE seguro_clientes 
        SET 
            tipo_pessoa = ?,
            cpf_cnpj = ?,
            nome_razao_social = ?,
            sigla_fantasia = ?,
            cep = ?,
            logradouro = ?,
            numero = ?,
            complemento = ?,
            bairro = ?,
            cidade = ?,
            uf = ?,
            matricula = ?,
            telefone = ?,
            celular = ?,
            email = ?,
            observacoes = ?,
            situacao = ?
        WHERE id = ? AND seguro_empresa_id = ?
    ");
    
    $stmt->execute([
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
        $dados['matricula'],
        $dados['telefone'] ?? null,
        $dados['celular'] ?? null,
        $dados['email'] ?? null,
        $dados['observacoes'] ?? null,
        $dados['situacao'],
        $cliente_id,
        $empresa_id
    ]);
    
    // Registrar log
    registrarLog(
        $empresa_id,
        $usuario_id,
        'editar',
        'clientes',
        "Cliente {$dados['nomeRazao']} atualizado (Código: {$clienteExiste['codigo']})"
    );
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Cliente atualizado com sucesso!',
        'codigo' => $clienteExiste['codigo']
    ]);
    
} catch(PDOException $e) {
    error_log("Erro ao editar cliente: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar cliente: ' . $e->getMessage()
    ]);
}
?>

