<?php
require_once 'config.php';
require_once 'db.php';

// Função para registrar ações do motorista
function log_motorista_action($motorista_id, $acao, $detalhes = '') {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            INSERT INTO logs_motorista (motorista_id, acao, detalhes, data_registro)
            VALUES (:motorista_id, :acao, :detalhes, NOW())
        ');
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':detalhes', $detalhes);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('Erro ao registrar log do motorista: ' . $e->getMessage());
    }
}

// Função para validar veículo da empresa
function validar_veiculo_empresa($veiculo_id, $empresa_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT id FROM veiculos 
            WHERE id = :veiculo_id 
            AND empresa_id = :empresa_id
        ');
        $stmt->bindParam(':veiculo_id', $veiculo_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (PDOException $e) {
        error_log('Erro ao validar veículo: ' . $e->getMessage());
        return false;
    }
}

// Função para validar quilometragem do veículo
function validar_km_veiculo($veiculo_id, $km) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT km_atual FROM veiculos 
            WHERE id = :veiculo_id
        ');
        $stmt->bindParam(':veiculo_id', $veiculo_id);
        $stmt->execute();
        $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$veiculo) {
            return false;
        }
        
        return $km >= $veiculo['km_atual'];
    } catch (PDOException $e) {
        error_log('Erro ao validar quilometragem: ' . $e->getMessage());
        return false;
    }
}

// Função para obter veículos da empresa
function obter_veiculos_empresa($empresa_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT id, placa, modelo 
            FROM veiculos 
            WHERE empresa_id = :empresa_id 
            ORDER BY placa
        ');
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao obter veículos: ' . $e->getMessage());
        return [];
    }
}

// Função para obter rotas pendentes
function obter_rotas_pendentes($motorista_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT r.*, v.placa, v.modelo 
            FROM rotas r 
            JOIN veiculos v ON r.veiculo_id = v.id 
            WHERE r.motorista_id = :motorista_id 
            AND r.status = "pendente" 
            ORDER BY r.data_saida DESC
        ');
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao obter rotas pendentes: ' . $e->getMessage());
        return [];
    }
}

// Função para obter abastecimentos pendentes
function obter_abastecimentos_pendentes($motorista_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT a.*, v.placa, v.modelo 
            FROM abastecimentos a 
            JOIN veiculos v ON a.veiculo_id = v.id 
            WHERE a.motorista_id = :motorista_id 
            AND a.status = "pendente" 
            ORDER BY a.data_abastecimento DESC
        ');
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao obter abastecimentos pendentes: ' . $e->getMessage());
        return [];
    }
}

// Função para obter checklists pendentes
function obter_checklists_pendentes($motorista_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT c.*, v.placa, v.modelo 
            FROM checklists c 
            JOIN veiculos v ON c.veiculo_id = v.id 
            WHERE c.motorista_id = :motorista_id 
            AND c.status = "pendente" 
            ORDER BY c.data_checklist DESC
        ');
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao obter checklists pendentes: ' . $e->getMessage());
        return [];
    }
}

// Função para registrar rota
function registrar_rota($dados) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            INSERT INTO rotas (
                motorista_id, empresa_id, veiculo_id, 
                data_saida, estado_origem, cidade_origem,
                estado_destino, cidade_destino, km_saida,
                km_chegada, distancia_km, peso_carga,
                descricao_carga, observacoes, status,
                fonte, data_registro
            ) VALUES (
                :motorista_id, :empresa_id, :veiculo_id,
                :data_saida, :estado_origem, :cidade_origem,
                :estado_destino, :cidade_destino, :km_saida,
                :km_chegada, :distancia_km, :peso_carga,
                :descricao_carga, :observacoes, "pendente",
                "motorista", NOW()
            )
        ');
        
        $stmt->bindParam(':motorista_id', $dados['motorista_id']);
        $stmt->bindParam(':empresa_id', $dados['empresa_id']);
        $stmt->bindParam(':veiculo_id', $dados['veiculo_id']);
        $stmt->bindParam(':data_saida', $dados['data_saida']);
        $stmt->bindParam(':estado_origem', $dados['estado_origem']);
        $stmt->bindParam(':cidade_origem', $dados['cidade_origem']);
        $stmt->bindParam(':estado_destino', $dados['estado_destino']);
        $stmt->bindParam(':cidade_destino', $dados['cidade_destino']);
        $stmt->bindParam(':km_saida', $dados['km_saida']);
        $stmt->bindParam(':km_chegada', $dados['km_chegada']);
        $stmt->bindParam(':distancia_km', $dados['distancia_km']);
        $stmt->bindParam(':peso_carga', $dados['peso_carga']);
        $stmt->bindParam(':descricao_carga', $dados['descricao_carga']);
        $stmt->bindParam(':observacoes', $dados['observacoes']);
        
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        error_log('Erro ao registrar rota: ' . $e->getMessage());
        return false;
    }
}

