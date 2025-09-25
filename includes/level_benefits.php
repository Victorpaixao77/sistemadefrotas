<?php
/**
 * Sistema de Benefícios por Nível
 * Implementa benefícios e privilégios baseados no nível do motorista
 */

class LevelBenefitsManager {
    
    /**
     * Obter benefícios do nível atual
     */
    public static function getLevelBenefits($nivel) {
        $benefits = [
            'Bronze' => [
                'name' => 'Bronze',
                'icon' => '🥉',
                'color' => '#cd7f32',
                'benefits' => [
                    'Acesso ao sistema básico',
                    'Visualização do ranking',
                    'Participação em desafios simples'
                ],
                'discounts' => [
                    'Combustível' => '0%',
                    'Manutenção' => '0%',
                    'Peças' => '0%'
                ],
                'privileges' => [
                    'Pode visualizar estatísticas básicas',
                    'Pode participar de competições mensais'
                ]
            ],
            'Prata' => [
                'name' => 'Prata',
                'icon' => '🥈',
                'color' => '#c0c0c0',
                'benefits' => [
                    'Todos os benefícios Bronze',
                    'Desconto em combustível',
                    'Acesso a relatórios avançados',
                    'Prioridade no suporte'
                ],
                'discounts' => [
                    'Combustível' => '2%',
                    'Manutenção' => '1%',
                    'Peças' => '1%'
                ],
                'privileges' => [
                    'Pode visualizar estatísticas avançadas',
                    'Pode participar de competições semanais',
                    'Acesso a treinamentos exclusivos'
                ]
            ],
            'Ouro' => [
                'name' => 'Ouro',
                'icon' => '🥇',
                'color' => '#ffd700',
                'benefits' => [
                    'Todos os benefícios Prata',
                    'Descontos maiores',
                    'Acesso a dados exclusivos',
                    'Reconhecimento especial'
                ],
                'discounts' => [
                    'Combustível' => '5%',
                    'Manutenção' => '3%',
                    'Peças' => '3%'
                ],
                'privileges' => [
                    'Pode visualizar todos os dados',
                    'Pode participar de competições diárias',
                    'Acesso a mentorias personalizadas',
                    'Reconhecimento no mural da empresa'
                ]
            ],
            'Platina' => [
                'name' => 'Platina',
                'icon' => '🏆',
                'color' => '#e5e4e2',
                'benefits' => [
                    'Todos os benefícios Ouro',
                    'Descontos premium',
                    'Acesso VIP a eventos',
                    'Consultoria personalizada'
                ],
                'discounts' => [
                    'Combustível' => '8%',
                    'Manutenção' => '5%',
                    'Peças' => '5%'
                ],
                'privileges' => [
                    'Pode sugerir melhorias no sistema',
                    'Pode participar de decisões estratégicas',
                    'Acesso a eventos exclusivos',
                    'Consultoria personalizada mensal'
                ]
            ],
            'Diamante' => [
                'name' => 'Diamante',
                'icon' => '💎',
                'color' => '#b9f2ff',
                'benefits' => [
                    'Todos os benefícios Platina',
                    'Descontos máximos',
                    'Status VIP completo',
                    'Influência nas decisões'
                ],
                'discounts' => [
                    'Combustível' => '12%',
                    'Manutenção' => '8%',
                    'Peças' => '8%'
                ],
                'privileges' => [
                    'Pode influenciar políticas da empresa',
                    'Pode mentorar outros motoristas',
                    'Acesso a eventos de alto nível',
                    'Consultoria estratégica personalizada'
                ]
            ],
            'Lenda' => [
                'name' => 'Lenda',
                'icon' => '👑',
                'color' => '#ff6b35',
                'benefits' => [
                    'Todos os benefícios Diamante',
                    'Descontos exclusivos',
                    'Status lendário',
                    'Influência máxima'
                ],
                'discounts' => [
                    'Combustível' => '15%',
                    'Manutenção' => '10%',
                    'Peças' => '10%'
                ],
                'privileges' => [
                    'Pode definir políticas da empresa',
                    'Pode treinar outros motoristas',
                    'Acesso a eventos lendários',
                    'Consultoria estratégica ilimitada',
                    'Status de embaixador da marca'
                ]
            ]
        ];
        
        return $benefits[$nivel] ?? $benefits['Bronze'];
    }
    
