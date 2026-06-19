<?php
/** Financeiro */
return [
'contas_pagar' => [
    'title' => 'Contas a Pagar',
    'visao_geral' => '<p>Gestão de obrigações financeiras da empresa: fornecedores, vencimentos, parcelas, categorias, status (aberto, pago, atrasado) e fluxo de caixa projetado.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Nova conta:</strong> fornecedor, valor, vencimento, categoria, centro de custo, anexo de boleto/NF.',
        '<strong>Baixa / pagamento:</strong> data efetiva, conta bancária, juros e desconto.',
        '<strong>Filtros:</strong> período, status, fornecedor, categoria.',
        '<strong>Recorrência</strong> para despesas repetitivas.',
        '<strong>Exportação</strong> e visão de totais por mês.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Lançar título', 'sub' => ['Informe vencimento e valor bruto.', 'Anexe comprovante quando disponível.']],
        ['text' => 'Quitar', 'sub' => ['Selecione a conta em aberto.', 'Registre pagamento com data real.']],
        ['text' => 'Priorizar atrasados', 'sub' => ['Filtre status Atrasado.', 'Ordene por vencimento mais antigo.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total em aberto</strong> (a pagar).',
        '<strong>Vencido:</strong> valor em atraso.',
        '<strong>Pago no mês.</strong>',
        '<strong>Previsão 30/60/90 dias.</strong>',
        '<strong>Por categoria</strong> de despesa.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Fluxo de vencimentos</strong> por semana/mês.',
        '<strong>Despesas por categoria.</strong>',
        '<strong>Top fornecedores</strong> por valor.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Pago'],
        ['color' => '#3b82f6', 'label' => 'Azul', 'desc' => 'Em aberto no prazo'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Vencido / atrasado'],
    ]),
    'dicas' => sf_hlp_ul([
        'Concilie semanalmente com extrato bancário.',
        'Use categorias consistentes para DRE na lucratividade.',
    ]),
],
'despesas_fixas' => [
    'title' => 'Despesas Fixas',
    'visao_geral' => '<p>Cadastro de custos recorrentes: aluguel, seguros, rastreador, salários administrativos rateados, licenças. Base para rateio na lucratividade e planejamento orçamentário.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Nova despesa fixa:</strong> descrição, valor, periodicidade (mensal, anual), categoria.',
        '<strong>Rateio por veículo ou % frota</strong> quando configurado.',
        '<strong>Ativar / inativar</strong> sem apagar histórico.',
        '<strong>Projeção anual</strong> de custo fixo total.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Cadastrar', 'sub' => ['Defina periodicidade correta.', 'Marque se entra no cálculo de lucratividade.']],
        ['text' => 'Revisar anualmente', 'sub' => ['Atualize valores de seguro e contratos.', 'Inative despesas encerradas.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Total mensal</strong> de despesas fixas.',
        '<strong>Total anual projetado.</strong>',
        '<strong>% sobre custo total</strong> da operação.',
        '<strong>Quantidade de itens</strong> ativos.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Composição por categoria.</strong>',
        '<strong>Evolução mensal</strong> do custo fixo.',
    ]),
    'dicas' => sf_hlp_ul([
        'Não duplique despesa fixa e conta a pagar do mesmo contrato.',
        'Seguros e rastreamento devem estar aqui para custo/km realista.',
    ]),
],
'financiamento' => [
    'title' => 'Financiamento',
    'visao_geral' => '<p>Controle de contratos de financiamento e leasing de veículos: parcelas, juros, saldo devedor, vínculo com ativo e impacto no custo da frota.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Novo contrato:</strong> veículo, instituição, valor financiado, taxa, nº parcelas.',
        '<strong>Cronograma de parcelas</strong> automático.',
        '<strong>Status:</strong> ativo, quitado, inadimplente.',
        '<strong>Simulação</strong> de quitação antecipada.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Cadastrar contrato', 'sub' => ['Vincule ao veículo correto.', 'Confira data da primeira parcela.']],
        ['text' => 'Dar baixa em parcela', 'sub' => ['Marque como paga na data do débito.', 'Integre com contas a pagar se aplicável.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Saldo devedor total.</strong>',
        '<strong>Parcela mensal</strong> consolidada.',
        '<strong>Contratos ativos.</strong>',
        '<strong>Custo financeiro</strong> no período.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Amortização</strong> saldo x tempo.',
        '<strong>Parcelas por mês.</strong>',
    ]),
    'dicas' => sf_hlp_ul([
        'Atualize quitação para remover parcelas futuras fantasmas.',
        'Use no cálculo de TCO (custo total de propriedade) do veículo.',
    ]),
],
'multas' => [
    'title' => 'Gestão de Multas',
    'visao_geral' => '<p>Registro de infrações de trânsito por veículo e motorista: auto, valor, pontos, vencimento, recurso, pagamento e responsabilização (empresa ou motorista).</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Nova multa:</strong> placa, data, código infração, valor, órgão autuador.',
        '<strong>Consulta DETRAN:</strong> via WSDenatran por CPF, placa ou CNPJ (certificado em <code>includes/denatran_config.php</code>).',
        '<strong>Vínculo motorista</strong> para desconto em comissão.',
        '<strong>Status:</strong> em aberto, paga, em recurso, cancelada.',
        '<strong>Insights IA:</strong> padrões por rota, motorista ou horário.',
        '<strong>Anexos:</strong> foto do auto e comprovante.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Registrar', 'sub' => ['Informe código e data da infração.', 'Associe motorista se conhecido.']],
        ['text' => 'Cobrar motorista', 'sub' => ['Marque responsabilidade.', 'Integre com comissão quando política da empresa permitir.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Multas no período</strong> e valor total.',
        '<strong>Pontos acumulados</strong> por motorista.',
        '<strong>Em aberto vs pagas.</strong>',
        '<strong>Custo por veículo.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Multas por tipo</strong> de infração.',
        '<strong>Evolução mensal</strong> de ocorrências.',
        '<strong>Ranking motoristas</strong> por pontos.',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Vencida ou gravidade gravíssima'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Prazo de pagamento com desconto próximo'],
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Paga ou cancelada'],
    ]),
    'dicas' => sf_hlp_ul([
        'Cadastre multa cedo para não perder desconto de 40%.',
        'Reincidência no mesmo motorista → treinamento direcionado.',
    ]),
],
'comissoes' => [
    'title' => 'Comissões',
    'visao_geral' => '<p>Cálculo e gestão de comissões de motoristas sobre fretes, com regras por percentual, faixa, adiantamentos e descontos (multas, danos). Fechamento por período.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Regras de comissão:</strong> % sobre frete, tabela por faixa, bônus.',
        '<strong>Fechamento mensal:</strong> consolida rotas concluídas.',
        '<strong>Adiantamentos e descontos.</strong>',
        '<strong>Exportação</strong> para folha/contabilidade.',
        '<strong>Histórico</strong> por motorista.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Configurar regra', 'sub' => ['Defina percentual padrão ou exceções por motorista.', 'Salve antes do fechamento.']],
        ['text' => 'Fechar mês', 'sub' => ['Selecione período.', 'Revise rotas não concluídas excluídas automaticamente.', 'Confirme fechamento.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Comissão total</strong> do período.',
        '<strong>Média por motorista.</strong>',
        '<strong>% comissão / faturamento.</strong>',
        '<strong>Adiantamentos pendentes.</strong>',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Comissão por motorista.</strong>',
        '<strong>Evolução mensal.</strong>',
    ]),
    'dicas' => sf_hlp_ul([
        'Rotas sem frete lançado geram comissão zerada — valide cadastro.',
        'Documente descontos para evitar disputa com motorista.',
    ]),
],
'lucratividade' => [
    'title' => 'Lucratividade',
    'visao_geral' => '<p>Análise de margem da operação: receita (fretes), custos variáveis (combustível, pedágio, comissão) e fixos (despesas rateadas). Visão por rota, veículo, motorista e período. Suporta drill-down e resumo IA.</p>',
    'funcionalidades' => sf_hlp_ul([
        '<strong>Dashboard de margem:</strong> receita, custo e lucro líquido.',
        '<strong>Drill-down:</strong> clique em KPI para detalhar origem do valor.',
        '<strong>Filtros:</strong> data, veículo, motorista, rota.',
        '<strong>Resumo IA:</strong> explicação em linguagem natural dos desvios.',
        '<strong>Exportação</strong> para análise externa.',
        '<strong>Comparativo</strong> período anterior.',
    ]),
    'como_usar' => sf_hlp_ol([
        ['text' => 'Selecionar período', 'sub' => ['Use mês fechado para relatório gerencial.', 'Evite período parcial para decisão estratégica.']],
        ['text' => 'Investigar margem negativa', 'sub' => ['Abra drill-down da rota.', 'Verifique combustível, pedágio e comissão.']],
        ['text' => 'Usar IA', 'sub' => ['Clique em Resumo IA para narrativa dos principais drivers.']],
    ]),
    'kpis' => sf_hlp_ul([
        '<strong>Receita bruta</strong> (fretes).',
        '<strong>Custos variáveis</strong> e <strong>fixos.</strong>',
        '<strong>Lucro líquido</strong> e <strong>margem %.</strong>',
        '<strong>Custo por km</strong> e <strong>receita por km.</strong>',
        '<strong>ROI por veículo</strong> quando disponível.',
    ]),
    'graficos' => sf_hlp_ul([
        '<strong>Waterfall receita → custos → lucro.</strong>',
        '<strong>Margem por rota</strong> (top/bottom).',
        '<strong>Evolução mensal</strong> de lucratividade.',
        '<strong>Composição de custos</strong> (pizza).',
    ]),
    'legenda' => sf_hlp_legenda([
        ['color' => '#16a34a', 'label' => 'Verde', 'desc' => 'Margem acima da meta'],
        ['color' => '#f59e0b', 'label' => 'Amarelo', 'desc' => 'Margem entre 0 e meta'],
        ['color' => '#dc2626', 'label' => 'Vermelho', 'desc' => 'Prejuízo no período'],
    ]),
    'dicas' => sf_hlp_ul([
        'Despesas fixas mal rateadas distorcem veículo lucrativo — revise cadastro.',
        'Compare sempre mesmo número de dias entre períodos.',
        'Use drill-down antes de reajustar tabela de frete.',
    ]),
],
];
