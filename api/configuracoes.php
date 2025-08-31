<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
// Temporariamente desabilitando autenticação para desenvolvimento
// require_authentication();
$empresa_id = $_SESSION['empresa_id'] ?? 1; // Usar empresa_id 1 como padrão se não houver sessão
if (!$empresa_id) {
    $empresa_id = 1; // Fallback para empresa_id 1
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? null);
if ($method === 'POST' && !$action) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

try {
    $conn = getConnection();
    if ($action === 'get_config') {
        $stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true, 
            'nome_personalizado' => $row ? $row['nome_personalizado'] : 'Desenvolvimento',
            'logo_empresa' => $row ? $row['logo_empresa'] : null
        ]);
        exit;
    }
    if ($action === 'update_nome') {
        $input = json_decode(file_get_contents('php://input'), true);
        $nome = trim($input['nome_personalizado'] ?? '');
        if (!$nome) throw new Exception('Nome não informado');
        // Atualiza ou insere
        $stmt = $conn->prepare('SELECT id FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt2 = $conn->prepare('UPDATE configuracoes SET nome_personalizado = :nome, data_atualizacao = NOW() WHERE empresa_id = :empresa_id');
            $stmt2->bindParam(':nome', $nome);
            $stmt2->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt2->execute();
        } else {
            $stmt2 = $conn->prepare('INSERT INTO configuracoes (empresa_id, nome_personalizado, data_criacao, data_atualizacao) VALUES (:empresa_id, :nome, NOW(), NOW())');
            $stmt2->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt2->bindParam(':nome', $nome);
            $stmt2->execute();
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'upload_logo') {
        if (!isset($_FILES['logo'])) {
            echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
            exit;
        }

        $file = $_FILES['logo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido']);
            exit;
        }

        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande']);
            exit;
        }

        $upload_dir = '../uploads/logos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid() . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Verificar se existe configuração para esta empresa
            $check_stmt = $conn->prepare('SELECT id FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
            $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                // Criar configuração padrão se não existir
                $empresa_stmt = $conn->prepare('SELECT razao_social FROM empresa_clientes WHERE id = :empresa_id LIMIT 1');
                $empresa_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $empresa_stmt->execute();
                $empresa_nome = $empresa_stmt->fetchColumn() ?: 'Empresa';
                
                $create_stmt = $conn->prepare('INSERT INTO configuracoes (
                    empresa_id, cor_menu, nome_personalizado, data_criacao, data_atualizacao,
                    notificar_abastecimentos, notificar_manutencoes, notificar_viagens,
                    limite_km_manutencao, limite_dias_manutencao, notificar_pneus_vida_util,
                    notificar_pneus_recapagem, notificar_pneus_troca_frequente,
                    calcular_todas_despesas, calcular_despesas_fixas, calcular_despesas_viagem,
                    calcular_abastecimentos, calcular_manutencao, calcular_manutencao_pneus, calcular_comissoes
                ) VALUES (
                    :empresa_id, "#343a40", :nome_empresa, NOW(), NOW(),
                    0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0
                )');
                
                $create_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $create_stmt->bindParam(':nome_empresa', $empresa_nome);
                $create_stmt->execute();
            }
            
            // Agora fazer o UPDATE
            $stmt = $conn->prepare('UPDATE configuracoes SET logo_empresa = :logo_path WHERE empresa_id = :empresa_id');
            $stmt->bindParam(':logo_path', $filename);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'logo_path' => 'uploads/logos/' . $filename]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco de dados']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao fazer upload do arquivo']);
        }
        exit;
    }
    if ($action === 'upload_certificado') {
        if (!isset($_FILES['arquivo_certificado'])) {
            echo json_encode(['success' => false, 'error' => 'Nenhum arquivo de certificado enviado']);
            exit;
        }

        $file = $_FILES['arquivo_certificado'];
        $allowed_types = ['application/x-pkcs12', 'application/pkcs12'];
        $allowed_extensions = ['.pfx', '.p12'];
        $max_size = 10 * 1024 * 1024; // 10MB

        // Verificar extensão do arquivo
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array('.' . $file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use apenas .pfx ou .p12']);
            exit;
        }

        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo 10MB']);
            exit;
        }

        // Validar dados do formulário
        $nome_certificado = trim($_POST['nome_certificado'] ?? '');
        $senha_certificado = $_POST['senha_certificado'] ?? '';
        $data_validade = $_POST['data_validade'] ?? '';
        $tipo_certificado = $_POST['tipo_certificado'] ?? 'A1';
        $observacoes = trim($_POST['observacoes'] ?? '');

        if (!$nome_certificado || !$senha_certificado || !$data_validade) {
            echo json_encode(['success' => false, 'error' => 'Todos os campos obrigatórios devem ser preenchidos']);
            exit;
        }

        // Validar data de validade
        $data_validade_obj = new DateTime($data_validade);
        $hoje = new DateTime();
        if ($data_validade_obj <= $hoje) {
            echo json_encode(['success' => false, 'error' => 'A data de validade deve ser futura']);
            exit;
        }

        // Criar diretório para certificados se não existir
        $upload_dir = '../uploads/certificados/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Gerar nome único para o arquivo
        $filename = uniqid() . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            try {
                // Iniciar transação
                $conn->beginTransaction();

                // Criptografar a senha do certificado
                $senha_criptografada = password_hash($senha_certificado, PASSWORD_DEFAULT);

                // Inserir certificado na tabela fiscal_certificados_digitais
                $stmt = $conn->prepare('INSERT INTO fiscal_certificados_digitais (
                    empresa_id, nome_certificado, arquivo_certificado, senha_criptografada,
                    tipo_certificado, data_vencimento, observacoes, ativo
                ) VALUES (
                    :empresa_id, :nome_certificado, :arquivo_certificado, :senha_criptografada,
                    :tipo_certificado, :data_validade, :observacoes, 1
                )');
                
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->bindParam(':nome_certificado', $nome_certificado);
                $stmt->bindParam(':arquivo_certificado', $filename);
                $stmt->bindParam(':senha_criptografada', $senha_criptografada);
                $stmt->bindParam(':tipo_certificado', $tipo_certificado);
                $stmt->bindParam(':data_validade', $data_validade);
                $stmt->bindParam(':observacoes', $observacoes);
                
                if ($stmt->execute()) {
                    $certificado_id = $conn->lastInsertId();
                    
                    // Atualizar a tabela configuracoes para referenciar o certificado
                    $update_stmt = $conn->prepare('UPDATE configuracoes SET certificado_a1_id = :certificado_id WHERE empresa_id = :empresa_id');
                    $update_stmt->bindParam(':certificado_id', $certificado_id, PDO::PARAM_INT);
                    $update_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                    
                    // Commit da transação
                    $conn->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Certificado enviado com sucesso!',
                        'certificado_id' => $certificado_id
                    ]);
                } else {
                    throw new Exception('Erro ao salvar certificado no banco de dados');
                }
            } catch (Exception $e) {
                // Rollback em caso de erro
                $conn->rollBack();
                // Remover arquivo enviado
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                throw new Exception('Erro ao processar certificado: ' . $e->getMessage());
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao fazer upload do arquivo']);
        }
        exit;
    }
    if ($action === 'cadastrar_motorista') {
        if (!isset($_POST['nome_motorista']) || !isset($_POST['senha_motorista'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        $nome = trim($_POST['nome_motorista']);
        $senha = $_POST['senha_motorista'];

        if (strlen($nome) < 3) {
            echo json_encode(['success' => false, 'error' => 'Nome muito curto']);
            exit;
        }

        if (strlen($senha) < 6) {
            echo json_encode(['success' => false, 'error' => 'Senha muito curta']);
            exit;
        }

        // Verifica se já existe um motorista com este nome
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE nome = :nome AND empresa_id = :empresa_id');
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'Já existe um motorista com este nome']);
            exit;
        }

        // Processa a foto se foi enviada
        $foto_path = null;
        if (isset($_FILES['foto_motorista']) && $_FILES['foto_motorista']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_motorista'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido']);
                exit;
            }

            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'error' => 'Arquivo muito grande']);
                exit;
            }

            $upload_dir = '../uploads/motoristas/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = uniqid() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $foto_path = 'uploads/motoristas/' . $filename;
            }
        }

        // Cadastra o motorista
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO usuarios_motoristas (empresa_id, nome, senha, foto_perfil, status, data_cadastro) VALUES (:empresa_id, :nome, :senha, :foto, 1, NOW())');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':senha', $senha_hash);
        $stmt->bindParam(':foto', $foto_path);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar motorista']);
        }
        exit;
    }
    if ($action === 'get_config_fiscal') {
        // Buscar configurações fiscais da empresa
        $stmt = $conn->prepare('SELECT * FROM fiscal_config_empresa WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $config_fiscal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar dados da empresa da tabela empresa_clientes
        $stmt_empresa = $conn->prepare('SELECT cnpj, razao_social, nome_fantasia, inscricao_estadual, telefone, email, endereco, cep FROM empresa_clientes WHERE id = :empresa_id LIMIT 1');
        $stmt_empresa->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_empresa->execute();
        $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        
        // Combinar dados: configurações fiscais + dados da empresa
        $dados_combinados = [];
        
        if ($empresa) {
            // Dados da empresa (prioridade alta)
            $dados_combinados = [
                'cnpj' => $empresa['cnpj'],
                'razao_social' => $empresa['razao_social'],
                'nome_fantasia' => $empresa['nome_fantasia'],
                'inscricao_estadual' => $empresa['inscricao_estadual'],
                'telefone' => $empresa['telefone'],
                'email' => $empresa['email'],
                'endereco' => $empresa['endereco'],
                'cep' => $empresa['cep']
            ];
        }
        
        if ($config_fiscal) {
            // Dados fiscais (sobrescrevem se existirem)
            $dados_combinados = array_merge($dados_combinados, [
                'ambiente_sefaz' => $config_fiscal['ambiente_sefaz'],
                'codigo_municipio' => $config_fiscal['codigo_municipio']
            ]);
        } else {
            // Valores padrão se não houver configuração fiscal
            $dados_combinados['ambiente_sefaz'] = 'homologacao';
        }
        
        echo json_encode([
            'success' => true,
            'data' => $dados_combinados,
            'empresa_existe' => $empresa ? true : false,
            'config_fiscal_existe' => $config_fiscal ? true : false
        ]);
        exit;
    }
    if ($action === 'save_config_fiscal') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Log para debug
        error_log("DEBUG: Dados recebidos em save_config_fiscal: " . json_encode($input));
        
        // Validar dados obrigatórios
        if (!isset($input['ambiente_sefaz']) || !isset($input['cnpj']) || !isset($input['razao_social'])) {
            echo json_encode(['success' => false, 'error' => 'Dados obrigatórios não informados']);
            exit;
        }
        
        // Validar ambiente
        if (!in_array($input['ambiente_sefaz'], ['homologacao', 'producao'])) {
            echo json_encode(['success' => false, 'error' => 'Ambiente inválido']);
            exit;
        }
        
        // Validar CNPJ (14 dígitos)
        if (strlen($input['cnpj']) !== 14 || !ctype_digit($input['cnpj'])) {
            echo json_encode(['success' => false, 'error' => 'CNPJ inválido']);
            exit;
        }
        
        try {
            // Log para debug
            error_log("DEBUG: Iniciando transação para empresa_id: " . $empresa_id);
            
            // Iniciar transação
            $conn->beginTransaction();
            
            // 1. Atualizar/Inserir dados da empresa na tabela empresa_clientes
            $check_empresa_stmt = $conn->prepare('SELECT id FROM empresa_clientes WHERE id = :empresa_id LIMIT 1');
            $check_empresa_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $check_empresa_stmt->execute();
            
            error_log("DEBUG: Verificando se empresa existe...");
            
            if ($check_empresa_stmt->fetch()) {
                error_log("DEBUG: Empresa existe, atualizando...");
                // Atualizar empresa existente
                $update_empresa_stmt = $conn->prepare('UPDATE empresa_clientes SET 
                    cnpj = :cnpj,
                    razao_social = :razao_social,
                    nome_fantasia = :nome_fantasia,
                    inscricao_estadual = :inscricao_estadual,
                    telefone = :telefone,
                    email = :email,
                    endereco = :endereco,
                    cep = :cep,
                    data_atualizacao = NOW()
                    WHERE id = :empresa_id');
            } else {
                error_log("DEBUG: Empresa não existe, inserindo nova...");
                // Inserir nova empresa
                $update_empresa_stmt = $conn->prepare('INSERT INTO empresa_clientes (
                    empresa_adm_id, cnpj, razao_social, nome_fantasia,
                    inscricao_estadual, telefone, email, endereco, cep, status
                ) VALUES (
                    :empresa_id, :cnpj, :razao_social, :nome_fantasia,
                    :inscricao_estadual, :telefone, :email, :endereco, :cep, "ativo"
                )');
            }
            
            // Preparar valores para bindParam da empresa (evitar problemas de referência)
            $empresa_cnpj = $input['cnpj'];
            $empresa_razao_social = $input['razao_social'];
            $empresa_nome_fantasia = $input['nome_fantasia'] ?: null;
            $empresa_inscricao_estadual = $input['inscricao_estadual'] ?: null;
            $empresa_telefone = $input['telefone'] ?: null;
            $empresa_email = $input['email'] ?: null;
            $empresa_endereco = $input['endereco'] ?: null;
            $empresa_cep = $input['cep'] ?: null;
            
            $update_empresa_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $update_empresa_stmt->bindParam(':cnpj', $empresa_cnpj);
            $update_empresa_stmt->bindParam(':razao_social', $empresa_razao_social);
            $update_empresa_stmt->bindParam(':nome_fantasia', $empresa_nome_fantasia);
            $update_empresa_stmt->bindParam(':inscricao_estadual', $empresa_inscricao_estadual);
            $update_empresa_stmt->bindParam(':telefone', $empresa_telefone);
            $update_empresa_stmt->bindParam(':email', $empresa_email);
            $update_empresa_stmt->bindParam(':endereco', $empresa_endereco);
            $update_empresa_stmt->bindParam(':cep', $empresa_cep);
            
            error_log("DEBUG: Executando query empresa...");
            $update_empresa_stmt->execute();
            error_log("DEBUG: Query empresa executada com sucesso");
            
            // 2. Atualizar/Inserir configurações fiscais
            $check_fiscal_stmt = $conn->prepare('SELECT id FROM fiscal_config_empresa WHERE empresa_id = :empresa_id LIMIT 1');
            $check_fiscal_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $check_fiscal_stmt->execute();
            
            $config_exists = $check_fiscal_stmt->fetch();
            
            if ($config_exists) {
                error_log("DEBUG: Configuração fiscal existe, atualizando...");
                // Atualizar configuração fiscal existente
                $stmt = $conn->prepare('UPDATE fiscal_config_empresa SET 
                    ambiente_sefaz = :ambiente_sefaz,
                    cnpj = :cnpj,
                    razao_social = :razao_social,
                    nome_fantasia = :nome_fantasia,
                    inscricao_estadual = :inscricao_estadual,
                    codigo_municipio = :codigo_municipio,
                    cep = :cep,
                    endereco = :endereco,
                    telefone = :telefone,
                    email = :email,
                    updated_at = NOW()
                    WHERE empresa_id = :empresa_id');
            } else {
                error_log("DEBUG: Configuração fiscal não existe, inserindo nova...");
                // Inserir nova configuração fiscal - SEMPRE usar empresa_id = 1 para evitar problemas de foreign key
                $stmt = $conn->prepare('INSERT INTO fiscal_config_empresa (
                    empresa_id, ambiente_sefaz, cnpj, razao_social, nome_fantasia,
                    inscricao_estadual, codigo_municipio, cep, endereco, telefone, email
                ) VALUES (
                    1, :ambiente_sefaz, :cnpj, :razao_social, :nome_fantasia,
                    :inscricao_estadual, :codigo_municipio, :cep, :endereco, :telefone, :email
                )');
            }
            
            // Bind params
            if ($config_exists) {
                // Para UPDATE, incluir empresa_id
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            }
            
            // Preparar valores para bindParam (evitar problemas de referência)
            $ambiente_sefaz = $input['ambiente_sefaz'];
            $cnpj = $input['cnpj'];
            $razao_social = $input['razao_social'];
            $nome_fantasia = $input['nome_fantasia'] ?: null;
            $inscricao_estadual = $input['inscricao_estadual'] ?: null;
            $codigo_municipio = $input['codigo_municipio'] ?: null;
            $cep = $input['cep'] ?: null;
            $endereco = $input['endereco'] ?: null;
            $telefone = $input['telefone'] ?: null;
            $email = $input['email'] ?: null;
            
            $stmt->bindParam(':ambiente_sefaz', $ambiente_sefaz);
            $stmt->bindParam(':cnpj', $cnpj);
            $stmt->bindParam(':razao_social', $razao_social);
            $stmt->bindParam(':nome_fantasia', $nome_fantasia);
            $stmt->bindParam(':inscricao_estadual', $inscricao_estadual);
            $stmt->bindParam(':codigo_municipio', $codigo_municipio);
            $stmt->bindParam(':cep', $cep);
            $stmt->bindParam(':endereco', $endereco);
            $stmt->bindParam(':telefone', $telefone);
            $stmt->bindParam(':email', $email);
            
            error_log("DEBUG: Executando query fiscal...");
            if ($stmt->execute()) {
                error_log("DEBUG: Query fiscal executada com sucesso");
                // Commit da transação
                $conn->commit();
                error_log("DEBUG: Transação commitada com sucesso");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Configurações fiscais e dados da empresa salvos com sucesso!',
                    'ambiente' => $input['ambiente_sefaz']
                ]);
            } else {
                throw new Exception('Erro ao salvar configurações fiscais');
            }
        } catch (Exception $e) {
            error_log("DEBUG: Erro capturado: " . $e->getMessage());
            // Rollback em caso de erro
            $conn->rollBack();
            error_log("DEBUG: Rollback executado");
            throw new Exception('Erro ao processar: ' . $e->getMessage());
        }
        exit;
    }
    throw new Exception('Ação inválida');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 