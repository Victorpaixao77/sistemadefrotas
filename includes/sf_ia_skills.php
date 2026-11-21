<?php
/**
 * Skills modulares do Copiloto — intenções, tools e permissões por domínio.
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_ia_tools.php';

function sf_ia_skills_catalog(): array
{
    return [
        'financeiro' => [
            'label' => 'Skill Financeiro',
            'icon' => 'fa-coins',
            'permission' => 'can_view_financial_data',
            'intents' => ['faturamento', 'contas_pagar', 'pedagio', 'despesas', 'multas', 'glossario', 'cliente_top', 'rota_top', 'motorista_top'],
            'tools' => ['consultar_banco', 'consultar_financeiro', 'consultar_rotas', 'gerar_pdf', 'exportar_excel'],
        ],
        'frota' => [
            'label' => 'Skill Frota',
            'icon' => 'fa-truck',
            'permission' => null,
            'intents' => ['frota', 'veiculo_top', 'veiculo_parado', 'consumo', 'km', 'pneus', 'implementos', 'abastecimentos'],
            'tools' => ['consultar_banco', 'consultar_veiculos', 'consultar_rotas'],
        ],
        'oficina' => [
            'label' => 'Skill Manutenção',
            'icon' => 'fa-wrench',
            'permission' => null,
            'intents' => ['manutencao', 'previsoes'],
            'tools' => ['consultar_banco', 'consultar_veiculos', 'executar_automacao'],
        ],
        'logistica' => [
            'label' => 'Skill Logística',
            'icon' => 'fa-route',
            'permission' => 'can_view_fleet_map',
            'intents' => ['rotas_ativas', 'rotas_mes', 'rota_top', 'no_prazo', 'atrasos', 'mapa_frota'],
            'tools' => ['consultar_banco', 'consultar_rotas', 'consultar_gps'],
        ],
        'rh' => [
            'label' => 'Skill RH',
            'icon' => 'fa-id-card',
            'permission' => null,
            'intents' => ['cnh', 'motorista_top'],
            'tools' => ['consultar_banco', 'consultar_veiculos'],
        ],
        'comercial' => [
            'label' => 'Skill Comercial',
            'icon' => 'fa-handshake',
            'permission' => null,
            'intents' => ['cliente_top'],
            'tools' => ['consultar_banco', 'consultar_rotas'],
        ],
        'geral' => [
            'label' => 'Skill Copiloto',
            'icon' => 'fa-brain',
            'permission' => null,
            'intents' => ['analise_empresa', 'analise_completa', 'executivo', 'documentos', 'ajuda_sistema', 'glossario'],
            'tools' => ['consultar_banco', 'consultar_documentos', 'narrativa_llm', 'abrir_tela'],
        ],
    ];
}

function sf_ia_skills_for_intent(string $intent): ?array
{
    foreach (sf_ia_skills_catalog() as $id => $skill) {
        if (in_array($intent, $skill['intents'], true)) {
            return array_merge($skill, ['id' => $id]);
        }
    }
    return null;
}

function sf_ia_skills_permitido(string $skillId): bool
{
    $cat = sf_ia_skills_catalog();
    if (!isset($cat[$skillId])) {
        return true;
    }
    $perm = $cat[$skillId]['permission'] ?? null;
    if ($perm === null || !function_exists($perm)) {
        return true;
    }
    return (bool) $perm();
}

function sf_ia_skills_list_ui(): array
{
    $out = [];
    foreach (sf_ia_skills_catalog() as $id => $s) {
        $out[] = [
            'id' => $id,
            'label' => $s['label'],
            'icon' => $s['icon'],
            'intents' => count($s['intents']),
            'tools' => count($s['tools']),
            'ativo' => sf_ia_skills_permitido($id),
        ];
    }
    return $out;
}
