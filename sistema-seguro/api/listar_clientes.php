<?php
/**
 * API - Listar Clientes
 * Retorna lista de clientes com paginação e filtros
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

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Parâmetros de filtro
    $busca = $_GET['busca'] ?? '';
    $situacao = $_GET['situacao'] ?? 'ativo'; // ativo, inativo, todos
    $todas = $_GET['todas'] ?? 'false'; // Se deve retornar TODAS ou com limite
    
    // Se 'todas=true', não aplicar limite
    if ($todas === 'true') {
        $pagina = 1;
        $porPagina = PHP_INT_MAX; // Sem limite
        $offset = 0;
    } else {
        $pagina = max(1, intval($_GET['pagina'] ?? 1));
        $porPagina = max(5, min(100, intval($_GET['porPagina'] ?? 10)));
        $offset = ($pagina - 1) * $porPagina;
    }
    
    // Montar query base
    $where = ["c.seguro_empresa_id = ?"];
    $params = [$empresa_id];
    
    // Filtro de situação
    if ($situacao !== 'todos') {
        $where[] = "c.situacao = ?";
        $params[] = $situacao;
    }
    
    // Filtro de busca
    if (!empty($busca)) {
        $where[] = "(
            c.codigo LIKE ? OR
            c.cpf_cnpj LIKE ? OR
            c.nome_razao_social LIKE ? OR
            c.sigla_fantasia LIKE ? OR
            c.bairro LIKE ? OR
            c.cidade LIKE ? OR
            c.uf LIKE ? OR
            e.unidade LIKE ?
        )";
        $buscaTerm = "%$busca%";
        for ($i = 0; $i < 8; $i++) {
            $params[] = $buscaTerm;
        }
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total de registros
    $stmtCount = $db->prepare("
        SELECT COUNT(*) as total 
        FROM seguro_clientes c
        INNER JOIN seguro_empresa_clientes e ON c.seguro_empresa_id = e.id
        WHERE $whereClause
    ");
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    $totalPaginas = $todas === 'true' ? 1 : ceil($totalRegistros / $porPagina);
    
    // Buscar clientes com unidade da empresa e soma das porcentagens dos contratos
    $sql = "
        SELECT 
            c.id,
            c.codigo,
            c.identificador,
            c.tipo_pessoa,
            c.cpf_cnpj,
            c.nome_razao_social,
            c.sigla_fantasia,
            c.bairro,
            c.cidade,
            c.uf,
            e.unidade,
            c.matricula,
            c.porcentagem_recorrencia,
            c.situacao,
            DATE_FORMAT(c.data_cadastro, '%d/%m/%Y %H:%i') as data_cadastro_formatada,
            COALESCE(
                (SELECT SUM(cc.porcentagem_recorrencia) 
                 FROM seguro_contratos_clientes cc 
                 WHERE cc.cliente_id = c.id 
                   AND cc.empresa_id = c.seguro_empresa_id
                   AND cc.ativo = 'sim'),
                0
            ) as porcentagem_total_contratos
        FROM seguro_clientes c
        INNER JOIN seguro_empresa_clientes e ON c.seguro_empresa_id = e.id
        WHERE $whereClause
        ORDER BY c.nome_razao_social ASC
    ";
    
    // Aplicar LIMIT apenas se não for "todas"
    if ($todas !== 'true') {
        $sql .= " LIMIT $porPagina OFFSET $offset";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
    
    echo json_encode([
        'sucesso' => true,
        'clientes' => $clientes,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'por_pagina' => $porPagina,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'inicio' => $offset + 1,
            'fim' => min($offset + $porPagina, $totalRegistros)
        ],
        'filtros' => [
            'busca' => $busca,
            'situacao' => $situacao
        ]
    ]);
    
} catch(PDOException $e) {
    error_log("Erro ao listar clientes: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao carregar clientes'
    ]);
}
?>

