<?php
require_once '../includes/conexao.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Criar tabelas se não existirem
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS adm_boletos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_adm_id INT NOT NULL,
        empresa_cliente_id INT NOT NULL,
        numero_boleto VARCHAR(50) NOT NULL UNIQUE,
        codigo_barras VARCHAR(100),
        linha_digitavel VARCHAR(100),
        descricao TEXT NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        data_vencimento DATE NOT NULL,
        data_emissao DATE NOT NULL,
        status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
        data_pagamento DATE NULL,
        forma_pagamento VARCHAR(50) NULL,
        observacoes TEXT NULL,
        mes_referencia VARCHAR(7) NULL,
        tipo_boleto ENUM('mensalidade', 'adicional', 'multa', 'outros') DEFAULT 'mensalidade',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_empresa_adm (empresa_adm_id),
        INDEX idx_empresa_cliente (empresa_cliente_id),
        INDEX idx_status (status),
        INDEX idx_data_vencimento (data_vencimento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS adm_pagamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        boleto_id INT NOT NULL,
        empresa_adm_id INT NOT NULL,
        empresa_cliente_id INT NOT NULL,
        valor_pago DECIMAL(10,2) NOT NULL,
        data_pagamento DATE NOT NULL,
        forma_pagamento VARCHAR(50) NOT NULL,
        comprovante_path VARCHAR(255) NULL,
        observacoes TEXT NULL,
        registrado_por INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_boleto (boleto_id),
        INDEX idx_empresa_adm (empresa_adm_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {
    // Tabelas já existem ou erro na criação
}

$mensagem = '';
$tipo_mensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'gerar_boleto':
                try {
                    $empresa_cliente_id = $_POST['empresa_cliente_id'];
                    
                    // Verificar se é geração em lote
                    if ($empresa_cliente_id === 'lote') {
                        // Gerar boletos em lote
                        $data_vencimento = $_POST['data_vencimento'];
                        $descricao = $_POST['descricao'];
                        $tipo_boleto = $_POST['tipo_boleto'] ?? 'mensalidade';
                        $mes_referencia = $_POST['mes_referencia'] ?? null;
                        $valor_fixo = isset($_POST['valor_fixo']) && $_POST['valor_fixo'] != '' ? (float)$_POST['valor_fixo'] : null;
                        $usar_valor_por_veiculo = isset($_POST['usar_valor_por_veiculo']) && $_POST['usar_valor_por_veiculo'] == '1';
                        
                        // Buscar todas as empresas ativas com seus planos
                        $stmt = $pdo->query("
                            SELECT ec.id as empresa_cliente_id, ec.empresa_adm_id, ec.razao_social, 
                                   ea.valor_por_veiculo, ea.plano_id,
                                   p.tipo as plano_tipo, p.valor_por_veiculo as plano_valor_por_veiculo,
                                   p.valor_maximo as plano_valor_maximo
                            FROM empresa_clientes ec
                            INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
                            LEFT JOIN adm_planos p ON ea.plano_id = p.id
                            WHERE ec.status = 'ativo'
                            ORDER BY ec.razao_social
                        ");
                        $empresas = $stmt->fetchAll();
                        
                        if (empty($empresas)) {
                            throw new Exception("Nenhuma empresa ativa encontrada.");
                        }
                        
                        $data_emissao = date('Y-m-d');
                        $boletos_gerados = 0;
                        $boletos_erros = [];
                        
                        $pdo->beginTransaction();
                        
                        try {
                            foreach ($empresas as $empresa) {
                                // Calcular valor do boleto
                                if ($valor_fixo !== null) {
                                    $valor = $valor_fixo;
                                } elseif ($usar_valor_por_veiculo) {
                                    // Usar valor do plano ou valor_por_veiculo da empresa
                                    if (!empty($empresa['plano_id'])) {
                                        // Tem plano configurado
                                        if ($empresa['plano_tipo'] === 'pacote') {
                                            // Plano pacote: valor fixo
                                            $valor = (float)$empresa['plano_valor_maximo'];
                                        } else {
                                            // Plano avulso: calcular por veículo
                                            $stmt_veic = $pdo->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ? AND status_id IN (1, 2)");
                                            $stmt_veic->execute([$empresa['empresa_cliente_id']]);
                                            $veiculo_data = $stmt_veic->fetch();
                                            $total_veiculos = $veiculo_data['total'] ?? 0;
                                            
                                            $valor = $empresa['plano_valor_por_veiculo'] * $total_veiculos;
                                        }
                                    } else {
                                        // Sem plano: usar valor_por_veiculo da empresa
                                        $stmt_veic = $pdo->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ? AND status_id IN (1, 2)");
                                        $stmt_veic->execute([$empresa['empresa_cliente_id']]);
                                        $veiculo_data = $stmt_veic->fetch();
                                        $total_veiculos = $veiculo_data['total'] ?? 0;
                                        
                                        $valor = $empresa['valor_por_veiculo'] * $total_veiculos;
                                    }
                                    
                                    if ($valor <= 0) {
                                        $boletos_erros[] = $empresa['razao_social'] . " (sem veículos ativos ou plano sem valor)";
                                        continue;
                                    }
                                } else {
                                    throw new Exception("Defina um valor fixo ou marque 'Usar valor do plano/veículo'.");
                                }
                                
                                // Gerar número do boleto único
                                do {
                                    $sequencial = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                    $numero_boleto = date('Ymd') . $sequencial;
                                    $stmt_check = $pdo->prepare("SELECT id FROM adm_boletos WHERE numero_boleto = ?");
                                    $stmt_check->execute([$numero_boleto]);
                                } while ($stmt_check->fetch());
                                
                                // Gerar código de barras
                                $codigo_barras = '';
                                for ($i = 0; $i < 44; $i++) {
                                    $codigo_barras .= rand(0, 9);
                                }
                                
                                // Gerar linha digitável
                                $ultimos_14_digitos = '';
                                for ($i = 0; $i < 14; $i++) {
                                    $ultimos_14_digitos .= rand(0, 9);
                                }
                                
                                $linha_digitavel = sprintf(
                                    "%05d.%05d %05d.%06d %05d.%06d %d %s",
                                    rand(10000, 99999),
                                    rand(10000, 99999),
                                    rand(10000, 99999),
                                    rand(100000, 999999),
                                    rand(10000, 99999),
                                    rand(100000, 999999),
                                    rand(0, 9),
                                    $ultimos_14_digitos
                                );
                                
                                // Inserir boleto
                                $stmt = $pdo->prepare("INSERT INTO adm_boletos (
                                    empresa_adm_id, empresa_cliente_id, numero_boleto, codigo_barras,
                                    linha_digitavel, descricao, valor, data_vencimento, data_emissao,
                                    tipo_boleto, mes_referencia, status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
                                
                                $stmt->execute([
                                    $empresa['empresa_adm_id'],
                                    $empresa['empresa_cliente_id'],
                                    $numero_boleto,
                                    $codigo_barras,
                                    $linha_digitavel,
                                    $descricao,
                                    $valor,
                                    $data_vencimento,
                                    $data_emissao,
                                    $tipo_boleto,
                                    $mes_referencia
                                ]);
                                
                                $boletos_gerados++;
                            }
                            
                            $pdo->commit();
                            
                            $mensagem = "Boletos gerados em lote com sucesso! Total: $boletos_gerados boletos";
                            if (!empty($boletos_erros)) {
                                $mensagem .= ". Erros: " . implode(", ", $boletos_erros);
                            }
                            $tipo_mensagem = "success";
                            
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        // Geração individual
                        $empresa_cliente_id = (int)$empresa_cliente_id;
                        $usar_valor_plano = isset($_POST['usar_valor_plano']) && $_POST['usar_valor_plano'] == '1';
                        $valor_manual = isset($_POST['valor']) && $_POST['valor'] != '' ? (float)$_POST['valor'] : null;
                        $data_vencimento = $_POST['data_vencimento'];
                        $descricao = $_POST['descricao'];
                        $tipo_boleto = $_POST['tipo_boleto'] ?? 'mensalidade';
                        $mes_referencia = $_POST['mes_referencia'] ?? null;
                        
                        // Buscar dados da empresa e plano
                        $stmt = $pdo->prepare("
                            SELECT ec.empresa_adm_id, ea.valor_por_veiculo, ea.plano_id,
                                   p.tipo as plano_tipo, p.valor_por_veiculo as plano_valor_por_veiculo,
                                   p.valor_maximo as plano_valor_maximo, p.limite_veiculos as plano_limite_veiculos
                            FROM empresa_clientes ec
                            INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
                            LEFT JOIN adm_planos p ON ea.plano_id = p.id
                            WHERE ec.id = ?
                        ");
                        $stmt->execute([$empresa_cliente_id]);
                        $empresa_data = $stmt->fetch();
                        
                        if (!$empresa_data) {
                            throw new Exception("Empresa cliente não encontrada.");
                        }
                        
                        $empresa_adm_id = $empresa_data['empresa_adm_id'];
                        
                        // Calcular valor do boleto
                        if ($usar_valor_plano) {
                            // Usar valor do plano configurado ou valor_por_veiculo da empresa
                            if (!empty($empresa_data['plano_id'])) {
                                // Tem plano configurado
                                if ($empresa_data['plano_tipo'] === 'pacote') {
                                    // Plano pacote: valor fixo
                                    $valor = (float)$empresa_data['plano_valor_maximo'];
                                } else {
                                    // Plano avulso: calcular por veículo
                                    $stmt_veic = $pdo->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ? AND status_id IN (1, 2)");
                                    $stmt_veic->execute([$empresa_cliente_id]);
                                    $veiculo_data = $stmt_veic->fetch();
                                    $total_veiculos = $veiculo_data['total'] ?? 0;
                                    
                                    $valor = $empresa_data['plano_valor_por_veiculo'] * $total_veiculos;
                                }
                            } else {
                                // Sem plano: usar valor_por_veiculo da empresa
                                $stmt_veic = $pdo->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ? AND status_id IN (1, 2)");
                                $stmt_veic->execute([$empresa_cliente_id]);
                                $veiculo_data = $stmt_veic->fetch();
                                $total_veiculos = $veiculo_data['total'] ?? 0;
                                
                                $valor = $empresa_data['valor_por_veiculo'] * $total_veiculos;
                            }
                            
                            if ($valor <= 0) {
                                throw new Exception("Não foi possível calcular o valor. Verifique se a empresa tem veículos ativos ou se o plano/valor por veículo está configurado corretamente.");
                            }
                        } elseif ($valor_manual !== null) {
                            // Usar valor manual
                            $valor = $valor_manual;
                            
                            if ($valor <= 0) {
                                throw new Exception("O valor do boleto não pode ser zero ou negativo.");
                            }
                        } else {
                            throw new Exception("Você deve marcar 'Usar valor do plano configurado' ou informar um valor manual.");
                        }
                        
                        // Gerar número do boleto (formato: YYYYMMDD + sequencial)
                        $data_emissao = date('Y-m-d');
                        $sequencial = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $numero_boleto = date('Ymd') . $sequencial;
                        
                        // Verificar se já existe
                        $stmt = $pdo->prepare("SELECT id FROM adm_boletos WHERE numero_boleto = ?");
                        $stmt->execute([$numero_boleto]);
                        if ($stmt->fetch()) {
                            $numero_boleto = date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        }
                        
                        // Gerar código de barras fictício (44 dígitos)
                        $codigo_barras = '';
                        for ($i = 0; $i < 44; $i++) {
                            $codigo_barras .= rand(0, 9);
                        }
                        
                        // Gerar linha digitável (formato: 00000.00000 00000.000000 00000.000000 0 00000000000000)
                        // Gerar os 14 últimos dígitos manualmente para evitar problemas com números grandes
                        $ultimos_14_digitos = '';
                        for ($i = 0; $i < 14; $i++) {
                            $ultimos_14_digitos .= rand(0, 9);
                        }
                        
                        $linha_digitavel = sprintf(
                            "%05d.%05d %05d.%06d %05d.%06d %d %s",
                            rand(10000, 99999),
                            rand(10000, 99999),
                            rand(10000, 99999),
                            rand(100000, 999999),
                            rand(10000, 99999),
                            rand(100000, 999999),
                            rand(0, 9),
                            $ultimos_14_digitos
                        );
                        
                        // Inserir boleto
                        $stmt = $pdo->prepare("INSERT INTO adm_boletos (
                            empresa_adm_id, empresa_cliente_id, numero_boleto, codigo_barras,
                            linha_digitavel, descricao, valor, data_vencimento, data_emissao,
                            tipo_boleto, mes_referencia, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
                        
                        $stmt->execute([
                            $empresa_adm_id,
                            $empresa_cliente_id,
                            $numero_boleto,
                            $codigo_barras,
                            $linha_digitavel,
                            $descricao,
                            $valor,
                            $data_vencimento,
                            $data_emissao,
                            $tipo_boleto,
                            $mes_referencia
                        ]);
                        
                        $mensagem = "Boleto gerado com sucesso! Número: $numero_boleto";
                        $tipo_mensagem = "success";
                    }
                } catch (Exception $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $mensagem = "Erro ao gerar boleto: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
                
            case 'registrar_pagamento':
                try {
                    $boleto_id = (int)$_POST['boleto_id'];
                    $data_pagamento = $_POST['data_pagamento'];
                    $forma_pagamento = $_POST['forma_pagamento'];
                    $observacoes = $_POST['observacoes'] ?? null;
                    
                    // Buscar dados do boleto
                    $stmt = $pdo->prepare("SELECT * FROM adm_boletos WHERE id = ?");
                    $stmt->execute([$boleto_id]);
                    $boleto = $stmt->fetch();
                    
                    if (!$boleto) {
                        throw new Exception("Boleto não encontrado.");
                    }
                    
                    if ($boleto['status'] == 'pago') {
                        throw new Exception("Este boleto já foi pago.");
                    }
                    
                    // Iniciar transação
                    $pdo->beginTransaction();
                    
                    try {
                        // Registrar pagamento
                        $stmt = $pdo->prepare("INSERT INTO adm_pagamentos (
                            boleto_id, empresa_adm_id, empresa_cliente_id, valor_pago,
                            data_pagamento, forma_pagamento, observacoes, registrado_por
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt->execute([
                            $boleto_id,
                            $boleto['empresa_adm_id'],
                            $boleto['empresa_cliente_id'],
                            $boleto['valor'],
                            $data_pagamento,
                            $forma_pagamento,
                            $observacoes,
                            $_SESSION['admin_id']
                        ]);
                        
                        // Atualizar status do boleto
                        $stmt = $pdo->prepare("UPDATE adm_boletos SET 
                            status = 'pago',
                            data_pagamento = ?,
                            forma_pagamento = ?
                            WHERE id = ?");
                        
                        $stmt->execute([$data_pagamento, $forma_pagamento, $boleto_id]);
                        
                        $pdo->commit();
                        
                        $mensagem = "Pagamento registrado com sucesso!";
                        $tipo_mensagem = "success";
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                } catch (Exception $e) {
                    $mensagem = "Erro ao registrar pagamento: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
                
            case 'cancelar_boleto':
                try {
                    $boleto_id = (int)$_POST['boleto_id'];
                    
                    $stmt = $pdo->prepare("UPDATE adm_boletos SET status = 'cancelado' WHERE id = ? AND status = 'pendente'");
                    $stmt->execute([$boleto_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $mensagem = "Boleto cancelado com sucesso!";
                        $tipo_mensagem = "success";
                    } else {
                        throw new Exception("Boleto não encontrado ou não pode ser cancelado.");
                    }
                } catch (Exception $e) {
                    $mensagem = "Erro ao cancelar boleto: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
                
            case 'reverter_pagamento':
                try {
                    $boleto_id = (int)$_POST['boleto_id'];
                    
                    // Buscar dados do boleto
                    $stmt = $pdo->prepare("SELECT * FROM adm_boletos WHERE id = ?");
                    $stmt->execute([$boleto_id]);
                    $boleto = $stmt->fetch();
                    
                    if (!$boleto) {
                        throw new Exception("Boleto não encontrado.");
                    }
                    
                    if ($boleto['status'] != 'pago') {
                        throw new Exception("Este boleto não está pago.");
                    }
                    
                    // Iniciar transação
                    $pdo->beginTransaction();
                    
                    try {
                        // Deletar registros de pagamento relacionados
                        $stmt = $pdo->prepare("DELETE FROM adm_pagamentos WHERE boleto_id = ?");
                        $stmt->execute([$boleto_id]);
                        
                        // Reverter status do boleto para pendente
                        $novo_status = 'pendente';
                        // Se a data de vencimento já passou, marcar como vencido
                        if (strtotime($boleto['data_vencimento']) < time()) {
                            $novo_status = 'vencido';
                        }
                        
                        $stmt = $pdo->prepare("UPDATE adm_boletos SET 
                            status = ?,
                            data_pagamento = NULL,
                            forma_pagamento = NULL
                            WHERE id = ?");
                        
                        $stmt->execute([$novo_status, $boleto_id]);
                        
                        $pdo->commit();
                        
                        $mensagem = "Pagamento revertido com sucesso! Boleto agora está como: " . ($novo_status == 'vencido' ? 'Vencido' : 'Pendente');
                        $tipo_mensagem = "success";
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                } catch (Exception $e) {
                    $mensagem = "Erro ao reverter pagamento: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
                
            case 'excluir_boleto':
                try {
                    $boleto_id = (int)$_POST['boleto_id'];
                    
                    // Buscar dados do boleto
                    $stmt = $pdo->prepare("SELECT * FROM adm_boletos WHERE id = ?");
                    $stmt->execute([$boleto_id]);
                    $boleto = $stmt->fetch();
                    
                    if (!$boleto) {
                        throw new Exception("Boleto não encontrado.");
                    }
                    
                    // Iniciar transação
                    $pdo->beginTransaction();
                    
                    try {
                        // Deletar registros de pagamento relacionados
                        $stmt = $pdo->prepare("DELETE FROM adm_pagamentos WHERE boleto_id = ?");
                        $stmt->execute([$boleto_id]);
                        
                        // Deletar o boleto
                        $stmt = $pdo->prepare("DELETE FROM adm_boletos WHERE id = ?");
                        $stmt->execute([$boleto_id]);
                        
                        $pdo->commit();
                        
                        $mensagem = "Boleto excluído com sucesso!";
                        $tipo_mensagem = "success";
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                } catch (Exception $e) {
                    $mensagem = "Erro ao excluir boleto: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
        }
    }
}

// Parâmetros de filtro
$filtro_empresa = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : null;
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;

// Se veio via iframe, aplicar filtro automaticamente
$is_iframe = isset($_GET['empresa_id']) || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe');

// Construir query
$where = [];
$params = [];

if ($filtro_empresa) {
    $where[] = "b.empresa_cliente_id = ?";
    $params[] = $filtro_empresa;
}

if ($filtro_status) {
    $where[] = "b.status = ?";
    $params[] = $filtro_status;
}

if ($data_inicio) {
    $where[] = "b.data_vencimento >= ?";
    $params[] = $data_inicio;
}

if ($data_fim) {
    $where[] = "b.data_vencimento <= ?";
    $params[] = $data_fim;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Buscar boletos
try {
    $sql = "SELECT b.*, 
                   e.razao_social as empresa_nome,
                   e.cnpj as empresa_cnpj,
                   ea.razao_social as empresa_adm_nome
            FROM adm_boletos b
            LEFT JOIN empresa_clientes e ON b.empresa_cliente_id = e.id
            LEFT JOIN empresa_adm ea ON b.empresa_adm_id = ea.id
            $where_clause
            ORDER BY b.data_vencimento DESC, b.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $boletos = $stmt->fetchAll();
    
    // Estatísticas - filtrar por empresa se estiver em modo iframe
    $stats_where = [];
    $stats_params = [];
    
    if ($filtro_empresa) {
        $stats_where[] = "empresa_cliente_id = ?";
        $stats_params[] = $filtro_empresa;
    }
    
    $stats_where_clause = !empty($stats_where) ? "WHERE " . implode(" AND ", $stats_where) : "";
    
    $stats_sql = "SELECT 
                    COUNT(*) as total_boletos,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagos,
                    SUM(CASE WHEN status = 'vencido' THEN 1 ELSE 0 END) as vencidos,
                    SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as total_recebido,
                    SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as total_pendente,
                    SUM(CASE WHEN status = 'vencido' THEN valor ELSE 0 END) as total_vencido
                  FROM adm_boletos
                  $stats_where_clause";
    
    if (!empty($stats_params)) {
        $stats_stmt = $pdo->prepare($stats_sql);
        $stats_stmt->execute($stats_params);
    } else {
        $stats_stmt = $pdo->query($stats_sql);
    }
    $estatisticas = $stats_stmt->fetch();
    
    // Buscar empresas para filtro
    $empresas_stmt = $pdo->query("SELECT id, razao_social FROM empresa_clientes ORDER BY razao_social");
    $empresas = $empresas_stmt->fetchAll();
    
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar dados: " . $e->getMessage();
    $tipo_mensagem = "error";
    $boletos = [];
    $estatisticas = [
        'total_boletos' => 0,
        'pendentes' => 0,
        'pagos' => 0,
        'vencidos' => 0,
        'total_recebido' => 0,
        'total_pendente' => 0,
        'total_vencido' => 0
    ];
    $empresas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posição Financeira - Sistema de Frotas</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .main-content.iframe-mode {
            margin-left: 0 !important;
            padding: 0;
            background: white;
        }
        .main-content.iframe-mode .header {
            padding: 10px 20px;
            margin-bottom: 0;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .main-content.iframe-mode .header h1 {
            font-size: 1.1rem;
            margin: 0;
            font-weight: 600;
        }
        .main-content.iframe-mode .stats-grid {
            padding: 10px 20px;
            margin-bottom: 0;
            background: white;
            border-bottom: 1px solid #dee2e6;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }
        .main-content.iframe-mode .stats-grid .stat-card {
            padding: 8px 10px;
            margin-bottom: 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .main-content.iframe-mode .stats-grid .stat-card h3 {
            font-size: 0.7rem;
            margin-bottom: 4px;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .main-content.iframe-mode .stats-grid .stat-card .value {
            font-size: 0.95rem;
            font-weight: 600;
        }
        .main-content.iframe-mode .table-container {
            margin: 0;
            border-radius: 0;
            box-shadow: none;
        }
        .main-content.iframe-mode .table-container table {
            font-size: 0.8rem;
        }
        .main-content.iframe-mode .table-container th,
        .main-content.iframe-mode .table-container td {
            padding: 6px 10px;
        }
        .main-content.iframe-mode .table-container th {
            font-size: 0.75rem;
            font-weight: 600;
        }
        .main-content.iframe-mode .message {
            margin: 0;
            border-radius: 0;
            padding: 8px 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #333;
            margin: 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            margin: 0;
        }
        .stat-card.success .value {
            color: #28a745;
        }
        .stat-card.danger .value {
            color: #dc3545;
        }
        .stat-card.warning .value {
            color: #ffc107;
        }
        .filters-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            font-size: 0.9rem;
            font-family: inherit;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            background: #007bff;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
            display: inline-block;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        .badge-secondary {
            background: #6c757d;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-content h2 {
            margin-top: 0;
            color: #333;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #333;
        }
        .message {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
        .actions button {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        @media (max-width: 768px) {
            .main-content:not(.iframe-mode) {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php if (!$is_iframe): ?>
        <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>
    
    <div class="main-content <?php echo $is_iframe ? 'iframe-mode' : ''; ?>">
        <div class="header">
            <h1><i class="fas fa-dollar-sign"></i> Posição Financeira</h1>
            <button class="btn btn-success" onclick="showGerarBoletoModal()">
                <i class="fas fa-plus"></i> Gerar Boleto
            </button>
        </div>

        <?php if ($mensagem && !$is_iframe): ?>
            <div class="message <?php echo $tipo_mensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Boletos</h3>
                <p class="value"><?php echo number_format($estatisticas['total_boletos']); ?></p>
            </div>
            <div class="stat-card success">
                <h3>Pagos</h3>
                <p class="value"><?php echo number_format($estatisticas['pagos']); ?></p>
            </div>
            <div class="stat-card warning">
                <h3>Pendentes</h3>
                <p class="value"><?php echo number_format($estatisticas['pendentes']); ?></p>
            </div>
            <div class="stat-card danger">
                <h3>Vencidos</h3>
                <p class="value"><?php echo number_format($estatisticas['vencidos']); ?></p>
            </div>
            <div class="stat-card success">
                <h3>Total Recebido</h3>
                <p class="value">R$ <?php echo number_format($estatisticas['total_recebido'], 2, ',', '.'); ?></p>
            </div>
            <div class="stat-card warning">
                <h3>Total Pendente</h3>
                <p class="value">R$ <?php echo number_format($estatisticas['total_pendente'], 2, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-container" <?php echo $is_iframe ? 'style="display: none;"' : ''; ?>>
            <h3 style="margin-top: 0; margin-bottom: 15px; color: #333;"><i class="fas fa-filter"></i> Filtros</h3>
            <form method="GET" action="">
                <?php if ($is_iframe): ?>
                    <input type="hidden" name="empresa_id" value="<?php echo $filtro_empresa; ?>">
                <?php endif; ?>
                <div class="filters-row">
                    <div class="form-group">
                        <label>Empresa</label>
                        <select name="empresa_id" <?php echo $is_iframe ? 'disabled' : ''; ?>>
                            <option value="">Todas as empresas</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?php echo $empresa['id']; ?>" <?php echo ($filtro_empresa == $empresa['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empresa['razao_social']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="pendente" <?php echo ($filtro_status == 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                            <option value="pago" <?php echo ($filtro_status == 'pago') ? 'selected' : ''; ?>>Pago</option>
                            <option value="vencido" <?php echo ($filtro_status == 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                            <option value="cancelado" <?php echo ($filtro_status == 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">
                    </div>
                    <div class="form-group">
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="posicao_financeira.php" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Boletos -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nº Boleto</th>
                        <th>Empresa</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($boletos)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                Nenhum boleto encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($boletos as $boleto): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($boleto['numero_boleto']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($boleto['empresa_nome'] ?? 'N/A'); ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($boleto['empresa_cnpj'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($boleto['descricao']); ?></td>
                                <td><strong>R$ <?php echo number_format($boleto['valor'], 2, ',', '.'); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($boleto['data_vencimento'])); ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pendente' => '<span class="badge badge-warning">Pendente</span>',
                                        'pago' => '<span class="badge badge-success">Pago</span>',
                                        'vencido' => '<span class="badge badge-danger">Vencido</span>',
                                        'cancelado' => '<span class="badge badge-secondary">Cancelado</span>'
                                    ];
                                    echo $status_badges[$boleto['status']] ?? '<span class="badge">' . htmlspecialchars($boleto['status']) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($boleto['status'] == 'pago' && $boleto['data_pagamento']): ?>
                                        <?php echo date('d/m/Y', strtotime($boleto['data_pagamento'])); ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($boleto['forma_pagamento'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($boleto['status'] == 'pendente' || $boleto['status'] == 'vencido'): ?>
                                            <button class="btn btn-success" onclick="showRegistrarPagamentoModal(<?php echo $boleto['id']; ?>, <?php echo $boleto['valor']; ?>)" title="Registrar Pagamento">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php if ($boleto['status'] == 'pendente'): ?>
                                                <button class="btn btn-danger" onclick="cancelarBoleto(<?php echo $boleto['id']; ?>)" title="Cancelar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($boleto['status'] == 'pago'): ?>
                                            <button class="btn btn-warning" onclick="reverterPagamento(<?php echo $boleto['id']; ?>)" title="Reverter Pagamento (Estorno)">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger" onclick="excluirBoleto(<?php echo $boleto['id']; ?>)" title="Excluir Boleto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-secondary" onclick="verDetalhes(<?php echo htmlspecialchars(json_encode($boleto)); ?>)" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Gerar Boleto -->
    <div id="gerarBoletoModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('gerarBoletoModal')">&times;</span>
            <h2><i class="fas fa-file-invoice"></i> Gerar Novo Boleto</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="gerar_boleto">
                <div class="form-group">
                    <label>Empresa Cliente *</label>
                    <select name="empresa_cliente_id" id="empresa_cliente_id" required <?php echo $is_iframe && $filtro_empresa ? 'disabled' : ''; ?> onchange="toggleLoteFields()">
                        <option value="">Selecione uma empresa</option>
                        <option value="lote">📦 Lote - Todas as Empresas</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php echo ($is_iframe && $filtro_empresa && $empresa['id'] == $filtro_empresa) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empresa['razao_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_iframe && $filtro_empresa): ?>
                        <input type="hidden" name="empresa_cliente_id" value="<?php echo $filtro_empresa; ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Descrição *</label>
                    <input type="text" name="descricao" required placeholder="Ex: Mensalidade Janeiro/2025">
                </div>
                <!-- Campos para geração individual -->
                <div id="campo_valor_individual">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="usar_valor_plano" id="usar_valor_plano" value="1" onchange="toggleValorPlano()">
                            <span>Usar valor do plano configurado</span>
                        </label>
                        <small style="color: #666; font-size: 0.85rem;">Se marcado, o valor será calculado automaticamente baseado no plano da empresa (pacote = valor fixo, avulso = por veículo)</small>
                    </div>
                    <div class="form-group" id="campo_valor_manual">
                        <label>Valor (R$) *</label>
                        <input type="number" name="valor" id="valor_individual" step="0.01" min="0.01" placeholder="0.00" required>
                        <small style="color: #666; font-size: 0.85rem;">Informe o valor manual do boleto</small>
                    </div>
                </div>
                <!-- Campos para geração em lote -->
                <div id="campos_lote" style="display: none;">
                    <div class="form-group">
                        <label>Valor Fixo para Todas (R$)</label>
                        <input type="number" name="valor_fixo" id="valor_fixo" step="0.01" min="0" placeholder="0.00">
                        <small style="color: #666; font-size: 0.85rem;">Deixe vazio para usar valor por veículo</small>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="usar_valor_por_veiculo" id="usar_valor_por_veiculo" value="1" onchange="toggleValorFixo()">
                            <span>Usar valor do plano/veículo de cada empresa</span>
                        </label>
                        <small style="color: #666; font-size: 0.85rem;">O valor será calculado automaticamente baseado no plano: pacote = valor fixo, avulso = (valor_por_veiculo × quantidade de veículos ativos)</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>Data de Vencimento *</label>
                    <input type="date" name="data_vencimento" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Boleto</label>
                    <select name="tipo_boleto">
                        <option value="mensalidade">Mensalidade</option>
                        <option value="adicional">Adicional</option>
                        <option value="multa">Multa</option>
                        <option value="outros">Outros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mês de Referência (opcional)</label>
                    <input type="month" name="mes_referencia" placeholder="YYYY-MM">
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Gerar Boleto
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('gerarBoletoModal')" style="margin-left: 10px;">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Registrar Pagamento -->
    <div id="registrarPagamentoModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('registrarPagamentoModal')">&times;</span>
            <h2><i class="fas fa-money-bill-wave"></i> Registrar Pagamento</h2>
            <form method="POST" action="" id="formRegistrarPagamento">
                <input type="hidden" name="action" value="registrar_pagamento">
                <input type="hidden" name="boleto_id" id="boleto_id_pagamento">
                <div class="form-group">
                    <label>Valor do Boleto</label>
                    <input type="text" id="valor_boleto" readonly style="background: #f0f0f0;">
                </div>
                <div class="form-group">
                    <label>Data do Pagamento *</label>
                    <input type="date" name="data_pagamento" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Forma de Pagamento *</label>
                    <select name="forma_pagamento" required>
                        <option value="">Selecione</option>
                        <option value="PIX">PIX</option>
                        <option value="Boleto">Boleto</option>
                        <option value="Transferência">Transferência Bancária</option>
                        <option value="Cartão de Crédito">Cartão de Crédito</option>
                        <option value="Cartão de Débito">Cartão de Débito</option>
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Outros">Outros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" rows="3" placeholder="Observações sobre o pagamento"></textarea>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Registrar Pagamento
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registrarPagamentoModal')" style="margin-left: 10px;">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ver Detalhes -->
    <div id="detalhesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('detalhesModal')">&times;</span>
            <h2><i class="fas fa-info-circle"></i> Detalhes do Boleto</h2>
            <div id="detalhesContent"></div>
        </div>
    </div>

    <script>
        function showGerarBoletoModal() {
            document.getElementById('gerarBoletoModal').style.display = 'block';
            toggleLoteFields(); // Verificar estado inicial
            toggleValorPlano(); // Verificar estado inicial do checkbox de valor do plano
        }
        
        function toggleLoteFields() {
            const empresaSelect = document.getElementById('empresa_cliente_id');
            const isLote = empresaSelect.value === 'lote';
            const camposLote = document.getElementById('campos_lote');
            const campoValorIndividual = document.getElementById('campo_valor_individual');
            
            if (isLote) {
                camposLote.style.display = 'block';
                campoValorIndividual.style.display = 'none';
                // Limpar campos individuais
                document.getElementById('usar_valor_plano').checked = false;
                document.getElementById('valor_individual').value = '';
                document.getElementById('valor_individual').removeAttribute('required');
            } else {
                camposLote.style.display = 'none';
                campoValorIndividual.style.display = 'block';
                // Limpar campos de lote
                document.getElementById('valor_fixo').value = '';
                document.getElementById('usar_valor_por_veiculo').checked = false;
                // Resetar estado do campo individual
                toggleValorPlano();
            }
        }
        
        function toggleValorFixo() {
            const usarValorPorVeiculo = document.getElementById('usar_valor_por_veiculo').checked;
            const valorFixo = document.getElementById('valor_fixo');
            
            if (usarValorPorVeiculo) {
                valorFixo.removeAttribute('required');
            } else {
                valorFixo.setAttribute('required', 'required');
            }
        }
        
        function toggleValorPlano() {
            const usarValorPlano = document.getElementById('usar_valor_plano');
            if (!usarValorPlano) return; // Elemento não existe (pode estar em modo lote)
            
            const isChecked = usarValorPlano.checked;
            const campoValorManual = document.getElementById('campo_valor_manual');
            const valorIndividual = document.getElementById('valor_individual');
            
            if (isChecked) {
                // Ocultar campo manual e remover obrigatoriedade
                campoValorManual.style.display = 'none';
                valorIndividual.removeAttribute('required');
                valorIndividual.value = '';
            } else {
                // Mostrar campo manual e tornar obrigatório
                campoValorManual.style.display = 'block';
                valorIndividual.setAttribute('required', 'required');
            }
        }
        
        // Validar formulário antes de enviar
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#gerarBoletoModal form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const empresaSelect = document.getElementById('empresa_cliente_id');
                    const isLote = empresaSelect.value === 'lote';
                    
                    if (isLote) {
                        const valorFixo = document.getElementById('valor_fixo').value;
                        const usarValorPorVeiculo = document.getElementById('usar_valor_por_veiculo').checked;
                        
                        if (!valorFixo && !usarValorPorVeiculo) {
                            e.preventDefault();
                            alert('Para gerar em lote, você deve definir um valor fixo ou marcar "Usar valor do plano/veículo"');
                            return false;
                        }
                        
                        // Validar valor fixo se informado
                        if (valorFixo && parseFloat(valorFixo) <= 0) {
                            e.preventDefault();
                            alert('O valor do boleto não pode ser zero ou negativo.');
                            return false;
                        }
                    } else {
                        // Validação para geração individual
                        const usarValorPlano = document.getElementById('usar_valor_plano').checked;
                        const valorManual = document.getElementById('valor_individual').value;
                        
                        if (!usarValorPlano) {
                            // Se não está usando valor do plano, o valor manual é obrigatório
                            if (!valorManual || parseFloat(valorManual) <= 0) {
                                e.preventDefault();
                                alert('Você deve marcar "Usar valor do plano configurado" ou informar um valor manual maior que zero.');
                                return false;
                            }
                        }
                    }
                });
            }
        });

        function showRegistrarPagamentoModal(boletoId, valor) {
            document.getElementById('boleto_id_pagamento').value = boletoId;
            document.getElementById('valor_boleto').value = 'R$ ' + valor.toFixed(2).replace('.', ',');
            document.getElementById('registrarPagamentoModal').style.display = 'block';
        }

        function cancelarBoleto(boletoId) {
            if (confirm('Tem certeza que deseja cancelar este boleto?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancelar_boleto">
                    <input type="hidden" name="boleto_id" value="${boletoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function reverterPagamento(boletoId) {
            if (confirm('Tem certeza que deseja reverter o pagamento deste boleto? O boleto voltará para o status pendente ou vencido.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reverter_pagamento">
                    <input type="hidden" name="boleto_id" value="${boletoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function excluirBoleto(boletoId) {
            if (confirm('ATENÇÃO: Tem certeza que deseja EXCLUIR este boleto permanentemente?\n\nEsta ação não pode ser desfeita. Todos os dados relacionados (pagamentos, etc.) serão removidos.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="excluir_boleto">
                    <input type="hidden" name="boleto_id" value="${boletoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function verDetalhes(boleto) {
            const content = `
                <div style="line-height: 1.8;">
                    <p><strong>Número do Boleto:</strong> ${boleto.numero_boleto}</p>
                    <p><strong>Empresa:</strong> ${boleto.empresa_nome || 'N/A'}</p>
                    <p><strong>CNPJ:</strong> ${boleto.empresa_cnpj || 'N/A'}</p>
                    <p><strong>Descrição:</strong> ${boleto.descricao}</p>
                    <p><strong>Valor:</strong> R$ ${parseFloat(boleto.valor).toFixed(2).replace('.', ',')}</p>
                    <p><strong>Data de Emissão:</strong> ${new Date(boleto.data_emissao).toLocaleDateString('pt-BR')}</p>
                    <p><strong>Data de Vencimento:</strong> ${new Date(boleto.data_vencimento).toLocaleDateString('pt-BR')}</p>
                    <p><strong>Status:</strong> ${boleto.status}</p>
                    ${boleto.linha_digitavel ? `<p><strong>Linha Digitável:</strong> ${boleto.linha_digitavel}</p>` : ''}
                    ${boleto.codigo_barras ? `<p><strong>Código de Barras:</strong> ${boleto.codigo_barras}</p>` : ''}
                    ${boleto.data_pagamento ? `<p><strong>Data de Pagamento:</strong> ${new Date(boleto.data_pagamento).toLocaleDateString('pt-BR')}</p>` : ''}
                    ${boleto.forma_pagamento ? `<p><strong>Forma de Pagamento:</strong> ${boleto.forma_pagamento}</p>` : ''}
                    ${boleto.observacoes ? `<p><strong>Observações:</strong> ${boleto.observacoes}</p>` : ''}
                </div>
            `;
            document.getElementById('detalhesContent').innerHTML = content;
            document.getElementById('detalhesModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Atualizar status de boletos vencidos
        setInterval(function() {
            // Esta função pode ser chamada via AJAX para atualizar status de vencidos
        }, 60000); // A cada minuto
    </script>
</body>
</html>
