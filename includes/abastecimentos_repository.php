<?php
/**
 * Lista abastecimentos aprovados paginados (página de listagem).
 */
function getAbastecimentos(int $page = 1, int $per_page = 10): array
{
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = in_array($per_page, [5, 10, 25, 50, 100], true) ? $per_page : 10;
        $offset = ($page - 1) * $limit;

        $sql_count = "SELECT COUNT(*) as total FROM abastecimentos WHERE empresa_id = :empresa_id AND status = 'aprovado'";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

        $sql = "SELECT 
                a.*,
                v.placa as veiculo_placa,
                m.nome as motorista_nome,
                r.id as rota_id,
                co.nome as cidade_origem_nome,
                cd.nome as cidade_destino_nome
                FROM abastecimentos a
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                LEFT JOIN motoristas m ON a.motorista_id = m.id
                LEFT JOIN rotas r ON a.rota_id = r.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado'
                ORDER BY a.data_abastecimento DESC, a.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'abastecimentos' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch (PDOException $e) {
        error_log("Erro ao buscar abastecimentos: " . $e->getMessage());
        return [
            'abastecimentos' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}
