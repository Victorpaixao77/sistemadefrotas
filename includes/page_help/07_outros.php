<?php
/** Mapa GPS, Rotas motoristas, Abastecimentos motoristas, Configurações, BI */
return [
'mapa_frota' => [
    'title' => 'Mapa GPS da Frota',
    'visao_geral' => '<p>Visualização em tempo real (ou última posição conhecida) dos veículos com rastreador GPS. Permite acompanhar viagens, consultar histórico de trajeto e identificar veículos offline.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Mapa interativo</strong> com marcadores por placa.',
        '<strong>Lista lateral</strong> de veículos, status e horário da última posição.',
        '<strong>Busca por placa</strong> e filtro online/offline.',
        '<strong>Histórico de rota</strong> por período (quando disponível).',
        '<strong>Cercas geográficas</strong> configuradas em Configurações → GPS.',
        '<strong>Painel de ajuda</strong> integrado ao botão ? no topo.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Posição em tempo real', 'sub' => ['Marcador vermelho = última posição.', 'Use busca, motorista ou Veículo (tempo real) e clique em Pesquisar.', 'Marque Atualizar tempo real (30 s) para renovar automaticamente.']],
        ['text' => 'Histórico de trajeto', 'sub' => ['Linha azul = percurso no período.', 'Selecione Veículo (rota), defina Início/Fim ou use atalhos 6 h / 24 h / 3 dias.', 'Clique em Carregar rota.']],
        ['text' => 'Opções e exportação', 'sub' => ['Botão Opções: cercas no mapa e lista de alertas.', 'Exportar gera CSV da situação filtrada.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Veículos online</strong> — sinal nos últimos minutos.',
        '<strong>Offline</strong> — sem atualização há X horas.',
        '<strong>Em movimento</strong> vs parados.',
        '<strong>Alertas de cerca</strong> no dia.',
    ]),
    'graficos' => sf_hlp_ul([
        'O mapa é a visualização principal; detalhes do veículo podem incluir KM percorrido no período.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#3b82f6', 'label' => 'Azul', 'desc' => 'Histórico de rota (trajeto percorrido)'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Última posição em tempo real'],
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Online — sinal recente'],
        ['color' => '#6b7280', 'label' => 'Cinza', 'desc' => 'Sem sinal / offline'],
    ]),
    'dicas' => sf_hlp_ul([
        'Configure cercas em postos autorizados e pátios da empresa.',
        'Cruze com Rotas para validar se o veículo seguiu o trajeto planejado.',
    ]),
],
'rotas_motoristas' => [
    'title' => 'Rotas Pendentes (motoristas)',
    'visao_geral' => '<p>Fila de aprovação das rotas enviadas pelos motoristas pelo app ou portal. O gestor revisa origem, destino, valores e despesas antes de aprovar ou rejeitar. Complementa o módulo Rotas com foco no fluxo pendente → aceita/rejeitada.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>KPIs:</strong> pendentes, aceitas, rejeitadas e total.',
        '<strong>Filtros</strong> por status, motorista, veículo e busca rápida.',
        '<strong>Detalhe da rota</strong> com paradas, despesas de viagem e comprovantes.',
        '<strong>Aprovar / rejeitar</strong> com motivo visível ao motorista.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Monitorar viagens ativas', 'sub' => ['Filtre status Em andamento.', 'Abra o detalhe para conferir KM e horários.']],
        ['text' => 'Conferir fechamento', 'sub' => ['Rotas concluídas devem ter KM final e despesas.', 'Pendências aparecem no dashboard.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Rotas ativas agora.</strong>',
        '<strong>Concluídas hoje / no período.</strong>',
        '<strong>Atrasadas</strong> (fora do prazo).',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Distribuição por status</strong> no período filtrado.',
    ]),
    'dicas' => sf_hlp_ul([
        'Use junto com Mapa GPS para posição ao vivo.',
        'Rotas sem fechamento impedem comissão e lucratividade corretas.',
    ]),
],
'abastecimentos_motoristas' => [
    'title' => 'Abastecimentos (app motorista)',
    'visao_geral' => '<p>Abastecimentos registrados pelo motorista no aplicativo: foto do cupom, litros, valor e hodômetro. Fluxo de aprovação pelo gestor antes de integrar ao módulo principal.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Fila de lançamentos mobile</strong> com foto e KM.',
        '<strong>Status:</strong> pendente, aprovado, rejeitado.',
        '<strong>Sincronização</strong> com Abastecimentos (gestor).',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Revisar pendências', 'sub' => ['Abra cada registro e valide foto legível.', 'Confira KM coerente com a rota.']],
        ['text' => 'Aprovar ou rejeitar', 'sub' => ['Use Gestão de Motoristas → Aprovações se o fluxo estiver centralizado lá.', 'Motivo de rejeição é exibido ao motorista.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Pendentes de aprovação.</strong>',
        '<strong>Litros via app</strong> no mês.',
        '<strong>Taxa de rejeição</strong> (foto ilegível, KM inválido).',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>% abastecimentos via app</strong> vs lançamento manual no escritório.',
    ]),
    'dicas' => sf_hlp_ul([
        'Treine motoristas a fotografar cupom completo e painel (hodômetro).',
        'Aprove no mesmo dia para não atrasar fechamento de consumo.',
    ]),
],
'configuracoes' => [
    'title' => 'Configurações do Sistema',
    'visao_geral' => '<p>Parâmetros globais da empresa divididos em abas: identidade visual, segurança, IA, fiscal, GPS, sistema e gamificação. Alterações aqui afetam todo o comportamento do Frotec para a empresa logada.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Geral:</strong> nome, logo, preferências de interface.',
        '<strong>Segurança:</strong> 2FA, política de senha, timeout de sessão, SMTP, reCAPTCHA, whitelist IP.',
        '<strong>IA & OCR:</strong> modelos OpenAI, Vision para cupons e documentos.',
        '<strong>Fiscal & Multas:</strong> certificado A1, DETRAN, ambiente SEFAZ, RNTRC e <strong>CIOT/PEF ANTT</strong> (card dedicado na aba Fiscal).',
        '<strong>GPS:</strong> cercas e provedor de rastreamento.',
        '<strong>Sistema:</strong> parâmetros técnicos e manutenção.',
        '<strong>Gamificação:</strong> regras de pontuação dos motoristas.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Navegar', 'sub' => ['Use o menu Configuração no sidebar.', 'Cada aba salva independentemente — clique em Salvar ao terminar.']],
        ['text' => 'Segurança', 'sub' => ['Ative 2FA para administradores.', 'Configure SMTP para alertas de novo acesso e recuperação.']],
        ['text' => 'Fiscal / CIOT', 'sub' => ['Na aba Fiscal & Multas configure certificado SEFAZ, RNTRC e o card CIOT — PEF ANTT.', 'Com integração ativa, declare CIOT em Sistema Fiscal → CIOT (PEF ANTT).', 'Use o botão Ajuda na aba Fiscal para o passo a passo completo do PEF.']],
    ]),
    'kpis' => sf_hlp_ul([
        'Esta tela não exibe KPIs — são parâmetros. O impacto aparece nos módulos após salvar.',
    ]),
    'dicas' => sf_hlp_ul([
        'Prefira segredos (API, SMTP) em variáveis de ambiente no servidor.',
        'Após alterar timeout de sessão, usuários serão deslogados pelo tempo novo.',
        'Logo personalizada pode exigir Ctrl+F5 por cache do navegador.',
    ]),
],
'bi' => [
    'title' => 'Business Intelligence (BI)',
    'visao_geral' => '<p>Painéis analíticos avançados com drill-down, comparação de períodos e cruzamento de dados da frota. Voltado a gestores que precisam ir além dos KPIs do dashboard inicial.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Painéis temáticos:</strong> custos, eficiência, manutenção, motoristas, pneus.',
        '<strong>Drill-down:</strong> do total ao detalhe (veículo, rota, dia).',
        '<strong>Filtros globais</strong> de data, veículo e motorista.',
        '<strong>Resumo IA</strong> em painéis selecionados.',
        '<strong>Exportação</strong> quando disponível no painel.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Explorar', 'sub' => ['Escolha o painel no menu BI.', 'Ajuste período e dimensões.', 'Clique em barras ou fatias para detalhar.']],
        ['text' => 'Comparar períodos', 'sub' => ['Use o mesmo filtro em meses diferentes.', 'Evite comparar mês parcial com mês fechado.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>TCO da frota</strong> (custo total de propriedade).',
        '<strong>Disponibilidade %</strong> (uptime).',
        '<strong>Custo por km</strong> consolidado.',
        '<strong>Margem operacional</strong> quando integrado ao financeiro.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Waterfall, heatmap e tendência</strong> conforme o painel.',
        '<strong>Comparativo ano a ano</strong> em indicadores estratégicos.',
        '<strong>Ranking</strong> de veículos e motoristas por custo ou eficiência.',
    ]),
    'dicas' => sf_hlp_ul([
        'Use o BI em reunião mensal com os mesmos filtros para ver evolução.',
        'Drill-down antes de tomar decisão de venda ou reforma de veículo.',
    ]),
],
];
