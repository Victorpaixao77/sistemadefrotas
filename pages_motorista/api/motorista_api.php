<?php
require_once '../config.php';
require_once '../../includes/db_connect.php';

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die('Acesso direto não permitido');
}

// Verificar se o motorista está logado
if (!isset($_SESSION['motorista_id'])) {
    error_response('Não autorizado', 401);
}

// Obter dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['motorista_empresa_id'];

// Obter a ação da requisição
$action = $_GET['action'] ?? '';

// Processar a ação
switch ($action) {
    case 'get_veiculos':
        // Obter veículos da empresa
        $conn = getConnection();
        $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response('Veículos obtidos com sucesso', $veiculos);
        break;
        
    case 'get_rotas_pendentes':
        // Obter rotas pendentes
        $conn = getConnection();
        $sql = "SELECT r.*, v.placa, v.modelo 
                FROM rotas r 
                JOIN veiculos v ON r.veiculo_id = v.id 
                WHERE v.empresa_id = :empresa_id 
                AND r.status = 'pendente'
                ORDER BY r.data_rota DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response('Rotas pendentes obtidas com sucesso', $rotas);
        break;
        
    case 'get_abastecimentos_pendentes':
        // Obter abastecimentos pendentes
        $conn = getConnection();
        $sql = "SELECT a.*, v.placa, v.modelo 
                FROM abastecimentos a 
                JOIN veiculos v ON a.veiculo_id = v.id 
                WHERE v.empresa_id = :empresa_id 
                AND a.status = 'pendente'
                ORDER BY a.data_abastecimento DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response('Abastecimentos pendentes obtidos com sucesso', $abastecimentos);
        break;
        
    case 'get_checklists_pendentes':
        // Obter checklists pendentes
        $conn = getConnection();
        $sql = "SELECT c.*, v.placa, v.modelo 
                FROM checklists c 
                JOIN veiculos v ON c.veiculo_id = v.id 
                WHERE v.empresa_id = :empresa_id 
                AND c.status = 'pendente'
                ORDER BY c.data_checklist DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        $checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response('Checklists pendentes obtidos com sucesso', $checklists);
        break;
        
    case 'registrar_rota':
        if (!isset($_POST['veiculo_id']) || !isset($_POST['data_saida']) || 
            !isset($_POST['estado_origem']) || !isset($_POST['cidade_origem']) ||
            !isset($_POST['estado_destino']) || !isset($_POST['cidade_destino']) ||
            !isset($_POST['km_saida']) || !isset($_POST['km_chegada'])) {
            error_response('Dados incompletos');
        }

        $veiculo_id = (int)$_POST['veiculo_id'];
        $data_saida = $_POST['data_saida'];
        $estado_origem = strtoupper(substr($_POST['estado_origem'], 0, 2));
        $cidade_origem = $_POST['cidade_origem'];
        $estado_destino = strtoupper(substr($_POST['estado_destino'], 0, 2));
        $cidade_destino = $_POST['cidade_destino'];
        $km_saida = (float)$_POST['km_saida'];
        $km_chegada = (float)$_POST['km_chegada'];
        $distancia_km = $km_chegada - $km_saida;
        $peso_carga = isset($_POST['peso_carga']) ? (float)$_POST['peso_carga'] : null;
        $descricao_carga = isset($_POST['descricao_carga']) ? $_POST['descricao_carga'] : null;
        $observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : null;

        // Valida o veículo
        if (!validar_veiculo_empresa($veiculo_id, $empresa_id)) {
            error_response('Veículo inválido');
        }

        // Valida o KM de saída
        if (!validar_km_veiculo($veiculo_id, $km_saida)) {
            error_response('Quilometragem de saída inválida');
        }

        // Valida a distância
        if ($distancia_km < 0) {
            error_response('A quilometragem de chegada deve ser maior que a de saída');
        }

        $conn = getConnection();
        $stmt = $conn->prepare('
            INSERT INTO rotas (
                empresa_id, veiculo_id, motorista_id,
                estado_origem, cidade_origem,
                estado_destino, cidade_destino,
                data_saida, km_saida, km_chegada,
                distancia_km, peso_carga, descricao_carga,
                observacoes, status, fonte
            ) VALUES (
                :empresa_id, :veiculo_id, :motorista_id,
                :estado_origem, :cidade_origem,
                :estado_destino, :cidade_destino,
                :data_saida, :km_saida, :km_chegada,
                :distancia_km, :peso_carga, :descricao_carga,
                :observacoes, "pendente", "motorista"
            )
        ');

        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
        $stmt->bindParam(':estado_origem', $estado_origem);
        $stmt->bindParam(':cidade_origem', $cidade_origem);
        $stmt->bindParam(':estado_destino', $estado_destino);
        $stmt->bindParam(':cidade_destino', $cidade_destino);
        $stmt->bindParam(':data_saida', $data_saida);
        $stmt->bindParam(':km_saida', $km_saida);
        $stmt->bindParam(':km_chegada', $km_chegada);
        $stmt->bindParam(':distancia_km', $distancia_km);
        $stmt->bindParam(':peso_carga', $peso_carga);
        $stmt->bindParam(':descricao_carga', $descricao_carga);
        $stmt->bindParam(':observacoes', $observacoes);

        if ($stmt->execute()) {
            log_motorista_action('registrar_rota', "Rota registrada: $cidade_origem/$estado_origem -> $cidade_destino/$estado_destino");
            success_response('Rota registrada com sucesso');
        } else {
            error_response('Erro ao registrar rota');
        }
        break;
        
    case 'salvar_abastecimento':
        // Validar CSRF token
        validate_csrf_token($_POST['csrf_token'] ?? '');
        
        // Validar dados
        $veiculo_id = $_POST['veiculo_id'] ?? '';
        $data_abastecimento = $_POST['data_abastecimento'] ?? date('Y-m-d');
        $tipo_combustivel = $_POST['tipo_combustivel'] ?? '';
        $quantidade = $_POST['quantidade'] ?? '';
        $valor_litro = $_POST['valor_litro'] ?? '';
        $valor_total = $_POST['valor_total'] ?? '';
        $km_atual = $_POST['km_atual'] ?? '';
        $posto = $_POST['posto'] ?? '';
        $observacoes = $_POST['observacoes'] ?? '';
        
        if (empty($veiculo_id) || empty($tipo_combustivel) || empty($quantidade) || 
            empty($valor_litro) || empty($valor_total) || empty($km_atual)) {
            error_response('Por favor, preencha todos os campos obrigatórios.');
        }
        
        try {
            $conn = getConnection();
            $sql = "INSERT INTO abastecimentos (
                        veiculo_id, data_abastecimento, tipo_combustivel,
                        quantidade, valor_litro, valor_total, km_atual,
                        posto, observacoes, status, fonte
                    ) VALUES (
                        :veiculo_id, :data_abastecimento, :tipo_combustivel,
                        :quantidade, :valor_litro, :valor_total, :km_atual,
                        :posto, :observacoes, 'pendente', 'motorista'
                    )";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':veiculo_id', $veiculo_id);
            $stmt->bindValue(':data_abastecimento', $data_abastecimento);
            $stmt->bindValue(':tipo_combustivel', $tipo_combustivel);
            $stmt->bindValue(':quantidade', $quantidade);
            $stmt->bindValue(':valor_litro', $valor_litro);
            $stmt->bindValue(':valor_total', $valor_total);
            $stmt->bindValue(':km_atual', $km_atual);
            $stmt->bindValue(':posto', $posto);
            $stmt->bindValue(':observacoes', $observacoes);
            
            if ($stmt->execute()) {
                log_motorista_action('salvar_abastecimento', "Abastecimento registrado para o veículo ID: $veiculo_id");
                success_response('Abastecimento registrado com sucesso! Aguardando aprovação.');
            } else {
                error_response('Erro ao registrar abastecimento.');
            }
        } catch (PDOException $e) {
            error_response('Erro ao registrar abastecimento: ' . $e->getMessage());
        }
        break;
        
    case 'salvar_checklist':
        // Validar CSRF token
        validate_csrf_token($_POST['csrf_token'] ?? '');
        
        // Validar dados
        $veiculo_id = $_POST['veiculo_id'] ?? '';
        $data_checklist = $_POST['data_checklist'] ?? date('Y-m-d');
        $tipo_checklist = $_POST['tipo_checklist'] ?? '';
        $km_atual = $_POST['km_atual'] ?? '';
        $observacoes = $_POST['observacoes'] ?? '';
        
        // Itens do checklist
        $itens = [
            'pneus' => $_POST['pneus'] ?? 'ok',
            'freios' => $_POST['freios'] ?? 'ok',
            'suspensao' => $_POST['suspensao'] ?? 'ok',
            'motor' => $_POST['motor'] ?? 'ok',
            'transmissao' => $_POST['transmissao'] ?? 'ok',
            'eletrica' => $_POST['eletrica'] ?? 'ok',
            'documentacao' => $_POST['documentacao'] ?? 'ok',
            'limpeza' => $_POST['limpeza'] ?? 'ok'
        ];
        
        if (empty($veiculo_id) || empty($tipo_checklist) || empty($km_atual)) {
            error_response('Por favor, preencha todos os campos obrigatórios.');
        }
        
        try {
            $conn = getConnection();
            $conn->beginTransaction();
            
            // Inserir checklist
            $sql = "INSERT INTO checklists (
                        veiculo_id, data_checklist, tipo_checklist,
                        km_atual, observacoes, status, fonte
                    ) VALUES (
                        :veiculo_id, :data_checklist, :tipo_checklist,
                        :km_atual, :observacoes, 'pendente', 'motorista'
                    )";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':veiculo_id', $veiculo_id);
            $stmt->bindValue(':data_checklist', $data_checklist);
            $stmt->bindValue(':tipo_checklist', $tipo_checklist);
            $stmt->bindValue(':km_atual', $km_atual);
            $stmt->bindValue(':observacoes', $observacoes);
            
            if ($stmt->execute()) {
                $checklist_id = $conn->lastInsertId();
                
                // Inserir itens do checklist
                $sql = "INSERT INTO checklist_itens (
                            checklist_id, item, status
                        ) VALUES (
                            :checklist_id, :item, :status
                        )";
                
                $stmt = $conn->prepare($sql);
                
                foreach ($itens as $item => $status) {
                    $stmt->bindValue(':checklist_id', $checklist_id);
                    $stmt->bindValue(':item', $item);
                    $stmt->bindValue(':status', $status);
                    $stmt->execute();
                }
                
                $conn->commit();
                log_motorista_action('salvar_checklist', "Checklist registrado para o veículo ID: $veiculo_id");
                success_response('Checklist registrado com sucesso! Aguardando aprovação.');
            } else {
                throw new Exception('Erro ao registrar checklist.');
            }
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            error_response('Erro ao registrar checklist: ' . $e->getMessage());
        }
        break;
        
    default:
        error_response('Ação inválida');
} 