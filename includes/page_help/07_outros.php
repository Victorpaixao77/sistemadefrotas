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
'usuarios' => [
    'title' => 'Usuários',
    'visao_geral' => '<p>Gerencia os usuários da sua empresa: gestores e operadores do sistema web e os motoristas do app. Controla nível de acesso (permissões), status ativo/inativo e a segurança da conta (2FA).</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Criar/editar usuário</strong> do sistema ou do app do motorista.',
        '<strong>Permissões</strong> por nível (admin, gestor, visualização).',
        '<strong>2FA (autenticação em 2 fatores)</strong> por QR Code, por usuário.',
        '<strong>Status</strong> ativo/inativo e redefinição de senha.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Criar um usuário', 'sub' => [
            'Clique em Novo usuário e informe nome e e-mail.',
            'Escolha o tipo (sistema ou motorista) e o nível de acesso.',
            'Salve e oriente a pessoa a definir a senha pelo fluxo de redefinição.',
        ]],
        ['text' => 'Ativar 2FA de um usuário', 'sub' => [
            'Abra o usuário e use o botão de 2FA (ícone QR).',
            'A pessoa escaneia o QR no app autenticador e confirma o código de 6 dígitos.',
        ]],
        ['text' => 'Bloquear/desativar acesso', 'sub' => [
            'Edite o usuário e mude o status para inativo.',
        ]],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Usuários (aqui)</strong> — você cria a conta e define o nível de acesso.',
        '<strong>Acesso</strong> — o usuário entra no sistema web (gestor) ou no app (motorista) com login e, se ativo, 2FA.',
        '<strong>Operação</strong> — as permissões controlam o que cada um vê/faz nas telas.',
        '<strong>Auditoria</strong> — logins e tentativas ficam registrados em <em>Log de acessos</em>.',
    ]),
    'dicas' => sf_hlp_ul([
        'Nunca compartilhe senha por chat — use o fluxo de redefinição.',
        'Exija 2FA para contas com acesso a dados financeiros/fiscais.',
        'Revise periodicamente usuários inativos e remova acessos desnecessários.',
    ]),
],
'planos_manutencao' => [
    'title' => 'Planos de Manutenção',
    'visao_geral' => '<p>Define os planos de manutenção preventiva da frota — os intervalos (por km rodado e/ou tempo) em que cada tipo de serviço deve ser feito. É a base que alimenta os alertas e a manutenção preditiva.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Criar/editar plano</strong> com intervalos por km e por período.',
        '<strong>Tipos de serviço</strong> (troca de óleo, revisão, filtros, etc.).',
        '<strong>Vínculo com a frota</strong> para gerar alertas de vencimento.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Criar um plano', 'sub' => [
            'Clique em Novo plano.',
            'Defina o serviço e o intervalo (ex.: a cada 10.000 km ou 6 meses).',
            'Salve — o plano passa a valer para o cálculo de vencimentos.',
        ]],
        ['text' => 'Acompanhar', 'sub' => [
            'Os veículos que se aproximam do intervalo aparecem como alerta.',
            'Use a Manutenção Preditiva para ver a projeção por veículo.',
        ]],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Planos de Manutenção (aqui)</strong> — você define os intervalos preventivos.',
        '<strong>Operação</strong> — os veículos rodam e o km é atualizado (app/rotas).',
        '<strong>Alerta</strong> — ao se aproximar do intervalo, entra em <em>Manutenção Preditiva</em> e na Central de Alertas.',
        '<strong>Execução</strong> — a manutenção é registrada em <em>Manutenções</em> (OS + custos), fechando o ciclo.',
    ]),
    'dicas' => sf_hlp_ul([
        'Mantenha o km dos veículos atualizado para os alertas serem confiáveis.',
        'Combine intervalo por km e por tempo para veículos que rodam pouco.',
    ]),
],
'painel_executivo' => [
    'title' => 'Painel Executivo',
    'visao_geral' => '<p>Visão estratégica da frota para a diretoria (CEO/gestor): em uma tela, os KPIs que importam — faturamento, lucro, margem, custo/km, ticket médio e frota ativa — mais a tendência de faturamento × lucro por mês e os rankings de motoristas, veículos e rotas. Diferente do Dashboard operacional, aqui o foco é decisão de negócio.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>KPIs estratégicos:</strong> faturamento, lucro (com margem), custo/km, km rodado, ticket médio, frota ativa/total.',
        '<strong>Composição de custo:</strong> combustível e manutenção do período.',
        '<strong>Gráfico</strong> faturamento × lucro por mês.',
        '<strong>Rankings</strong> top motoristas, veículos e rotas por faturamento.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher o período', 'sub' => ['Ajuste De/Até (padrão: últimos 12 meses) e clique em Aplicar.']],
        ['text' => 'Ler a saúde do negócio', 'sub' => ['Comece por faturamento, lucro e margem; depois custo/km e ticket médio.']],
        ['text' => 'Agir', 'sub' => ['Use os rankings para reconhecer os melhores e investigar os piores.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Operação</strong> — rotas, abastecimentos e manutenções geram receita e custo.',
        '<strong>Painel Executivo (aqui)</strong> — o sistema consolida tudo em KPIs de negócio.',
        '<strong>Decisão</strong> — margem/custo-km orientam preço de frete, compra de veículos e corte de custo.',
    ]),
    'dicas' => sf_hlp_ul([
        'Lucro = faturamento − (combustível + manutenção + comissão) do período.',
        'Mantenha os abastecimentos limpos — dados de teste inflam o custo e distorcem a margem.',
    ]),
],
'scores' => [
    'title' => 'Scores · Motoristas, Veículos e Clientes',
    'visao_geral' => '<p>Uma nota de 0 a 100 para cada motorista, veículo e cliente, calculada a partir de dados reais. Ajuda a reconhecer os melhores, priorizar treinamento/manutenção e identificar clientes mais (ou menos) vantajosos.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Score do motorista:</strong> pontualidade (rotas no prazo) + multas.',
        '<strong>Score do veículo:</strong> uso (rotas) + poucas manutenções.',
        '<strong>Score do cliente:</strong> faturamento + recorrência (nº de OTs) + margem.',
        '<strong>Faixas:</strong> Excelente, Bom, Regular e Atenção, com ranking.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher a aba e o período', 'sub' => ['Alterne entre Motoristas, Veículos e Clientes; ajuste De/Até e Aplicar.']],
        ['text' => 'Ler o ranking', 'sub' => ['Notas verdes (Excelente/Bom) no topo; vermelhas (Atenção) exigem ação.']],
        ['text' => 'Agir', 'sub' => ['Motorista baixo → treinamento; veículo baixo → revisar manutenção; cliente baixo → renegociar frete.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Dados reais</strong> — rotas, multas, manutenções e ordens de transporte alimentam o cálculo.',
        '<strong>Scores (aqui)</strong> — cada entidade recebe uma nota transparente (fórmula conhecida).',
        '<strong>Ação</strong> — reconhecimento, treinamento, manutenção ou renegociação conforme o score.',
    ]),
    'dicas' => sf_hlp_ul([
        'As fórmulas são simples e transparentes — a nota reflete só o que está registrado no sistema.',
        'Mantenha "no prazo" e multas atualizados para o score do motorista ser confiável.',
    ]),
],
'agenda_inteligente' => [
    'title' => 'Agenda Inteligente',
    'visao_geral' => '<p>Reúne, num só lugar, todos os vencimentos e compromissos da frota — CNH, documentos, licenciamento, seguro, pneus e manutenções — organizados por urgência (vencidos, próximos 7 dias, este mês, depois). Evita perder prazo e tomar multa. Usa a mesma base da Central de Alertas.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Resumo</strong> de vencidos, próximos 7 dias e do mês.',
        '<strong>Agenda por urgência</strong> com data, título e link para a tela de origem.',
        '<strong>Filtro por categoria</strong> (CNH, documentos, pneus, implementos, etc.).',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Ver o que vence', 'sub' => ['Comece pelos "Vencidos" e "Próximos 7 dias".']],
        ['text' => 'Filtrar', 'sub' => ['Clique numa categoria para focar (ex.: só CNH).']],
        ['text' => 'Resolver', 'sub' => ['Clique no item para ir direto à tela onde se renova/atualiza.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Cadastros</strong> — datas de CNH, licenciamento, seguro e pneus vêm de Motoristas, Veículos, Implementos e Pneus.',
        '<strong>Agenda (aqui)</strong> — o sistema calcula os prazos e agrupa por urgência.',
        '<strong>Ação</strong> — cada item leva à tela de origem para renovar antes de vencer.',
    ]),
    'dicas' => sf_hlp_ul([
        'Mantenha as datas de vencimento cadastradas — a agenda só mostra o que existe no sistema.',
        'Trate primeiro os itens críticos (vermelhos) para não rodar com documento vencido.',
    ]),
],
'ia_decisao' => [
    'title' => 'IA de Decisão · Consultor Estratégico',
    'visao_geral' => '<p>Um consultor que analisa seus dados reais (frota, motoristas, clientes e finanças dos últimos 12 meses) e devolve <strong>recomendações de ação</strong> — não só números. Responde perguntas de dono: qual veículo trocar, qual cliente dá prejuízo, quem promover, quem treinar e se vale expandir a frota. <strong>Sem achismo e sem inventar:</strong> cada sugestão vem com os fatos que a embasam.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Cartões de decisão</strong> com pergunta, veredito, fatos e ação sugerida.',
        '<strong>Trocar/vender veículo</strong> — pior relação manutenção × uso.',
        '<strong>Veículos ociosos</strong> — parados há mais de 60 dias.',
        '<strong>Promover / treinar motorista</strong> — melhor e pior score.',
        '<strong>Cliente no prejuízo</strong> — margem negativa nas ordens.',
        '<strong>Expandir a frota?</strong> — veredito por margem e utilização.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Ler os cartões', 'sub' => ['Cada cartão traz a pergunta, o veredito e os fatos que o justificam.', 'A cor indica a urgência (vermelho = atenção alta).']],
        ['text' => 'Agir', 'sub' => ['Use "Abrir tela" para ir direto onde se resolve (manutenção, motoristas, clientes, simulador).']],
        ['text' => 'Recalcular', 'sub' => ['Depois de agir, clique em Recalcular para ver a situação atualizada.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Dados reais</strong> — rotas, manutenções, multas, abastecimentos e ordens alimentam a análise.',
        '<strong>IA de Decisão (aqui)</strong> — regras transparentes cruzam esses dados e recomendam a ação.',
        '<strong>Ação</strong> — cada cartão leva à tela onde a decisão é executada.',
        '<strong>Complemento:</strong> para perguntas livres use o <em>Centro de Inteligência</em>; para as notas, os <em>Scores</em>.',
    ]),
    'dicas' => sf_hlp_ul([
        'As recomendações só aparecem quando há um achado real — sem cartão = tudo saudável.',
        'Mantenha os dados limpos (ex.: abastecimentos de teste distorcem a margem/expansão).',
        'É apoio à decisão, não ordem — o veredito é do gestor; a IA embasa.',
    ]),
],
'veiculo_timeline' => [
    'title' => 'Timeline do Veículo',
    'visao_geral' => '<p>O histórico completo de um veículo em uma linha do tempo única: rotas, abastecimentos, manutenções e multas, em ordem cronológica. Ótimo para investigar um veículo (o que aconteceu e quando) sem abrir cinco telas diferentes.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Linha do tempo</strong> unificada por veículo (evento mais recente no topo).',
        '<strong>Resumo</strong> por tipo (rotas, abastecimentos, manutenções, multas).',
        '<strong>Filtro por tipo</strong> de evento.',
        '<strong>Cada evento</strong> mostra data, detalhe, valor e leva à tela de origem.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher o veículo', 'sub' => ['Selecione a placa no topo — a timeline carrega na hora.']],
        ['text' => 'Filtrar', 'sub' => ['Use os chips (Rotas, Abastecimentos, Manutenções, Multas) para focar.']],
        ['text' => 'Aprofundar', 'sub' => ['Clique num evento para abrir a tela de origem (ex.: a manutenção).']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Operação</strong> — cada rota, abastecimento, manutenção e multa é registrada nas telas do dia a dia.',
        '<strong>Timeline (aqui)</strong> — o sistema junta tudo do veículo em ordem cronológica.',
        '<strong>Análise</strong> — investigue padrões (ex.: multa sempre no mesmo trecho, manutenção recorrente).',
    ]),
    'dicas' => sf_hlp_ul([
        'Use antes de decidir vender/trocar um veículo — o histórico conta a real.',
        'Eventos sem data não aparecem — mantenha as datas preenchidas nos lançamentos.',
    ]),
],
'simulacao_crescimento' => [
    'title' => 'Simulação de Crescimento',
    'visao_geral' => '<p>Responde "e se eu expandir?": informe quantos caminhões comprar (e o investimento), quantos motoristas contratar e o lucro esperado por veículo, e veja na hora o <strong>lucro adicional, o ROI ao ano e o payback</strong>. A base já vem das médias reais da sua operação (últimos 12 meses) — você só ajusta as premissas.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Sliders</strong> de novos caminhões, investimento por caminhão, lucro/veículo e motoristas.',
        '<strong>Projeção ao vivo:</strong> investimento, lucro adicional (mês/ano), ROI e payback.',
        '<strong>Veredito</strong> automático (vale a pena ou não) com base no ROI.',
        '<strong>Base real</strong> pré-carregada (faturamento e margem atuais).',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Definir o cenário', 'sub' => ['Arraste "novos caminhões" e informe o investimento por caminhão.', 'Ajuste o lucro médio por veículo/mês (vem da sua base — corrija se houver dado de teste).']],
        ['text' => 'Incluir motoristas', 'sub' => ['Informe quantos contratar e o custo mensal de cada um.']],
        ['text' => 'Ler a projeção', 'sub' => ['Veja ROI e payback; o veredito diz se compensa.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Operação real</strong> — o sistema calcula suas médias (faturamento/veículo, margem).',
        '<strong>Simulação (aqui)</strong> — você projeta o impacto de expandir sobre essa base.',
        '<strong>Decisão</strong> — leve o ROI/payback para a reunião de investimento (complementa o <em>Painel Executivo</em> e a <em>IA de Decisão</em>).',
    ]),
    'dicas' => sf_hlp_ul([
        'É uma projeção — quanto mais fiéis as premissas, melhor a estimativa.',
        'Se a base vier distorcida por dados de teste, ajuste o "lucro por veículo/mês" manualmente.',
    ]),
],
'sla_operacional' => [
    'title' => 'SLA Operacional',
    'visao_geral' => '<p>Mede o nível de serviço das suas viagens: o <strong>% de entregas no prazo</strong>, quantas atrasaram, o tempo médio de viagem e a evolução mês a mês — além do ranking de motoristas por SLA. Serve para acompanhar a promessa de prazo ao cliente e cobrar melhoria.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>SLA %</strong> (rotas no prazo) do período, com cor por faixa.',
        '<strong>Viagens</strong>, no prazo, atrasadas e tempo médio de viagem.',
        '<strong>Gráfico</strong> da evolução do SLA por mês.',
        '<strong>Ranking</strong> de motoristas por SLA.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher o período', 'sub' => ['Use o botão "Período" (padrão: últimos 12 meses).']],
        ['text' => 'Ler o SLA', 'sub' => ['Verde ≥ 90%, amarelo 75–89%, vermelho abaixo.']],
        ['text' => 'Agir', 'sub' => ['Veja quais motoristas puxam o SLA para baixo e trate (rota, planejamento, treinamento).']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Rotas</strong> — cada viagem registra saída, chegada e se foi no prazo.',
        '<strong>SLA (aqui)</strong> — o sistema calcula o % no prazo e o tempo médio.',
        '<strong>Melhoria</strong> — atue nos motoristas/trechos com pior SLA; acompanhe a evolução no gráfico.',
    ]),
    'dicas' => sf_hlp_ul([
        'O SLA depende do campo "no prazo" das rotas estar correto — feche as viagens com KM e datas.',
        'Combine com os <strong>Scores</strong> (que também usam pontualidade) e a <strong>IA de Decisão</strong>.',
    ]),
],
'previsao_financeira' => [
    'title' => 'Previsão Financeira',
    'visao_geral' => '<p>Projeta faturamento, custo e lucro dos <strong>próximos 3 meses</strong> a partir da tendência real do seu histórico (regressão linear sobre os últimos meses). Serve para antecipar caixa e planejar — não é bola de cristal: quanto mais consistente o histórico, melhor a estimativa.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Gráfico</strong> com histórico (linha cheia) e projeção (linha tracejada) de faturamento e lucro.',
        '<strong>Cartões</strong> com o previsto para cada um dos próximos 3 meses.',
        '<strong>Base real</strong> — rotas, abastecimentos e manutenções por mês.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Ler a tendência', 'sub' => ['A parte tracejada é a projeção; compare a inclinação (subindo/caindo).']],
        ['text' => 'Planejar', 'sub' => ['Use o lucro previsto para antecipar caixa e decisões de investimento.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Histórico real</strong> — o sistema monta a série mensal de faturamento e custo.',
        '<strong>Previsão (aqui)</strong> — projeta os próximos meses pela tendência.',
        '<strong>Ação</strong> — combine com o <em>Painel Executivo</em> e a <em>Simulação de Crescimento</em> para decidir.',
    ]),
    'dicas' => sf_hlp_ul([
        'Precisa de pelo menos 3 meses de histórico para projetar.',
        'Dados de teste distorcem a projeção — limpe-os para números realistas.',
    ]),
],
'qr_codes' => [
    'title' => 'QR Codes',
    'visao_geral' => '<p>Gera e imprime <strong>QR Codes</strong> para veículos, pneus, implementos e motoristas. Cole a etiqueta no ativo; ao escanear com o celular, abre direto a tela do item no sistema. Agiliza inspeção, conferência e localização.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>QR por tipo:</strong> veículos, pneus, implementos, motoristas.',
        '<strong>Impressão</strong> em folha (grade de etiquetas).',
        '<strong>Busca</strong> para filtrar os itens.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher o tipo', 'sub' => ['Selecione (veículos, pneus, etc.) — os QR são gerados na hora.']],
        ['text' => 'Imprimir', 'sub' => ['Clique em Imprimir e cole as etiquetas nos ativos.']],
        ['text' => 'Usar', 'sub' => ['Escaneie com o celular para abrir a tela do item.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Cadastro</strong> — veículos/pneus/etc. já cadastrados no sistema.',
        '<strong>QR Codes (aqui)</strong> — gera a etiqueta que aponta para a tela do item.',
        '<strong>Campo</strong> — escaneou, abriu a ficha/timeline; ótimo para o pátio e a oficina.',
    ]),
    'dicas' => sf_hlp_ul([
        'Imprima em etiqueta adesiva resistente para colar no ativo.',
        'O QR abre a tela do item — quem escaneia precisa estar logado no sistema.',
    ]),
],
'metas' => [
    'title' => 'Metas',
    'visao_geral' => '<p>Defina metas (faturamento, nº de rotas, km ou SLA) por mês — para a empresa toda, um motorista ou um veículo — e acompanhe o <strong>realizado calculado do dado real</strong>, com barra de progresso e %. Ótimo para dar direção à equipe e cobrar resultado.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Criar meta</strong> por tipo (geral/motorista/veículo), indicador e mês.',
        '<strong>Realizado automático</strong> — o sistema calcula das rotas do período.',
        '<strong>Progresso</strong> visual (barra + %) por meta; excluir quando quiser.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Criar', 'sub' => ['Clique em "Nova meta", escolha tipo/indicador/mês e o valor-alvo, e salve.']],
        ['text' => 'Acompanhar', 'sub' => ['A barra mostra o realizado vs a meta; verde = bateu.']],
        ['text' => 'Gerir', 'sub' => ['Exclua metas antigas; crie novas a cada mês.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Você define</strong> — a meta (ex.: faturar R$ 50 mil no mês).',
        '<strong>Operação</strong> — as rotas do período alimentam o realizado automaticamente.',
        '<strong>Acompanhamento</strong> — a barra atualiza; combine com <em>Scores</em> e <em>SLA</em> para gestão de time.',
    ]),
    'dicas' => sf_hlp_ul([
        'O realizado vem das rotas — feche as viagens com frete/KM/no prazo corretos.',
        'Crie metas por motorista para engajar e reconhecer quem bate.',
    ]),
],
'contratos' => [
    'title' => 'Contratos',
    'visao_geral' => '<p>Centraliza todos os contratos da operação — clientes, fornecedores, seguros, locações, financiamentos, leasing e prestadores — com valor, vigência e <strong>alerta de vencimento</strong>. Evita perder renovação de seguro/contrato e dá visão do que está por vencer.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Cadastro</strong> de contrato (tipo, contraparte, valor, início, vencimento).',
        '<strong>Resumo:</strong> ativos, vencendo em 30 dias, vencidos e valor total.',
        '<strong>Situação por cor</strong> (em dia / vence em Xd / vencido).',
        '<strong>Busca e filtro</strong> por tipo; excluir contratos.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Cadastrar', 'sub' => ['Clique em "Novo contrato", preencha e salve — informe o vencimento para gerar alerta.']],
        ['text' => 'Acompanhar', 'sub' => ['Veja os que vencem em 30 dias no resumo e na cor da situação.']],
        ['text' => 'Renovar', 'sub' => ['Antes de vencer, negocie a renovação e atualize o contrato.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Contratos (aqui)</strong> — você cadastra e mantém a vigência.',
        '<strong>Vencimento</strong> — o sistema calcula os dias e sinaliza os que vencem/venceram.',
        '<strong>Ação</strong> — renove a tempo (seguros, locações, financiamentos) evitando lapso de cobertura.',
    ]),
    'dicas' => sf_hlp_ul([
        'Sempre preencha o vencimento — é o que aciona o alerta.',
        'Use o tipo correto para filtrar rápido (ex.: só seguros).',
    ]),
],
'otimizacao' => [
    'title' => 'Otimização Inteligente',
    'visao_geral' => '<p>Sugere a <strong>melhor combinação de veículo e motorista</strong> para a próxima viagem, com base em dados reais: veículo de menor <strong>custo/km</strong> que esteja livre, e motorista de melhor <strong>score</strong> (pontualidade e multas) disponível. Inclui um simulador rápido de custo e lucro para a distância/frete que você informar.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Combinação recomendada</strong> (veículo + motorista) em destaque.',
        '<strong>Ranking de veículos</strong> por custo/km real e disponibilidade.',
        '<strong>Ranking de motoristas</strong> por score e disponibilidade.',
        '<strong>Simulador:</strong> distância + frete → custo estimado e lucro com o veículo recomendado.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Ver a recomendação', 'sub' => ['O topo mostra o melhor veículo e motorista livres.']],
        ['text' => 'Simular a viagem', 'sub' => ['Informe distância e frete para ver custo e lucro estimados.']],
        ['text' => 'Escolher', 'sub' => ['Use os rankings para decidir alternativas; "livre" = sem rota recente.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Histórico real</strong> — custo/km (abastecimento + manutenção ÷ km) e score dos motoristas.',
        '<strong>Otimização (aqui)</strong> — o sistema ranqueia e recomenda a melhor dupla disponível.',
        '<strong>Planejamento</strong> — use a recomendação ao criar a <em>Rota</em>/<em>Ordem de Transporte</em>.',
    ]),
    'dicas' => sf_hlp_ul([
        'O custo/km depende de abastecimentos e manutenções lançados por veículo.',
        '"Livre" considera veículo/motorista sem rota nos últimos 2 dias — ajuste conforme sua operação.',
    ]),
],
'planejamento_carga' => [
    'title' => 'Planejamento de Carga',
    'visao_geral' => '<p>Monte a carga antes de carregar e veja se cabe: selecione o veículo (a capacidade real vem do cadastro), adicione os itens com peso e volume e o sistema mostra o <strong>total, o % da capacidade, o peso médio por eixo e alerta de excesso</strong>. Evita autuação por sobrepeso e viagem mal aproveitada.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Capacidade real</strong> do veículo (kg e nº de eixos) do cadastro.',
        '<strong>Lista de carga</strong> com peso e volume por item.',
        '<strong>Utilização</strong> (% da capacidade) com barra que fica vermelha no excesso.',
        '<strong>Peso médio por eixo</strong> (estimativa) e alerta de sobrepeso.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher o veículo', 'sub' => ['A capacidade e os eixos aparecem automaticamente.']],
        ['text' => 'Adicionar a carga', 'sub' => ['Informe item, peso (kg) e volume (m³) e clique em Adicionar.']],
        ['text' => 'Conferir', 'sub' => ['Veja o % da capacidade; vermelho = excesso, ajuste antes de carregar.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Cadastro</strong> — a capacidade de carga e o nº de eixos vêm do cadastro do veículo.',
        '<strong>Planejamento (aqui)</strong> — você monta a carga e valida peso/eixo antes de carregar.',
        '<strong>Carregamento</strong> — carregue com segurança, dentro do limite legal.',
    ]),
    'dicas' => sf_hlp_ul([
        'Cadastre a capacidade de carga e o nº de eixos dos veículos para o cálculo funcionar.',
        'O peso por eixo aqui é uma média — a distribuição real depende do posicionamento da carga.',
    ]),
],
'patio' => [
    'title' => 'Gestão de Pátio',
    'visao_geral' => '<p>Mapa visual do seu pátio: cada vaga é um cartão colorido por situação (livre, ocupada, manutenção, reservada). Aloque um veículo numa vaga, libere quando sair e veja de relance a ocupação — ótimo para organizar carregamento, descarga e estacionamento.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Vagas por setor</strong> (carregamento, manutenção, etc.), coloridas por situação.',
        '<strong>Resumo</strong> de livres, ocupadas, manutenção e reservadas.',
        '<strong>Alocar/liberar</strong> veículo numa vaga com um clique.',
        '<strong>Criar/excluir</strong> vagas para montar o seu pátio.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Montar o pátio', 'sub' => ['Clique em "Nova vaga", dê um nome (ex.: A1) e um setor.']],
        ['text' => 'Alocar', 'sub' => ['Clique na vaga → escolha o veículo → Alocar (fica azul/ocupada).']],
        ['text' => 'Liberar', 'sub' => ['Clique na vaga ocupada → Liberar (volta a verde/livre).']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Pátio (aqui)</strong> — você cria as vagas e aloca os veículos.',
        '<strong>Operação</strong> — a cor mostra o que está livre/ocupado em tempo real.',
        '<strong>Organização</strong> — filas de carregamento/descarga e localização rápida do veículo.',
    ]),
    'dicas' => sf_hlp_ul([
        'Use setores (Carregamento, Descarga, Manutenção) para agrupar as vagas.',
        'Ao alocar um veículo, ele é automaticamente liberado de qualquer outra vaga.',
    ]),
],
'workflow' => [
    'title' => 'Workflow de Aprovação',
    'visao_geral' => '<p>Aprovações <strong>configuráveis por níveis</strong>: você define a cadeia (ex.: Supervisor → Gerente → Diretor → Financeiro) e cada solicitação sobe nível a nível — só é aprovada quando passa por todos. Toda decisão fica registrada (quem, quando, comentário). Ideal para compras, despesas e liberações que precisam de alçada.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Fluxo configurável:</strong> adicione/remova níveis de aprovação.',
        '<strong>Solicitações</strong> com título, tipo, valor e descrição.',
        '<strong>Aprovar/rejeitar</strong> no nível atual; aprovação avança ao próximo.',
        '<strong>Trilha</strong> de decisões (aprovador e comentário) por solicitação.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Montar o fluxo', 'sub' => ['Adicione os níveis na ordem (ex.: Gerente, Diretor).']],
        ['text' => 'Solicitar', 'sub' => ['Clique em "Nova solicitação", preencha e envie — ela entra no 1º nível.']],
        ['text' => 'Aprovar', 'sub' => ['Cada nível clica Aprovar (avança) ou Rejeitar (encerra).']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Solicitação</strong> — alguém pede algo (compra/despesa) e envia ao workflow.',
        '<strong>Níveis (aqui)</strong> — sobe Supervisor → Gerente → Diretor → Financeiro conforme você configurou.',
        '<strong>Decisão</strong> — aprovada só ao passar por todos; rejeição em qualquer nível encerra.',
    ]),
    'dicas' => sf_hlp_ul([
        'Configure a cadeia de níveis antes de criar solicitações.',
        'Uma rejeição em qualquer nível encerra a solicitação — registre o motivo no comentário.',
    ]),
],
'replay_viagem' => [
    'title' => 'Replay de Viagem',
    'visao_geral' => '<p>Reproduz no mapa o trajeto GPS gravado de um veículo, como um vídeo: dê <strong>play</strong> e acompanhe o percurso ponto a ponto, com velocidade, horário e timeline. Ótimo para conferir uma viagem, investigar um desvio ou uma parada.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Mapa</strong> com o trajeto (linha) e um marcador que percorre o caminho.',
        '<strong>Play/pause</strong>, barra de tempo e controle de velocidade (1x a 25x).',
        '<strong>KPIs:</strong> nº de posições, distância, velocidade máx. e período.',
        '<strong>Filtro por dia</strong> quando há posições de vários dias.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Escolher veículo e dia', 'sub' => ['Selecione o veículo (com GPS) e, se quiser, um dia específico.']],
        ['text' => 'Carregar', 'sub' => ['Clique em "Carregar trajeto" — o percurso aparece no mapa.']],
        ['text' => 'Assistir', 'sub' => ['Dê play; ajuste a velocidade e arraste a barra para pular no tempo.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>App do motorista</strong> — envia as posições GPS durante a viagem.',
        '<strong>Replay (aqui)</strong> — o sistema reconstrói o trajeto gravado no mapa.',
        '<strong>Análise</strong> — investigue desvios, paradas e velocidade da viagem.',
    ]),
    'dicas' => sf_hlp_ul([
        'Só aparecem veículos que enviaram posições GPS (via app do motorista).',
        'Se o veículo ficou parado, o trajeto é um ponto só — normal para veículo ocioso.',
    ]),
],
'digital_twin' => [
    'title' => 'Digital Twin da Frota',
    'visao_geral' => '<p>Um "gêmeo digital" da sua operação: cada veículo aparece como uma entidade viva com o <strong>estado em tempo real</strong> — em movimento, parado ou sem sinal — com posição no mapa, motorista e última atualização. A tela se atualiza sozinha (30s), espelhando o que acontece no mundo real.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Estado ao vivo</strong> de cada veículo (movimento / parado / sem sinal), colorido.',
        '<strong>Mapa</strong> com a posição de todos os veículos com GPS.',
        '<strong>Resumo</strong> da frota (em movimento, parados, sem sinal).',
        '<strong>Atualização automática</strong> a cada 30 segundos.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Acompanhar', 'sub' => ['Deixe a tela aberta — ela reflete a frota em tempo real.']],
        ['text' => 'Localizar', 'sub' => ['Clique no marcador do mapa para ver o veículo e o motorista.']],
        ['text' => 'Agir', 'sub' => ['"Sem sinal" por muito tempo pode indicar app fechado ou veículo desligado.']],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>App do motorista</strong> — envia a posição GPS continuamente.',
        '<strong>Digital Twin (aqui)</strong> — espelha cada veículo com seu estado atual.',
        '<strong>Torre</strong> — complementa o <em>Centro de Operações</em> com a visão viva por ativo.',
    ]),
    'dicas' => sf_hlp_ul([
        'O estado depende do GPS recente do app — sem posição há +2h, o veículo entra como "sem sinal".',
        'Ideal para telão da sala de operações — atualiza sozinho.',
    ]),
],
];
