<?php
/** Relatórios, BI, Configurações */
return [
'relatorios' => [
    'title' => 'Relatórios',
    'visao_geral' => '<p>Central de relatórios gerenciais exportáveis: frota, financeiro, combustível, manutenção, motoristas e pneus. Permite agendar envio automático quando configurado.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Catálogo de relatórios</strong> por área.',
        '<strong>Filtros de período</strong> e parâmetros específicos.',
        '<strong>Exportação PDF/Excel.</strong>',
        '<strong>Relatórios automáticos</strong> por e-mail (cron).',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Gerar', 'sub' => ['Escolha o relatório.', 'Defina período e filtros.', 'Clique Gerar / Exportar.']],
    ]),
    'kpis' => sf_hlp_ul([
        'KPIs variam por relatório selecionado — cada template documenta seus campos na pré-visualização.',
    ]),
    'graficos' => sf_hlp_ul([
        'Relatórios gráficos incluem consumo, custos e utilização conforme o tipo escolhido.',
    ]),
    'dicas' => sf_hlp_ul([
        'Prefira período fechado (mês completo) para envio à diretoria.',
        'Agende relatórios recorrentes para reduzir trabalho manual.',
    ]),
],
'bi' => [
    'title' => 'Business Intelligence (BI)',
    'visao_geral' => '<p>Painéis analíticos avançados com drill-down, comparação de períodos e cruzamento de dados da frota. Voltado a gestores que precisam ir além dos KPIs do dashboard.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Painéis temáticos:</strong> custos, eficiência, manutenção, motoristas.',
        '<strong>Drill-down:</strong> do agregado ao detalhe (veículo, rota, dia).',
        '<strong>Filtros globais</strong> de data e dimensão.',
        '<strong>Resumo IA</strong> em painéis selecionados.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Explorar', 'sub' => ['Selecione painel no menu BI.', 'Ajuste período.', 'Clique em barras/fatias para detalhar.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>TCO frota</strong> (custo total de propriedade).',
        '<strong>Disponibilidade %</strong> (uptime).',
        '<strong>Custo/km consolidado.</strong>',
        '<strong>Margem operacional.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Waterfall, heatmap, tendência</strong> conforme painel.',
        '<strong>Comparativo ano a ano.</strong>',
    ]),
    'dicas' => sf_hlp_ul([
        'Use BI em reunião mensal com mesmos filtros para comparar evolução.',
    ]),
],
'configuracoes' => [
    'title' => 'Configurações',
    'visao_geral' => '<p>Parâmetros globais da empresa no sistema, divididos em abas: Geral, Segurança, IA & OCR, Fiscal & Multas, GPS, Sistema e Gamificação.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Geral:</strong> nome, logo, preferências de interface.',
        '<strong>Segurança:</strong> 2FA, política de senha, timeout, SMTP, reCAPTCHA, whitelist IP.',
        '<strong>IA & OCR:</strong> chaves OpenAI, modelos, Vision para cupons.',
        '<strong>Fiscal & Multas:</strong> integrações e alertas.',
        '<strong>GPS:</strong> cercas e provedor de rastreamento.',
        '<strong>Sistema:</strong> parâmetros técnicos e manutenção.',
        '<strong>Gamificação:</strong> regras de pontuação de motoristas.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Navegar abas', 'sub' => ['Use o menu lateral Configuração.', 'Salve cada aba separadamente.']],
        ['text' => 'Segurança', 'sub' => ['Ative 2FA para administradores.', 'Configure SMTP para alertas de novo acesso.']],
    ]),
    'kpis' => sf_hlp_ul([
        'Não exibe KPIs — tela de parâmetros. Impacto aparece nos módulos após salvar.',
    ]),
    'dicas' => sf_hlp_ul([
        'Segredos (API keys) preferencialmente via variáveis de ambiente no servidor.',
        'Após mudar timeout de sessão, todos os usuários serão deslogados no tempo novo.',
    ]),
],
];
