<?php
/**
 * Intervalo de datas dos gráficos do dashboard (alinhado ao filtro ?period=).
 */
require_once __DIR__ . '/dashboard_home_metrics.php';

if (!function_exists('sf_dashboard_analytics_context')) {

    /**
     * @return array{period:string,d1:string,d2:string,label:string,bucket:string}
     */
    function sf_dashboard_analytics_context(): array
    {
        $period = dashboard_home_normalize_period($_GET['period'] ?? 'year');
        [$d1, $d2, , , $label] = dashboard_home_period_ranges($period);

        if ($period === 'all') {
            $d2 = date('Y-m-d');
            $d1 = date('Y-m-01', strtotime('-11 months'));
            $label = 'Últimos 12 meses';
            $bucket = 'month';
        } elseif ($period === 'month') {
            $bucket = 'day';
        } else {
            $bucket = 'month';
        }

        if ($d1 === null || $d2 === null) {
            $d2 = date('Y-m-d');
            $d1 = date('Y-01-01');
        }

        return [
            'period' => $period,
            'd1' => $d1,
            'd2' => $d2,
            'label' => $label,
            'bucket' => $bucket,
        ];
    }

    /**
     * Gera séries vazias e rótulos para o intervalo.
     *
     * @return array{labels:array<int,string>,keys:array<int,string>}
     */
    function sf_dashboard_analytics_series(string $d1, string $d2, string $bucket): array
    {
        $labels = [];
        $keys = [];
        $tz = new DateTimeZone(date_default_timezone_get() ?: 'America/Sao_Paulo');
        $start = new DateTime($d1, $tz);
        $end = new DateTime($d2, $tz);

        if ($bucket === 'day') {
            $cur = clone $start;
            while ($cur <= $end) {
                $k = $cur->format('Y-m-d');
                $keys[] = $k;
                $labels[] = $cur->format('d/m');
                $cur->modify('+1 day');
            }
            return ['labels' => $labels, 'keys' => $keys];
        }

        $cur = (clone $start)->modify('first day of this month');
        $endM = (clone $end)->modify('first day of this month');
        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        while ($cur <= $endM) {
            $k = $cur->format('Y-m');
            $keys[] = $k;
            $labels[] = $meses[(int) $cur->format('n')] . '/' . $cur->format('y');
            $cur->modify('+1 month');
        }
        return ['labels' => $labels, 'keys' => $keys];
    }

    function sf_dashboard_analytics_bucket_key(string $date, string $bucket): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }
        return $bucket === 'day' ? date('Y-m-d', $ts) : date('Y-m', $ts);
    }
}
