<?php
/** Gestão de Pneus */
return [
'pneus_dashboard' => [
    'title' => 'Dashboard de Pneus',
    'visao_geral' => '<p>Visão executiva da operação de pneus: estoque, instalados, em manutenção, custo por km, alertas de DOT, sulco, garantia e insights de IA cruzando abastecimento × desgaste.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>KPIs consolidados</strong> de toda a frota de pneus.',
        '<strong>Alertas DOT</strong> (idade) e <strong>sulco mínimo.</strong>',
        '<strong>Custo por km</strong> por marca/modelo.',
        '<strong>Insights IA:</strong> recomendações de troca e recapagem.',
        '<strong>Atalhos</strong> para Gestão Interativa, Estoque e Cadastro.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Monitorar alertas', 'sub' => ['Priorize cards vermelhos (DOT crítico, sulco baixo).', 'Clique para abrir a ficha do pneu.']],
        ['text' => 'Analisar custo', 'sub' => ['Compare custo/km entre marcas.', 'Identifique posições que desgastam mais rápido.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total de pneus</strong> cadastrados.',
        '<strong>Em uso / estoque / manutenção / sucata.</strong>',
        '<strong>Custo médio por km.</strong>',
        '<strong>Pneus com alerta DOT.</strong>',
        '<strong>Pneus abaixo do sulco mínimo.</strong>',
        '<strong>Valor imobilizado</strong> em estoque.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Distribuição por status.</strong>',
        '<strong>Desgaste por posição</strong> (direcional, tracao, carreta).',
        '<strong>Histórico de movimentações</strong> mensais.',
        '<strong>Abastecimento × desgaste</strong> (IA).',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Pneu em condição normal'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Atenção — planejar troca'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Crítico — troca urgente'],
        ['color' => '#6b7280', 'label' => 'Cinza', 'desc' => 'Manutenção ou sucata'],
    ]),
    'dicas' => sf_hlp_ul([
        'Revise o dashboard semanalmente — pneu crítico em carreta afeta segurança e multa.',
        'Cadastre DOT na entrada do pneu para alertas automáticos.',
    ]),
],
'pneus' => [
    'title' => 'Cadastro de Pneus',
    'visao_geral' => '<p>Ficha mestre de cada pneu: número de série, marca, modelo, medida, DOT, sulco, carcaça, filial, tipo de rodado ideal, garantias, fotos e QR Code. Origem de todos os módulos de pneus.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Novo pneu:</strong> dados completos + QR gerado automaticamente.',
        '<strong>Ficha completa:</strong> timeline, movimentações, recapagens, medições de sulco.',
        '<strong>Fotos e anexos</strong> (mercado v2).',
        '<strong>Garantias e sucata.</strong>',
        '<strong>Transferência entre filiais.</strong>',
        '<strong>Filtros</strong> por status, medida, marca, filial.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Cadastrar', 'sub' => ['Informe número de série único.', 'Preencha DOT e sulco inicial.', 'Defina tipo de rodado ideal (direcional/tracao/step).']],
        ['text' => 'Abrir ficha', 'sub' => ['Clique no ícone de ficha ou QR.', 'Registre medições periódicas de sulco.']],
        ['text' => 'Imprimir QR', 'sub' => ['Use na etiqueta física do pneu.', 'Facilita inventário e app motorista.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total cadastrados.</strong>',
        '<strong>Por status</strong> (novo, usado, recapado, sucata).',
        '<strong>Por medida</strong> e marca.',
        '<strong>Idade média</strong> (DOT).',
        '<strong>Sulco médio</strong> da frota.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Distribuição por status e medida.</strong>',
        '<strong>Idade da frota de pneus</strong> (DOT).',
    ]),
    'dicas' => sf_hlp_ul([
        'Nunca duplique número de série — gera conflito no inventário QR.',
        'Registre recapagem na ficha para custo/km correto.',
    ]),
],
'estoque_pneus' => [
    'title' => 'Estoque de Pneus',
    'visao_geral' => '<p>Pneus disponíveis para instalação, organizados por medida, marca, filial e condição. Integra com Gestão Interativa para alocação em veículos.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Lista de disponíveis:</strong> filtros por medida compatível com veículo.',
        '<strong>Entrada em estoque</strong> via cadastro ou devolução.',
        '<strong>Reserva</strong> para manutenção programada.',
        '<strong>KPIs de valor</strong> imobilizado.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Consultar compatível', 'sub' => ['Filtre pela medida do veículo selecionado.', 'Priorize menor sulco entre os adequados (FIFO).']],
        ['text' => 'Alocar', 'sub' => ['Vá à Gestão Interativa ou aloque a partir da rota de manutenção.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Quantidade em estoque.</strong>',
        '<strong>Valor total imobilizado.</strong>',
        '<strong>Medidas críticas</strong> (estoque baixo).',
        '<strong>Pneus sem movimento</strong> há X dias.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Estoque por medida.</strong>',
        '<strong>Entradas e saídas</strong> mensais.',
    ]),
    'dicas' => sf_hlp_ul([
        'Mantenha estoque mínimo por medida dos veículos mais usados.',
        'Pneu antigo no estoque (DOT) deve ser usado primeiro ou descartado.',
    ]),
],
'gestao_interativa' => [
    'title' => 'Gestão Interativa de Pneus',
    'visao_geral' => '<p>Interface visual para gerenciar pneus instalados em cada veículo (cavalo e carreta). Permite alocar, mover com arrastar-e-soltar, remover com motivo, enviar para manutenção, consultar análise IA e abrir a ficha completa do pneu — tudo em um diagrama interativo dos eixos.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Gestão visual:</strong> diagrama do veículo com cada posição de pneu identificada.',
        '<strong>Drag and drop:</strong> arraste pneus entre posições ou para o estoque (validação verde/vermelho).',
        '<strong>Modo flexível:</strong> adicione ou remova eixos e defina quantos pneus há em cada um.',
        '<strong>Menu do pneu:</strong> Análise IA, Ver Detalhes (resumo), Ficha completa, Remover, Enviar para Manutenção.',
        '<strong>Alertas inteligentes:</strong> status crítico, atenção ou bom por quilometragem, DOT e desgaste.',
        '<strong>Histórico e estatísticas:</strong> movimentações registradas e painéis de distribuição por status.',
        '<strong>Validação de posição:</strong> impede instalar direcional em tração ou posição incompatível.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Selecionar veículo', 'sub' => [
            'Escolha o veículo no seletor no topo da página.',
            'O sistema carrega o layout de eixos e os pneus já instalados.',
            'Se o diagrama estiver vazio, configure os eixos no modo flexível.',
        ]],
        ['text' => 'Alocar pneu em posição vazia', 'sub' => [
            'Clique no slot vazio (posição sem pneu).',
            'Escolha <strong>Alocar Pneu</strong> e selecione um pneu do estoque compatível.',
            'Confirme — o pneu passa de estoque para em uso.',
        ]],
        ['text' => 'Mover pneu (arrastar)', 'sub' => [
            'Clique e segure um pneu instalado.',
            'Arraste para outra posição ou para a área de estoque.',
            'Borda verde = movimento permitido; vermelho = inválido (solte para cancelar).',
        ]],
        ['text' => 'Opções ao clicar no pneu', 'sub' => [
            '<strong>Análise IA</strong> — painel com alertas e ações sugeridas.',
            '<strong>Ver Detalhes</strong> — modal central com resumo e IA.',
            '<strong>Ficha completa</strong> — histórico, sulco, fotos, carcaça, garantias.',
            '<strong>Remover Pneu</strong> — informe motivo quando solicitado.',
            '<strong>Enviar para Manutenção</strong> — remove do veículo e abre fluxo de OS.',
        ]],
        ['text' => 'Configurar eixos (modo flexível)', 'sub' => [
            'Use <strong>Adicionar Eixo</strong> no cavalo ou carreta.',
            'Defina a quantidade de pneus por eixo.',
            'Salve o layout — será reutilizado nas próximas visitas ao veículo.',
        ]],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total de veículos</strong> cadastrados com layout de eixos.',
        '<strong>Pneus disponíveis</strong> no estoque (prontos para alocar).',
        '<strong>Pneus em uso</strong> instalados em veículos.',
        '<strong>Em manutenção</strong> — recapagem, conserto ou borracharia.',
        '<strong>Slots vazios</strong> no veículo selecionado (posições sem pneu).',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Distribuição por status</strong> — novo, em uso, estoque, manutenção, sucata.',
        '<strong>Tendência de uso</strong> — movimentações nos últimos meses.',
        '<strong>Indicadores de performance</strong> — custo/km e alertas de sulco quando o painel estiver visível.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#28a745', 'label' => 'Verde', 'desc' => 'Posição vazia disponível ou pneu em bom estado'],
        ['color' => '#007bff', 'label' => 'Azul', 'desc' => 'Pneu instalado em uso normal'],
        ['color' => '#ffc107', 'label' => 'Amarelo', 'desc' => 'Alerta — planejar troca ou monitorar desgaste'],
        ['color' => '#fd7e14', 'label' => 'Laranja', 'desc' => 'Atenção a pressão ou calibração'],
        ['color' => '#dc3545', 'label' => 'Vermelho', 'desc' => 'Crítico — troca urgente'],
        ['color' => '#6c757d', 'label' => 'Cinza', 'desc' => 'Em manutenção ou indisponível'],
    ]),
    'dicas' => sf_hlp_ul([
        'Respeite o tipo de rodado (direcional, tração, estepe) — o sistema bloqueia posição errada.',
        'Faça rodízio periódico e registre na ficha para equilibrar desgaste.',
        'Use filtros para localizar pneus por medida ou status no estoque lateral.',
        'Pneus críticos na Análise IA devem ser tratados antes da próxima viagem.',
        'Mantenha o histórico atualizado — facilita auditoria e custo por km.',
        'Veículos com configuração especial (bitrem, rodotrem) devem usar o modo flexível.',
    ]),
],
'gestao_interativa_pro' => [
    'title' => 'Gestão Interativa',
    'visao_geral' => '<p>Versão premium da gestão de pneus: desenho técnico do veículo em vista superior (cavalo + carreta) com cada pneu clicável, colorido por saúde (sulco/vida). Permite montar os eixos arrastando, alocar pneus do estoque por posição, medir sulco, registrar calibragem, mover/remover/descartar/recapar, além de KPIs, filtros, alertas e histórico — tudo numa só tela.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Estágio premium:</strong> veículo em vista superior com pneus coloridos por saúde e % de vida útil.',
        '<strong>Visões:</strong> alterne entre <em>Tudo</em>, <em>Cavalo</em> e <em>Carreta</em> (a visão é lembrada).',
        '<strong>Montar eixos:</strong> arraste eixos e o tanque para posicionar; salve o layout do veículo.',
        '<strong>Menu Eixos:</strong> adicionar/excluir eixo (simples ou duplo) no caminhão ou carreta.',
        '<strong>Alocar em 2 passos:</strong> escolha o pneu do estoque e a posição (com sugestão de lado).',
        '<strong>Ações do pneu:</strong> medição de sulco, calibragem, trocar posição, recapagem, remover, descartar.',
        '<strong>KPIs clicáveis:</strong> clique num indicador para filtrar os pneus no estágio.',
        '<strong>Alertas:</strong> clique num alerta para focar e selecionar o pneu correspondente.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Selecionar veículo', 'sub' => [
            'Escolha no seletor — o último veículo é lembrado ao reabrir.',
            'O veículo marcado com ★ já tem montagem (posições) salva.',
        ]],
        ['text' => 'Montar / ajustar eixos', 'sub' => [
            'Use o menu <strong>Eixos</strong> para adicionar ou excluir eixos.',
            'Clique em <strong>Montar eixos</strong> e arraste eixos/tanque; depois <strong>Salvar layout</strong>.',
        ]],
        ['text' => 'Alocar um pneu', 'sub' => [
            'Clique numa posição vazia (+) e escolha o pneu do estoque.',
            'Selecione a posição de montagem (a sugerida vem destacada).',
        ]],
        ['text' => 'Gerenciar um pneu instalado', 'sub' => [
            'Clique no pneu para abrir o painel lateral.',
            'Registre <strong>sulco</strong> e <strong>calibragem</strong>, ou use Trocar posição / Recapagem / Remover / Descartar.',
            'Arraste um pneu para uma posição vazia para trocar rapidamente.',
        ]],
        ['text' => 'Filtrar e priorizar', 'sub' => [
            'Use os filtros (status, vida, mais filtros) ou clique num KPI.',
            'Clique num alerta para ir direto ao pneu no estágio.',
        ]],
    ]),
    'fluxo' => sf_hlp_ol([
        '<strong>Estoque</strong> — o pneu entra pelo <em>Estoque / Inventário de pneus</em> (série, medida, valor de compra).',
        '<strong>Gestão Interativa (aqui)</strong> — você monta os eixos e aloca o pneu do estoque na posição do veículo.',
        '<strong>Operação</strong> — o pneu roda; você registra <em>sulco</em> e <em>calibragem</em> aqui, e o motorista também alimenta pela inspeção por QR no app.',
        '<strong>Monitoramento</strong> — sulco baixo vira alerta nesta tela e no <em>Dashboard de Pneus</em>; custo/km alimenta o <em>BI</em>.',
        '<strong>Fim de vida</strong> — recapagem (volta ao ciclo, +1 vida) ou descarte; a <em>Manutenção de Pneus</em> registra a OS (conserto/recapagem).',
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total de pneus</strong> da empresa.',
        '<strong>Em uso</strong> — instalados em veículos.',
        '<strong>Em alerta / Críticos</strong> — por sulco baixo.',
        '<strong>Vida média</strong> — recapagens + 1.',
        '<strong>Custo total</strong> e <strong>Custo / km</strong> (depende do valor de compra cadastrado).',
        '<strong>Sem medição</strong> — pneus sem sulco aferido recentemente.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#94a3b8', 'label' => 'Cinza', 'desc' => 'Posição sem pneu'],
        ['color' => '#22c55e', 'label' => 'Verde', 'desc' => 'Bom / novo'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Atenção — planejar troca'],
        ['color' => '#ef4444', 'label' => 'Vermelho', 'desc' => 'Crítico — troca urgente'],
        ['color' => '#3b82f6', 'label' => 'Azul', 'desc' => 'Em recapagem'],
    ]),
    'dicas' => sf_hlp_ul([
        'O anel colorido em volta do pneu indica a saúde; o chip mostra o número e a % de vida.',
        'Cadastre o valor de compra dos pneus para os KPIs de custo deixarem de exibir “—”.',
        'Salve a montagem do veículo para reabrir sempre com os eixos posicionados.',
        'Registre o sulco periodicamente — pneus “sem medição” entram em alerta.',
    ]),
],
'manutencao_pneus' => [
    'title' => 'Manutenção de Pneus',
    'visao_geral' => '<p>Ordens de serviço específicas de pneus: recapagem, conserto, balanceamento, alinhamento, troca de válvula. Controle de envio e retorno do borracheiro com custos.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Nova OS de pneu:</strong> pneu, serviço, fornecedor, previsão, custo.',
        '<strong>Status:</strong> enviado, em serviço, concluído, cancelado.',
        '<strong>Histórico</strong> na ficha do pneu.',
        '<strong>Vínculo com estoque</strong> ao retornar.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Enviar pneu', 'sub' => ['Na Gestão Interativa ou aqui, crie OS.', 'Pneu sai de "em uso" e vai para manutenção.']],
        ['text' => 'Receber', 'sub' => ['Atualize status Concluído.', 'Registre sulco pós-recapagem e nota fiscal.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>OS abertas.</strong>',
        '<strong>Custo no mês</strong> com borracharia.',
        '<strong>Tempo médio</strong> de retorno.',
        '<strong>Recapagens vs consertos.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Serviços por tipo.</strong>',
        '<strong>Custo mensal</strong> de manutenção de pneus.',
    ]),
    'dicas' => sf_hlp_ul([
        'Compare custo recapagem vs pneu novo na ficha (custo/km).',
        'Exija medição de sulco no retorno para timeline correta.',
    ]),
],
'pneus_inventario' => [
    'title' => 'Inventário QR',
    'visao_geral' => '<p>Conferência física de pneus via leitura de QR Code: sessões de inventário, contagem por filial, divergências (faltante/sobra), auditoria e reconciliação com o sistema.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Nova sessão de inventário</strong> por filial ou pátio.',
        '<strong>Leitura QR</strong> (web ou app motorista).',
        '<strong>Lista de esperados vs lidos.</strong>',
        '<strong>Relatório de divergências.</strong>',
        '<strong>Encerramento</strong> com ajustes sugeridos.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Iniciar sessão', 'sub' => ['Defina filial e responsável.', 'Imprima lista esperada se necessário.']],
        ['text' => 'Escanear', 'sub' => ['Aponte câmera ao QR de cada pneu.', 'Som confirmará leitura válida.']],
        ['text' => 'Fechar', 'sub' => ['Revise faltantes — podem estar montados em veículo.', 'Registre ajustes aprovados.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>% conferido</strong> da sessão ativa.',
        '<strong>Divergências abertas.</strong>',
        '<strong>Pneus lidos / esperados.</strong>',
        '<strong>Tempo da sessão.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Progresso da conferência</strong> (barra).',
        '<strong>Divergências por tipo</strong> (faltante, sobra, local errado).',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Pneu lido e conferido'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Pendente de leitura'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Divergência — não encontrado ou local incorreto'],
    ]),
    'dicas' => sf_hlp_ul([
        'Faça inventário trimestral mínimo — reduz furto e erro de cadastro.',
        'Pneus em veículo rodando podem constar como "faltante" até vincular à placa.',
    ]),
],
];
