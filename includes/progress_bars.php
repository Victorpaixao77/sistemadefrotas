<?php
/**
 * Sistema de Barras de Progresso para Níveis
 * Implementa barras de progresso visuais para gamificação
 */

class ProgressBarManager {
    
    /**
     * Calcular progresso para próximo nível
     */
    public static function calculateLevelProgress($pontos_atuais, $nivel_atual) {
        $niveis = [
            'Bronze' => ['min' => 0, 'max' => 99],
            'Prata' => ['min' => 100, 'max' => 299],
            'Ouro' => ['min' => 300, 'max' => 599],
            'Platina' => ['min' => 600, 'max' => 899],
            'Diamante' => ['min' => 900, 'max' => 999],
            'Lenda' => ['min' => 1000, 'max' => 9999]
        ];
        
        if (!isset($niveis[$nivel_atual])) {
            return [
                'progress' => 0,
                'current_points' => $pontos_atuais,
                'next_level_points' => 100,
                'next_level' => 'Prata',
                'is_max_level' => false
            ];
        }
        
        $current_level = $niveis[$nivel_atual];
        $next_level_name = self::getNextLevel($nivel_atual);
        $next_level = $niveis[$next_level_name] ?? null;
        
        if (!$next_level) {
            // Nível máximo alcançado
            return [
                'progress' => 100,
                'current_points' => $pontos_atuais,
                'next_level_points' => $current_level['max'],
                'next_level' => $nivel_atual,
                'is_max_level' => true
            ];
        }
        
        $points_in_current_level = $pontos_atuais - $current_level['min'];
        $points_needed_for_next = $next_level['min'] - $current_level['min'];
        $progress = min(100, ($points_in_current_level / $points_needed_for_next) * 100);
        
        return [
            'progress' => round($progress, 1),
            'current_points' => $pontos_atuais,
            'next_level_points' => $next_level['min'],
            'next_level' => $next_level_name,
            'is_max_level' => false
        ];
    }
    
    /**
     * Obter próximo nível
     */
    private static function getNextLevel($current_level) {
        $level_order = ['Bronze', 'Prata', 'Ouro', 'Platina', 'Diamante', 'Lenda'];
        $current_index = array_search($current_level, $level_order);
        
        if ($current_index === false || $current_index >= count($level_order) - 1) {
            return null; // Nível máximo
        }
        
        return $level_order[$current_index + 1];
    }
    
    /**
     * Gerar HTML da barra de progresso
     */
    public static function generateProgressBar($pontos_atuais, $nivel_atual, $show_details = true) {
        $progress_data = self::calculateLevelProgress($pontos_atuais, $nivel_atual);
        
        $progress_color = self::getProgressColor($nivel_atual);
        $progress_icon = self::getLevelIcon($nivel_atual);
        $next_icon = self::getLevelIcon($progress_data['next_level']);
        
        $html = '<div class="level-progress-container">';
        
        if ($show_details) {
            $html .= '<div class="level-progress-header">';
            $html .= '<span class="current-level">' . $progress_icon . ' ' . $nivel_atual . '</span>';
            $html .= '<span class="level-points">' . $pontos_atuais . ' pontos</span>';
            $html .= '</div>';
        }
        
        $html .= '<div class="progress level-progress-bar">';
        $html .= '<div class="progress-bar ' . $progress_color . '" role="progressbar" ';
        $html .= 'style="width: ' . $progress_data['progress'] . '%" ';
        $html .= 'aria-valuenow="' . $progress_data['progress'] . '" ';
        $html .= 'aria-valuemin="0" aria-valuemax="100">';
        $html .= '<span class="progress-text">' . $progress_data['progress'] . '%</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        if ($show_details && !$progress_data['is_max_level']) {
            $html .= '<div class="level-progress-footer">';
            $html .= '<small class="text-muted">';
            $html .= 'Próximo: ' . $next_icon . ' ' . $progress_data['next_level'] . ' (' . $progress_data['next_level_points'] . ' pts)';
            $html .= '</small>';
            $html .= '</div>';
        } elseif ($progress_data['is_max_level']) {
            $html .= '<div class="level-progress-footer">';
            $html .= '<small class="text-success">';
            $html .= '🏆 Nível máximo alcançado!';
            $html .= '</small>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Obter cor da barra de progresso baseada no nível
     */
    private static function getProgressColor($nivel) {
        $colors = [
            'Bronze' => 'bg-warning',
            'Prata' => 'bg-secondary',
            'Ouro' => 'bg-warning',
            'Platina' => 'bg-info',
            'Diamante' => 'bg-primary',
            'Lenda' => 'bg-danger'
        ];
        
        return $colors[$nivel] ?? 'bg-secondary';
    }
    
    /**
     * Obter ícone do nível
     */
    private static function getLevelIcon($nivel) {
        $icons = [
            'Bronze' => '🥉',
            'Prata' => '🥈',
            'Ouro' => '🥇',
            'Platina' => '🏆',
            'Diamante' => '💎',
            'Lenda' => '👑'
        ];
        
        return $icons[$nivel] ?? '🥉';
    }
    
    /**
     * Gerar CSS para as barras de progresso
     */
    public static function getCSS() {
        return '
        <style>
        .level-progress-container {
            margin: 10px 0;
        }
        
        .level-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .current-level {
            font-weight: bold;
            font-size: 14px;
        }
        
        .level-points {
            font-size: 12px;
            color: #6c757d;
        }
        
        .level-progress-bar {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .level-progress-bar .progress-bar {
            border-radius: 10px;
            transition: width 0.6s ease;
            position: relative;
        }
        
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .level-progress-footer {
            margin-top: 5px;
            text-align: center;
        }
        
        .level-progress-bar .progress-bar.bg-warning {
            background: linear-gradient(45deg, #ffc107, #ff8f00);
        }
        
        .level-progress-bar .progress-bar.bg-secondary {
            background: linear-gradient(45deg, #6c757d, #495057);
        }
        
        .level-progress-bar .progress-bar.bg-info {
            background: linear-gradient(45deg, #17a2b8, #138496);
        }
        
        .level-progress-bar .progress-bar.bg-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
        }
        
        .level-progress-bar .progress-bar.bg-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }
        </style>';
    }
}
?>
