<?php
/**
 * Dados de listagem de rotas aprovadas (página de rotas).
 */
function getRotas(int $page = 1, int $per_page = 10): array
{
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = in_array($per_page, [5, 10, 25, 50, 100], true) ? $per_page : 10;
        $offset = ($page - 1) * $limit;

        $sql_count = "SELECT COUNT(*) as total FROM rotas WHERE empresa_id = :empresa_id AND status = 'aprovado'";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = "SELECT r.*, v.placa as veiculo_placa, m.nome as motorista_nome,
                co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :empresa_id
                AND r.status = 'aprovado'
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rotas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch (PDOException $e) {
        error_log("Erro ao buscar rotas: " . $e->getMessage());
        return [
            'rotas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}
