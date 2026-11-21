<?php

/**
 * Ajuda das telas novas do fluxo TMS (Centro de Operações, Ordens de Transporte,
 * Clientes, Implementos, Aprovações, Planejamento, Central de Alertas e Preditiva).
 */

declare(strict_types=1);

return [
    'centro_operacoes' => [
        'title' => 'Centro de Operações',
        'visao_geral' => '<p>Torre de controle da operação em <strong>tempo real</strong>: em uma tela você acompanha frota, viagens, alertas, mapa GPS, financeiro do mês corrente, rankings e pendências. Diferente do <strong>Dashboard</strong> (histórico com filtro de período), aqui os dados refletem o <strong>mês atual</strong> e a situação <strong>agora</strong>, com atualização automática a cada 60 segundos.</p><p>Todos os números vêm do banco de dados da sua empresa — onde não há registro, a tela mostra zero ou estado vazio, nunca valores fictícios.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Barra de status</strong> no topo: Sistema (online), GPS (veículos com posição recente), e — se você tiver acesso fiscal — CT-e e MDF-e com contagem de rascunhos/pendências.',
            '<strong>Atualizar</strong> (ícone de rotação): recarrega os dados na hora. O indicador “Tempo real” mostra a hora da última leitura.',
            '<strong>Ações rápidas:</strong> atalhos para Nova Viagem, Nova Ordem, Emitir CT-e/MDF-e (só com permissão fiscal), Abastecimento, Mapa GPS completo e Central de Alertas.',
            '<strong>Resumo automático:</strong> frases em linguagem natural sobre a operação do dia (viagens, frota, alertas), geradas a partir dos dados reais.',
            '<strong>KPIs executivos:</strong> receita, lucro, km rodados, consumo médio, disponibilidade da frota, ocupação e viagens em andamento. Valores em R$ só aparecem para quem tem permissão financeira.',
            '<strong>Mapa da frota:</strong> posições GPS recentes (app do motorista). Verde = rodando, amarelo = parado, cinza = sem sinal há mais de 2 h. Link “Abrir completo” leva ao mapa detalhado.',
            '<strong>Situação operacional:</strong> tiles clicáveis — Disponíveis, Rodando, Programadas, Manutenção, Atrasadas e Inativos — cada um abre a tela correspondente.',
            '<strong>Financeiro do mês:</strong> receita, lucro, contas a receber/pagar, combustível, pedágio, manutenção e margem. Oculto ou bloqueado sem permissão financeira.',
            '<strong>Gráficos (30 dias):</strong> faturamento diário e consumo (km/L) — faturamento só com permissão financeira.',
            '<strong>Rankings:</strong> top motoristas (km ou km/L no mês) e top veículos por lucro.',
            '<strong>Coluna lateral:</strong> alertas críticos e de atenção, eventos ao vivo (timeline), próximos vencimentos (CNH, documentos, etc.) e pendências (CT-e, MDF-e, ordens, aprovações, abastecimentos, manutenções).',
            '<strong>Link “Ver histórico no Dashboard”</strong> no topo: análise com filtro de período (mês, trimestre, ano).',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Comece pela barra de status', 'sub' => ['Verde = ok; amarelo = atenção; vermelho = problema ou offline.', 'GPS verde exige posição nas últimas 2 horas.']],
            ['text' => 'Leia o resumo automático e os KPIs', 'sub' => ['Identifique rapidamente viagens em andamento e disponibilidade da frota.']],
            ['text' => 'Use o mapa e os tiles de situação', 'sub' => ['Clique nos tiles para ir direto a veículos, rotas, planejamento ou manutenções.']],
            ['text' => 'Resolva pendências na coluna direita', 'sub' => ['Alertas, vencimentos e pendências são clicáveis e levam à tela de origem.']],
            ['text' => 'Ações rápidas', 'sub' => ['Crie viagem, ordem ou documento fiscal sem sair do fluxo operacional.']],
        ]),
        'kpis' => sf_hlp_ul([
            '<strong>Receita / Lucro / Lucro por km</strong> — mês corrente (rotas aprovadas). Requer permissão financeira.',
            '<strong>KM rodados</strong> — total de quilometragem das viagens do mês.',
            '<strong>Consumo médio</strong> — km/L calculado com abastecimentos aprovados do mês.',
            '<strong>Disponibilidade</strong> — % de veículos ativos em relação ao total da frota.',
            '<strong>Ocupação</strong> — % de veículos em viagem agora.',
            '<strong>Em andamento</strong> — rotas aprovadas com saída já ocorrida e ainda não encerradas.',
        ]),
        'graficos' => sf_hlp_ul([
            '<strong>Faturamento (30d):</strong> evolução diária do frete das rotas aprovadas. Visível apenas com permissão financeira.',
            '<strong>Consumo (30d):</strong> média de km/L por dia, com base em abastecimentos e km das rotas.',
        ]),
        'dicas' => sf_hlp_ul([
            'A página atualiza sozinha a cada 60 segundos, mas só enquanto a aba do navegador estiver visível.',
            'Use o botão de atualizar ao lado se precisar de dados imediatos após uma ação.',
            'Para análise histórica (trimestre, ano inteiro), use o Dashboard pelo link no topo da página.',
            'Sem permissão financeira, você ainda vê operação, mapa, alertas e consumo — apenas valores em R$ ficam ocultos.',
            'CT-e, MDF-e e pendências fiscais só aparecem para usuários com acesso ao módulo fiscal.',
        ]),
        'legenda' => sf_hlp_legenda([
            ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Sistema online, GPS ativo, veículo rodando ou situação ok.'],
            ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Atenção: GPS parado, alertas moderados ou vencimento próximo.'],
            ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Crítico: GPS offline, alerta grave ou documento vencido.'],
            ['color' => '#94a3b8', 'label' => 'Cinza', 'desc' => 'Sem sinal GPS recente no mapa (mais de 2 h sem posição).'],
        ]),
    ],

    'ordens_transporte' => [
        'title' => 'Ordens de Transporte',
        'visao_geral' => '<p>Fluxo comercial do frete: Pedido → Cotação → Ordem de Transporte → Rota. A OT aprovada <strong>gera a rota</strong> existente, sem retrabalho, e pode emitir o CT-e já pré-preenchido.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Cadastro da OT:</strong> cliente, trecho, carga, distância, frete e custo.',
            '<strong>Piso ANTT</strong> pelo Simulador de Rota.',
            '<strong>Status:</strong> cotação, aprovada, em viagem, concluída, cancelada.',
            '<strong>Gerar rota</strong> e <strong>emitir CT-e</strong> a partir da OT.',
            '<strong>Link de rastreio</strong> para o cliente acompanhar.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Nova OT', 'sub' => ['Preencha cliente, trecho e frete.', 'Salve como cotação.']],
            ['text' => 'Aprovar e planejar', 'sub' => ['Aprove a cotação.', 'Atribua veículo/motorista e gere a rota.']],
            ['text' => 'Faturar', 'sub' => ['Com a OT aprovada/em viagem, emita o CT-e.']],
        ]),
        'dicas' => sf_hlp_ul([
            'Vincule um cliente do cadastro para preencher os dados automaticamente.',
            'Use a busca e o filtro de status para achar uma OT rapidamente.',
        ]),
    ],

    'clientes' => [
        'title' => 'Clientes',
        'visao_geral' => '<p>Cadastro de clientes/embarcadores (tomadores de frete). Integra com as Ordens de Transporte — a coluna "OTs" mostra quantas ordens cada cliente já teve.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Cadastro PJ/PF</strong> com contato, endereço e documento.',
            '<strong>Status:</strong> ativo, prospect, inativo.',
            '<strong>Busca</strong> por nome/CNPJ e filtro por status.',
            '<strong>Contador de OTs</strong> por cliente.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Novo cliente', 'sub' => ['Informe razão social/nome e, se houver, CNPJ.', 'Salve.']],
            ['text' => 'Use na OT', 'sub' => ['Selecione o cliente ao abrir uma Ordem de Transporte.']],
        ]),
        'dicas' => sf_hlp_ul([
            'Prospects viram ativos automaticamente ao fechar a primeira OT — mantenha o status atualizado.',
        ]),
    ],

    'implementos' => [
        'title' => 'Implementos',
        'visao_geral' => '<p>Gestão dos implementos (carretas, reboques, dollys, pranchas) como ativos próprios — placa, documentação e vencimentos, separados do cavalo mecânico.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Cadastro</strong> com tipo, eixos, capacidade e documentos (RENAVAM, RNTRC).',
            '<strong>Vínculo</strong> com o cavalo mecânico.',
            '<strong>Vencimentos</strong> de licenciamento e seguro com alerta de prazo.',
            '<strong>Busca</strong> e filtro por status.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Novo implemento', 'sub' => ['Informe placa e tipo.', 'Preencha os vencimentos para receber alertas.']],
        ]),
        'dicas' => sf_hlp_ul([
            'Datas de licenciamento/seguro vencidas aparecem em vermelho e alimentam a Central de Alertas.',
        ]),
    ],

    'aprovacoes' => [
        'title' => 'Aprovações',
        'visao_geral' => '<p>Fila de aprovação das Ordens de Transporte em cotação. Toda decisão fica registrada com autor, data e comentário (trilha de auditoria).</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Pendentes:</strong> aprovar ou rejeitar cada OT.',
            '<strong>Histórico:</strong> quem decidiu, quando e por quê.',
            '<strong>Comentário</strong> opcional em cada decisão.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Revise a OT pendente (trecho, frete, veículo).'],
            ['text' => 'Aprove ou rejeite', 'sub' => ['Rejeitar cancela a OT.', 'O comentário fica no histórico.']],
        ]),
        'dicas' => sf_hlp_ul([
            'OTs aprovadas seguem para o Planejamento para atribuição de veículo/motorista.',
        ]),
    ],

    'planejamento' => [
        'title' => 'Planejamento',
        'visao_geral' => '<p>Quadro das ordens aprovadas aguardando veículo/motorista. Atribua os recursos e gere a rota direto daqui.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>KPIs</strong> de OTs a planejar, KM, peso, frete e veículos livres.',
            '<strong>Atribuição</strong> de veículo e motorista por OT.',
            '<strong>Gerar rota</strong> em um clique.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Escolha veículo e motorista disponíveis para a OT.'],
            ['text' => 'Defina a data de coleta e gere a rota.'],
        ]),
        'dicas' => sf_hlp_ul([
            'Veículos em viagem aparecem marcados — evite dupla alocação.',
        ]),
    ],

    'central_alertas' => [
        'title' => 'Central de Alertas',
        'visao_geral' => '<p>Todos os alertas do sistema num só lugar — CNH, documentos, multas, pneus e GPS. Clique para resolver na tela de origem.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Filtros</strong> por categoria e por nível (críticos).',
            '<strong>Busca</strong> textual no alerta.',
            '<strong>Priorização:</strong> críticos primeiro.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Filtre por categoria ou "Críticos".'],
            ['text' => 'Clique no alerta para ir direto ao cadastro que precisa de ação.'],
        ]),
        'dicas' => sf_hlp_ul([
            'A tela recarrega a cada 2 minutos; "Tudo em dia" significa nenhum alerta no filtro atual.',
        ]),
    ],

    'manutencao_preditiva' => [
        'title' => 'Manutenção Preditiva',
        'visao_geral' => '<p>Previsão de quando revisar cada veículo, com dados reais: KM desde a última revisão + KM/dia médio (rotas dos últimos 90 dias). O intervalo vem do histórico do próprio veículo; sem histórico, usa um intervalo padrão (indicado).</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Resumo:</strong> atrasadas, próximas, em dia e sem dados.',
            '<strong>Previsão</strong> em KM e dias por veículo.',
            '<strong>Atualizar KM da frota</strong> a partir de rotas/abastecimentos.',
            '<strong>Busca</strong> e filtro por status.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Clique em "Atualizar KM da frota" para puxar o KM mais recente.'],
            ['text' => 'Priorize os veículos "Atrasada" e "Próxima".'],
        ]),
        'dicas' => sf_hlp_ul([
            'Cadastre o KM da última manutenção nos veículos para previsões mais precisas.',
        ]),
    ],
    'assistente_ia' => [
        'title' => 'Centro de Inteligência',
        'visao_geral' => '<p>O "ChatGPT da sua operação": pergunte em linguagem natural e receba respostas com <strong>dado real</strong> da sua empresa. Reúne chat, insights, previsões, leitura de documentos, automações e auditoria. <strong>Segurança:</strong> a IA nunca roda SQL — ela classifica a pergunta numa intenção pré-definida e o backend executa a consulta parametrizada (somente leitura, filtrada pela sua empresa). Ações que alteram algo exigem sua confirmação.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Chat</strong> — pergunte "quantas rotas ativas tenho no mês?", "gasto com manutenção", "motorista que mais faturou".',
            '<strong>Insights</strong> — alertas e recomendações automáticas da operação.',
            '<strong>Previsões</strong> — projeções (ex.: manutenção, custos).',
            '<strong>Documentos</strong> — leitura/OCR por IA de comprovantes e notas.',
            '<strong>Automações</strong> — rotinas com IA, sempre com aprovação.',
            '<strong>Auditoria</strong> — trilha do que a IA consultou/sugeriu.',
            '<strong>Aprendizado</strong> (só admin) — mapeia perguntas não entendidas para intenções.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Perguntar no Chat', 'sub' => [
                'Digite em linguagem natural (ex.: "faturamento no mês passado").',
                'Se não disser o período, a IA usa os últimos 12 meses.',
                'Follow-up de período funciona: "e em 2024?" reaproveita a última pergunta.',
            ]],
            ['text' => 'Explorar as seções', 'sub' => [
                'Use as abas (Insights, Previsões, Documentos, Automações, Auditoria).',
                'Ações que mudam dados sempre pedem sua confirmação antes.',
            ]],
            ['text' => 'Ensinar a IA (admin)', 'sub' => [
                'Na aba Aprendizado, mapeie perguntas não entendidas para a intenção certa.',
            ]],
        ]),
        'fluxo' => sf_hlp_ol([
            '<strong>Você pergunta</strong> — em linguagem natural, sem precisar saber telas ou SQL.',
            '<strong>Classificação</strong> — a IA (ou o matcher por palavra-chave) escolhe a intenção e o período.',
            '<strong>Consulta segura</strong> — o backend roda a query parametrizada, só leitura, filtrada pela sua empresa.',
            '<strong>Resposta</strong> — com dado real (e gráfico quando faz sentido); perguntas não entendidas viram fila de Aprendizado.',
        ]),
        'dicas' => sf_hlp_ul([
            'Seja específico no período ("primeiro trimestre de 2025", "últimos 3 meses").',
            'A resposta indica quando foi interpretada por IA (🧠).',
            'Se algo não for entendido, avise o admin — dá para mapear na aba Aprendizado.',
        ]),
    ],
    'ia_painel' => [
        'title' => 'Painel Inteligente (IA)',
        'visao_geral' => '<p>Painel analítico com IA voltado à frota: reúne, em abas, a visão geral, alertas inteligentes, recomendações, previsões e a situação da frota. Usa os dados reais da sua empresa para antecipar problemas (manutenção, custos, riscos) e sugerir ações.</p>',
        'funcionalidades' => sf_hlp_ul([
            '<strong>Visão geral</strong> — indicadores consolidados da operação.',
            '<strong>Alertas</strong> — pontos de atenção detectados automaticamente.',
            '<strong>Recomendações</strong> — sugestões de ação baseadas nos dados.',
            '<strong>Previsões</strong> — projeções (manutenção, custos, tendências).',
            '<strong>Frota</strong> — situação por veículo.',
        ]),
        'como_usar' => sf_hlp_ol([
            ['text' => 'Escolher a aba', 'sub' => [
                'Use as abas no topo (Visão geral, Alertas, Recomendações, Previsões, Frota).',
                'A aba escolhida é lembrada ao reabrir.',
            ]],
            ['text' => 'Agir sobre um alerta/recomendação', 'sub' => [
                'Leia o alerta e vá à tela de origem (manutenção, abastecimentos, etc.).',
            ]],
        ]),
        'fluxo' => sf_hlp_ol([
            '<strong>Dados da operação</strong> — rotas, abastecimentos, manutenções e multas alimentam a análise.',
            '<strong>Painel Inteligente (aqui)</strong> — a IA cruza esses dados e gera alertas, recomendações e previsões.',
            '<strong>Ação</strong> — cada alerta leva à tela de origem para resolver.',
            '<strong>Conversa</strong> — para perguntas livres, use o <em>Centro de Inteligência</em> (assistente).',
        ]),
        'dicas' => sf_hlp_ul([
            'É preciso ter ao menos um veículo cadastrado para o painel funcionar.',
            'Mantenha rotas e abastecimentos em dia — a qualidade da IA depende do dado.',
        ]),
    ],
];
