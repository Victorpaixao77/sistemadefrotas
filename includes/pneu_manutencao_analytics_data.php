<?php
/**
 * Dados analíticos de manutenção de pneus (compartilhado entre API e SSR).
 */

if (!function_exists('pneu_manutencao_fetch_analytics')) {
  function pneu_manutencao_fetch_analytics(PDO $conn, int $empresa_id, ?int $mes = null, ?int $ano = null): array
  {
    $where_conditions = ['m.empresa_id = :empresa_id'];
    $params = [':empresa_id' => $empresa_id];

    if ($mes && $ano) {
      $where_conditions[] = 'MONTH(m.data_manutencao) = :mes AND YEAR(m.data_manutencao) = :ano';
      $params[':mes'] = $mes;
      $params[':ano'] = $ano;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    $bind = static function (PDOStatement $stmt, array $params): void {
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
      }
    };

    $sql_custo_mensal = "SELECT
        DATE_FORMAT(m.data_manutencao, '%Y-%m') as mes,
        COALESCE(SUM(m.custo), 0) as total_custo
        FROM pneu_manutencao m
        $where_clause
        GROUP BY DATE_FORMAT(m.data_manutencao, '%Y-%m')
        ORDER BY mes DESC
        LIMIT 12";
    $stmt = $conn->prepare($sql_custo_mensal);
    $bind($stmt, $params);
    $stmt->execute();
    $custo_mensal = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_quantidade_tipo = "SELECT
        COALESCE(t.nome, 'Não especificado') as tipo,
        COUNT(*) as quantidade
        FROM pneu_manutencao m
        LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
        $where_clause
        GROUP BY t.nome
        ORDER BY quantidade DESC";
    $stmt = $conn->prepare($sql_quantidade_tipo);
    $bind($stmt, $params);
    $stmt->execute();
    $quantidade_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_top_pneus = "SELECT
        COALESCE(p.numero_serie, 'Não especificado') as pneu,
        COALESCE(SUM(m.custo), 0) as total_custo
        FROM pneu_manutencao m
        LEFT JOIN pneus p ON m.pneu_id = p.id
        $where_clause
        GROUP BY p.numero_serie
        ORDER BY total_custo DESC
        LIMIT 5";
    $stmt = $conn->prepare($sql_top_pneus);
    $bind($stmt, $params);
    $stmt->execute();
    $top_pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_media_tipo = "SELECT
        COALESCE(t.nome, 'Não especificado') as tipo,
        COALESCE(AVG(m.custo), 0) as media_custo
        FROM pneu_manutencao m
        LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
        $where_clause
        GROUP BY t.nome
        ORDER BY media_custo DESC";
    $stmt = $conn->prepare($sql_media_tipo);
    $bind($stmt, $params);
    $stmt->execute();
    $media_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
      'success' => true,
      'custo_mensal' => $custo_mensal,
      'quantidade_tipo' => $quantidade_tipo,
      'top_pneus' => $top_pneus,
      'media_tipo' => $media_tipo,
    ];
  }
}
