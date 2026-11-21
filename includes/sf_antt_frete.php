<?php
/**
 * Piso mínimo de frete ANTT (PNPM-TRC, Lei 13.703/2018).
 * Fórmula oficial: Piso = (CCD × distância_km) + CC.
 *
 * Coeficientes versionados por `vigencia` na tabela antt_frete_coeficientes
 * (seed em sql/antt_frete_coeficientes.sql). O cálculo usa sempre a vigência
 * mais recente disponível, então importar uma tabela nova já passa a valer.
 */

require_once __DIR__ . '/db_connect.php';

if (!function_exists('sf_antt_ensure_schema')) {
    function sf_antt_ensure_schema(PDO $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn->exec(
            'CREATE TABLE IF NOT EXISTS antt_frete_coeficientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vigencia DATE NOT NULL,
                tipo_carga VARCHAR(40) NOT NULL,
                eixos TINYINT UNSIGNED NOT NULL,
                ccd DECIMAL(12,4) NOT NULL,
                cc DECIMAL(12,2) NOT NULL,
                contratacao ENUM("COMPOSICAO","TRACAO") NOT NULL DEFAULT "COMPOSICAO",
                alto_desempenho CHAR(1) NOT NULL DEFAULT "N",
                ativo CHAR(1) NOT NULL DEFAULT "S",
                UNIQUE KEY uniq_coef (vigencia, tipo_carga, eixos, contratacao, alto_desempenho)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('sf_antt_tipos_carga')) {
    /** Rótulos legíveis dos tipos de carga (chave => rótulo). */
    function sf_antt_tipos_carga(): array
    {
        return [
            'geral' => 'Carga Geral',
            'granel_solido' => 'Granel sólido',
            'granel_liquido' => 'Granel líquido',
            'frigorificada' => 'Frigorificada',
            'conteinerizada' => 'Conteinerizada',
            'neogranel' => 'Neogranel',
            'granel_pressurizada' => 'Granel pressurizada',
            'perigosa_geral' => 'Perigosa (carga geral)',
            'perigosa_granel_solido' => 'Perigosa (granel sólido)',
            'perigosa_granel_liquido' => 'Perigosa (granel líquido)',
            'perigosa_frigorificada' => 'Perigosa (frigorificada)',
            'perigosa_conteinerizada' => 'Perigosa (conteinerizada)',
        ];
    }
}

if (!function_exists('sf_antt_calc_piso')) {
    /**
     * Calcula o piso mínimo de frete.
     * @return array{piso:float,ccd:float,cc:float,eixos_usado:int,vigencia:string,disponivel:bool}|null
     */
    function sf_antt_calc_piso(PDO $conn, string $tipo_carga, int $eixos, float $distancia, string $contratacao = 'COMPOSICAO', string $alto_desempenho = 'N'): ?array
    {
        sf_antt_ensure_schema($conn);
        $contratacao = ($contratacao === 'TRACAO') ? 'TRACAO' : 'COMPOSICAO';
        $alto = ($alto_desempenho === 'S') ? 'S' : 'N';
        $tipo_carga = preg_replace('/[^a-z_]/', '', strtolower($tipo_carga));
        if ($tipo_carga === '' || $distancia <= 0) {
            return null;
        }

        // Vigência mais recente que tenha esse tipo de carga + contratação + modalidade.
        $stmt = $conn->prepare(
            'SELECT MAX(vigencia) FROM antt_frete_coeficientes
             WHERE tipo_carga = :tc AND contratacao = :ct AND alto_desempenho = :ad AND ativo = "S"'
        );
        $stmt->execute([':tc' => $tipo_carga, ':ct' => $contratacao, ':ad' => $alto]);
        $vigencia = $stmt->fetchColumn();
        if (!$vigencia) {
            return null;
        }

        // Eixos disponíveis nessa vigência (regra ANTT §5: usar o imediatamente
        // inferior; se não houver inferior, o imediatamente superior).
        $stmt = $conn->prepare(
            'SELECT eixos, ccd, cc FROM antt_frete_coeficientes
             WHERE tipo_carga = :tc AND contratacao = :ct AND alto_desempenho = :ad
               AND ativo = "S" AND vigencia = :vg ORDER BY eixos'
        );
        $stmt->execute([':tc' => $tipo_carga, ':ct' => $contratacao, ':ad' => $alto, ':vg' => $vigencia]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return null;
        }

        $escolha = null;
        foreach ($rows as $r) {
            if ((int) $r['eixos'] <= $eixos) {
                $escolha = $r; // mantém o maior <= eixos
            }
        }
        if ($escolha === null) {
            $escolha = $rows[0]; // nenhum inferior: usa o menor disponível (superior)
        }

        $ccd = (float) $escolha['ccd'];
        $cc = (float) $escolha['cc'];
        $piso = ($ccd * $distancia) + $cc;

        return [
            'piso' => round($piso, 2),
            'ccd' => $ccd,
            'cc' => $cc,
            'eixos_usado' => (int) $escolha['eixos'],
            'vigencia' => (string) $vigencia,
            'disponivel' => true,
        ];
    }
}