    /**
     * Gerar HTML dos benefícios
     */
    public static function generateBenefitsHTML($nivel) {
        $benefits = self::getLevelBenefits($nivel);
        
        $html = '<div class="level-benefits-container">';
        $html .= '<div class="level-benefits-header">';
        $html .= '<h5>' . $benefits['icon'] . ' Benefícios do Nível ' . $benefits['name'] . '</h5>';
        $html .= '</div>';
        
        $html .= '<div class="row">';
        
        // Benefícios gerais
        $html .= '<div class="col-md-6">';
        $html .= '<h6><i class="fas fa-gift"></i> Benefícios</h6>';
        $html .= '<ul class="benefits-list">';
        foreach ($benefits['benefits'] as $benefit) {
            $html .= '<li><i class="fas fa-check text-success"></i> ' . $benefit . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        
        // Descontos
        $html .= '<div class="col-md-6">';
        $html .= '<h6><i class="fas fa-percentage"></i> Descontos</h6>';
        $html .= '<div class="discounts-list">';
        foreach ($benefits['discounts'] as $item => $discount) {
            $html .= '<div class="discount-item">';
            $html .= '<span class="discount-item-name">' . $item . ':</span>';
            $html .= '<span class="discount-value text-success">' . $discount . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Privilégios
        $html .= '<div class="privileges-section">';
        $html .= '<h6><i class="fas fa-crown"></i> Privilégios Especiais</h6>';
        $html .= '<div class="privileges-grid">';
        foreach ($benefits['privileges'] as $privilege) {
            $html .= '<div class="privilege-item">';
            $html .= '<i class="fas fa-star text-warning"></i> ' . $privilege;
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Calcular economia total baseada no nível
     */
    public static function calculateTotalSavings($nivel, $gastos_mensais = []) {
        $benefits = self::getLevelBenefits($nivel);
        $total_savings = 0;
        
        $default_gastos = [
            'combustivel' => 2000,
            'manutencao' => 500,
            'pecas' => 300
        ];
        
        $gastos = array_merge($default_gastos, $gastos_mensais);
        
        foreach ($benefits['discounts'] as $item => $discount) {
            $item_key = strtolower(str_replace(' ', '_', $item));
            $item_key = str_replace('ç', 'c', $item_key);
            
            if (isset($gastos[$item_key])) {
                $discount_value = floatval(str_replace('%', '', $discount));
                $savings = ($gastos[$item_key] * $discount_value) / 100;
                $total_savings += $savings;
            }
        }
        
        return [
            'total_savings' => round($total_savings, 2),
            'monthly_savings' => round($total_savings, 2),
            'yearly_savings' => round($total_savings * 12, 2),
            'benefits' => $benefits
        ];
    }
    
    /**
     * Obter CSS para os benefícios
     */
    public static function getCSS() {
        return '
        <style>
        .level-benefits-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
        }
        
        .level-benefits-header h5 {
            color: #007bff;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .benefits-list {
            list-style: none;
            padding: 0;
        }
        
        .benefits-list li {
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .benefits-list li:last-child {
            border-bottom: none;
        }
        
        .discounts-list {
            background: white;
            border-radius: 8px;
            padding: 15px;
        }
        
        .discount-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .discount-item:last-child {
            border-bottom: none;
        }
        
        .discount-item-name {
            font-weight: 500;
        }
        
        .discount-value {
            font-weight: bold;
            font-size: 16px;
        }
        
        .privileges-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .privileges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .privilege-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #ffc107;
            font-size: 14px;
        }
        
        .privilege-item i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .privileges-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>';
    }
}
?>
