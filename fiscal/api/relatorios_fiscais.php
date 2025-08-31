<?php
/**
 * 📊 API de Relatórios Fiscais
 * 📋 Gera relatórios em PDF/Excel dos documentos fiscais
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configure session
configure_session();
session_start();

// Check authentication
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

try {
    $action = $_GET['action'] ?? '';
    
    if ($action !== 'gerar_relatorio') {
        throw new Exception('Ação inválida');
    }
    
    $tipo = $_GET['tipo'] ?? '';
    $formato = $_GET['formato'] ?? 'pdf';
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // Validar parâmetros
    if (empty($tipo)) {
        throw new Exception('Tipo de relatório é obrigatório');
    }
    
    // Gerar dados do relatório
    $dados_relatorio = gerarDadosRelatorio($tipo, $data_inicio, $data_fim, $empresa_id);
    
    // Gerar relatório no formato solicitado
    if ($formato === 'pdf') {
        gerarRelatorioPDF($dados_relatorio, $tipo);
    } else {
        gerarRelatorioExcel($dados_relatorio, $tipo);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Gerar dados do relatório baseado no tipo
 */
function gerarDadosRelatorio($tipo, $data_inicio, $data_fim, $empresa_id) {
    global $conn;
    
    $dados = [];
    
    switch ($tipo) {
        case 'nfe_recebidas':
            $stmt = $conn->prepare("
                SELECT 
                    numero_nfe,
                    serie_nfe,
                    chave_acesso,
                    data_emissao,
                    data_entrada,
                    cliente_razao_social,
                    cliente_nome_fantasia,
                    cliente_cnpj,
                    valor_total,
                    status,
                    protocolo_autorizacao,
                    observacoes
                FROM fiscal_nfe_clientes 
                WHERE empresa_id = ? 
                    AND data_emissao BETWEEN ? AND ?
                ORDER BY data_emissao DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Relatório de NF-e Recebidas';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        case 'cte_emitidos':
            $stmt = $conn->prepare("
                SELECT 
                    numero_cte,
                    serie_cte,
                    chave_acesso,
                    data_emissao,
                    tipo_servico,
                    natureza_operacao,
                    origem_estado,
                    origem_cidade,
                    destino_estado,
                    destino_cidade,
                    valor_total,
                    peso_total,
                    status,
                    protocolo_autorizacao,
                    observacoes
                FROM fiscal_cte 
                WHERE empresa_id = ? 
                    AND data_emissao BETWEEN ? AND ?
                ORDER BY data_emissao DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Relatório de CT-e Emitidos';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        case 'mdfe_gerados':
            $stmt = $conn->prepare("
                SELECT 
                    numero_mdfe,
                    serie_mdfe,
                    chave_acesso,
                    data_emissao,
                    tipo_transporte,
                    protocolo_autorizacao,
                    status,
                    valor_total_carga,
                    peso_total_carga,
                    qtd_total_volumes,
                    qtd_total_peso,
                    motorista_id,
                    veiculo_id,
                    observacoes
                FROM fiscal_mdfe 
                WHERE empresa_id = ? 
                    AND data_emissao BETWEEN ? AND ?
                ORDER BY data_emissao DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Relatório de MDF-e Gerados';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        case 'eventos_fiscais':
            $stmt = $conn->prepare("
                SELECT 
                    e.tipo_evento,
                    e.documento_tipo,
                    e.data_evento,
                    e.justificativa,
                    e.status,
                    e.protocolo_evento,
                    n.numero_nfe,
                    n.chave_acesso
                FROM fiscal_eventos_fiscais e
                JOIN fiscal_nfe_clientes n ON e.documento_id = n.id
                WHERE e.empresa_id = ? 
                    AND e.data_evento BETWEEN ? AND ?
                ORDER BY e.data_evento DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Relatório de Eventos Fiscais';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        case 'status_sefaz':
            $stmt = $conn->prepare("
                SELECT 'NF-e' as tipo_doc, numero_nfe as numero, status, data_emissao, protocolo_autorizacao as protocolo
                FROM fiscal_nfe_clientes 
                WHERE empresa_id = ? AND data_emissao BETWEEN ? AND ?
                UNION ALL
                SELECT 'CT-e' as tipo_doc, numero_cte as numero, status, data_emissao, protocolo_autorizacao as protocolo
                FROM fiscal_cte 
                WHERE empresa_id = ? AND data_emissao BETWEEN ? AND ?
                UNION ALL
                SELECT 'MDF-e' as tipo_doc, numero_mdfe as numero, status, data_emissao, protocolo_autorizacao as protocolo
                FROM fiscal_mdfe 
                WHERE empresa_id = ? AND data_emissao BETWEEN ? AND ?
                ORDER BY data_emissao DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim, $empresa_id, $data_inicio, $data_fim, $empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Relatório de Status SEFAZ';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        case 'viagens_completas':
            $stmt = $conn->prepare("
                SELECT 
                    numero_mdfe,
                    data_emissao,
                    tipo_transporte,
                    peso_total_carga,
                    valor_total_carga,
                    qtd_total_volumes,
                    qtd_total_peso,
                    status,
                    motorista_id,
                    veiculo_id,
                    protocolo_autorizacao,
                    observacoes
                FROM fiscal_mdfe
                WHERE empresa_id = ? 
                    AND data_emissao BETWEEN ? AND ?
                ORDER BY data_emissao DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Relatório de Viagens Completas';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        case 'alertas_validacoes':
            // Gerar alertas simples sem depender de arquivo externo
            $alertas_simples = [];
            
            // 1. NF-e sem status adequado há muito tempo
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total, 'NF-e pendentes há mais de 7 dias' as descricao
                FROM fiscal_nfe_clientes 
                WHERE empresa_id = ? 
                    AND status = 'pendente'
                    AND DATEDIFF(CURDATE(), data_emissao) > 7
            ");
            $stmt->execute([$empresa_id]);
            $nfe_pendentes = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($nfe_pendentes['total'] > 0) {
                $alertas_simples[] = [
                    'tipo' => 'warning',
                    'categoria' => 'nfe_pendente',
                    'titulo' => 'NF-e Pendentes',
                    'descricao' => $nfe_pendentes['total'] . ' NF-e pendentes há mais de 7 dias',
                    'prioridade' => 'media',
                    'acao_sugerida' => 'Verificar status das NF-e'
                ];
            }
            
            // 2. CT-e sem protocolo
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total, 'CT-e sem protocolo de autorização' as descricao
                FROM fiscal_cte 
                WHERE empresa_id = ? 
                    AND (protocolo_autorizacao IS NULL OR protocolo_autorizacao = '')
                    AND status = 'pendente'
            ");
            $stmt->execute([$empresa_id]);
            $cte_sem_protocolo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cte_sem_protocolo['total'] > 0) {
                $alertas_simples[] = [
                    'tipo' => 'info',
                    'categoria' => 'cte_pendente',
                    'titulo' => 'CT-e Sem Protocolo',
                    'descricao' => $cte_sem_protocolo['total'] . ' CT-e sem protocolo de autorização',
                    'prioridade' => 'baixa',
                    'acao_sugerida' => 'Enviar para SEFAZ'
                ];
            }
            
            // 3. Eventos fiscais rejeitados
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM fiscal_eventos_fiscais 
                WHERE empresa_id = ? 
                    AND status = 'rejeitado'
                    AND DATEDIFF(CURDATE(), data_evento) <= 30
            ");
            $stmt->execute([$empresa_id]);
            $eventos_rejeitados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($eventos_rejeitados['total'] > 0) {
                $alertas_simples[] = [
                    'tipo' => 'danger',
                    'categoria' => 'evento_erro',
                    'titulo' => 'Eventos Fiscais Rejeitados',
                    'descricao' => $eventos_rejeitados['total'] . ' eventos fiscais rejeitados nos últimos 30 dias',
                    'prioridade' => 'alta',
                    'acao_sugerida' => 'Reprocessar eventos rejeitados'
                ];
            }
            
            // Se não há alertas, criar um registro informativo
            if (empty($alertas_simples)) {
                $alertas_simples[] = [
                    'tipo' => 'success',
                    'categoria' => 'sistema_ok',
                    'titulo' => 'Sistema em Ordem',
                    'descricao' => 'Não foram identificados alertas no período analisado',
                    'prioridade' => 'baixa',
                    'acao_sugerida' => 'Continuar monitoramento'
                ];
            }
            
            $dados['registros'] = $alertas_simples;
            $dados['titulo'] = 'Relatório de Alertas e Validações';
            $dados['periodo'] = "Gerado em: " . date('d/m/Y H:i:s');
            break;
            
        case 'timeline_documentos':
            $stmt = $conn->prepare("
                SELECT 
                    'NF-e' as tipo_doc,
                    n.numero_nfe as numero,
                    n.data_emissao,
                    n.status,
                    'Recebimento' as evento
                FROM fiscal_nfe_clientes n
                WHERE n.empresa_id = ? AND n.data_emissao BETWEEN ? AND ?
                UNION ALL
                SELECT 
                    'CT-e' as tipo_doc,
                    c.numero_cte as numero,
                    c.data_emissao,
                    c.status,
                    'Emissão' as evento
                FROM fiscal_cte c
                WHERE c.empresa_id = ? AND c.data_emissao BETWEEN ? AND ?
                UNION ALL
                SELECT 
                    'MDF-e' as tipo_doc,
                    m.numero_mdfe as numero,
                    m.data_emissao,
                    m.status,
                    'Geração' as evento
                FROM fiscal_mdfe m
                WHERE m.empresa_id = ? AND m.data_emissao BETWEEN ? AND ?
                ORDER BY data_emissao DESC
            ");
            $stmt->execute([$empresa_id, $data_inicio, $data_fim, $empresa_id, $data_inicio, $data_fim, $empresa_id, $data_inicio, $data_fim]);
            $dados['registros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dados['titulo'] = 'Timeline de Documentos Fiscais';
            $dados['periodo'] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
            break;
            
        default:
            throw new Exception('Tipo de relatório não encontrado: ' . $tipo);
    }
    
    return $dados;
}

/**
 * Gerar relatório em PDF
 */
function gerarRelatorioPDF($dados, $tipo) {
    require_once '../../vendor/autoload.php';
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L', // Paisagem para mais colunas
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 16,
        'margin_bottom' => 16,
        'default_font' => 'dejavusans'
    ]);
    
    $html = gerarHTMLRelatorio($dados);
    
    $mpdf->SetTitle($dados['titulo']);
    $mpdf->SetAuthor('Sistema de Gestão de Frotas - Módulo Fiscal');
    $mpdf->WriteHTML($html);
    
    $filename = sanitizeFilename($dados['titulo']) . '_' . date('Y-m-d_H-i-s');
    $mpdf->Output($filename . '.pdf', 'D');
}

/**
 * Gerar relatório em Excel
 */
function gerarRelatorioExcel($dados, $tipo) {
    require_once '../../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Título
    $sheet->setCellValue('A1', $dados['titulo']);
    $sheet->setCellValue('A2', $dados['periodo']);
    
    if (!empty($dados['registros'])) {
        // Cabeçalhos
        $headers = array_keys($dados['registros'][0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '4', ucfirst(str_replace('_', ' ', $header)));
            $col++;
        }
        
        // Dados
        $row = 5;
        foreach ($dados['registros'] as $registro) {
            $col = 'A';
            foreach ($registro as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
    }
    
    // Configurar cabeçalho HTTP
    $filename = sanitizeFilename($dados['titulo']) . '_' . date('Y-m-d_H-i-s');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Gerar HTML do relatório
 */
function gerarHTMLRelatorio($dados) {
    $html = '<h1>' . $dados['titulo'] . '</h1>';
    $html .= '<p>' . $dados['periodo'] . '</p>';
    
    if (!empty($dados['registros'])) {
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse: collapse;">';
        
        // Cabeçalhos
        $headers = array_keys($dados['registros'][0]);
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th style="background-color: #f5f5f5;">' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Dados
        $html .= '<tbody>';
        foreach ($dados['registros'] as $registro) {
            $html .= '<tr>';
            foreach ($registro as $value) {
                $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
    } else {
        $html .= '<p>Nenhum registro encontrado para o período selecionado.</p>';
    }
    
    $html .= '<p style="margin-top: 30px; font-size: 10px; color: #666;">Relatório gerado em: ' . date('d/m/Y H:i:s') . '</p>';
    
    return $html;
}

/**
 * Limpar nome do arquivo
 */
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

?>