// Função para registrar abastecimento
function registrar_abastecimento($dados) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            INSERT INTO abastecimentos (
                motorista_id, empresa_id, veiculo_id, rota_id,
                posto, data_abastecimento, litros, valor_litro, valor_total,
                km_atual, tipo_combustivel, forma_pagamento, observacoes,
                status, fonte, data_cadastro
            ) VALUES (
                :motorista_id, :empresa_id, :veiculo_id, :rota_id,
                :posto, :data_abastecimento, :litros, :valor_litro, :valor_total,
                :km_atual, :tipo_combustivel, :forma_pagamento, :observacoes,
                "pendente", "motorista", NOW()
            )
        ');
        
        $stmt->bindParam(':motorista_id', $dados['motorista_id']);
        $stmt->bindParam(':empresa_id', $dados['empresa_id']);
        $stmt->bindParam(':veiculo_id', $dados['veiculo_id']);
        $stmt->bindParam(':rota_id', $dados['rota_id'] ?? null);
        $stmt->bindParam(':posto', $dados['posto']);
        $stmt->bindParam(':data_abastecimento', $dados['data_abastecimento']);
        $stmt->bindParam(':litros', $dados['quantidade']);
        $stmt->bindParam(':valor_litro', $dados['preco_litro']);
        $stmt->bindParam(':valor_total', $dados['valor_total']);
        $stmt->bindParam(':km_atual', $dados['km_atual']);
        $stmt->bindParam(':tipo_combustivel', $dados['tipo_combustivel']);
        $stmt->bindParam(':forma_pagamento', $dados['forma_pagamento'] ?? 'dinheiro');
        $stmt->bindParam(':observacoes', $dados['observacoes']);
        
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        error_log('Erro ao registrar abastecimento: ' . $e->getMessage());
        return false;
    }
}

// Função para registrar checklist
function registrar_checklist($dados) {
    try {
        $conn = getConnection();
        $conn->beginTransaction();
        
        // Insere o checklist
        $stmt = $conn->prepare('
            INSERT INTO checklists (
                motorista_id, empresa_id, veiculo_id,
                data_checklist, tipo_checklist, km_atual,
                observacoes, status, fonte, data_registro
            ) VALUES (
                :motorista_id, :empresa_id, :veiculo_id,
                :data_checklist, :tipo_checklist, :km_atual,
                :observacoes, "pendente", "motorista", NOW()
            )
        ');
        
        $stmt->bindParam(':motorista_id', $dados['motorista_id']);
        $stmt->bindParam(':empresa_id', $dados['empresa_id']);
        $stmt->bindParam(':veiculo_id', $dados['veiculo_id']);
        $stmt->bindParam(':data_checklist', $dados['data_checklist']);
        $stmt->bindParam(':tipo_checklist', $dados['tipo_checklist']);
        $stmt->bindParam(':km_atual', $dados['km_atual']);
        $stmt->bindParam(':observacoes', $dados['observacoes']);
        
        $stmt->execute();
        $checklist_id = $conn->lastInsertId();
        
        // Insere os itens do checklist
        $stmt = $conn->prepare('
            INSERT INTO checklist_itens (
                checklist_id, item, status
            ) VALUES (
                :checklist_id, :item, :status
            )
        ');
        
        foreach ($dados['itens'] as $item) {
            $stmt->bindParam(':checklist_id', $checklist_id);
            $stmt->bindParam(':item', $item['nome']);
            $stmt->bindParam(':status', $item['status']);
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log('Erro ao registrar checklist: ' . $e->getMessage());
        return false;
    }
} 