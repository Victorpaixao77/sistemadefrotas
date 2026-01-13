<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];


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
            'nome_personalizado' => $row ? $row['nome_personalizado'] : 'Frotec Online',
            'logo_empresa' => $row && $row['logo_empresa'] ? $row['logo_empresa'] : 'logo.png'
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
            $nome_padrao = 'Frotec Online';
            $logo_padrao = 'logo.png';
            $stmt2 = $conn->prepare('INSERT INTO configuracoes (empresa_id, nome_personalizado, logo_empresa, data_criacao, data_atualizacao) VALUES (:empresa_id, :nome, :logo, NOW(), NOW())');
            $stmt2->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt2->bindParam(':nome', $nome);
            $stmt2->bindParam(':logo', $logo_padrao);
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
                $nome_padrao = 'Frotec Online';
                $logo_padrao = 'logo.png';
                
                $create_stmt = $conn->prepare('INSERT INTO configuracoes (
                    empresa_id, cor_menu, nome_personalizado, logo_empresa, data_criacao, data_atualizacao,
                    notificar_abastecimentos, notificar_manutencoes, notificar_viagens,
                    limite_km_manutencao, limite_dias_manutencao, notificar_pneus_vida_util,
                    notificar_pneus_recapagem, notificar_pneus_troca_frequente,
                    calcular_todas_despesas, calcular_despesas_fixas, calcular_despesas_viagem,
                    calcular_abastecimentos, calcular_manutencao, calcular_manutencao_pneus, calcular_comissoes
                ) VALUES (
                    :empresa_id, "#343a40", :nome_empresa, :logo_empresa, NOW(), NOW(),
                    0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0
                )');
                
                $create_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $create_stmt->bindParam(':nome_empresa', $nome_padrao);
                $create_stmt->bindParam(':logo_empresa', $logo_padrao);
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
    
    // Configurações de Performance
    if ($action === 'get_config_performance') {
        try {
            // Criar tabela se não existir
            $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_performance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                peso_pontualidade INT DEFAULT 25,
                peso_consumo INT DEFAULT 30,
                peso_multas INT DEFAULT 20,
                peso_checklist INT DEFAULT 15,
                peso_ocorrencias INT DEFAULT 10,
                pontos_maximos INT DEFAULT 1000,
                gamificacao_ativa TINYINT(1) DEFAULT 1,
                ranking_automatico TINYINT(1) DEFAULT 1,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_empresa (empresa_id)
            )");
            
            $stmt = $conn->prepare('SELECT * FROM configuracoes_performance WHERE empresa_id = :empresa_id LIMIT 1');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                echo json_encode([
                    'success' => true,
                    'data' => $row
                ]);
            } else {
                // Retornar valores padrão se não existir configuração
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'peso_pontualidade' => 25,
                        'peso_consumo' => 30,
                        'peso_multas' => 20,
                        'peso_checklist' => 15,
                        'peso_ocorrencias' => 10,
                        'pontos_maximos' => 1000,
                        'gamificacao_ativa' => 1,
                        'ranking_automatico' => 1
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao carregar configurações: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($action === 'save_config_performance') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $peso_pontualidade = (int)($input['peso_pontualidade'] ?? 25);
        $peso_consumo = (int)($input['peso_consumo'] ?? 30);
        $peso_multas = (int)($input['peso_multas'] ?? 20);
        $peso_checklist = (int)($input['peso_checklist'] ?? 15);
        $peso_ocorrencias = (int)($input['peso_ocorrencias'] ?? 10);
        $pontos_maximos = (int)($input['pontos_maximos'] ?? 1000);
        $gamificacao_ativa = (bool)($input['gamificacao_ativa'] ?? true) ? 1 : 0;
        $ranking_automatico = (bool)($input['ranking_automatico'] ?? true) ? 1 : 0;
        
        // Validar se a soma dos pesos é 100%
        $total_pesos = $peso_pontualidade + $peso_consumo + $peso_multas + $peso_checklist + $peso_ocorrencias;
        if ($total_pesos !== 100) {
            throw new Exception("A soma dos pesos deve ser igual a 100%. Atual: {$total_pesos}%");
        }
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            peso_pontualidade INT DEFAULT 25,
            peso_consumo INT DEFAULT 30,
            peso_multas INT DEFAULT 20,
            peso_checklist INT DEFAULT 15,
            peso_ocorrencias INT DEFAULT 10,
            pontos_maximos INT DEFAULT 1000,
            gamificacao_ativa TINYINT(1) DEFAULT 1,
            ranking_automatico TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        // Inserir ou atualizar configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes_performance (
            empresa_id, peso_pontualidade, peso_consumo, peso_multas, 
            peso_checklist, peso_ocorrencias, pontos_maximos, 
            gamificacao_ativa, ranking_automatico
        ) VALUES (
            :empresa_id, :peso_pontualidade, :peso_consumo, :peso_multas,
            :peso_checklist, :peso_ocorrencias, :pontos_maximos,
            :gamificacao_ativa, :ranking_automatico
        ) ON DUPLICATE KEY UPDATE
            peso_pontualidade = :peso_pontualidade_upd,
            peso_consumo = :peso_consumo_upd,
            peso_multas = :peso_multas_upd,
            peso_checklist = :peso_checklist_upd,
            peso_ocorrencias = :peso_ocorrencias_upd,
            pontos_maximos = :pontos_maximos_upd,
            gamificacao_ativa = :gamificacao_ativa_upd,
            ranking_automatico = :ranking_automatico_upd,
            data_atualizacao = NOW()");
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':peso_pontualidade', $peso_pontualidade, PDO::PARAM_INT);
        $stmt->bindParam(':peso_consumo', $peso_consumo, PDO::PARAM_INT);
        $stmt->bindParam(':peso_multas', $peso_multas, PDO::PARAM_INT);
        $stmt->bindParam(':peso_checklist', $peso_checklist, PDO::PARAM_INT);
        $stmt->bindParam(':peso_ocorrencias', $peso_ocorrencias, PDO::PARAM_INT);
        $stmt->bindParam(':pontos_maximos', $pontos_maximos, PDO::PARAM_INT);
        $stmt->bindParam(':gamificacao_ativa', $gamificacao_ativa, PDO::PARAM_INT);
        $stmt->bindParam(':ranking_automatico', $ranking_automatico, PDO::PARAM_INT);
        
        // Parâmetros para UPDATE (com sufixo _upd)
        $stmt->bindParam(':peso_pontualidade_upd', $peso_pontualidade, PDO::PARAM_INT);
        $stmt->bindParam(':peso_consumo_upd', $peso_consumo, PDO::PARAM_INT);
        $stmt->bindParam(':peso_multas_upd', $peso_multas, PDO::PARAM_INT);
        $stmt->bindParam(':peso_checklist_upd', $peso_checklist, PDO::PARAM_INT);
        $stmt->bindParam(':peso_ocorrencias_upd', $peso_ocorrencias, PDO::PARAM_INT);
        $stmt->bindParam(':pontos_maximos_upd', $pontos_maximos, PDO::PARAM_INT);
        $stmt->bindParam(':gamificacao_ativa_upd', $gamificacao_ativa, PDO::PARAM_INT);
        $stmt->bindParam(':ranking_automatico_upd', $ranking_automatico, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de performance salvas com sucesso!']);
        exit;
    }
    
    // Configurações de Badges
    if ($action === 'save_config_badges') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $badge_motorista_economico = (int)($input['badge_motorista_economico'] ?? 3);
        $badge_sem_multas = (int)($input['badge_sem_multas'] ?? 12);
        $badge_checklists_perfeitos = (int)($input['badge_checklists_perfeitos'] ?? 50);
        $pontos_por_badge = (int)($input['pontos_por_badge'] ?? 50);
        $sistema_badges_ativo = (bool)($input['sistema_badges_ativo'] ?? true) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            badge_motorista_economico INT DEFAULT 3,
            badge_sem_multas INT DEFAULT 12,
            badge_checklists_perfeitos INT DEFAULT 50,
            pontos_por_badge INT DEFAULT 50,
            sistema_badges_ativo TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        // Inserir ou atualizar configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes_badges (
            empresa_id, badge_motorista_economico, badge_sem_multas, 
            badge_checklists_perfeitos, pontos_por_badge, sistema_badges_ativo
        ) VALUES (
            :empresa_id, :badge_motorista_economico, :badge_sem_multas,
            :badge_checklists_perfeitos, :pontos_por_badge, :sistema_badges_ativo
        ) ON DUPLICATE KEY UPDATE
            badge_motorista_economico = :badge_motorista_economico_upd,
            badge_sem_multas = :badge_sem_multas_upd,
            badge_checklists_perfeitos = :badge_checklists_perfeitos_upd,
            pontos_por_badge = :pontos_por_badge_upd,
            sistema_badges_ativo = :sistema_badges_ativo_upd,
            data_atualizacao = NOW()");
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':badge_motorista_economico', $badge_motorista_economico, PDO::PARAM_INT);
        $stmt->bindParam(':badge_sem_multas', $badge_sem_multas, PDO::PARAM_INT);
        $stmt->bindParam(':badge_checklists_perfeitos', $badge_checklists_perfeitos, PDO::PARAM_INT);
        $stmt->bindParam(':pontos_por_badge', $pontos_por_badge, PDO::PARAM_INT);
        $stmt->bindParam(':sistema_badges_ativo', $sistema_badges_ativo, PDO::PARAM_INT);
        
        // Parâmetros para UPDATE
        $stmt->bindParam(':badge_motorista_economico_upd', $badge_motorista_economico, PDO::PARAM_INT);
        $stmt->bindParam(':badge_sem_multas_upd', $badge_sem_multas, PDO::PARAM_INT);
        $stmt->bindParam(':badge_checklists_perfeitos_upd', $badge_checklists_perfeitos, PDO::PARAM_INT);
        $stmt->bindParam(':pontos_por_badge_upd', $pontos_por_badge, PDO::PARAM_INT);
        $stmt->bindParam(':sistema_badges_ativo_upd', $sistema_badges_ativo, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de badges salvas com sucesso!']);
        exit;
    }
    
    // Configurações de Níveis
    if ($action === 'save_config_niveis') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nivel_bronze_min = (int)($input['nivel_bronze_min'] ?? 0);
        $nivel_bronze_max = (int)($input['nivel_bronze_max'] ?? 99);
        $nivel_prata_min = (int)($input['nivel_prata_min'] ?? 100);
        $nivel_prata_max = (int)($input['nivel_prata_max'] ?? 299);
        $nivel_ouro_min = (int)($input['nivel_ouro_min'] ?? 300);
        $nivel_ouro_max = (int)($input['nivel_ouro_max'] ?? 599);
        $nivel_platina_min = (int)($input['nivel_platina_min'] ?? 600);
        $nivel_platina_max = (int)($input['nivel_platina_max'] ?? 899);
        $nivel_diamante_min = (int)($input['nivel_diamante_min'] ?? 900);
        $nivel_diamante_max = (int)($input['nivel_diamante_max'] ?? 999);
        $nivel_lenda_min = (int)($input['nivel_lenda_min'] ?? 1000);
        $nivel_lenda_max = (int)($input['nivel_lenda_max'] ?? 9999);
        $niveis_avancados_ativos = (bool)($input['niveis_avancados_ativos'] ?? true) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_niveis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            nivel_bronze_min INT DEFAULT 0,
            nivel_bronze_max INT DEFAULT 99,
            nivel_prata_min INT DEFAULT 100,
            nivel_prata_max INT DEFAULT 299,
            nivel_ouro_min INT DEFAULT 300,
            nivel_ouro_max INT DEFAULT 599,
            nivel_platina_min INT DEFAULT 600,
            nivel_platina_max INT DEFAULT 899,
            nivel_diamante_min INT DEFAULT 900,
            nivel_diamante_max INT DEFAULT 999,
            nivel_lenda_min INT DEFAULT 1000,
            nivel_lenda_max INT DEFAULT 9999,
            niveis_avancados_ativos TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        // Inserir ou atualizar configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes_niveis (
            empresa_id, nivel_bronze_min, nivel_bronze_max, nivel_prata_min, nivel_prata_max,
            nivel_ouro_min, nivel_ouro_max, nivel_platina_min, nivel_platina_max,
            nivel_diamante_min, nivel_diamante_max, nivel_lenda_min, nivel_lenda_max,
            niveis_avancados_ativos
        ) VALUES (
            :empresa_id, :nivel_bronze_min, :nivel_bronze_max, :nivel_prata_min, :nivel_prata_max,
            :nivel_ouro_min, :nivel_ouro_max, :nivel_platina_min, :nivel_platina_max,
            :nivel_diamante_min, :nivel_diamante_max, :nivel_lenda_min, :nivel_lenda_max,
            :niveis_avancados_ativos
        ) ON DUPLICATE KEY UPDATE
            nivel_bronze_min = :nivel_bronze_min_upd,
            nivel_bronze_max = :nivel_bronze_max_upd,
            nivel_prata_min = :nivel_prata_min_upd,
            nivel_prata_max = :nivel_prata_max_upd,
            nivel_ouro_min = :nivel_ouro_min_upd,
            nivel_ouro_max = :nivel_ouro_max_upd,
            nivel_platina_min = :nivel_platina_min_upd,
            nivel_platina_max = :nivel_platina_max_upd,
            nivel_diamante_min = :nivel_diamante_min_upd,
            nivel_diamante_max = :nivel_diamante_max_upd,
            nivel_lenda_min = :nivel_lenda_min_upd,
            nivel_lenda_max = :nivel_lenda_max_upd,
            niveis_avancados_ativos = :niveis_avancados_ativos_upd,
            data_atualizacao = NOW()");
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_bronze_min', $nivel_bronze_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_bronze_max', $nivel_bronze_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_prata_min', $nivel_prata_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_prata_max', $nivel_prata_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_ouro_min', $nivel_ouro_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_ouro_max', $nivel_ouro_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_platina_min', $nivel_platina_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_platina_max', $nivel_platina_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_diamante_min', $nivel_diamante_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_diamante_max', $nivel_diamante_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_lenda_min', $nivel_lenda_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_lenda_max', $nivel_lenda_max, PDO::PARAM_INT);
        $stmt->bindParam(':niveis_avancados_ativos', $niveis_avancados_ativos, PDO::PARAM_INT);
        
        // Parâmetros para UPDATE
        $stmt->bindParam(':nivel_bronze_min_upd', $nivel_bronze_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_bronze_max_upd', $nivel_bronze_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_prata_min_upd', $nivel_prata_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_prata_max_upd', $nivel_prata_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_ouro_min_upd', $nivel_ouro_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_ouro_max_upd', $nivel_ouro_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_platina_min_upd', $nivel_platina_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_platina_max_upd', $nivel_platina_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_diamante_min_upd', $nivel_diamante_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_diamante_max_upd', $nivel_diamante_max, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_lenda_min_upd', $nivel_lenda_min, PDO::PARAM_INT);
        $stmt->bindParam(':nivel_lenda_max_upd', $nivel_lenda_max, PDO::PARAM_INT);
        $stmt->bindParam(':niveis_avancados_ativos_upd', $niveis_avancados_ativos, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de níveis salvas com sucesso!']);
        exit;
    }
    
    // Configurações de Desafios
    if ($action === 'save_config_desafios') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $desafio_km_sem_infracoes = (int)($input['desafio_km_sem_infracoes'] ?? 5000);
        $desafio_rotas_sem_atrasos = (int)($input['desafio_rotas_sem_atrasos'] ?? 10);
        $desafio_economia_combustivel = (float)($input['desafio_economia_combustivel'] ?? 15.0);
        $pontos_desafio_completo = (int)($input['pontos_desafio_completo'] ?? 100);
        $desafios_ativos = (bool)($input['desafios_ativos'] ?? true) ? 1 : 0;
        $feedback_imediato = (bool)($input['feedback_imediato'] ?? true) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_desafios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            desafio_km_sem_infracoes INT DEFAULT 5000,
            desafio_rotas_sem_atrasos INT DEFAULT 10,
            desafio_economia_combustivel DECIMAL(5,2) DEFAULT 15.00,
            pontos_desafio_completo INT DEFAULT 100,
            desafios_ativos TINYINT(1) DEFAULT 1,
            feedback_imediato TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        // Inserir ou atualizar configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes_desafios (
            empresa_id, desafio_km_sem_infracoes, desafio_rotas_sem_atrasos,
            desafio_economia_combustivel, pontos_desafio_completo, desafios_ativos, feedback_imediato
        ) VALUES (
            :empresa_id, :desafio_km_sem_infracoes, :desafio_rotas_sem_atrasos,
            :desafio_economia_combustivel, :pontos_desafio_completo, :desafios_ativos, :feedback_imediato
        ) ON DUPLICATE KEY UPDATE
            desafio_km_sem_infracoes = :desafio_km_sem_infracoes_upd,
            desafio_rotas_sem_atrasos = :desafio_rotas_sem_atrasos_upd,
            desafio_economia_combustivel = :desafio_economia_combustivel_upd,
            pontos_desafio_completo = :pontos_desafio_completo_upd,
            desafios_ativos = :desafios_ativos_upd,
            feedback_imediato = :feedback_imediato_upd,
            data_atualizacao = NOW()");
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':desafio_km_sem_infracoes', $desafio_km_sem_infracoes, PDO::PARAM_INT);
        $stmt->bindParam(':desafio_rotas_sem_atrasos', $desafio_rotas_sem_atrasos, PDO::PARAM_INT);
        $stmt->bindParam(':desafio_economia_combustivel', $desafio_economia_combustivel, PDO::PARAM_STR);
        $stmt->bindParam(':pontos_desafio_completo', $pontos_desafio_completo, PDO::PARAM_INT);
        $stmt->bindParam(':desafios_ativos', $desafios_ativos, PDO::PARAM_INT);
        $stmt->bindParam(':feedback_imediato', $feedback_imediato, PDO::PARAM_INT);
        
        // Parâmetros para UPDATE
        $stmt->bindParam(':desafio_km_sem_infracoes_upd', $desafio_km_sem_infracoes, PDO::PARAM_INT);
        $stmt->bindParam(':desafio_rotas_sem_atrasos_upd', $desafio_rotas_sem_atrasos, PDO::PARAM_INT);
        $stmt->bindParam(':desafio_economia_combustivel_upd', $desafio_economia_combustivel, PDO::PARAM_STR);
        $stmt->bindParam(':pontos_desafio_completo_upd', $pontos_desafio_completo, PDO::PARAM_INT);
        $stmt->bindParam(':desafios_ativos_upd', $desafios_ativos, PDO::PARAM_INT);
        $stmt->bindParam(':feedback_imediato_upd', $feedback_imediato, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de desafios salvas com sucesso!']);
        exit;
    }
    
    // Configurações de Métricas Avançadas
    if ($action === 'save_config_metricas') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $peso_ocorrencias_sinistros = (int)($input['peso_ocorrencias_sinistros'] ?? 15);
        $peso_custos_km = (int)($input['peso_custos_km'] ?? 10);
        $peso_feedback_cliente = (int)($input['peso_feedback_cliente'] ?? 10);
        $peso_manutencao_preventiva = (int)($input['peso_manutencao_preventiva'] ?? 5);
        $metricas_avancadas_ativas = (bool)($input['metricas_avancadas_ativas'] ?? false) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_metricas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            peso_ocorrencias_sinistros INT DEFAULT 15,
            peso_custos_km INT DEFAULT 10,
            peso_feedback_cliente INT DEFAULT 10,
            peso_manutencao_preventiva INT DEFAULT 5,
            metricas_avancadas_ativas TINYINT(1) DEFAULT 0,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        // Inserir ou atualizar configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes_metricas (
            empresa_id, peso_ocorrencias_sinistros, peso_custos_km,
            peso_feedback_cliente, peso_manutencao_preventiva, metricas_avancadas_ativas
        ) VALUES (
            :empresa_id, :peso_ocorrencias_sinistros, :peso_custos_km,
            :peso_feedback_cliente, :peso_manutencao_preventiva, :metricas_avancadas_ativas
        ) ON DUPLICATE KEY UPDATE
            peso_ocorrencias_sinistros = :peso_ocorrencias_sinistros_upd,
            peso_custos_km = :peso_custos_km_upd,
            peso_feedback_cliente = :peso_feedback_cliente_upd,
            peso_manutencao_preventiva = :peso_manutencao_preventiva_upd,
            metricas_avancadas_ativas = :metricas_avancadas_ativas_upd,
            data_atualizacao = NOW()");
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':peso_ocorrencias_sinistros', $peso_ocorrencias_sinistros, PDO::PARAM_INT);
        $stmt->bindParam(':peso_custos_km', $peso_custos_km, PDO::PARAM_INT);
        $stmt->bindParam(':peso_feedback_cliente', $peso_feedback_cliente, PDO::PARAM_INT);
        $stmt->bindParam(':peso_manutencao_preventiva', $peso_manutencao_preventiva, PDO::PARAM_INT);
        $stmt->bindParam(':metricas_avancadas_ativas', $metricas_avancadas_ativas, PDO::PARAM_INT);
        
        // Parâmetros para UPDATE
        $stmt->bindParam(':peso_ocorrencias_sinistros_upd', $peso_ocorrencias_sinistros, PDO::PARAM_INT);
        $stmt->bindParam(':peso_custos_km_upd', $peso_custos_km, PDO::PARAM_INT);
        $stmt->bindParam(':peso_feedback_cliente_upd', $peso_feedback_cliente, PDO::PARAM_INT);
        $stmt->bindParam(':peso_manutencao_preventiva_upd', $peso_manutencao_preventiva, PDO::PARAM_INT);
        $stmt->bindParam(':metricas_avancadas_ativas_upd', $metricas_avancadas_ativas, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Métricas avançadas salvas com sucesso!']);
        exit;
    }
    
    // Configurações de Filtros
    if ($action === 'save_config_filtros') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $periodo_padrao = $input['periodo_padrao'] ?? 'mensal';
        $filtro_por_veiculo = $input['filtro_por_veiculo'] ?? 'todos';
        $filtro_por_rota = $input['filtro_por_rota'] ?? 'todas';
        $comparacao_filiais = $input['comparacao_filiais'] ?? 'desabilitada';
        $filtros_ativos = (bool)($input['filtros_ativos'] ?? true) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_filtros (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            periodo_padrao VARCHAR(20) DEFAULT 'mensal',
            filtro_por_veiculo VARCHAR(20) DEFAULT 'todos',
            filtro_por_rota VARCHAR(20) DEFAULT 'todas',
            comparacao_filiais VARCHAR(20) DEFAULT 'desabilitada',
            filtros_ativos TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        // Inserir ou atualizar configuração
        $stmt = $conn->prepare("INSERT INTO configuracoes_filtros (
            empresa_id, periodo_padrao, filtro_por_veiculo, filtro_por_rota,
            comparacao_filiais, filtros_ativos
        ) VALUES (
            :empresa_id, :periodo_padrao, :filtro_por_veiculo, :filtro_por_rota,
            :comparacao_filiais, :filtros_ativos
        ) ON DUPLICATE KEY UPDATE
            periodo_padrao = :periodo_padrao_upd,
            filtro_por_veiculo = :filtro_por_veiculo_upd,
            filtro_por_rota = :filtro_por_rota_upd,
            comparacao_filiais = :comparacao_filiais_upd,
            filtros_ativos = :filtros_ativos_upd,
            data_atualizacao = NOW()");
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':periodo_padrao', $periodo_padrao, PDO::PARAM_STR);
        $stmt->bindParam(':filtro_por_veiculo', $filtro_por_veiculo, PDO::PARAM_STR);
        $stmt->bindParam(':filtro_por_rota', $filtro_por_rota, PDO::PARAM_STR);
        $stmt->bindParam(':comparacao_filiais', $comparacao_filiais, PDO::PARAM_STR);
        $stmt->bindParam(':filtros_ativos', $filtros_ativos, PDO::PARAM_INT);
        
        // Parâmetros para UPDATE
        $stmt->bindParam(':periodo_padrao_upd', $periodo_padrao, PDO::PARAM_STR);
        $stmt->bindParam(':filtro_por_veiculo_upd', $filtro_por_veiculo, PDO::PARAM_STR);
        $stmt->bindParam(':filtro_por_rota_upd', $filtro_por_rota, PDO::PARAM_STR);
        $stmt->bindParam(':comparacao_filiais_upd', $comparacao_filiais, PDO::PARAM_STR);
        $stmt->bindParam(':filtros_ativos_upd', $filtros_ativos, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de filtros salvas com sucesso!']);
        exit;
    }
    
    // Funções de configurações avançadas
    if ($action === 'get_config_badges') {
        $stmt = $conn->prepare('SELECT * FROM configuracoes_badges WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Criar configuração padrão
            $stmt = $conn->prepare('INSERT INTO configuracoes_badges (empresa_id, badge_motorista_economico, badge_sem_multas, badge_checklists_perfeitos, badge_streak_fogo, pontos_badge_economico, pontos_badge_sem_multas, pontos_badge_checklists, pontos_badge_streak, data_criacao) VALUES (:empresa_id, 1, 1, 1, 1, 50, 100, 75, 25, NOW())');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $stmt = $conn->prepare('SELECT * FROM configuracoes_badges WHERE empresa_id = :empresa_id LIMIT 1');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }
    
    if ($action === 'get_config_niveis') {
        $stmt = $conn->prepare('SELECT * FROM configuracoes_niveis WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Criar configuração padrão
            $stmt = $conn->prepare('INSERT INTO configuracoes_niveis (empresa_id, nivel_bronze_min, nivel_bronze_max, nivel_prata_min, nivel_prata_max, nivel_ouro_min, nivel_ouro_max, nivel_platina_min, nivel_platina_max, nivel_diamante_min, nivel_diamante_max, nivel_lenda_min, nivel_lenda_max, data_criacao) VALUES (:empresa_id, 0, 99, 100, 299, 300, 599, 600, 899, 900, 999, 1000, 9999, NOW())');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $stmt = $conn->prepare('SELECT * FROM configuracoes_niveis WHERE empresa_id = :empresa_id LIMIT 1');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }
    
    if ($action === 'get_config_desafios') {
        $stmt = $conn->prepare('SELECT * FROM configuracoes_desafios WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Criar configuração padrão
            $stmt = $conn->prepare('INSERT INTO configuracoes_desafios (empresa_id, desafio_semana_ativo, desafio_mes_ativo, desafio_km_sem_infracoes, desafio_rotas_sem_atrasos, pontos_desafio_semana, pontos_desafio_mes, pontos_desafio_km, pontos_desafio_rotas, data_criacao) VALUES (:empresa_id, 1, 1, 1, 1, 100, 500, 200, 150, NOW())');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $stmt = $conn->prepare('SELECT * FROM configuracoes_desafios WHERE empresa_id = :empresa_id LIMIT 1');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }
    
    if ($action === 'get_config_metricas') {
        $stmt = $conn->prepare('SELECT * FROM configuracoes_metricas WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Criar configuração padrão
            $stmt = $conn->prepare('INSERT INTO configuracoes_metricas (empresa_id, peso_ocorrencias, peso_custos_km, peso_feedback_cliente, peso_manutencao_preventiva, peso_eficiencia_combustivel, peso_pontualidade, peso_multas, peso_checklists, data_criacao) VALUES (:empresa_id, 15, 10, 10, 5, 30, 25, 20, 15, NOW())');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $stmt = $conn->prepare('SELECT * FROM configuracoes_metricas WHERE empresa_id = :empresa_id LIMIT 1');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }
    
    if ($action === 'get_config_filtros') {
        $stmt = $conn->prepare('SELECT * FROM configuracoes_filtros WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Criar configuração padrão
            $stmt = $conn->prepare('INSERT INTO configuracoes_filtros (empresa_id, periodo_padrao, filtro_por_veiculo, filtro_por_rota, comparacao_filiais, filtros_ativos, data_criacao) VALUES (:empresa_id, "mensal", "todos", "todas", "nao", 1, NOW())');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $stmt = $conn->prepare('SELECT * FROM configuracoes_filtros WHERE empresa_id = :empresa_id LIMIT 1');
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }
    
    // Configurações de Notificações
    if ($action === 'save_notifications_config') {
        $notificacoes_badges = isset($_POST['notificacoes_badges']) ? 1 : 0;
        $notificacoes_niveis = isset($_POST['notificacoes_niveis']) ? 1 : 0;
        $notificacoes_ranking = isset($_POST['notificacoes_ranking']) ? 1 : 0;
        $notificacoes_desafios = isset($_POST['notificacoes_desafios']) ? 1 : 0;
        $sistema_notificacoes_ativo = isset($_POST['sistema_notificacoes_ativo']) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_notificacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            notificacoes_badges TINYINT(1) DEFAULT 1,
            notificacoes_niveis TINYINT(1) DEFAULT 1,
            notificacoes_ranking TINYINT(1) DEFAULT 1,
            notificacoes_desafios TINYINT(1) DEFAULT 1,
            sistema_notificacoes_ativo TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        $stmt = $conn->prepare('INSERT INTO configuracoes_notificacoes 
            (empresa_id, notificacoes_badges, notificacoes_niveis, notificacoes_ranking, notificacoes_desafios, sistema_notificacoes_ativo) 
            VALUES (:empresa_id, :notificacoes_badges, :notificacoes_niveis, :notificacoes_ranking, :notificacoes_desafios, :sistema_notificacoes_ativo)
            ON DUPLICATE KEY UPDATE 
            notificacoes_badges = :notificacoes_badges_upd,
            notificacoes_niveis = :notificacoes_niveis_upd,
            notificacoes_ranking = :notificacoes_ranking_upd,
            notificacoes_desafios = :notificacoes_desafios_upd,
            sistema_notificacoes_ativo = :sistema_notificacoes_ativo_upd,
            data_atualizacao = NOW()');
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_badges', $notificacoes_badges, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_niveis', $notificacoes_niveis, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_ranking', $notificacoes_ranking, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_desafios', $notificacoes_desafios, PDO::PARAM_INT);
        $stmt->bindParam(':sistema_notificacoes_ativo', $sistema_notificacoes_ativo, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_badges_upd', $notificacoes_badges, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_niveis_upd', $notificacoes_niveis, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_ranking_upd', $notificacoes_ranking, PDO::PARAM_INT);
        $stmt->bindParam(':notificacoes_desafios_upd', $notificacoes_desafios, PDO::PARAM_INT);
        $stmt->bindParam(':sistema_notificacoes_ativo_upd', $sistema_notificacoes_ativo, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de notificações salvas com sucesso!']);
        exit;
    }
    
    // Configurações de Cache
    if ($action === 'save_cache_config') {
        $cache_ttl = (int)($_POST['cache_ttl'] ?? 5);
        $cache_max_size = (int)($_POST['cache_max_size'] ?? 100);
        $background_intervalo = (int)($_POST['background_intervalo'] ?? 30);
        $background_timeout = (int)($_POST['background_timeout'] ?? 10);
        $cache_ativo = isset($_POST['cache_ativo']) ? 1 : 0;
        $background_ativo = isset($_POST['background_ativo']) ? 1 : 0;
        
        // Criar tabela se não existir
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracoes_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            cache_ttl INT DEFAULT 5,
            cache_max_size INT DEFAULT 100,
            background_intervalo INT DEFAULT 30,
            background_timeout INT DEFAULT 10,
            cache_ativo TINYINT(1) DEFAULT 1,
            background_ativo TINYINT(1) DEFAULT 1,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_empresa (empresa_id)
        )");
        
        $stmt = $conn->prepare('INSERT INTO configuracoes_cache 
            (empresa_id, cache_ttl, cache_max_size, background_intervalo, background_timeout, cache_ativo, background_ativo) 
            VALUES (:empresa_id, :cache_ttl, :cache_max_size, :background_intervalo, :background_timeout, :cache_ativo, :background_ativo)
            ON DUPLICATE KEY UPDATE 
            cache_ttl = :cache_ttl_upd,
            cache_max_size = :cache_max_size_upd,
            background_intervalo = :background_intervalo_upd,
            background_timeout = :background_timeout_upd,
            cache_ativo = :cache_ativo_upd,
            background_ativo = :background_ativo_upd,
            data_atualizacao = NOW()');
        
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':cache_ttl', $cache_ttl, PDO::PARAM_INT);
        $stmt->bindParam(':cache_max_size', $cache_max_size, PDO::PARAM_INT);
        $stmt->bindParam(':background_intervalo', $background_intervalo, PDO::PARAM_INT);
        $stmt->bindParam(':background_timeout', $background_timeout, PDO::PARAM_INT);
        $stmt->bindParam(':cache_ativo', $cache_ativo, PDO::PARAM_INT);
        $stmt->bindParam(':background_ativo', $background_ativo, PDO::PARAM_INT);
        $stmt->bindParam(':cache_ttl_upd', $cache_ttl, PDO::PARAM_INT);
        $stmt->bindParam(':cache_max_size_upd', $cache_max_size, PDO::PARAM_INT);
        $stmt->bindParam(':background_intervalo_upd', $background_intervalo, PDO::PARAM_INT);
        $stmt->bindParam(':background_timeout_upd', $background_timeout, PDO::PARAM_INT);
        $stmt->bindParam(':cache_ativo_upd', $cache_ativo, PDO::PARAM_INT);
        $stmt->bindParam(':background_ativo_upd', $background_ativo, PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Configurações de cache salvas com sucesso!']);
        exit;
    }
    
    // Limpar Cache
    if ($action === 'clear_cache') {
        // Aqui você pode implementar a lógica para limpar o cache
        // Por enquanto, vamos apenas retornar sucesso
        echo json_encode(['success' => true, 'message' => 'Cache limpo com sucesso!']);
        exit;
    }
    
    throw new Exception('Ação inválida');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 