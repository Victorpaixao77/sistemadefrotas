<?php
/** Gestão de Motoristas (menu) */
return [
'gestao_motoristas' => [
    'title' => 'Aprovações (Gestão de Motoristas)',
    'visao_geral' => '<p>Central de aprovação de lançamentos feitos pelo motorista no app: abastecimentos, despesas de viagem, checklists e fotos. Garante conformidade antes de ir para o financeiro.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Fila de aprovação:</strong> itens pendentes por tipo e data.',
        '<strong>Aprovar / rejeitar</strong> com motivo.',
        '<strong>Visualizar comprovantes</strong> e fotos.',
        '<strong>Filtro por motorista</strong> e veículo.',
        '<strong>Histórico</strong> de decisões.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Revisar pendência', 'sub' => ['Abra o item e confira foto, valor e KM.', 'Compare com política da empresa.']],
        ['text' => 'Aprovar', 'sub' => ['Confirma lançamento nos módulos financeiros/abastecimento.']],
        ['text' => 'Rejeitar', 'sub' => ['Informe motivo claro — motorista vê no app.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Pendentes hoje.</strong>',
        '<strong>Aprovados / rejeitados</strong> no período.',
        '<strong>Tempo médio</strong> de aprovação.',
        '<strong>Top motoristas</strong> com mais rejeições.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Volume por tipo</strong> (abastecimento, despesa, checklist).',
        '<strong>Taxa de aprovação</strong> semanal.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Aguardando aprovação'],
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Aprovado'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Rejeitado'],
    ]),
    'dicas' => sf_hlp_ul([
        'Aprove no mesmo dia para não atrasar fechamento de comissão.',
        'Padronize motivos de rejeição para treinar a equipe.',
    ]),
],
'checklists' => [
    'title' => 'Checklists',
    'visao_geral' => '<p>Modelos e respostas de checklists de saída/chegada: itens de segurança, equipamentos, pneus, documentos. Preenchidos pelo motorista no app e auditados aqui.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Modelos de checklist</strong> configuráveis.',
        '<strong>Respostas por viagem</strong> com data e motorista.',
        '<strong>Fotos de não conformidade.</strong>',
        '<strong>Filtros</strong> e exportação.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Criar modelo', 'sub' => ['Liste itens obrigatórios.', 'Marque quais exigem foto se falha.']],
        ['text' => 'Auditar respostas', 'sub' => ['Filtre por data.', 'Investigue itens marcados como NOK.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Checklists realizados</strong> no período.',
        '<strong>Taxa de conformidade %.</strong>',
        '<strong>Itens mais reprovados.</strong>',
        '<strong>Motoristas sem checklist</strong> em viagem.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Conformidade ao longo do tempo.</strong>',
        '<strong>Top falhas</strong> por item.',
    ]),
    'dicas' => sf_hlp_ul([
        'Checklist incompleto deve bloquear início de rota (política + app).',
        'Use fotos de pneus e freios como evidência em sinistro.',
    ]),
],
'rotas_motoristas' => [
    'title' => 'Rotas (Motoristas)',
    'visao_geral' => '<p>Visão das rotas atribuídas aos motoristas: status em tempo real, início/fim, KM e integração com app. Foco operacional (não planejamento comercial — ver menu Rotas).</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Lista de rotas</strong> por motorista e status.',
        '<strong>Detalhe da viagem:</strong> paradas, despesas, checklist.',
        '<strong>Acompanhamento</strong> de viagem em andamento.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Monitorar viagem', 'sub' => ['Filtre Em andamento.', 'Abra detalhe para ver última posição GPS se disponível.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Rotas ativas agora.</strong>',
        '<strong>Concluídas hoje.</strong>',
        '<strong>Atrasadas</strong> (fora do prazo previsto).',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Rotas por status.</strong>',
        '<strong>Desempenho de pontualidade.</strong>',
    ]),
    'dicas' => sf_hlp_ul([
        'Cruze com Mapa GPS para posição ao vivo.',
    ]),
],
'abastecimentos_motoristas' => [
    'title' => 'Abastecimentos (Motoristas)',
    'visao_geral' => '<p>Abastecimentos lançados pelo motorista via app, aguardando ou já aprovados. Complementa o módulo principal de Abastecimentos com foco na origem mobile.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Fila mobile:</strong> cupom foto, KM, litros.',
        '<strong>Status de aprovação.</strong>',
        '<strong>Sincronização</strong> com módulo gestor.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Conferir lançamento', 'sub' => ['Valide foto legível e KM coerente.', 'Aprove em Gestão de Motoristas se pendente.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Pendentes de aprovação.</strong>',
        '<strong>Litros via app</strong> no mês.',
        '<strong>Rejeições por foto ilegível.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>% abastecimentos via app</strong> vs manual.',
    ]),
    'dicas' => sf_hlp_ul([
        'Treine motoristas a fotografar cupom completo e hodômetro.',
    ]),
],
'mapa_frota' => [
    'title' => 'Mapa GPS',
    'visao_geral' => '<p>Localização em tempo real (ou última posição) dos veículos com rastreador. Cercas, histórico de trajeto e status de comunicação com o equipamento.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Mapa interativo</strong> com marcadores por placa.',
        '<strong>Lista lateral</strong> de veículos e última atualização.',
        '<strong>Cercas geográficas</strong> (quando configuradas em Configurações → GPS).',
        '<strong>Histórico de rota</strong> por período.',
        '<strong>Filtro</strong> por veículo ou status online/offline.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Localizar veículo', 'sub' => ['Busque pela placa na lista.', 'Clique no marcador para detalhes.']],
        ['text' => 'Ver histórico', 'sub' => ['Selecione veículo e intervalo de datas.', 'Reproduza trajeto no mapa.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Veículos online</strong> (sinal recente).',
        '<strong>Offline há mais de X horas.</strong>',
        '<strong>Alertas de cerca</strong> no dia.',
    ]),
    'graficos' => sf_hlp_ul([
        'Mapa principal é a visualização — gráficos auxiliares de KM percorrido podem aparecer no detalhe do veículo.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Online — posição atualizada'],
        ['color' => '#6b7280', 'label' => 'Cinza', 'desc' => 'Sem sinal / offline'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Alerta de cerca ou velocidade'],
    ]),
    'dicas' => sf_hlp_ul([
        'Offline prolongado pode indicar falha no rastreador ou área sem cobertura.',
        'Configure cercas em pontos de risco (posto autorizado, pátio).',
    ]),
],
];
