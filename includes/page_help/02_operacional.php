<?php
/** Abastecimentos, Manutenções */
return [
'abastecimentos' => [
    'title' => 'Abastecimentos',
    'visao_geral' => '<p>Registro e análise de todos os abastecimentos da frota: litros, valor, posto, forma de pagamento, hodômetro, motorista e veículo. Suporta importação CSV/JSON, OCR de cupom (foto/PDF) e detecção de anomalias de consumo.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Novo abastecimento:</strong> data, veículo, motorista, combustível, litros, R$/L, total, KM, posto e pagamento.',
        '<strong>Importar:</strong> CSV, JSON, foto ou PDF do cupom. Com <code>SF_OPENAI_API_KEY</code> usa OpenAI Vision; sem chave, OCR no navegador.',
        '<strong>Filtros:</strong> mês/ano, veículo, motorista, combustível, forma de pagamento.',
        '<strong>Anomalias:</strong> consumo fora do padrão, litragem suspeita, intervalo curto entre abastecimentos.',
        '<strong>Insights IA:</strong> resumo de eficiência e alertas por veículo/motorista.',
        '<strong>Editar / excluir</strong> registros com auditoria.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Registrar manualmente', 'sub' => ['Clique em Novo Abastecimento.', 'Preencha campos obrigatórios (*).', 'KM deve ser maior ou igual ao último registro do veículo.']],
        ['text' => 'Importar cupom', 'sub' => ['Use Importar → foto/PDF.', 'Revise cada linha extraída e clique Usar.', 'Complete veículo e motorista antes de salvar.']],
        ['text' => 'Analisar consumo', 'sub' => ['Aplique filtro mensal.', 'Compare km/L entre veículos do mesmo tipo.', 'Investigue anomalias em vermelho.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total de abastecimentos</strong> no período.',
        '<strong>Litros consumidos.</strong>',
        '<strong>Valor total gasto</strong> em combustível.',
        '<strong>Média km/L</strong> da frota filtrada.',
        '<strong>Custo por km</strong> (combustível / KM rodado).',
        '<strong>Preço médio por litro.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Consumo mensal</strong> (litros e R$).',
        '<strong>Eficiência km/L</strong> por veículo ou motorista.',
        '<strong>Distribuição por combustível</strong> (diesel S10, S500, arla).',
        '<strong>Evolução do preço médio</strong> do litro.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Consumo dentro da média histórica'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Desvio moderado — merece conferência'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Anomalia crítica (consumo, litros ou intervalo)'],
    ]),
    'dicas' => sf_hlp_ul([
        'Sempre informe hodômetro — sem KM não há cálculo de km/L confiável.',
        'Padronize postos no cadastro de fornecedores para comparar preço por região.',
        'Revise importações OCR antes de salvar em lote.',
        'Cruzamento com rotas identifica abastecimento fora do trajeto.',
    ]),
],
'manutencoes' => [
    'title' => 'Gestão de Manutenções',
    'visao_geral' => '<p>Controle de manutenções preventivas e corretivas dos veículos: serviços, peças, fornecedor, custos, hodômetro, tipo e status. Alimenta MTBF, MTTR, custo/km e planejamento de paradas.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Nova manutenção:</strong> veículo, tipo (preventiva/corretiva), oficina, itens, mão de obra, peças e KM.',
        '<strong>Filtros:</strong> período, veículo, tipo, status, fornecedor.',
        '<strong>Planos de manutenção</strong> (quando configurados): alertas por KM ou tempo.',
        '<strong>Insights IA:</strong> padrões de falha, veículos críticos e previsão de parada.',
        '<strong>Exportação</strong> para planilha ou PDF.',
        '<strong>Anexos:</strong> notas e ordens de serviço.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Registrar OS', 'sub' => ['Clique em Nova Manutenção.', 'Informe KM de entrada e descrição detalhada.', 'Lance peças e mão de obra separadamente.']],
        ['text' => 'Preventiva programada', 'sub' => ['Consulte alertas de plano por veículo.', 'Agende antes de atingir KM limite.']],
        ['text' => 'Fechar manutenção', 'sub' => ['Altere status para Concluída.', 'Registre KM de saída se aplicável.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total de manutenções</strong> no período.',
        '<strong>Preventivas vs corretivas:</strong> ideal ter mais preventivas.',
        '<strong>MTBF:</strong> tempo médio entre falhas.',
        '<strong>MTTR:</strong> tempo médio de reparo.',
        '<strong>Custo total</strong> e <strong>custo por km.</strong>',
        '<strong>Veículos em manutenção</strong> agora.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Manutenções por mês</strong> e por tipo.',
        '<strong>Custo por veículo</strong> — ranking.',
        '<strong>Componentes mais trocados.</strong>',
        '<strong>Tempo parado</strong> (horas/dias).',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#3b82f6', 'label' => 'Azul', 'desc' => 'Preventiva programada'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Em andamento na oficina'],
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Concluída'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Corretiva urgente / veículo parado'],
    ]),
    'dicas' => sf_hlp_ul([
        'Descrição pobre na OS prejudica análise de recorrência de falhas.',
        'Vincule sempre o fornecedor correto para negociar pacotes.',
        'MTBF baixo em um veículo específico indica problema crônico — avalie venda ou reforma.',
    ]),
],
];
