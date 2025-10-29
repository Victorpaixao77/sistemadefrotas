<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$empresa_id = obterEmpresaId();
$usuario_id = obterUsuarioId();

try {
    $db = getDB();
    
    // Obter parâmetros
    $tipo = $_GET['tipo'] ?? '';
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    $formato = $_GET['formato'] ?? 'pdf';
    $status = $_GET['status'] ?? 'todos';
    $graficos = $_GET['graficos'] ?? '1';
    $detalhes = $_GET['detalhes'] ?? '1';
    
    // Validar tipo
    $tipos_validos = ['clientes', 'financeiro', 'atendimentos', 'equipamentos', 'comissoes', 'personalizado'];
    if (!in_array($tipo, $tipos_validos)) {
        throw new Exception('Tipo de relatório inválido');
    }
    
    // Buscar dados conforme o tipo
    $dados = [];
    $titulo = '';
    
    switch ($tipo) {
        case 'clientes':
            $titulo = 'Relatório de Clientes';
            $sql = "
                SELECT 
                    codigo,
                    identificador,
                    cpf_cnpj,
                    nome_razao_social,
                    cidade,
                    uf,
                    telefone,
                    celular,
                    porcentagem_recorrencia,
                    situacao,
                    DATE_FORMAT(data_cadastro, '%d/%m/%Y') as data_cadastro_formatada
                FROM seguro_clientes
                WHERE seguro_empresa_id = ?
            ";
            
            if ($status !== 'todos') {
                $sql .= " AND situacao = '$status'";
            }
            
            $sql .= " ORDER BY nome_razao_social ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'financeiro':
            $titulo = 'Relatório Financeiro';
            // Buscar porcentagem da empresa
            $stmt = $db->prepare("SELECT porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $porcentagem_fixa = floatval($stmt->fetchColumn());
            
            $sql = "
                SELECT 
                    sf.protocolo,
                    c.nome_razao_social as cliente,
                    sf.tipo_documento,
                    sf.valor,
                    sf.valor_pago,
                    DATE_FORMAT(sf.data_baixa, '%d/%m/%Y') as data_baixa,
                    sf.status,
                    (sf.valor_pago * ? / 100 + sf.valor_pago * c.porcentagem_recorrencia / 100) as comissao
                FROM seguro_financeiro sf
                INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
                WHERE sf.seguro_empresa_id = ?
                  AND sf.status = 'pago'
                  AND sf.data_baixa BETWEEN ? AND ?
                ORDER BY sf.data_baixa DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$porcentagem_fixa, $empresa_id, $data_inicio, $data_fim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'atendimentos':
            $titulo = 'Relatório de Atendimentos';
            $sql = "
                SELECT 
                    a.protocolo,
                    c.nome_razao_social as cliente,
                    a.tipo,
                    a.prioridade,
                    a.titulo,
                    a.status,
                    DATE_FORMAT(a.data_abertura, '%d/%m/%Y %H:%i') as data_abertura,
                    DATE_FORMAT(a.data_fechamento, '%d/%m/%Y %H:%i') as data_fechamento,
                    u.nome as usuario
                FROM seguro_atendimentos a
                LEFT JOIN seguro_clientes c ON a.seguro_cliente_id = c.id
                LEFT JOIN seguro_usuarios u ON a.usuario_id = u.id
                WHERE a.seguro_empresa_id = ?
                  AND DATE(a.data_abertura) BETWEEN ? AND ?
                ORDER BY a.data_abertura DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$empresa_id, $data_inicio, $data_fim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'equipamentos':
            $titulo = 'Relatório de Equipamentos';
            $sql = "
                SELECT 
                    e.tipo,
                    e.descricao,
                    c.nome_razao_social as cliente,
                    e.marca,
                    e.modelo,
                    e.numero_serie,
                    DATE_FORMAT(e.data_instalacao, '%d/%m/%Y') as data_instalacao,
                    e.localizacao,
                    e.situacao
                FROM seguro_equipamentos e
                INNER JOIN seguro_clientes c ON e.seguro_cliente_id = c.id
                WHERE e.seguro_empresa_id = ?
                ORDER BY c.nome_razao_social ASC, e.tipo ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'comissoes':
            $titulo = 'Relatório de Comissões';
            // Buscar porcentagem da empresa
            $stmt = $db->prepare("SELECT porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $porcentagem_fixa = floatval($stmt->fetchColumn());
            
            $sql = "
                SELECT 
                    c.nome_razao_social as cliente,
                    COUNT(sf.id) as total_documentos,
                    SUM(sf.valor_pago) as total_pago,
                    SUM(sf.valor_pago * ? / 100 + sf.valor_pago * c.porcentagem_recorrencia / 100) as comissao_total,
                    c.porcentagem_recorrencia
                FROM seguro_clientes c
                LEFT JOIN seguro_financeiro sf ON (
                    COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id
                    AND sf.status = 'pago'
                    AND sf.data_baixa BETWEEN ? AND ?
                )
                WHERE c.seguro_empresa_id = ?
                  AND c.situacao = 'ativo'
                GROUP BY c.id, c.nome_razao_social, c.porcentagem_recorrencia
                HAVING total_pago > 0
                ORDER BY comissao_total DESC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$porcentagem_fixa, $data_inicio, $data_fim, $empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
    
    // Se formato for CSV, gerar CSV
    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_' . $tipo . '_' . date('YmdHis') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalho
        if (count($dados) > 0) {
            fputcsv($output, array_keys($dados[0]), ';');
            
            // Dados
            foreach ($dados as $row) {
                fputcsv($output, $row, ';');
            }
        }
        
        fclose($output);
        exit;
    }
    
    // Para PDF e Excel, por enquanto, gerar CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_' . $tipo . '_' . date('YmdHis') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Título
    fputcsv($output, [$titulo], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim))], ';');
    fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
    fputcsv($output, [''], ';');
    
    // Cabeçalho
    if (count($dados) > 0) {
        fputcsv($output, array_keys($dados[0]), ';');
        
        // Dados
        foreach ($dados as $row) {
            fputcsv($output, $row, ';');
        }
    } else {
        fputcsv($output, ['Nenhum dado encontrado para o período selecionado'], ';');
    }
    
    // Registrar no histórico (se a tabela existir)
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'seguro_historico_relatorios'");
        if ($stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO seguro_historico_relatorios 
                (seguro_empresa_id, seguro_usuario_id, tipo_relatorio, 
                 periodo_inicio, periodo_fim, total_registros, formato)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $empresa_id,
                $usuario_id,
                $tipo,
                $data_inicio,
                $data_fim,
                count($dados),
                'csv'
            ]);
        }
    } catch (Exception $e) {
        // Ignorar erro de histórico
        error_log("Erro ao registrar histórico: " . $e->getMessage());
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Erro ao gerar relatório: " . $e->getMessage());
    http_response_code(500);
    echo "Erro ao gerar relatório: " . $e->getMessage();
    exit;
}
?>

